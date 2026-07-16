<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTableApiResult
{
    /**
     * @param list<string> $columns
     * @param list<array<int|string, int|float|string|null>> $rows
     * @return array{
     *     status:string,
     *     row_count:int,
     *     column_count:int,
     *     empty_result_callbacks:bool,
     *     headers:list<string>,
     *     cells:list<int|float|string|null>,
     *     flat:list<int|float|string|null>,
     *     rows:list<array<int|string, int|float|string|null>>,
     *     source:string
     * }
     */
    public static function format(array $columns, array $rows, bool $emptyResultCallbacks = false): array
    {
        $headers = self::normalizeColumns($columns);
        $rowCount = count($rows);
        $columnCount = $rowCount === 0 && !$emptyResultCallbacks ? 0 : count($headers);
        $cells = $columnCount === 0 ? [] : $headers;

        foreach ($rows as $row) {
            foreach (self::rowValues($headers, $row) as $value) {
                $cells[] = $value;
            }
        }

        return [
            'status' => 'ok',
            'row_count' => $rowCount,
            'column_count' => $columnCount,
            'empty_result_callbacks' => $emptyResultCallbacks,
            'headers' => $columnCount === 0 ? [] : $headers,
            'cells' => $cells,
            'flat' => [0, $rowCount, $columnCount, ...$cells],
            'rows' => $rows,
            'source' => 'SQLite test/tableapi.test tableapi-2.7 and tableapi-3.7 empty_result_callbacks result headers',
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function normalizeColumns(array $columns): array
    {
        if ($columns === []) {
            throw new InvalidArgumentException('SQLite table API result needs at least one column');
        }

        $normalized = [];
        foreach ($columns as $column) {
            $name = trim($column);
            if ($name === '') {
                throw new InvalidArgumentException('SQLite table API result column names cannot be empty');
            }

            $normalized[] = $name;
        }

        return $normalized;
    }

    /**
     * @param list<string> $columns
     * @param array<int|string, int|float|string|null> $row
     * @return list<int|float|string|null>
     */
    private static function rowValues(array $columns, array $row): array
    {
        $values = [];
        $isList = array_is_list($row);
        foreach ($columns as $offset => $column) {
            $values[] = $isList ? ($row[$offset] ?? null) : ($row[$column] ?? null);
        }

        return $values;
    }
}
