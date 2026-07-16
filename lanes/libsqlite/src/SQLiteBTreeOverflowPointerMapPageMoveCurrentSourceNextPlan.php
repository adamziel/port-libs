<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $pageImages
     * @param list<int> $updatedPointerMapPageNumbers
     */
    private function __construct(
        public readonly int $sourcePageNumber,
        public readonly int $targetPageNumber,
        public readonly int $previousOverflowPageNumber,
        public readonly int $databasePageCount,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly array $pageImages,
        public readonly array $updatedPointerMapPageNumbers,
        public readonly SQLiteDatabase $databaseAfter,
    ) {
    }

    public static function moveLastOverflowPageIntoFreelistSlot(
        SQLiteDatabase $database,
        int $sourcePageNumber,
        int $previousOverflowPageNumber,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 requires an auto-vacuum database');
        }
        if ($sourcePageNumber !== max($database->pageCount(), $database->header->databaseSizePages)) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 source must be the last database page');
        }
        if ($sourcePageNumber < 3 || $previousOverflowPageNumber < 3) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 pages must be valid database pages');
        }
        if ($database->isPointerMapPage($sourcePageNumber) || $database->isPointerMapPage($previousOverflowPageNumber)) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 cannot move pointer-map pages');
        }

        $sourceEntry = $database->pointerMapEntryForPage($sourcePageNumber);
        if ($sourceEntry->type !== SQLitePointerMapEntry::OVERFLOW_PAGE || $sourceEntry->parentPageNumber !== $previousOverflowPageNumber) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 source must be an overflow page owned by the previous overflow page');
        }

        $previousPage = $database->page($previousOverflowPageNumber);
        $previousNextPage = self::readUInt32($previousPage, 0);
        if ($previousNextPage !== $sourcePageNumber) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 previous overflow page does not point at the source page');
        }

        $sourcePage = $database->page($sourcePageNumber);
        $sourceNextPage = self::readUInt32($sourcePage, 0);

        $allocationPlan = $database->planPageAllocation(1, false);
        $targetPageNumber = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if (!is_int($targetPageNumber)) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 could not allocate a target freelist page');
        }
        if ($targetPageNumber >= $sourcePageNumber) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 target must be before the source page');
        }
        if ($database->isPointerMapPage($targetPageNumber)) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 target cannot be a pointer-map page');
        }

        $databasePageCount = $sourcePageNumber - 1;
        $firstPage = substr_replace($allocationPlan->firstPage, self::uint32Bytes($databasePageCount), 28, 4);
        $movedPreviousPage = substr_replace($previousPage, self::uint32Bytes($targetPageNumber), 0, 4);

        $pageImages = $allocationPlan->pageImages();
        $pageImages[1] = $firstPage;
        $pageImages[$previousOverflowPageNumber] = $movedPreviousPage;
        $pageImages[$targetPageNumber] = $sourcePage;

        $pointerMapPageImages = $database->planPointerMapUpdates([
            $targetPageNumber => [
                'type' => SQLitePointerMapEntry::OVERFLOW_PAGE,
                'parent_page_number' => $previousOverflowPageNumber,
            ],
        ], $databasePageCount);
        foreach ($pointerMapPageImages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return new self(
            $sourcePageNumber,
            $targetPageNumber,
            $previousOverflowPageNumber,
            $databasePageCount,
            $allocationPlan,
            $pageImages,
            array_keys($pointerMapPageImages),
            self::databaseWithPageImages($database, $pageImages, $databasePageCount),
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
            'action' => 'btree-overflow-pointermap-page-move-current-source-next111',
            'source_page' => $this->sourcePageNumber,
            'target_page' => $this->targetPageNumber,
            'previous_overflow_page' => $this->previousOverflowPageNumber,
            'target_next_page' => self::readUInt32($this->pageImages[$this->targetPageNumber], 0),
            'database_page_count' => $this->databasePageCount,
            'allocated_page_numbers' => $this->allocationPlan->allocatedPageNumbers,
            'freelist_page_count' => $this->allocationPlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->allocationPlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_freelist_page_numbers' => array_keys($this->allocationPlan->updatedFreelistPages),
            'updated_pointer_map_page_numbers' => $this->updatedPointerMapPageNumbers,
            'pointer_map_target' => $this->databaseAfter->pointerMapEntryForPage($this->targetPageNumber)->toArray(),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, int $pageCount): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 could not read uint32');
        }

        return $value[1];
    }

    private static function uint32Bytes(int $value): string
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map page move current-source next111 uint32 is outside range');
        }

        return pack('N', $value);
    }
}
