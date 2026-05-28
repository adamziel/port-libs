<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext166
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array<string,mixed>
     */
    public static function currentNextPageFromCatalog(
        array $currentRecords,
        array $currentTables,
        array $nextRecords,
        array $nextTables,
        string $indexXinfoSql,
        int $offset = 0,
        int $limit = 166,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $currentForeignKeys = self::foreignKeysFromCatalog($currentRecords);
        $nextForeignKeys = self::foreignKeysFromCatalog($nextRecords);

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext156::currentNextPage(
            $currentRecords,
            $currentForeignKeys,
            $currentTables,
            $nextRecords,
            $nextForeignKeys,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            $cursor,
            $tableValuedIndexXinfo,
        );

        return [
            ...$page,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_source' => 'pragma_foreign_key_list_actions',
                'derived_foreign_keys' => count($currentForeignKeys),
                'foreign_key_actions' => self::actionSummary($currentForeignKeys),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_source' => 'pragma_foreign_key_list_actions',
                'derived_foreign_keys' => count($nextForeignKeys),
                'foreign_key_actions' => self::actionSummary($nextForeignKeys),
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function foreignKeysFromCatalog(array $records): array
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next166 records must be SQLiteSchemaRecord instances');
            }
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161::foreignKeysFromCatalog($records);
        $tableRecords = self::tableRecords($records);
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $enriched = [];

        foreach ($base as $foreignKey) {
            $table = (string) $foreignKey['table'];
            $id = (int) $foreignKey['id'];
            $pragmaRows = array_values(array_filter(
                $catalog->execute('PRAGMA foreign_key_list(' . self::pragmaArgumentLiteral($table) . ')')['rows'],
                static fn (array $row): bool => (int) $row['id'] === $id
            ));
            if ($pragmaRows === []) {
                throw new InvalidArgumentException("SQLite PRAGMA index_xinfo/FK current-source next166 missing foreign_key_list rows for {$table} id {$id}");
            }

            usort($pragmaRows, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
            $clause = self::foreignKeyClause($tableRecords[strtolower($table)] ?? null, $id);
            $onUpdate = self::normalizeAction((string) ($pragmaRows[0]['on_update'] ?? 'NO ACTION'));
            $onDelete = self::normalizeAction((string) ($pragmaRows[0]['on_delete'] ?? 'NO ACTION'));
            $match = strtoupper((string) ($pragmaRows[0]['match'] ?? 'NONE'));

            $enriched[] = [
                ...$foreignKey,
                'on_update' => $onUpdate,
                'on_delete' => $onDelete,
                'match' => $match,
                'deferrable' => self::deferrable($clause),
                'initially_deferred' => self::initiallyDeferred($clause),
                'pragma_rows' => array_map(
                    static fn (array $row): array => [
                        'seq' => (int) $row['seq'],
                        'from' => (string) $row['from'],
                        'to' => $row['to'] === null ? null : (string) $row['to'],
                        'on_update' => self::normalizeAction((string) $row['on_update']),
                        'on_delete' => self::normalizeAction((string) $row['on_delete']),
                        'match' => strtoupper((string) $row['match']),
                    ],
                    $pragmaRows,
                ),
            ];
        }

        return $enriched;
    }

    /**
     * @param list<array<string,mixed>> $foreignKeys
     * @return array<string,mixed>
     */
    private static function actionSummary(array $foreignKeys): array
    {
        $summary = [
            'on_update' => [],
            'on_delete' => [],
            'match' => [],
            'deferrable' => 0,
            'initially_deferred' => 0,
        ];
        foreach ($foreignKeys as $foreignKey) {
            self::increment($summary['on_update'], (string) ($foreignKey['on_update'] ?? 'NO ACTION'));
            self::increment($summary['on_delete'], (string) ($foreignKey['on_delete'] ?? 'NO ACTION'));
            self::increment($summary['match'], (string) ($foreignKey['match'] ?? 'NONE'));
            if (($foreignKey['deferrable'] ?? false) === true) {
                $summary['deferrable']++;
            }
            if (($foreignKey['initially_deferred'] ?? false) === true) {
                $summary['initially_deferred']++;
            }
        }
        ksort($summary['on_update']);
        ksort($summary['on_delete']);
        ksort($summary['match']);

        return $summary;
    }

    /**
     * @param array<string,int> $bucket
     */
    private static function increment(array &$bucket, string $key): void
    {
        $bucket[$key] = ($bucket[$key] ?? 0) + 1;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,SQLiteSchemaRecord>
     */
    private static function tableRecords(array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next166 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record;
            }
        }

        return $tables;
    }

    private static function foreignKeyClause(?SQLiteSchemaRecord $record, int $id): string
    {
        if ($record === null || $record->sql === null) {
            return '';
        }
        $body = self::parenthesizedBody($record->sql);
        if ($body === null) {
            return '';
        }

        $clauses = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            if (stripos($definition, 'REFERENCES') !== false) {
                $clauses[] = trim($definition);
            }
        }

        return $clauses[$id] ?? '';
    }

    private static function deferrable(string $clause): bool
    {
        return preg_match('/\bDEFERRABLE\b/i', $clause) === 1
            && preg_match('/\bNOT\s+DEFERRABLE\b/i', $clause) !== 1;
    }

    private static function initiallyDeferred(string $clause): bool
    {
        return self::deferrable($clause) && preg_match('/\bINITIALLY\s+DEFERRED\b/i', $clause) === 1;
    }

    private static function normalizeAction(string $action): string
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($action)) ?? trim($action));

        return $normalized === '' ? 'NO ACTION' : $normalized;
    }

    private static function pragmaArgumentLiteral(string $identifier): string
    {
        return "'" . str_replace("'", "''", $identifier) . "'";
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && ($sql[$i + 1] ?? '') === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($sql, $open + 1, $i - $open - 1);
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && ($sql[$i + 1] ?? '') === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && $char === $delimiter) {
                $parts[] = substr($sql, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($sql, $start);

        return $parts;
    }
}
