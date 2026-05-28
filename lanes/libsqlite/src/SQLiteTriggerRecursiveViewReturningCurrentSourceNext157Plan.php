<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Plan
{
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
    public static function execute(
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

        $baseRows = self::normalizeRows($rows);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');
        $maxDepth = self::positiveInt($options['max_depth'] ?? 8, 'max depth');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_next157'), 'savepoint');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);

        $current = self::runViewSource($baseRows, $currentRoots, $currentView, $returning, $maxDepth, 'current');
        $nextBaseRows = $admitNext ? $current['rows'] : $baseRows;
        $next = self::runViewSource($nextBaseRows, $nextRoots, $nextView, $returning, $maxDepth, 'next');

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-returning-next-source-admitted-next157'
                : 'trigger-recursive-view-returning-current-source-pinned-next157',
            'savepoint' => $savepoint,
            'current_view' => self::viewSummary($currentView),
            'next_view' => self::viewSummary($nextView),
            'visible_view' => self::viewSummary($admitNext ? $nextView : $currentView),
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
    private static function normalizeRows(array $rows): array
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
    private static function normalizeView(array $view, string $label): array
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
            'name' => self::identifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::token((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifier((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::token((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'root_key' => self::identifier((string) ($view['root_key'] ?? ''), $label . ' root key'),
            'parent_key' => self::identifier((string) ($view['parent_key'] ?? ''), $label . ' parent key'),
            'columns' => array_map(static fn (mixed $column): string => self::identifier((string) $column, $label . ' column'), $columns),
            'where' => $where,
            'order_by' => isset($view['order_by']) ? self::identifier((string) $view['order_by'], $label . ' order column') : null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $roots
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,where:callable(array<string,mixed>,string,int):bool,order_by:?string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,recursive_rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,changes:int,statement_rows:int,depths:list<int>}
     */
    private static function runViewSource(array $rows, array $roots, array $view, array $returning, int $maxDepth, string $phase): array
    {
        $recursiveRows = self::recursiveRows($rows, $roots, $view, $maxDepth);
        $yield = [];
        $returningRows = [];
        $depths = [];

        foreach ($recursiveRows as $ordinal => $viewRow) {
            $incoming = self::triggerRow($viewRow, $view, $phase);
            $rows = self::upsertOption($rows, $incoming);
            $returningRow = self::returningRow($returning, $incoming, $viewRow, $view['trigger_source'], $ordinal);
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
    private static function recursiveRows(array $rows, array $roots, array $view, int $maxDepth): array
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
    private static function triggerRow(array $viewRow, array $view, string $phase): array
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
    private static function upsertOption(array $rows, array $incoming): array
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
    private static function returningRow(array $returning, array $incoming, array $viewRow, string $triggerSource, int $ordinal): array
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
    private static function viewSummary(array $view): array
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

    private static function positiveInt(mixed $value, string $label): int
    {
        $value = (int) $value;
        if ($value < 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next157 {$label} must be positive");
        }

        return $value;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next157 {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next157 {$label} is malformed");
        }

        return $value;
    }
}
