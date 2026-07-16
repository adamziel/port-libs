<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeCellDefragmentPlan
{
    /**
     * @param list<array{index:int,offset:int,bytes:int,key:mixed}> $cellsBefore
     * @param list<array{index:int,offset:int,bytes:int,key:mixed}> $cellsAfter
     * @param array{status:string,page_type:string,fragmented_free_bytes:int,current_next_fragment_bytes:int,unaccounted_fragment_bytes:int,current_next_fragments:list<array{current_offset:int,current_end_offset:int,next_offset:int,fragment_bytes:int}>,error:?string} $currentNextFragmentReport
     */
    private function __construct(
        public readonly int $pageNumber,
        public readonly string $pageType,
        public readonly int $cellCount,
        public readonly int $fragmentedBytesBefore,
        public readonly int $fragmentedBytesAfter,
        public readonly int $firstFreeblockBefore,
        public readonly int $firstFreeblockAfter,
        public readonly int $cellContentStartBefore,
        public readonly int $cellContentStartAfter,
        public readonly int $freeSpaceBefore,
        public readonly int $freeSpaceAfter,
        public readonly int $movedCellCount,
        public readonly int $totalCellBytes,
        public readonly array $cellsBefore,
        public readonly array $cellsAfter,
        public readonly array $currentNextFragmentReport,
        public readonly string $pageImage,
    ) {
    }

    public static function fromDatabasePage(
        SQLiteDatabase $database,
        int $pageNumber,
        bool $clearFreeSpace = true,
        ?callable $overflowReader = null,
        int $textEncoding = 1,
    ): self {
        if ($pageNumber < 1 || $pageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite b-tree cell defragment page is outside the database image');
        }

        return self::fromPage(
            $pageNumber,
            $database->page($pageNumber),
            $database->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
            $database->usablePageSize(),
            $clearFreeSpace,
            $overflowReader,
            $textEncoding,
        );
    }

    public static function fromPage(
        int $pageNumber,
        string $page,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        bool $clearFreeSpace = true,
        ?callable $overflowReader = null,
        int $textEncoding = 1,
    ): self {
        $usableSize ??= $pageSize;
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree cell defragment page number must be positive');
        }
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree cell defragment requires a complete page image');
        }

        $beforeHeader = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        if (!$beforeHeader->isLeaf()) {
            throw new \InvalidArgumentException('SQLite b-tree cell defragmentation requires a leaf page');
        }

        $cellsBefore = self::cellDetails($page, $beforeHeader, $usableSize, $overflowReader, $textEncoding);
        $fragmentReport = $beforeHeader->freeblockFragmentReport($page, $usableSize);
        $afterPage = match ($beforeHeader->pageType) {
            'table-leaf' => SQLiteTableLeafPage::defragment($page, $pageSize, $headerOffset, $usableSize, $clearFreeSpace),
            'index-leaf' => SQLiteIndexLeafPage::defragment($page, $pageSize, $headerOffset, $usableSize, $clearFreeSpace),
            default => throw new \InvalidArgumentException('SQLite b-tree cell defragmentation requires a leaf page'),
        };

        $afterHeader = SQLiteBTreePageHeader::parsePage($afterPage, $pageSize, $headerOffset);
        $cellsAfter = self::cellDetails($afterPage, $afterHeader, $usableSize, $overflowReader, $textEncoding);

        $moved = 0;
        $totalCellBytes = 0;
        foreach ($cellsBefore as $index => $cell) {
            $totalCellBytes += $cell['bytes'];
            if (($cellsAfter[$index]['offset'] ?? null) !== $cell['offset']) {
                $moved++;
            }
        }

        return new self(
            $pageNumber,
            $beforeHeader->pageType,
            $beforeHeader->cellCount,
            $beforeHeader->fragmentedFreeBytes,
            $afterHeader->fragmentedFreeBytes,
            $beforeHeader->firstFreeblockOffset,
            $afterHeader->firstFreeblockOffset,
            $beforeHeader->cellContentAreaStart,
            $afterHeader->cellContentAreaStart,
            $beforeHeader->freeSpaceBytes($page, $usableSize),
            $afterHeader->freeSpaceBytes($afterPage, $usableSize),
            $moved,
            $totalCellBytes,
            $cellsBefore,
            $cellsAfter,
            $fragmentReport,
            $afterPage,
        );
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return [$this->pageNumber => $this->pageImage];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-cell-defragment-current-next',
            'page' => $this->pageNumber,
            'page_type' => $this->pageType,
            'cell_count' => $this->cellCount,
            'moved_cell_count' => $this->movedCellCount,
            'total_cell_bytes' => $this->totalCellBytes,
            'fragmented_bytes_before' => $this->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->fragmentedBytesAfter,
            'first_freeblock_before' => $this->firstFreeblockBefore,
            'first_freeblock_after' => $this->firstFreeblockAfter,
            'cell_content_start_before' => $this->cellContentStartBefore,
            'cell_content_start_after' => $this->cellContentStartAfter,
            'free_space_before' => $this->freeSpaceBefore,
            'free_space_after' => $this->freeSpaceAfter,
            'current_next_fragment_report' => $this->currentNextFragmentReport,
            'cells_before' => $this->cellsBefore,
            'cells_after' => $this->cellsAfter,
            'updated_page_numbers' => array_keys($this->pageImages()),
        ];
    }

    /**
     * @return list<array{index:int,offset:int,bytes:int,key:mixed}>
     */
    private static function cellDetails(
        string $page,
        SQLiteBTreePageHeader $header,
        int $usableSize,
        ?callable $overflowReader,
        int $textEncoding,
    ): array {
        if ($header->pageType === 'table-leaf') {
            return array_map(
                static fn (SQLiteTableLeafCell $cell, int $index): array => [
                    'index' => $index,
                    'offset' => $cell->offset,
                    'bytes' => $cell->bytesRead,
                    'key' => $cell->rowId,
                ],
                SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize, $overflowReader),
                array_keys($header->cellPointers($page)),
            );
        }

        return array_map(
            static fn (SQLiteIndexCell $cell, int $index): array => [
                'index' => $index,
                'offset' => $cell->offset,
                'bytes' => $cell->bytesRead,
                'key' => $cell->record($textEncoding)->values,
            ],
            SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader),
            array_keys($header->cellPointers($page)),
        );
    }
}
