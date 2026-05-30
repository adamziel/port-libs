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
        $databasePath = (string) ($options['database_path'] ?? '/tmp/wp-import-current-next.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $failOnError = (bool) ($options['fail_on_error'] ?? true);
        $statementPrefix = (string) ($options['statement_prefix'] ?? 'wp_import_row');

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
            $nameToId[$row['option_name']] = $id;
            $maxId = max($maxId, $id);
        }

        $yielded = [];
        $applied = [];
        $errors = [];
        $dirtyPages = [];
        $rolledBack = false;
        $nextOptionId = $maxId + 1;

        foreach ($stagedRows as $index => $row) {
            $ordinal = $index + 1;
            $statement = $statementPrefix . '_' . $ordinal;
            $currentOptionId = self::currentOptionId($row, $nameToId);

            try {
                $stage = self::normalizeStagedRow($row);
                $targetId = $stage['option_id'] ?? ($nameToId[$stage['option_name']] ?? null);
                $event = $targetId !== null && isset($finalRows[$targetId]) ? 'update' : 'insert';
                if ($targetId === null) {
                    $targetId = $maxId + 1;
                }
                if ($targetId <= 0) {
                    throw new \InvalidArgumentException('Staged wp_options option_id must be positive when supplied');
                }

                $conflictingId = self::conflictingOptionNameId($finalRows, $stage['option_name'], $targetId);
                if ($conflictingId !== null) {
                    throw new \LogicException("UNIQUE constraint failed: wp_options.option_name ({$stage['option_name']})");
                }

                $before = $finalRows[$targetId] ?? null;
                $after = [
                    'option_id' => $targetId,
                    'option_name' => $stage['option_name'],
                    'option_value' => $stage['option_value'],
                    'autoload' => $stage['autoload'],
                ];
                $finalRows[$targetId] = $after;
                unset($nameToId[$before['option_name'] ?? '']);
                $nameToId[$after['option_name']] = $targetId;
                $maxId = max($maxId, $targetId);
                $nextOptionId = $maxId + 1;
                $pageNumber = self::pageForOptionId($targetId);
                $dirtyPages[$pageNumber] = true;

                $applied[] = [
                    'ordinal' => $ordinal,
                    'statement' => $statement,
                    'event' => $event,
                    'option_id' => $targetId,
                    'option_name' => $after['option_name'],
                    'dirty_page' => $pageNumber,
                ];
                $yielded[] = self::yieldRow($ordinal, $statement, 'applied', $event, $currentOptionId, $nextOptionId, $after, null);
            } catch (\Throwable $throwable) {
                $error = self::wpError($throwable, $ordinal, $statement, $currentOptionId, $nextOptionId);
                $errors[] = $error;
                $yielded[] = self::yieldRow($ordinal, $statement, 'error', 'rollback_statement', $currentOptionId, $nextOptionId, null, $error);
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
                'sqlite-statement-error-wp-error-shape',
                'sqlite-begin-transaction-lock-mode',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{option_id:int,option_name:string,option_value:mixed,autoload:string}>
     */
    private static function normalizeCurrentRows(array $rows): array
    {
        $normalized = [];
        $names = [];
        foreach ($rows as $row) {
            $current = self::normalizeStagedRow($row);
            if (!isset($current['option_id'])) {
                throw new \InvalidArgumentException('Current wp_options rows require option_id');
            }
            $id = $current['option_id'];
            if (isset($normalized[$id])) {
                throw new \InvalidArgumentException("Duplicate current wp_options option_id {$id}");
            }
            if (isset($names[$current['option_name']])) {
                throw new \InvalidArgumentException("Duplicate current wp_options option_name {$current['option_name']}");
            }
            $normalized[$id] = $current;
            $names[$current['option_name']] = true;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{option_id?:int,option_name:string,option_value:mixed,autoload:string}
     */
    private static function normalizeStagedRow(array $row): array
    {
        $normalized = [];
        if (array_key_exists('option_id', $row) && $row['option_id'] !== null) {
            $id = $row['option_id'];
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                throw new \InvalidArgumentException('wp_options option_id must be an integer');
            }
            $id = (int) $id;
            if ($id <= 0) {
                throw new \InvalidArgumentException('wp_options option_id must be positive');
            }
            $normalized['option_id'] = $id;
        }

        $name = $row['option_name'] ?? null;
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('wp_options option_name must be non-empty text');
        }

        $autoload = $row['autoload'] ?? 'no';
        if (!is_string($autoload) || !in_array($autoload, ['yes', 'no', 'auto', 'on', 'off'], true)) {
            throw new \InvalidArgumentException('wp_options autoload must be a supported SQLite import value');
        }

        $normalized['option_name'] = $name;
        $normalized['option_value'] = $row['option_value'] ?? '';
        $normalized['autoload'] = $autoload;

        return $normalized;
    }

    /**
     * @param array<int,array{option_id:int,option_name:string,option_value:mixed,autoload:string}> $rows
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

    /**
     * @param array<string,mixed> $row
     * @param array<string,int> $nameToId
     */
    private static function currentOptionId(array $row, array $nameToId): ?int
    {
        if (isset($row['option_id']) && (is_int($row['option_id']) || (is_string($row['option_id']) && ctype_digit($row['option_id'])))) {
            return (int) $row['option_id'];
        }
        if (isset($row['option_name']) && is_string($row['option_name']) && isset($nameToId[$row['option_name']])) {
            return $nameToId[$row['option_name']];
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $row
     * @param array<string,mixed>|null $error
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, string $statement, string $status, string $event, ?int $currentOptionId, int $nextOptionId, ?array $row, ?array $error): array
    {
        return [
            'ordinal' => $ordinal,
            'statement' => $statement,
            'status' => $status,
            'event' => $event,
            'current_option_id' => $currentOptionId,
            'next_option_id' => $nextOptionId,
            'row' => $row,
            'wp_error' => $error,
        ];
    }

    /**
     * @return array{code:string,message:string,data:array{ordinal:int,statement:string,current_option_id:int|null,next_option_id:int,exception:string,sqlite_abort:string}}
     */
    private static function wpError(\Throwable $throwable, int $ordinal, string $statement, ?int $currentOptionId, int $nextOptionId): array
    {
        return [
            'code' => $throwable instanceof \InvalidArgumentException
                ? 'sqlite_import_error'
                : ($throwable instanceof \LogicException ? 'sqlite_constraint' : 'sqlite_import_error'),
            'message' => $throwable->getMessage(),
            'data' => [
                'ordinal' => $ordinal,
                'statement' => $statement,
                'current_option_id' => $currentOptionId,
                'next_option_id' => $nextOptionId,
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

    private static function pageForOptionId(int $optionId): int
    {
        return 2 + intdiv($optionId - 1, 64);
    }
}
