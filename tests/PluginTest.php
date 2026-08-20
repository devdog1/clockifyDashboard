<?php
/**
 * Test suite for Clockify Reports Plugin
 */

class PluginDatabase {
    private $plugin_slug;
    private $prefix;
    private $pdo;

    public function __construct($plugin_slug) {
        $this->plugin_slug = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace('-', '_', $plugin_slug));
        $this->prefix = 'plug_' . $this->plugin_slug . '_';
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getPrefix() {
        return $this->prefix;
    }

    public function getTableName($table_name) {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        return $this->prefix . $safe;
    }

    public function createTable($table_name, $columns_sql) {
        $full = $this->getTableName($table_name);
        $sql = "CREATE TABLE IF NOT EXISTS {$full} (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
        $this->pdo->exec($sql);
        return true;
    }

    public function query($sql, $params = []) {
        // SQLite query adaptation for testing
        if (strpos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            $table = $this->getTableName('settings');
            $sql = "INSERT INTO {$table} (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = ?";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

function get_db_connection() {
    return new PDO('sqlite::memory:');
}

require_once __DIR__ . '/../plugins/clockify-reports/clockify-lib.php';

echo "Testing Clockify Plugin Functions...\n";

// Test 1: Duration Conversion
assert(clockifyDurationToHours('PT1H30M') == 1.5, 'Duration PT1H30M should be 1.5');
assert(clockifyDurationToHours('PT3600S') == 1.0, 'Duration PT3600S should be 1.0');
echo "✓ clockifyDurationToHours test passed.\n";

// Test 2: Fiscal Year Weeks
$weeks = getFiscalYearWeeks();
assert(!empty($weeks), 'Fiscal year weeks should not be empty');
assert(is_array($weeks), 'Fiscal year weeks should be an array');
echo "✓ getFiscalYearWeeks test passed (" . count($weeks) . " weeks generated).\n";

// Test 3: Setting GET/SET via PluginDatabase
$testKey = 'api_key';
$testValue = 'test_secret_api_key_123';
$setResult = clockify_set_setting($testKey, $testValue);
assert($setResult === true, 'clockify_set_setting should return true');

$retrievedValue = clockify_get_setting($testKey);
assert($retrievedValue === $testValue, "Retrieved value '{$retrievedValue}' should match '{$testValue}'");
echo "✓ clockify_set_setting and clockify_get_setting test passed.\n";

// Test 4: Workspace ID Setting
clockify_set_setting('workspace_id', 'ws_test_456');
assert(getClockifyWorkspaceId() === 'ws_test_456', 'getClockifyWorkspaceId should return ws_test_456');
echo "✓ Workspace ID setting test passed.\n";

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
