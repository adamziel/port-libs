<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\DiffSqlRenderer;
use PortLibs\Dolt\DiffTabularRenderer;
use PortLibs\Dolt\TableDiff;

$fixture = require dirname(__DIR__) . '/fixtures/wp-keyless-import-log.php';
$rows = (new TableDiff())->keylessDiffTableRows(
    $fixture['fromRows'],
    $fixture['toRows'],
    $fixture['columns'],
    $fixture['fromCommit'],
    null,
    $fixture['toCommit'],
    null,
);

$sql = new DiffSqlRenderer();
$tabular = new DiffTabularRenderer();

return [
    'rows' => $rows,
    'sqlAll' => $sql->render($fixture['tableName'], $fixture['schema'], $rows),
    'sqlAdded' => $sql->render($fixture['tableName'], $fixture['schema'], $rows, ['filter' => 'added']),
    'sqlRemoved' => $sql->render($fixture['tableName'], $fixture['schema'], $rows, ['filter' => 'removed']),
    'tabularAll' => $tabular->render($fixture['tableName'], $fixture['schema'], $rows),
    'tabularAdded' => $tabular->render($fixture['tableName'], $fixture['schema'], $rows, ['filter' => 'added']),
    'tabularRemoved' => $tabular->render($fixture['tableName'], $fixture['schema'], $rows, ['filter' => 'removed']),
];
