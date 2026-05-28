<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext188Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool,current_watermark?:string,expected_current_watermark?:string,acknowledged_current_ordinals?:list<int>,auto_ack_current_ordinals?:bool,require_contiguous_ordinals?:bool} $options
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
        $watermark = self::token((string) ($options['current_watermark'] ?? 'wp.recursive.view.current.watermark.188'), 'current watermark');
        $expectedWatermark = self::token((string) ($options['expected_current_watermark'] ?? $watermark), 'expected current watermark');
        $requireContiguous = (bool) ($options['require_contiguous_ordinals'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext185Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $currentRows = self::rows($base['visible_current_returning_rows_next185'] ?? [], 'current rows');
        $baseNextRows = self::rows($base['visible_next_returning_rows_next185'] ?? [], 'next rows');
        $heldNextRows = self::rows($base['held_next_source_rows_next185'] ?? [], 'held next rows');
        $attemptedNextRows = self::dedupeRows(array_merge($baseNextRows, $heldNextRows));
        $requiredOrdinals = self::requiredOrdinals($currentRows);
        $acknowledgedOrdinals = self::acknowledgedOrdinals($options, $requiredOrdinals);
        $missingOrdinals = array_values(array_diff($requiredOrdinals, $acknowledgedOrdinals));
        $unexpectedOrdinals = array_values(array_diff($acknowledgedOrdinals, $requiredOrdinals));
        $contiguous = self::contiguous($acknowledgedOrdinals, $requiredOrdinals);
        $watermarkMatches = hash_equals($watermark, $expectedWatermark);
        $basePublishAllowed = (bool) ($base['outer_publish_allowed_next185'] ?? false);
        $ordinalFenceClear = $missingOrdinals === [] && $unexpectedOrdinals === [] && (!$requireContiguous || $contiguous);
        $nextPublishAllowed = $basePublishAllowed && $watermarkMatches && $ordinalFenceClear;

        $taggedCurrentRows = self::tagRows($currentRows, 'current', true, $watermark, []);
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next185'] ?? [],
            $basePublishAllowed,
            $watermarkMatches,
            $missingOrdinals,
            $unexpectedOrdinals,
            $requireContiguous,
            $contiguous,
        );
        $taggedNextRows = self::tagRows($attemptedNextRows, 'next', $nextPublishAllowed, $watermark, $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrentRows, $taggedNextRows),
            static fn (array $row): bool => $row['visible_after_current_watermark_next188']
        ));
        $blockedNextRows = array_values(array_filter(
            $taggedNextRows,
            static fn (array $row): bool => !$row['visible_after_current_watermark_next188']
        ));

        return $base + [
            'status_next188' => self::status($basePublishAllowed, $watermarkMatches, $ordinalFenceClear, $nextPublishAllowed),
            'current_watermark_next188' => $watermark,
            'expected_current_watermark_next188' => $expectedWatermark,
            'current_watermark_matches_next188' => $watermarkMatches,
            'required_current_ordinals_next188' => $requiredOrdinals,
            'acknowledged_current_ordinals_next188' => $acknowledgedOrdinals,
            'missing_current_ordinals_next188' => $missingOrdinals,
            'unexpected_current_ordinals_next188' => $unexpectedOrdinals,
            'require_contiguous_ordinals_next188' => $requireContiguous,
            'current_ordinals_contiguous_next188' => $contiguous,
            'current_ordinal_fence_clear_next188' => $ordinalFenceClear,
            'next_source_publish_allowed_next188' => $nextPublishAllowed,
            'current_watermark_rows_next188' => $taggedCurrentRows,
            'attempted_next_watermark_rows_next188' => $taggedNextRows,
            'visible_returning_rows_next188' => $visibleRows,
            'blocked_next_source_rows_next188' => $blockedNextRows,
            'visible_returning_payloads_next188' => array_column($visibleRows, 'returning'),
            'blocked_next_returning_payloads_next188' => array_column($blockedNextRows, 'returning'),
            'current_watermark_row_count_next188' => count($taggedCurrentRows),
            'attempted_next_watermark_row_count_next188' => count($taggedNextRows),
            'visible_row_count_next188' => count($visibleRows),
            'blocked_next_row_count_next188' => count($blockedNextRows),
            'blocked_reasons_next188' => $blockedReasons,
            'watermark_plan_next188' => [
                'base_publish_allowed' => $basePublishAllowed,
                'watermark_matches' => $watermarkMatches,
                'required_ordinals' => $requiredOrdinals,
                'acknowledged_ordinals' => $acknowledgedOrdinals,
                'missing_ordinals' => $missingOrdinals,
                'unexpected_ordinals' => $unexpectedOrdinals,
                'require_contiguous_ordinals' => $requireContiguous,
                'ordinals_contiguous' => $contiguous,
                'ordinal_fence_clear' => $ordinalFenceClear,
                'next_source_publish_allowed' => $nextPublishAllowed,
                'decision' => $nextPublishAllowed ? 'publish-next-after-current-row-watermark' : 'hold-next-until-current-row-watermark',
            ],
            'yield_boundary_next188' => $nextPublishAllowed
                ? 'recursive-view-returning-next188-current-row-watermark-then-next'
                : 'recursive-view-returning-next188-current-row-watermark-fences-next',
            'dependencies_next188' => [
                'sqlite-trigger-recursive-view-returning-current-source-next188',
                'sqlite-returning-current-source-row-watermark-fence',
                'sqlite-returning-current-source-ordinal-contiguity',
                'wordpress-recursive-view-returning-current-source-next188',
            ],
            'dependency_closure_next188' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-nested-depth-drain-model',
            'non_overlap_next188' => 'adds row-ordinal current-source RETURNING watermark admission after next185 nested-depth drain; does not repeat next184 checkpoint acknowledgements, next182 generation fencing, row-value RETURNING, WAL, VFS, schema-reparse, or accepted trigger/FK cascade slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function requiredOrdinals(array $rows): array
    {
        $ordinals = [];
        foreach ($rows as $index => $row) {
            $ordinals[] = (int) ($row['returning_row_ordinal'] ?? $index);
        }

        sort($ordinals);

        return array_values(array_unique($ordinals));
    }

    /**
     * @param array<string,mixed> $options
     * @param list<int> $requiredOrdinals
     * @return list<int>
     */
    private static function acknowledgedOrdinals(array $options, array $requiredOrdinals): array
    {
        if (($options['auto_ack_current_ordinals'] ?? false) === true) {
            return $requiredOrdinals;
        }

        return self::ordinalList($options['acknowledged_current_ordinals'] ?? [], 'acknowledged current ordinals');
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private static function ordinalList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} must be a list");
        }

        $out = [];
        foreach ($values as $value) {
            $ordinal = (int) $value;
            if ($ordinal < 0 || (string) $ordinal !== (string) $value) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} contain a malformed ordinal");
            }
            $out[] = $ordinal;
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $acknowledged
     * @param list<int> $required
     */
    private static function contiguous(array $acknowledged, array $required): bool
    {
        if ($acknowledged === []) {
            return $required === [];
        }

        $expected = range((int) min($required), (int) max($required));

        return $acknowledged === $expected;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, string $watermark, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $ordinal = (int) ($row['returning_row_ordinal'] ?? $index);
            $out[] = $row + [
                'watermark_phase_next188' => $phase,
                'current_watermark_next188' => $watermark,
                'current_watermark_ordinal_next188' => $ordinal,
                'visible_after_current_watermark_next188' => $visible,
                'held_by_current_watermark_reasons_next188' => $visible ? [] : $reasons,
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} contain a malformed row");
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
     * @param list<int> $missingOrdinals
     * @param list<int> $unexpectedOrdinals
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $basePublishAllowed,
        bool $watermarkMatches,
        array $missingOrdinals,
        array $unexpectedOrdinals,
        bool $requireContiguous,
        bool $contiguous,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next188 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next185-current-source-not-published';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'current-watermark-token-mismatch';
        }
        if ($missingOrdinals !== []) {
            $reasons[] = 'current-watermark-ordinal-missing';
        }
        if ($unexpectedOrdinals !== []) {
            $reasons[] = 'current-watermark-ordinal-unexpected';
        }
        if ($requireContiguous && !$contiguous) {
            $reasons[] = 'current-watermark-ordinal-gap';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $basePublishAllowed, bool $watermarkMatches, bool $ordinalFenceClear, bool $nextPublishAllowed): string
    {
        if ($nextPublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-watermark-released-next188';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-watermark-base-held-next188';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-returning-current-source-watermark-token-held-next188';
        }
        if (!$ordinalFenceClear) {
            return 'trigger-recursive-view-returning-current-source-watermark-ordinal-held-next188';
        }

        return 'trigger-recursive-view-returning-current-source-watermark-held-next188';
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next188 {$label} is malformed");
        }

        return $value;
    }
}
