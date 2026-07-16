<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\SchemaHistoryTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-schema-history.php';
$table = new SchemaHistoryTable();
$diffRows = $table->diffRows($fixture['commits'], $fixture['workingSchemas']);

return [
    'historyRows' => $table->historyRows($fixture['commits']),
    'workingDiffRows' => array_values(array_filter(
        $diffRows,
        static fn (array $row): bool => $row['to_commit'] === SchemaHistoryTable::WORKING_COMMIT
    )),
];
