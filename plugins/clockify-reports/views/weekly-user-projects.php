<?php
if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    require_once __DIR__ . '/../clockify-lib.php';
}

$apiKey = clockify_get_setting('api_key', '');
$workspaceId = clockify_get_setting('workspace_id', '');

if (empty($apiKey) || empty($workspaceId)) {
    echo '<div class="alert alert-warning border-start border-4 border-warning shadow-sm my-4">
        <h5><i class="fa-solid fa-triangle-exclamation me-2"></i>Clockify Settings Required</h5>
        <p class="mb-2">Clockify API Key or Workspace ID is missing. Please configure your credentials in the plugin settings to view this report.</p>
        <a href="index.php?route=clockify_settings" class="btn btn-warning text-dark font-weight-bold btn-sm">Configure Clockify Settings</a>
    </div>';
    return;
}

$weekOptions = getFiscalYearWeeks();

$selectedWeek = $_GET['week'] ?? array_key_first($weekOptions);
if (!array_key_exists($selectedWeek, $weekOptions)) {
    $selectedWeek = array_key_first($weekOptions);
}

$weekStart = $weekOptions[$selectedWeek]["start"];
$weekEnd   = $weekOptions[$selectedWeek]["end"];

$cacheFile = "$cacheDir/clockify_week_{$selectedWeek}.json";

$cachedData = loadCache($cacheFile, $cacheTTL);
if ($cachedData !== false) {
    $results = $cachedData["results"];
    logCache("CACHE HIT: $cacheFile");
} else {
    logCache("CACHE MISS: $cacheFile");

    $startISO = $weekStart->format("Y-m-d\TH:i:s\Z");
    $endISO   = $weekEnd->format("Y-m-d\TH:i:s\Z");

    $users = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/users");
    $projects = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500");

    $projectNames = [];
    if ($projects) {
        foreach ($projects as $p) $projectNames[$p["id"]] = $p["name"];
    }

    $results = [];
    if ($users) {
        foreach ($users as $u) {
            $userId = $u["id"];
            $userName = $u["name"];
            $page = 1;

            while (true) {
                $entries = clockifyGet(
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

    saveCache($cacheFile, [
        "results" => $results,
        "weekStart" => $weekStart->format("Y-m-d"),
        "weekEnd"   => $weekEnd->format("Y-m-d")
    ]);
}
?>

<div class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-calendar-week text-primary me-2"></i>Weekly Project Hours per User</h2>
        <a href="index.php?route=clockify_dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <form method="GET" action="index.php" class="row g-3 mb-4 bg-white p-3 rounded border shadow-sm">
        <input type="hidden" name="route" value="clockify_weekly_user_projects">
        <div class="col-auto">
            <label for="week" class="form-label fw-bold">Select Week (Fiscal Year):</label>
            <select name="week" id="week" class="form-select">
                <?php foreach ($weekOptions as $val => $data): ?>
                    <option value="<?= $val ?>" <?= $val == $selectedWeek ? "selected" : "" ?>><?= htmlspecialchars($data['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Load Report</button>
        </div>
    </form>

    <div class="alert alert-info">
        <strong><i class="fa-solid fa-calendar-day me-1"></i>Week Start:</strong> <?= $weekStart->format("Y-m-d") ?> &nbsp;|&nbsp;
        <strong><i class="fa-solid fa-calendar-day me-1"></i>Week End:</strong> <?= $weekEnd->format("Y-m-d") ?>
    </div>

    <div class="table-responsive bg-white rounded border shadow-sm">
        <table class="table table-bordered table-striped table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>User</th>
                    <th>Project</th>
                    <th>Hours Logged</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="3" class="text-center py-4 text-muted">No time log data found for this selected week.</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $user => $projects): ?>
                        <?php foreach ($projects as $project => $hours): ?>
                            <tr>
                                <td><i class="fa-solid fa-user me-2 text-secondary"></i><?= htmlspecialchars($user) ?></td>
                                <td><i class="fa-solid fa-folder me-2 text-primary"></i><?= htmlspecialchars($project) ?></td>
                                <td><strong><?= number_format($hours, 2) ?></strong> hrs</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted mt-3 small"><em>Cache activity logged to <code>cache/cache.log</code></em></p>
</div>
