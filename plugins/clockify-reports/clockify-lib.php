<?php
/**
 * Clockify Library - Plugin Edition with Models, DB Settings, Caching, and Team Management
 */

if (file_exists(__DIR__ . '/models/ClockifyModel.php')) {
    require_once __DIR__ . '/models/ClockifyModel.php';
}

if (!function_exists('clockify_get_plugin_db')) {
    function clockify_get_plugin_db() {
        return ClockifyModel::getPluginDb();
    }
}

if (!function_exists('clockify_install_tables')) {
    function clockify_install_tables() {
        return ClockifyModel::installTables();
    }
}

if (!function_exists('clockify_uninstall_tables')) {
    function clockify_uninstall_tables() {
        return ClockifyModel::uninstallTables();
    }
}

if (!function_exists('clockify_get_setting')) {
    function clockify_get_setting($key, $default = null) {
        return ClockifyModel::getSetting($key, $default);
    }
}

if (!function_exists('clockify_set_setting')) {
    function clockify_set_setting($key, $value) {
        return ClockifyModel::setSetting($key, $value);
    }
}

if (!function_exists('clockify_get_teams')) {
    function clockify_get_teams() {
        return ClockifyModel::getTeams();
    }
}

if (!function_exists('clockify_save_teams')) {
    function clockify_save_teams($teams) {
        return ClockifyModel::saveTeams($teams);
    }
}

if (!function_exists('getClockifyApiKey')) {
    function getClockifyApiKey() {
        return clockify_get_setting('api_key', '');
    }
}

if (!function_exists('getClockifyWorkspaceId')) {
    function getClockifyWorkspaceId() {
        return clockify_get_setting('workspace_id', '');
    }
}

$cacheTTL = (int) clockify_get_setting('cache_ttl', 60 * 60 * 12);

if (!function_exists('clockifyGet')) {
    function clockifyGet($url) {
        $apiKey = getClockifyApiKey();
        if (empty($apiKey)) {
            return null;
        }

        $headers = [
            "Content-Type: application/json",
            "X-Api-Key: $apiKey"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($response, true);
    }
}

if (!function_exists('loadCache')) {
    function loadCache($key, $ttl = null) {
        return ClockifyModel::loadCache($key, $ttl);
    }
}

if (!function_exists('saveCache')) {
    function saveCache($key, $data, $ttl = null) {
        return ClockifyModel::saveCache($key, $data, $ttl);
    }
}

if (!function_exists('clearClockifyCache')) {
    function clearClockifyCache() {
        return ClockifyModel::clearCache();
    }
}

if (!function_exists('logCache')) {
    function logCache($msg) {
        if (function_exists('log_action')) {
            log_action('CLOCKIFY_CACHE_EVENT', ['message' => $msg]);
        } else {
            error_log("[Clockify Cache] " . $msg);
        }
    }
}

if (!function_exists('getFiscalYearWeeks')) {
    function getFiscalYearWeeks() {
        $today = new DateTime("now", new DateTimeZone("UTC"));
        $currentYear = (int)$today->format("Y");

        $fiscalYearStart = new DateTime("Sept 1 " . ($today->format('m') >= 9 ? $currentYear : $currentYear - 1));
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
        return $weekOptions;
    }
}

if (!function_exists('getFiscalYearBoundaries')) {
    function getFiscalYearBoundaries() {
        $today = new DateTime("now", new DateTimeZone("UTC"));
        $currentYear = (int)$today->format("Y");
        $startYear = ($today->format('m') >= 9) ? $currentYear : $currentYear - 1;
        $fyStart = new DateTime("Sept 1 {$startYear} 00:00:00", new DateTimeZone("UTC"));
        $fyEnd = clone $fyStart;
        $fyEnd->modify("+1 year -1 second");

        return ['start' => $fyStart, 'end' => $fyEnd];
    }
}

if (!function_exists('getTeamReportData')) {
    function getTeamReportData($team, DateTime $start, DateTime $end) {
        $workspaceId = getClockifyWorkspaceId();
        if (empty($workspaceId)) return ['projectSummary' => [], 'results' => []];

        $startISO = $start->format("Y-m-d\TH:i:s\Z");
        $endISO   = $end->format("Y-m-d\TH:i:s\Z");

        $projects = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=500");
        $projectNames = [];
        if (is_array($projects)) {
            foreach ($projects as $p) $projectNames[$p['id']] = $p['name'];
        }

        $allUsers = clockifyGet("https://api.clockify.me/api/v1/workspaces/$workspaceId/users?page-size=5000");
        $userMap = [];
        if (is_array($allUsers)) {
            foreach ($allUsers as $u) $userMap[$u['id']] = $u['name'];
        }

        $teamUserIds = $team['users'] ?? [];
        $projectSummary = [];
        $results = [];

        foreach ($teamUserIds as $userId) {
            $userName = $userMap[$userId] ?? $userId;
            $page = 1;

            while (true) {
                $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
                       "?page-size=200&page=$page&start=$startISO&end=$endISO";
                $entries = clockifyGet($url);
                if (!is_array($entries) || empty($entries)) break;

                foreach ($entries as $e) {
                    if (!isset($e['timeInterval']['duration'])) continue;
                    $hours = clockifyDurationToHours($e['timeInterval']['duration']);
                    $projectId = $e['projectId'] ?? 'NO_PROJECT';
                    $projectLabel = $projectNames[$projectId] ?? 'No Project';

                    $projectSummary[$projectLabel] = ($projectSummary[$projectLabel] ?? 0) + $hours;
                    $results[$userName][$projectLabel] = ($results[$userName][$projectLabel] ?? 0) + $hours;
                }
                $page++;
            }
        }

        arsort($projectSummary);
        return [
            'projectSummary' => $projectSummary,
            'results'        => $results
        ];
    }
}

if (!function_exists('clockifyDurationToHours')) {
    function clockifyDurationToHours($duration) {
        if (!$duration) return 0;

        if (is_numeric($duration)) {
            return round(abs((int)$duration) / 3600, 2);
        }

        if (preg_match('/^PT/i', $duration)) {
            try {
                $interval = new DateInterval($duration);
                $seconds =
                    ($interval->d * 86400) +
                    ($interval->h * 3600) +
                    ($interval->i * 60) +
                    $interval->s;
                return round($seconds / 3600, 2);
            } catch (Exception $e) {
                return 0;
            }
        }

        return 0;
    }
}
