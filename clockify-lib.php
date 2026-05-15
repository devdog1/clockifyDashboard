<?php
/**
 * Clockify Library
 */

require_once __DIR__ . "/clockify-config.php";

if (!isset($apiKey) || !isset($workspaceId)) {
    die("Please configure clockify-config.php with your API Key and Workspace ID.");
}

$headers = [
    "Content-Type: application/json",
    "X-Api-Key: $apiKey"
];

$cacheDir = __DIR__ . "/cache";
$cacheTTL = 60 * 60 * 12; // 12 hours
$cacheLog = __DIR__ . "/cache/cache.log";

if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

function clockifyGet($url) {
    global $headers;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        return null;
    }

    return json_decode($response, true);
}

function clockifyGetCached($url, $ttl = null) {
    global $cacheDir, $cacheTTL;
    if ($ttl === null) $ttl = $cacheTTL;

    $cacheFile = $cacheDir . "/api_" . md5($url) . ".json";

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) <= $ttl)) {
        logCache("CACHE HIT: $url");
        return json_decode(file_get_contents($cacheFile), true);
    }

    logCache("CACHE MISS: $url");
    $data = clockifyGet($url);
    if ($data !== null) {
        file_put_contents($cacheFile, json_encode($data));
    }
    return $data;
}

function loadCache($file, $ttl) {
    if (file_exists($file) && (time() - filemtime($file) <= $ttl)) {
        return json_decode(file_get_contents($file), true);
    }
    return false;
}

function saveCache($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function logCache($msg) {
    global $cacheLog;
    $time = date("Y-m-d H:i:s");
    file_put_contents($cacheLog, "[$time] $msg\n", FILE_APPEND);
}

function getFiscalYearWeeks() {
    $today = new DateTime("now", new DateTimeZone("UTC"));
    $currentYear = (int)$today->format("Y");

    // Fiscal year: Sept 1 to Aug 31 next year
    // If we are currently before Sept 1, the fiscal year started last year.
    // However, the existing code seems to just use the current year.
    // Let's stick to the logic in the files but make it more robust.

    $fiscalYearStart = new DateTime("Sept 1 " . ($today->format('m') >= 9 ? $currentYear : $currentYear - 1));
    $fiscalYearEnd = clone $fiscalYearStart;
    $fiscalYearEnd->modify("+1 year -1 day");

    $weekOptions = [];
    $weekStart = clone $fiscalYearStart;
    $weekStart->modify("Monday this week");
    $weekNum = 1;

    while ($weekStart <= $fiscalYearEnd) {
        $weekEnd = clone $weekStart;
        $weekEnd->modify("+6 days");
        if ($weekEnd > $fiscalYearEnd) $weekEnd = clone $fiscalYearEnd;

        $label = sprintf(
            "%d-W%02d (%s → %s)",
            (int)$fiscalYearStart->format("Y") + 1,
            $weekNum,
            $weekStart->format("M-d"),
            $weekEnd->format("M-d")
        );

        $value = sprintf("%04d-W%02d",
            (int)$fiscalYearStart->format("Y") + 1,
            $weekNum
        );

        $weekOptions[$value] = [
            "label" => $label,
            "start" => clone $weekStart,
            "end"   => clone $weekEnd
        ];

        $weekNum++;
        $weekStart->modify("+7 days");
    }
    return $weekOptions;
}

function getFiscalYearBoundaries($offset = 0) {
    $today = new DateTime("now", new DateTimeZone("UTC"));
    $currentYear = (int)$today->format("Y");

    $fiscalYearStart = new DateTime("Sept 1 " . ($today->format('m') >= 9 ? $currentYear : $currentYear - 1));
    if ($offset !== 0) {
        $fiscalYearStart->modify("$offset years");
    }

    $fiscalYearEnd = clone $fiscalYearStart;
    $fiscalYearEnd->modify("+1 year -1 day");

    return [
        'start' => $fiscalYearStart,
        'end'   => $fiscalYearEnd,
        'label' => "Financial Year " . $fiscalYearStart->format("Y") . "/" . ($fiscalYearStart->format("y") + 1)
    ];
}

function getPastFiscalYears($count = 5) {
    $years = [];
    for ($i = 0; $i > -$count; $i--) {
        $bound = getFiscalYearBoundaries($i);
        $key = "fy_" . $bound['start']->format("Y");
        $years[$key] = $bound;
    }
    return $years;
}

function getPeriodOptions($fyCount = 5) {
    return [
        'fy'    => getPastFiscalYears($fyCount),
        'weeks' => getFiscalYearWeeks()
    ];
}

function resolveSelectedPeriod($selectedKey, $periodOptions) {
    if (isset($periodOptions['fy'][$selectedKey])) {
        return $periodOptions['fy'][$selectedKey];
    }
    if (isset($periodOptions['weeks'][$selectedKey])) {
        return $periodOptions['weeks'][$selectedKey];
    }
    // Default to current week
    $firstWeekKey = array_key_first($periodOptions['weeks']);
    return $periodOptions['weeks'][$firstWeekKey];
}

function loadSettings() {
    $file = __DIR__ . '/settings.json';
    if (!file_exists($file)) {
        return ['reports_enabled' => true, 'recipients' => []];
    }
    return json_decode(file_get_contents($file), true) ?: ['reports_enabled' => true, 'recipients' => []];
}

function saveSettings($settings) {
    $file = __DIR__ . '/settings.json';
    file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));
}

function getTeamReportData($team, $start, $end) {
    global $workspaceId;
    $results = [];
    $projectSummary = [];

    $startISO = $start->format("Y-m-d\TH:i:s\Z");
    $endISO   = $end->format("Y-m-d\TH:i:s\Z");

    $allUsersData = clockifyGetCached("https://api.clockify.me/api/v1/workspaces/$workspaceId/users");
    $allProjectsData = clockifyGetCached("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500");

    $userNames = [];
    if ($allUsersData) {
        foreach ($allUsersData as $u) $userNames[$u["id"]] = $u["name"];
    }

    $projectNames = [];
    if ($allProjectsData) {
        foreach ($allProjectsData as $p) $projectNames[$p["id"]] = $p["name"];
    }

    foreach ($team['users'] as $userId) {
        $userName = $userNames[$userId] ?? "Unknown ($userId)";
        $page = 1;

        while (true) {
            $entries = clockifyGetCached(
                "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
                "?page-size=200&page=$page&start=$startISO&end=$endISO"
            );
            if (!$entries || count($entries) === 0) break;

            foreach ($entries as $e) {
                if (!isset($e["timeInterval"]["duration"])) continue;
                $hours = clockifyDurationToHours($e["timeInterval"]["duration"]);
                $projectId = $e["projectId"] ?? "NO_PROJECT";
                $projectLabel = $projectNames[$projectId] ?? "No Project";

                $results[$userName][$projectLabel] = ($results[$userName][$projectLabel] ?? 0) + $hours;
                $projectSummary[$projectLabel] = ($projectSummary[$projectLabel] ?? 0) + $hours;
            }
            $page++;
        }
    }

    return [
        'results' => $results,
        'projectSummary' => $projectSummary
    ];
}

function clockifyDurationToHours($duration) {
    if (!$duration) return 0;

    if (is_numeric($duration)) {
        return round(abs((int)$duration) / 3600, 2);
    }

    if (preg_match('/^PT/i', $duration)) {
        try {
            $interval = new DateInterval($duration);
            $seconds =
                ($interval->d * 86400) +
                ($interval->h * 3600) +
                ($interval->i * 60) +
                $interval->s;
            return round($seconds / 3600, 2);
        } catch (Exception $e) {
            return 0;
        }
    }

    return 0;
}
