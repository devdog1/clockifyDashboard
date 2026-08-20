<?php
if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    require_once __DIR__ . '/../clockify-lib.php';
}

$apiKey = clockify_get_setting('api_key', '');
$workspaceId = clockify_get_setting('workspace_id', '');

if (empty($apiKey) || empty($workspaceId)) {
    echo '<div class="alert alert-warning border-start border-4 border-warning shadow-sm my-4">
        <h5><i class="fa-solid fa-triangle-exclamation me-2"></i>Clockify Settings Required</h5>
        <p class="mb-2">Clockify API Key or Workspace ID is missing. Please configure your credentials in the plugin settings to view this report.</p>
        <a href="index.php?route=clockify_settings" class="btn btn-warning text-dark font-weight-bold btn-sm">Configure Clockify Settings</a>
    </div>';
    return;
}

if (!function_exists('clockify_get_projects_list')) {
    function clockify_get_projects_list($workspaceId) {
        $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=5000";
        $data = clockifyGet($url);
        if (!is_array($data)) return [];

        $out = [];
        foreach ($data as $proj) {
            $out[$proj['id']] = $proj['name'];
        }
        asort($out);
        return $out;
    }
}

if (!function_exists('clockify_get_users_list')) {
    function clockify_get_users_list($workspaceId) {
        $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/users?page-size=5000";
        $data = clockifyGet($url);
        if (!is_array($data)) return [];

        $out = [];
        foreach ($data as $u) {
            $out[$u['id']] = $u['name'];
        }
        return $out;
    }
}

if (!function_exists('clockify_get_project_entries')) {
    function clockify_get_project_entries($workspaceId, $projectId, $users) {
        $entries = [];
        foreach ($users as $userId => $userName) {
            $page = 1;
            while (true) {
                $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
                       "?project=$projectId&page=$page&page-size=500";
                $data = clockifyGet($url);
                if (!is_array($data) || empty($data)) break;

                foreach ($data as $entry) {
                    $start = $entry['timeInterval']['start'] ?? null;
                    $startDate = $start ? substr($start, 0, 10) : '';
                    $duration = $entry['timeInterval']['duration'] ?? null;

                    $entries[] = [
                        'user'        => $userName,
                        'date'        => $startDate,
                        'hours'       => clockifyDurationToHours($duration),
                        'description' => $entry['description'] ?? '',
                    ];
                }

                if (count($data) < 500) break;
                $page++;
            }
        }
        return $entries;
    }
}

$projects = clockify_get_projects_list($workspaceId);
$users    = clockify_get_users_list($workspaceId);

$projectId = $_GET['project_id'] ?? '';
$selectedName = $projectId && isset($projects[$projectId]) ? $projects[$projectId] : '';

$entries = [];
if ($projectId) {
    $entries = clockify_get_project_entries($workspaceId, $projectId, $users);
}
?>

<div class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-list-check text-primary me-2"></i>Project Task Details</h2>
        <a href="index.php?route=clockify_dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">
            <i class="fa-solid fa-folder-open me-2"></i>Select a Project
        </div>
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3" id="projectForm">
                <input type="hidden" name="route" value="clockify_task_details">

                <div class="col-md-9">
                    <select name="project_id" id="projectSelect" class="form-select">
                        <option value="">-- Choose a Project --</option>
                        <?php foreach ($projects as $pid => $pname): ?>
                            <option value="<?= htmlspecialchars($pid) ?>" <?= $pid === $projectId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pname) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Load Task Details
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($projectId && $selectedName): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <strong><i class="fa-solid fa-folder me-2 text-info"></i>Project: <?= htmlspecialchars($selectedName) ?></strong>
                <span class="badge bg-secondary"><?= count($entries) ?> time entries</span>
            </div>

            <div class="card-body p-0">
                <?php if (empty($entries)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fa-solid fa-circle-info fa-2x mb-2 d-block text-secondary"></i>
                        No time entries found for this project.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20%;">User</th>
                                    <th style="width: 15%;">Date</th>
                                    <th style="width: 12%;">Hours</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $e): ?>
                                    <tr>
                                        <td><i class="fa-solid fa-user me-2 text-secondary"></i><?= htmlspecialchars($e['user']) ?></td>
                                        <td><i class="fa-solid fa-calendar me-2 text-muted"></i><?= htmlspecialchars($e['date']) ?></td>
                                        <td><strong><?= number_format($e['hours'], 2) ?></strong> hrs</td>
                                        <td><?= htmlspecialchars($e['description']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
