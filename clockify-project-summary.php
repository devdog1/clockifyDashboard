<?php
require_once "clockify-lib.php";

$periodOptions = getPeriodOptions();

/* ------------------------------------------------------------
   Validate Input & Select Period
-------------------------------------------------------------*/
$selectedPeriodKey = $_GET["period"] ?? array_key_first($periodOptions['weeks']);
$period = resolveSelectedPeriod($selectedPeriodKey, $periodOptions);

$weekStart = $period["start"];
$weekEnd   = $period["end"];

/* ------------------------------------------------------------
   Rebuilding Weekly + FY Summary
-------------------------------------------------------------*/
    $startISO = $weekStart->format("Y-m-d\TH:i:s\Z");
    $endISO   = $weekEnd->format("Y-m-d\TH:i:s\Z");

    // FY window (Current FY for comparison)
    $currentFY = getFiscalYearBoundaries();
    $fyStartISO = $currentFY['start']->format("Y-m-d\TH:i:s\Z");
    $fyEndISO   = $currentFY['end']->format("Y-m-d\TH:i:s\Z");

    // Load users & projects
    $users = clockifyGetCached("https://api.clockify.me/api/v1/workspaces/$workspaceId/users");
    $projects = clockifyGetCached("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500");

    $projectNames = [];
    if ($projects) {
        foreach ($projects as $p) $projectNames[$p["id"]] = $p["name"];
    }

    $weeklyResults = [];
    $fyResults = [];

    if ($users) {
        foreach ($users as $u) {
            $userId = $u["id"];

            // Process Weekly + FY for each user
            foreach (["weekly" => [$startISO, $endISO], "fy" => [$fyStartISO, $fyEndISO]] as $mode => $range) {

                [$rangeStart, $rangeEnd] = $range;

                $page = 1;
                while (true) {
                    $entries = clockifyGetCached(
                        "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
                        "?page-size=200&page=$page&start=$rangeStart&end=$rangeEnd"
                    );

                    if (!$entries || count($entries) === 0) break;

                    foreach ($entries as $e) {
                        if (!isset($e["timeInterval"]["duration"])) continue;
                        $hours = clockifyDurationToHours($e["timeInterval"]["duration"]);

                        $projectId = $e["projectId"] ?? "NO_PROJECT";
                        $projectLabel = $projectNames[$projectId] ?? "No Project";

                        if ($mode === "weekly") {
                            $weeklyResults[$projectLabel] = ($weeklyResults[$projectLabel] ?? 0) + $hours;
                        } else {
                            $fyResults[$projectLabel] = ($fyResults[$projectLabel] ?? 0) + $hours;
                        }
                    }
                    $page++;
                }
            }
        }
    }

$pageTitle = "Clockify Project Summary";
include "header.php";
?>

<div class="my-4">
    <h2 class="mb-4">Project Summary (Selected Period + Current FYTD)</h2>

    <!-- Period Selector -->
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
            <button class="btn btn-primary">Load</button>
        </div>
    </form>

    <!-- Week Info -->
    <div class="alert alert-info">
        <strong>Week Start:</strong> <?= $weekStart->format("Y-m-d") ?><br>
        <strong>Week End:</strong> <?= $weekEnd->format("Y-m-d") ?>
    </div>

    <div class="row">
        <div class="col-md-6">
            <!-- Selected Period Table -->
            <h4 class="mt-4">Project Hours (Selected Period)</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover bg-white">
                    <thead class="table-secondary">
                        <tr>
                            <th>Project</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($weeklyResults)): ?>
                            <tr><td colspan="2" class="text-center">No data for this week</td></tr>
                        <?php else: ?>
                            <?php foreach ($weeklyResults as $project => $hours): ?>
                                <tr>
                                    <td><?= htmlspecialchars($project) ?></td>
                                    <td><?= number_format($hours, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <!-- Fiscal Year Table -->
            <h4 class="mt-4">Fiscal Year Project Summary</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover bg-white">
                    <thead class="table-dark">
                        <tr>
                            <th>Project</th>
                            <th>Total Hours (FYTD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fyResults)): ?>
                            <tr><td colspan="2" class="text-center">No data for this fiscal year</td></tr>
                        <?php else: ?>
                            <?php foreach ($fyResults as $project => $hours): ?>
                                <tr>
                                    <td><?= htmlspecialchars($project) ?></td>
                                    <td><?= number_format($hours, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="text-muted mt-3 small">
        Cache hits/misses logged to <code>cache/cache.log</code>
    </p>
</div>

<?php include "footer.php"; ?>
