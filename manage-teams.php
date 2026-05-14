<?php
require_once "clockify-lib.php";

$teamsFile = __DIR__ . '/teams.json';

//---------------------------------------------------------------
// Load/Save Teams
//---------------------------------------------------------------
function loadTeams($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveTeams($file, $teams) {
    file_put_contents($file, json_encode($teams, JSON_PRETTY_PRINT));
}

//---------------------------------------------------------------
// Fetch Clockify Users
//---------------------------------------------------------------
function getUsers() {
    global $workspaceId;
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

$allUsers = getUsers();
$teams = loadTeams($teamsFile);

//---------------------------------------------------------------
// Handle Form Submissions
//---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_team') {
            $teamName = trim($_POST['team_name']);
            if ($teamName !== '') {
                $teams[] = [
                    'name' => $teamName,
                    'users' => []
                ];
            }
        } elseif ($_POST['action'] === 'delete_team') {
            $index = (int)$_POST['team_index'];
            if (isset($teams[$index])) {
                array_splice($teams, $index, 1);
            }
        } elseif ($_POST['action'] === 'update_members') {
            $index = (int)$_POST['team_index'];
            if (isset($teams[$index])) {
                $teams[$index]['users'] = $_POST['members'] ?? [];
            }
        }
        saveTeams($teamsFile, $teams);
    }
}

$pageTitle = "Manage Teams";
include "header.php";
?>

<div class="my-4">
    <h2 class="mb-4">Manage Teams</h2>

    <div class="row">
        <!-- Create Team -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><strong>Create New Team</strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_team">
                        <div class="mb-3">
                            <label for="team_name" class="form-label">Team Name</label>
                            <input type="text" name="team_name" id="team_name" class="form-control" placeholder="e.g. Engineering" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add Team</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Team List & Management -->
        <div class="col-md-8">
            <?php if (empty($teams)): ?>
                <div class="alert alert-info">No teams created yet.</div>
            <?php else: ?>
                <?php foreach ($teams as $index => $team): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                            <strong>Team: <?= htmlspecialchars($team['name']) ?></strong>
                            <form method="post" onsubmit="return confirm('Delete this team?');">
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="team_index" value="<?= $index ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="update_members">
                                <input type="hidden" name="team_index" value="<?= $index ?>">

                                <div class="mb-3">
                                    <label class="form-label">Team Members</label>
                                    <div class="row g-2">
                                        <?php foreach ($allUsers as $uid => $uname): ?>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="members[]" value="<?= $uid ?>" id="member_<?= $index ?>_<?= $uid ?>" <?= in_array($uid, $team['users']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="member_<?= $index ?>_<?= $uid ?>">
                                                        <?= htmlspecialchars($uname) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-secondary btn-sm">Update Members</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
