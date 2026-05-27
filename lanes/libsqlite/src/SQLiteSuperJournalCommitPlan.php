<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSuperJournalCommitPlan
{
    /**
     * @param list<array{database_path:string,journal_bytes:string,database_pages:array<int,string>}> $databaseCommits
     * @return array{super_journal_path:string,page_size:int,sync_mode:string,journal_mode:string,database_count:int,journal_paths:list<string>,database_pages:array<string,list<int>>,super_journal_bytes:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public static function commit(
        string $superJournalPath,
        array $databaseCommits,
        int $pageSize,
        string $syncMode = 'full',
        string $journalMode = 'delete',
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($superJournalPath === '') {
            throw new \InvalidArgumentException('SQLite super-journal commit requires a super-journal path');
        }
        if ($databaseCommits === []) {
            throw new \InvalidArgumentException('SQLite super-journal commit requires at least one attached database');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite super-journal commit page size must be a power of two at least 512');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite super-journal commit requires writable database handles');
        }

        $syncMode = strtolower($syncMode);
        if (!in_array($syncMode, ['off', 'normal', 'full', 'extra'], true)) {
            throw new \InvalidArgumentException('SQLite super-journal commit sync mode must be off, normal, full, or extra');
        }

        $journalMode = strtolower($journalMode);
        if (!in_array($journalMode, ['delete', 'truncate', 'persist'], true)) {
            throw new \InvalidArgumentException('SQLite super-journal commit journal mode must be delete, truncate, or persist');
        }

        $normalized = [];
        $journalPaths = [];
        $databasePages = [];
        foreach ($databaseCommits as $index => $commit) {
            $databasePath = isset($commit['database_path']) ? (string) $commit['database_path'] : '';
            $journalBytes = isset($commit['journal_bytes']) ? (string) $commit['journal_bytes'] : '';
            $pages = isset($commit['database_pages']) && is_array($commit['database_pages']) ? $commit['database_pages'] : [];
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite super-journal commit database {$index} requires a database path");
            }
            if ($journalBytes === '') {
                throw new \InvalidArgumentException("SQLite super-journal commit database {$databasePath} requires rollback-journal bytes");
            }
            if ($pages === []) {
                throw new \InvalidArgumentException("SQLite super-journal commit database {$databasePath} requires dirty pages");
            }

            ksort($pages);
            $pageNumbers = [];
            foreach ($pages as $pageNumber => $pageImage) {
                if (!is_int($pageNumber) || $pageNumber < 1) {
                    throw new \InvalidArgumentException("SQLite super-journal commit database {$databasePath} page numbers must be positive integers");
                }
                if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                    throw new \InvalidArgumentException("SQLite super-journal commit database {$databasePath} page {$pageNumber} image must match page size");
                }
                $pageNumbers[] = $pageNumber;
            }

            $journalPath = $databasePath . '-journal';
            if (isset($journalPaths[$journalPath])) {
                throw new \InvalidArgumentException("SQLite super-journal commit duplicate journal path: {$journalPath}");
            }
            $journalPaths[$journalPath] = true;
            $databasePages[$databasePath] = $pageNumbers;
            $normalized[] = [
                'database_path' => $databasePath,
                'journal_path' => $journalPath,
                'journal_bytes' => $journalBytes,
                'database_pages' => $pages,
            ];
        }

        $journalPathList = array_keys($journalPaths);
        $superJournalBytes = implode("\n", $journalPathList) . "\n";
        $operations = [
            [
                'op' => 'write',
                'path' => $superJournalPath,
                'payload_key' => $superJournalPath,
                'offset' => 0,
                'bytes' => strlen($superJournalBytes),
                'durable' => false,
                'reason' => 'write_super_journal_attached_journal_list',
            ],
        ];
        if ($syncMode !== 'off') {
            $operations[] = [
                'op' => 'sync',
                'path' => $superJournalPath,
                'durable' => true,
                'reason' => $syncMode === 'extra' ? 'sync_super_journal_fullfsync' : 'sync_super_journal',
            ];
        }

        foreach ($normalized as $commit) {
            $operations[] = [
                'op' => 'write',
                'path' => $commit['journal_path'],
                'payload_key' => $commit['journal_path'],
                'offset' => 0,
                'bytes' => strlen($commit['journal_bytes']),
                'durable' => false,
                'reason' => 'write_attached_rollback_journal_before_database_pages',
            ];
            if ($syncMode !== 'off') {
                $operations[] = [
                    'op' => 'sync',
                    'path' => $commit['journal_path'],
                    'durable' => true,
                    'reason' => $syncMode === 'extra' ? 'sync_attached_rollback_journal_fullfsync' : 'sync_attached_rollback_journal',
                ];
            }

            foreach ($commit['database_pages'] as $pageNumber => $pageImage) {
                $operations[] = [
                    'op' => 'write',
                    'path' => $commit['database_path'],
                    'payload_key' => $commit['database_path'] . '#page:' . $pageNumber,
                    'offset' => ($pageNumber - 1) * $pageSize,
                    'bytes' => $pageSize,
                    'durable' => false,
                    'reason' => "write_attached_database_page_{$pageNumber}",
                ];
            }
            if ($syncMode !== 'off') {
                $operations[] = [
                    'op' => 'sync',
                    'path' => $commit['database_path'],
                    'durable' => true,
                    'reason' => 'sync_attached_database_pages',
                ];
            }
        }

        $operations[] = [
            'op' => 'delete',
            'path' => $superJournalPath,
            'durable' => false,
            'reason' => 'delete_super_journal_to_commit_attached_databases',
        ];
        foreach ($normalized as $commit) {
            if ($journalMode === 'delete') {
                $operations[] = [
                    'op' => 'delete',
                    'path' => $commit['journal_path'],
                    'durable' => false,
                    'reason' => 'delete_attached_rollback_journal_after_super_commit',
                ];
            } elseif ($journalMode === 'truncate') {
                $operations[] = [
                    'op' => 'truncate',
                    'path' => $commit['journal_path'],
                    'bytes' => 0,
                    'durable' => false,
                    'reason' => 'truncate_attached_rollback_journal_after_super_commit',
                ];
            } else {
                $operations[] = [
                    'op' => 'write',
                    'path' => $commit['journal_path'],
                    'payload_key' => $commit['journal_path'] . '#persist-header',
                    'offset' => 0,
                    'bytes' => min(28, strlen($commit['journal_bytes'])),
                    'durable' => false,
                    'reason' => 'zero_attached_rollback_journal_header_after_super_commit',
                ];
            }
        }

        if ($syncMode !== 'off') {
            foreach (array_unique(array_map('dirname', array_merge([$superJournalPath], array_column($normalized, 'database_path')))) as $directory) {
                $operations[] = [
                    'op' => 'sync_directory',
                    'path' => $directory,
                    'durable' => true,
                    'reason' => 'persist_super_journal_commit_sidecars',
                ];
            }
        }

        return [
            'super_journal_path' => $superJournalPath,
            'page_size' => $pageSize,
            'sync_mode' => $syncMode,
            'journal_mode' => $journalMode,
            'database_count' => count($normalized),
            'journal_paths' => $journalPathList,
            'database_pages' => $databasePages,
            'super_journal_bytes' => strlen($superJournalBytes),
            'operations' => $operations,
            'dependencies' => ['sqlite-super-journal-commit', 'attached-database-atomic-commit', 'vfs-file-write-coordination'],
        ];
    }

    /**
     * @param list<array{database_path:string,journal_bytes:string,database_pages:array<int,string>}> $databaseCommits
     * @return array<string, string>
     */
    public static function payloads(string $superJournalPath, array $databaseCommits): array
    {
        $journalPaths = [];
        foreach ($databaseCommits as $commit) {
            $databasePath = (string) ($commit['database_path'] ?? '');
            if ($databasePath === '') {
                continue;
            }
            $journalPaths[] = $databasePath . '-journal';
        }

        $payloads = [$superJournalPath => implode("\n", $journalPaths) . "\n"];
        foreach ($databaseCommits as $commit) {
            $databasePath = (string) $commit['database_path'];
            $journalPath = $databasePath . '-journal';
            $journalBytes = (string) $commit['journal_bytes'];
            $payloads[$journalPath] = $journalBytes;
            $payloads[$journalPath . '#persist-header'] = str_repeat("\0", min(28, strlen($journalBytes)));
            foreach ($commit['database_pages'] as $pageNumber => $pageImage) {
                $payloads[$databasePath . '#page:' . $pageNumber] = $pageImage;
            }
        }

        return $payloads;
    }
}
