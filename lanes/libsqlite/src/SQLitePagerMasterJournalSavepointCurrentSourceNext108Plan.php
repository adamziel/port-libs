<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalSavepointCurrentSourceNext108Plan
{
    /**
     * @param list<array{database_path:string,current_database_bytes:string,current_journal_bytes:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @param array<int,string> $retryPageWrites
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        string $masterJournalPath,
        ?string $masterJournalBytes,
        array $databases,
        int $pageSize,
        string $primaryDatabasePath,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $retryPageWrites,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint current-source requires a savepoint name');
        }

        $beforeStack = clone $savepoints;
        $beforePlan = $beforeStack->rollbackToPlan($savepointName);
        $retry = SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext(
            $masterJournalPath,
            $masterJournalBytes,
            $databases,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            $retryPageWrites,
            $readOnly,
            $immutable
        );

        if ($retry['status'] === 'master_journal_recovery_blocked_before_retry_savepoint') {
            return [
                'status' => 'master_journal_savepoint_current_source_blocked',
                'reason' => 'master_journal_recovery_blocked_before_savepoint_before_images',
                'retry_recovery' => $retry,
                'savepoint' => $savepointName,
                'savepoint_before' => $beforePlan,
                'savepoint_after' => null,
                'rollback_preview' => null,
                'captured_page_numbers' => [],
                'captured_sources' => [],
                'current_source_verified' => false,
                'operations' => [],
                'payloads' => [],
                'dependencies' => array_values(array_unique(array_merge(
                    $retry['dependencies'],
                    ['sqlite-pager-master-journal-savepoint-current-source-next108']
                ))),
            ];
        }

        $afterStack = clone $savepoints;
        $capturedPages = [];
        $capturedSources = [];
        $captureOperations = [];
        foreach ($retry['captured_before_images'] as $capture) {
            $pageNumber = (int) $capture['page_number'];
            $image = self::pageImage((string) $retry['recovered_database_bytes'], $pageNumber, $pageSize);
            $afterStack->recordPageImageWrite($pageNumber, $image);
            $capturedPages[] = $pageNumber;
            $capturedSources[$pageNumber] = (string) $capture['source'];
            $captureOperations[] = [
                'op' => 'record_savepoint_before_image',
                'path' => $primaryDatabasePath,
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source' => $capture['source'],
                'reason' => 'record_recovered_current_source_before_retry_write',
            ];
        }

        $rollbackPreviewBytes = $afterStack->rollbackToDatabaseImage(
            $savepointName,
            (string) $retry['final_database_bytes'],
            $pageSize
        );
        $rollbackPlan = $afterStack->rollbackToImagePlan($savepointName, $pageSize);
        $restoredPrefixes = [];
        foreach ($rollbackPlan['restored_page_numbers'] as $pageNumber) {
            $restoredPrefixes[$pageNumber] = self::prefix(self::pageImage($rollbackPreviewBytes, (int) $pageNumber, $pageSize));
        }

        $payloadKey = $primaryDatabasePath . '#master-savepoint-current-source-next108';
        $rollbackPayloadKey = $primaryDatabasePath . '#master-savepoint-rollback-preview-next108';
        $operations = array_values(array_merge($retry['apply_operations'], $captureOperations));
        $operations[] = [
            'op' => 'preview_rollback_to_savepoint',
            'path' => $primaryDatabasePath,
            'payload_key' => $rollbackPayloadKey,
            'savepoint' => $savepointName,
            'bytes' => strlen($rollbackPreviewBytes),
            'durable' => false,
            'reason' => 'prove_retry_pages_restore_to_master_journal_recovered_source',
        ];

        return [
            'status' => 'master_journal_savepoint_current_source_next',
            'reason' => 'savepoint_before_images_use_master_journal_recovered_current_source',
            'retry_recovery' => $retry,
            'primary_database_path' => $primaryDatabasePath,
            'savepoint' => $savepointName,
            'savepoint_before' => $beforePlan,
            'savepoint_after' => $afterStack->rollbackToPlan($savepointName),
            'rollback_preview' => [
                'page_size' => $pageSize,
                'restored_page_numbers' => $rollbackPlan['restored_page_numbers'],
                'restored_prefixes' => $restoredPrefixes,
                'database_bytes' => strlen($rollbackPreviewBytes),
                'contains_retry_writes' => self::containsAny($rollbackPreviewBytes, $retryPageWrites),
                'matches_recovered_prefix' => substr($rollbackPreviewBytes, 0, strlen((string) $retry['recovered_database_bytes'])) === (string) $retry['recovered_database_bytes'],
            ],
            'captured_page_numbers' => $capturedPages,
            'captured_sources' => $capturedSources,
            'current_source_verified' => true,
            'operations' => $operations,
            'payloads' => $retry['payloads'] + [
                $payloadKey => (string) $retry['final_database_bytes'],
                $rollbackPayloadKey => $rollbackPreviewBytes,
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $retry['dependencies'],
                [
                    'sqlite-pager-master-journal-savepoint-current-source-next108',
                    'sqlite-savepoint-before-images-from-master-journal-current-source',
                ]
            ))),
        ];
    }

    private static function pageImage(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint page numbers are one-based');
        }
        $offset = ($pageNumber - 1) * $pageSize;
        $image = substr($databaseBytes, $offset, $pageSize);

        return str_pad($image, $pageSize, "\0");
    }

    /**
     * @param array<int,string> $needles
     */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (is_string($needle) && $needle !== '' && str_contains($haystack, rtrim(substr($needle, 0, 48), "\0."))) {
                return true;
            }
        }

        return false;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
