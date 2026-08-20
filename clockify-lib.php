<?php
/**
 * Clockify Library - Root Wrapper
 */
require_once __DIR__ . '/plugins/clockify-reports/clockify-lib.php';

// Maintain legacy global variables if needed
$apiKey = getClockifyApiKey();
$workspaceId = getClockifyWorkspaceId();
