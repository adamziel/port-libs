<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $currentTriggers
     * @param list<array<string,mixed>> $nextTriggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int):mixed> $returning
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string,rollback_on_deferred_violation?:bool} $currentOptions
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string} $nextOptions
     * @return array{savepoint:string,status:string,release_status:string,parent:list<array<string,mixed>>,child:list<array<string,mixed>>,current_visible_parent:list<array<string,mixed>>,current_visible_child:list<array<string,mixed>>,next_parent:list<array<string,mixed>>,next_child:list<array<string,mixed>>,current_returning_rows:list<array<string,mixed>>,attempted_current_yields:list<array<string,mixed>>,next_returning_rows:list<array<string,mixed>>,attempted_next_yields:list<array<string,mixed>>,current_fk_violations:list<array<string,mixed>>,next_fk_violations:list<array<string,mixed>>,current_trigger_effects:list<array<string,mixed>>,next_trigger_effects:list<array<string,mixed>>,current_changes:int,next_changes:int,total_attempted_changes:int,committed_changes:int,current_rolled_back_on_release:bool,next_started_from_savepoint:bool,savepoint_preserved_after_release:bool,discarded_current_parent:list<array<string,mixed>>,discarded_current_child:list<array<string,mixed>>,rollback_reason:?string,dependencies:list<string>}
     */
    public static function execute(
        string $savepoint,
        array $parentRows,
        array $childRows,
        array $currentRows,
        array $nextRows,
        array $uniqueColumns,
        array $assignments,
        array $foreignKey,
        array $currentTriggers,
        array $nextTriggers,
        array $returning,
        array $currentOptions = [],
        array $nextOptions = [],
    ): array {
        self::identifier($savepoint, 'savepoint');
        if ($currentRows === []) {
            throw new \InvalidArgumentException('SQLite trigger FK RETURNING UPSERT current rows cannot be empty');
        }
        if ($nextRows === []) {
            throw new \InvalidArgumentException('SQLite trigger FK RETURNING UPSERT next rows cannot be empty');
        }
        if ($returning === []) {
            throw new \InvalidArgumentException('SQLite trigger FK RETURNING UPSERT projection cannot be empty');
        }

        $current = SQLiteRecursiveSavepointUpsertPlan::execute(
            $savepoint,
            $parentRows,
            $childRows,
            $currentRows,
            $uniqueColumns,
            $assignments,
            $foreignKey,
            $currentTriggers,
            $currentOptions
        );

        $deferredViolation = ($foreignKey['deferred'] ?? false) && $current['foreign_key_violations'] !== [];
        $rollbackOnDeferred = (bool) ($currentOptions['rollback_on_deferred_violation'] ?? true);
        $releaseRollback = $deferredViolation && $rollbackOnDeferred;
        $releaseStatus = $deferredViolation ? 'deferred-foreign-key-failed' : 'released';
        $currentReturning = self::projectYields($current['yielded'], $current['attempted_parent'], $returning);

        $nextBaseParent = $releaseRollback ? $parentRows : $current['parent'];
        $nextBaseChild = $releaseRollback ? $childRows : $current['child'];
        $next = SQLiteRecursiveSavepointUpsertPlan::execute(
            $savepoint,
            $nextBaseParent,
            $nextBaseChild,
            $nextRows,
            $uniqueColumns,
            $assignments,
            $foreignKey,
            $nextTriggers,
            $nextOptions
        );
        $nextReturning = self::projectYields($next['yielded'], $next['attempted_parent'], $returning);

        return [
            'savepoint' => $savepoint,
            'status' => $releaseRollback ? 'current-returned-then-rolled-back-next-applied' : 'current-released-next-applied',
            'release_status' => $releaseStatus,
            'parent' => array_values($releaseRollback ? $parentRows : $current['parent']),
            'child' => array_values($releaseRollback ? $childRows : $current['child']),
            'current_visible_parent' => $current['attempted_parent'],
            'current_visible_child' => $current['attempted_child'],
            'next_parent' => $next['parent'],
            'next_child' => $next['child'],
            'current_returning_rows' => $releaseRollback ? $currentReturning : $currentReturning,
            'attempted_current_yields' => $current['yielded'],
            'next_returning_rows' => $nextReturning,
            'attempted_next_yields' => $next['yielded'],
            'current_fk_violations' => $current['foreign_key_violations'],
            'next_fk_violations' => $next['foreign_key_violations'],
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $next['trigger_effects'],
            'current_changes' => $releaseRollback ? 0 : $current['changes'],
            'next_changes' => $next['changes'],
            'total_attempted_changes' => $current['changes'] + $next['changes'],
            'committed_changes' => ($releaseRollback ? 0 : $current['changes']) + $next['changes'],
            'current_rolled_back_on_release' => $releaseRollback,
            'next_started_from_savepoint' => self::rowsEqual($nextBaseParent, $parentRows) && self::rowsEqual($nextBaseChild, $childRows),
            'savepoint_preserved_after_release' => $releaseRollback,
            'discarded_current_parent' => $releaseRollback ? self::changedRows($parentRows, $current['attempted_parent']) : [],
            'discarded_current_child' => $releaseRollback ? self::changedRows($childRows, $current['attempted_child']) : [],
            'rollback_reason' => $releaseRollback ? 'deferred-foreign-key-release' : null,
            'dependencies' => [
                'sqlite-recursive-trigger-current-savepoint',
                'sqlite-upsert-trigger-yield',
                'sqlite-returning-before-deferred-fk-release',
                'sqlite-savepoint-current-next75',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $yields
     * @param list<array<string,mixed>> $rows
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int):mixed> $returning
     * @return list<array<string,mixed>>
     */
    private static function projectYields(array $yields, array $rows, array $returning): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            if (array_key_exists('setting_id', $row)) {
                $byKey[(string) $row['setting_id']] = $row;
            }
        }

        $projected = [];
        foreach ($yields as $ordinal => $yield) {
            if (($yield['status'] ?? null) !== 'changed') {
                continue;
            }
            $key = (string) ($yield['new_key'] ?? '');
            if ($key === '' || !isset($byKey[$key])) {
                continue;
            }
            $projected[] = self::projectRow($byKey[$key], $yield, $ordinal, $returning);
        }

        return $projected;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $yield
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function projectRow(array $row, array $yield, int $ordinal, array $returning): array
    {
        $projected = [];
        foreach ($returning as $index => $term) {
            if ($term === '*') {
                $projected['*'] = $row;
                continue;
            }
            if (is_callable($term)) {
                $projected['expr' . $index] = $term($row, $yield, $ordinal);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? $expr);
                self::identifier($alias, 'RETURNING alias');
                $projected[$alias] = self::value($row, $yield, $expr);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new \InvalidArgumentException('SQLite trigger FK RETURNING UPSERT projection term is malformed');
            }
            $projected[$term] = self::value($row, $yield, $term);
        }

        return $projected;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $yield
     */
    private static function value(array $row, array $yield, string $expr): mixed
    {
        if (str_starts_with($expr, 'new.')) {
            $expr = substr($expr, 4);
        }
        if (str_starts_with($expr, 'yield.')) {
            $column = substr($expr, 6);
            if (!array_key_exists($column, $yield)) {
                throw new \InvalidArgumentException("SQLite trigger FK RETURNING UPSERT yield column {$column} is missing");
            }

            return $yield[$column];
        }
        if (!array_key_exists($expr, $row)) {
            throw new \InvalidArgumentException("SQLite trigger FK RETURNING UPSERT column {$expr} is missing");
        }

        return $row[$expr];
    }

    /**
     * @param list<array<string,mixed>> $original
     * @param list<array<string,mixed>> $attempted
     * @return list<array<string,mixed>>
     */
    private static function changedRows(array $original, array $attempted): array
    {
        $encodedOriginal = [];
        foreach ($original as $row) {
            $encodedOriginal[] = json_encode($row, JSON_THROW_ON_ERROR);
        }

        $changed = [];
        foreach ($attempted as $row) {
            if (!in_array(json_encode($row, JSON_THROW_ON_ERROR), $encodedOriginal, true)) {
                $changed[] = $row;
            }
        }

        return $changed;
    }

    /**
     * @param list<array<string,mixed>> $left
     * @param list<array<string,mixed>> $right
     */
    private static function rowsEqual(array $left, array $right): bool
    {
        return json_encode(array_values($left), JSON_THROW_ON_ERROR) === json_encode(array_values($right), JSON_THROW_ON_ERROR);
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger FK RETURNING UPSERT {$label} is malformed");
        }

        return $value;
    }
}
