<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerStatementRecoveryPlan
{
    /**
     * @param list<array{database_path:string,database_bytes:string,statement_journal_path?:string,statement_journal_exists?:bool,statement_pages:array<int,string>,outer_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @return array{status:string,reason:string,master_journal_path:string,master_journal_exists:bool,master_journal_members:list<string>,database_count:int,recovered_database_count:int,skipped_database_count:int,current_page_prefixes:array<string,array<int,string>>,next_page_prefixes:array<string,array<int,string>>,statement_journal_actions:array<string,string>,outer_journal_actions:array<string,string>,master_journal_action:string,operations:list<array<string,mixed>>,payloads:array<string,string>,databases:array<string,array<string,mixed>>,dependencies:list<string>}
     */
    public static function masterJournalStatementRecoveryCurrentNext(
        string $masterJournalPath,
        ?string $masterJournalBytes,
        array $databases,
        int $pageSize,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager statement recovery requires a master-journal path');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager statement recovery requires at least one attached database');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager statement recovery page size must be a power of two at least 512');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite pager statement recovery requires writable database handles');
        }

        $members = self::masterJournalMembers($masterJournalBytes ?? '');
        $memberSet = array_fill_keys($members, true);
        $masterExists = $members !== [];
        $plans = [];
        $operations = [];
        $payloads = [];
        $currentPrefixes = [];
        $nextPrefixes = [];
        $statementActions = [];
        $outerActions = [];
        $recovered = 0;
        $skipped = 0;

        foreach ($databases as $index => $database) {
            $databasePath = isset($database['database_path']) ? (string) $database['database_path'] : '';
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite pager statement recovery database {$index} requires a database path");
            }
            if (isset($plans[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager statement recovery duplicate database path: {$databasePath}");
            }

            $databaseBytes = isset($database['database_bytes']) ? (string) $database['database_bytes'] : '';
            if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
                throw new \InvalidArgumentException("SQLite pager statement recovery database {$databasePath} image must be aligned to page size");
            }

            $statementPages = isset($database['statement_pages']) && is_array($database['statement_pages']) ? $database['statement_pages'] : [];
            if ($statementPages === []) {
                throw new \InvalidArgumentException("SQLite pager statement recovery database {$databasePath} requires statement preimage pages");
            }

            ksort($statementPages);
            $normalizedPages = [];
            foreach ($statementPages as $pageNumber => $pageImage) {
                if (!is_int($pageNumber) || $pageNumber < 1) {
                    throw new \InvalidArgumentException("SQLite pager statement recovery database {$databasePath} page numbers must be positive integers");
                }
                if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                    throw new \InvalidArgumentException("SQLite pager statement recovery database {$databasePath} page {$pageNumber} image must match page size");
                }
                $normalizedPages[$pageNumber] = $pageImage;
            }

            $outerJournalPath = $databasePath . '-journal';
            $statementJournalPath = isset($database['statement_journal_path']) && (string) $database['statement_journal_path'] !== ''
                ? (string) $database['statement_journal_path']
                : $databasePath . '-stmt-journal';
            $statementJournalExists = (bool) ($database['statement_journal_exists'] ?? true);
            $memberPresent = isset($memberSet[$outerJournalPath]);
            $reservedLock = (bool) ($database['reserved_lock'] ?? false);
            $canRecover = $masterExists && $memberPresent && !$reservedLock && $statementJournalExists;
            $nextBytes = $canRecover
                ? self::restorePages($databaseBytes, $normalizedPages, $pageSize)
                : $databaseBytes;

            $currentPrefixes[$databasePath] = self::pagePrefixes($databaseBytes, array_keys($normalizedPages), $pageSize);
            $nextPrefixes[$databasePath] = self::pagePrefixes($nextBytes, array_keys($normalizedPages), $pageSize);
            $statementActions[$statementJournalPath] = $canRecover ? 'delete_statement_journal_after_rollback' : 'preserve_statement_journal';
            $outerActions[$outerJournalPath] = 'preserve_outer_rollback_journal';

            if ($canRecover) {
                $recovered++;
                $payloads[$databasePath . '#statement-rollback'] = $nextBytes;
                $operations[] = [
                    'op' => 'write',
                    'path' => $databasePath,
                    'offset' => 0,
                    'bytes' => strlen($nextBytes),
                    'payload_key' => $databasePath . '#statement-rollback',
                    'durable' => false,
                    'reason' => 'restore_statement_journal_page_preimages',
                ];
                $operations[] = [
                    'op' => 'truncate',
                    'path' => $databasePath,
                    'bytes' => strlen($nextBytes),
                    'durable' => false,
                    'reason' => 'trim_statement_recovered_database_image',
                ];
                $operations[] = [
                    'op' => 'delete',
                    'path' => $statementJournalPath,
                    'durable' => false,
                    'reason' => 'delete_statement_journal_after_recovery',
                ];
            } else {
                $skipped++;
            }

            $plans[$databasePath] = [
                'database_path' => $databasePath,
                'outer_journal_path' => $outerJournalPath,
                'statement_journal_path' => $statementJournalPath,
                'statement_journal_exists' => $statementJournalExists,
                'master_member_present' => $memberPresent,
                'reserved_lock' => $reservedLock,
                'recovered' => $canRecover,
                'reason' => $canRecover
                    ? 'master_journal_member_statement_rollback'
                    : ($reservedLock ? 'database_has_reserved_lock' : (!$statementJournalExists ? 'missing_statement_journal' : ($masterExists ? 'missing_master_journal_member' : 'missing_master_journal'))),
                'page_numbers' => array_keys($normalizedPages),
                'current_page_prefixes' => $currentPrefixes[$databasePath],
                'next_page_prefixes' => $nextPrefixes[$databasePath],
                'outer_journal_action' => $outerActions[$outerJournalPath],
                'statement_journal_action' => $statementActions[$statementJournalPath],
            ];
        }

        if ($recovered > 0) {
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($masterJournalPath),
                'durable' => true,
                'reason' => 'persist_statement_journal_recovery_sidecars',
            ];
        }

        return [
            'status' => $recovered > 0 ? 'master_journal_statement_recovered_current_next' : 'master_journal_statement_recovery_skipped_current_next',
            'reason' => 'master_journal_members_gate_statement_journal_recovery',
            'master_journal_path' => $masterJournalPath,
            'master_journal_exists' => $masterExists,
            'master_journal_members' => $members,
            'database_count' => count($databases),
            'recovered_database_count' => $recovered,
            'skipped_database_count' => $skipped,
            'current_page_prefixes' => $currentPrefixes,
            'next_page_prefixes' => $nextPrefixes,
            'statement_journal_actions' => $statementActions,
            'outer_journal_actions' => $outerActions,
            'master_journal_action' => 'preserve_master_journal_for_outer_transaction',
            'operations' => $operations,
            'payloads' => $payloads,
            'databases' => $plans,
            'dependencies' => [
                'sqlite-pager-master-journal-statement-recovery-current-next80',
                'sqlite-statement-journal-page-recovery',
                'attached-database-statement-rollback',
                'vfs-file-write-coordination',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function masterJournalMembers(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', $bytes) ?: [] as $line) {
            $member = trim($line);
            if ($member === '' || isset($members[$member])) {
                continue;
            }
            $members[$member] = $member;
        }

        return array_values($members);
    }

    /**
     * @param array<int,string> $pages
     */
    private static function restorePages(string $databaseBytes, array $pages, int $pageSize): string
    {
        $restored = $databaseBytes;
        foreach ($pages as $pageNumber => $pageImage) {
            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset >= strlen($restored)) {
                $restored = str_pad($restored, $offset + $pageSize, "\0");
            }
            $restored = substr_replace($restored, $pageImage, $offset, $pageSize);
        }

        return $restored;
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<int,string>
     */
    private static function pagePrefixes(string $databaseBytes, array $pageNumbers, int $pageSize): array
    {
        $prefixes = [];
        foreach ($pageNumbers as $pageNumber) {
            $offset = ($pageNumber - 1) * $pageSize;
            $prefixes[$pageNumber] = rtrim(substr($databaseBytes, $offset, min(48, $pageSize)), "\0");
        }

        return $prefixes;
    }
}
