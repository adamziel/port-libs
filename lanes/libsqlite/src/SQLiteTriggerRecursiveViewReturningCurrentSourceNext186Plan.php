<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Plan
{
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
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $postResetToken = self::token((string) ($options['post_reset_current_source_token'] ?? 'wp.current.source.postreset.186'), 'post reset current source token');
        $expectedPostResetToken = self::token((string) ($options['expected_post_reset_current_source_token'] ?? $postResetToken), 'expected post reset current source token');
        $postResetCursor = self::token((string) ($options['post_reset_cursor'] ?? 'wp.returning.postreset.cursor.186'), 'post reset cursor');
        $reuseStaleCursor = (bool) ($options['reuse_stale_returning_cursor'] ?? false);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext183Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $postResetView = self::view($options['post_reset_view'] ?? $currentView);
        $postResetInput = self::rows($options['post_reset_input'] ?? $currentInput, 'post reset input');
        $tokenMatches = hash_equals($postResetToken, $expectedPostResetToken);
        $resetApplied = (bool) ($base['current_source_rollback_applied_next183'] ?? false);
        $staleRows = self::rows($base['invalidated_current_rows_next183'] ?? [], 'invalidated current rows');
        $staleReturningRows = array_column($staleRows, 'returning');
        $freshRows = ($resetApplied && $tokenMatches && !$reuseStaleCursor)
            ? self::freshRows($postResetInput, $postResetView, $returning, $postResetToken, $postResetCursor, (string) $base['reset_generation_next183'])
            : [];
        $blockedReasons = self::blockedReasons($resetApplied, $tokenMatches, $reuseStaleCursor, $base);

        return [
            'status_next186' => self::status($resetApplied, $tokenMatches, $reuseStaleCursor),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'base' => $base,
            'post_reset_current_source_token_next186' => $postResetToken,
            'expected_post_reset_current_source_token_next186' => $expectedPostResetToken,
            'post_reset_current_source_token_matches_next186' => $tokenMatches,
            'post_reset_cursor_next186' => $postResetCursor,
            'reuse_stale_returning_cursor_next186' => $reuseStaleCursor,
            'post_reset_view_signature_next186' => self::signature($postResetView, $returning),
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
                'decision' => self::decision($resetApplied, $tokenMatches, $reuseStaleCursor),
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
    private static function freshRows(array $input, array $view, array $returning, string $token, string $cursor, string $resetGeneration): array
    {
        $mapping = self::mapping($view['mapping'] ?? []);
        $out = [];
        foreach ($input as $ordinal => $row) {
            $new = self::mappedRow($row, $mapping);
            $payload = self::returningPayload($returning, $new, $view, $ordinal);
            $out[] = [
                'statement_source' => 'post-reset-current',
                'returning_row_ordinal' => $ordinal,
                'returning' => $payload,
                'returning_option_name' => (string) ($new['option_name'] ?? $new['name'] ?? ''),
                'post_reset_current_source_token_next186' => $token,
                'post_reset_cursor_next186' => $cursor,
                'reset_generation_next186' => $resetGeneration,
                'source_signature_next186' => self::signature($view, $returning),
                'stale_cursor_reused_next186' => false,
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
    private static function view(mixed $view): array
    {
        if (!is_array($view)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next186 post reset view is malformed');
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

    /**
     * @return list<string>
     */
    private static function blockedReasons(bool $resetApplied, bool $tokenMatches, bool $reuseStaleCursor, array $base): array
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

    private static function status(bool $resetApplied, bool $tokenMatches, bool $reuseStaleCursor): string
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

    private static function decision(bool $resetApplied, bool $tokenMatches, bool $reuseStaleCursor): string
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

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next186 {$label} is malformed");
        }

        return $value;
    }
}
