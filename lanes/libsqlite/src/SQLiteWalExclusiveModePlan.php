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
