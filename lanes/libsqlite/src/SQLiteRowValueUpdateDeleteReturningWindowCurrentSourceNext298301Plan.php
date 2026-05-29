<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext298301Plan
{
    /**
     * @param list<array<string,mixed>> $readyCandidates
     * @return array<string,mixed>
     */
    public static function prepare(array $readyCandidates, string $sourceToken = 'rowvalue-window-current-source-next298-301'): array
    {
        if (count($readyCandidates) !== 4) {
            throw new \InvalidArgumentException('SQLite row-value returning window next298-301 requires exactly four next294-297 ready candidates');
        }
        if ($sourceToken === '') {
            throw new \InvalidArgumentException('SQLite row-value returning window next298-301 source token must be non-empty');
        }

        $expected = [294, 295, 296, 297];
        $validated = [];
        $rowCounts = [];
        $retryRowids = [];

        foreach ($readyCandidates as $index => $candidate) {
            $next = $expected[$index];
            $validated[] = self::validateCandidate($candidate, $next);
            $rowCounts[$next] = count($candidate['retry_window_rows']);
            $retryRowids[$next] = array_column($candidate['retry_window_rows'], 'current_rowid');
        }

        $receipt298 = self::hash(['next' => 298, 'source' => $sourceToken, 'ready' => $validated, 'rowids' => $retryRowids]);
        $ledger299 = self::hash(['next' => 299, 'receipt' => $receipt298, 'rows' => $rowCounts]);
        $handoff300 = self::hash(['next' => 300, 'ledger' => $ledger299, 'statuses' => array_column($validated, 'status')]);
        $seal301 = self::hash(['next' => 301, 'handoff' => $handoff300, 'source' => $sourceToken]);

        return [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next298-301-after-ready',
            'source_token' => $sourceToken,
            'ready_candidate_statuses' => array_column($validated, 'status'),
            'ready_candidate_nexts' => $expected,
            'retry_window_row_counts' => $rowCounts,
            'retry_window_rowids' => $retryRowids,
            'next298_receipt' => $receipt298,
            'next299_ledger' => $ledger299,
            'next300_handoff' => $handoff300,
            'next301_seal' => $seal301,
            'next301_ready' => true,
            'dependency_closure_next298_301' => 'no new support component needed; next298-301 prepares after-ready row-value UPDATE/DELETE RETURNING window current-source metadata from next294-297 ready candidates',
            'non_overlap_next298_301' => 'prepares only post-ready receipts for row-value UPDATE/DELETE RETURNING window current-source next294-297 candidates; avoids suite, JSON table, WAL/VFS, planner, PRAGMA, ATTACH, B-tree, and unrelated window slices',
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next294-ready',
                'sqlite-rowvalue-update-delete-returning-window-current-source-next295-ready',
                'sqlite-rowvalue-update-delete-returning-window-current-source-next296-ready',
                'sqlite-rowvalue-update-delete-returning-window-current-source-next297-ready',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $candidate
     * @return array{next:int,status:string}
     */
    private static function validateCandidate(array $candidate, int $next): array
    {
        $status = $candidate['status'] ?? null;
        $expectedStatus = "rowvalue-update-delete-returning-window-current-source-next{$next}-ready";
        if ($status !== $expectedStatus) {
            throw new \InvalidArgumentException("SQLite row-value returning window next298-301 expected {$expectedStatus}");
        }
        if (($candidate['after_ready'] ?? null) !== true) {
            throw new \InvalidArgumentException("SQLite row-value returning window next298-301 next{$next} is not after-ready");
        }
        if (!isset($candidate['retry_window_rows']) || !is_array($candidate['retry_window_rows']) || !array_is_list($candidate['retry_window_rows'])) {
            throw new \InvalidArgumentException("SQLite row-value returning window next298-301 next{$next} needs retry window rows");
        }
        foreach ($candidate['retry_window_rows'] as $row) {
            if (!is_array($row) || !array_key_exists('current_rowid', $row) || !array_key_exists('row_number', $row)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next298-301 next{$next} retry rows need row_number and current_rowid");
            }
        }

        return ['next' => $next, 'status' => $expectedStatus];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
