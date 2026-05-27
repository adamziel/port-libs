<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTempStoreSorterBTreePlan
{
    /**
     * @param list<array<string, mixed>> $inputRows
     * @param list<array<string, mixed>> $sortedRows
     * @param list<array{column:string,direction:string,collation:string}> $sortTerms
     * @param array<int, string> $pageImages
     * @param list<array{page_number:int,first_key:mixed,last_key:mixed,cell_count:int,bytes:int}> $runs
     * @param list<array{key:list<mixed>,sequence:int,row:array<string, mixed>}> $sortRecords
     * @param list<array<string, mixed>> $yieldedRows
     * @param list<string> $distinctColumns
     */
    public function __construct(
        public readonly array $inputRows,
        public readonly array $sortedRows,
        public readonly array $sortTerms,
        public readonly int $pageSize,
        public readonly int $memoryThresholdBytes,
        public readonly bool $spilledToTempBTree,
        public readonly array $pageImages,
        public readonly array $runs,
        public readonly array $sortRecords,
        public readonly int $estimatedMemoryBytes,
        public readonly array $yieldedRows = [],
        public readonly array $distinctColumns = [],
        public readonly ?int $limit = null,
        public readonly int $offset = 0,
        public readonly int $distinctRowsSeen = 0,
        public readonly int $duplicateRowsSkipped = 0,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array{column:string,direction?:string,collation?:string}> $sortTerms
     */
    public static function forRows(
        array $rows,
        array $sortTerms,
        int $pageSize = 512,
        int $memoryThresholdBytes = 512,
        int $firstTempPageNumber = 2,
    ): self {
        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite temp sorter requires at least one row');
        }
        if ($sortTerms === []) {
            throw new \InvalidArgumentException('SQLite temp sorter requires at least one sort term');
        }
        if ($pageSize < 512 || $pageSize > 65536) {
            throw new \InvalidArgumentException('SQLite temp sorter page size is outside supported bounds');
        }
        if ($memoryThresholdBytes < 1) {
            throw new \InvalidArgumentException('SQLite temp sorter memory threshold must be positive');
        }
        if ($firstTempPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite temp sorter first page number must leave page 1 for the temp database header');
        }

        $terms = self::normalizeSortTerms($sortTerms);
        $records = [];
        foreach ($rows as $sequence => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite temp sorter rows must be arrays');
            }
            $key = [];
            foreach ($terms as $term) {
                if (!array_key_exists($term['column'], $row)) {
                    throw new \InvalidArgumentException("SQLite temp sorter row is missing sort column: {$term['column']}");
                }
                $key[] = $row[$term['column']];
            }
            $records[] = ['key' => $key, 'sequence' => $sequence, 'row' => $row];
        }

        usort(
            $records,
            static fn (array $left, array $right): int => self::compareRecords($left, $right, $terms),
        );

        $sortedRows = array_map(static fn (array $record): array => $record['row'], $records);
        $estimatedMemoryBytes = self::estimatedMemoryBytes($records);
        $spilled = $estimatedMemoryBytes > $memoryThresholdBytes;
        $pageImages = [];
        $runs = [];

        if ($spilled) {
            [$pageImages, $runs] = self::assembleRuns($records, $terms, $pageSize, $firstTempPageNumber);
        }

        return new self($rows, $sortedRows, $terms, $pageSize, $memoryThresholdBytes, $spilled, $pageImages, $runs, $records, $estimatedMemoryBytes);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array{column:string,direction?:string,collation?:string}> $sortTerms
     * @param list<string> $distinctColumns
     */
    public static function forDistinctLimitRows(
        array $rows,
        array $sortTerms,
        array $distinctColumns,
        ?int $limit,
        int $offset = 0,
        int $pageSize = 512,
        int $memoryThresholdBytes = 512,
        int $firstTempPageNumber = 2,
    ): self {
        if ($distinctColumns === []) {
            throw new \InvalidArgumentException('SQLite temp sorter DISTINCT yield requires at least one distinct column');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite temp sorter DISTINCT yield OFFSET must be non-negative');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite temp sorter DISTINCT yield LIMIT must be non-negative or NULL');
        }
        foreach ($distinctColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite temp sorter DISTINCT yield columns must be non-empty strings');
            }
        }

        $plan = self::forRows($rows, $sortTerms, $pageSize, $memoryThresholdBytes, $firstTempPageNumber);
        $seen = [];
        $yielded = [];
        $distinctSeen = 0;
        $duplicateRowsSkipped = 0;

        foreach ($plan->sortRecords as $record) {
            if ($limit !== null && count($yielded) >= $limit) {
                break;
            }

            $parts = [];
            foreach ($distinctColumns as $column) {
                if (!array_key_exists($column, $record['row'])) {
                    throw new \InvalidArgumentException("SQLite temp sorter DISTINCT yield row is missing column: {$column}");
                }
                $parts[] = self::valueKey($record['row'][$column]);
            }

            $key = implode("\0", $parts);
            if (isset($seen[$key])) {
                $duplicateRowsSkipped++;
                continue;
            }
            $seen[$key] = true;
            $distinctSeen++;

            if ($distinctSeen <= $offset) {
                continue;
            }
            $yielded[] = $record['row'];
        }

        return new self(
            $plan->inputRows,
            $plan->sortedRows,
            $plan->sortTerms,
            $plan->pageSize,
            $plan->memoryThresholdBytes,
            $plan->spilledToTempBTree,
            $plan->pageImages,
            $plan->runs,
            $plan->sortRecords,
            $plan->estimatedMemoryBytes,
            $yielded,
            array_values($distinctColumns),
            $limit,
            $offset,
            $distinctSeen,
            $duplicateRowsSkipped,
        );
    }

    /**
     * @return array{action:string,input_rows:int,sorted_rows:int,sort_terms:list<array{column:string,direction:string,collation:string}>,memory_threshold_bytes:int,estimated_memory_bytes:int,spilled_to_temp_btree:bool,temp_page_numbers:list<int>,runs:list<array{page_number:int,first_key:mixed,last_key:mixed,cell_count:int,bytes:int}>,distinct_columns:list<string>,limit:int|null,offset:int,distinct_rows_seen:int,duplicate_rows_skipped:int,yielded_rows:int}
     */
    public function toArray(): array
    {
        return [
            'action' => 'temp-store-sorter-btree',
            'input_rows' => count($this->inputRows),
            'sorted_rows' => count($this->sortedRows),
            'sort_terms' => $this->sortTerms,
            'memory_threshold_bytes' => $this->memoryThresholdBytes,
            'estimated_memory_bytes' => $this->estimatedMemoryBytes,
            'spilled_to_temp_btree' => $this->spilledToTempBTree,
            'temp_page_numbers' => array_keys($this->pageImages),
            'runs' => $this->runs,
            'distinct_columns' => $this->distinctColumns,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'distinct_rows_seen' => $this->distinctRowsSeen,
            'duplicate_rows_skipped' => $this->duplicateRowsSkipped,
            'yielded_rows' => count($this->yieldedRows),
        ];
    }

    /**
     * @param list<array{column:string,direction?:string,collation?:string}> $sortTerms
     * @return list<array{column:string,direction:string,collation:string}>
     */
    private static function normalizeSortTerms(array $sortTerms): array
    {
        $terms = [];
        foreach ($sortTerms as $term) {
            if (!isset($term['column']) || !is_string($term['column']) || $term['column'] === '') {
                throw new \InvalidArgumentException('SQLite temp sorter sort term requires a column');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite temp sorter sort direction must be ASC or DESC');
            }
            $collation = strtoupper((string) ($term['collation'] ?? 'BINARY'));
            if ($collation !== 'BINARY' && $collation !== 'NOCASE' && $collation !== 'RTRIM') {
                throw new \InvalidArgumentException('SQLite temp sorter collation must be BINARY, NOCASE, or RTRIM');
            }
            $terms[] = ['column' => $term['column'], 'direction' => $direction, 'collation' => $collation];
        }

        return $terms;
    }

    /**
     * @param array{key:list<mixed>,sequence:int,row:array<string, mixed>} $left
     * @param array{key:list<mixed>,sequence:int,row:array<string, mixed>} $right
     * @param list<array{column:string,direction:string,collation:string}> $terms
     */
    private static function compareRecords(array $left, array $right, array $terms): int
    {
        foreach ($terms as $index => $term) {
            $comparison = self::compareValues($left['key'][$index], $right['key'][$index], $term['collation']);
            if ($comparison !== 0) {
                return $term['direction'] === 'DESC' ? -$comparison : $comparison;
            }
        }

        return $left['sequence'] <=> $right['sequence'];
    }

    private static function compareValues(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        $leftText = (string) $left;
        $rightText = (string) $right;
        if ($collation === 'RTRIM') {
            $leftText = rtrim($leftText, ' ');
            $rightText = rtrim($rightText, ' ');
        } elseif ($collation === 'NOCASE') {
            $leftText = self::asciiLower($leftText);
            $rightText = self::asciiLower($rightText);
        }

        return $leftText <=> $rightText;
    }

    private static function valueKey(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if (is_bool($value) || is_int($value)) {
            return 'integer:' . (int) $value;
        }
        if (is_float($value)) {
            return 'real:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        throw new \InvalidArgumentException('SQLite temp sorter DISTINCT yield values must be scalar, BLOB, or NULL');
    }

    private static function asciiLower(string $value): string
    {
        return preg_replace_callback('/[A-Z]/', static fn (array $match): string => chr(ord($match[0]) + 32), $value) ?? $value;
    }

    /**
     * @param list<array{key:list<mixed>,sequence:int,row:array<string, mixed>}> $records
     */
    private static function estimatedMemoryBytes(array $records): int
    {
        $bytes = 0;
        foreach ($records as $record) {
            $bytes += strlen(json_encode($record['key'], JSON_THROW_ON_ERROR));
            $bytes += strlen(json_encode($record['row'], JSON_THROW_ON_ERROR));
            $bytes += 16;
        }

        return $bytes;
    }

    /**
     * @param list<array{key:list<mixed>,sequence:int,row:array<string, mixed>}> $records
     * @param list<array{column:string,direction:string,collation:string}> $terms
     * @return array{0:array<int, string>,1:list<array{page_number:int,first_key:mixed,last_key:mixed,cell_count:int,bytes:int}>}
     */
    private static function assembleRuns(array $records, array $terms, int $pageSize, int $firstTempPageNumber): array
    {
        $pages = [];
        $runs = [];
        $cells = [];
        $currentBytes = 8;
        $pageNumber = $firstTempPageNumber;

        foreach ($records as $record) {
            $cell = SQLiteIndexCell::encode(SQLiteRecord::encode([
                ...$record['key'],
                $record['sequence'],
                json_encode($record['row'], JSON_THROW_ON_ERROR),
            ]), $pageSize);
            $projectedBytes = $currentBytes + (count($cells) * 2) + 2 + strlen($cell);
            if ($cells !== [] && $projectedBytes > $pageSize) {
                self::flushRun($pages, $runs, $cells, $pageNumber, $pageSize);
                $pageNumber++;
                $cells = [];
                $currentBytes = 8;
            }
            $cells[] = ['cell' => $cell, 'key' => $record['key'], 'bytes' => strlen($cell)];
            $currentBytes += strlen($cell);
        }

        if ($cells !== []) {
            self::flushRun($pages, $runs, $cells, $pageNumber, $pageSize);
        }

        return [$pages, $runs];
    }

    /**
     * @param array<int, string> $pages
     * @param list<array{page_number:int,first_key:mixed,last_key:mixed,cell_count:int,bytes:int}> $runs
     * @param list<array{cell:string,key:list<mixed>,bytes:int}> $cells
     */
    private static function flushRun(array &$pages, array &$runs, array $cells, int $pageNumber, int $pageSize): void
    {
        $page = SQLiteIndexLeafPage::assemble(array_map(static fn (array $cell): string => $cell['cell'], $cells), $pageSize);
        $pages[$pageNumber] = $page;
        $runs[] = [
            'page_number' => $pageNumber,
            'first_key' => $cells[0]['key'][0] ?? null,
            'last_key' => $cells[array_key_last($cells)]['key'][0] ?? null,
            'cell_count' => count($cells),
            'bytes' => array_sum(array_map(static fn (array $cell): int => $cell['bytes'], $cells)),
        ];
    }
}
