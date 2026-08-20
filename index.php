<?php
require_once "clockify-lib.php";

$apiKey = getClockifyApiKey();
$workspaceId = getClockifyWorkspaceId();

$pageTitle = "Clockify Reports - Home";
include "header.php";

require __DIR__ . "/plugins/clockify-reports/views/dashboard.php";

include "footer.php";
