<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan
{

    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $initialRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,int):mixed> $returning
     * @param array{view?:string,savepoint?:string,current_source?:string,next_source?:string,current_rollback_to?:bool,next_rollback_to?:bool,recursive_triggers?:bool,max_depth?:int,conflict_action?:string} $options
     * @return array<string,mixed>
     */
    public static function insertThroughViewSourcesNext143(
        array $initialRows,
        array $currentRows,
        array $nextRows,
        array $triggers,
        array $uniqueColumns,
        array $returning = ['*'],
        array $options = [],
    ): array {
        $view = self::identifierNext143((string) ($options['view'] ?? 'wp_option_import_view'), 'view');
        $savepoint = self::identifierNext143((string) ($options['savepoint'] ?? 'wp_recursive_view_import'), 'savepoint');
        $currentSource = self::sourceTokenNext143((string) ($options['current_source'] ?? 'current-recursive-view-returning'));
        $nextSource = self::sourceTokenNext143((string) ($options['next_source'] ?? 'next-recursive-view-returning'));
        $currentRollback = (bool) ($options['current_rollback_to'] ?? true);
        $nextRollback = (bool) ($options['next_rollback_to'] ?? false);

        if ($currentRows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING current rows cannot be empty');
        }
        if ($nextRows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next rows cannot be empty');
        }

        $shared = [
            'savepoint' => $savepoint,
            'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
            'max_depth' => (int) ($options['max_depth'] ?? 1000),
            'conflict_action' => (string) ($options['conflict_action'] ?? 'abort'),
        ];
        $current = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint(
            $initialRows,
            $currentRows,
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + [
                'rollback_to' => $currentRollback,
                'current_source' => $currentSource,
                'next_source' => $nextSource,
            ],
        );
        $nextBaseRows = $currentRollback ? $initialRows : $current['after_statement'];
        $next = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint(
            $nextBaseRows,
            $nextRows,
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + [
                'rollback_to' => $nextRollback,
                'current_source' => $currentSource,
                'next_source' => $nextSource,
            ],
        );

        $currentStream = self::streamNext143($current['yielded'], 'current', $currentSource, $view, !$currentRollback);
        $nextStream = self::streamNext143($next['yielded'], 'next', $nextSource, $view, !$nextRollback);
        $admittedCurrent = array_values(array_filter($currentStream, static fn (array $row): bool => $row['admitted']));
        $admittedNext = array_values(array_filter($nextStream, static fn (array $row): bool => $row['admitted']));
        $suppressedCurrent = array_values(array_filter($currentStream, static fn (array $row): bool => !$row['admitted']));
        $suppressedNext = array_values(array_filter($nextStream, static fn (array $row): bool => !$row['admitted']));
        $finalRows = $nextRollback ? $nextBaseRows : $next['after_statement'];

        return [
            'view' => $view,
            'savepoint' => $savepoint,
            'status' => self::statusNext143($currentRollback, $nextRollback),
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'source_transition' => [
                'from' => $currentSource,
                'to' => $nextSource,
                'current_rolled_back' => $currentRollback,
                'next_rolled_back' => $nextRollback,
                'next_input' => $currentRollback ? 'saved-current-source' : 'current-phase-output',
                'visible_source' => !$nextRollback ? $nextSource : ($currentRollback ? $currentSource : $nextSource . ':rolled-back'),
            ],
            'before' => array_values($initialRows),
            'current' => $current,
            'next' => $next,
            'current_source_stream' => $currentStream,
            'next_source_stream' => $nextStream,
            'attempted_source_stream' => array_merge($currentStream, $nextStream),
            'admitted_current_source_stream' => $admittedCurrent,
            'admitted_next_source_stream' => $admittedNext,
            'suppressed_current_source_stream' => $suppressedCurrent,
            'suppressed_next_source_stream' => $suppressedNext,
            'returning_rows' => self::rowsNext143(array_merge($admittedCurrent, $admittedNext)),
            'suppressed_returning_rows' => self::rowsNext143(array_merge($suppressedCurrent, $suppressedNext)),
            'final_rows' => array_values($finalRows),
            'current_source_admitted' => !$currentRollback,
            'next_source_admitted' => !$nextRollback,
            'discarded_returning_count' => $current['discarded_returning_count'] + $next['discarded_returning_count'],
            'dependency_closure' => 'reuses-native-recursive-trigger-returning-savepoint-and-view-current-source-plans',
            'dependencies' => array_values(array_unique(array_merge(
                (array) ($current['dependencies'] ?? []),
                (array) ($next['dependencies'] ?? []),
                [
                    'sqlite-trigger-returning-savepoint-view-current-source-next136',
                    'sqlite-trigger-recursive-returning-savepoint-current-source',
                    'sqlite-trigger-recursive-view-returning-current-source-next143',
                ],
            ))),
        ];
    }

    private static function statusNext143(bool $currentRollback, bool $nextRollback): string
    {
        if ($currentRollback && !$nextRollback) {
            return 'current-recursive-view-rollback-next-source-admitted';
        }
        if (!$currentRollback && $nextRollback) {
            return 'current-recursive-view-admitted-next-source-rolled-back';
        }
        if ($currentRollback && $nextRollback) {
            return 'current-next-recursive-view-savepoints-rolled-back';
        }

        return 'current-next-recursive-view-returning-source-admitted';
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function streamNext143(array $yielded, string $phase, string $source, string $view, bool $admitted): array
    {
        $stream = [];
        foreach ($yielded as $ordinal => $row) {
            $stream[] = [
                'phase' => $phase,
                'view' => $view,
                'source' => $source,
                'source_ordinal' => $ordinal,
                'returning_ordinal' => $ordinal,
                'admitted' => $admitted,
                'rolled_back_after_yield' => (bool) ($row['rolled_back_after_yield'] ?? false),
                'returning' => (array) ($row['row'] ?? []),
            ];
        }

        return $stream;
    }

    /**
     * @param list<array<string,mixed>> $stream
     * @return list<array<string,mixed>>
     */
    private static function rowsNext143(array $stream): array
    {
        return array_values(array_map(static fn (array $row): array => (array) $row['returning'], $stream));
    }

    private static function identifierNext143(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} is malformed");
        }

        return $identifier;
    }

    private static function sourceTokenNext143(string $token): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]+$/', $token)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING source token is malformed');
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where?:callable(array<string,mixed>,string,int):bool,order_by?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where?:callable(array<string,mixed>,string,int):bool,order_by?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{admit_next_source?:bool,max_depth?:int,savepoint?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext157(
        array $rows,
        array $currentRoots,
        array $nextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next157 projection cannot be empty');
        }

        $baseRows = self::normalizeRowsNext157($rows);
        $currentView = self::normalizeViewNext157($currentView, 'current view');
        $nextView = self::normalizeViewNext157($nextView, 'next view');
        $maxDepth = self::positiveIntNext157($options['max_depth'] ?? 8, 'max depth');
        $savepoint = self::tokenNext157((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_next157'), 'savepoint');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);

        $current = self::runViewSourceNext157($baseRows, $currentRoots, $currentView, $returning, $maxDepth, 'current');
        $nextBaseRows = $admitNext ? $current['rows'] : $baseRows;
        $next = self::runViewSourceNext157($nextBaseRows, $nextRoots, $nextView, $returning, $maxDepth, 'next');

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-returning-next-source-admitted-next157'
                : 'trigger-recursive-view-returning-current-source-pinned-next157',
            'savepoint' => $savepoint,
            'current_view' => self::viewSummaryNext157($currentView),
            'next_view' => self::viewSummaryNext157($nextView),
            'visible_view' => self::viewSummaryNext157($admitNext ? $nextView : $currentView),
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $admitNext ? $next['rows'] : $baseRows,
            'current_recursive_rows' => $current['recursive_rows'],
            'attempted_next_recursive_rows' => $next['recursive_rows'],
            'next_recursive_rows' => $admitNext ? $next['recursive_rows'] : [],
            'current_returning_rows' => $current['returning_rows'],
            'attempted_next_returning_rows' => $next['returning_rows'],
            'next_returning_rows' => $admitNext ? $next['returning_rows'] : [],
            'current_yield_stream' => $current['yield_stream'],
            'attempted_next_yield_stream' => $next['yield_stream'],
            'next_yield_stream' => $admitNext ? $next['yield_stream'] : [],
            'current_changes' => $current['changes'],
            'attempted_next_changes' => $next['changes'],
            'next_changes' => $admitNext ? $next['changes'] : 0,
            'changes' => $admitNext ? $current['changes'] + $next['changes'] : 0,
            'statement_rows' => $current['statement_rows'] + ($admitNext ? $next['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + $next['statement_rows'],
            'recursive_depths' => [
                'current' => $current['depths'],
                'attempted_next' => $next['depths'],
                'next' => $admitNext ? $next['depths'] : [],
            ],
            'trigger_source_changed' => $currentView['trigger_source'] !== $nextView['trigger_source'],
            'next_source_admitted' => $admitNext,
            'yield_boundary' => $admitNext
                ? 'recursive-view-current-returning-drained-then-next-source-admitted'
                : 'recursive-view-current-returning-drained-before-next-source',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next157',
                'sqlite-recursive-view-source-materialization',
                'sqlite-instead-of-view-trigger-returning-drain',
                'sqlite-next-recursive-view-source-attempted-only',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRowsNext157(array $rows): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next157 rows must be a list');
        }

        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('option_name', $row)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next157 row option_name is missing');
            }
            $key = (string) $row['option_name'];
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("SQLite trigger recursive view next157 duplicate option_name {$key}");
            }
            $seen[$key] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where:callable(array<string,mixed>,string,int):bool,order_by:?string}
     */
    private static function normalizeViewNext157(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite trigger recursive view {$label} columns must be a non-empty list");
        }

        $where = $view['where'] ?? static fn (array $row, string $root, int $depth): bool => true;
        if (!is_callable($where)) {
            throw new InvalidArgumentException("SQLite trigger recursive view {$label} WHERE callback is malformed");
        }

        return [
            'name' => self::identifierNext157((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::tokenNext157((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifierNext157((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::tokenNext157((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'root_key' => self::identifierNext157((string) ($view['root_key'] ?? ''), $label . ' root key'),
            'parent_key' => self::identifierNext157((string) ($view['parent_key'] ?? ''), $label . ' parent key'),
            'columns' => array_map(static fn (mixed $column): string => self::identifierNext157((string) $column, $label . ' column'), $columns),
            'where' => $where,
            'order_by' => isset($view['order_by']) ? self::identifierNext157((string) $view['order_by'], $label . ' order column') : null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $roots
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where:callable(array<string,mixed>,string,int):bool,order_by:?string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,recursive_rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,changes:int,statement_rows:int,depths:list<int>}
     */
    private static function runViewSourceNext157(array $rows, array $roots, array $view, array $returning, int $maxDepth, string $phase): array
    {
        $recursiveRows = self::recursiveRowsNext157($rows, $roots, $view, $maxDepth);
        $yield = [];
        $returningRows = [];
        $depths = [];

        foreach ($recursiveRows as $ordinal => $viewRow) {
            $incoming = self::triggerRowNext157($viewRow, $view, $phase);
            $rows = self::upsertOptionNext157($rows, $incoming);
            $returningRow = self::returningRowNext157($returning, $incoming, $viewRow, $view['trigger_source'], $ordinal);
            $returningRows[] = [
                'phase' => $phase,
                'source' => $view['source'],
                'trigger_source' => $view['trigger_source'],
                'view' => $view['name'],
                'trigger' => $view['trigger'],
                'ordinal' => $ordinal,
                'root' => $viewRow['_root'],
                'depth' => $viewRow['_depth'],
                'returning' => $returningRow,
            ];
            $yield[] = [
                'phase' => $phase,
                'source' => $view['source'],
                'trigger_source' => $view['trigger_source'],
                'view' => $view['name'],
                'trigger' => $view['trigger'],
                'ordinal' => $ordinal,
                'root' => $viewRow['_root'],
                'depth' => $viewRow['_depth'],
                'view_row' => $viewRow,
                'incoming_row' => $incoming,
                'returning' => $returningRow,
            ];
            $depths[] = (int) $viewRow['_depth'];
        }

        return [
            'rows' => array_values($rows),
            'recursive_rows' => $recursiveRows,
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'changes' => count($returningRows),
            'statement_rows' => count($recursiveRows),
            'depths' => $depths,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $roots
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where:callable(array<string,mixed>,string,int):bool,order_by:?string} $view
     * @return list<array<string,mixed>>
     */
    private static function recursiveRowsNext157(array $rows, array $roots, array $view, int $maxDepth): array
    {
        if (!array_is_list($roots)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next157 roots must be a list');
        }

        $byParent = [];
        foreach ($rows as $row) {
            $parent = $row[$view['parent_key']] ?? null;
            if ($parent === null) {
                continue;
            }
            $byParent[(string) $parent][] = $row;
        }
        if ($view['order_by'] !== null) {
            foreach ($byParent as &$children) {
                usort($children, static fn (array $left, array $right): int => ($left[$view['order_by']] ?? null) <=> ($right[$view['order_by']] ?? null));
            }
            unset($children);
        }

        $out = [];
        foreach ($roots as $root) {
            if (!is_array($root) || !array_key_exists($view['root_key'], $root)) {
                throw new InvalidArgumentException("SQLite trigger recursive view next157 root {$view['root_key']} is missing");
            }
            $rootKey = (string) $root[$view['root_key']];
            $queue = [[$rootKey, 0]];
            $seen = [];
            while ($queue !== []) {
                [$name, $depth] = array_shift($queue);
                if ($depth > $maxDepth) {
                    continue;
                }
                foreach ($byParent[$name] ?? [] as $row) {
                    $child = (string) ($row['option_name'] ?? '');
                    $edge = $rootKey . '>' . $child;
                    if (isset($seen[$edge])) {
                        continue;
                    }
                    $seen[$edge] = true;
                    $nextDepth = $depth + 1;
                    if ($nextDepth > $maxDepth) {
                        continue;
                    }
                    if (!(bool) $view['where']($row, $rootKey, $nextDepth)) {
                        continue;
                    }
                    $viewRow = ['_root' => $rootKey, '_depth' => $nextDepth];
                    foreach ($view['columns'] as $column) {
                        $viewRow[$column] = $row[$column] ?? null;
                    }
                    $out[] = $viewRow;
                    $queue[] = [$child, $nextDepth];
                }
            }
        }

        return $out;
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where:callable(array<string,mixed>,string,int):bool,order_by:?string} $view
     * @return array<string,mixed>
     */
    private static function triggerRowNext157(array $viewRow, array $view, string $phase): array
    {
        return [
            'option_name' => 'audit:' . $phase . ':' . $viewRow['_root'] . ':' . $viewRow['option_name'],
            'option_value' => ($viewRow['option_value'] ?? '') . '|depth=' . $viewRow['_depth'],
            'autoload' => $viewRow['autoload'] ?? 'no',
            'parent_name' => $viewRow['_root'],
            'source' => $view['trigger_source'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function upsertOptionNext157(array $rows, array $incoming): array
    {
        foreach ($rows as $index => $row) {
            if (($row['option_name'] ?? null) === $incoming['option_name']) {
                $rows[$index] = array_replace($row, $incoming);
                return $rows;
            }
        }

        $rows[] = $incoming;
        return $rows;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRowNext157(array $returning, array $incoming, array $viewRow, string $triggerSource, int $ordinal): array
    {
        $out = [];
        foreach ($returning as $index => $projection) {
            if (is_string($projection)) {
                $out[$projection] = $incoming[$projection] ?? $viewRow[$projection] ?? null;
                continue;
            }
            if (is_callable($projection)) {
                $out['expr' . $index] = $projection($incoming, $viewRow, $triggerSource, $ordinal);
                continue;
            }
            $expr = (string) ($projection['expr'] ?? '');
            $alias = (string) ($projection['as'] ?? $expr);
            $out[$alias] = match ($expr) {
                'trigger_source' => $triggerSource,
                'ordinal' => $ordinal,
                'root' => $viewRow['_root'],
                'depth' => $viewRow['_depth'],
                default => $incoming[$expr] ?? $viewRow[$expr] ?? null,
            };
        }

        return $out;
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where:callable(array<string,mixed>,string,int):bool,order_by:?string} $view
     * @return array<string,mixed>
     */
    private static function viewSummaryNext157(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'trigger' => $view['trigger'],
            'trigger_source' => $view['trigger_source'],
            'root_key' => $view['root_key'],
            'parent_key' => $view['parent_key'],
            'columns' => $view['columns'],
            'order_by' => $view['order_by'],
        ];
    }

    private static function positiveIntNext157(mixed $value, string $label): int
    {
        $value = (int) $value;
        if ($value < 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next157 {$label} must be positive");
        }

        return $value;
    }

    private static function identifierNext157(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next157 {$label} is malformed");
        }

        return $value;
    }

    private static function tokenNext157(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next157 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,current_source:string,next_source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @param array{release_current?:bool,rollback_next?:bool,recursive_triggers?:bool,savepoint?:string,max_depth?:int} $options
     * @return array<string,mixed>
     */
    public static function executeNext158(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $returning,
        array $options = [],
    ): array {
        self::validateColumnsNext158($uniqueColumns, 'unique column');
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING current-source projection must not be empty');
        }

        $view = self::normalizeViewNext158($view);
        $savepoint = self::tokenNext158((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_158'), 'savepoint');
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $releaseCurrent = (bool) ($options['release_current'] ?? false);
        $rollbackNext = (bool) ($options['rollback_next'] ?? false);
        $maxDepth = (int) ($options['max_depth'] ?? 8);
        if ($maxDepth < 0 || $maxDepth > 100) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING max depth is malformed');
        }

        $baseRows = self::normalizeRowsNext158($rows);
        $current = self::runSourceNext158($baseRows, $currentViewRows, $view, $view['current_source'], $uniqueColumns, $triggers, $returning, 'current', $recursive, $maxDepth);
        $nextInput = $releaseCurrent ? $current['rows'] : $baseRows;
        $next = self::runSourceNext158($nextInput, $nextViewRows, $view, $view['next_source'], $uniqueColumns, $triggers, $returning, 'next', $recursive, $maxDepth);

        $currentAdmitted = $releaseCurrent;
        $nextAdmitted = !$rollbackNext;
        $finalRows = $baseRows;
        $visibleSource = $view['current_source'];
        if ($releaseCurrent) {
            $finalRows = $current['rows'];
            $visibleSource = $view['current_source'];
        }
        if (!$rollbackNext) {
            $finalRows = $next['rows'];
            $visibleSource = $view['next_source'];
        }

        $admittedReturning = [];
        $suppressedReturning = [];
        $currentStream = self::tagAdmissionNext158($current['yield_stream'], $currentAdmitted);
        $nextStream = self::tagAdmissionNext158($next['yield_stream'], $nextAdmitted);
        if ($currentAdmitted) {
            $admittedReturning = array_merge($admittedReturning, $current['returning_rows']);
        } else {
            $suppressedReturning = array_merge($suppressedReturning, $current['returning_rows']);
        }
        if ($nextAdmitted) {
            $admittedReturning = array_merge($admittedReturning, $next['returning_rows']);
        } else {
            $suppressedReturning = array_merge($suppressedReturning, $next['returning_rows']);
        }

        return [
            'status' => self::statusNext158($releaseCurrent, $rollbackNext),
            'savepoint' => $savepoint,
            'view' => $view['name'],
            'current_source' => $view['current_source'],
            'next_source' => $view['next_source'],
            'visible_source' => $visibleSource,
            'current_source_admitted' => $currentAdmitted,
            'next_source_admitted' => $nextAdmitted,
            'recursive_triggers' => $recursive,
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'next_rows' => $next['rows'],
            'after_savepoint' => $finalRows,
            'returning_rows' => $admittedReturning,
            'suppressed_returning_rows' => $suppressedReturning,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $next['returning_rows'],
            'current_yield_stream' => $currentStream,
            'next_yield_stream' => $nextStream,
            'attempted_source_stream' => array_merge($currentStream, $nextStream),
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $next['trigger_effects'],
            'changes' => ($currentAdmitted ? $current['changes'] : 0) + ($nextAdmitted ? $next['changes'] : 0),
            'current_changes' => $current['changes'],
            'next_changes' => $next['changes'],
            'discarded_returning_count' => count($suppressedReturning),
            'yield_boundary' => $releaseCurrent
                ? ($rollbackNext ? 'recursive-view-returning-current-source-admitted-next-rolled-back' : 'recursive-view-returning-current-next-sources-admitted')
                : 'recursive-view-returning-current-source-yield-before-next-source',
            'next_input' => $releaseCurrent ? 'current-phase-output' : 'saved-current-source',
            'dependency_closure' => 'reuses-native-recursive-trigger-returning-view-current-source-plans',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next158',
                'sqlite-instead-of-view-trigger-returning',
                'sqlite-recursive-trigger-side-effect-current-source-yield',
                'sqlite-returning-current-source-before-next-source',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $viewRows
     * @param array{name:string,current_source:string,next_source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int}
     */
    private static function runSourceNext158(array $rows, array $viewRows, array $view, string $source, array $uniqueColumns, array $triggers, array $returning, string $phase, bool $recursive, int $maxDepth): array
    {
        $yield = [];
        $returningRows = [];
        $effects = [];
        $changes = 0;

        foreach (array_values($viewRows) as $ordinal => $viewRow) {
            if (!is_array($viewRow)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING row must be an array');
            }
            $incoming = self::projectNext158($viewRow, $view['mapping']);
            [$rows, $stepYield, $stepReturning, $stepEffects, $stepChanges] = self::applyUpsertNext158($rows, $incoming, $uniqueColumns, $triggers, $returning, $phase, $view['name'], $source, (int) $ordinal, 0, null, $recursive, $maxDepth);
            $yield = array_merge($yield, $stepYield);
            $returningRows = array_merge($returningRows, $stepReturning);
            $effects = array_merge($effects, $stepEffects);
            $changes += $stepChanges;
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>,3:list<array<string,mixed>>,4:int}
     */
    private static function applyUpsertNext158(array $rows, array $incoming, array $uniqueColumns, array $triggers, array $returning, string $phase, string $viewName, string $source, int $ordinal, int $depth, ?string $trigger, bool $recursive, int $maxDepth): array
    {
        $index = self::conflictIndexNext158($rows, $incoming, $uniqueColumns);
        $old = $index === null ? null : $rows[$index];
        $event = $old === null ? 'insert' : 'update';
        $new = $old === null ? $incoming : array_replace($old, $incoming);
        if ($index === null) {
            $rows[] = $new;
        } else {
            $rows[$index] = $new;
        }

        $returningRow = self::returningRowNext158($returning, $new, $old, $incoming, $event, $ordinal, $depth, $source, $trigger);
        $returningRows = [[
            'phase' => $phase,
            'source' => $source,
            'view' => $viewName,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'trigger' => $trigger,
            'returning' => $returningRow,
        ]];
        $yield = [[
            'phase' => $phase,
            'source' => $source,
            'view' => $viewName,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'trigger' => $trigger,
            'status' => 'changed',
            'incoming_row' => $incoming,
            'current_row' => $old,
            'next_row' => $new,
            'returning' => $returningRow,
        ]];
        $effects = [];
        $changes = 1;

        foreach ($triggers as $rowTrigger) {
            self::validateTriggerNext158($rowTrigger);
            if (($rowTrigger['when'] ?? null) !== ($new['option_name'] ?? null)) {
                continue;
            }
            $effect = [
                'phase' => $phase,
                'source' => $source,
                'view' => $viewName,
                'view_ordinal' => $ordinal,
                'depth' => $depth,
                'trigger' => $rowTrigger['name'],
                'source_option' => $new['option_name'] ?? null,
                'target_option' => $rowTrigger['target'],
                'result' => $recursive && (bool) ($rowTrigger['recursive'] ?? true) ? 'recursive-upsert' : 'recursive-suppressed',
            ];
            $effects[] = $effect;
            if (!$recursive || !(bool) ($rowTrigger['recursive'] ?? true)) {
                continue;
            }
            if ($depth >= $maxDepth) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING trigger depth limit exceeded');
            }
            [$rows, $subYield, $subReturning, $subEffects, $subChanges] = self::applyUpsertNext158(
                $rows,
                ['option_name' => $rowTrigger['target'], 'option_value' => str_replace('{value}', (string) ($new['option_value'] ?? ''), $rowTrigger['value']), 'autoload' => $new['autoload'] ?? null],
                $uniqueColumns,
                $triggers,
                $returning,
                $phase,
                $viewName,
                $source,
                $ordinal,
                $depth + 1,
                $rowTrigger['name'],
                $recursive,
                $maxDepth,
            );
            $yield = array_merge($yield, $subYield);
            $returningRows = array_merge($returningRows, $subReturning);
            $effects = array_merge($effects, $subEffects);
            $changes += $subChanges;
        }

        return [$rows, $yield, $returningRows, $effects, $changes];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRowNext158(array $returning, array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source, ?string $trigger): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $incoming, $event, $ordinal, $depth, $source, $trigger);
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) && isset($term['as']) ? self::identifierNext158((string) $term['as'], 'returning alias') : self::aliasNext158($expr, $index);
            $row[$alias] = self::exprValueNext158($expr, $new, $old, $incoming, $event, $ordinal, $depth, $source, $trigger);
        }

        return $row;
    }

    private static function exprValueNext158(string $expr, array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source, ?string $trigger): mixed
    {
        return match ($expr) {
            '*' => $new,
            'event' => $event,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'source' => $source,
            'trigger' => $trigger,
            default => self::columnExprNext158($expr, $new, $old, $incoming),
        };
    }

    private static function columnExprNext158(string $expr, array $new, ?array $old, array $incoming): mixed
    {
        if (str_starts_with($expr, 'new.')) {
            return $new[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING old column is unavailable for insert');
            }
            return $old[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'old_or_null.')) {
            return $old[substr($expr, 12)] ?? null;
        }
        if (str_starts_with($expr, 'excluded.')) {
            return $incoming[substr($expr, 9)] ?? null;
        }

        return $new[$expr] ?? null;
    }

    /** @param list<array<string,mixed>> $stream @return list<array<string,mixed>> */
    private static function tagAdmissionNext158(array $stream, bool $admitted): array
    {
        foreach ($stream as $index => $row) {
            $stream[$index]['admitted'] = $admitted;
            $stream[$index]['rolled_back_after_yield'] = !$admitted;
        }

        return $stream;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function normalizeRowsNext158(array $rows): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING row must be an array');
            }
        }

        return array_values($rows);
    }

    /** @param array<string,mixed> $view @return array{name:string,current_source:string,next_source:string,mapping:array<string,string>} */
    private static function normalizeViewNext158(array $view): array
    {
        if (!is_array($view['mapping'] ?? null) || $view['mapping'] === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING mapping must not be empty');
        }
        $mapping = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            $mapping[self::identifierNext158((string) $viewColumn, 'view column')] = self::identifierNext158((string) $tableColumn, 'table column');
        }

        return [
            'name' => self::identifierNext158((string) ($view['name'] ?? ''), 'view name'),
            'current_source' => self::tokenNext158((string) ($view['current_source'] ?? ''), 'current source'),
            'next_source' => self::tokenNext158((string) ($view['next_source'] ?? ''), 'next source'),
            'mapping' => $mapping,
        ];
    }

    /** @param array<string,mixed> $viewRow @param array<string,string> $mapping @return array<string,mixed> */
    private static function projectNext158(array $viewRow, array $mapping): array
    {
        $incoming = [];
        foreach ($mapping as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING row is missing {$viewColumn}");
            }
            $incoming[$tableColumn] = $viewRow[$viewColumn];
        }

        return $incoming;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $uniqueColumns */
    private static function conflictIndexNext158(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new InvalidArgumentException("SQLite recursive view RETURNING unique column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return (int) $index;
        }

        return null;
    }

    /** @param list<string> $columns */
    private static function validateColumnsNext158(array $columns, string $label): void
    {
        if ($columns === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} list must not be empty");
        }
        foreach ($columns as $column) {
            self::identifierNext158((string) $column, $label);
        }
    }

    /** @param array<string,mixed> $trigger */
    private static function validateTriggerNext158(array $trigger): void
    {
        self::identifierNext158((string) ($trigger['name'] ?? ''), 'trigger name');
        self::identifierNext158((string) ($trigger['target'] ?? ''), 'trigger target');
        if (!array_key_exists('when', $trigger) || !array_key_exists('value', $trigger)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING trigger is incomplete');
        }
    }

    private static function statusNext158(bool $releaseCurrent, bool $rollbackNext): string
    {
        if ($releaseCurrent && $rollbackNext) {
            return 'trigger-recursive-view-returning-current-source-admitted-next-rolled-back-next158';
        }
        if ($releaseCurrent) {
            return 'trigger-recursive-view-returning-current-next-source-admitted-next158';
        }

        return 'trigger-recursive-view-returning-current-source-retained-next158';
    }

    private static function aliasNext158(string $expr, int $index): string
    {
        if ($expr === '*') {
            return 'row';
        }
        $last = str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr;

        return self::identifierNext158($last !== '' ? $last : 'expr' . $index, 'returning alias');
    }

    private static function identifierNext158(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function tokenNext158(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_@.-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_next_source?:bool,max_depth?:int,savepoint?:string,current_generation?:string,next_generation?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext160(
        array $rows,
        array $currentRoots,
        array $nextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $currentGeneration = self::sourceTokenNext160((string) ($options['current_generation'] ?? 'current-source-next160'));
        $nextGeneration = self::sourceTokenNext160((string) ($options['next_generation'] ?? 'next-source-next160'));
        $releaseNext = (bool) ($options['release_next_source'] ?? false);

        $plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext157(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'admit_next_source' => $releaseNext,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_returning_next160',
            ],
        );

        $currentReturning = self::annotateReturningNext160(
            $plan['current_returning_rows'],
            $currentGeneration,
            'current-returning-drained',
            true,
        );
        $attemptedNextReturning = self::annotateReturningNext160(
            $plan['attempted_next_returning_rows'],
            $nextGeneration,
            $releaseNext ? 'next-returning-released' : 'next-returning-attempted-only',
            $releaseNext,
        );

        $plan['status'] = $releaseNext
            ? 'trigger-recursive-view-returning-current-source-release-next160'
            : 'trigger-recursive-view-returning-current-source-barrier-next160';
        $plan['current_generation'] = $currentGeneration;
        $plan['next_generation'] = $nextGeneration;
        $plan['source_barrier'] = [
            'savepoint' => $plan['savepoint'],
            'current_generation' => $currentGeneration,
            'next_generation' => $nextGeneration,
            'current_source' => $plan['current_view']['source'],
            'next_source' => $plan['next_view']['source'],
            'visible_source_before_release' => $plan['current_view']['source'],
            'visible_source_after_release' => $releaseNext ? $plan['next_view']['source'] : $plan['current_view']['source'],
            'current_returning_drained' => count($plan['current_returning_rows']),
            'next_returning_attempted' => count($plan['attempted_next_returning_rows']),
            'next_returning_visible' => $releaseNext ? count($plan['attempted_next_returning_rows']) : 0,
            'release_required_for_next_source' => true,
            'released' => $releaseNext,
        ];
        $plan['current_returning_rows'] = $currentReturning;
        $plan['attempted_next_returning_rows'] = $attemptedNextReturning;
        $plan['next_returning_rows'] = $releaseNext ? $attemptedNextReturning : [];
        $plan['returning_visibility'] = [
            'current_visible' => array_column($currentReturning, 'visibility_key'),
            'attempted_next' => array_column($attemptedNextReturning, 'visibility_key'),
            'visible' => array_column($releaseNext ? array_merge($currentReturning, $attemptedNextReturning) : $currentReturning, 'visibility_key'),
            'suppressed' => $releaseNext ? [] : array_column($attemptedNextReturning, 'visibility_key'),
        ];
        $plan['yield_boundary'] = $releaseNext
            ? 'current-source-returning-drained-release-admits-next-source-next160'
            : 'current-source-returning-drained-next-source-held-at-barrier-next160';
        $plan['dependencies'] = array_values(array_unique(array_merge($plan['dependencies'], [
            'sqlite-trigger-recursive-view-returning-current-source-next160',
            'sqlite-returning-source-generation-barrier',
            'sqlite-next-source-release-required-after-current-returning',
        ])));

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function annotateReturningNext160(array $rows, string $generation, string $visibility, bool $visible): array
    {
        $out = [];
        foreach ($rows as $row) {
            $returning = $row['returning'] ?? [];
            if (!is_array($returning)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next160 returning row is malformed');
            }
            $name = (string) ($returning['option_name'] ?? $row['ordinal'] ?? count($out));
            $row['source_generation'] = $generation;
            $row['visibility'] = $visibility;
            $row['visible_to_statement'] = $visible;
            $row['visibility_key'] = $generation . ':' . $name;
            $out[] = $row;
        }

        return $out;
    }

    private static function sourceTokenNext160(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('SQLite trigger recursive view next160 source generation is malformed');
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext161(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifierNext161((string) ($options['key'] ?? 'option_name'), 'key');
        $savepoint = self::tokenNext161((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_161'), 'savepoint');
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 2);
        if ($maxDepth < 0) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 max depth must be non-negative');
        }
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 projection cannot be empty');
        }

        $baseRows = self::normalizeRowsNext161($rows, $key);
        $currentView = self::normalizeViewNext161($currentView, 'current view');
        $nextView = self::normalizeViewNext161($nextView, 'next view');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);

        $current = self::runViewSourceNext161($baseRows, $currentInput, $currentView, $returning, $key, $recursive, $maxDepth, 'current');
        $nextAttempt = self::runViewSourceNext161($admitNext ? $current['rows'] : $baseRows, $nextInput, $nextView, $returning, $key, $recursive, $maxDepth, 'next');

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-returning-next-source-admitted-next161'
                : 'trigger-recursive-view-returning-current-source-pinned-next161',
            'savepoint' => $savepoint,
            'key' => $key,
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'current_view' => self::viewSummaryNext161($currentView),
            'next_view' => self::viewSummaryNext161($nextView),
            'visible_view' => self::viewSummaryNext161($admitNext ? $nextView : $currentView),
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $admitNext ? $nextAttempt['rows'] : $baseRows,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $admitNext ? $nextAttempt['returning_rows'] : [],
            'attempted_next_returning_rows' => $nextAttempt['returning_rows'],
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $admitNext ? $nextAttempt['yield_stream'] : [],
            'attempted_next_yield_stream' => $nextAttempt['yield_stream'],
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $admitNext ? $nextAttempt['trigger_effects'] : [],
            'attempted_next_trigger_effects' => $nextAttempt['trigger_effects'],
            'current_recursive_edges' => $current['recursive_edges'],
            'next_recursive_edges' => $admitNext ? $nextAttempt['recursive_edges'] : [],
            'attempted_next_recursive_edges' => $nextAttempt['recursive_edges'],
            'current_changes' => $current['changes'],
            'next_changes' => $admitNext ? $nextAttempt['changes'] : 0,
            'attempted_next_changes' => $nextAttempt['changes'],
            'changes' => $admitNext ? $current['changes'] + $nextAttempt['changes'] : 0,
            'statement_rows' => $current['statement_rows'] + ($admitNext ? $nextAttempt['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + $nextAttempt['statement_rows'],
            'next_source_admitted' => $admitNext,
            'trigger_source_changed' => $currentView['trigger_source'] !== $nextView['trigger_source'],
            'yield_boundary' => $admitNext
                ? 'recursive-view-returning-next-source-admitted-after-current-drain'
                : 'recursive-view-returning-current-source-drained-before-next-source',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next161',
                'sqlite-instead-of-view-trigger-recursive-returning',
                'sqlite-current-view-source-returning-drain',
                'sqlite-next-view-source-attempted-only',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRowsNext161(array $rows, string $key): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string}
     */
    private static function normalizeViewNext161(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        $mapping = $view['mapping'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} columns must be a non-empty list");
        }
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} mapping must not be empty");
        }

        $normalized = [
            'name' => self::identifierNext161((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::tokenNext161((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifierNext161((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::tokenNext161((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'columns' => array_map(static fn (mixed $column): string => self::identifierNext161((string) $column, $label . ' column'), $columns),
            'mapping' => [],
            'recursive_column' => self::identifierNext161((string) ($view['recursive_column'] ?? 'name'), $label . ' recursive column'),
            'recursive_suffix' => self::tokenNext161((string) ($view['recursive_suffix'] ?? '_child'), $label . ' recursive suffix'),
            'audit_label' => self::tokenNext161((string) ($view['audit_label'] ?? $label), $label . ' audit label'),
        ];
        foreach ($mapping as $viewColumn => $tableColumn) {
            $viewColumn = self::identifierNext161((string) $viewColumn, $label . ' mapping view column');
            if (!in_array($viewColumn, $normalized['columns'], true)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} mapping column {$viewColumn} is not visible");
            }
            $normalized['mapping'][$viewColumn] = self::identifierNext161((string) $tableColumn, $label . ' mapping table column');
        }
        if (!array_key_exists($normalized['recursive_column'], $normalized['mapping'])) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} recursive column is not mapped");
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $input
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,recursive_edges:list<array<string,mixed>>,changes:int,statement_rows:int}
     */
    private static function runViewSourceNext161(array $rows, array $input, array $view, array $returning, string $key, bool $recursive, int $maxDepth, string $phase): array
    {
        $queue = [];
        foreach (array_values($input) as $ordinal => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next161 input row must be an array');
            }
            $queue[] = ['view_row' => $row, 'ordinal' => (int) $ordinal, 'depth' => 0, 'parent' => null];
        }

        $yield = [];
        $returningRows = [];
        $effects = [];
        $edges = [];
        $changes = 0;
        $statementRows = count($queue);

        while ($queue !== []) {
            $item = array_shift($queue);
            $incoming = self::projectViewRowNext161($item['view_row'], $view);
            $rowKey = (string) ($incoming[$key] ?? '');
            if ($rowKey === '') {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 projected key {$key} is empty");
            }
            $event = self::rowIndexNext161($rows, $key, $rowKey) === null ? 'insert' : 'update';
            $rows = self::upsertRowNext161($rows, $key, $incoming);
            $returningRow = self::returningRowNext161($returning, $incoming, $item['view_row'], $event, (int) $item['ordinal'], (int) $item['depth'], $view['trigger_source']);
            $envelope = self::returningEnvelopeNext161($phase, $view, (int) $item['ordinal'], (int) $item['depth'], $event, $returningRow);
            $returningRows[] = $envelope;
            $yield[] = $envelope + [
                'status' => 'changed',
                'view_row' => $item['view_row'],
                'incoming_row' => $incoming,
                'parent_key' => $item['parent'],
            ];
            $effects[] = [
                'phase' => $phase,
                'ordinal' => (int) $item['ordinal'],
                'depth' => (int) $item['depth'],
                'trigger' => $view['trigger'],
                'trigger_source' => $view['trigger_source'],
                'audit_label' => $view['audit_label'],
                'event' => $event,
                'row_key' => $rowKey,
                'parent_key' => $item['parent'],
            ];
            ++$changes;

            if (!$recursive || (int) $item['depth'] >= $maxDepth || ($item['view_row']['spawn_child'] ?? true) === false) {
                continue;
            }

            $childViewRow = self::childViewRowNext161($item['view_row'], $view);
            $queue[] = [
                'view_row' => $childViewRow,
                'ordinal' => $statementRows,
                'depth' => (int) $item['depth'] + 1,
                'parent' => $rowKey,
            ];
            $edges[] = [
                'phase' => $phase,
                'parent_key' => $rowKey,
                'child_key' => (string) $childViewRow[$view['recursive_column']],
                'parent_depth' => (int) $item['depth'],
                'child_depth' => (int) $item['depth'] + 1,
                'source' => $view['source'],
                'trigger_source' => $view['trigger_source'],
            ];
            ++$statementRows;
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'recursive_edges' => $edges,
            'changes' => $changes,
            'statement_rows' => $statementRows,
        ];
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function projectViewRowNext161(array $viewRow, array $view): array
    {
        $row = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 missing view column {$viewColumn}");
            }
            $row[$tableColumn] = $viewRow[$viewColumn];
        }
        $row['source'] = $viewRow['origin'] ?? $view['trigger_source'];

        return $row;
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function childViewRowNext161(array $viewRow, array $view): array
    {
        $child = $viewRow;
        $column = $view['recursive_column'];
        $child[$column] = (string) $viewRow[$column] . $view['recursive_suffix'];
        if (array_key_exists('value', $child)) {
            $child['value'] = (string) $child['value'] . '/child';
        }
        if (array_key_exists('origin', $child)) {
            $child['origin'] = (string) $child['origin'] . '/recursive';
        }

        return $child;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function upsertRowNext161(array $rows, string $key, array $incoming): array
    {
        $index = self::rowIndexNext161($rows, $key, (string) $incoming[$key]);
        if ($index === null) {
            $rows[] = $incoming;
            return $rows;
        }
        $rows[$index] = array_replace($rows[$index], $incoming);

        return $rows;
    }

    private static function rowIndexNext161(array $rows, string $key, string $value): ?int
    {
        foreach ($rows as $index => $row) {
            if ((string) ($row[$key] ?? '') === $value) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRowNext161(array $returning, array $new, array $viewRow, string $event, int $ordinal, int $depth, string $source): array
    {
        $out = [];
        foreach (array_values($returning) as $index => $expr) {
            if (is_string($expr)) {
                $alias = str_starts_with($expr, 'new.') ? substr($expr, 4) : $expr;
                $out[$alias] = self::exprValueNext161($expr, $new, $viewRow, $event, $ordinal, $depth, $source);
                continue;
            }
            if (is_array($expr)) {
                $sql = (string) ($expr['expr'] ?? '');
                $alias = (string) ($expr['as'] ?? (str_starts_with($sql, 'new.') ? substr($sql, 4) : $sql));
                $out[self::identifierNext161($alias, 'RETURNING alias')] = self::exprValueNext161($sql, $new, $viewRow, $event, $ordinal, $depth, $source);
                continue;
            }
            if (is_callable($expr)) {
                $out['expr' . $index] = $expr($new, $viewRow, $event, $ordinal, $depth, $source);
                continue;
            }
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 projection expression is unsupported');
        }

        return $out;
    }

    private static function exprValueNext161(string $expr, array $new, array $viewRow, string $event, int $ordinal, int $depth, string $source): mixed
    {
        return match ($expr) {
            'event' => $event,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'trigger_source', 'source' => $source,
            default => str_starts_with($expr, 'new.')
                ? ($new[substr($expr, 4)] ?? null)
                : (str_starts_with($expr, 'view.')
                    ? ($viewRow[substr($expr, 5)] ?? null)
                    : ($new[$expr] ?? null)),
        };
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function returningEnvelopeNext161(string $phase, array $view, int $ordinal, int $depth, string $event, array $returning): array
    {
        return [
            'phase' => $phase,
            'source' => $view['source'],
            'trigger_source' => $view['trigger_source'],
            'view' => $view['name'],
            'trigger' => $view['trigger'],
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'returning' => $returning,
        ];
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function viewSummaryNext161(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'trigger' => $view['trigger'],
            'trigger_source' => $view['trigger_source'],
            'columns' => $view['columns'],
            'mapping' => $view['mapping'],
            'recursive_column' => $view['recursive_column'],
            'recursive_suffix' => $view['recursive_suffix'],
            'audit_label' => $view['audit_label'],
        ];
    }

    private static function identifierNext161(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} is malformed");
        }

        return $value;
    }

    private static function tokenNext161(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext162(
        array $rows,
        array $currentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $releaseCount = self::releaseCountNext162($options['release_staged_sources'] ?? 0);
        $currentGeneration = self::tokenNext162((string) ($options['current_generation'] ?? 'wp-import-current-162'), 'current generation');
        $firstNextGeneration = self::tokenNext162((string) ($options['first_next_generation'] ?? 'wp-import-next-162-a'), 'first next generation');
        $secondNextGeneration = self::tokenNext162((string) ($options['second_next_generation'] ?? 'wp-import-next-162-b'), 'second next generation');
        $savepoint = self::tokenNext162((string) ($options['savepoint'] ?? 'wp_recursive_view_next162'), 'savepoint');
        $maxDepth = $options['max_depth'] ?? 8;

        $first = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext160(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseCount >= 1,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_first',
                'current_generation' => $currentGeneration,
                'next_generation' => $firstNextGeneration,
            ],
        );

        $secondBaseRows = $releaseCount >= 1 ? $first['after_savepoint'] : $rows;
        $second = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext160(
            $secondBaseRows,
            $currentRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseCount >= 2,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_second',
                'current_generation' => $currentGeneration,
                'next_generation' => $secondNextGeneration,
            ],
        );

        $firstVisible = $releaseCount >= 1;
        $secondVisible = $releaseCount >= 2;
        $visibleRows = array_merge(
            $first['current_returning_rows'],
            $firstVisible ? $first['attempted_next_returning_rows'] : [],
            $secondVisible ? $second['attempted_next_returning_rows'] : [],
        );
        $suppressedRows = array_merge(
            $firstVisible ? [] : $first['attempted_next_returning_rows'],
            $secondVisible ? [] : $second['attempted_next_returning_rows'],
        );

        return [
            'status' => match ($releaseCount) {
                0 => 'trigger-recursive-view-returning-current-source-queue-held-next162',
                1 => 'trigger-recursive-view-returning-current-source-first-next-released-next162',
                default => 'trigger-recursive-view-returning-current-source-all-next-released-next162',
            },
            'savepoint' => $savepoint,
            'current_generation' => $currentGeneration,
            'staged_generations' => [$firstNextGeneration, $secondNextGeneration],
            'visible_generation' => $releaseCount === 0 ? $currentGeneration : ($releaseCount === 1 ? $firstNextGeneration : $secondNextGeneration),
            'current_source' => $first['source_barrier']['current_source'],
            'next_source' => $first['source_barrier']['next_source'],
            'first_stage' => $first,
            'second_stage' => $second,
            'next_source_queue' => [
                [
                    'generation' => $firstNextGeneration,
                    'roots' => array_values($firstNextRoots),
                    'attempted_returning' => count($first['attempted_next_returning_rows']),
                    'attempted_recursive' => count($first['attempted_next_recursive_rows']),
                    'visible' => $firstVisible,
                    'visibility_keys' => array_column($first['attempted_next_returning_rows'], 'visibility_key'),
                ],
                [
                    'generation' => $secondNextGeneration,
                    'roots' => array_values($secondNextRoots),
                    'attempted_returning' => count($second['attempted_next_returning_rows']),
                    'attempted_recursive' => count($second['attempted_next_recursive_rows']),
                    'visible' => $secondVisible,
                    'visibility_keys' => array_column($second['attempted_next_returning_rows'], 'visibility_key'),
                ],
            ],
            'returning_visibility' => [
                'visible' => array_column($visibleRows, 'visibility_key'),
                'suppressed' => array_column($suppressedRows, 'visibility_key'),
                'visible_count' => count($visibleRows),
                'suppressed_count' => count($suppressedRows),
            ],
            'statement_rows' => count($visibleRows),
            'attempted_statement_rows' => count($first['current_returning_rows']) + count($first['attempted_next_returning_rows']) + count($second['attempted_next_returning_rows']),
            'changes' => $releaseCount === 0 ? 0 : ($releaseCount === 1 ? $first['changes'] : $first['changes'] + $second['next_changes']),
            'after_savepoint' => $releaseCount === 0 ? $rows : ($releaseCount === 1 ? $first['after_savepoint'] : $second['after_savepoint']),
            'yield_boundary' => match ($releaseCount) {
                0 => 'recursive-view-returning-current-source-two-next-yields-held-next162',
                1 => 'recursive-view-returning-current-source-first-next-yield-released-next162',
                default => 'recursive-view-returning-current-source-all-next-yields-released-next162',
            },
            'dependencies' => array_values(array_unique(array_merge($first['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next162',
                'sqlite-recursive-view-returning-next-source-fifo-queue',
                'sqlite-current-source-retained-across-multiple-returning-yields',
            ]))),
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-barriers',
        ];
    }

    private static function releaseCountNext162(mixed $value): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > 2) {
            throw new InvalidArgumentException('SQLite trigger recursive view next162 release count must be 0, 1, or 2');
        }

        return $count;
    }

    private static function tokenNext162(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next162 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_next_source?:bool,max_depth?:int,savepoint?:string,current_generation?:string,next_generation?:string,trigger_child_prefix?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext163(
        array $rows,
        array $currentRoots,
        array $nextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $prefix = self::tokenNext163((string) ($options['trigger_child_prefix'] ?? 'audit-child'), 'trigger child prefix');
        $releaseNext = (bool) ($options['release_next_source'] ?? false);
        $currentRows = self::normalizeRowsNext163($rows);
        $currentView = self::normalizeViewNext163($currentView, 'current view');
        $nextView = self::normalizeViewNext163($nextView, 'next view');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext160(
            $currentRows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseNext,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_returning_next163',
                'current_generation' => $options['current_generation'] ?? 'current-source-next163',
                'next_generation' => $options['next_generation'] ?? 'next-source-next163',
            ],
        );

        $generatedRows = self::triggerGeneratedChildrenNext163($base['current_returning_rows'], $prefix, $currentView);
        $currentNames = self::namesNext163($base['current_recursive_rows']);
        $generatedNames = self::namesNext163($generatedRows);
        $suppressed = array_values(array_intersect($generatedNames, $currentNames));
        $seededNextRows = self::releasedNextRowsNext163($currentRows, $base, $generatedRows, $nextRoots, $nextView, $returning);
        $seededNextNames = self::namesNext163($seededNextRows['recursive_rows']);

        $base['status'] = $releaseNext
            ? 'trigger-recursive-view-returning-snapshot-release-next163'
            : 'trigger-recursive-view-returning-snapshot-barrier-next163';
        $base['trigger_generated_rows'] = $generatedRows;
        $base['trigger_generated_names'] = $generatedNames;
        $base['current_snapshot_guard'] = [
            'source' => $base['source_barrier']['current_source'],
            'snapshot_taken_before_trigger_writes' => true,
            'generated_rows' => count($generatedRows),
            'generated_names' => $generatedNames,
            'current_recursive_names' => $currentNames,
            'reentrant_visible_names' => $suppressed,
            'reentrant_suppressed' => $suppressed === [],
            'reason' => 'current recursive view source is materialized before INSTEAD OF trigger RETURNING rows are drained',
        ];
        $base['next_source_seed'] = [
            'released' => $releaseNext,
            'source' => $base['source_barrier']['next_source'],
            'seeded_recursive_rows' => $releaseNext ? count($seededNextRows['recursive_rows']) : 0,
            'seeded_names' => $releaseNext ? $seededNextNames : [],
            'seeded_returning_keys' => $releaseNext ? array_column($seededNextRows['returning_rows'], 'visibility_key') : [],
            'seeded_changes' => $releaseNext ? count($seededNextRows['returning_rows']) : 0,
        ];
        $base['statement_rows'] += $releaseNext ? count($seededNextRows['returning_rows']) : 0;
        $base['changes'] += $releaseNext ? count($seededNextRows['returning_rows']) : 0;
        $base['next_returning_rows'] = $releaseNext ? array_merge($base['next_returning_rows'], $seededNextRows['returning_rows']) : $base['next_returning_rows'];
        $base['returning_visibility']['visible'] = $releaseNext
            ? array_merge($base['returning_visibility']['visible'], array_column($seededNextRows['returning_rows'], 'visibility_key'))
            : $base['returning_visibility']['visible'];
        $base['yield_boundary'] = $releaseNext
            ? 'current-source-snapshot-drained-trigger-writes-seed-released-next-source-next163'
            : 'current-source-snapshot-drained-trigger-writes-held-from-recursive-reentry-next163';
        $base['dependencies'] = array_values(array_unique(array_merge($base['dependencies'], [
            'sqlite-trigger-recursive-view-returning-current-source-next163',
            'sqlite-recursive-view-source-snapshot-before-trigger-writes',
            'sqlite-trigger-generated-rows-seed-next-source-only-after-release',
        ])));

        return $base;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRowsNext163(array $rows): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next163 rows must be a list');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('option_name', $row)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next163 row option_name is missing');
            }
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,order_by:?string}
     */
    private static function normalizeViewNext163(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite trigger recursive view next163 {$label} columns must be a non-empty list");
        }

        return [
            'name' => self::identifierNext163((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::tokenNext163((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifierNext163((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::tokenNext163((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'root_key' => self::identifierNext163((string) ($view['root_key'] ?? ''), $label . ' root key'),
            'parent_key' => self::identifierNext163((string) ($view['parent_key'] ?? ''), $label . ' parent key'),
            'columns' => array_map(static fn (mixed $column): string => self::identifierNext163((string) $column, $label . ' column'), $columns),
            'order_by' => isset($view['order_by']) ? self::identifierNext163((string) $view['order_by'], $label . ' order column') : null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $returningRows
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,order_by:?string} $view
     * @return list<array<string,mixed>>
     */
    private static function triggerGeneratedChildrenNext163(array $returningRows, string $prefix, array $view): array
    {
        $out = [];
        foreach ($returningRows as $ordinal => $row) {
            $returning = $row['returning'] ?? null;
            if (!is_array($returning) || !array_key_exists('option_name', $returning)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next163 RETURNING row option_name is missing');
            }
            $parent = (string) $returning['option_name'];
            $name = $prefix . ':' . $parent;
            $out[] = [
                'option_name' => $name,
                'option_value' => 'generated-from:' . $parent,
                'autoload' => $returning['autoload'] ?? 'no',
                $view['parent_key'] => $parent,
                'priority' => 1000 + $ordinal,
                'source' => $view['trigger_source'],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $baseRows
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $generatedRows
     * @param list<array<string,mixed>> $nextRoots
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,order_by:?string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array{recursive_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>}
     */
    private static function releasedNextRowsNext163(array $baseRows, array $base, array $generatedRows, array $nextRoots, array $nextView, array $returning): array
    {
        if ($generatedRows === []) {
            return ['recursive_rows' => [], 'returning_rows' => []];
        }

        $rows = array_merge($baseRows, $generatedRows);
        $probe = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext160(
            $rows,
            [],
            $nextRoots,
            $nextView,
            $nextView,
            $returning,
            [
                'release_next_source' => true,
                'max_depth' => 1,
                'savepoint' => (string) $base['savepoint'] . '_seed',
                'current_generation' => (string) $base['current_generation'] . '-seed',
                'next_generation' => (string) $base['next_generation'] . '-seed',
            ],
        );

        $generated = array_flip(self::namesNext163($generatedRows));
        $recursiveRows = [];
        foreach ($probe['attempted_next_recursive_rows'] as $ordinal => $row) {
            if (!isset($generated[(string) ($row['option_name'] ?? '')])) {
                continue;
            }
            $row['_seed_ordinal'] = $ordinal;
            $recursiveRows[] = $row;
        }
        $returningRows = [];
        foreach ($recursiveRows as $recursiveRow) {
            $ordinal = (int) ($recursiveRow['_seed_ordinal'] ?? -1);
            $row = $probe['attempted_next_returning_rows'][$ordinal] ?? null;
            $returningRow = is_array($row) ? ($row['returning'] ?? []) : [];
            if (!is_array($row) || !is_array($returningRow)) {
                continue;
            }
            $row['source_generation'] = $base['next_generation'];
            $row['visibility'] = 'next-returning-released-from-trigger-generated-seed';
            $row['visible_to_statement'] = true;
            $row['visibility_key'] = (string) $base['next_generation'] . ':' . (string) ($returningRow['option_name'] ?? $recursiveRow['option_name']);
            $returningRows[] = $row;
        }

        return ['recursive_rows' => array_map(static function (array $row): array {
            unset($row['_seed_ordinal']);
            return $row;
        }, $recursiveRows), 'returning_rows' => $returningRows];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function namesNext163(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['option_name'] ?? ''), $rows));
    }

    private static function identifierNext163(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next163 {$label} is malformed");
        }

        return $value;
    }

    private static function tokenNext163(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next163 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext164(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifierNext164((string) ($options['key'] ?? 'option_name'), 'key');
        $savepoint = self::tokenNext164((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_164'), 'savepoint');
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 2);
        if ($maxDepth < 0) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next164 max depth must be non-negative');
        }
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next164 projection cannot be empty');
        }

        $skipColumn = self::identifierNext164((string) ($options['skip_column'] ?? 'autoload_flag'), 'skip column');
        $skipValue = $options['skip_value'] ?? 'skip';
        $conflictAction = strtolower((string) ($options['conflict_action'] ?? 'replace'));
        if (!in_array($conflictAction, ['replace', 'ignore'], true)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next164 conflict action must be replace or ignore');
        }

        $baseRows = self::normalizeRowsNext164($rows, $key);
        $currentView = self::normalizeViewNext164($currentView, 'current view');
        $nextView = self::normalizeViewNext164($nextView, 'next view');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);

        $current = self::runViewSourceNext164($baseRows, $currentInput, $currentView, $returning, $key, $recursive, $maxDepth, $skipColumn, $skipValue, $conflictAction, 'current');
        $nextAttempt = self::runViewSourceNext164($admitNext ? $current['rows'] : $baseRows, $nextInput, $nextView, $returning, $key, $recursive, $maxDepth, $skipColumn, $skipValue, $conflictAction, 'next');

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-returning-next-source-admitted-next164'
                : 'trigger-recursive-view-returning-current-source-pinned-next164',
            'savepoint' => $savepoint,
            'key' => $key,
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'skip_column' => $skipColumn,
            'skip_value' => $skipValue,
            'conflict_action' => $conflictAction,
            'current_view' => self::viewSummaryNext164($currentView),
            'next_view' => self::viewSummaryNext164($nextView),
            'visible_view' => self::viewSummaryNext164($admitNext ? $nextView : $currentView),
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $admitNext ? $nextAttempt['rows'] : $baseRows,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $admitNext ? $nextAttempt['returning_rows'] : [],
            'attempted_next_returning_rows' => $nextAttempt['returning_rows'],
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $admitNext ? $nextAttempt['yield_stream'] : [],
            'attempted_next_yield_stream' => $nextAttempt['yield_stream'],
            'current_skipped_rows' => $current['skipped_rows'],
            'next_skipped_rows' => $admitNext ? $nextAttempt['skipped_rows'] : [],
            'attempted_next_skipped_rows' => $nextAttempt['skipped_rows'],
            'current_replaced_keys' => $current['replaced_keys'],
            'next_replaced_keys' => $admitNext ? $nextAttempt['replaced_keys'] : [],
            'attempted_next_replaced_keys' => $nextAttempt['replaced_keys'],
            'current_recursive_edges' => $current['recursive_edges'],
            'next_recursive_edges' => $admitNext ? $nextAttempt['recursive_edges'] : [],
            'attempted_next_recursive_edges' => $nextAttempt['recursive_edges'],
            'current_changes' => $current['changes'],
            'next_changes' => $admitNext ? $nextAttempt['changes'] : 0,
            'attempted_next_changes' => $nextAttempt['changes'],
            'changes' => $admitNext ? $current['changes'] + $nextAttempt['changes'] : 0,
            'statement_rows' => $current['statement_rows'] + ($admitNext ? $nextAttempt['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + $nextAttempt['statement_rows'],
            'next_source_admitted' => $admitNext,
            'trigger_source_changed' => $currentView['trigger_source'] !== $nextView['trigger_source'],
            'yield_boundary' => $admitNext
                ? 'recursive-view-returning-next164-next-source-admitted-after-current-drain'
                : 'recursive-view-returning-next164-current-source-drained-before-next-source',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next164',
                'sqlite-instead-of-view-trigger-returning-raise-ignore',
                'sqlite-recursive-trigger-conflict-retry-current-source',
                'sqlite-next-view-source-attempted-only',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRowsNext164(array $rows, string $key): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next164 rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next164 row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next164 duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string}
     */
    private static function normalizeViewNext164(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        $mapping = $view['mapping'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next164 {$label} columns must be a non-empty list");
        }
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next164 {$label} mapping must not be empty");
        }

        $normalized = [
            'name' => self::identifierNext164((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::tokenNext164((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifierNext164((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::tokenNext164((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'columns' => array_map(static fn (mixed $column): string => self::identifierNext164((string) $column, $label . ' column'), $columns),
            'mapping' => [],
            'recursive_column' => self::identifierNext164((string) ($view['recursive_column'] ?? 'name'), $label . ' recursive column'),
            'recursive_suffix' => self::tokenNext164((string) ($view['recursive_suffix'] ?? '_child'), $label . ' recursive suffix'),
            'audit_label' => self::tokenNext164((string) ($view['audit_label'] ?? $label), $label . ' audit label'),
        ];
        foreach ($mapping as $viewColumn => $tableColumn) {
            $viewColumn = self::identifierNext164((string) $viewColumn, $label . ' mapping view column');
            if (!in_array($viewColumn, $normalized['columns'], true)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next164 {$label} mapping column {$viewColumn} is not visible");
            }
            $normalized['mapping'][$viewColumn] = self::identifierNext164((string) $tableColumn, $label . ' mapping table column');
        }
        if (!array_key_exists($normalized['recursive_column'], $normalized['mapping'])) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next164 {$label} recursive column is not mapped");
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $input
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,replaced_keys:list<string>,recursive_edges:list<array<string,mixed>>,changes:int,statement_rows:int}
     */
    private static function runViewSourceNext164(array $rows, array $input, array $view, array $returning, string $key, bool $recursive, int $maxDepth, string $skipColumn, mixed $skipValue, string $conflictAction, string $phase): array
    {
        $queue = [];
        foreach (array_values($input) as $ordinal => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next164 input row must be an array');
            }
            $queue[] = ['view_row' => $row, 'ordinal' => (int) $ordinal, 'depth' => 0, 'parent' => null];
        }

        $yield = [];
        $returningRows = [];
        $skipped = [];
        $replaced = [];
        $edges = [];
        $changes = 0;
        $statementRows = count($queue);

        while ($queue !== []) {
            $item = array_shift($queue);
            $incoming = self::projectViewRowNext164($item['view_row'], $view);
            $rowKey = (string) ($incoming[$key] ?? '');
            if ($rowKey === '') {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next164 projected key {$key} is empty");
            }
            $event = self::rowIndexNext164($rows, $key, $rowKey) === null ? 'insert' : 'update';
            $skip = ($item['view_row']['raise_ignore'] ?? false) === true
                || (array_key_exists($skipColumn, $item['view_row']) && $item['view_row'][$skipColumn] === $skipValue);
            if ($skip) {
                $envelope = self::returningEnvelopeNext164($phase, $view, (int) $item['ordinal'], (int) $item['depth'], $event, [
                    'option_name' => $rowKey,
                    'skip_reason' => 'raise-ignore',
                ]);
                $skipped[] = $envelope + ['parent_key' => $item['parent'], 'view_row' => $item['view_row']];
                $yield[] = $envelope + ['status' => 'skipped-raise-ignore', 'parent_key' => $item['parent']];
                continue;
            }

            $oldIndex = self::rowIndexNext164($rows, $key, $rowKey);
            $old = $oldIndex === null ? null : $rows[$oldIndex];
            if ($old !== null && $conflictAction === 'ignore') {
                $envelope = self::returningEnvelopeNext164($phase, $view, (int) $item['ordinal'], (int) $item['depth'], 'conflict-ignore', [
                    'option_name' => $rowKey,
                    'skip_reason' => 'conflict-ignore',
                ]);
                $skipped[] = $envelope + ['parent_key' => $item['parent'], 'view_row' => $item['view_row']];
                $yield[] = $envelope + ['status' => 'skipped-conflict-ignore', 'parent_key' => $item['parent']];
                continue;
            }

            $rows = self::upsertRowNext164($rows, $key, $incoming);
            if ($old !== null) {
                $replaced[] = $rowKey;
            }
            $returningRow = self::returningRowNext164($returning, $incoming, $item['view_row'], $old, $event, (int) $item['ordinal'], (int) $item['depth'], $view['trigger_source']);
            $envelope = self::returningEnvelopeNext164($phase, $view, (int) $item['ordinal'], (int) $item['depth'], $event, $returningRow);
            $returningRows[] = $envelope;
            $yield[] = $envelope + [
                'status' => 'changed',
                'view_row' => $item['view_row'],
                'incoming_row' => $incoming,
                'old_row' => $old,
                'parent_key' => $item['parent'],
            ];
            ++$changes;

            if (!$recursive || (int) $item['depth'] >= $maxDepth || ($item['view_row']['spawn_child'] ?? true) === false) {
                continue;
            }

            $childViewRow = self::childViewRowNext164($item['view_row'], $view);
            $queue[] = [
                'view_row' => $childViewRow,
                'ordinal' => $statementRows,
                'depth' => (int) $item['depth'] + 1,
                'parent' => $rowKey,
            ];
            $edges[] = [
                'phase' => $phase,
                'parent_key' => $rowKey,
                'child_key' => (string) $childViewRow[$view['recursive_column']],
                'parent_depth' => (int) $item['depth'],
                'child_depth' => (int) $item['depth'] + 1,
                'source' => $view['source'],
                'trigger_source' => $view['trigger_source'],
            ];
            ++$statementRows;
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'skipped_rows' => $skipped,
            'replaced_keys' => $replaced,
            'recursive_edges' => $edges,
            'changes' => $changes,
            'statement_rows' => $statementRows,
        ];
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function projectViewRowNext164(array $viewRow, array $view): array
    {
        $row = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next164 missing view column {$viewColumn}");
            }
            $row[$tableColumn] = $viewRow[$viewColumn];
        }
        $row['source'] = $viewRow['origin'] ?? $view['trigger_source'];

        return $row;
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function childViewRowNext164(array $viewRow, array $view): array
    {
        $child = $viewRow;
        $column = $view['recursive_column'];
        $child[$column] = (string) $viewRow[$column] . $view['recursive_suffix'];
        if (array_key_exists('value', $child)) {
            $child['value'] = (string) $child['value'] . '/child';
        }
        if (array_key_exists('origin', $child)) {
            $child['origin'] = (string) $child['origin'] . '/recursive';
        }

        return $child;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function upsertRowNext164(array $rows, string $key, array $incoming): array
    {
        $index = self::rowIndexNext164($rows, $key, (string) $incoming[$key]);
        if ($index === null) {
            $rows[] = $incoming;
            return $rows;
        }
        $rows[$index] = array_replace($rows[$index], $incoming);

        return $rows;
    }

    private static function rowIndexNext164(array $rows, string $key, string $value): ?int
    {
        foreach ($rows as $index => $row) {
            if ((string) ($row[$key] ?? '') === $value) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRowNext164(array $returning, array $new, array $viewRow, ?array $old, string $event, int $ordinal, int $depth, string $source): array
    {
        $out = [];
        foreach (array_values($returning) as $index => $expr) {
            if (is_string($expr)) {
                $alias = str_starts_with($expr, 'new.') ? substr($expr, 4) : $expr;
                $out[$alias] = self::exprValueNext164($expr, $new, $viewRow, $old, $event, $ordinal, $depth, $source);
                continue;
            }
            if (is_array($expr)) {
                $sql = (string) ($expr['expr'] ?? '');
                $alias = (string) ($expr['as'] ?? (str_starts_with($sql, 'new.') ? substr($sql, 4) : $sql));
                $out[self::identifierNext164($alias, 'RETURNING alias')] = self::exprValueNext164($sql, $new, $viewRow, $old, $event, $ordinal, $depth, $source);
                continue;
            }
            if (is_callable($expr)) {
                $out['expr' . $index] = $expr($new, $viewRow, $old, $event, $ordinal, $depth, $source);
                continue;
            }
            throw new InvalidArgumentException('SQLite recursive view RETURNING next164 projection expression is unsupported');
        }

        return $out;
    }

    private static function exprValueNext164(string $expr, array $new, array $viewRow, ?array $old, string $event, int $ordinal, int $depth, string $source): mixed
    {
        return match ($expr) {
            'event' => $event,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'trigger_source', 'source' => $source,
            default => str_starts_with($expr, 'new.')
                ? ($new[substr($expr, 4)] ?? null)
                : (str_starts_with($expr, 'old.')
                    ? ($old[substr($expr, 4)] ?? null)
                    : (str_starts_with($expr, 'view.')
                        ? ($viewRow[substr($expr, 5)] ?? null)
                        : ($new[$expr] ?? null))),
        };
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function returningEnvelopeNext164(string $phase, array $view, int $ordinal, int $depth, string $event, array $returning): array
    {
        return [
            'phase' => $phase,
            'source' => $view['source'],
            'trigger_source' => $view['trigger_source'],
            'view' => $view['name'],
            'trigger' => $view['trigger'],
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'returning' => $returning,
        ];
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function viewSummaryNext164(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'trigger' => $view['trigger'],
            'trigger_source' => $view['trigger_source'],
            'columns' => $view['columns'],
            'mapping' => $view['mapping'],
            'recursive_column' => $view['recursive_column'],
            'recursive_suffix' => $view['recursive_suffix'],
            'audit_label' => $view['audit_label'],
        ];
    }

    private static function identifierNext164(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next164 {$label} is malformed");
        }

        return $value;
    }

    private static function tokenNext164(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next164 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext165(
        array $rows,
        array $currentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $cursor = self::tokenNext165((string) ($options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_165'), 'cursor');
        $releaseCount = self::releaseCountNext165($options['release_staged_sources'] ?? 0);

        $queue = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext162(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => $releaseCount,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_next165',
                'current_generation' => $options['current_generation'] ?? 'wp-import-current-165',
                'first_next_generation' => $options['first_next_generation'] ?? 'wp-import-next-165-a',
                'second_next_generation' => $options['second_next_generation'] ?? 'wp-import-next-165-b',
            ],
        );

        $first = $queue['first_stage'];
        if (!is_array($first)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next165 first stage is malformed');
        }

        $currentRows = self::rowsNext165($first['current_returning_rows'] ?? [], 'current returning rows');
        $firstRows = self::rowsNext165($first['attempted_next_returning_rows'] ?? [], 'first next returning rows');
        $second = $queue['second_stage'] ?? null;
        if (!is_array($second)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next165 second stage is malformed');
        }
        $secondRows = self::rowsNext165($second['attempted_next_returning_rows'] ?? [], 'second next returning rows');

        $currentKeys = array_column($currentRows, 'visibility_key');
        $firstKeys = array_column($firstRows, 'visibility_key');
        $secondKeys = array_column($secondRows, 'visibility_key');
        $visibleKeys = self::stringListNext165($queue['returning_visibility']['visible'] ?? [], 'visible returning keys');
        $suppressedKeys = self::stringListNext165($queue['returning_visibility']['suppressed'] ?? [], 'suppressed returning keys');
        $currentGeneration = (string) $queue['current_generation'];
        $visibleGeneration = (string) $queue['visible_generation'];

        $steps = [];
        foreach ($currentKeys as $ordinal => $key) {
            $steps[] = [
                'cursor' => $cursor,
                'ordinal' => $ordinal,
                'phase' => 'current',
                'generation' => $currentGeneration,
                'visibility_key' => $key,
                'source' => $queue['current_source'],
                'visible' => true,
                'drained_before_next' => true,
            ];
        }
        foreach ($firstKeys as $ordinal => $key) {
            $visible = in_array($key, $visibleKeys, true);
            $steps[] = [
                'cursor' => $cursor,
                'ordinal' => $ordinal,
                'phase' => 'first-next',
                'generation' => $queue['staged_generations'][0],
                'visibility_key' => $key,
                'source' => $queue['next_source'],
                'visible' => $visible,
                'held_by_current_source' => !$visible,
            ];
        }
        foreach ($secondKeys as $ordinal => $key) {
            $visible = in_array($key, $visibleKeys, true);
            $steps[] = [
                'cursor' => $cursor,
                'ordinal' => $ordinal,
                'phase' => 'second-next',
                'generation' => $queue['staged_generations'][1],
                'visibility_key' => $key,
                'source' => $queue['next_source'],
                'visible' => $visible,
                'held_by_current_source' => !$visible,
            ];
        }

        $visibleSteps = array_values(array_filter($steps, static fn (array $step): bool => $step['visible'] === true));
        $heldSteps = array_values(array_filter($steps, static fn (array $step): bool => $step['visible'] === false));
        $sourceNextPlan = [
            'cursor' => $cursor,
            'current_generation' => $currentGeneration,
            'visible_generation' => $visibleGeneration,
            'release_count' => $releaseCount,
            'current_source_steps' => count($currentKeys),
            'staged_source_steps' => count($firstKeys) + count($secondKeys),
            'visible_steps' => count($visibleSteps),
            'held_steps' => count($heldSteps),
            'current_drained_before_staged' => self::currentBeforeStagedNext165($steps),
            'visible_keys' => array_column($visibleSteps, 'visibility_key'),
            'held_keys' => array_column($heldSteps, 'visibility_key'),
            'first_next_visible' => $releaseCount >= 1,
            'second_next_visible' => $releaseCount >= 2,
        ];

        return [
            'status' => match ($releaseCount) {
                0 => 'trigger-recursive-view-returning-current-source-next-cursor-held-next165',
                1 => 'trigger-recursive-view-returning-current-source-next-cursor-first-released-next165',
                default => 'trigger-recursive-view-returning-current-source-next-cursor-all-released-next165',
            },
            'savepoint' => $queue['savepoint'],
            'cursor' => $cursor,
            'queue' => $queue,
            'cursor_steps' => $steps,
            'visible_cursor_steps' => $visibleSteps,
            'held_cursor_steps' => $heldSteps,
            'source_next_plan' => $sourceNextPlan,
            'returning_visibility' => [
                'visible' => $visibleKeys,
                'suppressed' => $suppressedKeys,
                'current_visible' => $currentKeys,
                'first_next' => $firstKeys,
                'second_next' => $secondKeys,
            ],
            'statement_rows' => count($visibleSteps),
            'attempted_statement_rows' => count($steps),
            'changes' => $queue['changes'],
            'after_savepoint' => $queue['after_savepoint'],
            'yield_boundary' => match ($releaseCount) {
                0 => 'recursive-view-returning-current-source-next-cursor-held-next165',
                1 => 'recursive-view-returning-current-source-next-cursor-first-release-next165',
                default => 'recursive-view-returning-current-source-next-cursor-all-release-next165',
            },
            'dependencies' => array_values(array_unique(array_merge($queue['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next165',
                'sqlite-returning-current-source-next-cursor-drain',
                'sqlite-recursive-view-returning-staged-source-visibility',
            ]))),
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-queue-and-cursor-model',
        ];
    }

    private static function releaseCountNext165(mixed $value): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > 2) {
            throw new InvalidArgumentException('SQLite trigger recursive view next165 release count must be 0, 1, or 2');
        }

        return $count;
    }

    private static function tokenNext165(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next165 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext165(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite trigger recursive view next165 {$label} are malformed");
        }

        return $rows;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringListNext165(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite trigger recursive view next165 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    /**
     * @param list<array<string,mixed>> $steps
     */
    private static function currentBeforeStagedNext165(array $steps): bool
    {
        $seenStaged = false;
        foreach ($steps as $step) {
            if (($step['phase'] ?? '') === 'current' && $seenStaged) {
                return false;
            }
            if (($step['phase'] ?? '') !== 'current') {
                $seenStaged = true;
            }
        }

        return true;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_next_source?:bool,max_depth?:int,savepoint?:string,current_generation?:string,next_generation?:string,trigger_child_prefix?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext166(
        array $rows,
        array $currentRoots,
        array $nextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $releaseNext = (bool) ($options['release_next_source'] ?? true);
        $currentGeneration = self::tokenNext166((string) ($options['current_generation'] ?? 'current-source-next166'), 'current generation');
        $nextGeneration = self::tokenNext166((string) ($options['next_generation'] ?? 'next-source-next166'), 'next generation');
        $savepoint = self::tokenNext166((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_next166'), 'savepoint');

        $plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext163(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseNext,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $savepoint,
                'current_generation' => $currentGeneration,
                'next_generation' => $nextGeneration,
                'trigger_child_prefix' => $options['trigger_child_prefix'] ?? 'audit-child',
            ],
        );

        $held = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext163(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => false,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $savepoint . '_held_probe',
                'current_generation' => $currentGeneration,
                'next_generation' => $nextGeneration,
                'trigger_child_prefix' => $options['trigger_child_prefix'] ?? 'audit-child',
            ],
        );
        $releasedProbe = $releaseNext ? $plan : SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext163(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => true,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $savepoint . '_released_probe',
                'current_generation' => $currentGeneration,
                'next_generation' => $nextGeneration,
                'trigger_child_prefix' => $options['trigger_child_prefix'] ?? 'audit-child',
            ],
        );

        $currentTimeline = self::timelineRowsNext166($plan['current_returning_rows'], 'current-returning-drain', $currentGeneration, 0, true);
        $nextTimeline = $releaseNext
            ? self::timelineRowsNext166($plan['next_returning_rows'], 'next-source-after-current-drain', $nextGeneration, count($currentTimeline), true)
            : self::timelineRowsNext166($releasedProbe['next_returning_rows'], 'next-source-held-until-current-drain', $nextGeneration, count($currentTimeline), false);

        $visible = array_values(array_filter(array_merge($currentTimeline, $nextTimeline), static fn (array $row): bool => (bool) $row['visible']));
        $suppressed = array_values(array_filter(array_merge($currentTimeline, $nextTimeline), static fn (array $row): bool => !(bool) $row['visible']));
        $currentLastOrdinal = $currentTimeline === [] ? -1 : max(array_column($currentTimeline, 'ordinal'));
        $nextFirstOrdinal = $nextTimeline === [] ? null : min(array_column($nextTimeline, 'ordinal'));

        $plan['status'] = $releaseNext
            ? 'trigger-recursive-view-returning-current-drain-before-next-source-next166'
            : 'trigger-recursive-view-returning-current-drain-holds-next-source-next166';
        $plan['returning_drain'] = [
            'current_source' => $plan['source_barrier']['current_source'],
            'next_source' => $plan['source_barrier']['next_source'],
            'current_generation' => $currentGeneration,
            'next_generation' => $nextGeneration,
            'current_visible_count' => count($currentTimeline),
            'next_visible_count' => count(array_filter($nextTimeline, static fn (array $row): bool => (bool) $row['visible'])),
            'next_suppressed_count' => count(array_filter($nextTimeline, static fn (array $row): bool => !(bool) $row['visible'])),
            'current_last_ordinal' => $currentLastOrdinal,
            'next_first_ordinal' => $nextFirstOrdinal,
            'next_after_current_drain' => $nextFirstOrdinal === null || $nextFirstOrdinal > $currentLastOrdinal,
            'visible_keys' => array_column($visible, 'visibility_key'),
            'suppressed_keys' => array_column($suppressed, 'visibility_key'),
            'timeline' => array_merge($currentTimeline, $nextTimeline),
        ];
        $plan['next_source_admission'] = [
            'released' => $releaseNext,
            'seeded_by_trigger_generated_rows' => $plan['next_source_seed']['seeded_names'],
            'held_probe_seeded_names' => $held['next_source_seed']['seeded_names'],
            'held_probe_visible_keys' => $held['next_source_seed']['seeded_returning_keys'],
            'admission_reason' => $releaseNext
                ? 'current RETURNING drain completed before next source trigger seed admission'
                : 'next source remains held while current RETURNING rows are visible',
        ];
        $plan['yield_boundary'] = $releaseNext
            ? 'current-returning-drain-then-trigger-generated-next-source-next166'
            : 'current-returning-drain-with-next-source-held-next166';
        $plan['dependencies'] = array_values(array_unique(array_merge($plan['dependencies'], [
            'sqlite-trigger-recursive-view-returning-current-source-next166',
            'sqlite-returning-current-source-drain-before-next-source-admission',
            'sqlite-view-trigger-generated-rows-hidden-until-current-returning-drain',
        ])));
        $plan['dependency_closure'] = 'reuses-native-recursive-view-returning-source-barriers';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function timelineRowsNext166(array $rows, string $phase, string $generation, int $offset, bool $visible): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'] ?? [];
            if (!is_array($returning)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next166 RETURNING row is malformed');
            }
            $out[] = [
                'ordinal' => $offset + $index,
                'phase' => $phase,
                'generation' => $generation,
                'visible' => $visible,
                'visibility_key' => (string) ($row['visibility_key'] ?? ($generation . ':' . ($returning['option_name'] ?? $index))),
                'option_name' => (string) ($returning['option_name'] ?? ''),
                'root_name' => (string) ($returning['root_name'] ?? ''),
                'trigger_cookie' => (string) ($returning['trigger_cookie'] ?? ''),
            ];
        }

        return $out;
    }

    private static function tokenNext166(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next166 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext167(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $pageSize = (int) ($options['page_size'] ?? 2);
        if ($pageSize < 1) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next167 page size must be positive');
        }
        $drainCursor = self::tokenNext167((string) ($options['drain_cursor'] ?? 'current-returning-drain-167'), 'drain cursor');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext164(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentPages = self::pagesNext167($base['current_returning_rows'], $pageSize, 'current', $drainCursor);
        $attemptedNextPages = self::pagesNext167($base['attempted_next_returning_rows'], $pageSize, 'attempted-next', $drainCursor);
        $nextPages = self::pagesNext167($base['next_returning_rows'], $pageSize, 'next', $drainCursor);
        $currentComplete = self::pagesDrainedNext167($currentPages);
        $nextSourceAdmitted = (bool) $base['next_source_admitted'];

        return $base + [
            'status_next167' => $nextSourceAdmitted
                ? 'trigger-recursive-view-returning-next-source-admitted-after-current-drain-next167'
                : 'trigger-recursive-view-returning-current-source-drain-fenced-next167',
            'drain_cursor' => $drainCursor,
            'page_size' => $pageSize,
            'current_returning_pages' => $currentPages,
            'attempted_next_returning_pages' => $attemptedNextPages,
            'next_returning_pages' => $nextPages,
            'current_drain_complete' => $currentComplete,
            'next_source_visible_after_current_drain' => $nextSourceAdmitted && $currentComplete,
            'attempted_next_source_blocked_by_current_drain' => !$nextSourceAdmitted && $currentComplete,
            'visible_returning_pages' => $nextSourceAdmitted ? array_merge($currentPages, $nextPages) : $currentPages,
            'blocked_next_source_pages' => $nextSourceAdmitted ? [] : $attemptedNextPages,
            'source_signatures' => [
                'current' => self::sourceSignatureNext167($base['current_view']),
                'next' => self::sourceSignatureNext167($base['next_view']),
                'visible' => self::sourceSignatureNext167($base['visible_view']),
            ],
            'yield_boundary_next167' => $nextSourceAdmitted
                ? 'recursive-view-returning-next167-next-source-visible-after-current-pages-drained'
                : 'recursive-view-returning-next167-next-source-blocked-until-current-pages-drain',
            'dependencies_next167' => [
                'sqlite-trigger-recursive-view-returning-current-source-next167',
                'sqlite-returning-current-source-drain-before-next-source',
                'sqlite-view-trigger-recursive-returning-page-yield',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function pagesNext167(array $rows, int $pageSize, string $phase, string $cursor): array
    {
        $pages = [];
        foreach (array_chunk($rows, $pageSize) as $index => $chunk) {
            $last = $index === intdiv(max(count($rows) - 1, 0), $pageSize);
            $pages[] = [
                'cursor' => $cursor . ':' . $phase . ':' . $index,
                'phase' => $phase,
                'page' => $index,
                'count' => count($chunk),
                'drained' => true,
                'last' => $last,
                'sources' => array_values(array_unique(array_column($chunk, 'source'))),
                'trigger_sources' => array_values(array_unique(array_column($chunk, 'trigger_source'))),
                'names' => array_column(array_column($chunk, 'returning'), 'option_name'),
                'rows' => $chunk,
            ];
        }

        return $pages;
    }

    /**
     * @param list<array<string,mixed>> $pages
     */
    private static function pagesDrainedNext167(array $pages): bool
    {
        foreach ($pages as $page) {
            if (($page['drained'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $view
     */
    private static function sourceSignatureNext167(array $view): string
    {
        return implode('|', [
            (string) ($view['name'] ?? ''),
            (string) ($view['source'] ?? ''),
            (string) ($view['trigger'] ?? ''),
            (string) ($view['trigger_source'] ?? ''),
            implode(',', (array) ($view['columns'] ?? [])),
        ]);
    }

    private static function tokenNext167(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next167 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nestedCurrentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,nested_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext169(
        array $rows,
        array $currentRoots,
        array $nestedCurrentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $releaseCount = self::releaseCountNext169($options['release_staged_sources'] ?? 0);
        $cursor = self::tokenNext169((string) ($options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_169'), 'cursor');
        $savepoint = self::tokenNext169((string) ($options['savepoint'] ?? 'wp_recursive_view_next169'), 'savepoint');
        $currentGeneration = self::tokenNext169((string) ($options['current_generation'] ?? 'wp-import-current-169'), 'current generation');
        $nestedGeneration = self::tokenNext169((string) ($options['nested_generation'] ?? 'wp-import-current-169-nested'), 'nested generation');
        $firstNextGeneration = self::tokenNext169((string) ($options['first_next_generation'] ?? 'wp-import-next-169-a'), 'first next generation');
        $secondNextGeneration = self::tokenNext169((string) ($options['second_next_generation'] ?? 'wp-import-next-169-b'), 'second next generation');
        $maxDepth = (int) ($options['max_depth'] ?? 8);
        if ($maxDepth < 1) {
            throw new InvalidArgumentException('SQLite trigger recursive view next169 max depth must be positive');
        }

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext165(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => $releaseCount,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_base',
                'current_generation' => $currentGeneration,
                'first_next_generation' => $firstNextGeneration,
                'second_next_generation' => $secondNextGeneration,
                'cursor_name' => $cursor . '_base',
            ],
        );

        $nested = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext165(
            $rows,
            $nestedCurrentRoots,
            [],
            [],
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => 0,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_nested',
                'current_generation' => $nestedGeneration,
                'first_next_generation' => $firstNextGeneration . '_nested_empty_a',
                'second_next_generation' => $secondNextGeneration . '_nested_empty_b',
                'cursor_name' => $cursor . '_nested',
            ],
        );

        $baseCurrent = self::phaseStepsNext169($base['cursor_steps'], 'current', $cursor, 'current');
        $nestedCurrent = self::phaseStepsNext169($nested['cursor_steps'], 'current', $cursor, 'nested-current');
        $firstNext = self::phaseStepsNext169($base['cursor_steps'], 'first-next', $cursor, 'first-next');
        $secondNext = self::phaseStepsNext169($base['cursor_steps'], 'second-next', $cursor, 'second-next');
        $steps = array_values(array_merge($baseCurrent, $nestedCurrent, $firstNext, $secondNext));

        $visible = [];
        $held = [];
        foreach ($steps as $ordinal => $step) {
            $step['statement_ordinal'] = $ordinal;
            $isCurrent = in_array($step['phase'], ['current', 'nested-current'], true);
            $isVisible = $isCurrent
                || ($step['phase'] === 'first-next' && $releaseCount >= 1)
                || ($step['phase'] === 'second-next' && $releaseCount >= 2);
            $step['visible'] = $isVisible;
            $step['held_by_current_source'] = !$isVisible;
            if ($isVisible) {
                $visible[] = $step;
            } else {
                $held[] = $step;
            }
            $steps[$ordinal] = $step;
        }

        $currentCount = count($baseCurrent) + count($nestedCurrent);
        $stagedCount = count($firstNext) + count($secondNext);
        $sourceNextPlan = [
            'cursor' => $cursor,
            'release_count' => $releaseCount,
            'current_source_steps' => count($baseCurrent),
            'nested_current_source_steps' => count($nestedCurrent),
            'combined_current_source_steps' => $currentCount,
            'staged_source_steps' => $stagedCount,
            'visible_steps' => count($visible),
            'held_steps' => count($held),
            'current_drained_before_nested' => self::orderedBeforeNext169($steps, ['current'], ['nested-current']),
            'nested_drained_before_staged' => self::orderedBeforeNext169($steps, ['current', 'nested-current'], ['first-next', 'second-next']),
            'current_source_pinned_until_nested_drains' => $held !== [] && $releaseCount === 0,
            'first_next_visible' => $releaseCount >= 1,
            'second_next_visible' => $releaseCount >= 2,
            'visible_keys' => array_column($visible, 'visibility_key'),
            'held_keys' => array_column($held, 'visibility_key'),
        ];

        return [
            'status' => match ($releaseCount) {
                0 => 'trigger-recursive-view-returning-current-source-nested-held-next169',
                1 => 'trigger-recursive-view-returning-current-source-nested-first-released-next169',
                default => 'trigger-recursive-view-returning-current-source-nested-all-released-next169',
            },
            'savepoint' => $savepoint,
            'cursor' => $cursor,
            'base' => $base,
            'nested' => $nested,
            'cursor_steps' => $steps,
            'visible_cursor_steps' => $visible,
            'held_cursor_steps' => $held,
            'source_next_plan' => $sourceNextPlan,
            'statement_rows' => count($visible),
            'attempted_statement_rows' => count($steps),
            'changes' => $releaseCount === 0 ? $currentCount : count($visible),
            'returning_visibility' => [
                'visible' => array_column($visible, 'visibility_key'),
                'held' => array_column($held, 'visibility_key'),
                'current' => array_column($baseCurrent, 'visibility_key'),
                'nested_current' => array_column($nestedCurrent, 'visibility_key'),
                'first_next' => array_column($firstNext, 'visibility_key'),
                'second_next' => array_column($secondNext, 'visibility_key'),
            ],
            'yield_boundary' => match ($releaseCount) {
                0 => 'recursive-view-returning-current-source-nested-drain-before-held-next169',
                1 => 'recursive-view-returning-current-source-nested-drain-first-release-next169',
                default => 'recursive-view-returning-current-source-nested-drain-all-release-next169',
            },
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next169',
                'sqlite-returning-current-source-reentrant-drain',
                'sqlite-recursive-view-returning-nested-current-before-staged-next',
            ]))),
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-cursor-model-for-reentrant-drain',
        ];
    }

    private static function releaseCountNext169(mixed $value): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > 2) {
            throw new InvalidArgumentException('SQLite trigger recursive view next169 release count must be 0, 1, or 2');
        }

        return $count;
    }

    private static function tokenNext169(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next169 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param mixed $steps
     * @return list<array<string,mixed>>
     */
    private static function phaseStepsNext169(mixed $steps, string $sourcePhase, string $cursor, string $phase): array
    {
        if (!is_array($steps) || !array_is_list($steps)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next169 cursor steps are malformed');
        }

        $filtered = [];
        foreach ($steps as $step) {
            if (!is_array($step) || ($step['phase'] ?? null) !== $sourcePhase) {
                continue;
            }
            $step['cursor'] = $cursor;
            $step['phase'] = $phase;
            $filtered[] = $step;
        }

        return $filtered;
    }

    /**
     * @param list<array<string,mixed>> $steps
     * @param list<string> $early
     * @param list<string> $late
     */
    private static function orderedBeforeNext169(array $steps, array $early, array $late): bool
    {
        $seenLate = false;
        foreach ($steps as $step) {
            $phase = (string) ($step['phase'] ?? '');
            if (in_array($phase, $late, true)) {
                $seenLate = true;
            }
            if ($seenLate && in_array($phase, $early, true)) {
                return false;
            }
        }

        return true;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string,current_schema_cookie?:int,next_schema_cookie?:int,reprepare_token?:string,expected_reprepare_token?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext170(
        array $rows,
        array $currentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $currentCookie = self::nonNegativeIntNext170($options['current_schema_cookie'] ?? 1, 'current schema cookie');
        $nextCookie = self::nonNegativeIntNext170($options['next_schema_cookie'] ?? $currentCookie, 'next schema cookie');
        $token = self::tokenNext170((string) ($options['reprepare_token'] ?? 'wp-recursive-view-returning-next170'), 'reprepare token');
        $expectedToken = self::tokenNext170((string) ($options['expected_reprepare_token'] ?? $token), 'expected reprepare token');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext165(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => $options['release_staged_sources'] ?? 0,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_next170',
                'current_generation' => $options['current_generation'] ?? 'wp-import-current-170',
                'first_next_generation' => $options['first_next_generation'] ?? 'wp-import-next-170-a',
                'second_next_generation' => $options['second_next_generation'] ?? 'wp-import-next-170-b',
                'cursor_name' => $options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_170',
            ],
        );

        $sourceChanged = self::sourceChangedNext170($currentView, $nextView) || $currentCookie !== $nextCookie;
        $tokenMatches = $token === $expectedToken;
        $releaseRequested = (int) $base['source_next_plan']['release_count'];
        $releaseAllowed = !$sourceChanged || $tokenMatches;
        $steps = self::barrierStepsNext170($base['cursor_steps'], $sourceChanged, $releaseAllowed, $currentCookie, $nextCookie, $token, $expectedToken);
        $visible = array_values(array_filter($steps, static fn (array $step): bool => $step['visible_after_barrier']));
        $held = array_values(array_filter($steps, static fn (array $step): bool => !$step['visible_after_barrier']));
        $current = array_values(array_filter($steps, static fn (array $step): bool => $step['phase'] === 'current'));
        $staged = array_values(array_filter($steps, static fn (array $step): bool => $step['phase'] !== 'current'));

        return [
            'status' => self::statusNext170($sourceChanged, $releaseRequested, $releaseAllowed),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'current_schema_cookie' => $currentCookie,
            'next_schema_cookie' => $nextCookie,
            'reprepare_token' => $token,
            'expected_reprepare_token' => $expectedToken,
            'source_changed' => $sourceChanged,
            'reprepare_required' => $sourceChanged,
            'reprepare_token_matches' => $tokenMatches,
            'release_requested' => $releaseRequested,
            'release_allowed' => $releaseAllowed,
            'base' => $base,
            'barrier_steps' => $steps,
            'visible_barrier_steps' => $visible,
            'held_barrier_steps' => $held,
            'current_barrier_steps' => $current,
            'staged_barrier_steps' => $staged,
            'visible_keys' => array_column($visible, 'visibility_key'),
            'held_keys' => array_column($held, 'visibility_key'),
            'current_keys' => array_column($current, 'visibility_key'),
            'staged_keys' => array_column($staged, 'visibility_key'),
            'statement_rows' => count($visible),
            'attempted_statement_rows' => count($steps),
            'current_drained_before_next' => self::currentBeforeStagedNext170($steps),
            'returning_barrier' => [
                'current_source_visible' => count($current),
                'staged_source_attempted' => count($staged),
                'staged_source_visible' => count($visible) - count($current),
                'staged_source_held' => count($held),
                'reason' => $sourceChanged && !$releaseAllowed
                    ? 'next view or trigger source changed before matching reprepare token'
                    : 'current RETURNING stream drained before next source visibility',
            ],
            'yield_boundary' => $sourceChanged
                ? 'recursive-view-returning-current-source-reprepare-barrier-next170'
                : 'recursive-view-returning-current-source-drain-barrier-next170',
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-cursor-model',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next170',
                'sqlite-recursive-view-returning-current-source-drain-reprepare-barrier',
                'sqlite-returning-current-source-next-token-admission',
            ]))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $steps
     * @return list<array<string,mixed>>
     */
    private static function barrierStepsNext170(array $steps, bool $sourceChanged, bool $releaseAllowed, int $currentCookie, int $nextCookie, string $token, string $expectedToken): array
    {
        $out = [];
        foreach ($steps as $ordinal => $step) {
            if (!is_array($step) || !isset($step['phase'], $step['visibility_key'])) {
                throw new InvalidArgumentException('SQLite trigger recursive view next170 cursor step is malformed');
            }
            $phase = (string) $step['phase'];
            $baseVisible = (bool) ($step['visible'] ?? false);
            $current = $phase === 'current';
            $visible = $current || ($baseVisible && $releaseAllowed);
            $step['barrier_ordinal'] = $ordinal;
            $step['schema_cookie'] = $current ? $currentCookie : $nextCookie;
            $step['reprepare_token'] = $current ? null : $token;
            $step['expected_reprepare_token'] = $current ? null : $expectedToken;
            $step['source_changed'] = $sourceChanged;
            $step['visible_after_barrier'] = $visible;
            $step['held_by_reprepare_barrier'] = !$current && $baseVisible && !$releaseAllowed;
            $step['held_by_current_source'] = !$current && !$visible;
            $step['drained_current_before_step'] = !$current;
            $out[] = $step;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     */
    private static function sourceChangedNext170(array $currentView, array $nextView): bool
    {
        foreach (['source', 'trigger_source', 'trigger', 'name'] as $key) {
            if ((string) ($currentView[$key] ?? '') !== (string) ($nextView[$key] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private static function statusNext170(bool $sourceChanged, int $releaseRequested, bool $releaseAllowed): string
    {
        if ($sourceChanged && !$releaseAllowed) {
            return 'trigger-recursive-view-returning-current-source-reprepare-held-next170';
        }
        if ($releaseRequested > 0) {
            return 'trigger-recursive-view-returning-current-source-reprepared-next170';
        }

        return 'trigger-recursive-view-returning-current-source-drained-next170';
    }

    /**
     * @param list<array<string,mixed>> $steps
     */
    private static function currentBeforeStagedNext170(array $steps): bool
    {
        $seenStaged = false;
        foreach ($steps as $step) {
            if (($step['phase'] ?? null) !== 'current') {
                $seenStaged = true;
                continue;
            }
            if ($seenStaged) {
                return false;
            }
        }

        return true;
    }

    private static function nonNegativeIntNext170(mixed $value, string $label): int
    {
        $int = (int) $value;
        if ($int < 0) {
            throw new InvalidArgumentException("SQLite trigger recursive view next170 {$label} must be non-negative");
        }

        return $int;
    }

    private static function tokenNext170(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next170 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,acknowledged_current_pages?:int} $options
     * @return array<string,mixed>
     */
    public static function executeNext171(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $acknowledged = (int) ($options['acknowledged_current_pages'] ?? PHP_INT_MAX);
        if ($acknowledged < 0) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next171 acknowledged pages must be non-negative');
        }

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext167(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentPages = $base['current_returning_pages'];
        $currentPageCount = count($currentPages);
        $acknowledged = min($acknowledged, $currentPageCount);
        $currentAcknowledgedPages = array_slice($currentPages, 0, $acknowledged);
        $currentPendingPages = array_slice($currentPages, $acknowledged);
        $fullyAcknowledged = $acknowledged >= $currentPageCount;
        $nextAdmitted = (bool) $base['next_source_admitted'];
        $nextVisible = $nextAdmitted && $fullyAcknowledged;
        $attemptedNextPages = $base['attempted_next_returning_pages'];
        $blockedPages = $nextVisible ? [] : $attemptedNextPages;
        $visiblePages = $nextVisible
            ? array_merge($currentPages, $base['next_returning_pages'])
            : $currentAcknowledgedPages;

        return $base + [
            'status_next171' => match (true) {
                !$fullyAcknowledged => 'trigger-recursive-view-returning-current-source-cursor-open-next171',
                $nextVisible => 'trigger-recursive-view-returning-next-source-visible-after-cursor-close-next171',
                default => 'trigger-recursive-view-returning-current-source-cursor-closed-next171',
            },
            'acknowledged_current_pages' => $acknowledged,
            'pending_current_pages' => count($currentPendingPages),
            'current_returning_acknowledged_pages' => $currentAcknowledgedPages,
            'current_returning_pending_pages' => $currentPendingPages,
            'current_returning_cursor_complete' => $fullyAcknowledged,
            'next_source_fenced_by_open_returning_cursor' => !$fullyAcknowledged,
            'next_source_visible_after_cursor_close' => $nextVisible,
            'visible_returning_pages_next171' => $visiblePages,
            'blocked_next_source_pages_next171' => $blockedPages,
            'cursor_watermark_next171' => self::watermarkNext171($base['drain_cursor'], $acknowledged, $currentPageCount),
            'yield_boundary_next171' => match (true) {
                !$fullyAcknowledged => 'recursive-view-returning-next171-open-current-cursor-fences-next-source',
                $nextVisible => 'recursive-view-returning-next171-current-cursor-closed-next-source-visible',
                default => 'recursive-view-returning-next171-current-cursor-closed-next-source-still-pinned',
            },
            'dependencies_next171' => [
                'sqlite-trigger-recursive-view-returning-current-source-next171',
                'sqlite-returning-cursor-close-before-next-view-source',
                'sqlite-instead-of-view-trigger-recursive-returning-current-source',
            ],
        ];
    }

    private static function watermarkNext171(string $cursor, int $acknowledged, int $total): string
    {
        return $cursor . ':ack-' . $acknowledged . '-of-' . $total;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext172(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifierNext172((string) ($options['key'] ?? 'option_name'), 'key column');
        $savepoint = self::tokenNext172((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_next172'), 'savepoint');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 2);
        $childSuffix = self::tokenNext172((string) ($options['child_suffix'] ?? ':child'), 'child suffix');
        if ($maxDepth < 0 || $maxDepth > 32) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next172 max depth must be between 0 and 32');
        }
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next172 projection cannot be empty');
        }

        $baseRows = self::normalizeRowsNext172($baseRows, $key);
        $currentView = self::normalizeViewNext172($currentView, 'current view');
        $nextView = self::normalizeViewNext172($nextView, 'next view');

        $current = self::runSourceNext172($baseRows, $currentInput, $currentView, $returning, $key, $recursive, $maxDepth, $childSuffix, 'current');
        $nextBase = $admitNext ? $current['rows'] : $baseRows;
        $nextAttempt = self::runSourceNext172($nextBase, $nextInput, $nextView, $returning, $key, $recursive, $maxDepth, $childSuffix, 'next');

        $attemptedStream = array_merge($current['yield_stream'], $nextAttempt['yield_stream']);
        $visibleStream = $admitNext ? $attemptedStream : $current['yield_stream'];

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-returning-current-source-next172-next-admitted'
                : 'trigger-recursive-view-returning-current-source-next172-current-pinned',
            'savepoint' => $savepoint,
            'key' => $key,
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'child_suffix' => $childSuffix,
            'current_view' => self::viewSummaryNext172($currentView),
            'next_view' => self::viewSummaryNext172($nextView),
            'visible_view' => self::viewSummaryNext172($admitNext ? $nextView : $currentView),
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $admitNext ? $nextAttempt['rows'] : $baseRows,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $admitNext ? $nextAttempt['returning_rows'] : [],
            'attempted_next_returning_rows' => $nextAttempt['returning_rows'],
            'visible_returning_rows' => self::returningOnlyNext172($visibleStream),
            'suppressed_returning_rows' => $admitNext ? [] : self::returningOnlyNext172($nextAttempt['yield_stream']),
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $admitNext ? $nextAttempt['yield_stream'] : [],
            'attempted_next_yield_stream' => $nextAttempt['yield_stream'],
            'attempted_yield_stream' => $attemptedStream,
            'visible_yield_stream' => $visibleStream,
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $admitNext ? $nextAttempt['trigger_effects'] : [],
            'attempted_next_trigger_effects' => $nextAttempt['trigger_effects'],
            'current_changes' => $current['changes'],
            'next_changes' => $admitNext ? $nextAttempt['changes'] : 0,
            'attempted_next_changes' => $nextAttempt['changes'],
            'changes' => $admitNext ? $current['changes'] + $nextAttempt['changes'] : 0,
            'statement_rows' => $current['statement_rows'] + ($admitNext ? $nextAttempt['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + $nextAttempt['statement_rows'],
            'recursive_rows' => $current['recursive_rows'] + ($admitNext ? $nextAttempt['recursive_rows'] : 0),
            'attempted_recursive_rows' => $current['recursive_rows'] + $nextAttempt['recursive_rows'],
            'source_transition' => [
                'current_source' => $currentView['source'],
                'current_trigger_source' => $currentView['trigger_source'],
                'next_source' => $nextView['source'],
                'next_trigger_source' => $nextView['trigger_source'],
                'next_started_from' => $admitNext ? 'current-trigger-output' : 'savepoint-current-source',
                'current_returning_visibility' => 'drained-before-next-source',
                'next_returning_visibility' => $admitNext ? 'admitted-after-current-drain' : 'attempted-only-current-source-pinned',
                'visible_source' => $admitNext ? $nextView['source'] : $currentView['source'],
            ],
            'dependency_closure' => 'no-new-support-component-reuses-native-view-trigger-recursion-returning-current-source',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next172',
                'sqlite-instead-of-view-trigger-recursive-current-source-drain',
                'sqlite-returning-yield-before-next-view-source-admission',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $input
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,statement_rows:int,recursive_rows:int}
     */
    private static function runSourceNext172(array $rows, array $input, array $view, array $returning, string $key, bool $recursive, int $maxDepth, string $childSuffix, string $phase): array
    {
        $yield = [];
        $returningRows = [];
        $effects = [];
        $changes = 0;
        $recursiveRows = 0;
        $queue = [];

        foreach (array_values($input) as $ordinal => $viewRow) {
            if (!is_array($viewRow)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next172 input row must be an array');
            }
            $queue[] = [self::projectViewRowNext172($viewRow, $view), (int) $ordinal, 0, 'statement'];
        }

        while ($queue !== []) {
            [$incoming, $ordinal, $depth, $origin] = array_shift($queue);
            $index = self::rowIndexNext172($rows, $incoming, $key);
            $old = $index === null ? null : $rows[$index];
            $event = $old === null ? 'insert' : 'update';
            $new = $old === null ? $incoming : array_replace($old, $incoming);
            $new['generation'] = $depth;
            $new['trigger_source'] = $view['trigger_source'];
            $new['source_phase'] = $phase;
            $new['origin'] = $origin;
            if ($index === null) {
                $rows[] = $new;
            } else {
                $rows[$index] = $new;
            }

            $returningRow = self::returningRowNext172($returning, $new, $old, $incoming, $event, $ordinal, $depth, $view['trigger_source']);
            $envelope = [
                'phase' => $phase,
                'source' => $view['source'],
                'trigger_source' => $view['trigger_source'],
                'view' => $view['name'],
                'trigger' => $view['trigger'],
                'audit_label' => $view['audit_label'],
                'ordinal' => $ordinal,
                'depth' => $depth,
                'origin' => $origin,
                'event' => $event,
                'returning' => $returningRow,
            ];
            $yield[] = $envelope;
            $returningRows[] = $envelope;
            $effects[] = $envelope + [
                'option_name' => $new[$key],
                'old_value' => $old['option_value'] ?? null,
                'new_value' => $new['option_value'] ?? null,
            ];
            ++$changes;
            if ($origin === 'recursive') {
                ++$recursiveRows;
            }

            if ($recursive && $depth < $maxDepth && (($incoming['spawn_child'] ?? true) !== false)) {
                $child = $new;
                $child[$key] = (string) $new[$key] . $childSuffix;
                $child['option_value'] = (string) ($new['option_value'] ?? '') . $childSuffix;
                $child['parent_option'] = $new[$key];
                $queue[] = [$child, $ordinal, $depth + 1, 'recursive'];
            }
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'changes' => $changes,
            'statement_rows' => count($input),
            'recursive_rows' => $recursiveRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowIndexNext172(array $rows, array $incoming, string $key): ?int
    {
        if (!array_key_exists($key, $incoming)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 incoming row key {$key} is missing");
        }
        foreach ($rows as $index => $row) {
            if (($row[$key] ?? null) == $incoming[$key]) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRowsNext172(array $rows, string $key): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next172 rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string}
     */
    private static function normalizeViewNext172(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        $mapping = $view['mapping'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 {$label} columns must be a non-empty list");
        }
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 {$label} mapping must not be empty");
        }
        $normalized = [
            'name' => self::identifierNext172((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::tokenNext172((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifierNext172((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::tokenNext172((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'columns' => array_map(static fn (mixed $column): string => self::identifierNext172((string) $column, $label . ' column'), $columns),
            'mapping' => [],
            'audit_label' => self::tokenNext172((string) ($view['audit_label'] ?? $label), $label . ' audit label'),
        ];
        foreach ($mapping as $viewColumn => $tableColumn) {
            $viewColumn = self::identifierNext172((string) $viewColumn, $label . ' mapping view column');
            if (!in_array($viewColumn, $normalized['columns'], true)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 mapping column {$viewColumn} is not in the view");
            }
            $normalized['mapping'][$viewColumn] = self::identifierNext172((string) $tableColumn, $label . ' mapping table column');
        }

        return $normalized;
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function projectViewRowNext172(array $viewRow, array $view): array
    {
        $incoming = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 row is missing view column {$viewColumn}");
            }
            $incoming[$tableColumn] = $viewRow[$viewColumn];
        }
        if (array_key_exists('spawn_child', $viewRow)) {
            $incoming['spawn_child'] = (bool) $viewRow['spawn_child'];
        }

        return $incoming;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRowNext172(array $returning, array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $triggerSource): array
    {
        $row = [];
        foreach ($returning as $index => $expr) {
            if (is_callable($expr)) {
                $row['expr' . $index] = $expr($new, $old, $incoming, $event, $ordinal, $depth, $triggerSource);
                continue;
            }
            $alias = null;
            if (is_array($expr)) {
                $alias = isset($expr['as']) ? self::identifierNext172((string) $expr['as'], 'RETURNING alias') : null;
                $expr = (string) ($expr['expr'] ?? '');
            }
            $expr = trim((string) $expr);
            $column = $alias ?? self::identifierNext172(str_replace('.', '_', $expr), 'RETURNING expression');
            $row[$column] = match (true) {
                str_starts_with($expr, 'new.') => $new[substr($expr, 4)] ?? null,
                str_starts_with($expr, 'old.') => $old === null ? null : ($old[substr($expr, 4)] ?? null),
                str_starts_with($expr, 'incoming.') => $incoming[substr($expr, 9)] ?? null,
                $expr === 'event' => $event,
                $expr === 'ordinal' => $ordinal,
                $expr === 'depth' => $depth,
                $expr === 'trigger_source' => $triggerSource,
                default => $new[$expr] ?? $incoming[$expr] ?? null,
            };
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $stream
     * @return list<array<string,mixed>>
     */
    private static function returningOnlyNext172(array $stream): array
    {
        return array_values(array_map(static fn (array $row): array => (array) $row['returning'], $stream));
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function viewSummaryNext172(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'trigger' => $view['trigger'],
            'trigger_source' => $view['trigger_source'],
            'columns' => $view['columns'],
            'mapping' => $view['mapping'],
            'audit_label' => $view['audit_label'],
        ];
    }

    private static function identifierNext172(string $value, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 invalid {$label}: {$value}");
        }

        return $value;
    }

    private static function tokenNext172(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 invalid {$label}: {$value}");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext173(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $prepared = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext167(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['admit_next_source' => true],
        );

        $currentPages = self::pagesNext173($prepared['current_returning_pages'], 'current returning pages');
        $nextPages = self::pagesNext173($prepared['next_returning_pages'], 'next returning pages');
        $currentSignature = (string) ($prepared['source_signatures']['current'] ?? '');
        $resumeSignature = (string) ($options['resume_source_signature'] ?? $currentSignature);
        if ($resumeSignature === '') {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next173 resume source signature is empty');
        }

        $drainedCount = self::drainedCountNext173($options['drained_current_pages'] ?? count($currentPages), count($currentPages));
        $drainedCurrentPages = array_slice($currentPages, 0, $drainedCount);
        $pendingCurrentPages = array_slice($currentPages, $drainedCount);
        $currentExhausted = count($pendingCurrentPages) === 0;
        $resumeMatches = hash_equals($currentSignature, $resumeSignature);
        $requestedAdmission = (bool) ($options['admit_next_source'] ?? false);
        $admitNext = $requestedAdmission && $currentExhausted && $resumeMatches;

        $blockedReasons = [];
        if (!$requestedAdmission) {
            $blockedReasons[] = 'next-source-not-requested';
        }
        if (!$currentExhausted) {
            $blockedReasons[] = 'current-returning-cursor-not-exhausted';
        }
        if (!$resumeMatches) {
            $blockedReasons[] = 'current-source-resume-signature-mismatch';
        }

        $visiblePages = $admitNext ? array_merge($drainedCurrentPages, $nextPages) : $drainedCurrentPages;
        $blockedNextPages = $admitNext ? [] : $nextPages;

        return $prepared + [
            'status_next173' => $admitNext
                ? 'trigger-recursive-view-returning-next-source-admitted-after-exhausted-current-cursor-next173'
                : 'trigger-recursive-view-returning-current-source-cursor-fences-next-source-next173',
            'requested_next_source_admission' => $requestedAdmission,
            'resume_source_signature' => $resumeSignature,
            'resume_source_matches_current' => $resumeMatches,
            'current_source_signature_next173' => $currentSignature,
            'current_pages_drained_count' => $drainedCount,
            'current_pages_total_count' => count($currentPages),
            'current_cursor_exhausted' => $currentExhausted,
            'drained_current_pages' => $drainedCurrentPages,
            'pending_current_pages' => $pendingCurrentPages,
            'next_source_admitted_next173' => $admitNext,
            'visible_returning_pages_next173' => $visiblePages,
            'blocked_next_source_pages_next173' => $blockedNextPages,
            'next_source_block_reasons_next173' => $blockedReasons,
            'returning_cursor_state_next173' => [
                'cursor' => (string) ($prepared['drain_cursor'] ?? ''),
                'drained_current_pages' => $drainedCount,
                'pending_current_pages' => count($pendingCurrentPages),
                'visible_pages' => count($visiblePages),
                'blocked_next_pages' => count($blockedNextPages),
                'resume_source_matches_current' => $resumeMatches,
            ],
            'yield_boundary_next173' => $admitNext
                ? 'recursive-view-returning-next173-current-cursor-exhausted-source-token-matched'
                : 'recursive-view-returning-next173-next-source-held-by-current-cursor-or-token',
            'dependencies_next173' => [
                'sqlite-trigger-recursive-view-returning-current-source-next173',
                'sqlite-returning-current-cursor-exhaustion-before-next-source',
                'sqlite-returning-current-source-resume-token',
            ],
        ];
    }

    /**
     * @param mixed $pages
     * @return list<array<string,mixed>>
     */
    private static function pagesNext173(mixed $pages, string $label): array
    {
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next173 {$label} are malformed");
        }

        return $pages;
    }

    private static function drainedCountNext173(mixed $value, int $total): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > $total) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next173 drained current pages is out of range');
        }

        return $count;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string,current_schema_cookie?:int,next_schema_cookie?:int,reprepare_token?:string,expected_reprepare_token?:string,conflict_key_separator?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext174(
        array $rows,
        array $currentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $separator = self::separatorNext174((string) ($options['conflict_key_separator'] ?? ':'));
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext170(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => $options['release_staged_sources'] ?? 0,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_next174',
                'current_generation' => $options['current_generation'] ?? 'wp-import-current-174',
                'first_next_generation' => $options['first_next_generation'] ?? 'wp-import-next-174-a',
                'second_next_generation' => $options['second_next_generation'] ?? 'wp-import-next-174-b',
                'cursor_name' => $options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_174',
                'current_schema_cookie' => $options['current_schema_cookie'] ?? 174,
                'next_schema_cookie' => $options['next_schema_cookie'] ?? 175,
                'reprepare_token' => $options['reprepare_token'] ?? 'wp.reprepare.174',
                'expected_reprepare_token' => $options['expected_reprepare_token'] ?? 'wp.reprepare.174.expected',
            ],
        );

        $steps = self::watermarkedStepsNext174($base['barrier_steps'], $separator);
        $visible = array_values(array_filter($steps, static fn (array $step): bool => $step['visible_after_watermark']));
        $held = array_values(array_filter($steps, static fn (array $step): bool => !$step['visible_after_watermark']));
        $conflicts = array_values(array_filter($steps, static fn (array $step): bool => $step['conflicts_with_current_key']));
        $stagedConflicts = array_values(array_filter($conflicts, static fn (array $step): bool => $step['phase'] !== 'current'));

        return [
            'status' => self::statusNext174($base, $stagedConflicts),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'base' => $base,
            'conflict_key_separator' => $separator,
            'watermark_steps' => $steps,
            'visible_watermark_steps' => $visible,
            'held_watermark_steps' => $held,
            'conflicting_staged_steps' => $stagedConflicts,
            'statement_rows' => count($visible),
            'attempted_statement_rows' => count($steps),
            'current_statement_rows' => count(array_filter($steps, static fn (array $step): bool => $step['phase'] === 'current')),
            'staged_statement_rows' => count(array_filter($steps, static fn (array $step): bool => $step['phase'] !== 'current')),
            'visible_keys' => array_column($visible, 'visibility_key'),
            'held_keys' => array_column($held, 'visibility_key'),
            'conflict_keys' => array_values(array_unique(array_column($stagedConflicts, 'logical_key'))),
            'current_source_watermark' => [
                'current_keys' => array_values(array_unique(array_column(
                    array_filter($steps, static fn (array $step): bool => $step['phase'] === 'current'),
                    'logical_key',
                ))),
                'staged_conflict_keys' => array_values(array_unique(array_column($stagedConflicts, 'logical_key'))),
                'current_drained_before_next' => $base['current_drained_before_next'],
                'reprepare_token_matches' => $base['reprepare_token_matches'],
                'source_changed' => $base['source_changed'],
                'reason' => $stagedConflicts === []
                    ? 'no staged RETURNING row reused a current-source key'
                    : 'staged RETURNING rows reuse current-source keys and stay behind the current-source watermark',
            ],
            'yield_boundary' => $stagedConflicts === []
                ? 'recursive-view-returning-current-source-watermark-clear-next174'
                : 'recursive-view-returning-current-source-watermark-conflict-held-next174',
            'dependency_closure' => 'no new support component needed; reuses native recursive view RETURNING current-source cursor and reprepare barriers',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next174',
                'sqlite-returning-current-source-duplicate-key-watermark',
                'wordpress-recursive-view-returning-current-source-next174',
            ]))),
            'non_overlap' => 'extends accepted next170 source-drain/reprepare barrier with duplicate-key watermarking for staged next-source rows; does not repeat savepoint rollback, deferred FK, UPSERT, DELETE, or schema reparse trigger slices',
        ];
    }

    /**
     * @param mixed $steps
     * @return list<array<string,mixed>>
     */
    private static function watermarkedStepsNext174(mixed $steps, string $separator): array
    {
        if (!is_array($steps) || !array_is_list($steps)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next174 barrier steps are malformed');
        }

        $currentKeys = [];
        $out = [];
        foreach ($steps as $ordinal => $step) {
            if (!is_array($step) || !isset($step['visibility_key'], $step['phase'])) {
                throw new InvalidArgumentException('SQLite trigger recursive view next174 barrier step is malformed');
            }
            $logicalKey = self::logicalKeyNext174((string) $step['visibility_key'], $separator);
            $isCurrent = $step['phase'] === 'current';
            if ($isCurrent) {
                $currentKeys[$logicalKey] = true;
            }

            $conflicts = !$isCurrent && isset($currentKeys[$logicalKey]);
            $step['watermark_ordinal'] = $ordinal;
            $step['logical_key'] = $logicalKey;
            $step['conflicts_with_current_key'] = $conflicts;
            $step['visible_after_watermark'] = (bool) ($step['visible_after_barrier'] ?? false) && !$conflicts;
            $step['held_by_current_source_watermark'] = $conflicts;
            $out[] = $step;
        }

        return $out;
    }

    private static function logicalKeyNext174(string $visibilityKey, string $separator): string
    {
        $offset = strrpos($visibilityKey, $separator);
        if ($offset === false || $offset === strlen($visibilityKey) - strlen($separator)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next174 visibility key is malformed');
        }

        return substr($visibilityKey, $offset + strlen($separator));
    }

    private static function statusNext174(array $base, array $stagedConflicts): string
    {
        if ($stagedConflicts !== []) {
            return 'trigger-recursive-view-returning-current-source-watermark-held-next174';
        }
        if (($base['release_allowed'] ?? false) === true && (int) ($base['release_requested'] ?? 0) > 0) {
            return 'trigger-recursive-view-returning-current-source-watermark-released-next174';
        }

        return 'trigger-recursive-view-returning-current-source-watermark-drained-next174';
    }

    private static function separatorNext174(string $value): string
    {
        if ($value === '') {
            throw new InvalidArgumentException('SQLite trigger recursive view next174 conflict key separator must not be empty');
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int} $options
     * @return array<string,mixed>
     */
    public static function executeNext175(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $action = self::actionNext175((string) ($options['savepoint_action'] ?? 'hold'));
        $restartCursor = self::tokenNext175((string) ($options['restart_cursor'] ?? 'wp-recursive-view-returning-restart-175'), 'restart cursor');
        $epoch = self::epochNext175($options['current_source_epoch'] ?? 0);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext173(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['admit_next_source' => true],
        );

        $currentExhausted = (bool) ($base['current_cursor_exhausted'] ?? false);
        $sourceMatches = (bool) ($base['resume_source_matches_current'] ?? false);
        $nextPrepared = self::pagesNext175($base['next_returning_pages'] ?? [], 'next returning pages');
        $nextAdmittedByCursor = (bool) ($base['next_source_admitted_next173'] ?? false);
        $canRelease = $action === 'release' && $currentExhausted && $sourceMatches && $nextAdmittedByCursor;
        $rolledBack = $action === 'rollback';
        $held = !$canRelease;

        $blockedReasons = self::stringsNext175($base['next_source_block_reasons_next173'] ?? [], 'block reasons');
        if ($action === 'hold') {
            $blockedReasons[] = 'savepoint-release-not-requested';
        }
        if ($rolledBack) {
            $blockedReasons[] = 'savepoint-rolled-back-before-next-source-yield';
        }
        if ($action === 'release' && !$canRelease && $blockedReasons === []) {
            $blockedReasons[] = 'next-source-release-deferred';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $drainedCurrent = self::pagesNext175($base['drained_current_pages'] ?? [], 'drained current pages');
        $pendingCurrent = self::pagesNext175($base['pending_current_pages'] ?? [], 'pending current pages');
        $visiblePages = $canRelease
            ? self::pagesNext175($base['visible_returning_pages_next173'] ?? [], 'visible returning pages')
            : $drainedCurrent;
        $queuedNext = $canRelease ? [] : $nextPrepared;

        $restartPlan = [
            'cursor' => $restartCursor,
            'action' => $action,
            'epoch' => $epoch,
            'current_source_signature' => (string) ($base['current_source_signature_next173'] ?? ''),
            'resume_source_signature' => (string) ($base['resume_source_signature'] ?? ''),
            'current_exhausted' => $currentExhausted,
            'source_signature_matched' => $sourceMatches,
            'queued_next_pages' => count($queuedNext),
            'pending_current_pages' => count($pendingCurrent),
            'restart_required' => $rolledBack || !$sourceMatches,
            'restart_from' => $rolledBack ? 'current-source-savepoint-image' : ($sourceMatches ? 'next-source-queue' : 'current-source-resume-token'),
        ];

        return $base + [
            'status_next175' => match (true) {
                $canRelease => 'trigger-recursive-view-returning-savepoint-released-next-source-next175',
                $rolledBack => 'trigger-recursive-view-returning-savepoint-rollback-retains-current-source-next175',
                default => 'trigger-recursive-view-returning-savepoint-holds-next-source-next175',
            },
            'savepoint_action_next175' => $action,
            'current_source_epoch_next175' => $epoch,
            'restart_cursor_next175' => $restartCursor,
            'savepoint_release_allowed_next175' => $canRelease,
            'savepoint_rolled_back_next175' => $rolledBack,
            'next_source_held_by_savepoint_next175' => $held,
            'visible_returning_pages_next175' => $visiblePages,
            'queued_next_source_pages_next175' => $queuedNext,
            'pending_current_pages_next175' => $pendingCurrent,
            'blocked_reasons_next175' => $blockedReasons,
            'release_plan_next175' => [
                'requested_action' => $action,
                'current_cursor_exhausted' => $currentExhausted,
                'resume_source_matches_current' => $sourceMatches,
                'next_source_prepared_pages' => count($nextPrepared),
                'visible_pages' => count($visiblePages),
                'queued_pages' => count($queuedNext),
                'decision' => $canRelease ? 'release-next-source' : ($rolledBack ? 'rollback-next-source' : 'hold-next-source'),
            ],
            'restart_plan_next175' => $restartPlan,
            'yield_boundary_next175' => $canRelease
                ? 'recursive-view-returning-next175-savepoint-release-after-current-source-drain'
                : 'recursive-view-returning-next175-current-source-savepoint-fences-next-source',
            'dependencies_next175' => [
                'sqlite-trigger-recursive-view-returning-current-source-next175',
                'sqlite-returning-savepoint-release-after-current-source-drain',
                'sqlite-returning-savepoint-rollback-restarts-current-source-cursor',
            ],
            'dependency_closure_next175' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-savepoint-model',
        ];
    }

    private static function actionNext175(string $action): string
    {
        if (!in_array($action, ['hold', 'release', 'rollback'], true)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next175 savepoint action is unsupported');
        }

        return $action;
    }

    private static function tokenNext175(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next175 {$label} is malformed");
        }

        return $value;
    }

    private static function epochNext175(mixed $value): int
    {
        $epoch = (int) $value;
        if ($epoch < 0) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next175 current source epoch must be non-negative');
        }

        return $epoch;
    }

    /**
     * @param mixed $pages
     * @return list<array<string,mixed>>
     */
    private static function pagesNext175(mixed $pages, string $label): array
    {
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next175 {$label} are malformed");
        }

        return $pages;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringsNext175(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next175 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,acknowledged_current_page_indexes?:list<int>,resume_source_signature?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext176(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $prepared = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext173(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['admit_next_source' => true],
        );

        $currentPages = self::pagesNext176($prepared['current_returning_pages'] ?? null);
        $acknowledged = self::acknowledgedIndexesNext176($options['acknowledged_current_page_indexes'] ?? array_keys($currentPages), count($currentPages));
        $contiguous = self::isContiguousPrefixNext176($acknowledged);
        $drainedCount = $contiguous ? count($acknowledged) : 0;

        $drainOptions = $options;
        $drainOptions['drained_current_pages'] = $drainedCount;
        $drainOptions['admit_next_source'] = (bool) ($options['admit_next_source'] ?? false);
        unset($drainOptions['acknowledged_current_page_indexes']);

        $gated = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext173(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $drainOptions,
        );

        $total = count($currentPages);
        $missing = array_values(array_diff(range(0, max(0, $total - 1)), $acknowledged));
        $duplicateFree = count($acknowledged) === count(array_unique($acknowledged));
        $valid = $duplicateFree && $contiguous;
        $admit = $valid && (bool) $gated['next_source_admitted_next173'];

        $blocked = $gated['next_source_block_reasons_next173'];
        if (!$duplicateFree) {
            $blocked[] = 'current-returning-page-acknowledgement-duplicate';
        }
        if (!$contiguous) {
            $blocked[] = 'current-returning-page-acknowledgement-gap';
        }

        return $gated + [
            'status_next176' => $admit
                ? 'trigger-recursive-view-returning-current-pages-contiguous-next-source-admitted-next176'
                : 'trigger-recursive-view-returning-current-page-acknowledgement-fences-next-source-next176',
            'acknowledged_current_page_indexes_next176' => $acknowledged,
            'missing_current_page_indexes_next176' => $missing,
            'current_page_acknowledgements_contiguous_next176' => $contiguous,
            'current_page_acknowledgements_duplicate_free_next176' => $duplicateFree,
            'current_page_acknowledgements_valid_next176' => $valid,
            'next_source_admitted_next176' => $admit,
            'next_source_block_reasons_next176' => array_values(array_unique($blocked)),
            'returning_cursor_state_next176' => [
                'total_current_pages' => $total,
                'acknowledged_indexes' => $acknowledged,
                'missing_indexes' => $missing,
                'contiguous_prefix' => $contiguous,
                'duplicate_free' => $duplicateFree,
                'drained_current_pages' => $drainedCount,
                'next_source_admitted' => $admit,
            ],
            'yield_boundary_next176' => $admit
                ? 'recursive-view-returning-next176-contiguous-current-page-acks-release-next-source'
                : 'recursive-view-returning-next176-gap-or-duplicate-current-page-acks-hold-next-source',
            'dependencies_next176' => [
                'sqlite-trigger-recursive-view-returning-current-source-next176',
                'sqlite-returning-page-acknowledgement-contiguous-prefix',
                'sqlite-instead-of-view-trigger-recursive-returning-current-page-drain',
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function pagesNext176(mixed $pages): array
    {
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next176 current pages are malformed');
        }

        return $pages;
    }

    /**
     * @param mixed $indexes
     * @return list<int>
     */
    private static function acknowledgedIndexesNext176(mixed $indexes, int $total): array
    {
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next176 acknowledged page indexes must be a list');
        }

        $out = [];
        foreach ($indexes as $index) {
            $int = (int) $index;
            if ($int < 0 || $int >= $total) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next176 acknowledged page index is out of range');
            }
            $out[] = $int;
        }

        return $out;
    }

    /**
     * @param list<int> $indexes
     */
    private static function isContiguousPrefixNext176(array $indexes): bool
    {
        foreach ($indexes as $offset => $index) {
            if ($index !== $offset) {
                return false;
            }
        }

        return true;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int} $options
     * @return array<string,mixed>
     */
    public static function executeNext177(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $cursor = self::tokenNext177((string) ($options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_177'), 'cursor name');
        $currentGeneration = self::tokenNext177((string) ($options['current_generation'] ?? 'wp-current-returning-177'), 'current generation');
        $nextGeneration = self::tokenNext177((string) ($options['next_generation'] ?? 'wp-next-returning-177'), 'next generation');
        $token = self::tokenNext177((string) ($options['reprepare_token'] ?? 'wp.reprepare.177'), 'reprepare token');
        $expectedToken = self::tokenNext177((string) ($options['expected_reprepare_token'] ?? $token), 'expected reprepare token');
        $pageSize = self::positiveIntNext177($options['page_size'] ?? 3, 'page size');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext172(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            [
                'key' => $options['key'] ?? 'option_name',
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_returning_next177',
                'admit_next_source' => $options['admit_next_source'] ?? false,
                'recursive_triggers' => $options['recursive_triggers'] ?? true,
                'max_depth' => $options['max_depth'] ?? 2,
                'child_suffix' => $options['child_suffix'] ?? ':child',
            ],
        );

        $tokenMatches = $token === $expectedToken;
        $currentRows = self::annotateRowsNext177($base['current_yield_stream'], $cursor, $currentGeneration, 'current', true, 0, $pageSize);
        $attemptedNextRows = self::annotateRowsNext177(
            $base['attempted_next_yield_stream'],
            $cursor,
            $nextGeneration,
            'next',
            (bool) ($base['next_yield_stream'] !== []) && $tokenMatches,
            count($currentRows),
            $pageSize,
        );
        $attemptedRows = array_merge($currentRows, $attemptedNextRows);
        $visibleRows = array_values(array_filter($attemptedRows, static fn (array $row): bool => $row['visible_after_current_source']));
        $heldRows = array_values(array_filter($attemptedRows, static fn (array $row): bool => !$row['visible_after_current_source']));

        $currentLastToken = $currentRows === [] ? null : $currentRows[array_key_last($currentRows)]['resume_token'];
        $nextFirstToken = $attemptedNextRows === [] ? null : $attemptedNextRows[0]['resume_token'];
        $admittedNextRows = array_values(array_filter($attemptedNextRows, static fn (array $row): bool => $row['visible_after_current_source']));
        $heldNextRows = array_values(array_filter($attemptedNextRows, static fn (array $row): bool => !$row['visible_after_current_source']));

        return [
            'status' => self::statusNext177($base, $tokenMatches, $admittedNextRows, $heldNextRows),
            'savepoint' => $base['savepoint'],
            'cursor' => $cursor,
            'current_generation' => $currentGeneration,
            'next_generation' => $nextGeneration,
            'reprepare_token' => $token,
            'expected_reprepare_token' => $expectedToken,
            'reprepare_token_matches' => $tokenMatches,
            'base' => $base,
            'page_size' => $pageSize,
            'current_source_rows' => $currentRows,
            'attempted_next_source_rows' => $attemptedNextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'current_resume_tokens' => array_column($currentRows, 'resume_token'),
            'attempted_next_resume_tokens' => array_column($attemptedNextRows, 'resume_token'),
            'visible_resume_tokens' => array_column($visibleRows, 'resume_token'),
            'held_resume_tokens' => array_column($heldRows, 'resume_token'),
            'current_last_resume_token' => $currentLastToken,
            'next_first_resume_token' => $nextFirstToken,
            'resume_boundary' => [
                'current_drained_before_next' => self::currentDrainedBeforeNextNext177($attemptedRows),
                'current_last_resume_token' => $currentLastToken,
                'next_first_resume_token' => $nextFirstToken,
                'next_admitted' => $admittedNextRows !== [],
                'next_held' => $heldNextRows !== [],
                'held_reason' => $heldNextRows === []
                    ? null
                    : ($tokenMatches ? 'next source waits for current RETURNING cursor drain' : 'next source waits for matching reprepare token'),
            ],
            'counts' => [
                'current' => count($currentRows),
                'attempted_next' => count($attemptedNextRows),
                'visible' => count($visibleRows),
                'held' => count($heldRows),
                'pages' => self::pageCountNext177(count($attemptedRows), $pageSize),
            ],
            'yield_boundary' => $heldNextRows === []
                ? 'recursive-view-returning-current-source-resume-next177-next-visible'
                : 'recursive-view-returning-current-source-resume-next177-next-held',
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING current-source cursor modeling',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next177',
                'sqlite-returning-current-source-resume-token-boundary',
                'wordpress-recursive-view-returning-current-source-next177',
            ]))),
            'non_overlap' => 'adds resume-token current-source RETURNING admission over accepted recursive view trigger rows; avoids accepted next172 source pinning and next174 duplicate-key watermark behavior',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function annotateRowsNext177(mixed $rows, string $cursor, string $generation, string $phase, bool $visible, int $offset, int $pageSize): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next177 rows are malformed');
        }

        $out = [];
        foreach ($rows as $ordinal => $row) {
            if (!is_array($row) || !isset($row['returning'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next177 row is malformed');
            }
            $absolute = $offset + (int) $ordinal;
            $returning = $row['returning'];
            if (!is_array($returning)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next177 returning row is malformed');
            }
            $out[] = [
                'cursor' => $cursor,
                'phase' => $phase,
                'source' => $row['source'] ?? null,
                'trigger_source' => $row['trigger_source'] ?? null,
                'trigger' => $row['trigger'] ?? null,
                'ordinal' => (int) ($row['ordinal'] ?? $ordinal),
                'depth' => (int) ($row['depth'] ?? 0),
                'event' => $row['event'] ?? null,
                'generation' => $generation,
                'resume_ordinal' => $absolute,
                'resume_page' => intdiv($absolute, $pageSize),
                'resume_token' => $cursor . ':' . $generation . ':' . $absolute,
                'visible_after_current_source' => $visible,
                'returning' => $returning,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function currentDrainedBeforeNextNext177(array $rows): bool
    {
        $seenNext = false;
        foreach ($rows as $row) {
            if (($row['phase'] ?? null) === 'next') {
                $seenNext = true;
                continue;
            }
            if ($seenNext && ($row['phase'] ?? null) === 'current') {
                return false;
            }
        }

        return true;
    }

    private static function pageCountNext177(int $rows, int $pageSize): int
    {
        if ($rows === 0) {
            return 0;
        }

        return (int) ceil($rows / $pageSize);
    }

    private static function statusNext177(array $base, bool $tokenMatches, array $admittedNextRows, array $heldNextRows): string
    {
        if (!$tokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next177-reprepare-held';
        }
        if ($admittedNextRows !== []) {
            return 'trigger-recursive-view-returning-current-source-next177-next-admitted';
        }
        if ($heldNextRows !== [] || ($base['attempted_next_yield_stream'] ?? []) !== []) {
            return 'trigger-recursive-view-returning-current-source-next177-current-drained-next-held';
        }

        return 'trigger-recursive-view-returning-current-source-next177-current-only';
    }

    private static function tokenNext177(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next177 {$label} is malformed");
        }

        return $value;
    }

    private static function positiveIntNext177(mixed $value, string $label): int
    {
        $int = (int) $value;
        if ($int < 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next177 {$label} must be positive");
        }

        return $int;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int} $options
     * @return array<string,mixed>
     */
    public static function executeNext178(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $snapshotToken = self::tokenNext178((string) ($options['snapshot_token'] ?? 'wp.recursive.view.returning.snapshot.178'), 'snapshot token');
        $expectedSnapshotToken = self::tokenNext178((string) ($options['expected_snapshot_token'] ?? $snapshotToken), 'expected snapshot token');
        $schemaCookie = self::cookieNext178($options['current_schema_cookie'] ?? 178, 'current schema cookie');
        $expectedSchemaCookie = self::cookieNext178($options['expected_current_schema_cookie'] ?? $schemaCookie, 'expected current schema cookie');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext175(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $snapshotMatches = hash_equals($snapshotToken, $expectedSnapshotToken);
        $schemaMatches = $schemaCookie === $expectedSchemaCookie;
        $releaseAllowed = (bool) ($base['savepoint_release_allowed_next175'] ?? false);
        $currentPages = self::pagesNext178($base['drained_current_pages'] ?? [], 'drained current pages');
        $nextPages = self::pagesNext178($base['next_returning_pages'] ?? [], 'next returning pages');
        $visiblePages = self::pagesNext178($base['visible_returning_pages_next175'] ?? [], 'visible returning pages');
        $snapshotStable = $snapshotMatches && $schemaMatches;

        $visibleRows = $snapshotStable ? self::flattenRowsNext178($visiblePages, $snapshotToken, $schemaCookie) : self::flattenRowsNext178($currentPages, $snapshotToken, $schemaCookie);
        $queuedNextRows = $releaseAllowed && $snapshotStable ? [] : self::flattenRowsNext178($nextPages, $snapshotToken, $schemaCookie);
        $currentRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'current'));
        $nextRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'next'));
        $blockedReasons = self::stringsNext178($base['blocked_reasons_next175'] ?? [], 'blocked reasons');
        if (!$snapshotMatches) {
            $blockedReasons[] = 'current-source-returning-snapshot-token-mismatch';
        }
        if (!$schemaMatches) {
            $blockedReasons[] = 'current-source-view-schema-cookie-mismatch';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        return $base + [
            'status_next178' => match (true) {
                !$snapshotStable => 'trigger-recursive-view-returning-current-source-snapshot-restart-next178',
                $releaseAllowed => 'trigger-recursive-view-returning-current-source-snapshot-released-next178',
                default => 'trigger-recursive-view-returning-current-source-snapshot-held-next178',
            },
            'snapshot_token_next178' => $snapshotToken,
            'expected_snapshot_token_next178' => $expectedSnapshotToken,
            'current_schema_cookie_next178' => $schemaCookie,
            'expected_current_schema_cookie_next178' => $expectedSchemaCookie,
            'snapshot_token_matches_next178' => $snapshotMatches,
            'schema_cookie_matches_next178' => $schemaMatches,
            'current_source_snapshot_stable_next178' => $snapshotStable,
            'visible_returning_rows_next178' => $visibleRows,
            'current_source_returning_rows_next178' => $currentRows,
            'next_source_returning_rows_next178' => $nextRows,
            'queued_next_source_rows_next178' => $queuedNextRows,
            'statement_returning_row_count_next178' => count($visibleRows),
            'current_returning_row_count_next178' => count($currentRows),
            'next_returning_row_count_next178' => count($nextRows),
            'queued_next_row_count_next178' => count($queuedNextRows),
            'returning_source_order_next178' => array_values(array_unique(array_column($visibleRows, 'statement_source'))),
            'returning_snapshot_plan_next178' => [
                'snapshot_token_matches' => $snapshotMatches,
                'schema_cookie_matches' => $schemaMatches,
                'savepoint_release_allowed' => $releaseAllowed,
                'visible_rows' => count($visibleRows),
                'current_rows' => count($currentRows),
                'next_rows' => count($nextRows),
                'queued_next_rows' => count($queuedNextRows),
                'restart_required' => !$snapshotStable,
                'decision' => !$snapshotStable ? 'restart-current-source-returning-snapshot' : ($releaseAllowed ? 'publish-current-then-next-returning' : 'hold-next-source-returning'),
            ],
            'blocked_reasons_next178' => $blockedReasons,
            'yield_boundary_next178' => $snapshotStable && $releaseAllowed
                ? 'recursive-view-returning-next178-current-source-snapshot-stable-then-next'
                : 'recursive-view-returning-next178-current-source-snapshot-fences-next',
            'dependencies_next178' => [
                'sqlite-trigger-recursive-view-returning-current-source-next178',
                'sqlite-returning-current-source-snapshot-token-fence',
                'sqlite-returning-view-schema-cookie-fence',
                'wordpress-recursive-view-returning-current-source-next178',
            ],
            'dependency_closure_next178' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-savepoint-and-schema-cookie-model',
            'non_overlap_next178' => 'extends next175 savepoint fencing with current-source snapshot-token and view-schema-cookie RETURNING row publication; does not repeat duplicate-key watermarking, savepoint rollback/release, schema reparse, deferred FK, UPSERT, or WAL/VFS slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $pages
     * @return list<array<string,mixed>>
     */
    private static function flattenRowsNext178(array $pages, string $snapshotToken, int $schemaCookie): array
    {
        $rows = [];
        foreach ($pages as $page) {
            if (!isset($page['phase'], $page['page'], $page['rows']) || !is_array($page['rows'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next178 page is malformed');
            }
            foreach ($page['rows'] as $row) {
                if (!is_array($row) || !isset($row['returning'])) {
                    throw new InvalidArgumentException('SQLite recursive view RETURNING next178 row is malformed');
                }
                $source = (string) $page['phase'];
                $rows[] = $row + [
                    'statement_source' => str_starts_with($source, 'next') ? 'next' : 'current',
                    'returning_page' => (int) $page['page'],
                    'returning_snapshot_token' => $snapshotToken,
                    'returning_schema_cookie' => $schemaCookie,
                    'returning_row_ordinal' => count($rows),
                    'returning_option_name' => (string) (($row['returning']['option_name'] ?? null) ?? ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param mixed $pages
     * @return list<array<string,mixed>>
     */
    private static function pagesNext178(mixed $pages, string $label): array
    {
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} are malformed");
        }

        return $pages;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringsNext178(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function cookieNext178(mixed $value, string $label): int
    {
        $cookie = (int) $value;
        if ($cookie < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} must be non-negative");
        }

        return $cookie;
    }

    private static function tokenNext178(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,current_source_token?:string,expected_current_source_token?:string,drain_ack_token?:string,expected_drain_ack_token?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext180(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $currentToken = self::tokenNext180((string) ($options['current_source_token'] ?? 'wp.current.source.180'), 'current source token');
        $expectedCurrentToken = self::tokenNext180((string) ($options['expected_current_source_token'] ?? $currentToken), 'expected current source token');
        $drainAck = self::tokenNext180((string) ($options['drain_ack_token'] ?? 'wp.returning.drain.180'), 'drain ack token');
        $expectedDrainAck = self::tokenNext180((string) ($options['expected_drain_ack_token'] ?? $drainAck), 'expected drain ack token');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext177(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentSourceMatches = $currentToken === $expectedCurrentToken;
        $drainAckMatches = $drainAck === $expectedDrainAck;
        $sourceChanged = self::viewSignatureNext180($currentView, $returning) !== self::viewSignatureNext180($nextView, $returning);
        $canAdmitNext = $currentSourceMatches
            && $drainAckMatches
            && ($base['resume_boundary']['current_drained_before_next'] ?? false) === true
            && ($base['reprepare_token_matches'] ?? false) === true
            && (($base['base']['status'] ?? null) === 'trigger-recursive-view-returning-current-source-next172-next-admitted');

        $currentFrame = self::frameNext180($currentView, $returning, 'current', $currentToken, $expectedCurrentToken, true);
        $nextFrame = self::frameNext180($nextView, $returning, 'next', $drainAck, $expectedDrainAck, $canAdmitNext);
        $currentRows = self::sourceRowsNext180($base['current_source_rows'] ?? [], $currentFrame, true, []);
        $blockReasons = self::blockReasonsNext180($base, $currentSourceMatches, $drainAckMatches, $sourceChanged, $canAdmitNext);
        $nextRows = self::sourceRowsNext180($base['attempted_next_source_rows'] ?? [], $nextFrame, $canAdmitNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($currentRows, $nextRows), static fn (array $row): bool => $row['visible_after_source_snapshot']));
        $heldRows = array_values(array_filter($nextRows, static fn (array $row): bool => !$row['visible_after_source_snapshot']));

        return [
            'status_next180' => self::statusNext180($canAdmitNext, $currentSourceMatches, $drainAckMatches, $base),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'base' => $base,
            'current_source_token_next180' => $currentToken,
            'expected_current_source_token_next180' => $expectedCurrentToken,
            'current_source_token_matches_next180' => $currentSourceMatches,
            'drain_ack_token_next180' => $drainAck,
            'expected_drain_ack_token_next180' => $expectedDrainAck,
            'drain_ack_token_matches_next180' => $drainAckMatches,
            'source_changed_next180' => $sourceChanged,
            'next_source_admitted_next180' => $canAdmitNext,
            'source_frames_next180' => [$currentFrame, $nextFrame],
            'current_source_frame_next180' => $currentFrame,
            'next_source_frame_next180' => $nextFrame,
            'current_source_rows_next180' => $currentRows,
            'attempted_next_source_rows_next180' => $nextRows,
            'visible_rows_next180' => $visibleRows,
            'held_rows_next180' => $heldRows,
            'visible_returning_rows_next180' => array_column($visibleRows, 'returning'),
            'held_returning_rows_next180' => array_column($heldRows, 'returning'),
            'block_reasons_next180' => $blockReasons,
            'source_snapshot_next180' => [
                'current_signature' => $currentFrame['source_signature'],
                'next_signature' => $nextFrame['source_signature'],
                'current_rows_visible' => count($currentRows),
                'attempted_next_rows' => count($nextRows),
                'held_next_rows' => count($heldRows),
                'next_rows_visible' => count($nextRows) - count($heldRows),
                'current_source_frozen_until_reset' => true,
                'next_source_requires_reprepare' => $sourceChanged,
            ],
            'yield_boundary_next180' => $canAdmitNext
                ? 'recursive-view-returning-next180-source-snapshot-next-admitted'
                : 'recursive-view-returning-next180-current-source-snapshot-held',
            'dependency_closure_next180' => 'no new support component needed; reuses recursive view trigger RETURNING current-source cursor and source snapshot modeling',
            'dependencies_next180' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next180',
                'sqlite-returning-current-source-snapshot-admission',
                'wordpress-recursive-view-returning-current-source-next180',
            ]))),
            'non_overlap_next180' => 'adds current/next source-signature admission over accepted next177 resume tokens; avoids next172 source pinning, next174 watermarking, next175 savepoint release, and next177 cursor-token coverage',
        ];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function frameNext180(array $view, array $returning, string $phase, string $token, string $expectedToken, bool $admitted): array
    {
        $columns = self::stringsNext180($view['columns'] ?? [], "{$phase} view columns");
        $mapping = self::mappingNext180($view['mapping'] ?? [], "{$phase} view mapping");

        return [
            'phase' => $phase,
            'view' => self::identifierNext180((string) ($view['name'] ?? ''), "{$phase} view name"),
            'source' => self::tokenNext180((string) ($view['source'] ?? ''), "{$phase} source"),
            'trigger' => self::identifierNext180((string) ($view['trigger'] ?? ''), "{$phase} trigger"),
            'trigger_source' => self::tokenNext180((string) ($view['trigger_source'] ?? ''), "{$phase} trigger source"),
            'columns' => $columns,
            'mapping' => $mapping,
            'returning_aliases' => self::returningAliasesNext180($returning),
            'source_signature' => self::viewSignatureNext180($view, $returning),
            'token' => $token,
            'expected_token' => $expectedToken,
            'token_matches' => $token === $expectedToken,
            'admitted' => $admitted,
        ];
    }

    /**
     * @param mixed $rows
     * @param array<string,mixed> $frame
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function sourceRowsNext180(mixed $rows, array $frame, bool $visible, array $blockReasons): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 rows are malformed');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'])) {
                throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 row is malformed');
            }
            $out[] = $row + [
                'source_signature_next180' => $frame['source_signature'],
                'source_frame_phase_next180' => $frame['phase'],
                'source_frame_token_next180' => $frame['token'],
                'visible_after_source_snapshot' => $visible,
                'held_by_source_snapshot_reasons' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function blockReasonsNext180(array $base, bool $currentSourceMatches, bool $drainAckMatches, bool $sourceChanged, bool $canAdmitNext): array
    {
        if ($canAdmitNext) {
            return [];
        }

        $reasons = [];
        if (!$currentSourceMatches) {
            $reasons[] = 'current-source-token-mismatch';
        }
        if (!$drainAckMatches) {
            $reasons[] = 'current-returning-drain-ack-mismatch';
        }
        if (($base['resume_boundary']['current_drained_before_next'] ?? false) !== true) {
            $reasons[] = 'current-returning-cursor-not-drained';
        }
        if (($base['reprepare_token_matches'] ?? false) !== true) {
            $reasons[] = 'reprepare-token-mismatch';
        }
        if (($base['base']['status'] ?? null) !== 'trigger-recursive-view-returning-current-source-next172-next-admitted') {
            $reasons[] = $sourceChanged ? 'changed-next-source-awaits-reprepare' : 'next-source-admission-not-requested';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext180(bool $canAdmitNext, bool $currentSourceMatches, bool $drainAckMatches, array $base): string
    {
        if ($canAdmitNext) {
            return 'trigger-recursive-view-returning-current-source-next180-next-admitted';
        }
        if (!$currentSourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next180-current-source-token-held';
        }
        if (!$drainAckMatches) {
            return 'trigger-recursive-view-returning-current-source-next180-drain-ack-held';
        }
        if (($base['reprepare_token_matches'] ?? false) !== true) {
            return 'trigger-recursive-view-returning-current-source-next180-reprepare-held';
        }

        return 'trigger-recursive-view-returning-current-source-next180-current-source-held';
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function viewSignatureNext180(array $view, array $returning): string
    {
        $payload = [
            'name' => (string) ($view['name'] ?? ''),
            'source' => (string) ($view['source'] ?? ''),
            'trigger' => (string) ($view['trigger'] ?? ''),
            'trigger_source' => (string) ($view['trigger_source'] ?? ''),
            'columns' => array_values((array) ($view['columns'] ?? [])),
            'mapping' => (array) ($view['mapping'] ?? []),
            'returning' => self::returningAliasesNext180($returning),
        ];

        return substr(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<string>
     */
    private static function returningAliasesNext180(array $returning): array
    {
        $aliases = [];
        foreach ($returning as $index => $term) {
            if (is_string($term)) {
                $aliases[] = $term;
                continue;
            }
            if (is_array($term)) {
                $aliases[] = (string) ($term['as'] ?? $term['expr'] ?? "expr_{$index}");
                continue;
            }
            if (is_callable($term)) {
                $aliases[] = "callable_{$index}";
                continue;
            }
            throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 returning term is malformed');
        }

        if ($aliases === []) {
            throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 returning aliases cannot be empty');
        }

        return $aliases;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringsNext180(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} must be a non-empty list");
        }

        return array_map(static fn (mixed $value): string => self::identifierNext180((string) $value, $label), $values);
    }

    /**
     * @param mixed $mapping
     * @return array<string,string>
     */
    private static function mappingNext180(mixed $mapping, string $label): array
    {
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} must not be empty");
        }

        $out = [];
        foreach ($mapping as $from => $to) {
            $out[self::identifierNext180((string) $from, $label)] = self::identifierNext180((string) $to, $label);
        }

        return $out;
    }

    private static function identifierNext180(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} is malformed");
        }

        return $value;
    }

    private static function tokenNext180(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,checkpoint_name?:string,commit_visible_checkpoints?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext181(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $checkpoint = self::tokenNext181((string) ($options['checkpoint_name'] ?? 'wp_recursive_view_returning_checkpoint_181'), 'checkpoint name');
        $commitVisible = (bool) ($options['commit_visible_checkpoints'] ?? true);
        $baseOptions = $options + [
            'cursor_name' => 'wp_recursive_view_returning_cursor_181',
            'current_generation' => 'wp-current-returning-181',
            'next_generation' => 'wp-next-returning-181',
            'savepoint' => 'wp_recursive_view_returning_next181',
        ];

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext177(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $groups = self::checkpointGroupsNext181($base['current_source_rows'], $base['attempted_next_source_rows']);
        $checkpoints = [];
        foreach ($groups as $key => $rows) {
            $checkpoints[] = self::checkpointRowNext181($checkpoint, $key, $rows, $commitVisible);
        }

        $visible = array_values(array_filter($checkpoints, static fn (array $row): bool => $row['visible']));
        $pending = array_values(array_filter($checkpoints, static fn (array $row): bool => !$row['visible']));
        $durable = array_values(array_filter($visible, static fn (array $row): bool => $row['durable']));

        return [
            'status' => self::statusNext181($base, $pending),
            'checkpoint_name' => $checkpoint,
            'base' => $base,
            'checkpoints' => $checkpoints,
            'visible_checkpoints' => $visible,
            'pending_checkpoints' => $pending,
            'durable_checkpoints' => $durable,
            'checkpoint_tokens' => array_column($checkpoints, 'checkpoint_token'),
            'visible_checkpoint_tokens' => array_column($visible, 'checkpoint_token'),
            'pending_checkpoint_tokens' => array_column($pending, 'checkpoint_token'),
            'durable_checkpoint_tokens' => array_column($durable, 'checkpoint_token'),
            'last_visible_checkpoint' => $visible === [] ? null : $visible[array_key_last($visible)],
            'first_pending_checkpoint' => $pending[0] ?? null,
            'replay_plan' => [
                'current_generation' => $base['current_generation'],
                'next_generation' => $base['next_generation'],
                'next_admitted' => $base['resume_boundary']['next_admitted'],
                'pending_requires_reprepare' => $pending !== [],
                'resume_after_token' => $visible === [] ? null : $visible[array_key_last($visible)]['last_resume_token'],
                'blocked_at_token' => $pending[0]['first_resume_token'] ?? null,
            ],
            'counts' => [
                'checkpoints' => count($checkpoints),
                'visible' => count($visible),
                'pending' => count($pending),
                'durable' => count($durable),
                'rows_visible' => count($base['visible_rows']),
                'rows_pending' => count($base['held_rows']),
            ],
            'yield_boundary' => $pending === []
                ? 'recursive-view-returning-current-source-checkpoint-next181-all-visible'
                : 'recursive-view-returning-current-source-checkpoint-next181-next-pending',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next181',
                'sqlite-returning-cursor-checkpoint-source-boundary',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING cursor rows and checkpoint metadata',
            'non_overlap' => 'adds page checkpoint visibility/durability over accepted next177 resume tokens; avoids changing accepted next172 source pinning or next177 row admission',
        ];
    }

    /**
     * @param mixed $currentRows
     * @param mixed $nextRows
     * @return array<string,list<array<string,mixed>>>
     */
    private static function checkpointGroupsNext181(mixed $currentRows, mixed $nextRows): array
    {
        if (!is_array($currentRows) || !array_is_list($currentRows) || !is_array($nextRows) || !array_is_list($nextRows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next181 checkpoint rows are malformed');
        }

        $groups = [];
        foreach (array_merge($currentRows, $nextRows) as $row) {
            if (!is_array($row) || !isset($row['phase'], $row['generation'], $row['resume_page'], $row['resume_token'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next181 row envelope is malformed');
            }
            $key = (string) $row['phase'] . ':' . (string) $row['generation'] . ':' . (string) $row['resume_page'];
            $groups[$key][] = $row;
        }

        return $groups;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function checkpointRowNext181(string $checkpoint, string $key, array $rows, bool $commitVisible): array
    {
        [$phase, $generation, $page] = explode(':', $key, 3);
        $visible = array_values(array_unique(array_column($rows, 'visible_after_current_source'))) === [true];
        $names = [];
        foreach ($rows as $row) {
            $returning = $row['returning'] ?? [];
            $names[] = is_array($returning) ? ($returning['name'] ?? null) : null;
        }

        return [
            'checkpoint' => $checkpoint,
            'phase' => $phase,
            'generation' => $generation,
            'page' => (int) $page,
            'checkpoint_token' => $checkpoint . ':' . $generation . ':' . $page,
            'first_resume_token' => $rows[0]['resume_token'],
            'last_resume_token' => $rows[array_key_last($rows)]['resume_token'],
            'row_count' => count($rows),
            'names' => $names,
            'visible' => $visible,
            'durable' => $visible && $commitVisible,
            'source' => $rows[0]['source'] ?? null,
            'trigger_source' => $rows[0]['trigger_source'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $pending
     */
    private static function statusNext181(array $base, array $pending): string
    {
        if ($pending !== []) {
            return ($base['reprepare_token_matches'] ?? false)
                ? 'trigger-recursive-view-returning-current-source-next181-current-checkpointed-next-pending'
                : 'trigger-recursive-view-returning-current-source-next181-reprepare-checkpoint-pending';
        }

        return 'trigger-recursive-view-returning-current-source-next181-checkpoints-admitted';
    }

    private static function tokenNext181(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next181 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext182(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $currentGeneration = self::tokenNext182((string) ($options['current_source_generation'] ?? 'wp.recursive.view.current.182'), 'current source generation');
        $expectedCurrentGeneration = self::tokenNext182((string) ($options['expected_current_source_generation'] ?? $currentGeneration), 'expected current source generation');
        $triggerGeneration = self::tokenNext182((string) ($options['trigger_source_generation'] ?? 'wp.recursive.trigger.current.182'), 'trigger source generation');
        $expectedTriggerGeneration = self::tokenNext182((string) ($options['expected_trigger_source_generation'] ?? $triggerGeneration), 'expected trigger source generation');
        $cursorGeneration = self::tokenNext182((string) ($options['returning_cursor_generation'] ?? 'wp.recursive.returning.cursor.182'), 'returning cursor generation');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext178(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $currentMatches = hash_equals($currentGeneration, $expectedCurrentGeneration);
        $triggerMatches = hash_equals($triggerGeneration, $expectedTriggerGeneration);
        $snapshotStable = (bool) ($base['current_source_snapshot_stable_next178'] ?? false);
        $releaseAllowed = (bool) ($base['savepoint_release_allowed_next175'] ?? false);
        $generationStable = $snapshotStable && $currentMatches && $triggerMatches;
        $publishNext = $generationStable && $releaseAllowed;

        $baseVisibleRows = self::rowsNext182($base['visible_returning_rows_next178'] ?? [], 'visible rows');
        $baseCurrentRows = self::rowsNext182($base['current_source_returning_rows_next178'] ?? [], 'current rows');
        $baseNextRows = self::rowsNext182($base['next_source_returning_rows_next178'] ?? [], 'next rows');
        $baseQueuedNextRows = self::rowsNext182($base['queued_next_source_rows_next178'] ?? [], 'queued next rows');

        $visibleRows = self::tagRowsNext182($publishNext ? $baseVisibleRows : $baseCurrentRows, $currentGeneration, $triggerGeneration, $cursorGeneration);
        $currentRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'current'));
        $nextRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'next'));

        $quarantinedNext = $publishNext ? [] : self::tagRowsNext182(array_merge($baseNextRows, $baseQueuedNextRows), $currentGeneration, $triggerGeneration, $cursorGeneration);
        $quarantinedNext = self::dedupeRowsNext182($quarantinedNext);

        $blockedReasons = self::stringsNext182($base['blocked_reasons_next178'] ?? [], 'blocked reasons');
        if (!$currentMatches) {
            $blockedReasons[] = 'current-view-source-generation-mismatch';
        }
        if (!$triggerMatches) {
            $blockedReasons[] = 'current-trigger-source-generation-mismatch';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        return $base + [
            'status_next182' => match (true) {
                !$generationStable => 'trigger-recursive-view-returning-current-source-generation-restart-next182',
                $publishNext => 'trigger-recursive-view-returning-current-source-generation-released-next182',
                default => 'trigger-recursive-view-returning-current-source-generation-held-next182',
            },
            'current_source_generation_next182' => $currentGeneration,
            'expected_current_source_generation_next182' => $expectedCurrentGeneration,
            'trigger_source_generation_next182' => $triggerGeneration,
            'expected_trigger_source_generation_next182' => $expectedTriggerGeneration,
            'returning_cursor_generation_next182' => $cursorGeneration,
            'current_source_generation_matches_next182' => $currentMatches,
            'trigger_source_generation_matches_next182' => $triggerMatches,
            'current_source_generation_stable_next182' => $generationStable,
            'next_source_publish_allowed_next182' => $publishNext,
            'visible_returning_rows_next182' => $visibleRows,
            'current_source_returning_rows_next182' => $currentRows,
            'next_source_returning_rows_next182' => $nextRows,
            'quarantined_next_source_rows_next182' => $quarantinedNext,
            'statement_returning_row_count_next182' => count($visibleRows),
            'current_returning_row_count_next182' => count($currentRows),
            'next_returning_row_count_next182' => count($nextRows),
            'quarantined_next_row_count_next182' => count($quarantinedNext),
            'returning_source_order_next182' => array_values(array_unique(array_column($visibleRows, 'statement_source'))),
            'returning_generation_plan_next182' => [
                'snapshot_stable' => $snapshotStable,
                'savepoint_release_allowed' => $releaseAllowed,
                'current_source_generation_matches' => $currentMatches,
                'trigger_source_generation_matches' => $triggerMatches,
                'visible_rows' => count($visibleRows),
                'current_rows' => count($currentRows),
                'next_rows' => count($nextRows),
                'quarantined_next_rows' => count($quarantinedNext),
                'restart_required' => !$generationStable,
                'decision' => !$generationStable ? 'restart-current-source-generation' : ($publishNext ? 'publish-current-then-next-generation' : 'hold-next-source-generation'),
            ],
            'blocked_reasons_next182' => $blockedReasons,
            'yield_boundary_next182' => $publishNext
                ? 'recursive-view-returning-next182-current-generation-stable-then-next'
                : 'recursive-view-returning-next182-current-generation-fences-next',
            'dependencies_next182' => [
                'sqlite-trigger-recursive-view-returning-current-source-next182',
                'sqlite-returning-current-view-source-generation-fence',
                'sqlite-returning-current-trigger-source-generation-fence',
                'wordpress-recursive-view-returning-current-source-next182',
            ],
            'dependency_closure_next182' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-trigger-cookie-model',
            'non_overlap_next182' => 'extends next178 snapshot/schema-cookie fencing with current view-source and trigger-source generation quarantine; does not repeat duplicate-key watermarking, savepoint release/rollback, schema reparse, deferred FK, UPSERT, WAL, VFS, or row-value RETURNING slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext182(array $rows, string $currentGeneration, string $triggerGeneration, string $cursorGeneration): array
    {
        $out = [];
        foreach ($rows as $ordinal => $row) {
            $out[] = $row + [
                'returning_current_source_generation' => $currentGeneration,
                'returning_trigger_source_generation' => $triggerGeneration,
                'returning_cursor_generation' => $cursorGeneration,
                'returning_generation_ordinal' => $ordinal,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function dedupeRowsNext182(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = ($row['statement_source'] ?? '') . "\0" . ($row['returning_page'] ?? '') . "\0" . ($row['returning_row_ordinal'] ?? '') . "\0" . ($row['returning_option_name'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext182(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringsNext182(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function tokenNext182(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,current_source_token?:string,expected_current_source_token?:string,drain_ack_token?:string,expected_drain_ack_token?:string,rollback_current_source?:bool,rollback_token?:string,expected_rollback_token?:string,commit_current_source?:bool,reset_generation?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext183(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $rollbackToken = self::tokenNext183((string) ($options['rollback_token'] ?? 'wp.rollback.current.183'), 'rollback token');
        $expectedRollbackToken = self::tokenNext183((string) ($options['expected_rollback_token'] ?? $rollbackToken), 'expected rollback token');
        $resetGeneration = self::tokenNext183((string) ($options['reset_generation'] ?? 'wp-current-reset-183'), 'reset generation');
        $rollbackRequested = (bool) ($options['rollback_current_source'] ?? true);
        $commitCurrent = (bool) ($options['commit_current_source'] ?? false);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext180(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $rollbackTokenMatches = $rollbackToken === $expectedRollbackToken;
        $currentRows = self::rowsNext183($base['current_source_rows_next180'] ?? [], 'current rows');
        $attemptedNextRows = self::rowsNext183($base['attempted_next_source_rows_next180'] ?? [], 'attempted next rows');
        $currentRollbackApplied = $rollbackRequested && $rollbackTokenMatches && !$commitCurrent;
        $nextBaseAdmitted = (bool) ($base['next_source_admitted_next180'] ?? false);
        $nextVisible = $nextBaseAdmitted && $commitCurrent && !$currentRollbackApplied && $rollbackTokenMatches;

        $currentAfterBarrier = self::barrierRowsNext183(
            $currentRows,
            !$currentRollbackApplied,
            $currentRollbackApplied ? ['current-source-rollback-token-applied'] : [],
            $resetGeneration,
        );
        $nextAfterBarrier = self::barrierRowsNext183(
            $attemptedNextRows,
            $nextVisible,
            self::nextBarrierReasonsNext183($base, $currentRollbackApplied, $rollbackTokenMatches, $commitCurrent),
            $resetGeneration,
        );
        $visibleRows = array_values(array_filter(
            array_merge($currentAfterBarrier, $nextAfterBarrier),
            static fn (array $row): bool => $row['visible_after_current_source_reset_next183']
        ));
        $invalidatedCurrentRows = array_values(array_filter(
            $currentAfterBarrier,
            static fn (array $row): bool => !$row['visible_after_current_source_reset_next183']
        ));
        $blockedNextRows = array_values(array_filter(
            $nextAfterBarrier,
            static fn (array $row): bool => !$row['visible_after_current_source_reset_next183']
        ));

        return [
            'status_next183' => self::statusNext183($currentRollbackApplied, $rollbackRequested, $rollbackTokenMatches, $commitCurrent, $nextBaseAdmitted),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'base' => $base,
            'rollback_token_next183' => $rollbackToken,
            'expected_rollback_token_next183' => $expectedRollbackToken,
            'rollback_token_matches_next183' => $rollbackTokenMatches,
            'rollback_requested_next183' => $rollbackRequested,
            'commit_current_source_next183' => $commitCurrent,
            'current_source_rollback_applied_next183' => $currentRollbackApplied,
            'reset_generation_next183' => $resetGeneration,
            'next_source_admitted_before_reset_next183' => $nextBaseAdmitted,
            'next_source_visible_after_reset_next183' => $nextVisible,
            'current_rows_after_reset_next183' => $currentAfterBarrier,
            'attempted_next_rows_after_reset_next183' => $nextAfterBarrier,
            'visible_rows_after_reset_next183' => $visibleRows,
            'invalidated_current_rows_next183' => $invalidatedCurrentRows,
            'blocked_next_rows_next183' => $blockedNextRows,
            'visible_returning_rows_next183' => array_column($visibleRows, 'returning'),
            'invalidated_returning_rows_next183' => array_column($invalidatedCurrentRows, 'returning'),
            'blocked_next_returning_rows_next183' => array_column($blockedNextRows, 'returning'),
            'reset_barrier_next183' => [
                'current_rows_before_reset' => count($currentRows),
                'attempted_next_rows_before_reset' => count($attemptedNextRows),
                'visible_rows_after_reset' => count($visibleRows),
                'invalidated_current_rows' => count($invalidatedCurrentRows),
                'blocked_next_rows' => count($blockedNextRows),
                'rollback_token_matches' => $rollbackTokenMatches,
                'current_source_reset_generation' => $resetGeneration,
                'yielded_returning_invalidated_by_rollback' => $currentRollbackApplied,
                'next_source_requires_current_source_commit' => $currentRollbackApplied,
            ],
            'yield_boundary_next183' => $currentRollbackApplied
                ? 'recursive-view-returning-next183-yield-then-current-source-rollback'
                : ($nextVisible
                    ? 'recursive-view-returning-next183-current-source-committed-next-visible'
                    : 'recursive-view-returning-next183-current-source-held'),
            'dependency_closure_next183' => 'no new support component needed; reuses recursive view trigger RETURNING current-source snapshots and adds reset-barrier visibility modeling',
            'dependencies_next183' => array_values(array_unique(array_merge($base['dependencies_next180'], [
                'sqlite-trigger-recursive-view-returning-current-source-next183',
                'sqlite-returning-current-source-reset-invalidates-yielded-rows',
                'wordpress-recursive-view-returning-current-source-next183',
            ]))),
            'non_overlap_next183' => 'adds rollback/reset-barrier visibility after next180 source snapshots; avoids accepted next177 resume tokens and next180 source-signature admission',
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rowsNext183(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next183 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next183 {$label} row is malformed");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function barrierRowsNext183(array $rows, bool $visible, array $reasons, string $resetGeneration): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'visible_after_current_source_reset_next183' => $visible,
                'reset_generation_next183' => $resetGeneration,
                'reset_block_reasons_next183' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function nextBarrierReasonsNext183(array $base, bool $currentRollbackApplied, bool $rollbackTokenMatches, bool $commitCurrent): array
    {
        if ($currentRollbackApplied) {
            return ['current-source-rolled-back-before-next-source'];
        }
        if (($base['next_source_admitted_next180'] ?? false) !== true) {
            return $base['block_reasons_next180'] ?? ['next-source-held-by-source-snapshot'];
        }
        if (!$rollbackTokenMatches) {
            return ['rollback-token-mismatch'];
        }
        if (!$commitCurrent) {
            return ['current-source-not-committed'];
        }

        return [];
    }

    private static function statusNext183(bool $rollbackApplied, bool $rollbackRequested, bool $tokenMatches, bool $commitCurrent, bool $nextBaseAdmitted): string
    {
        if ($rollbackApplied) {
            return 'trigger-recursive-view-returning-current-source-next183-rolled-back';
        }
        if ($rollbackRequested && !$tokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next183-rollback-token-held';
        }
        if ($commitCurrent && $nextBaseAdmitted) {
            return 'trigger-recursive-view-returning-current-source-next183-committed-next-visible';
        }
        if (!$rollbackRequested && !$commitCurrent) {
            return 'trigger-recursive-view-returning-current-source-next183-current-held';
        }

        return 'trigger-recursive-view-returning-current-source-next183-next-held';
    }

    private static function tokenNext183(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next183 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,checkpoint_name?:string,commit_visible_checkpoints?:bool,handoff_token?:string,expected_handoff_token?:string,acknowledged_current_checkpoints?:list<string>,auto_ack_current?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext184(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $handoffToken = self::tokenNext184((string) ($options['handoff_token'] ?? 'wp.returning.current.source.handoff.184'), 'handoff token');
        $expectedHandoffToken = self::tokenNext184((string) ($options['expected_handoff_token'] ?? $handoffToken), 'expected handoff token');
        $baseOptions = $options + [
            'cursor_name' => 'wp_recursive_view_returning_cursor_184',
            'current_generation' => 'wp-current-returning-184',
            'next_generation' => 'wp-next-returning-184',
            'checkpoint_name' => 'wp_recursive_view_checkpoint_184',
            'savepoint' => 'wp_recursive_view_returning_next184',
        ];

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext181(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $currentCheckpoints = self::phaseCheckpointsNext184($base['visible_checkpoints'] ?? [], 'current');
        $currentTokens = array_column($currentCheckpoints, 'checkpoint_token');
        $acknowledged = self::acknowledgedTokensNext184($options, $currentTokens);
        $missing = array_values(array_diff($currentTokens, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $currentTokens));
        $handoffMatches = $handoffToken === $expectedHandoffToken;
        $currentComplete = $currentTokens !== [] && $missing === [] && $unexpected === [];
        $baseNextAdmitted = (bool) ($base['replay_plan']['next_admitted'] ?? false);
        $canExposeNext = $handoffMatches && $currentComplete && $baseNextAdmitted && ($base['pending_checkpoints'] ?? []) === [];

        $currentRows = self::handoffRowsNext184($base['base']['current_source_rows'] ?? [], 'current', true, $handoffToken, []);
        $nextBlockReasons = self::blockReasonsNext184($handoffMatches, $currentComplete, $baseNextAdmitted, $missing, $unexpected, $base);
        $nextRows = self::handoffRowsNext184($base['base']['attempted_next_source_rows'] ?? [], 'next', $canExposeNext, $handoffToken, $nextBlockReasons);
        $visibleRows = array_values(array_filter(array_merge($currentRows, $nextRows), static fn (array $row): bool => $row['visible_after_handoff']));
        $heldRows = array_values(array_filter($nextRows, static fn (array $row): bool => !$row['visible_after_handoff']));
        $currentAcks = self::checkpointAcksNext184($currentCheckpoints, $acknowledged, $handoffToken);
        $nextAcks = self::checkpointAcksNext184($base['pending_checkpoints'] ?? [], [], $handoffToken);

        return [
            'status' => self::statusNext184($canExposeNext, $handoffMatches, $currentComplete, $baseNextAdmitted),
            'base' => $base,
            'handoff_token' => $handoffToken,
            'expected_handoff_token' => $expectedHandoffToken,
            'handoff_token_matches' => $handoffMatches,
            'acknowledged_current_checkpoints' => $acknowledged,
            'required_current_checkpoints' => $currentTokens,
            'missing_current_checkpoints' => $missing,
            'unexpected_current_checkpoints' => $unexpected,
            'current_handoff_complete' => $currentComplete,
            'next_source_exposed_after_handoff' => $canExposeNext,
            'current_checkpoint_acks' => $currentAcks,
            'next_checkpoint_acks' => $nextAcks,
            'current_source_rows' => $currentRows,
            'attempted_next_source_rows' => $nextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'block_reasons' => $nextBlockReasons,
            'handoff_plan' => [
                'current_generation' => $base['replay_plan']['current_generation'] ?? null,
                'next_generation' => $base['replay_plan']['next_generation'] ?? null,
                'resume_after_token' => $currentRows === [] ? null : $currentRows[array_key_last($currentRows)]['resume_token'],
                'blocked_at_token' => $canExposeNext ? null : ($base['replay_plan']['blocked_at_token'] ?? ($nextRows[0]['resume_token'] ?? null)),
                'current_checkpoint_count' => count($currentTokens),
                'acknowledged_checkpoint_count' => count($acknowledged),
                'next_row_count' => count($nextRows),
            ],
            'counts' => [
                'required_current_checkpoints' => count($currentTokens),
                'acknowledged_current_checkpoints' => count($acknowledged),
                'missing_current_checkpoints' => count($missing),
                'unexpected_current_checkpoints' => count($unexpected),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'current_rows' => count($currentRows),
                'attempted_next_rows' => count($nextRows),
            ],
            'yield_boundary' => $canExposeNext
                ? 'recursive-view-returning-current-source-next184-next-source-exposed'
                : 'recursive-view-returning-current-source-next184-next-source-held',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next184',
                'sqlite-returning-current-source-handoff-ack',
                'wordpress-recursive-view-returning-current-source-next184',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING checkpoint and cursor metadata',
            'non_overlap' => 'adds current-source checkpoint acknowledgement handoff before next RETURNING exposure; avoids accepted next177 resume-token and next181 checkpoint visibility behavior',
        ];
    }

    /**
     * @param array<string,mixed> $options
     * @param mixed $currentTokens
     * @return list<string>
     */
    private static function acknowledgedTokensNext184(array $options, mixed $currentTokens): array
    {
        if (($options['auto_ack_current'] ?? false) === true) {
            return self::tokenListNext184($currentTokens, 'current checkpoint tokens');
        }

        return self::tokenListNext184($options['acknowledged_current_checkpoints'] ?? [], 'acknowledged current checkpoints');
    }

    /**
     * @param mixed $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function handoffRowsNext184(mixed $rows, string $phase, bool $visible, string $handoffToken, array $blockReasons): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next184 rows are malformed');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['resume_token'], $row['returning'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next184 row envelope is malformed');
            }
            $out[] = $row + [
                'handoff_phase' => $phase,
                'handoff_token' => $handoffToken,
                'visible_after_handoff' => $visible,
                'held_by_handoff_reasons' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $checkpoints
     * @param list<string> $acknowledged
     * @return list<array<string,mixed>>
     */
    private static function checkpointAcksNext184(mixed $checkpoints, array $acknowledged, string $handoffToken): array
    {
        if (!is_array($checkpoints) || !array_is_list($checkpoints)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next184 checkpoints are malformed');
        }

        $out = [];
        foreach ($checkpoints as $checkpoint) {
            if (!is_array($checkpoint) || !isset($checkpoint['checkpoint_token'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next184 checkpoint is malformed');
            }
            $token = (string) $checkpoint['checkpoint_token'];
            $out[] = [
                'checkpoint_token' => $token,
                'handoff_token' => $handoffToken,
                'acknowledged' => in_array($token, $acknowledged, true),
                'phase' => $checkpoint['phase'] ?? null,
                'first_resume_token' => $checkpoint['first_resume_token'] ?? null,
                'last_resume_token' => $checkpoint['last_resume_token'] ?? null,
                'row_count' => $checkpoint['row_count'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $checkpoints
     * @return list<array<string,mixed>>
     */
    private static function phaseCheckpointsNext184(mixed $checkpoints, string $phase): array
    {
        if (!is_array($checkpoints) || !array_is_list($checkpoints)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next184 phase checkpoints are malformed');
        }

        return array_values(array_filter($checkpoints, static fn (mixed $checkpoint): bool => is_array($checkpoint) && ($checkpoint['phase'] ?? null) === $phase));
    }

    /**
     * @param mixed $tokens
     * @return list<string>
     */
    private static function tokenListNext184(mixed $tokens, string $label): array
    {
        if (!is_array($tokens) || !array_is_list($tokens)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next184 {$label} must be a list");
        }

        return array_values(array_unique(array_map(static fn (mixed $token): string => self::tokenNext184((string) $token, $label), $tokens)));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockReasonsNext184(bool $handoffMatches, bool $currentComplete, bool $baseNextAdmitted, array $missing, array $unexpected, array $base): array
    {
        $reasons = [];
        if (!$handoffMatches) {
            $reasons[] = 'handoff-token-mismatch';
        }
        if (!$currentComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-checkpoint-ack-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-checkpoint-ack-unexpected';
            }
        }
        if (!$baseNextAdmitted) {
            $reasons[] = (($base['pending_checkpoints'] ?? []) !== []) ? 'next-checkpoints-still-pending' : 'next-source-not-admitted';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext184(bool $canExposeNext, bool $handoffMatches, bool $currentComplete, bool $baseNextAdmitted): string
    {
        if ($canExposeNext) {
            return 'trigger-recursive-view-returning-current-source-next184-next-exposed';
        }
        if (!$handoffMatches) {
            return 'trigger-recursive-view-returning-current-source-next184-handoff-token-held';
        }
        if (!$currentComplete) {
            return 'trigger-recursive-view-returning-current-source-next184-current-ack-held';
        }
        if (!$baseNextAdmitted) {
            return 'trigger-recursive-view-returning-current-source-next184-next-admission-held';
        }

        return 'trigger-recursive-view-returning-current-source-next184-next-held';
    }

    private static function tokenNext184(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next184 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext185(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $nestedEpoch = self::tokenNext185((string) ($options['nested_epoch'] ?? 'wp.recursive.view.nested.185'), 'nested epoch');
        $expectedNestedEpoch = self::tokenNext185((string) ($options['expected_nested_epoch'] ?? $nestedEpoch), 'expected nested epoch');
        $requiredDepths = self::depthsNext185($options['required_nested_depths'] ?? [1, 2], 'required nested depths');
        $drainedDepths = self::depthsNext185($options['drained_nested_depths'] ?? $requiredDepths, 'drained nested depths');
        $outerPublishRequested = (bool) ($options['outer_publish_requested'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext182(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $baseVisibleRows = self::rowsNext185($base['visible_returning_rows_next182'] ?? [], 'visible rows');
        $baseCurrentRows = self::rowsNext185($base['current_source_returning_rows_next182'] ?? [], 'current rows');
        $baseNextRows = self::rowsNext185($base['next_source_returning_rows_next182'] ?? [], 'next rows');
        $baseQuarantinedRows = self::rowsNext185($base['quarantined_next_source_rows_next182'] ?? [], 'quarantined rows');

        $nestedRows = array_values(array_filter($baseCurrentRows, static fn (array $row): bool => (int) ($row['depth_value'] ?? $row['depth'] ?? 0) > 0));
        $outerRows = array_values(array_filter($baseCurrentRows, static fn (array $row): bool => (int) ($row['depth_value'] ?? $row['depth'] ?? 0) === 0));
        $requiredMissing = array_values(array_diff($requiredDepths, $drainedDepths));
        $nestedEpochMatches = hash_equals($nestedEpoch, $expectedNestedEpoch);
        $nestedDepthsDrained = $requiredMissing === [];
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next182'] ?? false);
        $outerPublishAllowed = $basePublishAllowed && $outerPublishRequested && $nestedEpochMatches && $nestedDepthsDrained;

        $visibleRows = self::tagRowsNext185($outerPublishAllowed ? $baseVisibleRows : $baseCurrentRows, $nestedEpoch);
        $visibleCurrentRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'current'));
        $visibleNextRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'next'));
        $heldNextRows = $outerPublishAllowed ? [] : self::tagRowsNext185(array_merge($baseNextRows, $baseQuarantinedRows), $nestedEpoch);
        $heldNextRows = self::dedupeRowsNext185($heldNextRows);

        $blockedReasons = self::stringsNext185($base['blocked_reasons_next182'] ?? [], 'blocked reasons');
        if (!$outerPublishRequested) {
            $blockedReasons[] = 'outer-returning-publish-not-requested';
        }
        if (!$nestedEpochMatches) {
            $blockedReasons[] = 'nested-recursive-returning-epoch-mismatch';
        }
        if (!$nestedDepthsDrained) {
            $blockedReasons[] = 'nested-recursive-returning-depths-not-drained';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        return $base + [
            'status_next185' => match (true) {
                !$nestedEpochMatches => 'trigger-recursive-view-returning-current-source-nested-restart-next185',
                $outerPublishAllowed => 'trigger-recursive-view-returning-current-source-nested-drained-next185',
                default => 'trigger-recursive-view-returning-current-source-nested-held-next185',
            },
            'nested_epoch_next185' => $nestedEpoch,
            'expected_nested_epoch_next185' => $expectedNestedEpoch,
            'nested_epoch_matches_next185' => $nestedEpochMatches,
            'required_nested_depths_next185' => $requiredDepths,
            'drained_nested_depths_next185' => $drainedDepths,
            'missing_nested_depths_next185' => $requiredMissing,
            'nested_depths_drained_next185' => $nestedDepthsDrained,
            'outer_publish_requested_next185' => $outerPublishRequested,
            'outer_publish_allowed_next185' => $outerPublishAllowed,
            'outer_current_returning_rows_next185' => self::tagRowsNext185($outerRows, $nestedEpoch),
            'nested_current_returning_rows_next185' => self::tagRowsNext185($nestedRows, $nestedEpoch),
            'visible_returning_rows_next185' => $visibleRows,
            'visible_current_returning_rows_next185' => $visibleCurrentRows,
            'visible_next_returning_rows_next185' => $visibleNextRows,
            'held_next_source_rows_next185' => $heldNextRows,
            'outer_current_row_count_next185' => count($outerRows),
            'nested_current_row_count_next185' => count($nestedRows),
            'visible_row_count_next185' => count($visibleRows),
            'visible_current_row_count_next185' => count($visibleCurrentRows),
            'visible_next_row_count_next185' => count($visibleNextRows),
            'held_next_row_count_next185' => count($heldNextRows),
            'returning_source_order_next185' => array_values(array_unique(array_column($visibleRows, 'statement_source'))),
            'nested_depth_drain_plan_next185' => [
                'nested_epoch_matches' => $nestedEpochMatches,
                'required_depths' => $requiredDepths,
                'drained_depths' => $drainedDepths,
                'missing_depths' => $requiredMissing,
                'base_publish_allowed' => $basePublishAllowed,
                'outer_publish_requested' => $outerPublishRequested,
                'outer_publish_allowed' => $outerPublishAllowed,
                'decision' => !$nestedEpochMatches
                    ? 'restart-nested-recursive-returning-epoch'
                    : ($outerPublishAllowed ? 'publish-current-nested-then-next' : 'hold-next-until-nested-depths-drain'),
            ],
            'blocked_reasons_next185' => $blockedReasons,
            'yield_boundary_next185' => $outerPublishAllowed
                ? 'recursive-view-returning-next185-nested-current-source-drained-then-next'
                : 'recursive-view-returning-next185-nested-current-source-fences-next',
            'dependencies_next185' => [
                'sqlite-trigger-recursive-view-returning-current-source-next185',
                'sqlite-returning-nested-recursive-depth-drain-fence',
                'sqlite-returning-nested-recursive-epoch-fence',
                'wordpress-recursive-view-returning-current-source-next185',
            ],
            'dependency_closure_next185' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-nested-depth-drain-model',
            'non_overlap_next185' => 'extends next182 generation fencing with nested recursive RETURNING depth-drain epochs; does not repeat next178 snapshot/schema-cookie, next176 page acknowledgements, next181 checkpoints, row-value RETURNING, UPSERT, WAL, VFS, or schema-reparse slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext185(array $rows, string $nestedEpoch): array
    {
        $out = [];
        foreach ($rows as $row) {
            $depth = (int) ($row['depth_value'] ?? $row['depth'] ?? 0);
            $out[] = $row + [
                'returning_nested_epoch' => $nestedEpoch,
                'returning_nested_depth_drained' => $depth > 0,
                'returning_nested_depth' => $depth,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function dedupeRowsNext185(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = ($row['statement_source'] ?? '') . "\0" . ($row['returning_row_ordinal'] ?? '') . "\0" . ($row['returning_option_name'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext185(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private static function depthsNext185(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} are malformed");
        }

        $out = [];
        foreach ($values as $value) {
            $depth = (int) $value;
            if ($depth < 0 || (string) $depth !== (string) $value) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} contain a malformed depth");
            }
            $out[] = $depth;
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringsNext185(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function tokenNext185(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,current_source_token?:string,expected_current_source_token?:string,drain_ack_token?:string,expected_drain_ack_token?:string,rollback_current_source?:bool,rollback_token?:string,expected_rollback_token?:string,commit_current_source?:bool,reset_generation?:string,post_reset_current_source_token?:string,expected_post_reset_current_source_token?:string,post_reset_cursor?:string,post_reset_view?:array<string,mixed>,post_reset_input?:list<array<string,mixed>>,reuse_stale_returning_cursor?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext186(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $postResetToken = self::tokenNext186((string) ($options['post_reset_current_source_token'] ?? 'wp.current.source.postreset.186'), 'post reset current source token');
        $expectedPostResetToken = self::tokenNext186((string) ($options['expected_post_reset_current_source_token'] ?? $postResetToken), 'expected post reset current source token');
        $postResetCursor = self::tokenNext186((string) ($options['post_reset_cursor'] ?? 'wp.returning.postreset.cursor.186'), 'post reset cursor');
        $reuseStaleCursor = (bool) ($options['reuse_stale_returning_cursor'] ?? false);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext183(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $postResetView = self::viewNext186($options['post_reset_view'] ?? $currentView);
        $postResetInput = self::rowsNext186($options['post_reset_input'] ?? $currentInput, 'post reset input');
        $tokenMatches = hash_equals($postResetToken, $expectedPostResetToken);
        $resetApplied = (bool) ($base['current_source_rollback_applied_next183'] ?? false);
        $staleRows = self::rowsNext186($base['invalidated_current_rows_next183'] ?? [], 'invalidated current rows');
        $staleReturningRows = array_column($staleRows, 'returning');
        $freshRows = ($resetApplied && $tokenMatches && !$reuseStaleCursor)
            ? self::freshRowsNext186($postResetInput, $postResetView, $returning, $postResetToken, $postResetCursor, (string) $base['reset_generation_next183'])
            : [];
        $blockedReasons = self::blockedReasonsNext186($resetApplied, $tokenMatches, $reuseStaleCursor, $base);

        return [
            'status_next186' => self::statusNext186($resetApplied, $tokenMatches, $reuseStaleCursor),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'base' => $base,
            'post_reset_current_source_token_next186' => $postResetToken,
            'expected_post_reset_current_source_token_next186' => $expectedPostResetToken,
            'post_reset_current_source_token_matches_next186' => $tokenMatches,
            'post_reset_cursor_next186' => $postResetCursor,
            'reuse_stale_returning_cursor_next186' => $reuseStaleCursor,
            'post_reset_view_signature_next186' => self::signatureNext186($postResetView, $returning),
            'stale_returning_rows_discarded_next186' => $resetApplied,
            'stale_returning_rows_next186' => $staleReturningRows,
            'stale_returning_row_count_next186' => count($staleReturningRows),
            'fresh_returning_rows_next186' => $freshRows,
            'fresh_returning_payloads_next186' => array_column($freshRows, 'returning'),
            'fresh_returning_row_count_next186' => count($freshRows),
            'blocked_reasons_next186' => $blockedReasons,
            'post_reset_rebind_plan_next186' => [
                'reset_generation' => (string) $base['reset_generation_next183'],
                'reset_applied' => $resetApplied,
                'post_reset_token_matches' => $tokenMatches,
                'stale_cursor_reuse_requested' => $reuseStaleCursor,
                'stale_rows_discarded' => $resetApplied ? count($staleReturningRows) : 0,
                'fresh_rows_bound' => count($freshRows),
                'decision' => self::decisionNext186($resetApplied, $tokenMatches, $reuseStaleCursor),
            ],
            'yield_boundary_next186' => $freshRows !== []
                ? 'recursive-view-returning-next186-post-reset-current-source-rebound'
                : 'recursive-view-returning-next186-post-reset-current-source-held',
            'dependency_closure_next186' => 'no new support component needed; reuses next183 reset-barrier rows and adds post-reset current-source RETURNING cursor rebinding',
            'dependencies_next186' => array_values(array_unique(array_merge($base['dependencies_next183'], [
                'sqlite-trigger-recursive-view-returning-current-source-next186',
                'sqlite-returning-post-reset-current-source-rebind',
                'wordpress-recursive-view-returning-current-source-next186',
            ]))),
            'non_overlap_next186' => 'extends accepted next183 rollback/reset visibility by proving the following statement binds a fresh post-reset current source and discards stale yielded RETURNING rows; avoids next180 snapshot admission, next182 generation fencing, next183 rollback invalidation, DELETE RETURNING, UPSERT, row-value, WAL, VFS, and B-tree slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function freshRowsNext186(array $input, array $view, array $returning, string $token, string $cursor, string $resetGeneration): array
    {
        $mapping = self::mappingNext186($view['mapping'] ?? []);
        $out = [];
        foreach ($input as $ordinal => $row) {
            $new = self::mappedRowNext186($row, $mapping);
            $payload = self::returningPayloadNext186($returning, $new, $view, $ordinal);
            $out[] = [
                'statement_source' => 'post-reset-current',
                'returning_row_ordinal' => $ordinal,
                'returning' => $payload,
                'returning_option_name' => (string) ($new['option_name'] ?? $new['name'] ?? ''),
                'post_reset_current_source_token_next186' => $token,
                'post_reset_cursor_next186' => $cursor,
                'reset_generation_next186' => $resetGeneration,
                'source_signature_next186' => self::signatureNext186($view, $returning),
                'stale_cursor_reused_next186' => false,
            ];
        }

        return $out;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayloadNext186(array $returning, array $new, array $view, int $ordinal): array
    {
        $payload = [];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'post-reset-current', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'] ?? null,
                'new.option_value' => $new['option_value'] ?? null,
                'old.option_value' => null,
                'event' => 'post-reset-current',
                'depth' => 0,
                'ordinal' => $ordinal,
                'trigger_source' => (string) ($view['trigger_source'] ?? ''),
                'view.name' => (string) ($view['name'] ?? ''),
                default => $new[$expr] ?? null,
            };
        }

        return $payload;
    }

    /**
     * @param array<string,string> $mapping
     * @return array<string,mixed>
     */
    private static function mappedRowNext186(array $row, array $mapping): array
    {
        $mapped = $row;
        foreach ($mapping as $source => $target) {
            if (array_key_exists($source, $row)) {
                $mapped[$target] = $row[$source];
            }
        }

        return $mapped;
    }

    /**
     * @return array<string,string>
     */
    private static function mappingNext186(mixed $mapping): array
    {
        if (!is_array($mapping)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next186 view mapping is malformed');
        }
        $out = [];
        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target) || $source === '' || $target === '') {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next186 view mapping entry is malformed');
            }
            $out[$source] = $target;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewNext186(mixed $view): array
    {
        if (!is_array($view)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next186 post reset view is malformed');
        }
        self::mappingNext186($view['mapping'] ?? []);

        return $view;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rowsNext186(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next186 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next186 {$label} row is malformed");
            }
        }

        return $rows;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function signatureNext186(array $view, array $returning): string
    {
        $aliases = [];
        foreach ($returning as $index => $term) {
            $aliases[] = is_array($term) ? (string) ($term['as'] ?? $term['expr'] ?? $index) : (is_string($term) ? $term : 'callable_' . $index);
        }

        return substr(hash('sha256', json_encode([
            'name' => (string) ($view['name'] ?? ''),
            'source' => (string) ($view['source'] ?? ''),
            'trigger' => (string) ($view['trigger'] ?? ''),
            'trigger_source' => (string) ($view['trigger_source'] ?? ''),
            'mapping' => (array) ($view['mapping'] ?? []),
            'returning' => $aliases,
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @return list<string>
     */
    private static function blockedReasonsNext186(bool $resetApplied, bool $tokenMatches, bool $reuseStaleCursor, array $base): array
    {
        $reasons = [];
        if (!$resetApplied) {
            $reasons[] = 'current-source-reset-not-applied';
        }
        if (!$tokenMatches) {
            $reasons[] = 'post-reset-current-source-token-mismatch';
        }
        if ($reuseStaleCursor) {
            $reasons[] = 'stale-returning-cursor-reuse-rejected';
        }
        if (($base['status_next183'] ?? '') === 'trigger-recursive-view-returning-current-source-next183-committed-next-visible') {
            $reasons[] = 'current-source-committed-no-reset-rebind';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext186(bool $resetApplied, bool $tokenMatches, bool $reuseStaleCursor): string
    {
        if ($resetApplied && $tokenMatches && !$reuseStaleCursor) {
            return 'trigger-recursive-view-returning-current-source-next186-post-reset-rebound';
        }
        if ($reuseStaleCursor) {
            return 'trigger-recursive-view-returning-current-source-next186-stale-cursor-rejected';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next186-token-held';
        }

        return 'trigger-recursive-view-returning-current-source-next186-reset-held';
    }

    private static function decisionNext186(bool $resetApplied, bool $tokenMatches, bool $reuseStaleCursor): string
    {
        if ($resetApplied && $tokenMatches && !$reuseStaleCursor) {
            return 'bind-fresh-post-reset-current-source';
        }
        if ($reuseStaleCursor) {
            return 'reject-stale-returning-cursor';
        }
        if (!$tokenMatches) {
            return 'hold-post-reset-current-source-token';
        }

        return 'hold-until-current-source-reset';
    }

    private static function tokenNext186(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next186 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,checkpoint_name?:string,commit_visible_checkpoints?:bool,handoff_token?:string,expected_handoff_token?:string,acknowledged_current_checkpoints?:list<string>,auto_ack_current?:bool,drain_ticket?:string,expected_drain_ticket?:string,drain_ticket_prefix?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext187(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $baseOptions = $options + [
            'cursor_name' => 'wp_recursive_view_returning_cursor_187',
            'current_generation' => 'wp-current-returning-187',
            'next_generation' => 'wp-next-returning-187',
            'checkpoint_name' => 'wp_recursive_view_checkpoint_187',
            'handoff_token' => 'wp.returning.current.source.handoff.187',
            'savepoint' => 'wp_recursive_view_returning_next187',
        ];

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext184(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $prefix = self::tokenNext187((string) ($options['drain_ticket_prefix'] ?? 'wp.returning.current.source.drain.187'), 'drain ticket prefix');
        $expectedTicket = self::tokenNext187((string) ($options['expected_drain_ticket'] ?? self::ticketNext187($prefix, $base['required_current_checkpoints'] ?? [])), 'expected drain ticket');
        $actualTicket = self::tokenNext187((string) ($options['drain_ticket'] ?? self::ticketNext187($prefix, $base['acknowledged_current_checkpoints'] ?? [])), 'drain ticket');
        $ticketMatches = $actualTicket === $expectedTicket;
        $baseExposed = (bool) ($base['next_source_exposed_after_handoff'] ?? false);
        $ticketAdmitted = !$baseExposed || $ticketMatches;
        $canExposeNext = $baseExposed && $ticketAdmitted;
        $blockReasons = self::blockReasonsNext187($base['block_reasons'] ?? [], $ticketAdmitted, $baseExposed);

        $currentRows = self::ticketRowsNext187($base['current_source_rows'] ?? [], $actualTicket, true, []);
        $attemptedNextRows = self::ticketRowsNext187($base['attempted_next_source_rows'] ?? [], $actualTicket, $canExposeNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($currentRows, $attemptedNextRows), static fn (array $row): bool => $row['visible_after_drain_ticket']));
        $heldRows = array_values(array_filter($attemptedNextRows, static fn (array $row): bool => !$row['visible_after_drain_ticket']));

        return [
            'status' => self::statusNext187($canExposeNext, $ticketAdmitted, $baseExposed),
            'base' => $base,
            'drain_ticket_prefix' => $prefix,
            'drain_ticket' => $actualTicket,
            'expected_drain_ticket' => $expectedTicket,
            'drain_ticket_matches' => $ticketMatches,
            'base_next_exposed_before_ticket' => $baseExposed,
            'next_source_exposed_after_drain_ticket' => $canExposeNext,
            'current_source_rows' => $currentRows,
            'attempted_next_source_rows' => $attemptedNextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'block_reasons' => $blockReasons,
            'ticket_plan' => [
                'prefix' => $prefix,
                'required_checkpoint_count' => count(self::tokenListNext187($base['required_current_checkpoints'] ?? [], 'required current checkpoints')),
                'acknowledged_checkpoint_count' => count(self::tokenListNext187($base['acknowledged_current_checkpoints'] ?? [], 'acknowledged current checkpoints')),
                'expected_ticket' => $expectedTicket,
                'actual_ticket' => $actualTicket,
                'ticket_matches' => $ticketMatches,
                'next_row_count' => count($attemptedNextRows),
                'held_next_row_count' => count($heldRows),
                'resume_after_token' => $currentRows === [] ? null : $currentRows[array_key_last($currentRows)]['resume_token'],
                'blocked_at_token' => $canExposeNext ? null : ($attemptedNextRows[0]['resume_token'] ?? null),
            ],
            'counts' => [
                'current_rows' => count($currentRows),
                'attempted_next_rows' => count($attemptedNextRows),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'block_reasons' => count($blockReasons),
            ],
            'yield_boundary' => $canExposeNext
                ? 'recursive-view-returning-current-source-next187-drain-ticket-next-exposed'
                : 'recursive-view-returning-current-source-next187-drain-ticket-held',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next187',
                'sqlite-returning-current-source-drain-ticket',
                'wordpress-recursive-view-returning-current-source-next187',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING checkpoint handoff and adds current-source drain ticket validation',
            'non_overlap' => 'adds drain-ticket validation after accepted next184 checkpoint acknowledgement handoff; avoids next183 rollback reset and next184 checkpoint exposure behavior',
        ];
    }

    /**
     * @param mixed $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function ticketRowsNext187(mixed $rows, string $ticket, bool $visible, array $blockReasons): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next187 rows are malformed');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'], $row['resume_token'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next187 row envelope is malformed');
            }
            $out[] = $row + [
                'drain_ticket' => $ticket,
                'visible_after_drain_ticket' => $visible,
                'held_by_drain_ticket_reasons' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockReasonsNext187(mixed $baseReasons, bool $ticketMatches, bool $baseExposed): array
    {
        $reasons = self::stringsNext187($baseReasons, 'base block reasons');
        if (!$ticketMatches) {
            $reasons[] = 'current-source-drain-ticket-mismatch';
        }
        if (!$baseExposed && $reasons === []) {
            $reasons[] = 'checkpoint-handoff-not-exposed';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext187(bool $canExposeNext, bool $ticketMatches, bool $baseExposed): string
    {
        if ($canExposeNext) {
            return 'trigger-recursive-view-returning-current-source-next187-next-exposed';
        }
        if (!$ticketMatches) {
            return 'trigger-recursive-view-returning-current-source-next187-drain-ticket-held';
        }
        if (!$baseExposed) {
            return 'trigger-recursive-view-returning-current-source-next187-checkpoint-handoff-held';
        }

        return 'trigger-recursive-view-returning-current-source-next187-next-held';
    }

    /**
     * @param mixed $tokens
     */
    private static function ticketNext187(string $prefix, mixed $tokens): string
    {
        $normalized = self::tokenListNext187($tokens, 'drain ticket checkpoint tokens');
        sort($normalized);

        return $prefix . ':' . substr(hash('sha256', implode('|', $normalized)), 0, 16);
    }

    /**
     * @param mixed $tokens
     * @return list<string>
     */
    private static function tokenListNext187(mixed $tokens, string $label): array
    {
        if (!is_array($tokens) || !array_is_list($tokens)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next187 {$label} must be a list");
        }

        return array_values(array_unique(array_map(static fn (mixed $token): string => self::tokenNext187((string) $token, $label), $tokens)));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringsNext187(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next187 {$label} must be a list");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function tokenNext187(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next187 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool,current_watermark?:string,expected_current_watermark?:string,acknowledged_current_ordinals?:list<int>,auto_ack_current_ordinals?:bool,require_contiguous_ordinals?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext188(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $watermark = self::tokenNext188((string) ($options['current_watermark'] ?? 'wp.recursive.view.current.watermark.188'), 'current watermark');
        $expectedWatermark = self::tokenNext188((string) ($options['expected_current_watermark'] ?? $watermark), 'expected current watermark');
        $requireContiguous = (bool) ($options['require_contiguous_ordinals'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext185(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $currentRows = self::rowsNext188($base['visible_current_returning_rows_next185'] ?? [], 'current rows');
        $baseNextRows = self::rowsNext188($base['visible_next_returning_rows_next185'] ?? [], 'next rows');
        $heldNextRows = self::rowsNext188($base['held_next_source_rows_next185'] ?? [], 'held next rows');
        $attemptedNextRows = self::dedupeRowsNext188(array_merge($baseNextRows, $heldNextRows));
        $requiredOrdinals = self::requiredOrdinalsNext188($currentRows);
        $acknowledgedOrdinals = self::acknowledgedOrdinalsNext188($options, $requiredOrdinals);
        $missingOrdinals = array_values(array_diff($requiredOrdinals, $acknowledgedOrdinals));
        $unexpectedOrdinals = array_values(array_diff($acknowledgedOrdinals, $requiredOrdinals));
        $contiguous = self::contiguousNext188($acknowledgedOrdinals, $requiredOrdinals);
        $watermarkMatches = hash_equals($watermark, $expectedWatermark);
        $basePublishAllowed = (bool) ($base['outer_publish_allowed_next185'] ?? false);
        $ordinalFenceClear = $missingOrdinals === [] && $unexpectedOrdinals === [] && (!$requireContiguous || $contiguous);
        $nextPublishAllowed = $basePublishAllowed && $watermarkMatches && $ordinalFenceClear;

        $taggedCurrentRows = self::tagRowsNext188($currentRows, 'current', true, $watermark, []);
        $blockedReasons = self::blockedReasonsNext188(
            $base['blocked_reasons_next185'] ?? [],
            $basePublishAllowed,
            $watermarkMatches,
            $missingOrdinals,
            $unexpectedOrdinals,
            $requireContiguous,
            $contiguous,
        );
        $taggedNextRows = self::tagRowsNext188($attemptedNextRows, 'next', $nextPublishAllowed, $watermark, $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrentRows, $taggedNextRows),
            static fn (array $row): bool => $row['visible_after_current_watermark_next188']
        ));
        $blockedNextRows = array_values(array_filter(
            $taggedNextRows,
            static fn (array $row): bool => !$row['visible_after_current_watermark_next188']
        ));

        return $base + [
            'status_next188' => self::statusNext188($basePublishAllowed, $watermarkMatches, $ordinalFenceClear, $nextPublishAllowed),
            'current_watermark_next188' => $watermark,
            'expected_current_watermark_next188' => $expectedWatermark,
            'current_watermark_matches_next188' => $watermarkMatches,
            'required_current_ordinals_next188' => $requiredOrdinals,
            'acknowledged_current_ordinals_next188' => $acknowledgedOrdinals,
            'missing_current_ordinals_next188' => $missingOrdinals,
            'unexpected_current_ordinals_next188' => $unexpectedOrdinals,
            'require_contiguous_ordinals_next188' => $requireContiguous,
            'current_ordinals_contiguous_next188' => $contiguous,
            'current_ordinal_fence_clear_next188' => $ordinalFenceClear,
            'next_source_publish_allowed_next188' => $nextPublishAllowed,
            'current_watermark_rows_next188' => $taggedCurrentRows,
            'attempted_next_watermark_rows_next188' => $taggedNextRows,
            'visible_returning_rows_next188' => $visibleRows,
            'blocked_next_source_rows_next188' => $blockedNextRows,
            'visible_returning_payloads_next188' => array_column($visibleRows, 'returning'),
            'blocked_next_returning_payloads_next188' => array_column($blockedNextRows, 'returning'),
            'current_watermark_row_count_next188' => count($taggedCurrentRows),
            'attempted_next_watermark_row_count_next188' => count($taggedNextRows),
            'visible_row_count_next188' => count($visibleRows),
            'blocked_next_row_count_next188' => count($blockedNextRows),
            'blocked_reasons_next188' => $blockedReasons,
            'watermark_plan_next188' => [
                'base_publish_allowed' => $basePublishAllowed,
                'watermark_matches' => $watermarkMatches,
                'required_ordinals' => $requiredOrdinals,
                'acknowledged_ordinals' => $acknowledgedOrdinals,
                'missing_ordinals' => $missingOrdinals,
                'unexpected_ordinals' => $unexpectedOrdinals,
                'require_contiguous_ordinals' => $requireContiguous,
                'ordinals_contiguous' => $contiguous,
                'ordinal_fence_clear' => $ordinalFenceClear,
                'next_source_publish_allowed' => $nextPublishAllowed,
                'decision' => $nextPublishAllowed ? 'publish-next-after-current-row-watermark' : 'hold-next-until-current-row-watermark',
            ],
            'yield_boundary_next188' => $nextPublishAllowed
                ? 'recursive-view-returning-next188-current-row-watermark-then-next'
                : 'recursive-view-returning-next188-current-row-watermark-fences-next',
            'dependencies_next188' => [
                'sqlite-trigger-recursive-view-returning-current-source-next188',
                'sqlite-returning-current-source-row-watermark-fence',
                'sqlite-returning-current-source-ordinal-contiguity',
                'wordpress-recursive-view-returning-current-source-next188',
            ],
            'dependency_closure_next188' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-nested-depth-drain-model',
            'non_overlap_next188' => 'adds row-ordinal current-source RETURNING watermark admission after next185 nested-depth drain; does not repeat next184 checkpoint acknowledgements, next182 generation fencing, row-value RETURNING, WAL, VFS, schema-reparse, or accepted trigger/FK cascade slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function requiredOrdinalsNext188(array $rows): array
    {
        $ordinals = [];
        foreach ($rows as $index => $row) {
            $ordinals[] = (int) ($row['returning_row_ordinal'] ?? $index);
        }

        sort($ordinals);

        return array_values(array_unique($ordinals));
    }

    /**
     * @param array<string,mixed> $options
     * @param list<int> $requiredOrdinals
     * @return list<int>
     */
    private static function acknowledgedOrdinalsNext188(array $options, array $requiredOrdinals): array
    {
        if (($options['auto_ack_current_ordinals'] ?? false) === true) {
            return $requiredOrdinals;
        }

        return self::ordinalListNext188($options['acknowledged_current_ordinals'] ?? [], 'acknowledged current ordinals');
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private static function ordinalListNext188(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} must be a list");
        }

        $out = [];
        foreach ($values as $value) {
            $ordinal = (int) $value;
            if ($ordinal < 0 || (string) $ordinal !== (string) $value) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} contain a malformed ordinal");
            }
            $out[] = $ordinal;
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $acknowledged
     * @param list<int> $required
     */
    private static function contiguousNext188(array $acknowledged, array $required): bool
    {
        if ($acknowledged === []) {
            return $required === [];
        }

        $expected = range((int) min($required), (int) max($required));

        return $acknowledged === $expected;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext188(array $rows, string $phase, bool $visible, string $watermark, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $ordinal = (int) ($row['returning_row_ordinal'] ?? $index);
            $out[] = $row + [
                'watermark_phase_next188' => $phase,
                'current_watermark_next188' => $watermark,
                'current_watermark_ordinal_next188' => $ordinal,
                'visible_after_current_watermark_next188' => $visible,
                'held_by_current_watermark_reasons_next188' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext188(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function dedupeRowsNext188(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = ($row['statement_source'] ?? '') . "\0" . ($row['returning_row_ordinal'] ?? '') . "\0" . ($row['returning_option_name'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<int> $missingOrdinals
     * @param list<int> $unexpectedOrdinals
     * @return list<string>
     */
    private static function blockedReasonsNext188(
        mixed $baseReasons,
        bool $basePublishAllowed,
        bool $watermarkMatches,
        array $missingOrdinals,
        array $unexpectedOrdinals,
        bool $requireContiguous,
        bool $contiguous,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next188 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next185-current-source-not-published';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'current-watermark-token-mismatch';
        }
        if ($missingOrdinals !== []) {
            $reasons[] = 'current-watermark-ordinal-missing';
        }
        if ($unexpectedOrdinals !== []) {
            $reasons[] = 'current-watermark-ordinal-unexpected';
        }
        if ($requireContiguous && !$contiguous) {
            $reasons[] = 'current-watermark-ordinal-gap';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext188(bool $basePublishAllowed, bool $watermarkMatches, bool $ordinalFenceClear, bool $nextPublishAllowed): string
    {
        if ($nextPublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-watermark-released-next188';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-watermark-base-held-next188';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-returning-current-source-watermark-token-held-next188';
        }
        if (!$ordinalFenceClear) {
            return 'trigger-recursive-view-returning-current-source-watermark-ordinal-held-next188';
        }

        return 'trigger-recursive-view-returning-current-source-watermark-held-next188';
    }

    private static function tokenNext188(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,current_source_token?:string,expected_current_source_token?:string,drain_ack_token?:string,expected_drain_ack_token?:string,rollback_current_source?:bool,rollback_token?:string,expected_rollback_token?:string,commit_current_source?:bool,reset_generation?:string,post_reset_current_source_token?:string,expected_post_reset_current_source_token?:string,post_reset_cursor?:string,post_reset_view?:array<string,mixed>,post_reset_input?:list<array<string,mixed>>,reuse_stale_returning_cursor?:bool,fresh_acknowledged_ordinals?:list<int>,next_source_token?:string,expected_next_source_token?:string,next_cursor?:string,expected_reset_generation?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext189(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext186(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $acknowledged = self::ordinalsNext189($options['fresh_acknowledged_ordinals'] ?? []);
        $nextToken = self::tokenNext189((string) ($options['next_source_token'] ?? 'wp.next.source.189'), 'next source token');
        $expectedNextToken = self::tokenNext189((string) ($options['expected_next_source_token'] ?? $nextToken), 'expected next source token');
        $nextCursor = self::tokenNext189((string) ($options['next_cursor'] ?? 'wp.returning.next.cursor.189'), 'next cursor');
        $expectedResetGeneration = self::tokenNext189((string) ($options['expected_reset_generation'] ?? ($base['post_reset_rebind_plan_next186']['reset_generation'] ?? '')), 'expected reset generation');
        $resetGenerationMatches = hash_equals($expectedResetGeneration, (string) ($base['post_reset_rebind_plan_next186']['reset_generation'] ?? ''));
        $tokenMatches = hash_equals($nextToken, $expectedNextToken);
        $freshRows = self::rowsNext189($base['fresh_returning_rows_next186'] ?? [], 'fresh rows');
        $freshOrdinals = array_column($freshRows, 'returning_row_ordinal');
        $currentRowsAcknowledged = $freshRows !== [] && self::acknowledgesAllFreshRowsNext189($freshOrdinals, $acknowledged);
        $canAdmitNext = $currentRowsAcknowledged && $tokenMatches && $resetGenerationMatches && $base['blocked_reasons_next186'] === [];
        $nextRows = $canAdmitNext
            ? self::nextRowsNext189($nextInput, self::viewNext189($nextView), $returning, $nextToken, $nextCursor, (string) ($options['next_generation'] ?? 'wp-next-returning-189'))
            : [];

        return [
            'status_next189' => self::statusNext189($canAdmitNext, $currentRowsAcknowledged, $tokenMatches, $resetGenerationMatches, $base),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'fresh_acknowledged_ordinals_next189' => $acknowledged,
            'fresh_required_ordinals_next189' => $freshOrdinals,
            'fresh_current_rows_acknowledged_next189' => $currentRowsAcknowledged,
            'next_source_token_next189' => $nextToken,
            'expected_next_source_token_next189' => $expectedNextToken,
            'next_source_token_matches_next189' => $tokenMatches,
            'expected_reset_generation_next189' => $expectedResetGeneration,
            'reset_generation_matches_next189' => $resetGenerationMatches,
            'next_cursor_next189' => $nextCursor,
            'next_source_rows_next189' => $nextRows,
            'next_source_payloads_next189' => array_column($nextRows, 'returning'),
            'next_source_row_count_next189' => count($nextRows),
            'blocked_reasons_next189' => self::blockedReasonsNext189($currentRowsAcknowledged, $tokenMatches, $resetGenerationMatches, $base),
            'handoff_plan_next189' => [
                'fresh_rows_required' => count($freshRows),
                'fresh_rows_acknowledged' => count(array_intersect($freshOrdinals, $acknowledged)),
                'next_rows_visible' => count($nextRows),
                'decision' => self::decisionNext189($canAdmitNext, $currentRowsAcknowledged, $tokenMatches, $resetGenerationMatches, $base),
                'resume_after_fresh_ordinal' => $freshRows === [] ? null : max($freshOrdinals),
                'next_cursor' => $nextCursor,
            ],
            'yield_boundary_next189' => $canAdmitNext
                ? 'recursive-view-returning-next189-current-rebound-rows-acked-next-source-visible'
                : 'recursive-view-returning-next189-current-rebound-rows-fence-next-source',
            'dependency_closure_next189' => 'no new support component needed; reuses next186 post-reset RETURNING rebinding and adds row-ack next-source admission fencing',
            'dependencies_next189' => array_values(array_unique(array_merge($base['dependencies_next186'], [
                'sqlite-trigger-recursive-view-returning-current-source-next189',
                'sqlite-returning-post-reset-row-ack-next-source-admission',
                'wordpress-recursive-view-returning-current-source-next189',
            ]))),
            'non_overlap_next189' => 'extends accepted next186 post-reset current-source rebinding by requiring fresh rebound RETURNING row acknowledgements before queued next-source recursive view rows are visible; avoids next171/176 cursor/page acknowledgement, next183 rollback invalidation, next186 stale cursor rebinding, DELETE RETURNING, UPSERT, row-value, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @param list<int> $required
     * @param list<int> $acknowledged
     */
    private static function acknowledgesAllFreshRowsNext189(array $required, array $acknowledged): bool
    {
        if ($required === []) {
            return false;
        }
        sort($required);
        sort($acknowledged);

        return $required === $acknowledged;
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function nextRowsNext189(array $input, array $view, array $returning, string $token, string $cursor, string $generation): array
    {
        $mapping = self::mappingNext189($view['mapping'] ?? []);
        $out = [];
        foreach (self::rowsNext189($input, 'next input') as $ordinal => $row) {
            $new = self::mappedRowNext189($row, $mapping);
            $out[] = [
                'statement_source' => 'next-source',
                'returning_row_ordinal' => $ordinal,
                'returning' => self::returningPayloadNext189($returning, $new, $view, $ordinal),
                'returning_option_name' => (string) ($new['option_name'] ?? $new['name'] ?? ''),
                'next_source_token_next189' => $token,
                'next_cursor_next189' => $cursor,
                'next_generation_next189' => $generation,
                'source_signature_next189' => self::signatureNext189($view, $returning),
            ];
        }

        return $out;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayloadNext189(array $returning, array $new, array $view, int $ordinal): array
    {
        $payload = [];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'next-source', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'] ?? null,
                'new.option_value' => $new['option_value'] ?? null,
                'old.option_value' => null,
                'event' => 'next-source',
                'depth' => 0,
                'ordinal' => $ordinal,
                'trigger_source' => (string) ($view['trigger_source'] ?? ''),
                'view.name' => (string) ($view['name'] ?? ''),
                default => $new[$expr] ?? null,
            };
        }

        return $payload;
    }

    /**
     * @param array<string,string> $mapping
     * @return array<string,mixed>
     */
    private static function mappedRowNext189(array $row, array $mapping): array
    {
        $mapped = $row;
        foreach ($mapping as $source => $target) {
            if (array_key_exists($source, $row)) {
                $mapped[$target] = $row[$source];
            }
        }

        return $mapped;
    }

    /**
     * @return array<string,string>
     */
    private static function mappingNext189(mixed $mapping): array
    {
        if (!is_array($mapping)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next189 view mapping is malformed');
        }
        $out = [];
        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target) || $source === '' || $target === '') {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next189 view mapping entry is malformed');
            }
            $out[$source] = $target;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewNext189(mixed $view): array
    {
        if (!is_array($view)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next189 view is malformed');
        }
        self::mappingNext189($view['mapping'] ?? []);

        return $view;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rowsNext189(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next189 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next189 {$label} row is malformed");
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function ordinalsNext189(mixed $ordinals): array
    {
        if (!is_array($ordinals) || !array_is_list($ordinals)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next189 acknowledged ordinals must be a list');
        }
        $out = [];
        foreach ($ordinals as $ordinal) {
            if (!is_int($ordinal) || $ordinal < 0) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next189 acknowledged ordinals must be non-negative integers');
            }
            $out[] = $ordinal;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function signatureNext189(array $view, array $returning): string
    {
        $aliases = [];
        foreach ($returning as $index => $term) {
            $aliases[] = is_array($term) ? (string) ($term['as'] ?? $term['expr'] ?? $index) : (is_string($term) ? $term : 'callable_' . $index);
        }

        return substr(hash('sha256', json_encode([
            'name' => (string) ($view['name'] ?? ''),
            'source' => (string) ($view['source'] ?? ''),
            'trigger' => (string) ($view['trigger'] ?? ''),
            'trigger_source' => (string) ($view['trigger_source'] ?? ''),
            'mapping' => (array) ($view['mapping'] ?? []),
            'returning' => $aliases,
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }

    private static function statusNext189(bool $canAdmitNext, bool $currentRowsAcknowledged, bool $tokenMatches, bool $resetGenerationMatches, array $base): string
    {
        if ($canAdmitNext) {
            return 'trigger-recursive-view-returning-current-source-next189-next-source-visible';
        }
        if ($base['blocked_reasons_next186'] !== []) {
            return 'trigger-recursive-view-returning-current-source-next189-post-reset-held';
        }
        if (!$currentRowsAcknowledged) {
            return 'trigger-recursive-view-returning-current-source-next189-awaiting-current-row-acks';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next189-next-token-held';
        }
        if (!$resetGenerationMatches) {
            return 'trigger-recursive-view-returning-current-source-next189-reset-generation-held';
        }

        return 'trigger-recursive-view-returning-current-source-next189-held';
    }

    /**
     * @return list<string>
     */
    private static function blockedReasonsNext189(bool $currentRowsAcknowledged, bool $tokenMatches, bool $resetGenerationMatches, array $base): array
    {
        $reasons = $base['blocked_reasons_next186'];
        if (!$currentRowsAcknowledged) {
            $reasons[] = 'fresh-current-returning-rows-not-acknowledged';
        }
        if (!$tokenMatches) {
            $reasons[] = 'next-source-token-mismatch';
        }
        if (!$resetGenerationMatches) {
            $reasons[] = 'reset-generation-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function decisionNext189(bool $canAdmitNext, bool $currentRowsAcknowledged, bool $tokenMatches, bool $resetGenerationMatches, array $base): string
    {
        if ($canAdmitNext) {
            return 'admit-next-source-after-post-reset-current-acks';
        }
        if ($base['blocked_reasons_next186'] !== []) {
            return 'hold-next-source-until-post-reset-current-rebind';
        }
        if (!$currentRowsAcknowledged) {
            return 'hold-next-source-until-fresh-current-returning-acks';
        }
        if (!$tokenMatches) {
            return 'hold-next-source-token';
        }
        if (!$resetGenerationMatches) {
            return 'hold-next-source-reset-generation';
        }

        return 'hold-next-source';
    }

    private static function tokenNext189(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next189 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,checkpoint_name?:string,commit_visible_checkpoints?:bool,handoff_token?:string,expected_handoff_token?:string,acknowledged_current_checkpoints?:list<string>,auto_ack_current?:bool,drain_ticket?:string,expected_drain_ticket?:string,drain_ticket_prefix?:string,resume_source_token?:string,expected_resume_source_token?:string,resume_source_prefix?:string,next_source_resume_token?:string,expected_next_source_resume_token?:string,source_signature?:string,expected_source_signature?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext190(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $baseOptions = $options + [
            'cursor_name' => 'wp_recursive_view_returning_cursor_190',
            'current_generation' => 'wp-current-returning-190',
            'next_generation' => 'wp-next-returning-190',
            'checkpoint_name' => 'wp_recursive_view_checkpoint_190',
            'handoff_token' => 'wp.returning.current.source.handoff.190',
            'savepoint' => 'wp_recursive_view_returning_next190',
            'drain_ticket_prefix' => 'wp.returning.current.source.drain.190',
        ];

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext187(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $currentRows = self::rowsNext190($base['current_source_rows'] ?? [], 'current source rows');
        $attemptedNextRows = self::rowsNext190($base['attempted_next_source_rows'] ?? [], 'attempted next source rows');
        $lastCurrentResume = $currentRows === [] ? null : (string) $currentRows[array_key_last($currentRows)]['resume_token'];
        $firstNextResume = $attemptedNextRows === [] ? null : (string) $attemptedNextRows[0]['resume_token'];
        $prefix = self::tokenNext190((string) ($options['resume_source_prefix'] ?? 'wp.returning.current.source.resume.190'), 'resume source prefix');
        $expectedResumeSource = self::tokenNext190(
            (string) ($options['expected_resume_source_token'] ?? self::resumeTokenNext190($prefix, $lastCurrentResume, $currentView, $returning)),
            'expected resume source token',
        );
        $actualResumeSource = self::tokenNext190(
            (string) ($options['resume_source_token'] ?? self::resumeTokenNext190($prefix, $lastCurrentResume, $currentView, $returning)),
            'resume source token',
        );
        $expectedNextResume = self::tokenNext190(
            (string) ($options['expected_next_source_resume_token'] ?? ($firstNextResume ?? 'no-next-source-row')),
            'expected next source resume token',
        );
        $actualNextResume = self::tokenNext190(
            (string) ($options['next_source_resume_token'] ?? ($firstNextResume ?? 'no-next-source-row')),
            'next source resume token',
        );
        $expectedSignature = self::signatureOptionNext190($options['expected_source_signature'] ?? self::sourceSignatureNext190($currentView, $returning), 'expected source signature');
        $actualSignature = self::signatureOptionNext190($options['source_signature'] ?? self::sourceSignatureNext190($currentView, $returning), 'source signature');

        $resumeMatches = hash_equals($expectedResumeSource, $actualResumeSource);
        $nextResumeMatches = hash_equals($expectedNextResume, $actualNextResume);
        $signatureMatches = hash_equals($expectedSignature, $actualSignature);
        $baseExposed = (bool) ($base['next_source_exposed_after_drain_ticket'] ?? false);
        $resumeAdmitsNext = $baseExposed && $resumeMatches && $nextResumeMatches && $signatureMatches;
        $blockReasons = self::blockReasonsNext190($base['block_reasons'] ?? [], $baseExposed, $resumeMatches, $nextResumeMatches, $signatureMatches);

        $gatedCurrentRows = self::resumeRowsNext190($currentRows, $actualResumeSource, true, []);
        $gatedNextRows = self::resumeRowsNext190($attemptedNextRows, $actualResumeSource, $resumeAdmitsNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($gatedCurrentRows, $gatedNextRows), static fn (array $row): bool => $row['visible_after_resume_source']));
        $heldRows = array_values(array_filter($gatedNextRows, static fn (array $row): bool => !$row['visible_after_resume_source']));

        return [
            'status' => self::statusNext190($resumeAdmitsNext, $baseExposed, $resumeMatches, $nextResumeMatches, $signatureMatches),
            'base' => $base,
            'resume_source_prefix' => $prefix,
            'resume_source_token' => $actualResumeSource,
            'expected_resume_source_token' => $expectedResumeSource,
            'resume_source_matches' => $resumeMatches,
            'next_source_resume_token' => $actualNextResume,
            'expected_next_source_resume_token' => $expectedNextResume,
            'next_source_resume_matches' => $nextResumeMatches,
            'source_signature' => $actualSignature,
            'expected_source_signature' => $expectedSignature,
            'source_signature_matches' => $signatureMatches,
            'last_current_resume_token' => $lastCurrentResume,
            'first_next_resume_token' => $firstNextResume,
            'base_next_exposed_before_resume_source' => $baseExposed,
            'next_source_exposed_after_resume_source' => $resumeAdmitsNext,
            'current_source_rows' => $gatedCurrentRows,
            'attempted_next_source_rows' => $gatedNextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'block_reasons' => $blockReasons,
            'resume_plan' => [
                'current_row_count' => count($gatedCurrentRows),
                'attempted_next_row_count' => count($gatedNextRows),
                'visible_row_count' => count($visibleRows),
                'held_next_row_count' => count($heldRows),
                'last_current_resume_token' => $lastCurrentResume,
                'first_next_resume_token' => $firstNextResume,
                'resume_source_token' => $actualResumeSource,
                'resume_source_matches' => $resumeMatches,
                'next_source_resume_matches' => $nextResumeMatches,
                'source_signature_matches' => $signatureMatches,
                'decision' => $resumeAdmitsNext ? 'admit-next-source-returning' : 'hold-next-source-returning',
                'blocked_at_token' => $resumeAdmitsNext ? null : $firstNextResume,
            ],
            'counts' => [
                'current_rows' => count($gatedCurrentRows),
                'attempted_next_rows' => count($gatedNextRows),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'block_reasons' => count($blockReasons),
            ],
            'yield_boundary' => $resumeAdmitsNext
                ? 'recursive-view-returning-current-source-next190-resume-source-next-exposed'
                : 'recursive-view-returning-current-source-next190-resume-source-held',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next190',
                'sqlite-returning-current-source-resume-token',
                'wordpress-recursive-view-returning-current-source-next190',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING drain-ticket rows and adds current-source resume token validation',
            'non_overlap' => 'adds resume-source token and source-signature validation after accepted next187 drain-ticket exposure; avoids next184 checkpoint admission, next186 post-reset rebinding, next187 drain-ticket matching, row-value RETURNING, WAL, pager, B-tree, JSON, and encoding slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function resumeRowsNext190(array $rows, string $resumeSource, bool $visible, array $blockReasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'resume_source_token_next190' => $resumeSource,
                'visible_after_resume_source' => $visible,
                'held_by_resume_source_reasons' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext190(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next190 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'], $row['resume_token'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next190 {$label} row envelope is malformed");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockReasonsNext190(mixed $baseReasons, bool $baseExposed, bool $resumeMatches, bool $nextResumeMatches, bool $signatureMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next190 base block reasons must be a list');
        }

        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseExposed && $reasons === []) {
            $reasons[] = 'drain-ticket-not-exposed';
        }
        if (!$resumeMatches) {
            $reasons[] = 'current-source-resume-token-mismatch';
        }
        if (!$nextResumeMatches) {
            $reasons[] = 'next-source-resume-token-mismatch';
        }
        if (!$signatureMatches) {
            $reasons[] = 'current-source-signature-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext190(bool $admitted, bool $baseExposed, bool $resumeMatches, bool $nextResumeMatches, bool $signatureMatches): string
    {
        if ($admitted) {
            return 'trigger-recursive-view-returning-current-source-next190-next-exposed';
        }
        if (!$baseExposed) {
            return 'trigger-recursive-view-returning-current-source-next190-drain-ticket-held';
        }
        if (!$resumeMatches) {
            return 'trigger-recursive-view-returning-current-source-next190-resume-token-held';
        }
        if (!$nextResumeMatches) {
            return 'trigger-recursive-view-returning-current-source-next190-next-resume-held';
        }
        if (!$signatureMatches) {
            return 'trigger-recursive-view-returning-current-source-next190-source-signature-held';
        }

        return 'trigger-recursive-view-returning-current-source-next190-held';
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function resumeTokenNext190(string $prefix, ?string $lastCurrentResume, array $view, array $returning): string
    {
        $material = ($lastCurrentResume ?? 'no-current-row') . '|' . self::sourceSignatureNext190($view, $returning);

        return $prefix . ':' . substr(hash('sha256', $material), 0, 16);
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function sourceSignatureNext190(array $view, array $returning): string
    {
        $parts = [
            (string) ($view['name'] ?? ''),
            (string) ($view['source'] ?? ''),
            (string) ($view['trigger'] ?? ''),
            (string) ($view['trigger_source'] ?? ''),
        ];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $parts[] = 'callable';
                continue;
            }
            $parts[] = is_array($term) ? (string) ($term['expr'] ?? '') . ':' . (string) ($term['as'] ?? '') : (string) $term;
        }

        return 'sig190:' . substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    private static function signatureOptionNext190(mixed $value, string $label): string
    {
        $string = (string) $value;
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $string) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next190 {$label} is malformed");
        }

        return $string;
    }

    private static function tokenNext190(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next190 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool,current_watermark?:string,expected_current_watermark?:string,acknowledged_current_ordinals?:list<int>,auto_ack_current_ordinals?:bool,require_contiguous_ordinals?:bool,fingerprint_salt?:string,expected_fingerprint_salt?:string,acknowledged_current_fingerprints?:list<string>,auto_ack_current_fingerprints?:bool,require_fingerprint_order?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext191(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $salt = self::tokenNext191((string) ($options['fingerprint_salt'] ?? 'wp.recursive.view.returning.fingerprint.191'), 'fingerprint salt');
        $expectedSalt = self::tokenNext191((string) ($options['expected_fingerprint_salt'] ?? $salt), 'expected fingerprint salt');
        $requireOrder = (bool) ($options['require_fingerprint_order'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext188(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'savepoint_action' => 'release',
                'auto_ack_current_ordinals' => true,
            ],
        );

        $currentRows = self::rowsNext191($base['current_watermark_rows_next188'] ?? [], 'current rows');
        $nextRows = self::dedupeRowsNext191(array_merge(
            self::rowsNext191($base['attempted_next_watermark_rows_next188'] ?? [], 'attempted next rows'),
            self::rowsNext191($base['blocked_next_source_rows_next188'] ?? [], 'blocked next rows'),
        ));
        $required = self::fingerprintsNext191($currentRows, $salt);
        $acknowledged = self::acknowledgedFingerprintsNext191($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || self::orderedNext191($required, $acknowledged);
        $saltMatches = hash_equals($salt, $expectedSalt);
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next188'] ?? false);
        $fingerprintFenceClear = $missing === [] && $unexpected === [] && $orderMatches;
        $publishNext = $basePublishAllowed && $saltMatches && $fingerprintFenceClear;
        $blockedReasons = self::blockedReasonsNext191(
            $base['blocked_reasons_next188'] ?? [],
            $basePublishAllowed,
            $saltMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagRowsNext191($currentRows, 'current', true, $salt, $required, []);
        $nextTagged = self::tagRowsNext191($nextRows, 'next', $publishNext, $salt, [], $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentTagged, $nextTagged),
            static fn (array $row): bool => $row['visible_after_current_fingerprint_next191']
        ));
        $heldRows = array_values(array_filter(
            $nextTagged,
            static fn (array $row): bool => !$row['visible_after_current_fingerprint_next191']
        ));

        return $base + [
            'status_next191' => self::statusNext191($basePublishAllowed, $saltMatches, $fingerprintFenceClear, $publishNext),
            'fingerprint_salt_next191' => $salt,
            'expected_fingerprint_salt_next191' => $expectedSalt,
            'fingerprint_salt_matches_next191' => $saltMatches,
            'required_current_fingerprints_next191' => $required,
            'acknowledged_current_fingerprints_next191' => $acknowledged,
            'missing_current_fingerprints_next191' => $missing,
            'unexpected_current_fingerprints_next191' => $unexpected,
            'require_fingerprint_order_next191' => $requireOrder,
            'current_fingerprint_order_matches_next191' => $orderMatches,
            'current_fingerprint_fence_clear_next191' => $fingerprintFenceClear,
            'next_source_publish_allowed_next191' => $publishNext,
            'current_fingerprint_rows_next191' => $currentTagged,
            'attempted_next_fingerprint_rows_next191' => $nextTagged,
            'visible_returning_rows_next191' => $visibleRows,
            'held_next_source_rows_next191' => $heldRows,
            'visible_returning_payloads_next191' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next191' => array_column($heldRows, 'returning'),
            'current_fingerprint_row_count_next191' => count($currentTagged),
            'attempted_next_fingerprint_row_count_next191' => count($nextTagged),
            'visible_row_count_next191' => count($visibleRows),
            'held_next_row_count_next191' => count($heldRows),
            'blocked_reasons_next191' => $blockedReasons,
            'fingerprint_plan_next191' => [
                'base_publish_allowed' => $basePublishAllowed,
                'salt_matches' => $saltMatches,
                'required_fingerprints' => $required,
                'acknowledged_fingerprints' => $acknowledged,
                'missing_fingerprints' => $missing,
                'unexpected_fingerprints' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'fingerprint_fence_clear' => $fingerprintFenceClear,
                'next_source_publish_allowed' => $publishNext,
                'decision' => $publishNext ? 'publish-next-after-current-row-fingerprints' : 'hold-next-until-current-row-fingerprints',
            ],
            'yield_boundary_next191' => $publishNext
                ? 'recursive-view-returning-next191-current-fingerprints-then-next'
                : 'recursive-view-returning-next191-current-fingerprints-fence-next',
            'dependencies_next191' => [
                'sqlite-trigger-recursive-view-returning-current-source-next191',
                'sqlite-returning-current-source-row-fingerprint-fence',
                'sqlite-returning-current-source-payload-order',
                'wordpress-recursive-view-returning-current-source-next191',
            ],
            'dependency_closure_next191' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-row-watermark-and-payload-fingerprint-model',
            'non_overlap_next191' => 'adds payload/source fingerprint admission after next188 row-ordinal watermarks; does not repeat next188 ordinal fencing, next185 nested-depth drain, next184 checkpoint acknowledgements, row-value RETURNING, WAL, VFS, schema-reparse, or trigger/FK cascade slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function fingerprintsNext191(array $rows, string $salt): array
    {
        $fingerprints = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'] ?? [];
            ksort($payload);
            $parts = [
                $salt,
                (string) ($row['statement_source'] ?? 'current'),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ($payload['option_name'] ?? '')),
                (string) ($row['returning_current_source_generation'] ?? ''),
                (string) ($row['returning_trigger_source_generation'] ?? ''),
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
            $fingerprints[] = substr(hash('sha256', implode('|', $parts)), 0, 24);
        }

        return $fingerprints;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedFingerprintsNext191(array $options, array $required): array
    {
        if (($options['auto_ack_current_fingerprints'] ?? false) === true) {
            return $required;
        }

        return self::fingerprintListNext191($options['acknowledged_current_fingerprints'] ?? [], 'acknowledged current fingerprints');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function fingerprintListNext191(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{24}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} contain a malformed fingerprint");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     */
    private static function orderedNext191(array $required, array $acknowledged): bool
    {
        return $required === $acknowledged;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $fingerprints
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext191(array $rows, string $phase, bool $visible, string $salt, array $fingerprints, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'fingerprint_phase_next191' => $phase,
                'fingerprint_salt_next191' => $salt,
                'current_row_fingerprint_next191' => $fingerprints[$index] ?? null,
                'visible_after_current_fingerprint_next191' => $visible,
                'held_by_current_fingerprint_reasons_next191' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext191(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function dedupeRowsNext191(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = ($row['statement_source'] ?? '') . "\0" . ($row['returning_row_ordinal'] ?? '') . "\0" . ($row['returning_option_name'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext191(
        mixed $baseReasons,
        bool $basePublishAllowed,
        bool $saltMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next191 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next188-current-source-not-published';
        }
        if (!$saltMatches) {
            $reasons[] = 'current-fingerprint-salt-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-fingerprint-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-fingerprint-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-fingerprint-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext191(bool $basePublishAllowed, bool $saltMatches, bool $fingerprintFenceClear, bool $publishNext): string
    {
        if ($publishNext) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-released-next191';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-base-held-next191';
        }
        if (!$saltMatches) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-salt-held-next191';
        }
        if (!$fingerprintFenceClear) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-held-next191';
        }

        return 'trigger-recursive-view-returning-current-source-fingerprints-pending-next191';
    }

    private static function tokenNext191(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext192(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext189(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $nextRows = self::rowsNext192($base['next_source_rows_next189'] ?? [], 'next source rows');
        $requiredOrdinals = array_column($nextRows, 'returning_row_ordinal');
        $acknowledgedOrdinals = self::ordinalsNext192($options['next_acknowledged_ordinals'] ?? []);
        $nextRowsAcknowledged = $nextRows !== [] && self::sameOrdinalsNext192($requiredOrdinals, $acknowledgedOrdinals);
        $nextCursor = self::tokenNext192((string) ($options['next_cursor'] ?? ($base['next_cursor_next189'] ?? 'wp.returning.next.cursor.192')), 'next cursor');
        $closeCursor = self::tokenNext192((string) ($options['close_next_cursor'] ?? $nextCursor), 'close next cursor');
        $cursorMatches = hash_equals($nextCursor, $closeCursor);
        $followingToken = self::tokenNext192((string) ($options['following_current_source_token'] ?? 'wp.current.source.following.192'), 'following current source token');
        $expectedFollowingToken = self::tokenNext192((string) ($options['expected_following_current_source_token'] ?? $followingToken), 'expected following current source token');
        $followingTokenMatches = hash_equals($followingToken, $expectedFollowingToken);
        $followingCursor = self::tokenNext192((string) ($options['following_cursor'] ?? 'wp.returning.following.cursor.192'), 'following cursor');
        $baseAdmittedNext = ($base['status_next189'] ?? '') === 'trigger-recursive-view-returning-current-source-next189-next-source-visible';
        $canAdmitFollowing = $baseAdmittedNext && $nextRowsAcknowledged && $cursorMatches && $followingTokenMatches;
        $followingRows = $canAdmitFollowing
            ? self::followingRowsNext192(
                self::rowsNext192($options['following_current_input'] ?? [], 'following current input'),
                self::viewNext192($options['following_current_view'] ?? $currentView),
                $returning,
                $followingToken,
                $followingCursor,
                (string) ($options['following_generation'] ?? 'wp-following-current-192'),
            )
            : [];

        return [
            'status_next192' => self::statusNext192($canAdmitFollowing, $baseAdmittedNext, $nextRowsAcknowledged, $cursorMatches, $followingTokenMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'next_required_ordinals_next192' => $requiredOrdinals,
            'next_acknowledged_ordinals_next192' => $acknowledgedOrdinals,
            'next_source_rows_acknowledged_next192' => $nextRowsAcknowledged,
            'next_cursor_next192' => $nextCursor,
            'close_next_cursor_next192' => $closeCursor,
            'next_cursor_close_matches_next192' => $cursorMatches,
            'following_current_source_token_next192' => $followingToken,
            'expected_following_current_source_token_next192' => $expectedFollowingToken,
            'following_current_source_token_matches_next192' => $followingTokenMatches,
            'following_cursor_next192' => $followingCursor,
            'following_current_rows_next192' => $followingRows,
            'following_current_payloads_next192' => array_column($followingRows, 'returning'),
            'following_current_row_count_next192' => count($followingRows),
            'blocked_reasons_next192' => self::blockedReasonsNext192($base, $baseAdmittedNext, $nextRowsAcknowledged, $cursorMatches, $followingTokenMatches),
            'cursor_close_plan_next192' => [
                'next_rows_required' => count($nextRows),
                'next_rows_acknowledged' => count(array_intersect($requiredOrdinals, $acknowledgedOrdinals)),
                'next_cursor_matches_close_token' => $cursorMatches,
                'following_rows_visible' => count($followingRows),
                'decision' => self::decisionNext192($canAdmitFollowing, $baseAdmittedNext, $nextRowsAcknowledged, $cursorMatches, $followingTokenMatches),
                'resume_after_next_ordinal' => $nextRows === [] ? null : max($requiredOrdinals),
                'following_cursor' => $followingCursor,
            ],
            'yield_boundary_next192' => $canAdmitFollowing
                ? 'recursive-view-returning-next192-next-cursor-drained-following-current-visible'
                : 'recursive-view-returning-next192-next-cursor-fences-following-current',
            'dependency_closure_next192' => 'no new support component needed; reuses next189 next-source admission and adds next-cursor close fencing for the following current source',
            'dependencies_next192' => array_values(array_unique(array_merge($base['dependencies_next189'], [
                'sqlite-trigger-recursive-view-returning-current-source-next192',
                'sqlite-returning-next-cursor-close-following-current-source-admission',
                'wordpress-recursive-view-returning-current-source-next192',
            ]))),
            'non_overlap_next192' => 'extends accepted next189 row-ack next-source admission with the later next-source cursor-close barrier before a following current-source generation; avoids next183 rollback invalidation, next186 post-reset rebind, next189 current-row acknowledgements, row-value, UPSERT, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @param list<int> $required
     * @param list<int> $acknowledged
     */
    private static function sameOrdinalsNext192(array $required, array $acknowledged): bool
    {
        sort($required);
        sort($acknowledged);

        return $required === $acknowledged;
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function followingRowsNext192(array $input, array $view, array $returning, string $token, string $cursor, string $generation): array
    {
        $mapping = self::mappingNext192($view['mapping'] ?? []);
        $out = [];
        foreach ($input as $ordinal => $row) {
            $new = self::mappedRowNext192($row, $mapping);
            $out[] = [
                'statement_source' => 'following-current',
                'returning_row_ordinal' => $ordinal,
                'returning' => self::returningPayloadNext192($returning, $new, $view, $ordinal),
                'returning_option_name' => (string) ($new['option_name'] ?? $new['name'] ?? ''),
                'following_current_source_token_next192' => $token,
                'following_cursor_next192' => $cursor,
                'following_generation_next192' => $generation,
                'source_signature_next192' => self::signatureNext192($view, $returning),
            ];
        }

        return $out;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayloadNext192(array $returning, array $new, array $view, int $ordinal): array
    {
        $payload = [];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'following-current', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'] ?? null,
                'new.option_value' => $new['option_value'] ?? null,
                'old.option_value' => null,
                'event' => 'following-current',
                'depth' => 0,
                'ordinal' => $ordinal,
                'trigger_source' => (string) ($view['trigger_source'] ?? ''),
                'view.name' => (string) ($view['name'] ?? ''),
                default => $new[$expr] ?? null,
            };
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private static function mappedRowNext192(array $row, array $mapping): array
    {
        $mapped = $row;
        foreach ($mapping as $source => $target) {
            if (array_key_exists($source, $row)) {
                $mapped[$target] = $row[$source];
            }
        }

        return $mapped;
    }

    /**
     * @return array<string,string>
     */
    private static function mappingNext192(mixed $mapping): array
    {
        if (!is_array($mapping)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next192 view mapping is malformed');
        }
        $out = [];
        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target) || $source === '' || $target === '') {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next192 view mapping entry is malformed');
            }
            $out[$source] = $target;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewNext192(mixed $view): array
    {
        if (!is_array($view)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next192 following view is malformed');
        }
        self::mappingNext192($view['mapping'] ?? []);

        return $view;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rowsNext192(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next192 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next192 {$label} row is malformed");
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function ordinalsNext192(mixed $ordinals): array
    {
        if (!is_array($ordinals) || !array_is_list($ordinals)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next192 acknowledged ordinals must be a list');
        }
        $out = [];
        foreach ($ordinals as $ordinal) {
            if (!is_int($ordinal) || $ordinal < 0) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next192 acknowledged ordinals must be non-negative integers');
            }
            $out[] = $ordinal;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function signatureNext192(array $view, array $returning): string
    {
        $aliases = [];
        foreach ($returning as $index => $term) {
            $aliases[] = is_array($term) ? (string) ($term['as'] ?? $term['expr'] ?? $index) : (is_string($term) ? $term : 'callable_' . $index);
        }

        return substr(hash('sha256', json_encode([
            'name' => (string) ($view['name'] ?? ''),
            'source' => (string) ($view['source'] ?? ''),
            'trigger' => (string) ($view['trigger'] ?? ''),
            'trigger_source' => (string) ($view['trigger_source'] ?? ''),
            'mapping' => (array) ($view['mapping'] ?? []),
            'returning' => $aliases,
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @return list<string>
     */
    private static function blockedReasonsNext192(array $base, bool $baseAdmittedNext, bool $nextRowsAcknowledged, bool $cursorMatches, bool $followingTokenMatches): array
    {
        $reasons = [];
        if (!$baseAdmittedNext) {
            $reasons = array_merge($reasons, $base['blocked_reasons_next189'] ?? ['next-source-not-visible']);
        }
        if (!$nextRowsAcknowledged) {
            $reasons[] = 'next-source-returning-rows-not-acknowledged';
        }
        if (!$cursorMatches) {
            $reasons[] = 'next-cursor-close-token-mismatch';
        }
        if (!$followingTokenMatches) {
            $reasons[] = 'following-current-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function decisionNext192(bool $canAdmitFollowing, bool $baseAdmittedNext, bool $nextRowsAcknowledged, bool $cursorMatches, bool $followingTokenMatches): string
    {
        if ($canAdmitFollowing) {
            return 'admit-following-current-after-next-cursor-close';
        }
        if (!$baseAdmittedNext) {
            return 'hold-following-current-until-next-source-visible';
        }
        if (!$nextRowsAcknowledged) {
            return 'hold-following-current-until-next-returning-acks';
        }
        if (!$cursorMatches) {
            return 'hold-following-current-next-cursor-close-token';
        }
        if (!$followingTokenMatches) {
            return 'hold-following-current-source-token';
        }

        return 'hold-following-current';
    }

    private static function statusNext192(bool $canAdmitFollowing, bool $baseAdmittedNext, bool $nextRowsAcknowledged, bool $cursorMatches, bool $followingTokenMatches): string
    {
        if ($canAdmitFollowing) {
            return 'trigger-recursive-view-returning-current-source-next192-following-current-visible';
        }
        if (!$baseAdmittedNext) {
            return 'trigger-recursive-view-returning-current-source-next192-next-source-held';
        }
        if (!$nextRowsAcknowledged) {
            return 'trigger-recursive-view-returning-current-source-next192-awaiting-next-row-acks';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next192-next-cursor-held';
        }
        if (!$followingTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next192-following-token-held';
        }

        return 'trigger-recursive-view-returning-current-source-next192-held';
    }

    private static function tokenNext192(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next192 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext193(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext189(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $nextRows = self::rowsNext193($base['next_source_rows_next189'] ?? [], 'next source rows');
        $handoffToken = self::tokenNext193((string) ($options['handoff_token'] ?? 'wp.recursive.view.returning.handoff.193'), 'handoff token');
        $expectedHandoffToken = self::tokenNext193((string) ($options['expected_handoff_token'] ?? $handoffToken), 'expected handoff token');
        $sequenceToken = self::tokenNext193((string) ($options['source_sequence_token'] ?? self::sequenceTokenNext193($nextRows)), 'source sequence token');
        $expectedSequenceToken = self::tokenNext193((string) ($options['expected_source_sequence_token'] ?? self::sequenceTokenNext193($nextRows)), 'expected source sequence token');
        $expectedRows = self::nonNegativeIntNext193($options['expected_next_row_count'] ?? count($nextRows), 'expected next row count');
        $expectedSignature = (string) ($options['expected_next_source_signature'] ?? self::sourceSignatureNext193($nextRows));
        if ($expectedSignature === '' || preg_match('/\s/', $expectedSignature) === 1) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next193 expected signature is malformed');
        }

        $baseAdmitted = $base['blocked_reasons_next189'] === [] && $nextRows !== [];
        $rowCountMatches = count($nextRows) === $expectedRows;
        $signatureMatches = hash_equals($expectedSignature, self::sourceSignatureNext193($nextRows));
        $handoffTokenMatches = hash_equals($handoffToken, $expectedHandoffToken);
        $sequenceMatches = hash_equals($sequenceToken, $expectedSequenceToken);
        $canPublish = $baseAdmitted && $rowCountMatches && $signatureMatches && $handoffTokenMatches && $sequenceMatches;
        $publishedRows = $canPublish ? self::publishRowsNext193($nextRows, $handoffToken, $sequenceToken) : [];

        return [
            'status_next193' => self::statusNext193($canPublish, $baseAdmitted, $rowCountMatches, $signatureMatches, $handoffTokenMatches, $sequenceMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'handoff_token_next193' => $handoffToken,
            'expected_handoff_token_next193' => $expectedHandoffToken,
            'handoff_token_matches_next193' => $handoffTokenMatches,
            'source_sequence_token_next193' => $sequenceToken,
            'expected_source_sequence_token_next193' => $expectedSequenceToken,
            'source_sequence_matches_next193' => $sequenceMatches,
            'next_source_signature_next193' => self::sourceSignatureNext193($nextRows),
            'expected_next_source_signature_next193' => $expectedSignature,
            'next_source_signature_matches_next193' => $signatureMatches,
            'expected_next_row_count_next193' => $expectedRows,
            'next_row_count_matches_next193' => $rowCountMatches,
            'base_next_source_admitted_next193' => $baseAdmitted,
            'published_next_source_rows_next193' => $publishedRows,
            'published_next_source_payloads_next193' => array_column($publishedRows, 'returning'),
            'published_next_source_row_count_next193' => count($publishedRows),
            'blocked_reasons_next193' => self::blockedReasonsNext193($baseAdmitted, $rowCountMatches, $signatureMatches, $handoffTokenMatches, $sequenceMatches, $base),
            'current_source_returning_handoff_next193' => [
                'fresh_current_rows_required' => $base['handoff_plan_next189']['fresh_rows_required'] ?? 0,
                'fresh_current_rows_acknowledged' => $base['handoff_plan_next189']['fresh_rows_acknowledged'] ?? 0,
                'candidate_next_rows' => count($nextRows),
                'published_next_rows' => count($publishedRows),
                'source_signature' => self::sourceSignatureNext193($nextRows),
                'source_sequence_token' => $sequenceToken,
                'decision' => self::decisionNext193($canPublish, $baseAdmitted, $rowCountMatches, $signatureMatches, $handoffTokenMatches, $sequenceMatches),
            ],
            'yield_boundary_next193' => $canPublish
                ? 'recursive-view-returning-next193-next-source-sealed-after-current-drain'
                : 'recursive-view-returning-next193-next-source-quarantined',
            'dependency_closure_next193' => 'no new support component needed; reuses next189 current-row acknowledgements and adds source-signature handoff sealing',
            'dependencies_next193' => array_values(array_unique(array_merge($base['dependencies_next189'], [
                'sqlite-trigger-recursive-view-returning-current-source-next193',
                'sqlite-returning-current-source-handoff-seal',
                'wordpress-recursive-view-returning-current-source-next193',
            ]))),
            'non_overlap_next193' => 'extends accepted next189 row-ack next-source admission by sealing the admitted source with signature, row-count, handoff-token, and sequence-token checks before publication; avoids next181 checkpoint visibility, next186 rebind, next189 row acknowledgement, row-value RETURNING, UPSERT, deferred FK, schema reparse, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function publishRowsNext193(array $rows, string $handoffToken, string $sequenceToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'published_next193' => true,
                'publish_ordinal_next193' => $index,
                'handoff_token_next193' => $handoffToken,
                'source_sequence_token_next193' => $sequenceToken,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sourceSignatureNext193(array $rows): string
    {
        if ($rows === []) {
            return 'empty-next-source';
        }

        $signatures = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['source_signature_next189'] ?? ''),
            $rows,
        )));
        sort($signatures);

        return substr(hash('sha256', json_encode([
            'signatures' => $signatures,
            'names' => array_column($rows, 'returning_option_name'),
            'count' => count($rows),
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sequenceTokenNext193(array $rows): string
    {
        if ($rows === []) {
            return 'empty-next-source';
        }

        return 'seq-' . substr(hash('sha256', json_encode([
            'ordinals' => array_column($rows, 'returning_row_ordinal'),
            'names' => array_column($rows, 'returning_option_name'),
        ], JSON_THROW_ON_ERROR)), 0, 12);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rowsNext193(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} row is malformed");
            }
        }

        return $rows;
    }

    private static function nonNegativeIntNext193(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function tokenNext193(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} is malformed");
        }

        return $token;
    }

    private static function statusNext193(bool $canPublish, bool $baseAdmitted, bool $rowCountMatches, bool $signatureMatches, bool $handoffTokenMatches, bool $sequenceMatches): string
    {
        if ($canPublish) {
            return 'trigger-recursive-view-returning-current-source-next193-published';
        }
        if (!$baseAdmitted) {
            return 'trigger-recursive-view-returning-current-source-next193-awaiting-next189';
        }
        if (!$rowCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-row-count-held';
        }
        if (!$signatureMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-signature-held';
        }
        if (!$handoffTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-handoff-token-held';
        }
        if (!$sequenceMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-sequence-held';
        }

        return 'trigger-recursive-view-returning-current-source-next193-held';
    }

    /**
     * @return list<string>
     */
    private static function blockedReasonsNext193(bool $baseAdmitted, bool $rowCountMatches, bool $signatureMatches, bool $handoffTokenMatches, bool $sequenceMatches, array $base): array
    {
        $reasons = $base['blocked_reasons_next189'];
        if (!$baseAdmitted) {
            $reasons[] = 'next189-next-source-not-admitted';
        }
        if (!$rowCountMatches) {
            $reasons[] = 'next-source-row-count-mismatch';
        }
        if (!$signatureMatches) {
            $reasons[] = 'next-source-signature-mismatch';
        }
        if (!$handoffTokenMatches) {
            $reasons[] = 'handoff-token-mismatch';
        }
        if (!$sequenceMatches) {
            $reasons[] = 'source-sequence-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function decisionNext193(bool $canPublish, bool $baseAdmitted, bool $rowCountMatches, bool $signatureMatches, bool $handoffTokenMatches, bool $sequenceMatches): string
    {
        if ($canPublish) {
            return 'publish-sealed-next-source-after-current-drain';
        }
        if (!$baseAdmitted) {
            return 'hold-next-source-until-next189-admission';
        }
        if (!$rowCountMatches) {
            return 'hold-next-source-row-count';
        }
        if (!$signatureMatches) {
            return 'hold-next-source-signature';
        }
        if (!$handoffTokenMatches) {
            return 'hold-next-source-handoff-token';
        }
        if (!$sequenceMatches) {
            return 'hold-next-source-sequence-token';
        }

        return 'hold-next-source';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceDoneGate(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext190(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'admit_next_source' => true,
                'auto_ack_current' => true,
                'cursor_name' => 'wp_recursive_view_returning_cursor_194',
                'current_generation' => 'wp-current-returning-194',
                'next_generation' => 'wp-next-returning-194',
                'checkpoint_name' => 'wp_recursive_view_checkpoint_194',
                'handoff_token' => 'wp.returning.current.source.handoff.194',
                'savepoint' => 'wp_recursive_view_returning_next194',
                'drain_ticket_prefix' => 'wp.returning.current.source.drain.194',
                'resume_source_prefix' => 'wp.returning.current.source.resume.194',
            ],
        );

        $currentDoneCode = self::currentSourceDoneResultCode((string) ($options['current_result_code'] ?? 'SQLITE_DONE'), 'current result code');
        $expectedDoneCode = self::currentSourceDoneResultCode((string) ($options['expected_current_result_code'] ?? 'SQLITE_DONE'), 'expected current result code');
        $currentCookie = self::currentSourceDoneToken((string) ($options['current_source_cookie'] ?? self::currentSourceDoneCookie($currentView, $returning)), 'current source cookie');
        $expectedCookie = self::currentSourceDoneToken((string) ($options['expected_current_source_cookie'] ?? self::currentSourceDoneCookie($currentView, $returning)), 'expected current source cookie');
        $stepEpoch = self::currentSourceDoneToken((string) ($options['current_step_epoch'] ?? self::currentSourceDoneStepEpoch($base)), 'current step epoch');
        $expectedStepEpoch = self::currentSourceDoneToken((string) ($options['expected_current_step_epoch'] ?? self::currentSourceDoneStepEpoch($base)), 'expected current step epoch');

        $doneMatches = hash_equals($expectedDoneCode, $currentDoneCode) && $currentDoneCode === 'SQLITE_DONE';
        $cookieMatches = hash_equals($expectedCookie, $currentCookie);
        $epochMatches = hash_equals($expectedStepEpoch, $stepEpoch);
        $baseExposed = (bool) ($base['next_source_exposed_after_resume_source'] ?? false);
        $admitNext = $baseExposed && $doneMatches && $cookieMatches && $epochMatches;
        $blockReasons = self::currentSourceDoneBlockReasons($base['block_reasons'] ?? [], $baseExposed, $doneMatches, $cookieMatches, $epochMatches);

        $currentRows = self::currentSourceDoneRows($base['current_source_rows'] ?? [], 'current source rows');
        $nextRows = self::currentSourceDoneRows($base['attempted_next_source_rows'] ?? [], 'attempted next source rows');
        $gatedCurrentRows = self::tagCurrentSourceDoneRows($currentRows, $currentDoneCode, $currentCookie, $stepEpoch, true, []);
        $gatedNextRows = self::tagCurrentSourceDoneRows($nextRows, $currentDoneCode, $currentCookie, $stepEpoch, $admitNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($gatedCurrentRows, $gatedNextRows), static fn (array $row): bool => $row['visible_after_current_done_next194']));
        $heldRows = array_values(array_filter($gatedNextRows, static fn (array $row): bool => !$row['visible_after_current_done_next194']));

        return [
            'status' => self::currentSourceDoneStatus($admitNext, $baseExposed, $doneMatches, $cookieMatches, $epochMatches),
            'base' => $base,
            'current_result_code_next194' => $currentDoneCode,
            'expected_current_result_code_next194' => $expectedDoneCode,
            'current_result_code_matches_next194' => $doneMatches,
            'current_source_cookie_next194' => $currentCookie,
            'expected_current_source_cookie_next194' => $expectedCookie,
            'current_source_cookie_matches_next194' => $cookieMatches,
            'current_step_epoch_next194' => $stepEpoch,
            'expected_current_step_epoch_next194' => $expectedStepEpoch,
            'current_step_epoch_matches_next194' => $epochMatches,
            'base_next_exposed_before_current_done_next194' => $baseExposed,
            'next_source_exposed_after_current_done_next194' => $admitNext,
            'current_source_rows' => $gatedCurrentRows,
            'attempted_next_source_rows' => $gatedNextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'block_reasons_next194' => $blockReasons,
            'current_done_plan_next194' => [
                'current_rows' => count($gatedCurrentRows),
                'attempted_next_rows' => count($gatedNextRows),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'current_result_code' => $currentDoneCode,
                'current_source_cookie_matches' => $cookieMatches,
                'current_step_epoch_matches' => $epochMatches,
                'decision' => $admitNext ? 'admit-next-source-after-current-done' : 'hold-next-source-until-current-done',
                'blocked_at_resume_token' => $admitNext || $gatedNextRows === [] ? null : (string) ($gatedNextRows[0]['resume_token'] ?? ''),
            ],
            'counts_next194' => [
                'current_rows' => count($gatedCurrentRows),
                'attempted_next_rows' => count($gatedNextRows),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'block_reasons' => count($blockReasons),
            ],
            'yield_boundary_next194' => $admitNext
                ? 'recursive-view-returning-current-source-next194-current-done-next-exposed'
                : 'recursive-view-returning-current-source-next194-current-done-held',
            'dependencies_next194' => array_values(array_unique(array_merge($base['dependencies'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next194',
                'sqlite-returning-current-source-done-gate',
                'wordpress-recursive-view-returning-current-source-next194',
            ]))),
            'dependency_closure_next194' => 'no new support component needed; reuses recursive view trigger RETURNING resume rows and adds current-source SQLITE_DONE/source-cookie gating',
            'non_overlap_next194' => 'extends accepted next190 resume-source validation with final current-source SQLITE_DONE, source-cookie, and step-epoch gating; avoids accepted next190 resume-token, next187 drain-ticket, next184 checkpoint admission, row-value RETURNING, WAL, pager, B-tree, JSON, PRAGMA, and encoding slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentSourceDoneRows(array $rows, string $resultCode, string $cookie, string $epoch, bool $visible, array $blockReasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'current_result_code_next194' => $resultCode,
                'current_source_cookie_next194' => $cookie,
                'current_step_epoch_next194' => $epoch,
                'visible_after_current_done_next194' => $visible,
                'held_by_current_done_reasons_next194' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function currentSourceDoneRows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'], $row['resume_token'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} row envelope is malformed");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function currentSourceDoneBlockReasons(mixed $baseReasons, bool $baseExposed, bool $doneMatches, bool $cookieMatches, bool $epochMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next194 base block reasons must be a list');
        }

        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseExposed && $reasons === []) {
            $reasons[] = 'resume-source-not-exposed';
        }
        if (!$doneMatches) {
            $reasons[] = 'current-source-not-done';
        }
        if (!$cookieMatches) {
            $reasons[] = 'current-source-cookie-mismatch';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-step-epoch-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function currentSourceDoneStatus(bool $admitted, bool $baseExposed, bool $doneMatches, bool $cookieMatches, bool $epochMatches): string
    {
        if ($admitted) {
            return 'trigger-recursive-view-returning-current-source-next194-next-exposed';
        }
        if (!$baseExposed) {
            return 'trigger-recursive-view-returning-current-source-next194-resume-source-held';
        }
        if (!$doneMatches) {
            return 'trigger-recursive-view-returning-current-source-next194-current-not-done';
        }
        if (!$cookieMatches) {
            return 'trigger-recursive-view-returning-current-source-next194-source-cookie-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-returning-current-source-next194-step-epoch-held';
        }

        return 'trigger-recursive-view-returning-current-source-next194-held';
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function currentSourceDoneCookie(array $view, array $returning): string
    {
        $material = [
            (string) ($view['name'] ?? ''),
            (string) ($view['source'] ?? ''),
            (string) ($view['trigger_source'] ?? ''),
            count($returning),
        ];

        return 'cookie194:' . substr(hash('sha256', implode('|', $material)), 0, 16);
    }

    /**
     * @param array<string,mixed> $base
     */
    private static function currentSourceDoneStepEpoch(array $base): string
    {
        $resumePlan = is_array($base['resume_plan'] ?? null) ? $base['resume_plan'] : [];
        $material = [
            (string) ($base['last_current_resume_token'] ?? ''),
            (string) ($base['first_next_resume_token'] ?? ''),
            (string) ($resumePlan['visible_row_count'] ?? ''),
            (string) ($resumePlan['decision'] ?? ''),
        ];

        return 'epoch194:' . substr(hash('sha256', implode('|', $material)), 0, 16);
    }

    private static function currentSourceDoneResultCode(string $value, string $label): string
    {
        if (!in_array($value, ['SQLITE_ROW', 'SQLITE_DONE', 'SQLITE_BUSY', 'SQLITE_SCHEMA', 'SQLITE_ERROR'], true)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} is malformed");
        }

        return $value;
    }

    private static function currentSourceDoneToken(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool,current_watermark?:string,expected_current_watermark?:string,acknowledged_current_ordinals?:list<int>,auto_ack_current_ordinals?:bool,require_contiguous_ordinals?:bool,fingerprint_salt?:string,expected_fingerprint_salt?:string,acknowledged_current_fingerprints?:list<string>,auto_ack_current_fingerprints?:bool,require_fingerprint_order?:bool,current_source_token_next195?:string,expected_current_source_token_next195?:string,next_resume_token_next195?:string,expected_next_resume_token_next195?:string,acknowledged_current_source_receipts_next195?:list<string>,auto_ack_current_source_receipts_next195?:bool,require_receipt_order_next195?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeNext195(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $sourceToken = self::tokenNext195((string) ($options['current_source_token_next195'] ?? 'wp.recursive.view.current.source.195'), 'current source token');
        $expectedSourceToken = self::tokenNext195((string) ($options['expected_current_source_token_next195'] ?? $sourceToken), 'expected current source token');
        $resumeToken = self::tokenNext195((string) ($options['next_resume_token_next195'] ?? 'wp.recursive.view.next.resume.195'), 'next resume token');
        $expectedResumeToken = self::tokenNext195((string) ($options['expected_next_resume_token_next195'] ?? $resumeToken), 'expected next resume token');
        $requireOrder = (bool) ($options['require_receipt_order_next195'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext191(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'auto_ack_current_ordinals' => true,
                'auto_ack_current_fingerprints' => true,
            ],
        );

        $currentRows = self::rowsNext195($base['current_fingerprint_rows_next191'] ?? [], 'current rows');
        $nextRows = self::rowsNext195($base['attempted_next_fingerprint_rows_next191'] ?? [], 'attempted next rows');
        $required = self::sourceReceiptsNext195($currentRows, $sourceToken, $resumeToken);
        $acknowledged = self::acknowledgedReceiptsNext195($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $resumeMatches = hash_equals($resumeToken, $expectedResumeToken);
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next191'] ?? false);
        $receiptFenceClear = $missing === [] && $unexpected === [] && $orderMatches;
        $resumeNext = $basePublishAllowed && $sourceMatches && $resumeMatches && $receiptFenceClear;
        $blockedReasons = self::blockedReasonsNext195(
            $base['blocked_reasons_next191'] ?? [],
            $basePublishAllowed,
            $sourceMatches,
            $resumeMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagCurrentRowsNext195($currentRows, $required, $sourceToken, $resumeToken);
        $nextTagged = self::tagNextRowsNext195($nextRows, $resumeNext, $sourceToken, $resumeToken, $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentTagged, $nextTagged),
            static fn (array $row): bool => $row['visible_after_current_source_receipts_next195']
        ));
        $heldRows = array_values(array_filter(
            $nextTagged,
            static fn (array $row): bool => !$row['visible_after_current_source_receipts_next195']
        ));

        return $base + [
            'status_next195' => self::statusNext195($basePublishAllowed, $sourceMatches, $resumeMatches, $receiptFenceClear, $resumeNext),
            'current_source_token_next195' => $sourceToken,
            'expected_current_source_token_next195' => $expectedSourceToken,
            'current_source_token_matches_next195' => $sourceMatches,
            'next_resume_token_next195' => $resumeToken,
            'expected_next_resume_token_next195' => $expectedResumeToken,
            'next_resume_token_matches_next195' => $resumeMatches,
            'required_current_source_receipts_next195' => $required,
            'acknowledged_current_source_receipts_next195' => $acknowledged,
            'missing_current_source_receipts_next195' => $missing,
            'unexpected_current_source_receipts_next195' => $unexpected,
            'require_receipt_order_next195' => $requireOrder,
            'current_source_receipt_order_matches_next195' => $orderMatches,
            'current_source_receipt_fence_clear_next195' => $receiptFenceClear,
            'next_source_resume_allowed_next195' => $resumeNext,
            'current_source_receipt_rows_next195' => $currentTagged,
            'attempted_next_source_receipt_rows_next195' => $nextTagged,
            'visible_returning_rows_next195' => $visibleRows,
            'held_next_source_rows_next195' => $heldRows,
            'visible_returning_payloads_next195' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next195' => array_column($heldRows, 'returning'),
            'current_source_receipt_row_count_next195' => count($currentTagged),
            'attempted_next_source_receipt_row_count_next195' => count($nextTagged),
            'visible_row_count_next195' => count($visibleRows),
            'held_next_row_count_next195' => count($heldRows),
            'blocked_reasons_next195' => $blockedReasons,
            'current_source_receipt_plan_next195' => [
                'base_publish_allowed' => $basePublishAllowed,
                'source_token_matches' => $sourceMatches,
                'resume_token_matches' => $resumeMatches,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'receipt_fence_clear' => $receiptFenceClear,
                'next_source_resume_allowed' => $resumeNext,
                'decision' => $resumeNext ? 'resume-next-source-after-current-source-receipts' : 'hold-next-source-until-current-source-receipts',
            ],
            'yield_boundary_next195' => $resumeNext
                ? 'recursive-view-returning-next195-current-source-receipts-then-next'
                : 'recursive-view-returning-next195-current-source-receipts-fence-next',
            'dependencies_next195' => [
                'sqlite-trigger-recursive-view-returning-current-source-next195',
                'sqlite-returning-current-source-drain-receipts',
                'sqlite-view-trigger-next-source-resume-token',
                'wordpress-recursive-view-returning-current-source-next195',
            ],
            'dependency_closure_next195' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-fingerprint-fence-and-adds-drain-receipt-resume-model',
            'non_overlap_next195' => 'adds current-source drain receipts before next-source resume after next191 fingerprint admission; avoids accepted next191 fingerprint fencing, next188 ordinal watermarks, savepoint rollback, row-value RETURNING, schema reparse, WAL/VFS, and trigger/FK cascade clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sourceReceiptsNext195(array $rows, string $sourceToken, string $resumeToken): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceToken,
                $resumeToken,
                (string) ($row['current_row_fingerprint_next191'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ''),
                (string) ($row['source_signature_next188'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 28);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsNext195(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_receipts_next195'] ?? false) === true) {
            return $required;
        }

        return self::receiptListNext195($options['acknowledged_current_source_receipts_next195'] ?? [], 'acknowledged current source receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListNext195(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{28}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext195(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRowsNext195(array $rows, array $receipts, string $sourceToken, string $resumeToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'receipt_phase_next195' => 'current',
                'current_source_token_next195' => $sourceToken,
                'next_resume_token_next195' => $resumeToken,
                'current_source_receipt_next195' => $receipts[$index] ?? null,
                'visible_after_current_source_receipts_next195' => true,
                'held_by_current_source_receipt_reasons_next195' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRowsNext195(array $rows, bool $visible, string $sourceToken, string $resumeToken, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'receipt_phase_next195' => 'next',
                'current_source_token_next195' => $sourceToken,
                'next_resume_token_next195' => $resumeToken,
                'current_source_receipt_next195' => null,
                'visible_after_current_source_receipts_next195' => $visible,
                'held_by_current_source_receipt_reasons_next195' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext195(
        mixed $baseReasons,
        bool $basePublishAllowed,
        bool $sourceMatches,
        bool $resumeMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next195 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next191-current-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-token-mismatch';
        }
        if (!$resumeMatches) {
            $reasons[] = 'next-resume-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-receipt-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-receipt-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext195(bool $basePublishAllowed, bool $sourceMatches, bool $resumeMatches, bool $receiptFenceClear, bool $resumeNext): string
    {
        if ($resumeNext) {
            return 'trigger-recursive-view-returning-current-source-receipts-released-next195';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-receipts-base-held-next195';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-receipts-source-held-next195';
        }
        if (!$resumeMatches) {
            return 'trigger-recursive-view-returning-current-source-receipts-resume-held-next195';
        }
        if (!$receiptFenceClear) {
            return 'trigger-recursive-view-returning-current-source-receipts-held-next195';
        }

        return 'trigger-recursive-view-returning-current-source-receipts-pending-next195';
    }

    private static function tokenNext195(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext196(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext192(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $followingRows = self::rowsNext196($base['following_current_rows_next192'] ?? [], 'following current rows');
        $followingVisible = ($base['status_next192'] ?? '') === 'trigger-recursive-view-returning-current-source-next192-following-current-visible';
        $recursiveColumn = self::identifierNext196((string) ($options['recursive_child_column'] ?? 'spawn_child'), 'recursive child column');
        $recursiveSuffix = self::tokenNext196((string) ($options['recursive_child_suffix'] ?? '_child'), 'recursive child suffix');
        $currentToken = self::tokenNext196((string) ($options['following_current_source_token'] ?? ($base['following_current_source_token_next192'] ?? 'wp.current.source.following.196')), 'following current source token');
        $childToken = self::tokenNext196((string) ($options['recursive_child_source_token'] ?? 'wp.current.source.recursive.child.196'), 'recursive child source token');
        $expectedChildToken = self::tokenNext196((string) ($options['expected_recursive_child_source_token'] ?? $childToken), 'expected recursive child source token');
        $cursor = self::tokenNext196((string) ($options['recursive_child_cursor'] ?? 'wp.returning.recursive.child.cursor.196'), 'recursive child cursor');
        $generation = self::tokenNext196((string) ($options['recursive_child_generation'] ?? 'wp-recursive-child-current-196'), 'recursive child generation');
        $childRows = $followingVisible
            ? self::childRowsNext196($followingRows, $returning, $currentView, $recursiveColumn, $recursiveSuffix, $currentToken, $childToken, $cursor, $generation)
            : [];
        $required = array_column($childRows, 'returning_row_ordinal');
        $acknowledged = self::ordinalsNext196($options['recursive_child_acknowledged_ordinals'] ?? []);
        $childrenAcknowledged = $childRows !== [] && self::sameOrdinalsNext196($required, $acknowledged);
        $tokenMatches = hash_equals($childToken, $expectedChildToken);
        $publishNext = $followingVisible && $childrenAcknowledged && $tokenMatches;
        $blocked = self::blockedReasonsNext196($base, $followingVisible, $childrenAcknowledged, $tokenMatches);

        return [
            'status_next196' => self::statusNext196($publishNext, $followingVisible, $childrenAcknowledged, $tokenMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'following_current_source_visible_next196' => $followingVisible,
            'recursive_child_column_next196' => $recursiveColumn,
            'recursive_child_suffix_next196' => $recursiveSuffix,
            'following_current_source_token_next196' => $currentToken,
            'recursive_child_source_token_next196' => $childToken,
            'expected_recursive_child_source_token_next196' => $expectedChildToken,
            'recursive_child_source_token_matches_next196' => $tokenMatches,
            'recursive_child_cursor_next196' => $cursor,
            'recursive_child_generation_next196' => $generation,
            'recursive_child_rows_next196' => $childRows,
            'recursive_child_payloads_next196' => array_column($childRows, 'returning'),
            'recursive_child_required_ordinals_next196' => $required,
            'recursive_child_acknowledged_ordinals_next196' => $acknowledged,
            'recursive_child_rows_acknowledged_next196' => $childrenAcknowledged,
            'recursive_child_row_count_next196' => count($childRows),
            'next_source_publish_allowed_next196' => $publishNext,
            'blocked_reasons_next196' => $blocked,
            'current_source_next_plan_next196' => [
                'following_rows_visible' => count($followingRows),
                'recursive_child_rows_required' => count($childRows),
                'recursive_child_rows_acknowledged' => count(array_intersect($required, $acknowledged)),
                'child_source_token_matches' => $tokenMatches,
                'next_source_publish_allowed' => $publishNext,
                'decision' => self::decisionNext196($publishNext, $followingVisible, $childrenAcknowledged, $tokenMatches),
                'resume_after_recursive_child_ordinal' => $childRows === [] ? null : max($required),
            ],
            'yield_boundary_next196' => $publishNext
                ? 'recursive-view-returning-next196-following-current-child-returning-drained-next-source'
                : 'recursive-view-returning-next196-following-current-child-returning-fences-next-source',
            'dependency_closure_next196' => 'no new support component needed; reuses next192 following-current admission and adds recursive child RETURNING drain fencing before the next source',
            'dependencies_next196' => array_values(array_unique(array_merge($base['dependencies_next192'], [
                'sqlite-trigger-recursive-view-returning-current-source-next196',
                'sqlite-returning-recursive-child-current-source-fence',
                'wordpress-recursive-view-returning-current-source-next196',
            ]))),
            'non_overlap_next196' => 'extends accepted next192 cursor-close following-current admission with recursive child RETURNING current-source drain fencing; avoids next189 row-ack, next191 fingerprint, next192 cursor-close, row-value RETURNING, UPSERT, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $followingRows
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function childRowsNext196(array $followingRows, array $returning, array $view, string $recursiveColumn, string $suffix, string $currentToken, string $childToken, string $cursor, string $generation): array
    {
        $out = [];
        foreach ($followingRows as $parentOrdinal => $parent) {
            $payload = self::payloadNext196($parent);
            $shouldSpawn = (bool) ($payload[$recursiveColumn] ?? $parent[$recursiveColumn] ?? false);
            if (!$shouldSpawn) {
                continue;
            }
            $new = [
                'option_name' => (string) ($payload['name'] ?? $payload['option_name'] ?? $parent['returning_option_name'] ?? '') . $suffix,
                'option_value' => (string) ($payload['value'] ?? $payload['option_value'] ?? '') . $suffix,
                $recursiveColumn => false,
            ];
            $out[] = [
                'statement_source' => 'recursive-child-current',
                'parent_returning_row_ordinal' => (int) ($parent['returning_row_ordinal'] ?? $parentOrdinal),
                'returning_row_ordinal' => count($out),
                'returning' => self::returningPayloadNext196($returning, $new, $view, count($out)),
                'returning_option_name' => $new['option_name'],
                'parent_following_current_source_token_next196' => $currentToken,
                'recursive_child_source_token_next196' => $childToken,
                'recursive_child_cursor_next196' => $cursor,
                'recursive_child_generation_next196' => $generation,
                'recursive_depth_next196' => 1,
                'source_signature_next196' => self::signatureNext196($view, $returning, $childToken),
            ];
        }

        return $out;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayloadNext196(array $returning, array $new, array $view, int $ordinal): array
    {
        $payload = [];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'recursive-child-current', 1, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'],
                'new.option_value' => $new['option_value'],
                'old.option_value' => null,
                'event' => 'recursive-child-current',
                'depth' => 1,
                'ordinal' => $ordinal,
                'trigger_source' => (string) ($view['trigger_source'] ?? ''),
                'view.name' => (string) ($view['name'] ?? ''),
                default => $new[$expr] ?? null,
            };
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private static function payloadNext196(array $row): array
    {
        $payload = $row['returning'] ?? [];
        if (!is_array($payload)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next196 parent payload is malformed');
        }

        return $payload;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rowsNext196(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} row is malformed");
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function ordinalsNext196(mixed $ordinals): array
    {
        if (!is_array($ordinals) || !array_is_list($ordinals)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next196 acknowledged ordinals must be a list');
        }
        $out = [];
        foreach ($ordinals as $ordinal) {
            if (!is_int($ordinal) || $ordinal < 0) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next196 acknowledged ordinals must be non-negative integers');
            }
            $out[] = $ordinal;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $required
     * @param list<int> $acknowledged
     */
    private static function sameOrdinalsNext196(array $required, array $acknowledged): bool
    {
        sort($required);
        sort($acknowledged);

        return $required === $acknowledged;
    }

    /**
     * @return list<string>
     */
    private static function blockedReasonsNext196(array $base, bool $followingVisible, bool $childrenAcknowledged, bool $tokenMatches): array
    {
        $reasons = [];
        if (!$followingVisible) {
            $reasons = array_merge($reasons, $base['blocked_reasons_next192'] ?? ['following-current-source-not-visible']);
        }
        if (!$childrenAcknowledged) {
            $reasons[] = 'recursive-child-returning-rows-not-acknowledged';
        }
        if (!$tokenMatches) {
            $reasons[] = 'recursive-child-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function decisionNext196(bool $publishNext, bool $followingVisible, bool $childrenAcknowledged, bool $tokenMatches): string
    {
        if ($publishNext) {
            return 'publish-next-after-recursive-child-current-returning-drain';
        }
        if (!$followingVisible) {
            return 'hold-next-until-following-current-visible';
        }
        if (!$childrenAcknowledged) {
            return 'hold-next-until-recursive-child-returning-acks';
        }
        if (!$tokenMatches) {
            return 'hold-next-recursive-child-source-token';
        }

        return 'hold-next-source';
    }

    private static function statusNext196(bool $publishNext, bool $followingVisible, bool $childrenAcknowledged, bool $tokenMatches): string
    {
        if ($publishNext) {
            return 'trigger-recursive-view-returning-current-source-next196-next-source-visible';
        }
        if (!$followingVisible) {
            return 'trigger-recursive-view-returning-current-source-next196-following-current-held';
        }
        if (!$childrenAcknowledged) {
            return 'trigger-recursive-view-returning-current-source-next196-awaiting-recursive-child-acks';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next196-child-token-held';
        }

        return 'trigger-recursive-view-returning-current-source-next196-held';
    }

    private static function identifierNext196(string $identifier, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} is malformed");
        }

        return $identifier;
    }

    private static function tokenNext196(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} is malformed");
        }

        return $token;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function signatureNext196(array $view, array $returning, string $token): string
    {
        $aliases = [];
        foreach ($returning as $index => $term) {
            $aliases[] = is_array($term) ? (string) ($term['as'] ?? $term['expr'] ?? $index) : (is_string($term) ? $term : 'callable_' . $index);
        }

        return substr(hash('sha256', json_encode([
            'name' => (string) ($view['name'] ?? ''),
            'trigger_source' => (string) ($view['trigger_source'] ?? ''),
            'token' => $token,
            'returning' => $aliases,
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext200(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDoneGate(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'admit_next_source' => true,
                'auto_ack_current' => true,
                'cursor_name' => 'wp_recursive_view_returning_cursor_200',
                'current_generation' => 'wp-current-returning-200',
                'next_generation' => 'wp-next-returning-200',
                'checkpoint_name' => 'wp_recursive_view_checkpoint_200',
                'handoff_token' => 'wp.returning.current.source.handoff.200',
                'savepoint' => 'wp_recursive_view_returning_next200',
                'drain_ticket_prefix' => 'wp.returning.current.source.drain.200',
                'resume_source_prefix' => 'wp.returning.current.source.resume.200',
            ],
        );

        $currentRows = self::rowsNext200($base['current_source_rows'] ?? [], 'current source rows');
        $nextRows = self::rowsNext200($base['attempted_next_source_rows'] ?? [], 'attempted next source rows');
        $actualDrainCount = count($currentRows);
        $expectedDrainCount = self::nonNegativeIntNext200($options['expected_current_drain_count_next200'] ?? $actualDrainCount, 'expected current drain count');
        $actualHighWater = self::lastResumeTokenNext200($currentRows);
        $expectedHighWater = self::tokenNext200((string) ($options['expected_current_highwater_token_next200'] ?? $actualHighWater), 'expected current highwater token');
        $currentGeneration = self::tokenNext200((string) ($base['current_step_epoch_next194'] ?? 'epoch200:missing'), 'current step epoch');
        $expectedGeneration = self::tokenNext200((string) ($options['expected_current_generation_epoch_next200'] ?? $currentGeneration), 'expected current generation epoch');

        $baseAdmitted = (bool) ($base['next_source_exposed_after_current_done_next194'] ?? false);
        $drainCountMatches = $actualDrainCount === $expectedDrainCount;
        $highWaterMatches = hash_equals($expectedHighWater, $actualHighWater);
        $generationMatches = hash_equals($expectedGeneration, $currentGeneration);
        $nextVisible = $baseAdmitted && $drainCountMatches && $highWaterMatches && $generationMatches;
        $blockReasons = self::blockReasonsNext200(
            $base['block_reasons_next194'] ?? [],
            $baseAdmitted,
            $drainCountMatches,
            $highWaterMatches,
            $generationMatches,
        );

        $taggedCurrent = self::tagRowsNext200($currentRows, true, [], $actualDrainCount, $actualHighWater, $currentGeneration);
        $taggedNext = self::tagRowsNext200($nextRows, $nextVisible, $blockReasons, $actualDrainCount, $actualHighWater, $currentGeneration);
        $visibleRows = array_values(array_filter(array_merge($taggedCurrent, $taggedNext), static fn (array $row): bool => $row['visible_after_current_highwater_next200']));
        $heldRows = array_values(array_filter($taggedNext, static fn (array $row): bool => !$row['visible_after_current_highwater_next200']));

        return [
            'status_next200' => self::statusNext200($nextVisible, $baseAdmitted, $drainCountMatches, $highWaterMatches, $generationMatches),
            'base' => $base,
            'current_drain_count_next200' => $actualDrainCount,
            'expected_current_drain_count_next200' => $expectedDrainCount,
            'current_drain_count_matches_next200' => $drainCountMatches,
            'current_highwater_token_next200' => $actualHighWater,
            'expected_current_highwater_token_next200' => $expectedHighWater,
            'current_highwater_token_matches_next200' => $highWaterMatches,
            'current_generation_epoch_next200' => $currentGeneration,
            'expected_current_generation_epoch_next200' => $expectedGeneration,
            'current_generation_epoch_matches_next200' => $generationMatches,
            'base_next_exposed_before_highwater_next200' => $baseAdmitted,
            'next_source_exposed_after_current_highwater_next200' => $nextVisible,
            'current_source_rows_next200' => $taggedCurrent,
            'attempted_next_source_rows_next200' => $taggedNext,
            'visible_rows_next200' => $visibleRows,
            'held_rows_next200' => $heldRows,
            'visible_returning_rows_next200' => array_column($visibleRows, 'returning'),
            'held_returning_rows_next200' => array_column($heldRows, 'returning'),
            'block_reasons_next200' => $blockReasons,
            'current_highwater_plan_next200' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'current_highwater_token' => $actualHighWater,
                'current_drain_count_matches' => $drainCountMatches,
                'current_highwater_token_matches' => $highWaterMatches,
                'current_generation_epoch_matches' => $generationMatches,
                'decision' => $nextVisible ? 'admit-next-source-after-current-highwater' : 'hold-next-source-until-current-highwater',
                'blocked_at_resume_token' => $nextVisible || $taggedNext === [] ? null : (string) ($taggedNext[0]['resume_token'] ?? ''),
            ],
            'counts_next200' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'block_reasons' => count($blockReasons),
            ],
            'yield_boundary_next200' => $nextVisible
                ? 'recursive-view-returning-current-source-next200-current-highwater-next-exposed'
                : 'recursive-view-returning-current-source-next200-current-highwater-held',
            'dependencies_next200' => array_values(array_unique(array_merge($base['dependencies_next194'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next200',
                'sqlite-returning-current-source-highwater-gate',
                'wordpress-recursive-view-returning-current-source-next200',
            ]))),
            'dependency_closure_next200' => 'no new support component needed; reuses recursive view trigger RETURNING resume rows and adds current-source drain high-water gating',
            'non_overlap_next200' => 'extends accepted next194 SQLITE_DONE/source-cookie gate with current drain-count and high-water resume-token admission; avoids next194 done-gate repeats, next187 drain tickets, row-value RETURNING, schema reparse, WAL, pager, B-tree, JSON, PRAGMA, and encoding slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext200(array $rows, bool $visible, array $blockReasons, int $drainCount, string $highWater, string $generation): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'current_drain_count_next200' => $drainCount,
                'current_highwater_token_next200' => $highWater,
                'current_generation_epoch_next200' => $generation,
                'visible_after_current_highwater_next200' => $visible,
                'held_by_current_highwater_reasons_next200' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rowsNext200(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'], $row['resume_token'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} row envelope is malformed");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function lastResumeTokenNext200(array $rows): string
    {
        $last = $rows[array_key_last($rows)] ?? null;
        if (!is_array($last) || !isset($last['resume_token'])) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next200 current highwater row is missing');
        }

        return self::tokenNext200((string) $last['resume_token'], 'current highwater token');
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockReasonsNext200(mixed $baseReasons, bool $baseAdmitted, bool $drainCountMatches, bool $highWaterMatches, bool $generationMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next200 base block reasons must be a list');
        }

        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseAdmitted && $reasons === []) {
            $reasons[] = 'current-source-done-gate-held';
        }
        if (!$drainCountMatches) {
            $reasons[] = 'current-source-drain-count-mismatch';
        }
        if (!$highWaterMatches) {
            $reasons[] = 'current-source-highwater-token-mismatch';
        }
        if (!$generationMatches) {
            $reasons[] = 'current-source-generation-epoch-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext200(bool $visible, bool $baseAdmitted, bool $drainCountMatches, bool $highWaterMatches, bool $generationMatches): string
    {
        if ($visible) {
            return 'trigger-recursive-view-returning-current-source-next200-next-exposed';
        }
        if (!$baseAdmitted) {
            return 'trigger-recursive-view-returning-current-source-next200-done-gate-held';
        }
        if (!$drainCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next200-drain-count-held';
        }
        if (!$highWaterMatches) {
            return 'trigger-recursive-view-returning-current-source-next200-highwater-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-returning-current-source-next200-generation-held';
        }

        return 'trigger-recursive-view-returning-current-source-next200-held';
    }

    private static function nonNegativeIntNext200(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} is malformed");
        }

        return $value;
    }

    private static function tokenNext200(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext202(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext196(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentGeneration = self::tokenNext202((string) ($options['current_view_generation_next202'] ?? 'wp.current.recursive.view.202'), 'current view generation');
        $expectedGeneration = self::tokenNext202((string) ($options['expected_current_view_generation_next202'] ?? $currentGeneration), 'expected current view generation');
        $nextGeneration = self::tokenNext202((string) ($options['next_view_generation_next202'] ?? 'wp.next.recursive.view.202'), 'next view generation');
        $resumeBarrier = self::tokenNext202((string) ($options['returning_resume_barrier_next202'] ?? 'wp.returning.resume.barrier.202'), 'returning resume barrier');
        $requiredDepths = self::depthsNext202($options['required_current_depths_next202'] ?? self::requiredDepthsNext202($base), 'required current depths');
        $acknowledgedDepths = self::depthsNext202($options['acknowledged_current_depths_next202'] ?? [], 'acknowledged current depths');
        $generationMatches = hash_equals($currentGeneration, $expectedGeneration);
        $baseAllowsNext = (bool) ($base['next_source_publish_allowed_next196'] ?? false);
        $depthsAcknowledged = self::sameSetNext202($requiredDepths, $acknowledgedDepths);
        $publishNext = $baseAllowsNext && $generationMatches && $depthsAcknowledged;
        $blocked = self::blockedReasonsNext202($base, $baseAllowsNext, $generationMatches, $depthsAcknowledged);
        $currentRows = self::currentRowsNext202($base, $currentGeneration, $resumeBarrier, $requiredDepths);
        $nextRows = self::nextRowsNext202($base, $nextGeneration, $resumeBarrier, $publishNext, $blocked);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => $row['visible_after_current_generation_next202']
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !$row['visible_after_current_generation_next202']
        ));

        return [
            'status_next202' => self::statusNext202($publishNext, $baseAllowsNext, $generationMatches, $depthsAcknowledged),
            'base_next202' => $base,
            'savepoint_next202' => (string) ($base['savepoint'] ?? ''),
            'current_view_generation_next202' => $currentGeneration,
            'expected_current_view_generation_next202' => $expectedGeneration,
            'current_view_generation_matches_next202' => $generationMatches,
            'next_view_generation_next202' => $nextGeneration,
            'returning_resume_barrier_next202' => $resumeBarrier,
            'required_current_depths_next202' => $requiredDepths,
            'acknowledged_current_depths_next202' => $acknowledgedDepths,
            'current_depths_acknowledged_next202' => $depthsAcknowledged,
            'base_next_source_publish_allowed_next202' => $baseAllowsNext,
            'next_source_publish_allowed_next202' => $publishNext,
            'blocked_reasons_next202' => $blocked,
            'current_generation_rows_next202' => $currentRows,
            'attempted_next_generation_rows_next202' => $nextRows,
            'visible_returning_rows_next202' => $visibleRows,
            'held_next_returning_rows_next202' => $heldRows,
            'visible_returning_payloads_next202' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next202' => array_column($heldRows, 'returning'),
            'current_generation_row_count_next202' => count($currentRows),
            'attempted_next_generation_row_count_next202' => count($nextRows),
            'visible_row_count_next202' => count($visibleRows),
            'held_next_row_count_next202' => count($heldRows),
            'current_source_next_plan_next202' => [
                'base_next_source_publish_allowed' => $baseAllowsNext,
                'current_view_generation_matches' => $generationMatches,
                'required_current_depths' => $requiredDepths,
                'acknowledged_current_depths' => $acknowledgedDepths,
                'current_depths_acknowledged' => $depthsAcknowledged,
                'next_source_publish_allowed' => $publishNext,
                'decision' => self::decisionNext202($publishNext, $baseAllowsNext, $generationMatches, $depthsAcknowledged),
            ],
            'yield_boundary_next202' => $publishNext
                ? 'recursive-view-returning-next202-current-generation-depths-then-next'
                : 'recursive-view-returning-next202-current-generation-depths-fence-next',
            'dependencies_next202' => array_values(array_unique(array_merge($base['dependencies_next196'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next202',
                'sqlite-returning-current-view-generation-depth-fence',
                'wordpress-recursive-view-returning-current-source-next202',
            ]))),
            'dependency_closure_next202' => 'no new support component needed; reuses next196 recursive child drain and adds current view generation/depth acknowledgement fencing',
            'non_overlap_next202' => 'adds current view generation and recursive depth acknowledgement fencing after accepted next196 child-ordinal drains; avoids next195 receipt fences, next196 child drain, row-value RETURNING, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @return list<int>
     */
    private static function requiredDepthsNext202(array $base): array
    {
        $depths = [];
        foreach (self::baseCurrentRowsNext202($base) as $row) {
            if (isset($row['recursive_depth_next196']) && is_int($row['recursive_depth_next196'])) {
                $depths[] = $row['recursive_depth_next196'];
                continue;
            }
            $payload = $row['returning'] ?? [];
            if (is_array($payload) && isset($payload['depth_value']) && is_int($payload['depth_value'])) {
                $depths[] = $payload['depth_value'];
            }
        }

        $depths = array_values(array_unique($depths));
        sort($depths);

        return $depths === [] ? [0] : $depths;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function baseCurrentRowsNext202(array $base): array
    {
        $rows = [];
        foreach (['base', 'recursive_child_rows_next196'] as $key) {
            $source = $key === 'base' ? ($base['base']['following_current_rows_next192'] ?? []) : ($base[$key] ?? []);
            if (!is_array($source) || !array_is_list($source)) {
                continue;
            }
            foreach ($source as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function depthsNext202(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next202 {$label} must be a list");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value < 0) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next202 {$label} must contain non-negative integers");
            }
            $out[] = $value;
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param list<int> $required
     * @param list<int> $acknowledged
     */
    private static function sameSetNext202(array $required, array $acknowledged): bool
    {
        sort($required);
        sort($acknowledged);

        return $required === $acknowledged;
    }

    /**
     * @param list<int> $depths
     * @return list<array<string,mixed>>
     */
    private static function currentRowsNext202(array $base, string $generation, string $barrier, array $depths): array
    {
        $rows = [];
        foreach (self::baseCurrentRowsNext202($base) as $ordinal => $row) {
            $rows[] = $row + [
                'generation_phase_next202' => 'current',
                'current_view_generation_next202' => $generation,
                'returning_resume_barrier_next202' => $barrier,
                'required_current_depths_next202' => $depths,
                'visible_after_current_generation_next202' => true,
                'held_by_current_generation_reasons_next202' => [],
                'generation_row_ordinal_next202' => $ordinal,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $blocked
     * @return list<array<string,mixed>>
     */
    private static function nextRowsNext202(array $base, string $generation, string $barrier, bool $visible, array $blocked): array
    {
        $rows = [];
        $source = $base['base']['base']['next_source_rows_next189']
            ?? $base['base']['next_source_rows_next192']
            ?? $base['base']['attempted_next_rows_next190']
            ?? [];
        if (!is_array($source) || !array_is_list($source)) {
            $source = [];
        }
        foreach ($source as $ordinal => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $row + [
                'generation_phase_next202' => 'next',
                'next_view_generation_next202' => $generation,
                'returning_resume_barrier_next202' => $barrier,
                'visible_after_current_generation_next202' => $visible,
                'held_by_current_generation_reasons_next202' => $visible ? [] : $blocked,
                'generation_row_ordinal_next202' => $ordinal,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function blockedReasonsNext202(array $base, bool $baseAllowsNext, bool $generationMatches, bool $depthsAcknowledged): array
    {
        $reasons = [];
        if (!$baseAllowsNext) {
            $baseReasons = $base['blocked_reasons_next196'] ?? [];
            $reasons = is_array($baseReasons) ? array_values(array_map('strval', $baseReasons)) : ['base-next196-held'];
        }
        if (!$generationMatches) {
            $reasons[] = 'current-view-generation-mismatch';
        }
        if (!$depthsAcknowledged) {
            $reasons[] = 'current-recursive-depths-not-acknowledged';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext202(bool $publishNext, bool $baseAllowsNext, bool $generationMatches, bool $depthsAcknowledged): string
    {
        if ($publishNext) {
            return 'trigger-recursive-view-returning-current-source-next202-next-source-visible';
        }
        if (!$baseAllowsNext) {
            return 'trigger-recursive-view-returning-current-source-next202-base-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-returning-current-source-next202-generation-held';
        }
        if (!$depthsAcknowledged) {
            return 'trigger-recursive-view-returning-current-source-next202-depth-held';
        }

        return 'trigger-recursive-view-returning-current-source-next202-held';
    }

    private static function decisionNext202(bool $publishNext, bool $baseAllowsNext, bool $generationMatches, bool $depthsAcknowledged): string
    {
        if ($publishNext) {
            return 'publish-next-after-current-generation-depth-acks';
        }
        if (!$baseAllowsNext) {
            return 'hold-next-until-next196-child-drain';
        }
        if (!$generationMatches) {
            return 'hold-next-current-view-generation';
        }
        if (!$depthsAcknowledged) {
            return 'hold-next-current-recursive-depths';
        }

        return 'hold-next-source';
    }

    private static function tokenNext202(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next202 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext203(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext196(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentGeneration = self::tokenNext203((string) ($options['current_generation_next203'] ?? 'wp.current.recursive.returning.generation.203'), 'current generation');
        $expectedGeneration = self::tokenNext203((string) ($options['expected_current_generation_next203'] ?? $currentGeneration), 'expected current generation');
        $handoffCursor = self::tokenNext203((string) ($options['current_handoff_cursor_next203'] ?? 'wp.returning.current.handoff.cursor.203'), 'current handoff cursor');
        $commitMarker = self::tokenNext203((string) ($options['current_generation_commit_marker_next203'] ?? 'wp.current.recursive.returning.commit.203'), 'current generation commit marker');
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next196'] ?? false);
        $generationMatches = hash_equals($currentGeneration, $expectedGeneration);
        $requiredReceipts = self::generationReceiptsNext203(
            self::rowsNext203($base['recursive_child_rows_next196'] ?? [], 'recursive child rows'),
            $currentGeneration,
            $handoffCursor,
            $commitMarker,
        );
        $acknowledgedReceipts = self::acknowledgedReceiptsNext203($options, $requiredReceipts);
        $requireOrder = (bool) ($options['require_generation_receipt_order_next203'] ?? true);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $generationFenceClear = $requiredReceipts !== []
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $basePublishAllowed && $generationMatches && $generationFenceClear;
        $blockedReasons = self::blockedReasonsNext203(
            $base['blocked_reasons_next196'] ?? [],
            $basePublishAllowed,
            $generationMatches,
            $generationFenceClear,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagCurrentRowsNext203(
            self::rowsNext203($base['recursive_child_rows_next196'] ?? [], 'recursive child rows'),
            $requiredReceipts,
            $currentGeneration,
            $handoffCursor,
            $commitMarker,
        );
        $nextRows = self::tagNextRowsNext203(
            self::rowsNext203($base['base']['base']['next_source_rows_next189'] ?? [], 'next rows'),
            $nextVisible,
            $currentGeneration,
            $handoffCursor,
            $commitMarker,
            $blockedReasons,
        );
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_generation_next203'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_generation_next203'],
        ));

        return [
            'status_next203' => self::statusNext203($basePublishAllowed, $generationMatches, $generationFenceClear, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_publish_allowed_next203' => $basePublishAllowed,
            'current_generation_next203' => $currentGeneration,
            'expected_current_generation_next203' => $expectedGeneration,
            'current_generation_matches_next203' => $generationMatches,
            'current_handoff_cursor_next203' => $handoffCursor,
            'current_generation_commit_marker_next203' => $commitMarker,
            'required_current_generation_receipts_next203' => $requiredReceipts,
            'acknowledged_current_generation_receipts_next203' => $acknowledgedReceipts,
            'missing_current_generation_receipts_next203' => $missingReceipts,
            'unexpected_current_generation_receipts_next203' => $unexpectedReceipts,
            'require_generation_receipt_order_next203' => $requireOrder,
            'current_generation_receipt_order_matches_next203' => $orderMatches,
            'current_generation_fence_clear_next203' => $generationFenceClear,
            'next_source_visible_after_current_generation_next203' => $nextVisible,
            'current_generation_rows_next203' => $currentRows,
            'attempted_next_generation_rows_next203' => $nextRows,
            'visible_returning_rows_next203' => $visibleRows,
            'held_next_source_rows_next203' => $heldRows,
            'visible_returning_payloads_next203' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next203' => array_column($heldRows, 'returning'),
            'current_generation_row_count_next203' => count($currentRows),
            'attempted_next_generation_row_count_next203' => count($nextRows),
            'visible_row_count_next203' => count($visibleRows),
            'held_next_row_count_next203' => count($heldRows),
            'blocked_reasons_next203' => $blockedReasons,
            'current_generation_plan_next203' => [
                'base_next_source_publish_allowed' => $basePublishAllowed,
                'current_generation_matches' => $generationMatches,
                'required_generation_receipts' => $requiredReceipts,
                'acknowledged_generation_receipts' => $acknowledgedReceipts,
                'missing_generation_receipts' => $missingReceipts,
                'unexpected_generation_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'generation_fence_clear' => $generationFenceClear,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-generation-handoff'
                    : 'hold-next-source-until-current-generation-handoff',
            ],
            'yield_boundary_next203' => $nextVisible
                ? 'recursive-view-returning-next203-current-generation-handoff-then-next'
                : 'recursive-view-returning-next203-current-generation-handoff-fences-next',
            'dependency_closure_next203' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-handoff-fence',
            'dependencies_next203' => array_values(array_unique(array_merge($base['dependencies_next196'], [
                'sqlite-trigger-recursive-view-returning-current-source-next203',
                'sqlite-returning-current-source-generation-handoff',
                'wordpress-recursive-view-returning-current-source-next203',
            ]))),
            'non_overlap_next203' => 'adds current-source generation handoff receipts after next196 recursive child drain; avoids accepted next196 child drain, next195 receipt fence, next191 fingerprint fencing, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function generationReceiptsNext203(array $rows, string $generation, string $cursor, string $commitMarker): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $generation,
                $cursor,
                $commitMarker,
                (string) ($row['source_signature_next196'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 30);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsNext203(array $options, array $required): array
    {
        if (($options['auto_ack_current_generation_receipts_next203'] ?? false) === true) {
            return $required;
        }

        return self::receiptListNext203($options['acknowledged_current_generation_receipts_next203'] ?? [], 'acknowledged current generation receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListNext203(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{30}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext203(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRowsNext203(array $rows, array $receipts, string $generation, string $cursor, string $commitMarker): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'generation_phase_next203' => 'current',
                'current_generation_next203' => $generation,
                'current_handoff_cursor_next203' => $cursor,
                'current_generation_commit_marker_next203' => $commitMarker,
                'current_generation_receipt_next203' => $receipts[$index] ?? null,
                'visible_after_current_generation_next203' => true,
                'held_by_current_generation_reasons_next203' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRowsNext203(array $rows, bool $visible, string $generation, string $cursor, string $commitMarker, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'generation_phase_next203' => 'next',
                'current_generation_next203' => $generation,
                'current_handoff_cursor_next203' => $cursor,
                'current_generation_commit_marker_next203' => $commitMarker,
                'current_generation_receipt_next203' => null,
                'visible_after_current_generation_next203' => $visible,
                'held_by_current_generation_reasons_next203' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext203(
        mixed $baseReasons,
        bool $basePublishAllowed,
        bool $generationMatches,
        bool $generationFenceClear,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next203 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next196-current-source-not-published';
        }
        if (!$generationMatches) {
            $reasons[] = 'current-generation-mismatch';
        }
        if (!$generationFenceClear) {
            if ($missing !== []) {
                $reasons[] = 'current-generation-receipt-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-generation-receipt-unexpected';
            }
            if ($requireOrder && !$orderMatches) {
                $reasons[] = 'current-generation-receipt-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext203(bool $basePublishAllowed, bool $generationMatches, bool $generationFenceClear, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next203-generation-released';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-next203-base-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-returning-current-source-next203-generation-held';
        }
        if (!$generationFenceClear) {
            return 'trigger-recursive-view-returning-current-source-next203-receipts-held';
        }

        return 'trigger-recursive-view-returning-current-source-next203-held';
    }

    private static function tokenNext203(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext205(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext203(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsNext205($base['current_generation_rows_next203'] ?? [], 'current generation rows');
        $nextRows = self::rowsNext205($base['attempted_next_generation_rows_next203'] ?? [], 'attempted next generation rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_generation_next203'] ?? false);
        $sourceToken = self::tokenNext205((string) ($options['current_source_sequence_token_next205'] ?? 'wp.current.returning.source.sequence.205'), 'current source sequence token');
        $expectedSourceToken = self::tokenNext205((string) ($options['expected_current_source_sequence_token_next205'] ?? $sourceToken), 'expected current source sequence token');
        $nextSourceToken = self::tokenNext205((string) ($options['next_source_sequence_token_next205'] ?? 'wp.next.returning.source.sequence.205'), 'next source sequence token');
        $expectedNextSourceToken = self::tokenNext205((string) ($options['expected_next_source_sequence_token_next205'] ?? $nextSourceToken), 'expected next source sequence token');
        $cursor = self::tokenNext205((string) ($options['source_sequence_cursor_next205'] ?? 'wp.returning.source.sequence.cursor.205'), 'source sequence cursor');
        $sequence = self::sequenceNext205($currentRows, $sourceToken, $cursor);
        $acknowledged = self::acknowledgedSequenceNext205($options, $sequence);
        $requireOrder = (bool) ($options['require_source_sequence_order_next205'] ?? true);
        $missing = array_values(array_diff($sequence, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $sequence));
        $orderMatches = !$requireOrder || $sequence === $acknowledged;
        $sourceTokenMatches = hash_equals($sourceToken, $expectedSourceToken);
        $nextSourceTokenMatches = hash_equals($nextSourceToken, $expectedNextSourceToken);
        $sourceFenceClear = $sequence !== []
            && $missing === []
            && $unexpected === []
            && $orderMatches
            && $sourceTokenMatches
            && $nextSourceTokenMatches;
        $nextVisible = $baseVisible && $sourceFenceClear;
        $blocked = self::blockedReasonsNext205(
            $base['blocked_reasons_next203'] ?? [],
            $baseVisible,
            $sourceFenceClear,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
            $sourceTokenMatches,
            $nextSourceTokenMatches,
        );

        $taggedCurrent = self::tagCurrentNextSourceRows($currentRows, $sequence, $sourceToken, $nextSourceToken, $cursor);
        $taggedNext = self::tagNextNext205($nextRows, $nextVisible, $sourceToken, $nextSourceToken, $cursor, $blocked);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_source_sequence_next205'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_source_sequence_next205'],
        ));

        return [
            'status_next205' => self::statusNext205($baseVisible, $sourceFenceClear, $sourceTokenMatches, $nextSourceTokenMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next205' => $baseVisible,
            'current_source_sequence_token_next205' => $sourceToken,
            'expected_current_source_sequence_token_next205' => $expectedSourceToken,
            'current_source_sequence_token_matches_next205' => $sourceTokenMatches,
            'next_source_sequence_token_next205' => $nextSourceToken,
            'expected_next_source_sequence_token_next205' => $expectedNextSourceToken,
            'next_source_sequence_token_matches_next205' => $nextSourceTokenMatches,
            'source_sequence_cursor_next205' => $cursor,
            'required_current_source_sequence_next205' => $sequence,
            'acknowledged_current_source_sequence_next205' => $acknowledged,
            'missing_current_source_sequence_next205' => $missing,
            'unexpected_current_source_sequence_next205' => $unexpected,
            'require_source_sequence_order_next205' => $requireOrder,
            'current_source_sequence_order_matches_next205' => $orderMatches,
            'current_source_sequence_fence_clear_next205' => $sourceFenceClear,
            'next_source_visible_after_source_sequence_next205' => $nextVisible,
            'current_source_sequence_rows_next205' => $taggedCurrent,
            'attempted_next_source_sequence_rows_next205' => $taggedNext,
            'visible_returning_rows_next205' => $visibleRows,
            'held_next_source_rows_next205' => $heldRows,
            'visible_returning_payloads_next205' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next205' => array_column($heldRows, 'returning'),
            'current_source_sequence_row_count_next205' => count($taggedCurrent),
            'attempted_next_source_sequence_row_count_next205' => count($taggedNext),
            'visible_row_count_next205' => count($visibleRows),
            'held_next_row_count_next205' => count($heldRows),
            'blocked_reasons_next205' => $blocked,
            'source_sequence_plan_next205' => [
                'base_next_source_visible' => $baseVisible,
                'required_sequence' => $sequence,
                'acknowledged_sequence' => $acknowledged,
                'missing_sequence' => $missing,
                'unexpected_sequence' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'current_source_token_matches' => $sourceTokenMatches,
                'next_source_token_matches' => $nextSourceTokenMatches,
                'source_sequence_fence_clear' => $sourceFenceClear,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-sequence'
                    : 'hold-next-source-until-current-source-sequence',
            ],
            'yield_boundary_next205' => $nextVisible
                ? 'recursive-view-returning-next205-current-source-sequence-then-next'
                : 'recursive-view-returning-next205-current-source-sequence-fences-next',
            'dependency_closure_next205' => 'no new support component needed; reuses native recursive view RETURNING current-source sequence fencing',
            'dependencies_next205' => array_values(array_unique(array_merge($base['dependencies_next203'], [
                'sqlite-trigger-recursive-view-returning-current-source-next205',
                'sqlite-returning-current-source-sequence-fence',
                'wordpress-recursive-view-returning-current-source-next205',
            ]))),
            'non_overlap_next205' => 'adds a source-sequence fence after next203 generation receipts; avoids accepted next203 generation handoff, next196 child drain, next195 receipt fence, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sequenceNext205(array $rows, string $sourceToken, string $cursor): array
    {
        $sequence = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceToken,
                $cursor,
                (string) ($row['current_generation_receipt_next203'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ''),
            ];
            $sequence[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
        }

        return $sequence;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSequenceNext205(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_sequence_next205'] ?? false) === true) {
            return $required;
        }

        return self::sequenceListNext205($options['acknowledged_current_source_sequence_next205'] ?? [], 'acknowledged current source sequence');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sequenceListNext205(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} contains a malformed sequence token");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext205(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $sequence
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentNextSourceRows(array $rows, array $sequence, string $sourceToken, string $nextSourceToken, string $cursor): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_sequence_phase_next205' => 'current',
                'current_source_sequence_token_next205' => $sourceToken,
                'next_source_sequence_token_next205' => $nextSourceToken,
                'source_sequence_cursor_next205' => $cursor,
                'current_source_sequence_next205' => $sequence[$index] ?? null,
                'visible_after_source_sequence_next205' => true,
                'held_by_source_sequence_reasons_next205' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextNext205(array $rows, bool $visible, string $sourceToken, string $nextSourceToken, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_sequence_phase_next205' => 'next',
                'current_source_sequence_token_next205' => $sourceToken,
                'next_source_sequence_token_next205' => $nextSourceToken,
                'source_sequence_cursor_next205' => $cursor,
                'current_source_sequence_next205' => null,
                'visible_after_source_sequence_next205' => $visible,
                'held_by_source_sequence_reasons_next205' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext205(
        mixed $baseReasons,
        bool $baseVisible,
        bool $sourceFenceClear,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $sourceTokenMatches,
        bool $nextSourceTokenMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next205 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next203-current-generation-not-visible';
        }
        if (!$sourceTokenMatches) {
            $reasons[] = 'current-source-sequence-token-mismatch';
        }
        if (!$nextSourceTokenMatches) {
            $reasons[] = 'next-source-sequence-token-mismatch';
        }
        if (!$sourceFenceClear) {
            if ($missing !== []) {
                $reasons[] = 'current-source-sequence-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-sequence-unexpected';
            }
            if ($requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-sequence-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext205(bool $baseVisible, bool $sourceFenceClear, bool $sourceTokenMatches, bool $nextSourceTokenMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next205-source-sequence-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next205-base-held';
        }
        if (!$sourceTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next205-current-source-held';
        }
        if (!$nextSourceTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next205-next-source-held';
        }
        if (!$sourceFenceClear) {
            return 'trigger-recursive-view-returning-current-source-next205-sequence-held';
        }

        return 'trigger-recursive-view-returning-current-source-next205-held';
    }

    private static function tokenNext205(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext206(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext203(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsNext206($base['current_generation_rows_next203'] ?? [], 'current generation rows');
        $nextRows = self::rowsNext206($base['attempted_next_generation_rows_next203'] ?? [], 'attempted next generation rows');
        $sourceToken = self::tokenNext206((string) ($options['yield_current_source_token_next206'] ?? 'wp.current.recursive.returning.source.206'), 'current source token');
        $cursor = self::tokenNext206((string) ($options['yield_current_cursor_next206'] ?? 'wp.returning.current.cursor.206'), 'current cursor');
        $statementToken = self::tokenNext206((string) ($options['yield_statement_token_next206'] ?? 'wp.recursive.view.returning.statement.206'), 'statement token');
        $batchKeys = self::batchKeysNext206($currentRows, $sourceToken, $cursor, $statementToken);
        $watermark = self::watermarkNext206($batchKeys, $sourceToken, $cursor, $statementToken);
        $expectedWatermark = self::tokenNext206((string) ($options['expected_yield_watermark_next206'] ?? $watermark), 'expected watermark');
        $acknowledgedWatermark = self::tokenNext206((string) ($options['acknowledged_yield_watermark_next206'] ?? $watermark), 'acknowledged watermark');
        $expectedCount = self::nonNegativeIntNext206($options['expected_yield_row_count_next206'] ?? count($currentRows), 'expected row count');
        $baseVisible = (bool) ($base['next_source_visible_after_current_generation_next203'] ?? false);
        $watermarkMatches = hash_equals($watermark, $expectedWatermark) && hash_equals($watermark, $acknowledgedWatermark);
        $rowCountMatches = count($currentRows) === $expectedCount;
        $nextVisible = $baseVisible && $watermarkMatches && $rowCountMatches;
        $blockedReasons = self::blockedReasonsNext206(
            $base['blocked_reasons_next203'] ?? [],
            $baseVisible,
            $watermarkMatches,
            $rowCountMatches,
        );

        $taggedCurrent = self::tagCurrentRowsNext206($currentRows, $batchKeys, $watermark, $sourceToken, $cursor, $statementToken);
        $taggedNext = self::tagNextRowsNext206($nextRows, $nextVisible, $blockedReasons, $watermark, $sourceToken, $cursor, $statementToken);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_yield_watermark_next206'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_yield_watermark_next206'],
        ));

        return [
            'status_next206' => self::statusNext206($nextVisible, $baseVisible, $watermarkMatches, $rowCountMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next206' => $baseVisible,
            'yield_current_source_token_next206' => $sourceToken,
            'yield_current_cursor_next206' => $cursor,
            'yield_statement_token_next206' => $statementToken,
            'yield_batch_keys_next206' => $batchKeys,
            'yield_watermark_next206' => $watermark,
            'expected_yield_watermark_next206' => $expectedWatermark,
            'acknowledged_yield_watermark_next206' => $acknowledgedWatermark,
            'yield_watermark_matches_next206' => $watermarkMatches,
            'yield_row_count_next206' => count($currentRows),
            'expected_yield_row_count_next206' => $expectedCount,
            'yield_row_count_matches_next206' => $rowCountMatches,
            'next_source_visible_after_yield_watermark_next206' => $nextVisible,
            'current_source_rows_next206' => $taggedCurrent,
            'attempted_next_source_rows_next206' => $taggedNext,
            'visible_returning_rows_next206' => $visibleRows,
            'held_next_source_rows_next206' => $heldRows,
            'visible_returning_payloads_next206' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next206' => array_column($heldRows, 'returning'),
            'blocked_reasons_next206' => $blockedReasons,
            'yield_watermark_plan_next206' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'base_next_source_visible' => $baseVisible,
                'watermark_matches' => $watermarkMatches,
                'row_count_matches' => $rowCountMatches,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-yield-watermark'
                    : 'hold-next-source-until-current-yield-watermark',
            ],
            'yield_boundary_next206' => $nextVisible
                ? 'recursive-view-returning-next206-current-watermark-then-next'
                : 'recursive-view-returning-next206-current-watermark-fences-next',
            'dependency_closure_next206' => 'no new support component needed; reuses next203 recursive view RETURNING generation receipts and adds current-source yield watermark fencing',
            'dependencies_next206' => array_values(array_unique(array_merge($base['dependencies_next203'], [
                'sqlite-trigger-recursive-view-returning-current-source-next206',
                'sqlite-returning-current-source-yield-watermark',
                'wordpress-recursive-view-returning-current-source-next206',
            ]))),
            'non_overlap_next206' => 'adds current-source yield watermark admission after next203 generation receipts; avoids accepted next203 generation handoff, next196 recursive child drain, next195 receipt fences, next191 fingerprint fencing, row-value RETURNING, DML trigger conflicts, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function batchKeysNext206(array $rows, string $sourceToken, string $cursor, string $statementToken): array
    {
        $keys = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'];
            $parts = [
                $sourceToken,
                $cursor,
                $statementToken,
                (string) ($row['current_generation_receipt_next203'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($payload['name'] ?? $payload['option_name'] ?? $row['returning_option_name'] ?? ''),
            ];
            $keys[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
        }

        return $keys;
    }

    /**
     * @param list<string> $batchKeys
     */
    private static function watermarkNext206(array $batchKeys, string $sourceToken, string $cursor, string $statementToken): string
    {
        if ($batchKeys === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next206 current source batch is empty');
        }

        return substr(hash('sha256', $sourceToken . '|' . $cursor . '|' . $statementToken . '|' . implode(',', $batchKeys)), 0, 32);
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext206(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $batchKeys
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRowsNext206(array $rows, array $batchKeys, string $watermark, string $sourceToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'yield_phase_next206' => 'current',
                'yield_current_source_token_next206' => $sourceToken,
                'yield_current_cursor_next206' => $cursor,
                'yield_statement_token_next206' => $statementToken,
                'yield_batch_key_next206' => $batchKeys[$index] ?? null,
                'yield_watermark_next206' => $watermark,
                'visible_after_yield_watermark_next206' => true,
                'held_by_yield_watermark_reasons_next206' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blockedReasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRowsNext206(array $rows, bool $visible, array $blockedReasons, string $watermark, string $sourceToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'yield_phase_next206' => 'next',
                'yield_current_source_token_next206' => $sourceToken,
                'yield_current_cursor_next206' => $cursor,
                'yield_statement_token_next206' => $statementToken,
                'yield_batch_key_next206' => null,
                'yield_watermark_next206' => $watermark,
                'visible_after_yield_watermark_next206' => $visible,
                'held_by_yield_watermark_reasons_next206' => $visible ? [] : $blockedReasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasonsNext206(mixed $baseReasons, bool $baseVisible, bool $watermarkMatches, bool $rowCountMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next206 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next203-generation-handoff-held';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'current-yield-watermark-mismatch';
        }
        if (!$rowCountMatches) {
            $reasons[] = 'current-yield-row-count-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext206(bool $nextVisible, bool $baseVisible, bool $watermarkMatches, bool $rowCountMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next206-watermark-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next206-base-held';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-returning-current-source-next206-watermark-held';
        }
        if (!$rowCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next206-row-count-held';
        }

        return 'trigger-recursive-view-returning-current-source-next206-held';
    }

    private static function tokenNext206(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} is malformed");
        }

        return $token;
    }

    private static function nonNegativeIntNext206(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} must be a non-negative integer");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext207(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext206(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsNext207($base['current_source_rows_next206'] ?? [], 'current source rows');
        $nextRows = self::rowsNext207($base['attempted_next_source_rows_next206'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_yield_watermark_next206'] ?? false);
        $drainToken = self::tokenNext207((string) ($options['current_returning_drain_token_next207'] ?? 'wp.current.returning.drain.207'), 'current drain token');
        $expectedDrainToken = self::tokenNext207((string) ($options['expected_current_returning_drain_token_next207'] ?? $drainToken), 'expected current drain token');
        $cursor = self::tokenNext207((string) ($options['current_returning_cursor_next207'] ?? 'wp.current.returning.cursor.207'), 'current returning cursor');
        $statementToken = self::tokenNext207((string) ($options['returning_statement_token_next207'] ?? 'wp.recursive.view.returning.statement.207'), 'statement token');
        $drainKeys = self::drainKeysNext207($currentRows, $drainToken, $cursor, $statementToken);
        $acknowledgedDrainKeys = self::acknowledgedDrainKeysNext207($options, $drainKeys);
        $requireOrder = (bool) ($options['require_returning_drain_order_next207'] ?? true);
        $missing = array_values(array_diff($drainKeys, $acknowledgedDrainKeys));
        $unexpected = array_values(array_diff($acknowledgedDrainKeys, $drainKeys));
        $orderMatches = !$requireOrder || $drainKeys === $acknowledgedDrainKeys;
        $drainTokenMatches = hash_equals($drainToken, $expectedDrainToken);
        $expectedCount = self::nonNegativeIntNext207($options['expected_current_returning_drain_count_next207'] ?? count($currentRows), 'expected current drain count');
        $countMatches = count($currentRows) === $expectedCount;
        $drainClear = $drainKeys !== []
            && $missing === []
            && $unexpected === []
            && $orderMatches
            && $drainTokenMatches
            && $countMatches;
        $nextVisible = $baseVisible && $drainClear;
        $blocked = self::blockedReasonsNext207(
            $base['blocked_reasons_next206'] ?? [],
            $baseVisible,
            $drainTokenMatches,
            $countMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagCurrentNextDrainRows($currentRows, $drainKeys, $drainToken, $cursor, $statementToken);
        $taggedNext = self::tagNextNext207($nextRows, $nextVisible, $blocked, $drainToken, $cursor, $statementToken);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_drain_next207'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_drain_next207'],
        ));

        return [
            'status_next207' => self::statusNext207($nextVisible, $baseVisible, $drainTokenMatches, $countMatches, $missing, $unexpected, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next207' => $baseVisible,
            'current_returning_drain_token_next207' => $drainToken,
            'expected_current_returning_drain_token_next207' => $expectedDrainToken,
            'current_returning_drain_token_matches_next207' => $drainTokenMatches,
            'current_returning_cursor_next207' => $cursor,
            'returning_statement_token_next207' => $statementToken,
            'current_returning_drain_keys_next207' => $drainKeys,
            'acknowledged_current_returning_drain_keys_next207' => $acknowledgedDrainKeys,
            'missing_current_returning_drain_keys_next207' => $missing,
            'unexpected_current_returning_drain_keys_next207' => $unexpected,
            'require_returning_drain_order_next207' => $requireOrder,
            'current_returning_drain_order_matches_next207' => $orderMatches,
            'current_returning_drain_count_next207' => count($currentRows),
            'expected_current_returning_drain_count_next207' => $expectedCount,
            'current_returning_drain_count_matches_next207' => $countMatches,
            'current_returning_drain_clear_next207' => $drainClear,
            'next_source_visible_after_current_drain_next207' => $nextVisible,
            'current_returning_drain_rows_next207' => $taggedCurrent,
            'attempted_next_returning_drain_rows_next207' => $taggedNext,
            'visible_returning_rows_next207' => $visibleRows,
            'held_next_source_rows_next207' => $heldRows,
            'visible_returning_payloads_next207' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next207' => array_column($heldRows, 'returning'),
            'blocked_reasons_next207' => $blocked,
            'current_drain_plan_next207' => [
                'base_next_source_visible' => $baseVisible,
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'drain_token_matches' => $drainTokenMatches,
                'drain_count_matches' => $countMatches,
                'missing_drain_keys' => $missing,
                'unexpected_drain_keys' => $unexpected,
                'drain_order_matches' => $orderMatches,
                'drain_clear' => $drainClear,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-drain'
                    : 'hold-next-source-until-current-returning-drain',
            ],
            'yield_boundary_next207' => $nextVisible
                ? 'recursive-view-returning-next207-current-drain-then-next'
                : 'recursive-view-returning-next207-current-drain-fences-next',
            'dependency_closure_next207' => 'no new support component needed; reuses next206 recursive view RETURNING watermark rows and adds current RETURNING drain fencing',
            'dependencies_next207' => array_values(array_unique(array_merge($base['dependencies_next206'], [
                'sqlite-trigger-recursive-view-returning-current-source-next207',
                'sqlite-returning-current-source-drain-fence',
                'wordpress-recursive-view-returning-current-source-next207',
            ]))),
            'non_overlap_next207' => 'adds current RETURNING drain admission after next206 yield watermark; avoids accepted next206 watermark, next205 sequence, next203 generation handoff, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function drainKeysNext207(array $rows, string $drainToken, string $cursor, string $statementToken): array
    {
        $keys = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'];
            $parts = [
                $drainToken,
                $cursor,
                $statementToken,
                (string) ($row['yield_watermark_next206'] ?? ''),
                (string) ($row['yield_batch_key_next206'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($payload['name'] ?? $payload['option_name'] ?? $row['returning_option_name'] ?? ''),
            ];
            $keys[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
        }

        return $keys;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedDrainKeysNext207(array $options, array $required): array
    {
        if (($options['auto_ack_current_returning_drain_next207'] ?? false) === true) {
            return $required;
        }

        return self::drainKeyListNext207($options['acknowledged_current_returning_drain_keys_next207'] ?? [], 'acknowledged current drain keys');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function drainKeyListNext207(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} contains a malformed drain key");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext207(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $drainKeys
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentNextDrainRows(array $rows, array $drainKeys, string $drainToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'drain_phase_next207' => 'current',
                'current_returning_drain_token_next207' => $drainToken,
                'current_returning_cursor_next207' => $cursor,
                'returning_statement_token_next207' => $statementToken,
                'current_returning_drain_key_next207' => $drainKeys[$index] ?? null,
                'visible_after_current_drain_next207' => true,
                'held_by_current_drain_reasons_next207' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blocked
     * @return list<array<string,mixed>>
     */
    private static function tagNextNext207(array $rows, bool $visible, array $blocked, string $drainToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'drain_phase_next207' => 'next',
                'current_returning_drain_token_next207' => $drainToken,
                'current_returning_cursor_next207' => $cursor,
                'returning_statement_token_next207' => $statementToken,
                'current_returning_drain_key_next207' => null,
                'visible_after_current_drain_next207' => $visible,
                'held_by_current_drain_reasons_next207' => $visible ? [] : $blocked,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext207(
        mixed $baseReasons,
        bool $baseVisible,
        bool $drainTokenMatches,
        bool $countMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next207 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next206-yield-watermark-held';
        }
        if (!$drainTokenMatches) {
            $reasons[] = 'current-returning-drain-token-mismatch';
        }
        if (!$countMatches) {
            $reasons[] = 'current-returning-drain-count-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-drain-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-drain-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-returning-drain-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext207(
        bool $nextVisible,
        bool $baseVisible,
        bool $drainTokenMatches,
        bool $countMatches,
        array $missing,
        array $unexpected,
        bool $orderMatches,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next207-drain-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next207-base-held';
        }
        if (!$drainTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next207-token-held';
        }
        if (!$countMatches) {
            return 'trigger-recursive-view-returning-current-source-next207-count-held';
        }
        if ($missing !== [] || $unexpected !== [] || !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next207-drain-held';
        }

        return 'trigger-recursive-view-returning-current-source-next207-held';
    }

    private static function tokenNext207(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} is malformed");
        }

        return $token;
    }

    private static function nonNegativeIntNext207(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} must be a non-negative integer");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext208(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext206(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsNext208($base['current_source_rows_next206'] ?? [], 'current source rows');
        $nextRows = self::rowsNext208($base['attempted_next_source_rows_next206'] ?? [], 'attempted next source rows');
        $currentCursor = self::tokenNext208((string) ($base['yield_current_cursor_next206'] ?? ''), 'base current cursor');
        $closeCursor = self::tokenNext208((string) ($options['close_current_cursor_next208'] ?? $currentCursor), 'close current cursor');
        $expectedCloseCursor = self::tokenNext208((string) ($options['expected_close_current_cursor_next208'] ?? $currentCursor), 'expected close cursor');
        $closeStatement = self::tokenNext208((string) ($options['close_statement_token_next208'] ?? 'wp.recursive.view.returning.close.208'), 'close statement token');
        $closedWatermark = self::closeWatermarkNext208($currentRows, $currentCursor, $closeCursor, $closeStatement, (string) ($base['yield_watermark_next206'] ?? ''));
        $expectedWatermark = self::tokenNext208((string) ($options['expected_closed_yield_watermark_next208'] ?? $closedWatermark), 'expected closed watermark');
        $cursorMatches = hash_equals($currentCursor, $closeCursor) && hash_equals($currentCursor, $expectedCloseCursor);
        $watermarkMatches = hash_equals($closedWatermark, $expectedWatermark);
        $baseVisible = (bool) ($base['next_source_visible_after_yield_watermark_next206'] ?? false);
        $nextVisible = $baseVisible && $cursorMatches && $watermarkMatches;
        $blockedReasons = self::blockedReasonsNext208(
            $base['blocked_reasons_next206'] ?? [],
            $baseVisible,
            $cursorMatches,
            $watermarkMatches,
        );

        $taggedCurrent = self::tagRowsNext208($currentRows, 'current', true, [], $closeCursor, $closeStatement, $closedWatermark);
        $taggedNext = self::tagRowsNext208($nextRows, 'next', $nextVisible, $blockedReasons, $closeCursor, $closeStatement, $closedWatermark);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_cursor_close_next208'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_cursor_close_next208'],
        ));

        return [
            'status_next208' => self::statusNext208($nextVisible, $baseVisible, $cursorMatches, $watermarkMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next208' => $baseVisible,
            'yield_current_cursor_next208' => $currentCursor,
            'close_current_cursor_next208' => $closeCursor,
            'expected_close_current_cursor_next208' => $expectedCloseCursor,
            'close_statement_token_next208' => $closeStatement,
            'closed_yield_watermark_next208' => $closedWatermark,
            'expected_closed_yield_watermark_next208' => $expectedWatermark,
            'current_cursor_close_matches_next208' => $cursorMatches,
            'closed_yield_watermark_matches_next208' => $watermarkMatches,
            'next_source_visible_after_current_cursor_close_next208' => $nextVisible,
            'current_source_rows_next208' => $taggedCurrent,
            'attempted_next_source_rows_next208' => $taggedNext,
            'visible_returning_rows_next208' => $visibleRows,
            'held_next_source_rows_next208' => $heldRows,
            'visible_returning_payloads_next208' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next208' => array_column($heldRows, 'returning'),
            'blocked_reasons_next208' => $blockedReasons,
            'current_cursor_close_plan_next208' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'base_next_source_visible' => $baseVisible,
                'cursor_matches' => $cursorMatches,
                'closed_watermark_matches' => $watermarkMatches,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-cursor-close'
                    : 'hold-next-source-until-current-returning-cursor-close',
            ],
            'yield_boundary_next208' => $nextVisible
                ? 'recursive-view-returning-next208-current-cursor-closed-then-next'
                : 'recursive-view-returning-next208-current-cursor-close-fences-next',
            'dependency_closure_next208' => 'no new support component needed; reuses next206 current-source yield watermark and adds current RETURNING cursor close fencing',
            'dependencies_next208' => array_values(array_unique(array_merge($base['dependencies_next206'], [
                'sqlite-trigger-recursive-view-returning-current-source-next208',
                'sqlite-returning-current-source-cursor-close-fence',
                'wordpress-recursive-view-returning-current-source-next208',
            ]))),
            'non_overlap_next208' => 'adds current RETURNING cursor close fencing after next206 yield watermark; avoids accepted next206 watermark, next203 generation handoff, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext208(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next208 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next208 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function closeWatermarkNext208(array $rows, string $currentCursor, string $closeCursor, string $closeStatement, string $yieldWatermark): string
    {
        if ($rows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next208 current source rows are empty');
        }

        $parts = [$currentCursor, $closeCursor, $closeStatement, $yieldWatermark, (string) count($rows)];
        foreach ($rows as $row) {
            $parts[] = (string) ($row['yield_batch_key_next206'] ?? '');
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext208(array $rows, string $phase, bool $visible, array $reasons, string $closeCursor, string $closeStatement, string $closedWatermark): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'cursor_close_phase_next208' => $phase,
                'close_current_cursor_next208' => $closeCursor,
                'close_statement_token_next208' => $closeStatement,
                'closed_yield_watermark_next208' => $closedWatermark,
                'visible_after_current_cursor_close_next208' => $visible,
                'held_by_current_cursor_close_reasons_next208' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasonsNext208(mixed $baseReasons, bool $baseVisible, bool $cursorMatches, bool $watermarkMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next208 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next206-yield-watermark-held';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-returning-cursor-close-mismatch';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'closed-yield-watermark-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext208(bool $nextVisible, bool $baseVisible, bool $cursorMatches, bool $watermarkMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next208-cursor-closed';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next208-base-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next208-cursor-held';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-returning-current-source-next208-watermark-held';
        }

        return 'trigger-recursive-view-returning-current-source-next208-held';
    }

    private static function tokenNext208(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next208 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext209(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext203(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $drainSource = self::tokenNext209((string) ($options['current_source_drain_token_next209'] ?? 'wp.current.source.drain.209'), 'current source drain token');
        $viewCookie = self::tokenNext209((string) ($options['current_view_cookie_next209'] ?? (string) ($currentView['source'] ?? 'current-view-cookie-209')), 'current view cookie');
        $triggerCookie = self::tokenNext209((string) ($options['current_trigger_cookie_next209'] ?? (string) ($currentView['trigger_source'] ?? 'current-trigger-cookie-209')), 'current trigger cookie');
        $expectedViewCookie = self::tokenNext209((string) ($options['expected_current_view_cookie_next209'] ?? $viewCookie), 'expected current view cookie');
        $expectedTriggerCookie = self::tokenNext209((string) ($options['expected_current_trigger_cookie_next209'] ?? $triggerCookie), 'expected current trigger cookie');
        $baseVisible = (bool) ($base['next_source_visible_after_current_generation_next203'] ?? false);

        $requiredWatermarks = self::watermarksNext209(
            self::rowsNext209($base['current_generation_rows_next203'] ?? [], 'current generation rows'),
            $drainSource,
            $viewCookie,
            $triggerCookie,
        );
        $acknowledgedWatermarks = self::acknowledgedWatermarksNext209($options, $requiredWatermarks);
        $missingWatermarks = array_values(array_diff($requiredWatermarks, $acknowledgedWatermarks));
        $unexpectedWatermarks = array_values(array_diff($acknowledgedWatermarks, $requiredWatermarks));
        $viewCookieMatches = hash_equals($viewCookie, $expectedViewCookie);
        $triggerCookieMatches = hash_equals($triggerCookie, $expectedTriggerCookie);
        $drainComplete = $requiredWatermarks !== []
            && $missingWatermarks === []
            && $unexpectedWatermarks === [];
        $nextVisible = $baseVisible && $drainComplete && $viewCookieMatches && $triggerCookieMatches;
        $blockedReasons = self::blockedReasonsNext209(
            $base['blocked_reasons_next203'] ?? [],
            $baseVisible,
            $drainComplete,
            $missingWatermarks,
            $unexpectedWatermarks,
            $viewCookieMatches,
            $triggerCookieMatches,
        );

        $currentRows = self::tagCurrentRowsNext209(
            self::rowsNext209($base['current_generation_rows_next203'] ?? [], 'current generation rows'),
            $requiredWatermarks,
            $drainSource,
            $viewCookie,
            $triggerCookie,
        );
        $nextRows = self::tagNextRowsNext209(
            self::rowsNext209($base['attempted_next_generation_rows_next203'] ?? [], 'attempted next generation rows'),
            $nextVisible,
            $drainSource,
            $viewCookie,
            $triggerCookie,
            $blockedReasons,
        );
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_drain_next209'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_drain_next209'],
        ));

        return [
            'status_next209' => self::statusNext209($baseVisible, $drainComplete, $viewCookieMatches, $triggerCookieMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next209' => $baseVisible,
            'current_source_drain_token_next209' => $drainSource,
            'current_view_cookie_next209' => $viewCookie,
            'expected_current_view_cookie_next209' => $expectedViewCookie,
            'current_view_cookie_matches_next209' => $viewCookieMatches,
            'current_trigger_cookie_next209' => $triggerCookie,
            'expected_current_trigger_cookie_next209' => $expectedTriggerCookie,
            'current_trigger_cookie_matches_next209' => $triggerCookieMatches,
            'required_current_source_watermarks_next209' => $requiredWatermarks,
            'acknowledged_current_source_watermarks_next209' => $acknowledgedWatermarks,
            'missing_current_source_watermarks_next209' => $missingWatermarks,
            'unexpected_current_source_watermarks_next209' => $unexpectedWatermarks,
            'current_source_drain_complete_next209' => $drainComplete,
            'next_source_visible_after_current_source_drain_next209' => $nextVisible,
            'current_source_rows_next209' => $currentRows,
            'attempted_next_source_rows_next209' => $nextRows,
            'visible_returning_rows_next209' => $visibleRows,
            'held_next_source_rows_next209' => $heldRows,
            'visible_returning_payloads_next209' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next209' => array_column($heldRows, 'returning'),
            'current_source_row_count_next209' => count($currentRows),
            'attempted_next_source_row_count_next209' => count($nextRows),
            'visible_row_count_next209' => count($visibleRows),
            'held_next_row_count_next209' => count($heldRows),
            'blocked_reasons_next209' => $blockedReasons,
            'current_source_drain_plan_next209' => [
                'base_next_source_visible' => $baseVisible,
                'required_watermarks' => $requiredWatermarks,
                'acknowledged_watermarks' => $acknowledgedWatermarks,
                'missing_watermarks' => $missingWatermarks,
                'unexpected_watermarks' => $unexpectedWatermarks,
                'view_cookie_matches' => $viewCookieMatches,
                'trigger_cookie_matches' => $triggerCookieMatches,
                'drain_complete' => $drainComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-drain'
                    : 'hold-next-source-until-current-source-drain',
            ],
            'yield_boundary_next209' => $nextVisible
                ? 'recursive-view-returning-next209-current-source-drain-then-next'
                : 'recursive-view-returning-next209-current-source-drain-fences-next',
            'dependency_closure_next209' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-drain-watermarks',
            'dependencies_next209' => array_values(array_unique(array_merge($base['dependencies_next203'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next209',
                'sqlite-returning-current-source-drain-watermark',
                'wordpress-recursive-view-returning-current-source-next209',
            ]))),
            'non_overlap_next209' => 'adds current-source drain watermarks after next203 generation handoff; avoids accepted trigger recursive view RETURNING next172-next203 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function watermarksNext209(array $rows, string $drainSource, string $viewCookie, string $triggerCookie): array
    {
        $watermarks = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $drainSource,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_generation_receipt_next203'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
            ];
            $watermarks[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
        }

        return $watermarks;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedWatermarksNext209(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_watermarks_next209'] ?? false) === true) {
            return $required;
        }

        return self::watermarkListNext209($options['acknowledged_current_source_watermarks_next209'] ?? [], 'acknowledged current source watermarks');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function watermarkListNext209(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} contain a malformed watermark");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext209(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $watermarks
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRowsNext209(array $rows, array $watermarks, string $drainSource, string $viewCookie, string $triggerCookie): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_drain_phase_next209' => 'current',
                'current_source_drain_token_next209' => $drainSource,
                'current_view_cookie_next209' => $viewCookie,
                'current_trigger_cookie_next209' => $triggerCookie,
                'current_source_watermark_next209' => $watermarks[$index] ?? null,
                'visible_after_current_source_drain_next209' => true,
                'held_by_current_source_drain_reasons_next209' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRowsNext209(array $rows, bool $visible, string $drainSource, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_drain_phase_next209' => 'next',
                'current_source_drain_token_next209' => $drainSource,
                'current_view_cookie_next209' => $viewCookie,
                'current_trigger_cookie_next209' => $triggerCookie,
                'current_source_watermark_next209' => null,
                'visible_after_current_source_drain_next209' => $visible,
                'held_by_current_source_drain_reasons_next209' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext209(
        mixed $baseReasons,
        bool $baseVisible,
        bool $drainComplete,
        array $missing,
        array $unexpected,
        bool $viewCookieMatches,
        bool $triggerCookieMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next209 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next203-current-generation-not-published';
        }
        if (!$drainComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-watermark-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-watermark-unexpected';
            }
        }
        if (!$viewCookieMatches) {
            $reasons[] = 'current-view-cookie-mismatch';
        }
        if (!$triggerCookieMatches) {
            $reasons[] = 'current-trigger-cookie-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext209(bool $baseVisible, bool $drainComplete, bool $viewCookieMatches, bool $triggerCookieMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next209-drain-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next209-base-held';
        }
        if (!$drainComplete) {
            return 'trigger-recursive-view-returning-current-source-next209-drain-held';
        }
        if (!$viewCookieMatches) {
            return 'trigger-recursive-view-returning-current-source-next209-view-cookie-held';
        }
        if (!$triggerCookieMatches) {
            return 'trigger-recursive-view-returning-current-source-next209-trigger-cookie-held';
        }

        return 'trigger-recursive-view-returning-current-source-next209-held';
    }

    private static function tokenNext209(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext210(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext209(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $sequenceToken = self::tokenNext210((string) ($options['current_source_sequence_token_next210'] ?? 'wp.current.source.sequence.210'), 'current source sequence token');
        $handoffCursor = self::tokenNext210((string) ($options['sequence_handoff_cursor_next210'] ?? 'wp.returning.sequence.cursor.210'), 'sequence handoff cursor');
        $expectedHandoffCursor = self::tokenNext210((string) ($options['expected_sequence_handoff_cursor_next210'] ?? $handoffCursor), 'expected sequence handoff cursor');
        $viewCookie = self::tokenNext210((string) ($base['current_view_cookie_next209'] ?? ''), 'base view cookie');
        $triggerCookie = self::tokenNext210((string) ($base['current_trigger_cookie_next209'] ?? ''), 'base trigger cookie');
        $expectedSourceSignature = self::tokenNext210((string) ($options['expected_current_source_signature_next210'] ?? self::sourceSignatureNext210($viewCookie, $triggerCookie, $sequenceToken)), 'expected current source signature');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_drain_next209'] ?? false);

        $currentRows = self::rowsNext210($base['current_source_rows_next209'] ?? [], 'current source rows');
        $nextRows = self::rowsNext210($base['attempted_next_source_rows_next209'] ?? [], 'attempted next source rows');
        $requiredSequence = self::sequenceNext210($currentRows, $sequenceToken, $handoffCursor, $viewCookie, $triggerCookie);
        $acknowledgedSequence = self::acknowledgedSequenceNext210($options, $requiredSequence);
        $missingSequence = array_values(array_diff($requiredSequence, $acknowledgedSequence));
        $unexpectedSequence = array_values(array_diff($acknowledgedSequence, $requiredSequence));
        $requireOrder = (bool) ($options['require_current_source_sequence_order_next210'] ?? true);
        $orderMatches = !$requireOrder || $requiredSequence === $acknowledgedSequence;
        $cursorMatches = hash_equals($handoffCursor, $expectedHandoffCursor);
        $sourceSignature = self::sourceSignatureNext210($viewCookie, $triggerCookie, $sequenceToken);
        $sourceSignatureMatches = hash_equals($sourceSignature, $expectedSourceSignature);
        $sequenceComplete = $requiredSequence !== []
            && $missingSequence === []
            && $unexpectedSequence === []
            && $orderMatches;
        $nextVisible = $baseVisible && $sequenceComplete && $cursorMatches && $sourceSignatureMatches;
        $blockedReasons = self::blockedReasonsNext210(
            $base['blocked_reasons_next209'] ?? [],
            $baseVisible,
            $sequenceComplete,
            $missingSequence,
            $unexpectedSequence,
            $requireOrder,
            $orderMatches,
            $cursorMatches,
            $sourceSignatureMatches,
        );

        $taggedCurrent = self::tagCurrentRowsNext210($currentRows, $requiredSequence, $sequenceToken, $handoffCursor, $sourceSignature);
        $taggedNext = self::tagNextRowsNext210($nextRows, $nextVisible, $sequenceToken, $handoffCursor, $sourceSignature, $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_sequence_next210'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_sequence_next210'],
        ));

        return [
            'status_next210' => self::statusNext210($baseVisible, $sequenceComplete, $cursorMatches, $sourceSignatureMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next210' => $baseVisible,
            'current_source_sequence_token_next210' => $sequenceToken,
            'sequence_handoff_cursor_next210' => $handoffCursor,
            'expected_sequence_handoff_cursor_next210' => $expectedHandoffCursor,
            'sequence_handoff_cursor_matches_next210' => $cursorMatches,
            'current_source_signature_next210' => $sourceSignature,
            'expected_current_source_signature_next210' => $expectedSourceSignature,
            'current_source_signature_matches_next210' => $sourceSignatureMatches,
            'required_current_source_sequence_next210' => $requiredSequence,
            'acknowledged_current_source_sequence_next210' => $acknowledgedSequence,
            'missing_current_source_sequence_next210' => $missingSequence,
            'unexpected_current_source_sequence_next210' => $unexpectedSequence,
            'require_current_source_sequence_order_next210' => $requireOrder,
            'current_source_sequence_order_matches_next210' => $orderMatches,
            'current_source_sequence_complete_next210' => $sequenceComplete,
            'next_source_visible_after_current_source_sequence_next210' => $nextVisible,
            'current_source_rows_next210' => $taggedCurrent,
            'attempted_next_source_rows_next210' => $taggedNext,
            'visible_returning_rows_next210' => $visibleRows,
            'held_next_source_rows_next210' => $heldRows,
            'visible_returning_payloads_next210' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next210' => array_column($heldRows, 'returning'),
            'current_source_row_count_next210' => count($taggedCurrent),
            'attempted_next_source_row_count_next210' => count($taggedNext),
            'visible_row_count_next210' => count($visibleRows),
            'held_next_row_count_next210' => count($heldRows),
            'blocked_reasons_next210' => $blockedReasons,
            'current_source_sequence_plan_next210' => [
                'base_next_source_visible' => $baseVisible,
                'source_signature' => $sourceSignature,
                'source_signature_matches' => $sourceSignatureMatches,
                'required_sequence' => $requiredSequence,
                'acknowledged_sequence' => $acknowledgedSequence,
                'missing_sequence' => $missingSequence,
                'unexpected_sequence' => $unexpectedSequence,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'sequence_complete' => $sequenceComplete,
                'handoff_cursor_matches' => $cursorMatches,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-sequence'
                    : 'hold-next-source-until-current-source-sequence',
            ],
            'yield_boundary_next210' => $nextVisible
                ? 'recursive-view-returning-next210-current-source-sequence-then-next'
                : 'recursive-view-returning-next210-current-source-sequence-fences-next',
            'dependency_closure_next210' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-drain-and-adds-ordered-source-sequence-fence',
            'dependencies_next210' => array_values(array_unique(array_merge($base['dependencies_next209'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next210',
                'sqlite-returning-current-source-ordered-sequence-fence',
                'wordpress-recursive-view-returning-current-source-next210',
            ]))),
            'non_overlap_next210' => 'adds ordered current-source sequence fencing after next209 drain watermarks; avoids accepted next209 drain, next208 cursor close, next203 generation handoff, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sequenceNext210(array $rows, string $sequenceToken, string $handoffCursor, string $viewCookie, string $triggerCookie): array
    {
        $sequence = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sequenceToken,
                $handoffCursor,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_watermark_next209'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
            ];
            $sequence[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $sequence;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSequenceNext210(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_sequence_next210'] ?? false) === true) {
            return $required;
        }

        return self::sequenceListNext210($options['acknowledged_current_source_sequence_next210'] ?? [], 'acknowledged current source sequence');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sequenceListNext210(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} contains a malformed sequence token");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext210(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $sequence
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRowsNext210(array $rows, array $sequence, string $sequenceToken, string $handoffCursor, string $sourceSignature): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_sequence_phase_next210' => 'current',
                'current_source_sequence_token_next210' => $sequenceToken,
                'sequence_handoff_cursor_next210' => $handoffCursor,
                'current_source_signature_next210' => $sourceSignature,
                'current_source_sequence_next210' => $sequence[$index] ?? null,
                'visible_after_current_source_sequence_next210' => true,
                'held_by_current_source_sequence_reasons_next210' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRowsNext210(array $rows, bool $visible, string $sequenceToken, string $handoffCursor, string $sourceSignature, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_sequence_phase_next210' => 'next',
                'current_source_sequence_token_next210' => $sequenceToken,
                'sequence_handoff_cursor_next210' => $handoffCursor,
                'current_source_signature_next210' => $sourceSignature,
                'current_source_sequence_next210' => null,
                'visible_after_current_source_sequence_next210' => $visible,
                'held_by_current_source_sequence_reasons_next210' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingSequence
     * @param list<string> $unexpectedSequence
     * @return list<string>
     */
    private static function blockedReasonsNext210(
        mixed $baseReasons,
        bool $baseVisible,
        bool $sequenceComplete,
        array $missingSequence,
        array $unexpectedSequence,
        bool $requireOrder,
        bool $orderMatches,
        bool $cursorMatches,
        bool $sourceSignatureMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next210 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next209-current-source-drain-held';
        }
        if (!$sequenceComplete && $missingSequence !== []) {
            $reasons[] = 'current-source-sequence-missing';
        }
        if (!$sequenceComplete && $unexpectedSequence !== []) {
            $reasons[] = 'current-source-sequence-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-sequence-order-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-source-sequence-cursor-mismatch';
        }
        if (!$sourceSignatureMatches) {
            $reasons[] = 'current-source-signature-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function sourceSignatureNext210(string $viewCookie, string $triggerCookie, string $sequenceToken): string
    {
        return substr(hash('sha256', $viewCookie . '|' . $triggerCookie . '|' . $sequenceToken), 0, 34);
    }

    private static function statusNext210(bool $baseVisible, bool $sequenceComplete, bool $cursorMatches, bool $sourceSignatureMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next210-sequence-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next210-base-held';
        }
        if (!$sequenceComplete) {
            return 'trigger-recursive-view-returning-current-source-next210-sequence-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next210-cursor-held';
        }
        if (!$sourceSignatureMatches) {
            return 'trigger-recursive-view-returning-current-source-next210-source-held';
        }

        return 'trigger-recursive-view-returning-current-source-next210-held';
    }

    private static function tokenNext210(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext211(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext209(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsNext211($base['current_source_rows_next209'] ?? [], 'current source rows');
        $nextRows = self::rowsNext211($base['attempted_next_source_rows_next209'] ?? [], 'attempted next source rows');
        $tamperedCurrentRows = self::tamperCurrentRowsNext211($currentRows, $options['tamper_current_returning_sources_next211'] ?? []);
        $sourceSeal = self::sourceSealNext211($tamperedCurrentRows, 'current');
        $expectedSourceSeal = self::hexNext211((string) ($options['expected_current_source_seal_next211'] ?? $sourceSeal), 'expected current source seal');
        $expectedRowCount = self::nonNegativeIntNext211($options['expected_current_source_row_count_next211'] ?? count($tamperedCurrentRows), 'expected current source row count');
        $actualRowCount = count($tamperedCurrentRows);
        $watermarksUnique = self::uniqueWatermarksNext211($tamperedCurrentRows);
        $sourceSealMatches = hash_equals($sourceSeal, $expectedSourceSeal);
        $rowCountMatches = $actualRowCount === $expectedRowCount;
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_drain_next209'] ?? false);
        $currentSourceSealed = $baseVisible && $sourceSealMatches && $rowCountMatches && $watermarksUnique;
        $blockedReasons = self::blockedReasonsNext211(
            $base['blocked_reasons_next209'] ?? [],
            $baseVisible,
            $sourceSealMatches,
            $rowCountMatches,
            $watermarksUnique,
        );

        $taggedCurrent = self::tagRowsNext211($tamperedCurrentRows, 'current', true, [], $sourceSeal, $expectedSourceSeal, $expectedRowCount);
        $taggedNext = self::tagRowsNext211($nextRows, 'next', $currentSourceSealed, $blockedReasons, $sourceSeal, $expectedSourceSeal, $expectedRowCount);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_seal_next211'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_seal_next211'],
        ));

        return [
            'status_next211' => self::statusNext211($baseVisible, $sourceSealMatches, $rowCountMatches, $watermarksUnique, $currentSourceSealed),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next211' => $baseVisible,
            'current_source_seal_next211' => $sourceSeal,
            'expected_current_source_seal_next211' => $expectedSourceSeal,
            'current_source_seal_matches_next211' => $sourceSealMatches,
            'current_source_row_count_next211' => $actualRowCount,
            'expected_current_source_row_count_next211' => $expectedRowCount,
            'current_source_row_count_matches_next211' => $rowCountMatches,
            'current_source_watermarks_unique_next211' => $watermarksUnique,
            'next_source_visible_after_current_source_seal_next211' => $currentSourceSealed,
            'current_source_rows_next211' => $taggedCurrent,
            'attempted_next_source_rows_next211' => $taggedNext,
            'visible_returning_rows_next211' => $visibleRows,
            'held_next_source_rows_next211' => $heldRows,
            'visible_returning_payloads_next211' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next211' => array_column($heldRows, 'returning'),
            'blocked_reasons_next211' => $blockedReasons,
            'current_source_seal_plan_next211' => [
                'base_next_source_visible' => $baseVisible,
                'current_rows' => $actualRowCount,
                'expected_current_rows' => $expectedRowCount,
                'row_count_matches' => $rowCountMatches,
                'source_seal' => $sourceSeal,
                'expected_source_seal' => $expectedSourceSeal,
                'source_seal_matches' => $sourceSealMatches,
                'watermarks_unique' => $watermarksUnique,
                'next_source_visible' => $currentSourceSealed,
                'decision' => $currentSourceSealed
                    ? 'publish-next-source-after-current-returning-source-seal'
                    : 'hold-next-source-until-current-returning-source-seal',
            ],
            'yield_boundary_next211' => $currentSourceSealed
                ? 'recursive-view-returning-next211-current-source-sealed-then-next'
                : 'recursive-view-returning-next211-current-source-seal-fences-next',
            'dependency_closure_next211' => 'no new support component needed; reuses recursive view RETURNING generation, current-source drain watermarks, and adds a bounded current-source RETURNING source seal',
            'dependencies_next211' => array_values(array_unique(array_merge($base['dependencies_next209'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next211',
                'sqlite-returning-current-source-seal',
                'wordpress-recursive-view-returning-current-source-next211',
            ]))),
            'non_overlap_next211' => 'adds current-source RETURNING source sealing after next209 drain watermarks; avoids next208 cursor-close fencing, next209 drain-watermark admission, next203 generation handoff, DML/row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext211(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param mixed $overrides
     * @return list<array<string,mixed>>
     */
    private static function tamperCurrentRowsNext211(array $rows, mixed $overrides): array
    {
        if ($overrides === []) {
            return $rows;
        }
        if (!is_array($overrides)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next211 source overrides must be an array');
        }
        foreach ($overrides as $index => $source) {
            if (!is_int($index) || !isset($rows[$index])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next211 source override index is invalid');
            }
            if (!is_string($source) || $source === '' || preg_match('/\s/', $source) === 1) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next211 source override is malformed');
            }
            $rows[$index]['returning']['trigger_source_alias'] = $source;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sourceSealNext211(array $rows, string $phase): string
    {
        if ($rows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next211 current source rows are empty');
        }
        $parts = [$phase, (string) count($rows)];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts[] = implode(':', [
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($row['current_source_watermark_next209'] ?? ''),
            ]);
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function uniqueWatermarksNext211(array $rows): bool
    {
        $watermarks = [];
        foreach ($rows as $row) {
            $watermark = $row['current_source_watermark_next209'] ?? null;
            if (!is_string($watermark) || preg_match('/^[a-f0-9]{32}$/', $watermark) !== 1) {
                return false;
            }
            $watermarks[] = $watermark;
        }

        return count($watermarks) === count(array_unique($watermarks));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext211(array $rows, string $phase, bool $visible, array $reasons, string $sourceSeal, string $expectedSourceSeal, int $expectedRowCount): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_seal_phase_next211' => $phase,
                'current_source_seal_next211' => $sourceSeal,
                'expected_current_source_seal_next211' => $expectedSourceSeal,
                'expected_current_source_row_count_next211' => $expectedRowCount,
                'visible_after_current_source_seal_next211' => $visible,
                'held_by_current_source_seal_reasons_next211' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasonsNext211(
        mixed $baseReasons,
        bool $baseVisible,
        bool $sourceSealMatches,
        bool $rowCountMatches,
        bool $watermarksUnique,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next211 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next209-current-source-drain-held';
        }
        if (!$sourceSealMatches) {
            $reasons[] = 'current-source-returning-seal-mismatch';
        }
        if (!$rowCountMatches) {
            $reasons[] = 'current-source-returning-row-count-mismatch';
        }
        if (!$watermarksUnique) {
            $reasons[] = 'current-source-returning-watermark-duplicate';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusNext211(bool $baseVisible, bool $sourceSealMatches, bool $rowCountMatches, bool $watermarksUnique, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next211-source-sealed';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next211-base-held';
        }
        if (!$sourceSealMatches) {
            return 'trigger-recursive-view-returning-current-source-next211-source-seal-held';
        }
        if (!$rowCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next211-row-count-held';
        }
        if (!$watermarksUnique) {
            return 'trigger-recursive-view-returning-current-source-next211-watermark-held';
        }

        return 'trigger-recursive-view-returning-current-source-next211-held';
    }

    private static function hexNext211(string $value, string $label): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} must be a 32-hex token");
        }

        return $value;
    }

    private static function nonNegativeIntNext211(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} must be a non-negative integer");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext212(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext209(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $yieldToken = self::tokenNext212((string) ($options['current_source_yield_token_next212'] ?? 'wp.current.source.yield.212'), 'current source yield token');
        $viewCursor = self::tokenNext212((string) ($options['current_view_yield_cursor_next212'] ?? 'wp.returning.view.yield.cursor.212'), 'current view yield cursor');
        $triggerCursor = self::tokenNext212((string) ($options['current_trigger_yield_cursor_next212'] ?? 'wp.returning.trigger.yield.cursor.212'), 'current trigger yield cursor');
        $requireOrder = (bool) ($options['require_current_source_yield_order_next212'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_drain_next209'] ?? false);

        $currentRows = self::rowsNext212($base['current_source_rows_next209'] ?? [], 'current source rows');
        $attemptedNextRows = self::rowsNext212($base['attempted_next_source_rows_next209'] ?? [], 'attempted next source rows');
        $requiredYields = self::yieldReceiptsNext212($currentRows, $yieldToken, $viewCursor, $triggerCursor);
        $acknowledgedYields = self::acknowledgedYieldsNext212($options, $requiredYields);
        $missingYields = array_values(array_diff($requiredYields, $acknowledgedYields));
        $unexpectedYields = array_values(array_diff($acknowledgedYields, $requiredYields));
        $orderMatches = !$requireOrder || $requiredYields === $acknowledgedYields;
        $yieldComplete = $requiredYields !== []
            && $missingYields === []
            && $unexpectedYields === []
            && $orderMatches;
        $nextVisible = $baseVisible && $yieldComplete;
        $blockedReasons = self::blockedReasonsNext212(
            $base['blocked_reasons_next209'] ?? [],
            $baseVisible,
            $yieldComplete,
            $missingYields,
            $unexpectedYields,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsNext212(
            $currentRows,
            'current',
            true,
            $requiredYields,
            $yieldToken,
            $viewCursor,
            $triggerCursor,
            [],
        );
        $nextRows = self::tagRowsNext212(
            $attemptedNextRows,
            'next',
            $nextVisible,
            [],
            $yieldToken,
            $viewCursor,
            $triggerCursor,
            $nextVisible ? [] : $blockedReasons,
        );
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_yield_next212'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_yield_next212'],
        ));

        return [
            'status_next212' => self::statusNext212($baseVisible, $yieldComplete, $missingYields, $unexpectedYields, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next212' => $baseVisible,
            'current_source_yield_token_next212' => $yieldToken,
            'current_view_yield_cursor_next212' => $viewCursor,
            'current_trigger_yield_cursor_next212' => $triggerCursor,
            'required_current_source_yields_next212' => $requiredYields,
            'acknowledged_current_source_yields_next212' => $acknowledgedYields,
            'missing_current_source_yields_next212' => $missingYields,
            'unexpected_current_source_yields_next212' => $unexpectedYields,
            'require_current_source_yield_order_next212' => $requireOrder,
            'current_source_yield_order_matches_next212' => $orderMatches,
            'current_source_yield_complete_next212' => $yieldComplete,
            'next_source_visible_after_current_source_yield_next212' => $nextVisible,
            'current_source_rows_next212' => $currentRows,
            'attempted_next_source_rows_next212' => $nextRows,
            'visible_returning_rows_next212' => $visibleRows,
            'held_next_source_rows_next212' => $heldRows,
            'visible_returning_payloads_next212' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next212' => array_column($heldRows, 'returning'),
            'current_source_row_count_next212' => count($currentRows),
            'attempted_next_source_row_count_next212' => count($nextRows),
            'visible_row_count_next212' => count($visibleRows),
            'held_next_row_count_next212' => count($heldRows),
            'blocked_reasons_next212' => $blockedReasons,
            'current_source_yield_plan_next212' => [
                'base_next_source_visible' => $baseVisible,
                'required_yields' => $requiredYields,
                'acknowledged_yields' => $acknowledgedYields,
                'missing_yields' => $missingYields,
                'unexpected_yields' => $unexpectedYields,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'yield_complete' => $yieldComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-yield'
                    : 'hold-next-source-until-current-source-yield',
            ],
            'yield_boundary_next212' => $nextVisible
                ? 'recursive-view-returning-next212-current-source-yield-then-next'
                : 'recursive-view-returning-next212-current-source-yield-fences-next',
            'dependency_closure_next212' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-yield-receipts',
            'dependencies_next212' => array_values(array_unique(array_merge($base['dependencies_next209'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next212',
                'sqlite-returning-current-source-yield-receipt',
                'wordpress-recursive-view-returning-current-source-next212',
            ]))),
            'non_overlap_next212' => 'adds ordered current-source trigger-yield receipts after next209 drain watermarks; avoids accepted trigger recursive view RETURNING next157-next209 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function yieldReceiptsNext212(array $rows, string $yieldToken, string $viewCursor, string $triggerCursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $yieldToken,
                $viewCursor,
                $triggerCursor,
                (string) ($row['current_source_watermark_next209'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedYieldsNext212(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_yields_next212'] ?? false) === true) {
            return $required;
        }

        return self::yieldListNext212($options['acknowledged_current_source_yields_next212'] ?? [], 'acknowledged current source yields');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function yieldListNext212(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} contain a malformed yield receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext212(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext212(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $yieldToken,
        string $viewCursor,
        string $triggerCursor,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_yield_phase_next212' => $phase,
                'current_source_yield_token_next212' => $yieldToken,
                'current_view_yield_cursor_next212' => $viewCursor,
                'current_trigger_yield_cursor_next212' => $triggerCursor,
                'current_source_yield_receipt_next212' => $receipts[$index] ?? null,
                'visible_after_current_source_yield_next212' => $visible,
                'held_by_current_source_yield_reasons_next212' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext212(
        mixed $baseReasons,
        bool $baseVisible,
        bool $yieldComplete,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next212 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next209-current-source-drain-not-published';
        }
        if (!$yieldComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-yield-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-yield-unexpected';
            }
            if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-yield-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext212(bool $baseVisible, bool $yieldComplete, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next212-yield-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next212-base-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && $yieldComplete === false) {
            return 'trigger-recursive-view-returning-current-source-next212-yield-order-held';
        }
        if (!$yieldComplete) {
            return 'trigger-recursive-view-returning-current-source-next212-yield-held';
        }

        return 'trigger-recursive-view-returning-current-source-next212-held';
    }

    private static function tokenNext212(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext213(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext212(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $sealToken = self::tokenNext213((string) ($options['current_source_payload_seal_token_next213'] ?? 'wp.current.source.payload.seal.213'), 'current source payload seal token');
        $sealCursor = self::tokenNext213((string) ($options['current_source_payload_seal_cursor_next213'] ?? 'wp.returning.current.payload.cursor.213'), 'current source payload seal cursor');
        $requireOrder = (bool) ($options['require_current_source_payload_seal_order_next213'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_yield_next212'] ?? false);

        $currentRows = self::rowsNext213($base['current_source_rows_next212'] ?? [], 'current source rows');
        $attemptedNextRows = self::rowsNext213($base['attempted_next_source_rows_next212'] ?? [], 'attempted next source rows');
        $requiredSeals = self::payloadSealsNext213($currentRows, $sealToken, $sealCursor);
        $acknowledgedSeals = self::acknowledgedSealsNext213($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $sealComplete = $requiredSeals !== []
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;
        $nextVisible = $baseVisible && $sealComplete;
        $blockedReasons = self::blockedReasonsNext213(
            $base['blocked_reasons_next212'] ?? [],
            $baseVisible,
            $sealComplete,
            $missingSeals,
            $unexpectedSeals,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsNext213($currentRows, 'current', true, $requiredSeals, $sealToken, $sealCursor, []);
        $nextRows = self::tagRowsNext213($attemptedNextRows, 'next', $nextVisible, [], $sealToken, $sealCursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_payload_seal_next213'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_payload_seal_next213'],
        ));

        return [
            'status_next213' => self::statusNext213($baseVisible, $sealComplete, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next213' => $baseVisible,
            'current_source_payload_seal_token_next213' => $sealToken,
            'current_source_payload_seal_cursor_next213' => $sealCursor,
            'required_current_source_payload_seals_next213' => $requiredSeals,
            'acknowledged_current_source_payload_seals_next213' => $acknowledgedSeals,
            'missing_current_source_payload_seals_next213' => $missingSeals,
            'unexpected_current_source_payload_seals_next213' => $unexpectedSeals,
            'require_current_source_payload_seal_order_next213' => $requireOrder,
            'current_source_payload_seal_order_matches_next213' => $orderMatches,
            'current_source_payload_seal_complete_next213' => $sealComplete,
            'next_source_visible_after_current_source_payload_seal_next213' => $nextVisible,
            'current_source_rows_next213' => $currentRows,
            'attempted_next_source_rows_next213' => $nextRows,
            'visible_returning_rows_next213' => $visibleRows,
            'held_next_source_rows_next213' => $heldRows,
            'visible_returning_payloads_next213' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next213' => array_column($heldRows, 'returning'),
            'current_source_row_count_next213' => count($currentRows),
            'attempted_next_source_row_count_next213' => count($nextRows),
            'visible_row_count_next213' => count($visibleRows),
            'held_next_row_count_next213' => count($heldRows),
            'blocked_reasons_next213' => $blockedReasons,
            'current_source_payload_seal_plan_next213' => [
                'base_next_source_visible' => $baseVisible,
                'required_payload_seals' => $requiredSeals,
                'acknowledged_payload_seals' => $acknowledgedSeals,
                'missing_payload_seals' => $missingSeals,
                'unexpected_payload_seals' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'payload_seal_complete' => $sealComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-payload-seal'
                    : 'hold-next-source-until-current-payload-seal',
            ],
            'yield_boundary_next213' => $nextVisible
                ? 'recursive-view-returning-next213-current-payload-seal-then-next'
                : 'recursive-view-returning-next213-current-payload-seal-fences-next',
            'dependency_closure_next213' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-payload-seals',
            'dependencies_next213' => array_values(array_unique(array_merge($base['dependencies_next212'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next213',
                'sqlite-returning-current-source-payload-seal',
                'wordpress-recursive-view-returning-current-source-next213',
            ]))),
            'non_overlap_next213' => 'adds current-source RETURNING payload seals after next212 yield receipts; avoids accepted trigger recursive view RETURNING next172-next212 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function payloadSealsNext213(array $rows, string $sealToken, string $sealCursor): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $payload = self::stablePayloadNext213($row['returning']);
            $parts = [
                $sealToken,
                $sealCursor,
                (string) ($row['current_source_yield_receipt_next212'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                $payload,
            ];
            $seals[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
        }

        return $seals;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSealsNext213(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_payload_seals_next213'] ?? false) === true) {
            return $required;
        }

        return self::sealListNext213($options['acknowledged_current_source_payload_seals_next213'] ?? [], 'acknowledged current source payload seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sealListNext213(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} contain a malformed payload seal");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext213(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $seals
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext213(
        array $rows,
        string $phase,
        bool $visible,
        array $seals,
        string $sealToken,
        string $sealCursor,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'payload_seal_phase_next213' => $phase,
                'current_source_payload_seal_token_next213' => $sealToken,
                'current_source_payload_seal_cursor_next213' => $sealCursor,
                'current_source_payload_seal_next213' => $seals[$index] ?? null,
                'visible_after_current_source_payload_seal_next213' => $visible,
                'held_by_current_source_payload_seal_reasons_next213' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function stablePayloadNext213(array $payload): string
    {
        ksort($payload);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next213 payload cannot be encoded');
        }

        return $json;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext213(
        mixed $baseReasons,
        bool $baseVisible,
        bool $sealComplete,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next213 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next212-current-source-yield-not-published';
        }
        if (!$sealComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-payload-seal-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-payload-seal-unexpected';
            }
            if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-payload-seal-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext213(bool $baseVisible, bool $sealComplete, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next213-payload-seal-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next213-base-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && !$sealComplete) {
            return 'trigger-recursive-view-returning-current-source-next213-payload-seal-order-held';
        }
        if (!$sealComplete) {
            return 'trigger-recursive-view-returning-current-source-next213-payload-seal-held';
        }

        return 'trigger-recursive-view-returning-current-source-next213-held';
    }

    private static function tokenNext213(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext217(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext212(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $provenanceToken = self::tokenNext217((string) ($options['current_source_provenance_token_next217'] ?? 'wp.current.source.provenance.217'), 'current source provenance token');
        $viewSource = self::tokenNext217((string) ($currentView['source'] ?? ''), 'current view source');
        $triggerSource = self::tokenNext217((string) ($currentView['trigger_source'] ?? ''), 'current trigger source');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_yield_next212'] ?? false);
        $currentRows = self::rowsNext217($base['current_source_rows_next212'] ?? [], 'current source rows');
        $nextRows = self::rowsNext217($base['attempted_next_source_rows_next212'] ?? [], 'attempted next source rows');
        $currentRows = self::tamperCurrentRowsNext217($currentRows, $options['tamper_current_returning_payloads_next217'] ?? []);

        $required = self::provenanceReceiptsNext217($currentRows, $provenanceToken, $viewSource, $triggerSource);
        $expected = self::receiptListNext217($options['expected_current_source_provenance_next217'] ?? $required, 'expected current source provenance');
        $acknowledged = self::acknowledgedNext217($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $expectedMissing = array_values(array_diff($required, $expected));
        $expectedUnexpected = array_values(array_diff($expected, $required));
        $requireOrder = (bool) ($options['require_current_source_provenance_order_next217'] ?? true);
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $expectedMatches = $expectedMissing === [] && $expectedUnexpected === [];
        $provenanceComplete = $required !== []
            && $missing === []
            && $unexpected === []
            && $expectedMatches
            && $orderMatches;
        $nextVisible = $baseVisible && $provenanceComplete;
        $reasons = self::blockedReasonsNext217(
            $base['blocked_reasons_next212'] ?? [],
            $baseVisible,
            $missing,
            $unexpected,
            $expectedMissing,
            $expectedUnexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagRowsNext217($currentRows, 'current', true, [], $required, $provenanceToken, $viewSource, $triggerSource);
        $nextTagged = self::tagRowsNext217($nextRows, 'next', $nextVisible, $reasons, [], $provenanceToken, $viewSource, $triggerSource);
        $visibleRows = array_values(array_filter(
            array_merge($currentTagged, $nextTagged),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_provenance_next217'],
        ));
        $heldRows = array_values(array_filter(
            $nextTagged,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_provenance_next217'],
        ));

        return [
            'status_next217' => self::statusNext217($baseVisible, $provenanceComplete, $expectedMatches, $missing, $unexpected, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next217' => $baseVisible,
            'current_source_provenance_token_next217' => $provenanceToken,
            'current_view_source_next217' => $viewSource,
            'current_trigger_source_next217' => $triggerSource,
            'required_current_source_provenance_next217' => $required,
            'expected_current_source_provenance_next217' => $expected,
            'acknowledged_current_source_provenance_next217' => $acknowledged,
            'missing_current_source_provenance_next217' => $missing,
            'unexpected_current_source_provenance_next217' => $unexpected,
            'expected_missing_current_source_provenance_next217' => $expectedMissing,
            'expected_unexpected_current_source_provenance_next217' => $expectedUnexpected,
            'current_source_provenance_expected_matches_next217' => $expectedMatches,
            'require_current_source_provenance_order_next217' => $requireOrder,
            'current_source_provenance_order_matches_next217' => $orderMatches,
            'current_source_provenance_complete_next217' => $provenanceComplete,
            'next_source_visible_after_current_source_provenance_next217' => $nextVisible,
            'current_source_rows_next217' => $currentTagged,
            'attempted_next_source_rows_next217' => $nextTagged,
            'visible_returning_rows_next217' => $visibleRows,
            'held_next_source_rows_next217' => $heldRows,
            'visible_returning_payloads_next217' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next217' => array_column($heldRows, 'returning'),
            'current_source_row_count_next217' => count($currentTagged),
            'attempted_next_source_row_count_next217' => count($nextTagged),
            'visible_row_count_next217' => count($visibleRows),
            'held_next_row_count_next217' => count($heldRows),
            'blocked_reasons_next217' => $reasons,
            'current_source_provenance_plan_next217' => [
                'base_next_source_visible' => $baseVisible,
                'view_source' => $viewSource,
                'trigger_source' => $triggerSource,
                'required_provenance' => $required,
                'expected_provenance' => $expected,
                'acknowledged_provenance' => $acknowledged,
                'expected_matches' => $expectedMatches,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'provenance_complete' => $provenanceComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-provenance'
                    : 'hold-next-source-until-current-returning-provenance',
            ],
            'yield_boundary_next217' => $nextVisible
                ? 'recursive-view-returning-next217-current-source-provenance-then-next'
                : 'recursive-view-returning-next217-current-source-provenance-fences-next',
            'dependency_closure_next217' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-yield-and-adds-returning-payload-provenance-fence',
            'dependencies_next217' => array_values(array_unique(array_merge($base['dependencies_next212'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next217',
                'sqlite-returning-current-source-provenance-fence',
                'wordpress-recursive-view-returning-current-source-next217',
            ]))),
            'non_overlap_next217' => 'adds current-source RETURNING payload provenance after next212 yield receipts; avoids accepted next210 sequence, next211 source seal, next212 yield receipts, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function provenanceReceiptsNext217(array $rows, string $token, string $viewSource, string $triggerSource): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $token,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_yield_receipt_next212'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedNext217(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_provenance_next217'] ?? false) === true) {
            return $required;
        }

        return self::receiptListNext217($options['acknowledged_current_source_provenance_next217'] ?? [], 'acknowledged current source provenance');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListNext217(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} contains a malformed provenance receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext217(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param mixed $overrides
     * @return list<array<string,mixed>>
     */
    private static function tamperCurrentRowsNext217(array $rows, mixed $overrides): array
    {
        if ($overrides === []) {
            return $rows;
        }
        if (!is_array($overrides)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next217 payload overrides must be an array');
        }
        foreach ($overrides as $index => $payload) {
            if (!is_int($index) || !isset($rows[$index]) || !is_array($payload)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next217 payload override is malformed');
            }
            foreach ($payload as $key => $value) {
                if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                    throw new InvalidArgumentException('SQLite recursive view RETURNING next217 payload override field is malformed');
                }
                $rows[$index]['returning'][$key] = $value;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext217(array $rows, string $phase, bool $visible, array $reasons, array $receipts, string $token, string $viewSource, string $triggerSource): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_provenance_phase_next217' => $phase,
                'current_source_provenance_token_next217' => $token,
                'current_view_source_next217' => $viewSource,
                'current_trigger_source_next217' => $triggerSource,
                'current_source_provenance_receipt_next217' => $receipts[$index] ?? null,
                'visible_after_current_source_provenance_next217' => $visible,
                'held_by_current_source_provenance_reasons_next217' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @param list<string> $expectedMissing
     * @param list<string> $expectedUnexpected
     * @return list<string>
     */
    private static function blockedReasonsNext217(
        mixed $baseReasons,
        bool $baseVisible,
        array $missing,
        array $unexpected,
        array $expectedMissing,
        array $expectedUnexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next217 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next212-current-source-yield-not-published';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-provenance-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-provenance-unexpected';
        }
        if ($expectedMissing !== [] || $expectedUnexpected !== []) {
            $reasons[] = 'current-source-provenance-expected-mismatch';
        }
        if ($missing === [] && $unexpected === [] && $expectedMissing === [] && $expectedUnexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-provenance-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext217(bool $baseVisible, bool $complete, bool $expectedMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next217-base-held';
        }
        if (!$expectedMatches) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-mismatch-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && $expectedMatches && !$complete) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-order-held';
        }
        if (!$complete) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-held';
        }

        return 'trigger-recursive-view-returning-current-source-next217-held';
    }

    private static function tokenNext217(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext218(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext212(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $epoch = self::tokenNext218((string) ($options['current_source_epoch_next218'] ?? 'wp.current.source.epoch.218'), 'current source epoch');
        $expectedEpoch = self::tokenNext218((string) ($options['expected_current_source_epoch_next218'] ?? $epoch), 'expected current source epoch');
        $viewEpoch = self::tokenNext218((string) ($options['current_view_epoch_next218'] ?? 'wp.returning.view.epoch.218'), 'current view epoch');
        $triggerEpoch = self::tokenNext218((string) ($options['current_trigger_epoch_next218'] ?? 'wp.returning.trigger.epoch.218'), 'current trigger epoch');
        $requireOrder = (bool) ($options['require_current_source_epoch_order_next218'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_yield_next212'] ?? false);
        $epochMatches = hash_equals($epoch, $expectedEpoch);

        $currentRows = self::rowsNext218($base['current_source_rows_next212'] ?? [], 'current source rows');
        $nextRows = self::rowsNext218($base['attempted_next_source_rows_next212'] ?? [], 'attempted next source rows');
        $requiredEpochs = self::epochReceiptsNext218($currentRows, $epoch, $viewEpoch, $triggerEpoch);
        $acknowledgedEpochs = self::acknowledgedEpochsNext218($options, $requiredEpochs);
        $missingEpochs = array_values(array_diff($requiredEpochs, $acknowledgedEpochs));
        $unexpectedEpochs = array_values(array_diff($acknowledgedEpochs, $requiredEpochs));
        $orderMatches = !$requireOrder || $requiredEpochs === $acknowledgedEpochs;
        $epochComplete = $requiredEpochs !== []
            && $epochMatches
            && $missingEpochs === []
            && $unexpectedEpochs === []
            && $orderMatches;
        $nextVisible = $baseVisible && $epochComplete;
        $blockedReasons = self::blockedReasonsNext218(
            $base['blocked_reasons_next212'] ?? [],
            $baseVisible,
            $epochMatches,
            $epochComplete,
            $missingEpochs,
            $unexpectedEpochs,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsNext218($currentRows, 'current', true, $requiredEpochs, $epoch, $viewEpoch, $triggerEpoch, []);
        $nextRows = self::tagRowsNext218($nextRows, 'next', $nextVisible, [], $epoch, $viewEpoch, $triggerEpoch, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_epoch_next218'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_epoch_next218'],
        ));

        return [
            'status_next218' => self::statusNext218($baseVisible, $epochMatches, $epochComplete, $missingEpochs, $unexpectedEpochs, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next218' => $baseVisible,
            'current_source_epoch_next218' => $epoch,
            'expected_current_source_epoch_next218' => $expectedEpoch,
            'current_source_epoch_matches_next218' => $epochMatches,
            'current_view_epoch_next218' => $viewEpoch,
            'current_trigger_epoch_next218' => $triggerEpoch,
            'required_current_source_epochs_next218' => $requiredEpochs,
            'acknowledged_current_source_epochs_next218' => $acknowledgedEpochs,
            'missing_current_source_epochs_next218' => $missingEpochs,
            'unexpected_current_source_epochs_next218' => $unexpectedEpochs,
            'require_current_source_epoch_order_next218' => $requireOrder,
            'current_source_epoch_order_matches_next218' => $orderMatches,
            'current_source_epoch_complete_next218' => $epochComplete,
            'next_source_visible_after_current_source_epoch_next218' => $nextVisible,
            'current_source_rows_next218' => $currentRows,
            'attempted_next_source_rows_next218' => $nextRows,
            'visible_returning_rows_next218' => $visibleRows,
            'held_next_source_rows_next218' => $heldRows,
            'visible_returning_payloads_next218' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next218' => array_column($heldRows, 'returning'),
            'current_source_row_count_next218' => count($currentRows),
            'attempted_next_source_row_count_next218' => count($nextRows),
            'visible_row_count_next218' => count($visibleRows),
            'held_next_row_count_next218' => count($heldRows),
            'blocked_reasons_next218' => $blockedReasons,
            'current_source_epoch_plan_next218' => [
                'base_next_source_visible' => $baseVisible,
                'current_source_epoch_matches' => $epochMatches,
                'required_epochs' => $requiredEpochs,
                'acknowledged_epochs' => $acknowledgedEpochs,
                'missing_epochs' => $missingEpochs,
                'unexpected_epochs' => $unexpectedEpochs,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'epoch_complete' => $epochComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-epoch'
                    : 'hold-next-source-until-current-source-epoch',
            ],
            'yield_boundary_next218' => $nextVisible
                ? 'recursive-view-returning-next218-current-source-epoch-then-next'
                : 'recursive-view-returning-next218-current-source-epoch-fences-next',
            'dependency_closure_next218' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-handoff',
            'dependencies_next218' => array_values(array_unique(array_merge($base['dependencies_next212'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next218',
                'sqlite-returning-current-source-epoch-handoff',
                'wordpress-recursive-view-returning-current-source-next218',
            ]))),
            'non_overlap_next218' => 'adds current-source epoch handoff after next212 yield receipts; avoids accepted trigger recursive view RETURNING next157-next212 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function epochReceiptsNext218(array $rows, string $epoch, string $viewEpoch, string $triggerEpoch): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $epoch,
                $viewEpoch,
                $triggerEpoch,
                (string) ($row['current_source_yield_receipt_next212'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 36);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedEpochsNext218(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_epochs_next218'] ?? false) === true) {
            return $required;
        }

        return self::epochListNext218($options['acknowledged_current_source_epochs_next218'] ?? [], 'acknowledged current source epochs');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function epochListNext218(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{36}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} contain a malformed epoch receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext218(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext218(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $epoch,
        string $viewEpoch,
        string $triggerEpoch,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_epoch_phase_next218' => $phase,
                'current_source_epoch_next218' => $epoch,
                'current_view_epoch_next218' => $viewEpoch,
                'current_trigger_epoch_next218' => $triggerEpoch,
                'current_source_epoch_receipt_next218' => $receipts[$index] ?? null,
                'visible_after_current_source_epoch_next218' => $visible,
                'held_by_current_source_epoch_reasons_next218' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext218(
        mixed $baseReasons,
        bool $baseVisible,
        bool $epochMatches,
        bool $epochComplete,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next218 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next212-current-source-yield-not-published';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-source-epoch-mismatch';
        }
        if (!$epochComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-epoch-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-epoch-unexpected';
            }
            if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-epoch-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext218(bool $baseVisible, bool $epochMatches, bool $epochComplete, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next218-base-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-mismatch-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && $epochComplete === false) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-order-held';
        }
        if (!$epochComplete) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-held';
        }

        return 'trigger-recursive-view-returning-current-source-next218-held';
    }

    private static function tokenNext218(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext219(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext217(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $resetToken = self::tokenNext219((string) ($options['next_source_reset_token_next219'] ?? 'wp.next.source.reset.219'), 'next source reset token');
        $resetCursor = self::tokenNext219((string) ($options['next_source_reset_cursor_next219'] ?? 'wp.returning.next.reset.cursor.219'), 'next source reset cursor');
        $expectedResetCursor = self::tokenNext219((string) ($options['expected_next_source_reset_cursor_next219'] ?? $resetCursor), 'expected next source reset cursor');
        $followingToken = self::tokenNext219((string) ($options['following_current_source_token_next219'] ?? 'wp.current.source.following.219'), 'following current source token');
        $expectedFollowingToken = self::tokenNext219((string) ($options['expected_following_current_source_token_next219'] ?? $followingToken), 'expected following current source token');
        $followingView = self::viewNext219($options['following_current_view_next219'] ?? $currentView);
        $followingInput = self::inputRowsNext219($options['following_current_input_next219'] ?? [], 'following current input');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_provenance_next217'] ?? false);
        $nextRows = self::rowsNext219($base['attempted_next_source_rows_next217'] ?? [], 'attempted next source rows');
        $requiredReset = self::resetReceiptsNext219($nextRows, $resetToken, $resetCursor);
        $acknowledgedReset = self::acknowledgedResetNext219($options, $requiredReset);
        $missingReset = array_values(array_diff($requiredReset, $acknowledgedReset));
        $unexpectedReset = array_values(array_diff($acknowledgedReset, $requiredReset));
        $requireOrder = (bool) ($options['require_next_source_reset_order_next219'] ?? true);
        $orderMatches = !$requireOrder || $requiredReset === $acknowledgedReset;
        $resetCursorMatches = hash_equals($resetCursor, $expectedResetCursor);
        $followingTokenMatches = hash_equals($followingToken, $expectedFollowingToken);
        $resetComplete = $requiredReset !== []
            && $missingReset === []
            && $unexpectedReset === []
            && $orderMatches;
        $followingVisible = $baseVisible && $resetComplete && $resetCursorMatches && $followingTokenMatches;
        $reasons = self::blockedReasonsNext219(
            $base['blocked_reasons_next217'] ?? [],
            $baseVisible,
            $missingReset,
            $unexpectedReset,
            $requireOrder,
            $orderMatches,
            $resetCursorMatches,
            $followingTokenMatches,
        );

        $taggedNext = self::tagNextRowsNext219($nextRows, $requiredReset, $resetToken, $resetCursor, $followingVisible ? [] : $reasons);
        $followingRows = $followingVisible
            ? self::followingRowsNext219($followingInput, $followingView, $returning, $followingToken, $resetToken, $resetCursor)
            : [];
        $visibleRows = array_values(array_merge(
            self::rowsNext219($base['visible_returning_rows_next217'] ?? [], 'base visible rows'),
            $followingRows,
        ));

        return [
            'status_next219' => self::statusNext219($baseVisible, $resetComplete, $resetCursorMatches, $followingTokenMatches, $followingVisible, $missingReset, $unexpectedReset, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next219' => $baseVisible,
            'next_source_reset_token_next219' => $resetToken,
            'next_source_reset_cursor_next219' => $resetCursor,
            'expected_next_source_reset_cursor_next219' => $expectedResetCursor,
            'next_source_reset_cursor_matches_next219' => $resetCursorMatches,
            'following_current_source_token_next219' => $followingToken,
            'expected_following_current_source_token_next219' => $expectedFollowingToken,
            'following_current_source_token_matches_next219' => $followingTokenMatches,
            'required_next_source_reset_receipts_next219' => $requiredReset,
            'acknowledged_next_source_reset_receipts_next219' => $acknowledgedReset,
            'missing_next_source_reset_receipts_next219' => $missingReset,
            'unexpected_next_source_reset_receipts_next219' => $unexpectedReset,
            'require_next_source_reset_order_next219' => $requireOrder,
            'next_source_reset_order_matches_next219' => $orderMatches,
            'next_source_reset_complete_next219' => $resetComplete,
            'following_current_source_visible_next219' => $followingVisible,
            'attempted_next_source_rows_next219' => $taggedNext,
            'following_current_rows_next219' => $followingRows,
            'visible_returning_rows_next219' => $visibleRows,
            'visible_returning_payloads_next219' => array_column($visibleRows, 'returning'),
            'following_current_payloads_next219' => array_column($followingRows, 'returning'),
            'attempted_next_source_row_count_next219' => count($taggedNext),
            'following_current_row_count_next219' => count($followingRows),
            'visible_row_count_next219' => count($visibleRows),
            'blocked_reasons_next219' => $reasons,
            'next_source_reset_plan_next219' => [
                'base_next_source_visible' => $baseVisible,
                'required_reset_receipts' => $requiredReset,
                'acknowledged_reset_receipts' => $acknowledgedReset,
                'missing_reset_receipts' => $missingReset,
                'unexpected_reset_receipts' => $unexpectedReset,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'reset_cursor_matches' => $resetCursorMatches,
                'following_token_matches' => $followingTokenMatches,
                'reset_complete' => $resetComplete,
                'following_current_source_visible' => $followingVisible,
                'decision' => $followingVisible
                    ? 'admit-following-current-source-after-next-returning-reset'
                    : 'hold-following-current-source-until-next-returning-reset',
            ],
            'yield_boundary_next219' => $followingVisible
                ? 'recursive-view-returning-next219-next-source-reset-then-following-current'
                : 'recursive-view-returning-next219-next-source-reset-fences-following-current',
            'dependency_closure_next219' => 'no-new-support-component-reuses-native-recursive-view-returning-provenance-and-adds-next-source-reset-admission-fence',
            'dependencies_next219' => array_values(array_unique(array_merge($base['dependencies_next217'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next219',
                'sqlite-returning-next-source-reset-following-current-fence',
                'wordpress-recursive-view-returning-current-source-next219',
            ]))),
            'non_overlap_next219' => 'adds next-source RETURNING reset admission before a following current-source view trigger generation; avoids next217 provenance, next212 yield receipts, next210 sequence, next211 source seal, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function resetReceiptsNext219(array $rows, string $token, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $token,
                $cursor,
                (string) ($row['current_view_source_next217'] ?? ''),
                (string) ($row['current_trigger_source_next217'] ?? ''),
                (string) ($row['source_provenance_phase_next217'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['name'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedResetNext219(array $options, array $required): array
    {
        if (($options['auto_ack_next_source_reset_next219'] ?? false) === true) {
            return $required;
        }

        return self::receiptListNext219($options['acknowledged_next_source_reset_receipts_next219'] ?? [], 'acknowledged next source reset receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListNext219(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} contains a malformed reset receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext219(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function inputRowsNext219(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewNext219(mixed $view): array
    {
        if (!is_array($view) || !isset($view['source'], $view['trigger_source']) || !is_string($view['source']) || !is_string($view['trigger_source'])) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next219 following current view is malformed');
        }

        return $view;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRowsNext219(array $rows, array $receipts, string $token, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'next_source_reset_token_next219' => $token,
                'next_source_reset_cursor_next219' => $cursor,
                'next_source_reset_receipt_next219' => $receipts[$index] ?? null,
                'next_source_reset_reasons_next219' => $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function followingRowsNext219(array $input, array $view, array $returning, string $followingToken, string $resetToken, string $resetCursor): array
    {
        $rows = [];
        foreach ($input as $row) {
            $new = [
                'option_name' => (string) ($row['name'] ?? $row['option_name'] ?? ''),
                'option_value' => (string) ($row['value'] ?? $row['option_value'] ?? ''),
                'autoload' => (string) ($row['autoload_flag'] ?? $row['autoload'] ?? 'yes'),
                'spawn_child' => (bool) ($row['spawn_child'] ?? false),
            ];
            $rows[] = [
                'statement_source' => 'following-current-after-next-reset',
                'returning_row_ordinal' => count($rows),
                'returning' => self::returningPayloadNext219($returning, $new, $view, count($rows)),
                'returning_option_name' => $new['option_name'],
                'following_current_source_token_next219' => $followingToken,
                'next_source_reset_token_next219' => $resetToken,
                'next_source_reset_cursor_next219' => $resetCursor,
                'current_view_source_next219' => (string) $view['source'],
                'current_trigger_source_next219' => (string) $view['trigger_source'],
                'visible_after_next_source_reset_next219' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayloadNext219(array $returning, array $new, array $view, int $ordinal): array
    {
        $payload = [];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'following-current-after-next-reset', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'],
                'new.option_value' => $new['option_value'],
                'old.option_value' => null,
                'event' => 'following-current-after-next-reset',
                'depth' => 0,
                'ordinal' => $ordinal,
                'trigger_source' => (string) ($view['trigger_source'] ?? ''),
                'spawn_child' => $new['spawn_child'],
                'view.name' => (string) ($view['name'] ?? ''),
                default => $new[$expr] ?? null,
            };
        }

        return $payload;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext219(
        mixed $baseReasons,
        bool $baseVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $resetCursorMatches,
        bool $followingTokenMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next219 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next217-provenance-not-published';
        }
        if ($missing !== []) {
            $reasons[] = 'next-source-reset-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'next-source-reset-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'next-source-reset-order-mismatch';
        }
        if (!$resetCursorMatches) {
            $reasons[] = 'next-source-reset-cursor-mismatch';
        }
        if (!$followingTokenMatches) {
            $reasons[] = 'following-current-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext219(
        bool $baseVisible,
        bool $resetComplete,
        bool $resetCursorMatches,
        bool $followingTokenMatches,
        bool $followingVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): string {
        if ($followingVisible) {
            return 'trigger-recursive-view-returning-current-source-next219-following-current-visible';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next219-base-held';
        }
        if (!$resetCursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next219-reset-cursor-held';
        }
        if (!$followingTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next219-following-token-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next219-reset-order-held';
        }
        if (!$resetComplete) {
            return 'trigger-recursive-view-returning-current-source-next219-reset-held';
        }

        return 'trigger-recursive-view-returning-current-source-next219-held';
    }

    private static function tokenNext219(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext222(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext218(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $sourceTicket = self::tokenNext222((string) ($options['current_source_ticket_next222'] ?? 'wp.current.source.ticket.222'), 'current source ticket');
        $viewSource = self::tokenNext222((string) ($options['current_view_source_next222'] ?? (string) ($currentView['source'] ?? 'main@view-cookie-222-current')), 'current view source');
        $triggerSource = self::tokenNext222((string) ($options['current_trigger_source_next222'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-cookie-222-current')), 'current trigger source');
        $expectedViewSource = self::tokenNext222((string) ($options['expected_current_view_source_next222'] ?? $viewSource), 'expected current view source');
        $expectedTriggerSource = self::tokenNext222((string) ($options['expected_current_trigger_source_next222'] ?? $triggerSource), 'expected current trigger source');
        $requireOrder = (bool) ($options['require_current_source_ticket_order_next222'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_epoch_next218'] ?? false);
        $sourceMatches = hash_equals($viewSource, $expectedViewSource) && hash_equals($triggerSource, $expectedTriggerSource);

        $currentRows = self::rowsNext222($base['current_source_rows_next218'] ?? [], 'current source rows');
        $nextRows = self::rowsNext222($base['attempted_next_source_rows_next218'] ?? [], 'attempted next source rows');
        $requiredTickets = self::sourceTicketsNext222($currentRows, $sourceTicket, $viewSource, $triggerSource);
        $acknowledgedTickets = self::acknowledgedTicketsNext222($options, $requiredTickets);
        $missingTickets = array_values(array_diff($requiredTickets, $acknowledgedTickets));
        $unexpectedTickets = array_values(array_diff($acknowledgedTickets, $requiredTickets));
        $orderMatches = !$requireOrder || $requiredTickets === $acknowledgedTickets;
        $ticketComplete = $requiredTickets !== []
            && $sourceMatches
            && $missingTickets === []
            && $unexpectedTickets === []
            && $orderMatches;
        $nextVisible = $baseVisible && $ticketComplete;
        $blockedReasons = self::blockedReasonsNext222(
            $base['blocked_reasons_next218'] ?? [],
            $baseVisible,
            $sourceMatches,
            $missingTickets,
            $unexpectedTickets,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsNext222($currentRows, 'current', true, $requiredTickets, $sourceTicket, $viewSource, $triggerSource, []);
        $nextRows = self::tagRowsNext222($nextRows, 'next', $nextVisible, [], $sourceTicket, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_ticket_next222'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_ticket_next222'],
        ));

        return [
            'status_next222' => self::statusNext222($baseVisible, $sourceMatches, $missingTickets, $unexpectedTickets, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next222' => $baseVisible,
            'current_source_ticket_next222' => $sourceTicket,
            'current_view_source_next222' => $viewSource,
            'current_trigger_source_next222' => $triggerSource,
            'expected_current_view_source_next222' => $expectedViewSource,
            'expected_current_trigger_source_next222' => $expectedTriggerSource,
            'current_source_matches_next222' => $sourceMatches,
            'required_current_source_tickets_next222' => $requiredTickets,
            'acknowledged_current_source_tickets_next222' => $acknowledgedTickets,
            'missing_current_source_tickets_next222' => $missingTickets,
            'unexpected_current_source_tickets_next222' => $unexpectedTickets,
            'require_current_source_ticket_order_next222' => $requireOrder,
            'current_source_ticket_order_matches_next222' => $orderMatches,
            'current_source_ticket_complete_next222' => $ticketComplete,
            'next_source_visible_after_current_source_ticket_next222' => $nextVisible,
            'current_source_rows_next222' => $currentRows,
            'attempted_next_source_rows_next222' => $nextRows,
            'visible_returning_rows_next222' => $visibleRows,
            'held_next_source_rows_next222' => $heldRows,
            'visible_returning_payloads_next222' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next222' => array_column($heldRows, 'returning'),
            'current_source_row_count_next222' => count($currentRows),
            'attempted_next_source_row_count_next222' => count($nextRows),
            'visible_row_count_next222' => count($visibleRows),
            'held_next_row_count_next222' => count($heldRows),
            'blocked_reasons_next222' => $blockedReasons,
            'current_source_ticket_plan_next222' => [
                'base_next_source_visible' => $baseVisible,
                'current_source_matches' => $sourceMatches,
                'required_tickets' => $requiredTickets,
                'acknowledged_tickets' => $acknowledgedTickets,
                'missing_tickets' => $missingTickets,
                'unexpected_tickets' => $unexpectedTickets,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'ticket_complete' => $ticketComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-ticket'
                    : 'hold-next-source-until-current-source-ticket',
            ],
            'yield_boundary_next222' => $nextVisible
                ? 'recursive-view-returning-next222-current-source-ticket-then-next'
                : 'recursive-view-returning-next222-current-source-ticket-fences-next',
            'dependency_closure_next222' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-ticket-handoff',
            'dependencies_next222' => array_values(array_unique(array_merge($base['dependencies_next218'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next222',
                'sqlite-returning-current-source-ticket-handoff',
                'wordpress-recursive-view-returning-current-source-next222',
            ]))),
            'non_overlap_next222' => 'adds current view/trigger source ticket admission after accepted next218 epoch handoff; avoids accepted trigger recursive view RETURNING next157-next218 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sourceTicketsNext222(array $rows, string $ticket, string $viewSource, string $triggerSource): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $ticket,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_epoch_receipt_next218'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 42);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedTicketsNext222(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_tickets_next222'] ?? false) === true) {
            return $required;
        }

        return self::ticketListNext222($options['acknowledged_current_source_tickets_next222'] ?? [], 'acknowledged current source tickets');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function ticketListNext222(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{42}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} contain a malformed source ticket");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext222(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext222(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $ticket,
        string $viewSource,
        string $triggerSource,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_ticket_phase_next222' => $phase,
                'current_source_ticket_next222' => $ticket,
                'current_view_source_next222' => $viewSource,
                'current_trigger_source_next222' => $triggerSource,
                'current_source_ticket_receipt_next222' => $receipts[$index] ?? null,
                'visible_after_current_source_ticket_next222' => $visible,
                'held_by_current_source_ticket_reasons_next222' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext222(
        mixed $baseReasons,
        bool $baseVisible,
        bool $sourceMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next222 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next218-current-source-epoch-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-ticket-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-ticket-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-ticket-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-ticket-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext222(
        bool $baseVisible,
        bool $sourceMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next222-source-ticket-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next222-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next222-source-mismatch-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next222-source-ticket-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next222-source-ticket-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next222-source-ticket-empty-held';
    }

    private static function tokenNext222(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext224(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext218(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsNext224($base['current_source_rows_next218'] ?? [], 'current source rows');
        $nextRows = self::rowsNext224($base['attempted_next_source_rows_next218'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_epoch_next218'] ?? false);
        $sourceToken = self::tokenNext224((string) ($options['current_returning_source_token_next224'] ?? 'wp.current.returning.source.224'), 'current returning source token');
        $expectedSourceToken = self::tokenNext224((string) ($options['expected_current_returning_source_token_next224'] ?? $sourceToken), 'expected current returning source token');
        $viewSource = self::tokenNext224((string) ($options['current_returning_view_source_next224'] ?? ($currentView['source'] ?? 'main@view-cookie-224-current')), 'current returning view source');
        $expectedViewSource = self::tokenNext224((string) ($options['expected_current_returning_view_source_next224'] ?? $viewSource), 'expected current returning view source');
        $triggerSource = self::tokenNext224((string) ($options['current_returning_trigger_source_next224'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-224-current')), 'current returning trigger source');
        $expectedTriggerSource = self::tokenNext224((string) ($options['expected_current_returning_trigger_source_next224'] ?? $triggerSource), 'expected current returning trigger source');
        $requiredSeals = self::sourceSealsNext224($currentRows, $sourceToken, $viewSource, $triggerSource);
        $acknowledgedSeals = self::acknowledgedSealsNext224($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $sealComplete = $requiredSeals !== []
            && $sourceMatches
            && $viewMatches
            && $triggerMatches
            && $missingSeals === []
            && $unexpectedSeals === [];
        $nextVisible = $baseVisible && $sealComplete;
        $blockedReasons = self::blockedReasonsNext224(
            $base['blocked_reasons_next218'] ?? [],
            $baseVisible,
            $sourceMatches,
            $viewMatches,
            $triggerMatches,
            $missingSeals,
            $unexpectedSeals,
        );

        $currentRows = self::tagRowsNext224($currentRows, 'current', true, $requiredSeals, $sourceToken, $viewSource, $triggerSource, []);
        $nextRows = self::tagRowsNext224($nextRows, 'next', $nextVisible, [], $sourceToken, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_returning_source_next224'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_returning_source_next224'],
        ));

        return [
            'status_next224' => self::statusNext224($nextVisible, $baseVisible, $sourceMatches, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next224' => $baseVisible,
            'current_returning_source_token_next224' => $sourceToken,
            'expected_current_returning_source_token_next224' => $expectedSourceToken,
            'current_returning_source_matches_next224' => $sourceMatches,
            'current_returning_view_source_next224' => $viewSource,
            'expected_current_returning_view_source_next224' => $expectedViewSource,
            'current_returning_view_source_matches_next224' => $viewMatches,
            'current_returning_trigger_source_next224' => $triggerSource,
            'expected_current_returning_trigger_source_next224' => $expectedTriggerSource,
            'current_returning_trigger_source_matches_next224' => $triggerMatches,
            'required_current_returning_source_seals_next224' => $requiredSeals,
            'acknowledged_current_returning_source_seals_next224' => $acknowledgedSeals,
            'missing_current_returning_source_seals_next224' => $missingSeals,
            'unexpected_current_returning_source_seals_next224' => $unexpectedSeals,
            'current_returning_source_complete_next224' => $sealComplete,
            'next_source_visible_after_current_returning_source_next224' => $nextVisible,
            'current_source_rows_next224' => $currentRows,
            'attempted_next_source_rows_next224' => $nextRows,
            'visible_returning_rows_next224' => $visibleRows,
            'held_next_source_rows_next224' => $heldRows,
            'visible_returning_payloads_next224' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next224' => array_column($heldRows, 'returning'),
            'current_source_row_count_next224' => count($currentRows),
            'attempted_next_source_row_count_next224' => count($nextRows),
            'visible_row_count_next224' => count($visibleRows),
            'held_next_row_count_next224' => count($heldRows),
            'blocked_reasons_next224' => $blockedReasons,
            'current_returning_source_plan_next224' => [
                'base_next_source_visible' => $baseVisible,
                'source_matches' => $sourceMatches,
                'view_source_matches' => $viewMatches,
                'trigger_source_matches' => $triggerMatches,
                'required_seals' => $requiredSeals,
                'acknowledged_seals' => $acknowledgedSeals,
                'missing_seals' => $missingSeals,
                'unexpected_seals' => $unexpectedSeals,
                'source_complete' => $sealComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-source-seal'
                    : 'hold-next-source-until-current-returning-source-seal',
            ],
            'yield_boundary_next224' => $nextVisible
                ? 'recursive-view-returning-next224-current-source-sealed-then-next'
                : 'recursive-view-returning-next224-current-source-seal-fences-next',
            'dependency_closure_next224' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-and-adds-source-seal',
            'dependencies_next224' => array_values(array_unique(array_merge($base['dependencies_next218'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next224',
                'sqlite-returning-current-source-seal',
                'wordpress-recursive-view-returning-current-source-next224',
            ]))),
            'non_overlap_next224' => 'adds current returning source/view/trigger source seals after next218 epoch receipts; avoids accepted next208 cursor close, next212 yield receipts, next218 epoch receipts, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sourceSealsNext224(array $rows, string $sourceToken, string $viewSource, string $triggerSource): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceToken,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_epoch_receipt_next218'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $seals[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
        }

        return $seals;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSealsNext224(array $options, array $required): array
    {
        if (($options['auto_ack_current_returning_source_seals_next224'] ?? false) === true) {
            return $required;
        }

        return self::sealListNext224($options['acknowledged_current_returning_source_seals_next224'] ?? [], 'acknowledged current returning source seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sealListNext224(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} contain a malformed source seal");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext224(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $seals
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsNext224(array $rows, string $phase, bool $visible, array $seals, string $sourceToken, string $viewSource, string $triggerSource, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_source_phase_next224' => $phase,
                'current_returning_source_token_next224' => $sourceToken,
                'current_returning_view_source_next224' => $viewSource,
                'current_returning_trigger_source_next224' => $triggerSource,
                'current_returning_source_seal_next224' => $seals[$index] ?? null,
                'visible_after_current_returning_source_next224' => $visible,
                'held_by_current_returning_source_reasons_next224' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext224(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next224 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next218-current-source-epoch-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-returning-source-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-returning-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-returning-trigger-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-source-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-source-seal-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext224(bool $nextVisible, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next224-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next224-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next224-source-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-returning-current-source-next224-view-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-returning-current-source-next224-trigger-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next224-seal-held';
        }

        return 'trigger-recursive-view-returning-current-source-next224-held';
    }

    private static function tokenNext224(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeNext226(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext219(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseFollowingVisible = (bool) ($base['following_current_source_visible_next219'] ?? false);
        $followingRows = self::rowsNext226($base['following_current_rows_next219'] ?? [], 'following current rows');
        $sealToken = self::tokenNext226((string) ($options['following_current_seal_token_next226'] ?? 'wp.following.current.seal.226'), 'following current seal token');
        $sealCursor = self::tokenNext226((string) ($options['following_current_seal_cursor_next226'] ?? 'wp.returning.following.current.cursor.226'), 'following current seal cursor');
        $expectedSealCursor = self::tokenNext226((string) ($options['expected_following_current_seal_cursor_next226'] ?? $sealCursor), 'expected following current seal cursor');
        $subsequentToken = self::tokenNext226((string) ($options['subsequent_next_source_token_next226'] ?? 'wp.subsequent.next.source.226'), 'subsequent next source token');
        $expectedSubsequentToken = self::tokenNext226((string) ($options['expected_subsequent_next_source_token_next226'] ?? $subsequentToken), 'expected subsequent next source token');
        $subsequentView = self::viewNext226($options['subsequent_next_view_next226'] ?? $nextView);
        $subsequentInput = self::inputRowsNext226($options['subsequent_next_input_next226'] ?? [], 'subsequent next input');
        $requiredSeals = self::sealReceiptsNext226($followingRows, $sealToken, $sealCursor);
        $acknowledgedSeals = self::acknowledgedSealsNext226($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $requireOrder = (bool) ($options['require_following_current_seal_order_next226'] ?? true);
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $sealCursorMatches = hash_equals($sealCursor, $expectedSealCursor);
        $subsequentTokenMatches = hash_equals($subsequentToken, $expectedSubsequentToken);
        $sealComplete = $requiredSeals !== []
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;
        $subsequentVisible = $baseFollowingVisible && $sealComplete && $sealCursorMatches && $subsequentTokenMatches;
        $blockedReasons = self::blockedReasonsNext226(
            $base['blocked_reasons_next219'] ?? [],
            $baseFollowingVisible,
            $missingSeals,
            $unexpectedSeals,
            $requireOrder,
            $orderMatches,
            $sealCursorMatches,
            $subsequentTokenMatches,
        );

        $taggedFollowingRows = self::tagFollowingRowsNext226($followingRows, $requiredSeals, $sealToken, $sealCursor, $subsequentVisible ? [] : $blockedReasons);
        $subsequentRows = $subsequentVisible
            ? self::subsequentRowsNext226($subsequentInput, $subsequentView, $returning, $subsequentToken, $sealToken, $sealCursor)
            : [];
        $visibleRows = array_values(array_merge(
            self::rowsNext226($base['visible_returning_rows_next219'] ?? [], 'base visible rows'),
            $subsequentRows,
        ));

        return [
            'status_next226' => self::statusNext226($baseFollowingVisible, $sealComplete, $sealCursorMatches, $subsequentTokenMatches, $subsequentVisible, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_following_current_visible_next226' => $baseFollowingVisible,
            'following_current_seal_token_next226' => $sealToken,
            'following_current_seal_cursor_next226' => $sealCursor,
            'expected_following_current_seal_cursor_next226' => $expectedSealCursor,
            'following_current_seal_cursor_matches_next226' => $sealCursorMatches,
            'subsequent_next_source_token_next226' => $subsequentToken,
            'expected_subsequent_next_source_token_next226' => $expectedSubsequentToken,
            'subsequent_next_source_token_matches_next226' => $subsequentTokenMatches,
            'required_following_current_seal_receipts_next226' => $requiredSeals,
            'acknowledged_following_current_seal_receipts_next226' => $acknowledgedSeals,
            'missing_following_current_seal_receipts_next226' => $missingSeals,
            'unexpected_following_current_seal_receipts_next226' => $unexpectedSeals,
            'require_following_current_seal_order_next226' => $requireOrder,
            'following_current_seal_order_matches_next226' => $orderMatches,
            'following_current_seal_complete_next226' => $sealComplete,
            'subsequent_next_source_visible_next226' => $subsequentVisible,
            'following_current_rows_next226' => $taggedFollowingRows,
            'subsequent_next_rows_next226' => $subsequentRows,
            'visible_returning_rows_next226' => $visibleRows,
            'visible_returning_payloads_next226' => array_column($visibleRows, 'returning'),
            'subsequent_next_payloads_next226' => array_column($subsequentRows, 'returning'),
            'following_current_row_count_next226' => count($taggedFollowingRows),
            'subsequent_next_row_count_next226' => count($subsequentRows),
            'visible_row_count_next226' => count($visibleRows),
            'blocked_reasons_next226' => $blockedReasons,
            'following_current_seal_plan_next226' => [
                'base_following_current_visible' => $baseFollowingVisible,
                'required_seal_receipts' => $requiredSeals,
                'acknowledged_seal_receipts' => $acknowledgedSeals,
                'missing_seal_receipts' => $missingSeals,
                'unexpected_seal_receipts' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'seal_cursor_matches' => $sealCursorMatches,
                'subsequent_token_matches' => $subsequentTokenMatches,
                'seal_complete' => $sealComplete,
                'subsequent_next_source_visible' => $subsequentVisible,
                'decision' => $subsequentVisible
                    ? 'admit-subsequent-next-source-after-following-current-seal'
                    : 'hold-subsequent-next-source-until-following-current-seal',
            ],
            'yield_boundary_next226' => $subsequentVisible
                ? 'recursive-view-returning-next226-following-current-sealed-then-subsequent-next'
                : 'recursive-view-returning-next226-following-current-seal-fences-subsequent-next',
            'dependency_closure_next226' => 'no-new-support-component-reuses-native-recursive-view-returning-next219-and-adds-following-current-seal-admission',
            'dependencies_next226' => array_values(array_unique(array_merge($base['dependencies_next219'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next226',
                'sqlite-returning-following-current-seal-subsequent-next-fence',
                'wordpress-recursive-view-returning-current-source-next226',
            ]))),
            'non_overlap_next226' => 'adds following-current RETURNING seal admission before a subsequent next-source view trigger generation; avoids next219 next-source reset, next217 provenance, next212 yield receipts, next190 resume-source validation, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sealReceiptsNext226(array $rows, string $token, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $token,
                $cursor,
                (string) ($row['current_view_source_next219'] ?? ''),
                (string) ($row['current_trigger_source_next219'] ?? ''),
                (string) ($row['following_current_source_token_next219'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['name'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSealsNext226(array $options, array $required): array
    {
        if (($options['auto_ack_following_current_seal_next226'] ?? false) === true) {
            return $required;
        }

        return self::receiptListNext226($options['acknowledged_following_current_seal_receipts_next226'] ?? [], 'acknowledged following current seal receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListNext226(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} contains a malformed seal receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsNext226(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function inputRowsNext226(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewNext226(mixed $view): array
    {
        if (!is_array($view) || !isset($view['source'], $view['trigger_source']) || !is_string($view['source']) || !is_string($view['trigger_source'])) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next226 subsequent next view is malformed');
        }

        return $view;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagFollowingRowsNext226(array $rows, array $receipts, string $token, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'following_current_seal_token_next226' => $token,
                'following_current_seal_cursor_next226' => $cursor,
                'following_current_seal_receipt_next226' => $receipts[$index] ?? null,
                'following_current_seal_reasons_next226' => $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function subsequentRowsNext226(array $input, array $view, array $returning, string $subsequentToken, string $sealToken, string $sealCursor): array
    {
        $rows = [];
        foreach ($input as $row) {
            $new = [
                'option_name' => (string) ($row['name'] ?? $row['option_name'] ?? ''),
                'option_value' => (string) ($row['value'] ?? $row['option_value'] ?? ''),
                'autoload' => (string) ($row['autoload_flag'] ?? $row['autoload'] ?? 'yes'),
                'spawn_child' => (bool) ($row['spawn_child'] ?? false),
            ];
            $rows[] = [
                'statement_source' => 'subsequent-next-after-following-current-seal',
                'returning_row_ordinal' => count($rows),
                'returning' => self::returningPayloadNext226($returning, $new, $view, count($rows)),
                'returning_option_name' => $new['option_name'],
                'subsequent_next_source_token_next226' => $subsequentToken,
                'following_current_seal_token_next226' => $sealToken,
                'following_current_seal_cursor_next226' => $sealCursor,
                'next_view_source_next226' => (string) $view['source'],
                'next_trigger_source_next226' => (string) $view['trigger_source'],
                'visible_after_following_current_seal_next226' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayloadNext226(array $returning, array $new, array $view, int $ordinal): array
    {
        $payload = [];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'subsequent-next-after-following-current-seal', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'],
                'new.option_value' => $new['option_value'],
                'old.option_value' => null,
                'event' => 'subsequent-next-after-following-current-seal',
                'depth' => 0,
                'ordinal' => $ordinal,
                'trigger_source' => (string) ($view['trigger_source'] ?? ''),
                'spawn_child' => $new['spawn_child'],
                'view.name' => (string) ($view['name'] ?? ''),
                default => $new[$expr] ?? null,
            };
        }

        return $payload;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsNext226(
        mixed $baseReasons,
        bool $baseFollowingVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $sealCursorMatches,
        bool $subsequentTokenMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next226 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseFollowingVisible && $reasons === []) {
            $reasons[] = 'following-current-next219-not-visible';
        }
        if ($missing !== []) {
            $reasons[] = 'following-current-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'following-current-seal-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'following-current-seal-order-mismatch';
        }
        if (!$sealCursorMatches) {
            $reasons[] = 'following-current-seal-cursor-mismatch';
        }
        if (!$subsequentTokenMatches) {
            $reasons[] = 'subsequent-next-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusNext226(
        bool $baseFollowingVisible,
        bool $sealComplete,
        bool $sealCursorMatches,
        bool $subsequentTokenMatches,
        bool $subsequentVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): string {
        if ($subsequentVisible) {
            return 'trigger-recursive-view-returning-current-source-next226-subsequent-next-visible';
        }
        if (!$baseFollowingVisible) {
            return 'trigger-recursive-view-returning-current-source-next226-base-held';
        }
        if (!$sealCursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next226-seal-cursor-held';
        }
        if (!$subsequentTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next226-subsequent-token-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next226-seal-order-held';
        }
        if (!$sealComplete) {
            return 'trigger-recursive-view-returning-current-source-next226-seal-held';
        }

        return 'trigger-recursive-view-returning-current-source-next226-held';
    }

    private static function tokenNext226(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function currentReturningSnapshotAcknowledgement(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext224(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::returningSnapshotRows($base['current_source_rows_next224'] ?? [], 'current source rows');
        $nextRows = self::returningSnapshotRows($base['attempted_next_source_rows_next224'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_returning_source_next224'] ?? false);
        $snapshotToken = self::returningSnapshotToken((string) ($options['current_returning_snapshot_token_next228'] ?? 'wp.current.returning.snapshot.228'), 'current returning snapshot token');
        $expectedSnapshotToken = self::returningSnapshotToken((string) ($options['expected_current_returning_snapshot_token_next228'] ?? $snapshotToken), 'expected current returning snapshot token');
        $viewSource = self::returningSnapshotToken((string) ($options['current_returning_view_source_next228'] ?? ($currentView['source'] ?? 'main@view-cookie-228-current')), 'current returning view source');
        $expectedViewSource = self::returningSnapshotToken((string) ($options['expected_current_returning_view_source_next228'] ?? $viewSource), 'expected current returning view source');
        $triggerSource = self::returningSnapshotToken((string) ($options['current_returning_trigger_source_next228'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-228-current')), 'current returning trigger source');
        $expectedTriggerSource = self::returningSnapshotToken((string) ($options['expected_current_returning_trigger_source_next228'] ?? $triggerSource), 'expected current returning trigger source');
        $requiredAcks = self::snapshotAcknowledgements($currentRows, $snapshotToken, $viewSource, $triggerSource);
        $acknowledgedAcks = self::acknowledgedSnapshotAcks($options, $requiredAcks);
        $missingAcks = array_values(array_diff($requiredAcks, $acknowledgedAcks));
        $unexpectedAcks = array_values(array_diff($acknowledgedAcks, $requiredAcks));
        $sourceMatches = hash_equals($snapshotToken, $expectedSnapshotToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $snapshotComplete = $requiredAcks !== []
            && $sourceMatches
            && $viewMatches
            && $triggerMatches
            && $missingAcks === []
            && $unexpectedAcks === [];
        $nextVisible = $baseVisible && $snapshotComplete;
        $blockedReasons = self::snapshotBlockedReasons(
            $base['blocked_reasons_next218'] ?? [],
            $baseVisible,
            $sourceMatches,
            $viewMatches,
            $triggerMatches,
            $missingAcks,
            $unexpectedAcks,
        );

        $currentRows = self::tagSnapshotRows($currentRows, 'current', true, $requiredAcks, $snapshotToken, $viewSource, $triggerSource, []);
        $nextRows = self::tagSnapshotRows($nextRows, 'next', $nextVisible, [], $snapshotToken, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_returning_snapshot_next228'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_returning_snapshot_next228'],
        ));

        return [
            'status_next228' => self::snapshotStatus($nextVisible, $baseVisible, $sourceMatches, $viewMatches, $triggerMatches, $missingAcks, $unexpectedAcks),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next228' => $baseVisible,
            'current_returning_snapshot_token_next228' => $snapshotToken,
            'expected_current_returning_snapshot_token_next228' => $expectedSnapshotToken,
            'current_returning_snapshot_matches_next228' => $sourceMatches,
            'current_returning_view_source_next228' => $viewSource,
            'expected_current_returning_view_source_next228' => $expectedViewSource,
            'current_returning_view_source_matches_next228' => $viewMatches,
            'current_returning_trigger_source_next228' => $triggerSource,
            'expected_current_returning_trigger_source_next228' => $expectedTriggerSource,
            'current_returning_trigger_source_matches_next228' => $triggerMatches,
            'required_current_returning_snapshot_acks_next228' => $requiredAcks,
            'acknowledged_current_returning_snapshot_acks_next228' => $acknowledgedAcks,
            'missing_current_returning_snapshot_acks_next228' => $missingAcks,
            'unexpected_current_returning_snapshot_acks_next228' => $unexpectedAcks,
            'current_returning_snapshot_complete_next228' => $snapshotComplete,
            'next_source_visible_after_current_returning_snapshot_next228' => $nextVisible,
            'current_source_rows_next228' => $currentRows,
            'attempted_next_source_rows_next228' => $nextRows,
            'visible_returning_rows_next228' => $visibleRows,
            'held_next_source_rows_next228' => $heldRows,
            'visible_returning_payloads_next228' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next228' => array_column($heldRows, 'returning'),
            'current_source_row_count_next228' => count($currentRows),
            'attempted_next_source_row_count_next228' => count($nextRows),
            'visible_row_count_next228' => count($visibleRows),
            'held_next_row_count_next228' => count($heldRows),
            'blocked_reasons_next228' => $blockedReasons,
            'current_returning_snapshot_plan_next228' => [
                'base_next_source_visible' => $baseVisible,
                'source_matches' => $sourceMatches,
                'view_source_matches' => $viewMatches,
                'trigger_source_matches' => $triggerMatches,
                'required_acks' => $requiredAcks,
                'acknowledged_acks' => $acknowledgedAcks,
                'missing_acks' => $missingAcks,
                'unexpected_acks' => $unexpectedAcks,
                'source_complete' => $snapshotComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-source-ack'
                    : 'hold-next-source-until-current-returning-source-ack',
            ],
            'yield_boundary_next228' => $nextVisible
                ? 'recursive-view-returning-next228-current-source-acked-then-next'
                : 'recursive-view-returning-next228-current-source-ack-fences-next',
            'dependency_closure_next228' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-and-adds-source-ack',
            'dependencies_next228' => array_values(array_unique(array_merge($base['dependencies_next224'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next228',
                'sqlite-returning-current-source-snapshot-ack',
                'wordpress-recursive-view-returning-current-source-next228',
            ]))),
            'non_overlap_next228' => 'adds current returning snapshot acknowledgements after accepted next224 source seals; avoids accepted next222 ticket handoff, next224 source seal, next208 cursor close, next212 yield receipts, next218 epoch receipts, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function snapshotAcknowledgements(array $rows, string $snapshotToken, string $viewSource, string $triggerSource): array
    {
        $acks = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $snapshotToken,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_epoch_receipt_next218'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $acks[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
        }

        return $acks;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSnapshotAcks(array $options, array $required): array
    {
        if (($options['auto_ack_current_returning_snapshot_acks_next228'] ?? false) === true) {
            return $required;
        }

        return self::snapshotAckList($options['acknowledged_current_returning_snapshot_acks_next228'] ?? [], 'acknowledged current returning snapshot acks');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function snapshotAckList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} contain a malformed snapshot ack");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function returningSnapshotRows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $acks
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagSnapshotRows(array $rows, string $phase, bool $visible, array $acks, string $snapshotToken, string $viewSource, string $triggerSource, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_snapshot_phase_next228' => $phase,
                'current_returning_snapshot_token_next228' => $snapshotToken,
                'current_returning_view_source_next228' => $viewSource,
                'current_returning_trigger_source_next228' => $triggerSource,
                'current_returning_snapshot_ack_next228' => $acks[$index] ?? null,
                'visible_after_current_returning_snapshot_next228' => $visible,
                'held_by_current_returning_snapshot_reasons_next228' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function snapshotBlockedReasons(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next228 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next218-current-source-epoch-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-returning-source-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-returning-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-returning-trigger-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-source-ack-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-source-ack-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function snapshotStatus(bool $nextVisible, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next228-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next228-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next228-source-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-returning-current-source-next228-view-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-returning-current-source-next228-trigger-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next228-ack-held';
        }

        return 'trigger-recursive-view-returning-current-source-next228-held';
    }

    private static function returningSnapshotToken(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentReturningGenerationSeal(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext224(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::currentReturningGenerationRows($base['current_source_rows_next224'] ?? [], 'current source rows');
        $nextRows = self::currentReturningGenerationRows($base['attempted_next_source_rows_next224'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_returning_source_next224'] ?? false);
        $sourceGeneration = self::currentReturningGenerationToken((string) ($options['current_returning_source_generation_next229'] ?? 'wp.current.returning.source.generation.229'), 'current returning source generation');
        $expectedSourceGeneration = self::currentReturningGenerationToken((string) ($options['expected_current_returning_source_generation_next229'] ?? $sourceGeneration), 'expected current returning source generation');
        $viewGeneration = self::currentReturningGenerationToken((string) ($options['current_returning_view_generation_next229'] ?? ($currentView['source'] ?? 'main@view-generation-229-current')), 'current returning view generation');
        $expectedViewGeneration = self::currentReturningGenerationToken((string) ($options['expected_current_returning_view_generation_next229'] ?? $viewGeneration), 'expected current returning view generation');
        $triggerGeneration = self::currentReturningGenerationToken((string) ($options['current_returning_trigger_generation_next229'] ?? ($currentView['trigger_source'] ?? 'main@trigger-generation-229-current')), 'current returning trigger generation');
        $expectedTriggerGeneration = self::currentReturningGenerationToken((string) ($options['expected_current_returning_trigger_generation_next229'] ?? $triggerGeneration), 'expected current returning trigger generation');
        $requireOrder = (bool) ($options['require_current_returning_generation_order_next229'] ?? true);
        $requiredSeals = self::currentReturningGenerationSeals($currentRows, $sourceGeneration, $viewGeneration, $triggerGeneration);
        $acknowledgedSeals = self::acknowledgedCurrentReturningGenerationSeals($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $sourceMatches = hash_equals($sourceGeneration, $expectedSourceGeneration);
        $viewMatches = hash_equals($viewGeneration, $expectedViewGeneration);
        $triggerMatches = hash_equals($triggerGeneration, $expectedTriggerGeneration);
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $sealComplete = $requiredSeals !== []
            && $sourceMatches
            && $viewMatches
            && $triggerMatches
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;
        $nextVisible = $baseVisible && $sealComplete;
        $blockedReasons = self::currentReturningGenerationBlockedReasons(
            $base['blocked_reasons_next224'] ?? [],
            $baseVisible,
            $sourceMatches,
            $viewMatches,
            $triggerMatches,
            $missingSeals,
            $unexpectedSeals,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagCurrentReturningGenerationRows($currentRows, 'current', true, $requiredSeals, $sourceGeneration, $viewGeneration, $triggerGeneration, []);
        $nextRows = self::tagCurrentReturningGenerationRows($nextRows, 'next', $nextVisible, [], $sourceGeneration, $viewGeneration, $triggerGeneration, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_returning_generation_next229'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_returning_generation_next229'],
        ));

        return [
            'status_next229' => self::currentReturningGenerationStatus($nextVisible, $baseVisible, $sourceMatches, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next229' => $baseVisible,
            'current_returning_source_generation_next229' => $sourceGeneration,
            'expected_current_returning_source_generation_next229' => $expectedSourceGeneration,
            'current_returning_source_generation_matches_next229' => $sourceMatches,
            'current_returning_view_generation_next229' => $viewGeneration,
            'expected_current_returning_view_generation_next229' => $expectedViewGeneration,
            'current_returning_view_generation_matches_next229' => $viewMatches,
            'current_returning_trigger_generation_next229' => $triggerGeneration,
            'expected_current_returning_trigger_generation_next229' => $expectedTriggerGeneration,
            'current_returning_trigger_generation_matches_next229' => $triggerMatches,
            'required_current_returning_generation_seals_next229' => $requiredSeals,
            'acknowledged_current_returning_generation_seals_next229' => $acknowledgedSeals,
            'missing_current_returning_generation_seals_next229' => $missingSeals,
            'unexpected_current_returning_generation_seals_next229' => $unexpectedSeals,
            'require_current_returning_generation_order_next229' => $requireOrder,
            'current_returning_generation_order_matches_next229' => $orderMatches,
            'current_returning_generation_complete_next229' => $sealComplete,
            'next_source_visible_after_current_returning_generation_next229' => $nextVisible,
            'current_source_rows_next229' => $currentRows,
            'attempted_next_source_rows_next229' => $nextRows,
            'visible_returning_rows_next229' => $visibleRows,
            'held_next_source_rows_next229' => $heldRows,
            'visible_returning_payloads_next229' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next229' => array_column($heldRows, 'returning'),
            'current_source_row_count_next229' => count($currentRows),
            'attempted_next_source_row_count_next229' => count($nextRows),
            'visible_row_count_next229' => count($visibleRows),
            'held_next_row_count_next229' => count($heldRows),
            'blocked_reasons_next229' => $blockedReasons,
            'current_returning_source_plan_next229' => [
                'base_next_source_visible' => $baseVisible,
                'source_generation_matches' => $sourceMatches,
                'view_generation_matches' => $viewMatches,
                'trigger_generation_matches' => $triggerMatches,
                'required_seals' => $requiredSeals,
                'acknowledged_seals' => $acknowledgedSeals,
                'missing_seals' => $missingSeals,
                'unexpected_seals' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'generation_complete' => $sealComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-generation-seal'
                    : 'hold-next-source-until-current-returning-generation-seal',
            ],
            'yield_boundary_next229' => $nextVisible
                ? 'recursive-view-returning-next229-current-source-generation-sealed-then-next'
                : 'recursive-view-returning-next229-current-source-generation-seal-fences-next',
            'dependency_closure_next229' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-seal-and-adds-generation-seal',
            'dependencies_next229' => array_values(array_unique(array_merge($base['dependencies_next224'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next229',
                'sqlite-returning-current-source-generation-seal',
                'wordpress-recursive-view-returning-current-source-next229',
            ]))),
            'non_overlap_next229' => 'adds ordered current returning source/view/trigger generation seals after next224 source seals; avoids accepted next208 cursor close, next212 yield receipts, next218 epoch receipts, next224 source seals, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function currentReturningGenerationSeals(array $rows, string $sourceGeneration, string $viewGeneration, string $triggerGeneration): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceGeneration,
                $viewGeneration,
                $triggerGeneration,
                (string) ($row['current_returning_source_seal_next224'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $seals[] = substr(hash('sha256', implode('|', $parts)), 0, 44);
        }

        return $seals;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedCurrentReturningGenerationSeals(array $options, array $required): array
    {
        if (($options['auto_ack_current_returning_generation_seals_next229'] ?? false) === true) {
            return $required;
        }

        return self::currentReturningGenerationSealList($options['acknowledged_current_returning_generation_seals_next229'] ?? [], 'acknowledged current returning generation seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function currentReturningGenerationSealList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{44}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} contain a malformed generation seal");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function currentReturningGenerationRows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $seals
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentReturningGenerationRows(array $rows, string $phase, bool $visible, array $seals, string $sourceGeneration, string $viewGeneration, string $triggerGeneration, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_generation_phase_next229' => $phase,
                'current_returning_source_generation_next229' => $sourceGeneration,
                'current_returning_view_generation_next229' => $viewGeneration,
                'current_returning_trigger_generation_next229' => $triggerGeneration,
                'current_returning_generation_seal_next229' => $seals[$index] ?? null,
                'visible_after_current_returning_generation_next229' => $visible,
                'held_by_current_returning_generation_reasons_next229' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function currentReturningGenerationBlockedReasons(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next229 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next224-current-returning-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-returning-source-generation-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-returning-view-generation-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-returning-trigger-generation-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-generation-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-generation-seal-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-returning-generation-seal-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function currentReturningGenerationStatus(bool $nextVisible, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next229-generation-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next229-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-source-generation-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-view-generation-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-trigger-generation-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next229-generation-seal-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-generation-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next229-held';
    }

    private static function currentReturningGenerationToken(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceEpochReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext226(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['subsequent_next_source_visible_next226'] ?? false);
        $followingRows = self::currentSourceEpochRows($base['following_current_rows_next226'] ?? [], 'following current rows');
        $subsequentRows = self::currentSourceEpochRows($base['subsequent_next_rows_next226'] ?? [], 'subsequent next rows');
        $epoch = self::currentSourceEpochToken((string) ($options['current_source_epoch_next230'] ?? 'wp.current.source.epoch.230'), 'current source epoch');
        $expectedEpoch = self::currentSourceEpochToken((string) ($options['expected_current_source_epoch_next230'] ?? $epoch), 'expected current source epoch');
        $epochCursor = self::currentSourceEpochToken((string) ($options['current_source_epoch_cursor_next230'] ?? 'wp.returning.current.epoch.cursor.230'), 'current source epoch cursor');
        $expectedEpochCursor = self::currentSourceEpochToken((string) ($options['expected_current_source_epoch_cursor_next230'] ?? $epochCursor), 'expected current source epoch cursor');
        $requiredEpochs = self::currentSourceEpochReceipts($followingRows, $epoch, $epochCursor);
        $acknowledgedEpochs = self::acknowledgedCurrentSourceEpochs($options, $requiredEpochs);
        $missingEpochs = array_values(array_diff($requiredEpochs, $acknowledgedEpochs));
        $unexpectedEpochs = array_values(array_diff($acknowledgedEpochs, $requiredEpochs));
        $requireOrder = (bool) ($options['require_current_source_epoch_order_next230'] ?? true);
        $orderMatches = !$requireOrder || $requiredEpochs === $acknowledgedEpochs;
        $epochMatches = hash_equals($epoch, $expectedEpoch);
        $cursorMatches = hash_equals($epochCursor, $expectedEpochCursor);
        $epochComplete = $requiredEpochs !== []
            && $missingEpochs === []
            && $unexpectedEpochs === []
            && $orderMatches;
        $nextVisible = $baseVisible && $epochComplete && $epochMatches && $cursorMatches;
        $blockedReasons = self::currentSourceEpochBlockedReasons(
            $base['blocked_reasons_next226'] ?? [],
            $baseVisible,
            $missingEpochs,
            $unexpectedEpochs,
            $requireOrder,
            $orderMatches,
            $epochMatches,
            $cursorMatches,
        );

        $taggedFollowing = self::tagCurrentSourceEpochRows($followingRows, 'following-current', true, $requiredEpochs, $epoch, $epochCursor, []);
        $taggedSubsequent = self::tagCurrentSourceEpochRows($subsequentRows, 'subsequent-next', $nextVisible, [], $epoch, $epochCursor, $nextVisible ? [] : $blockedReasons);
        $baseVisibleRows = self::currentSourceEpochRows($base['visible_returning_rows_next226'] ?? [], 'base visible rows');
        $visibleRows = $nextVisible
            ? $baseVisibleRows
            : array_slice($baseVisibleRows, 0, max(0, count($baseVisibleRows) - count($subsequentRows)));
        $heldRows = array_values(array_filter(
            $taggedSubsequent,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_epoch_next230'],
        ));

        return [
            'status_next230' => self::currentSourceEpochStatus($baseVisible, $epochComplete, $epochMatches, $cursorMatches, $nextVisible, $missingEpochs, $unexpectedEpochs, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_subsequent_next_visible_next230' => $baseVisible,
            'current_source_epoch_next230' => $epoch,
            'expected_current_source_epoch_next230' => $expectedEpoch,
            'current_source_epoch_matches_next230' => $epochMatches,
            'current_source_epoch_cursor_next230' => $epochCursor,
            'expected_current_source_epoch_cursor_next230' => $expectedEpochCursor,
            'current_source_epoch_cursor_matches_next230' => $cursorMatches,
            'required_current_source_epoch_receipts_next230' => $requiredEpochs,
            'acknowledged_current_source_epoch_receipts_next230' => $acknowledgedEpochs,
            'missing_current_source_epoch_receipts_next230' => $missingEpochs,
            'unexpected_current_source_epoch_receipts_next230' => $unexpectedEpochs,
            'require_current_source_epoch_order_next230' => $requireOrder,
            'current_source_epoch_order_matches_next230' => $orderMatches,
            'current_source_epoch_complete_next230' => $epochComplete,
            'subsequent_next_source_visible_after_epoch_next230' => $nextVisible,
            'following_current_rows_next230' => $taggedFollowing,
            'subsequent_next_rows_next230' => $taggedSubsequent,
            'visible_returning_rows_next230' => $visibleRows,
            'visible_returning_payloads_next230' => array_column($visibleRows, 'returning'),
            'held_subsequent_next_rows_next230' => $heldRows,
            'held_subsequent_next_payloads_next230' => array_column($heldRows, 'returning'),
            'following_current_row_count_next230' => count($taggedFollowing),
            'subsequent_next_row_count_next230' => count($taggedSubsequent),
            'visible_row_count_next230' => count($visibleRows),
            'held_subsequent_next_row_count_next230' => count($heldRows),
            'blocked_reasons_next230' => $blockedReasons,
            'current_source_epoch_plan_next230' => [
                'base_subsequent_next_visible' => $baseVisible,
                'required_epoch_receipts' => $requiredEpochs,
                'acknowledged_epoch_receipts' => $acknowledgedEpochs,
                'missing_epoch_receipts' => $missingEpochs,
                'unexpected_epoch_receipts' => $unexpectedEpochs,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'epoch_matches' => $epochMatches,
                'cursor_matches' => $cursorMatches,
                'epoch_complete' => $epochComplete,
                'subsequent_next_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-subsequent-next-source-after-current-epoch'
                    : 'hold-subsequent-next-source-until-current-epoch',
            ],
            'yield_boundary_next230' => $nextVisible
                ? 'recursive-view-returning-next230-current-source-epoch-then-subsequent-next'
                : 'recursive-view-returning-next230-current-source-epoch-fences-subsequent-next',
            'dependency_closure_next230' => 'no-new-support-component-reuses-native-recursive-view-returning-next226-and-adds-current-source-epoch-admission',
            'dependencies_next230' => array_values(array_unique(array_merge($base['dependencies_next226'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next230',
                'sqlite-returning-current-source-epoch-fence',
                'wordpress-recursive-view-returning-current-source-next230',
            ]))),
            'non_overlap_next230' => 'adds current-source epoch receipt admission after next226 following-current seal; avoids next226 seal, next222 source ticket, next219 reset, next212 yield receipts, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function currentSourceEpochReceipts(array $rows, string $epoch, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $epoch,
                $cursor,
                (string) ($row['current_source_yield_token_next212'] ?? ''),
                (string) ($row['following_current_seal_token_next226'] ?? ''),
                (string) ($row['current_view_source_next219'] ?? ''),
                (string) ($row['current_trigger_source_next219'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['name'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedCurrentSourceEpochs(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_epoch_next230'] ?? false) === true) {
            return $required;
        }

        return self::currentSourceEpochReceiptList($options['acknowledged_current_source_epoch_receipts_next230'] ?? [], 'acknowledged current source epoch receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function currentSourceEpochReceiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} contains a malformed epoch receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function currentSourceEpochRows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentSourceEpochRows(array $rows, string $phase, bool $visible, array $receipts, string $epoch, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_epoch_phase_next230' => $phase,
                'current_source_epoch_next230' => $epoch,
                'current_source_epoch_cursor_next230' => $cursor,
                'current_source_epoch_receipt_next230' => $receipts[$index] ?? null,
                'visible_after_current_source_epoch_next230' => $visible,
                'held_by_current_source_epoch_reasons_next230' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function currentSourceEpochBlockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $epochMatches,
        bool $cursorMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next230 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next226-following-current-seal-held';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-epoch-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-epoch-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-epoch-order-mismatch';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-source-epoch-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-source-epoch-cursor-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function currentSourceEpochStatus(bool $baseVisible, bool $epochComplete, bool $epochMatches, bool $cursorMatches, bool $nextVisible, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next230-subsequent-next-visible';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next230-base-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-returning-current-source-next230-epoch-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next230-cursor-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && !$epochComplete) {
            return 'trigger-recursive-view-returning-current-source-next230-epoch-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next230-epoch-receipt-held';
    }

    private static function currentSourceEpochToken(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceCursorClose(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext222(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $cursor = self::currentSourceCloseToken((string) ($options['current_source_cursor_next231'] ?? 'wp.returning.current.cursor.231'), 'current source cursor');
        $closeToken = self::currentSourceCloseToken((string) ($options['current_source_close_token_next231'] ?? 'wp.current.source.close.231'), 'current source close token');
        $expectedCloseToken = self::currentSourceCloseToken((string) ($options['expected_current_source_close_token_next231'] ?? $closeToken), 'expected current source close token');
        $viewCookie = self::currentSourceCloseToken((string) ($options['current_view_cookie_next231'] ?? (string) ($currentView['source'] ?? 'main@view-cookie-231-current')), 'current view cookie');
        $triggerCookie = self::currentSourceCloseToken((string) ($options['current_trigger_cookie_next231'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-cookie-231-current')), 'current trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_close_order_next231'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_ticket_next222'] ?? false);
        $closeMatches = hash_equals($closeToken, $expectedCloseToken);

        $currentRows = self::currentSourceCloseRows($base['current_source_rows_next222'] ?? [], 'current source rows');
        $nextRows = self::currentSourceCloseRows($base['attempted_next_source_rows_next222'] ?? [], 'attempted next source rows');
        $requiredClosures = self::currentSourceCloseReceipts($currentRows, $cursor, $closeToken, $viewCookie, $triggerCookie);
        $acknowledgedClosures = self::acknowledgedCurrentSourceClosures($options, $requiredClosures);
        $missingClosures = array_values(array_diff($requiredClosures, $acknowledgedClosures));
        $unexpectedClosures = array_values(array_diff($acknowledgedClosures, $requiredClosures));
        $orderMatches = !$requireOrder || $requiredClosures === $acknowledgedClosures;
        $closeComplete = $requiredClosures !== []
            && $closeMatches
            && $missingClosures === []
            && $unexpectedClosures === []
            && $orderMatches;
        $nextVisible = $baseVisible && $closeComplete;
        $blockedReasons = self::currentSourceCloseBlockedReasons(
            $base['blocked_reasons_next222'] ?? [],
            $baseVisible,
            $closeMatches,
            $missingClosures,
            $unexpectedClosures,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagCurrentSourceCloseRows($currentRows, 'current', true, $requiredClosures, $cursor, $closeToken, $viewCookie, $triggerCookie, []);
        $nextRows = self::tagCurrentSourceCloseRows($nextRows, 'next', $nextVisible, [], $cursor, $closeToken, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_close_next231'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_close_next231'],
        ));

        return [
            'status_next231' => self::currentSourceCloseStatus($baseVisible, $closeMatches, $missingClosures, $unexpectedClosures, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next231' => $baseVisible,
            'current_source_cursor_next231' => $cursor,
            'current_source_close_token_next231' => $closeToken,
            'expected_current_source_close_token_next231' => $expectedCloseToken,
            'current_source_close_matches_next231' => $closeMatches,
            'current_view_cookie_next231' => $viewCookie,
            'current_trigger_cookie_next231' => $triggerCookie,
            'required_current_source_closures_next231' => $requiredClosures,
            'acknowledged_current_source_closures_next231' => $acknowledgedClosures,
            'missing_current_source_closures_next231' => $missingClosures,
            'unexpected_current_source_closures_next231' => $unexpectedClosures,
            'require_current_source_close_order_next231' => $requireOrder,
            'current_source_close_order_matches_next231' => $orderMatches,
            'current_source_close_complete_next231' => $closeComplete,
            'next_source_visible_after_current_source_close_next231' => $nextVisible,
            'current_source_rows_next231' => $currentRows,
            'attempted_next_source_rows_next231' => $nextRows,
            'visible_returning_rows_next231' => $visibleRows,
            'held_next_source_rows_next231' => $heldRows,
            'visible_returning_payloads_next231' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next231' => array_column($heldRows, 'returning'),
            'current_source_row_count_next231' => count($currentRows),
            'attempted_next_source_row_count_next231' => count($nextRows),
            'visible_row_count_next231' => count($visibleRows),
            'held_next_row_count_next231' => count($heldRows),
            'blocked_reasons_next231' => $blockedReasons,
            'current_source_close_plan_next231' => [
                'base_next_source_visible' => $baseVisible,
                'close_token_matches' => $closeMatches,
                'required_closures' => $requiredClosures,
                'acknowledged_closures' => $acknowledgedClosures,
                'missing_closures' => $missingClosures,
                'unexpected_closures' => $unexpectedClosures,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'close_complete' => $closeComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-cursor-close'
                    : 'hold-next-source-until-current-returning-cursor-close',
            ],
            'yield_boundary_next231' => $nextVisible
                ? 'recursive-view-returning-next231-current-cursor-close-then-next'
                : 'recursive-view-returning-next231-current-cursor-close-fences-next',
            'dependency_closure_next231' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-close-handoff',
            'dependencies_next231' => array_values(array_unique(array_merge($base['dependencies_next222'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next231',
                'sqlite-returning-current-source-cursor-close-handoff',
                'wordpress-recursive-view-returning-current-source-next231',
            ]))),
            'non_overlap_next231' => 'adds current RETURNING cursor close admission after accepted next222 source-ticket handoff; avoids accepted trigger recursive view RETURNING next157-next222 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function currentSourceCloseReceipts(array $rows, string $cursor, string $closeToken, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $cursor,
                $closeToken,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_ticket_receipt_next222'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 48);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedCurrentSourceClosures(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_closures_next231'] ?? false) === true) {
            return $required;
        }

        return self::currentSourceClosureList($options['acknowledged_current_source_closures_next231'] ?? [], 'acknowledged current source closures');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function currentSourceClosureList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} contain a malformed close receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function currentSourceCloseRows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentSourceCloseRows(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $cursor,
        string $closeToken,
        string $viewCookie,
        string $triggerCookie,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_close_phase_next231' => $phase,
                'current_source_cursor_next231' => $cursor,
                'current_source_close_token_next231' => $closeToken,
                'current_view_cookie_next231' => $viewCookie,
                'current_trigger_cookie_next231' => $triggerCookie,
                'current_source_close_receipt_next231' => $receipts[$index] ?? null,
                'visible_after_current_source_close_next231' => $visible,
                'held_by_current_source_close_reasons_next231' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function currentSourceCloseBlockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        bool $closeMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next231 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next222-current-source-ticket-not-published';
        }
        if (!$closeMatches) {
            $reasons[] = 'current-source-close-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-close-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-close-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-close-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function currentSourceCloseStatus(
        bool $baseVisible,
        bool $closeMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next231-cursor-close-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next231-base-held';
        }
        if (!$closeMatches) {
            return 'trigger-recursive-view-returning-current-source-next231-close-token-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next231-cursor-close-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next231-cursor-close-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next231-cursor-close-empty-held';
    }

    private static function currentSourceCloseToken(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} is malformed");
        }

        return $value;
    }
}
