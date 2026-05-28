<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext175Plan
{
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
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $action = self::action((string) ($options['savepoint_action'] ?? 'hold'));
        $restartCursor = self::token((string) ($options['restart_cursor'] ?? 'wp-recursive-view-returning-restart-175'), 'restart cursor');
        $epoch = self::epoch($options['current_source_epoch'] ?? 0);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Plan::execute(
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
        $nextPrepared = self::pages($base['next_returning_pages'] ?? [], 'next returning pages');
        $nextAdmittedByCursor = (bool) ($base['next_source_admitted_next173'] ?? false);
        $canRelease = $action === 'release' && $currentExhausted && $sourceMatches && $nextAdmittedByCursor;
        $rolledBack = $action === 'rollback';
        $held = !$canRelease;

        $blockedReasons = self::strings($base['next_source_block_reasons_next173'] ?? [], 'block reasons');
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

        $drainedCurrent = self::pages($base['drained_current_pages'] ?? [], 'drained current pages');
        $pendingCurrent = self::pages($base['pending_current_pages'] ?? [], 'pending current pages');
        $visiblePages = $canRelease
            ? self::pages($base['visible_returning_pages_next173'] ?? [], 'visible returning pages')
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

    private static function action(string $action): string
    {
        if (!in_array($action, ['hold', 'release', 'rollback'], true)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next175 savepoint action is unsupported');
        }

        return $action;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next175 {$label} is malformed");
        }

        return $value;
    }

    private static function epoch(mixed $value): int
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
    private static function pages(mixed $pages, string $label): array
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
    private static function strings(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next175 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }
}
