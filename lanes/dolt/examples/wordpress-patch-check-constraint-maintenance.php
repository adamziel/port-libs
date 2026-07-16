<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchFunctionCall;
use PortLibs\Dolt\TableSchema;

$fixture = require dirname(__DIR__) . '/fixtures/wp-patch-check-constraint-maintenance.php';
$warnings = [];
$rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

return [
    'rows' => $rows,
    'statements' => array_column($rows, 'statement'),
    'warnings' => $warnings,
    'checkDiffTypes' => array_column(
        TableSchema::diffChecks($fixture['baseSchema'], $fixture['workingSchema']),
        'diff_type'
    ),
];
