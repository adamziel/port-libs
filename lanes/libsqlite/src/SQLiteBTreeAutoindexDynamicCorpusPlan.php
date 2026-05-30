<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeAutoindexDynamicCorpusPlan
{
    /**
     * @return list<array{a:int,b:int}>
     */
    public static function t1Rows(int $rowCount, int $payloadStep = 11): array
    {
        self::assertPositive($rowCount, 'row count');
        self::assertPositive($payloadStep, 'payload step');

        $rows = [];
        for ($i = 1; $i <= $rowCount; $i++) {
            $rows[] = ['a' => $i, 'b' => $i * $payloadStep];
        }

        return $rows;
    }

    /**
     * @param list<array{a:int,b:int}> $rows
     * @return list<array{c:int,d:int}>
     */
    public static function t2Rows(array $rows, int $payloadOffset = 900): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = ['c' => self::intValue($row, 'a'), 'd' => self::intValue($row, 'b') + $payloadOffset];
        }

        return $result;
    }

    /**
     * @param list<array{a:int,b:int}> $left
     * @param list<array{c:int,d:int}> $right
     * @return array{source:string,automatic_index:bool,index_column:string,warning:?string,step_count:int,autoindex_inserts:int,rows:list<array{b:int,d:int}>,row_count:int}
     */
    public static function join(array $left, array $right, bool $automaticIndex): array
    {
        $rows = [];
        $stepCount = 0;
        $index = [];

        if ($automaticIndex) {
            foreach ($right as $rightRow) {
                $index[self::intValue($rightRow, 'c')][] = $rightRow;
            }
            foreach ($left as $leftRow) {
                $stepCount++;
                foreach ($index[self::intValue($leftRow, 'a')] ?? [] as $rightRow) {
                    $rows[] = ['b' => self::intValue($leftRow, 'b'), 'd' => self::intValue($rightRow, 'd')];
                }
            }
        } else {
            foreach ($left as $leftRow) {
                foreach ($right as $rightRow) {
                    $stepCount++;
                    if (self::intValue($leftRow, 'a') === self::intValue($rightRow, 'c')) {
                        $rows[] = ['b' => self::intValue($leftRow, 'b'), 'd' => self::intValue($rightRow, 'd')];
                    }
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => $a['b'] <=> $b['b']);

        return [
            'source' => $automaticIndex ? 'autoindex1-110' : 'autoindex1-100',
            'automatic_index' => $automaticIndex,
            'index_column' => 't2.c',
            'warning' => $automaticIndex ? 'SQLITE_WARNING_AUTOINDEX automatic index on t2(c)' : null,
            'step_count' => $stepCount,
            'autoindex_inserts' => $automaticIndex ? count($right) : 0,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * @param list<array{a:int,b:int}> $left
     * @param list<array{c:int,d:int}> $right
     * @return array{source:string,automatic_index:bool,correlated:bool,index_column:?string,rows:list<array{b:int,d:int|null}>,row_count:int,step_count:int,autoindex_inserts:int}
     */
    public static function scalarSubquery(array $left, array $right, bool $automaticIndex, bool $correlated): array
    {
        $rows = [];
        $stepCount = 0;
        $index = [];

        if ($automaticIndex && $correlated) {
            foreach ($right as $rightRow) {
                $index[self::intValue($rightRow, 'c')][] = $rightRow;
            }
        }

        foreach ($left as $leftRow) {
            $match = null;
            if ($automaticIndex && $correlated) {
                $stepCount++;
                $match = ($index[self::intValue($leftRow, 'a')] ?? [])[0] ?? null;
            } else {
                foreach ($right as $rightRow) {
                    $stepCount++;
                    if (self::intValue($rightRow, 'c') === self::intValue($leftRow, 'a')) {
                        $match = $rightRow;
                        break;
                    }
                }
            }
            $rows[] = ['b' => self::intValue($leftRow, 'b'), 'd' => $match === null ? null : self::intValue($match, 'd')];
        }

        return [
            'source' => $automaticIndex && $correlated ? 'autoindex1-210' : 'autoindex1-200',
            'automatic_index' => $automaticIndex,
            'correlated' => $correlated,
            'index_column' => $automaticIndex && $correlated ? 't2.c' : null,
            'rows' => $rows,
            'row_count' => count($rows),
            'step_count' => $stepCount,
            'autoindex_inserts' => $automaticIndex && $correlated ? count($right) : 0,
        ];
    }

    /**
     * @param list<array{c:int,d:int}> $right
     * @return array{source:string,rows:list<array{b:int,d:int}>,right_after:list<array{c:int,d:int}>,index_snapshot:list<int>,mutation_count:int}
     */
    public static function joinWithMutatingRightTable(array $left, array $right): array
    {
        $index = [];
        foreach ($right as $rightRow) {
            $index[self::intValue($rightRow, 'c')][] = $rightRow;
        }

        $rows = [];
        $rightAfter = $right;
        foreach ($left as $leftRow) {
            foreach ($index[self::intValue($leftRow, 'a')] ?? [] as $rightRow) {
                $rows[] = ['b' => self::intValue($leftRow, 'b'), 'd' => self::intValue($rightRow, 'd')];
            }
            foreach ($rightAfter as $i => $rightAfterRow) {
                $rightAfter[$i]['d'] = self::intValue($rightAfterRow, 'd') + 1;
            }
        }

        usort($rows, static fn (array $a, array $b): int => $a['b'] <=> $b['b']);
        usort($rightAfter, static fn (array $a, array $b): int => $a['d'] <=> $b['d']);

        return [
            'source' => 'autoindex1-299-310',
            'rows' => $rows,
            'right_after' => $rightAfter,
            'index_snapshot' => array_map(static fn (array $row): int => $row['d'], $rows),
            'mutation_count' => count($left) * count($right),
        ];
    }

    /**
     * @return array{source:string,row_count:int,join_depth:int,path_count:int,terminal_values:list<int>}
     */
    public static function chainJoinCount(int $rowCount, int $joinDepth): array
    {
        self::assertPositive($rowCount, 'row count');
        if ($joinDepth < 2) {
            throw new \InvalidArgumentException('SQLite autoindex chain join depth must be at least 2');
        }

        return [
            'source' => 'autoindex1-400-401',
            'row_count' => $rowCount,
            'join_depth' => $joinDepth,
            'path_count' => max(0, $rowCount - $joinDepth + 1),
            'terminal_values' => $rowCount >= $joinDepth ? [$joinDepth, $rowCount] : [],
        ];
    }

    /**
     * @return array{source:string,outer_access:string,subquery_kind:string,subquery_access:string,autoindex:bool,bloom_filter:bool}
     */
    public static function inSubqueryPlan(bool $correlated, bool $outerPointLookup): array
    {
        $autoindex = $correlated && !$outerPointLookup;

        return [
            'source' => $correlated ? ($outerPointLookup ? 'autoindex1-502' : 'autoindex1-501') : 'autoindex1-500.1',
            'outer_access' => $outerPointLookup ? 'SEARCH t501 USING INTEGER PRIMARY KEY (rowid=?)' : ($correlated ? 'SCAN t501' : 'SEARCH t501 USING INTEGER PRIMARY KEY (rowid=?)'),
            'subquery_kind' => $correlated ? 'CORRELATED LIST SUBQUERY' : 'LIST SUBQUERY',
            'subquery_access' => $autoindex ? 'SEARCH t502 USING AUTOMATIC COVERING INDEX (y=?)' : 'SCAN t502',
            'autoindex' => $autoindex,
            'bloom_filter' => $autoindex,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function intValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite autoindex corpus row {$key} must be an integer");
        }

        return $value;
    }

    private static function assertPositive(int $value, string $label): void
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException("SQLite autoindex {$label} must be positive");
        }
    }
}
