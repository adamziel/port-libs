<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectFlatteningPlan;

$sql = "SELECT option_name FROM (SELECT option_id, option_name, autoload FROM wp_options WHERE autoload = 'yes') AS staged WHERE option_id BETWEEN 1 AND 3 ORDER BY option_name";

echo json_encode(SQLiteSelectFlatteningPlan::plan($sql), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
