<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext171Plan
{
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
    public static function execute(
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

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext167Plan::execute(
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
            'cursor_watermark_next171' => self::watermark($base['drain_cursor'], $acknowledged, $currentPageCount),
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

    private static function watermark(string $cursor, int $acknowledged, int $total): string
    {
        return $cursor . ':ack-' . $acknowledged . '-of-' . $total;
    }
}
