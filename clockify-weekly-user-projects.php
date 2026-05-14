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

// -------------------------------
// Determine Fiscal-Year-Based Week Options
// -------------------------------
$today = new DateTime("now", new DateTimeZone("UTC"));
$currentYear = (int)$today->format("Y");

// Fiscal year: Sept 1 to Aug 31 next year
$fiscalYearStart = new DateTime("Sept 1 $currentYear");
$fiscalYearEnd = clone $fiscalYearStart;
$fiscalYearEnd->modify("+1 year -1 day");

$weekOptions = [];
$weekNum = 1;
$weekStart = clone $fiscalYearStart;

// Align first week to Monday
$weekStart->modify('Monday this week');

while ($weekStart <= $fiscalYearEnd) {
    $weekEnd = clone $weekStart;
    $weekEnd->modify("+6 days");
    if ($weekEnd > $fiscalYearEnd) $weekEnd = clone $fiscalYearEnd;

    $label = sprintf("%d-W%02d (%s → %s)",
        (int)$fiscalYearStart->format("Y") + 1, // fiscal year label
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

// -------------------------------
// Validate user input
// -------------------------------
$selectedWeek = $_GET['week'] ?? array_key_first($weekOptions);
if (!array_key_exists($selectedWeek, $weekOptions)) {
    $selectedWeek = array_key_first($weekOptions);
}

$weekStart = $weekOptions[$selectedWeek]["start"];
$weekEnd   = $weekOptions[$selectedWeek]["end"];

// Cache filename
$cacheFile = "$cacheDir/clockify_week_{$selectedWeek}.json";

// -------------------------------
// Load cache
// -------------------------------
$cachedData = loadCache($cacheFile, $cacheTTL);
if ($cachedData !== false) {
    $results = $cachedData["results"];
    logCache("CACHE HIT: $cacheFile");
} else {
    logCache("CACHE MISS: $cacheFile");

    $startISO = $weekStart->format("Y-m-d\TH:i:s\Z");
    $endISO   = $weekEnd->format("Y-m-d\TH:i:s\Z");

    // Fetch users and projects
    $users = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/users", $headers);
    $projects = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500", $headers);

    $projectNames = [];
    foreach ($projects as $p) $projectNames[$p["id"]] = $p["name"];

    // Process time entries
    $results = [];
    foreach ($users as $u) {
        $userId = $u["id"];
        $userName = $u["name"];
        $page = 1;

        while (true) {
            $entries = clockifyGet(
                "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
                "?page-size=200&page=$page&start=$startISO&end=$endISO",
                $headers
            );
            if (!$entries || count($entries) === 0) break;

            foreach ($entries as $e) {
                if (!isset($e["timeInterval"]["duration"])) continue;
                $d = new DateInterval($e["timeInterval"]["duration"]);
                $seconds = ($d->d*86400)+($d->h*3600)+($d->i*60)+$d->s;
                $projectId = $e["projectId"] ?? "NO_PROJECT";
                $projectLabel = $projectNames[$projectId] ?? "No Project";

                $results[$userName][$projectLabel] = ($results[$userName][$projectLabel] ?? 0) + $seconds/3600;
            }
            $page++;
        }
    }

    saveCache($cacheFile, [
        "results" => $results,
        "weekStart" => $weekStart->format("Y-m-d"),
        "weekEnd"   => $weekEnd->format("Y-m-d")
    ]);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Weekly User Project Hours</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-4">
    <h2 class="mb-4">Weekly Project Hours per User (Fiscal Year)</h2>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-auto">
            <label for="week" class="form-label">Select Week (Fiscal Year):</label>
            <select name="week" id="week" class="form-select">
                <?php foreach ($weekOptions as $val => $data): ?>
                    <option value="<?= $val ?>" <?= $val == $selectedWeek ? "selected" : "" ?>><?= $data['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary">Load</button>
        </div>
    </form>

    <p>
        <strong>Week Start:</strong> <?= $weekStart->format("Y-m-d") ?><br>
        <strong>Week End:</strong> <?= $weekEnd->format("Y-m-d") ?><br>
        <strong>Cache File:</strong> <?= basename($cacheFile) ?>
    </p>

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
                <?php foreach ($results as $user => $projects): ?>
                    <?php foreach ($projects as $project => $hours): ?>
                        <tr>
                            <td><?= htmlspecialchars($user) ?></td>
                            <td><?= htmlspecialchars($project) ?></td>
                            <td><?= number_format($hours,2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted"><em>Cache hits/misses logged to <code>cache/cache.log</code></em></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

