<?php
require_once "clockify-lib.php";

$weekOptions = getFiscalYearWeeks();

/* ------------------------------------------------------------
   Validate Input & Select Week
-------------------------------------------------------------*/
$selectedWeek = $_GET["week"] ?? array_key_first($weekOptions);
if (!array_key_exists($selectedWeek, $weekOptions)) {
    $selectedWeek = array_key_first($weekOptions);
}

$weekStart = $weekOptions[$selectedWeek]["start"];
$weekEnd   = $weekOptions[$selectedWeek]["end"];

/* ------------------------------------------------------------
   Cache File
-------------------------------------------------------------*/
$cacheFile = "$cacheDir/clockify_project_summary_{$selectedWeek}.json";
$fyCacheFile = "$cacheDir/clockify_project_fy_summary.json";

/* ------------------------------------------------------------
   Load Weekly Cache or Build
-------------------------------------------------------------*/
$cachedWeekly = loadCache($cacheFile, $cacheTTL);
$cachedFY     = loadCache($fyCacheFile, $cacheTTL);

if ($cachedWeekly !== false && $cachedFY !== false) {
    logCache("CACHE HIT: Weekly + FY Summary");
    $weeklyResults = $cachedWeekly["weekly"];
    $fyResults = $cachedFY["fy"];
} else {
    logCache("CACHE MISS: Rebuilding Weekly + FY Summary");

    $startISO = $weekStart->format("Y-m-d\TH:i:s\Z");
    $endISO   = $weekEnd->format("Y-m-d\TH:i:s\Z");

    // FY window
    $fiscalYearStart = reset($weekOptions)["start"]; // Rough estimate for start of FY in options
    $fyStartISO = $fiscalYearStart->format("Y-m-d\TH:i:s\Z");
    $fyEndISO   = end($weekOptions)["end"]->format("Y-m-d\TH:i:s\Z");

    // Load users & projects
    $users = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/users");
    $projects = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500");

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
                    $entries = clockifyGet(
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

    saveCache($cacheFile, ["weekly" => $weeklyResults]);
    saveCache($fyCacheFile, ["fy" => $fyResults]);
}

$pageTitle = "Clockify Project Summary";
include "header.php";
?>

<div class="my-4">
    <h2 class="mb-4">Project Summary (Weekly + Fiscal Year)</h2>

    <!-- Week Selector -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-auto">
            <label for="week" class="form-label">Select Week (Fiscal Year):</label>
            <select name="week" id="week" class="form-select">
                <?php foreach ($weekOptions as $val => $data): ?>
                    <option value="<?= $val ?>" <?= $val === $selectedWeek ? "selected" : "" ?>>
                        <?= $data["label"] ?>
                    </option>
                <?php endforeach; ?>
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
            <!-- Weekly Table -->
            <h4 class="mt-4">Weekly Project Hours</h4>
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
