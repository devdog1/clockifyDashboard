<?php
require_once "clockify-lib.php";

$pageTitle = "Clockify Project Summary";
include "header.php";

require __DIR__ . "/plugins/clockify-reports/views/project-summary.php";

include "footer.php";
