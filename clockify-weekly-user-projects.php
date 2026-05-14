<?php
require_once "clockify-lib.php";

$periodOptions = getPeriodOptions();

// -------------------------------
// Validate user input
// -------------------------------
$selectedPeriodKey = $_GET['period'] ?? array_key_first($periodOptions['weeks']);
$period = resolveSelectedPeriod($selectedPeriodKey, $periodOptions);

$weekStart = $period["start"];
$weekEnd   = $period["end"];

// -------------------------------
// Load data
// -------------------------------
    $startISO = $weekStart->format("Y-m-d\TH:i:s\Z");
    $endISO   = $weekEnd->format("Y-m-d\TH:i:s\Z");

    // Fetch users and projects
    $users = clockifyGetCached("https://api.clockify.me/api/v1/workspaces/$workspaceId/users");
    $projects = clockifyGetCached("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500");

    $projectNames = [];
    if ($projects) {
        foreach ($projects as $p) $projectNames[$p["id"]] = $p["name"];
    }

    // Process time entries
    $results = [];
    if ($users) {
        foreach ($users as $u) {
            $userId = $u["id"];
            $userName = $u["name"];
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
                }
                $page++;
            }
        }
    }


$pageTitle = "Weekly User Project Hours";
include "header.php";
?>

<div class="my-4">
    <h2 class="mb-4">Weekly Project Hours per User (Fiscal Year)</h2>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-auto">
            <label for="period" class="form-label">Select Period:</label>
            <select name="period" id="period" class="form-select">
                <optgroup label="Financial Years">
                    <?php foreach ($periodOptions['fy'] as $val => $data): ?>
                        <option value="<?= $val ?>" <?= $val == $selectedPeriodKey ? "selected" : "" ?>><?= $data['label'] ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Weekly Reports">
                    <?php foreach ($periodOptions['weeks'] as $val => $data): ?>
                        <option value="<?= $val ?>" <?= $val == $selectedPeriodKey ? "selected" : "" ?>><?= $data['label'] ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary">Load</button>
        </div>
    </form>

    <div class="alert alert-info">
        <strong>Week Start:</strong> <?= $weekStart->format("Y-m-d") ?><br>
        <strong>Week End:</strong> <?= $weekEnd->format("Y-m-d") ?>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover bg-white">
            <thead class="table-secondary">
                <tr>
                    <th>User</th>
                    <th>Project</th>
                    <th>Hours</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="3" class="text-center">No data for this week</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $user => $projects): ?>
                        <?php foreach ($projects as $project => $hours): ?>
                            <tr>
                                <td><?= htmlspecialchars($user) ?></td>
                                <td><?= htmlspecialchars($project) ?></td>
                                <td><?= number_format($hours,2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted mt-3 small"><em>Cache hits/misses logged to <code>cache/cache.log</code></em></p>
</div>

<?php include "footer.php"; ?>
