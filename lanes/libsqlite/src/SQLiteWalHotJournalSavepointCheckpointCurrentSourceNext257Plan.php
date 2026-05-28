<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan
{
    /**
     * @param array<string,mixed> $nextSourcePlan
     * @param list<array<string,mixed>> $retirementReceipts
     * @return array<string,mixed>
     */
    public static function retireCheckpointSource(array $nextSourcePlan, array $retirementReceipts): array
    {
        if (($nextSourcePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next253'
            || ($nextSourcePlan['next_source_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next257 requires an admitted next253 next-source handoff');
        }
        if ($retirementReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next257 requires retirement receipts');
        }

        $databasePath = self::path($nextSourcePlan['database_path'] ?? null, 'database path');
        $walPath = self::path($nextSourcePlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($nextSourcePlan['journal_path'] ?? null, 'journal path');
        $checkpointToken = self::token($nextSourcePlan['source_token'] ?? null, 'checkpoint source token');
        $nextToken = self::token($nextSourcePlan['next_source_token'] ?? null, 'next source token');
        $checkpointGeneration = self::positiveInt($nextSourcePlan['commit_generation'] ?? null, 'checkpoint generation');
        $nextGeneration = self::positiveInt($nextSourcePlan['next_commit_generation'] ?? null, 'next generation');
        if ($nextGeneration <= $checkpointGeneration) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next257 requires an advancing generation');
        }
        $checkpointFrame = self::nonNegativeInt($nextSourcePlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $nextCheckpointFrame = self::nonNegativeInt($nextSourcePlan['next_checkpoint_frame'] ?? null, 'next checkpoint frame');
        $checkpointDatabaseDigest = self::digest($nextSourcePlan['database_digest'] ?? null, 'checkpoint database digest');
        $checkpointWalDigest = self::digest($nextSourcePlan['wal_digest'] ?? null, 'checkpoint wal digest');
        $nextDatabaseDigest = self::digest($nextSourcePlan['next_database_digest'] ?? null, 'next database digest');
        $nextWalDigest = self::digest($nextSourcePlan['next_wal_digest'] ?? null, 'next wal digest');
        $oldReaders = self::tokenSet($nextSourcePlan['accepted_reader_names'] ?? null, 'accepted reader names');
        $nextPages = self::positiveIntSet($nextSourcePlan['written_next_pages'] ?? null, 'written next pages');
        $nextFrames = self::positiveIntSet($nextSourcePlan['synced_next_frames'] ?? null, 'synced next frames');

        $rows = [];
        foreach ($retirementReceipts as $receipt) {
            $rows[] = self::retirementRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $checkpointToken,
                $nextToken,
                $checkpointGeneration,
                $nextGeneration,
                $checkpointFrame,
                $nextCheckpointFrame,
                $checkpointDatabaseDigest,
                $checkpointWalDigest,
                $nextDatabaseDigest,
                $nextWalDigest,
                $oldReaders
            );
        }

        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $types = [];
        $operations = [];
        $retiredReaders = [];
        $retainedPages = [];
        $retainedFrames = [];

        foreach ($acceptedRows as $row) {
            $types[$row['receipt_type']] = true;
            $operations[] = $row['operation'];
            foreach ($row['retired_reader_names'] as $readerName) {
                $retiredReaders[$readerName] = true;
            }
            foreach ($row['retained_pages'] as $page) {
                $retainedPages[$page] = true;
            }
            foreach ($row['retained_frames'] as $frame) {
                $retainedFrames[$frame] = true;
            }
        }

        $retiredReaderNames = array_values(array_keys($retiredReaders));
        sort($retiredReaderNames);
        $retainedPageNumbers = array_map('intval', array_keys($retainedPages));
        sort($retainedPageNumbers);
        $retainedFrameNumbers = array_map('intval', array_keys($retainedFrames));
        sort($retainedFrameNumbers);
        $missingReaders = array_values(array_diff($oldReaders, $retiredReaderNames));
        $missingPages = array_values(array_diff($nextPages, $retainedPageNumbers));
        $missingFrames = array_values(array_diff($nextFrames, $retainedFrameNumbers));
        $missingTypes = array_values(array_diff(['reader-retire', 'wal-retain', 'journal-delete', 'savepoint-close', 'page-cache-seal'], array_keys($types)));
        $orderSafe = self::orderSafe($operations);

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'checkpoint_retirement_receipt_name_duplicate';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'checkpoint_reader_retirement_missing';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'next_source_page_retention_missing';
        }
        if ($missingFrames !== []) {
            $blockedReasons[] = 'next_source_frame_retention_missing';
        }
        if ($missingTypes !== []) {
            $blockedReasons[] = 'checkpoint_retirement_receipt_type_missing';
        }
        if (!$orderSafe) {
            $blockedReasons[] = 'checkpoint_retirement_order_unsafe';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            ['name' => 'next253_next_source_admitted', 'matched' => true, 'reason' => 'a retry current source is already admitted before old checkpoint retirement'],
            ['name' => 'checkpoint_retirement_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'retirement receipts must be attributable once'],
            ['name' => 'old_checkpoint_readers_retired', 'matched' => $missingReaders === [], 'reason' => 'all readers pinned to the checkpoint source must be reopened or retired'],
            ['name' => 'next_source_pages_retained', 'matched' => $missingPages === [], 'reason' => 'new database pages must stay retained while the old source is retired'],
            ['name' => 'next_source_frames_retained', 'matched' => $missingFrames === [], 'reason' => 'new WAL frames must stay retained while the old source is retired'],
            ['name' => 'checkpoint_sidecars_removed', 'matched' => !in_array('journal-delete', $missingTypes, true) && !in_array('savepoint-close', $missingTypes, true), 'reason' => 'hot-journal and savepoint sidecars must be gone before old source retirement'],
            ['name' => 'page_cache_sealed_to_next_source', 'matched' => !in_array('page-cache-seal', $missingTypes, true), 'reason' => 'page-cache digest must be sealed to the next source'],
            ['name' => 'checkpoint_retirement_order_safe', 'matched' => $orderSafe, 'reason' => 'reader retirement and next-source retention must precede old sidecar deletion and cache sealing'],
            ['name' => 'all_checkpoint_retirement_receipts_accepted', 'matched' => $blockedRows === [], 'reason' => 'receipt tokens, generations, digests, paths, locks, and IO state must match'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $retired = $blockedGuards === [];

        return [
            'status' => $retired ? 'wal-hot-journal-savepoint-checkpoint-current-source-next257' : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next257',
            'reason' => $retired ? 'checkpoint_source_retired_after_next_source_admission' : 'checkpoint_source_retirement_held_after_next_source_admission',
            'base_status' => $nextSourcePlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'checkpoint_source_token' => $checkpointToken,
            'next_source_token' => $nextToken,
            'checkpoint_commit_generation' => $checkpointGeneration,
            'next_commit_generation' => $nextGeneration,
            'checkpoint_frame' => $checkpointFrame,
            'next_checkpoint_frame' => $nextCheckpointFrame,
            'checkpoint_database_digest' => $checkpointDatabaseDigest,
            'checkpoint_wal_digest' => $checkpointWalDigest,
            'next_database_digest' => $nextDatabaseDigest,
            'next_wal_digest' => $nextWalDigest,
            'old_reader_names' => $oldReaders,
            'retirement_rows' => $rows,
            'retirement_receipt_names' => array_column($rows, 'name'),
            'accepted_retirement_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_retirement_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_retirement_receipt_names' => $duplicateNames,
            'retirement_receipt_types' => array_values(array_keys($types)),
            'missing_retirement_receipt_types' => $missingTypes,
            'retired_reader_names' => $retiredReaderNames,
            'missing_retired_reader_names' => $missingReaders,
            'retained_next_pages' => $retainedPageNumbers,
            'missing_retained_next_pages' => $missingPages,
            'retained_next_frames' => $retainedFrameNumbers,
            'missing_retained_next_frames' => $missingFrames,
            'operation_order' => $operations,
            'retirement_order_safe' => $orderSafe,
            'blocked_reasons' => $blockedReasons,
            'checkpoint_source_retired' => $retired,
            'reader_action' => $retired ? 'retire_checkpoint_readers_for_generation_' . $checkpointGeneration : 'hold_checkpoint_readers_until_retirement_receipts_match',
            'wal_action' => $retired ? 'retain_next_wal_generation_' . $nextCheckpointFrame . '_and_discard_checkpoint_source_' . $checkpointFrame : 'keep_checkpoint_wal_source_available',
            'journal_action' => $retired ? 'delete_checkpoint_hot_journal_after_reader_retirement' : 'retain_checkpoint_hot_journal_fence',
            'savepoint_action' => $retired ? 'close_checkpoint_savepoint_after_next_source_retention' : 'keep_checkpoint_savepoint_replayable',
            'page_cache_action' => $retired ? 'seal_page_cache_to_next_source_' . $nextGeneration : 'discard_unsealed_page_cache_retirement',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'retirement_digest' => hash('sha256', json_encode([$checkpointToken, $nextToken, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($nextSourcePlan['operation_names'] ?? null) ? $nextSourcePlan['operation_names'] : [],
                [
                    'verify_checkpoint_source_retirement_after_next_source_next257',
                    $retired ? 'retire_checkpoint_current_source_next257' : 'hold_checkpoint_current_source_next257',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($nextSourcePlan['dependencies'] ?? null) ? $nextSourcePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next257',
                    'sqlite-wal-checkpoint-source-retirement-after-retry-admission',
                    'wordpress-import-hot-journal-savepoint-checkpoint-source-retirement',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next253 next-source admission metadata, reader retirement receipts, WAL retention receipts, hot-journal deletion, savepoint close, and page-cache seal evidence',
            'non_overlap' => 'next257 retires the previous checkpoint source only after next253 retry-source admission; it does not repeat next-source handoff admission, durable VFS receipt ordering, reopened current-source digest checks, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, reader snapshot matching, JSON, SELECT, or B-tree behavior',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $oldReaders
     * @return array<string,mixed>
     */
    private static function retirementRow(array $receipt, string $databasePath, string $walPath, string $journalPath, string $checkpointToken, string $nextToken, int $checkpointGeneration, int $nextGeneration, int $checkpointFrame, int $nextCheckpointFrame, string $checkpointDatabaseDigest, string $checkpointWalDigest, string $nextDatabaseDigest, string $nextWalDigest, array $oldReaders): array
    {
        $name = self::token($receipt['name'] ?? null, 'receipt name');
        $type = self::receiptType($receipt['receipt_type'] ?? null, "{$name} receipt type");
        $operation = self::operation($receipt['operation'] ?? null, "{$name} operation");
        $path = self::path($receipt['path'] ?? null, "{$name} path");
        $retiredReaders = self::optionalTokenSet($receipt['retired_reader_names'] ?? null, "{$name} retired readers");
        $retainedPages = self::optionalPositiveIntSet($receipt['retained_pages'] ?? null, "{$name} retained pages");
        $retainedFrames = self::optionalPositiveIntSet($receipt['retained_frames'] ?? null, "{$name} retained frames");
        $reasons = [];

        $expectedPath = match ($type) {
            'reader-retire', 'page-cache-seal' => $databasePath,
            'wal-retain' => $walPath,
            'journal-delete' => $journalPath,
            'savepoint-close' => $databasePath,
        };
        if ($path !== $expectedPath) {
            $reasons[] = 'checkpoint_retirement_path_mismatch';
        }
        if (self::token($receipt['checkpoint_source_token'] ?? null, "{$name} checkpoint token") !== $checkpointToken) {
            $reasons[] = 'checkpoint_source_token_mismatch';
        }
        if (self::token($receipt['next_source_token'] ?? null, "{$name} next token") !== $nextToken) {
            $reasons[] = 'checkpoint_next_source_token_mismatch';
        }
        if (self::positiveInt($receipt['checkpoint_commit_generation'] ?? null, "{$name} checkpoint generation") !== $checkpointGeneration) {
            $reasons[] = 'checkpoint_generation_mismatch';
        }
        if (self::positiveInt($receipt['next_commit_generation'] ?? null, "{$name} next generation") !== $nextGeneration) {
            $reasons[] = 'checkpoint_next_generation_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'checkpoint_frame_mismatch';
        }
        if (self::nonNegativeInt($receipt['next_checkpoint_frame'] ?? null, "{$name} next checkpoint frame") !== $nextCheckpointFrame) {
            $reasons[] = 'checkpoint_next_frame_mismatch';
        }
        if (self::digest($receipt['checkpoint_database_digest'] ?? null, "{$name} checkpoint database digest") !== $checkpointDatabaseDigest) {
            $reasons[] = 'checkpoint_database_digest_mismatch';
        }
        if (self::digest($receipt['checkpoint_wal_digest'] ?? null, "{$name} checkpoint wal digest") !== $checkpointWalDigest) {
            $reasons[] = 'checkpoint_wal_digest_mismatch';
        }
        if (self::digest($receipt['next_database_digest'] ?? null, "{$name} next database digest") !== $nextDatabaseDigest) {
            $reasons[] = 'checkpoint_next_database_digest_mismatch';
        }
        if (self::digest($receipt['next_wal_digest'] ?? null, "{$name} next wal digest") !== $nextWalDigest) {
            $reasons[] = 'checkpoint_next_wal_digest_mismatch';
        }
        if (($receipt['exclusive_lock_held'] ?? null) !== true) {
            $reasons[] = 'checkpoint_retirement_exclusive_lock_missing';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'checkpoint_retirement_io_error';
        }
        if ($type === 'reader-retire' && ($operation !== 'retire_readers' || $retiredReaders === [] || array_diff($retiredReaders, $oldReaders) !== [])) {
            $reasons[] = 'checkpoint_reader_retirement_invalid';
        }
        if ($type === 'wal-retain' && ($operation !== 'retain_next_wal' || $retainedFrames === [])) {
            $reasons[] = 'checkpoint_wal_retention_invalid';
        }
        if ($type === 'journal-delete' && ($operation !== 'delete_checkpoint_journal' || ($receipt['hot_journal_deleted'] ?? null) !== true)) {
            $reasons[] = 'checkpoint_hot_journal_delete_missing';
        }
        if ($type === 'savepoint-close' && ($operation !== 'close_checkpoint_savepoint' || ($receipt['savepoint_closed'] ?? null) !== true)) {
            $reasons[] = 'checkpoint_savepoint_close_missing';
        }
        if ($type === 'page-cache-seal' && ($operation !== 'seal_page_cache' || $retainedPages === [] || ($receipt['page_cache_sealed'] ?? null) !== true)) {
            $reasons[] = 'checkpoint_page_cache_seal_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'receipt_type' => $type,
            'operation' => $operation,
            'path' => $path,
            'retired_reader_names' => $retiredReaders,
            'retained_pages' => $retainedPages,
            'retained_frames' => $retainedFrames,
            'accepted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'checkpoint_retirement_receipt_matches_next_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /** @param list<string> $operations */
    private static function orderSafe(array $operations): bool
    {
        $positions = [];
        foreach ($operations as $index => $operation) {
            $positions[$operation] ??= $index;
        }
        foreach (['retire_readers', 'retain_next_wal', 'delete_checkpoint_journal', 'close_checkpoint_savepoint', 'seal_page_cache'] as $operation) {
            if (!array_key_exists($operation, $positions)) {
                return false;
            }
        }

        return $positions['retire_readers'] < $positions['retain_next_wal']
            && $positions['retain_next_wal'] < $positions['delete_checkpoint_journal']
            && $positions['delete_checkpoint_journal'] < $positions['close_checkpoint_savepoint']
            && $positions['close_checkpoint_savepoint'] < $positions['seal_page_cache'];
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    /** @return list<int> */
    private static function positiveIntSet(mixed $value, string $label): array
    {
        $result = self::optionalPositiveIntSet($value, $label);
        if ($result === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $result;
    }

    /** @return list<int> */
    private static function optionalPositiveIntSet(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item <= 0) {
                throw new \InvalidArgumentException("Invalid {$label}");
            }
            $set[$item] = true;
        }
        $result = array_map('intval', array_keys($set));
        sort($result);

        return $result;
    }

    /** @return list<string> */
    private static function tokenSet(mixed $value, string $label): array
    {
        $result = self::optionalTokenSet($value, $label);
        if ($result === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $result;
    }

    /** @return list<string> */
    private static function optionalTokenSet(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $set = [];
        foreach ($value as $item) {
            $set[self::token($item, $label)] = true;
        }
        $result = array_keys($set);
        sort($result);

        return $result;
    }

    private static function receiptType(mixed $value, string $label): string
    {
        if (!is_string($value) || !in_array($value, ['reader-retire', 'wal-retain', 'journal-delete', 'savepoint-close', 'page-cache-seal'], true)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    private static function operation(mixed $value, string $label): string
    {
        if (!is_string($value) || !in_array($value, ['retire_readers', 'retain_next_wal', 'delete_checkpoint_journal', 'close_checkpoint_savepoint', 'seal_page_cache'], true)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    /** @param list<string> $values @return list<string> */
    private static function duplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                $duplicates[$value] = true;
                continue;
            }
            $seen[$value] = true;
        }

        return array_values(array_keys($duplicates));
    }
}
