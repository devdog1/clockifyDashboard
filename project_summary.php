<?php
// ------------------------------------------------------
// project_summary.php
// Displays weekly summaries and fiscal-year totals per project
// Uses the SAME shared cache file as the per-user page
// ------------------------------------------------------

$fiscalYear = '2025';
$cacheFile = __DIR__ . '/cache/weekly_cache.json';

// Load weekly cache
$weeklyData = [];
if (file_exists($cacheFile)) {
    $weeklyData = json_decode(file_get_contents($cacheFile), true);
}

// Determine project ID from URL
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Filter weekly records for this project
$projectWeeks = array_filter($weeklyData, function ($entry) use ($projectId) {
    return $entry['project_id'] == $projectId;
});

// Extract project title
$projectName = "Unknown Project";
if (!empty($projectWeeks)) {
    $projectName = reset($projectWeeks)['project_name'];
}

// Build weekly totals
$weeklyTotals = [];
foreach ($projectWeeks as $row) {
    $week = $row['week'];
    if (!isset($weeklyTotals[$week])) {
        $weeklyTotals[$week] = 0;
    }
    $weeklyTotals[$week] += $row['hours'];
}

// Build fiscal-year per-project summary
$fiscalTotals = 0;
foreach ($projectWeeks as $row) {
    $fiscalTotals += $row['hours'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Summary - <?= htmlspecialchars($projectName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
<div class="container mt-4">

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h3 class="mb-0"><?= htmlspecialchars($projectName) ?></h3>
            <p class="text-muted mb-0">Project Summary for Fiscal Year <?= $fiscalYear ?></p>
        </div>
    </div>

    <!-- Weekly Table ------------------------------------------------------>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Weekly Hours</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Week</th>
                        <th>Total Hours</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($weeklyTotals as $week => $hours): ?>
                    <tr>
                        <td><?= htmlspecialchars($week) ?></td>
                        <td><?= number_format($hours, 2) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($weeklyTotals)): ?>
                    <tr><td colspan="2" class="text-center text-muted">No logged entries</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    <!-- Fiscal Summary ------------------------------------------------------>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Fiscal Year Summary</strong>
        </div>
        <div class="card-body">
            <p class="h4 mb-0"><?= number_format($fiscalTotals, 2) ?> Total Hours</p>
            <span class="text-muted">Across all weeks logged in fiscal <?= $fiscalYear ?></span>
        </div>
    </div>


    <!-- Detailed Weekly Entries --------------------------------------------->
    <div class="card shadow-sm mb-5">
        <div class="card-header">
            <strong>Detailed Weekly Entries</strong>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Week</th>
                        <th>Hours</th>
                        <th>User</th>
                        <th>Description</th>
                        <th>Date Logged</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($projectWeeks as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['week']) ?></td>
                        <td><?= number_format($row['hours'], 2) ?></td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['date_logged']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($projectWeeks)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No entries found for this project</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>

