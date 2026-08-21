<?php
/**
 * Test suite for Clockify Reports Plugin
 */

class MockScheduler {
    public $registeredTasks = [];

    public function registerTask($task_key, $callback, $interval_seconds = 3600, $plugin_slug = 'core') {
        $this->registeredTasks[$task_key] = [
            'callback' => $callback,
            'interval' => $interval_seconds,
            'plugin' => $plugin_slug
        ];
    }
}

class PluginManager {
    private static $instance = null;
    public $actions = [];
    public $filters = [];
    public $routes = [];

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addAction($hook, $callback, $priority = 10) {
        $this->actions[$hook][] = $callback;
    }

    public function addFilter($hook, $callback, $priority = 10) {
        $this->filters[$hook][] = $callback;
    }

    public function registerRoute($route, $callback) {
        $this->routes[$route] = $callback;
    }
}

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
        if (strpos($table_name, 'cache') !== false) {
            $sql = "CREATE TABLE IF NOT EXISTS {$full} (cache_key TEXT PRIMARY KEY, cache_data TEXT NOT NULL, expires_at DATETIME NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
        } elseif (strpos($table_name, 'teams') !== false) {
            $sql = "CREATE TABLE IF NOT EXISTS {$full} (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, members TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$full} (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
        }
        $this->pdo->exec($sql);
        return true;
    }

    public function dropTable($table_name) {
        $full = $this->getTableName($table_name);
        $sql = "DROP TABLE IF EXISTS {$full}";
        $this->pdo->exec($sql);
        return true;
    }

    public function query($sql, $params = []) {
        if (strpos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            if (strpos($sql, 'cache') !== false) {
                $table = $this->getTableName('cache');
                $sql = "INSERT INTO {$table} (cache_key, cache_data, expires_at) VALUES (?, ?, ?) ON CONFLICT(cache_key) DO UPDATE SET cache_data = excluded.cache_data, expires_at = excluded.expires_at";
                $params = array_slice($params, 0, 3);
            } else {
                $table = $this->getTableName('settings');
                $sql = "INSERT INTO {$table} (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value";
                $params = array_slice($params, 0, 2);
            }
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

// Test 3: Table Installation & Setting GET/SET via PluginDatabase
assert(clockify_install_tables() === true, 'clockify_install_tables should return true');
$testKey = 'api_key';
$testValue = 'test_secret_api_key_123';
$setResult = clockify_set_setting($testKey, $testValue);
assert($setResult === true, 'clockify_set_setting should return true');

$retrievedValue = clockify_get_setting($testKey);
assert($retrievedValue === $testValue, "Retrieved value '{$retrievedValue}' should match '{$testValue}'");
echo "✓ Table installation and settings test passed.\n";

// Test 4: Workspace ID Setting
clockify_set_setting('workspace_id', 'ws_test_456');
assert(getClockifyWorkspaceId() === 'ws_test_456', 'getClockifyWorkspaceId should return ws_test_456');
echo "✓ Workspace ID setting test passed.\n";

// Test 5: DB Cache Save & Load
$cacheKey = 'test_report_cache';
$sampleData = ['user1' => ['Project A' => 10.5]];
$saved = saveCache($cacheKey, $sampleData, 3600);
assert($saved === true, 'saveCache should return true');

$cachedData = loadCache($cacheKey);
assert($cachedData !== false, 'loadCache should return cached array');
assert($cachedData['user1']['Project A'] === 10.5, 'Cached data content should match');
echo "✓ DB Cache save and load test passed.\n";

// Test 6: DB Cache Clearing
clearClockifyCache();
assert(loadCache($cacheKey) === false, 'loadCache should return false after clearing cache');
echo "✓ DB Cache clearing test passed.\n";

// Test 7: Teams Save & Get
$sampleTeams = [
    [
        'name' => 'Engineering',
        'users' => ['u100', 'u101']
    ]
];
assert(clockify_save_teams($sampleTeams) === true, 'clockify_save_teams should return true');
$loadedTeams = clockify_get_teams();
assert(count($loadedTeams) === 1, 'Should load 1 team from DB');
assert($loadedTeams[0]['name'] === 'Engineering', 'Team name should match');
assert($loadedTeams[0]['users'] === ['u100', 'u101'], 'Team members should match');
echo "✓ Team creation, save, and load test passed.\n";

// Test 8: Table Uninstallation
assert(clockify_uninstall_tables() === true, 'clockify_uninstall_tables should return true');
echo "✓ Table uninstallation test passed.\n";

// Test 9: Require plugin.php and verify Task Scheduler Registration
require_once __DIR__ . '/../plugins/clockify-reports/plugin.php';
$pm = PluginManager::getInstance();
assert(isset($pm->routes['clockify_dashboard']), 'clockify_dashboard route should be registered');
assert(isset($pm->routes['clockify_manage_teams']), 'clockify_manage_teams route should be registered');
assert(isset($pm->routes['clockify_settings']), 'clockify_settings route should be registered');
assert(isset($pm->actions['init_scheduler']), 'init_scheduler action hook should be registered');

$mockScheduler = new MockScheduler();
foreach ($pm->actions['init_scheduler'] as $callback) {
    call_user_func($callback, $mockScheduler);
}
assert(isset($mockScheduler->registeredTasks['clockify_cron_team_reports']), 'clockify_cron_team_reports task should be registered with Task Scheduler');
echo "✓ Task Scheduler task registration test passed.\n";

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
