<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$cronRows = [
    ['hook' => 'wp_version_check', 'scheduled' => '2460370.129253472', 'mode' => 'julianday'],
    ['hook' => 'wp_update_plugins', 'scheduled' => '1709219167.875', 'mode' => 'unixepoch'],
    ['hook' => 'wp_delete_temp_updater_backups', 'scheduled' => '2440587.5', 'mode' => 'auto'],
];

$summary = [];
foreach ($cronRows as $row) {
    $modifiers = match ($row['mode']) {
        'julianday' => ['julianday'],
        'unixepoch' => ['unixepoch'],
        default => ['auto'],
    };
    $summary[$row['hook']] = [
        'source' => $row['mode'],
        'iso' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', array_merge(['%FT%T.%fZ', $row['scheduled']], $modifiers)),
        'epoch' => SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', array_merge([$row['scheduled']], $modifiers)),
        'julian' => round((float) SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', array_merge([$row['scheduled']], $modifiers)), 9),
    ];
}

if (in_array('--self-test', $argv, true)) {
    $expected = [
        'wp_version_check' => ['source' => 'julianday', 'iso' => '2024-02-29T15:06:07.07.499Z', 'epoch' => 1709219167, 'julian' => 2460370.129253472],
        'wp_update_plugins' => ['source' => 'unixepoch', 'iso' => '2024-02-29T15:06:07.07.875Z', 'epoch' => 1709219167, 'julian' => 2460370.129257813],
        'wp_delete_temp_updater_backups' => ['source' => 'auto', 'iso' => '1970-01-01T00:00:00.00.000Z', 'epoch' => 0, 'julian' => 2440587.5],
    ];
    if ($summary !== $expected) {
        fwrite(STDERR, "Unexpected datetime summary\n");
        var_export($summary);
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
