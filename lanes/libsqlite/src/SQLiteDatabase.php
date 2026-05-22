<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDatabase
{
    private function __construct(
        private readonly string $bytes,
        public readonly SQLiteHeader $header,
    ) {
    }

    public static function fromBytes(string $bytes): self
    {
        $header = SQLiteHeader::parse($bytes);
        if (strlen($bytes) < $header->pageSize) {
            throw new \InvalidArgumentException('SQLite database reader requires a complete first page image');
        }

        return new self($bytes, $header);
    }

    public static function fromFile(string $path): self
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Unable to read SQLite database file: {$path}");
        }

        return self::fromBytes($bytes);
    }

    public function pageCount(): int
    {
        return intdiv(strlen($this->bytes), $this->header->pageSize);
    }

    public function usablePageSize(): int
    {
        $usableSize = $this->header->pageSize - $this->header->reservedSpace;
        if ($usableSize < 480) {
            throw new \InvalidArgumentException('SQLite usable page size is too small');
        }

        return $usableSize;
    }

    public function page(int $pageNumber): string
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }

        $offset = ($pageNumber - 1) * $this->header->pageSize;
        if ($offset + $this->header->pageSize > strlen($this->bytes)) {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not present in the database image");
        }

        return substr($this->bytes, $offset, $this->header->pageSize);
    }

    public function pageHeader(int $pageNumber): SQLiteBTreePageHeader
    {
        return SQLiteBTreePageHeader::parsePage(
            $this->page($pageNumber),
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function schemaRecords(): array
    {
        $records = [];
        foreach ($this->tableLeafCells(1) as $cell) {
            $records[] = SQLiteSchemaRecord::fromTableLeafCell($cell, $this->header->textEncoding);
        }

        return $records;
    }

    public function tableRootPage(string $tableName): ?int
    {
        foreach ($this->schemaRecords() as $record) {
            if ($record->isTable($tableName)) {
                return $record->rootPage;
            }
        }

        return null;
    }

    public function tablePageHeader(string $tableName): ?SQLiteBTreePageHeader
    {
        $rootPage = $this->tableRootPage($tableName);
        if ($rootPage === null) {
            return null;
        }

        return $this->pageHeader($rootPage);
    }

    /**
     * @return list<SQLiteTableLeafCell>
     */
    public function tableLeafCells(int $rootPageNumber, ?int $limit = null): array
    {
        $visited = [];
        $cells = [];
        $this->collectTableLeafCells($rootPageNumber, $visited, $cells, $limit);

        return $cells;
    }

    /**
     * @return list<SQLiteTableRow>
     */
    public function tableRows(int $rootPageNumber, ?int $limit = null): array
    {
        $rows = [];
        foreach ($this->tableLeafCells($rootPageNumber, $limit) as $cell) {
            $rows[] = SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding);
        }

        return $rows;
    }

    /**
     * @return list<SQLiteTableRow>
     */
    public function tableRowsByName(string $tableName, ?int $limit = null): array
    {
        $rootPage = $this->tableRootPage($tableName);
        if ($rootPage === null) {
            return [];
        }

        return $this->tableRows($rootPage, $limit);
    }

    public function tableRowByRowId(int $rootPageNumber, int $rowId): ?SQLiteTableRow
    {
        $visited = [];
        $cell = $this->findTableLeafCellByRowId($rootPageNumber, $rowId, $visited);

        return $cell === null ? null : SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding);
    }

    public function tableRowByRowIdByName(string $tableName, int $rowId): ?SQLiteTableRow
    {
        $rootPage = $this->tableRootPage($tableName);
        if ($rootPage === null) {
            return null;
        }

        return $this->tableRowByRowId($rootPage, $rowId);
    }

    /**
     * @return list<SQLiteIndexCell>
     */
    public function indexCells(int $rootPageNumber, ?int $limit = null): array
    {
        $visited = [];
        $cells = [];
        $this->collectIndexCells($rootPageNumber, $visited, $cells, $limit);

        return $cells;
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function indexRecordsForTable(string $tableName): array
    {
        $indexes = [];
        foreach ($this->schemaRecords() as $record) {
            if ($record->isIndexForTable($tableName) && $record->rootPage !== null) {
                $indexes[] = $record;
            }
        }

        return $indexes;
    }

    public function indexRootPageForColumn(string $tableName, string $columnName): ?int
    {
        $lookup = $this->indexLookupForColumn($tableName, $columnName);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForPointLookup(string $tableName, string $columnName, mixed $value): ?int
    {
        $lookup = $this->indexLookupForColumn($tableName, $columnName, $value, true);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumn(
        string $tableName,
        string $columnName,
        mixed $pointLookupValue = null,
        bool $isPointLookup = false,
    ): ?array
    {
        $autoIndexFirstColumns = null;
        $autoIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql !== null) {
                $firstColumn = SQLiteCreateIndex::firstColumn($record->sql);
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    if ($firstColumn->partial) {
                        $partialPredicate = $firstColumn->partialPredicate;
                        if (
                            !$isPointLookup
                            || $partialPredicate === null
                            || !$partialPredicate->isImpliedByPointLookup($columnName, $pointLookupValue)
                        ) {
                            continue;
                        }
                    }

                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
            if ($record->sql === null && self::isAutomaticIndex($record, $tableName)) {
                if ($autoIndexFirstColumns === null) {
                    $autoIndexFirstColumns = $this->automaticIndexFirstColumnsForTable($tableName);
                }
                $firstColumn = $autoIndexFirstColumns[$autoIndexOrdinal] ?? null;
                $autoIndexOrdinal++;
                if ($firstColumn !== null && strcasecmp($firstColumn, $columnName) === 0) {
                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => 'BINARY',
                        'descending' => false,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptions(int $limit = 100): array
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options limit cannot be negative');
        }

        $options = [];
        foreach ($this->tableRowsByName('wp_options', $limit) as $row) {
            $options[] = SQLiteWordPressOption::fromTableRow($row);
        }

        return $options;
    }

    public function wordpressOptionByIndexedName(string $optionName): ?SQLiteWordPressOption
    {
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForColumn('wp_options', 'option_name', $optionName, true);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options option_name index is not present');
        }

        $visited = [];
        $indexCell = $this->findIndexCellByFirstValue(
            $indexLookup['rootPage'],
            $optionName,
            $visited,
            $indexLookup['collation'],
            $indexLookup['descending'],
        );
        if ($indexCell === null) {
            return null;
        }

        $rowId = $this->rowIdFromIndexCell($indexCell);
        $row = $this->tableRowByRowId($tableRootPage, $rowId);
        if ($row === null) {
            throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
        }

        return SQLiteWordPressOption::fromTableRow($row);
    }

    /**
     * @param array<int, true> $visited
     * @param list<SQLiteTableLeafCell> $cells
     */
    private function collectTableLeafCells(int $pageNumber, array &$visited, array &$cells, ?int $limit): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite table leaf cell limit cannot be negative');
        }
        if ($limit !== null && count($cells) >= $limit) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite table b-tree traversal reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );

        if ($header->pageType === 'table-leaf') {
            $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
                if ($limit !== null && count($cells) >= $limit) {
                    return;
                }
                $cells[] = $cell;
            }

            return;
        }
        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a table b-tree page");
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite table interior page {$pageNumber} has an invalid right-most pointer");
        }

        foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $interiorCell) {
            $this->collectTableLeafCells($interiorCell->leftChildPage, $visited, $cells, $limit);
            if ($limit !== null && count($cells) >= $limit) {
                return;
            }
        }
        $this->collectTableLeafCells($header->rightMostPointer, $visited, $cells, $limit);
    }

    /**
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $cells
     */
    private function collectIndexCells(int $pageNumber, array &$visited, array &$cells, ?int $limit): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite index cell limit cannot be negative');
        }
        if ($limit !== null && count($cells) >= $limit) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree traversal reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $pageCells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
        if ($header->pageType === 'index-leaf') {
            foreach ($pageCells as $cell) {
                if ($limit !== null && count($cells) >= $limit) {
                    return;
                }
                $cells[] = $cell;
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }
        foreach ($pageCells as $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has a cell without a child pointer");
            }
            $this->collectIndexCells($cell->leftChildPage, $visited, $cells, $limit);
            if ($limit !== null && count($cells) >= $limit) {
                return;
            }
            $cells[] = $cell;
            if ($limit !== null && count($cells) >= $limit) {
                return;
            }
        }
        $this->collectIndexCells($header->rightMostPointer, $visited, $cells, $limit);
    }

    /**
     * @param array<int, true> $visited
     */
    private function findTableLeafCellByRowId(int $pageNumber, int $rowId, array &$visited): ?SQLiteTableLeafCell
    {
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite table b-tree rowid lookup reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );

        if ($header->pageType === 'table-leaf') {
            $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
                if ($cell->rowId === $rowId) {
                    return $cell;
                }
            }

            return null;
        }
        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a table b-tree page");
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite table interior page {$pageNumber} has an invalid right-most pointer");
        }

        foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $interiorCell) {
            if ($rowId <= $interiorCell->key) {
                return $this->findTableLeafCellByRowId($interiorCell->leftChildPage, $rowId, $visited);
            }
        }

        return $this->findTableLeafCellByRowId($header->rightMostPointer, $rowId, $visited);
    }

    /**
     * @param array<int, true> $visited
     */
    private function findIndexCellByFirstValue(
        int $pageNumber,
        mixed $value,
        array &$visited,
        string $collation,
        bool $descending,
    ): ?SQLiteIndexCell
    {
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree lookup reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $cells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
        if ($cells === []) {
            return null;
        }

        $lower = 0;
        $upper = count($cells) - 1;
        $comparison = -1;
        while ($lower <= $upper) {
            $index = intdiv($lower + $upper, 2);
            $record = $cells[$index]->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            $comparison = self::compareSQLiteScalar($record->values[0], $value, $collation);
            if ($descending) {
                $comparison = -$comparison;
            }
            if ($comparison < 0) {
                $lower = $index + 1;
            } elseif ($comparison > 0) {
                $upper = $index - 1;
            } else {
                return $cells[$index];
            }
        }

        if ($header->pageType === 'index-leaf') {
            return null;
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $childPage = $lower >= count($cells) ? $header->rightMostPointer : $cells[$lower]->leftChildPage;
        if ($childPage === null || $childPage < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
        }

        return $this->findIndexCellByFirstValue($childPage, $value, $visited, $collation, $descending);
    }

    private function rowIdFromIndexCell(SQLiteIndexCell $cell): int
    {
        $record = $cell->record($this->header->textEncoding);
        if ($record->values === []) {
            throw new \InvalidArgumentException('SQLite index record must contain at least one value');
        }
        $rowId = $record->values[array_key_last($record->values)];
        if (!is_int($rowId)) {
            throw new \InvalidArgumentException('SQLite index record must end with an integer rowid');
        }

        return $rowId;
    }

    private function automaticIndexFirstColumnsForTable(string $tableName): array
    {
        foreach ($this->schemaRecords() as $record) {
            if ($record->isTable($tableName) && $record->sql !== null) {
                return SQLiteCreateTable::automaticIndexFirstColumns($record->sql);
            }
        }

        return [];
    }

    private static function isAutomaticIndex(SQLiteSchemaRecord $record, string $tableName): bool
    {
        return $record->type === 'index'
            && $record->tableName === $tableName
            && str_starts_with($record->name, "sqlite_autoindex_{$tableName}_");
    }

    private static function compareSQLiteScalar(mixed $left, mixed $right, string $collation = 'BINARY'): int
    {
        $leftRank = self::sqliteScalarRank($left);
        $rightRank = self::sqliteScalarRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null && $right === null) {
            return 0;
        }
        if (is_int($left) || is_float($left)) {
            return $left <=> $right;
        }
        if (is_string($left)) {
            if (!is_string($right)) {
                throw new \InvalidArgumentException('SQLite scalar comparison values must share a storage class');
            }

            return self::compareSQLiteText($left, $right, $collation);
        }

        throw new \InvalidArgumentException('Unsupported SQLite scalar comparison value');
    }

    private static function compareSQLiteText(string $left, string $right, string $collation): int
    {
        return match (strtoupper($collation)) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
            default => throw new \InvalidArgumentException("Unsupported SQLite index collation: {$collation}"),
        };
    }

    private static function asciiLower(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x41 && $ord <= 0x5a) {
                $bytes[$i] = chr($ord + 0x20);
            }
        }

        return $bytes;
    }

    private static function sqliteScalarRank(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }

        throw new \InvalidArgumentException('Unsupported SQLite scalar comparison value');
    }

    private function readOverflowPayload(int $firstOverflowPage, int $byteCount): string
    {
        if ($byteCount < 0) {
            throw new \InvalidArgumentException('SQLite overflow byte count cannot be negative');
        }
        if ($byteCount === 0) {
            return '';
        }

        $usableSize = $this->usablePageSize();
        $overflowPagePayloadSize = $usableSize - 4;
        if ($overflowPagePayloadSize <= 0) {
            throw new \InvalidArgumentException('SQLite overflow page payload size is invalid');
        }

        $payload = '';
        $remaining = $byteCount;
        $pageNumber = $firstOverflowPage;
        $visited = [];
        while ($remaining > 0) {
            if ($pageNumber < 2) {
                throw new \InvalidArgumentException('SQLite overflow chain ended before payload was complete');
            }
            if (isset($visited[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite overflow chain loops at page {$pageNumber}");
            }
            if ($pageNumber > $this->pageCount()) {
                throw new \InvalidArgumentException("SQLite overflow page {$pageNumber} is not present in the database image");
            }
            $visited[$pageNumber] = true;

            $page = $this->page($pageNumber);
            $nextPage = self::readUInt32($page, 0);
            $chunkLength = min($remaining, $overflowPagePayloadSize);
            $payload .= substr($page, 4, $chunkLength);
            $remaining -= $chunkLength;
            $pageNumber = $nextPage;
        }

        return $payload;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite uint32 field is truncated');
        }

        return unpack('N', substr($bytes, $offset, 4))[1];
    }
}
