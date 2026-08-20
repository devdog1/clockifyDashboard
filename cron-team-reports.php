<?php
/**
 * Automated Team Summary Report Generator
 * Designed to be run via cron (CLI).
 */

require_once __DIR__ . "/clockify-lib.php";

$reportsEnabled = clockify_get_setting('reports_enabled', '1') === '1';
if (!$reportsEnabled) {
    die("Automated reports are currently disabled in settings.\n");
}

$recipientsRaw = clockify_get_setting('recipients', '[]');
$recipients = json_decode($recipientsRaw, true) ?: [];

// Also check legacy reportRecipientEmail if set
if (isset($reportRecipientEmail) && filter_var($reportRecipientEmail, FILTER_VALIDATE_EMAIL) && !in_array($reportRecipientEmail, $recipients)) {
    $recipients[] = $reportRecipientEmail;
}

if (empty($recipients)) {
    die("Error: No recipients configured for automated reports.\n");
}

$teams = clockify_get_teams();
if (empty($teams)) {
    die("Error: No teams defined.\n");
}

// Date Ranges
$lastSunday = new DateTime("last sunday", new DateTimeZone("UTC"));
$lastSunday->setTime(23, 59, 59);

$lastMonday = clone $lastSunday;
$lastMonday->modify("last monday");
$lastMonday->setTime(0, 0, 0);

$fyBoundaries = getFiscalYearBoundaries();
$fyStart = $fyBoundaries['start'];
$fyEnd   = new DateTime("today 23:59:59", new DateTimeZone("UTC"));

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #343a40; color: #fff; }
        .header { background-color: #0d6efd; color: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .section-title { background-color: #e9ecef; padding: 8px 12px; font-weight: bold; margin-top: 20px; border-left: 4px solid #0d6efd; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;">Clockify Automated Team Reports</h1>
        <p style="margin:5px 0 0 0;">Report Generated: <?= date('Y-m-d H:i') ?></p>
    </div>

    <?php foreach ($teams as $team): ?>
        <h2>Team: <?= htmlspecialchars($team['name']) ?></h2>

        <!-- Weekly Summary -->
        <div class="section-title">Last Week Summary (<?= $lastMonday->format('M d, Y') ?> - <?= $lastSunday->format('M d, Y') ?>)</div>
        <?php
        $weekly = getTeamReportData($team, $lastMonday, $lastSunday);
        echo "<h3>Project Totals</h3>";
        renderCronTable($weekly);
        echo "<h3>User Breakdown</h3>";
        renderCronUserTable($weekly);
        ?>

        <!-- FYTD Summary -->
        <div class="section-title">Fiscal Year to Date (<?= $fyStart->format('Y-m-d') ?> - <?= $fyEnd->format('Y-m-d') ?>)</div>
        <?php
        $fytd = getTeamReportData($team, $fyStart, $fyEnd);
        echo "<h3>Project Totals</h3>";
        renderCronTable($fytd);
        echo "<h3>User Breakdown</h3>";
        renderCronUserTable($fytd);
        ?>
        <hr style="margin-top: 30px; margin-bottom: 30px;">
    <?php endforeach; ?>
</body>
</html>
<?php
$htmlContent = ob_get_clean();

function renderCronTable($data) {
    if (empty($data['projectSummary'])) {
        echo "<p><em>No data found for this period.</em></p>";
        return;
    }
    ?>
    <table>
        <thead>
            <tr>
                <th>Project</th>
                <th style="text-align: right; width: 30%;">Total Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['projectSummary'] as $proj => $hours): ?>
                <tr>
                    <td><?= htmlspecialchars($proj) ?></td>
                    <td style="text-align: right;"><strong><?= number_format($hours, 2) ?></strong> hrs</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function renderCronUserTable($data) {
    if (empty($data['results'])) {
        return;
    }
    ?>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Project</th>
                <th style="text-align: right; width: 25%;">Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['results'] as $user => $userProjects): ?>
                <?php
                $first = true;
                $rowCount = count($userProjects);
                foreach ($userProjects as $proj => $hours):
                ?>
                    <tr>
                        <?php if ($first): ?>
                            <td rowspan="<?= $rowCount ?>"><?= htmlspecialchars($user) ?></td>
                            <?php $first = false; ?>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($proj) ?></td>
                        <td style="text-align: right;"><?= number_format($hours, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

$subject = "Clockify Team Reports: " . $lastMonday->format('Y-m-d');
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type:text/html;charset=UTF-8\r\n";
$headers .= "From: Clockify Reports <noreply@" . (gethostname() ?: 'localhost') . ">\r\n";

foreach ($recipients as $email) {
    if (mail($email, $subject, $htmlContent, $headers)) {
        echo "Report sent successfully to $email\n";
    } else {
        echo "Executed report for $email\n";
    }
}
