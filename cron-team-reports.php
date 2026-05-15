<?php
/**
 * Automated Team Summary Report Generator
 * Designed to be run via cron (CLI).
 */

require_once __DIR__ . "/clockify-lib.php";

$settings = loadSettings();
if (!$settings['reports_enabled']) {
    die("Automated reports are currently disabled in settings.\n");
}

$recipients = $settings['recipients'];
if (isset($reportRecipientEmail) && !in_array($reportRecipientEmail, $recipients)) {
    $recipients[] = $reportRecipientEmail;
}

if (empty($recipients)) {
    die("Error: No recipients configured for automated reports.\n");
}

$teamsFile = __DIR__ . '/teams.json';
if (!file_exists($teamsFile)) {
    die("Error: No teams defined in $teamsFile\n");
}
$teams = json_decode(file_get_contents($teamsFile), true) ?: [];

//---------------------------------------------------------------
// Calculate Date Ranges
//---------------------------------------------------------------
// Last Week (Mon-Fri)
$lastMonday = new DateTime("last monday", new DateTimeZone("UTC"));
$lastFriday = clone $lastMonday;
$lastFriday->modify("+4 days");
$lastFriday->setTime(23, 59, 59);

// FYTD (Sept 1 to today)
$fyBoundaries = getFiscalYearBoundaries();
$fyStart = $fyBoundaries['start'];
$fyEnd   = new DateTime("today 23:59:59", new DateTimeZone("UTC"));

//---------------------------------------------------------------
// Generate Report Content
//---------------------------------------------------------------
ob_start();
?>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { background-color: #333; color: white; padding: 10px; margin-bottom: 20px; }
        .section-title { background-color: #eee; padding: 5px; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Clockify Automated Team Reports</h1>
        <p>Report Generated: <?= date('Y-m-d H:i') ?></p>
    </div>

    <?php foreach ($teams as $team): ?>
        <h2>Team: <?= htmlspecialchars($team['name']) ?></h2>

        <!-- Weekly Summary -->
        <div class="section-title">Last Week Summary (<?= $lastMonday->format('M d') ?> - <?= $lastFriday->format('M d') ?>)</div>
        <?php
        $weekly = getTeamReportData($team, $lastMonday, $lastFriday);
        renderTable($weekly);
        ?>

        <!-- FYTD Summary -->
        <div class="section-title">Fiscal Year to Date (<?= $fyStart->format('Y-m-d') ?> - <?= $fyEnd->format('Y-m-d') ?>)</div>
        <?php
        $fytd = getTeamReportData($team, $fyStart, $fyEnd);
        renderTable($fytd);
        ?>
        <hr>
    <?php endforeach; ?>
</body>
</html>
<?php
$htmlContent = ob_get_clean();

function renderTable($data) {
    if (empty($data['projectSummary'])) {
        echo "<p>No data found for this period.</p>";
        return;
    }
    ?>
    <table>
        <thead>
            <tr>
                <th>Project</th>
                <th style="text-align: right;">Total Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['projectSummary'] as $proj => $hours): ?>
                <tr>
                    <td><?= htmlspecialchars($proj) ?></td>
                    <td style="text-align: right;"><?= number_format($hours, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

//---------------------------------------------------------------
// Send Email
//---------------------------------------------------------------
$subject = "Clockify Team Reports: " . $lastMonday->format('Y-m-d');
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: Clockify Reports <noreply@" . gethostname() . ">" . "\r\n";

foreach ($recipients as $email) {
    if (mail($email, $subject, $htmlContent, $headers)) {
        echo "Report sent successfully to $email\n";
    } else {
        echo "Error: Failed to send email to $email\n";
    }
}
