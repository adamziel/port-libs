<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWordPressJsonImportSavepointPlan
{
    /**
     * @param list<array{option_id:int,option_name:string,option_value:mixed,autoload?:string,page_number?:int}> $currentRows
     * @param list<array{option_name:string,function?:string,path:string,value:mixed,page_number?:int,wal_frame_index?:int,statement?:string,on_missing?:string,insert_option_id?:int,insert_autoload?:string,initial_value?:mixed}> $mutations
     * @param array{database_bytes?:string,page_size?:int,savepoint?:string,transaction?:string} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $mutations, array $options = []): array
    {
        $pageSize = (int) ($options['page_size'] ?? 512);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import savepoint page size must be a power of two at least 512');
        }

        $transactionName = (string) ($options['transaction'] ?? 'wordpress_json_import');
        $savepointName = (string) ($options['savepoint'] ?? 'current_json_batch');
        if ($transactionName === '' || $savepointName === '') {
            throw new \InvalidArgumentException('SQLite WordPress JSON import savepoint names must not be empty');
        }

        $rowsByName = [];
        $rowsById = [];
        $pageImages = [];
        $maxPage = 1;
        $maxOptionId = 0;
        foreach ($currentRows as $row) {
            $normalized = self::normalizeRow($row);
            if (isset($rowsByName[$normalized['option_name']])) {
                throw new \InvalidArgumentException("Duplicate current wp_options JSON option_name {$normalized['option_name']}");
            }
            if (isset($rowsById[$normalized['option_id']])) {
                throw new \InvalidArgumentException("Duplicate current wp_options JSON option_id {$normalized['option_id']}");
            }

            $rowsByName[$normalized['option_name']] = $normalized;
            $rowsById[$normalized['option_id']] = $normalized;
            $maxOptionId = max($maxOptionId, $normalized['option_id']);
            $page = $normalized['page_number'];
            $maxPage = max($maxPage, $page);
            $pageImages[$page] ??= self::pageImage($pageSize, $page, $normalized['option_name'] . ':before');
        }

        $databaseBytes = (string) ($options['database_bytes'] ?? self::databaseImage($pageSize, $maxPage, $pageImages));
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import savepoint database image must be aligned to the page size');
        }

        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction($transactionName);
        $savepoints->savepoint($savepointName);

        $applied = [];
        $failed = [];
        $statementPlans = [];
        $nextWalFrame = 1;
        $workingRows = $rowsByName;
        $workingIds = $rowsById;
        $workingDatabase = $databaseBytes;

        foreach ($mutations as $index => $mutation) {
            $statementName = self::statementName($mutation, $index);
            $savepoints->beginStatementJournal($statementName);
            $insertedOptionName = null;

            try {
                $optionName = self::mutationOptionName($mutation);
                if (!isset($workingRows[$optionName])) {
                    $row = self::insertMissingRow($mutation, $optionName, $workingIds, $maxOptionId);
                    $workingRows[$optionName] = $row;
                    $workingIds[$row['option_id']] = $row;
                    $maxOptionId = max($maxOptionId, $row['option_id']);
                    $insertedOptionName = $optionName;
                } else {
                    $row = $workingRows[$optionName];
                }

                $pageNumber = self::mutationPageNumber($mutation, $row);
                $beforeImage = self::pageFromDatabase($workingDatabase, $pageSize, $pageNumber)
                    ?? str_repeat("\0", $pageSize);

                $walFrame = self::mutationWalFrame($mutation, $nextWalFrame);
                $function = strtolower((string) ($mutation['function'] ?? 'json_set'));
                $savepoints->recordStatementPageImageWrite($statementName, $pageNumber, $beforeImage);
                $savepoints->recordStatementWalFrameWrite($statementName, $walFrame, $pageNumber);
                $mutatedValue = SQLiteJsonMutation::mutateSqlFunctionArguments($function, [
                    $row['option_value'],
                    $mutation['path'],
                    $mutation['value'],
                ]);

                $savepoints->recordPageImageWrite($pageNumber, $beforeImage);
                $workingRows[$optionName]['option_value'] = $mutatedValue;
                $workingDatabase = self::writePage(
                    $workingDatabase,
                    $pageSize,
                    $pageNumber,
                    self::pageImage($pageSize, $pageNumber, $optionName . ':after:' . $index)
                );

                $statementPlans[] = $savepoints->statementRollbackPlan($statementName, $pageSize) + [
                    'status' => 'applied',
                    'option_name' => $optionName,
                    'json_function' => $function,
                    'json_path' => $mutation['path'],
                ];
                $applied[] = [
                    'statement' => $statementName,
                    'option_name' => $optionName,
                    'page_number' => $pageNumber,
                    'wal_frame_index' => $walFrame,
                    'json_function' => $function,
                    'json_path' => $mutation['path'],
                    'inserted_option' => $insertedOptionName === $optionName,
                    'option_value' => $mutatedValue,
                ];
            } catch (\Throwable $exception) {
                $workingDatabase = $savepoints->rollbackStatementDatabaseImage($statementName, $workingDatabase, $pageSize);
                $rollback = $savepoints->rollbackStatementOnErrorWithPlan($statementName, $pageSize);
                if ($insertedOptionName !== null && isset($workingRows[$insertedOptionName])) {
                    unset($workingIds[$workingRows[$insertedOptionName]['option_id']]);
                    unset($workingRows[$insertedOptionName]);
                }
                $failed[] = [
                    'statement' => $statementName,
                    'option_name' => isset($mutation['option_name']) && is_string($mutation['option_name']) ? $mutation['option_name'] : null,
                    'error' => $exception->getMessage(),
                    'rollback' => $rollback,
                    'database_restored' => true,
                ];
            }
        }

        $rollbackPlan = $savepoints->rollbackToImagePlan($savepointName, $pageSize);
        $walRollbackPlan = $savepoints->walRollbackToPlan($savepointName);
        $commitPlan = $savepoints->commitPlan();

        return [
            'status' => $failed === [] ? 'ready' : 'partial_rollback',
            'transaction' => $transactionName,
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'applied' => $applied,
            'failed' => $failed,
            'statement_plans' => $statementPlans,
            'final_rows' => array_values($workingRows),
            'database_bytes' => $workingDatabase,
            'database_changed' => $workingDatabase !== $databaseBytes,
            'savepoint_state' => $savepoints->toArray(),
            'statement_journals' => $savepoints->statementJournalState(),
            'rollback_to_savepoint' => $rollbackPlan,
            'wal_rollback_to_savepoint' => $walRollbackPlan,
            'commit' => $commitPlan,
            'dependencies' => [
                'sqlite-json-mutation-current',
                'sqlite-savepoint-statement-journal-current',
                'sqlite-wordpress-json-import-savepoint-current',
                'sqlite-wordpress-json-import-savepoint-insert-current-next48',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{option_id:int,option_name:string,option_value:mixed,autoload:string,page_number:int}
     */
    private static function normalizeRow(array $row): array
    {
        $id = $row['option_id'] ?? null;
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new \InvalidArgumentException('wp_options JSON import option_id must be an integer');
        }
        $id = (int) $id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('wp_options JSON import option_id must be positive');
        }

        $name = $row['option_name'] ?? null;
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('wp_options JSON import option_name must be non-empty text');
        }

        $page = $row['page_number'] ?? (2 + intdiv($id - 1, 64));
        if (!is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('wp_options JSON import page number must be one-based');
        }

        $autoload = $row['autoload'] ?? 'no';
        if (!is_string($autoload)) {
            throw new \InvalidArgumentException('wp_options JSON import autoload must be text');
        }

        return [
            'option_id' => $id,
            'option_name' => $name,
            'option_value' => $row['option_value'] ?? '{}',
            'autoload' => $autoload,
            'page_number' => $page,
        ];
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function statementName(array $mutation, int $index): string
    {
        $statement = $mutation['statement'] ?? ('json_import_' . ($index + 1));
        if (!is_string($statement) || $statement === '') {
            throw new \InvalidArgumentException('SQLite WordPress JSON import statement name must be non-empty text');
        }

        return $statement;
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function mutationOptionName(array $mutation): string
    {
        $optionName = $mutation['option_name'] ?? null;
        if (!is_string($optionName) || $optionName === '') {
            throw new \InvalidArgumentException('SQLite WordPress JSON import mutation requires an option_name');
        }

        return $optionName;
    }

    /**
     * @param array<string,mixed> $mutation
     * @param array<int,array{option_id:int,option_name:string,option_value:mixed,autoload:string,page_number:int}> $rowsById
     * @return array{option_id:int,option_name:string,option_value:mixed,autoload:string,page_number:int}
     */
    private static function insertMissingRow(array $mutation, string $optionName, array $rowsById, int $maxOptionId): array
    {
        $mode = $mutation['on_missing'] ?? null;
        if ($mode !== 'insert') {
            throw new \InvalidArgumentException("SQLite WordPress JSON import option does not exist: {$optionName}");
        }

        $id = $mutation['insert_option_id'] ?? ($maxOptionId + 1);
        if (!is_int($id) || $id <= 0) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import inserted option_id must be positive');
        }
        if (isset($rowsById[$id])) {
            throw new \InvalidArgumentException("SQLite WordPress JSON import inserted option_id already exists: {$id}");
        }

        $autoload = $mutation['insert_autoload'] ?? 'no';
        if (!is_string($autoload)) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import inserted autoload must be text');
        }

        $page = $mutation['page_number'] ?? (2 + intdiv($id - 1, 64));
        if (!is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import inserted page number must be one-based');
        }

        return [
            'option_id' => $id,
            'option_name' => $optionName,
            'option_value' => $mutation['initial_value'] ?? '{}',
            'autoload' => $autoload,
            'page_number' => $page,
        ];
    }

    /**
     * @param array<string,mixed> $mutation
     * @param array{page_number:int} $row
     */
    private static function mutationPageNumber(array $mutation, array $row): int
    {
        $page = $mutation['page_number'] ?? $row['page_number'];
        if (!is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import mutation page number must be one-based');
        }

        return $page;
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function mutationWalFrame(array $mutation, int &$nextWalFrame): int
    {
        $frame = $mutation['wal_frame_index'] ?? $nextWalFrame;
        if (!is_int($frame) || $frame < $nextWalFrame) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import WAL frame indexes must be increasing');
        }
        $nextWalFrame = $frame + 1;

        return $frame;
    }

    /**
     * @param array<int,string> $pageImages
     */
    private static function databaseImage(int $pageSize, int $maxPage, array $pageImages): string
    {
        $database = str_repeat("\0", $pageSize * $maxPage);
        foreach ($pageImages as $pageNumber => $pageImage) {
            $database = self::writePage($database, $pageSize, $pageNumber, $pageImage);
        }

        return $database;
    }

    private static function pageFromDatabase(string $database, int $pageSize, int $pageNumber): ?string
    {
        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset + $pageSize > strlen($database)) {
            return null;
        }

        return substr($database, $offset, $pageSize);
    }

    private static function writePage(string $database, int $pageSize, int $pageNumber, string $page): string
    {
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WordPress JSON import page image does not match page size');
        }

        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset + $pageSize > strlen($database)) {
            $database = str_pad($database, $offset + $pageSize, "\0");
        }

        return substr_replace($database, $page, $offset, $pageSize);
    }

    private static function pageImage(int $pageSize, int $pageNumber, string $label): string
    {
        return str_pad("wp-json-page:{$pageNumber}:{$label}", $pageSize, "\0");
    }
}
