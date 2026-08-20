<?php
if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    require_once __DIR__ . '/../clockify-lib.php';
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    }

    if (isset($_POST['save_settings'])) {
        $apiKey = trim($_POST['api_key'] ?? '');
        $workspaceId = trim($_POST['workspace_id'] ?? '');
        $cacheTTL = (int)($_POST['cache_ttl'] ?? 43200);

        clockify_set_setting('api_key', $apiKey);
        clockify_set_setting('workspace_id', $workspaceId);
        clockify_set_setting('cache_ttl', (string)$cacheTTL);

        if (function_exists('log_action')) {
            log_action('CLOCKIFY_SETTINGS_UPDATE', [
                'api_key_set' => !empty($apiKey),
                'workspace_id' => $workspaceId,
                'cache_ttl' => $cacheTTL
            ]);
        }

        $message = 'Clockify settings saved successfully to the plugin database table!';
    } elseif (isset($_POST['clear_cache'])) {
        $cacheDir = __DIR__ . '/../cache';
        $files = glob($cacheDir . '/*.json');
        $cleared = 0;
        if ($files) {
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                    $cleared++;
                }
            }
        }
        if (function_exists('log_action')) {
            log_action('CLOCKIFY_CACHE_CLEAR', ['cleared_files' => $cleared]);
        }
        $message = "Cache cleared ($cleared files removed).";
    }
}

$currentApiKey = clockify_get_setting('api_key', '');
$currentWorkspaceId = clockify_get_setting('workspace_id', '');
$currentCacheTTL = clockify_get_setting('cache_ttl', '43200');
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fa-solid fa-gear text-primary me-2"></i>Clockify Settings</h2>
                <p class="text-muted">Manage Clockify API integration settings. All settings are stored securely in the plugin's isolated settings database table (<code>plug_clockify_reports_settings</code>).</p>
            </div>
            <div>
                <a href="index.php?route=clockify_dashboard" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <i class="fa-solid fa-key me-2"></i>API Configuration
            </div>
            <div class="card-body p-4">
                <form method="POST" action="index.php?route=clockify_settings">
                    <?php if (function_exists('csrf_field')) csrf_field(); ?>

                    <div class="mb-3">
                        <label for="api_key" class="form-label fw-bold">Clockify API Key</label>
                        <input type="password" class="form-control" id="api_key" name="api_key"
                               value="<?= htmlspecialchars($currentApiKey) ?>"
                               placeholder="Enter your Clockify API Key" required>
                        <div class="form-text">Generate your API key in Clockify Profile Settings &gt; API.</div>
                    </div>

                    <div class="mb-3">
                        <label for="workspace_id" class="form-label fw-bold">Workspace ID</label>
                        <input type="text" class="form-control" id="workspace_id" name="workspace_id"
                               value="<?= htmlspecialchars($currentWorkspaceId) ?>"
                               placeholder="Enter your Clockify Workspace ID" required>
                        <div class="form-text">Find your Workspace ID in your Clockify URL or workspace settings.</div>
                    </div>

                    <div class="mb-4">
                        <label for="cache_ttl" class="form-label fw-bold">Cache Duration (Seconds)</label>
                        <input type="number" class="form-control" id="cache_ttl" name="cache_ttl"
                               value="<?= htmlspecialchars($currentCacheTTL) ?>" min="60" step="60">
                        <div class="form-text">Default: 43200 seconds (12 hours). Reports use local caching to minimize Clockify API calls.</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="fa-solid fa-database me-2"></i>Storage Details
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2"><strong>Database Table:</strong></p>
                <code class="d-block mb-3 p-2 bg-light border rounded">plug_clockify_reports_settings</code>
                <p class="small text-muted mb-3">Settings are safely isolated inside your portal database table structure according to framework specifications.</p>
                <hr>
                <h6 class="fw-bold mb-2">Cache Actions</h6>
                <form method="POST" action="index.php?route=clockify_settings">
                    <?php if (function_exists('csrf_field')) csrf_field(); ?>
                    <button type="submit" name="clear_cache" class="btn btn-outline-warning w-100 btn-sm">
                        <i class="fa-solid fa-trash me-1"></i> Clear Local Report Cache
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
