<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteImportTransactionPlan
{
    /**
     * @param list<array<string, mixed>> $currentRows
     * @param list<array<string, mixed>> $stagedRows
     * @param array{begin?:string,database_path?:string,delete_missing?:bool,journal_mode?:string,page_size?:int,replace_conflicts?:bool,sync_mode?:string} $options
     * @return array<string, mixed>
     */
    public static function plan(array $currentRows, array $stagedRows, array $options = []): array
    {
        $beginSql = (string) ($options['begin'] ?? 'BEGIN IMMEDIATE');
        $databasePath = (string) ($options['database_path'] ?? '/tmp/wp-options.sqlite');
        $deleteMissing = (bool) ($options['delete_missing'] ?? false);
        $replaceConflicts = (bool) ($options['replace_conflicts'] ?? false);
        $journalMode = strtolower((string) ($options['journal_mode'] ?? 'delete'));
        $syncMode = strtolower((string) ($options['sync_mode'] ?? 'full'));
        $pageSize = (int) ($options['page_size'] ?? 4096);

        if ($databasePath === '' || $databasePath[0] !== '/' || str_contains($databasePath, "\0") || str_contains($databasePath, '..')) {
            throw new \InvalidArgumentException('SQLite Application import transaction requires a safe absolute database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application import transaction page size must be a power of two at least 512');
        }
        if (!in_array($journalMode, ['delete', 'truncate', 'persist'], true)) {
            throw new \InvalidArgumentException('SQLite Application import transaction journal mode must be delete, truncate, or persist');
        }
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite Application import transaction sync mode must be off, normal, or full');
        }

        $begin = SQLiteTransactionBeginLockPlan::plan($beginSql, journalMode: 'delete');
        if (!$begin['write_lock_acquired']) {
            throw new \InvalidArgumentException('SQLite Application import transaction requires an immediate or exclusive write transaction');
        }

        $currentById = [];
        $currentNameToId = [];
        $maxId = 0;
        foreach ($currentRows as $row) {
            $normalized = self::normalizeRow($row, false);
            $id = $normalized['option_id'];
            $name = $normalized['option_name'];
            if (isset($currentById[$id])) {
                throw new \InvalidArgumentException("Duplicate current wp_options option_id {$id}");
            }
            if (isset($currentNameToId[$name])) {
                throw new \InvalidArgumentException("Duplicate current wp_options option_name {$name}");
            }
            $currentById[$id] = $normalized;
            $currentNameToId[$name] = $id;
            $maxId = max($maxId, $id);
        }

        $stagedByKey = [];
        foreach ($stagedRows as $row) {
            $normalized = self::normalizeRow($row, true);
            $key = $normalized['option_id'] !== null ? 'id:' . $normalized['option_id'] : 'name:' . $normalized['option_name'];
            $stagedByKey[$key] = $normalized;
        }

        $finalById = $currentById;
        $updated = [];
        $inserted = [];
        $deleted = [];
        $unchanged = [];
        $conflicts = [];
        $dirtyPages = [];

        foreach ($stagedByKey as $stage) {
            $targetId = $stage['option_id'];
            if ($targetId === null && isset($currentNameToId[$stage['option_name']])) {
                $targetId = $currentNameToId[$stage['option_name']];
            }

            if ($targetId !== null && isset($finalById[$targetId])) {
                $before = $finalById[$targetId];
                $after = [
                    'option_id' => $targetId,
                    'option_name' => $stage['option_name'],
                    'option_value' => $stage['option_value'],
                    'autoload' => $stage['autoload'],
                ];
                $conflictingId = self::conflictingOptionNameId($finalById, $after['option_name'], $targetId);
                if ($conflictingId !== null) {
                    $conflicts[] = [
                        'option_name' => $after['option_name'],
                        'incoming_option_id' => $targetId,
                        'conflicting_option_id' => $conflictingId,
                        'action' => $replaceConflicts ? 'delete_conflicting_current' : 'abort',
                    ];
                    if (!$replaceConflicts) {
                        throw new \LogicException("SQLite Application import unique option_name conflict: {$after['option_name']}");
                    }
                    $deleted[] = $finalById[$conflictingId] + ['reason' => 'replace_conflict'];
                    unset($finalById[$conflictingId]);
                    $dirtyPages[self::pageForOptionId($conflictingId)] = true;
                }

                if ($before === $after) {
                    $unchanged[] = $after;
                } else {
                    $updated[] = ['before' => $before, 'after' => $after];
                    $dirtyPages[self::pageForOptionId($targetId)] = true;
                }
                $finalById[$targetId] = $after;
                continue;
            }

            $newId = $targetId;
            if ($newId === null) {
                $newId = ++$maxId;
            } elseif ($newId <= 0) {
                throw new \InvalidArgumentException('Staged wp_options option_id must be positive when supplied');
            } else {
                $maxId = max($maxId, $newId);
            }

            $conflictingId = self::conflictingOptionNameId($finalById, $stage['option_name'], $newId);
            if ($conflictingId !== null) {
                $conflicts[] = [
                    'option_name' => $stage['option_name'],
                    'incoming_option_id' => $newId,
                    'conflicting_option_id' => $conflictingId,
                    'action' => $replaceConflicts ? 'delete_conflicting_current' : 'abort',
                ];
                if (!$replaceConflicts) {
                    throw new \LogicException("SQLite Application import unique option_name conflict: {$stage['option_name']}");
                }
                $deleted[] = $finalById[$conflictingId] + ['reason' => 'replace_conflict'];
                unset($finalById[$conflictingId]);
                $dirtyPages[self::pageForOptionId($conflictingId)] = true;
            }

            $insert = [
                'option_id' => $newId,
                'option_name' => $stage['option_name'],
                'option_value' => $stage['option_value'],
                'autoload' => $stage['autoload'],
            ];
            $inserted[] = $insert;
            $finalById[$newId] = $insert;
            $dirtyPages[self::pageForOptionId($newId)] = true;
        }

        if ($deleteMissing) {
            $stagedNames = [];
            foreach ($stagedByKey as $stage) {
                $stagedNames[$stage['option_name']] = true;
            }
            foreach ($currentById as $id => $row) {
                if (!isset($stagedNames[$row['option_name']]) && isset($finalById[$id])) {
                    $deleted[] = $row + ['reason' => 'missing_from_stage'];
                    unset($finalById[$id]);
                    $dirtyPages[self::pageForOptionId($id)] = true;
                }
            }
        }

        ksort($finalById);
        ksort($dirtyPages);
        $dirtyPageNumbers = array_map('intval', array_keys($dirtyPages));
        $journalBytes = $dirtyPageNumbers === [] ? 0 : 28 + (count($dirtyPageNumbers) * ($pageSize + 8));
        $syncSequence = $dirtyPageNumbers === []
            ? []
            : SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, $syncMode, $journalMode === 'persist');

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'begin' => $begin,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'page_size' => $pageSize,
            'delete_missing' => $deleteMissing,
            'replace_conflicts' => $replaceConflicts,
            'updated' => array_values($updated),
            'inserted' => array_values($inserted),
            'deleted' => array_values($deleted),
            'unchanged' => array_values($unchanged),
            'conflicts' => $conflicts,
            'final_rows' => array_values($finalById),
            'dirty_pages' => $dirtyPageNumbers,
            'journal_bytes' => $journalBytes,
            'statements' => self::statementSummary($updated, $inserted, $deleted, $syncSequence),
            'sync_sequence' => $syncSequence,
            'dependencies' => [
                'sqlite-application-import-transaction-current',
                'sqlite-begin-transaction-lock-mode',
                'sqlite-rollback-journal-commit',
                'vfs-file-handle-sync',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{option_id:int|null,option_name:string,option_value:mixed,autoload:string}
     */
    private static function normalizeRow(array $row, bool $allowNullId): array
    {
        $id = $row['option_id'] ?? null;
        if ($id !== null) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                throw new \InvalidArgumentException('wp_options option_id must be an integer');
            }
            $id = (int) $id;
            if ($id <= 0) {
                throw new \InvalidArgumentException('wp_options option_id must be positive');
            }
        } elseif (!$allowNullId) {
            throw new \InvalidArgumentException('Current wp_options rows require option_id');
        }

        $name = $row['option_name'] ?? null;
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('wp_options option_name must be non-empty text');
        }

        $autoload = $row['autoload'] ?? 'no';
        if (!is_string($autoload) || !in_array($autoload, ['yes', 'no', 'auto', 'on', 'off'], true)) {
            throw new \InvalidArgumentException('wp_options autoload must be a supported SQLite import value');
        }

        return [
            'option_id' => $id,
            'option_name' => $name,
            'option_value' => $row['option_value'] ?? '',
            'autoload' => $autoload,
        ];
    }

    /**
     * @param array<int, array{option_id:int,option_name:string,option_value:mixed,autoload:string}> $rows
     */
    private static function conflictingOptionNameId(array $rows, string $name, int $exceptId): ?int
    {
        foreach ($rows as $id => $row) {
            if ($id !== $exceptId && $row['option_name'] === $name) {
                return $id;
            }
        }

        return null;
    }

    private static function pageForOptionId(int $optionId): int
    {
        return 2 + intdiv($optionId - 1, 64);
    }

    /**
     * @param list<array{before:array<string,mixed>,after:array<string,mixed>}> $updated
     * @param list<array<string,mixed>> $inserted
     * @param list<array<string,mixed>> $deleted
     * @param list<array<string,mixed>> $syncSequence
     * @return list<array<string, mixed>>
     */
    private static function statementSummary(array $updated, array $inserted, array $deleted, array $syncSequence): array
    {
        $statements = [];
        if ($updated !== []) {
            $statements[] = ['op' => 'update', 'rows' => count($updated), 'reason' => 'apply_staged_current_option_rows'];
        }
        if ($inserted !== []) {
            $statements[] = ['op' => 'insert', 'rows' => count($inserted), 'reason' => 'insert_new_staged_option_rows'];
        }
        if ($deleted !== []) {
            $statements[] = ['op' => 'delete', 'rows' => count($deleted), 'reason' => 'delete_replaced_or_missing_current_rows'];
        }
        foreach ($syncSequence as $sync) {
            $statements[] = [
                'op' => 'sync',
                'target' => $sync['target'],
                'path' => $sync['path'],
                'flags' => $sync['flag_names'],
                'reason' => 'durable_import_transaction_commit',
            ];
        }

        return $statements;
    }
}
