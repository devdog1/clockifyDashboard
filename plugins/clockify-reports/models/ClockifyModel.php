<?php
/**
 * Clockify Model - Handles DB tables, Settings, Caching, Team CRUD, and Role/Permission Management
 */

class ClockifyModel {
    private static $pdb = null;

    public static function getPluginDb() {
        if (self::$pdb !== null) {
            return self::$pdb;
        }

        $pdbPath = null;
        if (file_exists(__DIR__ . '/../../../PluginDatabase.php')) {
            $pdbPath = __DIR__ . '/../../../PluginDatabase.php';
        } elseif (file_exists(__DIR__ . '/../../PluginDatabase.php')) {
            $pdbPath = __DIR__ . '/../../PluginDatabase.php';
        } elseif (file_exists(__DIR__ . '/../PluginDatabase.php')) {
            $pdbPath = __DIR__ . '/../PluginDatabase.php';
        }

        if ($pdbPath && !class_exists('PluginDatabase')) {
            require_once $pdbPath;
        }

        if (class_exists('PluginDatabase')) {
            try {
                $instance = new PluginDatabase('clockify-reports');
                self::$pdb = $instance;
                return self::$pdb;
            } catch (Exception $e) {
                // Return false if database connection unavailable
            }
        }

        self::$pdb = false;
        return self::$pdb;
    }

    public static function getCoreDb() {
        if (function_exists('get_db_connection')) {
            try {
                return get_db_connection();
            } catch (Exception $e) {
                error_log("ClockifyModel getCoreDb error: " . $e->getMessage());
            }
        }
        return null;
    }

    public static function installTables($pdb = null) {
        if ($pdb === null) {
            $pdb = self::getPluginDb();
        }
        if (!$pdb) return false;

        try {
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

            // Ensure is_disabled column exists on roles table
            $db = self::getCoreDb();
            if ($db) {
                try {
                    $db->exec("ALTER TABLE roles ADD COLUMN is_disabled TINYINT(1) DEFAULT 0");
                } catch (Exception $e) {
                    // Column already exists or table un-alterable
                }
            }

            if (function_exists('log_action')) {
                log_action('CLOCKIFY_MODEL_INSTALL_TABLES', ['status' => 'success']);
            }
            return true;
        } catch (Exception $e) {
            error_log("ClockifyModel installTables error: " . $e->getMessage());
            return false;
        }
    }

    public static function uninstallTables($pdb = null) {
        if ($pdb === null) {
            $pdb = self::getPluginDb();
        }
        if (!$pdb) return false;

        try {
            $pdb->dropTable('cache');
            $pdb->dropTable('teams');
            $pdb->dropTable('settings');

            if (function_exists('log_action')) {
                log_action('CLOCKIFY_MODEL_UNINSTALL_TABLES', ['status' => 'success']);
            }
            return true;
        } catch (Exception $e) {
            error_log("ClockifyModel uninstallTables error: " . $e->getMessage());
            return false;
        }
    }

    public static function getSetting($key, $default = null) {
        $pdb = self::getPluginDb();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('settings');
                $stmt = $pdb->query("SELECT setting_value FROM {$tableName} WHERE setting_key = ?", [$key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['setting_value']) && $row['setting_value'] !== '') {
                    return $row['setting_value'];
                }
            } catch (Exception $e) {
                // Table might not exist yet before activation
            }
        }

        // Fallback: check clockify-config.php
        $configFiles = [
            __DIR__ . '/../clockify-config.php',
            __DIR__ . '/../../../clockify-config.php',
            dirname(__DIR__, 3) . '/clockify-config.php'
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

    public static function setSetting($key, $value) {
        $pdb = self::getPluginDb();
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
                error_log("ClockifyModel setSetting error for {$key}: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    public static function getTeams() {
        $pdb = self::getPluginDb();
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
                // Table might not exist yet before activation
            }
        }

        $teamsFile = __DIR__ . '/../teams.json';
        if (file_exists($teamsFile)) {
            $json = json_decode(file_get_contents($teamsFile), true);
            if (is_array($json)) return $json;
        }

        return [];
    }

    public static function saveTeams($teams) {
        $pdb = self::getPluginDb();
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
                error_log("ClockifyModel saveTeams error: " . $e->getMessage());
            }
        }

        $teamsFile = __DIR__ . '/../teams.json';
        @file_put_contents($teamsFile, json_encode($teams, JSON_PRETTY_PRINT));
        return true;
    }

    public static function loadCache($key, $ttl = null) {
        if ($ttl === null) {
            $ttl = (int) self::getSetting('cache_ttl', 60 * 60 * 12);
        }
        $pdb = self::getPluginDb();
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
                // Table might not exist yet before activation
            }
        }
        return false;
    }

    public static function saveCache($key, $data, $ttl = null) {
        if ($ttl === null) {
            $ttl = (int) self::getSetting('cache_ttl', 60 * 60 * 12);
        }
        $pdb = self::getPluginDb();
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
                error_log("ClockifyModel saveCache error for key {$key}: " . $e->getMessage());
            }
        }
        return false;
    }

    public static function clearCache() {
        $pdb = self::getPluginDb();
        if ($pdb) {
            try {
                $tableName = $pdb->getTableName('cache');
                $pdb->query("DELETE FROM {$tableName}");
                return true;
            } catch (Exception $e) {
                error_log("ClockifyModel clearCache error: " . $e->getMessage());
            }
        }
        return false;
    }

    /* =========================================================
     * ROLE & PERMISSION MANAGEMENT
     * ========================================================= */

    public static function getAllRoles() {
        $db = self::getCoreDb();
        if (!$db) return [];

        try {
            $stmt = $db->query("SELECT * FROM roles ORDER BY role_name ASC");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($roles as &$r) {
                $stmt_p = $db->prepare("
                    SELECT p.id, p.permission_name, p.description
                    FROM permissions p
                    JOIN role_permissions rp ON rp.permission_id = p.id
                    WHERE rp.role_id = ?
                    ORDER BY p.permission_name ASC
                ");
                $stmt_p->execute([$r['id']]);
                $r['permissions'] = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
                $r['permission_ids'] = array_column($r['permissions'], 'id');
                $r['is_disabled'] = (int)($r['is_disabled'] ?? 0);
            }

            return $roles;
        } catch (Exception $e) {
            error_log("ClockifyModel getAllRoles error: " . $e->getMessage());
            return [];
        }
    }

    public static function getAllPermissions() {
        $db = self::getCoreDb();
        if (!$db) return [];

        try {
            $stmt = $db->query("SELECT * FROM permissions ORDER BY permission_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ClockifyModel getAllPermissions error: " . $e->getMessage());
            return [];
        }
    }

    public static function createRole($roleName, $description = '') {
        $db = self::getCoreDb();
        if (!$db) return false;

        $cleanName = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $roleName)));
        if (empty($cleanName)) return false;

        try {
            $stmt = $db->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
            $stmt->execute([$cleanName, $description]);
            $roleId = $db->lastInsertId();

            if (function_exists('log_action')) {
                log_action('CLOCKIFY_ROLE_CREATE', ['role_id' => $roleId, 'role_name' => $cleanName]);
            }
            return $roleId;
        } catch (Exception $e) {
            error_log("ClockifyModel createRole error: " . $e->getMessage());
            return false;
        }
    }

    public static function updateRole($roleId, $roleName, $description, $isDisabled = 0) {
        $db = self::getCoreDb();
        if (!$db) return false;

        $cleanName = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $roleName)));
        if (empty($cleanName)) return false;

        try {
            try {
                $stmt = $db->prepare("UPDATE roles SET role_name = ?, description = ?, is_disabled = ? WHERE id = ?");
                $stmt->execute([$cleanName, $description, (int)$isDisabled, $roleId]);
            } catch (Exception $ex) {
                $stmt = $db->prepare("UPDATE roles SET role_name = ?, description = ? WHERE id = ?");
                $stmt->execute([$cleanName, $description, $roleId]);
            }

            if (function_exists('log_action')) {
                log_action('CLOCKIFY_ROLE_UPDATE', ['role_id' => $roleId, 'role_name' => $cleanName, 'is_disabled' => $isDisabled]);
            }
            return true;
        } catch (Exception $e) {
            error_log("ClockifyModel updateRole error: " . $e->getMessage());
            return false;
        }
    }

    public static function toggleRoleStatus($roleId, $isDisabled) {
        $db = self::getCoreDb();
        if (!$db) return false;

        try {
            try {
                $db->exec("ALTER TABLE roles ADD COLUMN is_disabled TINYINT(1) DEFAULT 0");
            } catch (Exception $ex) {}

            $stmt = $db->prepare("UPDATE roles SET is_disabled = ? WHERE id = ?");
            $stmt->execute([(int)$isDisabled, $roleId]);

            if (function_exists('log_action')) {
                log_action('CLOCKIFY_ROLE_TOGGLE_STATUS', ['role_id' => $roleId, 'is_disabled' => $isDisabled]);
            }
            return true;
        } catch (Exception $e) {
            error_log("ClockifyModel toggleRoleStatus error: " . $e->getMessage());
            return false;
        }
    }

    public static function updateRolePermissions($roleId, array $permissionIds) {
        $db = self::getCoreDb();
        if (!$db) return false;

        try {
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);

            if (!empty($permissionIds)) {
                $stmt_check = $db->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                $stmt_insert = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

                foreach ($permissionIds as $pid) {
                    $stmt_check->execute([$roleId, (int)$pid]);
                    if (!$stmt_check->fetch()) {
                        $stmt_insert->execute([$roleId, (int)$pid]);
                    }
                }
            }

            if (function_exists('log_action')) {
                log_action('CLOCKIFY_ROLE_PERMISSIONS_UPDATE', ['role_id' => $roleId, 'permission_count' => count($permissionIds)]);
            }
            return true;
        } catch (Exception $e) {
            error_log("ClockifyModel updateRolePermissions error: " . $e->getMessage());
            return false;
        }
    }

    public static function deleteRole($roleId) {
        $db = self::getCoreDb();
        if (!$db) return false;

        try {
            $stmt_check = $db->prepare("SELECT role_name FROM roles WHERE id = ?");
            $stmt_check->execute([$roleId]);
            $role = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($role && in_array($role['role_name'], ['admin', 'manager', 'user'])) {
                return false;
            }

            $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);

            if (function_exists('log_action')) {
                log_action('CLOCKIFY_ROLE_DELETE', ['role_id' => $roleId]);
            }
            return true;
        } catch (Exception $e) {
            error_log("ClockifyModel deleteRole error: " . $e->getMessage());
            return false;
        }
    }
}
