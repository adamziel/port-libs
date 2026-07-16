<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteScopedKeyValueWalPlan
{
    /**
     * @param list<array{tenant_id?:int,scope:string,setting_id?:int,key_name:string,key_value:string,load_policy?:string}> $currentRows
     * @param list<array{tenant_id?:int,scope:string,key_name:string,key_value:string,load_policy?:string}> $importRows
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function currentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $currentRows,
        array $importRows,
        array $pageNumbers,
        int $firstSettingPageNumber = 2,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite Application scoped settings WAL import requires a database path');
        }
        if ($importRows === []) {
            throw new \InvalidArgumentException('SQLite Application scoped settings WAL import requires imported rows');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite Application scoped settings WAL import requires current/next page numbers');
        }
        if ($firstSettingPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite Application scoped settings pages must start after page one');
        }
        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite Application scoped settings WAL import requires a concrete WAL page size');
        }

        $current = self::normalizeRows($currentRows, true);
        $next = $current;
        $inserted = [];
        $updated = [];

        foreach (self::normalizeRows($importRows, false) as $key => $row) {
            if (isset($next[$key])) {
                $updated[] = $key;
                $row['setting_id'] = $next[$key]['setting_id'];
            } else {
                $inserted[] = $key;
                $row['setting_id'] = self::nextKeyValueIdForTable($next, $row['table']);
            }
            $next[$key] = $row;
        }

        ksort($next, SORT_STRING);
        $pageMap = self::pageMap($next, $firstSettingPageNumber);
        $tablePages = self::tablePageMap($pageMap);
        $indexPages = self::loadPolicyIndexPageMap($tablePages);
        $databasePageCount = max([...array_values($pageMap), ...array_values($indexPages)]);

        $pages = [];
        foreach ($next as $key => $row) {
            $pages[$pageMap[$key]] = self::rowPage($row, $pageMap[$key], $pageSize);
        }
        foreach ($indexPages as $table => $pageNumber) {
            $pages[$pageNumber] = self::loadPolicyIndexPage($next, $table, $pageNumber, $pageSize);
        }
        ksort($pages, SORT_NUMERIC);

        $append = SQLiteWalAppendPlan::appendTransactions($wal, $databasePath, [[
            'pages' => $pages,
            'database_page_count' => $databasePageCount,
            'commit' => true,
        ]]);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $pageSize, true);
        $currentEndFrame = $wal->frameCount();
        $nextEndFrame = $append['last_commit_frame'];

        $currentReader = [];
        $nextReader = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite Application scoped settings WAL import pages must be integers');
            }
            $currentReader[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame);
            $nextReader[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame);
        }

        return [
            'status' => 'planned',
            'reason' => 'application_scoped_settings_wal_commit_current_next_visibility',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_rows' => array_values($current),
            'next_rows' => array_values($next),
            'inserted_keys' => $inserted,
            'updated_keys' => $updated,
            'tables' => array_keys($tablePages),
            'table_page_numbers' => $tablePages,
            'load_policy_index_page_numbers' => $indexPages,
            'setting_page_numbers' => $pageMap,
            'load_policy_yes_by_table' => self::loadPolicyNamesByTable($next),
            'database_page_count' => $databasePageCount,
            'current_reader' => $currentReader,
            'next_reader' => $nextReader,
            'current_reader_sources' => self::visibilityColumn($currentReader, 'source'),
            'next_reader_sources' => self::visibilityColumn($nextReader, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($currentReader, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($nextReader, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($currentReader),
            'next_reader_errors' => self::visibilityErrors($nextReader),
            'append' => $append,
            'dependencies' => array_values(array_unique(array_merge(
                $append['dependencies'],
                ['application-scoped-settings-wal-current-next42']
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{key:string,scope:string,tenant_id:int|null,table:string,setting_id:int,key_name:string,key_value:string,load_policy:string}>
     */
    private static function normalizeRows(array $rows, bool $requireIds): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $scope = strtolower(trim((string) ($row['scope'] ?? '')));
            if (!in_array($scope, ['global', 'tenant'], true)) {
                throw new \InvalidArgumentException('SQLite Application scoped setting scope must be global or tenant');
            }
            $tenantId = $scope === 'tenant' ? self::positiveInt($row['tenant_id'] ?? null, 'tenant_id') : null;
            $name = trim((string) ($row['key_name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('SQLite Application scoped key_name must be non-empty');
            }
            $settingId = $row['setting_id'] ?? ($index + 1);
            if ($requireIds && (!is_int($settingId) || $settingId < 1)) {
                throw new \InvalidArgumentException('SQLite Application scoped current rows require positive setting_id values');
            }
            $table = self::tableName($scope, $tenantId);
            $key = $table . ':' . $name;
            if (isset($normalized[$key])) {
                throw new \InvalidArgumentException("Duplicate Application scoped setting row {$key}");
            }
            $normalized[$key] = [
                'key' => $key,
                'scope' => $scope,
                'tenant_id' => $tenantId,
                'table' => $table,
                'setting_id' => is_int($settingId) && $settingId > 0 ? $settingId : 0,
                'key_name' => $name,
                'key_value' => (string) ($row['key_value'] ?? ''),
                'load_policy' => self::normalizeLoadPolicy((string) ($row['load_policy'] ?? 'yes')),
            ];
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("SQLite Application scoped {$label} must be positive");
        }

        return $value;
    }

    private static function tableName(string $scope, ?int $tenantId): string
    {
        return $scope === 'global' ? 'app_tenant_settings' : 'app_tenant_' . $tenantId . '_settings';
    }

    private static function normalizeLoadPolicy(string $loadPolicy): string
    {
        $loadPolicy = strtolower(trim($loadPolicy));

        return in_array($loadPolicy, ['yes', 'on', 'true', '1'], true) ? 'yes' : 'no';
    }

    /**
     * @param array<string,array{table:string,setting_id:int}> $rows
     */
    private static function nextKeyValueIdForTable(array $rows, string $table): int
    {
        $max = 0;
        foreach ($rows as $row) {
            if ($row['table'] === $table) {
                $max = max($max, $row['setting_id']);
            }
        }

        return $max + 1;
    }

    /**
     * @param array<string,array{table:string}> $rows
     * @return array<string,int>
     */
    private static function pageMap(array $rows, int $firstPage): array
    {
        $pages = [];
        $page = $firstPage;
        foreach ($rows as $key => $_row) {
            $pages[$key] = $page++;
        }

        return $pages;
    }

    /**
     * @param array<string,int> $pageMap
     * @return array<string,list<int>>
     */
    private static function tablePageMap(array $pageMap): array
    {
        $tables = [];
        foreach ($pageMap as $key => $pageNumber) {
            [$table] = explode(':', $key, 2);
            $tables[$table][] = $pageNumber;
        }
        ksort($tables, SORT_STRING);

        return $tables;
    }

    /**
     * @param array<string,list<int>> $tablePages
     * @return array<string,int>
     */
    private static function loadPolicyIndexPageMap(array $tablePages): array
    {
        $nextPage = max(array_merge(...array_values($tablePages))) + 1;
        $indexPages = [];
        foreach (array_keys($tablePages) as $table) {
            $indexPages[$table] = $nextPage++;
        }

        return $indexPages;
    }

    /**
     * @param array{key:string,scope:string,tenant_id:int|null,table:string,setting_id:int,key_name:string,key_value:string,load_policy:string} $row
     */
    private static function rowPage(array $row, int $pageNumber, int $pageSize): string
    {
        $json = json_encode([
            'page' => $pageNumber,
            'table' => $row['table'],
            'scope' => $row['scope'],
            'tenant_id' => $row['tenant_id'],
            'setting_id' => $row['setting_id'],
            'key_name' => $row['key_name'],
            'key_value' => $row['key_value'],
            'load_policy' => $row['load_policy'],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode Application scoped setting WAL row');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite Application scoped setting page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{table:string,key_name:string,load_policy:string}> $rows
     */
    private static function loadPolicyIndexPage(array $rows, string $table, int $pageNumber, int $pageSize): string
    {
        $json = json_encode([
            'page' => $pageNumber,
            'index' => $table . '_load_policy',
            'table' => $table,
            'load_policy' => 'yes',
            'key_names' => self::loadPolicyNamesForTable($rows, $table),
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode Application scoped load_policy WAL page');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite Application scoped load_policy page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{table:string,key_name:string,load_policy:string}> $rows
     * @return array<string,list<string>>
     */
    private static function loadPolicyNamesByTable(array $rows): array
    {
        $tables = [];
        foreach ($rows as $row) {
            $tables[$row['table']] = self::loadPolicyNamesForTable($rows, $row['table']);
        }
        ksort($tables, SORT_STRING);

        return $tables;
    }

    /**
     * @param array<string,array{table:string,key_name:string,load_policy:string}> $rows
     * @return list<string>
     */
    private static function loadPolicyNamesForTable(array $rows, string $table): array
    {
        $names = [];
        foreach ($rows as $row) {
            if ($row['table'] === $table && $row['load_policy'] === 'yes') {
                $names[] = $row['key_name'];
            }
        }
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeReaderVisibility(SQLiteWal $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\Throwable $error) {
            $snapshot = $wal->readerSnapshot($databaseBytes, min($snapshotEndFrame ?? $wal->frameCount(), $wal->frameCount()));

            return [
                'page_number' => $pageNumber,
                'source' => 'error',
                'frame_index' => null,
                'database_offset' => null,
                'image' => '',
                'snapshot_end_frame' => $snapshot['end_frame'],
                'snapshot_commit_frame' => $snapshot['commit_frame']?->index,
                'database_page_count' => $snapshot['database_page_count'],
                'error' => $error::class . ': ' . $error->getMessage(),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<mixed>
     */
    private static function visibilityColumn(array $entries, string $column): array
    {
        return array_map(static fn (array $entry): mixed => $entry[$column] ?? null, $entries);
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<string>
     */
    private static function visibilityErrors(array $entries): array
    {
        $errors = [];
        foreach ($entries as $entry) {
            if (isset($entry['error'])) {
                $errors[] = (string) $entry['error'];
            }
        }

        return $errors;
    }
}
