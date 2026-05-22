<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\ProcedureHistoryTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-procedure-history.php';
$table = new ProcedureHistoryTable();
$diffRows = $table->diffRows($fixture['commits'], $fixture['workingProcedures']);

return [
    'historyRows' => $table->historyRows($fixture['commits']),
    'workingDiffRows' => array_values(array_filter(
        $diffRows,
        static fn (array $row): bool => $row['to_commit'] === ProcedureHistoryTable::WORKING_COMMIT
    )),
];
