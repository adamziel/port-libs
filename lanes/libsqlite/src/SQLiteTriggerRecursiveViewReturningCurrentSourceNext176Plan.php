<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext176Plan
{
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
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $prepared = SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['admit_next_source' => true],
        );

        $currentPages = self::pages($prepared['current_returning_pages'] ?? null);
        $acknowledged = self::acknowledgedIndexes($options['acknowledged_current_page_indexes'] ?? array_keys($currentPages), count($currentPages));
        $contiguous = self::isContiguousPrefix($acknowledged);
        $drainedCount = $contiguous ? count($acknowledged) : 0;

        $drainOptions = $options;
        $drainOptions['drained_current_pages'] = $drainedCount;
        $drainOptions['admit_next_source'] = (bool) ($options['admit_next_source'] ?? false);
        unset($drainOptions['acknowledged_current_page_indexes']);

        $gated = SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Plan::execute(
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
    private static function pages(mixed $pages): array
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
    private static function acknowledgedIndexes(mixed $indexes, int $total): array
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
    private static function isContiguousPrefix(array $indexes): bool
    {
        foreach ($indexes as $offset => $index) {
            if ($index !== $offset) {
                return false;
            }
        }

        return true;
    }
}
