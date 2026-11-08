<?php
// /www/wwwroot/obgecom/dashboard/cron/cleanup_ai_attempts.php

define('BASE_PATH', '/www/wwwroot/obgecom/dashboard/');
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Delete AI attempts older than 90 days
$db->query(
    "DELETE FROM ai_confirmation_attempts 
     WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
);

// Delete old notifications
$db->query(
    "DELETE FROM notifications 
     WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
     AND is_read = 1"
);

echo "Cleanup completed: " . date('Y-m-d H:i:s') . "\n";