<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext193Plan
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
        $handoffToken = self::token((string) ($options['handoff_token'] ?? 'wp.recursive.view.returning.handoff.193'), 'handoff token');
        $expectedHandoffToken = self::token((string) ($options['expected_handoff_token'] ?? $handoffToken), 'expected handoff token');
        $sequenceToken = self::token((string) ($options['source_sequence_token'] ?? self::sequenceToken($nextRows)), 'source sequence token');
        $expectedSequenceToken = self::token((string) ($options['expected_source_sequence_token'] ?? self::sequenceToken($nextRows)), 'expected source sequence token');
        $expectedRows = self::nonNegativeInt($options['expected_next_row_count'] ?? count($nextRows), 'expected next row count');
        $expectedSignature = (string) ($options['expected_next_source_signature'] ?? self::sourceSignature($nextRows));
        if ($expectedSignature === '' || preg_match('/\s/', $expectedSignature) === 1) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next193 expected signature is malformed');
        }

        $baseAdmitted = $base['blocked_reasons_next189'] === [] && $nextRows !== [];
        $rowCountMatches = count($nextRows) === $expectedRows;
        $signatureMatches = hash_equals($expectedSignature, self::sourceSignature($nextRows));
        $handoffTokenMatches = hash_equals($handoffToken, $expectedHandoffToken);
        $sequenceMatches = hash_equals($sequenceToken, $expectedSequenceToken);
        $canPublish = $baseAdmitted && $rowCountMatches && $signatureMatches && $handoffTokenMatches && $sequenceMatches;
        $publishedRows = $canPublish ? self::publishRows($nextRows, $handoffToken, $sequenceToken) : [];

        return [
            'status_next193' => self::status($canPublish, $baseAdmitted, $rowCountMatches, $signatureMatches, $handoffTokenMatches, $sequenceMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'handoff_token_next193' => $handoffToken,
            'expected_handoff_token_next193' => $expectedHandoffToken,
            'handoff_token_matches_next193' => $handoffTokenMatches,
            'source_sequence_token_next193' => $sequenceToken,
            'expected_source_sequence_token_next193' => $expectedSequenceToken,
            'source_sequence_matches_next193' => $sequenceMatches,
            'next_source_signature_next193' => self::sourceSignature($nextRows),
            'expected_next_source_signature_next193' => $expectedSignature,
            'next_source_signature_matches_next193' => $signatureMatches,
            'expected_next_row_count_next193' => $expectedRows,
            'next_row_count_matches_next193' => $rowCountMatches,
            'base_next_source_admitted_next193' => $baseAdmitted,
            'published_next_source_rows_next193' => $publishedRows,
            'published_next_source_payloads_next193' => array_column($publishedRows, 'returning'),
            'published_next_source_row_count_next193' => count($publishedRows),
            'blocked_reasons_next193' => self::blockedReasons($baseAdmitted, $rowCountMatches, $signatureMatches, $handoffTokenMatches, $sequenceMatches, $base),
            'current_source_returning_handoff_next193' => [
                'fresh_current_rows_required' => $base['handoff_plan_next189']['fresh_rows_required'] ?? 0,
                'fresh_current_rows_acknowledged' => $base['handoff_plan_next189']['fresh_rows_acknowledged'] ?? 0,
                'candidate_next_rows' => count($nextRows),
                'published_next_rows' => count($publishedRows),
                'source_signature' => self::sourceSignature($nextRows),
                'source_sequence_token' => $sequenceToken,
                'decision' => self::decision($canPublish, $baseAdmitted, $rowCountMatches, $signatureMatches, $handoffTokenMatches, $sequenceMatches),
            ],
            'yield_boundary_next193' => $canPublish
                ? 'recursive-view-returning-next193-next-source-sealed-after-current-drain'
                : 'recursive-view-returning-next193-next-source-quarantined',
            'dependency_closure_next193' => 'no new support component needed; reuses next189 current-row acknowledgements and adds source-signature handoff sealing',
            'dependencies_next193' => array_values(array_unique(array_merge($base['dependencies_next189'], [
                'sqlite-trigger-recursive-view-returning-current-source-next193',
                'sqlite-returning-current-source-handoff-seal',
                'wordpress-recursive-view-returning-current-source-next193',
            ]))),
            'non_overlap_next193' => 'extends accepted next189 row-ack next-source admission by sealing the admitted source with signature, row-count, handoff-token, and sequence-token checks before publication; avoids next181 checkpoint visibility, next186 rebind, next189 row acknowledgement, row-value RETURNING, UPSERT, deferred FK, schema reparse, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function publishRows(array $rows, string $handoffToken, string $sequenceToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'published_next193' => true,
                'publish_ordinal_next193' => $index,
                'handoff_token_next193' => $handoffToken,
                'source_sequence_token_next193' => $sequenceToken,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sourceSignature(array $rows): string
    {
        if ($rows === []) {
            return 'empty-next-source';
        }

        $signatures = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['source_signature_next189'] ?? ''),
            $rows,
        )));
        sort($signatures);

        return substr(hash('sha256', json_encode([
            'signatures' => $signatures,
            'names' => array_column($rows, 'returning_option_name'),
            'count' => count($rows),
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sequenceToken(array $rows): string
    {
        if ($rows === []) {
            return 'empty-next-source';
        }

        return 'seq-' . substr(hash('sha256', json_encode([
            'ordinals' => array_column($rows, 'returning_row_ordinal'),
            'names' => array_column($rows, 'returning_option_name'),
        ], JSON_THROW_ON_ERROR)), 0, 12);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} row is malformed");
            }
        }

        return $rows;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next193 {$label} is malformed");
        }

        return $token;
    }

    private static function status(bool $canPublish, bool $baseAdmitted, bool $rowCountMatches, bool $signatureMatches, bool $handoffTokenMatches, bool $sequenceMatches): string
    {
        if ($canPublish) {
            return 'trigger-recursive-view-returning-current-source-next193-published';
        }
        if (!$baseAdmitted) {
            return 'trigger-recursive-view-returning-current-source-next193-awaiting-next189';
        }
        if (!$rowCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-row-count-held';
        }
        if (!$signatureMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-signature-held';
        }
        if (!$handoffTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-handoff-token-held';
        }
        if (!$sequenceMatches) {
            return 'trigger-recursive-view-returning-current-source-next193-sequence-held';
        }

        return 'trigger-recursive-view-returning-current-source-next193-held';
    }

    /**
     * @return list<string>
     */
    private static function blockedReasons(bool $baseAdmitted, bool $rowCountMatches, bool $signatureMatches, bool $handoffTokenMatches, bool $sequenceMatches, array $base): array
    {
        $reasons = $base['blocked_reasons_next189'];
        if (!$baseAdmitted) {
            $reasons[] = 'next189-next-source-not-admitted';
        }
        if (!$rowCountMatches) {
            $reasons[] = 'next-source-row-count-mismatch';
        }
        if (!$signatureMatches) {
            $reasons[] = 'next-source-signature-mismatch';
        }
        if (!$handoffTokenMatches) {
            $reasons[] = 'handoff-token-mismatch';
        }
        if (!$sequenceMatches) {
            $reasons[] = 'source-sequence-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function decision(bool $canPublish, bool $baseAdmitted, bool $rowCountMatches, bool $signatureMatches, bool $handoffTokenMatches, bool $sequenceMatches): string
    {
        if ($canPublish) {
            return 'publish-sealed-next-source-after-current-drain';
        }
        if (!$baseAdmitted) {
            return 'hold-next-source-until-next189-admission';
        }
        if (!$rowCountMatches) {
            return 'hold-next-source-row-count';
        }
        if (!$signatureMatches) {
            return 'hold-next-source-signature';
        }
        if (!$handoffTokenMatches) {
            return 'hold-next-source-handoff-token';
        }
        if (!$sequenceMatches) {
            return 'hold-next-source-sequence-token';
        }

        return 'hold-next-source';
    }
}
