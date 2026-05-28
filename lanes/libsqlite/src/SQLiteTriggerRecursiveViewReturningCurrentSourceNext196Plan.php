<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext196Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $followingRows = self::rows($base['following_current_rows_next192'] ?? [], 'following current rows');
        $followingVisible = ($base['status_next192'] ?? '') === 'trigger-recursive-view-returning-current-source-next192-following-current-visible';
        $recursiveColumn = self::identifier((string) ($options['recursive_child_column'] ?? 'spawn_child'), 'recursive child column');
        $recursiveSuffix = self::token((string) ($options['recursive_child_suffix'] ?? '_child'), 'recursive child suffix');
        $currentToken = self::token((string) ($options['following_current_source_token'] ?? ($base['following_current_source_token_next192'] ?? 'wp.current.source.following.196')), 'following current source token');
        $childToken = self::token((string) ($options['recursive_child_source_token'] ?? 'wp.current.source.recursive.child.196'), 'recursive child source token');
        $expectedChildToken = self::token((string) ($options['expected_recursive_child_source_token'] ?? $childToken), 'expected recursive child source token');
        $cursor = self::token((string) ($options['recursive_child_cursor'] ?? 'wp.returning.recursive.child.cursor.196'), 'recursive child cursor');
        $generation = self::token((string) ($options['recursive_child_generation'] ?? 'wp-recursive-child-current-196'), 'recursive child generation');
        $childRows = $followingVisible
            ? self::childRows($followingRows, $returning, $currentView, $recursiveColumn, $recursiveSuffix, $currentToken, $childToken, $cursor, $generation)
            : [];
        $required = array_column($childRows, 'returning_row_ordinal');
        $acknowledged = self::ordinals($options['recursive_child_acknowledged_ordinals'] ?? []);
        $childrenAcknowledged = $childRows !== [] && self::sameOrdinals($required, $acknowledged);
        $tokenMatches = hash_equals($childToken, $expectedChildToken);
        $publishNext = $followingVisible && $childrenAcknowledged && $tokenMatches;
        $blocked = self::blockedReasons($base, $followingVisible, $childrenAcknowledged, $tokenMatches);

        return [
            'status_next196' => self::status($publishNext, $followingVisible, $childrenAcknowledged, $tokenMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'following_current_source_visible_next196' => $followingVisible,
            'recursive_child_column_next196' => $recursiveColumn,
            'recursive_child_suffix_next196' => $recursiveSuffix,
            'following_current_source_token_next196' => $currentToken,
            'recursive_child_source_token_next196' => $childToken,
            'expected_recursive_child_source_token_next196' => $expectedChildToken,
            'recursive_child_source_token_matches_next196' => $tokenMatches,
            'recursive_child_cursor_next196' => $cursor,
            'recursive_child_generation_next196' => $generation,
            'recursive_child_rows_next196' => $childRows,
            'recursive_child_payloads_next196' => array_column($childRows, 'returning'),
            'recursive_child_required_ordinals_next196' => $required,
            'recursive_child_acknowledged_ordinals_next196' => $acknowledged,
            'recursive_child_rows_acknowledged_next196' => $childrenAcknowledged,
            'recursive_child_row_count_next196' => count($childRows),
            'next_source_publish_allowed_next196' => $publishNext,
            'blocked_reasons_next196' => $blocked,
            'current_source_next_plan_next196' => [
                'following_rows_visible' => count($followingRows),
                'recursive_child_rows_required' => count($childRows),
                'recursive_child_rows_acknowledged' => count(array_intersect($required, $acknowledged)),
                'child_source_token_matches' => $tokenMatches,
                'next_source_publish_allowed' => $publishNext,
                'decision' => self::decision($publishNext, $followingVisible, $childrenAcknowledged, $tokenMatches),
                'resume_after_recursive_child_ordinal' => $childRows === [] ? null : max($required),
            ],
            'yield_boundary_next196' => $publishNext
                ? 'recursive-view-returning-next196-following-current-child-returning-drained-next-source'
                : 'recursive-view-returning-next196-following-current-child-returning-fences-next-source',
            'dependency_closure_next196' => 'no new support component needed; reuses next192 following-current admission and adds recursive child RETURNING drain fencing before the next source',
            'dependencies_next196' => array_values(array_unique(array_merge($base['dependencies_next192'], [
                'sqlite-trigger-recursive-view-returning-current-source-next196',
                'sqlite-returning-recursive-child-current-source-fence',
                'wordpress-recursive-view-returning-current-source-next196',
            ]))),
            'non_overlap_next196' => 'extends accepted next192 cursor-close following-current admission with recursive child RETURNING current-source drain fencing; avoids next189 row-ack, next191 fingerprint, next192 cursor-close, row-value RETURNING, UPSERT, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $followingRows
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function childRows(array $followingRows, array $returning, array $view, string $recursiveColumn, string $suffix, string $currentToken, string $childToken, string $cursor, string $generation): array
    {
        $out = [];
        foreach ($followingRows as $parentOrdinal => $parent) {
            $payload = self::payload($parent);
            $shouldSpawn = (bool) ($payload[$recursiveColumn] ?? $parent[$recursiveColumn] ?? false);
            if (!$shouldSpawn) {
                continue;
            }
            $new = [
                'option_name' => (string) ($payload['name'] ?? $payload['option_name'] ?? $parent['returning_option_name'] ?? '') . $suffix,
                'option_value' => (string) ($payload['value'] ?? $payload['option_value'] ?? '') . $suffix,
                $recursiveColumn => false,
            ];
            $out[] = [
                'statement_source' => 'recursive-child-current',
                'parent_returning_row_ordinal' => (int) ($parent['returning_row_ordinal'] ?? $parentOrdinal),
                'returning_row_ordinal' => count($out),
                'returning' => self::returningPayload($returning, $new, $view, count($out)),
                'returning_option_name' => $new['option_name'],
                'parent_following_current_source_token_next196' => $currentToken,
                'recursive_child_source_token_next196' => $childToken,
                'recursive_child_cursor_next196' => $cursor,
                'recursive_child_generation_next196' => $generation,
                'recursive_depth_next196' => 1,
                'source_signature_next196' => self::signature($view, $returning, $childToken),
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
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'recursive-child-current', 1, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'],
                'new.option_value' => $new['option_value'],
                'old.option_value' => null,
                'event' => 'recursive-child-current',
                'depth' => 1,
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
    private static function payload(array $row): array
    {
        $payload = $row['returning'] ?? [];
        if (!is_array($payload)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next196 parent payload is malformed');
        }

        return $payload;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} row is malformed");
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
            throw new InvalidArgumentException('SQLite recursive view RETURNING next196 acknowledged ordinals must be a list');
        }
        $out = [];
        foreach ($ordinals as $ordinal) {
            if (!is_int($ordinal) || $ordinal < 0) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next196 acknowledged ordinals must be non-negative integers');
            }
            $out[] = $ordinal;
        }

        return array_values(array_unique($out));
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
     * @return list<string>
     */
    private static function blockedReasons(array $base, bool $followingVisible, bool $childrenAcknowledged, bool $tokenMatches): array
    {
        $reasons = [];
        if (!$followingVisible) {
            $reasons = array_merge($reasons, $base['blocked_reasons_next192'] ?? ['following-current-source-not-visible']);
        }
        if (!$childrenAcknowledged) {
            $reasons[] = 'recursive-child-returning-rows-not-acknowledged';
        }
        if (!$tokenMatches) {
            $reasons[] = 'recursive-child-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function decision(bool $publishNext, bool $followingVisible, bool $childrenAcknowledged, bool $tokenMatches): string
    {
        if ($publishNext) {
            return 'publish-next-after-recursive-child-current-returning-drain';
        }
        if (!$followingVisible) {
            return 'hold-next-until-following-current-visible';
        }
        if (!$childrenAcknowledged) {
            return 'hold-next-until-recursive-child-returning-acks';
        }
        if (!$tokenMatches) {
            return 'hold-next-recursive-child-source-token';
        }

        return 'hold-next-source';
    }

    private static function status(bool $publishNext, bool $followingVisible, bool $childrenAcknowledged, bool $tokenMatches): string
    {
        if ($publishNext) {
            return 'trigger-recursive-view-returning-current-source-next196-next-source-visible';
        }
        if (!$followingVisible) {
            return 'trigger-recursive-view-returning-current-source-next196-following-current-held';
        }
        if (!$childrenAcknowledged) {
            return 'trigger-recursive-view-returning-current-source-next196-awaiting-recursive-child-acks';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next196-child-token-held';
        }

        return 'trigger-recursive-view-returning-current-source-next196-held';
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} is malformed");
        }

        return $identifier;
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next196 {$label} is malformed");
        }

        return $token;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function signature(array $view, array $returning, string $token): string
    {
        $aliases = [];
        foreach ($returning as $index => $term) {
            $aliases[] = is_array($term) ? (string) ($term['as'] ?? $term['expr'] ?? $index) : (is_string($term) ? $term : 'callable_' . $index);
        }

        return substr(hash('sha256', json_encode([
            'name' => (string) ($view['name'] ?? ''),
            'trigger_source' => (string) ($view['trigger_source'] ?? ''),
            'token' => $token,
            'returning' => $aliases,
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }
}
