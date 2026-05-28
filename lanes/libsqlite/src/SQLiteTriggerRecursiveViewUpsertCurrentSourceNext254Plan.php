<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext254Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_rowid_provenance_next250'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next250'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next250'] ?? [], 'attempted next source rows');
        $mapping = self::mapping($currentView['mapping'] ?? [], 'current view mapping');
        $expectedMapping = self::mapping($options['expected_current_view_mapping_next254'] ?? $mapping, 'expected current view mapping');
        $mappingMatches = $mapping === $expectedMapping;
        $sourceToken = self::token((string) ($options['current_view_mapping_source_token_next254'] ?? ($currentView['source'] ?? 'main@view-mapping-current-254')), 'view mapping source token');
        $expectedSourceToken = self::token((string) ($options['expected_current_view_mapping_source_token_next254'] ?? $sourceToken), 'expected view mapping source token');
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $triggerToken = self::token((string) ($options['current_view_mapping_trigger_token_next254'] ?? ($currentView['trigger_source'] ?? 'main@trigger-mapping-current-254')), 'view mapping trigger token');
        $expectedTriggerToken = self::token((string) ($options['expected_current_view_mapping_trigger_token_next254'] ?? $triggerToken), 'expected view mapping trigger token');
        $triggerMatches = hash_equals($triggerToken, $expectedTriggerToken);
        $requiredColumns = self::columnList($options['required_current_view_mapping_columns_next254'] ?? ['import_id', 'name', 'value', 'autoload_flag'], 'required mapping columns');
        $missingColumns = array_values(array_filter($requiredColumns, static fn (string $column): bool => !array_key_exists($column, $mapping)));
        $mappingRows = self::mappingRows($currentRows, $mapping, $sourceToken, $triggerToken, $requiredColumns);
        $required = self::mappingReceipts($mappingRows, $sourceToken, $triggerToken);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $mappingComplete = $required !== []
            && $mappingMatches
            && $sourceMatches
            && $triggerMatches
            && $missingColumns === []
            && $missing === []
            && $unexpected === [];
        $nextVisible = $baseVisible && $mappingComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next250'] ?? [],
            $baseVisible,
            $mappingMatches,
            $sourceMatches,
            $triggerMatches,
            $missingColumns,
            $missing,
            $unexpected,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-view-mapping', true, $mappingRows, $required, $sourceToken, $triggerToken, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], [], $sourceToken, $triggerToken, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next254' => self::status($nextVisible, $baseVisible, $mappingMatches, $sourceMatches, $triggerMatches, $missingColumns, $missing, $unexpected),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next254' => $baseVisible,
            'current_view_mapping_next254' => $mapping,
            'expected_current_view_mapping_next254' => $expectedMapping,
            'current_view_mapping_matches_next254' => $mappingMatches,
            'current_view_mapping_source_token_next254' => $sourceToken,
            'expected_current_view_mapping_source_token_next254' => $expectedSourceToken,
            'current_view_mapping_source_token_matches_next254' => $sourceMatches,
            'current_view_mapping_trigger_token_next254' => $triggerToken,
            'expected_current_view_mapping_trigger_token_next254' => $expectedTriggerToken,
            'current_view_mapping_trigger_token_matches_next254' => $triggerMatches,
            'required_current_view_mapping_columns_next254' => $requiredColumns,
            'missing_current_view_mapping_columns_next254' => $missingColumns,
            'current_view_mapping_rows_next254' => $mappingRows,
            'required_current_view_mapping_receipts_next254' => $required,
            'acknowledged_current_view_mapping_receipts_next254' => $acknowledged,
            'missing_current_view_mapping_receipts_next254' => $missing,
            'unexpected_current_view_mapping_receipts_next254' => $unexpected,
            'current_view_mapping_complete_next254' => $mappingComplete,
            'next_source_visible_after_current_view_mapping_next254' => $nextVisible,
            'current_source_rows_next254' => $taggedCurrent,
            'attempted_next_source_rows_next254' => $taggedNext,
            'visible_returning_rows_next254' => $visibleRows,
            'held_next_source_rows_next254' => $heldRows,
            'visible_returning_payloads_next254' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next254' => array_column($heldRows, 'returning'),
            'current_source_row_count_next254' => count($taggedCurrent),
            'attempted_next_source_row_count_next254' => count($taggedNext),
            'visible_row_count_next254' => count($visibleRows),
            'held_next_row_count_next254' => count($heldRows),
            'blocked_reasons_next254' => $blockedReasons,
            'current_view_mapping_plan_next254' => [
                'base_next_source_visible' => $baseVisible,
                'mapping_matches' => $mappingMatches,
                'source_token_matches' => $sourceMatches,
                'trigger_token_matches' => $triggerMatches,
                'required_columns' => $requiredColumns,
                'missing_columns' => $missingColumns,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'mapping_complete' => $mappingComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-mapping'
                    : 'hold-next-source-until-current-recursive-view-upsert-mapping',
            ],
            'yield_boundary_next254' => $nextVisible
                ? 'recursive-view-upsert-next254-current-view-mapping-then-next'
                : 'recursive-view-upsert-next254-current-view-mapping-fence-next',
            'dependency_closure_next254' => 'no-new-support-component-reuses-native-recursive-view-upsert-rowid-provenance-and-adds-current-view-mapping-receipts',
            'dependencies_next254' => array_values(array_unique(array_merge($base['dependencies_next250'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next254',
                'sqlite-instead-of-view-trigger-current-mapping-receipts',
                'wordpress-recursive-view-upsert-current-source-next254',
            ]))),
            'non_overlap_next254' => 'adds current view-column mapping and source-token receipt fencing after accepted next250 rowid provenance; avoids next250 rowid receipts, next247 sequence receipts, next244 commit watermarks, trigger RETURNING cursor/ticket/generation surfaces, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @return array<string,string>
     */
    private static function mapping(mixed $mapping, string $label): array
    {
        if (!is_array($mapping)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be an array");
        }
        $out = [];
        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target) || !self::isColumn($source) || !self::isColumn($target)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contains malformed columns");
            }
            $out[$source] = $target;
        }
        ksort($out);

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function columnList(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($columns as $column) {
            if (!is_string($column) || !self::isColumn($column)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contains a malformed column");
            }
            $out[] = $column;
        }

        return array_values(array_unique($out));
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} is malformed");
        }

        return $value;
    }

    private static function isColumn(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,string> $mapping
     * @param list<string> $requiredColumns
     * @return list<array<string,mixed>>
     */
    private static function mappingRows(array $rows, array $mapping, string $sourceToken, string $triggerToken, array $requiredColumns): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $mapped = [];
            foreach ($requiredColumns as $sourceColumn) {
                $targetColumn = $mapping[$sourceColumn] ?? null;
                $mapped[$sourceColumn] = [
                    'target' => $targetColumn,
                    'value' => $targetColumn === null ? null : ($returning[self::returningAlias($sourceColumn, $targetColumn)] ?? $returning[$targetColumn] ?? null),
                ];
            }
            $out[] = [
                'ordinal' => $index,
                'returning_name' => $returning['name'] ?? null,
                'returning_value' => $returning['value'] ?? null,
                'rowid_receipt' => $row['current_source_rowid_receipt_next250'] ?? null,
                'source_token' => $sourceToken,
                'trigger_token' => $triggerToken,
                'mapping' => $mapped,
            ];
        }

        return $out;
    }

    private static function returningAlias(string $sourceColumn, string $targetColumn): string
    {
        if ($sourceColumn === 'name' && $targetColumn === 'option_name') {
            return 'name';
        }
        if ($sourceColumn === 'value' && $targetColumn === 'option_value') {
            return 'value';
        }
        if ($sourceColumn === 'import_id' && $targetColumn === 'option_id') {
            return 'import_id';
        }
        if ($sourceColumn === 'autoload_flag' && $targetColumn === 'autoload') {
            return 'autoload_flag';
        }

        return $targetColumn;
    }

    /**
     * @param list<array<string,mixed>> $mappingRows
     * @return list<string>
     */
    private static function mappingReceipts(array $mappingRows, string $sourceToken, string $triggerToken): array
    {
        $receipts = [];
        foreach ($mappingRows as $row) {
            $receipts[] = substr(hash('sha256', json_encode([$sourceToken, $triggerToken, $row], JSON_THROW_ON_ERROR)), 0, 52);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_view_mapping_receipts_next254'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_view_mapping_receipts_next254'] ?? [], 'acknowledged view mapping receipts');
    }

    /**
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{52}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contain a malformed mapping receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $mappingRows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $mappingRows, array $receipts, string $sourceToken, string $triggerToken, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'view_mapping_phase_next254' => $phase,
                'current_view_mapping_source_token_next254' => $sourceToken,
                'current_view_mapping_trigger_token_next254' => $triggerToken,
                'current_view_mapping_row_next254' => $mappingRows[$index] ?? null,
                'current_view_mapping_receipt_next254' => $receipts[$index] ?? null,
                'visible_after_current_view_mapping_next254' => $visible,
                'held_by_current_view_mapping_reasons_next254' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingColumns
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $mappingMatches, bool $sourceMatches, bool $triggerMatches, array $missingColumns, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next254 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next250-rowid-provenance-not-published';
        }
        if (!$mappingMatches) {
            $reasons[] = 'current-view-mapping-mismatch';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-view-mapping-source-token-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-view-mapping-trigger-token-mismatch';
        }
        if ($missingColumns !== []) {
            $reasons[] = 'current-view-mapping-required-columns-missing';
        }
        if ($missing !== []) {
            $reasons[] = 'current-view-mapping-receipts-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-view-mapping-receipts-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missingColumns
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $mappingMatches, bool $sourceMatches, bool $triggerMatches, array $missingColumns, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next254-view-mapping-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next254-base-held';
        }
        if (!$mappingMatches) {
            return 'trigger-recursive-view-upsert-current-source-next254-view-mapping-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next254-source-token-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next254-trigger-token-held';
        }
        if ($missingColumns !== []) {
            return 'trigger-recursive-view-upsert-current-source-next254-columns-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next254-receipts-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next254-receipts-unexpected-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next254-held';
    }
}
