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
    <div class="col-md-4">
        <div class="h-100 p-5 text-white bg-dark rounded-3 shadow-sm">
            <h2>User Weekly</h2>
            <p>View project hours logged by each user on a weekly basis, aligned with the fiscal year.</p>
            <a href="clockify-weekly-user-projects.php" class="btn btn-outline-light">View Report</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="h-100 p-5 bg-white border rounded-3 shadow-sm">
            <h2>Project Summary</h2>
            <p>See a high-level summary of hours per project for the current week and the entire fiscal year-to-date.</p>
            <a href="clockify-project-summary.php" class="btn btn-outline-secondary">View Report</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="h-100 p-5 bg-white border rounded-3 shadow-sm">
            <h2>Task Details</h2>
            <p>Drill down into specific projects to see individual time entries, descriptions, and users.</p>
            <a href="clockify-project-task-details.php" class="btn btn-outline-secondary">View Report</a>
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
