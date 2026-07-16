<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningUpsertViewCurrentNextPlan
{
    /**
     * @param list<SQLiteSchemaRecord> $schema
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $viewRows
     * @param array<string,string> $viewToTable
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string):mixed>|null $returning
     * @param list<list<string>>|null $uniqueConstraints
     * @return array{savepoint:string,view:string,trigger:string,targetType:string,parent:list<array<string,mixed>>,child:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,changes:int,statement_rows:int,rolled_back:bool,rollback_reason:?string,rolled_back_at_ordinal:?int,dependencies:list<string>}
     */
    public static function execute(
        array $schema,
        string $triggerName,
        array $parentRows,
        array $childRows,
        array $viewRows,
        array $viewToTable,
        array $uniqueColumns,
        array $assignments,
        array $foreignKey,
        array $triggers,
        ?callable $where = null,
        ?array $returning = null,
        string $savepoint = 'current',
        ?array $uniqueConstraints = null,
    ): array {
        if (trim($savepoint) === '') {
            throw new \InvalidArgumentException('SQLite trigger RETURNING view current/next savepoint name must not be empty');
        }

        $resolution = SQLiteViewTriggerNameResolution::resolveTrigger($schema, $triggerName);
        if (!$resolution['insteadOf'] || $resolution['targetType'] !== 'view' || $resolution['status'] !== 'resolved') {
            throw new \InvalidArgumentException('SQLite trigger RETURNING view current/next requires a resolved INSTEAD OF trigger on a view');
        }
        self::validateMapping($viewToTable, $resolution['columns']);
        self::validateUniqueConstraints($uniqueConstraints);

        $startParent = array_values($parentRows);
        $startChild = array_values($childRows);
        $parent = $startParent;
        $child = $startChild;
        $yieldStream = [];
        $returningRows = [];
        $effects = [];
        $violations = [];
        $changes = 0;

        foreach ($viewRows as $ordinal => $viewRow) {
            $incoming = self::projectViewRow($viewRow, $viewToTable, $resolution['columns']);
            $current = self::conflictRow($parent, $incoming, $uniqueColumns);

            try {
                $step = SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
                    $parent,
                    $child,
                    [$incoming],
                    $uniqueColumns,
                    $assignments,
                    $foreignKey,
                    $triggers,
                    $where,
                    $returning,
                    $uniqueConstraints,
                );
            } catch (\Throwable $throwable) {
                return [
                    'savepoint' => $savepoint,
                    'view' => $resolution['target'],
                    'trigger' => $resolution['trigger'],
                    'targetType' => $resolution['targetType'],
                    'parent' => $startParent,
                    'child' => $startChild,
                    'yield_stream' => $yieldStream,
                    'returning_rows' => [],
                    'trigger_effects' => $effects,
                    'foreign_key_violations' => $violations,
                    'changes' => 0,
                    'statement_rows' => count($viewRows),
                    'rolled_back' => true,
                    'rollback_reason' => $throwable->getMessage(),
                    'rolled_back_at_ordinal' => (int) $ordinal,
                    'dependencies' => self::dependencies(),
                ];
            }

            $yield = $step['yielded'][0] ?? null;
            if (!is_array($yield)) {
                throw new \RuntimeException('SQLite trigger RETURNING view current/next yielded no row for a view input row');
            }

            $next = self::conflictRow($step['parent'], $incoming, $uniqueColumns);
            $status = (string) ($yield['status'] ?? 'unknown');
            $event = (string) ($yield['event'] ?? ($current === null ? 'insert' : 'update'));
            $returningRow = is_array($yield['returning'] ?? null) ? $yield['returning'] : null;

            $yieldStream[] = [
                'ordinal' => (int) $ordinal,
                'view' => $resolution['target'],
                'trigger' => $resolution['trigger'],
                'event' => $event,
                'status' => $status,
                'view_row' => $viewRow,
                'incoming_row' => $incoming,
                'current_row' => $current,
                'next_row' => $status === 'skipped' ? $current : $next,
                'returning' => $returningRow,
                'changed' => $status === 'changed',
                'foreign_key_violation_count' => (int) ($yield['violations_before_after_triggers'] ?? 0) + (int) ($yield['violations_after_triggers'] ?? 0),
            ];

            if ($returningRow !== null) {
                $returningRows[] = $returningRow;
            }
            $parent = $step['parent'];
            $child = $step['child'];
            $effects = array_merge($effects, self::tagRows($step['trigger_effects'], (int) $ordinal, $viewRow));
            $violations = array_merge($violations, self::tagRows($step['foreign_key_violations'], (int) $ordinal, $viewRow));
            $changes += $step['changes'];
        }

        return [
            'savepoint' => $savepoint,
            'view' => $resolution['target'],
            'trigger' => $resolution['trigger'],
            'targetType' => $resolution['targetType'],
            'parent' => array_values($parent),
            'child' => array_values($child),
            'yield_stream' => $yieldStream,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'foreign_key_violations' => $violations,
            'changes' => $changes,
            'statement_rows' => count($viewRows),
            'rolled_back' => false,
            'rollback_reason' => null,
            'rolled_back_at_ordinal' => null,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @return array<string,mixed>|null
     */
    private static function conflictRow(array $rows, array $incoming, array $uniqueColumns): ?array
    {
        foreach ($rows as $row) {
            foreach ($uniqueColumns as $column) {
                self::identifier($column, 'unique column');
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite trigger RETURNING view current/next unique column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return $row;
        }

        return null;
    }

    /**
     * @param list<list<string>>|null $uniqueConstraints
     */
    private static function validateUniqueConstraints(?array $uniqueConstraints): void
    {
        if ($uniqueConstraints === null) {
            return;
        }
        if ($uniqueConstraints === [] || !array_is_list($uniqueConstraints)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING view current/next unique constraints must be a non-empty list');
        }
        foreach ($uniqueConstraints as $columns) {
            if ($columns === [] || !array_is_list($columns)) {
                throw new \InvalidArgumentException('SQLite trigger RETURNING view current/next unique constraint must be a non-empty column list');
            }
            foreach ($columns as $column) {
                self::identifier((string) $column, 'unique constraint column');
            }
        }
    }

    /**
     * @param array<string,string> $viewToTable
     * @param list<string> $viewColumns
     */
    private static function validateMapping(array $viewToTable, array $viewColumns): void
    {
        if ($viewToTable === []) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING view current/next column mapping must not be empty');
        }
        foreach ($viewToTable as $viewColumn => $tableColumn) {
            self::identifier((string) $viewColumn, 'view column');
            self::identifier((string) $tableColumn, 'table column');
            if (!in_array($viewColumn, $viewColumns, true)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING view current/next column {$viewColumn} does not exist on the view");
            }
        }
    }

    /**
     * @param array<string,mixed> $viewRow
     * @param array<string,string> $viewToTable
     * @param list<string> $viewColumns
     * @return array<string,mixed>
     */
    private static function projectViewRow(array $viewRow, array $viewToTable, array $viewColumns): array
    {
        $incoming = [];
        foreach ($viewToTable as $viewColumn => $tableColumn) {
            if (!in_array($viewColumn, $viewColumns, true)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING view current/next column {$viewColumn} does not exist on the view");
            }
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING view current/next row is missing view column {$viewColumn}");
            }
            $incoming[$tableColumn] = $viewRow[$viewColumn];
        }

        return $incoming;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $viewRow
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, int $ordinal, array $viewRow): array
    {
        foreach ($rows as &$row) {
            $row['view_ordinal'] = $ordinal;
            $row['view_row'] = $viewRow;
        }
        unset($row);

        return $rows;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING view current/next {$label} is malformed");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function dependencies(): array
    {
        return [
            'sqlite-instead-of-view-trigger',
            'sqlite-upsert-returning-trigger-fk-yield',
            'sqlite-current-next-row-stream',
        ];
    }
}
