<?php
//---------------------------------------------------------------
// clockify-project-task-details.php
// Shows all tasks logged under a specific Clockify project
//---------------------------------------------------------------

require_once __DIR__ . '/clockify-config.php'; // Provides $apiKey, $workspaceId

//---------------------------------------------------------------
// Safe duration parser
//---------------------------------------------------------------
function clockifyDurationToHours($duration)
{
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

//---------------------------------------------------------------
// Call Clockify API
//---------------------------------------------------------------
function callClockify($url, $apiKey)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Api-Key: $apiKey"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        error_log("[clockify-project-task-details] API error | " . json_encode([
            'http' => $http,
            'url'  => $url,
            'body' => $resp
        ]));
        return null;
    }

    return json_decode($resp, true);
}

//---------------------------------------------------------------
// Fetch project list
//---------------------------------------------------------------
function getProjects($apiKey, $workspaceId)
{
    $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=5000";
    $data = callClockify($url, $apiKey);
    if (!is_array($data)) return [];

    $out = [];
    foreach ($data as $proj) {
        $out[$proj['id']] = $proj['name'];
    }

    asort($out);
    return $out;
}

//---------------------------------------------------------------
// Fetch user list
//---------------------------------------------------------------
function getUsers($apiKey, $workspaceId)
{
    $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/users?page-size=5000";
    $data = callClockify($url, $apiKey);
    if (!is_array($data)) return [];

    $out = [];
    foreach ($data as $u) {
        $out[$u['id']] = $u['name'];
    }
    return $out;
}

//---------------------------------------------------------------
// Fetch all time entries for selected project
//---------------------------------------------------------------
function getProjectEntries($apiKey, $workspaceId, $projectId, $users)
{
    $entries = [];

    foreach ($users as $userId => $userName) {
        $page = 1;

        while (true) {
            $url =
                "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
                "?project=$projectId&page=$page&page-size=500";

            $data = callClockify($url, $apiKey);
            if (!is_array($data) || empty($data)) break;

            foreach ($data as $entry) {
                $start = $entry['timeInterval']['start'] ?? null;
                $startDate = $start ? substr($start, 0, 10) : '';
                $duration = $entry['timeInterval']['duration'] ?? null;

                $entries[] = [
                    'user'        => $userName,
                    'date'        => $startDate,
                    'hours'       => clockifyDurationToHours($duration),
                    'description' => $entry['description'] ?? '',
                ];
            }

            if (count($data) < 500) break;
            $page++;
        }
    }

    return $entries;
}

//---------------------------------------------------------------
// MAIN
//---------------------------------------------------------------
$projects = getProjects($apiKey, $workspaceId);
$users    = getUsers($apiKey, $workspaceId);

$projectId = $_GET['project_id'] ?? '';
$selectedName = $projectId && isset($projects[$projectId]) ? $projects[$projectId] : '';

$entries = [];
if ($projectId) {
    $entries = getProjectEntries($apiKey, $workspaceId, $projectId, $users);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clockify – Project Task Details</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Select a Project</strong></div>
        <div class="card-body">
            <form method="get" class="row g-3" id="projectForm">

                <div class="col-md-6">
                    <input
                        class="form-control"
                        list="projectList"
                        id="projectText"
                        placeholder="Start typing a project name…"
                        value="<?= htmlspecialchars($selectedName) ?>">

                    <datalist id="projectList">
                        <?php foreach ($projects as $pid => $pname): ?>
                            <option
                                data-id="<?= htmlspecialchars($pid) ?>"
                                value="<?= htmlspecialchars($pname) ?>">
                            </option>
                        <?php endforeach; ?>
                    </datalist>

                    <input type="hidden" name="project_id" id="project_id"
                           value="<?= htmlspecialchars($projectId) ?>">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary w-100">Load</button>
                </div>

            </form>
        </div>
    </div>

    <?php if ($projectId && $selectedName): ?>
        <div class="card shadow-sm">
            <div class="card-header">
                <strong><?= htmlspecialchars($selectedName) ?></strong>
            </div>

            <div class="card-body p-0">

                <?php if (empty($entries)): ?>
                    <div class="p-3 text-center text-muted">
                        No time entries found.
                    </div>
                <?php else: ?>

                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 20%;">User</th>
                            <th style="width: 12%;">Date</th>
                            <th style="width: 8%;">Hours</th>
                            <th>Description</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['user']) ?></td>
                                <td><?= htmlspecialchars($e['date']) ?></td>
                                <td><?= number_format($e['hours'], 2) ?></td>
                                <td><?= htmlspecialchars($e['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    const projects = <?= json_encode($projects) ?>;

    $('#projectText').on('change', function () {
        const name = this.value;
        let matchedId = '';

        for (const [id, pname] of Object.entries(projects)) {
            if (pname === name) {
                matchedId = id;
                break;
            }
        }

        $('#project_id').val(matchedId);
    });
</script>

</body>
</html>

