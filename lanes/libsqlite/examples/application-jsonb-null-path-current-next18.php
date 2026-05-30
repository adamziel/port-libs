<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugins' => [
        ['slug' => 'seo', 'enabled' => true],
        ['slug' => 'forms', 'enabled' => false],
    ],
    'meta' => ['source' => 'import'],
]));

$mutated = SQLiteJsonMutation::mutateSqlFunction(
    'jsonb_set',
    $settings,
    null,
    'ignored-path-from-optional-filter',
    '$.plugins[#]',
    new SQLiteBlobValue(SQLiteJsonB::encode(['slug' => 'cache', 'enabled' => true])),
);

echo json_encode([
    'option_name' => 'active_plugins',
    'mutationKind' => 'jsonb-null-path-current-next18',
    'decodedAfter' => SQLiteJsonB::decode($mutated->bytes),
    'removeWithNullPath' => SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $settings, null),
    'applicationUse' => 'Preserves SQLite JSONB mutation behavior for optional wp_options JSON path filters: NULL mutation paths are skipped while JSON remove with a NULL path returns SQL NULL, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
