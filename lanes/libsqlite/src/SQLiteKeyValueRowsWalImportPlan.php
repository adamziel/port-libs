<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteKeyValueRowsWalImportPlan
{
    /**
     * @param list<array{setting_id?:int,key_name:string,key_value:string,load_policy?:string}> $currentRows
     * @param list<array{setting_id?:int,key_name:string,key_value:string,load_policy?:string}> $importRows
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,wal_path:string,current_rows:list<array<string,mixed>>,next_rows:list<array<string,mixed>>,inserted_key_names:list<string>,updated_key_names:list<string>,deleted_key_names:list<string>,load_policy_yes_key_names:list<string>,setting_page_numbers:array<string,int>,database_page_count:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,append:array<string,mixed>,dependencies:list<string>}
     */
    public static function currentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $currentRows,
        array $importRows,
        array $pageNumbers,
        int $firstSettingPageNumber = 2,
        ?int $loadPolicyIndexPageNumber = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite Application settings WAL import requires a database path');
        }
        if ($importRows === []) {
            throw new \InvalidArgumentException('SQLite Application settings WAL import requires at least one imported row');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite Application settings WAL import current/next requires at least one page number');
        }
        if ($firstSettingPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite Application settings WAL import setting pages must start after page one');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite Application settings WAL import requires a concrete WAL page size');
        }

        $normalizedCurrent = self::normalizeRows($currentRows, true);
        $nextRows = $normalizedCurrent;
        $inserted = [];
        $updated = [];

        foreach (self::normalizeRows($importRows, false) as $row) {
            $name = (string) $row['key_name'];
            if (array_key_exists($name, $nextRows)) {
                $updated[$name] = true;
                $row['setting_id'] = $nextRows[$name]['setting_id'];
            } else {
                $inserted[$name] = true;
                $row['setting_id'] = self::nextKeyValueId($nextRows);
            }
            $nextRows[$name] = $row;
        }

        ksort($nextRows, SORT_STRING);
        $keyValuePageNumbers = self::keyValuePageNumbers($nextRows, $firstSettingPageNumber);
        $loadPolicyIndexPageNumber ??= $firstSettingPageNumber + count($keyValuePageNumbers);
        $databasePageCount = max([$loadPolicyIndexPageNumber, ...array_values($keyValuePageNumbers)]);

        $pages = [];
        foreach ($nextRows as $name => $row) {
            $pages[$keyValuePageNumbers[$name]] = self::rowPage($row, $keyValuePageNumbers[$name], $pageSize);
        }
        $pages[$loadPolicyIndexPageNumber] = self::loadPolicyIndexPage($nextRows, $loadPolicyIndexPageNumber, $pageSize);
        ksort($pages, SORT_NUMERIC);

        $append = SQLiteWalAppendPlan::appendTransactions($wal, $databasePath, [[
            'pages' => $pages,
            'database_page_count' => $databasePageCount,
            'commit' => true,
        ]]);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $pageSize, true);
        $currentEndFrame = $wal->frameCount();
        $nextEndFrame = $append['last_commit_frame'];

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite Application settings WAL import pages must be integers');
            }
            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame);
        }

        return [
            'status' => 'planned',
            'reason' => 'application_settings_import_wal_commit_current_next_visibility',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_rows' => array_values($normalizedCurrent),
            'next_rows' => array_values($nextRows),
            'inserted_key_names' => array_keys($inserted),
            'updated_key_names' => array_keys($updated),
            'deleted_key_names' => [],
            'load_policy_yes_key_names' => self::loadPolicyNames($nextRows),
            'setting_page_numbers' => $keyValuePageNumbers,
            'database_page_count' => $databasePageCount,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'append' => $append,
            'dependencies' => array_values(array_unique(array_merge(
                $append['dependencies'],
                ['application-settings-wal-import-current-next']
            ))),
        ];
    }

    /**
     * @param list<array{setting_id?:int,key_name:string,key_value:string,load_policy?:string}> $rows
     * @return array<string,array{setting_id:int,key_name:string,key_value:string,load_policy:string}>
     */
    private static function normalizeRows(array $rows, bool $requireIds): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['key_name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('SQLite Application settings WAL import requires key_name');
            }
            $settingId = $row['setting_id'] ?? ($index + 1);
            if ($requireIds && (!is_int($settingId) || $settingId < 1)) {
                throw new \InvalidArgumentException('SQLite Application settings WAL import requires positive current setting_id values');
            }
            $normalized[$name] = [
                'setting_id' => is_int($settingId) && $settingId > 0 ? $settingId : 0,
                'key_name' => $name,
                'key_value' => (string) ($row['key_value'] ?? ''),
                'load_policy' => self::normalizeLoadPolicy((string) ($row['load_policy'] ?? 'yes')),
            ];
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string,array{setting_id:int}> $rows
     */
    private static function nextKeyValueId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) $row['setting_id']);
        }

        return $max + 1;
    }

    private static function normalizeLoadPolicy(string $loadPolicy): string
    {
        $loadPolicy = strtolower(trim($loadPolicy));

        return in_array($loadPolicy, ['yes', 'on', 'true', '1'], true) ? 'yes' : 'no';
    }

    /**
     * @param array<string,array{setting_id:int,key_name:string,key_value:string,load_policy:string}> $rows
     * @return array<string,int>
     */
    private static function keyValuePageNumbers(array $rows, int $firstSettingPageNumber): array
    {
        $pages = [];
        $page = $firstSettingPageNumber;
        foreach ($rows as $name => $_row) {
            $pages[$name] = $page++;
        }

        return $pages;
    }

    /**
     * @param array{setting_id:int,key_name:string,key_value:string,load_policy:string} $row
     */
    private static function rowPage(array $row, int $pageNumber, int $pageSize): string
    {
        $json = json_encode([
            'page' => $pageNumber,
            'table' => 'app_settings',
            'setting_id' => $row['setting_id'],
            'key_name' => $row['key_name'],
            'key_value' => $row['key_value'],
            'load_policy' => $row['load_policy'],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode Application settings WAL import row');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite Application settings WAL import row page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{key_name:string,load_policy:string}> $rows
     */
    private static function loadPolicyIndexPage(array $rows, int $pageNumber, int $pageSize): string
    {
        $names = self::loadPolicyNames($rows);
        $json = json_encode([
            'page' => $pageNumber,
            'index' => 'app_settings_load_policy',
            'load_policy' => 'yes',
            'key_names' => $names,
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode Application settings WAL import load_policy page');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite Application settings WAL import load_policy page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{key_name:string,load_policy:string}> $rows
     * @return list<string>
     */
    private static function loadPolicyNames(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            if ($row['load_policy'] === 'yes') {
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
                'snapshot_end_frame' => $snapshotEndFrame ?? $wal->frameCount(),
                'snapshot_commit_frame' => $snapshot['commit_frame']?->index,
                'database_page_count' => $snapshot['database_page_count'],
                'error' => $error->getMessage(),
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
