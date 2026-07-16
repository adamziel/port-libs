<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDeltaMatcher;

$fixture = require dirname(__DIR__) . '/fixtures/wp-ignore-summary.php';

return [
    'ignorePatterns' => $fixture['ignorePatterns'],
    'rows' => (new TableDeltaMatcher())->summaryRows(
        $fixture['fromTables'],
        $fixture['toTables'],
        null,
        false,
        $fixture['ignorePatterns'],
    ),
];
