<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext191Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool,current_watermark?:string,expected_current_watermark?:string,acknowledged_current_ordinals?:list<int>,auto_ack_current_ordinals?:bool,require_contiguous_ordinals?:bool,fingerprint_salt?:string,expected_fingerprint_salt?:string,acknowledged_current_fingerprints?:list<string>,auto_ack_current_fingerprints?:bool,require_fingerprint_order?:bool} $options
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
        $salt = self::token((string) ($options['fingerprint_salt'] ?? 'wp.recursive.view.returning.fingerprint.191'), 'fingerprint salt');
        $expectedSalt = self::token((string) ($options['expected_fingerprint_salt'] ?? $salt), 'expected fingerprint salt');
        $requireOrder = (bool) ($options['require_fingerprint_order'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext188Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'savepoint_action' => 'release',
                'auto_ack_current_ordinals' => true,
            ],
        );

        $currentRows = self::rows($base['current_watermark_rows_next188'] ?? [], 'current rows');
        $nextRows = self::dedupeRows(array_merge(
            self::rows($base['attempted_next_watermark_rows_next188'] ?? [], 'attempted next rows'),
            self::rows($base['blocked_next_source_rows_next188'] ?? [], 'blocked next rows'),
        ));
        $required = self::fingerprints($currentRows, $salt);
        $acknowledged = self::acknowledgedFingerprints($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || self::ordered($required, $acknowledged);
        $saltMatches = hash_equals($salt, $expectedSalt);
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next188'] ?? false);
        $fingerprintFenceClear = $missing === [] && $unexpected === [] && $orderMatches;
        $publishNext = $basePublishAllowed && $saltMatches && $fingerprintFenceClear;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next188'] ?? [],
            $basePublishAllowed,
            $saltMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagRows($currentRows, 'current', true, $salt, $required, []);
        $nextTagged = self::tagRows($nextRows, 'next', $publishNext, $salt, [], $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentTagged, $nextTagged),
            static fn (array $row): bool => $row['visible_after_current_fingerprint_next191']
        ));
        $heldRows = array_values(array_filter(
            $nextTagged,
            static fn (array $row): bool => !$row['visible_after_current_fingerprint_next191']
        ));

        return $base + [
            'status_next191' => self::status($basePublishAllowed, $saltMatches, $fingerprintFenceClear, $publishNext),
            'fingerprint_salt_next191' => $salt,
            'expected_fingerprint_salt_next191' => $expectedSalt,
            'fingerprint_salt_matches_next191' => $saltMatches,
            'required_current_fingerprints_next191' => $required,
            'acknowledged_current_fingerprints_next191' => $acknowledged,
            'missing_current_fingerprints_next191' => $missing,
            'unexpected_current_fingerprints_next191' => $unexpected,
            'require_fingerprint_order_next191' => $requireOrder,
            'current_fingerprint_order_matches_next191' => $orderMatches,
            'current_fingerprint_fence_clear_next191' => $fingerprintFenceClear,
            'next_source_publish_allowed_next191' => $publishNext,
            'current_fingerprint_rows_next191' => $currentTagged,
            'attempted_next_fingerprint_rows_next191' => $nextTagged,
            'visible_returning_rows_next191' => $visibleRows,
            'held_next_source_rows_next191' => $heldRows,
            'visible_returning_payloads_next191' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next191' => array_column($heldRows, 'returning'),
            'current_fingerprint_row_count_next191' => count($currentTagged),
            'attempted_next_fingerprint_row_count_next191' => count($nextTagged),
            'visible_row_count_next191' => count($visibleRows),
            'held_next_row_count_next191' => count($heldRows),
            'blocked_reasons_next191' => $blockedReasons,
            'fingerprint_plan_next191' => [
                'base_publish_allowed' => $basePublishAllowed,
                'salt_matches' => $saltMatches,
                'required_fingerprints' => $required,
                'acknowledged_fingerprints' => $acknowledged,
                'missing_fingerprints' => $missing,
                'unexpected_fingerprints' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'fingerprint_fence_clear' => $fingerprintFenceClear,
                'next_source_publish_allowed' => $publishNext,
                'decision' => $publishNext ? 'publish-next-after-current-row-fingerprints' : 'hold-next-until-current-row-fingerprints',
            ],
            'yield_boundary_next191' => $publishNext
                ? 'recursive-view-returning-next191-current-fingerprints-then-next'
                : 'recursive-view-returning-next191-current-fingerprints-fence-next',
            'dependencies_next191' => [
                'sqlite-trigger-recursive-view-returning-current-source-next191',
                'sqlite-returning-current-source-row-fingerprint-fence',
                'sqlite-returning-current-source-payload-order',
                'wordpress-recursive-view-returning-current-source-next191',
            ],
            'dependency_closure_next191' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-row-watermark-and-payload-fingerprint-model',
            'non_overlap_next191' => 'adds payload/source fingerprint admission after next188 row-ordinal watermarks; does not repeat next188 ordinal fencing, next185 nested-depth drain, next184 checkpoint acknowledgements, row-value RETURNING, WAL, VFS, schema-reparse, or trigger/FK cascade slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function fingerprints(array $rows, string $salt): array
    {
        $fingerprints = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'] ?? [];
            ksort($payload);
            $parts = [
                $salt,
                (string) ($row['statement_source'] ?? 'current'),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ($payload['option_name'] ?? '')),
                (string) ($row['returning_current_source_generation'] ?? ''),
                (string) ($row['returning_trigger_source_generation'] ?? ''),
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
            $fingerprints[] = substr(hash('sha256', implode('|', $parts)), 0, 24);
        }

        return $fingerprints;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedFingerprints(array $options, array $required): array
    {
        if (($options['auto_ack_current_fingerprints'] ?? false) === true) {
            return $required;
        }

        return self::fingerprintList($options['acknowledged_current_fingerprints'] ?? [], 'acknowledged current fingerprints');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function fingerprintList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{24}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} contain a malformed fingerprint");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     */
    private static function ordered(array $required, array $acknowledged): bool
    {
        return $required === $acknowledged;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $fingerprints
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, string $salt, array $fingerprints, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'fingerprint_phase_next191' => $phase,
                'fingerprint_salt_next191' => $salt,
                'current_row_fingerprint_next191' => $fingerprints[$index] ?? null,
                'visible_after_current_fingerprint_next191' => $visible,
                'held_by_current_fingerprint_reasons_next191' => $visible ? [] : $reasons,
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function dedupeRows(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = ($row['statement_source'] ?? '') . "\0" . ($row['returning_row_ordinal'] ?? '') . "\0" . ($row['returning_option_name'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $basePublishAllowed,
        bool $saltMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next191 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next188-current-source-not-published';
        }
        if (!$saltMatches) {
            $reasons[] = 'current-fingerprint-salt-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-fingerprint-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-fingerprint-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-fingerprint-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $basePublishAllowed, bool $saltMatches, bool $fingerprintFenceClear, bool $publishNext): string
    {
        if ($publishNext) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-released-next191';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-base-held-next191';
        }
        if (!$saltMatches) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-salt-held-next191';
        }
        if (!$fingerprintFenceClear) {
            return 'trigger-recursive-view-returning-current-source-fingerprints-held-next191';
        }

        return 'trigger-recursive-view-returning-current-source-fingerprints-pending-next191';
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next191 {$label} is malformed");
        }

        return $value;
    }
}
