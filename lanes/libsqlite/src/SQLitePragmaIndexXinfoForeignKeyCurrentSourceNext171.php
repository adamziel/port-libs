<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext171
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
        int $limit = 171,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        self::validateRecords($currentRecords);
        self::validateRecords($nextRecords);

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::currentNextPageFromCatalog(
            $currentRecords,
            $currentTables,
            $nextRecords,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            null,
            $tableValuedIndexXinfo,
        );

        $currentTiming = self::timingRows($currentRecords);
        $nextTiming = self::timingRows($nextRecords);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next171',
            'base' => $page['source_id'],
            'current_timing' => self::timingSummary($currentTiming),
            'next_timing' => self::timingSummary($nextTiming),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentTimingMap = self::timingMap($currentTiming);
        $nextTimingMap = self::timingMap($nextTiming);
        $rows = array_map(
            static fn (array $row): array => self::decorateRow($row, $row['side'] === 'current' ? $currentTimingMap : $nextTimingMap),
            $page['rows'],
        );

        return [
            ...$page,
            'source_id' => $sourceId,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_timing_source' => 'sqlite_schema_foreign_key_deferrable',
                'foreign_key_timing' => self::timingSummary($currentTiming),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_timing_source' => 'sqlite_schema_foreign_key_deferrable',
                'foreign_key_timing' => self::timingSummary($nextTiming),
            ],
            'current' => [
                ...$page['current'],
                'foreign_key_timing' => self::timingCounts($currentTiming),
            ],
            'next_counts' => [
                ...$page['next_counts'],
                'foreign_key_timing' => self::timingCounts($nextTiming),
            ],
            'delta' => [
                ...$page['delta'],
                'foreign_key_timing_changes' => self::timingChangeCount($currentTiming, $nextTiming),
                'foreign_key_timing_changed' => self::timingSummary($currentTiming) !== self::timingSummary($nextTiming),
            ],
            'next' => $page['next'] === null ? null : [
                'source_id' => $sourceId,
                'offset' => $page['next']['offset'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function foreignKeysFromCatalog(array $records): array
    {
        self::validateRecords($records);

        $foreignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records);
        $timing = self::timingMap(self::timingRows($records));

        return array_map(
            static function (array $foreignKey) use ($timing): array {
                $rowTiming = $timing[self::timingKey((string) $foreignKey['table'], (int) $foreignKey['id'])] ?? self::defaultTiming();

                return [
                    ...$foreignKey,
                    ...$rowTiming,
                ];
            },
            $foreignKeys,
        );
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{table:string,parent:string,fkid:int,deferrable:bool,initially_deferred:bool,timing:string}>
     */
    private static function timingRows(array $records): array
    {
        self::validateRecords($records);

        $foreignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167::foreignKeysFromCatalog($records);
        $clauses = self::timingClausesByTable($records);
        $rows = [];

        foreach ($foreignKeys as $foreignKey) {
            $table = (string) $foreignKey['table'];
            $id = (int) $foreignKey['id'];
            $clause = $clauses[strtolower($table)][$id] ?? null;
            $timing = $clause === null ? self::defaultTiming() : self::timingFromClause($clause);
            $rows[] = [
                'table' => $table,
                'parent' => (string) $foreignKey['parent'],
                'fkid' => $id,
                ...$timing,
            ];
        }

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,list<string>>
     */
    private static function timingClausesByTable(array $records): array
    {
        $clauses = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }

            $body = self::parenthesizedBody($record->sql);
            if ($body === null) {
                continue;
            }

            foreach (self::splitTopLevel($body, ',') as $definition) {
                if (preg_match('/\bREFERENCES\b/i', $definition) !== 1) {
                    continue;
                }
                $clauses[strtolower($record->name)][] = $definition;
            }
        }

        return $clauses;
    }

    /** @param list<mixed> $records */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next171 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @return array{deferrable:bool,initially_deferred:bool,timing:string}
     */
    private static function timingFromClause(string $clause): array
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($clause)) ?? trim($clause));
        $notDeferrable = str_contains($normalized, 'NOT DEFERRABLE');
        $deferrable = !$notDeferrable && str_contains($normalized, 'DEFERRABLE');
        $initiallyDeferred = $deferrable && str_contains($normalized, 'INITIALLY DEFERRED');
        $timing = 'not_deferrable';
        if ($notDeferrable) {
            $timing = 'not_deferrable';
        } elseif ($deferrable && str_contains($normalized, 'INITIALLY IMMEDIATE')) {
            $timing = 'deferrable_immediate';
        } elseif ($deferrable) {
            $timing = $initiallyDeferred ? 'deferrable_deferred' : 'deferrable_immediate';
        }

        return [
            'deferrable' => $deferrable,
            'initially_deferred' => $initiallyDeferred,
            'timing' => $timing,
        ];
    }

    /**
     * @return array{deferrable:bool,initially_deferred:bool,timing:string}
     */
    private static function defaultTiming(): array
    {
        return [
            'deferrable' => false,
            'initially_deferred' => false,
            'timing' => 'not_deferrable',
        ];
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,deferrable:bool,initially_deferred:bool,timing:string}> $timing
     * @return array<string,array{deferrable:bool,initially_deferred:bool,timing:string}>
     */
    private static function timingMap(array $timing): array
    {
        $map = [];
        foreach ($timing as $row) {
            $map[self::timingKey($row['table'], $row['fkid'])] = [
                'deferrable' => $row['deferrable'],
                'initially_deferred' => $row['initially_deferred'],
                'timing' => $row['timing'],
            ];
        }

        return $map;
    }

    /**
     * @param array<string,array{deferrable:bool,initially_deferred:bool,timing:string}> $timing
     * @return array<string,mixed>
     */
    private static function decorateRow(array $row, array $timing): array
    {
        if (($row['kind'] ?? null) !== 'index_admission' && ($row['kind'] ?? null) !== 'foreign_key_check') {
            return $row;
        }

        $rowTiming = $timing[self::timingKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1))] ?? null;
        if ($rowTiming === null) {
            return $row;
        }

        return [
            ...$row,
            ...$rowTiming,
        ];
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,deferrable:bool,initially_deferred:bool,timing:string}> $timing
     * @return list<string>
     */
    private static function timingSummary(array $timing): array
    {
        $summary = array_map(
            static fn (array $row): string => $row['table'] . '#' . $row['fkid'] . '->' . $row['parent'] . ':timing=' . $row['timing'],
            $timing,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,deferrable:bool,initially_deferred:bool,timing:string}> $timing
     * @return array<string,int>
     */
    private static function timingCounts(array $timing): array
    {
        $counts = [];
        foreach ($timing as $row) {
            $counts['timing:' . $row['timing']] = ($counts['timing:' . $row['timing']] ?? 0) + 1;
            $counts['deferrable:' . ($row['deferrable'] ? 'yes' : 'no')] = ($counts['deferrable:' . ($row['deferrable'] ? 'yes' : 'no')] ?? 0) + 1;
            $counts['initially_deferred:' . ($row['initially_deferred'] ? 'yes' : 'no')] = ($counts['initially_deferred:' . ($row['initially_deferred'] ? 'yes' : 'no')] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param list<array{table:string,parent:string,fkid:int,deferrable:bool,initially_deferred:bool,timing:string}> $current
     * @param list<array{table:string,parent:string,fkid:int,deferrable:bool,initially_deferred:bool,timing:string}> $next
     */
    private static function timingChangeCount(array $current, array $next): int
    {
        return count(array_diff(self::timingSummary($next), self::timingSummary($current)))
            + count(array_diff(self::timingSummary($current), self::timingSummary($next)));
    }

    private static function timingKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $start = strpos($sql, '(');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? null) === "'") {
                        $i++;
                        continue;
                    }
                    if ($quote === '"' && ($sql[$i + 1] ?? null) === '"') {
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
                    return substr($sql, $start + 1, $i - $start - 1);
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && ($value[$i + 1] ?? null) === $quote) {
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
            if ($char === $delimiter && $depth === 0) {
                $parts[] = substr($value, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($value, $start);

        return $parts;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next171 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next171 cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
