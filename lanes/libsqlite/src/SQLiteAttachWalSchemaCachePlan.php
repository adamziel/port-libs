<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalSchemaCachePlan
{
    /**
     * @param list<string> $tables
     * @param list<string> $indexes
     * @param array<string,int> $schemaCookies
     * @return array<string,mixed>
     */
    public static function snapshot(
        SQLiteAttachedSchemaCatalog $catalog,
        array $tables,
        array $indexes,
        array $schemaCookies,
        string $sourceSchema = 'main',
        int $walEndFrame = 0,
    ): array {
        $snapshot = $catalog->schemaCacheResolutionSnapshot($tables, $indexes, $sourceSchema);
        $snapshot['schema_cookies'] = self::normalizeCookieMap($schemaCookies);
        $snapshot['wal_end_frame'] = $walEndFrame;
        $snapshot['dependencies'] = ['sqlite-attach-wal-schema-cache-current-next'];

        return $snapshot;
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param array<string,int> $nextSchemaCookies
     * @return array<string,mixed>
     */
    public static function currentNext(
        SQLiteAttachedSchemaCatalog $catalog,
        array $snapshot,
        array $nextSchemaCookies,
        int $nextWalEndFrame,
    ): array {
        $resolution = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $currentCookies = self::normalizeCookieMap(is_array($snapshot['schema_cookies'] ?? null) ? $snapshot['schema_cookies'] : []);
        $nextCookies = self::normalizeCookieMap($nextSchemaCookies);
        $cookieChanges = self::cookieChanges($currentCookies, $nextCookies);
        $changedCookieSchemas = array_keys(array_filter(
            $cookieChanges,
            static fn (array $change): bool => $change['changed'],
        ));
        sort($changedCookieSchemas);

        $tableReprepare = self::reprepareObjects($resolution['table_changes'], $changedCookieSchemas);
        $indexReprepare = self::reprepareObjects($resolution['index_changes'], $changedCookieSchemas);
        $reasons = [];
        if (!$resolution['current']) {
            $reasons[] = 'attach-detach-generation';
        }
        if ($resolution['added_schemas'] !== []) {
            $reasons[] = 'attached-schema-added';
        }
        if ($resolution['removed_schemas'] !== []) {
            $reasons[] = 'attached-schema-removed';
        }
        if ($changedCookieSchemas !== []) {
            $reasons[] = 'wal-schema-cookie-changed';
        }
        if ($nextWalEndFrame !== (int) ($snapshot['wal_end_frame'] ?? 0)) {
            $reasons[] = 'wal-end-frame-advanced';
        }

        return [
            'status' => 'planned',
            'requires_reprepare' => $resolution['stale'] || $changedCookieSchemas !== [],
            'reasons' => array_values(array_unique($reasons)),
            'current_reader' => [
                'generation' => $snapshot['generation'] ?? null,
                'wal_end_frame' => (int) ($snapshot['wal_end_frame'] ?? 0),
                'search_order' => array_values($snapshot['search_order'] ?? []),
                'schema_cookies' => $currentCookies,
            ],
            'next_reader' => [
                'generation' => $catalog->schemaGeneration(),
                'wal_end_frame' => $nextWalEndFrame,
                'search_order' => $catalog->searchOrder(),
                'schema_cookies' => $nextCookies,
            ],
            'schema_cache' => $resolution,
            'schema_cookie_changes' => $cookieChanges,
            'changed_cookie_schemas' => $changedCookieSchemas,
            'table_reprepare' => $tableReprepare,
            'index_reprepare' => $indexReprepare,
            'stable_tables' => array_values(array_diff(array_keys($resolution['table_changes']), array_keys($tableReprepare))),
            'stable_indexes' => array_values(array_diff(array_keys($resolution['index_changes']), array_keys($indexReprepare))),
            'dependencies' => ['sqlite-attach-wal-schema-cache-current-next', 'sqlite-wal-reader-current-next', 'sqlite-schema-cache'],
        ];
    }

    /**
     * @param array<string,array{before:array{schema:string|null,name:string|null,rootpage:int|null,type:string|null},after:array{schema:string|null,name:string|null,rootpage:int|null,type:string|null},changed:bool}> $changes
     * @param list<string> $changedCookieSchemas
     * @return array<string,array{schema:string|null, reason:string}>
     */
    private static function reprepareObjects(array $changes, array $changedCookieSchemas): array
    {
        $objects = [];
        foreach ($changes as $name => $change) {
            $afterSchema = $change['after']['schema'];
            $beforeSchema = $change['before']['schema'];
            if ($change['changed']) {
                $objects[$name] = ['schema' => $afterSchema, 'reason' => 'resolution-changed'];
                continue;
            }
            $schema = $afterSchema ?? $beforeSchema;
            if ($schema !== null && in_array($schema, $changedCookieSchemas, true)) {
                $objects[$name] = ['schema' => $schema, 'reason' => 'schema-cookie-changed'];
            }
        }

        return $objects;
    }

    /**
     * @param array<string,int> $cookies
     * @return array<string,int>
     */
    private static function normalizeCookieMap(array $cookies): array
    {
        $normalized = [];
        foreach ($cookies as $schema => $cookie) {
            $name = strtolower(trim((string) $schema));
            if ($name === '') {
                continue;
            }
            $normalized[$name] = (int) $cookie;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,int> $current
     * @param array<string,int> $next
     * @return array<string,array{before:int|null,after:int|null,changed:bool}>
     */
    private static function cookieChanges(array $current, array $next): array
    {
        $schemas = array_values(array_unique(array_merge(array_keys($current), array_keys($next))));
        sort($schemas);
        $changes = [];
        foreach ($schemas as $schema) {
            $before = $current[$schema] ?? null;
            $after = $next[$schema] ?? null;
            $changes[$schema] = [
                'before' => $before,
                'after' => $after,
                'changed' => $before !== $after,
            ];
        }

        return $changes;
    }
}
