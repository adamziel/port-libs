<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerJournalOpenPlan
{
    /**
     * @return array{status:string,database_path:string,journal_path:string,page_size:int,journal_mode:string,read_only:bool,immutable:bool,hot_journal:array<string,mixed>|null,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>,reason:string|null}
     */
    public static function open(
        string $databasePath,
        int $pageSize,
        string $journalMode = 'delete',
        ?string $existingJournalBytes = null,
        bool $databaseReservedLock = false,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        self::validateDatabasePath($databasePath);
        self::validatePageSize($pageSize);
        $journalMode = self::normalizeJournalMode($journalMode);
        $journalPath = $databasePath . '-journal';

        if ($readOnly || $immutable) {
            return self::blocked(
                'blocked',
                $databasePath,
                $journalPath,
                $pageSize,
                $journalMode,
                $readOnly,
                $immutable,
                null,
                $readOnly ? 'read_only_database_handle' : 'immutable_database_handle',
                ['sqlite-pager-rollback-journal-open', 'writable-database-handle-required']
            );
        }

        if ($existingJournalBytes !== null && $existingJournalBytes !== '') {
            $hotJournal = SQLiteRollbackJournal::hotJournalCandidate($existingJournalBytes, $databaseReservedLock);
            if ($hotJournal['hot']) {
                return self::blocked(
                    'recovery-required',
                    $databasePath,
                    $journalPath,
                    $pageSize,
                    $journalMode,
                    $readOnly,
                    $immutable,
                    $hotJournal,
                    'hot_rollback_journal_must_be_recovered_before_write_transaction',
                    ['sqlite-pager-rollback-journal-open', 'hot-journal-before-write-transaction']
                );
            }
        }

        $payloadKey = $journalPath . '#zero-header';
        $zeroHeader = str_repeat("\0", 28);

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'read_only' => false,
            'immutable' => false,
            'hot_journal' => $existingJournalBytes === null ? null : SQLiteRollbackJournal::hotJournalCandidate($existingJournalBytes, $databaseReservedLock),
            'operations' => [
                [
                    'op' => 'write',
                    'path' => $journalPath,
                    'payload_key' => $payloadKey,
                    'offset' => 0,
                    'bytes' => strlen($zeroHeader),
                    'durable' => false,
                    'reason' => 'open_zeroed_rollback_journal_header',
                ],
            ],
            'payloads' => [$payloadKey => $zeroHeader],
            'dependencies' => ['sqlite-pager-rollback-journal-open', 'vfs-file-handle-write-application'],
            'reason' => null,
        ];
    }

    /**
     * @return array{status:string,database_path:string,journal_path:string,page_size:int,journal_mode:string,read_only:bool,immutable:bool,hot_journal:array<string,mixed>|null,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>,reason:string|null}
     */
    public static function closeWithoutDirtyPages(
        string $databasePath,
        int $pageSize,
        string $journalMode = 'delete',
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        self::validateDatabasePath($databasePath);
        self::validatePageSize($pageSize);
        $journalMode = self::normalizeJournalMode($journalMode);
        $journalPath = $databasePath . '-journal';

        if ($readOnly || $immutable) {
            return self::blocked(
                'blocked',
                $databasePath,
                $journalPath,
                $pageSize,
                $journalMode,
                $readOnly,
                $immutable,
                null,
                $readOnly ? 'read_only_database_handle' : 'immutable_database_handle',
                ['sqlite-pager-rollback-journal-close', 'writable-database-handle-required']
            );
        }

        $payloads = [];
        if ($journalMode === 'delete') {
            $operations = [[
                'op' => 'delete',
                'path' => $journalPath,
                'durable' => false,
                'reason' => 'delete_unused_rollback_journal_on_transaction_close',
            ]];
        } elseif ($journalMode === 'truncate') {
            $operations = [[
                'op' => 'truncate',
                'path' => $journalPath,
                'bytes' => 0,
                'durable' => false,
                'reason' => 'truncate_unused_rollback_journal_on_transaction_close',
            ]];
        } else {
            $payloadKey = $journalPath . '#persist-zero-header';
            $payloads[$payloadKey] = str_repeat("\0", 28);
            $operations = [[
                'op' => 'write',
                'path' => $journalPath,
                'payload_key' => $payloadKey,
                'offset' => 0,
                'bytes' => 28,
                'durable' => false,
                'reason' => 'preserve_unused_journal_with_zeroed_header',
            ]];
        }

        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($databasePath),
            'durable' => true,
            'reason' => 'persist_rollback_journal_open_close_sidecar',
        ];

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'read_only' => false,
            'immutable' => false,
            'hot_journal' => null,
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => ['sqlite-pager-rollback-journal-close', 'vfs-file-handle-write-application'],
            'reason' => null,
        ];
    }

    /**
     * @return array{status:string,database_path:string,journal_path:string,page_size:int,journal_mode:string,open:array<string,mixed>,close:array<string,mixed>,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>,reason:string|null}
     */
    public static function openAndCloseWithoutDirtyPages(
        string $databasePath,
        int $pageSize,
        string $journalMode = 'delete',
        ?string $existingJournalBytes = null,
        bool $databaseReservedLock = false,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        $open = self::open($databasePath, $pageSize, $journalMode, $existingJournalBytes, $databaseReservedLock, $readOnly, $immutable);
        if ($open['status'] !== 'planned') {
            return self::combined($open['status'], $databasePath, $databasePath . '-journal', $pageSize, self::normalizeJournalMode($journalMode), $open, null, [], [], $open['dependencies'], $open['reason']);
        }

        $close = self::closeWithoutDirtyPages($databasePath, $pageSize, $journalMode, $readOnly, $immutable);
        if ($close['status'] !== 'planned') {
            return self::combined($close['status'], $databasePath, $databasePath . '-journal', $pageSize, self::normalizeJournalMode($journalMode), $open, $close, $open['operations'], $open['payloads'], array_merge($open['dependencies'], $close['dependencies']), $close['reason']);
        }

        return self::combined(
            'planned',
            $databasePath,
            $open['journal_path'],
            $pageSize,
            $open['journal_mode'],
            $open,
            $close,
            array_merge($open['operations'], $close['operations']),
            array_merge($open['payloads'], $close['payloads']),
            array_merge($open['dependencies'], $close['dependencies'], ['sqlite-pager-transaction-journal-open-closure']),
            null
        );
    }

    private static function validateDatabasePath(string $databasePath): void
    {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager journal open requires a database path');
        }
    }

    private static function validatePageSize(int $pageSize): void
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager journal open page size must be a power of two between 512 and 65536');
        }
    }

    private static function normalizeJournalMode(string $journalMode): string
    {
        $journalMode = strtolower($journalMode);
        if (!in_array($journalMode, ['delete', 'truncate', 'persist'], true)) {
            throw new \InvalidArgumentException('SQLite pager journal mode must be delete, truncate, or persist');
        }

        return $journalMode;
    }

    /**
     * @param array<string,mixed>|null $hotJournal
     * @param list<string> $dependencies
     * @return array{status:string,database_path:string,journal_path:string,page_size:int,journal_mode:string,read_only:bool,immutable:bool,hot_journal:array<string,mixed>|null,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>,reason:string|null}
     */
    private static function blocked(
        string $status,
        string $databasePath,
        string $journalPath,
        int $pageSize,
        string $journalMode,
        bool $readOnly,
        bool $immutable,
        ?array $hotJournal,
        string $reason,
        array $dependencies,
    ): array {
        return [
            'status' => $status,
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'read_only' => $readOnly,
            'immutable' => $immutable,
            'hot_journal' => $hotJournal,
            'operations' => [],
            'payloads' => [],
            'dependencies' => $dependencies,
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string,mixed> $open
     * @param array<string,mixed>|null $close
     * @param list<array<string,mixed>> $operations
     * @param array<string,string> $payloads
     * @param list<string> $dependencies
     * @return array{status:string,database_path:string,journal_path:string,page_size:int,journal_mode:string,open:array<string,mixed>,close:array<string,mixed>,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>,reason:string|null}
     */
    private static function combined(
        string $status,
        string $databasePath,
        string $journalPath,
        int $pageSize,
        string $journalMode,
        array $open,
        ?array $close,
        array $operations,
        array $payloads,
        array $dependencies,
        ?string $reason,
    ): array {
        return [
            'status' => $status,
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'open' => $open,
            'close' => $close ?? [],
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => array_values(array_unique($dependencies)),
            'reason' => $reason,
        ];
    }
}
