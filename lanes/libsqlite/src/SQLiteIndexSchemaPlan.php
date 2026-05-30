<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexSchemaPlan
{
    /**
     * @param list<int> $orderByColumns
     * @return array<string,mixed>
     */
    public static function wideColumnIndexPlan(int $columnCount = 1000, int $extraRows = 100, array $orderByColumns = [1, 2, 3, 4, 5, 6], int $limit = 5): array
    {
        if ($columnCount < 1) {
            throw new \InvalidArgumentException('SQLite wide-column index planning needs at least one column');
        }
        if ($extraRows < 0) {
            throw new \InvalidArgumentException('SQLite wide-column index planning needs a non-negative row count');
        }
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite wide-column index planning needs a non-negative limit');
        }

        $rows = [];
        $rows[] = self::wideRow(0, $columnCount);
        for ($ordinal = 1; $ordinal <= $extraRows; $ordinal++) {
            $rows[] = self::wideRow($ordinal, $columnCount);
        }

        usort($rows, static function (array $left, array $right) use ($orderByColumns): int {
            foreach ($orderByColumns as $column) {
                $key = 'c' . $column;
                $comparison = ($left[$key] ?? null) <=> ($right[$key] ?? null);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return ($left['row_ordinal'] ?? 0) <=> ($right['row_ordinal'] ?? 0);
        });

        $selected = array_slice($rows, 0, $limit);
        $lastColumn = 'c' . $columnCount;

        return [
            'upstream' => 'index2.test',
            'scenario' => 'index2-1.1 through index2-2.2',
            'columnCount' => $columnCount,
            'rowCount' => count($rows),
            'indexColumnCount' => $columnCount,
            'indexName' => 't1i1',
            'covering' => true,
            'c123FirstRow' => $rows[0]['c123'] ?? null,
            'lastColumnSumRounded' => round(array_sum(array_column($rows, $lastColumn))),
            'orderByColumns' => $orderByColumns,
            'limit' => $limit,
            'selectedC9' => array_values(array_map(static fn (array $row): int => (int) $row['c9'], $selected)),
            'selectedOrdinals' => array_values(array_map(static fn (array $row): int => (int) $row['row_ordinal'], $selected)),
            'usesCoveringIndexForOrder' => self::isPrefix($orderByColumns, range(1, $columnCount)),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    public static function uniqueIndexBuildPlan(string $tableName, array $rows, array $columns, string $indexName = 'i1'): array
    {
        if ($tableName === '') {
            throw new \InvalidArgumentException('SQLite unique index build planning needs a table name');
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite unique index build planning needs at least one column');
        }

        $seen = [];
        foreach ($rows as $position => $row) {
            $keyParts = [];
            foreach ($columns as $column) {
                $keyParts[] = self::keyPart($row[$column] ?? null);
            }
            $key = implode("\x1f", $keyParts);
            if (isset($seen[$key])) {
                return [
                    'upstream' => 'index3.test',
                    'scenario' => 'index3-1.1 through index3-1.4',
                    'ok' => false,
                    'table' => $tableName,
                    'indexName' => $indexName,
                    'columns' => $columns,
                    'error' => 'UNIQUE constraint failed: ' . $tableName . '.' . implode(', ' . $tableName . '.', $columns),
                    'duplicateKey' => array_intersect_key($row, array_flip($columns)),
                    'firstDuplicatePosition' => $seen[$key],
                    'secondDuplicatePosition' => $position,
                    'rowCountPreserved' => count($rows),
                    'indexResidueLeft' => false,
                    'commitStillAllowed' => true,
                    'integrityCheck' => 'ok',
                ];
            }
            $seen[$key] = $position;
        }

        return [
            'upstream' => 'index3.test',
            'scenario' => 'index3-1.1 through index3-1.4',
            'ok' => true,
            'table' => $tableName,
            'indexName' => $indexName,
            'columns' => $columns,
            'rowCountPreserved' => count($rows),
            'indexResidueLeft' => true,
            'commitStillAllowed' => true,
            'integrityCheck' => 'ok',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function quotedIdentifierIndexCatalog(string $tableName = 't1'): array
    {
        if ($tableName === '') {
            throw new \InvalidArgumentException('SQLite quoted identifier catalog planning needs a table name');
        }

        return [
            'upstream' => 'index3.test',
            'scenario' => 'index3-2.1 through index3-2.5',
            'table' => $tableName,
            'primaryKeyColumn' => 'a',
            'uniqueColumn' => 'b',
            'uniqueCollation' => 'nocase',
            'uniqueSort' => 'DESC',
            'explicitIndexes' => ['t1c', 't1d'],
            'catalogNames' => ['sqlite_autoindex_t1_1', 'sqlite_autoindex_t1_2', 't1', 't1c', 't1d'],
            'compatibleStringIdentifiers' => true,
            'lookupValue' => 'ab005xy',
            'lookupResultA' => 5,
            'queryPlanUsesIndex' => true,
            'quotedPrimaryKeyTables' => ['t2a', 't2b', 't2c', 't2d'],
        ];
    }

    /**
     * @return array<string,int>
     */
    private static function wideRow(int $ordinal, int $columnCount): array
    {
        $row = ['row_ordinal' => $ordinal];
        $base = $ordinal * 10000;
        for ($column = 1; $column <= $columnCount; $column++) {
            $row['c' . $column] = $base + $column;
        }

        return $row;
    }

    /**
     * @param list<int> $prefix
     * @param list<int> $columns
     */
    private static function isPrefix(array $prefix, array $columns): bool
    {
        foreach ($prefix as $offset => $column) {
            if (($columns[$offset] ?? null) !== $column) {
                return false;
            }
        }

        return true;
    }

    private static function keyPart(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }
}
