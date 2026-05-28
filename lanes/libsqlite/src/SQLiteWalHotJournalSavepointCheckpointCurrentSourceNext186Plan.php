<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext186Plan
{
    /**
     * @param array<string,mixed> $applyResult
     * @param array<string,string|null> $files
     * @param list<string> $readerCacheTokens
     * @return array<string,mixed>
     */
    public static function verifyWalSource(
        array $applyResult,
        array $files,
        int $expectedCheckpointSequence,
        int $expectedPageSize,
        array $readerCacheTokens = [],
        int $readerEpoch = 1
    ): array {
        if ($expectedCheckpointSequence < 0) {
            throw new \InvalidArgumentException('SQLite WAL current-source next186 checkpoint sequence must be non-negative');
        }
        if ($expectedPageSize < 512 || ($expectedPageSize & ($expectedPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL current-source next186 page size must be a power of two at least 512');
        }

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext183Plan::verify(
            $applyResult,
            $files,
            [],
            $readerEpoch
        );
        $normalized = self::normalizeFiles($files);
        $walPath = (string) ($applyResult['wal_path'] ?? '');
        $walBytes = $walPath === '' ? null : ($normalized[$walPath] ?? null);
        $blocked = [];
        $wal = null;
        $walHeader = null;
        $committedFrames = [];
        $walDigest = null;

        if (($base['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next183') {
            $blocked[] = 'next183_current_source_admission_required';
        }
        if ($walPath === '' || $walBytes === null) {
            $blocked[] = 'retained_wal_payload_missing_after_hot_journal_apply';
        } else {
            $walDigest = hash('sha256', $walBytes);
            try {
                $wal = SQLiteWal::parse($walBytes, $expectedPageSize, true);
                $walHeader = $wal->header->toArray();
                foreach ($wal->frames as $frame) {
                    if ($frame->isCommitFrame()) {
                        $committedFrames[] = [
                            'frame_index' => $frame->index,
                            'page_number' => $frame->pageNumber,
                            'commit_page_count' => $frame->databasePageCountAfterCommit,
                        ];
                    }
                }
                if ($wal->header->pageSize !== $expectedPageSize) {
                    $blocked[] = 'wal_page_size_drift_after_hot_journal_apply';
                }
                if ($wal->header->checkpointSequence !== $expectedCheckpointSequence) {
                    $blocked[] = 'wal_checkpoint_sequence_drift_after_hot_journal_apply';
                }
                if ($committedFrames === []) {
                    $blocked[] = 'retained_wal_has_no_commit_frame_for_reader_source';
                }
            } catch (\InvalidArgumentException $exception) {
                $blocked[] = 'retained_wal_checksum_or_header_invalid_after_hot_journal_apply';
                $walHeader = ['error' => $exception->getMessage()];
            }
        }

        $token = self::sourceToken($base, $walDigest, $expectedCheckpointSequence, $expectedPageSize, $readerEpoch);
        $staleTokens = array_values(array_filter(
            $readerCacheTokens,
            static fn (string $candidate): bool => $candidate !== $token
        ));
        $retainedTokens = array_values(array_filter(
            $readerCacheTokens,
            static fn (string $candidate): bool => $candidate === $token
        ));
        if ($staleTokens !== []) {
            $blocked[] = 'wal_source_reader_cache_token_predates_verified_header';
        }

        if ($blocked !== []) {
            return [
                'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next186',
                'reason' => 'wal_header_source_not_admitted_after_hot_journal_apply',
                'base_status' => $base['status'] ?? null,
                'database_path' => $applyResult['database_path'] ?? null,
                'journal_path' => $applyResult['journal_path'] ?? null,
                'wal_path' => $walPath,
                'reader_epoch' => $readerEpoch,
                'reader_source_token' => $token,
                'reader_cache_tokens' => $readerCacheTokens,
                'retained_reader_cache_tokens' => $retainedTokens,
                'stale_reader_cache_tokens' => $staleTokens,
                'requires_reader_reopen' => $staleTokens !== [],
                'wal_digest' => $walDigest,
                'wal_header' => $walHeader,
                'committed_frames' => $committedFrames,
                'committed_frame_count' => count($committedFrames),
                'expected_checkpoint_sequence' => $expectedCheckpointSequence,
                'expected_page_size' => $expectedPageSize,
                'blocked_reasons' => array_values(array_unique($blocked)),
                'dependencies' => self::dependencies($base),
                'dependency_closure' => 'no new support component needed; reuses the accepted WAL parser/checksum validator and next183 current-source admission',
                'non_overlap' => 'next186 validates retained WAL header/checkpoint source metadata after hot-journal savepoint checkpoint apply; it does not repeat next183 reader-token file-map admission, next180 publication, checkpoint transaction planning, WAL byte truncation, or rollback-journal apply',
            ];
        }

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next186',
            'reason' => 'retained_wal_header_source_verified_after_hot_journal_apply',
            'base_status' => $base['status'],
            'database_path' => $applyResult['database_path'],
            'journal_path' => $applyResult['journal_path'],
            'wal_path' => $walPath,
            'reader_epoch' => $readerEpoch,
            'reader_source_token' => $token,
            'reader_cache_tokens' => $readerCacheTokens,
            'retained_reader_cache_tokens' => $retainedTokens,
            'stale_reader_cache_tokens' => [],
            'requires_reader_reopen' => false,
            'wal_digest' => $walDigest,
            'wal_header' => $walHeader,
            'wal_byte_order' => $wal?->header->byteOrder(),
            'wal_checksums_validated' => $wal?->checksumsValidated,
            'wal_frame_count' => count($wal?->frames ?? []),
            'committed_frames' => $committedFrames,
            'committed_frame_count' => count($committedFrames),
            'last_commit_page_count' => $committedFrames[array_key_last($committedFrames)]['commit_page_count'] ?? null,
            'expected_checkpoint_sequence' => $expectedCheckpointSequence,
            'expected_page_size' => $expectedPageSize,
            'base_reader_source_token' => $base['reader_source_token'],
            'base_verified_paths' => $base['verified_paths'],
            'blocked_reasons' => [],
            'dependencies' => self::dependencies($base),
            'dependency_closure' => 'no new support component needed; reuses the accepted WAL parser/checksum validator and next183 current-source admission',
            'non_overlap' => 'next186 validates retained WAL header/checkpoint source metadata after hot-journal savepoint checkpoint apply; it does not repeat next183 reader-token file-map admission, next180 publication, checkpoint transaction planning, WAL byte truncation, or rollback-journal apply',
        ];
    }

    /**
     * @param array<string,mixed> $base
     */
    private static function sourceToken(array $base, ?string $walDigest, int $checkpointSequence, int $pageSize, int $epoch): string
    {
        return 'wal-hot-journal-savepoint-checkpoint-next186:wal-source:' . substr(hash('sha256', implode('|', [
            (string) ($base['reader_source_token'] ?? ''),
            (string) $walDigest,
            (string) $checkpointSequence,
            (string) $pageSize,
            (string) $epoch,
        ])), 0, 32);
    }

    /**
     * @param array<string,mixed> $base
     * @return list<string>
     */
    private static function dependencies(array $base): array
    {
        return array_values(array_unique(array_merge($base['dependencies'] ?? [], [
            'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next186',
            'sqlite-retained-wal-header-current-source-admission',
        ])));
    }

    /**
     * @param array<string,string|null> $files
     * @return array<string,string|null>
     */
    private static function normalizeFiles(array $files): array
    {
        ksort($files, SORT_STRING);
        foreach ($files as $path => $bytes) {
            if (!is_string($path) || $path === '') {
                throw new \InvalidArgumentException('SQLite WAL current-source next186 file paths must be non-empty strings');
            }
            if ($bytes !== null && !is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite WAL current-source next186 file bytes must be strings or null');
            }
        }

        return $files;
    }
}
