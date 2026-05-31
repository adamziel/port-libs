<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteReturningQueryResultFormatterPlan
{
    private const SOURCE = 'qrf05.test';
    private const STYLE_LIST = 'list';
    private const SUPPORTED_VERSION = 1;

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $returning
     * @return array{
     *     source:string,
     *     scenario:string,
     *     style:string,
     *     version:int,
     *     rc:int,
     *     ok:bool,
     *     error:?string,
     *     before:list<array<string,mixed>>,
     *     attempted:array<string,mixed>,
     *     after:list<array<string,mixed>>,
     *     returning_rows:list<array<string,mixed>>,
     *     formatted:string,
     *     changes:int,
     *     dependencies:list<string>
     * }
     */
    public static function insertReturningList(
        array $rows,
        string $table,
        string $column,
        mixed $value,
        array $returning = ['*'],
        bool $notNull = true,
        int $version = self::SUPPORTED_VERSION
    ): array {
        self::assertRows($rows, 'query-result formatter');
        $table = self::identifier($table, 'table');
        $column = self::identifier($column, 'column');
        $returning = self::returningProjection($returning);

        if ($version !== self::SUPPORTED_VERSION) {
            return self::errorResult(
                $rows,
                $table,
                $column,
                $value,
                $returning,
                $version,
                'unusable sqlite3_qrf_spec.iVersion (' . $version . ')',
                'qrf05-1.3 unsupported query-result formatter version is rejected'
            );
        }

        $attempted = [$column => $value];
        if ($notNull && $value === null) {
            return self::errorResult(
                $rows,
                $table,
                $column,
                $value,
                $returning,
                $version,
                'NOT NULL constraint failed: ' . $table . '.' . $column,
                'qrf05-1.2 NOT NULL failure is reported before RETURNING formatting'
            );
        }

        $returningRows = [self::project($attempted, $returning)];
        $after = array_values($rows);
        $after[] = $attempted;

        return [
            'source' => self::SOURCE,
            'scenario' => 'qrf05-1.1 INSERT RETURNING is formatted as a list row',
            'style' => self::STYLE_LIST,
            'version' => $version,
            'rc' => 0,
            'ok' => true,
            'error' => null,
            'before' => array_values($rows),
            'attempted' => $attempted,
            'after' => $after,
            'returning_rows' => $returningRows,
            'formatted' => self::formatListRows($returningRows),
            'changes' => 1,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $returning
     * @return array{
     *     source:string,
     *     scenario:string,
     *     style:string,
     *     version:int,
     *     rc:int,
     *     ok:bool,
     *     error:string,
     *     before:list<array<string,mixed>>,
     *     attempted:array<string,mixed>,
     *     after:list<array<string,mixed>>,
     *     returning_rows:list<array<string,mixed>>,
     *     formatted:string,
     *     changes:int,
     *     dependencies:list<string>
     * }
     */
    private static function errorResult(
        array $rows,
        string $table,
        string $column,
        mixed $value,
        array $returning,
        int $version,
        string $error,
        string $scenario
    ): array {
        return [
            'source' => self::SOURCE,
            'scenario' => $scenario,
            'style' => self::STYLE_LIST,
            'version' => $version,
            'rc' => 1,
            'ok' => false,
            'error' => $error,
            'before' => array_values($rows),
            'attempted' => [$column => $value],
            'after' => array_values($rows),
            'returning_rows' => [],
            'formatted' => '',
            'changes' => 0,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function assertRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite {$label} rows must be a list");
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite {$label} row {$index} must be an array");
            }
            foreach ($row as $column => $_value) {
                if (!is_string($column) || $column === '') {
                    throw new InvalidArgumentException("SQLite {$label} row {$index} has invalid column name");
                }
            }
        }
    }

    private static function identifier(string $identifier, string $label): string
    {
        if ($identifier === '' || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite query-result formatter {$label} is malformed");
        }

        return $identifier;
    }

    /**
     * @param list<string> $returning
     * @return list<string>
     */
    private static function returningProjection(array $returning): array
    {
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite query-result formatter RETURNING projection cannot be empty');
        }

        foreach ($returning as $column) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('SQLite query-result formatter RETURNING projection is malformed');
            }
        }

        return array_values($returning);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $returning
     * @return array<string,mixed>
     */
    private static function project(array $row, array $returning): array
    {
        if ($returning === ['*']) {
            return $row;
        }

        $projected = [];
        foreach ($returning as $column) {
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException("SQLite query-result formatter row missing RETURNING column {$column}");
            }
            $projected[$column] = $row[$column];
        }

        return $projected;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function formatListRows(array $rows): string
    {
        $values = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $values[] = self::formatListValue($value);
            }
        }

        return implode(' ', $values);
    }

    private static function formatListValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException('SQLite query-result formatter list value is not scalar');
    }

    /**
     * @return list<string>
     */
    private static function dependencies(): array
    {
        return [
            'qrf05.test-1.1',
            'qrf05.test-1.2',
            'qrf05.test-1.3',
            'sqlite-query-result-formatter-returning-list',
        ];
    }
}
