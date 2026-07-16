<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalExclusiveModePlan
{
    /**
     * @return array{status:string,script:string,section:string,vfs_version:int,supports_shared_memory:bool,first_access_locking_mode:string,journal_mode_request:string,journal_mode_result:string,can_access:bool,can_write:bool,shm_created:bool,wal_created:bool,sticky_exclusive:bool,normal_request_result:string,second_connection_result:string,header_read_version:int,header_write_version:int,dependencies:list<string>,upstream:list<string>}
     */
    public static function access(
        int $vfsVersion,
        string $section,
        string $firstAccessLockingMode,
        bool $existingWalDatabase,
        bool $writeAttempt,
        bool $leaveWalBeforeNormalRequest = false
    ): array {
        if ($vfsVersion < 1) {
            throw new \InvalidArgumentException('SQLite WAL exclusive-mode VFS version must be positive');
        }

        $section = trim($section);
        $firstAccessLockingMode = strtolower(trim($firstAccessLockingMode));
        if (!in_array($firstAccessLockingMode, ['normal', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite WAL exclusive-mode locking mode must be normal or exclusive');
        }
        if (!in_array($section, ['e_wal-1', 'e_wal-2', 'e_wal-3', 'e_wal-4'], true)) {
            throw new \InvalidArgumentException('Unsupported e_wal exclusive-mode section');
        }

        $supportsSharedMemory = $vfsVersion >= 2;
        $exclusiveBeforeAccess = $firstAccessLockingMode === 'exclusive';
        $walAvailable = $supportsSharedMemory || $exclusiveBeforeAccess;
        $journalModeResult = $walAvailable ? 'wal' : 'delete';
        $canAccess = !$existingWalDatabase || $walAvailable;
        $canWrite = $canAccess && (!$existingWalDatabase || $walAvailable);
        $shmCreated = $journalModeResult === 'wal' && $supportsSharedMemory && !$exclusiveBeforeAccess;
        $walCreated = $journalModeResult === 'wal';
        $stickyExclusive = $journalModeResult === 'wal' && $exclusiveBeforeAccess && !$shmCreated;
        $normalRequestResult = $leaveWalBeforeNormalRequest ? 'normal' : ($stickyExclusive ? 'exclusive' : 'normal');
        $secondConnectionResult = $normalRequestResult === 'exclusive' ? 'database is locked' : 'ok';

        if (!$canAccess) {
            $secondConnectionResult = 'unable to open database file';
        }
        if ($writeAttempt && !$canWrite) {
            $secondConnectionResult = 'unable to open database file';
        }

        $headerReadVersion = $journalModeResult === 'wal' ? 2 : 1;
        $headerWriteVersion = $journalModeResult === 'wal' ? 2 : 1;

        return [
            'status' => $canAccess ? 'ok' : 'blocked',
            'script' => 'e_wal.test',
            'section' => $section,
            'vfs_version' => $vfsVersion,
            'supports_shared_memory' => $supportsSharedMemory,
            'first_access_locking_mode' => $firstAccessLockingMode,
            'journal_mode_request' => 'wal',
            'journal_mode_result' => $journalModeResult,
            'can_access' => $canAccess,
            'can_write' => $writeAttempt ? $canWrite : $canAccess,
            'shm_created' => $shmCreated,
            'wal_created' => $walCreated,
            'sticky_exclusive' => $stickyExclusive,
            'normal_request_result' => $normalRequestResult,
            'second_connection_result' => $secondConnectionResult,
            'header_read_version' => $headerReadVersion,
            'header_write_version' => $headerWriteVersion,
            'dependencies' => [
                'real-upstream-corpus-e-wal',
                'sqlite-wal-exclusive-locking-mode',
                'sqlite-vfs-shared-memory-capability',
            ],
            'upstream' => [self::upstreamSection($section)],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function journalModeExitRows(int $count = 1000): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite WAL journal-mode exit rows require a positive count');
        }

        $pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
        $rows = [];

        foreach (range(1, $count) as $case) {
            $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
            $insertedRowCount = 1 + (($case - 1) % 17);
            $databasePageCount = 2 + (($case + intdiv($case, 13)) % 7);
            $walFrameCount = $insertedRowCount + 1 + (($case + intdiv($case, 11)) % 5);
            $walBytesBeforeExit = 32 + ($walFrameCount * (24 + $pageSize));

            $rows[] = [
                'upstream' => sprintf('e_wal.test 4.2.3..4.3 WAL exit header reversion dynamic case %04d', $case),
                'script' => 'e_wal.test',
                'case' => $case,
                'section' => 'e_wal-4.2.3..4.3',
                'evidence' => ['R-02535-05811', 'R-60175-02388'],
                'database_header_offset' => 18,
                'database_header_length' => 2,
                'pre_wal_header_hex' => '0101',
                'wal_header_hex' => '0202',
                'post_exit_header_hex' => '0101',
                'pre_wal_read_version' => 1,
                'pre_wal_write_version' => 1,
                'wal_read_version' => 2,
                'wal_write_version' => 2,
                'post_exit_read_version' => 1,
                'post_exit_write_version' => 1,
                'journal_mode_before_exit' => 'wal',
                'journal_mode_request' => 'delete',
                'journal_mode_result' => 'delete',
                'wal_sidecar_exists_before_exit' => true,
                'wal_sidecar_exists_after_exit' => false,
                'checkpoint_required_before_unlink' => true,
                'checkpointed_frame_count_on_exit' => $walFrameCount,
                'wal_frame_count_before_exit' => $walFrameCount,
                'wal_bytes_before_exit' => $walBytesBeforeExit,
                'wal_bytes_after_exit' => 0,
                'page_size' => $pageSize,
                'database_page_count' => $databasePageCount,
                'inserted_row_count_before_exit' => $insertedRowCount,
                'legacy_reader_can_access_after_exit' => true,
                'format_reversion_reason' => 'deliberate_exit_from_wal_mode',
                'dependencies' => [
                    'real-upstream-corpus-e-wal',
                    'sqlite-wal-journal-mode-exit',
                    'sqlite-wal-header-version-reversion',
                    'sqlite-wal-sidecar-delete',
                ],
            ];
        }

        return $rows;
    }

    private static function upstreamSection(string $section): string
    {
        return match ($section) {
            'e_wal-1' => 'e_wal.test 1.1.1..1.3.3 old VFS uses WAL only after EXCLUSIVE locking_mode',
            'e_wal-2' => 'e_wal.test 2.1.1..2.3.4 exclusive WAL without SHM remains sticky until leaving WAL',
            'e_wal-3' => 'e_wal.test 3.0..3.4.2 normal WAL access creates SHM and allows mode changes',
            'e_wal-4' => 'e_wal.test 4.1.1..4.2.1 WAL mode updates database header format bytes',
        };
    }
}
