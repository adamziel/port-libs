<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\CheckConstraintValidator;
use PortLibs\Dolt\InformationSchema;

$fixture = require dirname(__DIR__) . '/fixtures/wp-check-constraint-information-schema.php';
$tables = [$fixture['tableName'] => $fixture['schema']];
$informationSchema = new InformationSchema();
$violations = (new CheckConstraintValidator())->violations(
    $fixture['schema'],
    $fixture['rows'],
    $fixture['tableName']
);

return [
    'checkConstraints' => $informationSchema->checkConstraints($tables, $fixture['schemaName']),
    'tableConstraints' => $informationSchema->tableConstraints($tables, $fixture['schemaName']),
    'violations' => $violations,
    'violationConstraintNames' => array_column($violations, 'constraint_name'),
    'violationRowIndexes' => array_column($violations, 'row_index'),
];
