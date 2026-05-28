<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $nextRows = self::rows($base['next_source_rows_next189'] ?? [], 'next source rows');
        $requiredOrdinals = array_column($nextRows, 'returning_row_ordinal');
        $acknowledgedOrdinals = self::ordinals($options['next_acknowledged_ordinals'] ?? []);
        $nextRowsAcknowledged = $nextRows !== [] && self::sameOrdinals($requiredOrdinals, $acknowledgedOrdinals);
        $nextCursor = self::token((string) ($options['next_cursor'] ?? ($base['next_cursor_next189'] ?? 'wp.returning.next.cursor.192')), 'next cursor');
        $closeCursor = self::token((string) ($options['close_next_cursor'] ?? $nextCursor), 'close next cursor');
        $cursorMatches = hash_equals($nextCursor, $closeCursor);
        $followingToken = self::token((string) ($options['following_current_source_token'] ?? 'wp.current.source.following.192'), 'following current source token');
        $expectedFollowingToken = self::token((string) ($options['expected_following_current_source_token'] ?? $followingToken), 'expected following current source token');
        $followingTokenMatches = hash_equals($followingToken, $expectedFollowingToken);
        $followingCursor = self::token((string) ($options['following_cursor'] ?? 'wp.returning.following.cursor.192'), 'following cursor');
        $baseAdmittedNext = ($base['status_next189'] ?? '') === 'trigger-recursive-view-returning-current-source-next189-next-source-visible';
        $canAdmitFollowing = $baseAdmittedNext && $nextRowsAcknowledged && $cursorMatches && $followingTokenMatches;
        $followingRows = $canAdmitFollowing
            ? self::followingRows(
                self::rows($options['following_current_input'] ?? [], 'following current input'),
                self::view($options['following_current_view'] ?? $currentView),
                $returning,
                $followingToken,
                $followingCursor,
                (string) ($options['following_generation'] ?? 'wp-following-current-192'),
            )
            : [];

        return [
            'status_next192' => self::status($canAdmitFollowing, $baseAdmittedNext, $nextRowsAcknowledged, $cursorMatches, $followingTokenMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'next_required_ordinals_next192' => $requiredOrdinals,
            'next_acknowledged_ordinals_next192' => $acknowledgedOrdinals,
            'next_source_rows_acknowledged_next192' => $nextRowsAcknowledged,
            'next_cursor_next192' => $nextCursor,
            'close_next_cursor_next192' => $closeCursor,
            'next_cursor_close_matches_next192' => $cursorMatches,
            'following_current_source_token_next192' => $followingToken,
            'expected_following_current_source_token_next192' => $expectedFollowingToken,
            'following_current_source_token_matches_next192' => $followingTokenMatches,
            'following_cursor_next192' => $followingCursor,
            'following_current_rows_next192' => $followingRows,
            'following_current_payloads_next192' => array_column($followingRows, 'returning'),
            'following_current_row_count_next192' => count($followingRows),
            'blocked_reasons_next192' => self::blockedReasons($base, $baseAdmittedNext, $nextRowsAcknowledged, $cursorMatches, $followingTokenMatches),
            'cursor_close_plan_next192' => [
                'next_rows_required' => count($nextRows),
                'next_rows_acknowledged' => count(array_intersect($requiredOrdinals, $acknowledgedOrdinals)),
                'next_cursor_matches_close_token' => $cursorMatches,
                'following_rows_visible' => count($followingRows),
                'decision' => self::decision($canAdmitFollowing, $baseAdmittedNext, $nextRowsAcknowledged, $cursorMatches, $followingTokenMatches),
                'resume_after_next_ordinal' => $nextRows === [] ? null : max($requiredOrdinals),
                'following_cursor' => $followingCursor,
            ],
            'yield_boundary_next192' => $canAdmitFollowing
                ? 'recursive-view-returning-next192-next-cursor-drained-following-current-visible'
                : 'recursive-view-returning-next192-next-cursor-fences-following-current',
            'dependency_closure_next192' => 'no new support component needed; reuses next189 next-source admission and adds next-cursor close fencing for the following current source',
            'dependencies_next192' => array_values(array_unique(array_merge($base['dependencies_next189'], [
                'sqlite-trigger-recursive-view-returning-current-source-next192',
                'sqlite-returning-next-cursor-close-following-current-source-admission',
                'wordpress-recursive-view-returning-current-source-next192',
            ]))),
            'non_overlap_next192' => 'extends accepted next189 row-ack next-source admission with the later next-source cursor-close barrier before a following current-source generation; avoids next183 rollback invalidation, next186 post-reset rebind, next189 current-row acknowledgements, row-value, UPSERT, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @param list<int> $required
     * @param list<int> $acknowledged
     */
    private static function sameOrdinals(array $required, array $acknowledged): bool
    {
        sort($required);
        sort($acknowledged);

        return $required === $acknowledged;
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function followingRows(array $input, array $view, array $returning, string $token, string $cursor, string $generation): array
    {
        $mapping = self::mapping($view['mapping'] ?? []);
        $out = [];
        foreach ($input as $ordinal => $row) {
            $new = self::mappedRow($row, $mapping);
            $out[] = [
                'statement_source' => 'following-current',
                'returning_row_ordinal' => $ordinal,
                'returning' => self::returningPayload($returning, $new, $view, $ordinal),
                'returning_option_name' => (string) ($new['option_name'] ?? $new['name'] ?? ''),
                'following_current_source_token_next192' => $token,
                'following_cursor_next192' => $cursor,
                'following_generation_next192' => $generation,
                'source_signature_next192' => self::signature($view, $returning),
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
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'following-current', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'] ?? null,
                'new.option_value' => $new['option_value'] ?? null,
                'old.option_value' => null,
                'event' => 'following-current',
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
            throw new InvalidArgumentException('SQLite recursive view RETURNING next192 view mapping is malformed');
        }
        $out = [];
        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target) || $source === '' || $target === '') {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next192 view mapping entry is malformed');
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
            throw new InvalidArgumentException('SQLite recursive view RETURNING next192 following view is malformed');
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next192 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next192 {$label} row is malformed");
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
            throw new InvalidArgumentException('SQLite recursive view RETURNING next192 acknowledged ordinals must be a list');
        }
        $out = [];
        foreach ($ordinals as $ordinal) {
            if (!is_int($ordinal) || $ordinal < 0) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next192 acknowledged ordinals must be non-negative integers');
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

    /**
     * @return list<string>
     */
    private static function blockedReasons(array $base, bool $baseAdmittedNext, bool $nextRowsAcknowledged, bool $cursorMatches, bool $followingTokenMatches): array
    {
        $reasons = [];
        if (!$baseAdmittedNext) {
            $reasons = array_merge($reasons, $base['blocked_reasons_next189'] ?? ['next-source-not-visible']);
        }
        if (!$nextRowsAcknowledged) {
            $reasons[] = 'next-source-returning-rows-not-acknowledged';
        }
        if (!$cursorMatches) {
            $reasons[] = 'next-cursor-close-token-mismatch';
        }
        if (!$followingTokenMatches) {
            $reasons[] = 'following-current-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function decision(bool $canAdmitFollowing, bool $baseAdmittedNext, bool $nextRowsAcknowledged, bool $cursorMatches, bool $followingTokenMatches): string
    {
        if ($canAdmitFollowing) {
            return 'admit-following-current-after-next-cursor-close';
        }
        if (!$baseAdmittedNext) {
            return 'hold-following-current-until-next-source-visible';
        }
        if (!$nextRowsAcknowledged) {
            return 'hold-following-current-until-next-returning-acks';
        }
        if (!$cursorMatches) {
            return 'hold-following-current-next-cursor-close-token';
        }
        if (!$followingTokenMatches) {
            return 'hold-following-current-source-token';
        }

        return 'hold-following-current';
    }

    private static function status(bool $canAdmitFollowing, bool $baseAdmittedNext, bool $nextRowsAcknowledged, bool $cursorMatches, bool $followingTokenMatches): string
    {
        if ($canAdmitFollowing) {
            return 'trigger-recursive-view-returning-current-source-next192-following-current-visible';
        }
        if (!$baseAdmittedNext) {
            return 'trigger-recursive-view-returning-current-source-next192-next-source-held';
        }
        if (!$nextRowsAcknowledged) {
            return 'trigger-recursive-view-returning-current-source-next192-awaiting-next-row-acks';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next192-next-cursor-held';
        }
        if (!$followingTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next192-following-token-held';
        }

        return 'trigger-recursive-view-returning-current-source-next192-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next192 {$label} is malformed");
        }

        return $token;
    }
}
