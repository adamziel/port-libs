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
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $this->usablePageSize()) as $cell) {
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
}
