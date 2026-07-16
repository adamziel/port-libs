<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\ConstraintViolationsTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-constraint-violation-review.php';
$table = new ConstraintViolationsTable();
$violationRows = $table->checkConstraintRows(
    $fixture['schema'],
    $fixture['rows'],
    $fixture['tableName'],
    $fixture['fromRootIsh'],
);

return [
    'summaryRows' => $table->summaryRows([$fixture['tableName'] => $violationRows]),
    'violationRows' => $violationRows,
    'reviewRows' => array_map(
        static fn (array $row): array => [
            'table' => $fixture['tableName'],
            'audit_id' => $row['audit_id'],
            'status' => $row['import_status'],
            'constraint' => $row['violation_info']['Name'],
        ],
        $violationRows
    ),
];
