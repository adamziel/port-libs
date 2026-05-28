<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext183Plan
{
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
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $rollbackToken = self::token((string) ($options['rollback_token'] ?? 'wp.rollback.current.183'), 'rollback token');
        $expectedRollbackToken = self::token((string) ($options['expected_rollback_token'] ?? $rollbackToken), 'expected rollback token');
        $resetGeneration = self::token((string) ($options['reset_generation'] ?? 'wp-current-reset-183'), 'reset generation');
        $rollbackRequested = (bool) ($options['rollback_current_source'] ?? true);
        $commitCurrent = (bool) ($options['commit_current_source'] ?? false);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext180Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $rollbackTokenMatches = $rollbackToken === $expectedRollbackToken;
        $currentRows = self::rows($base['current_source_rows_next180'] ?? [], 'current rows');
        $attemptedNextRows = self::rows($base['attempted_next_source_rows_next180'] ?? [], 'attempted next rows');
        $currentRollbackApplied = $rollbackRequested && $rollbackTokenMatches && !$commitCurrent;
        $nextBaseAdmitted = (bool) ($base['next_source_admitted_next180'] ?? false);
        $nextVisible = $nextBaseAdmitted && $commitCurrent && !$currentRollbackApplied && $rollbackTokenMatches;

        $currentAfterBarrier = self::barrierRows(
            $currentRows,
            !$currentRollbackApplied,
            $currentRollbackApplied ? ['current-source-rollback-token-applied'] : [],
            $resetGeneration,
        );
        $nextAfterBarrier = self::barrierRows(
            $attemptedNextRows,
            $nextVisible,
            self::nextBarrierReasons($base, $currentRollbackApplied, $rollbackTokenMatches, $commitCurrent),
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
            'status_next183' => self::status($currentRollbackApplied, $rollbackRequested, $rollbackTokenMatches, $commitCurrent, $nextBaseAdmitted),
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
    private static function rows(mixed $rows, string $label): array
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
    private static function barrierRows(array $rows, bool $visible, array $reasons, string $resetGeneration): array
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
    private static function nextBarrierReasons(array $base, bool $currentRollbackApplied, bool $rollbackTokenMatches, bool $commitCurrent): array
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

    private static function status(bool $rollbackApplied, bool $rollbackRequested, bool $tokenMatches, bool $commitCurrent, bool $nextBaseAdmitted): string
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

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next183 {$label} is malformed");
        }

        return $value;
    }
}
