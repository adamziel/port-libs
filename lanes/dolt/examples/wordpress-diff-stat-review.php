<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDiff;

$fixture = require dirname(__DIR__) . '/fixtures/wp-diff-stat-review.php';
$warnings = [];

return [
    'row' => (new TableDiff())->diffStatRow(
        $fixture['tableName'],
        $fixture['fromRows'],
        $fixture['toRows'],
        $fixture['primaryKey'],
        $fixture['fromSchema'],
        $fixture['toSchema'],
        $warnings,
    ),
    'warnings' => $warnings,
];
