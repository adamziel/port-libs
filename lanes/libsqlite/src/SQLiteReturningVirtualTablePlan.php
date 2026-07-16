<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteReturningVirtualTablePlan
{
    /**
     * @param array<string,mixed> $assignments
     * @param list<string> $returning
     * @return array{source:string,scenario:string,table:string,ok:false,error:string,assignments:array<string,mixed>,assignments_applied:bool,returning_projection:list<string>,returning_evaluated:bool,returning_rows:list<array<string,mixed>>,changes:int,dependencies:list<string>}
     */
    public static function updateReadOnlyPragmaReturning(string $pragmaTable, array $assignments, array $returning): array
    {
        $table = self::normalizePragmaTable($pragmaTable);
        if ($assignments === []) {
            throw new InvalidArgumentException('SQLite RETURNING pragma update requires at least one assignment');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'pragma assignment column');
        }
        foreach ($returning as $column) {
            if ($column !== '*') {
                self::identifier($column, 'RETURNING column');
            }
        }

        return [
            'source' => 'returning1.test',
            'scenario' => 'returning1-9.1 read-only pragma virtual table rejects UPDATE before RETURNING',
            'table' => $table,
            'ok' => false,
            'error' => "table {$table} may not be modified",
            'assignments' => $assignments,
            'assignments_applied' => false,
            'returning_projection' => $returning,
            'returning_evaluated' => false,
            'returning_rows' => [],
            'changes' => 0,
            'dependencies' => [
                'sqlite-returning-readonly-pragma-virtual-table',
                'returning1.test-9.1',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int|float,c:int|float}> $rtreeRows
     * @param list<array<string,mixed>> $subqueryRows
     * @param array{a:int,b:int|float,c:int|float} $incoming
     * @return array{source:string,scenario:string,virtual_table:string,inserted:array{a:int,b:int|float,c:int|float},after:list<array{a:int,b:int|float,c:int|float}>,returning_rows:list<array{returning_value:mixed}>,returning_evaluated:bool,changes:int,subquery_column:string,subquery_source_count:int,dependencies:list<string>}
     */
    public static function insertRtreeReturningScalarSubquery(array $rtreeRows, array $subqueryRows, array $incoming, string $subqueryColumn = 'b'): array
    {
        $column = self::identifier($subqueryColumn, 'scalar subquery column');
        $inserted = self::normalizeRtreeRow($incoming);
        $after = [];
        foreach ($rtreeRows as $row) {
            $after[] = self::normalizeRtreeRow($row);
        }
        $after[] = $inserted;

        $returningValue = null;
        if ($subqueryRows !== []) {
            $first = $subqueryRows[0];
            $returningValue = $first[$column] ?? null;
        }

        return [
            'source' => 'returning1.test',
            'scenario' => 'returning1-13.1 rtree INSERT RETURNING scalar subquery evaluates after virtual-table admission',
            'virtual_table' => 'rtree',
            'inserted' => $inserted,
            'after' => $after,
            'returning_rows' => [['returning_value' => $returningValue]],
            'returning_evaluated' => true,
            'changes' => 1,
            'subquery_column' => $column,
            'subquery_source_count' => count($subqueryRows),
            'dependencies' => [
                'sqlite-returning-rtree-scalar-subquery',
                'returning1.test-13.1',
            ],
        ];
    }

    /**
     * @param list<array<string,string>> $ftsRows
     * @param list<array<string,mixed>> $peerRows
     * @param array<string,mixed> $incoming
     * @return array{source:string,scenario:string,virtual_table:string,peer_table:string,peer_rows:list<array<string,mixed>>,inserted:array<string,string>,after:list<array<string,string>>,returning_rows:list<array<string,string>>,returning_evaluated:bool,changes:int,peer_write_visible:bool,dependencies:list<string>}
     */
    public static function insertFts5ReturningAfterPeerWrite(array $ftsRows, array $peerRows, array $incoming, string $column = 'c', string $peerTable = 't2'): array
    {
        $contentColumn = self::identifier($column, 'FTS5 content column');
        $peerTableName = self::identifier($peerTable, 'peer table');
        $inserted = self::normalizeFts5Row($incoming, $contentColumn);

        $after = [];
        foreach ($ftsRows as $row) {
            $after[] = self::normalizeFts5Row($row, $contentColumn);
        }
        $after[] = $inserted;

        return [
            'source' => 'returning1.test',
            'scenario' => 'returning1-24.3 fts5 INSERT RETURNING emits inserted row after peer write',
            'virtual_table' => 'fts5',
            'peer_table' => $peerTableName,
            'peer_rows' => $peerRows,
            'inserted' => $inserted,
            'after' => $after,
            'returning_rows' => [$inserted],
            'returning_evaluated' => true,
            'changes' => 1,
            'peer_write_visible' => $peerRows !== [],
            'dependencies' => [
                'sqlite-returning-fts5-virtual-table',
                'sqlite-returning-peer-schema-refresh',
                'returning1.test-24.3',
            ],
        ];
    }

    private static function normalizePragmaTable(string $pragmaTable): string
    {
        $table = strtolower(trim($pragmaTable));
        self::identifier($table, 'pragma virtual table');
        if (!str_starts_with($table, 'pragma_')) {
            throw new InvalidArgumentException('SQLite RETURNING pragma virtual table must use pragma_ prefix');
        }

        return $table;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{a:int,b:int|float,c:int|float}
     */
    private static function normalizeRtreeRow(array $row): array
    {
        foreach (['a', 'b', 'c'] as $column) {
            if (!array_key_exists($column, $row) || !is_int($row[$column]) && !is_float($row[$column])) {
                throw new InvalidArgumentException("SQLite RTREE RETURNING row requires numeric {$column}");
            }
        }

        return [
            'a' => (int) $row['a'],
            'b' => $row['b'],
            'c' => $row['c'],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private static function normalizeFts5Row(array $row, string $column): array
    {
        if (!array_key_exists($column, $row) || !is_string($row[$column])) {
            throw new InvalidArgumentException("SQLite FTS5 RETURNING row requires text {$column}");
        }

        return [$column => $row[$column]];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite RETURNING invalid {$label}");
        }

        return $value;
    }
}
