<?php

declare(strict_types=1);

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

$makeCatalog = static fn (): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
    ],
);

$loader = static function (string $file, string $schema) use ($record): array {
    return [
        $record('table', 'wp_' . str_replace('-', '_', $schema) . '_options', 'wp_' . str_replace('-', '_', $schema) . '_options', strlen($file) + 20),
    ];
};

$get = static function (array $value, string $path): mixed {
    $current = $value;
    foreach (explode('.', $path) as $part) {
        if (is_array($current) && array_key_exists($part, $current)) {
            $current = $current[$part];
            continue;
        }

        return null;
    }

    return $current;
};

$tests = [];

$attachCases = [
    'percent decoded absolute path' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'file',
        '/srv/www/site copy.sqlite',
    ],
    'database list stores decoded uri path' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'database_list.2.file',
        '/srv/www/site copy.sqlite',
    ],
    'loader receives decoded uri path length through root page' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'schema_root',
        45,
    ],
    'uri input preserves original filename' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'uri.input',
        'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared',
    ],
    'uri mode rw is exposed' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'uri.mode',
        'rw',
    ],
    'uri cache shared is exposed' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'uri.cache',
        'shared',
    ],
    'open plan mode follows uri mode' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'open_plan.mode',
        'rw',
    ],
    'open plan shared cache dependency is retained' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw&cache=shared' AS site",
        'open_plan.dependencies.1',
        'shared-cache-coordination',
    ],
    'localhost authority path is normalized' => [
        "ATTACH 'file://localhost/srv/www/archive.sqlite?mode=ro&immutable=1' AS archive",
        'file',
        '/srv/www/archive.sqlite',
    ],
    'localhost authority is retained as metadata' => [
        "ATTACH 'file://localhost/srv/www/archive.sqlite?mode=ro&immutable=1' AS archive",
        'uri.authority',
        'localhost',
    ],
    'immutable uri opens read only' => [
        "ATTACH 'file://localhost/srv/www/archive.sqlite?mode=ro&immutable=1' AS archive",
        'open_plan.read_only',
        true,
    ],
    'immutable dependency is retained' => [
        "ATTACH 'file://localhost/srv/www/archive.sqlite?mode=ro&immutable=1' AS archive",
        'open_plan.dependencies.1',
        'immutable-readonly-open',
    ],
    'nolock one is exposed on attach' => [
        "ATTACH 'file:/srv/www/cache.sqlite?nolock=1&mode=rw' AS cache",
        'uri.nolock',
        true,
    ],
    'nolock dependency is retained' => [
        "ATTACH 'file:/srv/www/cache.sqlite?nolock=1&mode=rw' AS cache",
        'open_plan.dependencies.1',
        'nolock-open',
    ],
    'nolock zero remains false' => [
        "ATTACH 'file:/srv/www/cache.sqlite?nolock=0&mode=rw' AS cache",
        'uri.nolock',
        false,
    ],
    'psow one is exposed on attach' => [
        "ATTACH 'file:/srv/www/cache.sqlite?psow=1&mode=rw' AS cache",
        'uri.psow',
        true,
    ],
    'psow zero is exposed on attach' => [
        "ATTACH 'file:/srv/www/cache.sqlite?psow=0&mode=rw' AS cache",
        'uri.psow',
        false,
    ],
    'vfs parameter is exposed' => [
        "ATTACH 'file:/srv/www/cache.sqlite?vfs=unix-none&mode=rw' AS cache",
        'uri.vfs',
        'unix-none',
    ],
    'vfs dependency is retained' => [
        "ATTACH 'file:/srv/www/cache.sqlite?vfs=unix-none&mode=rw' AS cache",
        'open_plan.dependencies.1',
        'vfs-admission',
    ],
    'unknown uri query parameter is preserved' => [
        "ATTACH 'file:/srv/www/cache.sqlite?mode=rw&z=plugin' AS cache",
        'uri.unknown_parameters.z',
        'plugin',
    ],
    'last duplicate mode query parameter wins' => [
        "ATTACH 'file:/srv/www/cache.sqlite?mode=ro&mode=rw' AS cache",
        'uri.mode',
        'rw',
    ],
    'duplicate query list keeps both values' => [
        "ATTACH 'file:/srv/www/cache.sqlite?mode=ro&mode=rw' AS cache",
        'uri.all_query_parameters.mode.0',
        'ro',
    ],
    'duplicate query list keeps final value' => [
        "ATTACH 'file:/srv/www/cache.sqlite?mode=ro&mode=rw' AS cache",
        'uri.all_query_parameters.mode.1',
        'rw',
    ],
    'bare uri token with query is accepted' => [
        'ATTACH file:/srv/www/bare.sqlite?mode=rw AS bare',
        'file',
        '/srv/www/bare.sqlite',
    ],
    'bare uri token exposes mode' => [
        'ATTACH file:/srv/www/bare.sqlite?mode=rw AS bare',
        'open_plan.mode',
        'rw',
    ],
    'file memory uri normalizes to memory path' => [
        "ATTACH 'file::memory:?mode=memory&cache=shared' AS scratch",
        'file',
        ':memory:',
    ],
    'file memory uri open plan is memory' => [
        "ATTACH 'file::memory:?mode=memory&cache=shared' AS scratch",
        'open_plan.memory',
        true,
    ],
    'plain memory name remains non uri' => [
        "ATTACH ':memory:' AS scratch",
        'uri.is_uri',
        false,
    ],
    'plain memory open plan is memory' => [
        "ATTACH ':memory:' AS scratch",
        'open_plan.memory',
        true,
    ],
    'plain path remains non uri' => [
        "ATTACH '/srv/www/plain.sqlite' AS plain",
        'uri.is_uri',
        false,
    ],
    'plain path open plan defaults rwc' => [
        "ATTACH '/srv/www/plain.sqlite' AS plain",
        'open_plan.mode',
        'rwc',
    ],
    'percent decoded path supports apostrophe' => [
        "ATTACH 'file:/srv/www/site%27s.sqlite?mode=rw' AS quoted",
        'file',
        "/srv/www/site's.sqlite",
    ],
    'percent decoded path supports hash literal' => [
        "ATTACH 'file:/srv/www/site%23one.sqlite?mode=rw' AS hashdb",
        'file',
        '/srv/www/site#one.sqlite',
    ],
    'percent decoded path supports plus literal' => [
        "ATTACH 'file:/srv/www/site%2Bplugin.sqlite?mode=rw' AS plusdb",
        'file',
        '/srv/www/site+plugin.sqlite',
    ],
    'percent decoded path supports unicode bytes' => [
        "ATTACH 'file:/srv/www/plugin-%C3%85.sqlite?mode=rw' AS unicode",
        'file',
        '/srv/www/plugin-Å.sqlite',
    ],
    'schema still resolves loaded records after uri attach' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS plugin_cache",
        'resolved_schema',
        'plugin_cache',
    ],
    'search order includes uri attachment after main' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS plugin_cache",
        'search_order.2',
        'plugin_cache',
    ],
    'schema name is normalized independent of uri' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS \"Plugin-Cache\"",
        'schema',
        'plugin-cache',
    ],
    'database list name is normalized independent of uri' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS \"Plugin-Cache\"",
        'database_list.2.name',
        'plugin-cache',
    ],
    'detach keeps uri normalized path only until removal' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'detach_database_count',
        2,
    ],
    'detach clears uri metadata on detach result' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'detach_uri',
        null,
    ],
    'detach clears open plan on detach result' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'detach_open_plan',
        null,
    ],
    'pragma database list sees decoded uri path' => [
        "ATTACH 'file:/srv/www/site%20copy.sqlite?mode=rw' AS site",
        'pragma.rows.2.file',
        '/srv/www/site copy.sqlite',
    ],
    'file uri known parameters include cache' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw&cache=private' AS site",
        'uri.known_parameters.cache',
        'private',
    ],
    'private cache open plan dependency is omitted' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw&cache=private' AS site",
        'open_plan.dependencies.1',
        null,
    ],
    'open plan reports ready for existing attached file' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'open_plan.status',
        'ready',
    ],
    'open plan can open uri attachment' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'open_plan.can_open',
        true,
    ],
    'open plan does not create assumed existing uri attachment' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rwc' AS site",
        'open_plan.can_create',
        false,
    ],
    'uri path survives quoted SQL expression unescape' => [
        'ATTACH "file:/srv/www/site""quoted.sqlite?mode=rw" AS quoted',
        'file',
        '/srv/www/site"quoted.sqlite',
    ],
    'uri parser preserves original quoted-unescaped input' => [
        'ATTACH "file:/srv/www/site""quoted.sqlite?mode=rw" AS quoted',
        'uri.input',
        'file:/srv/www/site"quoted.sqlite?mode=rw',
    ],
    'attached uri records remain addressable by schema qualification' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'qualified_lookup',
        'site',
    ],
    'attached uri does not disturb temp wp_options shadowing' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'wp_options_schema',
        'temp',
    ],
    'second uri attachment receives later database sequence' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'second_attach_seq',
        3,
    ],
    'second uri attachment preserves its decoded path' => [
        "ATTACH 'file:/srv/www/site.sqlite?mode=rw' AS site",
        'second_attach_file',
        '/srv/www/archive copy.sqlite',
    ],
];

foreach ($attachCases as $name => [$sql, $path, $expected]) {
    $tests['attach open uri current next24 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $loader, $get, $sql, $path, $expected): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql($sql, $loader);
        if ($path === 'schema_root') {
            $actual = $catalog->resolveTable($result['schema'] . '.wp_' . str_replace('-', '_', $result['schema']) . '_options')['record']->rootPage;
        } elseif ($path === 'resolved_schema') {
            $actual = $catalog->resolveTable('wp_plugin_cache_options')['schema'];
        } elseif ($path === 'search_order.2') {
            $actual = $catalog->searchOrder()[2] ?? null;
        } elseif ($path === 'detach_database_count') {
            $actual = count($catalog->executeAttachDetachSql('DETACH ' . $result['schema'])['database_list']);
        } elseif ($path === 'detach_uri') {
            $actual = $catalog->executeAttachDetachSql('DETACH ' . $result['schema'])['uri'];
        } elseif ($path === 'detach_open_plan') {
            $actual = $catalog->executeAttachDetachSql('DETACH ' . $result['schema'])['open_plan'];
        } elseif (str_starts_with($path, 'pragma.')) {
            $actual = $get(['pragma' => $catalog->executeSchemaPragma('PRAGMA database_list')], $path);
        } elseif ($path === 'qualified_lookup') {
            $actual = $catalog->resolveTable($result['schema'] . '.wp_' . str_replace('-', '_', $result['schema']) . '_options')['schema'];
        } elseif ($path === 'wp_options_schema') {
            $actual = $catalog->resolveTable('wp_options')['schema'];
        } elseif ($path === 'second_attach_seq') {
            $second = $catalog->executeAttachDetachSql("ATTACH 'file:/srv/www/archive%20copy.sqlite?mode=ro' AS archive", $loader);
            $actual = $second['database_list'][3]['seq'] ?? null;
        } elseif ($path === 'second_attach_file') {
            $second = $catalog->executeAttachDetachSql("ATTACH 'file:/srv/www/archive%20copy.sqlite?mode=ro' AS archive", $loader);
            $actual = $second['database_list'][3]['file'] ?? null;
        } else {
            $actual = $get($result, $path);
        }

        $t->same($expected, $actual);
    };
}

$errorCases = [
    'rejects malformed percent escape in uri path' => "ATTACH 'file:/srv/www/site%2.sqlite?mode=rw' AS site",
    'rejects unsupported uri authority' => "ATTACH 'file://remotehost/srv/www/site.sqlite?mode=rw' AS site",
    'rejects unsupported uri mode' => "ATTACH 'file:/srv/www/site.sqlite?mode=write' AS site",
    'rejects unsupported uri cache' => "ATTACH 'file:/srv/www/site.sqlite?cache=global' AS site",
    'rejects invalid immutable boolean' => "ATTACH 'file:/srv/www/site.sqlite?immutable=yes' AS site",
    'rejects invalid nolock boolean' => "ATTACH 'file:/srv/www/site.sqlite?nolock=yes' AS site",
    'rejects invalid psow boolean' => "ATTACH 'file:/srv/www/site.sqlite?psow=yes' AS site",
    'rejects empty query parameter name' => "ATTACH 'file:/srv/www/site.sqlite?=bad' AS site",
    'rejects empty uri filename' => "ATTACH 'file:?mode=rw' AS site",
    'rejects unbounded expression still before uri parse' => 'ATTACH concat("file:/srv/", "site.sqlite") AS site',
];

foreach ($errorCases as $name => $sql) {
    $tests['attach open uri current next24 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $sql): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql($sql));
    };
}

return $tests;
