<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<array{table:string,columns:list<string>}> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_cleanup',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value savepoint plan needs at least one statement');
        }

        $current = self::normalizeTables($tables);
        $savepointImage = $current;
        $attempted = $current;
        $executed = [];
        $yieldedReturning = [];
        $attemptedReturning = [];
        $rollbackReason = null;
        $rollbackStatement = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $attempted;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $attempted, $rowIdColumn);
            $attempted = $result['tables'];

            $statement = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'table' => $result['table'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'returning_rows' => $result['returning'],
                'current_source_before_count' => count($before[$result['table']] ?? []),
                'next_source_after_count' => count($attempted[$result['table']] ?? []),
            ];
            $executed[] = $statement;
            $attemptedReturning[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'table' => $result['table'],
                'rows' => $result['returning'],
            ];

            $violation = self::firstUniqueViolation($attempted, $uniqueConstraints);
            if ($violation !== null) {
                $rollbackReason = 'unique-constraint:' . $violation['table'] . ':' . implode(',', $violation['columns']) . ':' . $violation['key'];
                $rollbackStatement = $ordinal;
                break;
            }

            $yieldedReturning[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'table' => $result['table'],
                'rows' => $result['returning'],
            ];
        }

        $rolledBack = $rollbackReason !== null;
        $currentAfter = $rolledBack ? $savepointImage : $attempted;
        $changedTables = self::changedTables($savepointImage, $attempted);

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'rolled-back-to-savepoint' : 'released',
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_statement_ordinal' => $rollbackStatement,
            'savepoint_preserved' => $rolledBack,
            'current_source_tables' => $currentAfter,
            'next_source_tables' => $attempted,
            'savepoint_image_tables' => $savepointImage,
            'executed_statements' => $executed,
            'yielded_returning' => $rolledBack ? $yieldedReturning : $attemptedReturning,
            'attempted_returning' => $attemptedReturning,
            'rollback_changed_tables' => $rolledBack ? $changedTables : [],
            'rollback_restored_row_counts' => self::rowCounts($currentAfter),
            'attempted_row_counts' => self::rowCounts($attempted),
            'changes' => $rolledBack ? 0 : self::totalMutationCount($executed),
            'attempted_changes' => self::totalMutationCount($executed),
            'dependencies' => [
                'sqlite-row-value-update-delete',
                'sqlite-savepoint-current-source-rollback',
                'sqlite-returning-yield-before-savepoint-rollback',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value savepoint rows must be arrays');
                }
            }
        }

        return $tables;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{table:string,columns:list<string>}> $uniqueConstraints
     * @return array{table:string,columns:list<string>,key:string}|null
     */
    private static function firstUniqueViolation(array $tables, array $uniqueConstraints): ?array
    {
        foreach ($uniqueConstraints as $constraint) {
            $table = $constraint['table'];
            $columns = $constraint['columns'];
            if (!isset($tables[$table])) {
                throw new \InvalidArgumentException("SQLite row-value savepoint unique table {$table} is missing");
            }
            if ($columns === []) {
                throw new \InvalidArgumentException('SQLite row-value savepoint unique constraints need columns');
            }

            $seen = [];
            foreach ($tables[$table] as $row) {
                $keyParts = [];
                $hasNull = false;
                foreach ($columns as $column) {
                    if (!is_string($column) || $column === '' || !array_key_exists($column, $row)) {
                        throw new \InvalidArgumentException("SQLite row-value savepoint unique column {$column} is missing");
                    }
                    if ($row[$column] === null) {
                        $hasNull = true;
                        break;
                    }
                    $keyParts[] = self::keyPart($row[$column]);
                }
                if ($hasNull) {
                    continue;
                }
                $key = implode("\x1f", $keyParts);
                if (isset($seen[$key])) {
                    return ['table' => $table, 'columns' => $columns, 'key' => implode('|', $keyParts)];
                }
                $seen[$key] = true;
            }
        }

        return null;
    }

    private static function keyPart(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        return serialize($value);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTables(array $before, array $after): array
    {
        $names = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        sort($names);
        $changed = [];
        foreach ($names as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) {
                $changed[] = $name;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function totalMutationCount(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }
}
