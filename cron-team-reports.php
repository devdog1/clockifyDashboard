<?php
/**
 * Automated Team Summary Report Wrapper
 */

if (file_exists(__DIR__ . '/plugins/clockify-reports/tasks/cron-team-reports.php')) {
    require __DIR__ . '/plugins/clockify-reports/tasks/cron-team-reports.php';
} else {
    require __DIR__ . '/tasks/cron-team-reports.php';
}
