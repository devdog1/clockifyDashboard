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
    } elseif (isset($_POST['update_reports_status'])) {
        $enabled = isset($_POST['reports_enabled']) ? '1' : '0';
        clockify_set_setting('reports_enabled', $enabled);
        if (function_exists('log_action')) {
            log_action('CLOCKIFY_REPORTS_STATUS_UPDATE', ['enabled' => $enabled]);
        }
        $message = "Automated email reports " . ($enabled === '1' ? 'enabled' : 'disabled') . ".";
    } elseif (isset($_POST['add_recipient'])) {
        $email = trim($_POST['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipientsRaw = clockify_get_setting('recipients', '[]');
            $recipients = json_decode($recipientsRaw, true) ?: [];
            if (!in_array($email, $recipients)) {
                $recipients[] = $email;
                clockify_set_setting('recipients', json_encode($recipients));
                $message = "Recipient " . htmlspecialchars($email) . " added.";
            } else {
                $error = "Recipient " . htmlspecialchars($email) . " already exists.";
            }
        } else {
            $error = "Invalid email address.";
        }
    } elseif (isset($_POST['remove_recipient'])) {
        $email = trim($_POST['email'] ?? '');
        $recipientsRaw = clockify_get_setting('recipients', '[]');
        $recipients = json_decode($recipientsRaw, true) ?: [];
        if (($idx = array_search($email, $recipients)) !== false) {
            array_splice($recipients, $idx, 1);
            clockify_set_setting('recipients', json_encode($recipients));
            $message = "Recipient " . htmlspecialchars($email) . " removed.";
        }
    } elseif (isset($_POST['clear_cache'])) {
        if (function_exists('clearClockifyCache')) {
            clearClockifyCache();
        }
        if (function_exists('log_action')) {
            log_action('CLOCKIFY_CACHE_CLEAR', ['status' => 'cleared']);
        }
        $message = "Database report cache cleared successfully.";
    }
}

$currentApiKey = clockify_get_setting('api_key', '');
$currentWorkspaceId = clockify_get_setting('workspace_id', '');
$currentCacheTTL = clockify_get_setting('cache_ttl', '43200');
$reportsEnabled = clockify_get_setting('reports_enabled', '1') === '1';
$recipientsRaw = clockify_get_setting('recipients', '[]');
$recipients = json_decode($recipientsRaw, true) ?: [];
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fa-solid fa-gear text-primary me-2"></i>Clockify Settings</h2>
                <p class="text-muted">Manage Clockify API integration settings, automated email report schedules, and recipients. Stored securely in database table <code>plug_clockify_reports_settings</code>.</p>
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
    <div class="col-md-7">
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
                        <div class="form-text">Default: 43200 seconds (12 hours). Reports use database caching to minimize Clockify API calls.</div>
                    </div>

                    <button type="submit" name="save_settings" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save API Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Automated Report Recipients -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <strong><i class="fa-solid fa-envelope me-2"></i>Automated Report Email Recipients</strong>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="index.php?route=clockify_settings" class="row g-2 mb-4">
                    <?php if (function_exists('csrf_field')) csrf_field(); ?>
                    <div class="col-md-8">
                        <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="add_recipient" class="btn btn-success w-100">
                            <i class="fa-solid fa-user-plus me-1"></i> Add Recipient
                        </button>
                    </div>
                </form>

                <div class="table-responsive border rounded">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recipient Email</th>
                                <th class="text-end" style="width: 25%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recipients)): ?>
                                <tr><td colspan="2" class="text-center py-3 text-muted">No report email recipients configured.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recipients as $email): ?>
                                    <tr>
                                        <td><i class="fa-solid fa-envelope me-2 text-secondary"></i><?= htmlspecialchars($email) ?></td>
                                        <td class="text-end">
                                            <form method="POST" action="index.php?route=clockify_settings" style="display:inline;" onsubmit="return confirm('Remove this recipient?');">
                                                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                                                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                                                <button type="submit" name="remove_recipient" class="btn btn-danger btn-sm">
                                                    <i class="fa-solid fa-trash me-1"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <!-- Automated Report Status -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="fa-solid fa-paper-plane me-2"></i>Automated Cron Reports
            </div>
            <div class="card-body p-4">
                <form method="POST" action="index.php?route=clockify_settings">
                    <?php if (function_exists('csrf_field')) csrf_field(); ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="reports_enabled" name="reports_enabled" <?= $reportsEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="reports_enabled">Enable Weekly Cron Email Reports</label>
                    </div>
                    <p class="small text-muted mb-3">When enabled, running <code>cron-team-reports.php</code> via CLI or task scheduler sends automated summary reports to all recipients.</p>
                    <button type="submit" name="update_reports_status" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-sliders me-1"></i> Save Report Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Storage Details & Cache -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <i class="fa-solid fa-database me-2"></i>Storage Details
            </div>
            <div class="card-body">
                <p class="small text-muted mb-1"><strong>Settings Table:</strong></p>
                <code class="d-block mb-2 p-2 bg-light border rounded">plug_clockify_reports_settings</code>
                <p class="small text-muted mb-1"><strong>Teams Table:</strong></p>
                <code class="d-block mb-2 p-2 bg-light border rounded">plug_clockify_reports_teams</code>
                <p class="small text-muted mb-1"><strong>Cache Table:</strong></p>
                <code class="d-block mb-3 p-2 bg-light border rounded">plug_clockify_reports_cache</code>
                <hr>
                <h6 class="fw-bold mb-2">Cache Actions</h6>
                <form method="POST" action="index.php?route=clockify_settings">
                    <?php if (function_exists('csrf_field')) csrf_field(); ?>
                    <button type="submit" name="clear_cache" class="btn btn-outline-warning w-100 btn-sm">
                        <i class="fa-solid fa-trash me-1"></i> Clear Database Report Cache
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
