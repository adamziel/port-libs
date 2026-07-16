<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachUriSchemaCache;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name . '(option_name TEXT, option_value TEXT)',
    1,
);

$cache = new SQLiteAttachUriSchemaCache();
$loads = 0;
$loader = static function (string $file, string $schema) use (&$loads, $record): array {
    ++$loads;

    return [$record('wp_' . $schema . '_options', 30 + $loads)];
};

$first = $cache->attach(
    new SQLiteAttachedSchemaCatalog([$record('wp_options', 2)], []),
    "ATTACH 'file:/srv/application/cache%20copy.sqlite?mode=ro&cache=shared&immutable=1' AS cache_a",
    $loader,
    17,
    17,
);
$secondCatalog = new SQLiteAttachedSchemaCatalog([$record('wp_options', 2)], []);
$second = $cache->attach(
    $secondCatalog,
    "ATTACH 'file:/srv/application/cache%20copy.sqlite?mode=ro&cache=shared&immutable=1' AS cache_b",
    $loader,
    17,
    18,
);

$summary = [
    'first_event' => $first['cache_event'],
    'second_event' => $second['cache_event'],
    'loader_calls' => $loads,
    'decoded_file' => $second['file'],
    'schema_cookie' => $second['schema_cookie'],
    'next_requires_reload' => $second['next']['requires_reload'],
    'attached_table_root' => $secondCatalog->resolveTable('cache_b.wp_cache_a_options')['record']->rootPage,
    'applicationUse' => 'Reuse a copied Application attached database schema for file: URI opens only while cache=shared and the schema cookie remains current; a changed next schema cookie schedules a reload before the next attach.',
];

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        'first_event' => 'shared_schema_cache_store',
        'second_event' => 'shared_schema_cache_hit',
        'loader_calls' => 1,
        'decoded_file' => '/srv/application/cache copy.sqlite',
        'schema_cookie' => 17,
        'next_requires_reload' => true,
        'attached_table_root' => 31,
    ];

    foreach ($expected as $key => $value) {
        if ($summary[$key] !== $value) {
            fwrite(STDERR, "application-attach-uri-schema-cache-current-next31 self-test failed at {$key}\n");
            exit(1);
        }
    }

    echo "application-attach-uri-schema-cache-current-next31 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
