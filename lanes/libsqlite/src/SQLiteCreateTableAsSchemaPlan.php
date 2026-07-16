<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteCreateTableAsSchemaPlan
{
    /**
     * Model table.test CREATE TABLE AS SELECT schema text and materialized rows.
     *
     * @param list<array<string,mixed>>|null $sourceRows Null represents a missing source table.
     * @param array<string,string> $sourceDeclaredTypes
     * @return array{
     *     status:string,
     *     source:string,
     *     table:string,
     *     source_table:string,
     *     temporary:bool,
     *     columns:list<array{name:string,type:string,expression:string,quoted_name:string,sql_fragment:string}>,
     *     create_sql:string|null,
     *     result_rows:list<array<string,mixed>>,
     *     persists_after_reopen:bool,
     *     error:string|null,
     *     dependencies:list<string>
     * }
     */
    public static function materialize(string $createTableAsSql, ?array $sourceRows, array $sourceDeclaredTypes = []): array
    {
        $parsed = self::parseCreateTableAs($createTableAsSql);
        if ($sourceRows === null) {
            return [
                'status' => 'error',
                'source' => 'table.test table-8.1 through table-8.10',
                'table' => $parsed['table'],
                'source_table' => $parsed['source_table'],
                'temporary' => $parsed['temporary'],
                'columns' => [],
                'create_sql' => null,
                'result_rows' => [],
                'persists_after_reopen' => false,
                'error' => 'no such table: ' . $parsed['source_table'],
                'dependencies' => [
                    'sqlite-create-table-as-select',
                    'sqlite-ctas-column-affinity',
                    'sqlite-ctas-schema-sql',
                ],
            ];
        }

        $sourceColumns = self::sourceColumns($sourceRows, $sourceDeclaredTypes);
        $projection = self::parseProjection($parsed['select'], $sourceColumns, $sourceDeclaredTypes);
        $rows = self::evaluateRows($projection, $sourceRows, $sourceColumns);
        $columns = array_map(static function (array $column): array {
            $quotedName = self::quoteIdentifier($column['name']);
            $sqlFragment = $quotedName . ($column['type'] === '' ? '' : ' ' . $column['type']);

            return [
                'name' => $column['name'],
                'type' => $column['type'],
                'expression' => $column['expression'],
                'quoted_name' => $quotedName,
                'sql_fragment' => $sqlFragment,
            ];
        }, $projection['columns']);

        return [
            'status' => 'ok',
            'source' => 'table.test table-8.1 through table-8.10',
            'table' => $parsed['table'],
            'source_table' => $parsed['source_table'],
            'temporary' => $parsed['temporary'],
            'columns' => $columns,
            'create_sql' => self::createSql($parsed['table'], $columns, $parsed['temporary']),
            'result_rows' => $rows,
            'persists_after_reopen' => !$parsed['temporary'],
            'error' => null,
            'dependencies' => [
                'sqlite-create-table-as-select',
                'sqlite-ctas-column-affinity',
                'sqlite-ctas-schema-sql',
            ],
        ];
    }

    /**
     * @return array{table:string,source_table:string,temporary:bool,select:string}
     */
    private static function parseCreateTableAs(string $sql): array
    {
        $identifier = self::identifierPattern();
        if (
            preg_match(
                '/^\s*CREATE\s+(?<temporary>TEMP(?:ORARY)?\s+)?TABLE\s+(?<table>' . $identifier . ')\s+AS\s+SELECT\s+(?<select>.*?)\s+FROM\s+(?<source>' . $identifier . ')\s*;?\s*$/is',
                $sql,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException('SQLite CTAS schema plan requires CREATE TABLE name AS SELECT ... FROM source SQL');
        }

        return [
            'table' => self::unquoteIdentifier($matches['table']),
            'source_table' => self::unquoteIdentifier($matches['source']),
            'temporary' => trim((string) ($matches['temporary'] ?? '')) !== '',
            'select' => trim($matches['select']),
        ];
    }

    /**
     * @param list<array<string,mixed>> $sourceRows
     * @param array<string,string> $sourceDeclaredTypes
     * @return list<string>
     */
    private static function sourceColumns(array $sourceRows, array $sourceDeclaredTypes): array
    {
        if ($sourceRows !== []) {
            /** @var array<string,mixed> $first */
            $first = $sourceRows[0];

            return array_keys($first);
        }

        return array_keys($sourceDeclaredTypes);
    }

    /**
     * @param list<string> $sourceColumns
     * @param array<string,string> $sourceDeclaredTypes
     * @return array{columns:list<array{name:string,type:string,expression:string,kind:string,column?:string,left?:string,right?:string}>,aggregate:bool}
     */
    private static function parseProjection(string $select, array $sourceColumns, array $sourceDeclaredTypes): array
    {
        $columns = [];
        $aggregate = false;
        foreach (self::splitTopLevelComma($select) as $rawExpression) {
            $expression = trim($rawExpression);
            if ($expression === '*') {
                foreach ($sourceColumns as $column) {
                    $columns[] = [
                        'name' => $column,
                        'type' => self::ctasType($sourceDeclaredTypes[$column] ?? ''),
                        'expression' => '*.' . $column,
                        'kind' => 'source_column',
                        'column' => $column,
                    ];
                }
                continue;
            }

            if (preg_match('/^count\s*\(\s*\*\s*\)(?:\s+AS\s+(?<alias>' . self::identifierPattern() . '))?$/i', $expression, $matches) === 1) {
                $aggregate = true;
                $columns[] = [
                    'name' => isset($matches['alias']) && $matches['alias'] !== '' ? self::unquoteIdentifier($matches['alias']) : 'count(*)',
                    'type' => '',
                    'expression' => 'count(*)',
                    'kind' => 'count',
                ];
                continue;
            }

            if (
                preg_match(
                    '/^max\s*\(\s*(?<left>' . self::identifierPattern() . ')\s*\+\s*(?<right>' . self::identifierPattern() . ')\s*\)(?:\s+AS\s+(?<alias>' . self::identifierPattern() . '))?$/i',
                    $expression,
                    $matches
                ) === 1
            ) {
                $aggregate = true;
                $left = self::unquoteIdentifier($matches['left']);
                $right = self::unquoteIdentifier($matches['right']);
                self::assertSourceColumn($left, $sourceColumns);
                self::assertSourceColumn($right, $sourceColumns);
                $columns[] = [
                    'name' => isset($matches['alias']) && $matches['alias'] !== '' ? self::unquoteIdentifier($matches['alias']) : "max({$left}+{$right})",
                    'type' => '',
                    'expression' => "max({$left}+{$right})",
                    'kind' => 'max_sum',
                    'left' => $left,
                    'right' => $right,
                ];
                continue;
            }

            if (preg_match('/^(?<column>' . self::identifierPattern() . ')(?:\s+AS\s+(?<alias>' . self::identifierPattern() . '))?$/i', $expression, $matches) === 1) {
                $column = self::unquoteIdentifier($matches['column']);
                self::assertSourceColumn($column, $sourceColumns);
                $columns[] = [
                    'name' => isset($matches['alias']) && $matches['alias'] !== '' ? self::unquoteIdentifier($matches['alias']) : $column,
                    'type' => self::ctasType($sourceDeclaredTypes[$column] ?? ''),
                    'expression' => $column,
                    'kind' => 'source_column',
                    'column' => $column,
                ];
                continue;
            }

            throw new InvalidArgumentException("SQLite CTAS schema plan unsupported SELECT expression {$expression}");
        }

        if ($columns === []) {
            throw new InvalidArgumentException('SQLite CTAS schema plan SELECT list cannot be empty');
        }

        return ['columns' => $columns, 'aggregate' => $aggregate];
    }

    /**
     * @param array{columns:list<array{name:string,type:string,expression:string,kind:string,column?:string,left?:string,right?:string}>,aggregate:bool} $projection
     * @param list<array<string,mixed>> $sourceRows
     * @param list<string> $sourceColumns
     * @return list<array<string,mixed>>
     */
    private static function evaluateRows(array $projection, array $sourceRows, array $sourceColumns): array
    {
        if ($projection['aggregate']) {
            $row = [];
            foreach ($projection['columns'] as $column) {
                if ($column['kind'] === 'count') {
                    $row[$column['name']] = count($sourceRows);
                    continue;
                }
                if ($column['kind'] === 'max_sum') {
                    $max = null;
                    foreach ($sourceRows as $sourceRow) {
                        $value = ($sourceRow[$column['left']] ?? null) + ($sourceRow[$column['right']] ?? null);
                        $max = $max === null ? $value : max($max, $value);
                    }
                    $row[$column['name']] = $max;
                    continue;
                }
                throw new InvalidArgumentException('SQLite CTAS schema plan cannot mix aggregate and source-column projections');
            }

            return [$row];
        }

        $rows = [];
        foreach ($sourceRows as $sourceRow) {
            $row = [];
            foreach ($projection['columns'] as $column) {
                $sourceColumn = (string) ($column['column'] ?? '');
                self::assertSourceColumn($sourceColumn, $sourceColumns);
                $row[$column['name']] = $sourceRow[$sourceColumn] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private static function ctasType(string $declaredType): string
    {
        $type = strtoupper(trim($declaredType));
        if ($type === '') {
            return '';
        }
        if (str_contains($type, 'INT')) {
            return 'INT';
        }
        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return 'TEXT';
        }
        if (str_contains($type, 'BLOB')) {
            return '';
        }
        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return 'REAL';
        }

        return 'NUM';
    }

    /**
     * @param list<array{name:string,type:string,expression:string,quoted_name:string,sql_fragment:string}> $columns
     */
    private static function createSql(string $table, array $columns, bool $temporary): string
    {
        $prefix = 'CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE ' . self::quoteIdentifier($table);
        $fragments = array_map(static fn (array $column): string => $column['sql_fragment'], $columns);

        return $prefix . "(\n  " . implode(",\n  ", $fragments) . "\n)";
    }

    /**
     * @param list<string> $sourceColumns
     */
    private static function assertSourceColumn(string $column, array $sourceColumns): void
    {
        if (!in_array($column, $sourceColumns, true)) {
            throw new InvalidArgumentException("SQLite CTAS schema plan source column {$column} does not exist");
        }
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelComma(string $sql): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $quoteEnd = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quoteEnd) {
                    if (($quote === '"' || $quote === "'") && ($sql[$i + 1] ?? '') === $quoteEnd) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                    $quoteEnd = null;
                }
                continue;
            }
            if ($char === '[') {
                $quote = '[';
                $quoteEnd = ']';
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $quoteEnd = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = substr($sql, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($sql, $start);

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    private static function identifierPattern(): string
    {
        return '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
    }

    private static function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1 && !self::isKeyword($identifier)) {
            return $identifier;
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private static function isKeyword(string $identifier): bool
    {
        return in_array(strtolower($identifier), [
            'asc',
            'begin',
            'desc',
            'end',
            'from',
            'key',
            'release',
            'savepoint',
            'select',
            'table',
            'temporary',
        ], true);
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('SQLite CTAS schema plan identifier cannot be empty');
        }

        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }
}
