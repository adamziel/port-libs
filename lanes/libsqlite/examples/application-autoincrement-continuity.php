<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-autoincrement-continuity.php path/to/application.sqlite [table[,table...]] [table=id[,table=id...]]\n");
    exit(1);
}

$tables = isset($argv[2])
    ? array_values(array_filter(array_map(trim(...), explode(',', $argv[2])), static fn (string $table): bool => $table !== ''))
    : ['wp_posts', 'wp_comments', 'wp_users'];

$plannedExplicitIds = [];
if (isset($argv[3])) {
    foreach (array_filter(array_map(trim(...), explode(',', $argv[3])), static fn (string $entry): bool => $entry !== '') as $entry) {
        [$table, $id] = array_pad(explode('=', $entry, 2), 2, null);
        if ($table === null || $table === '' || $id === null || !preg_match('/^-?[0-9]+$/', $id)) {
            throw new InvalidArgumentException("Invalid explicit ID entry: {$entry}");
        }
        $plannedExplicitIds[$table] = (int) $id;
    }
}

$database = SQLiteDatabase::fromFile($databasePath);
$continuity = [];
foreach ($tables as $table) {
    $state = $database->autoincrementStateForTable($table);
    $initial = $state->toArray();
    $nextGeneratedId = $state->allocateRowId();
    $afterGeneratedInsert = $state->toArray();

    $afterExplicitImport = null;
    if (array_key_exists($table, $plannedExplicitIds)) {
        $importState = $database->autoincrementStateForTable($table);
        $importState->recordInsertedRowId($plannedExplicitIds[$table]);
        $afterExplicitImport = $importState->toArray();
    }

    $continuity[$table] = [
        'initial' => $initial,
        'nextGeneratedId' => $nextGeneratedId,
        'afterGeneratedInsert' => $afterGeneratedInsert,
        'plannedExplicitImport' => $plannedExplicitIds[$table] ?? null,
        'afterExplicitImport' => $afterExplicitImport,
    ];
}

echo json_encode([
    'path' => $databasePath,
    'autoincrementContinuity' => $continuity,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
