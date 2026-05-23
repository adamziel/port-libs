<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\ConstraintViolationsTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-foreign-key-constraint-violation-review.php';
$table = new ConstraintViolationsTable();
$violationRows = $table->rowsForTable(
    $fixture['schema'],
    $fixture['violations'],
    $fixture['fromRootIsh'],
);
$singleDelete = $table->deleteRowsForTable(
    $fixture['schema'],
    $fixture['violations'],
    $fixture['singleDeleteCriteria'],
    $fixture['fromRootIsh'],
);
$bulkDelete = $table->deleteRowsForTable(
    $fixture['schema'],
    $singleDelete['remaining_violations'],
    [],
    $fixture['fromRootIsh'],
);

return [
    'summaryRows' => $table->summaryRows([$fixture['tableName'] => $violationRows]),
    'violationRows' => $violationRows,
    'reviewRows' => array_map(
        static fn (array $row): array => [
            'table' => $fixture['tableName'],
            'meta_id' => $row['meta_id'],
            'orphaned_post_id' => $row['post_id'],
            'meta_key' => $row['meta_key'],
            'foreign_key' => $row['violation_info']['ForeignKey'],
            'referenced_table' => $row['violation_info']['ReferencedTable'],
            'resolution' => 'restore parent post or remove dangling postmeta before promotion',
        ],
        $violationRows
    ),
    'singleDelete' => [
        'rows_affected' => $singleDelete['rows_affected'],
        'remaining_rows' => $singleDelete['remaining_rows'],
        'remaining_summary' => $table->summaryRows([$fixture['tableName'] => $singleDelete['remaining_rows']]),
    ],
    'bulkDelete' => [
        'rows_affected' => $bulkDelete['rows_affected'],
        'remaining_rows' => $bulkDelete['remaining_rows'],
        'remaining_summary' => $table->summaryRows([$fixture['tableName'] => $bulkDelete['remaining_rows']]),
    ],
];
