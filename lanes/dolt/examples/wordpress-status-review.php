<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\StatusTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-status-review.php';
$status = new StatusTable();

return [
    'rows' => $status->rows(
        $fixture['headTables'],
        $fixture['stagedTables'],
        $fixture['workingTables'],
        $fixture['dataConflictTables'],
        [],
        [],
        [],
        $fixture['ignorePatterns'],
    ),
    'rowsWithIgnored' => $status->rowsWithIgnored(
        $fixture['headTables'],
        $fixture['stagedTables'],
        $fixture['workingTables'],
        $fixture['dataConflictTables'],
        [],
        [],
        [],
        $fixture['ignorePatterns'],
    ),
];
