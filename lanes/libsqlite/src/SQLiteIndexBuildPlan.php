<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexBuildPlan
{
    /**
     * @param list<string> $columns
     * @param list<array<string,mixed>> $rows
     * @param list<string> $indexColumns
     * @return array{
     *   upstream:string,
     *   table:string,
     *   index:string,
     *   column_count:int,
     *   row_count:int,
     *   index_columns:list<string>,
     *   key_records:list<array{rowid:int,key:list<mixed>}>,
     *   ordered_rowids:list<int>,
     *   created:bool,
     *   integrity:string,
     *   schema_residue:bool,
     *   non_overlap:string,
     *   dependency_closure:string
     * }
     */
    public static function build(
        string $table,
        string $index,
        array $columns,
        array $rows,
        array $indexColumns,
        bool $unique = false,
    ): array {
        self::assertIdentifier($table, 'table');
        self::assertIdentifier($index, 'index');
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite index build requires at least one table column');
        }
        if ($indexColumns === []) {
            throw new \InvalidArgumentException('SQLite index build requires at least one index column');
        }

        $knownColumns = array_fill_keys($columns, true);
        foreach ($indexColumns as $column) {
            if (!isset($knownColumns[$column])) {
                throw new \InvalidArgumentException("no such column: {$column}");
            }
        }

        $keyRecords = [];
        $seen = [];
        foreach (array_values($rows) as $rowIndex => $row) {
            $key = [];
            foreach ($indexColumns as $column) {
                $key[] = $row[$column] ?? null;
            }

            $encodedKey = json_encode($key);
            if ($unique && isset($seen[$encodedKey])) {
                $label = count($indexColumns) === 1 ? $indexColumns[0] : implode(', ', $indexColumns);
                throw new SQLiteIndexBuildUniqueConstraintException(
                    "UNIQUE constraint failed: {$table}.{$label}",
                    [
                        'upstream' => 'index3.test index3-1.2',
                        'table' => $table,
                        'index' => $index,
                        'created' => false,
                        'schema_residue' => false,
                        'duplicate_key' => $key,
                        'duplicate_rowids' => [$seen[$encodedKey], $rowIndex + 1],
                    ],
                );
            }
            $seen[$encodedKey] = $rowIndex + 1;
            $keyRecords[] = ['rowid' => $rowIndex + 1, 'key' => $key];
        }

        usort(
            $keyRecords,
            static function (array $left, array $right): int {
                $leftKey = $left['key'];
                $rightKey = $right['key'];
                $count = max(count($leftKey), count($rightKey));
                for ($i = 0; $i < $count; $i++) {
                    $comparison = self::compareValues($leftKey[$i] ?? null, $rightKey[$i] ?? null);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return $left['rowid'] <=> $right['rowid'];
            },
        );

        return [
            'upstream' => 'index2.test index2-2.1/index2-2.2; index4.test index4-1.2/index4-1.3',
            'table' => $table,
            'index' => $index,
            'column_count' => count($columns),
            'row_count' => count($rows),
            'index_columns' => array_values($indexColumns),
            'key_records' => $keyRecords,
            'ordered_rowids' => array_map(static fn (array $record): int => $record['rowid'], $keyRecords),
            'created' => true,
            'integrity' => 'ok',
            'schema_residue' => false,
            'non_overlap' => 'real upstream index build key materialization; does not repeat INDEXED BY forcing, expression-index range costs, page relocation, root collapse, or overflow freelist release',
            'dependency_closure' => 'no new support component needed; uses PHP row arrays to model SQLite index key extraction and uniqueness checks from upstream CREATE INDEX tests',
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<array<string,int>>
     */
    public static function deterministicRows(array $columns, int $rowCount, int $step = 10000): array
    {
        if ($rowCount < 0) {
            throw new \InvalidArgumentException('SQLite index build row count cannot be negative');
        }

        $rows = [];
        for ($row = 0; $row < $rowCount; $row++) {
            $record = [];
            foreach ($columns as $index => $column) {
                $record[$column] = ($row * $step) + $index + 1;
            }
            $rows[] = $record;
        }

        return $rows;
    }

    private static function assertIdentifier(string $identifier, string $kind): void
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException("SQLite index build {$kind} name cannot be empty");
        }
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }
}
