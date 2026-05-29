<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWordPressMultisiteOptionsWalPlan
{
    /**
     * @param list<array{blog_id?:int,scope:string,option_id?:int,option_name:string,option_value:string,autoload?:string}> $currentRows
     * @param list<array{blog_id?:int,scope:string,option_name:string,option_value:string,autoload?:string}> $importRows
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
        int $firstOptionPageNumber = 2,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WordPress multisite options WAL import requires a database path');
        }
        if ($importRows === []) {
            throw new \InvalidArgumentException('SQLite WordPress multisite options WAL import requires imported rows');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WordPress multisite options WAL import requires current/next page numbers');
        }
        if ($firstOptionPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite WordPress multisite options pages must start after page one');
        }
        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WordPress multisite options WAL import requires a concrete WAL page size');
        }

        $current = self::normalizeRows($currentRows, true);
        $next = $current;
        $inserted = [];
        $updated = [];

        foreach (self::normalizeRows($importRows, false) as $key => $row) {
            if (isset($next[$key])) {
                $updated[] = $key;
                $row['option_id'] = $next[$key]['option_id'];
            } else {
                $inserted[] = $key;
                $row['option_id'] = self::nextOptionIdForTable($next, $row['table']);
            }
            $next[$key] = $row;
        }

        ksort($next, SORT_STRING);
        $pageMap = self::pageMap($next, $firstOptionPageNumber);
        $tablePages = self::tablePageMap($pageMap);
        $indexPages = self::autoloadIndexPageMap($tablePages);
        $databasePageCount = max([...array_values($pageMap), ...array_values($indexPages)]);

        $pages = [];
        foreach ($next as $key => $row) {
            $pages[$pageMap[$key]] = self::rowPage($row, $pageMap[$key], $pageSize);
        }
        foreach ($indexPages as $table => $pageNumber) {
            $pages[$pageNumber] = self::autoloadIndexPage($next, $table, $pageNumber, $pageSize);
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
                throw new \InvalidArgumentException('SQLite WordPress multisite options WAL import pages must be integers');
            }
            $currentReader[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame);
            $nextReader[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame);
        }

        return [
            'status' => 'planned',
            'reason' => 'wordpress_multisite_options_wal_commit_current_next_visibility',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_rows' => array_values($current),
            'next_rows' => array_values($next),
            'inserted_keys' => $inserted,
            'updated_keys' => $updated,
            'tables' => array_keys($tablePages),
            'table_page_numbers' => $tablePages,
            'autoload_index_page_numbers' => $indexPages,
            'option_page_numbers' => $pageMap,
            'autoload_yes_by_table' => self::autoloadNamesByTable($next),
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
                ['wordpress-multisite-options-wal-current-next42']
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{key:string,scope:string,blog_id:int|null,table:string,option_id:int,option_name:string,option_value:string,autoload:string}>
     */
    private static function normalizeRows(array $rows, bool $requireIds): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $scope = strtolower(trim((string) ($row['scope'] ?? '')));
            if (!in_array($scope, ['network', 'blog'], true)) {
                throw new \InvalidArgumentException('SQLite WordPress multisite option scope must be network or blog');
            }
            $blogId = $scope === 'blog' ? self::positiveInt($row['blog_id'] ?? null, 'blog_id') : null;
            $name = trim((string) ($row['option_name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('SQLite WordPress multisite option_name must be non-empty');
            }
            $optionId = $row['option_id'] ?? ($index + 1);
            if ($requireIds && (!is_int($optionId) || $optionId < 1)) {
                throw new \InvalidArgumentException('SQLite WordPress multisite current rows require positive option_id values');
            }
            $table = self::tableName($scope, $blogId);
            $key = $table . ':' . $name;
            if (isset($normalized[$key])) {
                throw new \InvalidArgumentException("Duplicate WordPress multisite option row {$key}");
            }
            $normalized[$key] = [
                'key' => $key,
                'scope' => $scope,
                'blog_id' => $blogId,
                'table' => $table,
                'option_id' => is_int($optionId) && $optionId > 0 ? $optionId : 0,
                'option_name' => $name,
                'option_value' => (string) ($row['option_value'] ?? ''),
                'autoload' => self::normalizeAutoload((string) ($row['autoload'] ?? 'yes')),
            ];
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("SQLite WordPress multisite {$label} must be positive");
        }

        return $value;
    }

    private static function tableName(string $scope, ?int $blogId): string
    {
        return $scope === 'network' ? 'wp_sitemeta' : 'wp_' . $blogId . '_options';
    }

    private static function normalizeAutoload(string $autoload): string
    {
        $autoload = strtolower(trim($autoload));

        return in_array($autoload, ['yes', 'on', 'true', '1'], true) ? 'yes' : 'no';
    }

    /**
     * @param array<string,array{table:string,option_id:int}> $rows
     */
    private static function nextOptionIdForTable(array $rows, string $table): int
    {
        $max = 0;
        foreach ($rows as $row) {
            if ($row['table'] === $table) {
                $max = max($max, $row['option_id']);
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
    private static function autoloadIndexPageMap(array $tablePages): array
    {
        $nextPage = max(array_merge(...array_values($tablePages))) + 1;
        $indexPages = [];
        foreach (array_keys($tablePages) as $table) {
            $indexPages[$table] = $nextPage++;
        }

        return $indexPages;
    }

    /**
     * @param array{key:string,scope:string,blog_id:int|null,table:string,option_id:int,option_name:string,option_value:string,autoload:string} $row
     */
    private static function rowPage(array $row, int $pageNumber, int $pageSize): string
    {
        $json = json_encode([
            'page' => $pageNumber,
            'table' => $row['table'],
            'scope' => $row['scope'],
            'blog_id' => $row['blog_id'],
            'option_id' => $row['option_id'],
            'option_name' => $row['option_name'],
            'option_value' => $row['option_value'],
            'autoload' => $row['autoload'],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode WordPress multisite option WAL row');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite WordPress multisite option page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{table:string,option_name:string,autoload:string}> $rows
     */
    private static function autoloadIndexPage(array $rows, string $table, int $pageNumber, int $pageSize): string
    {
        $json = json_encode([
            'page' => $pageNumber,
            'index' => $table . '_autoload',
            'table' => $table,
            'autoload' => 'yes',
            'option_names' => self::autoloadNamesForTable($rows, $table),
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode WordPress multisite autoload WAL page');
        }
        if (strlen($json) > $pageSize) {
            throw new \InvalidArgumentException('SQLite WordPress multisite autoload page exceeds page size');
        }

        return str_pad($json, $pageSize, "\0");
    }

    /**
     * @param array<string,array{table:string,option_name:string,autoload:string}> $rows
     * @return array<string,list<string>>
     */
    private static function autoloadNamesByTable(array $rows): array
    {
        $tables = [];
        foreach ($rows as $row) {
            $tables[$row['table']] = self::autoloadNamesForTable($rows, $row['table']);
        }
        ksort($tables, SORT_STRING);

        return $tables;
    }

    /**
     * @param array<string,array{table:string,option_name:string,autoload:string}> $rows
     * @return list<string>
     */
    private static function autoloadNamesForTable(array $rows, string $table): array
    {
        $names = [];
        foreach ($rows as $row) {
            if ($row['table'] === $table && $row['autoload'] === 'yes') {
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
