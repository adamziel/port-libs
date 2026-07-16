<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteImportTransactionErrorYieldPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $stagedRows
     * @param array{begin?:string,database_path?:string,page_size?:int,fail_on_error?:bool,statement_prefix?:string} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $stagedRows, array $options = []): array
    {
        $beginSql = (string) ($options['begin'] ?? 'BEGIN IMMEDIATE');
        $databasePath = (string) ($options['database_path'] ?? '/tmp/app-import-current-next.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $failOnError = (bool) ($options['fail_on_error'] ?? true);
        $statementPrefix = (string) ($options['statement_prefix'] ?? 'app_import_row');

        self::assertSafeDatabasePath($databasePath);
        self::assertPageSize($pageSize);
        if ($statementPrefix === '' || str_contains($statementPrefix, "\0")) {
            throw new \InvalidArgumentException('SQLite Application import transaction error yield statement prefix must be non-empty text');
        }

        $begin = SQLiteTransactionBeginLockPlan::plan($beginSql, journalMode: 'delete');
        if (!$begin['write_lock_acquired']) {
            throw new \InvalidArgumentException('SQLite Application import transaction error yield requires an immediate or exclusive write transaction');
        }

        $originalRows = self::normalizeCurrentRows($currentRows);
        $finalRows = $originalRows;
        $nameToId = [];
        $maxId = 0;
        foreach ($finalRows as $id => $row) {
            $nameToId[$row['key_name']] = $id;
            $maxId = max($maxId, $id);
        }

        $yielded = [];
        $applied = [];
        $errors = [];
        $dirtyPages = [];
        $rolledBack = false;
        $nextKeyValueId = $maxId + 1;

        foreach ($stagedRows as $index => $row) {
            $ordinal = $index + 1;
            $statement = $statementPrefix . '_' . $ordinal;
            $currentKeyValueId = self::currentKeyValueId($row, $nameToId);

            try {
                $stage = self::normalizeStagedRow($row);
                $targetId = $stage['setting_id'] ?? ($nameToId[$stage['key_name']] ?? null);
                $event = $targetId !== null && isset($finalRows[$targetId]) ? 'update' : 'insert';
                if ($targetId === null) {
                    $targetId = $maxId + 1;
                }
                if ($targetId <= 0) {
                    throw new \InvalidArgumentException('Staged app_settings setting_id must be positive when supplied');
                }

                $conflictingId = self::conflictingKeyNameId($finalRows, $stage['key_name'], $targetId);
                if ($conflictingId !== null) {
                    throw new \LogicException("UNIQUE constraint failed: app_settings.key_name ({$stage['key_name']})");
                }

                $before = $finalRows[$targetId] ?? null;
                $after = [
                    'setting_id' => $targetId,
                    'key_name' => $stage['key_name'],
                    'key_value' => $stage['key_value'],
                    'load_policy' => $stage['load_policy'],
                ];
                $finalRows[$targetId] = $after;
                unset($nameToId[$before['key_name'] ?? '']);
                $nameToId[$after['key_name']] = $targetId;
                $maxId = max($maxId, $targetId);
                $nextKeyValueId = $maxId + 1;
                $pageNumber = self::pageForKeyValueId($targetId);
                $dirtyPages[$pageNumber] = true;

                $applied[] = [
                    'ordinal' => $ordinal,
                    'statement' => $statement,
                    'event' => $event,
                    'setting_id' => $targetId,
                    'key_name' => $after['key_name'],
                    'dirty_page' => $pageNumber,
                ];
                $yielded[] = self::yieldRow($ordinal, $statement, 'applied', $event, $currentKeyValueId, $nextKeyValueId, $after, null);
            } catch (\Throwable $throwable) {
                $error = self::sqliteError($throwable, $ordinal, $statement, $currentKeyValueId, $nextKeyValueId);
                $errors[] = $error;
                $yielded[] = self::yieldRow($ordinal, $statement, 'error', 'rollback_statement', $currentKeyValueId, $nextKeyValueId, null, $error);
                if ($failOnError) {
                    $rolledBack = true;
                    $finalRows = $originalRows;
                    $dirtyPages = [];
                    break;
                }
            }
        }

        ksort($finalRows);
        $dirtyPageNumbers = array_map('intval', array_keys($dirtyPages));
        sort($dirtyPageNumbers, SORT_NUMERIC);

        return [
            'status' => $rolledBack ? 'rolled_back' : ($errors === [] ? 'committed' : 'partial_errors'),
            'database_path' => $databasePath,
            'begin' => $begin,
            'page_size' => $pageSize,
            'fail_on_error' => $failOnError,
            'current_count' => count($originalRows),
            'staged_count' => count($stagedRows),
            'applied_count' => $rolledBack ? 0 : count($applied),
            'error_count' => count($errors),
            'yielded' => $yielded,
            'applied' => $rolledBack ? [] : $applied,
            'errors' => $errors,
            'final_rows' => array_values($finalRows),
            'dirty_pages' => $dirtyPageNumbers,
            'rollback' => [
                'transaction_rolled_back' => $rolledBack,
                'statement_rollback_only' => !$rolledBack && $errors !== [],
                'restored_current_rows' => $rolledBack ? count($originalRows) : 0,
                'discarded_applied_rows' => $rolledBack ? count($applied) : 0,
            ],
            'dependencies' => [
                'sqlite-application-import-transaction-error-yield-current-next29',
                'sqlite-statement-error-app-error-shape',
                'sqlite-begin-transaction-lock-mode',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{setting_id:int,key_name:string,key_value:mixed,load_policy:string}>
     */
    private static function normalizeCurrentRows(array $rows): array
    {
        $normalized = [];
        $names = [];
        foreach ($rows as $row) {
            $current = self::normalizeStagedRow($row);
            if (!isset($current['setting_id'])) {
                throw new \InvalidArgumentException('Current app_settings rows require setting_id');
            }
            $id = $current['setting_id'];
            if (isset($normalized[$id])) {
                throw new \InvalidArgumentException("Duplicate current app_settings setting_id {$id}");
            }
            if (isset($names[$current['key_name']])) {
                throw new \InvalidArgumentException("Duplicate current app_settings key_name {$current['key_name']}");
            }
            $normalized[$id] = $current;
            $names[$current['key_name']] = true;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{setting_id?:int,key_name:string,key_value:mixed,load_policy:string}
     */
    private static function normalizeStagedRow(array $row): array
    {
        $normalized = [];
        if (array_key_exists('setting_id', $row) && $row['setting_id'] !== null) {
            $id = $row['setting_id'];
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                throw new \InvalidArgumentException('app_settings setting_id must be an integer');
            }
            $id = (int) $id;
            if ($id <= 0) {
                throw new \InvalidArgumentException('app_settings setting_id must be positive');
            }
            $normalized['setting_id'] = $id;
        }

        $name = $row['key_name'] ?? null;
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('app_settings key_name must be non-empty text');
        }

        $load_policy = $row['load_policy'] ?? 'no';
        if (!is_string($load_policy) || !in_array($load_policy, ['yes', 'no', 'auto', 'on', 'off'], true)) {
            throw new \InvalidArgumentException('app_settings load_policy must be a supported SQLite import value');
        }

        $normalized['key_name'] = $name;
        $normalized['key_value'] = $row['key_value'] ?? '';
        $normalized['load_policy'] = $load_policy;

        return $normalized;
    }

    /**
     * @param array<int,array{setting_id:int,key_name:string,key_value:mixed,load_policy:string}> $rows
     */
    private static function conflictingKeyNameId(array $rows, string $name, int $exceptId): ?int
    {
        foreach ($rows as $id => $row) {
            if ($id !== $exceptId && $row['key_name'] === $name) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,int> $nameToId
     */
    private static function currentKeyValueId(array $row, array $nameToId): ?int
    {
        if (isset($row['setting_id']) && (is_int($row['setting_id']) || (is_string($row['setting_id']) && ctype_digit($row['setting_id'])))) {
            return (int) $row['setting_id'];
        }
        if (isset($row['key_name']) && is_string($row['key_name']) && isset($nameToId[$row['key_name']])) {
            return $nameToId[$row['key_name']];
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $row
     * @param array<string,mixed>|null $error
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, string $statement, string $status, string $event, ?int $currentKeyValueId, int $nextKeyValueId, ?array $row, ?array $error): array
    {
        return [
            'ordinal' => $ordinal,
            'statement' => $statement,
            'status' => $status,
            'event' => $event,
            'current_setting_id' => $currentKeyValueId,
            'next_setting_id' => $nextKeyValueId,
            'row' => $row,
            'error' => $error,
        ];
    }

    /**
     * @return array{code:string,message:string,data:array{ordinal:int,statement:string,current_setting_id:int|null,next_setting_id:int,exception:string,sqlite_abort:string}}
     */
    private static function sqliteError(\Throwable $throwable, int $ordinal, string $statement, ?int $currentKeyValueId, int $nextKeyValueId): array
    {
        return [
            'code' => $throwable instanceof \InvalidArgumentException
                ? 'sqlite_import_error'
                : ($throwable instanceof \LogicException ? 'sqlite_constraint' : 'sqlite_import_error'),
            'message' => $throwable->getMessage(),
            'data' => [
                'ordinal' => $ordinal,
                'statement' => $statement,
                'current_setting_id' => $currentKeyValueId,
                'next_setting_id' => $nextKeyValueId,
                'exception' => $throwable::class,
                'sqlite_abort' => 'statement',
            ],
        ];
    }

    private static function assertSafeDatabasePath(string $databasePath): void
    {
        if ($databasePath === '' || $databasePath[0] !== '/' || str_contains($databasePath, "\0") || str_contains($databasePath, '..')) {
            throw new \InvalidArgumentException('SQLite Application import transaction error yield requires a safe absolute database path');
        }
    }

    private static function assertPageSize(int $pageSize): void
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application import transaction error yield page size must be a power of two at least 512');
        }
    }

    private static function pageForKeyValueId(int $settingId): int
    {
        return 2 + intdiv($settingId - 1, 64);
    }
}
