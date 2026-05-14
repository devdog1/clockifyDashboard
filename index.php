<?php
require_once "clockify-lib.php";

$pageTitle = "Clockify Reports - Home";
include "header.php";
?>

<div class="p-5 mb-4 bg-light rounded-3 border mt-4">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Clockify Reporting Dashboard</h1>
        <p class="col-md-8 fs-4">Access various reports and summaries for your Clockify workspace. Navigate using the links below or the menu above.</p>
    </div>
</div>

<div class="row align-items-md-stretch mb-4">
    <div class="col-md-3">
        <div class="h-100 p-4 text-white bg-dark rounded-3 shadow-sm">
            <h2>User Weekly</h2>
            <p>View project hours logged by each user weekly, aligned with the fiscal year.</p>
            <a href="clockify-weekly-user-projects.php" class="btn btn-outline-light btn-sm">View Report</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="h-100 p-4 bg-white border rounded-3 shadow-sm">
            <h2>User Details</h2>
            <p>Select a user to see their project contributions and individual task entries.</p>
            <a href="clockify-user-details.php" class="btn btn-outline-secondary btn-sm">View Report</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="h-100 p-4 bg-white border rounded-3 shadow-sm">
            <h2>Project Summary</h2>
            <p>High-level summary of hours per project for current week and fiscal YTD.</p>
            <a href="clockify-project-summary.php" class="btn btn-outline-secondary btn-sm">View Report</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="h-100 p-4 bg-white border rounded-3 shadow-sm">
            <h2>Task Details</h2>
            <p>Drill down into projects to see individual entries, descriptions, and users.</p>
            <a href="clockify-project-task-details.php" class="btn btn-outline-secondary btn-sm">View Report</a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        Workspace Information
    </div>
    <div class="card-body">
        <p class="card-text"><strong>Workspace ID:</strong> <?php echo htmlspecialchars($workspaceId); ?></p>
        <p class="card-text text-muted small">To change settings, please edit <code>clockify-config.php</code>.</p>
    </div>
</div>

<?php include "footer.php"; ?>
