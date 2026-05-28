<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext178Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int} $options
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
        $snapshotToken = self::token((string) ($options['snapshot_token'] ?? 'wp.recursive.view.returning.snapshot.178'), 'snapshot token');
        $expectedSnapshotToken = self::token((string) ($options['expected_snapshot_token'] ?? $snapshotToken), 'expected snapshot token');
        $schemaCookie = self::cookie($options['current_schema_cookie'] ?? 178, 'current schema cookie');
        $expectedSchemaCookie = self::cookie($options['expected_current_schema_cookie'] ?? $schemaCookie, 'expected current schema cookie');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext175Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $snapshotMatches = hash_equals($snapshotToken, $expectedSnapshotToken);
        $schemaMatches = $schemaCookie === $expectedSchemaCookie;
        $releaseAllowed = (bool) ($base['savepoint_release_allowed_next175'] ?? false);
        $currentPages = self::pages($base['drained_current_pages'] ?? [], 'drained current pages');
        $nextPages = self::pages($base['next_returning_pages'] ?? [], 'next returning pages');
        $visiblePages = self::pages($base['visible_returning_pages_next175'] ?? [], 'visible returning pages');
        $snapshotStable = $snapshotMatches && $schemaMatches;

        $visibleRows = $snapshotStable ? self::flattenRows($visiblePages, $snapshotToken, $schemaCookie) : self::flattenRows($currentPages, $snapshotToken, $schemaCookie);
        $queuedNextRows = $releaseAllowed && $snapshotStable ? [] : self::flattenRows($nextPages, $snapshotToken, $schemaCookie);
        $currentRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'current'));
        $nextRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'next'));
        $blockedReasons = self::strings($base['blocked_reasons_next175'] ?? [], 'blocked reasons');
        if (!$snapshotMatches) {
            $blockedReasons[] = 'current-source-returning-snapshot-token-mismatch';
        }
        if (!$schemaMatches) {
            $blockedReasons[] = 'current-source-view-schema-cookie-mismatch';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        return $base + [
            'status_next178' => match (true) {
                !$snapshotStable => 'trigger-recursive-view-returning-current-source-snapshot-restart-next178',
                $releaseAllowed => 'trigger-recursive-view-returning-current-source-snapshot-released-next178',
                default => 'trigger-recursive-view-returning-current-source-snapshot-held-next178',
            },
            'snapshot_token_next178' => $snapshotToken,
            'expected_snapshot_token_next178' => $expectedSnapshotToken,
            'current_schema_cookie_next178' => $schemaCookie,
            'expected_current_schema_cookie_next178' => $expectedSchemaCookie,
            'snapshot_token_matches_next178' => $snapshotMatches,
            'schema_cookie_matches_next178' => $schemaMatches,
            'current_source_snapshot_stable_next178' => $snapshotStable,
            'visible_returning_rows_next178' => $visibleRows,
            'current_source_returning_rows_next178' => $currentRows,
            'next_source_returning_rows_next178' => $nextRows,
            'queued_next_source_rows_next178' => $queuedNextRows,
            'statement_returning_row_count_next178' => count($visibleRows),
            'current_returning_row_count_next178' => count($currentRows),
            'next_returning_row_count_next178' => count($nextRows),
            'queued_next_row_count_next178' => count($queuedNextRows),
            'returning_source_order_next178' => array_values(array_unique(array_column($visibleRows, 'statement_source'))),
            'returning_snapshot_plan_next178' => [
                'snapshot_token_matches' => $snapshotMatches,
                'schema_cookie_matches' => $schemaMatches,
                'savepoint_release_allowed' => $releaseAllowed,
                'visible_rows' => count($visibleRows),
                'current_rows' => count($currentRows),
                'next_rows' => count($nextRows),
                'queued_next_rows' => count($queuedNextRows),
                'restart_required' => !$snapshotStable,
                'decision' => !$snapshotStable ? 'restart-current-source-returning-snapshot' : ($releaseAllowed ? 'publish-current-then-next-returning' : 'hold-next-source-returning'),
            ],
            'blocked_reasons_next178' => $blockedReasons,
            'yield_boundary_next178' => $snapshotStable && $releaseAllowed
                ? 'recursive-view-returning-next178-current-source-snapshot-stable-then-next'
                : 'recursive-view-returning-next178-current-source-snapshot-fences-next',
            'dependencies_next178' => [
                'sqlite-trigger-recursive-view-returning-current-source-next178',
                'sqlite-returning-current-source-snapshot-token-fence',
                'sqlite-returning-view-schema-cookie-fence',
                'wordpress-recursive-view-returning-current-source-next178',
            ],
            'dependency_closure_next178' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-savepoint-and-schema-cookie-model',
            'non_overlap_next178' => 'extends next175 savepoint fencing with current-source snapshot-token and view-schema-cookie RETURNING row publication; does not repeat duplicate-key watermarking, savepoint rollback/release, schema reparse, deferred FK, UPSERT, or WAL/VFS slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $pages
     * @return list<array<string,mixed>>
     */
    private static function flattenRows(array $pages, string $snapshotToken, int $schemaCookie): array
    {
        $rows = [];
        foreach ($pages as $page) {
            if (!isset($page['phase'], $page['page'], $page['rows']) || !is_array($page['rows'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next178 page is malformed');
            }
            foreach ($page['rows'] as $row) {
                if (!is_array($row) || !isset($row['returning'])) {
                    throw new InvalidArgumentException('SQLite recursive view RETURNING next178 row is malformed');
                }
                $source = (string) $page['phase'];
                $rows[] = $row + [
                    'statement_source' => str_starts_with($source, 'next') ? 'next' : 'current',
                    'returning_page' => (int) $page['page'],
                    'returning_snapshot_token' => $snapshotToken,
                    'returning_schema_cookie' => $schemaCookie,
                    'returning_row_ordinal' => count($rows),
                    'returning_option_name' => (string) (($row['returning']['option_name'] ?? null) ?? ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param mixed $pages
     * @return list<array<string,mixed>>
     */
    private static function pages(mixed $pages, string $label): array
    {
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} are malformed");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function cookie(mixed $value, string $label): int
    {
        $cookie = (int) $value;
        if ($cookie < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} must be non-negative");
        }

        return $cookie;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next178 {$label} is malformed");
        }

        return $value;
    }
}
