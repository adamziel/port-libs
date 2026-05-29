<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext173
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
        int $limit = 173,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
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

        $currentDeferrals = self::deferralRows($currentRecords);
        $nextDeferrals = self::deferralRows($nextRecords);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next173',
            'base' => $page['source_id'],
            'current_deferrals' => self::deferralSummary($currentDeferrals),
            'next_deferrals' => self::deferralSummary($nextDeferrals),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentMap = self::deferralMap($currentDeferrals);
        $nextMap = self::deferralMap($nextDeferrals);

        return [
            ...$page,
            'source_id' => $sourceId,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_deferral_source' => 'create_table_foreign_key_clause',
                'foreign_key_deferrals' => self::deferralSummary($currentDeferrals),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_deferral_source' => 'create_table_foreign_key_clause',
                'foreign_key_deferrals' => self::deferralSummary($nextDeferrals),
            ],
            'current' => [
                ...$page['current'],
                'foreign_key_deferrals' => self::deferralCounts($currentDeferrals),
            ],
            'next_counts' => [
                ...$page['next_counts'],
                'foreign_key_deferrals' => self::deferralCounts($nextDeferrals),
            ],
            'delta' => [
                ...$page['delta'],
                'foreign_key_deferral_changes' => self::deferralChangeCount($currentDeferrals, $nextDeferrals),
                'foreign_key_deferral_changed' => self::deferralSummary($currentDeferrals) !== self::deferralSummary($nextDeferrals),
            ],
            'next' => $page['next'] === null ? null : [
                'source_id' => $sourceId,
                'offset' => $page['next']['offset'],
            ],
            'rows' => array_map(
                static fn (array $row): array => self::decorateRow($row, ($row['side'] ?? 'current') === 'next' ? $nextMap : $currentMap),
                $page['rows'],
            ),
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{table:string,fkid:int,deferred:bool,initially:string,deferrable:string}>
     */
    public static function deferralRows(array $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next173 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }

            $foreignKeys = self::foreignKeyClauses($record->sql);
            foreach ($foreignKeys as $id => $clause) {
                $rows[] = [
                    'table' => $record->name,
                    'fkid' => $id,
                    'deferred' => self::isDeferred($clause),
                    'initially' => self::initially($clause),
                    'deferrable' => self::deferrable($clause),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array{table:string,fkid:int,deferred:bool,initially:string,deferrable:string}> $deferrals
     * @return list<string>
     */
    private static function deferralSummary(array $deferrals): array
    {
        $summary = array_map(
            static fn (array $row): string => $row['table'] . '#' . $row['fkid'] . ':' . $row['deferrable'] . ',initially=' . $row['initially'],
            $deferrals,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<array{table:string,fkid:int,deferred:bool,initially:string,deferrable:string}> $deferrals
     * @return array<string,int>
     */
    private static function deferralCounts(array $deferrals): array
    {
        $counts = [
            'deferrable' => 0,
            'not_deferrable' => 0,
            'initially_deferred' => 0,
            'initially_immediate' => 0,
            'deferred_runtime' => 0,
        ];
        foreach ($deferrals as $row) {
            if ($row['deferrable'] === 'DEFERRABLE') {
                $counts['deferrable']++;
            } else {
                $counts['not_deferrable']++;
            }
            if ($row['initially'] === 'DEFERRED') {
                $counts['initially_deferred']++;
            } else {
                $counts['initially_immediate']++;
            }
            if ($row['deferred']) {
                $counts['deferred_runtime']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array{table:string,fkid:int,deferred:bool,initially:string,deferrable:string}> $deferrals
     * @return array<string,array{deferred:bool,initially:string,deferrable:string}>
     */
    private static function deferralMap(array $deferrals): array
    {
        $map = [];
        foreach ($deferrals as $row) {
            $map[self::deferralKey($row['table'], $row['fkid'])] = [
                'deferred' => $row['deferred'],
                'initially' => $row['initially'],
                'deferrable' => $row['deferrable'],
            ];
        }

        return $map;
    }

    /**
     * @param array<string,array{deferred:bool,initially:string,deferrable:string}> $deferrals
     * @return array<string,mixed>
     */
    private static function decorateRow(array $row, array $deferrals): array
    {
        if (($row['kind'] ?? null) !== 'index_admission' && ($row['kind'] ?? null) !== 'foreign_key_check') {
            return $row;
        }

        $deferral = $deferrals[self::deferralKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1))] ?? null;
        if ($deferral === null) {
            return $row;
        }

        return [
            ...$row,
            'deferrable' => $deferral['deferrable'],
            'initially' => $deferral['initially'],
            'deferred' => $deferral['deferred'],
            'deferral_summary' => $deferral['deferrable'] . '/INITIALLY ' . $deferral['initially'],
        ];
    }

    /**
     * @param list<array{table:string,fkid:int,deferred:bool,initially:string,deferrable:string}> $current
     * @param list<array{table:string,fkid:int,deferred:bool,initially:string,deferrable:string}> $next
     */
    private static function deferralChangeCount(array $current, array $next): int
    {
        return count(array_diff(self::deferralSummary($next), self::deferralSummary($current)))
            + count(array_diff(self::deferralSummary($current), self::deferralSummary($next)));
    }

    /**
     * @return list<string>
     */
    private static function foreignKeyClauses(string $sql): array
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return [];
        }

        $clauses = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if (preg_match('/^(?:CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s+)?FOREIGN\s+KEY\b/is', $definition) === 1) {
                $clauses[] = $definition;
                continue;
            }
            if (preg_match('/^(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\b.*\bREFERENCES\b/is', $definition) === 1) {
                $clauses[] = $definition;
            }
        }

        return $clauses;
    }

    private static function isDeferred(string $clause): bool
    {
        return preg_match('/\bDEFERRABLE\s+INITIALLY\s+DEFERRED\b/i', $clause) === 1;
    }

    private static function initially(string $clause): string
    {
        if (preg_match('/\bINITIALLY\s+DEFERRED\b/i', $clause) === 1) {
            return 'DEFERRED';
        }

        return 'IMMEDIATE';
    }

    private static function deferrable(string $clause): string
    {
        if (preg_match('/\bNOT\s+DEFERRABLE\b/i', $clause) === 1) {
            return 'NOT DEFERRABLE';
        }
        if (preg_match('/\bDEFERRABLE\b/i', $clause) === 1) {
            return 'DEFERRABLE';
        }

        return 'NOT DEFERRABLE';
    }

    private static function deferralKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next173 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next173 cursor offset does not match the requested page offset');
        }
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
            } elseif ($char === ')') {
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
                    if (($quote === "'" || $quote === '"') && ($value[$i + 1] ?? '') === $quote) {
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

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
