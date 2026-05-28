<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext184Plan
{
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
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $handoffToken = self::token((string) ($options['handoff_token'] ?? 'wp.returning.current.source.handoff.184'), 'handoff token');
        $expectedHandoffToken = self::token((string) ($options['expected_handoff_token'] ?? $handoffToken), 'expected handoff token');
        $baseOptions = $options + [
            'cursor_name' => 'wp_recursive_view_returning_cursor_184',
            'current_generation' => 'wp-current-returning-184',
            'next_generation' => 'wp-next-returning-184',
            'checkpoint_name' => 'wp_recursive_view_checkpoint_184',
            'savepoint' => 'wp_recursive_view_returning_next184',
        ];

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext181Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $currentCheckpoints = self::phaseCheckpoints($base['visible_checkpoints'] ?? [], 'current');
        $currentTokens = array_column($currentCheckpoints, 'checkpoint_token');
        $acknowledged = self::acknowledgedTokens($options, $currentTokens);
        $missing = array_values(array_diff($currentTokens, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $currentTokens));
        $handoffMatches = $handoffToken === $expectedHandoffToken;
        $currentComplete = $currentTokens !== [] && $missing === [] && $unexpected === [];
        $baseNextAdmitted = (bool) ($base['replay_plan']['next_admitted'] ?? false);
        $canExposeNext = $handoffMatches && $currentComplete && $baseNextAdmitted && ($base['pending_checkpoints'] ?? []) === [];

        $currentRows = self::handoffRows($base['base']['current_source_rows'] ?? [], 'current', true, $handoffToken, []);
        $nextBlockReasons = self::blockReasons($handoffMatches, $currentComplete, $baseNextAdmitted, $missing, $unexpected, $base);
        $nextRows = self::handoffRows($base['base']['attempted_next_source_rows'] ?? [], 'next', $canExposeNext, $handoffToken, $nextBlockReasons);
        $visibleRows = array_values(array_filter(array_merge($currentRows, $nextRows), static fn (array $row): bool => $row['visible_after_handoff']));
        $heldRows = array_values(array_filter($nextRows, static fn (array $row): bool => !$row['visible_after_handoff']));
        $currentAcks = self::checkpointAcks($currentCheckpoints, $acknowledged, $handoffToken);
        $nextAcks = self::checkpointAcks($base['pending_checkpoints'] ?? [], [], $handoffToken);

        return [
            'status' => self::status($canExposeNext, $handoffMatches, $currentComplete, $baseNextAdmitted),
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
    private static function acknowledgedTokens(array $options, mixed $currentTokens): array
    {
        if (($options['auto_ack_current'] ?? false) === true) {
            return self::tokenList($currentTokens, 'current checkpoint tokens');
        }

        return self::tokenList($options['acknowledged_current_checkpoints'] ?? [], 'acknowledged current checkpoints');
    }

    /**
     * @param mixed $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function handoffRows(mixed $rows, string $phase, bool $visible, string $handoffToken, array $blockReasons): array
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
    private static function checkpointAcks(mixed $checkpoints, array $acknowledged, string $handoffToken): array
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
    private static function phaseCheckpoints(mixed $checkpoints, string $phase): array
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
    private static function tokenList(mixed $tokens, string $label): array
    {
        if (!is_array($tokens) || !array_is_list($tokens)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next184 {$label} must be a list");
        }

        return array_values(array_unique(array_map(static fn (mixed $token): string => self::token((string) $token, $label), $tokens)));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockReasons(bool $handoffMatches, bool $currentComplete, bool $baseNextAdmitted, array $missing, array $unexpected, array $base): array
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

    private static function status(bool $canExposeNext, bool $handoffMatches, bool $currentComplete, bool $baseNextAdmitted): string
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

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next184 {$label} is malformed");
        }

        return $value;
    }
}
