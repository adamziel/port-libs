<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
    ],
    [],
);

$loaderSeen = [];
$loader = static function (string $file, string $schema) use (&$loaderSeen, $record): array {
    $loaderSeen = [$file, $schema];

    return [
        $record('table', 'wp_site_options', 'wp_site_options', 14, 'CREATE TABLE wp_site_options(option_name TEXT, option_value TEXT)'),
    ];
};

$attach = $catalog->executeAttachDetachSql(
    "ATTACH DATABASE 'file:/srv/application/wp-content/database/site%20copy.sqlite?mode=ro&immutable=1&cache=shared' AS site",
    $loader,
);

$pragma = $catalog->executeSchemaPragma('PRAGMA database_list');

$summary = [
    'schema' => $attach['schema'],
    'decoded_file' => $attach['file'],
    'loader_received' => $loaderSeen,
    'open_status' => $attach['open_plan']['status'],
    'read_only' => $attach['open_plan']['read_only'],
    'immutable' => $attach['uri']['immutable'],
    'cache' => $attach['uri']['cache'],
    'database_list_file' => $pragma['rows'][2]['file'],
    'site_options_schema' => $catalog->resolveTable('site.wp_site_options')['schema'],
    'applicationUse' => 'Attach a copied Application SQLite database by file URI, decode the filename for PRAGMA database_list and schema loading, and preserve mode/cache/immutable open metadata without requiring ext/sqlite.',
];

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        'schema' => 'site',
        'decoded_file' => '/srv/application/wp-content/database/site copy.sqlite',
        'loader_received' => ['/srv/application/wp-content/database/site copy.sqlite', 'site'],
        'open_status' => 'ready',
        'read_only' => true,
        'immutable' => true,
        'cache' => 'shared',
        'database_list_file' => '/srv/application/wp-content/database/site copy.sqlite',
        'site_options_schema' => 'site',
    ];

    foreach ($expected as $key => $value) {
        if ($summary[$key] !== $value) {
            fwrite(STDERR, "application-attach-open-uri-current-next24 self-test failed at {$key}\n");
            exit(1);
        }
    }

    echo "application-attach-open-uri-current-next24 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
