<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Plan
{
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
    public static function execute(
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

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext187Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $currentRows = self::rows($base['current_source_rows'] ?? [], 'current source rows');
        $attemptedNextRows = self::rows($base['attempted_next_source_rows'] ?? [], 'attempted next source rows');
        $lastCurrentResume = $currentRows === [] ? null : (string) $currentRows[array_key_last($currentRows)]['resume_token'];
        $firstNextResume = $attemptedNextRows === [] ? null : (string) $attemptedNextRows[0]['resume_token'];
        $prefix = self::token((string) ($options['resume_source_prefix'] ?? 'wp.returning.current.source.resume.190'), 'resume source prefix');
        $expectedResumeSource = self::token(
            (string) ($options['expected_resume_source_token'] ?? self::resumeToken($prefix, $lastCurrentResume, $currentView, $returning)),
            'expected resume source token',
        );
        $actualResumeSource = self::token(
            (string) ($options['resume_source_token'] ?? self::resumeToken($prefix, $lastCurrentResume, $currentView, $returning)),
            'resume source token',
        );
        $expectedNextResume = self::token(
            (string) ($options['expected_next_source_resume_token'] ?? ($firstNextResume ?? 'no-next-source-row')),
            'expected next source resume token',
        );
        $actualNextResume = self::token(
            (string) ($options['next_source_resume_token'] ?? ($firstNextResume ?? 'no-next-source-row')),
            'next source resume token',
        );
        $expectedSignature = self::signatureOption($options['expected_source_signature'] ?? self::sourceSignature($currentView, $returning), 'expected source signature');
        $actualSignature = self::signatureOption($options['source_signature'] ?? self::sourceSignature($currentView, $returning), 'source signature');

        $resumeMatches = hash_equals($expectedResumeSource, $actualResumeSource);
        $nextResumeMatches = hash_equals($expectedNextResume, $actualNextResume);
        $signatureMatches = hash_equals($expectedSignature, $actualSignature);
        $baseExposed = (bool) ($base['next_source_exposed_after_drain_ticket'] ?? false);
        $resumeAdmitsNext = $baseExposed && $resumeMatches && $nextResumeMatches && $signatureMatches;
        $blockReasons = self::blockReasons($base['block_reasons'] ?? [], $baseExposed, $resumeMatches, $nextResumeMatches, $signatureMatches);

        $gatedCurrentRows = self::resumeRows($currentRows, $actualResumeSource, true, []);
        $gatedNextRows = self::resumeRows($attemptedNextRows, $actualResumeSource, $resumeAdmitsNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($gatedCurrentRows, $gatedNextRows), static fn (array $row): bool => $row['visible_after_resume_source']));
        $heldRows = array_values(array_filter($gatedNextRows, static fn (array $row): bool => !$row['visible_after_resume_source']));

        return [
            'status' => self::status($resumeAdmitsNext, $baseExposed, $resumeMatches, $nextResumeMatches, $signatureMatches),
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
    private static function resumeRows(array $rows, string $resumeSource, bool $visible, array $blockReasons): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function blockReasons(mixed $baseReasons, bool $baseExposed, bool $resumeMatches, bool $nextResumeMatches, bool $signatureMatches): array
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

    private static function status(bool $admitted, bool $baseExposed, bool $resumeMatches, bool $nextResumeMatches, bool $signatureMatches): string
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
    private static function resumeToken(string $prefix, ?string $lastCurrentResume, array $view, array $returning): string
    {
        $material = ($lastCurrentResume ?? 'no-current-row') . '|' . self::sourceSignature($view, $returning);

        return $prefix . ':' . substr(hash('sha256', $material), 0, 16);
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function sourceSignature(array $view, array $returning): string
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

    private static function signatureOption(mixed $value, string $label): string
    {
        $string = (string) $value;
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $string) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next190 {$label} is malformed");
        }

        return $string;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next190 {$label} is malformed");
        }

        return $value;
    }
}
