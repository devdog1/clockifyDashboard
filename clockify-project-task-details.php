<?php
require_once "clockify-lib.php";

//---------------------------------------------------------------
// Fetch project list
//---------------------------------------------------------------
function getProjects()
{
    global $workspaceId;
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

//---------------------------------------------------------------
// Fetch user list
//---------------------------------------------------------------
function getUsers()
{
    global $workspaceId;
    $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/users?page-size=5000";
    $data = clockifyGet($url);
    if (!is_array($data)) return [];

    $out = [];
    foreach ($data as $u) {
        $out[$u['id']] = $u['name'];
    }
    return $out;
}

//---------------------------------------------------------------
// Fetch all time entries for selected project
//---------------------------------------------------------------
function getProjectEntries($projectId, $users)
{
    global $workspaceId;
    $entries = [];

    foreach ($users as $userId => $userName) {
        $page = 1;

        while (true) {
            $url =
                "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/$userId/time-entries" .
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

//---------------------------------------------------------------
// MAIN
//---------------------------------------------------------------
$projects = getProjects();
$users    = getUsers();

$projectId = $_GET['project_id'] ?? '';
$selectedName = $projectId && isset($projects[$projectId]) ? $projects[$projectId] : '';

$entries = [];
if ($projectId) {
    $entries = getProjectEntries($projectId, $users);
}

$pageTitle = "Clockify Project Task Details";
include "header.php";
?>

<div class="my-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><strong>Select a Project</strong></div>
        <div class="card-body">
            <form method="get" class="row g-3" id="projectForm">

                <div class="col-md-9">
                    <input
                        class="form-control"
                        list="projectList"
                        id="projectText"
                        placeholder="Start typing a project name…"
                        value="<?= htmlspecialchars($selectedName) ?>">

                    <datalist id="projectList">
                        <?php foreach ($projects as $pid => $pname): ?>
                            <option
                                data-id="<?= htmlspecialchars($pid) ?>"
                                value="<?= htmlspecialchars($pname) ?>">
                            </option>
                        <?php endforeach; ?>
                    </datalist>

                    <input type="hidden" name="project_id" id="project_id"
                           value="<?= htmlspecialchars($projectId) ?>">
                </div>

                <div class="col-md-3">
                    <button class="btn btn-primary w-100">Load Task Details</button>
                </div>

            </form>
        </div>
    </div>

    <?php if ($projectId && $selectedName): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <strong>Project: <?= htmlspecialchars($selectedName) ?></strong>
            </div>

            <div class="card-body p-0">

                <?php if (empty($entries)): ?>
                    <div class="p-3 text-center text-muted">
                        No time entries found for this project.
                    </div>
                <?php else: ?>

                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 20%;">User</th>
                            <th style="width: 12%;">Date</th>
                            <th style="width: 8%;">Hours</th>
                            <th>Description</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['user']) ?></td>
                                <td><?= htmlspecialchars($e['date']) ?></td>
                                <td><?= number_format($e['hours'], 2) ?></td>
                                <td><?= htmlspecialchars($e['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    (function() {
        const projects = <?= json_encode($projects) ?>;
        const projectText = document.getElementById('projectText');
        const projectIdField = document.getElementById('project_id');

        if (projectText && projectIdField) {
            projectText.addEventListener('input', function () {
                const name = this.value;
                let matchedId = '';

                for (const [id, pname] of Object.entries(projects)) {
                    if (pname === name) {
                        matchedId = id;
                        break;
                    }
                }
                projectIdField.value = matchedId;
            });
        }
    })();
</script>

<?php include "footer.php"; ?>
