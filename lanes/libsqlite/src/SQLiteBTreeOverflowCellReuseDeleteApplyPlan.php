<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowCellReuseDeleteApplyPlan
{
    /**
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $remainingFreeblocks
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $leafPageNumber,
        public readonly string $leafPageType,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly int $reusedCellOffset,
        public readonly int $replacementCellBytes,
        public readonly int $remainingFreeblockBytes,
        public readonly array $remainingFreeblocks,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function tableCell(
        SQLiteDatabase $database,
        int $leafPageNumber,
        string $leafPage,
        int $deleteRowId,
        int $replacementRowId,
        string $replacementRecordPayload,
        callable $overflowPageNumbers,
        bool $secureDelete = false,
    ): self {
        $delete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
            $leafPage,
            $deleteRowId,
            $overflowPageNumbers,
            $database->header->pageSize,
            0,
            $database->usablePageSize(),
            $secureDelete,
        );
        $replacementPage = SQLiteTableLeafPage::insertCellByRowIdReusingFreeblock(
            $delete['page'],
            $replacementRowId,
            $replacementRecordPayload,
            $database->header->pageSize,
            0,
            $database->usablePageSize(),
        );
        $replacementCell = SQLiteTableLeafCell::encode($replacementRowId, $replacementRecordPayload);
        $header = SQLiteBTreePageHeader::parsePage($replacementPage, $database->header->pageSize);
        $insertedOffset = self::tableCellOffset($replacementPage, $header, $replacementRowId, $database->usablePageSize());

        return self::fromReplacementPage(
            $database,
            $leafPageNumber,
            'table-leaf',
            $replacementPage,
            $delete['obsolete_overflow_page_numbers'],
            $insertedOffset,
            strlen($replacementCell),
            $secureDelete,
        );
    }

    /**
     * @param list<mixed> $deleteRecordValues
     * @param list<mixed> $replacementRecordValues
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function indexCell(
        SQLiteDatabase $database,
        int $leafPageNumber,
        string $leafPage,
        array $deleteRecordValues,
        array $replacementRecordValues,
        callable $overflowPageNumbers,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): self {
        $delete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
            $leafPage,
            $deleteRecordValues,
            $overflowPageNumbers,
            $database->header->pageSize,
            0,
            $database->usablePageSize(),
            $database->header->textEncoding,
            $secureDelete,
            $overflowReader,
        );
        $replacementPage = SQLiteIndexLeafPage::insertCellByRecordValuesReusingFreeblock(
            $delete['page'],
            $replacementRecordValues,
            $database->header->pageSize,
            0,
            $database->usablePageSize(),
            $database->header->textEncoding,
        );
        $replacementCell = SQLiteIndexCell::encode(SQLiteRecord::encode($replacementRecordValues, $database->header->textEncoding));
        $header = SQLiteBTreePageHeader::parsePage($replacementPage, $database->header->pageSize);
        $insertedOffset = self::indexCellOffset($replacementPage, $header, $replacementRecordValues, $database->usablePageSize(), $database->header->textEncoding);

        return self::fromReplacementPage(
            $database,
            $leafPageNumber,
            'index-leaf',
            $replacementPage,
            $delete['obsolete_overflow_page_numbers'],
            $insertedOffset,
            strlen($replacementCell),
            $secureDelete,
        );
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-cell-reuse-delete-apply',
            'leaf_page' => $this->leafPageNumber,
            'leaf_page_type' => $this->leafPageType,
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'reused_cell_offset' => $this->reusedCellOffset,
            'replacement_cell_bytes' => $this->replacementCellBytes,
            'remaining_freeblock_bytes' => $this->remainingFreeblockBytes,
            'remaining_freeblocks' => $this->remainingFreeblocks,
            'freed_pages' => $this->freePlan->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
        ];
    }

    /**
     * @param list<int> $obsoleteOverflowPageNumbers
     */
    private static function fromReplacementPage(
        SQLiteDatabase $database,
        int $leafPageNumber,
        string $leafPageType,
        string $replacementPage,
        array $obsoleteOverflowPageNumbers,
        int $reusedCellOffset,
        int $replacementCellBytes,
        bool $secureDelete,
    ): self {
        if ($leafPageNumber < 2 || $leafPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite overflow cell reuse leaf page is outside the database image');
        }
        if ($obsoleteOverflowPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite overflow cell reuse requires obsolete overflow pages');
        }

        $seen = [];
        foreach ($obsoleteOverflowPageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite overflow cell reuse obsolete pages must be integers');
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite overflow cell reuse page {$pageNumber} appears more than once");
            }
            $seen[$pageNumber] = true;
        }

        $header = SQLiteBTreePageHeader::parsePage($replacementPage, $database->header->pageSize);
        if ($header->pageType !== $leafPageType) {
            throw new \InvalidArgumentException("SQLite overflow cell reuse expected {$leafPageType} replacement page");
        }

        $remainingFreeblocks = [];
        $remainingFreeblockBytes = 0;
        foreach ($header->freeblocks($replacementPage, $database->usablePageSize()) as $freeblock) {
            $remainingFreeblocks[] = $freeblock->toArray();
            $remainingFreeblockBytes += $freeblock->size;
        }

        $freePlan = $database->planPageFreeList($obsoleteOverflowPageNumbers, $secureDelete);
        $pageImages = $freePlan->pageImages();
        $pageImages[$leafPageNumber] = $replacementPage;
        ksort($pageImages);

        return new self(
            $leafPageNumber,
            $leafPageType,
            array_values($obsoleteOverflowPageNumbers),
            $reusedCellOffset,
            $replacementCellBytes,
            $remainingFreeblockBytes,
            $remainingFreeblocks,
            $freePlan,
            $pageImages,
        );
    }

    private static function tableCellOffset(string $page, SQLiteBTreePageHeader $header, int $rowId, int $usableSize): int
    {
        foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize) as $cell) {
            if ($cell->rowId === $rowId) {
                return $cell->offset;
            }
        }

        throw new \InvalidArgumentException('SQLite overflow cell reuse replacement table row was not written');
    }

    /**
     * @param list<mixed> $recordValues
     */
    private static function indexCellOffset(string $page, SQLiteBTreePageHeader $header, array $recordValues, int $usableSize, int $textEncoding): int
    {
        foreach (SQLiteIndexCell::parsePageCells($page, $header, $usableSize) as $cell) {
            if ($cell->record($textEncoding)->values === $recordValues) {
                return $cell->offset;
            }
        }

        throw new \InvalidArgumentException('SQLite overflow cell reuse replacement index record was not written');
    }
}
