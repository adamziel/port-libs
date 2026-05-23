<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchFunctionCall;

$fixture = require dirname(__DIR__) . '/fixtures/wp-patch-check-constraint-review.php';
$warnings = [];
$rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

return [
    'rows' => $rows,
    'statements' => array_column($rows, 'statement'),
    'warnings' => $warnings,
];
