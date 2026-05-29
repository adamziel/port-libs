<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>} $options
     * @return array{current:array<string,mixed>,next:array<string,mixed>,rows:list<array<string,mixed>>,current_returning:list<array<string,mixed>>,next_returning:list<array<string,mixed>>,current_yield_edges:list<array<string,mixed>>,next_yield_edges:list<array<string,mixed>>,current_source_rows:list<array<string,mixed>>,next_source_rows:list<array<string,mixed>>,changes:int,status:string,dependencies:list<string>}
     */
    public static function execute(
        array $rows,
        array $currentRows,
        array $nextRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $options = [],
    ): array {
        if ($currentRows === [] || !array_is_list($currentRows)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING recursive UPSERT current source rows must be a non-empty list');
        }
        if ($nextRows === [] || !array_is_list($nextRows)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING recursive UPSERT next source rows must be a non-empty list');
        }

        $currentSourceRows = array_values($rows);
        $current = SQLiteRecursiveUpsertConflictYieldPlan::execute(
            $currentSourceRows,
            array_values($currentRows),
            $uniqueColumns,
            $assignments,
            $triggers,
            $options,
        );

        $nextSourceRows = array_values($current['rows']);
        $next = SQLiteRecursiveUpsertConflictYieldPlan::execute(
            $nextSourceRows,
            array_values($nextRows),
            $uniqueColumns,
            $assignments,
            $triggers,
            $options,
        );

        return [
            'current' => $current,
            'next' => $next,
            'rows' => array_values($next['rows']),
            'current_returning' => array_values($current['returning']),
            'next_returning' => array_values($next['returning']),
            'current_yield_edges' => self::yieldEdges('current', $current['yielded']),
            'next_yield_edges' => self::yieldEdges('next', $next['yielded']),
            'current_source_rows' => $currentSourceRows,
            'next_source_rows' => $nextSourceRows,
            'changes' => (int) $current['changes'] + (int) $next['changes'],
            'status' => 'current-returning-recursive-upsert-next-source-applied',
            'dependencies' => [
                'sqlite-recursive-upsert-trigger-returning-current-source-next118',
                'sqlite-returning-before-next-source-handoff',
                'sqlite-recursive-trigger-upsert-current-source-yield',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function yieldEdges(string $phase, array $yielded): array
    {
        $edges = [];
        foreach ($yielded as $index => $yield) {
            $returning = $yield['returning'] ?? null;
            $edges[] = [
                'phase' => $phase,
                'ordinal' => $yield['ordinal'] ?? null,
                'yield_index' => $index,
                'status' => $yield['status'] ?? null,
                'event' => $yield['event'] ?? null,
                'source' => $yield['source'] ?? null,
                'trigger' => $yield['trigger'] ?? null,
                'depth' => $yield['depth'] ?? null,
                'current_source_key' => $yield['old_key'] ?? $yield['incoming_key'] ?? null,
                'next_source_key' => $yield['new_key'] ?? null,
                'returning_key' => is_array($returning) ? ($returning['option_name'] ?? null) : null,
                'returning' => $returning,
            ];
        }

        return $edges;
    }
}
