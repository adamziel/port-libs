<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempCachePlan
{
    /**
     * Model the cache boundary SQLite crosses when a shared-cache ATTACH opens
     * a WAL-backed database whose schema cookie changes on the next committed
     * WAL transaction. Temp/main name resolution stays connection-local, while
     * attached schema records must be reloaded for the new cookie.
     *
     * @param callable(string, string): list<SQLiteSchemaRecord> $currentRecordLoader
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param list<string> $tables
     * @param list<string> $indexes
     * @return array<string,mixed>
     */
    public static function currentNext(
        SQLiteAttachedSchemaCatalog $catalog,
        SQLiteAttachUriSchemaCache $cache,
        string $attachSql,
        callable $currentRecordLoader,
        array $nextRecords,
        int $currentSchemaCookie,
        int $nextSchemaCookie,
        array $tables,
        array $indexes = [],
    ): array {
        if ($nextSchemaCookie < 0 || $currentSchemaCookie < 0) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL schema cookies must be non-negative');
        }
        if ($nextRecords === []) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL next schema records cannot be empty');
        }
        foreach ($nextRecords as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new \InvalidArgumentException('SQLite ATTACH WAL next schema records must be SQLiteSchemaRecord instances');
            }
        }
        if ($tables === [] && $indexes === []) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL cache plan requires at least one table or index lookup');
        }

        $attach = $cache->attach(
            $catalog,
            $attachSql,
            $currentRecordLoader,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );
        if (($attach['cacheable'] ?? false) !== true) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL cache plan requires cache=shared URI attachment');
        }

        $schema = (string) $attach['schema'];
        $currentSnapshot = $catalog->schemaCacheResolutionSnapshot($tables, $indexes, $schema);
        $currentStats = $catalog->lookupCacheStats();

        $catalog->replaceSchemaRecords($schema, $nextRecords);
        $nextInvalidation = $catalog->schemaCacheResolutionInvalidation($currentSnapshot);
        $nextSnapshot = $catalog->schemaCacheResolutionSnapshot($tables, $indexes, $schema);
        $nextStats = $catalog->lookupCacheStats();

        $nextProbeCatalog = self::catalogWithBaseRecords($currentSnapshot['database_list'], $catalog, $schema);
        $nextAttach = $cache->attach(
            $nextProbeCatalog,
            self::reattachSql($attachSql, $schema . '_next'),
            static fn (): array => $nextRecords,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        return [
            'status' => 'planned',
            'operation' => 'attach-wal-temp-cache-current-next',
            'schema' => $schema,
            'file' => $attach['file'],
            'current_schema_cookie' => $currentSchemaCookie,
            'next_schema_cookie' => $nextSchemaCookie,
            'cookie_changed' => $currentSchemaCookie !== $nextSchemaCookie,
            'attach' => $attach,
            'current_snapshot' => $currentSnapshot,
            'next_snapshot' => $nextSnapshot,
            'invalidation' => $nextInvalidation,
            'current_lookup_cache' => $currentStats,
            'next_lookup_cache' => $nextStats,
            'next_attach' => $nextAttach,
            'temp_shadow_tables' => self::tempShadowTables($nextInvalidation),
            'attached_changed_tables' => self::changedForSchema($nextInvalidation['table_changes'], $schema),
            'attached_changed_indexes' => self::changedForSchema($nextInvalidation['index_changes'], $schema),
            'dependencies' => [
                'sqlite-attach-wal-temp-cache-current-next44',
                'attach-uri-schema-cache',
                'shared-cache-schema-cookie',
                'sqlite-temp-schema-shadow-resolution',
            ],
        ];
    }

    /**
     * @param list<array{seq:int,name:string,file:string|null}> $databaseList
     */
    private static function catalogWithBaseRecords(array $databaseList, SQLiteAttachedSchemaCatalog $catalog, string $skipSchema): SQLiteAttachedSchemaCatalog
    {
        $next = new SQLiteAttachedSchemaCatalog(
            $catalog->schemaRecords('main'),
            $catalog->schemaRecords('temp'),
        );

        foreach ($databaseList as $row) {
            $name = (string) $row['name'];
            if ($name === 'main' || $name === 'temp' || $name === $skipSchema) {
                continue;
            }
            $next->attach($name, (string) ($row['file'] ?? ''), $catalog->schemaRecords($name));
        }

        return $next;
    }

    private static function reattachSql(string $attachSql, string $schema): string
    {
        $trimmed = rtrim(trim($attachSql), " \t\r\n;");
        if (preg_match('/^(attach(?:\s+database)?\s+.+?\s+as\s+)(.+)$/is', $trimmed, $matches) !== 1) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL cache plan only supports ATTACH statements');
        }

        return $matches[1] . $schema;
    }

    /**
     * @param array<string,array{before:array{schema:string|null,name:string|null,rootpage:int|null,type:string|null},after:array{schema:string|null,name:string|null,rootpage:int|null,type:string|null},changed:bool}> $changes
     * @return list<string>
     */
    private static function changedForSchema(array $changes, string $schema): array
    {
        $names = [];
        foreach ($changes as $name => $change) {
            if ($change['changed'] && (($change['before']['schema'] ?? null) === $schema || ($change['after']['schema'] ?? null) === $schema)) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * @param array<string,mixed> $invalidation
     * @return list<string>
     */
    private static function tempShadowTables(array $invalidation): array
    {
        $tables = [];
        foreach ($invalidation['table_changes'] ?? [] as $name => $change) {
            if (($change['before']['schema'] ?? null) === 'temp' && ($change['after']['schema'] ?? null) === 'temp') {
                $tables[] = (string) $name;
            }
        }

        return $tables;
    }
}
