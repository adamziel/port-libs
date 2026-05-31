<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonImportSavepointPlan
{
    /**
     * @param list<array{setting_id:int,key_name:string,key_value:mixed,load_policy?:string,page_number?:int,tenant_id?:int}> $currentRows
     * @param list<array{key_name:string,function?:string,path:string,value:mixed,page_number?:int,wal_frame_index?:int,statement?:string,on_missing?:string,insert_setting_id?:int,insert_load_policy?:string,initial_value?:mixed,tenant_id?:int}> $mutations
     * @param array{database_bytes?:string,page_size?:int,savepoint?:string,transaction?:string,pre_savepoint_wal_pages?:list<int>} $keys
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $mutations, array $keys = []): array
    {
        $pageSize = (int) ($keys['page_size'] ?? 512);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import savepoint page size must be a power of two at least 512');
        }

        $transactionName = (string) ($keys['transaction'] ?? 'application_json_import');
        $savepointName = (string) ($keys['savepoint'] ?? 'current_json_batch');
        if ($transactionName === '' || $savepointName === '') {
            throw new \InvalidArgumentException('SQLite Application JSON import savepoint names must not be empty');
        }

        $rowsByKey = [];
        $rowsById = [];
        $pageImages = [];
        $maxPage = 1;
        $maxSettingId = 0;
        $hasMultitenantRows = false;
        foreach ($currentRows as $row) {
            $normalized = self::normalizeRow($row);
            $hasMultitenantRows = $hasMultitenantRows || $normalized['tenant_id'] !== null;
            $rowKey = self::rowKey($normalized);
            if (isset($rowsByKey[$rowKey])) {
                throw new \InvalidArgumentException("Duplicate current app_settings JSON key key {$rowKey}");
            }
            if (isset($rowsById[$normalized['setting_id']])) {
                throw new \InvalidArgumentException("Duplicate current app_settings JSON setting_id {$normalized['setting_id']}");
            }

            $rowsByKey[$rowKey] = $normalized;
            $rowsById[$normalized['setting_id']] = $normalized;
            $maxSettingId = max($maxSettingId, $normalized['setting_id']);
            $page = $normalized['page_number'];
            $maxPage = max($maxPage, $page);
            $pageImages[$page] ??= self::pageImage($pageSize, $page, self::rowLabel($normalized) . ':before');
        }

        $databaseBytes = (string) ($keys['database_bytes'] ?? self::databaseImage($pageSize, $maxPage, $pageImages));
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import savepoint database image must be aligned to the page size');
        }

        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction($transactionName);
        $preSavepointWalPages = $keys['pre_savepoint_wal_pages'] ?? [];
        if (!is_array($preSavepointWalPages)) {
            throw new \InvalidArgumentException('SQLite Application JSON import pre-savepoint WAL pages must be a list');
        }
        $nextWalFrame = 1;
        foreach (array_values($preSavepointWalPages) as $preSavepointPage) {
            if (!is_int($preSavepointPage) || $preSavepointPage < 1) {
                throw new \InvalidArgumentException('SQLite Application JSON import pre-savepoint WAL pages must be one-based integers');
            }
            $savepoints->recordWalFrameWrite($nextWalFrame, $preSavepointPage);
            $nextWalFrame++;
        }
        $savepoints->savepoint($savepointName);

        $applied = [];
        $failed = [];
        $statementPlans = [];
        $workingIds = $rowsById;
        $workingRows = $rowsByKey;
        $workingDatabase = $databaseBytes;

        foreach ($mutations as $index => $mutation) {
            $statementName = self::statementName($mutation, $index);
            $savepoints->beginStatementJournal($statementName);
            $insertedSettingName = null;

            try {
                $keyName = self::mutationKeyName($mutation);
                $rowKey = self::mutationRowKey($mutation, $hasMultitenantRows);
                if (!isset($workingRows[$rowKey])) {
                    $row = self::insertMissingRow($mutation, $keyName, $rowKey, $workingIds, $maxSettingId, $hasMultitenantRows);
                    $workingRows[$rowKey] = $row;
                    $workingIds[$row['setting_id']] = $row;
                    $maxSettingId = max($maxSettingId, $row['setting_id']);
                    $insertedSettingName = $rowKey;
                } else {
                    $row = $workingRows[$rowKey];
                }

                $pageNumber = self::mutationPageNumber($mutation, $row);
                $beforeImage = self::pageFromDatabase($workingDatabase, $pageSize, $pageNumber)
                    ?? self::pageImage($pageSize, $pageNumber, self::rowLabel($row) . ':before');

                $walFrame = self::mutationWalFrame($mutation, $nextWalFrame);
                $function = strtolower((string) ($mutation['function'] ?? 'json_set'));
                $savepoints->recordStatementPageImageWrite($statementName, $pageNumber, $beforeImage);
                $savepoints->recordStatementWalFrameWrite($statementName, $walFrame, $pageNumber);
                $mutatedValue = SQLiteJsonMutation::mutateSqlFunctionArguments($function, [
                    $row['key_value'],
                    $mutation['path'],
                    $mutation['value'],
                ]);

                $savepoints->recordPageImageWrite($pageNumber, $beforeImage);
                $workingRows[$rowKey]['key_value'] = $mutatedValue;
                $workingDatabase = self::writePage(
                    $workingDatabase,
                    $pageSize,
                    $pageNumber,
                    self::pageImage($pageSize, $pageNumber, self::rowLabel($row) . ':after:' . $index)
                );

                $statementPlans[] = $savepoints->statementRollbackPlan($statementName, $pageSize) + [
                    'status' => 'applied',
                    'key_name' => $row['key_name'],
                    'setting_key' => $rowKey,
                    'tenant_id' => $row['tenant_id'],
                    'json_function' => $function,
                    'json_path' => $mutation['path'],
                ];
                $applied[] = [
                    'statement' => $statementName,
                    'key_name' => $row['key_name'],
                    'setting_key' => $rowKey,
                    'tenant_id' => $row['tenant_id'],
                    'page_number' => $pageNumber,
                    'wal_frame_index' => $walFrame,
                    'json_function' => $function,
                    'json_path' => $mutation['path'],
                    'inserted_setting' => $insertedSettingName === $rowKey,
                    'key_value' => $mutatedValue,
                ];
            } catch (\Throwable $exception) {
                $workingDatabase = $savepoints->rollbackStatementDatabaseImage($statementName, $workingDatabase, $pageSize);
                $rollback = $savepoints->rollbackStatementOnErrorWithPlan($statementName, $pageSize);
                if ($insertedSettingName !== null && isset($workingRows[$insertedSettingName])) {
                    unset($workingIds[$workingRows[$insertedSettingName]['setting_id']]);
                    unset($workingRows[$insertedSettingName]);
                }
                $failed[] = [
                    'statement' => $statementName,
                    'key_name' => isset($mutation['key_name']) && is_string($mutation['key_name']) ? $mutation['key_name'] : null,
                    'setting_key' => self::failedMutationRowKey($mutation, $hasMultitenantRows),
                    'tenant_id' => self::mutationTenantIdOrNull($mutation),
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
                $hasMultitenantRows ? 'sqlite-application-multitenant-json-import-current' : 'sqlite-application-json-import-savepoint-current',
                'sqlite-application-json-import-savepoint-insert-current-next48',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{setting_id:int,key_name:string,key_value:mixed,load_policy:string,page_number:int,tenant_id:?int}
     */
    private static function normalizeRow(array $row): array
    {
        $id = $row['setting_id'] ?? null;
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new \InvalidArgumentException('app_settings JSON import setting_id must be an integer');
        }
        $id = (int) $id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('app_settings JSON import setting_id must be positive');
        }

        $name = $row['key_name'] ?? null;
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('app_settings JSON import key_name must be non-empty text');
        }

        $page = $row['page_number'] ?? (2 + intdiv($id - 1, 64));
        if (!is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('app_settings JSON import page number must be one-based');
        }

        $load_policy = $row['load_policy'] ?? 'no';
        if (!is_string($load_policy)) {
            throw new \InvalidArgumentException('app_settings JSON import load_policy must be text');
        }

        $tenantId = self::tenantIdOrNull($row['tenant_id'] ?? null, 'app_settings JSON import tenant_id');

        return [
            'setting_id' => $id,
            'key_name' => $name,
            'key_value' => $row['key_value'] ?? '{}',
            'load_policy' => $load_policy,
            'page_number' => $page,
            'tenant_id' => $tenantId,
        ];
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function statementName(array $mutation, int $index): string
    {
        $statement = $mutation['statement'] ?? ('json_import_' . ($index + 1));
        if (!is_string($statement) || $statement === '') {
            throw new \InvalidArgumentException('SQLite Application JSON import statement name must be non-empty text');
        }

        return $statement;
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function mutationKeyName(array $mutation): string
    {
        $keyName = $mutation['key_name'] ?? null;
        if (!is_string($keyName) || $keyName === '') {
            throw new \InvalidArgumentException('SQLite Application JSON import mutation requires an key_name');
        }

        return $keyName;
    }

    /**
     * @param array<string,mixed> $mutation
     * @param array<int,array{setting_id:int,key_name:string,key_value:mixed,load_policy:string,page_number:int,tenant_id:?int}> $rowsById
     * @return array{setting_id:int,key_name:string,key_value:mixed,load_policy:string,page_number:int,tenant_id:?int}
     */
    private static function insertMissingRow(array $mutation, string $keyName, string $rowKey, array $rowsById, int $maxSettingId, bool $hasMultitenantRows): array
    {
        $mode = $mutation['on_missing'] ?? null;
        if ($mode !== 'insert') {
            throw new \InvalidArgumentException("SQLite Application JSON import key does not exist: {$rowKey}");
        }

        $id = $mutation['insert_setting_id'] ?? ($maxSettingId + 1);
        if (!is_int($id) || $id <= 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import inserted setting_id must be positive');
        }
        if (isset($rowsById[$id])) {
            throw new \InvalidArgumentException("SQLite Application JSON import inserted setting_id already exists: {$id}");
        }

        $load_policy = $mutation['insert_load_policy'] ?? 'no';
        if (!is_string($load_policy)) {
            throw new \InvalidArgumentException('SQLite Application JSON import inserted load_policy must be text');
        }

        $page = $mutation['page_number'] ?? (2 + intdiv($id - 1, 64));
        if (!is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON import inserted page number must be one-based');
        }

        return [
            'setting_id' => $id,
            'key_name' => $keyName,
            'key_value' => $mutation['initial_value'] ?? '{}',
            'load_policy' => $load_policy,
            'page_number' => $page,
            'tenant_id' => $hasMultitenantRows ? self::mutationTenantIdOrNull($mutation) : null,
        ];
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function mutationRowKey(array $mutation, bool $hasMultitenantRows): string
    {
        $keyName = self::mutationKeyName($mutation);
        if (!$hasMultitenantRows) {
            return $keyName;
        }

        $tenantId = self::tenantIdOrNull($mutation['tenant_id'] ?? null, 'app_settings JSON import mutation tenant_id');
        if ($tenantId === null) {
            throw new \InvalidArgumentException('SQLite Application multitenant JSON import mutation requires tenant_id');
        }

        return self::key($tenantId, $keyName);
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function failedMutationRowKey(array $mutation, bool $hasMultitenantRows): ?string
    {
        if (!isset($mutation['key_name']) || !is_string($mutation['key_name']) || $mutation['key_name'] === '') {
            return null;
        }
        if (!$hasMultitenantRows) {
            return $mutation['key_name'];
        }

        $tenantId = self::mutationTenantIdOrNull($mutation);

        return $tenantId === null ? null : self::key($tenantId, $mutation['key_name']);
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private static function mutationTenantIdOrNull(array $mutation): ?int
    {
        return self::tenantIdOrNull($mutation['tenant_id'] ?? null, 'app_settings JSON import mutation tenant_id');
    }

    /**
     * @param array{tenant_id:?int,key_name:string} $row
     */
    private static function rowKey(array $row): string
    {
        return $row['tenant_id'] === null ? $row['key_name'] : self::key($row['tenant_id'], $row['key_name']);
    }

    /**
     * @param array{tenant_id:?int,key_name:string} $row
     */
    private static function rowLabel(array $row): string
    {
        return $row['tenant_id'] === null ? $row['key_name'] : 'tenant' . $row['tenant_id'] . ':' . $row['key_name'];
    }

    private static function key(int $tenantId, string $keyName): string
    {
        return $tenantId . ':' . $keyName;
    }

    private static function tenantIdOrNull(mixed $value, string $label): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException($label . ' must be a positive integer');
        }
        $tenantId = (int) $value;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException($label . ' must be a positive integer');
        }

        return $tenantId;
    }

    /**
     * @param array<string,mixed> $mutation
     * @param array{page_number:int} $row
     */
    private static function mutationPageNumber(array $mutation, array $row): int
    {
        $page = $mutation['page_number'] ?? $row['page_number'];
        if (!is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON import mutation page number must be one-based');
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
            throw new \InvalidArgumentException('SQLite Application JSON import WAL frame indexes must be increasing');
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
            throw new \InvalidArgumentException('SQLite Application JSON import page image does not match page size');
        }

        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset + $pageSize > strlen($database)) {
            $database = str_pad($database, $offset + $pageSize, "\0");
        }

        return substr_replace($database, $page, $offset, $pageSize);
    }

    private static function pageImage(int $pageSize, int $pageNumber, string $label): string
    {
        return str_pad("app-json-page:{$pageNumber}:{$label}", $pageSize, "\0");
    }
}
