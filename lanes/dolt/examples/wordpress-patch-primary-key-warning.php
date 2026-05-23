<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchFunctionCall;

$fixture = require dirname(__DIR__) . '/fixtures/wp-patch-primary-key-warning.php';
$warnings = [];
$rows = (new PatchFunctionCall())->rows(
    [],
    ['STAGED', 'WORKING', 'wp_postmeta'],
    $fixture['options'],
    $warnings,
);

return [
    'rows' => $rows,
    'warnings' => $warnings,
    'statements' => array_column($rows, 'statement'),
];
