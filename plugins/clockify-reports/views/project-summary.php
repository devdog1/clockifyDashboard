<?php
if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    require_once __DIR__ . '/../clockify-lib.php';
}

$apiKey = clockify_get_setting('api_key', '');
$workspaceId = clockify_get_setting('workspace_id', '');
$cacheTTL = (int) clockify_get_setting('cache_ttl', 43200);

if (empty($apiKey) || empty($workspaceId)) {
    echo '<div class="alert alert-warning border-start border-4 border-warning shadow-sm my-4">
        <h5><i class="fa-solid fa-triangle-exclamation me-2"></i>Clockify Settings Required</h5>
        <p class="mb-2">Clockify API Key or Workspace ID is missing. Please configure your credentials in the plugin settings to view this report.</p>
        <a href="index.php?route=clockify_settings" class="btn btn-warning text-dark font-weight-bold btn-sm">Configure Clockify Settings</a>
    </div>';
    return;
}

$weekOptions = getFiscalYearWeeks();

$selectedWeek = $_GET["week"] ?? array_key_first($weekOptions);
if (!array_key_exists($selectedWeek, $weekOptions)) {
    $selectedWeek = array_key_first($weekOptions);
}

$weekStart = $weekOptions[$selectedWeek]["start"];
$weekEnd   = $weekOptions[$selectedWeek]["end"];

$cacheKey = "clockify_project_summary_{$selectedWeek}";
$fyCacheKey = "clockify_project_fy_summary";

$cachedWeekly = loadCache($cacheKey, $cacheTTL);
$cachedFY     = loadCache($fyCacheKey, $cacheTTL);

if ($cachedWeekly !== false && $cachedFY !== false) {
    logCache("CACHE HIT: Weekly + FY Summary");
    $weeklyResults = $cachedWeekly["weekly"];
    $fyResults = $cachedFY["fy"];
} else {
    logCache("CACHE MISS: Rebuilding Weekly + FY Summary");

    $startISO = $weekStart->format("Y-m-d\TH:i:s\Z");
    $endISO   = $weekEnd->format("Y-m-d\TH:i:s\Z");

    $fiscalYearStart = reset($weekOptions)["start"];
    $fyStartISO = $fiscalYearStart->format("Y-m-d\TH:i:s\Z");
    $fyEndISO   = end($weekOptions)["end"]->format("Y-m-d\TH:i:s\Z");

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

    saveCache($cacheKey, ["weekly" => $weeklyResults], $cacheTTL);
    saveCache($fyCacheKey, ["fy" => $fyResults], $cacheTTL);
}
?>

<div class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-chart-simple text-primary me-2"></i>Project Summary (Weekly + Fiscal Year)</h2>
        <a href="index.php?route=clockify_dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <form method="GET" action="index.php" class="row g-3 mb-4 bg-white p-3 rounded border shadow-sm">
        <input type="hidden" name="route" value="clockify_project_summary">
        <div class="col-auto">
            <label for="week" class="form-label fw-bold">Select Week (Fiscal Year):</label>
            <select name="week" id="week" class="form-select">
                <?php foreach ($weekOptions as $val => $data): ?>
                    <option value="<?= $val ?>" <?= $val === $selectedWeek ? "selected" : "" ?>>
                        <?= htmlspecialchars($data["label"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Load Report</button>
        </div>
    </form>

    <div class="alert alert-info">
        <strong><i class="fa-solid fa-calendar-day me-1"></i>Week Start:</strong> <?= $weekStart->format("Y-m-d") ?> &nbsp;|&nbsp;
        <strong><i class="fa-solid fa-calendar-day me-1"></i>Week End:</strong> <?= $weekEnd->format("Y-m-d") ?>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-clock me-2"></i>Weekly Project Hours</h5>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Project</th>
                                <th style="width: 30%;">Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($weeklyResults)): ?>
                                <tr><td colspan="2" class="text-center py-3 text-muted">No data recorded for this week.</td></tr>
                            <?php else: ?>
                                <?php foreach ($weeklyResults as $project => $hours): ?>
                                    <tr>
                                        <td><i class="fa-solid fa-folder me-2 text-primary"></i><?= htmlspecialchars($project) ?></td>
                                        <td><strong><?= number_format($hours, 2) ?></strong> hrs</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-chart-pie me-2"></i>Fiscal Year Summary (FYTD)</h5>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Project</th>
                                <th style="width: 30%;">Total Hours (FYTD)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fyResults)): ?>
                                <tr><td colspan="2" class="text-center py-3 text-muted">No data recorded for this fiscal year.</td></tr>
                            <?php else: ?>
                                <?php foreach ($fyResults as $project => $hours): ?>
                                    <tr>
                                        <td><i class="fa-solid fa-folder me-2 text-primary"></i><?= htmlspecialchars($project) ?></td>
                                        <td><strong><?= number_format($hours, 2) ?></strong> hrs</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted mt-2 small"><em>Cache data stored in plugin database table <code>plug_clockify_reports_cache</code></em></p>
</div>
