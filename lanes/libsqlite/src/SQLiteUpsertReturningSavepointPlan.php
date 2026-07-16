<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertReturningSavepointPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string):mixed>|null $returning
     * @return array{savepoint:string,parent:list<array<string,mixed>>,child:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,attempted_yields:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,changes:int,rolled_back:bool,rollback_reason:?string,rolled_back_at_ordinal:?int,statement_rows:int}
     */
    public static function execute(
        array $parentRows,
        array $childRows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $foreignKey,
        array $triggers,
        ?callable $where = null,
        ?array $returning = null,
        string $savepoint = 'current',
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING savepoint name must not be empty');
        }

        $startParent = array_values($parentRows);
        $startChild = array_values($childRows);
        $parent = $startParent;
        $child = $startChild;
        $returningRows = [];
        $attemptedYields = [];
        $effects = [];
        $violations = [];
        $changes = 0;

        foreach ($incomingRows as $ordinal => $incoming) {
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
                );
            } catch (\Throwable $throwable) {
                return [
                    'savepoint' => $savepoint,
                    'parent' => $startParent,
                    'child' => $startChild,
                    'returning_rows' => [],
                    'attempted_yields' => $attemptedYields,
                    'trigger_effects' => $effects,
                    'foreign_key_violations' => $violations,
                    'changes' => 0,
                    'rolled_back' => true,
                    'rollback_reason' => $throwable->getMessage(),
                    'rolled_back_at_ordinal' => (int) $ordinal,
                    'statement_rows' => count($incomingRows),
                ];
            }

            $parent = $step['parent'];
            $child = $step['child'];
            $effects = array_merge($effects, self::tagRows($step['trigger_effects'], $ordinal));
            $violations = array_merge($violations, self::tagRows($step['foreign_key_violations'], $ordinal));
            $attemptedYields = array_merge($attemptedYields, self::retagYields($step['yielded'], $ordinal));
            foreach ($step['yielded'] as $yield) {
                if (($yield['status'] ?? null) === 'changed' && is_array($yield['returning'] ?? null)) {
                    $returningRows[] = $yield['returning'];
                }
            }
            $changes += $step['changes'];
        }

        return [
            'savepoint' => $savepoint,
            'parent' => array_values($parent),
            'child' => array_values($child),
            'returning_rows' => $returningRows,
            'attempted_yields' => $attemptedYields,
            'trigger_effects' => $effects,
            'foreign_key_violations' => $violations,
            'changes' => $changes,
            'rolled_back' => false,
            'rollback_reason' => null,
            'rolled_back_at_ordinal' => null,
            'statement_rows' => count($incomingRows),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, int $ordinal): array
    {
        foreach ($rows as &$row) {
            $row['statement_ordinal'] = $ordinal;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function retagYields(array $rows, int $ordinal): array
    {
        foreach ($rows as &$row) {
            $row['statement_ordinal'] = $ordinal;
            $row['ordinal'] = $ordinal;
        }
        unset($row);

        return $rows;
    }
}
