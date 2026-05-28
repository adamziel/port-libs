<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext211Plan
{
    /**
     * @param array<string,mixed> $readerPlan
     * @param array<string,array<string,mixed>> $acknowledgements
     * @return array<string,mixed>
     */
    public static function plan(array $readerPlan, array $acknowledgements): array
    {
        self::assertReaderPlan($readerPlan);
        if ($acknowledgements === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next211 requires acknowledgement rows');
        }

        $token = $readerPlan['current_source_token'];
        $tokenId = (string) $token['id'];
        $epoch = (int) $token['epoch'];
        $readerRows = $readerPlan['reader_rows'];
        $checkpointFrame = (int) $readerPlan['checkpoint_frame'];
        $checkpointCookie = (int) $readerPlan['checkpoint_cookie'];
        $schemaCookie = (int) $readerPlan['schema_cookie'];
        $pageNumbers = array_map('intval', $readerPlan['page_numbers']);

        $rows = [];
        foreach ($readerRows as $reader) {
            $readerName = (string) $reader['name'];
            $ack = $acknowledgements[$readerName] ?? null;
            $rows[] = self::admissionRow(
                $reader,
                is_array($ack) ? $ack : null,
                $tokenId,
                $epoch,
                $checkpointFrame,
                $checkpointCookie,
                $schemaCookie
            );
        }

        $missingAcknowledgements = array_values(array_column(array_filter(
            $rows,
            static fn (array $row): bool => $row['expected_action'] === 'retain-reader-cache' && $row['acknowledged'] === false
        ), 'reader'));
        $missingReopenFences = array_values(array_column(array_filter(
            $rows,
            static fn (array $row): bool => $row['expected_action'] === 'reopen-reader-cache' && $row['reopen_fenced'] === false
        ), 'reader'));
        $unexpectedAcknowledgements = array_values(array_column(array_filter(
            $rows,
            static fn (array $row): bool => $row['expected_action'] === 'reopen-reader-cache' && $row['acknowledged'] === true
        ), 'reader'));
        $staleAcknowledgements = array_values(array_column(array_filter(
            $rows,
            static fn (array $row): bool => $row['stale_acknowledgement'] === true
        ), 'reader'));
        $orphanAcknowledgements = array_values(array_diff(array_keys($acknowledgements), array_column($rows, 'reader')));

        $guards = [
            'next205_reader_plan_ready' => $readerPlan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next205',
            'checkpoint_published' => ($readerPlan['checkpoint_published'] ?? false) === true,
            'journal_removed' => ($readerPlan['journal_removed'] ?? false) === true,
            'all_retained_readers_acknowledged' => $missingAcknowledgements === [],
            'all_reopened_readers_fenced' => $missingReopenFences === [],
            'no_stale_acknowledgements' => $staleAcknowledgements === [],
            'no_unexpected_stale_reader_acknowledgements' => $unexpectedAcknowledgements === [],
            'no_orphan_acknowledgements' => $orphanAcknowledgements === [],
        ];
        $blocked = array_keys(array_filter($guards, static fn (bool $passed): bool => !$passed));
        $admittedReaders = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['checkpoint_admitted']), 'reader'));
        $reopenReaders = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['expected_action'] === 'reopen-reader-cache'), 'reader'));

        return [
            'status' => $blocked === []
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next211'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next211',
            'reason' => $blocked === []
                ? 'checkpoint_current_source_acknowledgements_admit_next_source'
                : 'checkpoint_current_source_acknowledgements_block_next_source',
            'database_path' => (string) $readerPlan['database_path'],
            'wal_path' => (string) $readerPlan['wal_path'],
            'journal_path' => (string) $readerPlan['journal_path'],
            'current_source_token' => $token,
            'checkpoint_frame' => $checkpointFrame,
            'checkpoint_cookie' => $checkpointCookie,
            'schema_cookie' => $schemaCookie,
            'page_numbers' => $pageNumbers,
            'reader_admission_rows' => $rows,
            'admitted_reader_names' => $admittedReaders,
            'reopen_reader_names' => $reopenReaders,
            'missing_acknowledgements' => $missingAcknowledgements,
            'missing_reopen_fences' => $missingReopenFences,
            'unexpected_acknowledgements' => $unexpectedAcknowledgements,
            'stale_acknowledgements' => $staleAcknowledgements,
            'orphan_acknowledgements' => $orphanAcknowledgements,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blocked,
            'checkpoint_admitted' => $blocked === [],
            'next_source_epoch' => $epoch + 1,
            'checkpoint_admission_digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($readerPlan['operation_names'] ?? null) ? $readerPlan['operation_names'] : [],
                [
                    'acknowledge_reader_page_digest_next211',
                    'fence_reopened_reader_cache_next211',
                    'admit_checkpoint_next_source_after_hot_journal_next211',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($readerPlan['dependencies'] ?? null) ? $readerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211',
                    'sqlite-wal-current-source-reader-acknowledgement-fence',
                    'wordpress-import-checkpoint-reader-reopen-fence',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next205 reader page digests, current-source tokens, checkpoint cookies, and reopen fences',
            'non_overlap' => 'next211 admits checkpoint next-source publication only after reader digest acknowledgements and reopen fences; it does not repeat next205 page-image validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, or writer-handle fencing',
        ];
    }

    /**
     * @param array<string,mixed> $readerPlan
     */
    private static function assertReaderPlan(array $readerPlan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'page_numbers', 'reader_rows'] as $key) {
            if (!array_key_exists($key, $readerPlan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next211 missing reader plan {$key}");
            }
        }
        if (!is_array($readerPlan['current_source_token']) || (string) ($readerPlan['current_source_token']['id'] ?? '') === '' || (int) ($readerPlan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next211 token is invalid');
        }
        if (!is_array($readerPlan['reader_rows']) || $readerPlan['reader_rows'] === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next211 requires reader rows');
        }
        if (!is_array($readerPlan['page_numbers']) || $readerPlan['page_numbers'] === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next211 requires page numbers');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie'] as $key) {
            if ((int) $readerPlan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next211 {$key} must be positive");
            }
        }
    }

    /**
     * @param array<string,mixed> $reader
     * @param array<string,mixed>|null $ack
     * @return array<string,mixed>
     */
    private static function admissionRow(
        array $reader,
        ?array $ack,
        string $tokenId,
        int $epoch,
        int $checkpointFrame,
        int $checkpointCookie,
        int $schemaCookie
    ): array {
        foreach (['name', 'page', 'admitted', 'requires_reopen', 'expected_image_sha256', 'observed_image_sha256'] as $key) {
            if (!array_key_exists($key, $reader)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next211 missing reader {$key}");
            }
        }
        $name = (string) $reader['name'];
        $page = (int) $reader['page'];
        if ($name === '' || $page <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next211 reader name/page is invalid');
        }
        $expected = (string) $reader['expected_image_sha256'];
        $observed = (string) $reader['observed_image_sha256'];
        if ($expected === '' || !preg_match('/^[a-f0-9]{64}$/', $observed)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next211 reader digests are invalid');
        }

        $expectsRetain = (bool) $reader['admitted'] && (bool) $reader['requires_reopen'] === false;
        $acknowledged = $ack !== null && ($ack['acknowledged'] ?? false) === true;
        $reopenFenced = $ack !== null && ($ack['reopen_fenced'] ?? false) === true;
        $ackDigest = $ack === null ? null : (string) ($ack['image_sha256'] ?? '');
        $ackSource = $ack === null ? null : (string) ($ack['source_id'] ?? '');
        $ackEpoch = $ack === null ? null : (int) ($ack['epoch'] ?? 0);
        $ackFrame = $ack === null ? null : (int) ($ack['checkpoint_frame'] ?? 0);
        $ackCookie = $ack === null ? null : (int) ($ack['checkpoint_cookie'] ?? 0);
        $ackSchema = $ack === null ? null : (int) ($ack['schema_cookie'] ?? 0);

        $stale = $ack !== null && (
            $ackSource !== $tokenId
            || $ackEpoch !== $epoch
            || $ackFrame !== $checkpointFrame
            || $ackCookie !== $checkpointCookie
            || $ackSchema !== $schemaCookie
            || ($expectsRetain && !hash_equals($observed, $ackDigest))
        );
        $checkpointAdmitted = $expectsRetain
            && $acknowledged
            && !$stale
            && hash_equals($expected, $observed);

        return [
            'reader' => $name,
            'page' => $page,
            'expected_action' => $expectsRetain ? 'retain-reader-cache' : 'reopen-reader-cache',
            'acknowledged' => $acknowledged,
            'reopen_fenced' => $reopenFenced,
            'stale_acknowledgement' => $stale,
            'checkpoint_admitted' => $checkpointAdmitted,
            'source_id' => $ackSource,
            'epoch' => $ackEpoch,
            'acknowledged_image_sha256' => $ackDigest,
            'expected_image_sha256' => $expected,
            'observed_image_sha256' => $observed,
            'reason' => $checkpointAdmitted
                ? 'reader_acknowledged_checkpoint_current_source_digest'
                : ($expectsRetain ? 'reader_acknowledgement_required_before_next_source' : 'reader_reopen_fence_required_before_next_source'),
            'transition' => $name . '>' . ($checkpointAdmitted ? 'admit-next-source' : ($expectsRetain ? 'wait-for-ack' : 'fence-reopen')) . ':next211',
        ];
    }
}
