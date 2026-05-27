<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWordPressJsonImportWalSavepointPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array{name?:string,json:mixed,path?:string,release?:bool,on_conflict?:string}> $imports
     * @param array{database_path?:string,journal_mode?:string,page_size?:int,replace_conflicts?:bool,sync_mode?:string} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $imports, array $options = []): array
    {
        if ($imports === []) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import WAL savepoint plan requires at least one import');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/wp-json-import.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $journalMode = strtolower((string) ($options['journal_mode'] ?? 'wal'));
        $syncMode = strtolower((string) ($options['sync_mode'] ?? 'normal'));
        $replaceConflicts = (bool) ($options['replace_conflicts'] ?? true);

        if ($databasePath === '' || $databasePath[0] !== '/' || str_contains($databasePath, "\0") || str_contains($databasePath, '..')) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import WAL savepoint plan requires a safe absolute database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import WAL savepoint page size must be a power of two at least 512');
        }
        if (!in_array($journalMode, ['wal', 'delete', 'truncate', 'persist'], true)) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import WAL savepoint journal mode must be wal, delete, truncate, or persist');
        }
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import WAL savepoint sync mode must be off, normal, or full');
        }

        $visibleRows = self::rowsById($currentRows);
        $releasedRows = $visibleRows;
        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction('wp_json_import');

        $batchPlans = [];
        $releasedNames = [];
        $rolledBackNames = [];
        $walFrames = [];
        $currentWalFrame = 0;
        $dirtyPages = [];

        foreach (array_values($imports) as $index => $import) {
            $name = self::savepointName($import, $index);
            $onConflict = strtolower((string) ($import['on_conflict'] ?? 'rollback'));
            if (!in_array($onConflict, ['rollback', 'abort'], true)) {
                throw new \InvalidArgumentException('SQLite WordPress JSON import conflict action must be rollback or abort');
            }

            $savepoints->savepoint($name);
            $beforeRows = $visibleRows;
            $beforeWalFrame = $currentWalFrame;
            $jsonPlan = self::jsonStageRows($import['json'] ?? null, (string) ($import['path'] ?? '$'));

            if (!$jsonPlan['valid']) {
                if ($onConflict === 'abort') {
                    throw new \LogicException((string) $jsonPlan['error']);
                }

                $rollback = $savepoints->walRollbackToWithPlan($name);
                $rolledBackNames[] = $name;
                $batchPlans[] = self::rolledBackBatch($name, (string) $jsonPlan['error'], $jsonPlan, $rollback, $beforeWalFrame, $currentWalFrame, $beforeRows);
                continue;
            }

            try {
                $stagePlan = SQLiteWordPressImportTransactionPlan::plan(array_values($visibleRows), $jsonPlan['rows'], [
                    'database_path' => $databasePath,
                    'page_size' => $pageSize,
                    'journal_mode' => $journalMode === 'wal' ? 'delete' : $journalMode,
                    'sync_mode' => $syncMode,
                    'replace_conflicts' => $replaceConflicts,
                ]);
            } catch (\LogicException $exception) {
                if ($onConflict === 'abort') {
                    throw $exception;
                }

                $rollback = $savepoints->walRollbackToWithPlan($name);
                $rolledBackNames[] = $name;
                $batchPlans[] = self::rolledBackBatch($name, $exception->getMessage(), $jsonPlan, $rollback, $beforeWalFrame, $currentWalFrame, $beforeRows);
                continue;
            }

            $visibleRows = self::rowsById($stagePlan['final_rows']);
            $batchFrames = [];
            foreach ($stagePlan['dirty_pages'] as $pageNumber) {
                $pageNumber = (int) $pageNumber;
                $currentWalFrame++;
                $commitFrame = $pageNumber === (int) end($stagePlan['dirty_pages']);
                $savepoints->recordPageImageWrite($pageNumber, self::pageImage($name, $pageNumber, $beforeWalFrame));
                $savepoints->recordWalFrameWrite($currentWalFrame, $pageNumber, $commitFrame);
                $dirtyPages[$pageNumber] = true;
                $frame = [
                    'frame_index' => $currentWalFrame,
                    'page_number' => $pageNumber,
                    'commit_frame' => $commitFrame,
                    'savepoint' => $name,
                ];
                $walFrames[] = $frame;
                $batchFrames[] = $frame;
            }

            $shouldRelease = (bool) ($import['release'] ?? true);
            if ($shouldRelease) {
                $savepoints->release($name);
                $releasedRows = $visibleRows;
                $releasedNames[] = $name;
            }

            $batchPlans[] = [
                'name' => $name,
                'status' => $shouldRelease ? 'released' : 'open',
                'error' => null,
                'json' => $jsonPlan,
                'before_option_names' => array_column(array_values($beforeRows), 'option_name'),
                'after_option_names' => array_column(array_values($visibleRows), 'option_name'),
                'updated' => count($stagePlan['updated']),
                'inserted' => count($stagePlan['inserted']),
                'deleted' => count($stagePlan['deleted']),
                'dirty_pages' => $stagePlan['dirty_pages'],
                'wal_start_frame' => $beforeWalFrame,
                'wal_current_frame' => $currentWalFrame,
                'wal_frames' => $batchFrames,
                'released' => $shouldRelease,
            ];
        }

        ksort($dirtyPages);

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'replace_conflicts' => $replaceConflicts,
            'batch_count' => count($batchPlans),
            'released_batches' => $releasedNames,
            'rolled_back_batches' => $rolledBackNames,
            'batches' => $batchPlans,
            'current_rows' => array_values($currentRows),
            'final_rows' => array_values($visibleRows),
            'released_rows' => array_values($releasedRows),
            'final_option_names' => array_column(array_values($visibleRows), 'option_name'),
            'released_option_names' => array_column(array_values($releasedRows), 'option_name'),
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'wal' => [
                'path' => $databasePath . '-wal',
                'frame_count' => count($walFrames),
                'current_frame' => $currentWalFrame,
                'frames' => $walFrames,
                'bytes' => 32 + (count($walFrames) * (24 + $pageSize)),
                'current_next35' => true,
            ],
            'dependencies' => [
                'sqlite-wordpress-json-import-wal-savepoint-current-next35',
                'sqlite-wordpress-import-transaction-current',
                'sqlite-json-extract',
                'sqlite-savepoint-wal-rollback',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $import
     */
    private static function savepointName(array $import, int $index): string
    {
        $name = (string) ($import['name'] ?? 'wp_json_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,option_names:list<string>,row_count:int,error:string|null}
     */
    private static function jsonStageRows(mixed $json, string $path): array
    {
        if (!is_string($json) && !$json instanceof SQLiteBlobValue && !$json instanceof SQLiteJsonSubtypeValue) {
            return self::invalidJsonPlan($path, 'SQLite WordPress JSON import requires text, JSON subtype, or JSONB blob input');
        }

        try {
            $value = $json instanceof SQLiteJsonSubtypeValue ? $json->json : $json;
            if ($value instanceof SQLiteBlobValue) {
                $valid = SQLiteJsonValidity::jsonValid($value, SQLiteJsonValidity::FLAG_STRICT_JSONB | SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB);
            } else {
                $valid = SQLiteJsonValidity::jsonValid((string) $value, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT);
            }
            if ($valid !== true) {
                return self::invalidJsonPlan($path, 'SQLite WordPress JSON import source is malformed JSON');
            }

            $extracted = SQLiteJsonExtract::extract($value, $path);
            if ($extracted === null) {
                return self::invalidJsonPlan($path, 'SQLite WordPress JSON import path did not match any rows');
            }

            $decoded = is_string($extracted)
                ? json_decode($extracted, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR)
                : $extracted;
            $rows = self::normalizeDecodedRows($decoded);

            return [
                'valid' => true,
                'path' => $path,
                'rows' => $rows,
                'option_names' => array_column($rows, 'option_name'),
                'row_count' => count($rows),
                'error' => null,
            ];
        } catch (\Throwable $throwable) {
            return self::invalidJsonPlan($path, $throwable->getMessage());
        }
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,option_names:list<string>,row_count:int,error:string}
     */
    private static function invalidJsonPlan(string $path, string $error): array
    {
        return [
            'valid' => false,
            'path' => $path,
            'rows' => [],
            'option_names' => [],
            'row_count' => 0,
            'error' => $error,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function normalizeDecodedRows(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import path must resolve to an object or array of objects');
        }

        $rows = array_is_list($decoded) ? $decoded : [$decoded];
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite WordPress JSON import rows must be objects');
            }
            $name = $row['option_name'] ?? $row['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SQLite WordPress JSON import row requires option_name');
            }
            $normalized[] = [
                'option_id' => isset($row['option_id']) ? (int) $row['option_id'] : null,
                'option_name' => $name,
                'option_value' => $row['option_value'] ?? $row['value'] ?? '',
                'autoload' => isset($row['autoload']) && is_string($row['autoload']) ? $row['autoload'] : 'no',
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $id = $row['option_id'] ?? null;
            if (!is_int($id)) {
                throw new \InvalidArgumentException('SQLite WordPress JSON import rows require integer option_id values');
            }
            $byId[$id] = $row;
        }
        ksort($byId);

        return $byId;
    }

    /**
     * @param array<string,mixed> $jsonPlan
     * @param array<string,mixed> $rollback
     * @param array<int,array<string,mixed>> $beforeRows
     * @return array<string,mixed>
     */
    private static function rolledBackBatch(string $name, string $error, array $jsonPlan, array $rollback, int $beforeWalFrame, int $currentWalFrame, array $beforeRows): array
    {
        return [
            'name' => $name,
            'status' => 'rolled_back',
            'error' => $error,
            'json' => $jsonPlan,
            'before_option_names' => array_column(array_values($beforeRows), 'option_name'),
            'after_option_names' => array_column(array_values($beforeRows), 'option_name'),
            'updated' => 0,
            'inserted' => 0,
            'deleted' => 0,
            'dirty_pages' => [],
            'wal_start_frame' => $beforeWalFrame,
            'wal_current_frame' => $currentWalFrame,
            'wal_rollback_to_frame' => $rollback['rollback_to_frame'],
            'discarded_wal_frames' => $rollback['discarded_wal_frames'],
            'released' => false,
        ];
    }

    private static function pageImage(string $savepoint, int $pageNumber, int $walFrame): string
    {
        return str_pad($savepoint . ':before:' . $pageNumber . ':wal:' . $walFrame, 512, '.', STR_PAD_RIGHT);
    }
}
