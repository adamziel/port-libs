<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteReturningIndexedDeletePlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *     source:string,
     *     statement:string,
     *     indexed_by:string,
     *     before:list<array<string,mixed>>,
     *     after:list<array<string,mixed>>,
     *     returning_rows:list<array<string,mixed>>,
     *     predicate_trace:list<array{row:array<string,mixed>,is_nocase:bool,selector:int,selector_match:bool,deleted:bool}>,
     *     changes:int,
     *     integrity:string,
     *     dependencies:list<string>
     * }
     */
    public static function deleteIndexedByExpressionReturning(
        array $rows,
        string $keyColumn = 'key_name',
        string $indexedColumn = 'indexed_name',
        string $selectorColumn = 'selector',
        string $indexName = 'app_delete_expr_idx'
    ): array {
        self::assertRows($rows);
        self::assertIdentifier($keyColumn, 'key column');
        self::assertIdentifier($indexedColumn, 'indexed column');
        self::assertIdentifier($selectorColumn, 'selector column');
        self::assertIdentifier($indexName, 'index name');

        $before = array_values($rows);
        $selectorValues = [];
        foreach ($before as $offset => $row) {
            self::assertColumn($row, $keyColumn, $offset);
            self::assertColumn($row, $indexedColumn, $offset);
            self::assertColumn($row, $selectorColumn, $offset);
            $selectorValues[] = $row[$selectorColumn];
        }

        $after = [];
        $returning = [];
        $trace = [];

        foreach ($before as $row) {
            $isNoCase = self::sqliteIsNoCase($row[$keyColumn], $row[$indexedColumn]);
            $selector = $isNoCase ? 1 : 0;
            $selectorMatch = self::selectorContains($selectorValues, $selector);
            $deleted = $selectorMatch;

            $trace[] = [
                'row' => $row,
                'is_nocase' => $isNoCase,
                'selector' => $selector,
                'selector_match' => $selectorMatch,
                'deleted' => $deleted,
            ];

            if ($deleted) {
                $returning[] = $row;
                continue;
            }

            $after[] = $row;
        }

        return [
            'source' => 'indexexpr1.test indexexpr1-1900 through indexexpr1-1920',
            'statement' => "DELETE FROM app_returning_indexed_delete INDEXED BY {$indexName} WHERE {$keyColumn} IS +{$indexedColumn} COLLATE NOCASE IN (SELECT {$selectorColumn} FROM app_returning_indexed_delete) RETURNING *",
            'indexed_by' => $indexName,
            'before' => $before,
            'after' => $after,
            'returning_rows' => $returning,
            'predicate_trace' => $trace,
            'changes' => count($returning),
            'integrity' => count($before) === count($after) + count($returning) ? 'ok' : 'corrupt',
            'dependencies' => [
                'indexexpr1.test-1900',
                'indexexpr1.test-1910',
                'indexexpr1.test-1920',
                'sqlite-delete-indexed-by-expression-index-returning',
            ],
        ];
    }

    /**
     * @return list<array{
     *     case:int,
     *     upstream_section:string,
     *     source:string,
     *     statement:string,
     *     before:list<array<string,mixed>>,
     *     after:list<array<string,mixed>>,
     *     returning_rows:list<array<string,mixed>>,
     *     predicate_trace:list<array{row:array<string,mixed>,is_nocase:bool,selector:int,selector_match:bool,deleted:bool}>,
     *     changes:int,
     *     integrity:string,
     *     dependencies:list<string>
     * }>
     */
    public static function dynamicIndexedDeleteReturningCases(int $caseCount = 1000): array
    {
        if ($caseCount < 1) {
            throw new InvalidArgumentException('SQLite indexed DELETE RETURNING dynamic corpus requires at least one case');
        }

        $cases = [];
        for ($case = 1; $case <= $caseCount; ++$case) {
            $prefix = 'setting_' . str_pad((string) $case, 4, '0', STR_PAD_LEFT);
            $rows = [
                [
                    'key_name' => $prefix . '_alpha',
                    'indexed_name' => strtoupper($prefix . '_alpha'),
                    'selector' => 1,
                    'payload' => 'delete-' . $case,
                ],
                [
                    'key_name' => $prefix . '_bravo',
                    'indexed_name' => $prefix . '_charlie',
                    'selector' => 1,
                    'payload' => 'keep-' . $case,
                ],
            ];

            $plan = self::deleteIndexedByExpressionReturning($rows);
            $cases[] = [
                'case' => $case,
                'upstream_section' => 'indexexpr1-1900/1910/1920',
                'source' => $plan['source'],
                'statement' => $plan['statement'],
                'before' => $plan['before'],
                'after' => $plan['after'],
                'returning_rows' => $plan['returning_rows'],
                'predicate_trace' => $plan['predicate_trace'],
                'changes' => $plan['changes'],
                'integrity' => $plan['integrity'],
                'dependencies' => $plan['dependencies'],
            ];
        }

        return $cases;
    }

    private static function sqliteIsNoCase(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return strcasecmp((string) $left, (string) $right) === 0;
    }

    /** @param list<mixed> $values */
    private static function selectorContains(array $values, int $selector): bool
    {
        foreach ($values as $value) {
            if (is_int($value) || is_float($value) || is_string($value)) {
                $numeric = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                if ($numeric !== null && (float) $numeric === (float) $selector) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function assertRows(array $rows): void
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite indexed DELETE RETURNING rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite indexed DELETE RETURNING row must be an array');
            }
        }
    }

    private static function assertColumn(array $row, string $column, int $offset): void
    {
        if (!array_key_exists($column, $row)) {
            throw new InvalidArgumentException("SQLite indexed DELETE RETURNING row {$offset} is missing column {$column}");
        }
    }

    private static function assertIdentifier(string $identifier, string $label): void
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite indexed DELETE RETURNING {$label} is malformed");
        }
    }
}
