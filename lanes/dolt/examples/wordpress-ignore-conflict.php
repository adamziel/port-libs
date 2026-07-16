<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDeltaMatcher;

$fixture = require dirname(__DIR__) . '/fixtures/wp-ignore-conflict.php';

try {
    $rows = (new TableDeltaMatcher())->summaryRows(
        $fixture['fromTables'],
        $fixture['toTables'],
        null,
        false,
        $fixture['ignorePatterns'],
    );

    return ['rows' => $rows, 'error' => null];
} catch (RuntimeException $e) {
    return ['rows' => [], 'error' => $e->getMessage()];
}
