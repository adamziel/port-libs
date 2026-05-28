<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext139Plan
{
    /**
     * @param list<array<string,mixed>> $initialRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,int):mixed> $returning
     * @param array{savepoint?:string,rollback_to?:bool,recursive_triggers?:bool,max_depth?:int,current_source?:string,next_source?:string,conflict_action?:string} $options
     * @return array<string,mixed>
     */
    public static function insertRowsWithinSavepoint(
        array $initialRows,
        array $inputRows,
        array $triggers,
        array $uniqueColumns,
        array $returning = ['*'],
        array $options = [],
    ): array {
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'wp_recursive_import'), 'savepoint');
        $rollbackTo = (bool) ($options['rollback_to'] ?? true);
        $currentSource = (string) ($options['current_source'] ?? 'current-recursive-trigger-returning');
        $nextSource = (string) ($options['next_source'] ?? 'next-after-recursive-trigger-savepoint');
        $conflictAction = (string) ($options['conflict_action'] ?? 'abort');

        $beforeRows = array_values($initialRows);
        $statement = SQLiteDmlTriggerRecursionPlan::insertRows(
            $beforeRows,
            array_values($inputRows),
            $triggers,
            $uniqueColumns,
            $conflictAction,
            [
                'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
                'max_depth' => (int) ($options['max_depth'] ?? 1000),
            ],
        );

        $returningRows = self::returningRows($statement['inserted'], $statement['effects'], $returning);
        $afterRows = $rollbackTo ? $beforeRows : $statement['rows'];

        return [
            'savepoint' => $savepoint,
            'rolled_back' => $rollbackTo,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'before' => $beforeRows,
            'after_statement' => $statement['rows'],
            'after_savepoint' => $afterRows,
            'returning_rows' => $returningRows,
            'yielded' => self::yieldedRows($returningRows, $savepoint, $rollbackTo),
            'inserted_before_rollback' => $statement['inserted'],
            'trigger_effects_before_rollback' => $statement['effects'],
            'ignored_before_rollback' => $statement['ignored'],
            'changes_before_rollback' => $statement['changes'],
            'discarded_returning_count' => $rollbackTo ? count($returningRows) : 0,
            'restored_unique_keys' => self::uniqueKeys($afterRows, $uniqueColumns),
            'statement_unique_keys' => self::uniqueKeys($statement['rows'], $uniqueColumns),
            'recursive_triggers' => $statement['recursive_triggers'],
            'max_depth' => $statement['max_depth'],
            'dependencies' => [
                'sqlite-dml-trigger-recursion-corpus',
                'sqlite-trigger-deferred-returning-savepoint-current-source-next119',
                'sqlite-trigger-recursive-returning-savepoint-current-source-next139',
                'sqlite-returning-yield-before-rollback-to-savepoint',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $effects
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,int):mixed> $returning
     * @return list<array<string,mixed>>
     */
    private static function returningRows(array $rows, array $effects, array $returning): array
    {
        $depths = [];
        foreach ($effects as $effect) {
            if (($effect['action'] ?? null) === 'insert' && in_array($effect['result'] ?? null, ['inserted', 'replaced-conflict'], true)) {
                $depths[] = (int) $effect['depth'];
            }
        }

        $result = [];
        foreach ($rows as $index => $row) {
            $resultRow = [];
            foreach ($returning as $returningIndex => $expr) {
                if ($expr === '*') {
                    foreach ($row as $column => $value) {
                        $resultRow[(string) $column] = $value;
                    }
                    continue;
                }
                if (is_callable($expr)) {
                    $resultRow['expr' . $returningIndex] = $expr($row, $index, $depths[$index] ?? $index);
                    continue;
                }

                $column = is_array($expr) ? (string) $expr['expr'] : (string) $expr;
                $alias = is_array($expr) ? (string) ($expr['as'] ?? $column) : $column;
                if (str_starts_with($column, 'new.')) {
                    $column = substr($column, 4);
                    if (!is_array($expr)) {
                        $alias = $column;
                    }
                }
                $column = self::identifier($column, 'returning column');
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger RETURNING column {$column} is missing");
                }
                $resultRow[$alias] = $row[$column];
            }
            $result[] = $resultRow;
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $returningRows
     * @return list<array<string,mixed>>
     */
    private static function yieldedRows(array $returningRows, string $savepoint, bool $rolledBack): array
    {
        $yielded = [];
        foreach ($returningRows as $index => $row) {
            $yielded[] = [
                'statement' => $index,
                'savepoint' => $savepoint,
                'rolled_back_after_yield' => $rolledBack,
                'row' => $row,
            ];
        }

        return $yielded;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function uniqueKeys(array $rows, array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite recursive trigger RETURNING unique columns cannot be empty');
        }

        $keys = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($columns as $column) {
                $column = self::identifier($column, 'unique column');
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger RETURNING unique column {$column} is missing");
                }
                $parts[] = (string) $row[$column];
            }
            $keys[] = implode("\0", $parts);
        }

        return $keys;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite recursive trigger RETURNING savepoint {$label} is malformed");
        }

        return $identifier;
    }
}
