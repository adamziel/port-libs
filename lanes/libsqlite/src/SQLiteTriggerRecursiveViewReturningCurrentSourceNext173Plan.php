<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Plan
{
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
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $prepared = SQLiteTriggerRecursiveViewReturningCurrentSourceNext167Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['admit_next_source' => true],
        );

        $currentPages = self::pages($prepared['current_returning_pages'], 'current returning pages');
        $nextPages = self::pages($prepared['next_returning_pages'], 'next returning pages');
        $currentSignature = (string) ($prepared['source_signatures']['current'] ?? '');
        $resumeSignature = (string) ($options['resume_source_signature'] ?? $currentSignature);
        if ($resumeSignature === '') {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next173 resume source signature is empty');
        }

        $drainedCount = self::drainedCount($options['drained_current_pages'] ?? count($currentPages), count($currentPages));
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
    private static function pages(mixed $pages, string $label): array
    {
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next173 {$label} are malformed");
        }

        return $pages;
    }

    private static function drainedCount(mixed $value, int $total): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > $total) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next173 drained current pages is out of range');
        }

        return $count;
    }
}
