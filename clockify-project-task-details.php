<?php
require_once "clockify-lib.php";

$pageTitle = "Clockify Project Task Details";
include "header.php";

require __DIR__ . "/plugins/clockify-reports/views/project-task-details.php";

include "footer.php";
