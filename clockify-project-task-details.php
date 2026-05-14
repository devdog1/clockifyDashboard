<?php
require_once "clockify-lib.php";

//---------------------------------------------------------------
// Fetch project list
//---------------------------------------------------------------
function getProjects()
{
    global $workspaceId;
    $url = "https://api.clockify.me/api/v1/workspaces/$workspaceId/projects?archived=false&page-size=5000";
    $data = clockifyGetCached($url);
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
    $data = clockifyGetCached($url);
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
function getProjectEntries($projectId, $users, $start = null, $end = null)
{
    global $workspaceId;
    $entries = [];

    $base = "https://api.clockify.me/api/v1/workspaces/$workspaceId/user/{USER_ID}/time-entries?project=$projectId&page-size=500";
    if ($start) $base .= "&start=" . $start->format("Y-m-d\TH:i:s\Z");
    if ($end)   $base .= "&end="   . $end->format("Y-m-d\TH:i:s\Z");

    foreach ($users as $userId => $userName) {
        $page = 1;
        $userUrl = str_replace("{USER_ID}", $userId, $base);

        while (true) {
            $url = "$userUrl&page=$page";
            $data = clockifyGetCached($url);
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
$periodOptions = getPeriodOptions();

$projectId = $_GET['project_id'] ?? '';
$selectedName = $projectId && isset($projects[$projectId]) ? $projects[$projectId] : '';

$selectedPeriodKey = $_GET['period'] ?? array_key_first($periodOptions['weeks']);
$period = resolveSelectedPeriod($selectedPeriodKey, $periodOptions);

$entries = [];
if ($projectId) {
    $entries = getProjectEntries($projectId, $users, $period['start'], $period['end']);
}

$pageTitle = "Clockify Project Task Details";
include "header.php";
?>

<div class="my-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><strong>Report Options</strong></div>
        <div class="card-body">
            <form method="get" class="row g-3" id="projectForm">

                <div class="col-md-5">
                    <label for="projectText" class="form-label">Project</label>
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

                <div class="col-md-4">
                    <label for="period" class="form-label">Period</label>
                    <select name="period" id="period" class="form-select">
                        <optgroup label="Financial Years">
                            <?php foreach ($periodOptions['fy'] as $val => $data): ?>
                                <option value="<?= $val ?>" <?= $val == $selectedPeriodKey ? "selected" : "" ?>><?= $data['label'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Weekly Reports">
                            <?php foreach ($periodOptions['weeks'] as $val => $data): ?>
                                <option value="<?= $val ?>" <?= $val == $selectedPeriodKey ? "selected" : "" ?>><?= $data['label'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-3 align-self-end">
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
