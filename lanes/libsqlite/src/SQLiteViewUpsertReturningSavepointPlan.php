<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteViewUpsertReturningSavepointPlan
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
     * @return array{savepoint:string,view:string,trigger:string,target:string,targetType:string,parent:list<array<string,mixed>>,child:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,view_returning_rows:list<array<string,mixed>>,attempted_view_rows:list<array<string,mixed>>,attempted_yields:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,changes:int,statement_rows:int,rolled_back:bool,rollback_reason:?string,rolled_back_at_ordinal:?int,dependencies:list<string>}
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
    ): array {
        if (trim($savepoint) === '') {
            throw new \InvalidArgumentException('SQLite view UPSERT RETURNING savepoint name must not be empty');
        }

        $resolution = SQLiteViewTriggerNameResolution::resolveTrigger($schema, $triggerName);
        if (!$resolution['insteadOf'] || $resolution['targetType'] !== 'view' || $resolution['status'] !== 'resolved') {
            throw new \InvalidArgumentException('SQLite view UPSERT RETURNING requires a resolved INSTEAD OF trigger on a view');
        }
        self::validateMapping($viewToTable, $resolution['columns']);

        $startParent = array_values($parentRows);
        $startChild = array_values($childRows);
        $parent = $startParent;
        $child = $startChild;
        $returningRows = [];
        $viewReturningRows = [];
        $attemptedViewRows = [];
        $attemptedYields = [];
        $effects = [];
        $violations = [];
        $changes = 0;

        foreach ($viewRows as $ordinal => $viewRow) {
            $incoming = self::projectViewRow($viewRow, $viewToTable, $resolution['columns']);
            $attemptedViewRows[] = [
                'ordinal' => (int) $ordinal,
                'view_row' => $viewRow,
                'incoming_row' => $incoming,
            ];

            $step = SQLiteUpsertReturningSavepointPlan::execute(
                $parent,
                $child,
                [$incoming],
                $uniqueColumns,
                $assignments,
                $foreignKey,
                $triggers,
                $where,
                $returning,
                $savepoint,
            );

            $attemptedYields = array_merge($attemptedYields, self::tagRows($step['attempted_yields'], (int) $ordinal, $viewRow));
            $effects = array_merge($effects, self::tagRows($step['trigger_effects'], (int) $ordinal, $viewRow));
            $violations = array_merge($violations, self::tagRows($step['foreign_key_violations'], (int) $ordinal, $viewRow));

            if ($step['rolled_back']) {
                return [
                    'savepoint' => $savepoint,
                    'view' => $resolution['target'],
                    'trigger' => $resolution['trigger'],
                    'target' => $resolution['target'],
                    'targetType' => $resolution['targetType'],
                    'parent' => $startParent,
                    'child' => $startChild,
                    'returning_rows' => [],
                    'view_returning_rows' => $viewReturningRows,
                    'attempted_view_rows' => $attemptedViewRows,
                    'attempted_yields' => $attemptedYields,
                    'trigger_effects' => $effects,
                    'foreign_key_violations' => $violations,
                    'changes' => 0,
                    'statement_rows' => count($viewRows),
                    'rolled_back' => true,
                    'rollback_reason' => $step['rollback_reason'],
                    'rolled_back_at_ordinal' => (int) $ordinal,
                    'dependencies' => self::dependencies(),
                ];
            }

            $parent = $step['parent'];
            $child = $step['child'];
            $changes += $step['changes'];
            foreach ($step['returning_rows'] as $row) {
                $tagged = $row + [
                    'view' => $resolution['target'],
                    'view_ordinal' => (int) $ordinal,
                ];
                $returningRows[] = $row;
                $viewReturningRows[] = $tagged;
            }
        }

        return [
            'savepoint' => $savepoint,
            'view' => $resolution['target'],
            'trigger' => $resolution['trigger'],
            'target' => $resolution['target'],
            'targetType' => $resolution['targetType'],
            'parent' => array_values($parent),
            'child' => array_values($child),
            'returning_rows' => $returningRows,
            'view_returning_rows' => $viewReturningRows,
            'attempted_view_rows' => $attemptedViewRows,
            'attempted_yields' => $attemptedYields,
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
     * @param array<string,string> $viewToTable
     * @param list<string> $viewColumns
     */
    private static function validateMapping(array $viewToTable, array $viewColumns): void
    {
        if ($viewToTable === []) {
            throw new \InvalidArgumentException('SQLite view UPSERT RETURNING column mapping must not be empty');
        }
        foreach ($viewToTable as $viewColumn => $tableColumn) {
            if (!is_string($viewColumn) || $viewColumn === '' || !is_string($tableColumn) || $tableColumn === '') {
                throw new \InvalidArgumentException('SQLite view UPSERT RETURNING column mapping is malformed');
            }
            if (!in_array($viewColumn, $viewColumns, true)) {
                throw new \InvalidArgumentException("SQLite view UPSERT RETURNING column {$viewColumn} does not exist on the view");
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
                throw new \InvalidArgumentException("SQLite view UPSERT RETURNING column {$viewColumn} does not exist on the view");
            }
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new \InvalidArgumentException("SQLite view UPSERT RETURNING row is missing view column {$viewColumn}");
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

    /**
     * @return list<string>
     */
    private static function dependencies(): array
    {
        return [
            'sqlite-instead-of-view-trigger',
            'sqlite-upsert-returning-current-savepoint',
            'sqlite-trigger-fk-yield',
        ];
    }
}
