<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOptionRowsWalImportPlan
{
    /**
     * @param list<array{option_id?:int,option_name:string,option_value:string,autoload?:string}> $currentRows
     * @param list<array{option_id?:int,option_name:string,option_value:string,autoload?:string}> $importRows
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,wal_path:string,current_rows:list<array<string,mixed>>,next_rows:list<array<string,mixed>>,inserted_names:list<string>,updated_names:list<string>,deleted_names:list<string>,autoload_yes_names:list<string>,option_page_numbers:array<string,int>,database_page_count:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,append:array<string,mixed>,dependencies:list<string>}
     */
    public static function currentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $currentRows,
        array $importRows,
        array $pageNumbers,
        int $firstOptionPageNumber = 2,
        ?int $autoloadIndexPageNumber = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite Application options WAL import requires a database path');
        }
        if ($importRows === []) {
            throw new \InvalidArgumentException('SQLite Application options WAL import requires at least one imported row');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite Application options WAL import current/next requires at least one page number');
        }
        if ($firstOptionPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite Application options WAL import option pages must start after page one');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite Application options WAL import requires a concrete WAL page size');
        }

        $normalizedCurrent = self::normalizeRows($currentRows, true);
        $nextRows = $normalizedCurrent;
        $inserted = [];
        $updated = [];

        foreach (self::normalizeRows($importRows, false) as $row) {
            $name = (string) $row['option_name'];
            if (array_key_exists($name, $nextRows)) {
                $updated[$name] = true;
                $row['option_id'] = $nextRows[$name]['option_id'];
            } else {
                $inserted[$name] = true;
                $row['option_id'] = self::nextOptionId($nextRows);
            }
            $nextRows[$name] = $row;
        }

        ksort($nextRows, SORT_STRING);
        $optionPageNumbers = self::optionPageNumbers($nextRows, $firstOptionPageNumber);
        $autoloadIndexPageNumber ??= $firstOptionPageNumber + count($optionPageNumbers);
        $databasePageCount = max([$autoloadIndexPageNumber, ...array_values($optionPageNumbers)]);

        $pages = [];
        foreach ($nextRows as $name => $row) {
            $pages[$optionPageNumbers[$name]] = self::rowPage($row, $optionPageNumbers[$name], $pageSize);
        }
        $pages[$autoloadIndexPageNumber] = self::autoloadIndexPage($nextRows, $autoloadIndexPageNumber, $pageSize);
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
                throw new \InvalidArgumentException('SQLite Application options WAL import pages must be integers');
            }
            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame);
        }

        return [
            'status' => 'planned',
            'reason' => 'application_options_import_wal_commit_current_next_visibility',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_rows' => array_values($normalizedCurrent),
            'next_rows' => array_values($nextRows),
            'inserted_names' => array_keys($inserted),
            'updated_names' => array_keys($updated),
            'deleted_names' => [],
            'autoload_yes_names' => self::autoloadNames($nextRows),
            'option_page_numbers' => $optionPageNumbers,
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
                ['application-options-wal-import-current-next']
            ))),
        ];
    }

    /**
     * @param list<array{option_id?:int,option_name:string,option_value:string,autoload?:string}> $rows
     * @return array<string,array{option_id:int,option_name:string,option_value:string,autoload:string}>
     */
    private static function normalizeRows(array $rows, bool $requireIds): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['option_name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('SQLite Application options WAL import requires option_name');
            }
            $optionId = $row['option_id'] ?? ($index + 1);
            if ($requireIds && (!is_int($optionId) || $optionId < 1)) {
                throw new \InvalidArgumentException('SQLite Application options WAL import requires positive current option_id values');
            }
            $normalized[$name] = [
                'option_id' => is_int($optionId) && $optionId > 0 ? $optionId : 0,
                'option_name' => $name,
                'option_value' => (string) ($row['option_value'] ?? ''),
                'autoload' => self::normalizeAutoload((string) ($row['autoload'] ?? 'yes')),
            ];
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string,array{option_id:int}> $rows
     */
    private static function nextOptionId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) $row['option_id']);
        }

        return $max + 1;
    }

    private static function normalizeAutoload(string $autoload): string
    {
        $autoload = strtolower(trim($autoload));

        return in_array($autoload, ['yes', 'on', 'true', '1'], true) ? 'yes' : 'no';
    }

    /**
     * @param array<string,array{option_id:int,option_name:string,option_value:string,autoload:string}> $rows
     * @return array<string,int>
     */
    private static function optionPageNumbers(array $rows, int $firstOptionPageNumber): array
    {
        $pages = [];
        $page = $firstOptionPageNumber;
        foreach ($rows as $name => $_row) {
            $pages[$name] = $page++;
        }

        return $pages;
    }

    /**
     * @param array{option_id:int,option_name:string,option_value:string,autoload:string} $row
     */
    private static function rowPage(array $row, int $pageNumber, int $pageSize): string
    {
        $json = json_encode([
            'page' => $pageNumber,
            'table' => 'wp_options',
            'option_id' => $row['option_id'],
            'option_name' => $row['option_name'],
            'option_value' => $row['option_value'],
            'autoload' => $row['autoload'],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode Application options WAL import row');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite Application options WAL import row page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{option_name:string,autoload:string}> $rows
     */
    private static function autoloadIndexPage(array $rows, int $pageNumber, int $pageSize): string
    {
        $names = self::autoloadNames($rows);
        $json = json_encode([
            'page' => $pageNumber,
            'index' => 'wp_options_autoload',
            'autoload' => 'yes',
            'option_names' => $names,
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode Application options WAL import autoload page');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite Application options WAL import autoload page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{option_name:string,autoload:string}> $rows
     * @return list<string>
     */
    private static function autoloadNames(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            if ($row['autoload'] === 'yes') {
                $names[] = $row['option_name'];
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
