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

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
            $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
        ],
        [
            $record('table', 'wp_temp_options', 'wp_temp_options', 4, 'CREATE TABLE wp_temp_options(option_name TEXT)'),
        ],
    );
};

$loader = static function (string $file, string $schema) use ($record): array {
    return match ($schema) {
        'site' => [
            $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
            $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 11, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
        ],
        'archive' => [
            $record('table', 'wp_archived_options', 'wp_archived_options', 20, 'CREATE TABLE wp_archived_options(option_name TEXT)'),
        ],
        default => [
            $record('table', 'wp_' . str_replace('-', '_', $schema), 'wp_' . str_replace('-', '_', $schema), 30),
        ],
    };
};

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$cases = [
    'initial generation is zero' => [static fn () => $catalog()->schemaGeneration(), 0],
    'initial snapshot generation is zero' => [static fn () => $catalog()->schemaCacheSnapshot()['generation'], 0],
    'initial snapshot source defaults main' => [static fn () => $catalog()->schemaCacheSnapshot()['source'], 'main'],
    'initial snapshot database count includes main temp' => [static fn () => $catalog()->schemaCacheSnapshot()['database_count'], 2],
    'initial snapshot search order temp main' => [static fn () => $catalog()->schemaCacheSnapshot()['search_order'], ['temp', 'main']],
    'initial snapshot schema names main temp order' => [static fn () => $catalog()->schemaCacheSnapshot()['schema_names'], ['main', 'temp']],
    'temp source snapshot is allowed' => [static fn () => $catalog()->schemaCacheSnapshot('temp')['source'], 'temp'],
    'quoted source snapshot is normalized' => [static fn () => $catalog()->schemaCacheSnapshot('"TEMP"')['source'], 'temp'],
    'fresh snapshot is current' => [static fn () => $catalog()->schemaCacheIsCurrent($catalog()->schemaCacheSnapshot()), true],
    'fresh invalidation current is true' => [static fn () => $catalog()->schemaCacheInvalidation($catalog()->schemaCacheSnapshot())['current'], true],
    'fresh invalidation has no added schemas' => [static fn () => $catalog()->schemaCacheInvalidation($catalog()->schemaCacheSnapshot())['added_schemas'], []],
    'fresh invalidation has no removed schemas' => [static fn () => $catalog()->schemaCacheInvalidation($catalog()->schemaCacheSnapshot())['removed_schemas'], []],
    'fresh invalidation has no sequence change' => [static fn () => $catalog()->schemaCacheInvalidation($catalog()->schemaCacheSnapshot())['sequence_changed'], false],
    'attach bumps generation to one' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaGeneration();
    }, 1],
    'attach result exposes schema generation' => [static function () use ($catalog, $loader): int {
        return $catalog()->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader)['schema_generation'];
    }, 1],
    'attach result flags cache invalidated' => [static function () use ($catalog, $loader): bool {
        return $catalog()->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader)['cache_invalidated'];
    }, true],
    'attach stale snapshot is not current' => [static function () use ($catalog, $loader): bool {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheIsCurrent($snapshot);
    }, false],
    'attach invalidation reports before generation' => [static function () use ($catalog, $loader): ?int {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheInvalidation($snapshot)['before_generation'];
    }, 0],
    'attach invalidation reports after generation' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheInvalidation($snapshot)['after_generation'];
    }, 1],
    'attach invalidation reports added site schema' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheInvalidation($snapshot)['added_schemas'];
    }, ['site']],
    'attach invalidation reports no removed schemas' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheInvalidation($snapshot)['removed_schemas'];
    }, []],
    'attach invalidation reports invalidated site schema' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheInvalidation($snapshot)['invalidated_schemas'];
    }, ['site']],
    'attach invalidation reports database count increase' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $plan = $c->schemaCacheInvalidation($snapshot);
        return [$plan['before_database_count'], $plan['after_database_count']];
    }, [2, 3]],
    'attach invalidation after search order includes site' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheInvalidation($snapshot)['after_search_order'];
    }, ['temp', 'main', 'site']],
    'attach invalidation sequence changes' => [static function () use ($catalog, $loader): bool {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheInvalidation($snapshot)['sequence_changed'];
    }, true],
    'snapshot after attach is current' => [static function () use ($catalog, $loader): bool {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheIsCurrent($c->schemaCacheSnapshot());
    }, true],
    'snapshot after attach has database count three' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheSnapshot()['database_count'];
    }, 3],
    'snapshot after attach has schema names' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheSnapshot()['schema_names'];
    }, ['main', 'temp', 'site']],
    'attached source snapshot is allowed after attach' => [static function () use ($catalog, $loader): string {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->schemaCacheSnapshot('site')['source'];
    }, 'site'],
    'detach bumps generation to two' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql('DETACH site');
        return $c->schemaGeneration();
    }, 2],
    'detach result exposes generation two' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->executeAttachDetachSql('DETACH site')['schema_generation'];
    }, 2],
    'detach result flags invalidation' => [static function () use ($catalog, $loader): bool {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $c->executeAttachDetachSql('DETACH site')['cache_invalidated'];
    }, true],
    'detach invalidation reports removed site schema' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql('DETACH site');
        return $c->schemaCacheInvalidation($snapshot)['removed_schemas'];
    }, ['site']],
    'detach invalidation reports no added schemas' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql('DETACH site');
        return $c->schemaCacheInvalidation($snapshot)['added_schemas'];
    }, []],
    'detach invalidation reports count decrease' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql('DETACH site');
        $plan = $c->schemaCacheInvalidation($snapshot);
        return [$plan['before_database_count'], $plan['after_database_count']];
    }, [3, 2]],
    'detach invalidation search order returns to temp main' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql('DETACH site');
        return $c->schemaCacheInvalidation($snapshot)['after_search_order'];
    }, ['temp', 'main']],
    'detached source snapshot is rejected' => [static function (TestRunner $t) use ($catalog, $loader): mixed {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql('DETACH site');
        $t->throws(InvalidArgumentException::class, static fn () => $c->schemaCacheSnapshot('site'));
        return null;
    }, null],
    'detach first of two reseats archive sequence' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        $c->executeAttachDetachSql('DETACH site');
        return $c->databaseList()[2]['seq'];
    }, 2],
    'detach first of two marks sequence change for archive' => [static function () use ($catalog, $loader): bool {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql('DETACH site');
        return $c->schemaCacheInvalidation($snapshot)['sequence_changed'];
    }, true],
    'detach second of two keeps site sequence unchanged but cache stale' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql('DETACH archive');
        $plan = $c->schemaCacheInvalidation($snapshot);
        return [$plan['current'], $plan['sequence_changed'], $c->databaseList()[2]['name'], $c->databaseList()[2]['seq']];
    }, [false, true, 'site', 2]],
    'reattach same schema after detach has new generation' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site-a.sqlite' AS site", $loader);
        $c->executeAttachDetachSql('DETACH site');
        $c->executeAttachDetachSql("ATTACH '/srv/site-b.sqlite' AS site", $loader);
        return $c->schemaGeneration();
    }, 3],
    'reattach same schema invalidates old snapshot even with same schema name' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site-a.sqlite' AS site", $loader);
        $snapshot = $c->schemaCacheSnapshot('site');
        $c->executeAttachDetachSql('DETACH site');
        $c->executeAttachDetachSql("ATTACH '/srv/site-b.sqlite' AS site", $loader);
        $plan = $c->schemaCacheInvalidation($snapshot);
        return [$plan['current'], $plan['before_generation'], $plan['after_generation'], $c->databaseList()[2]['file']];
    }, [false, 1, 3, '/srv/site-b.sqlite']],
    'schema cache invalidation tolerates partial snapshot' => [static fn () => $catalog()->schemaCacheInvalidation(['generation' => -1])['current'], false],
    'schema cache invalidation partial snapshot sees current database count' => [static fn () => $catalog()->schemaCacheInvalidation(['generation' => -1])['after_database_count'], 2],
    'schema cache invalidation partial snapshot has main temp added' => [static fn () => $catalog()->schemaCacheInvalidation(['generation' => -1])['added_schemas'], ['main', 'temp']],
    'missing source snapshot raises' => [static function (TestRunner $t) use ($catalog): mixed {
        $t->throws(InvalidArgumentException::class, static fn () => $catalog()->schemaCacheSnapshot('missing'));
        return null;
    }, null],
    'schema cache tracks quoted attached source' => [static function () use ($catalog, $loader): string {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS \"Archive\"", $loader);
        return $c->schemaCacheSnapshot('"Archive"')['source'];
    }, 'archive'],
    'schema cache tracks table resolution after attach' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $before = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return [$c->schemaCacheIsCurrent($before), $c->resolveTable('wp_sitemeta')['schema']];
    }, [false, 'site']],
    'schema cache tracks index resolution after attach' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $before = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return [$c->schemaCacheIsCurrent($before), $c->resolveIndex('wp_sitemeta_key')['schema']];
    }, [false, 'site']],
    'schema cache stale after detach before pragma fallback' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql('DETACH site');
        $pragma = $c->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)');
        return [$c->schemaCacheIsCurrent($snapshot), $pragma['schema'], $pragma['rows']];
    }, [false, 'main', []]],
    'database list snapshot preserves attached file name' => [static function () use ($catalog, $loader, $value): string {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $value($c->schemaCacheSnapshot(), 'database_list.2.file');
    }, '/srv/site.sqlite'],
    'database list snapshot preserves attached sequence' => [static function () use ($catalog, $loader, $value): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        return $value($c->schemaCacheSnapshot(), 'database_list.2.seq');
    }, 2],
    'two attaches generation reaches two' => [static function () use ($catalog, $loader): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        return $c->schemaGeneration();
    }, 2],
    'two attaches snapshot order includes both attached schemas' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        return $c->schemaCacheSnapshot()['search_order'];
    }, ['temp', 'main', 'site', 'archive']],
    'two attaches invalidation from initial reports both added' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        return $c->schemaCacheInvalidation($snapshot)['added_schemas'];
    }, ['site', 'archive']],
    'detach missing does not bump generation' => [static function (TestRunner $t) use ($catalog): int {
        $c = $catalog();
        $t->throws(InvalidArgumentException::class, static fn () => $c->executeAttachDetachSql('DETACH missing'));
        return $c->schemaGeneration();
    }, 0],
    'duplicate attach does not bump generation past first attach' => [static function (TestRunner $t) use ($catalog): int {
        $c = $catalog();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site");
        $t->throws(InvalidArgumentException::class, static fn () => $c->executeAttachDetachSql("ATTACH '/srv/site2.sqlite' AS site"));
        return $c->schemaGeneration();
    }, 1],
    'failed attach main does not bump generation' => [static function (TestRunner $t) use ($catalog): int {
        $c = $catalog();
        $t->throws(InvalidArgumentException::class, static fn () => $c->executeAttachDetachSql("ATTACH '/srv/main.sqlite' AS main"));
        return $c->schemaGeneration();
    }, 0],
    'failed attach empty file does not bump generation' => [static function (TestRunner $t) use ($catalog): int {
        $c = $catalog();
        $t->throws(InvalidArgumentException::class, static fn () => $c->executeAttachDetachSql("ATTACH '' AS site"));
        return $c->schemaGeneration();
    }, 0],
    'old initial snapshot remains stale after attach detach back to same names' => [static function () use ($catalog, $loader): array {
        $c = $catalog();
        $snapshot = $c->schemaCacheSnapshot();
        $c->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $c->executeAttachDetachSql('DETACH site');
        $plan = $c->schemaCacheInvalidation($snapshot);
        return [$plan['current'], $plan['before_database_count'], $plan['after_database_count'], $plan['added_schemas'], $plan['removed_schemas']];
    }, [false, 2, 2, [], []]],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['attach detach schema cache current next29 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback($t));
    };
}

return $tests;
