<?php
if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    require_once __DIR__ . '/../clockify-lib.php';
}

$apiKey = clockify_get_setting('api_key', '');
$workspaceId = clockify_get_setting('workspace_id', '');
$isConfigured = !empty($apiKey) && !empty($workspaceId);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fa-solid fa-clock text-primary me-2"></i>Clockify Reporting Dashboard</h2>
                <p class="text-muted">Access detailed workspace time-tracking reports and summaries.</p>
            </div>
            <div>
                <a href="index.php?route=clockify_settings" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-gear me-1"></i> Settings
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!$isConfigured): ?>
    <div class="alert alert-warning border-start border-4 border-warning shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation fa-2x me-3 text-warning"></i>
            <div>
                <h5 class="alert-heading mb-1">Configuration Required</h5>
                <p class="mb-0">Clockify API Key or Workspace ID has not been configured yet. Please configure them in the plugin settings to enable reports.</p>
            </div>
            <div class="ms-auto">
                <a href="index.php?route=clockify_settings" class="btn btn-warning text-dark font-weight-bold">Configure Now</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row align-items-md-stretch mb-4">
    <div class="col-md-4 mb-3">
        <div class="h-100 p-4 text-white bg-dark rounded-3 shadow-sm d-flex flex-column justify-content-between">
            <div>
                <h3><i class="fa-solid fa-calendar-week text-info me-2"></i>User Weekly</h3>
                <p class="mt-3">View project hours logged by each team member on a weekly basis, aligned with the fiscal year schedule.</p>
            </div>
            <div class="mt-4">
                <a href="index.php?route=clockify_weekly_user_projects" class="btn btn-outline-light w-100">
                    View Report <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="h-100 p-4 bg-white border rounded-3 shadow-sm d-flex flex-column justify-content-between">
            <div>
                <h3><i class="fa-solid fa-chart-simple text-primary me-2"></i>Project Summary</h3>
                <p class="mt-3 text-muted">High-level summary comparing hours per project for the selected week and entire fiscal year-to-date.</p>
            </div>
            <div class="mt-4">
                <a href="index.php?route=clockify_project_summary" class="btn btn-outline-primary w-100">
                    View Report <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="h-100 p-4 bg-white border rounded-3 shadow-sm d-flex flex-column justify-content-between">
            <div>
                <h3><i class="fa-solid fa-list-check text-success me-2"></i>Task Details</h3>
                <p class="mt-3 text-muted">Drill down into specific projects to review granular time entries, detailed descriptions, and user logs.</p>
            </div>
            <div class="mt-4">
                <a href="index.php?route=clockify_task_details" class="btn btn-outline-success w-100">
                    View Report <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-light fw-bold">
        <i class="fa-solid fa-circle-info me-2 text-primary"></i>Workspace Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-2">
                <p class="mb-1"><strong>Workspace ID:</strong></p>
                <code><?= htmlspecialchars($workspaceId ?: 'Not Configured') ?></code>
            </div>
            <div class="col-md-6 mb-2">
                <p class="mb-1"><strong>Storage Engine:</strong></p>
                <span class="badge bg-secondary p-2">Plugin DB Table (<code>plug_clockify_reports_settings</code>)</span>
            </div>
        </div>
    </div>
</div>
