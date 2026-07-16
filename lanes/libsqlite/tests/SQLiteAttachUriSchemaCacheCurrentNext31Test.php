<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachUriSchemaCache;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $name, int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    $sql ?? 'CREATE TABLE ' . $name . '(option_name TEXT, option_value TEXT)',
    1,
);

$catalog = static fn (): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(
    [$record('wp_options', 2)],
    [],
);

$tests = [
    'attach uri schema cache current next31 reuses shared cache schema records for same URI and schema cookie' => static function (TestRunner $t) use ($catalog, $record): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loads = 0;
        $loader = static function (string $file, string $schema) use (&$loads, $record): array {
            ++$loads;

            return [$record('wp_' . $schema . '_options', 40 + $loads)];
        };

        $firstCatalog = $catalog();
        $first = $cache->attach($firstCatalog, "ATTACH 'file:/srv/wp/site%20copy.sqlite?mode=ro&cache=shared&immutable=1' AS site", $loader, 7);
        $secondCatalog = $catalog();
        $second = $cache->attach($secondCatalog, "ATTACH 'file:/srv/wp/site%20copy.sqlite?mode=ro&cache=shared&immutable=1' AS mirror", $loader, 7);

        $t->same('shared_schema_cache_store', $first['cache_event']);
        $t->same('shared_schema_cache_hit', $second['cache_event']);
        $t->same(true, $first['loader_called']);
        $t->same(false, $second['loader_called']);
        $t->same(1, $loads);
        $t->same(41, $secondCatalog->resolveTable('mirror.wp_site_options')['record']->rootPage);
        $t->same('/srv/wp/site copy.sqlite', $second['file']);
        $t->same('shared', $second['uri']['cache']);
        $t->same('ro', $second['uri']['mode']);
        $t->same(true, $second['uri']['immutable']);
        $t->same(['attach-uri-schema-cache', 'shared-cache-schema-cookie'], $second['dependencies']);
    },
    'attach uri schema cache current next31 reloads private cache and plain filenames' => static function (TestRunner $t) use ($catalog, $record): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loads = 0;
        $loader = static function (string $file, string $schema) use (&$loads, $record): array {
            ++$loads;

            return [$record('wp_' . $schema . '_options', 80 + $loads)];
        };

        $privateOne = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/private.sqlite?mode=rw&cache=private' AS private_one", $loader, 3);
        $privateTwo = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/private.sqlite?mode=rw&cache=private' AS private_two", $loader, 3);
        $plain = $cache->attach($catalog(), "ATTACH '/srv/wp/plain.sqlite' AS plain", $loader, 3);

        $t->same('uncacheable_private_or_plain', $privateOne['cache_event']);
        $t->same('uncacheable_private_or_plain', $privateTwo['cache_event']);
        $t->same('uncacheable_private_or_plain', $plain['cache_event']);
        $t->same(true, $privateOne['loader_called']);
        $t->same(true, $privateTwo['loader_called']);
        $t->same(true, $plain['loader_called']);
        $t->same(false, $privateOne['cacheable']);
        $t->same(false, $plain['cacheable']);
        $t->same(null, $privateOne['cache_key']);
        $t->same(null, $plain['next']['cache_key']);
        $t->same(3, $loads);
    },
    'attach uri schema cache current next31 schema cookie changes force shared reload' => static function (TestRunner $t) use ($catalog, $record): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loads = 0;
        $loader = static function (string $file, string $schema) use (&$loads, $record): array {
            ++$loads;

            return [$record('wp_' . $schema . '_options', 120 + $loads)];
        };

        $first = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/current.sqlite?mode=rw&cache=shared' AS current_a", $loader, 9, 10);
        $second = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/current.sqlite?mode=rw&cache=shared' AS current_b", $loader, 10);
        $third = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/current.sqlite?mode=rw&cache=shared' AS current_c", $loader, 10);

        $t->same('shared_schema_cache_store', $first['cache_event']);
        $t->same(false, $first['next']['reuse_current']);
        $t->same(true, $first['next']['requires_reload']);
        $t->same(10, $first['next']['schema_cookie']);
        $t->same('shared_schema_cache_store', $second['cache_event']);
        $t->same('shared_schema_cache_hit', $third['cache_event']);
        $t->same(true, $second['loader_called']);
        $t->same(false, $third['loader_called']);
        $t->same(2, $loads);
        $t->same(10, $third['schema_cookie']);
        $t->same(true, $third['next']['reuse_current']);
    },
    'attach uri schema cache current next31 URI identity includes vfs mode immutable and normalized path' => static function (TestRunner $t) use ($catalog, $record): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loads = 0;
        $loader = static function (string $file, string $schema) use (&$loads, $record): array {
            ++$loads;

            return [$record('wp_' . $schema . '_options', 160 + $loads)];
        };

        $base = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/cache.sqlite?mode=rw&cache=shared&vfs=unix-none' AS cache_a", $loader, 4);
        $same = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/cache.sqlite?cache=shared&mode=rw&vfs=unix-none' AS cache_b", $loader, 4);
        $differentMode = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/cache.sqlite?mode=ro&cache=shared&vfs=unix-none' AS cache_c", $loader, 4);
        $differentVfs = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/cache.sqlite?mode=rw&cache=shared&vfs=unix-excl' AS cache_d", $loader, 4);
        $differentPath = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/cache%20copy.sqlite?mode=rw&cache=shared&vfs=unix-none' AS cache_e", $loader, 4);

        $t->same('shared_schema_cache_store', $base['cache_event']);
        $t->same('shared_schema_cache_hit', $same['cache_event']);
        $t->same('shared_schema_cache_store', $differentMode['cache_event']);
        $t->same('shared_schema_cache_store', $differentVfs['cache_event']);
        $t->same('shared_schema_cache_store', $differentPath['cache_event']);
        $t->same(4, $loads);
        $t->same('unix-none', $same['uri']['vfs']);
        $t->same('ro', $differentMode['uri']['mode']);
        $t->same('unix-excl', $differentVfs['uri']['vfs']);
        $t->same('/srv/wp/cache copy.sqlite', $differentPath['file']);
        $t->same(false, $differentPath['loader_called'] === false);
    },
    'attach uri schema cache current next31 detach leaves shared schema cache available for reattach' => static function (TestRunner $t) use ($catalog, $record): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loads = 0;
        $loader = static function (string $file, string $schema) use (&$loads, $record): array {
            ++$loads;

            return [$record('wp_' . $schema . '_options', 200 + $loads)];
        };

        $firstCatalog = $catalog();
        $first = $cache->attach($firstCatalog, "ATTACH 'file:/srv/wp/detach.sqlite?mode=rw&cache=shared' AS cache_a", $loader, 11);
        $detach = $firstCatalog->executeAttachDetachSql('DETACH cache_a');
        $secondCatalog = $catalog();
        $second = $cache->attach($secondCatalog, "ATTACH 'file:/srv/wp/detach.sqlite?mode=rw&cache=shared' AS cache_b", $loader, 11);

        $t->same('shared_schema_cache_store', $first['cache_event']);
        $t->same('detach', $detach['operation']);
        $t->same(2, count($detach['database_list']));
        $t->same('shared_schema_cache_hit', $second['cache_event']);
        $t->same(false, $second['loader_called']);
        $t->same(1, $loads);
        $t->same(201, $secondCatalog->resolveTable('cache_b.wp_cache_a_options')['record']->rootPage);
        $t->same(1, count($cache->entries()));
    },
    'attach uri schema cache current next31 preserves open plan and database list metadata' => static function (TestRunner $t) use ($catalog, $record): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loader = static fn (string $file, string $schema): array => [$record('wp_network_options', 240)];
        $catalog = $catalog();
        $result = $cache->attach($catalog, "ATTACH 'file://localhost/srv/wp/network.sqlite?mode=ro&cache=shared&immutable=1' AS network", $loader, 13);

        $t->same('network', $result['schema']);
        $t->same('/srv/wp/network.sqlite', $result['database_list'][2]['file']);
        $t->same('ready', $result['open_plan']['status']);
        $t->same(true, $result['open_plan']['read_only']);
        $t->same(true, $result['open_plan']['immutable']);
        $t->same('localhost', $result['uri']['authority']);
        $t->same(240, $catalog->resolveTable('network.wp_network_options')['record']->rootPage);
        $t->same(1, count($cache->entries()));
    },
    'attach uri schema cache current next31 rejects unsupported ATTACH file expressions before caching' => static function (TestRunner $t) use ($catalog, $record): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loader = static fn (): array => [$record('wp_never_loaded', 300)];

        $t->throws(InvalidArgumentException::class, static fn () => $cache->attach($catalog(), 'DETACH cache_a', $loader, 1));
        $t->throws(InvalidArgumentException::class, static fn () => $cache->attach($catalog(), 'ATTACH concat("file:/srv/", "site.sqlite") AS site', $loader, 1));
        $t->throws(InvalidArgumentException::class, static fn () => $cache->attach($catalog(), "ATTACH 'file:/srv/wp/bad.sqlite?cache=global' AS bad", $loader, 1));
        $t->same([], $cache->entries());
    },
];

$identityCases = [
    'shared cache marks uri opens cacheable' => ['cacheable', true],
    'shared cache stores decoded file' => ['file', '/srv/wp/case copy.sqlite'],
    'shared cache exposes current schema cookie' => ['schema_cookie', 21],
    'shared cache exposes open status' => ['open_plan.status', 'ready'],
    'shared cache exposes read only open' => ['open_plan.read_only', true],
    'shared cache exposes immutable open' => ['open_plan.immutable', true],
    'shared cache exposes uri cache shared' => ['uri.cache', 'shared'],
    'shared cache exposes uri mode ro' => ['uri.mode', 'ro'],
    'shared cache exposes uri immutable true' => ['uri.immutable', true],
    'shared cache exposes database list attached name' => ['database_list.2.name', 'case_a'],
    'shared cache exposes database list attached file' => ['database_list.2.file', '/srv/wp/case copy.sqlite'],
    'shared cache reports current next reuse' => ['next.reuse_current', true],
    'shared cache reports no reload when next cookie matches' => ['next.requires_reload', false],
];

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

foreach ($identityCases as $name => [$path, $expected]) {
    $tests['attach uri schema cache current next31 ' . $name] = static function (TestRunner $t) use ($catalog, $record, $get, $path, $expected): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $result = $cache->attach(
            $catalog(),
            "ATTACH 'file:/srv/wp/case%20copy.sqlite?mode=ro&cache=shared&immutable=1' AS case_a",
            static fn (string $file, string $schema): array => [$record('wp_case_options', 321)],
            21,
            21,
        );

        $t->same($expected, $get($result, $path));
    };
}

$reloadCases = [
    'next schema cookie changes cache key' => static fn (array $result): bool => is_string($result['cache_key']) && $result['cache_key'] !== $result['next']['cache_key'],
    'next schema cookie schedules reload' => static fn (array $result): bool => $result['next']['requires_reload'] === true,
    'next schema cookie stops current reuse' => static fn (array $result): bool => $result['next']['reuse_current'] === false,
    'next schema cookie keeps current entry generation' => static fn (array $result): bool => $result['generation'] === 1,
    'next schema cookie keeps current attach loaded' => static fn (array $result): bool => $result['loader_called'] === true,
    'next schema cookie keeps shared dependencies' => static fn (array $result): bool => $result['dependencies'] === ['attach-uri-schema-cache', 'shared-cache-schema-cookie'],
    'next schema cookie keeps one record' => static fn (array $result): bool => $result['record_count'] === 1,
    'next schema cookie preserves shared cache event' => static fn (array $result): bool => $result['cache_event'] === 'shared_schema_cache_store',
    'next schema cookie preserves URI vfs' => static fn (array $result): bool => $result['uri']['vfs'] === 'unix-none',
    'next schema cookie preserves open dependency' => static fn (array $result): bool => in_array('shared-cache-coordination', $result['open_plan']['dependencies'], true),
];

foreach ($reloadCases as $name => $predicate) {
    $tests['attach uri schema cache current next31 ' . $name] = static function (TestRunner $t) use ($catalog, $record, $predicate): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $result = $cache->attach(
            $catalog(),
            "ATTACH 'file:/srv/wp/reload.sqlite?mode=rw&cache=shared&vfs=unix-none' AS reload_a",
            static fn (string $file, string $schema): array => [$record('wp_reload_options', 411)],
            30,
            31,
        );

        $t->same(true, $predicate($result));
    };
}

$hitCases = [
    'shared cache hit avoids second load',
    'shared cache hit keeps original root page',
    'shared cache hit creates one cache entry',
    'shared cache hit uses second schema database list name',
    'shared cache hit uses normalized shared URI identity',
    'shared cache hit keeps generation stable',
    'shared cache hit keeps record count stable',
    'shared cache hit keeps current cache key stable',
    'shared cache hit keeps current next cache key stable',
    'shared cache hit preserves decoded path',
];

foreach ($hitCases as $name) {
    $tests['attach uri schema cache current next31 ' . $name] = static function (TestRunner $t) use ($catalog, $record, $name): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loads = 0;
        $loader = static function (string $file, string $schema) use (&$loads, $record): array {
            ++$loads;

            return [$record('wp_hit_options', 500 + $loads)];
        };

        $first = $cache->attach($catalog(), "ATTACH 'file:/srv/wp/hit.sqlite?mode=rw&cache=shared' AS hit_a", $loader, 41);
        $secondCatalog = $catalog();
        $second = $cache->attach($secondCatalog, "ATTACH 'file:/srv/wp/hit.sqlite?cache=shared&mode=rw' AS hit_b", $loader, 41);

        $actual = match ($name) {
            'shared cache hit avoids second load' => $loads,
            'shared cache hit keeps original root page' => $secondCatalog->resolveTable('hit_b.wp_hit_options')['record']->rootPage,
            'shared cache hit creates one cache entry' => count($cache->entries()),
            'shared cache hit uses second schema database list name' => $second['database_list'][2]['name'],
            'shared cache hit uses normalized shared URI identity' => $second['cache_event'],
            'shared cache hit keeps generation stable' => $second['generation'],
            'shared cache hit keeps record count stable' => $second['record_count'],
            'shared cache hit keeps current cache key stable' => $first['cache_key'] === $second['cache_key'],
            'shared cache hit keeps current next cache key stable' => $second['cache_key'] === $second['next']['cache_key'],
            'shared cache hit preserves decoded path' => $second['file'],
        };

        $expected = match ($name) {
            'shared cache hit avoids second load' => 1,
            'shared cache hit keeps original root page' => 501,
            'shared cache hit creates one cache entry' => 1,
            'shared cache hit uses second schema database list name' => 'hit_b',
            'shared cache hit uses normalized shared URI identity' => 'shared_schema_cache_hit',
            'shared cache hit keeps generation stable' => 1,
            'shared cache hit keeps record count stable' => 1,
            'shared cache hit keeps current cache key stable' => true,
            'shared cache hit keeps current next cache key stable' => true,
            'shared cache hit preserves decoded path' => '/srv/wp/hit.sqlite',
        };

        $t->same($expected, $actual);
    };
}

$privateCases = [
    'private cache has no shared entry' => static fn (SQLiteAttachUriSchemaCache $cache, array $result, int $loads): mixed => count($cache->entries()),
    'private cache calls loader once' => static fn (SQLiteAttachUriSchemaCache $cache, array $result, int $loads): mixed => $loads,
    'private cache reports false cacheable' => static fn (SQLiteAttachUriSchemaCache $cache, array $result, int $loads): mixed => $result['cacheable'],
    'private cache has no current cache key' => static fn (SQLiteAttachUriSchemaCache $cache, array $result, int $loads): mixed => $result['cache_key'],
    'private cache has no next cache key' => static fn (SQLiteAttachUriSchemaCache $cache, array $result, int $loads): mixed => $result['next']['cache_key'],
    'private cache keeps private uri metadata' => static fn (SQLiteAttachUriSchemaCache $cache, array $result, int $loads): mixed => $result['uri']['cache'],
    'private cache keeps base dependency only' => static fn (SQLiteAttachUriSchemaCache $cache, array $result, int $loads): mixed => $result['dependencies'],
];

foreach ($privateCases as $name => $projection) {
    $tests['attach uri schema cache current next31 ' . $name] = static function (TestRunner $t) use ($catalog, $record, $projection, $name): void {
        $cache = new SQLiteAttachUriSchemaCache();
        $loads = 0;
        $result = $cache->attach(
            $catalog(),
            "ATTACH 'file:/srv/wp/private-case.sqlite?mode=rw&cache=private' AS private_case",
            static function (string $file, string $schema) use (&$loads, $record): array {
                ++$loads;

                return [$record('wp_private_options', 601)];
            },
            51,
        );

        $expected = match ($name) {
            'private cache has no shared entry' => 0,
            'private cache calls loader once' => 1,
            'private cache reports false cacheable' => false,
            'private cache has no current cache key' => null,
            'private cache has no next cache key' => null,
            'private cache keeps private uri metadata' => 'private',
            'private cache keeps base dependency only' => ['attach-uri-schema-cache'],
        };

        $t->same($expected, $projection($cache, $result, $loads));
    };
}

return $tests;
