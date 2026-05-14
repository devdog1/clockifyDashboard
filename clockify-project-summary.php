<?php
require_once "clockify-config.php";

$headers = [
    "Content-Type: application/json",
    "X-Api-Key: $apiKey"
];

$cacheDir = __DIR__ . "/cache";
$cacheTTL = 60 * 60 * 12; // 12 hours
$cacheLog = __DIR__ . "/cache/cache.log";

if (!file_exists($cacheDir)) mkdir($cacheDir, 0777, true);

function clockifyGet($url, $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function loadCache($file, $ttl) {
    return (file_exists($file) && (time() - filemtime($file) <= $ttl))
        ? json_decode(file_get_contents($file), true)
        : false;
}

function saveCache($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function logCache($msg) {
    global $cacheLog;
    $time = date("Y-m-d H:i:s");
    file_put_contents($cacheLog, "[$time] $msg\n", FILE_APPEND);
}

/* ------------------------------------------------------------
   Build Fiscal-Year Weeks (same logic as per-user screen)
-------------------------------------------------------------*/
$today = new DateTime("now", new DateTimeZone("UTC"));
$currentYear = (int)$today->format("Y");

$fiscalYearStart = new DateTime("Sept 1 $currentYear");
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
    $fyStartISO = $fiscalYearStart->format("Y-m-d\TH:i:s\Z");
    $fyEndISO   = $fiscalYearEnd->format("Y-m-d\TH:i:s\Z");

    // Load users & projects
    $users = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/users", $headers);
    $projects = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500", $headers);

    $projectNames = [];
    foreach ($projects as $p) $projectNames[$p["id"]] = $p["name"];

    $weeklyResults = [];
    $fyResults = [];

    foreach ($users as $u) {
        $userId = $u["id"];

        // Process Weekly + FY for each user
        foreach (["weekly" => [$startISO, $endISO], "fy" => [$fyStartISO, $fyEndISO]] as $mode => $range) {

            [$rangeStart, $rangeEnd] = $range;

            $page = 1;
            while (true) {
                $entries = clockifyGet(
                    "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
                    "?page-size=200&page=$page&start=$rangeStart&end=$rangeEnd",
                    $headers
                );

                if (!$entries || count($entries) === 0) break;

                foreach ($entries as $e) {
                    if (!isset($e["timeInterval"]["duration"])) continue;
                    $d = new DateInterval($e["timeInterval"]["duration"]);
                    $seconds = ($d->d * 86400) + ($d->h * 3600) + ($d->i * 60) + $d->s;

                    $projectId = $e["projectId"] ?? "NO_PROJECT";
                    $projectLabel = $projectNames[$projectId] ?? "No Project";

                    if ($mode === "weekly") {
                        $weeklyResults[$projectLabel] =
                            ($weeklyResults[$projectLabel] ?? 0) + ($seconds / 3600);
                    } else {
                        $fyResults[$projectLabel] =
                            ($fyResults[$projectLabel] ?? 0) + ($seconds / 3600);
                    }
                }
                $page++;
            }
        }
    }

    saveCache($cacheFile, ["weekly" => $weeklyResults]);
    saveCache($fyCacheFile, ["fy" => $fyResults]);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Clockify Project Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-4">

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
    <p>
        <strong>Week Start:</strong> <?= $weekStart->format("Y-m-d") ?><br>
        <strong>Week End:</strong> <?= $weekEnd->format("Y-m-d") ?><br>
        <strong>Weekly Cache:</strong> <?= basename($cacheFile) ?><br>
        <strong>FY Cache:</strong> <?= basename($fyCacheFile) ?>
    </p>

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
                <?php foreach ($weeklyResults as $project => $hours): ?>
                    <tr>
                        <td><?= htmlspecialchars($project) ?></td>
                        <td><?= number_format($hours, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Fiscal Year Table -->
    <h4 class="mt-5">Fiscal Year Project Summary</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover bg-white">
            <thead class="table-dark">
                <tr>
                    <th>Project</th>
                    <th>Total Hours (FYTD)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fyResults as $project => $hours): ?>
                    <tr>
                        <td><?= htmlspecialchars($project) ?></td>
                        <td><?= number_format($hours, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted mt-3">
        Cache hits/misses logged to <code>cache/cache.log</code>
    </p>

</div>
</body>
</html>

