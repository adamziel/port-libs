<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext169
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
        int $limit = 169,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext165::currentNextPageFromCatalog(
            $currentRecords,
            $currentTables,
            $nextRecords,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            $cursor,
            $tableValuedIndexXinfo,
        );

        $currentDeferrals = self::deferralMap($currentRecords);
        $nextDeferrals = self::deferralMap($nextRecords);
        $page['rows'] = array_map(
            static function (array $row) use ($currentDeferrals, $nextDeferrals): array {
                if (($row['kind'] ?? null) !== 'index_admission' && ($row['kind'] ?? null) !== 'foreign_key_check') {
                    return $row;
                }

                $map = ($row['side'] ?? 'current') === 'next' ? $nextDeferrals : $currentDeferrals;
                $deferral = $map[self::deferralKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1))] ?? null;
                if ($deferral === null) {
                    return $row;
                }

                return [
                    ...$row,
                    'deferrable' => $deferral['deferrable'],
                    'initially_deferred' => $deferral['initially_deferred'],
                    'deferred_until_commit' => $deferral['deferrable'] && $deferral['initially_deferred'],
                    'deferral_summary' => self::summaryName($deferral),
                ];
            },
            $page['rows'],
        );

        return [
            ...$page,
            'current_source' => [
                ...$page['current_source'],
                'deferral_source' => 'sqlite_schema_foreign_key_clause',
                'deferral_summary' => self::deferralSummary($currentDeferrals),
            ],
            'next_source' => [
                ...$page['next_source'],
                'deferral_source' => 'sqlite_schema_foreign_key_clause',
                'deferral_summary' => self::deferralSummary($nextDeferrals),
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,array{deferrable:bool,initially_deferred:bool}>
     */
    public static function deferralMap(array $records): array
    {
        $map = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next169 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }

            $clauses = self::foreignKeyClauses($record->sql);
            foreach ($clauses as $id => $clause) {
                $map[self::deferralKey($record->name, $id)] = self::clauseDeferral($clause);
            }
        }

        return $map;
    }

    /**
     * @param array<string,array{deferrable:bool,initially_deferred:bool}> $map
     * @return array<string,int>
     */
    private static function deferralSummary(array $map): array
    {
        $summary = [
            'immediate' => 0,
            'deferrable_immediate' => 0,
            'deferrable_deferred' => 0,
        ];
        foreach ($map as $deferral) {
            $summary[self::summaryName($deferral)]++;
        }

        return $summary;
    }

    /** @param array{deferrable:bool,initially_deferred:bool} $deferral */
    private static function summaryName(array $deferral): string
    {
        if (!$deferral['deferrable']) {
            return 'immediate';
        }

        return $deferral['initially_deferred'] ? 'deferrable_deferred' : 'deferrable_immediate';
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
            if (preg_match('/\bREFERENCES\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)/is', $definition) === 1) {
                $clauses[] = $definition;
            }
        }

        return $clauses;
    }

    /**
     * @return array{deferrable:bool,initially_deferred:bool}
     */
    private static function clauseDeferral(string $clause): array
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', ' ', $clause));
        if (preg_match('/\bNOT\s+DEFERRABLE\b/', $normalized) === 1) {
            return ['deferrable' => false, 'initially_deferred' => false];
        }
        if (preg_match('/\bDEFERRABLE\b/', $normalized) !== 1) {
            return ['deferrable' => false, 'initially_deferred' => false];
        }

        return [
            'deferrable' => true,
            'initially_deferred' => preg_match('/\bINITIALLY\s+DEFERRED\b/', $normalized) === 1,
        ];
    }

    private static function deferralKey(string $table, int $id): string
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
                    if (($quote === "'" || $quote === '"') && ($sql[$i + 1] ?? null) === $quote) {
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
                $parts[] = substr($sql, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($sql, $start);

        return $parts;
    }
}
