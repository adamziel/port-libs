<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Plan
{
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
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $acknowledged = self::ordinals($options['fresh_acknowledged_ordinals'] ?? []);
        $nextToken = self::token((string) ($options['next_source_token'] ?? 'wp.next.source.189'), 'next source token');
        $expectedNextToken = self::token((string) ($options['expected_next_source_token'] ?? $nextToken), 'expected next source token');
        $nextCursor = self::token((string) ($options['next_cursor'] ?? 'wp.returning.next.cursor.189'), 'next cursor');
        $expectedResetGeneration = self::token((string) ($options['expected_reset_generation'] ?? ($base['post_reset_rebind_plan_next186']['reset_generation'] ?? '')), 'expected reset generation');
        $resetGenerationMatches = hash_equals($expectedResetGeneration, (string) ($base['post_reset_rebind_plan_next186']['reset_generation'] ?? ''));
        $tokenMatches = hash_equals($nextToken, $expectedNextToken);
        $freshRows = self::rows($base['fresh_returning_rows_next186'] ?? [], 'fresh rows');
        $freshOrdinals = array_column($freshRows, 'returning_row_ordinal');
        $currentRowsAcknowledged = $freshRows !== [] && self::acknowledgesAllFreshRows($freshOrdinals, $acknowledged);
        $canAdmitNext = $currentRowsAcknowledged && $tokenMatches && $resetGenerationMatches && $base['blocked_reasons_next186'] === [];
        $nextRows = $canAdmitNext
            ? self::nextRows($nextInput, self::view($nextView), $returning, $nextToken, $nextCursor, (string) ($options['next_generation'] ?? 'wp-next-returning-189'))
            : [];

        return [
            'status_next189' => self::status($canAdmitNext, $currentRowsAcknowledged, $tokenMatches, $resetGenerationMatches, $base),
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
            'blocked_reasons_next189' => self::blockedReasons($currentRowsAcknowledged, $tokenMatches, $resetGenerationMatches, $base),
            'handoff_plan_next189' => [
                'fresh_rows_required' => count($freshRows),
                'fresh_rows_acknowledged' => count(array_intersect($freshOrdinals, $acknowledged)),
                'next_rows_visible' => count($nextRows),
                'decision' => self::decision($canAdmitNext, $currentRowsAcknowledged, $tokenMatches, $resetGenerationMatches, $base),
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
    private static function acknowledgesAllFreshRows(array $required, array $acknowledged): bool
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
    private static function nextRows(array $input, array $view, array $returning, string $token, string $cursor, string $generation): array
    {
        $mapping = self::mapping($view['mapping'] ?? []);
        $out = [];
        foreach (self::rows($input, 'next input') as $ordinal => $row) {
            $new = self::mappedRow($row, $mapping);
            $out[] = [
                'statement_source' => 'next-source',
                'returning_row_ordinal' => $ordinal,
                'returning' => self::returningPayload($returning, $new, $view, $ordinal),
                'returning_option_name' => (string) ($new['option_name'] ?? $new['name'] ?? ''),
                'next_source_token_next189' => $token,
                'next_cursor_next189' => $cursor,
                'next_generation_next189' => $generation,
                'source_signature_next189' => self::signature($view, $returning),
            ];
        }

        return $out;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayload(array $returning, array $new, array $view, int $ordinal): array
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
    private static function mappedRow(array $row, array $mapping): array
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
    private static function mapping(mixed $mapping): array
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
    private static function view(mixed $view): array
    {
        if (!is_array($view)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next189 view is malformed');
        }
        self::mapping($view['mapping'] ?? []);

        return $view;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
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
    private static function ordinals(mixed $ordinals): array
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
    private static function signature(array $view, array $returning): string
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

    private static function status(bool $canAdmitNext, bool $currentRowsAcknowledged, bool $tokenMatches, bool $resetGenerationMatches, array $base): string
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
    private static function blockedReasons(bool $currentRowsAcknowledged, bool $tokenMatches, bool $resetGenerationMatches, array $base): array
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

    private static function decision(bool $canAdmitNext, bool $currentRowsAcknowledged, bool $tokenMatches, bool $resetGenerationMatches, array $base): string
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

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next189 {$label} is malformed");
        }

        return $token;
    }
}
