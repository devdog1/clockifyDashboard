<?php
require_once "clockify-lib.php";

$teamsFile = __DIR__ . '/teams.json';
$weekOptions = getFiscalYearWeeks();
$fyOptions = getPastFiscalYears(5);

function loadTeams($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

//---------------------------------------------------------------
// Validate Input
//---------------------------------------------------------------
$teams = loadTeams($teamsFile);
$selectedTeamIndex = isset($_GET['team_index']) && isset($teams[$_GET['team_index']]) ? (int)$_GET['team_index'] : -1;
$selectedWeek = $_GET['week'] ?? array_key_first($weekOptions);
if (!array_key_exists($selectedWeek, $fyOptions) && !array_key_exists($selectedWeek, $weekOptions)) {
    $selectedWeek = array_key_first($weekOptions);
}

if (array_key_exists($selectedWeek, $fyOptions)) {
    $weekStart = $fyOptions[$selectedWeek]['start'];
    $weekEnd   = $fyOptions[$selectedWeek]['end'];
    $periodLabel = $fyOptions[$selectedWeek]['label'];
} else {
    $weekStart = $weekOptions[$selectedWeek]["start"];
    $weekEnd   = $weekOptions[$selectedWeek]["end"];
    $periodLabel = "Week: " . $weekOptions[$selectedWeek]['label'];
}

$results = [];
$projectSummary = [];

if ($selectedTeamIndex !== -1) {
    $team = $teams[$selectedTeamIndex];

    $startISO = $weekStart->format("Y-m-d\TH:i:s\Z");
    $endISO   = $weekEnd->format("Y-m-d\TH:i:s\Z");

    // Fetch users and projects
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
}

$pageTitle = "Team Summary Report";
include "header.php";
?>

<div class="my-4">
    <h2 class="mb-4">Team Summary Report</h2>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="team_index" class="form-label">Select Team</label>
                    <select name="team_index" id="team_index" class="form-select">
                        <option value="">-- Select a Team --</option>
                        <?php foreach ($teams as $idx => $t): ?>
                            <option value="<?= $idx ?>" <?= $idx === $selectedTeamIndex ? "selected" : "" ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="week" class="form-label">Select Period</label>
                    <select name="week" id="week" class="form-select">
                        <optgroup label="Financial Years">
                            <?php foreach ($fyOptions as $val => $data): ?>
                                <option value="<?= $val ?>" <?= $val == $selectedWeek ? "selected" : "" ?>>
                                    <?= $data['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Weekly Reports">
                            <?php foreach ($weekOptions as $val => $data): ?>
                                <option value="<?= $val ?>" <?= $val == $selectedWeek ? "selected" : "" ?>>
                                    <?= $data['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-4 align-self-end">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedTeamIndex !== -1): ?>
        <div class="alert alert-info">
            <strong>Team:</strong> <?= htmlspecialchars($teams[$selectedTeamIndex]['name']) ?><br>
            <strong>Period:</strong> <?= $periodLabel ?><br>
            <strong>Range:</strong> <?= $weekStart->format("Y-m-d") ?> to <?= $weekEnd->format("Y-m-d") ?>
        </div>

        <div class="row">
            <!-- Project Aggregation -->
            <div class="col-md-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white"><strong>Team Project Summary</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th class="text-end">Total Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($projectSummary)): ?>
                                    <tr><td colspan="2" class="text-center">No data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($projectSummary as $proj => $hours): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($proj) ?></td>
                                            <td class="text-end"><?= number_format($hours, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Per User / Per Project -->
            <div class="col-md-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white"><strong>Per User Breakdown</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Project</th>
                                    <th class="text-end">Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($results)): ?>
                                    <tr><td colspan="3" class="text-center">No data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($results as $user => $userProjects): ?>
                                        <?php
                                        $first = true;
                                        $rowCount = count($userProjects);
                                        foreach ($userProjects as $proj => $hours):
                                        ?>
                                            <tr>
                                                <?php if ($first): ?>
                                                    <td rowspan="<?= $rowCount ?>" class="fw-bold border-end"><?= htmlspecialchars($user) ?></td>
                                                    <?php $first = false; ?>
                                                <?php endif; ?>
                                                <td><?= htmlspecialchars($proj) ?></td>
                                                <td class="text-end"><?= number_format($hours, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
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
