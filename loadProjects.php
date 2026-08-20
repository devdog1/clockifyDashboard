<?php

/**
 * Clockify Project Import Script
 *
 * Features:
 * - Uses plugin database settings or external configuration file
 * - Auto-creates missing clients
 * - Supports --dry-run CLI flag
 * - Emits structured JSON logs for audit/change management
 * - Idempotent (safe to re-run)
 */

/* =======================
 * Load Configuration / DB Settings
 * ======================= */
require_once __DIR__ . '/clockify-lib.php';

$apiKey      = getClockifyApiKey();
$workspaceId = getClockifyWorkspaceId();

if (empty($apiKey) || empty($workspaceId)) {
    fwrite(STDERR, "API key or Workspace ID not set in plugin settings or config file\n");
    exit(1);
}

$apiBase = 'https://api.clockify.me/api/v1';

/* =======================
 * CLI Arguments
 * ======================= */
$options = getopt('', ['file:', 'dry-run']);

if (!isset($options['file'])) {
    fwrite(STDERR, "Usage: php loadProjects.php --file=projects.txt [--dry-run]\n");
    exit(1);
}

$inputFile = $options['file'];
$dryRun    = isset($options['dry-run']);

/* =======================
 * Constants
 * ======================= */
$requiredClients = ['Projects', 'CAPEX'];

/* =======================
 * Structured Logging
 * ======================= */
function logEvent(array $event): void
{
    $event['timestamp'] = gmdate('c');
    echo json_encode($event, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

/* =======================
 * HTTP Helper
 * ======================= */
function clockifyRequest(string $method, string $url, string $apiKey, array $payload = null)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'X-Api-Key: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payload ? json_encode($payload) : null,
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        throw new RuntimeException(curl_error($ch));
    }

    curl_close($ch);

    if ($status >= 300) {
        throw new RuntimeException("HTTP {$status}: {$response}");
    }

    return json_decode($response, true);
}

/* =======================
 * Fetch Existing Clients
 * ======================= */
$clients = [];

$clientList = clockifyRequest(
    'GET',
    "$apiBase/workspaces/$workspaceId/clients",
    $apiKey
);

foreach ($clientList as $client) {
    $clients[$client['name']] = $client['id'];
}

/* =======================
 * Auto-Create Missing Clients
 * ======================= */
foreach ($requiredClients as $clientName) {

    if (!isset($clients[$clientName])) {

        logEvent([
            'action' => 'client.missing',
            'client' => $clientName,
            'mode'   => $dryRun ? 'dry-run' : 'apply',
        ]);

        if (!$dryRun) {
            $client = clockifyRequest(
                'POST',
                "$apiBase/workspaces/$workspaceId/clients",
                $apiKey,
                ['name' => $clientName]
            );

            $clients[$clientName] = $client['id'];

            logEvent([
                'action' => 'client.create',
                'client' => $clientName,
                'result' => 'created',
            ]);
        }
    }
}

/* =======================
 * Fetch Existing Projects
 * ======================= */
$existingProjects = [];
$page = 1;

do {
    $projects = clockifyRequest(
        'GET',
        "$apiBase/workspaces/$workspaceId/projects?page=$page&page-size=200",
        $apiKey
    );

    foreach ($projects as $project) {
        $existingProjects[strtolower(trim($project['name']))] = true;
    }

    $page++;

} while (count($projects) === 200);

/* =======================
 * Process Input File
 * ======================= */
$lines = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $projectName) {

    $projectName = trim($projectName);
    $projectKey  = strtolower($projectName);

    if (isset($existingProjects[$projectKey])) {
        logEvent([
            'action'  => 'project.skip',
            'project' => $projectName,
            'reason'  => 'already-exists',
        ]);
        continue;
    }

    if (preg_match('/^\d{5}/', $projectName)) {
        $clientName = 'Projects';
    } elseif (stripos($projectName, 'CAPEX') === 0) {
        $clientName = 'CAPEX';
    } else {
        logEvent([
            'action'  => 'project.skip',
            'project' => $projectName,
            'reason'  => 'no-matching-rule',
        ]);
        continue;
    }

    logEvent([
        'action'  => 'project.evaluate',
        'project' => $projectName,
        'client'  => $clientName,
        'mode'    => $dryRun ? 'dry-run' : 'apply',
    ]);

    if ($dryRun) {
        continue;
    }

    clockifyRequest(
        'POST',
        "$apiBase/workspaces/$workspaceId/projects",
        $apiKey,
        [
            'name'      => $projectName,
            'clientId' => $clients[$clientName],
            'isPublic' => false,
        ]
    );

    $existingProjects[$projectKey] = true;

    logEvent([
        'action'  => 'project.create',
        'project' => $projectName,
        'client'  => $clientName,
        'result'  => 'created',
    ]);
}

logEvent([
    'action' => 'run.complete',
    'mode'   => $dryRun ? 'dry-run' : 'apply',
]);
