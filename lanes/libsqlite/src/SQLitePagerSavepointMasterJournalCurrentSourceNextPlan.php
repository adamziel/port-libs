<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointMasterJournalCurrentSourceNextPlan
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
        array $retryPageWrites,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($primaryDatabasePath === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal current-source requires a primary database path');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal current-source requires a savepoint name');
        }
        if ($retryPageWrites === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal current-source requires retry page writes');
        }

        $retryPageWrites = self::normalizePages($retryPageWrites, $pageSize, 'retry');
        $masterRecovery = SQLitePagerMasterJournalHotRollbackCurrentSourceNext89Plan::currentSourceNext(
            $masterJournalPath,
            $masterJournalBytes,
            $databases,
            $pageSize,
            $readOnly,
            $immutable
        );

        $currentDatabase = self::databaseBytes($databases, $primaryDatabasePath);
        $recoveredDatabase = $masterRecovery['next_databases'][$primaryDatabasePath] ?? null;
        if (!is_string($recoveredDatabase)) {
            throw new \InvalidArgumentException("SQLite pager savepoint master-journal current-source primary database is not part of the recovery plan: {$primaryDatabasePath}");
        }

        $captured = [];
        $operations = $masterRecovery['operations'];
        $finalDatabase = $recoveredDatabase;
        foreach ($retryPageWrites as $pageNumber => $pageImage) {
            $offset = ($pageNumber - 1) * $pageSize;
            $beforeImage = substr($recoveredDatabase, $offset, $pageSize);
            $currentDirtyImage = substr($currentDatabase, $offset, $pageSize);
            $zeroFilled = false;
            if ($beforeImage === '') {
                $beforeImage = str_repeat("\0", $pageSize);
                $zeroFilled = true;
            }
            if (strlen($beforeImage) !== $pageSize) {
                $beforeImage = str_pad($beforeImage, $pageSize, "\0");
                $zeroFilled = true;
            }

            $captured[] = [
                'page_number' => $pageNumber,
                'source' => $zeroFilled ? 'zero-fill' : 'master-journal-recovered-database',
                'prefix' => self::prefix($beforeImage),
                'matches_dirty_current_source' => $currentDirtyImage !== '' && $currentDirtyImage === $beforeImage,
                'zero_filled_short_read' => $zeroFilled,
            ];
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'path' => $primaryDatabasePath,
                'page_number' => $pageNumber,
                'reason' => 'capture_retry_savepoint_after_master_journal_current_source_recovery',
            ];

            $finalDatabase = self::writePage($finalDatabase, $pageNumber, $pageImage, $pageSize);
            $operations[] = [
                'op' => 'write_retry_savepoint_page',
                'path' => $primaryDatabasePath,
                'page_number' => $pageNumber,
                'reason' => 'write_retry_savepoint_after_master_journal_current_source_recovery',
            ];
        }

        $payloadKey = $primaryDatabasePath . '#savepoint-master-current-source-next92';
        $applyOperations = [];
        if ($masterRecovery['recovered_database_count'] > 0) {
            $applyOperations = $masterRecovery['operations'];
            $applyOperations[] = [
                'op' => 'write',
                'path' => $primaryDatabasePath,
                'payload_key' => $payloadKey,
                'offset' => 0,
                'bytes' => strlen($finalDatabase),
                'durable' => false,
                'reason' => 'write_retry_savepoint_after_master_current_source_recovery',
            ];
            $applyOperations[] = [
                'op' => 'truncate',
                'path' => $primaryDatabasePath,
                'bytes' => strlen($finalDatabase),
                'durable' => false,
                'reason' => 'trim_retry_savepoint_after_master_current_source_recovery',
            ];
            $applyOperations[] = [
                'op' => 'sync',
                'path' => $primaryDatabasePath,
                'durable' => true,
                'reason' => 'sync_retry_savepoint_after_master_current_source_recovery',
            ];
            $applyOperations[] = [
                'op' => 'sync_directory',
                'path' => dirname($primaryDatabasePath),
                'durable' => true,
                'reason' => 'persist_retry_savepoint_after_master_current_source_recovery',
            ];
        }

        return [
            'status' => $masterRecovery['recovered_database_count'] === 0
                ? 'master_journal_recovery_blocked_before_retry_savepoint'
                : 'master_journal_recovered_retry_savepoint_current_source_next',
            'reason' => 'retry_savepoint_uses_master_journal_recovered_current_source',
            'master_recovery' => $masterRecovery,
            'primary_database_path' => $primaryDatabasePath,
            'savepoint' => $savepointName,
            'retry_page_numbers' => array_keys($retryPageWrites),
            'captured_before_images' => $captured,
            'current_database_bytes' => $currentDatabase,
            'recovered_database_bytes' => $recoveredDatabase,
            'final_database_bytes' => $masterRecovery['recovered_database_count'] === 0 ? $currentDatabase : $finalDatabase,
            'operations' => $operations,
            'apply_operations' => $applyOperations,
            'payloads' => $masterRecovery['payloads'] + [$payloadKey => $finalDatabase],
            'dependencies' => array_values(array_unique(array_merge(
                $masterRecovery['dependencies'],
                [
                    'sqlite-pager-savepoint-master-journal-current-source-next92',
                    'sqlite-savepoint-before-image-after-master-journal-recovery',
                ]
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $databases
     */
    private static function databaseBytes(array $databases, string $databasePath): string
    {
        foreach ($databases as $entry) {
            if (($entry['database_path'] ?? null) === $databasePath) {
                return (string) ($entry['current_database_bytes'] ?? '');
            }
        }

        throw new \InvalidArgumentException("SQLite pager savepoint master-journal current-source database is missing: {$databasePath}");
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal current-source page size must be a power of two at least 512');
        }

        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal {$label} page numbers must be one-based integers");
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $pageImage;
        }

        return $normalized;
    }

    private static function writePage(string $databaseBytes, int $pageNumber, string $pageImage, int $pageSize): string
    {
        $offset = ($pageNumber - 1) * $pageSize;
        if (strlen($databaseBytes) < $offset) {
            $databaseBytes = str_pad($databaseBytes, $offset, "\0");
        }

        return substr_replace($databaseBytes, $pageImage, $offset, $pageSize);
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
