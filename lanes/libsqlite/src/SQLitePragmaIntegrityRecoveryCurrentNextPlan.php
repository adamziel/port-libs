<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityRecoveryCurrentNextPlan
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param list<array<string,mixed>> $recoveryOperations
     * @return array{status:string,reason:string,integrity_sql:string,current:array<string,mixed>,next:array<string,mixed>,resolved:list<array<string,mixed>>,persisting:list<array<string,mixed>>,introduced:list<array<string,mixed>>,resolved_count:int,persisting_count:int,introduced_count:int,recovery_operations:list<array<string,mixed>>,must_block_commit:bool,dependencies:list<string>}
     */
    public static function compare(
        string|SQLiteDatabase $currentDatabase,
        string|SQLiteDatabase $nextDatabase,
        array $currentSchemas = [],
        array $nextSchemas = [],
        string $integritySql = 'PRAGMA integrity_check',
        array $recoveryOperations = [],
    ): array {
        $current = self::snapshot($currentDatabase, $currentSchemas, $integritySql);
        $next = self::snapshot($nextDatabase, $nextSchemas, $integritySql);
        $currentByKey = self::rowsByKey($current['rows']);
        $nextByKey = self::rowsByKey($next['rows']);

        $resolved = [];
        foreach ($currentByKey as $key => $row) {
            if (!isset($nextByKey[$key])) {
                $resolved[] = $row;
            }
        }

        $persisting = [];
        foreach ($currentByKey as $key => $row) {
            if (isset($nextByKey[$key])) {
                $persisting[] = $row;
            }
        }

        $introduced = [];
        foreach ($nextByKey as $key => $row) {
            if (!isset($currentByKey[$key])) {
                $introduced[] = $row;
            }
        }

        $status = match (true) {
            $introduced !== [] => 'recovery_introduced_integrity_findings',
            $persisting !== [] && $resolved !== [] => 'recovery_partially_resolved_integrity_findings',
            $persisting !== [] => 'recovery_preserved_integrity_findings',
            $resolved !== [] => 'recovery_resolved_integrity_findings',
            default => 'recovery_integrity_clean',
        };

        return [
            'status' => $status,
            'reason' => 'current_dirty_database_next_recovered_integrity_snapshot',
            'integrity_sql' => $integritySql,
            'current' => $current,
            'next' => $next,
            'resolved' => array_values($resolved),
            'persisting' => array_values($persisting),
            'introduced' => array_values($introduced),
            'resolved_count' => count($resolved),
            'persisting_count' => count($persisting),
            'introduced_count' => count($introduced),
            'recovery_operations' => array_values($recoveryOperations),
            'must_block_commit' => $introduced !== [] || $persisting !== [],
            'dependencies' => [
                'sqlite-pragma-integrity-current-next-yield',
                'sqlite-recovery-current-next-integrity-gate',
                'sqlite-pager-recovery-apply',
            ],
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{total:int,counts:array<string,int>,rows:list<array<string,mixed>>,messages:list<string>}
     */
    private static function snapshot(string|SQLiteDatabase $database, array $schemas, string $integritySql): array
    {
        $rows = SQLitePragmaIntegrityCurrentNextYield::collect($database, $schemas, $integritySql);

        return [
            'total' => count($rows),
            'counts' => self::counts($rows),
            'rows' => $rows,
            'messages' => array_map(static fn (array $row): string => (string) $row['message'], $rows),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function rowsByKey(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = implode('|', [
                (string) ($row['kind'] ?? ''),
                (string) ($row['source'] ?? ''),
                (string) ($row['schema'] ?? ''),
                (string) ($row['table'] ?? ''),
                (string) ($row['rowid'] ?? ''),
                (string) ($row['parent'] ?? ''),
                (string) ($row['fkid'] ?? ''),
                (string) ($row['page'] ?? ''),
                (string) ($row['pointer_map_page'] ?? ''),
                (string) ($row['message'] ?? ''),
            ]);
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException('SQLite PRAGMA integrity recovery comparison received duplicate diagnostic rows');
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'integrity' => 0,
            'header' => 0,
            'freelist' => 0,
            'schema_root' => 0,
            'pointer_map' => 0,
            'btree' => 0,
            'foreign_key' => 0,
        ];
        foreach ($rows as $row) {
            $source = (string) ($row['source'] ?? 'integrity');
            $counts[$source] = ($counts[$source] ?? 0) + 1;
        }

        return $counts;
    }
}
