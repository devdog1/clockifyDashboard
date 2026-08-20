<?php
if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    require_once __DIR__ . '/../clockify-lib.php';
}

$apiKey = clockify_get_setting('api_key', '');
$workspaceId = clockify_get_setting('workspace_id', '');

if (empty($apiKey) || empty($workspaceId)) {
    echo '<div class="alert alert-warning border-start border-4 border-warning shadow-sm my-4">
        <h5><i class="fa-solid fa-triangle-exclamation me-2"></i>Clockify Settings Required</h5>
        <p class="mb-2">Clockify API Key or Workspace ID is missing. Please configure your credentials in the plugin settings first.</p>
        <a href="index.php?route=clockify_settings" class="btn btn-warning text-dark font-weight-bold btn-sm">Configure Clockify Settings</a>
    </div>';
    return;
}

function clockify_get_all_users_map($workspaceId) {
    $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/users?page-size=5000";
    $data = clockifyGet($url);
    if (!is_array($data)) return [];

    $out = [];
    foreach ($data as $u) {
        $out[$u['id']] = $u['name'];
    }
    asort($out);
    return $out;
}

$allUsers = clockify_get_all_users_map($workspaceId);
$teams = clockify_get_teams();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_team') {
            $teamName = trim($_POST['team_name'] ?? '');
            if ($teamName !== '') {
                $teams[] = [
                    'name' => $teamName,
                    'users' => []
                ];
                clockify_save_teams($teams);
                $message = "Team '" . htmlspecialchars($teamName) . "' created successfully.";
            }
        } elseif ($_POST['action'] === 'delete_team') {
            $index = (int)($_POST['team_index'] ?? -1);
            if (isset($teams[$index])) {
                $deletedName = $teams[$index]['name'];
                array_splice($teams, $index, 1);
                clockify_save_teams($teams);
                $message = "Team '" . htmlspecialchars($deletedName) . "' deleted.";
            }
        } elseif ($_POST['action'] === 'update_members') {
            $index = (int)($_POST['team_index'] ?? -1);
            if (isset($teams[$index])) {
                $teams[$index]['users'] = $_POST['members'] ?? [];
                clockify_save_teams($teams);
                $message = "Team members updated for '" . htmlspecialchars($teams[$index]['name']) . "'.";
            }
        }
    }
}
?>

<div class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-people-group text-primary me-2"></i>Manage Teams</h2>
        <a href="index.php?route=clockify_dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Create Team Card -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fa-solid fa-plus-circle me-2"></i>Create New Team
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?route=clockify_manage_teams">
                        <?php if (function_exists('csrf_field')) csrf_field(); ?>
                        <input type="hidden" name="action" value="add_team">

                        <div class="mb-3">
                            <label for="team_name" class="form-label fw-bold">Team Name</label>
                            <input type="text" name="team_name" id="team_name" class="form-control" placeholder="e.g. Engineering, Support" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-folder-plus me-1"></i> Add Team
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Teams List -->
        <div class="col-md-8">
            <?php if (empty($teams)): ?>
                <div class="alert alert-info shadow-sm">
                    <i class="fa-solid fa-circle-info me-2"></i>No teams defined yet. Use the form on the left to create your first team.
                </div>
            <?php else: ?>
                <?php foreach ($teams as $index => $team): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                            <strong><i class="fa-solid fa-users me-2 text-info"></i>Team: <?= htmlspecialchars($team['name']) ?></strong>
                            <form method="POST" action="index.php?route=clockify_manage_teams" style="display:inline;" onsubmit="return confirm('Delete this team?');">
                                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="team_index" value="<?= $index ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Team
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?route=clockify_manage_teams">
                                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                                <input type="hidden" name="action" value="update_members">
                                <input type="hidden" name="team_index" value="<?= $index ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Team Members</label>
                                    <?php if (empty($allUsers)): ?>
                                        <div class="text-muted small">No workspace users fetched from Clockify.</div>
                                    <?php else: ?>
                                        <div class="row g-2">
                                            <?php foreach ($allUsers as $uid => $uname): ?>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="members[]"
                                                               value="<?= htmlspecialchars($uid) ?>"
                                                               id="member_<?= $index ?>_<?= htmlspecialchars($uid) ?>"
                                                               <?= in_array($uid, $team['users'] ?? []) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="member_<?= $index ?>_<?= htmlspecialchars($uid) ?>">
                                                            <?= htmlspecialchars($uname) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Update Members
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
