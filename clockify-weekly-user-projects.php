<?php
require_once "clockify-lib.php";

$pageTitle = "Weekly User Project Hours";
include "header.php";

require __DIR__ . "/plugins/clockify-reports/views/weekly-user-projects.php";

include "footer.php";
