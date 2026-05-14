<?php
require_once "clockify-lib.php";

//---------------------------------------------------------------
// Fetch user list
//---------------------------------------------------------------
function getUsers()
{
    global $workspaceId;
    $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/users?page-size=5000";
    $data = clockifyGetCached($url);
    if (!is_array($data)) return [];

    $out = [];
    foreach ($data as $u) {
        $out[$u['id']] = $u['name'];
    }
    asort($out);
    return $out;
}

//---------------------------------------------------------------
// Fetch projects
//---------------------------------------------------------------
function getProjects()
{
    global $workspaceId;
    $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=5000";
    $data = clockifyGetCached($url);
    if (!is_array($data)) return [];

    $out = [];
    foreach ($data as $proj) {
        $out[$proj['id']] = $proj['name'];
    }
    return $out;
}

//---------------------------------------------------------------
// Fetch all time entries for selected user
//---------------------------------------------------------------
function getUserEntries($userId, $projectNames)
{
    global $workspaceId;
    $entries = [];

    $page = 1;
    while (true) {
        $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries?page=$page&page-size=500";
        $data = clockifyGetCached($url);
        if (!is_array($data) || empty($data)) break;

        foreach ($data as $entry) {
            $projectId = $entry['projectId'] ?? 'NO_PROJECT';
            $projectName = $projectNames[$projectId] ?? 'No Project';
            $start = $entry['timeInterval']['start'] ?? null;
            $startDate = $start ? substr($start, 0, 10) : '';
            $duration = $entry['timeInterval']['duration'] ?? null;
            $hours = clockifyDurationToHours($duration);

            $entries[] = [
                'project_id'   => $projectId,
                'project_name' => $projectName,
                'date'         => $startDate,
                'hours'        => $hours,
                'description'  => $entry['description'] ?? '',
            ];
        }

        if (count($data) < 500) break;
        $page++;
    }

    return $entries;
}

//---------------------------------------------------------------
// MAIN
//---------------------------------------------------------------
$users = getUsers();
$projectNames = getProjects();

$userId = $_GET['user_id'] ?? '';
$selectedUserName = $userId && isset($users[$userId]) ? $users[$userId] : '';

$entries = [];
$projectContributions = [];
$weeklySummary = [];

if ($userId) {
    $entries = getUserEntries($userId, $projectNames);

    // Aggregate by project and week
    foreach ($entries as $e) {
        // Project aggregation
        $pName = $e['project_name'];
        if (!isset($projectContributions[$pName])) {
            $projectContributions[$pName] = 0;
        }
        $projectContributions[$pName] += $e['hours'];

        // Weekly aggregation
        if ($e['date']) {
            $date = new DateTime($e['date']);
            $weekYear = $date->format('o'); // ISO-8601 year
            $weekNum = $date->format('W');
            $weekKey = "$weekYear-W$weekNum";

            if (!isset($weeklySummary[$weekKey])) {
                $weeklySummary[$weekKey] = 0;
            }
            $weeklySummary[$weekKey] += $e['hours'];
        }
    }
    arsort($projectContributions);
    krsort($weeklySummary); // Sort by week descending
}

$pageTitle = "Clockify User Details";
include "header.php";
?>

<div class="my-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><strong>Select a User</strong></div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-9">
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">-- Select a User --</option>
                        <?php foreach ($users as $uid => $uname): ?>
                            <option value="<?= htmlspecialchars($uid) ?>" <?= $uid === $userId ? "selected" : "" ?>>
                                <?= htmlspecialchars($uname) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">Load User Details</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($userId && $selectedUserName): ?>
        <h2 class="mb-4">Details for: <?= htmlspecialchars($selectedUserName) ?></h2>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white"><strong>Project Contributions</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th class="text-end">Total Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($projectContributions)): ?>
                                    <tr><td colspan="2" class="text-center">No contributions found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($projectContributions as $pName => $hours): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($pName) ?></td>
                                            <td class="text-end"><?= number_format($hours, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white"><strong>Weekly Summary</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Week</th>
                                    <th class="text-end">Total Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($weeklySummary)): ?>
                                    <tr><td colspan="2" class="text-center">No weekly data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($weeklySummary as $week => $hours): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($week) ?></td>
                                            <td class="text-end"><?= number_format($hours, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white"><strong>Individual Task Entries</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20%;">Date</th>
                                    <th style="width: 25%;">Project</th>
                                    <th style="width: 10%;">Hours</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($entries)): ?>
                                    <tr><td colspan="4" class="text-center">No tasks found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($entries as $e): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($e['date']) ?></td>
                                            <td><?= htmlspecialchars($e['project_name']) ?></td>
                                            <td><?= number_format($e['hours'], 2) ?></td>
                                            <td><?= htmlspecialchars($e['description']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>
