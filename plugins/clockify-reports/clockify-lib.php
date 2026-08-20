<?php
/**
 * Clockify Library - Plugin Edition with MySQL DB Settings, Caching, and Team Management
 */

if (!function_exists('clockify_get_plugin_db')) {
    function clockify_get_plugin_db() {
        static $pdb = null;
        if ($pdb !== null) {
            return $pdb;
        }

        $pdbPath = null;
        if (file_exists(__DIR__ . '/../../PluginDatabase.php')) {
            $pdbPath = __DIR__ . '/../../PluginDatabase.php';
        } elseif (file_exists(__DIR__ . '/PluginDatabase.php')) {
            $pdbPath = __DIR__ . '/PluginDatabase.php';
        }

        if ($pdbPath && !class_exists('PluginDatabase')) {
            require_once $pdbPath;
        }

        if (class_exists('PluginDatabase')) {
            try {
                $instance = new PluginDatabase('clockify-reports');

                // Settings table
                $instance->createTable('settings', "
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");

                // DB Cache table
                $instance->createTable('cache', "
                    cache_key VARCHAR(150) PRIMARY KEY,
                    cache_data LONGTEXT NOT NULL,
                    expires_at DATETIME NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");

                // Teams table
                $instance->createTable('teams', "
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    members TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");

                $pdb = $instance;
                return $pdb;
            } catch (Exception $e) {
                error_log("Clockify plugin DB init error: " . $e->getMessage());
            }
        }

        $pdb = false;
        return $pdb;
    }
}

if (!function_exists('clockify_install_tables')) {
    function clockify_install_tables() {
        if (!class_exists('PluginDatabase')) {
            $pdbPath = null;
            if (file_exists(__DIR__ . '/../../PluginDatabase.php')) {
                $pdbPath = __DIR__ . '/../../PluginDatabase.php';
            } elseif (file_exists(__DIR__ . '/PluginDatabase.php')) {
                $pdbPath = __DIR__ . '/PluginDatabase.php';
            }
            if ($pdbPath) {
                require_once $pdbPath;
            }
        }

        if (class_exists('PluginDatabase')) {
            try {
                $pdb = new PluginDatabase('clockify-reports');

                $pdb->createTable('settings', "
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");

                $pdb->createTable('cache', "
                    cache_key VARCHAR(150) PRIMARY KEY,
                    cache_data LONGTEXT NOT NULL,
                    expires_at DATETIME NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");

                $pdb->createTable('teams', "
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    members TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");

                if (function_exists('log_action')) {
                    log_action('CLOCKIFY_PLUGIN_TABLES_CREATED', ['plugin' => 'clockify-reports']);
                }
                return true;
            } catch (Exception $e) {
                error_log("Clockify plugin install tables error: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}

if (!function_exists('clockify_uninstall_tables')) {
    function clockify_uninstall_tables() {
        if (!class_exists('PluginDatabase')) {
            $pdbPath = null;
            if (file_exists(__DIR__ . '/../../PluginDatabase.php')) {
                $pdbPath = __DIR__ . '/../../PluginDatabase.php';
            } elseif (file_exists(__DIR__ . '/PluginDatabase.php')) {
                $pdbPath = __DIR__ . '/PluginDatabase.php';
            }
            if ($pdbPath) {
                require_once $pdbPath;
            }
        }

        if (class_exists('PluginDatabase')) {
            try {
                $pdb = new PluginDatabase('clockify-reports');
                $pdb->dropTable('cache');
                $pdb->dropTable('settings');
                $pdb->dropTable('teams');

                if (function_exists('log_action')) {
                    log_action('CLOCKIFY_PLUGIN_TABLES_DROPPED', ['plugin' => 'clockify-reports']);
                }
                return true;
            } catch (Exception $e) {
                error_log("Clockify plugin uninstall tables error: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}

if (!function_exists('clockify_get_setting')) {
    function clockify_get_setting($key, $default = null) {
        $pdb = clockify_get_plugin_db();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('settings');
                $stmt = $pdb->query("SELECT setting_value FROM {$tableName} WHERE setting_key = ?", [$key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['setting_value']) && $row['setting_value'] !== '') {
                    return $row['setting_value'];
                }
            } catch (Exception $e) {
                error_log("Clockify get_setting error for {$key}: " . $e->getMessage());
            }
        }

        // Fallback: check clockify-config.php
        $configFiles = [
            __DIR__ . '/clockify-config.php',
            __DIR__ . '/../../clockify-config.php',
            dirname(__DIR__, 2) . '/clockify-config.php'
        ];
        foreach ($configFiles as $configFile) {
            if (file_exists($configFile)) {
                $apiKey = null;
                $workspaceId = null;
                include $configFile;
                if ($key === 'api_key' && !empty($apiKey) && $apiKey !== '<apikey>') return $apiKey;
                if ($key === 'workspace_id' && !empty($workspaceId) && $workspaceId !== '<workspaceId>') return $workspaceId;
            }
        }

        return $default;
    }
}

if (!function_exists('clockify_set_setting')) {
    function clockify_set_setting($key, $value) {
        $pdb = clockify_get_plugin_db();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('settings');
                $pdb->query("
                    INSERT INTO {$tableName} (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ", [$key, $value, $value]);

                if (function_exists('log_action')) {
                    log_action('CLOCKIFY_SETTING_SAVE', ['key' => $key]);
                }
                return true;
            } catch (Exception $e) {
                error_log("Clockify set_setting error for {$key}: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}

if (!function_exists('clockify_get_teams')) {
    function clockify_get_teams() {
        $pdb = clockify_get_plugin_db();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('teams');
                $stmt = $pdb->query("SELECT id, name, members FROM {$tableName} ORDER BY name ASC");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $teams = [];
                foreach ($rows as $row) {
                    $members = json_decode($row['members'], true) ?: [];
                    $teams[] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'],
                        'users' => $members
                    ];
                }
                if (!empty($teams)) {
                    return $teams;
                }
            } catch (Exception $e) {
                error_log("Clockify get_teams error: " . $e->getMessage());
            }
        }

        $teamsFile = __DIR__ . '/teams.json';
        if (file_exists($teamsFile)) {
            $json = json_decode(file_get_contents($teamsFile), true);
            if (is_array($json)) return $json;
        }

        return [];
    }
}

if (!function_exists('clockify_save_teams')) {
    function clockify_save_teams($teams) {
        $pdb = clockify_get_plugin_db();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('teams');
                $pdb->query("DELETE FROM {$tableName}");
                foreach ($teams as $t) {
                    $membersJson = json_encode($t['users'] ?? []);
                    $pdb->query("
                        INSERT INTO {$tableName} (name, members)
                        VALUES (?, ?)
                    ", [$t['name'], $membersJson]);
                }
                if (function_exists('log_action')) {
                    log_action('CLOCKIFY_TEAMS_SAVE', ['count' => count($teams)]);
                }
                return true;
            } catch (Exception $e) {
                error_log("Clockify save_teams error: " . $e->getMessage());
            }
        }

        $teamsFile = __DIR__ . '/teams.json';
        @file_put_contents($teamsFile, json_encode($teams, JSON_PRETTY_PRINT));
        return true;
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
        if ($ttl === null) {
            $ttl = (int) clockify_get_setting('cache_ttl', 60 * 60 * 12);
        }
        $pdb = clockify_get_plugin_db();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('cache');
                $stmt = $pdb->query("SELECT cache_data, expires_at FROM {$tableName} WHERE cache_key = ?", [$key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if (strtotime($row['expires_at']) > time()) {
                        return json_decode($row['cache_data'], true);
                    }
                }
            } catch (Exception $e) {
                error_log("Clockify loadCache error for key {$key}: " . $e->getMessage());
            }
        }
        return false;
    }
}

if (!function_exists('saveCache')) {
    function saveCache($key, $data, $ttl = null) {
        if ($ttl === null) {
            $ttl = (int) clockify_get_setting('cache_ttl', 60 * 60 * 12);
        }
        $pdb = clockify_get_plugin_db();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('cache');
                $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $expiresAt = date('Y-m-d H:i:s', time() + (int)$ttl);

                $pdb->query("
                    INSERT INTO {$tableName} (cache_key, cache_data, expires_at)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE cache_data = ?, expires_at = ?
                ", [$key, $jsonData, $expiresAt, $jsonData, $expiresAt]);
                return true;
            } catch (Exception $e) {
                error_log("Clockify saveCache error for key {$key}: " . $e->getMessage());
            }
        }
        return false;
    }
}

if (!function_exists('clearClockifyCache')) {
    function clearClockifyCache() {
        $pdb = clockify_get_plugin_db();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('cache');
                $pdb->query("DELETE FROM {$tableName}");
                return true;
            } catch (Exception $e) {
                error_log("Clockify clearClockifyCache error: " . $e->getMessage());
            }
        }
        return false;
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
