<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan
{
    /**
     * @param array<string,mixed> $reopenPlan
     * @param list<array<string,mixed>> $handoffReceipts
     * @return array<string,mixed>
     */
    public static function admitNextCurrentSource(array $reopenPlan, array $handoffReceipts): array
    {
        if (($reopenPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next249'
            || ($reopenPlan['reopened_current_source_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next253 requires an admitted next249 reopen plan');
        }
        if ($handoffReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next253 requires next-source handoff receipts');
        }

        $databasePath = self::path($reopenPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($reopenPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($reopenPlan['journal_path'] ?? null, 'journal path');
        $currentToken = self::token($reopenPlan['source_token'] ?? null, 'source token');
        $nextToken = self::token($reopenPlan['next_source_token'] ?? null, 'next source token');
        if ($nextToken === $currentToken) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next253 requires a distinct next source token');
        }
        $commitGeneration = self::positiveInt($reopenPlan['commit_generation'] ?? null, 'commit generation');
        $nextGeneration = self::positiveInt($reopenPlan['next_commit_generation'] ?? null, 'next commit generation');
        if ($nextGeneration <= $commitGeneration) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next253 requires an advancing generation');
        }
        $schemaCookie = self::positiveInt($reopenPlan['schema_cookie'] ?? null, 'schema cookie');
        $nextSchemaCookie = self::positiveInt($reopenPlan['next_schema_cookie'] ?? null, 'next schema cookie');
        $checkpointFrame = self::nonNegativeInt($reopenPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $nextCheckpointFrame = self::nonNegativeInt($reopenPlan['next_checkpoint_frame'] ?? null, 'next checkpoint frame');
        if ($nextCheckpointFrame < $checkpointFrame) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next253 requires a non-regressing checkpoint frame');
        }
        $databaseDigest = self::digest($reopenPlan['database_digest'] ?? null, 'database digest');
        $nextDatabaseDigest = self::digest($reopenPlan['next_database_digest'] ?? null, 'next database digest');
        $walDigest = self::digest($reopenPlan['wal_digest'] ?? null, 'wal digest');
        $nextWalDigest = self::digest($reopenPlan['next_wal_digest'] ?? null, 'next wal digest');
        $readerNames = self::tokenSet($reopenPlan['accepted_reader_names'] ?? null, 'accepted reader names');
        $dirtyPages = self::positiveIntSet($reopenPlan['next_dirty_pages'] ?? null, 'next dirty pages');
        $commitFrames = self::positiveIntSet($reopenPlan['next_commit_frames'] ?? null, 'next commit frames');

        $rows = [];
        foreach ($handoffReceipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $nextToken,
                $nextGeneration,
                $nextSchemaCookie,
                $nextCheckpointFrame,
                $nextDatabaseDigest,
                $nextWalDigest,
                $readerNames
            );
        }

        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));

        $seenPages = [];
        $seenFrames = [];
        $seenReaders = [];
        $receiptTypes = [];
        $operations = [];
        foreach ($acceptedRows as $row) {
            $receiptTypes[$row['receipt_type']] = true;
            $operations[] = $row['operation'];
            foreach ($row['pages'] as $page) {
                $seenPages[$page] = true;
            }
            foreach ($row['frames'] as $frame) {
                $seenFrames[$frame] = true;
            }
            foreach ($row['reader_names'] as $readerName) {
                $seenReaders[$readerName] = true;
            }
        }

        $writtenPages = array_map('intval', array_keys($seenPages));
        sort($writtenPages);
        $syncedFrames = array_map('intval', array_keys($seenFrames));
        sort($syncedFrames);
        $ackReaders = array_values(array_keys($seenReaders));
        sort($ackReaders);
        $missingPages = array_values(array_diff($dirtyPages, $writtenPages));
        $missingFrames = array_values(array_diff($commitFrames, $syncedFrames));
        $missingReaders = array_values(array_diff($readerNames, $ackReaders));
        $missingTypes = array_values(array_diff(['database', 'wal', 'readers', 'journal', 'savepoint'], array_keys($receiptTypes)));
        $orderSafe = self::orderSafe($operations);

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'next_source_receipt_name_duplicate';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'next_source_dirty_page_missing';
        }
        if ($missingFrames !== []) {
            $blockedReasons[] = 'next_source_commit_frame_missing';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'next_source_reader_ack_missing';
        }
        if ($missingTypes !== []) {
            $blockedReasons[] = 'next_source_receipt_type_missing';
        }
        if (!$orderSafe) {
            $blockedReasons[] = 'next_source_handoff_order_unsafe';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            ['name' => 'next249_reopen_plan_admitted', 'matched' => true, 'reason' => 'the prior current source was durably reopened before source handoff'],
            ['name' => 'next_source_generation_advances', 'matched' => $nextGeneration > $commitGeneration, 'reason' => 'retry readers must not reuse a stale generation'],
            ['name' => 'next_source_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'each handoff receipt must be attributable once'],
            ['name' => 'next_source_pages_written', 'matched' => $missingPages === [], 'reason' => 'every next-source dirty page must be materialized before reader admission'],
            ['name' => 'next_source_commit_frames_synced', 'matched' => $missingFrames === [], 'reason' => 'every next-source commit frame must be synced before reader admission'],
            ['name' => 'next_source_readers_acknowledged', 'matched' => $missingReaders === [], 'reason' => 'all retry readers must acknowledge the advancing source token'],
            ['name' => 'hot_journal_and_savepoint_fenced', 'matched' => !in_array('journal', $missingTypes, true) && !in_array('savepoint', $missingTypes, true), 'reason' => 'stale hot-journal and savepoint scopes must be fenced before advancing'],
            ['name' => 'next_source_order_safe', 'matched' => $orderSafe, 'reason' => 'database and WAL writes must precede reader ack, hot-journal fence, and savepoint release'],
            ['name' => 'all_next_source_receipts_accepted', 'matched' => $blockedRows === [], 'reason' => 'receipt path, token, generation, schema, checkpoint, digest, lock, and error metadata must match'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted ? 'wal-hot-journal-savepoint-checkpoint-current-source-next253' : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next253',
            'reason' => $admitted ? 'next_source_handoff_admits_retry_readers_after_checkpoint' : 'next_source_handoff_holds_retry_readers_after_checkpoint',
            'base_status' => $reopenPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $currentToken,
            'next_source_token' => $nextToken,
            'commit_generation' => $commitGeneration,
            'next_commit_generation' => $nextGeneration,
            'schema_cookie' => $schemaCookie,
            'next_schema_cookie' => $nextSchemaCookie,
            'checkpoint_frame' => $checkpointFrame,
            'next_checkpoint_frame' => $nextCheckpointFrame,
            'database_digest' => $databaseDigest,
            'next_database_digest' => $nextDatabaseDigest,
            'wal_digest' => $walDigest,
            'next_wal_digest' => $nextWalDigest,
            'accepted_reader_names' => $readerNames,
            'next_dirty_pages' => $dirtyPages,
            'next_commit_frames' => $commitFrames,
            'receipt_rows' => $rows,
            'receipt_names' => array_column($rows, 'name'),
            'accepted_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_receipt_names' => $duplicateNames,
            'receipt_types' => array_values(array_keys($receiptTypes)),
            'missing_receipt_types' => $missingTypes,
            'written_next_pages' => $writtenPages,
            'missing_next_pages' => $missingPages,
            'synced_next_frames' => $syncedFrames,
            'missing_next_frames' => $missingFrames,
            'acknowledged_reader_names' => $ackReaders,
            'missing_reader_names' => $missingReaders,
            'operation_order' => $operations,
            'handoff_order_safe' => $orderSafe,
            'blocked_reasons' => $blockedReasons,
            'next_source_admitted' => $admitted,
            'reader_action' => $admitted ? 'advance_retry_readers_to_next_source_' . $nextGeneration : 'hold_retry_readers_on_checkpoint_source_' . $commitGeneration,
            'wal_action' => $admitted ? 'publish_next_wal_generation_' . $nextCheckpointFrame : 'retain_checkpoint_wal_generation_' . $checkpointFrame,
            'journal_action' => $admitted ? 'keep_hot_journal_fenced_for_next_source' : 'preserve_hot_journal_recovery_fence',
            'savepoint_action' => $admitted ? 'release_checkpoint_savepoint_scope_after_next_source_ack' : 'keep_checkpoint_savepoint_scope_replayable',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'handoff_digest' => hash('sha256', json_encode([$nextToken, $nextGeneration, $nextDatabaseDigest, $nextWalDigest, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($reopenPlan['operation_names'] ?? null) ? $reopenPlan['operation_names'] : [],
                [
                    'verify_next_source_handoff_after_checkpoint_next253',
                    $admitted ? 'admit_retry_readers_to_next_current_source_next253' : 'hold_retry_readers_on_checkpoint_current_source_next253',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($reopenPlan['dependencies'] ?? null) ? $reopenPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next253',
                    'sqlite-wal-next-source-handoff-after-checkpoint',
                    'wordpress-import-hot-journal-savepoint-checkpoint-retry-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next249 reopened current-source metadata, VFS write/sync receipts, WAL digest inventory, reader acknowledgements, hot-journal fences, and savepoint release receipts',
            'non_overlap' => 'next253 admits a retry next-source handoff after next249 reopened visibility; it does not repeat durable VFS receipt ordering, reopened current-source digest checks, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, reader snapshot matching, JSON, SELECT, or B-tree behavior',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $readerNames
     * @return array<string,mixed>
     */
    private static function receiptRow(array $receipt, string $databasePath, string $walPath, string $journalPath, string $nextToken, int $nextGeneration, int $nextSchemaCookie, int $nextCheckpointFrame, string $nextDatabaseDigest, string $nextWalDigest, array $readerNames): array
    {
        $name = self::token($receipt['name'] ?? null, 'receipt name');
        $type = self::receiptType($receipt['receipt_type'] ?? null, "{$name} receipt type");
        $operation = self::operation($receipt['operation'] ?? null, "{$name} operation");
        $path = self::path($receipt['path'] ?? null, "{$name} path");
        $pages = self::optionalPositiveIntSet($receipt['pages'] ?? null, "{$name} pages");
        $frames = self::optionalPositiveIntSet($receipt['frames'] ?? null, "{$name} frames");
        $rowReaders = self::optionalTokenSet($receipt['reader_names'] ?? null, "{$name} reader names");
        $reasons = [];

        $expectedPath = match ($type) {
            'database' => $databasePath,
            'wal' => $walPath,
            'readers', 'savepoint' => $databasePath,
            'journal' => $journalPath,
        };
        if ($path !== $expectedPath) {
            $reasons[] = 'next_source_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $nextToken) {
            $reasons[] = 'next_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $nextGeneration) {
            $reasons[] = 'next_source_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $nextSchemaCookie) {
            $reasons[] = 'next_source_schema_cookie_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $nextCheckpointFrame) {
            $reasons[] = 'next_source_checkpoint_frame_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $nextDatabaseDigest) {
            $reasons[] = 'next_source_database_digest_mismatch';
        }
        if (self::digest($receipt['wal_digest'] ?? null, "{$name} wal digest") !== $nextWalDigest) {
            $reasons[] = 'next_source_wal_digest_mismatch';
        }
        if (($receipt['exclusive_lock_held'] ?? null) !== true) {
            $reasons[] = 'next_source_exclusive_lock_missing';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'next_source_io_error';
        }
        if ($type === 'database' && ($operation !== 'write_database_pages' || $pages === [])) {
            $reasons[] = 'next_source_database_receipt_invalid';
        }
        if ($type === 'wal' && ($operation !== 'sync_wal_frames' || $frames === [])) {
            $reasons[] = 'next_source_wal_receipt_invalid';
        }
        if ($type === 'readers' && ($operation !== 'ack_readers' || $rowReaders === [] || array_diff($rowReaders, $readerNames) !== [])) {
            $reasons[] = 'next_source_reader_receipt_invalid';
        }
        if ($type === 'journal' && ($operation !== 'fence_hot_journal' || ($receipt['hot_journal_fenced'] ?? null) !== true)) {
            $reasons[] = 'next_source_hot_journal_fence_missing';
        }
        if ($type === 'savepoint' && ($operation !== 'release_savepoint' || ($receipt['savepoint_released'] ?? null) !== true)) {
            $reasons[] = 'next_source_savepoint_release_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'receipt_type' => $type,
            'operation' => $operation,
            'path' => $path,
            'pages' => $pages,
            'frames' => $frames,
            'reader_names' => $rowReaders,
            'accepted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'next_source_receipt_matches_checkpoint_handoff' : implode('|', $reasons),
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

        foreach (['write_database_pages', 'sync_wal_frames', 'ack_readers', 'fence_hot_journal', 'release_savepoint'] as $operation) {
            if (!array_key_exists($operation, $positions)) {
                return false;
            }
        }

        return $positions['write_database_pages'] < $positions['sync_wal_frames']
            && $positions['sync_wal_frames'] < $positions['ack_readers']
            && $positions['ack_readers'] < $positions['fence_hot_journal']
            && $positions['fence_hot_journal'] < $positions['release_savepoint'];
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
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return self::optionalPositiveIntSet($value, $label);
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
        if (!is_string($value) || !in_array($value, ['database', 'wal', 'readers', 'journal', 'savepoint'], true)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    private static function operation(mixed $value, string $label): string
    {
        if (!is_string($value) || !in_array($value, ['write_database_pages', 'sync_wal_frames', 'ack_readers', 'fence_hot_journal', 'release_savepoint'], true)) {
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
