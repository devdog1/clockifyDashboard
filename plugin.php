<?php
/*
Plugin Name: Clockify Reports
Description: Workspace reporting dashboard, user weekly hours, project summary, task details, team management, and settings management for Clockify.
Version: 1.0.0
Author: Clockify Integration Team
Permissions: view_clockify_reports, manage_clockify_settings
*/

if (!class_exists('PluginManager')) {
    // If loaded outside zzz5 framework context
    return;
}

if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    define('CLOCKIFY_PLUGIN_DIR', __DIR__);
}

// Define helper wrappers if functions.php was not loaded prior to plugin inclusion
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10) {
        PluginManager::getInstance()->addAction($hook, $callback, $priority);
    }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10) {
        PluginManager::getInstance()->addFilter($hook, $callback, $priority);
    }
}
if (!function_exists('register_route')) {
    function register_route($route_name, $callback) {
        PluginManager::getInstance()->registerRoute($route_name, $callback);
    }
}

if (file_exists(__DIR__ . '/clockify-lib.php')) {
    require_once __DIR__ . '/clockify-lib.php';
} elseif (file_exists(__DIR__ . '/plugins/clockify-reports/clockify-lib.php')) {
    require_once __DIR__ . '/plugins/clockify-reports/clockify-lib.php';
}

$pm = PluginManager::getInstance();

// Register Plugin Activation Hook to initialize DB tables
$pm->addAction('plugin_activate_clockify-reports', function() {
    if (function_exists('clockify_install_tables')) {
        clockify_install_tables();
    }
});
$pm->addAction('plugin_activate_clockify_reports', function() {
    if (function_exists('clockify_install_tables')) {
        clockify_install_tables();
    }
});

// Register Plugin Deactivation Hook to cleanup DB tables
$pm->addAction('plugin_deactivate_clockify-reports', function() {
    if (function_exists('clockify_uninstall_tables')) {
        clockify_uninstall_tables();
    }
});
$pm->addAction('plugin_deactivate_clockify_reports', function() {
    if (function_exists('clockify_uninstall_tables')) {
        clockify_uninstall_tables();
    }
});

// Callback function for framework task scheduler
if (!function_exists('clockify_run_cron_team_reports')) {
    function clockify_run_cron_team_reports() {
        $taskFile = __DIR__ . '/tasks/cron-team-reports.php';
        if (!file_exists($taskFile)) {
            $taskFile = __DIR__ . '/plugins/clockify-reports/tasks/cron-team-reports.php';
        }
        if (file_exists($taskFile)) {
            require $taskFile;
        }
    }
}

// Register Task Scheduler Hook
$pm->addAction('init_scheduler', function($scheduler) {
    if (is_object($scheduler) && method_exists($scheduler, 'registerTask')) {
        $scheduler->registerTask(
            'clockify_cron_team_reports',
            'clockify_run_cron_team_reports',
            86400,
            'clockify-reports'
        );
    }
});

function clockify_get_view_path($viewName) {
    if (file_exists(__DIR__ . "/views/{$viewName}.php")) {
        return __DIR__ . "/views/{$viewName}.php";
    }
    if (file_exists(__DIR__ . "/plugins/clockify-reports/views/{$viewName}.php")) {
        return __DIR__ . "/plugins/clockify-reports/views/{$viewName}.php";
    }
    return null;
}

// Register Navigation Links
$pm->addFilter('theme_nav_links', function ($links) {
    if (function_exists('has_permission') && !has_permission('clockify_reports_view_clockify_reports') && !has_permission('view_dashboard')) {
        return $links;
    }

    $links[] = [
        'label' => 'Clockify Reports',
        'icon'  => 'fa-solid fa-clock',
        'permission' => 'clockify_reports_view_clockify_reports',
        'children' => [
            [
                'route' => 'clockify_dashboard',
                'label' => 'Dashboard',
                'icon'  => 'fa-solid fa-gauge-high',
                'permission' => 'clockify_reports_view_clockify_reports'
            ],
            [
                'route' => 'clockify_weekly_user_projects',
                'label' => 'User Weekly',
                'icon'  => 'fa-solid fa-calendar-week',
                'permission' => 'clockify_reports_view_clockify_reports'
            ],
            [
                'route' => 'clockify_project_summary',
                'label' => 'Project Summary',
                'icon'  => 'fa-solid fa-chart-simple',
                'permission' => 'clockify_reports_view_clockify_reports'
            ],
            [
                'route' => 'clockify_task_details',
                'label' => 'Task Details',
                'icon'  => 'fa-solid fa-list-check',
                'permission' => 'clockify_reports_view_clockify_reports'
            ],
            [
                'route' => 'clockify_manage_teams',
                'label' => 'Manage Teams',
                'icon'  => 'fa-solid fa-people-group',
                'permission' => 'clockify_reports_manage_clockify_settings'
            ],
            [
                'route' => 'clockify_settings',
                'label' => 'Clockify Settings',
                'icon'  => 'fa-solid fa-gear',
                'permission' => 'clockify_reports_manage_clockify_settings'
            ]
        ]
    ];
    return $links;
});

// Register Home Screen Dashboard Widget
$pm->addAction('index_dashboard_widgets', function ($userContext) {
    if (function_exists('has_permission') && !has_permission('clockify_reports_view_clockify_reports') && !has_permission('view_dashboard')) {
        return;
    }
    $apiKey = function_exists('clockify_get_setting') ? clockify_get_setting('api_key', '') : '';
    $workspaceId = function_exists('clockify_get_setting') ? clockify_get_setting('workspace_id', '') : '';
    $configured = !empty($apiKey) && !empty($workspaceId);
    ?>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-start border-5 border-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock text-primary me-2"></i>Clockify Reports</h6>
                    <span class="badge <?= $configured ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= $configured ? 'Configured' : 'Setup Needed' ?>
                    </span>
                </div>
                <p class="small text-muted mb-3">Access workspace project summaries, task breakdown details, user weekly hours, and team management.</p>
                <div class="d-flex gap-2">
                    <a href="index.php?route=clockify_dashboard" class="btn btn-sm btn-primary">Open Reports</a>
                    <a href="index.php?route=clockify_manage_teams" class="btn btn-sm btn-outline-primary">Teams</a>
                    <a href="index.php?route=clockify_settings" class="btn btn-sm btn-outline-secondary">Settings</a>
                </div>
            </div>
        </div>
    </div>
    <?php
});

// Register Extensible Routes
$pm->registerRoute('clockify_dashboard', function() {
    if (function_exists('has_permission') && !has_permission('clockify_reports_view_clockify_reports') && !has_permission('view_dashboard')) {
        echo '<div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>Access Denied. You do not have permission to view Clockify Reports.</div>';
        return;
    }
    $view = clockify_get_view_path('dashboard');
    if ($view) require $view;
});

$pm->registerRoute('clockify_weekly_user_projects', function() {
    if (function_exists('has_permission') && !has_permission('clockify_reports_view_clockify_reports') && !has_permission('view_dashboard')) {
        echo '<div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>Access Denied. You do not have permission to view Clockify Reports.</div>';
        return;
    }
    $view = clockify_get_view_path('weekly-user-projects');
    if ($view) require $view;
});

$pm->registerRoute('clockify_project_summary', function() {
    if (function_exists('has_permission') && !has_permission('clockify_reports_view_clockify_reports') && !has_permission('view_dashboard')) {
        echo '<div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>Access Denied. You do not have permission to view Clockify Reports.</div>';
        return;
    }
    $view = clockify_get_view_path('project-summary');
    if ($view) require $view;
});

$pm->registerRoute('clockify_task_details', function() {
    if (function_exists('has_permission') && !has_permission('clockify_reports_view_clockify_reports') && !has_permission('view_dashboard')) {
        echo '<div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>Access Denied. You do not have permission to view Clockify Reports.</div>';
        return;
    }
    $view = clockify_get_view_path('project-task-details');
    if ($view) require $view;
});

$pm->registerRoute('clockify_manage_teams', function() {
    if (function_exists('has_permission') && !has_permission('clockify_reports_manage_clockify_settings') && !has_permission('manage_settings')) {
        echo '<div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>Access Denied. You do not have permission to manage Clockify teams.</div>';
        return;
    }
    $view = clockify_get_view_path('manage-teams');
    if ($view) require $view;
});

$pm->registerRoute('clockify_settings', function() {
    if (function_exists('has_permission') && !has_permission('clockify_reports_manage_clockify_settings') && !has_permission('manage_settings')) {
        echo '<div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>Access Denied. You do not have permission to manage Clockify settings.</div>';
        return;
    }
    $view = clockify_get_view_path('settings');
    if ($view) require $view;
});
