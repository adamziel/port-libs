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

    public function toBytes(): string
    {
        return $this->bytes;
    }

    public function usablePageSize(): int
    {
        $usableSize = $this->header->pageSize - $this->header->reservedSpace;
        if ($usableSize < 480) {
            throw new \InvalidArgumentException('SQLite usable page size is too small');
        }

        return $usableSize;
    }

    public function isAutoVacuum(): bool
    {
        return $this->header->largestRootBtreePage > 0;
    }

    public function isIncrementalVacuum(): bool
    {
        return $this->isAutoVacuum() && $this->header->incrementalVacuum !== 0;
    }

    public function pointerMapEntriesPerPage(): int
    {
        return intdiv($this->usablePageSize(), 5);
    }

    public function pagesPerPointerMapStride(): int
    {
        return $this->pointerMapEntriesPerPage() + 1;
    }

    public function pendingBytePageNumber(): int
    {
        return intdiv(0x40000000, $this->header->pageSize) + 1;
    }

    public function pointerMapPageFor(int $pageNumber): ?int
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($pageNumber === 1) {
            return null;
        }

        $stride = $this->pagesPerPointerMapStride();
        $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
        if ($pointerMapPage === $this->pendingBytePageNumber()) {
            $pointerMapPage++;
        }

        return $pointerMapPage;
    }

    public function isPointerMapPage(int $pageNumber): bool
    {
        if (!$this->isAutoVacuum() || $pageNumber < 2) {
            return false;
        }

        return $this->pointerMapPageFor($pageNumber) === $pageNumber;
    }

    public function pointerMapOffsetFor(int $pageNumber): int
    {
        $pointerMapPage = $this->pointerMapPageFor($pageNumber);
        if ($pointerMapPage === null || $pointerMapPage === $pageNumber) {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} does not have a pointer-map entry");
        }

        $offset = 5 * ($pageNumber - $pointerMapPage - 1);
        if ($offset < 0 || $offset + 5 > $this->usablePageSize()) {
            throw new \InvalidArgumentException("SQLite pointer-map offset for page {$pageNumber} is outside the usable page area");
        }

        return $offset;
    }

    public function pointerMapEntryForPage(int $pageNumber): SQLitePointerMapEntry
    {
        if (!$this->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite pointer-map entries require an auto-vacuum database');
        }
        if ($pageNumber > $this->pageCount()) {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not present in the database image");
        }

        $pointerMapPage = $this->pointerMapPageFor($pageNumber);
        if ($pointerMapPage === null || $pointerMapPage === $pageNumber) {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} does not have a pointer-map entry");
        }

        $offset = $this->pointerMapOffsetFor($pageNumber);
        $page = $this->page($pointerMapPage);

        return new SQLitePointerMapEntry(
            $pageNumber,
            $pointerMapPage,
            $offset,
            ord($page[$offset]),
            self::readUInt32($page, $offset + 1),
        );
    }

    /**
     * @return array<int, SQLitePointerMapEntry>
     */
    public function pointerMapEntries(): array
    {
        if (!$this->isAutoVacuum()) {
            return [];
        }

        $entries = [];
        for ($pageNumber = 2; $pageNumber <= $this->pageCount(); $pageNumber++) {
            if ($this->isPointerMapPage($pageNumber)) {
                continue;
            }

            $entries[$pageNumber] = $this->pointerMapEntryForPage($pageNumber);
        }

        return $entries;
    }

    /**
     * @param array<int, SQLitePointerMapEntry|array{0:int,1:int}|array{type:int,parent_page_number:int}> $updatesByPage
     * @return array<int, string>
     */
    public function planPointerMapUpdates(array $updatesByPage, ?int $databasePageCount = null): array
    {
        return $this->pointerMapPageImagesForUpdates([], $updatesByPage, $databasePageCount);
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

    /**
     * @param array<int, string> $pageImages
     */
    private function withPageImages(array $pageImages): self
    {
        $pageCount = $this->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite replacement page images must use one-based page numbers');
            }
            if (!is_string($page) || strlen($page) !== $this->header->pageSize) {
                throw new \InvalidArgumentException('SQLite replacement page image length does not match page size');
            }
            if ($pageNumber > $pageCount) {
                $pageCount = $pageNumber;
            }
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber]
                ?? ($pageNumber <= $this->pageCount() ? $this->page($pageNumber) : str_repeat("\0", $this->header->pageSize));
        }

        return self::fromBytes(implode('', $pages));
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
     * @return list<SQLiteFreelistTrunkPage>
     */
    public function freelistTrunkPages(): array
    {
        $plan = $this->freelistTraversalPlan();
        if (!$plan->isValid()) {
            throw new \InvalidArgumentException($plan->errors[0]);
        }

        return $plan->trunkPages;
    }

    public function freelistTraversalPlan(): SQLiteFreelistTraversalPlan
    {
        $expectedPageCount = $this->header->freelistPageCount;
        $firstTrunkPage = $this->header->firstFreelistTrunkPage;
        $errors = [];
        if ($expectedPageCount === 0) {
            if ($firstTrunkPage !== 0) {
                $errors[] = 'SQLite freelist header points at a trunk page but has zero free pages';
            }

            return new SQLiteFreelistTraversalPlan(
                $expectedPageCount,
                $firstTrunkPage === 0 ? null : $firstTrunkPage,
                [],
                [],
                [],
                [],
                [],
                null,
                [],
                0,
                $errors,
            );
        }
        if ($firstTrunkPage < 2) {
            return new SQLiteFreelistTraversalPlan(
                $expectedPageCount,
                null,
                [],
                [],
                [],
                [],
                [],
                null,
                [],
                0,
                ['SQLite freelist header has free pages but no valid first trunk page'],
            );
        }

        $trunkPages = [];
        $trunkPageNumbers = [];
        $leafPageNumbers = [];
        $pageNumbers = [];
        $allocationOrder = [];
        $seenPages = [];
        $path = [];
        $cycleAtPage = null;
        $cyclePath = [];
        $actualPageCount = 0;
        $pageNumber = $firstTrunkPage;
        while ($pageNumber !== 0) {
            if (isset($seenPages[$pageNumber])) {
                $cycleAtPage = $pageNumber;
                $cycleStart = array_search($pageNumber, $path, true);
                $cyclePath = $cycleStart === false ? [$pageNumber] : array_slice($path, $cycleStart);
                $cyclePath[] = $pageNumber;
                $errors[] = "SQLite freelist loops at page {$pageNumber}";
                break;
            }

            $path[] = $pageNumber;
            try {
                $trunkPage = SQLiteFreelistTrunkPage::parse(
                    $pageNumber,
                    $this->page($pageNumber),
                    $this->usablePageSize(),
                    $this->pageCount(),
                );
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
                break;
            }

            $trunkPages[] = $trunkPage;
            $trunkPageNumbers[] = $trunkPage->pageNumber;
            $pageNumbers[] = $trunkPage->pageNumber;
            array_push($allocationOrder, ...$trunkPage->allocationOrder());
            $seenPages[$pageNumber] = 'trunk';
            $actualPageCount++;

            foreach ($trunkPage->leafPageNumbers as $leafPageNumber) {
                if (isset($seenPages[$leafPageNumber])) {
                    $errors[] = "SQLite freelist page {$leafPageNumber} appears more than once";
                    break 2;
                }
                $seenPages[$leafPageNumber] = 'leaf';
                $leafPageNumbers[] = $leafPageNumber;
                $pageNumbers[] = $leafPageNumber;
                $actualPageCount++;
            }
            if ($actualPageCount > $expectedPageCount) {
                $errors[] = "SQLite freelist size is {$actualPageCount} but should be {$expectedPageCount}";
                break;
            }

            $pageNumber = $trunkPage->nextTrunkPage ?? 0;
        }

        if ($errors === [] && $actualPageCount !== $expectedPageCount) {
            $errors[] = "SQLite freelist size is {$actualPageCount} but should be {$expectedPageCount}";
        }

        return new SQLiteFreelistTraversalPlan(
            $expectedPageCount,
            $firstTrunkPage,
            $trunkPages,
            $trunkPageNumbers,
            $leafPageNumbers,
            $pageNumbers,
            $allocationOrder,
            $cycleAtPage,
            $cyclePath,
            $actualPageCount,
            $errors,
        );
    }

    /**
     * @return list<int>
     */
    public function freelistPageNumbers(): array
    {
        $pageNumbers = [];
        foreach ($this->freelistTrunkPages() as $trunkPage) {
            $pageNumbers[] = $trunkPage->pageNumber;
            foreach ($trunkPage->leafPageNumbers as $leafPageNumber) {
                $pageNumbers[] = $leafPageNumber;
            }
        }

        return $pageNumbers;
    }

    /**
     * @return list<int>
     */
    public function freelistAllocationOrder(?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite freelist allocation order limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $pageNumbers = [];
        foreach ($this->freelistTrunkPages() as $trunkPage) {
            foreach ($trunkPage->allocationOrder() as $pageNumber) {
                $pageNumbers[] = $pageNumber;
                if ($limit !== null && count($pageNumbers) >= $limit) {
                    return $pageNumbers;
                }
            }
        }

        return $pageNumbers;
    }

    public function planPageAllocation(int $count, bool $allowAppend = true): SQLiteFreelistAllocationPlan
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('SQLite page allocation count cannot be negative');
        }

        $this->freelistTrunkPages();

        $databasePageCount = $this->pageCount();
        if ($this->header->databaseSizePages > $databasePageCount) {
            $databasePageCount = $this->header->databaseSizePages;
        }

        $firstPage = $this->page(1);
        $firstTrunkPage = $this->header->firstFreelistTrunkPage;
        $freelistPageCount = $this->header->freelistPageCount;
        $updatedFreelistPages = [];
        $allocatedPageNumbers = [];
        $appendedPageNumbers = [];
        $allocationSteps = [];

        for ($i = 0; $i < $count; $i++) {
            if ($freelistPageCount > 0) {
                if ($firstTrunkPage < 2) {
                    throw new \InvalidArgumentException('SQLite freelist has free pages but no valid first trunk page');
                }

                $trunkPageBytes = $updatedFreelistPages[$firstTrunkPage] ?? $this->page($firstTrunkPage);
                $trunkPage = SQLiteFreelistTrunkPage::parse(
                    $firstTrunkPage,
                    $trunkPageBytes,
                    $this->usablePageSize(),
                    $this->pageCount(),
                );

                $freelistPageCount--;
                if ($trunkPage->leafPageNumbers === []) {
                    $allocatedPageNumbers[] = $trunkPage->pageNumber;
                    $allocationSteps[] = [
                        'source' => 'freelist-trunk',
                        'allocated_page' => $trunkPage->pageNumber,
                        'trunk_page' => $trunkPage->pageNumber,
                        'next_trunk_page_before' => $trunkPage->nextTrunkPage,
                        'next_trunk_page_after' => $trunkPage->nextTrunkPage,
                        'freelist_page_count_after' => $freelistPageCount,
                    ];
                    unset($updatedFreelistPages[$trunkPage->pageNumber]);
                    $firstTrunkPage = $trunkPage->nextTrunkPage ?? 0;
                    continue;
                }

                $leafPageNumbers = $trunkPage->leafPageNumbers;
                $allocatedPageNumbers[] = $leafPageNumbers[0];
                $leafCount = count($leafPageNumbers);
                $allocationSteps[] = [
                    'source' => 'freelist-leaf',
                    'allocated_page' => $leafPageNumbers[0],
                    'trunk_page' => $trunkPage->pageNumber,
                    'next_trunk_page_before' => $trunkPage->nextTrunkPage,
                    'next_trunk_page_after' => $trunkPage->nextTrunkPage,
                    'leaf_count_before' => $leafCount,
                    'leaf_count_after' => $leafCount - 1,
                    'freelist_page_count_after' => $freelistPageCount,
                ];
                if ($leafCount > 1) {
                    $trunkPageBytes = substr_replace($trunkPageBytes, pack('N', $leafPageNumbers[$leafCount - 1]), 8, 4);
                }
                $trunkPageBytes = substr_replace($trunkPageBytes, pack('N', $leafCount - 1), 4, 4);
                $updatedFreelistPages[$trunkPage->pageNumber] = $trunkPageBytes;
                continue;
            }

            if (!$allowAppend) {
                throw new \InvalidArgumentException('SQLite freelist does not contain enough pages for this allocation');
            }

            $databasePageCount = $this->nextAppendPageNumber($databasePageCount);
            $allocatedPageNumbers[] = $databasePageCount;
            $appendedPageNumbers[] = $databasePageCount;
            $allocationSteps[] = [
                'source' => 'append',
                'allocated_page' => $databasePageCount,
                'trunk_page' => null,
                'next_trunk_page_before' => null,
                'next_trunk_page_after' => null,
                'freelist_page_count_after' => $freelistPageCount,
            ];
        }

        if ($freelistPageCount === 0) {
            $firstTrunkPage = 0;
        }

        $firstPage = substr_replace($firstPage, self::uint32Bytes($databasePageCount), 28, 4);
        $firstPage = substr_replace($firstPage, self::uint32Bytes($firstTrunkPage), 32, 4);
        $firstPage = substr_replace($firstPage, self::uint32Bytes($freelistPageCount), 36, 4);

        return new SQLiteFreelistAllocationPlan(
            $allocatedPageNumbers,
            $appendedPageNumbers,
            $firstPage,
            $updatedFreelistPages,
            $databasePageCount,
            $firstTrunkPage,
            $freelistPageCount,
            allocationSteps: $allocationSteps,
        );
    }

    public function planBtreePageAllocation(
        int $count,
        ?int $parentPageNumber,
        bool $allowAppend = true,
    ): SQLiteFreelistAllocationPlan {
        if ($parentPageNumber !== null && $parentPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree allocation parent page must be null or at page 2 or later');
        }

        $allocationPlan = $this->planPageAllocation($count, $allowAppend);
        $updatedPointerMapPages = [];
        $allocatedPointerMapEntries = [];
        if ($this->isAutoVacuum() && $allocationPlan->allocatedPageNumbers !== []) {
            $updates = [];
            foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
                $updates[$pageNumber] = [
                    'type' => $parentPageNumber === null
                        ? SQLitePointerMapEntry::ROOT_PAGE
                        : SQLitePointerMapEntry::BTREE_PAGE,
                    'parent_page_number' => $parentPageNumber ?? 0,
                ];
            }
            $updatedPointerMapPages = $this->pointerMapPageImagesForUpdates(
                $allocationPlan->pageImages(),
                $updates,
                $allocationPlan->databasePageCount,
            );
            unset($updatedPointerMapPages[1]);
            foreach (array_keys($allocationPlan->updatedFreelistPages) as $freelistPageNumber) {
                unset($updatedPointerMapPages[$freelistPageNumber]);
            }
            $postPointerMapImages = $allocationPlan->pageImages();
            foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
                if ($pageNumber > $this->pageCount()) {
                    $postPointerMapImages[$pageNumber] = str_repeat("\0", $this->header->pageSize);
                }
            }
            foreach ($updatedPointerMapPages as $pageNumber => $page) {
                $postPointerMapImages[$pageNumber] = $page;
            }
            $postPointerMapDatabase = $this->withPageImages($postPointerMapImages);
            foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
                if ($postPointerMapDatabase->isPointerMapPage($pageNumber)) {
                    continue;
                }

                $allocatedPointerMapEntries[] = $postPointerMapDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }
        }

        return new SQLiteFreelistAllocationPlan(
            $allocationPlan->allocatedPageNumbers,
            $allocationPlan->appendedPageNumbers,
            $allocationPlan->firstPage,
            $allocationPlan->updatedFreelistPages,
            $allocationPlan->databasePageCount,
            $allocationPlan->firstFreelistTrunkPage,
            $allocationPlan->freelistPageCount,
            $updatedPointerMapPages,
            $allocationPlan->allocationSteps,
            $allocatedPointerMapEntries,
        );
    }

    /**
     * @param array<int, string> $allocatedPageImages
     */
    public function applyPageAllocationPlan(
        SQLiteFreelistAllocationPlan $allocationPlan,
        array $allocatedPageImages = [],
    ): self {
        $allocated = array_fill_keys($allocationPlan->allocatedPageNumbers, true);
        $pageImages = $allocationPlan->pageImages();

        foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
            $pageImages[$pageNumber] = str_repeat("\0", $this->header->pageSize);
        }

        foreach ($allocatedPageImages as $pageNumber => $page) {
            if (!isset($allocated[$pageNumber])) {
                throw new \InvalidArgumentException('SQLite allocated page image was not part of the allocation plan');
            }
            if (!is_string($page) || strlen($page) !== $this->header->pageSize) {
                throw new \InvalidArgumentException('SQLite allocated page image length does not match page size');
            }
            $pageImages[$pageNumber] = $page;
        }

        return $this->withPageImages($pageImages);
    }

    public function applyPageFreePlan(SQLiteFreelistFreePlan $freePlan): self
    {
        return $this->withPageImages($freePlan->pageImages());
    }

    public function planOverflowPageAllocation(
        int $count,
        int $parentBtreePageNumber,
        bool $allowAppend = true,
    ): SQLiteFreelistAllocationPlan {
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow allocation parent b-tree page must be at page 2 or later');
        }

        return $this->planOverflowPageAllocationForParent($count, $parentBtreePageNumber, $allowAppend);
    }

    public function planRootOverflowPageAllocation(int $count, bool $allowAppend = true): SQLiteFreelistAllocationPlan
    {
        return $this->planOverflowPageAllocationForParent($count, 1, $allowAppend);
    }

    private function planOverflowPageAllocationForParent(
        int $count,
        int $parentBtreePageNumber,
        bool $allowAppend,
    ): SQLiteFreelistAllocationPlan {
        $allocationPlan = $this->planPageAllocation($count, $allowAppend);
        $updatedPointerMapPages = [];
        $allocatedPointerMapEntries = [];
        if ($this->isAutoVacuum() && $allocationPlan->allocatedPageNumbers !== []) {
            $updates = [];
            $previousOverflowPageNumber = null;
            foreach ($allocationPlan->allocatedPageNumbers as $index => $pageNumber) {
                $updates[$pageNumber] = [
                    'type' => $index === 0
                        ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE
                        : SQLitePointerMapEntry::OVERFLOW_PAGE,
                    'parent_page_number' => $index === 0
                        ? $parentBtreePageNumber
                        : $previousOverflowPageNumber,
                ];
                $previousOverflowPageNumber = $pageNumber;
            }

            $updatedPointerMapPages = $this->pointerMapPageImagesForUpdates(
                $allocationPlan->pageImages(),
                $updates,
                $allocationPlan->databasePageCount,
            );
            unset($updatedPointerMapPages[1]);
            foreach (array_keys($allocationPlan->updatedFreelistPages) as $freelistPageNumber) {
                unset($updatedPointerMapPages[$freelistPageNumber]);
            }

            $postPointerMapImages = $allocationPlan->pageImages();
            foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
                if ($pageNumber > $this->pageCount()) {
                    $postPointerMapImages[$pageNumber] = str_repeat("\0", $this->header->pageSize);
                }
            }
            foreach ($updatedPointerMapPages as $pageNumber => $page) {
                $postPointerMapImages[$pageNumber] = $page;
            }
            $postPointerMapDatabase = $this->withPageImages($postPointerMapImages);
            foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
                if ($postPointerMapDatabase->isPointerMapPage($pageNumber)) {
                    continue;
                }

                $allocatedPointerMapEntries[] = $postPointerMapDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }
        }

        return new SQLiteFreelistAllocationPlan(
            $allocationPlan->allocatedPageNumbers,
            $allocationPlan->appendedPageNumbers,
            $allocationPlan->firstPage,
            $allocationPlan->updatedFreelistPages,
            $allocationPlan->databasePageCount,
            $allocationPlan->firstFreelistTrunkPage,
            $allocationPlan->freelistPageCount,
            $updatedPointerMapPages,
            $allocationPlan->allocationSteps,
            $allocatedPointerMapEntries,
        );
    }

    public function planPageFree(int $pageNumber, bool $secureDelete = false): SQLiteFreelistFreePlan
    {
        return $this->planPageFreeList([$pageNumber], $secureDelete);
    }

    public function planFreelistTailTruncation(int $maxPages = 1): SQLiteFreelistTruncatePlan
    {
        if ($maxPages < 1) {
            throw new \InvalidArgumentException('SQLite freelist tail truncation count must be positive');
        }

        $databasePageCount = max($this->pageCount(), $this->header->databaseSizePages);
        $trunkPages = $this->freelistTrunkPages();
        $freelistPages = array_fill_keys($this->freelistPageNumbers(), true);
        $truncatedPageNumbers = [];
        for ($pageNumber = $databasePageCount; $pageNumber >= 2 && count($truncatedPageNumbers) < $maxPages; $pageNumber--) {
            if ($pageNumber === $this->pendingBytePageNumber()) {
                break;
            }
            if ($this->isAutoVacuum() && $this->isPointerMapPage($pageNumber)) {
                $truncatedPageNumbers[] = $pageNumber;
                continue;
            }
            if (!isset($freelistPages[$pageNumber])) {
                break;
            }

            $truncatedPageNumbers[] = $pageNumber;
        }

        $firstPage = $this->page(1);
        if ($truncatedPageNumbers === []) {
            $firstPage = substr_replace($firstPage, self::uint32Bytes($databasePageCount), 28, 4);

            return new SQLiteFreelistTruncatePlan(
                [],
                $firstPage,
                [],
                $databasePageCount,
                $this->header->firstFreelistTrunkPage,
                $this->header->freelistPageCount,
            );
        }

        $truncatedLookup = array_fill_keys($truncatedPageNumbers, true);
        $truncatedPointerMapEntries = [];
        if ($this->isAutoVacuum()) {
            foreach ($truncatedPageNumbers as $pageNumber) {
                if (!$this->isPointerMapPage($pageNumber)) {
                    $entry = $this->pointerMapEntryForPageIfPresent($pageNumber);
                    if ($entry !== null) {
                        $truncatedPointerMapEntries[] = $entry->toArray();
                    }
                }
            }
        }
        $previousTrunkByPage = [];
        foreach ($trunkPages as $trunkPage) {
            if ($trunkPage->nextTrunkPage !== null) {
                $previousTrunkByPage[$trunkPage->nextTrunkPage] = $trunkPage->pageNumber;
            }
        }

        $updatedFreelistPages = [];
        $removedTrunkPages = [];
        $firstTrunkPage = $this->header->firstFreelistTrunkPage;
        foreach ($trunkPages as $trunkPage) {
            if (isset($truncatedLookup[$trunkPage->pageNumber])) {
                $removedTrunkPages[$trunkPage->pageNumber] = true;
                if ($trunkPage->pageNumber === $firstTrunkPage) {
                    $firstTrunkPage = $trunkPage->nextTrunkPage ?? 0;
                } elseif (isset($previousTrunkByPage[$trunkPage->pageNumber])) {
                    $previousTrunkPage = $previousTrunkByPage[$trunkPage->pageNumber];
                    $previousBytes = $updatedFreelistPages[$previousTrunkPage] ?? $this->page($previousTrunkPage);
                    $updatedFreelistPages[$previousTrunkPage] = substr_replace(
                        $previousBytes,
                        self::uint32Bytes($trunkPage->nextTrunkPage ?? 0),
                        0,
                        4,
                    );
                }
                continue;
            }

            $leafPageNumbers = [];
            foreach ($trunkPage->leafPageNumbers as $leafPageNumber) {
                if (!isset($truncatedLookup[$leafPageNumber])) {
                    $leafPageNumbers[] = $leafPageNumber;
                }
            }
            if (count($leafPageNumbers) !== count($trunkPage->leafPageNumbers)) {
                $updatedFreelistPages[$trunkPage->pageNumber] = SQLiteFreelistTrunkPage::assemble(
                    $trunkPage->nextTrunkPage,
                    $leafPageNumbers,
                    $this->header->pageSize,
                    $this->usablePageSize(),
                );
            }
        }

        foreach ($removedTrunkPages as $removedTrunkPage => $_) {
            unset($updatedFreelistPages[$removedTrunkPage]);
        }

        $truncatedFreelistPageCount = 0;
        foreach ($truncatedPageNumbers as $pageNumber) {
            if (isset($freelistPages[$pageNumber])) {
                $truncatedFreelistPageCount++;
            }
        }

        $freelistPageCount = $this->header->freelistPageCount - $truncatedFreelistPageCount;
        if ($freelistPageCount < 0) {
            throw new \InvalidArgumentException('SQLite freelist tail truncation exceeds the freelist page count');
        }
        if ($freelistPageCount === 0) {
            $firstTrunkPage = 0;
            $updatedFreelistPages = [];
        }

        $newDatabasePageCount = $databasePageCount - count($truncatedPageNumbers);
        $firstPage = substr_replace($firstPage, self::uint32Bytes($newDatabasePageCount), 28, 4);
        $firstPage = substr_replace($firstPage, self::uint32Bytes($firstTrunkPage), 32, 4);
        $firstPage = substr_replace($firstPage, self::uint32Bytes($freelistPageCount), 36, 4);

        ksort($updatedFreelistPages);
        $boundaryPointerMapEntry = null;
        if ($this->isAutoVacuum() && $newDatabasePageCount >= 2 && !$this->isPointerMapPage($newDatabasePageCount)) {
            $postImages = [1 => $firstPage];
            foreach ($updatedFreelistPages as $pageNumber => $page) {
                if ($pageNumber <= $newDatabasePageCount) {
                    $postImages[$pageNumber] = $page;
                }
            }
            $postPages = [];
            for ($pageNumber = 1; $pageNumber <= $newDatabasePageCount; $pageNumber++) {
                $postPages[$pageNumber] = $postImages[$pageNumber] ?? $this->page($pageNumber);
            }
            $postDatabase = self::fromBytes(implode('', $postPages));
            $boundaryPointerMapEntry = $postDatabase->pointerMapEntryForPageIfPresent($newDatabasePageCount)?->toArray();
        }

        return new SQLiteFreelistTruncatePlan(
            $truncatedPageNumbers,
            $firstPage,
            $updatedFreelistPages,
            $newDatabasePageCount,
            $firstTrunkPage,
            $freelistPageCount,
            $truncatedPointerMapEntries,
            $boundaryPointerMapEntry,
        );
    }

    private function pointerMapEntryForPageIfPresent(int $pageNumber): ?SQLitePointerMapEntry
    {
        $pointerMapPage = $this->pointerMapPageFor($pageNumber);
        if ($pointerMapPage === null || $pointerMapPage === $pageNumber) {
            return null;
        }

        $offset = $this->pointerMapOffsetFor($pageNumber);
        if (ord($this->page($pointerMapPage)[$offset]) === 0) {
            return null;
        }

        return $this->pointerMapEntryForPage($pageNumber);
    }

    /**
     * @param list<int> $pageNumbers
     */
    public function planPageFreeList(array $pageNumbers, bool $secureDelete = false): SQLiteFreelistFreePlan
    {
        $pageNumbers = array_values($pageNumbers);

        $this->freelistTrunkPages();

        $databasePageCount = $this->pageCount();
        if ($this->header->databaseSizePages > $databasePageCount) {
            $databasePageCount = $this->header->databaseSizePages;
        }

        $alreadyFree = array_fill_keys($this->freelistPageNumbers(), true);
        $seenInput = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite freed page numbers must be integers');
            }
            if ($pageNumber < 2 || $pageNumber > $databasePageCount) {
                throw new \InvalidArgumentException('SQLite freed page number is outside the database image');
            }
            if ($this->isAutoVacuum() && $this->isPointerMapPage($pageNumber)) {
                throw new \InvalidArgumentException('SQLite auto-vacuum pointer-map pages cannot be placed on the freelist');
            }
            if (isset($seenInput[$pageNumber]) || isset($alreadyFree[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite page {$pageNumber} is already on the freelist");
            }
            $seenInput[$pageNumber] = true;
        }

        $firstPage = $this->page(1);
        $firstTrunkPage = $this->header->firstFreelistTrunkPage;
        $freelistPageCount = $this->header->freelistPageCount;
        $updatedFreelistPages = [];
        $leafPageNumbers = [];
        $newTrunkPageNumbers = [];
        $clearedPageNumbers = [];
        $clearedPageImages = [];
        $usableSize = $this->usablePageSize();
        $trunkLeafInsertLimit = intdiv($usableSize, 4) - 8;

        foreach ($pageNumbers as $pageNumber) {
            if ($freelistPageCount !== 0) {
                if ($firstTrunkPage < 2) {
                    throw new \InvalidArgumentException('SQLite freelist has free pages but no valid first trunk page');
                }

                $trunkPageBytes = $updatedFreelistPages[$firstTrunkPage] ?? $this->page($firstTrunkPage);
                $trunkPage = SQLiteFreelistTrunkPage::parse(
                    $firstTrunkPage,
                    $trunkPageBytes,
                    $usableSize,
                    $databasePageCount,
                );

                $leafCount = count($trunkPage->leafPageNumbers);
                if ($leafCount < $trunkLeafInsertLimit) {
                    $trunkPageBytes = substr_replace($trunkPageBytes, self::uint32Bytes($leafCount + 1), 4, 4);
                    $trunkPageBytes = substr_replace($trunkPageBytes, self::uint32Bytes($pageNumber), 8 + ($leafCount * 4), 4);
                    $updatedFreelistPages[$firstTrunkPage] = $trunkPageBytes;
                    $leafPageNumbers[] = $pageNumber;
                    if ($secureDelete) {
                        $clearedPageNumbers[] = $pageNumber;
                        $clearedPageImages[$pageNumber] = str_repeat("\0", $this->header->pageSize);
                    }
                    $freelistPageCount++;
                    $alreadyFree[$pageNumber] = true;
                    continue;
                }
            }

            $updatedFreelistPages[$pageNumber] = SQLiteFreelistTrunkPage::assemble(
                $firstTrunkPage === 0 ? null : $firstTrunkPage,
                [],
                $this->header->pageSize,
                $usableSize,
            );
            $firstTrunkPage = $pageNumber;
            $newTrunkPageNumbers[] = $pageNumber;
            $freelistPageCount++;
            $alreadyFree[$pageNumber] = true;
        }

        $updatedPointerMapPages = [];
        $freedPointerMapEntries = [];
        if ($this->isAutoVacuum() && $pageNumbers !== []) {
            $pointerMapUpdates = [];
            foreach ($pageNumbers as $pageNumber) {
                $pointerMapUpdates[$pageNumber] = [
                    'type' => SQLitePointerMapEntry::FREE_PAGE,
                    'parent_page_number' => 0,
                ];
            }

            $updatedPointerMapPages = $this->pointerMapPageImagesForUpdates([], $pointerMapUpdates, $databasePageCount);
            $postPointerMapDatabase = $this->withPageImages($updatedPointerMapPages);
            foreach ($pageNumbers as $pageNumber) {
                $freedPointerMapEntries[] = $postPointerMapDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }
        }

        $firstPage = substr_replace($firstPage, self::uint32Bytes($firstTrunkPage), 32, 4);
        $firstPage = substr_replace($firstPage, self::uint32Bytes($freelistPageCount), 36, 4);

        return new SQLiteFreelistFreePlan(
            $pageNumbers,
            $leafPageNumbers,
            $newTrunkPageNumbers,
            $firstPage,
            $updatedFreelistPages,
            $databasePageCount,
            $firstTrunkPage,
            $freelistPageCount,
            $updatedPointerMapPages,
            $clearedPageNumbers,
            $clearedPageImages,
            $freedPointerMapEntries,
        );
    }

    public function planKeyValueRowInsert(
        int $rowId,
        string $keyName,
        string $keyValue,
        ?string $loadPolicy = 'yes',
        bool $allowAppend = true,
    ): SQLiteKeyValueRowWritePlan {
        if ($rowId < 1) {
            throw new \InvalidArgumentException('SQLite app_settings insert rowid must be positive');
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            throw new \InvalidArgumentException('SQLite app_settings table is not present');
        }
        if ($tableRootPage === 1) {
            throw new \InvalidArgumentException('SQLite app_settings insert planning requires a table root page separate from sqlite_schema');
        }

        $tablePage = $this->page($tableRootPage);
        $tableHeader = SQLiteBTreePageHeader::parsePage(
            $tablePage,
            $this->header->pageSize,
            $tableRootPage === 1 ? 100 : 0,
        );
        if ($tableHeader->pageType !== 'table-leaf') {
            throw new \InvalidArgumentException('SQLite app_settings insert planning currently requires a single table leaf root page');
        }

        $insertIndexes = $this->supportedKeyValueRowIndexesForInsert($keyName, $loadPolicy);
        $existingCells = [];
        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        foreach (SQLiteTableLeafCell::parsePageCells($tablePage, $tableHeader, $this->usablePageSize(), $overflowReader) as $cell) {
            if ($cell->rowId === $rowId) {
                throw new \InvalidArgumentException("SQLite app_settings rowid {$rowId} already exists");
            }
            $setting = SQLiteKeyValueRow::fromTableRow(SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding));
            if ($setting->keyName === $keyName) {
                throw new \InvalidArgumentException("SQLite app_settings key_name {$keyName} already exists");
            }

            $existingCells[] = [
                'rowid' => $cell->rowId,
                'cell' => substr($tablePage, $cell->offset, $cell->bytesRead),
            ];
        }

        $payload = SQLiteRecord::encode([null, $keyName, $keyValue, $loadPolicy], $this->header->textEncoding);
        $usableSize = $this->usablePageSize();
        $localPayloadLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $usableSize);
        $overflowPayload = substr($payload, $localPayloadLength);
        $overflowPageNumbers = [];
        $allocationPlan = null;
        if ($overflowPayload !== '') {
            $overflowPageCount = SQLiteOverflowPage::requiredPageCount(
                strlen($overflowPayload),
                $this->header->pageSize,
                $usableSize,
            );
            $allocationPlan = $this->planPageAllocation($overflowPageCount, $allowAppend);
            $overflowPageNumbers = $allocationPlan->allocatedPageNumbers;
        }

        $newCell = SQLiteTableLeafCell::encode(
            $rowId,
            $payload,
            $usableSize,
            $overflowPageNumbers[0] ?? null,
        );
        $existingCells[] = [
            'rowid' => $rowId,
            'cell' => $newCell,
        ];
        usort(
            $existingCells,
            static fn (array $left, array $right): int => $left['rowid'] <=> $right['rowid'],
        );

        $pageImages = $allocationPlan?->pageImages() ?? [];
        $pageImages[$tableRootPage] = SQLiteTableLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $existingCells),
            $this->header->pageSize,
            $tableRootPage === 1 ? 100 : 0,
            $tablePage,
            $usableSize,
        );
        if ($overflowPayload !== '') {
            foreach (
                SQLiteOverflowPage::encodeChainAtPages(
                    $overflowPayload,
                    $overflowPageNumbers,
                    $this->header->pageSize,
                    $usableSize,
                ) as $pageNumber => $page
            ) {
                $pageImages[$pageNumber] = $page;
            }
        }
        ksort($pageImages);

        $pageImages = $this->withKeyValueRowIndexInsertPages($pageImages, $insertIndexes, $rowId, $allowAppend);
        $databasePageCount = max(
            $this->pageCount(),
            $this->header->databaseSizePages,
            $allocationPlan?->databasePageCount ?? 0,
            $pageImages === [] ? 0 : max(array_keys($pageImages)),
        );
        $pageImages = $this->withBtreePointerMapPages($pageImages, $databasePageCount);
        $databasePageCount = max(
            $databasePageCount,
            $pageImages === [] ? 0 : max(array_keys($pageImages)),
        );
        $pageImages = $this->withOverflowPointerMapPages(
            $pageImages,
            $overflowPageNumbers,
            $tableRootPage,
            $databasePageCount,
        );
        $databasePageCount = max(
            $databasePageCount,
            $pageImages === [] ? 0 : max(array_keys($pageImages)),
        );

        return new SQLiteKeyValueRowWritePlan(
            $tableRootPage,
            $rowId,
            $keyName,
            $keyValue,
            $loadPolicy,
            $overflowPageNumbers,
            $localPayloadLength,
            $databasePageCount,
            $pageImages,
        );
    }

    public function planKeyValueRowInsertOrReplaceCurrent(
        int $rowId,
        string $keyName,
        string $keyValue,
        ?string $loadPolicy = 'yes',
        bool $allowAppend = true,
    ): SQLiteKeyValueRowInsertOrReplacePlan {
        if ($rowId < 1) {
            throw new \InvalidArgumentException('SQLite app_settings insert-or-replace rowid must be positive');
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            throw new \InvalidArgumentException('SQLite app_settings table is not present');
        }
        if ($tableRootPage === 1) {
            throw new \InvalidArgumentException('SQLite app_settings insert-or-replace planning requires a table root page separate from sqlite_schema');
        }

        $tablePage = $this->page($tableRootPage);
        $tableHeader = SQLiteBTreePageHeader::parsePage(
            $tablePage,
            $this->header->pageSize,
            0,
        );
        if ($tableHeader->pageType !== 'table-leaf') {
            throw new \InvalidArgumentException('SQLite app_settings insert-or-replace planning currently requires a single table leaf root page');
        }

        $insertIndexes = $this->supportedKeyValueRowIndexesForInsert($keyName, $loadPolicy);
        $remainingCells = [];
        $deletedRowIds = [];
        $deletedKeyNames = [];
        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        foreach (SQLiteTableLeafCell::parsePageCells($tablePage, $tableHeader, $this->usablePageSize(), $overflowReader) as $cell) {
            $setting = SQLiteKeyValueRow::fromTableRow(SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding));
            if ($cell->rowId === $rowId || $setting->keyName === $keyName) {
                $deletedRowIds[$cell->rowId] = true;
                $deletedKeyNames[$setting->keyName] = true;
                continue;
            }

            $remainingCells[] = [
                'rowid' => $cell->rowId,
                'cell' => substr($tablePage, $cell->offset, $cell->bytesRead),
            ];
        }

        if ($deletedRowIds === []) {
            return new SQLiteKeyValueRowInsertOrReplacePlan(
                $this->planKeyValueRowInsert($rowId, $keyName, $keyValue, $loadPolicy, $allowAppend),
                [],
                [],
            );
        }

        $pageImages = [
            $tableRootPage => SQLiteTableLeafPage::assemble(
                array_map(static fn (array $entry): string => $entry['cell'], $remainingCells),
                $this->header->pageSize,
                0,
                $tablePage,
                $this->usablePageSize(),
            ),
        ];
        $deletedRowIdSet = $deletedRowIds;
        foreach ($insertIndexes as $index) {
            $record = $index['record'];
            $rootPage = $record->rootPage;
            if ($rootPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings index root page is missing');
            }
            $indexPage = $this->page($rootPage);
            $indexHeader = SQLiteBTreePageHeader::parsePage($indexPage, $this->header->pageSize, $rootPage === 1 ? 100 : 0);
            if ($indexHeader->pageType !== 'index-leaf') {
                throw new \InvalidArgumentException('SQLite app_settings insert-or-replace planning currently deletes conflicts from single-leaf indexes only');
            }

            $entries = array_values(array_filter(
                $this->writableIndexLeafEntries($indexPage, $indexHeader, $index['columns']),
                static fn (array $entry): bool => !isset($deletedRowIdSet[(int) ($entry['values'][count($index['columns'])] ?? 0)]),
            ));
            $pageImages[$rootPage] = $this->assembleWritableIndexLeafPage($entries, $rootPage === 1 ? 100 : 0, $indexPage);
        }

        ksort($pageImages);
        $insertPlan = $this
            ->withPageImages($pageImages)
            ->planKeyValueRowInsert($rowId, $keyName, $keyValue, $loadPolicy, $allowAppend);

        return new SQLiteKeyValueRowInsertOrReplacePlan(
            $insertPlan,
            array_map('intval', array_keys($deletedRowIds)),
            array_map('strval', array_keys($deletedKeyNames)),
        );
    }

    public function planKeyValueRowReplace(
        string $keyName,
        string $keyValue,
        ?string $loadPolicy = null,
        bool $allowAppend = true,
        bool $secureDelete = false,
    ): SQLiteKeyValueRowReplacementPlan {
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            throw new \InvalidArgumentException('SQLite app_settings table is not present');
        }
        if ($tableRootPage === 1) {
            throw new \InvalidArgumentException('SQLite app_settings replacement planning requires a table root page separate from sqlite_schema');
        }

        $usableSize = $this->usablePageSize();
        $targetLeaf = $this->writableKeyValueRowTableLeafForReplacement($tableRootPage, $keyName, $usableSize);
        $replacementIndexes = $this->supportedKeyValueRowIndexesForReplacement();
        $existingCells = $targetLeaf['entries'];
        $matchedRowId = $targetLeaf['rowid'];
        $matchedLoadPolicy = $targetLeaf[SQLiteKeyValueRow::LOAD_POLICY_COLUMN];
        $replacementLoadPolicy = $loadPolicy ?? $matchedLoadPolicy;
        $replacementLocalPayloadLength = 0;
        $replacementOverflowPayload = '';
        $overflowPageNumbers = [];
        $obsoleteOverflowPageNumbers = $targetLeaf['obsoleteOverflowPageNumbers'];
        $values = $targetLeaf['values'];
        $values[0] = $values[0] ?? null;
        $values[1] = $keyName;
        $values[2] = $keyValue;
        if (array_key_exists(3, $values) || $replacementLoadPolicy !== null) {
            $values[3] = $replacementLoadPolicy;
        }
        $payload = SQLiteRecord::encode(array_values($values), $this->header->textEncoding);
        $replacementLocalPayloadLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $usableSize);
        $replacementOverflowPayload = substr($payload, $replacementLocalPayloadLength);

        $allocationPlan = null;
        if ($replacementOverflowPayload !== '') {
            $overflowPageCount = SQLiteOverflowPage::requiredPageCount(
                strlen($replacementOverflowPayload),
                $this->header->pageSize,
                $usableSize,
            );
            $allocationPlan = $this->planPageAllocation($overflowPageCount, $allowAppend);
            $overflowPageNumbers = $allocationPlan->allocatedPageNumbers;
        }

        foreach ($existingCells as $index => $entry) {
            if ($entry['rowid'] === $matchedRowId) {
                $existingCells[$index]['cell'] = SQLiteTableLeafCell::encode(
                    $matchedRowId,
                    $payload,
                    $usableSize,
                    $overflowPageNumbers[0] ?? null,
                );
            }
        }

        usort(
            $existingCells,
            static fn (array $left, array $right): int => $left['rowid'] <=> $right['rowid'],
        );

        $freeSource = $allocationPlan === null ? $this : $this->withPageImages($allocationPlan->pageImages());
        $freePlan = $obsoleteOverflowPageNumbers === [] ? null : $freeSource->planPageFreeList($obsoleteOverflowPageNumbers, $secureDelete);
        $pageImages = [];
        foreach ($allocationPlan?->pageImages() ?? [] as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        foreach ($freePlan?->pageImages() ?? [] as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $pageImages = $this->withAssembledWritableTableLeafPage(
            $pageImages,
            $tableRootPage,
            $targetLeaf,
            $existingCells,
            $allowAppend,
        );
        if ($replacementOverflowPayload !== '') {
            foreach (
                SQLiteOverflowPage::encodeChainAtPages(
                    $replacementOverflowPayload,
                    $overflowPageNumbers,
                    $this->header->pageSize,
                    $usableSize,
                ) as $pageNumber => $page
            ) {
                $pageImages[$pageNumber] = $page;
            }
        }
        ksort($pageImages);
        $pageImages = $this->withKeyValueRowIndexReplacementPages(
            $pageImages,
            $replacementIndexes,
            $matchedRowId,
            $keyName,
            $matchedLoadPolicy,
            $replacementLoadPolicy,
            $allowAppend,
            $obsoleteOverflowPageNumbers,
        );
        $freedObsoletePages = $freePlan?->freedPageNumbers ?? [];
        $pendingObsoletePages = array_values(array_diff($obsoleteOverflowPageNumbers, $freedObsoletePages));
        if ($pendingObsoletePages !== []) {
            $indexFreePlan = $this->withPageImages($pageImages)->planPageFreeList($pendingObsoletePages, $secureDelete);
            foreach ($indexFreePlan->pageImages() as $pageNumber => $page) {
                $pageImages[$pageNumber] = $page;
            }
            $freePlan = $indexFreePlan;
            ksort($pageImages);
        }
        $databasePageCount = max(
            $this->pageCount(),
            $this->header->databaseSizePages,
            $allocationPlan?->databasePageCount ?? 0,
            $freePlan?->databasePageCount ?? 0,
            $pageImages === [] ? 0 : max(array_keys($pageImages)),
        );
        $pageImages = $this->withBtreePointerMapPages($pageImages, $databasePageCount);
        $databasePageCount = max(
            $databasePageCount,
            $pageImages === [] ? 0 : max(array_keys($pageImages)),
        );
        if ($this->isAutoVacuum() && $overflowPageNumbers !== []) {
            $ownerBtreePageNumber = $this->tableLeafPageNumberForRowIdInPlannedImages(
                $tableRootPage,
                $matchedRowId,
                $pageImages,
            );
            $pageImages = $this->withOverflowPointerMapPages(
                $pageImages,
                $overflowPageNumbers,
                $ownerBtreePageNumber,
                $databasePageCount,
            );
            $databasePageCount = max(
                $databasePageCount,
                $pageImages === [] ? 0 : max(array_keys($pageImages)),
            );
        }

        return new SQLiteKeyValueRowReplacementPlan(
            $tableRootPage,
            $matchedRowId,
            $keyName,
            $keyValue,
            $replacementLoadPolicy,
            $overflowPageNumbers,
            $obsoleteOverflowPageNumbers,
            $replacementLocalPayloadLength,
            $databasePageCount,
            $pageImages,
            $this->btreeRebalanceActionsForPageImages($pageImages),
        );
    }

    /**
     * @param array<int, string> $pageImages
     * @return list<array<string, mixed>>
     */
    private function btreeRebalanceActionsForPageImages(array $pageImages): array
    {
        $postDatabase = $this->withPageImages($pageImages);
        $freelistPages = array_fill_keys($postDatabase->freelistPageNumbers(), true);
        $actions = [];

        $pageNumbers = array_unique(array_merge(array_keys($pageImages), array_keys($freelistPages)));
        sort($pageNumbers);

        foreach ($pageNumbers as $pageNumber) {
            if ($pageNumber === 1) {
                continue;
            }

            $before = $pageNumber <= $this->pageCount() ? $this->tryPageHeader($pageNumber) : null;
            $after = !isset($freelistPages[$pageNumber]) ? $postDatabase->tryPageHeader($pageNumber) : null;
            $beforeFreeBytes = $before === null ? null : $this->btreePageFreeSpaceBytes($this, $pageNumber, $before);
            $afterFreeBytes = $after === null ? null : $this->btreePageFreeSpaceBytes($postDatabase, $pageNumber, $after);

            if (isset($freelistPages[$pageNumber])) {
                $actions[] = [
                    'action' => 'free-page',
                    'page' => $pageNumber,
                    'before_type' => $before?->pageType,
                    'before_free_space_bytes' => $beforeFreeBytes,
                ];
                continue;
            }

            if ($before === null || $after === null) {
                continue;
            }

            if ($before->pageType !== $after->pageType) {
                $actions[] = [
                    'action' => 'page-type-change',
                    'page' => $pageNumber,
                    'before_type' => $before->pageType,
                    'after_type' => $after->pageType,
                    'before_cells' => $before->cellCount,
                    'after_cells' => $after->cellCount,
                    'before_free_space_bytes' => $beforeFreeBytes,
                    'after_free_space_bytes' => $afterFreeBytes,
                ];
                continue;
            }

            if (
                ($after->pageType === 'index-interior' || $after->pageType === 'table-interior')
                && $before->rightMostPointer !== $after->rightMostPointer
            ) {
                $actions[] = [
                    'action' => $after->pageType === 'index-interior'
                        ? 'index-interior-rightmost-pointer-update'
                        : 'table-interior-rightmost-pointer-update',
                    'page' => $pageNumber,
                    'page_type' => $after->pageType,
                    'before_rightmost_pointer' => $before->rightMostPointer,
                    'after_rightmost_pointer' => $after->rightMostPointer,
                    'before_free_space_bytes' => $beforeFreeBytes,
                    'after_free_space_bytes' => $afterFreeBytes,
                ];
            }

            if ($before->cellCount === $after->cellCount) {
                continue;
            }

            $delta = $after->cellCount - $before->cellCount;
            $action = [
                'action' => $this->btreeCellDeltaAction($after->pageType, $delta),
                'page' => $pageNumber,
                'page_type' => $after->pageType,
                'before_cells' => $before->cellCount,
                'after_cells' => $after->cellCount,
                'delta_cells' => $delta,
                'before_free_space_bytes' => $beforeFreeBytes,
                'after_free_space_bytes' => $afterFreeBytes,
                'delta_free_space_bytes' => $afterFreeBytes - $beforeFreeBytes,
            ];
            if ($after->pageType === 'index-interior' || $after->pageType === 'table-interior') {
                $action['before_left_children'] = $this->btreeInteriorLeftChildPointers($this->page($pageNumber), $before, $pageNumber);
                $action['after_left_children'] = $this->btreeInteriorLeftChildPointers($postDatabase->page($pageNumber), $after, $pageNumber);
            }
            $actions[] = $action;
        }

        return $actions;
    }

    private function btreePageFreeSpaceBytes(self $database, int $pageNumber, SQLiteBTreePageHeader $header): int
    {
        return $header->freeSpaceBytes(
            $database->page($pageNumber),
            $database->usablePageSize(),
        );
    }

    /**
     * @return list<int>
     */
    private function btreeInteriorLeftChildPointers(string $page, SQLiteBTreePageHeader $header, int $pageNumber): array
    {
        if ($header->pageType === 'index-interior') {
            $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
            $leftChildren = [];
            foreach (SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
                if ($cell->leftChildPage === null) {
                    throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid left child pointer");
                }
                $leftChildren[] = $cell->leftChildPage;
            }

            return $leftChildren;
        }
        if ($header->pageType === 'table-interior') {
            return array_map(
                static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage,
                SQLiteTableInteriorCell::parsePageCells($page, $header),
            );
        }

        throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an interior b-tree page");
    }

    private function btreeCellDeltaAction(string $pageType, int $delta): string
    {
        if ($pageType === 'index-interior') {
            return $delta < 0 ? 'index-interior-divider-removal' : 'index-interior-divider-insert';
        }
        if ($pageType === 'index-leaf') {
            return $delta < 0 ? 'index-leaf-entry-removal' : 'index-leaf-entry-merge';
        }
        if ($pageType === 'table-interior') {
            return $delta < 0 ? 'table-interior-divider-removal' : 'table-interior-divider-insert';
        }
        if ($pageType === 'table-leaf') {
            return $delta < 0 ? 'table-leaf-entry-removal' : 'table-leaf-entry-insert';
        }

        return $delta < 0 ? 'btree-cell-removal' : 'btree-cell-insert';
    }

    private function tryPageHeader(int $pageNumber): ?SQLiteBTreePageHeader
    {
        try {
            return $this->pageHeader($pageNumber);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return array{pageNumber:int,page:string,headerOffset:int,entries:list<array{rowid:int,cell:string}>,rowid:int,load_policy:?string,values:list<mixed>,obsoleteOverflowPageNumbers:list<int>,parent:?array}
     */
    private function writableKeyValueRowTableLeafForReplacement(
        int $rootPage,
        string $keyName,
        int $usableSize,
    ): array {
        $visited = [];
        $target = null;
        $this->collectWritableKeyValueRowTableLeafForReplacement(
            $rootPage,
            $keyName,
            $usableSize,
            $visited,
            $target,
            null,
        );

        if ($target === null) {
            throw new \InvalidArgumentException("SQLite app_settings key_name {$keyName} is not present");
        }

        return $target;
    }

    /**
     * @param array<int, true> $visited
     * @param null|array{pageNumber:int,page:string,headerOffset:int,entries:list<array{rowid:int,cell:string}>,rowid:int,load_policy:?string,values:list<mixed>,obsoleteOverflowPageNumbers:list<int>,parent:?array} $target
     * @param null|array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parentContext
     */
    private function collectWritableKeyValueRowTableLeafForReplacement(
        int $pageNumber,
        string $keyName,
        int $usableSize,
        array &$visited,
        ?array &$target,
        ?array $parentContext,
    ): void {
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite app_settings replacement planning reached table page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $headerOffset = $pageNumber === 1 ? 100 : 0;
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $headerOffset,
        );

        if ($header->pageType === 'table-leaf') {
            $entries = [];
            $matched = null;
            $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize, $overflowReader) as $cell) {
                $entries[] = [
                    'rowid' => $cell->rowId,
                    'cell' => substr($page, $cell->offset, $cell->bytesRead),
                ];

                $row = SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding);
                $setting = SQLiteKeyValueRow::fromTableRow($row);
                if ($setting->keyName !== $keyName) {
                    continue;
                }
                if ($matched !== null || $target !== null) {
                    throw new \InvalidArgumentException("SQLite app_settings key_name {$keyName} is not unique");
                }

                $obsoleteOverflowPageNumbers = [];
                if ($cell->firstOverflowPage !== null) {
                    $obsoleteOverflowPageNumbers = $this->overflowPageNumbers(
                        $cell->firstOverflowPage,
                        $cell->payloadLength - $cell->localPayloadLength,
                    );
                }

                $matched = [
                    'rowid' => $cell->rowId,
                    SQLiteKeyValueRow::LOAD_POLICY_COLUMN => $setting->loadPolicy,
                    'values' => $row->values(),
                    'obsoleteOverflowPageNumbers' => $obsoleteOverflowPageNumbers,
                ];
            }

            if ($matched !== null) {
                $target = [
                    'pageNumber' => $pageNumber,
                    'page' => $page,
                    'headerOffset' => $headerOffset,
                    'entries' => $entries,
                    'rowid' => $matched['rowid'],
                    SQLiteKeyValueRow::LOAD_POLICY_COLUMN => $matched[SQLiteKeyValueRow::LOAD_POLICY_COLUMN],
                    'values' => $matched['values'],
                    'obsoleteOverflowPageNumbers' => $matched['obsoleteOverflowPageNumbers'],
                    'parent' => $parentContext,
                ];
            }

            return;
        }

        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a table b-tree page");
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite table interior page {$pageNumber} has an invalid right-most pointer");
        }

        $interiorCells = SQLiteTableInteriorCell::parsePageCells($page, $header);
        foreach ($interiorCells as $cellIndex => $interiorCell) {
            $this->collectWritableKeyValueRowTableLeafForReplacement(
                $interiorCell->leftChildPage,
                $keyName,
                $usableSize,
                $visited,
                $target,
                [
                    'pageNumber' => $pageNumber,
                    'page' => $page,
                    'header' => $header,
                    'headerOffset' => $headerOffset,
                    'childIndex' => $cellIndex,
                    'parent' => $parentContext,
                ],
            );
        }
        $this->collectWritableKeyValueRowTableLeafForReplacement(
            $header->rightMostPointer,
            $keyName,
            $usableSize,
            $visited,
            $target,
            [
                'pageNumber' => $pageNumber,
                'page' => $page,
                'header' => $header,
                'headerOffset' => $headerOffset,
                'childIndex' => count($interiorCells),
                'parent' => $parentContext,
            ],
        );
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,headerOffset:int,entries:list<array{rowid:int,cell:string}>,parent:?array} $leaf
     * @param list<array{rowid:int,cell:string}> $entries
     * @return array<int, string>
     */
    private function withAssembledWritableTableLeafPage(
        array $pageImages,
        int $rootPage,
        array $leaf,
        array $entries,
        bool $allowAppend,
    ): array {
        try {
            $pageImages[$leaf['pageNumber']] = $this->assembleWritableTableLeafPage(
                $entries,
                $leaf['headerOffset'],
                $leaf['page'],
            );

            return $pageImages;
        } catch (\InvalidArgumentException $exception) {
            if (!str_contains($exception->getMessage(), 'split table leaf pages')) {
                throw $exception;
            }
        }

        return $this->withSplitWritableTableLeafPage($pageImages, $rootPage, $leaf, $entries, $allowAppend);
    }

    /**
     * @param list<array{rowid:int,cell:string}> $entries
     */
    private function assembleWritableTableLeafPage(array $entries, int $headerOffset, string $basePage): string
    {
        try {
            return SQLiteTableLeafPage::assemble(
                array_map(static fn (array $entry): string => $entry['cell'], $entries),
                $this->header->pageSize,
                $headerOffset,
                $basePage,
                $this->usablePageSize(),
            );
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), 'overlap')) {
                throw new \InvalidArgumentException(
                    'SQLite app_settings table replacement planning does not yet split table leaf pages',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,headerOffset:int,parent:?array} $leaf
     * @param list<array{rowid:int,cell:string}> $entries
     * @return array<int, string>
     */
    private function withSplitWritableTableLeafPage(
        array $pageImages,
        int $rootPage,
        array $leaf,
        array $entries,
        bool $allowAppend,
    ): array {
        $parent = $leaf['parent'];
        if ($parent === null) {
            return $this->withGrownRootWritableTableLeafPage($pageImages, $rootPage, $leaf, $entries, $allowAppend);
        }
        if ($parent['header']->pageType !== 'table-interior') {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning can split only children of table interior pages');
        }

        [$leftEntries, $rightEntries] = $this->partitionWritableTableLeafEntriesForSplit(
            $entries,
            $leaf['headerOffset'],
            $leaf['page'],
        );
        $leftMaxRowId = $leftEntries[count($leftEntries) - 1]['rowid'];

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(1, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $newLeafPageNumber = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if ($newLeafPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning could not allocate a split table leaf page');
        }

        $pageImages[$leaf['pageNumber']] = SQLiteTableLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $leftEntries),
            $this->header->pageSize,
            $leaf['headerOffset'],
            $leaf['page'],
            $this->usablePageSize(),
        );
        $pageImages[$newLeafPageNumber] = SQLiteTableLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $rightEntries),
            $this->header->pageSize,
            0,
            str_repeat("\0", $this->header->pageSize),
            $this->usablePageSize(),
        );
        $pageImages = $this->withAssembledWritableTableParentAfterLeafSplit(
            $pageImages,
            $rootPage,
            $parent,
            $leaf['pageNumber'],
            $newLeafPageNumber,
            $leftMaxRowId,
            $allowAppend,
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,headerOffset:int} $leaf
     * @param list<array{rowid:int,cell:string}> $entries
     * @return array<int, string>
     */
    private function withGrownRootWritableTableLeafPage(
        array $pageImages,
        int $rootPage,
        array $leaf,
        array $entries,
        bool $allowAppend,
    ): array {
        if ($leaf['pageNumber'] !== $rootPage) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning can grow only a table leaf root page');
        }

        [$leftEntries, $rightEntries] = $this->partitionWritableTableLeafEntriesForSplit(
            $entries,
            0,
            str_repeat("\0", $this->header->pageSize),
        );
        $leftMaxRowId = $leftEntries[count($leftEntries) - 1]['rowid'];

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(2, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $allocatedPageNumbers = array_values($allocationPlan->allocatedPageNumbers);
        $leftLeafPageNumber = $allocatedPageNumbers[0] ?? null;
        $rightLeafPageNumber = $allocatedPageNumbers[1] ?? null;
        if ($leftLeafPageNumber === null || $rightLeafPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning could not allocate root split leaf pages');
        }

        $pageImages[$leftLeafPageNumber] = SQLiteTableLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $leftEntries),
            $this->header->pageSize,
            0,
            str_repeat("\0", $this->header->pageSize),
            $this->usablePageSize(),
        );
        $pageImages[$rightLeafPageNumber] = SQLiteTableLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $rightEntries),
            $this->header->pageSize,
            0,
            str_repeat("\0", $this->header->pageSize),
            $this->usablePageSize(),
        );
        $pageImages[$rootPage] = SQLiteTableInteriorPage::assemble(
            [SQLiteTableInteriorCell::encode($leftLeafPageNumber, $leftMaxRowId)],
            $rightLeafPageNumber,
            $this->header->pageSize,
            $leaf['headerOffset'],
            $leaf['page'],
            $this->usablePageSize(),
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param list<array{rowid:int,cell:string}> $entries
     * @return array{0:non-empty-list<array{rowid:int,cell:string}>,1:non-empty-list<array{rowid:int,cell:string}>}
     */
    private function partitionWritableTableLeafEntriesForSplit(array $entries, int $headerOffset, string $basePage): array
    {
        $entryCount = count($entries);
        if ($entryCount < 2) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning cannot split fewer than two table rows');
        }

        $best = null;
        $bestScore = null;
        for ($dividerIndex = 1; $dividerIndex <= $entryCount - 1; $dividerIndex++) {
            $leftEntries = array_slice($entries, 0, $dividerIndex);
            $rightEntries = array_slice($entries, $dividerIndex);

            try {
                SQLiteTableLeafPage::assemble(
                    array_map(static fn (array $entry): string => $entry['cell'], $leftEntries),
                    $this->header->pageSize,
                    $headerOffset,
                    $basePage,
                    $this->usablePageSize(),
                );
                SQLiteTableLeafPage::assemble(
                    array_map(static fn (array $entry): string => $entry['cell'], $rightEntries),
                    $this->header->pageSize,
                    0,
                    str_repeat("\0", $this->header->pageSize),
                    $this->usablePageSize(),
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $score = abs(count($leftEntries) - count($rightEntries));
            if ($bestScore === null || $score < $bestScore) {
                $best = [$leftEntries, $rightEntries];
                $bestScore = $score;
            }
        }

        if ($best === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning cannot split these table rows within page capacity');
        }

        return $best;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @return array<int, string>
     */
    private function withAssembledWritableTableParentAfterLeafSplit(
        array $pageImages,
        int $rootPage,
        array $parent,
        int $oldLeafPageNumber,
        int $newLeafPageNumber,
        int $leftMaxRowId,
        bool $allowAppend,
    ): array {
        [$entries, $rightMostPointer] = $this->writableTableParentEntriesAfterLeafSplit(
            $parent,
            $oldLeafPageNumber,
            $newLeafPageNumber,
            $leftMaxRowId,
        );

        try {
            $pageImages[$parent['pageNumber']] = $this->assembleWritableTableInteriorPageFromEntries(
                $entries,
                $rightMostPointer,
                $parent['headerOffset'],
                $parent['page'],
            );

            return $pageImages;
        } catch (\InvalidArgumentException $exception) {
            if (!str_contains($exception->getMessage(), 'split parent table pages')) {
                throw $exception;
            }
            if ($parent['pageNumber'] !== $rootPage) {
                return $this->withSplitWritableTableInteriorParentPage(
                    $pageImages,
                    $rootPage,
                    $parent,
                    $entries,
                    $rightMostPointer,
                    $allowAppend,
                );
            }
        }

        return $this->withGrownRootWritableTableInteriorPage(
            $pageImages,
            $parent,
            $entries,
            $rightMostPointer,
            $allowAppend,
        );
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @param list<array{leftChild:int,key:int}> $entries
     * @return array<int, string>
     */
    private function withSplitWritableTableInteriorParentPage(
        array $pageImages,
        int $rootPage,
        array $parent,
        array $entries,
        int $rightMostPointer,
        bool $allowAppend,
    ): array {
        $grandparent = $parent['parent'] ?? null;
        if ($grandparent === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning cannot split a non-root parent without a grandparent page');
        }
        if ($parent['header']->pageType !== 'table-interior') {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning can split only table interior parent pages');
        }

        [$leftEntries, $dividerEntry, $leftRightMostPointer, $rightEntries, $rightRightMostPointer] =
            $this->partitionWritableTableInteriorEntriesForSplit($entries, $rightMostPointer);

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(1, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $newInteriorPageNumber = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if ($newInteriorPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning could not allocate a split parent table page');
        }

        $pageImages[$parent['pageNumber']] = $this->assembleWritableTableInteriorPageFromEntries(
            $leftEntries,
            $leftRightMostPointer,
            $parent['headerOffset'],
            $parent['page'],
        );
        $pageImages[$newInteriorPageNumber] = $this->assembleWritableTableInteriorPageFromEntries(
            $rightEntries,
            $rightRightMostPointer,
            0,
            str_repeat("\0", $this->header->pageSize),
        );

        return $this->withAssembledWritableTableParentAfterLeafSplit(
            $pageImages,
            $rootPage,
            $grandparent,
            $parent['pageNumber'],
            $newInteriorPageNumber,
            $dividerEntry['key'],
            $allowAppend,
        );
    }

    /**
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @return array{0:list<array{leftChild:int,key:int}>,1:int}
     */
    private function writableTableParentEntriesAfterLeafSplit(
        array $parent,
        int $oldLeafPageNumber,
        int $newLeafPageNumber,
        int $leftMaxRowId,
    ): array {
        $header = $parent['header'];
        if ($header->pageType !== 'table-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning requires a table interior parent page');
        }

        $parentCells = SQLiteTableInteriorCell::parsePageCells($parent['page'], $header);
        $childIndex = $parent['childIndex'];
        if ($childIndex < 0 || $childIndex > count($parentCells)) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning found an invalid parent child slot');
        }
        if ($childIndex === count($parentCells)) {
            if ($header->rightMostPointer !== $oldLeafPageNumber) {
                throw new \InvalidArgumentException('SQLite app_settings table replacement planning parent right-most pointer does not match the split leaf');
            }
        } elseif ($parentCells[$childIndex]->leftChildPage !== $oldLeafPageNumber) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning parent child pointer does not match the split leaf');
        }

        $entries = [];
        foreach ($parentCells as $index => $cell) {
            if ($index === $childIndex) {
                $entries[] = [
                    'leftChild' => $oldLeafPageNumber,
                    'key' => $leftMaxRowId,
                ];
                $entries[] = [
                    'leftChild' => $newLeafPageNumber,
                    'key' => $cell->key,
                ];
                continue;
            }

            $entries[] = [
                'leftChild' => $cell->leftChildPage,
                'key' => $cell->key,
            ];
        }

        $rightMostPointer = $header->rightMostPointer;
        if ($childIndex === count($parentCells)) {
            $entries[] = [
                'leftChild' => $oldLeafPageNumber,
                'key' => $leftMaxRowId,
            ];
            $rightMostPointer = $newLeafPageNumber;
        }

        return [$entries, $rightMostPointer];
    }

    /**
     * @param list<array{leftChild:int,key:int}> $entries
     */
    private function assembleWritableTableInteriorPageFromEntries(
        array $entries,
        int $rightMostPointer,
        int $headerOffset,
        string $basePage,
    ): string {
        try {
            return SQLiteTableInteriorPage::assemble(
                array_map(
                    static fn (array $entry): string => SQLiteTableInteriorCell::encode($entry['leftChild'], $entry['key']),
                    $entries,
                ),
                $rightMostPointer,
                $this->header->pageSize,
                $headerOffset,
                $basePage,
                $this->usablePageSize(),
            );
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), 'overlap')) {
                throw new \InvalidArgumentException(
                    'SQLite app_settings table replacement planning does not yet split parent table pages',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int} $root
     * @param list<array{leftChild:int,key:int}> $entries
     * @return array<int, string>
     */
    private function withGrownRootWritableTableInteriorPage(
        array $pageImages,
        array $root,
        array $entries,
        int $rightMostPointer,
        bool $allowAppend,
    ): array {
        if ($root['header']->pageType !== 'table-interior') {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning can grow only a table interior root page');
        }

        [$leftEntries, $dividerEntry, $leftRightMostPointer, $rightEntries, $rightRightMostPointer] =
            $this->partitionWritableTableInteriorEntriesForSplit($entries, $rightMostPointer);

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(2, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $allocatedPageNumbers = array_values($allocationPlan->allocatedPageNumbers);
        $leftInteriorPageNumber = $allocatedPageNumbers[0] ?? null;
        $rightInteriorPageNumber = $allocatedPageNumbers[1] ?? null;
        if ($leftInteriorPageNumber === null || $rightInteriorPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning could not allocate root split interior pages');
        }

        $pageImages[$leftInteriorPageNumber] = $this->assembleWritableTableInteriorPageFromEntries(
            $leftEntries,
            $leftRightMostPointer,
            0,
            str_repeat("\0", $this->header->pageSize),
        );
        $pageImages[$rightInteriorPageNumber] = $this->assembleWritableTableInteriorPageFromEntries(
            $rightEntries,
            $rightRightMostPointer,
            0,
            str_repeat("\0", $this->header->pageSize),
        );
        $pageImages[$root['pageNumber']] = SQLiteTableInteriorPage::assemble(
            [SQLiteTableInteriorCell::encode($leftInteriorPageNumber, $dividerEntry['key'])],
            $rightInteriorPageNumber,
            $this->header->pageSize,
            $root['headerOffset'],
            $root['page'],
            $this->usablePageSize(),
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param list<array{leftChild:int,key:int}> $entries
     * @return array{0:list<array{leftChild:int,key:int}>,1:array{leftChild:int,key:int},2:int,3:list<array{leftChild:int,key:int}>,4:int}
     */
    private function partitionWritableTableInteriorEntriesForSplit(array $entries, int $rightMostPointer): array
    {
        $entryCount = count($entries);
        if ($entryCount < 3) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning cannot split fewer than three parent table entries');
        }

        $best = null;
        $bestScore = null;
        for ($dividerIndex = 1; $dividerIndex <= $entryCount - 2; $dividerIndex++) {
            $leftEntries = array_slice($entries, 0, $dividerIndex);
            $dividerEntry = $entries[$dividerIndex];
            $rightEntries = array_slice($entries, $dividerIndex + 1);
            $leftRightMostPointer = $dividerEntry['leftChild'];

            try {
                $this->assembleWritableTableInteriorPageFromEntries(
                    $leftEntries,
                    $leftRightMostPointer,
                    0,
                    str_repeat("\0", $this->header->pageSize),
                );
                $this->assembleWritableTableInteriorPageFromEntries(
                    $rightEntries,
                    $rightMostPointer,
                    0,
                    str_repeat("\0", $this->header->pageSize),
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $score = abs(count($leftEntries) - count($rightEntries));
            if ($bestScore === null || $score < $bestScore) {
                $best = [$leftEntries, $dividerEntry, $leftRightMostPointer, $rightEntries, $rightMostPointer];
                $bestScore = $score;
            }
        }

        if ($best === null) {
            throw new \InvalidArgumentException('SQLite app_settings table replacement planning cannot split these parent table entries within page capacity');
        }

        return $best;
    }

    /**
     * @return list<array{record:SQLiteSchemaRecord,columns:non-empty-list<SQLiteIndexColumn>,values:list<mixed>}>
     */
    private function supportedKeyValueRowIndexesForInsert(string $keyName, ?string $loadPolicy): array
    {
        $indexes = [];
        $automaticIndexColumns = null;
        $automaticIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable(SQLiteKeyValueRow::TABLE_NAME) as $record) {
            $columns = $this->applicationWriteIndexColumns(
                $record,
                $automaticIndexColumns,
                $automaticIndexOrdinal,
                'insert',
            );

            if (
                count($columns) === 1
                && strcasecmp($columns[0]->columnName, SQLiteKeyValueRow::KEY_COLUMN) === 0
            ) {
                $indexValues = [$keyName];
            } elseif (
                count($columns) === 2
                && strcasecmp($columns[0]->columnName, SQLiteKeyValueRow::LOAD_POLICY_COLUMN) === 0
                && strcasecmp($columns[1]->columnName, SQLiteKeyValueRow::KEY_COLUMN) === 0
            ) {
                $indexValues = [$loadPolicy, $keyName];
            } else {
                throw new \InvalidArgumentException('SQLite app_settings insert planning currently supports only key_name or load_policy, key_name indexes');
            }

            self::assertSupportedApplicationWriteIndexColumns($columns);
            self::assertSupportedApplicationWriteIndexPartialPredicate($columns[0]);

            $indexes[] = [
                'record' => $record,
                'columns' => $columns,
                'values' => $indexValues,
            ];
        }

        return $indexes;
    }

    /**
     * @return list<array{record:SQLiteSchemaRecord,columns:non-empty-list<SQLiteIndexColumn>}>
     */
    private function supportedKeyValueRowIndexesForReplacement(): array
    {
        $indexes = [];
        $automaticIndexColumns = null;
        $automaticIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable(SQLiteKeyValueRow::TABLE_NAME) as $record) {
            $columns = $this->applicationWriteIndexColumns(
                $record,
                $automaticIndexColumns,
                $automaticIndexOrdinal,
                'replacement',
            );

            $isSupported = (
                count($columns) === 1
                && strcasecmp($columns[0]->columnName, SQLiteKeyValueRow::KEY_COLUMN) === 0
            ) || (
                count($columns) === 2
                && strcasecmp($columns[0]->columnName, SQLiteKeyValueRow::LOAD_POLICY_COLUMN) === 0
                && strcasecmp($columns[1]->columnName, SQLiteKeyValueRow::KEY_COLUMN) === 0
            );
            if (!$isSupported) {
                throw new \InvalidArgumentException('SQLite app_settings replacement planning currently supports only key_name or load_policy, key_name indexes');
            }

            self::assertSupportedApplicationWriteIndexColumns($columns);
            self::assertSupportedApplicationWriteIndexPartialPredicate($columns[0]);

            $indexes[] = [
                'record' => $record,
                'columns' => $columns,
            ];
        }

        return $indexes;
    }

    /**
     * @param null|list<non-empty-list<SQLiteIndexColumn>> $automaticIndexColumns
     * @return non-empty-list<SQLiteIndexColumn>
     */
    private function applicationWriteIndexColumns(
        SQLiteSchemaRecord $record,
        ?array &$automaticIndexColumns,
        int &$automaticIndexOrdinal,
        string $operation,
    ): array {
        if ($record->sql !== null) {
            $columns = SQLiteCreateIndex::columns($record->sql);
            if ($columns === null) {
                throw new \InvalidArgumentException("SQLite app_settings {$operation} planning currently supports only ordinary column indexes");
            }

            return $columns;
        }

        if (!self::isAutomaticIndex($record, SQLiteKeyValueRow::TABLE_NAME)) {
            throw new \InvalidArgumentException("SQLite app_settings {$operation} planning currently supports only explicit or automatic key_name indexes");
        }

        if ($automaticIndexColumns === null) {
            $automaticIndexColumns = $this->automaticIndexColumnsForTable(SQLiteKeyValueRow::TABLE_NAME);
        }
        $columns = $automaticIndexColumns[$automaticIndexOrdinal] ?? null;
        $automaticIndexOrdinal++;
        if ($columns === null) {
            throw new \InvalidArgumentException("SQLite app_settings {$operation} planning cannot infer automatic index columns from CREATE TABLE");
        }

        return $columns;
    }

    /**
     * @param non-empty-list<SQLiteIndexColumn> $columns
     */
    private static function assertSupportedApplicationWriteIndexColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (!in_array(strtoupper($column->collation), ['BINARY', 'NOCASE', 'RTRIM'], true)) {
                throw new \InvalidArgumentException('SQLite app_settings write planning does not yet maintain custom-collation indexes');
            }
        }
    }

    private static function assertSupportedApplicationWriteIndexPartialPredicate(SQLiteIndexColumn $column): void
    {
        if (
            $column->partial
            && (
                $column->partialPredicate === null
                || !self::partialPredicateCoversAllKeyValueNameRows($column->partialPredicate)
            )
        ) {
            throw new \InvalidArgumentException('SQLite app_settings write planning currently supports only key_name IS NOT NULL partial indexes');
        }
    }

    private static function partialPredicateCoversAllKeyValueNameRows(SQLiteIndexPredicate $predicate): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL) {
            return strcasecmp($predicate->columnName, SQLiteKeyValueRow::KEY_COLUMN) === 0;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if ($subPredicate instanceof SQLiteIndexPredicate && self::partialPredicateCoversAllKeyValueNameRows($subPredicate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array{record:SQLiteSchemaRecord,columns:non-empty-list<SQLiteIndexColumn>,values:list<mixed>}> $indexes
     * @param array<int, string> $pageImages
     * @return array<int, string>
     */
    private function withKeyValueRowIndexInsertPages(
        array $pageImages,
        array $indexes,
        int $rowId,
        bool $allowAppend,
    ): array {
        foreach ($indexes as $index) {
            $record = $index['record'];
            $columns = $index['columns'];
            $indexValues = $index['values'];
            $rootPage = $record->rootPage;
            if ($rootPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings index root page is missing');
            }

            $newEntryValues = array_merge($indexValues, [$rowId]);
            $newPayload = SQLiteRecord::encode($newEntryValues, $this->header->textEncoding);
            if (SQLiteIndexCell::localPayloadLength(strlen($newPayload), $this->usablePageSize()) !== strlen($newPayload)) {
                throw new \InvalidArgumentException('SQLite app_settings indexed insert planning does not yet allocate index overflow pages');
            }
            $this->assertIndexDoesNotContainRowId($rootPage, $rowId);

            $leaf = $this->writableIndexLeafForEntry($rootPage, $newEntryValues, $columns);
            $entries = $this->writableIndexLeafEntries($leaf['page'], $leaf['header'], $columns);
            $entries[] = [
                'values' => $newEntryValues,
                'cell' => SQLiteIndexCell::encode($newPayload, $this->usablePageSize()),
            ];

            usort(
                $entries,
                fn (array $left, array $right): int => $this->compareApplicationIndexEntryValues(
                    $left['values'],
                    $right['values'],
                    $columns,
                ),
            );

            $pageImages = $this->withAssembledWritableIndexLeafPage(
                $pageImages,
                $rootPage,
                $leaf,
                $entries,
                $columns,
                $allowAppend,
            );
        }

        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param list<array{record:SQLiteSchemaRecord,columns:non-empty-list<SQLiteIndexColumn>}> $indexes
     * @param array<int, string> $pageImages
     * @return array<int, string>
     */
    private function withKeyValueRowIndexReplacementPages(
        array $pageImages,
        array $indexes,
        int $rowId,
        string $keyName,
        ?string $oldLoadPolicy,
        ?string $newLoadPolicy,
        bool $allowAppend,
        array &$obsoleteOverflowPageNumbers,
    ): array
    {
        foreach ($indexes as $index) {
            $rootPage = $index['record']->rootPage;
            if ($rootPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings index root page is missing');
            }

            $columns = $index['columns'];
            $oldValues = self::keyValueRowIndexValuesForColumns($columns, $keyName, $oldLoadPolicy);
            $newValues = self::keyValueRowIndexValuesForColumns($columns, $keyName, $newLoadPolicy);
            $keyColumnCount = count($columns);
            $mutatesKey = $oldValues !== $newValues;

            $oldEntryValues = array_merge($oldValues, [$rowId]);
            $oldLeaf = $this->writableIndexLeafForEntry($rootPage, $oldEntryValues, $columns, true);
            $entries = [];
            $found = false;
            foreach ($this->writableIndexLeafEntries($oldLeaf['page'], $oldLeaf['header'], $columns) as $entry) {
                $indexRowId = $entry['values'][$keyColumnCount] ?? null;
                if (
                    $indexRowId === $rowId
                    && self::keyValueRowIndexValuesMatchColumns(
                        array_slice($entry['values'], 0, $keyColumnCount),
                        $oldValues,
                        $columns,
                    )
                ) {
                    $found = true;
                    if ($mutatesKey) {
                        if ($entry['firstOverflowPage'] !== null) {
                            $obsoleteOverflowPageNumbers = array_values(array_unique(array_merge(
                                $obsoleteOverflowPageNumbers,
                                $this->overflowPageNumbers(
                                    $entry['firstOverflowPage'],
                                    $entry['payloadLength'] - $entry['localPayloadLength'],
                                ),
                            )));
                        }
                        continue;
                    }
                } elseif ($indexRowId === $rowId) {
                    throw new \InvalidArgumentException("SQLite app_settings index does not reference rowid {$rowId} with the expected key");
                }

                $entries[] = $entry;
            }

            if (!$found) {
                throw new \InvalidArgumentException("SQLite app_settings index does not reference rowid {$rowId}");
            }

            if (!$mutatesKey) {
                continue;
            }

            $newEntryValues = array_merge($newValues, [$rowId]);
            $newPayload = SQLiteRecord::encode($newEntryValues, $this->header->textEncoding);
            if (SQLiteIndexCell::localPayloadLength(strlen($newPayload), $this->usablePageSize()) !== strlen($newPayload)) {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning does not yet allocate index overflow pages');
            }

            $newLeaf = $this->writableIndexLeafForEntry($rootPage, $newEntryValues, $columns);
            if ($newLeaf['pageNumber'] !== $oldLeaf['pageNumber']) {
                if ($entries === []) {
                    $pageImages = $this->withCollapsedRootIndexAfterEmptyReplacementLeaf(
                        $pageImages,
                        $rootPage,
                        $oldLeaf,
                        $newLeaf,
                        $oldValues,
                        $newEntryValues,
                        $newPayload,
                        $columns,
                    );
                    continue;
                }

                $pageImages = $this->withRebalancedWritableIndexSourceLeafAfterDeletion(
                    $pageImages,
                    $rootPage,
                    $oldLeaf,
                    $entries,
                    $columns,
                );
                $workingDatabase = $this->withPageImages($pageImages);
                $newLeaf = $workingDatabase->writableIndexLeafForEntry($rootPage, $newEntryValues, $columns);
                $entries = $workingDatabase->writableIndexLeafEntries($newLeaf['page'], $newLeaf['header'], $columns);
                foreach ($entries as $entry) {
                    if (($entry['values'][$keyColumnCount] ?? null) === $rowId) {
                        throw new \InvalidArgumentException("SQLite app_settings index already contains rowid {$rowId}");
                    }
                }
            }

            $entries[] = [
                'values' => $newEntryValues,
                'cell' => SQLiteIndexCell::encode($newPayload, $this->usablePageSize()),
            ];

            usort(
                $entries,
                fn (array $left, array $right): int => $this->compareApplicationIndexEntryValues(
                    $left['values'],
                    $right['values'],
                    $columns,
                ),
            );

            $pageImages = $this->withPageImages($pageImages)->withAssembledWritableIndexLeafPage(
                $pageImages,
                $rootPage,
                $newLeaf,
                $entries,
                $columns,
                $allowAppend,
            );
        }

        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $leaf
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withRebalancedWritableIndexSourceLeafAfterDeletion(
        array $pageImages,
        int $rootPage,
        array $leaf,
        array $entries,
        array $columns,
    ): array {
        $leafPage = $this->assembleWritableIndexLeafPage($entries, $leaf['headerOffset'], $leaf['page']);
        $leafHeader = SQLiteBTreePageHeader::parsePage(
            $leafPage,
            $this->header->pageSize,
            $leaf['headerOffset'],
        );
        if ($leafHeader->freeSpaceBytes($leafPage, $this->usablePageSize()) * 3 <= $this->usablePageSize() * 2) {
            $pageImages[$leaf['pageNumber']] = $leafPage;

            return $pageImages;
        }

        $parent = $leaf['parent'];
        if (
            $parent === null
            || $parent['header']->pageType !== 'index-interior'
            || $parent['header']->cellCount < 2
        ) {
            $pageImages[$leaf['pageNumber']] = $leafPage;

            return $pageImages;
        }

        try {
            return $this->withRedistributedWritableIndexLeafSiblingsAfterDeletion(
                $pageImages,
                $parent,
                $leaf,
                $entries,
                $columns,
            );
        } catch (\InvalidArgumentException) {
            try {
                return $this->withMergedWritableIndexLeafSiblingAfterDeletion(
                    $pageImages,
                    $rootPage,
                    $parent,
                    $leaf,
                    $entries,
                    $columns,
                );
            } catch (\InvalidArgumentException) {
                $pageImages[$leaf['pageNumber']] = $leafPage;

                return $pageImages;
            }
        }
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $leaf
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withRedistributedWritableIndexLeafSiblingsAfterDeletion(
        array $pageImages,
        array $parent,
        array $leaf,
        array $entries,
        array $columns,
    ): array {
        $workingDatabase = $this->withPageImages($pageImages);
        $parentPage = $workingDatabase->page($parent['pageNumber']);
        $parentHeader = SQLiteBTreePageHeader::parsePage(
            $parentPage,
            $this->header->pageSize,
            $parent['headerOffset'],
        );
        if ($parentHeader->pageType !== 'index-interior' || $parentHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning requires an index interior parent page');
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $workingDatabase->readOverflowPayload($firstOverflowPage, $byteCount);
        $parentCells = SQLiteIndexCell::parsePageCells($parentPage, $parentHeader, $workingDatabase->usablePageSize(), $overflowReader);
        $childPages = [];
        $parentEntries = [];
        foreach ($parentCells as $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found a parent cell without a child pointer');
            }
            $childPages[] = $cell->leftChildPage;
            $parentEntries[] = [
                'values' => $workingDatabase->indexEntryValuesForColumns($cell, count($columns)),
                'payload' => $cell->payload,
                'leftChild' => $cell->leftChildPage,
            ];
        }
        $childPages[] = $parentHeader->rightMostPointer;

        $childIndex = $parent['childIndex'];
        if (
            $childIndex < 0
            || $childIndex >= count($childPages)
            || $childPages[$childIndex] !== $leaf['pageNumber']
        ) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found an invalid source leaf slot');
        }

        $siblingIndex = $childIndex > 0 ? $childIndex - 1 : $childIndex + 1;
        if (!isset($childPages[$siblingIndex])) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning needs an adjacent sibling leaf');
        }
        $dividerIndex = min($childIndex, $siblingIndex);
        if (!isset($parentEntries[$dividerIndex])) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found a missing sibling divider');
        }

        $siblingPageNumber = $childPages[$siblingIndex];
        $siblingPage = $workingDatabase->page($siblingPageNumber);
        $siblingHeader = SQLiteBTreePageHeader::parsePage($siblingPage, $this->header->pageSize, $siblingPageNumber === 1 ? 100 : 0);
        if ($siblingHeader->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can redistribute only leaf siblings');
        }

        $siblingEntries = $workingDatabase->writableIndexLeafEntries($siblingPage, $siblingHeader, $columns);
        if ($siblingIndex < $childIndex) {
            $leftPageNumber = $siblingPageNumber;
            $leftPage = $siblingPage;
            $leftHeaderOffset = $siblingPageNumber === 1 ? 100 : 0;
            $leftEntries = $siblingEntries;
            $rightPageNumber = $leaf['pageNumber'];
            $rightPage = $leaf['page'];
            $rightHeaderOffset = $leaf['headerOffset'];
            $rightEntries = $entries;
        } else {
            $leftPageNumber = $leaf['pageNumber'];
            $leftPage = $leaf['page'];
            $leftHeaderOffset = $leaf['headerOffset'];
            $leftEntries = $entries;
            $rightPageNumber = $siblingPageNumber;
            $rightPage = $siblingPage;
            $rightHeaderOffset = $siblingPageNumber === 1 ? 100 : 0;
            $rightEntries = $siblingEntries;
        }

        $dividerEntry = [
            'values' => $parentEntries[$dividerIndex]['values'],
            'cell' => SQLiteIndexCell::encode($parentEntries[$dividerIndex]['payload'], $workingDatabase->usablePageSize()),
        ];
        $combinedEntries = array_merge($leftEntries, [$dividerEntry], $rightEntries);
        usort(
            $combinedEntries,
            fn (array $left, array $right): int => $workingDatabase->compareApplicationIndexEntryValues(
                $left['values'],
                $right['values'],
                $columns,
            ),
        );

        [$newLeftEntries, $newDividerEntry, $newRightEntries] =
            $workingDatabase->partitionWritableIndexLeafEntriesForRedistribution(
                $combinedEntries,
                $leftHeaderOffset,
                $leftPage,
                $rightHeaderOffset,
                $rightPage,
            );

        $newDividerPayload = SQLiteRecord::encode($newDividerEntry['values'], $this->header->textEncoding);
        $parentEntries[$dividerIndex] = [
            'values' => $newDividerEntry['values'],
            'payload' => $newDividerPayload,
            'leftChild' => $childPages[$dividerIndex],
        ];

        $pageImages[$leftPageNumber] = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $newLeftEntries),
            $this->header->pageSize,
            $leftHeaderOffset,
            $leftPage,
            $workingDatabase->usablePageSize(),
        );
        $pageImages[$rightPageNumber] = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $newRightEntries),
            $this->header->pageSize,
            $rightHeaderOffset,
            $rightPage,
            $workingDatabase->usablePageSize(),
        );
        $pageImages[$parent['pageNumber']] = $workingDatabase->assembleWritableIndexInteriorPageFromEntries(
            $parentEntries,
            $parentHeader->rightMostPointer,
            $parent['headerOffset'],
            $parentPage,
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $leaf
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withMergedWritableIndexLeafSiblingAfterDeletion(
        array $pageImages,
        int $rootPage,
        array $parent,
        array $leaf,
        array $entries,
        array $columns,
    ): array {
        $workingDatabase = $this->withPageImages($pageImages);
        $parentPage = $workingDatabase->page($parent['pageNumber']);
        $parentHeader = SQLiteBTreePageHeader::parsePage(
            $parentPage,
            $this->header->pageSize,
            $parent['headerOffset'],
        );
        if ($parentHeader->pageType !== 'index-interior' || $parentHeader->rightMostPointer === null || $parentHeader->cellCount < 2) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning requires a mergeable index interior parent');
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $workingDatabase->readOverflowPayload($firstOverflowPage, $byteCount);
        $parentCells = SQLiteIndexCell::parsePageCells($parentPage, $parentHeader, $workingDatabase->usablePageSize(), $overflowReader);
        $childPages = [];
        $parentEntries = [];
        foreach ($parentCells as $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found a parent cell without a child pointer');
            }
            $childPages[] = $cell->leftChildPage;
            $parentEntries[] = [
                'values' => $workingDatabase->indexEntryValuesForColumns($cell, count($columns)),
                'payload' => $cell->payload,
                'leftChild' => $cell->leftChildPage,
            ];
        }
        $childPages[] = $parentHeader->rightMostPointer;

        $childIndex = $parent['childIndex'];
        if (
            $childIndex < 0
            || $childIndex >= count($childPages)
            || $childPages[$childIndex] !== $leaf['pageNumber']
        ) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found an invalid source leaf slot');
        }

        $siblingIndex = $childIndex > 0 ? $childIndex - 1 : $childIndex + 1;
        if (!isset($childPages[$siblingIndex])) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning needs an adjacent sibling leaf to merge');
        }

        $leftIndex = min($childIndex, $siblingIndex);
        $rightIndex = max($childIndex, $siblingIndex);
        if (!isset($parentEntries[$leftIndex])) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found a missing sibling divider');
        }

        $siblingPageNumber = $childPages[$siblingIndex];
        $siblingPage = $workingDatabase->page($siblingPageNumber);
        $siblingHeaderOffset = $siblingPageNumber === 1 ? 100 : 0;
        $siblingHeader = SQLiteBTreePageHeader::parsePage(
            $siblingPage,
            $this->header->pageSize,
            $siblingHeaderOffset,
        );
        if ($siblingHeader->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can merge only leaf siblings');
        }

        $siblingEntries = $workingDatabase->writableIndexLeafEntries($siblingPage, $siblingHeader, $columns);
        if ($siblingIndex < $childIndex) {
            $leftPageNumber = $siblingPageNumber;
            $leftPage = $siblingPage;
            $leftHeaderOffset = $siblingHeaderOffset;
            $leftEntries = $siblingEntries;
            $rightPageNumber = $leaf['pageNumber'];
            $rightEntries = $entries;
        } else {
            $leftPageNumber = $leaf['pageNumber'];
            $leftPage = $leaf['page'];
            $leftHeaderOffset = $leaf['headerOffset'];
            $leftEntries = $entries;
            $rightPageNumber = $siblingPageNumber;
            $rightEntries = $siblingEntries;
        }

        $dividerEntry = [
            'values' => $parentEntries[$leftIndex]['values'],
            'cell' => SQLiteIndexCell::encode($parentEntries[$leftIndex]['payload'], $workingDatabase->usablePageSize()),
        ];
        $mergedEntries = array_merge($leftEntries, [$dividerEntry], $rightEntries);
        usort(
            $mergedEntries,
            fn (array $left, array $right): int => $workingDatabase->compareApplicationIndexEntryValues(
                $left['values'],
                $right['values'],
                $columns,
            ),
        );

        $mergedLeafPage = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $mergedEntries),
            $this->header->pageSize,
            $leftHeaderOffset,
            $leftPage,
            $workingDatabase->usablePageSize(),
        );

        $newChildPages = $childPages;
        array_splice($newChildPages, $rightIndex, 1);
        $newParentEntries = $parentEntries;
        array_splice($newParentEntries, $leftIndex, 1);
        if (count($newChildPages) !== count($newParentEntries) + 1) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning produced an invalid merged parent shape');
        }
        foreach ($newParentEntries as $index => $entry) {
            $newParentEntries[$index]['leftChild'] = $newChildPages[$index];
        }
        $rightMostPointer = $newChildPages[array_key_last($newChildPages)] ?? null;
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning lost the parent right-most pointer');
        }

        if (($parent['parent'] ?? null) !== null && count($newParentEntries) < 3) {
            return $workingDatabase->withCollapsedRootIndexAfterNonRootParentUnderflow(
                $pageImages,
                $rootPage,
                $parent,
                $newParentEntries,
                $rightMostPointer,
                $leftPageNumber,
                $mergedLeafPage,
                $rightPageNumber,
                $columns,
            );
        }

        $newParentPage = $workingDatabase->assembleWritableIndexInteriorPageFromEntries(
            $newParentEntries,
            $rightMostPointer,
            $parent['headerOffset'],
            $parentPage,
        );

        $freePlan = $workingDatabase->planPageFreeList([$rightPageNumber]);
        foreach ($freePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $pageImages[$leftPageNumber] = $mergedLeafPage;
        $pageImages[$parent['pageNumber']] = $newParentPage;
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $underfilledParent
     * @param list<array{values:list<mixed>,payload:string,leftChild:int}> $underfilledEntries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withCollapsedRootIndexAfterNonRootParentUnderflow(
        array $pageImages,
        int $rootPage,
        array $underfilledParent,
        array $underfilledEntries,
        int $underfilledRightMostPointer,
        int $mergedLeafPageNumber,
        string $mergedLeafPage,
        int $obsoleteLeafPageNumber,
        array $columns,
    ): array {
        $rootContext = $underfilledParent['parent'] ?? null;
        if (
            $rootContext === null
            || ($rootContext['parent'] ?? null) !== null
            || $rootContext['pageNumber'] !== $rootPage
            || $rootContext['header']->pageType !== 'index-interior'
        ) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can collapse only a root parent underflow');
        }

        $rootPageBytes = $this->page($rootPage);
        $rootHeader = SQLiteBTreePageHeader::parsePage(
            $rootPageBytes,
            $this->header->pageSize,
            $rootContext['headerOffset'],
        );
        if ($rootHeader->pageType !== 'index-interior' || $rootHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning requires an index-interior root');
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $rootCells = SQLiteIndexCell::parsePageCells($rootPageBytes, $rootHeader, $this->usablePageSize(), $overflowReader);
        $rootEntries = [];
        $rootChildPages = [];
        foreach ($rootCells as $rootCell) {
            if ($rootCell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found an invalid root divider');
            }
            $rootChildPages[] = $rootCell->leftChildPage;
            $rootEntries[] = [
                'values' => $this->indexEntryValuesForColumns($rootCell, count($columns)),
                'payload' => $rootCell->payload,
                'leftChild' => $rootCell->leftChildPage,
            ];
        }
        $rootChildPages[] = $rootHeader->rightMostPointer;

        $rootChildIndex = $rootContext['childIndex'];
        if (
            $rootChildIndex < 0
            || $rootChildIndex >= count($rootChildPages)
            || $rootChildPages[$rootChildIndex] !== $underfilledParent['pageNumber']
        ) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found an invalid underfilled parent slot');
        }

        if ($rootHeader->cellCount !== 1) {
            return $this->withMergedRootIndexChildParentsAfterNonRootParentUnderflow(
                $pageImages,
                $rootPageBytes,
                $rootContext,
                $rootEntries,
                $rootChildPages,
                $rootChildIndex,
                $underfilledParent,
                $underfilledEntries,
                $underfilledRightMostPointer,
                $mergedLeafPageNumber,
                $mergedLeafPage,
                $obsoleteLeafPageNumber,
                $columns,
            );
        }

        $siblingIndex = $rootChildIndex === 0 ? 1 : 0;
        $siblingPageNumber = $rootChildPages[$siblingIndex];
        $siblingPage = $this->page($siblingPageNumber);
        $siblingHeaderOffset = $siblingPageNumber === 1 ? 100 : 0;
        $siblingHeader = SQLiteBTreePageHeader::parsePage(
            $siblingPage,
            $this->header->pageSize,
            $siblingHeaderOffset,
        );
        if ($siblingHeader->pageType !== 'index-interior' || $siblingHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can collapse only sibling index-interior parents');
        }

        $siblingCells = SQLiteIndexCell::parsePageCells($siblingPage, $siblingHeader, $this->usablePageSize(), $overflowReader);
        $siblingEntries = [];
        foreach ($siblingCells as $siblingCell) {
            if ($siblingCell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found a sibling parent cell without a child pointer');
            }
            $siblingEntries[] = [
                'values' => $this->indexEntryValuesForColumns($siblingCell, count($columns)),
                'payload' => $siblingCell->payload,
                'leftChild' => $siblingCell->leftChildPage,
            ];
        }

        $rootDivider = $rootEntries[0] ?? null;
        if ($rootDivider === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found an invalid root divider');
        }

        $rootDividerEntry = [
            'values' => $rootDivider['values'],
            'payload' => $rootDivider['payload'],
            'leftChild' => $rootChildIndex === 0 ? $underfilledRightMostPointer : $siblingHeader->rightMostPointer,
        ];

        if ($rootChildIndex === 0) {
            $collapsedEntries = array_merge($underfilledEntries, [$rootDividerEntry], $siblingEntries);
            $collapsedRightMostPointer = $siblingHeader->rightMostPointer;
        } else {
            $collapsedEntries = array_merge($siblingEntries, [$rootDividerEntry], $underfilledEntries);
            $collapsedRightMostPointer = $underfilledRightMostPointer;
        }

        $collapsedRootPage = $this->assembleWritableIndexInteriorPageFromEntries(
            $collapsedEntries,
            $collapsedRightMostPointer,
            $rootContext['headerOffset'],
            $rootPageBytes,
        );

        $freePlan = $this->planPageFreeList(array_values(array_unique([
            $obsoleteLeafPageNumber,
            $underfilledParent['pageNumber'],
            $siblingPageNumber,
        ])));
        foreach ($freePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $pageImages[$mergedLeafPageNumber] = $mergedLeafPage;
        $pageImages[$rootPage] = $collapsedRootPage;
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $rootContext
     * @param non-empty-list<array{values:list<mixed>,payload:string,leftChild:int}> $rootEntries
     * @param non-empty-list<int> $rootChildPages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $underfilledParent
     * @param list<array{values:list<mixed>,payload:string,leftChild:int}> $underfilledEntries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withMergedRootIndexChildParentsAfterNonRootParentUnderflow(
        array $pageImages,
        string $rootPageBytes,
        array $rootContext,
        array $rootEntries,
        array $rootChildPages,
        int $rootChildIndex,
        array $underfilledParent,
        array $underfilledEntries,
        int $underfilledRightMostPointer,
        int $mergedLeafPageNumber,
        string $mergedLeafPage,
        int $obsoleteLeafPageNumber,
        array $columns,
    ): array {
        $siblingIndex = $rootChildIndex > 0 ? $rootChildIndex - 1 : $rootChildIndex + 1;
        if (!isset($rootChildPages[$siblingIndex])) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning needs an adjacent root child parent');
        }

        $leftRootChildIndex = min($rootChildIndex, $siblingIndex);
        $rightRootChildIndex = max($rootChildIndex, $siblingIndex);
        $rootDivider = $rootEntries[$leftRootChildIndex] ?? null;
        if ($rootDivider === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found a missing root divider');
        }

        $siblingPageNumber = $rootChildPages[$siblingIndex];
        $siblingPage = $this->page($siblingPageNumber);
        $siblingHeaderOffset = $siblingPageNumber === 1 ? 100 : 0;
        $siblingHeader = SQLiteBTreePageHeader::parsePage(
            $siblingPage,
            $this->header->pageSize,
            $siblingHeaderOffset,
        );
        if ($siblingHeader->pageType !== 'index-interior' || $siblingHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can merge only sibling index-interior parents');
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $siblingCells = SQLiteIndexCell::parsePageCells($siblingPage, $siblingHeader, $this->usablePageSize(), $overflowReader);
        $siblingEntries = [];
        foreach ($siblingCells as $siblingCell) {
            if ($siblingCell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found a sibling parent cell without a child pointer');
            }
            $siblingEntries[] = [
                'values' => $this->indexEntryValuesForColumns($siblingCell, count($columns)),
                'payload' => $siblingCell->payload,
                'leftChild' => $siblingCell->leftChildPage,
            ];
        }

        if ($rootChildIndex < $siblingIndex) {
            $leftPageNumber = $underfilledParent['pageNumber'];
            $leftPage = $underfilledParent['page'];
            $leftHeaderOffset = $underfilledParent['headerOffset'];
            $leftEntries = $underfilledEntries;
            $leftRightMostPointer = $underfilledRightMostPointer;
            $rightPageNumber = $siblingPageNumber;
            $rightEntries = $siblingEntries;
            $rightRightMostPointer = $siblingHeader->rightMostPointer;
        } else {
            $leftPageNumber = $siblingPageNumber;
            $leftPage = $siblingPage;
            $leftHeaderOffset = $siblingHeaderOffset;
            $leftEntries = $siblingEntries;
            $leftRightMostPointer = $siblingHeader->rightMostPointer;
            $rightPageNumber = $underfilledParent['pageNumber'];
            $rightEntries = $underfilledEntries;
            $rightRightMostPointer = $underfilledRightMostPointer;
        }

        $dividerEntry = [
            'values' => $rootDivider['values'],
            'payload' => $rootDivider['payload'],
            'leftChild' => $leftRightMostPointer,
        ];
        $mergedParentEntries = array_merge($leftEntries, [$dividerEntry], $rightEntries);
        $mergedParentPage = $this->assembleWritableIndexInteriorPageFromEntries(
            $mergedParentEntries,
            $rightRightMostPointer,
            $leftHeaderOffset,
            $leftPage,
        );

        $newRootChildPages = $rootChildPages;
        $newRootChildPages[$leftRootChildIndex] = $leftPageNumber;
        array_splice($newRootChildPages, $rightRootChildIndex, 1);
        $newRootEntries = $rootEntries;
        array_splice($newRootEntries, $leftRootChildIndex, 1);
        if (count($newRootChildPages) !== count($newRootEntries) + 1) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning produced an invalid merged root shape');
        }
        foreach ($newRootEntries as $index => $entry) {
            $newRootEntries[$index]['leftChild'] = $newRootChildPages[$index];
        }
        $newRootRightMostPointer = $newRootChildPages[array_key_last($newRootChildPages)] ?? null;
        if (!is_int($newRootRightMostPointer)) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning lost the root right-most pointer');
        }

        $newRootPage = $this->assembleWritableIndexInteriorPageFromEntries(
            $newRootEntries,
            $newRootRightMostPointer,
            $rootContext['headerOffset'],
            $rootPageBytes,
        );

        $freePlan = $this->withPageImages($pageImages)->planPageFreeList(array_values(array_unique([
            $obsoleteLeafPageNumber,
            $rightPageNumber,
        ])));
        foreach ($freePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $pageImages[$mergedLeafPageNumber] = $mergedLeafPage;
        $pageImages[$leftPageNumber] = $mergedParentPage;
        $pageImages[$rootContext['pageNumber']] = $newRootPage;
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @return array{0:list<array{values:list<mixed>,cell:string}>,1:array{values:list<mixed>,cell:string},2:list<array{values:list<mixed>,cell:string}>}
     */
    private function partitionWritableIndexLeafEntriesForRedistribution(
        array $entries,
        int $leftHeaderOffset,
        string $leftBasePage,
        int $rightHeaderOffset,
        string $rightBasePage,
    ): array {
        $entryCount = count($entries);
        if ($entryCount < 3) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning cannot redistribute fewer than three index entries');
        }

        $best = null;
        $bestScore = null;
        for ($dividerIndex = 1; $dividerIndex <= $entryCount - 2; $dividerIndex++) {
            $leftEntries = array_slice($entries, 0, $dividerIndex);
            $dividerEntry = $entries[$dividerIndex];
            $rightEntries = array_slice($entries, $dividerIndex + 1);

            try {
                $leftPage = SQLiteIndexLeafPage::assemble(
                    array_map(static fn (array $entry): string => $entry['cell'], $leftEntries),
                    $this->header->pageSize,
                    $leftHeaderOffset,
                    $leftBasePage,
                    $this->usablePageSize(),
                );
                $rightPage = SQLiteIndexLeafPage::assemble(
                    array_map(static fn (array $entry): string => $entry['cell'], $rightEntries),
                    $this->header->pageSize,
                    $rightHeaderOffset,
                    $rightBasePage,
                    $this->usablePageSize(),
                );
                $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $this->header->pageSize, $leftHeaderOffset);
                $rightHeader = SQLiteBTreePageHeader::parsePage($rightPage, $this->header->pageSize, $rightHeaderOffset);
                if (
                    $leftHeader->freeSpaceBytes($leftPage, $this->usablePageSize()) * 3 > $this->usablePageSize() * 2
                    || $rightHeader->freeSpaceBytes($rightPage, $this->usablePageSize()) * 3 > $this->usablePageSize() * 2
                ) {
                    continue;
                }
            } catch (\InvalidArgumentException) {
                continue;
            }

            $score = abs(count($leftEntries) - count($rightEntries));
            if ($bestScore === null || $score < $bestScore) {
                $best = [$leftEntries, $dividerEntry, $rightEntries];
                $bestScore = $score;
            }
        }

        if ($best === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning cannot redistribute these index leaf entries within page capacity');
        }

        return $best;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $oldLeaf
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $newLeaf
     * @param list<mixed> $oldValues
     * @param list<mixed> $newEntryValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withCollapsedRootIndexAfterEmptyReplacementLeaf(
        array $pageImages,
        int $rootPage,
        array $oldLeaf,
        array $newLeaf,
        array $oldValues,
        array $newEntryValues,
        string $newPayload,
        array $columns,
    ): array {
        $oldParent = $oldLeaf['parent'];
        $newParent = $newLeaf['parent'];
        if (
            $oldParent === null
            || $newParent === null
            || ($oldParent['parent'] ?? null) !== null
            || ($newParent['parent'] ?? null) !== null
            || $oldParent['pageNumber'] !== $rootPage
            || $newParent['pageNumber'] !== $rootPage
            || $oldParent['pageNumber'] !== $newParent['pageNumber']
            || $oldParent['header']->pageType !== 'index-interior'
            || $newParent['header']->pageType !== 'index-interior'
        ) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can collapse only empty leaves directly below an index root');
        }

        $workingDatabase = $this->withPageImages($pageImages);
        $rootPageBytes = $workingDatabase->page($rootPage);
        $rootHeader = SQLiteBTreePageHeader::parsePage(
            $rootPageBytes,
            $this->header->pageSize,
            $rootPage === 1 ? 100 : 0,
        );
        if ($rootHeader->pageType !== 'index-interior' || $rootHeader->cellCount !== 1 || $rootHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can collapse only two-child index roots');
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $workingDatabase->readOverflowPayload($firstOverflowPage, $byteCount);
        $rootCells = SQLiteIndexCell::parsePageCells($rootPageBytes, $rootHeader, $workingDatabase->usablePageSize(), $overflowReader);
        $leftChildPage = $rootCells[0]->leftChildPage ?? null;
        $rightChildPage = $rootHeader->rightMostPointer;
        if ($leftChildPage === null || $leftChildPage < 2 || $rightChildPage < 2) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning found an invalid collapsible root child');
        }

        $childPages = [$leftChildPage, $rightChildPage];
        sort($childPages);
        if (
            !in_array($oldLeaf['pageNumber'], $childPages, true)
            || !in_array($newLeaf['pageNumber'], $childPages, true)
            || $oldLeaf['pageNumber'] === $newLeaf['pageNumber']
        ) {
            throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can collapse only sibling leaves below the same root');
        }

        foreach ($childPages as $childPage) {
            $childHeader = $workingDatabase->pageHeader($childPage);
            if ($childHeader->pageType !== 'index-leaf') {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning can collapse only leaf children');
            }
        }

        $rowId = $newEntryValues[array_key_last($newEntryValues)] ?? null;
        if (!is_int($rowId)) {
            throw new \InvalidArgumentException('SQLite app_settings index replacement entry must end with a rowid');
        }
        $keyColumnCount = count($columns);
        $entries = [];
        $foundOldEntry = false;
        foreach ($workingDatabase->indexCells($rootPage) as $cell) {
            $values = $workingDatabase->indexEntryValuesForColumns($cell, $keyColumnCount);
            $indexRowId = $values[$keyColumnCount] ?? null;
            if (
                $indexRowId === $rowId
                && self::keyValueRowIndexValuesMatchColumns(
                    array_slice($values, 0, $keyColumnCount),
                    $oldValues,
                    $columns,
                )
            ) {
                $foundOldEntry = true;
                continue;
            }
            if ($indexRowId === $rowId) {
                throw new \InvalidArgumentException("SQLite app_settings index does not reference rowid {$rowId} with the expected key");
            }
            if (SQLiteIndexCell::localPayloadLength(strlen($cell->payload), $workingDatabase->usablePageSize()) !== strlen($cell->payload)) {
                throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning does not yet collapse overflow index cells');
            }

            $entries[] = [
                'values' => $values,
                'cell' => SQLiteIndexCell::encode($cell->payload, $workingDatabase->usablePageSize()),
            ];
        }

        if (!$foundOldEntry) {
            throw new \InvalidArgumentException("SQLite app_settings index does not reference rowid {$rowId}");
        }

        $entries[] = [
            'values' => $newEntryValues,
            'cell' => SQLiteIndexCell::encode($newPayload, $workingDatabase->usablePageSize()),
        ];
        usort(
            $entries,
            fn (array $left, array $right): int => $this->compareApplicationIndexEntryValues(
                $left['values'],
                $right['values'],
                $columns,
            ),
        );

        $freePlan = $workingDatabase->planPageFreeList($childPages);
        foreach ($freePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $pageImages[$rootPage] = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $entries),
            $this->header->pageSize,
            $rootHeader->headerOffset,
            $rootPageBytes,
            $workingDatabase->usablePageSize(),
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return list<mixed>
     */
    private static function keyValueRowIndexValuesForColumns(
        array $columns,
        string $keyName,
        ?string $loadPolicy,
    ): array {
        $values = [];
        foreach ($columns as $column) {
            if (strcasecmp($column->columnName, SQLiteKeyValueRow::KEY_COLUMN) === 0) {
                $values[] = $keyName;
                continue;
            }
            if (strcasecmp($column->columnName, SQLiteKeyValueRow::LOAD_POLICY_COLUMN) === 0) {
                $values[] = $loadPolicy;
                continue;
            }

            throw new \InvalidArgumentException('SQLite app_settings write planning supports only key_name and load_policy index columns');
        }

        return $values;
    }

    /**
     * @param list<mixed> $leftValues
     * @param list<mixed> $rightValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     */
    private static function keyValueRowIndexValuesMatchColumns(
        array $leftValues,
        array $rightValues,
        array $columns,
    ): bool {
        foreach ($columns as $index => $column) {
            if (
                !array_key_exists($index, $leftValues)
                || !array_key_exists($index, $rightValues)
                || self::compareSQLiteScalarForIndexColumn($leftValues[$index], $rightValues[$index], $column, []) !== 0
            ) {
                return false;
            }
        }

        return true;
    }

    private function assertIndexDoesNotContainRowId(int $rootPage, int $rowId): void
    {
        foreach ($this->indexCells($rootPage) as $cell) {
            if ($this->rowIdFromIndexCell($cell) === $rowId) {
                throw new \InvalidArgumentException("SQLite app_settings index already contains rowid {$rowId}");
            }
        }
    }

    /**
     * @param list<mixed> $entryValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array}
     */
    private function writableIndexLeafForEntry(
        int $rootPage,
        array $entryValues,
        array $columns,
        bool $rejectInteriorMatch = false,
    ): array {
        $visited = [];
        $pageNumber = $rootPage;
        $parentContext = null;
        while (true) {
            if (isset($visited[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite app_settings index write planning reached page {$pageNumber} more than once");
            }
            $visited[$pageNumber] = true;

            $page = $this->page($pageNumber);
            $headerOffset = $pageNumber === 1 ? 100 : 0;
            $header = SQLiteBTreePageHeader::parsePage(
                $page,
                $this->header->pageSize,
                $headerOffset,
            );

            if ($header->pageType === 'index-leaf') {
                return [
                    'pageNumber' => $pageNumber,
                    'page' => $page,
                    'header' => $header,
                    'headerOffset' => $headerOffset,
                    'parent' => $parentContext,
                ];
            }
            if ($header->pageType !== 'index-interior') {
                throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
            }
            if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
            }

            $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
            $cells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
            foreach ($cells as $cellIndex => $cell) {
                if ($cell->leftChildPage === null || $cell->leftChildPage < 1) {
                    throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
                }

                $comparison = $this->compareApplicationIndexEntryValues(
                    $entryValues,
                    $this->indexEntryValuesForColumns($cell, count($columns)),
                    $columns,
                );
                if ($comparison === 0 && $rejectInteriorMatch) {
                    throw new \InvalidArgumentException('SQLite app_settings indexed replacement planning does not yet delete index entries from interior pages');
                }
                if ($comparison < 0) {
                    $parentContext = [
                        'pageNumber' => $pageNumber,
                        'page' => $page,
                        'header' => $header,
                        'headerOffset' => $headerOffset,
                        'childIndex' => $cellIndex,
                        'parent' => $parentContext,
                    ];
                    $pageNumber = $cell->leftChildPage;
                    continue 2;
                }
            }

            $parentContext = [
                'pageNumber' => $pageNumber,
                'page' => $page,
                'header' => $header,
                'headerOffset' => $headerOffset,
                'childIndex' => count($cells),
                'parent' => $parentContext,
            ];
            $pageNumber = $header->rightMostPointer;
        }
    }

    /**
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return list<array{values:list<mixed>,cell:string,payloadLength:int,localPayloadLength:int,firstOverflowPage:?int}>
     */
    private function writableIndexLeafEntries(string $page, SQLiteBTreePageHeader $header, array $columns): array
    {
        if ($header->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite app_settings index write planning requires leaf page entries');
        }

        $entries = [];
        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        foreach (SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
            $entries[] = [
                'values' => $this->indexEntryValuesForColumns($cell, count($columns)),
                'cell' => substr($page, $cell->offset, $cell->bytesRead),
                'payloadLength' => $cell->payloadLength,
                'localPayloadLength' => $cell->localPayloadLength,
                'firstOverflowPage' => $cell->firstOverflowPage,
            ];
        }

        return $entries;
    }

    /**
     * @return list<mixed>
     */
    private function indexEntryValuesForColumns(SQLiteIndexCell $cell, int $keyColumnCount): array
    {
        $recordValues = $cell->record($this->header->textEncoding)->values;
        if (count($recordValues) < $keyColumnCount + 1) {
            throw new \InvalidArgumentException('SQLite app_settings index record must contain all keys and rowid');
        }

        return array_merge(array_slice($recordValues, 0, $keyColumnCount), [$this->rowIdFromIndexCell($cell)]);
    }

    /**
     * @param list<array{values:list<mixed>,cell:string}> $entries
     */
    private function assembleWritableIndexLeafPage(array $entries, int $headerOffset, string $basePage): string
    {
        try {
            return SQLiteIndexLeafPage::assemble(
                array_map(static fn (array $entry): string => $entry['cell'], $entries),
                $this->header->pageSize,
                $headerOffset,
                $basePage,
                $this->usablePageSize(),
            );
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), 'overlap')) {
                throw new \InvalidArgumentException(
                    'SQLite app_settings indexed write planning does not yet split index leaf pages',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $leaf
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withAssembledWritableIndexLeafPage(
        array $pageImages,
        int $rootPage,
        array $leaf,
        array $entries,
        array $columns,
        bool $allowAppend,
    ): array {
        try {
            $pageImages[$leaf['pageNumber']] = $this->assembleWritableIndexLeafPage(
                $entries,
                $leaf['headerOffset'],
                $leaf['page'],
            );

            return $pageImages;
        } catch (\InvalidArgumentException $exception) {
            if (!str_contains($exception->getMessage(), 'split index leaf pages')) {
                throw $exception;
            }
        }

        return $this->withSplitWritableIndexLeafPage($pageImages, $rootPage, $leaf, $entries, $columns, $allowAppend);
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $leaf
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withSplitWritableIndexLeafPage(
        array $pageImages,
        int $rootPage,
        array $leaf,
        array $entries,
        array $columns,
        bool $allowAppend,
    ): array {
        $parent = $leaf['parent'];
        if ($parent === null) {
            return $this->withGrownRootWritableIndexLeafPage($pageImages, $rootPage, $leaf, $entries, $allowAppend);
        }
        if ($parent['header']->pageType !== 'index-interior') {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning can split only children of index interior pages');
        }

        [$leftEntries, $dividerEntry, $rightEntries] = $this->partitionWritableIndexLeafEntriesForSplit(
            $entries,
            $leaf['headerOffset'],
            $leaf['page'],
        );

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(1, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $newLeafPageNumber = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if ($newLeafPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning could not allocate a split index leaf page');
        }

        $pageImages[$leaf['pageNumber']] = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $leftEntries),
            $this->header->pageSize,
            $leaf['headerOffset'],
            $leaf['page'],
            $this->usablePageSize(),
        );
        $pageImages[$newLeafPageNumber] = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $rightEntries),
            $this->header->pageSize,
            0,
            str_repeat("\0", $this->header->pageSize),
            $this->usablePageSize(),
        );
        $pageImages = $this->withAssembledWritableIndexParentAfterLeafSplit(
            $pageImages,
            $rootPage,
            $parent,
            $dividerEntry,
            $leaf['pageNumber'],
            $newLeafPageNumber,
            $columns,
            $allowAppend,
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,parent:?array} $leaf
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @return array<int, string>
     */
    private function withGrownRootWritableIndexLeafPage(
        array $pageImages,
        int $rootPage,
        array $leaf,
        array $entries,
        bool $allowAppend,
    ): array {
        if ($leaf['pageNumber'] !== $rootPage || $leaf['header']->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning can grow only an index leaf root page');
        }

        [$leftEntries, $dividerEntry, $rightEntries] = $this->partitionWritableIndexLeafEntriesForSplit(
            $entries,
            0,
            str_repeat("\0", $this->header->pageSize),
        );

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(2, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $allocatedPageNumbers = array_values($allocationPlan->allocatedPageNumbers);
        $leftLeafPageNumber = $allocatedPageNumbers[0] ?? null;
        $rightLeafPageNumber = $allocatedPageNumbers[1] ?? null;
        if ($leftLeafPageNumber === null || $rightLeafPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning could not allocate root split leaf pages');
        }

        $pageImages[$leftLeafPageNumber] = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $leftEntries),
            $this->header->pageSize,
            0,
            str_repeat("\0", $this->header->pageSize),
            $this->usablePageSize(),
        );
        $pageImages[$rightLeafPageNumber] = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => $entry['cell'], $rightEntries),
            $this->header->pageSize,
            0,
            str_repeat("\0", $this->header->pageSize),
            $this->usablePageSize(),
        );

        $dividerPayload = SQLiteRecord::encode($dividerEntry['values'], $this->header->textEncoding);
        $pageImages[$rootPage] = SQLiteIndexInteriorPage::assemble(
            [SQLiteIndexCell::encode($dividerPayload, $this->usablePageSize(), null, $leftLeafPageNumber)],
            $rightLeafPageNumber,
            $this->header->pageSize,
            $leaf['headerOffset'],
            $leaf['page'],
            $this->usablePageSize(),
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param list<array{values:list<mixed>,cell:string}> $entries
     * @return array{0:list<array{values:list<mixed>,cell:string}>,1:array{values:list<mixed>,cell:string},2:list<array{values:list<mixed>,cell:string}>}
     */
    private function partitionWritableIndexLeafEntriesForSplit(array $entries, int $headerOffset, string $basePage): array
    {
        $entryCount = count($entries);
        if ($entryCount < 3) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning cannot split fewer than three index entries');
        }

        $best = null;
        $bestScore = null;
        for ($dividerIndex = 1; $dividerIndex <= $entryCount - 2; $dividerIndex++) {
            $leftEntries = array_slice($entries, 0, $dividerIndex);
            $dividerEntry = $entries[$dividerIndex];
            $rightEntries = array_slice($entries, $dividerIndex + 1);

            try {
                SQLiteIndexLeafPage::assemble(
                    array_map(static fn (array $entry): string => $entry['cell'], $leftEntries),
                    $this->header->pageSize,
                    $headerOffset,
                    $basePage,
                    $this->usablePageSize(),
                );
                SQLiteIndexLeafPage::assemble(
                    array_map(static fn (array $entry): string => $entry['cell'], $rightEntries),
                    $this->header->pageSize,
                    0,
                    str_repeat("\0", $this->header->pageSize),
                    $this->usablePageSize(),
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $score = abs(count($leftEntries) - count($rightEntries));
            if ($bestScore === null || $score < $bestScore) {
                $best = [$leftEntries, $dividerEntry, $rightEntries];
                $bestScore = $score;
            }
        }

        if ($best === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning cannot split these index leaf entries within page capacity');
        }

        return $best;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @param array{values:list<mixed>,cell:string} $dividerEntry
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withAssembledWritableIndexParentAfterLeafSplit(
        array $pageImages,
        int $rootPage,
        array $parent,
        array $dividerEntry,
        int $oldLeafPageNumber,
        int $newLeafPageNumber,
        array $columns,
        bool $allowAppend,
    ): array {
        [$parentEntries, $rightMostPointer] = $this->writableIndexParentEntriesAfterLeafSplit(
            $parent,
            $dividerEntry,
            $oldLeafPageNumber,
            $newLeafPageNumber,
            $columns,
        );

        try {
            $pageImages[$parent['pageNumber']] = $this->assembleWritableIndexInteriorPageFromEntries(
                $parentEntries,
                $rightMostPointer,
                $parent['headerOffset'],
                $parent['page'],
            );

            return $pageImages;
        } catch (\InvalidArgumentException $exception) {
            if (!str_contains($exception->getMessage(), 'split parent index pages')) {
                throw $exception;
            }
            if ($parent['pageNumber'] !== $rootPage) {
                return $this->withSplitWritableIndexInteriorParentPage(
                    $pageImages,
                    $rootPage,
                    $parent,
                    $parentEntries,
                    $rightMostPointer,
                    $columns,
                    $allowAppend,
                );
            }
        }

        return $this->withGrownRootWritableIndexInteriorPage(
            $pageImages,
            $parent,
            $parentEntries,
            $rightMostPointer,
            $allowAppend,
        );
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @param list<array{values:list<mixed>,payload:string,leftChild:int}> $entries
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array<int, string>
     */
    private function withSplitWritableIndexInteriorParentPage(
        array $pageImages,
        int $rootPage,
        array $parent,
        array $entries,
        int $rightMostPointer,
        array $columns,
        bool $allowAppend,
    ): array {
        $grandparent = $parent['parent'] ?? null;
        if ($grandparent === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning cannot split a non-root parent without a grandparent page');
        }
        if ($parent['header']->pageType !== 'index-interior') {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning can split only index interior parent pages');
        }

        [$leftEntries, $dividerEntry, $leftRightMostPointer, $rightEntries, $rightRightMostPointer] =
            $this->partitionWritableIndexInteriorEntriesForSplit($entries, $rightMostPointer);

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(1, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $newInteriorPageNumber = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if ($newInteriorPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning could not allocate a split parent index page');
        }

        $pageImages[$parent['pageNumber']] = $this->assembleWritableIndexInteriorPageFromEntries(
            $leftEntries,
            $leftRightMostPointer,
            $parent['headerOffset'],
            $parent['page'],
        );
        $pageImages[$newInteriorPageNumber] = $this->assembleWritableIndexInteriorPageFromEntries(
            $rightEntries,
            $rightRightMostPointer,
            0,
            str_repeat("\0", $this->header->pageSize),
        );

        return $this->withAssembledWritableIndexParentAfterLeafSplit(
            $pageImages,
            $rootPage,
            $grandparent,
            $dividerEntry,
            $parent['pageNumber'],
            $newInteriorPageNumber,
            $columns,
            $allowAppend,
        );
    }

    /**
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int,parent:?array} $parent
     * @param array{values:list<mixed>,cell:string} $dividerEntry
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return array{0:list<array{values:list<mixed>,payload:string,leftChild:int}>,1:int}
     */
    private function writableIndexParentEntriesAfterLeafSplit(
        array $parent,
        array $dividerEntry,
        int $oldLeafPageNumber,
        int $newLeafPageNumber,
        array $columns,
    ): array {
        $header = $parent['header'];
        if ($header->pageType !== 'index-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning requires an index interior parent page');
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $parentCells = SQLiteIndexCell::parsePageCells($parent['page'], $header, $this->usablePageSize(), $overflowReader);
        $childIndex = $parent['childIndex'];
        if ($childIndex < 0 || $childIndex > count($parentCells)) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning found an invalid parent child slot');
        }
        if ($childIndex === count($parentCells)) {
            if ($header->rightMostPointer !== $oldLeafPageNumber) {
                throw new \InvalidArgumentException('SQLite app_settings indexed write planning parent right-most pointer does not match the split leaf');
            }
        } elseif ($parentCells[$childIndex]->leftChildPage !== $oldLeafPageNumber) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning parent child pointer does not match the split leaf');
        }

        $dividerPayload = SQLiteRecord::encode($dividerEntry['values'], $this->header->textEncoding);
        $entries = [];
        foreach ($parentCells as $index => $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite app_settings indexed write planning found a parent cell without a child pointer');
            }
            $existingEntry = [
                'values' => $this->indexEntryValuesForColumns($cell, count($columns)),
                'payload' => $cell->payload,
                'leftChild' => $cell->leftChildPage,
            ];
            if ($index === $childIndex) {
                $entries[] = [
                    'values' => $dividerEntry['values'],
                    'payload' => $dividerPayload,
                    'leftChild' => $oldLeafPageNumber,
                ];
                $entries[] = [
                    'values' => $existingEntry['values'],
                    'payload' => $existingEntry['payload'],
                    'leftChild' => $newLeafPageNumber,
                ];
                continue;
            }

            $entries[] = $existingEntry;
        }

        $rightMostPointer = $header->rightMostPointer;
        if ($childIndex === count($parentCells)) {
            $entries[] = [
                'values' => $dividerEntry['values'],
                'payload' => $dividerPayload,
                'leftChild' => $oldLeafPageNumber,
            ];
            $rightMostPointer = $newLeafPageNumber;
        }

        return [$entries, $rightMostPointer];
    }

    /**
     * @param list<array{values:list<mixed>,payload:string,leftChild:int}> $entries
     */
    private function assembleWritableIndexInteriorPageFromEntries(
        array $entries,
        int $rightMostPointer,
        int $headerOffset,
        string $basePage,
    ): string {
        $cellBytes = array_map(
            fn (array $entry): string => SQLiteIndexCell::encode(
                $entry['payload'],
                $this->usablePageSize(),
                null,
                $entry['leftChild'],
            ),
            $entries,
        );

        try {
            return SQLiteIndexInteriorPage::assemble(
                $cellBytes,
                $rightMostPointer,
                $this->header->pageSize,
                $headerOffset,
                $basePage,
                $this->usablePageSize(),
            );
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), 'overlap')) {
                throw new \InvalidArgumentException(
                    'SQLite app_settings indexed write planning does not yet split parent index pages',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, string> $pageImages
     * @param array{pageNumber:int,page:string,header:SQLiteBTreePageHeader,headerOffset:int,childIndex:int} $root
     * @param list<array{values:list<mixed>,payload:string,leftChild:int}> $entries
     * @return array<int, string>
     */
    private function withGrownRootWritableIndexInteriorPage(
        array $pageImages,
        array $root,
        array $entries,
        int $rightMostPointer,
        bool $allowAppend,
    ): array {
        if ($root['header']->pageType !== 'index-interior') {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning can grow only an index interior root page');
        }

        [$leftEntries, $dividerEntry, $leftRightMostPointer, $rightEntries, $rightRightMostPointer] =
            $this->partitionWritableIndexInteriorEntriesForSplit($entries, $rightMostPointer);

        $workingDatabase = $this->withPageImages($pageImages);
        $allocationPlan = $workingDatabase->planPageAllocation(2, $allowAppend);
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        $allocatedPageNumbers = array_values($allocationPlan->allocatedPageNumbers);
        $leftInteriorPageNumber = $allocatedPageNumbers[0] ?? null;
        $rightInteriorPageNumber = $allocatedPageNumbers[1] ?? null;
        if ($leftInteriorPageNumber === null || $rightInteriorPageNumber === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning could not allocate root split interior pages');
        }

        $pageImages[$leftInteriorPageNumber] = $this->assembleWritableIndexInteriorPageFromEntries(
            $leftEntries,
            $leftRightMostPointer,
            0,
            str_repeat("\0", $this->header->pageSize),
        );
        $pageImages[$rightInteriorPageNumber] = $this->assembleWritableIndexInteriorPageFromEntries(
            $rightEntries,
            $rightRightMostPointer,
            0,
            str_repeat("\0", $this->header->pageSize),
        );
        $pageImages[$root['pageNumber']] = SQLiteIndexInteriorPage::assemble(
            [SQLiteIndexCell::encode($dividerEntry['payload'], $this->usablePageSize(), null, $leftInteriorPageNumber)],
            $rightInteriorPageNumber,
            $this->header->pageSize,
            $root['headerOffset'],
            $root['page'],
            $this->usablePageSize(),
        );
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @param list<array{values:list<mixed>,payload:string,leftChild:int}> $entries
     * @return array{0:list<array{values:list<mixed>,payload:string,leftChild:int}>,1:array{values:list<mixed>,payload:string,leftChild:int},2:int,3:list<array{values:list<mixed>,payload:string,leftChild:int}>,4:int}
     */
    private function partitionWritableIndexInteriorEntriesForSplit(array $entries, int $rightMostPointer): array
    {
        $entryCount = count($entries);
        if ($entryCount < 3) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning cannot split fewer than three parent index entries');
        }

        $best = null;
        $bestScore = null;
        for ($dividerIndex = 1; $dividerIndex <= $entryCount - 2; $dividerIndex++) {
            $leftEntries = array_slice($entries, 0, $dividerIndex);
            $dividerEntry = $entries[$dividerIndex];
            $rightEntries = array_slice($entries, $dividerIndex + 1);
            $leftRightMostPointer = $dividerEntry['leftChild'];

            try {
                $this->assembleWritableIndexInteriorPageFromEntries(
                    $leftEntries,
                    $leftRightMostPointer,
                    0,
                    str_repeat("\0", $this->header->pageSize),
                );
                $this->assembleWritableIndexInteriorPageFromEntries(
                    $rightEntries,
                    $rightMostPointer,
                    0,
                    str_repeat("\0", $this->header->pageSize),
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $score = abs(count($leftEntries) - count($rightEntries));
            if ($bestScore === null || $score < $bestScore) {
                $best = [$leftEntries, $dividerEntry, $leftRightMostPointer, $rightEntries, $rightMostPointer];
                $bestScore = $score;
            }
        }

        if ($best === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed write planning cannot split these parent index entries within page capacity');
        }

        return $best;
    }

    /**
     * @param list<mixed> $leftValues
     * @param list<mixed> $rightValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     */
    private function compareApplicationIndexEntryValues(
        array $leftValues,
        array $rightValues,
        array $columns,
    ): int {
        foreach ($columns as $index => $column) {
            $keyComparison = self::compareSQLiteScalarForIndexColumn($leftValues[$index] ?? null, $rightValues[$index] ?? null, $column, []);
            if ($keyComparison !== 0) {
                return $column->descending ? -$keyComparison : $keyComparison;
            }
        }

        $rowIdIndex = count($columns);

        return ($leftValues[$rowIdIndex] ?? null) <=> ($rightValues[$rowIdIndex] ?? null);
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
            $rows[] = $this->tableRowFromLeafCell($cell);
        }

        return $rows;
    }

    /**
     * @return list<SQLiteTableRow>
     */
    public function tableRowsByRowIdRange(
        int $rootPageNumber,
        ?int $lowerInclusive,
        ?int $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite table rowid range limit cannot be negative');
        }
        if ($limit === 0 || self::rowIdRangeIsEmpty($lowerInclusive, $upperBound, $upperInclusive)) {
            return [];
        }

        $visited = [];
        $cells = [];
        $this->collectTableLeafCellsByRowIdRange(
            $rootPageNumber,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $visited,
            $cells,
            $limit,
            false,
            null,
            false,
            null,
        );

        $rows = [];
        foreach ($cells as $cell) {
            $rows[] = $this->tableRowFromLeafCell($cell);
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
     * @return list<SQLiteTableRow>
     */
    public function tableRowsByRowIdRangeByName(
        string $tableName,
        ?int $lowerInclusive,
        ?int $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        $rootPage = $this->tableRootPage($tableName);
        if ($rootPage === null) {
            return [];
        }

        return $this->tableRowsByRowIdRange(
            $rootPage,
            $lowerInclusive,
            $upperBound,
            $limit,
            $upperInclusive,
        );
    }

    /**
     * @return list<SQLiteSequenceRecord>
     */
    public function sqliteSequenceRecords(?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite sqlite_sequence row limit cannot be negative');
        }

        $rootPage = $this->tableRootPage('sqlite_sequence');
        if ($rootPage === null || $limit === 0) {
            return [];
        }

        $records = [];
        foreach ($this->tableRows($rootPage, $limit) as $row) {
            $records[] = SQLiteSequenceRecord::fromTableRow($row);
        }

        return $records;
    }

    public function sqliteSequenceForTable(string $tableName): ?SQLiteSequenceRecord
    {
        foreach ($this->sqliteSequenceRecords() as $record) {
            if ($record->matchesTable($tableName)) {
                return $record;
            }
        }

        return null;
    }

    public function autoincrementStateForTable(string $tableName): SQLiteAutoincrementState
    {
        $tableRootPage = $this->tableRootPage($tableName);
        if ($tableRootPage === null) {
            throw new \InvalidArgumentException("SQLite table {$tableName} is not present");
        }

        if ($this->tableRootPage('sqlite_sequence') === null) {
            throw new \InvalidArgumentException('SQLite AUTOINCREMENT allocation requires sqlite_sequence');
        }

        $largestTableRowId = null;
        foreach ($this->tableLeafCells($tableRootPage) as $cell) {
            if ($largestTableRowId === null || $cell->rowId > $largestTableRowId) {
                $largestTableRowId = $cell->rowId;
            }
        }

        $sequenceRecord = null;
        $largestSequenceRowId = 0;
        foreach ($this->sqliteSequenceRecords() as $record) {
            if ($record->rowId > $largestSequenceRowId) {
                $largestSequenceRowId = $record->rowId;
            }
            if ($sequenceRecord === null && $record->matchesTable($tableName)) {
                $sequenceRecord = $record;
            }
        }

        return SQLiteAutoincrementState::fromDatabaseState(
            $tableName,
            $sequenceRecord,
            $largestTableRowId,
            $largestSequenceRowId,
        );
    }

    public function tableRowByRowId(int $rootPageNumber, int $rowId): ?SQLiteTableRow
    {
        $visited = [];
        $cell = $this->findTableLeafCellByRowId($rootPageNumber, $rowId, $visited);

        return $cell === null ? null : $this->tableRowFromLeafCell($cell);
    }

    private function tableRowFromLeafCell(SQLiteTableLeafCell $cell): SQLiteTableRow
    {
        try {
            return SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException('database disk image is malformed', 0, $exception);
        }
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
     * @param list<mixed> $values
     */
    public function indexRootPageForInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForLowercaseInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForLowerExpressionColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForLowercaseInLookupWithCollation(
        string $tableName,
        string $columnName,
        string $collationName,
        array $values,
    ): ?int {
        $lookup = $this->indexLookupForLowerExpressionColumnInListWithCollation(
            $tableName,
            $columnName,
            $collationName,
            $values,
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForUppercaseInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForUpperExpressionColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param array<string, mixed> $equalityConstraints
     */
    public function indexRootPageForPointLookupWithConstraints(
        string $tableName,
        string $columnName,
        mixed $value,
        array $equalityConstraints,
    ): ?int {
        $equalityConstraints[$columnName] = $value;
        $lookup = $this->indexLookupForColumn($tableName, $columnName, $value, true, $equalityConstraints);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLowercasePointLookup(string $tableName, string $columnName, string $value): ?int
    {
        $lookup = $this->indexLookupForLowerExpressionColumn($tableName, $columnName, $value);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForUppercasePointLookup(string $tableName, string $columnName, string $value): ?int
    {
        $lookup = $this->indexLookupForUpperExpressionColumn($tableName, $columnName);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForTrimmedPointLookup(
        string $tableName,
        string $columnName,
        string $value,
        string $functionName = 'trim',
        ?string $characters = null,
    ): ?int {
        self::sqliteTrim($value, $functionName, $characters);
        $lookup = $this->indexLookupForTrimExpressionColumn($tableName, $columnName, $functionName, $characters);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLengthPointLookup(string $tableName, string $columnName, int $length): ?int
    {
        $lookup = $this->indexLookupForLengthExpressionColumn($tableName, $columnName, $length);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForIntegerCastPointLookup(string $tableName, string $columnName, int $value): ?int
    {
        $lookup = $this->indexLookupForIntegerCastExpressionColumn($tableName, $columnName, $value);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForIntegerCastInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForIntegerCastExpressionColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForIntegerCastRangeLookup(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForIntegerCastExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonExtractPointLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $value,
    ): ?int {
        self::sqliteJsonScalar($value);
        $lookup = $this->indexLookupForJsonExtractExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonValueOperatorPointLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $value,
    ): ?int {
        self::sqliteJsonTextValue($value);
        $lookup = $this->indexLookupForJsonValueOperatorExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForJsonValueOperatorInLookup(
        string $tableName,
        string $columnName,
        string $path,
        array $values,
    ): ?int {
        if ($values === []) {
            return null;
        }

        self::sqliteJsonTextValueList($values);
        $lookup = $this->indexLookupForJsonValueOperatorExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonValueOperatorRangeLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonTextValue($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonTextValue($upperBound);
        $lookup = $this->indexLookupForJsonValueOperatorExpressionColumnRange(
            $tableName,
            $columnName,
            $path,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForJsonExtractInLookup(
        string $tableName,
        string $columnName,
        string $path,
        array $values,
    ): ?int {
        $lookupValues = self::sqliteJsonScalarList($values);
        if (!self::containsNonNullValue($lookupValues)) {
            return null;
        }

        $lookup = $this->indexLookupForJsonExtractExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonExtractRangeLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonScalar($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonScalar($upperBound);
        $lookup = $this->indexLookupForJsonExtractExpressionColumnRange(
            $tableName,
            $columnName,
            $path,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLengthRangeLookup(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForLengthExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $lengths
     */
    public function indexRootPageForLengthInLookup(string $tableName, string $columnName, array $lengths): ?int
    {
        $lookup = $this->indexLookupForLengthExpressionColumnInList($tableName, $columnName, $lengths);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForSubstringPointLookup(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
        string $value,
    ): ?int {
        $lookup = $this->indexLookupForSubstringExpressionColumn($tableName, $columnName, $start, $length);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForSubstringInLookup(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
        array $values,
    ): ?int {
        $lookup = $this->indexLookupForSubstringExpressionColumnInList($tableName, $columnName, $start, $length, $values);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLowercaseRangeLookup(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForLowerExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLowercaseRangeLookupWithCollation(
        string $tableName,
        string $columnName,
        string $collationName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForLowerExpressionColumnRangeWithCollation(
            $tableName,
            $columnName,
            $collationName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForUppercaseRangeLookup(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForUpperExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForRangeLookup(
        string $tableName,
        string $columnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForColumnRange($tableName, $columnName, $lowerInclusive, $upperBound, $upperInclusive);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param non-empty-array<string, mixed> $equalityPrefix
     */
    public function indexRootPageForPrefixRangeLookup(
        string $tableName,
        array $equalityPrefix,
        string $rangeColumnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        if ($equalityPrefix === []) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires at least one equality column');
        }

        $lookup = $this->indexLookupForColumnPrefixRange(
            $tableName,
            array_keys($equalityPrefix),
            array_values($equalityPrefix),
            $rangeColumnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param non-empty-array<string, mixed> $equalityPrefix
     * @param array<string, callable(string, string): int> $customCollations
     */
    public function indexRootPageForPrefixRangeLookupWithCollations(
        string $tableName,
        array $equalityPrefix,
        string $rangeColumnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        array $customCollations,
        bool $upperInclusive = false,
    ): ?int {
        if ($equalityPrefix === []) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires at least one equality column');
        }

        $lookup = $this->indexLookupForColumnPrefixRangeWithCollations(
            $tableName,
            array_keys($equalityPrefix),
            array_values($equalityPrefix),
            $rangeColumnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            self::normalizeCustomCollations($customCollations),
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param non-empty-array<string, mixed> $columnValues
     */
    public function indexRootPageForPointLookupColumns(string $tableName, array $columnValues): ?int
    {
        if ($columnValues === []) {
            throw new \InvalidArgumentException('SQLite index prefix lookup requires at least one column');
        }
        if (count($columnValues) === 1) {
            $columnName = array_key_first($columnValues);

            return $this->indexRootPageForPointLookup($tableName, $columnName, $columnValues[$columnName]);
        }

        $lookup = $this->indexLookupForColumnPrefix(
            $tableName,
            array_keys($columnValues),
            array_values($columnValues),
        );

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
        array $equalityConstraints = [],
        bool $allowEqualityPartialPredicate = true,
        array $rangeConstraints = [],
    ): ?array
    {
        if ($isPointLookup) {
            $equalityConstraints[$columnName] = $pointLookupValue;
        }
        $autoIndexFirstColumns = null;
        $autoIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql !== null) {
                $firstColumn = SQLiteCreateIndex::firstColumn($record->sql);
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    if ($firstColumn->partial) {
                        $partialPredicate = $firstColumn->partialPredicate;
                        $hasPredicateConstraints = $isPointLookup || $rangeConstraints !== [];
                        if (
                            !$hasPredicateConstraints
                            || $partialPredicate === null
                            || !self::partialPredicateIsImpliedByConstraints(
                                $partialPredicate,
                                $equalityConstraints,
                                $rangeConstraints,
                                $allowEqualityPartialPredicate,
                                [$firstColumn->columnName => $firstColumn->collation],
                            )
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
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumnWithCollation(
        string $tableName,
        string $columnName,
        string $collationName,
        mixed $pointLookupValue,
    ): ?array {
        if ($collationName === '') {
            throw new \InvalidArgumentException('SQLite custom collation name cannot be empty');
        }

        $autoIndexFirstColumns = null;
        $autoIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql !== null) {
                $firstColumn = SQLiteCreateIndex::firstColumn($record->sql);
                if (
                    $firstColumn !== null
                    && strcasecmp($firstColumn->columnName, $columnName) === 0
                    && strcasecmp($firstColumn->collation, $collationName) === 0
                ) {
                    if (
                        $firstColumn->partial
                        && (
                            $firstColumn->partialPredicate === null
                            || !self::partialPredicateIsImpliedByConstraints(
                                $firstColumn->partialPredicate,
                                [$columnName => $pointLookupValue],
                                [],
                                true,
                                [$firstColumn->columnName => $firstColumn->collation],
                            )
                        )
                    ) {
                        continue;
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
                if (
                    $firstColumn !== null
                    && strcasecmp($firstColumn->columnName, $columnName) === 0
                    && strcasecmp($firstColumn->collation, $collationName) === 0
                ) {
                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumnRangeWithCollation(
        string $tableName,
        string $columnName,
        string $collationName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
    ): ?array {
        if ($collationName === '') {
            throw new \InvalidArgumentException('SQLite custom collation name cannot be empty');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite custom-collation index range lookup requires at least one bound');
        }

        $autoIndexFirstColumns = null;
        $autoIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql !== null) {
                $firstColumn = SQLiteCreateIndex::firstColumn($record->sql);
                if (
                    $firstColumn !== null
                    && strcasecmp($firstColumn->columnName, $columnName) === 0
                    && strcasecmp($firstColumn->collation, $collationName) === 0
                ) {
                    if (
                        $firstColumn->partial
                        && (
                            $firstColumn->partialPredicate === null
                            || !self::lowerExpressionRangeImpliesPartialPredicate(
                                $firstColumn->partialPredicate,
                                $columnName,
                            )
                        )
                    ) {
                        continue;
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
                if (
                    $firstColumn !== null
                    && strcasecmp($firstColumn->columnName, $columnName) === 0
                    && strcasecmp($firstColumn->collation, $collationName) === 0
                ) {
                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumnInList(string $tableName, string $columnName, array $values): ?array
    {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value !== null) {
                $hasNonNullValue = true;
                break;
            }
        }
        if (!$hasNonNullValue) {
            return null;
        }

        $autoIndexFirstColumns = null;
        $autoIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql !== null) {
                $firstColumn = SQLiteCreateIndex::firstColumn($record->sql);
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    if (
                        $firstColumn->partial
                        && (
                            $firstColumn->partialPredicate === null
                            || !self::partialPredicateIsImpliedByInListConstraints(
                                $firstColumn->partialPredicate,
                                $columnName,
                                $values,
                                $firstColumn->collation,
                            )
                        )
                    ) {
                        continue;
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
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumnInList(string $tableName, string $columnName, array $values): ?array
    {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value !== null) {
                $hasNonNullValue = true;
                break;
            }
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumnInListWithCollation(
        string $tableName,
        string $columnName,
        string $collationName,
        array $values,
    ): ?array {
        if ($collationName === '') {
            throw new \InvalidArgumentException('SQLite custom collation name cannot be empty');
        }
        if (!self::containsNonNullValue($values)) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if (
                $firstExpression === null
                || strcasecmp($firstExpression->columnName, $columnName) !== 0
                || strcasecmp($firstExpression->collation, $collationName) !== 0
            ) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForUpperExpressionColumnInList(string $tableName, string $columnName, array $values): ?array
    {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value !== null) {
                $hasNonNullValue = true;
                break;
            }
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstUpperExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumn(
        string $tableName,
        string $columnName,
        string $pointLookupValue,
    ): ?array {
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::partialPredicateIsImpliedByConstraints(
                        $firstExpression->partialPredicate,
                        [$columnName => $pointLookupValue],
                        [],
                        true,
                        [$firstExpression->columnName => $firstExpression->collation],
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumnWithCollation(
        string $tableName,
        string $columnName,
        string $collationName,
        string $pointLookupValue,
    ): ?array {
        if ($collationName === '') {
            throw new \InvalidArgumentException('SQLite custom collation name cannot be empty');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if (
                $firstExpression === null
                || strcasecmp($firstExpression->columnName, $columnName) !== 0
                || strcasecmp($firstExpression->collation, $collationName) !== 0
            ) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::partialPredicateIsImpliedByConstraints(
                        $firstExpression->partialPredicate,
                        [$columnName => $pointLookupValue],
                        [],
                        true,
                        [$firstExpression->columnName => $firstExpression->collation],
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumnRangeWithCollation(
        string $tableName,
        string $columnName,
        string $collationName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($collationName === '') {
            throw new \InvalidArgumentException('SQLite custom collation name cannot be empty');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite custom-collation lower expression index range lookup requires at least one bound');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if (
                $firstExpression === null
                || strcasecmp($firstExpression->columnName, $columnName) !== 0
                || strcasecmp($firstExpression->collation, $collationName) !== 0
            ) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForUpperExpressionColumn(
        string $tableName,
        string $columnName,
    ): ?array {
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstUpperExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForTrimExpressionColumn(
        string $tableName,
        string $columnName,
        string $functionName,
        ?string $characters,
    ): ?array {
        $functionName = self::normalizeTrimFunctionName($functionName);
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstTrimExpression($record->sql);
            if (
                $firstExpression === null
                || $firstExpression->functionName !== $functionName
                || $firstExpression->characters !== $characters
                || strcasecmp($firstExpression->columnName, $columnName) !== 0
            ) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLengthExpressionColumn(
        string $tableName,
        string $columnName,
        int $pointLookupValue,
    ): ?array {
        if ($pointLookupValue < 0) {
            throw new \InvalidArgumentException('SQLite length expression index lookup length cannot be negative');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLengthExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLengthExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite length expression index range lookup requires at least one bound');
        }
        if ($lowerInclusive !== null && $lowerInclusive < 0) {
            throw new \InvalidArgumentException('SQLite length expression index range lower bound cannot be negative');
        }
        if ($upperBound !== null && $upperBound < 0) {
            throw new \InvalidArgumentException('SQLite length expression index range upper bound cannot be negative');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLengthExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForIntegerCastExpressionColumn(
        string $tableName,
        string $columnName,
        int $pointLookupValue,
    ): ?array {
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstIntegerCastExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForIntegerCastExpressionColumnInList(
        string $tableName,
        string $columnName,
        array $values,
    ): ?array {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (!is_int($value)) {
                throw new \InvalidArgumentException('SQLite CAST AS INTEGER expression index IN lookup values must be integers or null');
            }
            $hasNonNullValue = true;
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstIntegerCastExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForIntegerCastExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite CAST AS INTEGER expression index range lookup requires at least one bound');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstIntegerCastExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonExtractExpressionColumn(
        string $tableName,
        string $columnName,
        string $path,
    ): ?array {
        $requestedPath = self::parseSimpleJsonPath($path);

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $expression = SQLiteCreateIndex::firstJsonExtractExpression($record->sql)
                ?? SQLiteCreateIndex::firstJsonTextOperatorExpression($record->sql);
            if (
                $expression === null
                || strcasecmp($expression->columnName, $columnName) !== 0
                || !self::jsonExpressionPathMatches($expression->path, $requestedPath)
            ) {
                continue;
            }

            if (
                $expression->partial
                && (
                    $expression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $expression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $expression->collation,
                'descending' => $expression->descending,
                'path' => $expression->path,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonValueOperatorExpressionColumn(
        string $tableName,
        string $columnName,
        string $path,
    ): ?array {
        $requestedPath = self::parseSimpleJsonPath($path);

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $expression = SQLiteCreateIndex::firstJsonValueOperatorExpression($record->sql);
            if (
                $expression === null
                || strcasecmp($expression->columnName, $columnName) !== 0
                || !self::jsonExpressionPathMatches($expression->path, $requestedPath)
            ) {
                continue;
            }

            if (
                $expression->partial
                && (
                    $expression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $expression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $expression->collation,
                'descending' => $expression->descending,
                'path' => $expression->path,
            ];
        }

        return null;
    }

    /**
     * @param list<array{kind:string,value:int|string|null}> $requestedPath
     */
    private static function jsonExpressionPathMatches(string $expressionPath, array $requestedPath): bool
    {
        try {
            return self::parseSimpleJsonPath($expressionPath) === $requestedPath;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonExtractExpressionColumnRange(
        string $tableName,
        string $columnName,
        string $path,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite json_extract expression index range lookup requires at least one bound');
        }

        return $this->indexLookupForJsonExtractExpressionColumn($tableName, $columnName, $path);
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonValueOperatorExpressionColumnRange(
        string $tableName,
        string $columnName,
        string $path,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite JSON -> expression index range lookup requires at least one bound');
        }

        return $this->indexLookupForJsonValueOperatorExpressionColumn($tableName, $columnName, $path);
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLengthExpressionColumnInList(
        string $tableName,
        string $columnName,
        array $values,
    ): ?array {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (!is_int($value)) {
                throw new \InvalidArgumentException('SQLite length expression index IN lookup lengths must be integers or null');
            }
            if ($value < 0) {
                throw new \InvalidArgumentException('SQLite length expression index IN lookup lengths cannot be negative');
            }
            $hasNonNullValue = true;
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLengthExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,expression:SQLiteSubstringIndexExpression}
     */
    private function indexLookupForSubstringExpressionColumn(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
    ): ?array {
        if ($start === 0) {
            throw new \InvalidArgumentException('SQLite substr expression index lookup start cannot be zero');
        }
        if ($length !== null && $length < 0) {
            throw new \InvalidArgumentException('SQLite substr expression index lookup length cannot be negative');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $expression = SQLiteCreateIndex::firstSubstringExpression($record->sql);
            if (
                $expression === null
                || strcasecmp($expression->columnName, $columnName) !== 0
                || $expression->start !== $start
                || $expression->length !== $length
            ) {
                continue;
            }

            if (
                $expression->partial
                && (
                    $expression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $expression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $expression->collation,
                'descending' => $expression->descending,
                'expression' => $expression,
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool,expression:SQLiteSubstringIndexExpression}
     */
    private function indexLookupForSubstringExpressionColumnInList(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
        array $values,
    ): ?array {
        if (!self::containsNonNullValue($values)) {
            return null;
        }

        return $this->indexLookupForSubstringExpressionColumn($tableName, $columnName, $start, $length);
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite lower expression index range lookup requires at least one bound');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForUpperExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite upper expression index range lookup requires at least one bound');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstUpperExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumnRange(
        string $tableName,
        string $columnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite index range lookup requires at least one bound');
        }

        $rangeConstraints = [
            $columnName => [
                'lowerInclusive' => $lowerInclusive,
                'upperBound' => $upperBound,
                'upperInclusive' => $upperInclusive,
            ],
        ];

        return $this->indexLookupForColumn(
            $tableName,
            $columnName,
            null,
            false,
            [],
            false,
            $rangeConstraints,
        );
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRows(int $limit = 100): array
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings limit cannot be negative');
        }

        $settings = [];
        foreach ($this->tableRowsByName(SQLiteKeyValueRow::TABLE_NAME, $limit) as $row) {
            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsOrdered(
        string $orderBy,
        bool $descending = false,
        ?int $limit = null,
        int $offset = 0,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings ordered scan limit cannot be negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite app_settings ordered scan offset cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $column = self::normalizeKeyValueRowOrderColumn($orderBy);
        $settings = [];
        foreach ($this->tableRowsByName(SQLiteKeyValueRow::TABLE_NAME, null) as $row) {
            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
        }

        usort($settings, static function (SQLiteKeyValueRow $left, SQLiteKeyValueRow $right) use ($column, $descending): int {
            $comparison = self::compareKeyValueRowOrderValues(
                self::keyValueRowOrderValue($left, $column),
                self::keyValueRowOrderValue($right, $column),
            );
            if ($comparison === 0) {
                return $left->rowId <=> $right->rowId;
            }

            return $descending ? -$comparison : $comparison;
        });

        if ($offset !== 0 || $limit !== null) {
            $settings = array_slice($settings, $offset, $limit);
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByRowIdRange(
        ?int $lowerInclusive,
        ?int $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings rowid range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $settings = [];
        foreach (
            $this->tableRowsByRowIdRangeByName(
                SQLiteKeyValueRow::TABLE_NAME,
                $lowerInclusive,
                $upperBound,
                $limit,
                $upperInclusive,
            ) as $row
        ) {
            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByNameLike(
        string $pattern,
        ?string $escape = null,
        ?int $limit = null,
        bool $caseSensitive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings LIKE lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $settings = [];
        foreach ($this->tableRowsByName(SQLiteKeyValueRow::TABLE_NAME, null) as $row) {
            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (!self::likeMatches($setting->keyName, $pattern, $escape, $caseSensitive)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameLikePrefixRange(
        string $pattern,
        ?string $escape = null,
        ?int $limit = null,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings indexed LIKE prefix lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $bounds = self::likePrefixRangeBounds($pattern, $escape);
        if ($bounds === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed LIKE prefix lookup requires a leading literal prefix');
        }

        $settings = [];
        foreach ($this->keyValueRowsByIndexedNameRange($bounds['lowerInclusive'], $bounds['upperBound']) as $setting) {
            if (!self::likeMatches($setting->keyName, $pattern, $escape, true)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameLikePrefixRangeNoCase(
        string $pattern,
        ?string $escape = null,
        ?int $limit = null,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings NOCASE indexed LIKE prefix lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $bounds = self::likeNoCasePrefixRangeBounds($pattern, $escape);
        if ($bounds === null) {
            throw new \InvalidArgumentException('SQLite app_settings NOCASE indexed LIKE prefix lookup requires a leading literal prefix');
        }

        $compareNoCase = static fn (string $left, string $right): int => strcmp(self::asciiLower($left), self::asciiLower($right));

        $settings = [];
        foreach ($this->keyValueRowsByIndexedNameRangeWithCollation(
            $bounds['lowerInclusive'],
            $bounds['upperBound'],
            'NOCASE',
            $compareNoCase,
        ) as $setting) {
            if (!self::likeMatches($setting->keyName, $pattern, $escape, false)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByNameGlob(string $pattern, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings GLOB lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $settings = [];
        foreach ($this->tableRowsByName(SQLiteKeyValueRow::TABLE_NAME, null) as $row) {
            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (!self::globMatches($setting->keyName, $pattern)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameGlobPrefixRange(string $pattern, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings indexed GLOB prefix lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $bounds = self::globPrefixRangeBounds($pattern);
        if ($bounds === null) {
            throw new \InvalidArgumentException('SQLite app_settings indexed GLOB prefix lookup requires a leading literal prefix');
        }

        $settings = [];
        foreach ($this->keyValueRowsByIndexedNameRange($bounds['lowerInclusive'], $bounds['upperBound']) as $setting) {
            if (!self::globMatches($setting->keyName, $pattern)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    private static function normalizeKeyValueRowOrderColumn(string $orderBy): string
    {
        $column = strtolower($orderBy);
        return match ($column) {
            SQLiteKeyValueRow::ID_COLUMN, SQLiteKeyValueRow::KEY_COLUMN, SQLiteKeyValueRow::VALUE_COLUMN, SQLiteKeyValueRow::LOAD_POLICY_COLUMN, 'rowid' => $column,
            default => throw new \InvalidArgumentException("SQLite app_settings ordered scan cannot order by {$orderBy}"),
        };
    }

    private static function keyValueRowOrderValue(SQLiteKeyValueRow $setting, string $column): int|string|null
    {
        return match ($column) {
            SQLiteKeyValueRow::ID_COLUMN => $setting->settingId,
            SQLiteKeyValueRow::KEY_COLUMN => $setting->keyName,
            SQLiteKeyValueRow::VALUE_COLUMN => $setting->keyValue,
            SQLiteKeyValueRow::LOAD_POLICY_COLUMN => $setting->loadPolicy,
            'rowid' => $setting->rowId,
        };
    }

    private static function compareKeyValueRowOrderValues(int|string|null $left, int|string|null $right): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        if (is_int($left) && is_int($right)) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param callable(string, string): bool $regexp
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByNameRegexp(string $pattern, callable $regexp, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings REGEXP lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $settings = [];
        foreach ($this->tableRowsByName(SQLiteKeyValueRow::TABLE_NAME, null) as $row) {
            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (!self::regexpMatches($setting->keyName, $pattern, $regexp)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    public function keyValueRowByIndexedName(string $keyName): ?SQLiteKeyValueRow
    {
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForColumn(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $keyName, true);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings key_name index is not present');
        }

        $visited = [];
        $indexCell = $this->findIndexCellByFirstValue(
            $indexLookup['rootPage'],
            $keyName,
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
            throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
        }

        return SQLiteKeyValueRow::fromTableRow($row);
    }

    /**
     * @param callable(string, string): int $compare
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameWithCollation(
        string $keyName,
        string $collationName,
        callable $compare,
        ?int $limit = null,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumnWithCollation(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $collationName,
            $keyName,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings key_name index with collation {$collationName} is not present");
        }

        $settings = [];
        foreach ($this->indexCells($indexLookup['rootPage']) as $indexCell) {
            $record = $indexCell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            if (self::compareSQLiteScalarWithCustomTextCollation($record->values[0], $keyName, $compare) !== 0) {
                continue;
            }

            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param callable(string, string): int $compare
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameRangeWithCollation(
        ?string $lowerInclusive,
        ?string $upperBound,
        string $collationName,
        callable $compare,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumnRangeWithCollation(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $collationName,
            $lowerInclusive,
            $upperBound,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings key_name range index with collation {$collationName} is not present");
        }

        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalarWithCustomTextCollation($lowerInclusive, $upperBound, $compare);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach ($this->indexCells($indexLookup['rootPage']) as $indexCell) {
            $record = $indexCell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            if (!self::customFirstValueIsInRange($record->values[0], $lowerInclusive, $upperBound, $upperInclusive, $compare)) {
                continue;
            }

            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param list<?string> $keyNames
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNames(array $keyNames, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings indexed IN lookup limit cannot be negative');
        }
        if ($limit === 0 || $keyNames === []) {
            return [];
        }

        $hasNonNullName = false;
        foreach ($keyNames as $keyName) {
            if ($keyName !== null && !is_string($keyName)) {
                throw new \InvalidArgumentException('SQLite app_settings indexed IN lookup names must be strings or null');
            }
            $hasNonNullName = $hasNonNullName || $keyName !== null;
        }
        if (!$hasNonNullName) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumnInList(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $keyNames);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings key_name IN-list index is not present');
        }

        $settings = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $keyNames,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param list<?string> $keyNames
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedLowercaseNames(array $keyNames, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings lower expression indexed IN lookup limit cannot be negative');
        }
        if ($limit === 0 || $keyNames === []) {
            return [];
        }

        $lookupValues = [];
        foreach ($keyNames as $keyName) {
            if ($keyName !== null && !is_string($keyName)) {
                throw new \InvalidArgumentException('SQLite app_settings lower expression indexed IN lookup names must be strings or null');
            }
            $lookupValues[] = $keyName === null ? null : self::asciiLower($keyName);
        }
        if (!self::containsNonNullValue($lookupValues)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumnInList(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $lookupValues);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings lower(key_name) expression IN-list index is not present');
        }

        $settings = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $lookupValues,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($lookupValues, self::asciiLower($setting->keyName), $indexLookup['collation'])) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @param list<?string> $keyNames
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedUppercaseNames(array $keyNames, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings upper expression indexed IN lookup limit cannot be negative');
        }
        if ($limit === 0 || $keyNames === []) {
            return [];
        }

        $lookupValues = [];
        foreach ($keyNames as $keyName) {
            if ($keyName !== null && !is_string($keyName)) {
                throw new \InvalidArgumentException('SQLite app_settings upper expression indexed IN lookup names must be strings or null');
            }
            $lookupValues[] = $keyName === null ? null : self::asciiUpper($keyName);
        }
        if (!self::containsNonNullValue($lookupValues)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForUpperExpressionColumnInList(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $lookupValues);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings upper(key_name) expression IN-list index is not present');
        }

        $settings = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $lookupValues,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($lookupValues, self::asciiUpper($setting->keyName), $indexLookup['collation'])) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    public function keyValueRowByIndexedNameForLoadPolicy(string $keyName, string $loadPolicy): ?SQLiteKeyValueRow
    {
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForColumn(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $keyName, true, [
            SQLiteKeyValueRow::LOAD_POLICY_COLUMN => $loadPolicy,
        ]);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings key_name index matching the load_policy constraint is not present');
        }

        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $keyName,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (
                $setting->loadPolicy === $loadPolicy
                && self::compareSQLiteScalar($setting->keyName, $keyName, $indexLookup['collation']) === 0
            ) {
                return $setting;
            }
        }

        return null;
    }

    public function keyValueRowByIndexedLowercaseName(string $keyName): ?SQLiteKeyValueRow
    {
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumn(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $keyName);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings lower(key_name) expression index is not present');
        }

        $lookupValue = self::asciiLower($keyName);
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::compareSQLiteScalar(self::asciiLower($setting->keyName), $lookupValue, $indexLookup['collation']) === 0) {
                return $setting;
            }
        }

        return null;
    }

    /**
     * @param callable(string, string): int $compare
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedLowercaseNameWithCollation(
        string $keyName,
        string $collationName,
        callable $compare,
        ?int $limit = null,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation lower expression lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumnWithCollation(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $collationName,
            $keyName,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings lower(key_name) expression index with collation {$collationName} is not present");
        }

        $lookupValue = self::asciiLower($keyName);
        $settings = [];
        foreach ($this->indexCells($indexLookup['rootPage']) as $indexCell) {
            $record = $indexCell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite expression index record must contain at least one key column');
            }
            if (self::compareSQLiteScalarWithCustomTextCollation($record->values[0], $lookupValue, $compare) !== 0) {
                continue;
            }

            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::compareSQLiteScalarWithCustomTextCollation(self::asciiLower($setting->keyName), $lookupValue, $compare) !== 0) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param list<?string> $keyNames
     * @param callable(string, string): int $compare
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedLowercaseNamesWithCollation(
        array $keyNames,
        string $collationName,
        callable $compare,
        ?int $limit = null,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation lower expression IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $keyNames === []) {
            return [];
        }

        $lookupValues = [];
        foreach ($keyNames as $keyName) {
            if ($keyName !== null && !is_string($keyName)) {
                throw new \InvalidArgumentException('SQLite app_settings custom-collation lower expression IN-list names must be strings or null');
            }
            $lookupValues[] = $keyName === null ? null : self::asciiLower($keyName);
        }
        if (!self::containsNonNullValue($lookupValues)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumnInListWithCollation(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $collationName,
            $lookupValues,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings lower(key_name) expression IN-list index with collation {$collationName} is not present");
        }

        $settings = [];
        foreach ($this->indexCells($indexLookup['rootPage']) as $indexCell) {
            $record = $indexCell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite expression index record must contain at least one key column');
            }
            if (!self::inListContainsSQLiteScalarWithCustomTextCollation($lookupValues, $record->values[0], $compare)) {
                continue;
            }

            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (!self::inListContainsSQLiteScalarWithCustomTextCollation($lookupValues, self::asciiLower($setting->keyName), $compare)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param callable(string, string): int $compare
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedLowercaseNameRangeWithCollation(
        ?string $lowerInclusive,
        ?string $upperBound,
        string $collationName,
        callable $compare,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation lower expression range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumnRangeWithCollation(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $collationName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings lower(key_name) expression range index with collation {$collationName} is not present");
        }

        $lowerKey = $lowerInclusive === null ? null : self::asciiLower($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::asciiLower($upperBound);
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalarWithCustomTextCollation($lowerKey, $upperKey, $compare);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach ($this->indexCells($indexLookup['rootPage']) as $indexCell) {
            $record = $indexCell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite expression index record must contain at least one key column');
            }
            if (!self::customFirstValueIsInRange($record->values[0], $lowerKey, $upperKey, $upperInclusive, $compare)) {
                continue;
            }

            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (!self::customFirstValueIsInRange(self::asciiLower($setting->keyName), $lowerKey, $upperKey, $upperInclusive, $compare)) {
                continue;
            }

            $settings[] = $setting;
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    public function keyValueRowByIndexedUppercaseName(string $keyName): ?SQLiteKeyValueRow
    {
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForUpperExpressionColumn(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings upper(key_name) expression index is not present');
        }

        $lookupValue = self::asciiUpper($keyName);
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::compareSQLiteScalar(self::asciiUpper($setting->keyName), $lookupValue, $indexLookup['collation']) === 0) {
                return $setting;
            }
        }

        return null;
    }

    public function keyValueRowByIndexedTrimmedName(
        string $keyName,
        string $functionName = 'trim',
        ?string $characters = null,
    ): ?SQLiteKeyValueRow {
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForTrimExpressionColumn(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $functionName, $characters);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings trim(key_name) expression index is not present');
        }

        $lookupValue = self::sqliteTrim($keyName, $functionName, $characters);
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::compareSQLiteScalar(
                self::sqliteTrim($setting->keyName, $functionName, $characters),
                $lookupValue,
                $indexLookup['collation'],
            ) === 0) {
                return $setting;
            }
        }

        return null;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNamePrefix(string $prefix, ?int $limit = null): array
    {
        if ($prefix === '') {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) prefix lookup requires a non-empty prefix');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) prefix lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $length = strlen($prefix);
        $indexLookup = $this->indexLookupForSubstringExpressionColumn(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            1,
            $length,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) expression index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $prefix,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteSubstring($setting->keyName, 1, $length),
                    $prefix,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @param list<?string> $prefixes
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNamePrefixes(array $prefixes, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) prefix IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $prefixes === []) {
            return [];
        }

        $prefixLength = null;
        foreach ($prefixes as $prefix) {
            if ($prefix !== null && !is_string($prefix)) {
                throw new \InvalidArgumentException('SQLite app_settings substr(key_name) prefix IN-list values must be strings or null');
            }
            if ($prefix === null) {
                continue;
            }
            if ($prefix === '') {
                throw new \InvalidArgumentException('SQLite app_settings substr(key_name) prefix IN-list values must be non-empty');
            }
            $currentLength = self::sqliteLength($prefix);
            if ($prefixLength === null) {
                $prefixLength = $currentLength;
                continue;
            }
            if ($currentLength !== $prefixLength) {
                throw new \InvalidArgumentException('SQLite app_settings substr(key_name) prefix IN-list values must share one prefix length');
            }
        }
        if ($prefixLength === null) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForSubstringExpressionColumnInList(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            1,
            $prefixLength,
            $prefixes,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) expression IN-list index is not present');
        }

        $settings = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $prefixes,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::inListContainsSQLiteScalar(
                $prefixes,
                self::sqliteSubstring($setting->keyName, 1, $prefixLength),
                $indexLookup['collation'],
            )) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameSuffix(string $suffix, ?int $limit = null): array
    {
        if ($suffix === '') {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) suffix lookup requires a non-empty suffix');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) suffix lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $start = -self::sqliteLength($suffix);
        $indexLookup = $this->indexLookupForSubstringExpressionColumn(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $start,
            null,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings substr(key_name) suffix expression index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $suffix,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteSubstring($setting->keyName, $start, null),
                    $suffix,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameLength(int $length, ?int $limit = null): array
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('SQLite app_settings length(key_name) lookup length cannot be negative');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings length(key_name) lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLengthExpressionColumn(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $length);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings length(key_name) expression index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $length,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::sqliteLength($setting->keyName) === $length) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @param list<?int> $lengths
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameLengths(array $lengths, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings length(key_name) IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $lengths === []) {
            return [];
        }

        foreach ($lengths as $length) {
            if ($length === null) {
                continue;
            }
            if (!is_int($length)) {
                throw new \InvalidArgumentException('SQLite app_settings length(key_name) IN-list values must be integers or null');
            }
            if ($length < 0) {
                throw new \InvalidArgumentException('SQLite app_settings length(key_name) IN-list values cannot be negative');
            }
        }
        if (!self::containsNonNullValue($lengths)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLengthExpressionColumnInList(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::KEY_COLUMN, $lengths);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings length(key_name) expression IN-list index is not present');
        }

        $settings = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $lengths,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($lengths, self::sqliteLength($setting->keyName), $indexLookup['collation'])) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameLengthRange(
        ?int $lowerInclusive,
        ?int $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings length(key_name) range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLengthExpressionColumnRange(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings length(key_name) expression range index is not present');
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerInclusive,
                $upperBound,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::sqliteLength($setting->keyName),
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedIntegerValue(int $value, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings CAST(key_value AS INTEGER) lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForIntegerCastExpressionColumn(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::VALUE_COLUMN, $value);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings CAST(key_value AS INTEGER) expression index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $value,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::sqliteCastAsInteger($setting->keyValue) === $value) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @param list<?int> $values
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedIntegerValues(array $values, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings CAST(key_value AS INTEGER) IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $values === []) {
            return [];
        }

        foreach ($values as $value) {
            if ($value !== null && !is_int($value)) {
                throw new \InvalidArgumentException('SQLite app_settings CAST(key_value AS INTEGER) IN-list values must be integers or null');
            }
        }
        if (!self::containsNonNullValue($values)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForIntegerCastExpressionColumnInList(SQLiteKeyValueRow::TABLE_NAME, SQLiteKeyValueRow::VALUE_COLUMN, $values);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings CAST(key_value AS INTEGER) expression IN-list index is not present');
        }

        $settings = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $values,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($values, self::sqliteCastAsInteger($setting->keyValue), $indexLookup['collation'])) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedIntegerValueRange(
        ?int $lowerInclusive,
        ?int $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings CAST(key_value AS INTEGER) range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForIntegerCastExpressionColumnRange(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::VALUE_COLUMN,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings CAST(key_value AS INTEGER) expression range index is not present');
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerInclusive,
                $upperBound,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::sqliteCastAsInteger($setting->keyValue),
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedJsonValue(string $jsonPath, mixed $value, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings json_extract(key_value) lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lookupValue = self::sqliteJsonScalar($value);
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonExtractExpressionColumn(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::VALUE_COLUMN,
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings json_extract(key_value) expression index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteJsonExtract($setting->keyValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                    $lookupValue,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedJsonFragment(string $jsonPath, mixed $value, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings JSON -> lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lookupValue = self::sqliteJsonTextValue($value);
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonValueOperatorExpressionColumn(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::VALUE_COLUMN,
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings JSON -> expression index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteJsonValueOperator($setting->keyValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                    $lookupValue,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @param list<mixed> $values
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedJsonFragments(string $jsonPath, array $values, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings JSON -> IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $values === []) {
            return [];
        }

        $lookupValues = self::sqliteJsonTextValueList($values);
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonValueOperatorExpressionColumn(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::VALUE_COLUMN,
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings JSON -> expression IN-list index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueList(
                $indexLookup['rootPage'],
                $lookupValues,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            $fragment = self::sqliteJsonValueOperator($setting->keyValue, $jsonPath, $row->record->serialTypes[2] ?? null);
            if (
                $fragment !== null
                && self::inListContainsSQLiteScalar($lookupValues, $fragment, $indexLookup['collation'])
            ) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedJsonFragmentRange(
        string $jsonPath,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings JSON -> range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonTextValue($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonTextValue($upperBound);
        if ($lowerKey === null && $upperKey === null) {
            throw new \InvalidArgumentException('SQLite app_settings JSON -> range lookup requires at least one bound');
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonValueOperatorExpressionColumnRange(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::VALUE_COLUMN,
            $jsonPath,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings JSON -> expression range index is not present');
        }
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            $fragment = self::sqliteJsonValueOperator($setting->keyValue, $jsonPath, $row->record->serialTypes[2] ?? null);
            if (
                $fragment !== null
                && self::firstValueIsInRange(
                    $fragment,
                    $lowerKey,
                    $upperKey,
                    $upperInclusive,
                    $indexLookup['collation'],
                )
            ) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @param list<mixed> $values
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedJsonValues(string $jsonPath, array $values, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings json_extract(key_value) IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $values === []) {
            return [];
        }

        $lookupValues = self::sqliteJsonScalarList($values);
        if (!self::containsNonNullValue($lookupValues)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonExtractExpressionColumn(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::VALUE_COLUMN,
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings json_extract(key_value) expression IN-list index is not present');
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueList(
                $indexLookup['rootPage'],
                $lookupValues,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (
                self::inListContainsSQLiteScalar(
                    $lookupValues,
                    self::sqliteJsonExtract($setting->keyValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                    $indexLookup['collation'],
                )
            ) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedJsonValueRange(
        string $jsonPath,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings json_extract(key_value) range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonScalar($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonScalar($upperBound);
        if ($lowerKey === null && $upperKey === null) {
            throw new \InvalidArgumentException('SQLite app_settings json_extract(key_value) range lookup requires at least one bound');
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonExtractExpressionColumnRange(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::VALUE_COLUMN,
            $jsonPath,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings json_extract(key_value) expression range index is not present');
        }
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::sqliteJsonExtract($setting->keyValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                $lowerKey,
                $upperKey,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedLowercaseNameRange(
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings lower expression indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumnRange(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings lower(key_name) expression range index is not present');
        }

        $lowerKey = $lowerInclusive === null ? null : self::asciiLower($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::asciiLower($upperBound);
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::asciiLower($setting->keyName),
                $lowerKey,
                $upperKey,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedUppercaseNameRange(
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings upper expression indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForUpperExpressionColumnRange(
            SQLiteKeyValueRow::TABLE_NAME,
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings upper(key_name) expression range index is not present');
        }

        $lowerKey = $lowerInclusive === null ? null : self::asciiUpper($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::asciiUpper($upperBound);
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings expression index points to missing rowid {$rowId}");
            }

            $setting = SQLiteKeyValueRow::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::asciiUpper($setting->keyName),
                $lowerKey,
                $upperKey,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $settings[] = $setting;
                if ($limit !== null && count($settings) >= $limit) {
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedLoadPolicy(string $loadPolicy, ?int $limit = null): array
    {
        return $this->keyValueRowsByIndexedFirstColumn(SQLiteKeyValueRow::LOAD_POLICY_COLUMN, $loadPolicy, $limit);
    }

    public function keyValueRowByIndexedLoadPolicyAndName(string $loadPolicy, string $keyName): ?SQLiteKeyValueRow
    {
        $settings = $this->keyValueRowsByIndexedColumnPrefix([
            SQLiteKeyValueRow::LOAD_POLICY_COLUMN => $loadPolicy,
            SQLiteKeyValueRow::KEY_COLUMN => $keyName,
        ], 1);

        return $settings[0] ?? null;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedLoadPolicyAndNameRange(
        string $loadPolicy,
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        return $this->keyValueRowsByIndexedColumnPrefixRange(
            [SQLiteKeyValueRow::LOAD_POLICY_COLUMN => $loadPolicy],
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $limit,
            $upperInclusive,
        );
    }

    /**
     * @param non-empty-array<string, mixed> $equalityPrefix
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameRangeWithPrefix(
        array $equalityPrefix,
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($equalityPrefix === []) {
            throw new \InvalidArgumentException('SQLite app_settings indexed name range lookup requires at least one equality column');
        }

        return $this->keyValueRowsByIndexedColumnPrefixRange(
            $equalityPrefix,
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $limit,
            $upperInclusive,
        );
    }

    /**
     * @param non-empty-array<string, mixed> $equalityPrefix
     * @param callable(string, string): int $compare
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameRangeWithPrefixAndCollation(
        array $equalityPrefix,
        ?string $lowerInclusive,
        ?string $upperBound,
        string $collationName,
        callable $compare,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($equalityPrefix === []) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation indexed name range lookup requires at least one equality column');
        }

        return $this->keyValueRowsByIndexedColumnPrefixRangeWithCollation(
            $equalityPrefix,
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $collationName,
            $compare,
            $limit,
            $upperInclusive,
        );
    }

    /**
     * @param non-empty-array<string, mixed> $equalityPrefix
     * @param array<string, callable(string, string): int> $customCollations
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameRangeWithPrefixCollations(
        array $equalityPrefix,
        ?string $lowerInclusive,
        ?string $upperBound,
        array $customCollations,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($equalityPrefix === []) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation indexed name range lookup requires at least one equality column');
        }

        return $this->keyValueRowsByIndexedColumnPrefixRangeWithCollations(
            $equalityPrefix,
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $customCollations,
            $limit,
            $upperInclusive,
        );
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    public function keyValueRowsByIndexedNameRange(
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array
    {
        return $this->keyValueRowsByIndexedFirstColumnRange(
            SQLiteKeyValueRow::KEY_COLUMN,
            $lowerInclusive,
            $upperBound,
            $limit,
            $upperInclusive,
        );
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
     * @param list<SQLiteTableLeafCell> $cells
     */
    private function collectTableLeafCellsByRowIdRange(
        int $pageNumber,
        ?int $lowerInclusive,
        ?int $upperBound,
        bool $upperInclusive,
        array &$visited,
        array &$cells,
        ?int $limit,
        bool $hasIntervalLowerExclusive,
        ?int $intervalLowerExclusive,
        bool $hasIntervalUpperInclusive,
        ?int $intervalUpperInclusive,
    ): void {
        if ($limit !== null && count($cells) >= $limit) {
            return;
        }
        if (
            !self::rowIdRangeIntersectsInterval(
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $hasIntervalLowerExclusive,
                $intervalLowerExclusive,
                $hasIntervalUpperInclusive,
                $intervalUpperInclusive,
            )
        ) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite table b-tree rowid range traversal reached page {$pageNumber} more than once");
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
                if (self::rowIdIsInRange($cell->rowId, $lowerInclusive, $upperBound, $upperInclusive)) {
                    $cells[] = $cell;
                }
            }

            return;
        }
        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a table b-tree page");
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite table interior page {$pageNumber} has an invalid right-most pointer");
        }

        $hasPrevious = false;
        $previousKey = null;
        foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $interiorCell) {
            $this->collectTableLeafCellsByRowIdRange(
                $interiorCell->leftChildPage,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $visited,
                $cells,
                $limit,
                $hasPrevious,
                $previousKey,
                true,
                $interiorCell->key,
            );
            if ($limit !== null && count($cells) >= $limit) {
                return;
            }
            $hasPrevious = true;
            $previousKey = $interiorCell->key;
        }
        $this->collectTableLeafCellsByRowIdRange(
            $header->rightMostPointer,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $visited,
            $cells,
            $limit,
            $hasPrevious,
            $previousKey,
            false,
            null,
        );
    }

    private static function rowIdRangeIsEmpty(?int $lowerInclusive, ?int $upperBound, bool $upperInclusive): bool
    {
        return $lowerInclusive !== null
            && $upperBound !== null
            && ($lowerInclusive > $upperBound || ($lowerInclusive === $upperBound && !$upperInclusive));
    }

    private static function rowIdIsInRange(
        int $rowId,
        ?int $lowerInclusive,
        ?int $upperBound,
        bool $upperInclusive,
    ): bool {
        if ($lowerInclusive !== null && $rowId < $lowerInclusive) {
            return false;
        }
        if ($upperBound === null) {
            return true;
        }
        if ($rowId > $upperBound) {
            return false;
        }

        return $upperInclusive || $rowId !== $upperBound;
    }

    private static function rowIdRangeIntersectsInterval(
        ?int $lowerInclusive,
        ?int $upperBound,
        bool $upperInclusive,
        bool $hasIntervalLowerExclusive,
        ?int $intervalLowerExclusive,
        bool $hasIntervalUpperInclusive,
        ?int $intervalUpperInclusive,
    ): bool {
        if (self::rowIdRangeIsEmpty($lowerInclusive, $upperBound, $upperInclusive)) {
            return false;
        }
        if (
            $upperBound !== null
            && $hasIntervalLowerExclusive
            && $intervalLowerExclusive !== null
            && $upperBound <= $intervalLowerExclusive
        ) {
            return false;
        }
        if (
            $lowerInclusive !== null
            && $hasIntervalUpperInclusive
            && $intervalUpperInclusive !== null
            && $lowerInclusive > $intervalUpperInclusive
        ) {
            return false;
        }

        return true;
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

    /**
     * @return list<SQLiteKeyValueRow>
     */
    private function keyValueRowsByIndexedFirstColumn(string $columnName, mixed $value, ?int $limit): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings indexed lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumn(SQLiteKeyValueRow::TABLE_NAME, $columnName, $value, true);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings {$columnName} index is not present");
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $value,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteKeyValueRow>
     */
    private function keyValueRowsByIndexedFirstColumnRange(
        string $columnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumnRange(SQLiteKeyValueRow::TABLE_NAME, $columnName, $lowerInclusive, $upperBound, $upperInclusive);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings {$columnName} range index is not present");
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerInclusive,
                $upperBound,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param non-empty-array<string, mixed> $columnValues
     * @return list<SQLiteKeyValueRow>
     */
    private function keyValueRowsByIndexedColumnPrefix(array $columnValues, ?int $limit): array
    {
        if ($columnValues === []) {
            throw new \InvalidArgumentException('SQLite app_settings indexed lookup requires at least one column');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings indexed lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $columnNames = array_keys($columnValues);
        $values = array_values($columnValues);
        $indexLookup = $this->indexLookupForColumnPrefix(SQLiteKeyValueRow::TABLE_NAME, $columnNames, $values);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings composite index is not present');
        }

        $settings = [];
        foreach ($this->indexCellsByColumnPrefix($indexLookup['rootPage'], $values, $indexLookup['columns']) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param non-empty-array<string, mixed> $equalityColumnValues
     * @return list<SQLiteKeyValueRow>
     */
    private function keyValueRowsByIndexedColumnPrefixRange(
        array $equalityColumnValues,
        string $rangeColumnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit,
        bool $upperInclusive = false,
    ): array {
        if ($equalityColumnValues === []) {
            throw new \InvalidArgumentException('SQLite app_settings indexed range lookup requires at least one equality column');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $equalityColumnNames = array_keys($equalityColumnValues);
        $equalityValues = array_values($equalityColumnValues);
        $indexLookup = $this->indexLookupForColumnPrefixRange(
            SQLiteKeyValueRow::TABLE_NAME,
            $equalityColumnNames,
            $equalityValues,
            $rangeColumnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings composite range index is not present');
        }
        $rangeColumn = $indexLookup['columns'][count($equalityValues)] ?? null;
        if ($rangeColumn === null) {
            throw new \InvalidArgumentException('SQLite app_settings composite range index is missing the range column');
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $rangeColumn->collation);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByColumnPrefixRange(
                $indexLookup['rootPage'],
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $indexLookup['columns'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param non-empty-array<string, mixed> $equalityColumnValues
     * @param callable(string, string): int $compare
     * @return list<SQLiteKeyValueRow>
     */
    private function keyValueRowsByIndexedColumnPrefixRangeWithCollation(
        array $equalityColumnValues,
        string $rangeColumnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        string $collationName,
        callable $compare,
        ?int $limit,
        bool $upperInclusive = false,
    ): array {
        if ($equalityColumnValues === []) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation indexed range lookup requires at least one equality column');
        }
        if ($collationName === '') {
            throw new \InvalidArgumentException('SQLite custom collation name cannot be empty');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite custom-collation index prefix range lookup requires at least one bound');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $equalityColumnNames = array_keys($equalityColumnValues);
        $equalityValues = array_values($equalityColumnValues);
        $indexLookup = $this->indexLookupForColumnPrefixRangeWithRangeCollation(
            SQLiteKeyValueRow::TABLE_NAME,
            $equalityColumnNames,
            $equalityValues,
            $rangeColumnName,
            $collationName,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite app_settings composite range index with collation {$collationName} is not present");
        }

        $rangeIndex = count($equalityValues);
        $rangeColumn = $indexLookup['columns'][$rangeIndex] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation composite range index is missing the range column');
        }

        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalarWithCustomTextCollation($lowerInclusive, $upperBound, $compare);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach ($this->indexCells($indexLookup['rootPage']) as $indexCell) {
            $record = $indexCell->record($this->header->textEncoding);
            if (count($record->values) <= $rangeIndex) {
                throw new \InvalidArgumentException('SQLite index record has fewer values than the constrained custom-collation prefix range');
            }

            foreach ($equalityValues as $index => $value) {
                if (self::compareSQLiteScalar($record->values[$index], $value, $indexLookup['columns'][$index]->collation) !== 0) {
                    continue 2;
                }
            }

            if (!self::customFirstValueIsInRange(
                $record->values[$rangeIndex],
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $compare,
            )) {
                continue;
            }

            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @param non-empty-array<string, mixed> $equalityColumnValues
     * @param array<string, callable(string, string): int> $customCollations
     * @return list<SQLiteKeyValueRow>
     */
    private function keyValueRowsByIndexedColumnPrefixRangeWithCollations(
        array $equalityColumnValues,
        string $rangeColumnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        array $customCollations,
        ?int $limit,
        bool $upperInclusive = false,
    ): array {
        if ($equalityColumnValues === []) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation indexed range lookup requires at least one equality column');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite custom-collation index prefix range lookup requires at least one bound');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $normalizedCollations = self::normalizeCustomCollations($customCollations);
        $tableRootPage = $this->tableRootPage(SQLiteKeyValueRow::TABLE_NAME);
        if ($tableRootPage === null) {
            return [];
        }

        $equalityColumnNames = array_keys($equalityColumnValues);
        $equalityValues = array_values($equalityColumnValues);
        $indexLookup = $this->indexLookupForColumnPrefixRangeWithCollations(
            SQLiteKeyValueRow::TABLE_NAME,
            $equalityColumnNames,
            $equalityValues,
            $rangeColumnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $normalizedCollations,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite app_settings composite range index with supplied collations is not present');
        }

        $rangeColumn = $indexLookup['columns'][count($equalityValues)] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite app_settings custom-collation composite range index is missing the range column');
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalarForIndexColumn(
                $lowerInclusive,
                $upperBound,
                $rangeColumn,
                $normalizedCollations,
            );
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $settings = [];
        foreach (
            $this->indexCellsByColumnPrefixRange(
                $indexLookup['rootPage'],
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $indexLookup['columns'],
                $normalizedCollations,
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite app_settings index points to missing rowid {$rowId}");
            }

            $settings[] = SQLiteKeyValueRow::fromTableRow($row);
            if ($limit !== null && count($settings) >= $limit) {
                break;
            }
        }

        return $settings;
    }

    /**
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByFirstValue(
        int $rootPageNumber,
        mixed $value,
        string $collation,
        bool $descending = false,
    ): array
    {
        return $this->indexCellsByFirstValueRange(
            $rootPageNumber,
            $value,
            $value,
            $collation,
            true,
            $descending,
        );
    }

    /**
     * @param list<mixed> $values
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByFirstValueList(
        int $rootPageNumber,
        array $values,
        string $collation,
        bool $descending,
    ): array
    {
        $matches = [];
        $visited = [];
        $this->collectIndexCellsByFirstValueList(
            $rootPageNumber,
            $values,
            $collation,
            $descending,
            $visited,
            $matches,
            false,
            null,
            false,
            null,
        );

        return $matches;
    }

    /**
     * @param list<mixed> $values
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $matches
     */
    private function collectIndexCellsByFirstValueList(
        int $pageNumber,
        array $values,
        string $collation,
        bool $descending,
        array &$visited,
        array &$matches,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
    ): void {
        if (
            !self::firstValueListIntersectsInterval(
                $values,
                $hasIntervalLower,
                $intervalLower,
                $hasIntervalUpper,
                $intervalUpper,
                $collation,
            )
        ) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree bounded IN-list lookup reached page {$pageNumber} more than once");
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
        if ($header->pageType === 'index-leaf') {
            foreach ($cells as $cell) {
                $record = $cell->record($this->header->textEncoding);
                if ($record->values === []) {
                    throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
                }
                if (self::inListContainsSQLiteScalar($values, $record->values[0], $collation)) {
                    $matches[] = $cell;
                }
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $hasPrevious = false;
        $previousValue = null;
        foreach ($cells as $cell) {
            if ($cell->leftChildPage === null || $cell->leftChildPage < 1) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
            }

            $record = $cell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            $currentValue = $record->values[0];

            $childHasLower = $descending ? true : $hasPrevious;
            $childLower = $descending ? $currentValue : $previousValue;
            $childHasUpper = $descending ? $hasPrevious : true;
            $childUpper = $descending ? $previousValue : $currentValue;
            $this->collectIndexCellsByFirstValueList(
                $cell->leftChildPage,
                $values,
                $collation,
                $descending,
                $visited,
                $matches,
                $childHasLower,
                $childLower,
                $childHasUpper,
                $childUpper,
            );

            if (self::inListContainsSQLiteScalar($values, $currentValue, $collation)) {
                $matches[] = $cell;
            }

            $hasPrevious = true;
            $previousValue = $currentValue;
        }

        $rightHasLower = $descending ? false : $hasPrevious;
        $rightLower = $descending ? null : $previousValue;
        $rightHasUpper = $descending ? $hasPrevious : false;
        $rightUpper = $descending ? $previousValue : null;
        $this->collectIndexCellsByFirstValueList(
            $header->rightMostPointer,
            $values,
            $collation,
            $descending,
            $visited,
            $matches,
            $rightHasLower,
            $rightLower,
            $rightHasUpper,
            $rightUpper,
        );
    }

    /**
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByFirstValueRange(
        int $rootPageNumber,
        mixed $lowerInclusive,
        mixed $upperBound,
        string $collation,
        bool $upperInclusive = false,
        bool $descending = false,
    ): array {
        $matches = [];
        $visited = [];
        $this->collectIndexCellsByFirstValueRange(
            $rootPageNumber,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $collation,
            $descending,
            $visited,
            $matches,
            false,
            null,
            false,
            null,
        );

        return $matches;
    }

    /**
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $matches
     */
    private function collectIndexCellsByFirstValueRange(
        int $pageNumber,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        string $collation,
        bool $descending,
        array &$visited,
        array &$matches,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
    ): void {
        if (
            !self::firstValueRangeIntersectsInterval(
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $hasIntervalLower,
                $intervalLower,
                $hasIntervalUpper,
                $intervalUpper,
                $collation,
            )
        ) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree bounded lookup reached page {$pageNumber} more than once");
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
        if ($header->pageType === 'index-leaf') {
            foreach ($cells as $cell) {
                $record = $cell->record($this->header->textEncoding);
                if ($record->values === []) {
                    throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
                }
                if (self::firstValueIsInRange($record->values[0], $lowerInclusive, $upperBound, $upperInclusive, $collation)) {
                    $matches[] = $cell;
                }
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $hasPrevious = false;
        $previousValue = null;
        foreach ($cells as $cell) {
            if ($cell->leftChildPage === null || $cell->leftChildPage < 1) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
            }

            $record = $cell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            $currentValue = $record->values[0];

            $childHasLower = $descending ? true : $hasPrevious;
            $childLower = $descending ? $currentValue : $previousValue;
            $childHasUpper = $descending ? $hasPrevious : true;
            $childUpper = $descending ? $previousValue : $currentValue;
            $this->collectIndexCellsByFirstValueRange(
                $cell->leftChildPage,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $collation,
                $descending,
                $visited,
                $matches,
                $childHasLower,
                $childLower,
                $childHasUpper,
                $childUpper,
            );

            if (self::firstValueIsInRange($currentValue, $lowerInclusive, $upperBound, $upperInclusive, $collation)) {
                $matches[] = $cell;
            }

            $hasPrevious = true;
            $previousValue = $currentValue;
        }

        $rightHasLower = $descending ? false : $hasPrevious;
        $rightLower = $descending ? null : $previousValue;
        $rightHasUpper = $descending ? $hasPrevious : false;
        $rightUpper = $descending ? $previousValue : null;
        $this->collectIndexCellsByFirstValueRange(
            $header->rightMostPointer,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $collation,
            $descending,
            $visited,
            $matches,
            $rightHasLower,
            $rightLower,
            $rightHasUpper,
            $rightUpper,
        );
    }

    private static function firstValueIsInRange(
        mixed $value,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        string $collation,
    ): bool {
        if (($lowerInclusive !== null || $upperBound !== null) && $value === null) {
            return false;
        }
        if ($lowerInclusive !== null && self::compareSQLiteScalar($value, $lowerInclusive, $collation) < 0) {
            return false;
        }
        if ($upperBound !== null) {
            $upperComparison = self::compareSQLiteScalar($value, $upperBound, $collation);
            if ($upperComparison > 0 || ($upperComparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param callable(string, string): int $compare
     */
    private static function customFirstValueIsInRange(
        mixed $value,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        callable $compare,
    ): bool {
        if (($lowerInclusive !== null || $upperBound !== null) && $value === null) {
            return false;
        }
        if ($lowerInclusive !== null && self::compareSQLiteScalarWithCustomTextCollation($value, $lowerInclusive, $compare) < 0) {
            return false;
        }
        if ($upperBound !== null) {
            $upperComparison = self::compareSQLiteScalarWithCustomTextCollation($value, $upperBound, $compare);
            if ($upperComparison > 0 || ($upperComparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    private static function firstValueRangeIntersectsInterval(
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
        string $collation,
    ): bool {
        if (
            $lowerInclusive !== null
            && $hasIntervalUpper
            && self::compareSQLiteScalar($intervalUpper, $lowerInclusive, $collation) < 0
        ) {
            return false;
        }
        if ($upperBound !== null && $hasIntervalLower) {
            $lowerToUpper = self::compareSQLiteScalar($intervalLower, $upperBound, $collation);
            if ($lowerToUpper > 0 || ($lowerToUpper === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $values
     */
    private static function firstValueListIntersectsInterval(
        array $values,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
        string $collation,
    ): bool {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (
                $hasIntervalLower
                && self::compareSQLiteScalar($value, $intervalLower, $collation) < 0
            ) {
                continue;
            }
            if (
                $hasIntervalUpper
                && self::compareSQLiteScalar($value, $intervalUpper, $collation) > 0
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByColumnPrefixRange(
        int $rootPageNumber,
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        array $columns,
        array $customCollations = [],
    ): array {
        $rangeIndex = count($equalityValues);
        $rangeColumn = $columns[$rangeIndex] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite index range column metadata is missing');
        }

        $matches = [];
        $visited = [];
        $this->collectIndexCellsByColumnPrefixRange(
            $rootPageNumber,
            $equalityValues,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $columns,
            $visited,
            $matches,
            false,
            null,
            false,
            null,
            $customCollations,
        );

        return $matches;
    }

    /**
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $matches
     * @param null|list<mixed> $intervalLowerValues
     * @param null|list<mixed> $intervalUpperValues
     * @param array<string, callable(string, string): int> $customCollations
     */
    private function collectIndexCellsByColumnPrefixRange(
        int $pageNumber,
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        array $columns,
        array &$visited,
        array &$matches,
        bool $hasIntervalLower,
        ?array $intervalLowerValues,
        bool $hasIntervalUpper,
        ?array $intervalUpperValues,
        array $customCollations,
    ): void {
        if (
            !self::columnPrefixRangeIntersectsInterval(
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $columns,
                $hasIntervalLower,
                $intervalLowerValues,
                $hasIntervalUpper,
                $intervalUpperValues,
                $customCollations,
            )
        ) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree bounded composite lookup reached page {$pageNumber} more than once");
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
        if ($header->pageType === 'index-leaf') {
            foreach ($cells as $cell) {
                $record = $cell->record($this->header->textEncoding);
                if (self::indexRecordMatchesColumnPrefixRange(
                    $record->values,
                    $equalityValues,
                    $lowerInclusive,
                    $upperBound,
                    $upperInclusive,
                    $columns,
                    $customCollations,
                )) {
                    $matches[] = $cell;
                }
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $hasPrevious = false;
        $previousValues = null;
        foreach ($cells as $cell) {
            if ($cell->leftChildPage === null || $cell->leftChildPage < 1) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
            }

            $record = $cell->record($this->header->textEncoding);
            $currentValues = $record->values;
            $this->collectIndexCellsByColumnPrefixRange(
                $cell->leftChildPage,
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $columns,
                $visited,
                $matches,
                $hasPrevious ? true : $hasIntervalLower,
                $hasPrevious ? $previousValues : $intervalLowerValues,
                true,
                $currentValues,
                $customCollations,
            );

            if (self::indexRecordMatchesColumnPrefixRange(
                $record->values,
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $columns,
                $customCollations,
            )) {
                $matches[] = $cell;
            }

            $hasPrevious = true;
            $previousValues = $currentValues;
        }

        $this->collectIndexCellsByColumnPrefixRange(
            $header->rightMostPointer,
            $equalityValues,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $columns,
            $visited,
            $matches,
            $hasPrevious ? true : $hasIntervalLower,
            $hasPrevious ? $previousValues : $intervalLowerValues,
            $hasIntervalUpper,
            $intervalUpperValues,
            $customCollations,
        );
    }

    /**
     * @param list<mixed> $recordValues
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @param array<string, callable(string, string): int> $customCollations
     */
    private static function indexRecordMatchesColumnPrefixRange(
        array $recordValues,
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        array $columns,
        array $customCollations,
    ): bool {
        $rangeIndex = count($equalityValues);
        $rangeColumn = $columns[$rangeIndex] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite index range column metadata is missing');
        }
        if (count($recordValues) <= $rangeIndex) {
            throw new \InvalidArgumentException('SQLite index record has fewer values than the constrained prefix range');
        }

        foreach ($equalityValues as $index => $value) {
            if (self::compareSQLiteScalarForIndexColumn($recordValues[$index], $value, $columns[$index], $customCollations) !== 0) {
                return false;
            }
        }

        $rangeValue = $recordValues[$rangeIndex];
        if (($lowerInclusive !== null || $upperBound !== null) && $rangeValue === null) {
            return false;
        }
        if ($lowerInclusive !== null && self::compareSQLiteScalarForIndexColumn($rangeValue, $lowerInclusive, $rangeColumn, $customCollations) < 0) {
            return false;
        }
        if ($upperBound !== null) {
            $upperComparison = self::compareSQLiteScalarForIndexColumn($rangeValue, $upperBound, $rangeColumn, $customCollations);
            if ($upperComparison > 0 || ($upperComparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @param null|list<mixed> $intervalLowerValues
     * @param null|list<mixed> $intervalUpperValues
     * @param array<string, callable(string, string): int> $customCollations
     */
    private static function columnPrefixRangeIntersectsInterval(
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        array $columns,
        bool $hasIntervalLower,
        ?array $intervalLowerValues,
        bool $hasIntervalUpper,
        ?array $intervalUpperValues,
        array $customCollations,
    ): bool {
        $rangeIndex = count($equalityValues);
        $prefixLength = count($equalityValues);
        $rangeColumn = $columns[$rangeIndex] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite index range column metadata is missing');
        }

        if ($prefixLength > 0) {
            if (
                $hasIntervalUpper
                && $intervalUpperValues !== null
                && count($intervalUpperValues) >= $prefixLength
                && self::compareIndexKeyValues(
                    array_slice($intervalUpperValues, 0, $prefixLength),
                    $equalityValues,
                    array_slice($columns, 0, $prefixLength),
                    $customCollations,
                ) < 0
            ) {
                return false;
            }
            if (
                $hasIntervalLower
                && $intervalLowerValues !== null
                && count($intervalLowerValues) >= $prefixLength
                && self::compareIndexKeyValues(
                    array_slice($intervalLowerValues, 0, $prefixLength),
                    $equalityValues,
                    array_slice($columns, 0, $prefixLength),
                    $customCollations,
                ) > 0
            ) {
                return false;
            }
        }

        $physicalLower = null;
        $physicalUpper = null;
        if ($rangeColumn->descending) {
            if ($upperBound !== null) {
                $physicalLower = array_merge($equalityValues, [$upperBound]);
            }
            if ($lowerInclusive !== null) {
                $physicalUpper = array_merge($equalityValues, [$lowerInclusive]);
            }
        } else {
            if ($lowerInclusive !== null) {
                $physicalLower = array_merge($equalityValues, [$lowerInclusive]);
            }
            if ($upperBound !== null) {
                $physicalUpper = array_merge($equalityValues, [$upperBound]);
            }
        }

        $constrainedColumns = array_slice($columns, 0, $rangeIndex + 1);
        if (
            $physicalLower !== null
            && $hasIntervalUpper
            && $intervalUpperValues !== null
            && count($intervalUpperValues) >= $rangeIndex + 1
            && self::compareIndexKeyValues(
                array_slice($intervalUpperValues, 0, $rangeIndex + 1),
                $physicalLower,
                $constrainedColumns,
                $customCollations,
            ) < 0
        ) {
            return false;
        }
        if (
            $physicalUpper !== null
            && $hasIntervalLower
            && $intervalLowerValues !== null
            && count($intervalLowerValues) >= $rangeIndex + 1
            && self::compareIndexKeyValues(
                array_slice($intervalLowerValues, 0, $rangeIndex + 1),
                $physicalUpper,
                $constrainedColumns,
                $customCollations,
            ) > 0
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param list<mixed> $leftValues
     * @param list<mixed> $rightValues
     * @param list<SQLiteIndexColumn> $columns
     * @param array<string, callable(string, string): int> $customCollations
     */
    private static function compareIndexKeyValues(
        array $leftValues,
        array $rightValues,
        array $columns,
        array $customCollations = [],
    ): int
    {
        foreach ($columns as $index => $column) {
            if (!array_key_exists($index, $leftValues) || !array_key_exists($index, $rightValues)) {
                break;
            }
            $comparison = self::compareSQLiteScalarForIndexColumn(
                $leftValues[$index],
                $rightValues[$index],
                $column,
                $customCollations,
            );
            if ($comparison !== 0) {
                return $column->descending ? -$comparison : $comparison;
            }
        }

        return count($leftValues) <=> count($rightValues);
    }

    /**
     * @param list<mixed> $values
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByColumnPrefix(int $rootPageNumber, array $values, array $columns): array
    {
        $matches = [];
        foreach ($this->indexCells($rootPageNumber) as $cell) {
            $record = $cell->record($this->header->textEncoding);
            if (count($record->values) < count($values)) {
                throw new \InvalidArgumentException('SQLite index record has fewer values than the constrained prefix');
            }

            foreach ($values as $index => $value) {
                if (self::compareSQLiteScalar($record->values[$index], $value, $columns[$index]->collation) !== 0) {
                    continue 2;
                }
            }

            $matches[] = $cell;
        }

        return $matches;
    }

    /**
     * @param non-empty-list<string> $columnNames
     * @param non-empty-list<mixed> $pointLookupValues
     * @return null|array{rootPage:int,columns:non-empty-list<SQLiteIndexColumn>}
     */
    private function indexLookupForColumnPrefix(string $tableName, array $columnNames, array $pointLookupValues): ?array
    {
        if ($columnNames === []) {
            throw new \InvalidArgumentException('SQLite index prefix lookup requires at least one column');
        }
        if (count($columnNames) !== count($pointLookupValues)) {
            throw new \InvalidArgumentException('SQLite index prefix lookup requires one value per column');
        }

        $automaticIndexColumns = null;
        $automaticIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                if (!self::isAutomaticIndex($record, $tableName)) {
                    continue;
                }
                if ($automaticIndexColumns === null) {
                    $automaticIndexColumns = $this->automaticIndexColumnsForTable($tableName);
                }
                $columns = $automaticIndexColumns[$automaticIndexOrdinal] ?? null;
                $automaticIndexOrdinal++;
            } else {
                $columns = SQLiteCreateIndex::columns($record->sql);
            }
            if ($columns === null || count($columns) < count($columnNames)) {
                continue;
            }

            $prefix = array_slice($columns, 0, count($columnNames));
            foreach ($prefix as $index => $column) {
                if (strcasecmp($column->columnName, $columnNames[$index]) !== 0) {
                    continue 2;
                }
            }

            if (
                $prefix[0]->partial
                && (
                    $prefix[0]->partialPredicate === null
                    || !self::partialPredicateIsImpliedByConstraints(
                        $prefix[0]->partialPredicate,
                        array_combine($columnNames, $pointLookupValues),
                        [],
                        true,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'columns' => $prefix,
            ];
        }

        return null;
    }

    /**
     * @param non-empty-list<string> $equalityColumnNames
     * @param non-empty-list<mixed> $pointLookupValues
     * @return null|array{rootPage:int,columns:non-empty-list<SQLiteIndexColumn>}
     */
    private function indexLookupForColumnPrefixRange(
        string $tableName,
        array $equalityColumnNames,
        array $pointLookupValues,
        string $rangeColumnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($equalityColumnNames === []) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires at least one equality column');
        }
        if (count($equalityColumnNames) !== count($pointLookupValues)) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires one value per equality column');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires at least one range bound');
        }

        $wantedColumnNames = array_merge($equalityColumnNames, [$rangeColumnName]);
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $columns = SQLiteCreateIndex::columns($record->sql);
            if ($columns === null || count($columns) < count($wantedColumnNames)) {
                continue;
            }

            $prefix = array_slice($columns, 0, count($wantedColumnNames));
            foreach ($prefix as $index => $column) {
                if (strcasecmp($column->columnName, $wantedColumnNames[$index]) !== 0) {
                    continue 2;
                }
            }

            if ($prefix[0]->partial) {
                $equalityConstraints = array_combine($equalityColumnNames, $pointLookupValues);
                if ($equalityConstraints === false) {
                    $equalityConstraints = [];
                }
                $rangeConstraints = [
                    $rangeColumnName => [
                        'lowerInclusive' => $lowerInclusive,
                        'upperBound' => $upperBound,
                        'upperInclusive' => $upperInclusive,
                    ],
                ];
                if (
                    $prefix[0]->partialPredicate === null
                    || !self::partialPredicateIsImpliedByConstraints(
                        $prefix[0]->partialPredicate,
                        $equalityConstraints,
                        $rangeConstraints,
                        true,
                    )
                ) {
                    continue;
                }
            }

            return [
                'rootPage' => $record->rootPage,
                'columns' => $prefix,
            ];
        }

        return null;
    }

    /**
     * @param non-empty-list<string> $equalityColumnNames
     * @param non-empty-list<mixed> $pointLookupValues
     * @return null|array{rootPage:int,columns:non-empty-list<SQLiteIndexColumn>}
     */
    private function indexLookupForColumnPrefixRangeWithRangeCollation(
        string $tableName,
        array $equalityColumnNames,
        array $pointLookupValues,
        string $rangeColumnName,
        string $rangeCollationName,
    ): ?array {
        if ($equalityColumnNames === []) {
            throw new \InvalidArgumentException('SQLite custom-collation index prefix range lookup requires at least one equality column');
        }
        if (count($equalityColumnNames) !== count($pointLookupValues)) {
            throw new \InvalidArgumentException('SQLite custom-collation index prefix range lookup requires one value per equality column');
        }
        if ($rangeCollationName === '') {
            throw new \InvalidArgumentException('SQLite custom collation name cannot be empty');
        }

        $wantedColumnNames = array_merge($equalityColumnNames, [$rangeColumnName]);
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $columns = SQLiteCreateIndex::columns($record->sql);
            if ($columns === null || count($columns) < count($wantedColumnNames)) {
                continue;
            }

            $prefix = array_slice($columns, 0, count($wantedColumnNames));
            foreach ($prefix as $index => $column) {
                if (strcasecmp($column->columnName, $wantedColumnNames[$index]) !== 0) {
                    continue 2;
                }
            }

            $rangeColumn = $prefix[count($equalityColumnNames)] ?? null;
            if (
                !$rangeColumn instanceof SQLiteIndexColumn
                || strcasecmp($rangeColumn->collation, $rangeCollationName) !== 0
            ) {
                continue;
            }

            if ($prefix[0]->partial) {
                $equalityConstraints = array_combine($equalityColumnNames, $pointLookupValues);
                if ($equalityConstraints === false) {
                    $equalityConstraints = [];
                }
                if (
                    $prefix[0]->partialPredicate === null
                    || !self::partialPredicateIsImpliedByEqualityAndNonNullRange(
                        $prefix[0]->partialPredicate,
                        $equalityConstraints,
                        $rangeColumnName,
                    )
                ) {
                    continue;
                }
            }

            return [
                'rootPage' => $record->rootPage,
                'columns' => $prefix,
            ];
        }

        return null;
    }

    /**
     * @param non-empty-list<string> $equalityColumnNames
     * @param non-empty-list<mixed> $pointLookupValues
     * @param array<string, callable(string, string): int> $customCollations
     * @return null|array{rootPage:int,columns:non-empty-list<SQLiteIndexColumn>}
     */
    private function indexLookupForColumnPrefixRangeWithCollations(
        string $tableName,
        array $equalityColumnNames,
        array $pointLookupValues,
        string $rangeColumnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        array $customCollations,
    ): ?array {
        if ($equalityColumnNames === []) {
            throw new \InvalidArgumentException('SQLite custom-collation index prefix range lookup requires at least one equality column');
        }
        if (count($equalityColumnNames) !== count($pointLookupValues)) {
            throw new \InvalidArgumentException('SQLite custom-collation index prefix range lookup requires one value per equality column');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite custom-collation index prefix range lookup requires at least one bound');
        }

        $wantedColumnNames = array_merge($equalityColumnNames, [$rangeColumnName]);
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $columns = SQLiteCreateIndex::columns($record->sql);
            if ($columns === null || count($columns) < count($wantedColumnNames)) {
                continue;
            }

            $prefix = array_slice($columns, 0, count($wantedColumnNames));
            foreach ($prefix as $index => $column) {
                if (strcasecmp($column->columnName, $wantedColumnNames[$index]) !== 0) {
                    continue 2;
                }
            }
            if (!self::indexColumnsHaveSupportedCollations($prefix, $customCollations)) {
                continue;
            }
            $usesCustomCollation = self::indexColumnsUseCustomCollations($prefix);

            if ($prefix[0]->partial) {
                $equalityConstraints = array_combine($equalityColumnNames, $pointLookupValues);
                if ($equalityConstraints === false) {
                    $equalityConstraints = [];
                }
                $predicateIsImplied = false;
                if ($prefix[0]->partialPredicate !== null) {
                    if ($usesCustomCollation) {
                        $predicateIsImplied = self::partialPredicateIsImpliedByEqualityAndNonNullRange(
                            $prefix[0]->partialPredicate,
                            $equalityConstraints,
                            $rangeColumnName,
                        );
                    } else {
                        $rangeConstraints = [
                            $rangeColumnName => [
                                'lowerInclusive' => $lowerInclusive,
                                'upperBound' => $upperBound,
                                'upperInclusive' => $upperInclusive,
                            ],
                        ];
                        $predicateIsImplied = self::partialPredicateIsImpliedByConstraints(
                            $prefix[0]->partialPredicate,
                            $equalityConstraints,
                            $rangeConstraints,
                            true,
                        );
                    }
                }
                if (
                    $prefix[0]->partialPredicate === null
                    || !$predicateIsImplied
                ) {
                    continue;
                }
            }

            return [
                'rootPage' => $record->rootPage,
                'columns' => $prefix,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $columnValues
     */
    private static function partialPredicateIsImpliedByConstraints(
        SQLiteIndexPredicate $predicate,
        array $columnValues,
        array $rangeConstraints,
        bool $allowEqualityPredicate,
        array $collationsByColumn = [],
    ): bool {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    !$subPredicate instanceof SQLiteIndexPredicate
                    || !self::partialPredicateIsImpliedByConstraints(
                        $subPredicate,
                        $columnValues,
                        $rangeConstraints,
                        $allowEqualityPredicate,
                        $collationsByColumn,
                    )
                ) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    $subPredicate instanceof SQLiteIndexPredicate
                    && self::partialPredicateIsImpliedByConstraints(
                        $subPredicate,
                        $columnValues,
                        $rangeConstraints,
                        $allowEqualityPredicate,
                        $collationsByColumn,
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (!$allowEqualityPredicate && $predicate->operator === SQLiteIndexPredicate::EQUALS) {
            return false;
        }

        foreach ($columnValues as $columnName => $value) {
            $columnName = (string) $columnName;
            if ($predicate->isImpliedByPointLookup($columnName, $value, $collationsByColumn[$columnName] ?? 'BINARY')) {
                return true;
            }
        }
        foreach ($rangeConstraints as $columnName => $bounds) {
            $columnName = (string) $columnName;
            if (
                is_array($bounds)
                && $predicate->isImpliedByRangeLookup(
                    $columnName,
                    $bounds['lowerInclusive'] ?? null,
                    $bounds['upperBound'] ?? null,
                    (bool) ($bounds['upperInclusive'] ?? false),
                    $collationsByColumn[$columnName] ?? 'BINARY',
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $columnValues
     */
    private static function partialPredicateIsImpliedByEqualityAndNonNullRange(
        SQLiteIndexPredicate $predicate,
        array $columnValues,
        string $rangeColumnName,
    ): bool {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    !$subPredicate instanceof SQLiteIndexPredicate
                    || !self::partialPredicateIsImpliedByEqualityAndNonNullRange(
                        $subPredicate,
                        $columnValues,
                        $rangeColumnName,
                    )
                ) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    $subPredicate instanceof SQLiteIndexPredicate
                    && self::partialPredicateIsImpliedByEqualityAndNonNullRange(
                        $subPredicate,
                        $columnValues,
                        $rangeColumnName,
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (
            strcasecmp($predicate->columnName, $rangeColumnName) === 0
            && $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL
        ) {
            return true;
        }

        foreach ($columnValues as $columnName => $value) {
            if ($predicate->isImpliedByPointLookup((string) $columnName, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<mixed> $values
     */
    private static function partialPredicateIsImpliedByInListConstraints(
        SQLiteIndexPredicate $predicate,
        string $columnName,
        array $values,
        string $collation = 'BINARY',
    ): bool {
        return $predicate->isImpliedByInListLookup($columnName, $values, $collation);
    }

    private static function lowerExpressionRangeImpliesPartialPredicate(
        SQLiteIndexPredicate $predicate,
        string $columnName,
    ): bool {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    !$subPredicate instanceof SQLiteIndexPredicate
                    || !self::lowerExpressionRangeImpliesPartialPredicate($subPredicate, $columnName)
                ) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    $subPredicate instanceof SQLiteIndexPredicate
                    && self::lowerExpressionRangeImpliesPartialPredicate($subPredicate, $columnName)
                ) {
                    return true;
                }
            }

            return false;
        }

        return strcasecmp($predicate->columnName, $columnName) === 0
            && $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL;
    }

    private function automaticIndexFirstColumnsForTable(string $tableName): array
    {
        return array_map(
            static fn (array $columns): SQLiteIndexColumn => $columns[0],
            $this->automaticIndexColumnsForTable($tableName),
        );
    }

    /**
     * @return list<non-empty-list<SQLiteIndexColumn>>
     */
    private function automaticIndexColumnsForTable(string $tableName): array
    {
        foreach ($this->schemaRecords() as $record) {
            if ($record->isTable($tableName) && $record->sql !== null) {
                return SQLiteCreateTable::automaticIndexColumnMetadata($record->sql);
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

    /**
     * @param list<mixed> $values
     */
    private static function inListContainsSQLiteScalar(array $values, mixed $needle, string $collation): bool
    {
        if ($needle === null) {
            return false;
        }

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (self::compareSQLiteScalar($needle, $value, $collation) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<mixed> $values
     */
    private static function containsNonNullValue(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<mixed> $values
     * @param callable(string, string): int $compare
     */
    private static function inListContainsSQLiteScalarWithCustomTextCollation(
        array $values,
        mixed $needle,
        callable $compare,
    ): bool {
        if ($needle === null) {
            return false;
        }

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (self::compareSQLiteScalarWithCustomTextCollation($needle, $value, $compare) === 0) {
                return true;
            }
        }

        return false;
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

    /**
     * @param callable(string, string): int $compare
     */
    private static function compareSQLiteScalarWithCustomTextCollation(mixed $left, mixed $right, callable $compare): int
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

            $comparison = $compare($left, $right);
            if (!is_int($comparison)) {
                throw new \InvalidArgumentException('SQLite custom collation callback must return an integer');
            }

            return $comparison <=> 0;
        }

        throw new \InvalidArgumentException('Unsupported SQLite scalar comparison value');
    }

    /**
     * @param array<string, callable(string, string): int> $customCollations
     */
    private static function compareSQLiteScalarForIndexColumn(
        mixed $left,
        mixed $right,
        SQLiteIndexColumn $column,
        array $customCollations,
    ): int {
        $collationName = strtoupper($column->collation);
        if (isset($customCollations[$collationName])) {
            return self::compareSQLiteScalarWithCustomTextCollation($left, $right, $customCollations[$collationName]);
        }

        return self::compareSQLiteScalar($left, $right, $column->collation);
    }

    /**
     * @param array<string, callable(string, string): int> $customCollations
     * @return array<string, callable(string, string): int>
     */
    private static function normalizeCustomCollations(array $customCollations): array
    {
        $normalized = [];
        foreach ($customCollations as $name => $compare) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SQLite custom collation names must be non-empty strings');
            }
            if (!is_callable($compare)) {
                throw new \InvalidArgumentException("SQLite custom collation {$name} must be callable");
            }
            $normalized[strtoupper($name)] = $compare;
        }

        return $normalized;
    }

    /**
     * @param list<SQLiteIndexColumn> $columns
     * @param array<string, callable(string, string): int> $customCollations
     */
    private static function indexColumnsHaveSupportedCollations(array $columns, array $customCollations): bool
    {
        foreach ($columns as $column) {
            if (!$column instanceof SQLiteIndexColumn) {
                return false;
            }
            $collationName = strtoupper($column->collation);
            if (
                !isset($customCollations[$collationName])
                && !in_array($collationName, ['BINARY', 'NOCASE', 'RTRIM'], true)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<SQLiteIndexColumn> $columns
     */
    private static function indexColumnsUseCustomCollations(array $columns): bool
    {
        foreach ($columns as $column) {
            if (!$column instanceof SQLiteIndexColumn) {
                return false;
            }
            if (!in_array(strtoupper($column->collation), ['BINARY', 'NOCASE', 'RTRIM'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function compareSQLiteText(string $left, string $right, string $collation): int
    {
        return match (strtoupper($collation)) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(self::sqliteRtrimCollationKey($left), self::sqliteRtrimCollationKey($right)),
            default => throw new \InvalidArgumentException("Unsupported SQLite index collation: {$collation}"),
        };
    }

    private static function sqliteRtrimCollationKey(string $value): string
    {
        return rtrim($value, ' ');
    }

    public static function likeMatches(
        string $value,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitive = false,
    ): bool {
        if ($escape !== null && self::sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite LIKE ESCAPE expression must be a single character');
        }

        return self::likeMatchesAt(
            self::sqlitePatternCharacters($value),
            self::sqlitePatternCharacters($pattern),
            self::sqlitePatternCharacters($escape ?? ''),
            $caseSensitive,
            0,
            0,
            [],
        );
    }

    /**
     * @return null|array{lowerInclusive:string,upperBound:?string}
     */
    public static function likePrefixRangeBounds(string $pattern, ?string $escape = null): ?array
    {
        $plan = self::likePatternPlan($pattern, $escape);
        if ($plan['prefix'] === '') {
            return null;
        }

        return $plan['binaryRange'];
    }

    /**
     * @return array{
     *   pattern:string,
     *   escape:?string,
     *   prefix:string,
     *   prefixCharacters:int,
     *   prefixIsAscii:bool,
     *   hasWildcard:bool,
     *   binaryRange:array{lowerInclusive:string,upperBound:?string},
     *   noCaseRange:array{lowerInclusive:string,upperBound:?string}
     * }
     */
    public static function likePatternPlan(string $pattern, ?string $escape = null): array
    {
        if ($escape !== null && self::sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite LIKE ESCAPE expression must be a single character');
        }

        $patternCharacters = self::sqlitePatternCharacters($pattern);
        $escapeCharacters = self::sqlitePatternCharacters($escape ?? '');
        $prefix = '';
        $hasWildcard = false;
        $count = count($patternCharacters);
        for ($offset = 0; $offset < $count; $offset++) {
            $character = $patternCharacters[$offset];
            if ($escapeCharacters !== [] && $character === $escapeCharacters[0]) {
                $offset++;
                if ($offset >= $count) {
                    break;
                }
                $prefix .= $patternCharacters[$offset];
                continue;
            }
            if ($character === '%' || $character === '_') {
                $hasWildcard = true;
                break;
            }
            $prefix .= $character;
        }

        $lowerNoCase = self::asciiLower($prefix);

        return [
            'pattern' => $pattern,
            'escape' => $escape,
            'prefix' => $prefix,
            'prefixCharacters' => self::sqliteTextLength($prefix),
            'prefixIsAscii' => self::sqliteTextIsAscii($prefix),
            'hasWildcard' => $hasWildcard,
            'binaryRange' => [
                'lowerInclusive' => $prefix,
                'upperBound' => self::nextBinaryPrefixUpperBound($prefix),
            ],
            'noCaseRange' => [
                'lowerInclusive' => $lowerNoCase,
                'upperBound' => self::nextBinaryPrefixUpperBound($lowerNoCase),
            ],
        ];
    }

    /**
     * @return null|array{lowerInclusive:string,upperBound:?string}
     */
    public static function likeNoCasePrefixRangeBounds(string $pattern, ?string $escape = null): ?array
    {
        $plan = self::likePatternPlan($pattern, $escape);
        if ($plan['prefix'] === '') {
            return null;
        }

        return $plan['noCaseRange'];
    }

    public static function globMatches(string $value, string $pattern): bool
    {
        return self::globMatchesAt(
            self::sqlitePatternCharacters($value),
            self::sqlitePatternCharacters($pattern),
            0,
            0,
            [],
        );
    }

    /**
     * @return null|array{lowerInclusive:string,upperBound:?string}
     */
    public static function globPrefixRangeBounds(string $pattern): ?array
    {
        $patternCharacters = self::sqlitePatternCharacters($pattern);
        $prefix = '';
        $count = count($patternCharacters);
        for ($offset = 0; $offset < $count; $offset++) {
            $character = $patternCharacters[$offset];
            if ($character === '*' || $character === '?' || $character === '[') {
                if ($character === '[' && self::readGlobCharacterClass($patternCharacters, $offset) === null) {
                    $prefix .= $character;
                    continue;
                }
                break;
            }
            $prefix .= $character;
        }

        if ($prefix === '') {
            return null;
        }

        return [
            'lowerInclusive' => $prefix,
            'upperBound' => self::nextBinaryPrefixUpperBound($prefix),
        ];
    }

    /**
     * @param callable(string, string): bool $regexp
     */
    public static function regexpMatches(string $value, string $pattern, callable $regexp): bool
    {
        $matched = $regexp($pattern, $value);
        if (!is_bool($matched)) {
            throw new \InvalidArgumentException('SQLite REGEXP callback must return a boolean');
        }

        return $matched;
    }

    /**
     * @param list<string> $value
     * @param list<string> $pattern
     * @param list<string> $escape
     * @param array<string, bool> $seen
     */
    private static function likeMatchesAt(
        array $value,
        array $pattern,
        array $escape,
        bool $caseSensitive,
        int $valueOffset,
        int $patternOffset,
        array $seen,
    ): bool {
        $state = $valueOffset . ':' . $patternOffset;
        if (isset($seen[$state])) {
            return false;
        }
        $seen[$state] = true;

        $valueCount = count($value);
        $patternCount = count($pattern);
        while ($patternOffset < $patternCount) {
            $patternCharacter = $pattern[$patternOffset];
            if ($escape !== [] && $patternCharacter === $escape[0]) {
                $patternOffset++;
                if ($patternOffset >= $patternCount) {
                    return false;
                }
                $patternCharacter = $pattern[$patternOffset];
            } elseif ($patternCharacter === '%') {
                while ($patternOffset + 1 < $patternCount && $pattern[$patternOffset + 1] === '%') {
                    $patternOffset++;
                }
                if ($patternOffset + 1 >= $patternCount) {
                    return true;
                }
                for ($nextValueOffset = $valueOffset; $nextValueOffset <= $valueCount; $nextValueOffset++) {
                    if (self::likeMatchesAt($value, $pattern, $escape, $caseSensitive, $nextValueOffset, $patternOffset + 1, $seen)) {
                        return true;
                    }
                }

                return false;
            } elseif ($patternCharacter === '_') {
                if ($valueOffset >= $valueCount) {
                    return false;
                }
                $valueOffset++;
                $patternOffset++;
                continue;
            }

            if ($valueOffset >= $valueCount || !self::sqlitePatternCharactersEqual($value[$valueOffset], $patternCharacter, $caseSensitive)) {
                return false;
            }

            $valueOffset++;
            $patternOffset++;
        }

        return $valueOffset === $valueCount;
    }

    /**
     * @param list<string> $value
     * @param list<string> $pattern
     * @param array<string, bool> $seen
     */
    private static function globMatchesAt(array $value, array $pattern, int $valueOffset, int $patternOffset, array $seen): bool
    {
        $state = $valueOffset . ':' . $patternOffset;
        if (isset($seen[$state])) {
            return false;
        }
        $seen[$state] = true;

        $valueCount = count($value);
        $patternCount = count($pattern);
        while ($patternOffset < $patternCount) {
            $patternCharacter = $pattern[$patternOffset];
            if ($patternCharacter === '*') {
                while ($patternOffset + 1 < $patternCount && $pattern[$patternOffset + 1] === '*') {
                    $patternOffset++;
                }
                if ($patternOffset + 1 >= $patternCount) {
                    return true;
                }
                for ($nextValueOffset = $valueOffset; $nextValueOffset <= $valueCount; $nextValueOffset++) {
                    if (self::globMatchesAt($value, $pattern, $nextValueOffset, $patternOffset + 1, $seen)) {
                        return true;
                    }
                }

                return false;
            }
            if ($valueOffset >= $valueCount) {
                return false;
            }
            if ($patternCharacter === '?') {
                $valueOffset++;
                $patternOffset++;
                continue;
            }
            if ($patternCharacter === '[') {
                $class = self::readGlobCharacterClass($pattern, $patternOffset);
                if ($class === null) {
                    if ($value[$valueOffset] !== '[') {
                        return false;
                    }
                    $valueOffset++;
                    $patternOffset++;
                    continue;
                }
                if (self::globCharacterClassContains($value[$valueOffset], $class) === $class['negated']) {
                    return false;
                }
                $valueOffset++;
                $patternOffset = $class['nextOffset'];
                continue;
            }
            if ($value[$valueOffset] !== $patternCharacter) {
                return false;
            }

            $valueOffset++;
            $patternOffset++;
        }

        return $valueOffset === $valueCount;
    }

    /**
     * @param list<string> $pattern
     * @return null|array{characters:list<string>,ranges:list<array{0:string,1:string}>,negated:bool,nextOffset:int}
     */
    private static function readGlobCharacterClass(array $pattern, int $offset): ?array
    {
        $count = count($pattern);
        if ($offset + 1 >= $count) {
            return null;
        }

        $index = $offset + 1;
        $negated = false;
        if ($pattern[$index] === '^') {
            $negated = true;
            $index++;
        }

        $characters = [];
        $ranges = [];
        $first = true;
        while ($index < $count) {
            $character = $pattern[$index];
            if ($character === ']' && !$first) {
                return [
                    'characters' => array_values(array_unique($characters)),
                    'ranges' => $ranges,
                    'negated' => $negated,
                    'nextOffset' => $index + 1,
                ];
            }
            if (
                $index + 2 < $count
                && $pattern[$index + 1] === '-'
                && $pattern[$index + 2] !== ']'
            ) {
                if (self::sqliteCodepoint($character) <= self::sqliteCodepoint($pattern[$index + 2])) {
                    $ranges[] = [$character, $pattern[$index + 2]];
                } else {
                    $characters[] = $character;
                }
                $index += 3;
                $first = false;
                continue;
            }

            $characters[] = $character;
            $index++;
            $first = false;
        }

        return null;
    }

    /**
     * @param array{characters:list<string>,ranges:list<array{0:string,1:string}>,negated:bool,nextOffset:int} $class
     */
    private static function globCharacterClassContains(string $character, array $class): bool
    {
        if (in_array($character, $class['characters'], true)) {
            return true;
        }

        $codepoint = self::sqliteCodepoint($character);
        foreach ($class['ranges'] as [$start, $end]) {
            if ($codepoint >= self::sqliteCodepoint($start) && $codepoint <= self::sqliteCodepoint($end)) {
                return true;
            }
        }

        return false;
    }

    private static function sqliteCodepoint(string $character): int
    {
        $bytes = array_values(unpack('C*', $character) ?: []);
        $length = count($bytes);
        if ($length === 1) {
            return $bytes[0];
        }
        if ($length === 2) {
            return (($bytes[0] & 0x1f) << 6) | ($bytes[1] & 0x3f);
        }
        if ($length === 3) {
            return (($bytes[0] & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
        }
        if ($length === 4) {
            return (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
        }

        return $bytes[0] ?? 0;
    }

    /**
     * @return list<string>
     */
    private static function sqlitePatternCharacters(string $value): array
    {
        return self::sqliteTextPatternCharacters($value);
    }

    private static function sqliteTextLength(string $value): int
    {
        return count(self::sqlitePatternCharacters($value));
    }

    private static function sqliteTextIsAscii(string $value): bool
    {
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            if (ord($value[$offset]) > 0x7f) {
                return false;
            }
        }

        return true;
    }

    private static function sqlitePatternCharactersEqual(string $left, string $right, bool $caseSensitive): bool
    {
        if ($caseSensitive) {
            return $left === $right;
        }

        return self::asciiLower($left) === self::asciiLower($right);
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

    private static function nextBinaryPrefixUpperBound(string $prefix): ?string
    {
        if ($prefix !== '' && preg_match('//u', $prefix) === 1) {
            $characters = self::sqlitePatternCharacters($prefix);
            $last = array_pop($characters);
            if ($last !== null) {
                $codepoint = self::sqliteUtf8Codepoint($last);
                if ($codepoint < 0x10ffff) {
                    return implode('', $characters) . self::sqliteUtf8FromCodepoint($codepoint + 1);
                }
                return null;
            }
        }

        for ($offset = strlen($prefix) - 1; $offset >= 0; $offset--) {
            $byte = ord($prefix[$offset]);
            if ($byte === 0xff) {
                continue;
            }

            return substr($prefix, 0, $offset) . chr($byte + 1);
        }

        return null;
    }

    private static function sqliteUtf8Codepoint(string $character): int
    {
        $bytes = array_values(unpack('C*', $character) ?: []);
        $length = count($bytes);
        if ($length === 1) {
            return $bytes[0];
        }
        if ($length === 2) {
            return (($bytes[0] & 0x1f) << 6) | ($bytes[1] & 0x3f);
        }
        if ($length === 3) {
            return (($bytes[0] & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
        }
        if ($length === 4) {
            return (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
        }

        throw new \InvalidArgumentException('SQLite UTF-8 prefix character must be well formed');
    }

    private static function sqliteUtf8FromCodepoint(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            throw new \InvalidArgumentException('SQLite UTF-8 codepoint is outside Unicode scalar range');
        }
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }

    private static function asciiUpper(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x61 && $ord <= 0x7a) {
                $bytes[$i] = chr($ord - 0x20);
            }
        }

        return $bytes;
    }

    private static function normalizeTrimFunctionName(string $functionName): string
    {
        $normalized = strtolower($functionName);
        if (!in_array($normalized, ['trim', 'ltrim', 'rtrim'], true)) {
            throw new \InvalidArgumentException('SQLite trim expression lookup function must be trim, ltrim, or rtrim');
        }

        return $normalized;
    }

    private static function sqliteTrim(string $value, string $functionName, ?string $characters): string
    {
        $functionName = self::normalizeTrimFunctionName($functionName);
        $characters ??= ' ';
        if ($characters === '' || $value === '') {
            return $value;
        }

        $valueCharacters = self::sqliteTextCharacters($value);
        $trimCharacters = self::sqliteTextCharacters($characters);
        if ($valueCharacters === null || $trimCharacters === null) {
            return self::sqliteTrimBytes($value, $functionName, $characters);
        }

        $trimSet = array_fill_keys($trimCharacters, true);
        if ($functionName === 'trim' || $functionName === 'ltrim') {
            while ($valueCharacters !== [] && isset($trimSet[$valueCharacters[0]])) {
                array_shift($valueCharacters);
            }
        }
        if ($functionName === 'trim' || $functionName === 'rtrim') {
            while ($valueCharacters !== []) {
                $lastIndex = count($valueCharacters) - 1;
                if (!isset($trimSet[$valueCharacters[$lastIndex]])) {
                    break;
                }
                array_pop($valueCharacters);
            }
        }

        return implode('', $valueCharacters);
    }

    /**
     * @return null|list<string>
     */
    private static function sqliteTextCharacters(string $value): ?array
    {
        if ($value === '') {
            return [];
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return null;
        }

        return $characters;
    }

    /**
     * Split LIKE/GLOB text as SQLite does for UTF-8 pattern matching: valid
     * UTF-8 codepoints remain single characters, while malformed bytes are
     * consumed one byte at a time instead of forcing the whole string into a
     * byte split.
     *
     * @return list<string>
     */
    private static function sqliteTextPatternCharacters(string $value): array
    {
        $strict = self::sqliteTextCharacters($value);
        if ($strict !== null) {
            return $strict;
        }

        $characters = [];
        $length = strlen($value);
        for ($offset = 0; $offset < $length;) {
            $byte = ord($value[$offset]);
            $sequenceLength = match (true) {
                $byte < 0x80 => 1,
                $byte >= 0xc2 && $byte <= 0xdf => 2,
                $byte >= 0xe0 && $byte <= 0xef => 3,
                $byte >= 0xf0 && $byte <= 0xf4 => 4,
                default => 1,
            };

            if ($sequenceLength === 1) {
                $characters[] = $value[$offset];
                $offset++;
                continue;
            }

            $sequence = substr($value, $offset, $sequenceLength);
            if (strlen($sequence) === $sequenceLength && self::sqliteUtf8SequenceIsWellFormed($sequence)) {
                $characters[] = $sequence;
                $offset += $sequenceLength;
                continue;
            }

            $characters[] = $value[$offset];
            $offset++;
        }

        return $characters;
    }

    private static function sqliteUtf8SequenceIsWellFormed(string $sequence): bool
    {
        $bytes = array_values(unpack('C*', $sequence) ?: []);
        $length = count($bytes);
        if ($length === 2) {
            return $bytes[0] >= 0xc2
                && $bytes[0] <= 0xdf
                && self::sqliteUtf8ContinuationByte($bytes[1]);
        }
        if ($length === 3) {
            if (!self::sqliteUtf8ContinuationByte($bytes[1]) || !self::sqliteUtf8ContinuationByte($bytes[2])) {
                return false;
            }

            return ($bytes[0] !== 0xe0 || $bytes[1] >= 0xa0)
                && ($bytes[0] !== 0xed || $bytes[1] <= 0x9f);
        }
        if ($length === 4) {
            if (
                !self::sqliteUtf8ContinuationByte($bytes[1])
                || !self::sqliteUtf8ContinuationByte($bytes[2])
                || !self::sqliteUtf8ContinuationByte($bytes[3])
            ) {
                return false;
            }

            return ($bytes[0] !== 0xf0 || $bytes[1] >= 0x90)
                && ($bytes[0] !== 0xf4 || $bytes[1] <= 0x8f);
        }

        return $length === 1 && ($bytes[0] ?? 0) < 0x80;
    }

    private static function sqliteUtf8ContinuationByte(int $byte): bool
    {
        return $byte >= 0x80 && $byte <= 0xbf;
    }

    private static function sqliteTrimBytes(string $value, string $functionName, string $characters): string
    {
        $trimBytes = [];
        for ($i = 0, $length = strlen($characters); $i < $length; $i++) {
            $trimBytes[$characters[$i]] = true;
        }

        $start = 0;
        $end = strlen($value);
        if ($functionName === 'trim' || $functionName === 'ltrim') {
            while ($start < $end && isset($trimBytes[$value[$start]])) {
                $start++;
            }
        }
        if ($functionName === 'trim' || $functionName === 'rtrim') {
            while ($end > $start && isset($trimBytes[$value[$end - 1]])) {
                $end--;
            }
        }

        return substr($value, $start, $end - $start);
    }

    private static function sqliteSubstring(string $value, int $start, ?int $length): string
    {
        if ($start === 0) {
            throw new \InvalidArgumentException('SQLite substr helper in this slice does not support zero start offsets');
        }
        if ($length !== null && $length < 0) {
            throw new \InvalidArgumentException('SQLite substr helper in this slice requires a non-negative length');
        }

        if (function_exists('mb_check_encoding') && function_exists('mb_substr') && mb_check_encoding($value, 'UTF-8')) {
            $offset = $start > 0 ? $start - 1 : $start;
            if ($length === null) {
                return mb_substr($value, $offset, null, 'UTF-8');
            }

            return mb_substr($value, $offset, $length, 'UTF-8');
        }

        $offset = $start > 0 ? $start - 1 : $start;
        if ($length === null) {
            return substr($value, $offset);
        }

        return substr($value, $offset, $length);
    }

    private static function sqliteLength(string $value): int
    {
        if (function_exists('mb_check_encoding') && function_exists('mb_strlen') && mb_check_encoding($value, 'UTF-8')) {
            return mb_strlen($value, 'UTF-8');
        }
        if (preg_match('//u', $value) === 1) {
            $count = preg_match_all('/./us', $value);
            if (is_int($count)) {
                return $count;
            }
        }

        return strlen($value);
    }

    private static function sqliteCastAsInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite CAST AS INTEGER value must be scalar text, numeric, or null');
        }

        $text = ltrim($value);
        if (!preg_match('/^[+-]?\d+/', $text, $matches)) {
            return 0;
        }

        $integer = $matches[0];
        $negative = str_starts_with($integer, '-');
        if ($integer[0] === '-' || $integer[0] === '+') {
            $integer = substr($integer, 1);
        }

        $digits = ltrim($integer, '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return $negative ? PHP_INT_MIN : PHP_INT_MAX;
        }
        if ($negative && $digits === '9223372036854775808') {
            return PHP_INT_MIN;
        }

        $parsed = (int) $digits;

        return $negative ? -$parsed : $parsed;
    }

    private static function sqliteJsonExtract(mixed $json, string $path, ?int $serialType = null): mixed
    {
        $located = self::sqliteJsonLocate($json, $path, $serialType);
        if (!$located['found']) {
            return null;
        }

        return self::sqliteJsonScalar($located['value']);
    }

    private static function sqliteJsonValueOperator(mixed $json, string $path, ?int $serialType = null): ?string
    {
        $located = self::sqliteJsonLocate($json, $path, $serialType);
        if (!$located['found']) {
            return null;
        }

        return self::sqliteJsonTextValue($located['value']);
    }

    /**
     * @return array{found:bool,value:mixed}
     */
    private static function sqliteJsonLocate(mixed $json, string $path, ?int $serialType = null): array
    {
        $segments = self::parseSimpleJsonPath($path);
        if ($json === null) {
            return ['found' => false, 'value' => null];
        }
        if (!is_string($json)) {
            $json = (string) self::sqliteJsonScalar($json);
        }

        $value = self::decodeSQLiteJsonInput($json, $serialType);

        foreach ($segments as $segment) {
            if ($segment['kind'] === 'index' || $segment['kind'] === 'indexFromEnd' || $segment['kind'] === 'arrayAppend') {
                if (!is_array($value) || !array_is_list($value)) {
                    return ['found' => false, 'value' => null];
                }

                if ($segment['kind'] === 'arrayAppend') {
                    return ['found' => false, 'value' => null];
                }

                $indexValue = $segment['value'];
                if (!is_int($indexValue)) {
                    return ['found' => false, 'value' => null];
                }

                $index = $segment['kind'] === 'indexFromEnd'
                    ? count($value) - $indexValue
                    : $indexValue;
                if ($index < 0 || !array_key_exists($index, $value)) {
                    return ['found' => false, 'value' => null];
                }

                $value = $value[$index];
                continue;
            }

            $member = $segment['value'];
            if (
                !is_string($member)
                || !is_array($value)
                || array_is_list($value)
                || !array_key_exists($member, $value)
            ) {
                return ['found' => false, 'value' => null];
            }
            $value = $value[$member];
        }

        return ['found' => true, 'value' => $value];
    }

    private static function decodeSQLiteJsonInput(string $json, ?int $serialType): mixed
    {
        if ($serialType !== null && $serialType >= 12 && $serialType % 2 === 0 && SQLiteJsonB::isJsonB($json)) {
            return SQLiteJsonB::decode($json);
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            try {
                return SQLiteJson5Parser::decode($json);
            } catch (\InvalidArgumentException $json5Exception) {
                throw new \InvalidArgumentException('SQLite json_extract expression index value is not valid strict JSON, supported JSON5, or JSONB', 0, $json5Exception);
            }
        }
    }

    private static function sqliteJsonScalar(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            if (is_float($value) && is_nan($value)) {
                return null;
            }

            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_array($value)) {
            return self::sqliteJsonTextValue($value);
        }

        throw new \InvalidArgumentException('SQLite json_extract lookup value must be null, scalar, or JSON-encodable array');
    }

    private static function sqliteJsonTextValue(mixed $value): string
    {
        if (is_resource($value) || is_object($value)) {
            throw new \InvalidArgumentException('SQLite JSON -> lookup value must be null, scalar, or JSON-encodable array');
        }

        return self::sqliteJsonTextEncode($value, 0);
    }

    private static function sqliteJsonTextEncode(mixed $value, int $depth): string
    {
        if ($depth > 512) {
            throw new \InvalidArgumentException('SQLite JSON -> lookup value exceeds the maximum nesting depth');
        }
        if ($value === null) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'null';
            }
            if (!is_finite($value)) {
                return $value < 0 ? '-9e999' : '9e999';
            }
        }

        if (is_string($value) || is_float($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                throw new \InvalidArgumentException('SQLite JSON -> lookup value cannot be encoded as JSON');
            }

            return $json;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite JSON -> lookup value must be null, scalar, or JSON-encodable array');
        }

        if (array_is_list($value)) {
            $items = [];
            foreach ($value as $item) {
                $items[] = self::sqliteJsonTextEncode($item, $depth + 1);
            }

            return '[' . implode(',', $items) . ']';
        }

        $items = [];
        foreach ($value as $key => $item) {
            $jsonKey = json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($jsonKey)) {
                throw new \InvalidArgumentException('SQLite JSON -> lookup key cannot be encoded as JSON');
            }
            $items[] = $jsonKey . ':' . self::sqliteJsonTextEncode($item, $depth + 1);
        }

        return '{' . implode(',', $items) . '}';
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private static function sqliteJsonTextValueList(array $values): array
    {
        $texts = [];
        foreach ($values as $value) {
            $texts[] = self::sqliteJsonTextValue($value);
        }

        return $texts;
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private static function sqliteJsonScalarList(array $values): array
    {
        $scalars = [];
        foreach ($values as $value) {
            $scalars[] = self::sqliteJsonScalar($value);
        }

        return $scalars;
    }

    /**
     * @return list<array{kind:string,value:int|string|null}>
     */
    private static function parseSimpleJsonPath(string $path): array
    {
        $length = strlen($path);
        if ($length === 0 || $path[0] !== '$') {
            throw new \InvalidArgumentException('SQLite json_extract expression indexes in this slice require paths that start with $');
        }
        if ($path === '$') {
            return [];
        }

        $segments = [];
        $offset = 1;
        while ($offset < $length) {
            if ($path[$offset] === '[') {
                $close = strpos($path, ']', $offset + 1);
                if ($close === false) {
                    throw new \InvalidArgumentException('SQLite json_extract expression index array path is unterminated');
                }

                $indexText = substr($path, $offset + 1, $close - $offset - 1);
                if (preg_match('/^\d+$/', $indexText) === 1) {
                    $maxIndexText = (string) PHP_INT_MAX;
                    if (
                        strlen($indexText) > strlen($maxIndexText)
                        || (strlen($indexText) === strlen($maxIndexText) && strcmp($indexText, $maxIndexText) > 0)
                    ) {
                        throw new \InvalidArgumentException('SQLite json_extract expression index array index is too large for this slice');
                    }

                    $segments[] = [
                        'kind' => 'index',
                        'value' => (int) $indexText,
                    ];
                    $offset = $close + 1;
                    continue;
                }

                if ($indexText === '#') {
                    $segments[] = [
                        'kind' => 'arrayAppend',
                        'value' => null,
                    ];
                    $offset = $close + 1;
                    continue;
                }

                if (preg_match('/^#-(\d+)$/', $indexText, $matches) === 1) {
                    $digits = ltrim($matches[1], '0');
                    $digits = $digits === '' ? '0' : $digits;
                    $maxIndexText = (string) PHP_INT_MAX;
                    $value = (
                        strlen($digits) > strlen($maxIndexText)
                        || (strlen($digits) === strlen($maxIndexText) && strcmp($digits, $maxIndexText) > 0)
                    )
                        ? $digits
                        : (int) $digits;

                    $segments[] = [
                        'kind' => 'indexFromEnd',
                        'value' => $value,
                    ];
                    $offset = $close + 1;
                    continue;
                }

                throw new \InvalidArgumentException('SQLite json_extract expression indexes in this slice support only non-negative array indexes, [#], or [#-N] reverse array indexes');
            }
            if ($path[$offset] !== '.') {
                throw new \InvalidArgumentException('SQLite json_extract expression indexes in this slice support only object-member and array-index paths');
            }
            $offset++;
            if ($offset >= $length) {
                throw new \InvalidArgumentException('SQLite json_extract expression index path has an empty object member');
            }

            if ($path[$offset] === '"') {
                $end = self::jsonPathQuotedMemberEnd($path, $offset);
                $literal = substr($path, $offset, $end - $offset + 1);
                try {
                    $member = SQLiteJson5Parser::decode($literal);
                } catch (\InvalidArgumentException $exception) {
                    throw new \InvalidArgumentException('SQLite json_extract expression index quoted path member is invalid', 0, $exception);
                }
                if (!is_string($member)) {
                    throw new \InvalidArgumentException('SQLite json_extract expression index quoted path member must decode to text');
                }
                $segments[] = [
                    'kind' => 'member',
                    'value' => $member,
                ];
                $offset = $end + 1;
                continue;
            }

            $end = $offset;
            while ($end < $length && $path[$end] !== '.' && $path[$end] !== '[') {
                $end++;
            }
            if ($end === $offset) {
                throw new \InvalidArgumentException('SQLite json_extract expression index path has an empty object member');
            }
            $member = SQLiteJsonPath::decodeBareMember(substr($path, $offset, $end - $offset));
            if ($member === null) {
                throw new \InvalidArgumentException('SQLite json_extract expression index path member escape is invalid');
            }
            $segments[] = [
                'kind' => 'member',
                'value' => $member,
            ];
            $offset = $end;
        }

        return $segments;
    }

    private static function jsonPathQuotedMemberEnd(string $path, int $offset): int
    {
        $length = strlen($path);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($path[$i] === '\\') {
                $i++;
                continue;
            }
            if ($path[$i] === '"') {
                return $i;
            }
        }

        throw new \InvalidArgumentException('SQLite json_extract expression index quoted path member is unterminated');
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

    /**
     * @return list<int>
     */
    public function overflowPageChainNumbers(int $firstOverflowPage, int $byteCount): array
    {
        return $this->overflowPageNumbers($firstOverflowPage, $byteCount);
    }

    public function readOverflowPayloadForBtreePlan(int $firstOverflowPage, int $byteCount): string
    {
        return $this->readOverflowPayload($firstOverflowPage, $byteCount);
    }

    /**
     * @return list<int>
     */
    private function overflowPageNumbers(int $firstOverflowPage, int $byteCount): array
    {
        if ($byteCount < 0) {
            throw new \InvalidArgumentException('SQLite overflow byte count cannot be negative');
        }
        if ($byteCount === 0) {
            return [];
        }

        $usableSize = $this->usablePageSize();
        $overflowPagePayloadSize = $usableSize - 4;
        if ($overflowPagePayloadSize <= 0) {
            throw new \InvalidArgumentException('SQLite overflow page payload size is invalid');
        }

        $pageNumbers = [];
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
            $pageNumbers[] = $pageNumber;

            $page = $this->page($pageNumber);
            $remaining -= min($remaining, $overflowPagePayloadSize);
            $pageNumber = self::readUInt32($page, 0);
        }

        return $pageNumbers;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite uint32 field is truncated');
        }

        return unpack('N', substr($bytes, $offset, 4))[1];
    }

    /**
     * @param array<int, string> $pageImages
     * @param list<int> $overflowPageNumbers
     * @return array<int, string>
     */
    private function withOverflowPointerMapPages(
        array $pageImages,
        array $overflowPageNumbers,
        int $ownerBtreePageNumber,
        int $databasePageCount,
    ): array {
        if (!$this->isAutoVacuum() || $overflowPageNumbers === []) {
            return $pageImages;
        }
        if ($ownerBtreePageNumber < 2 || $ownerBtreePageNumber > $databasePageCount) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map owner page is outside the planned database image');
        }

        $updates = [];
        $previousPageNumber = $ownerBtreePageNumber;
        foreach (array_values($overflowPageNumbers) as $index => $overflowPageNumber) {
            if ($overflowPageNumber < 2 || $overflowPageNumber > $databasePageCount) {
                throw new \InvalidArgumentException('SQLite overflow pointer-map update page is outside the planned database image');
            }

            $updates[$overflowPageNumber] = [
                'type' => $index === 0
                    ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE
                    : SQLitePointerMapEntry::OVERFLOW_PAGE,
                'parent_page_number' => $previousPageNumber,
            ];
            $previousPageNumber = $overflowPageNumber;
        }

        return $this->pointerMapPageImagesForUpdates($pageImages, $updates, $databasePageCount);
    }

    /**
     * @param array<int, string> $pageImages
     * @return array<int, string>
     */
    private function withBtreePointerMapPages(array $pageImages, int $databasePageCount): array
    {
        if (!$this->isAutoVacuum()) {
            return $pageImages;
        }

        $plannedDatabase = $this->withPageImages($pageImages);
        $updates = [];
        $visitedRoots = [];
        foreach ($plannedDatabase->schemaRecords() as $record) {
            $rootPage = $record->rootPage;
            if ($rootPage === null || $rootPage < 2 || $rootPage > $databasePageCount || isset($visitedRoots[$rootPage])) {
                continue;
            }
            $visitedRoots[$rootPage] = true;

            $visitedPages = [];
            $plannedDatabase->collectBtreePointerMapUpdates(
                $rootPage,
                null,
                $updates,
                $visitedPages,
                $databasePageCount,
            );
        }

        if ($updates === []) {
            return $pageImages;
        }

        return $this->pointerMapPageImagesForUpdates($pageImages, $updates, $databasePageCount);
    }

    /**
     * @param array<int, array{type:int,parent_page_number:int}> $updates
     * @param array<int, true> $visitedPages
     */
    private function collectBtreePointerMapUpdates(
        int $pageNumber,
        ?int $parentPageNumber,
        array &$updates,
        array &$visitedPages,
        int $databasePageCount,
    ): void {
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \InvalidArgumentException("SQLite b-tree page {$pageNumber} is outside the planned database image");
        }
        if (isset($visitedPages[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite planned b-tree pointer-map traversal reached page {$pageNumber} more than once");
        }
        if ($this->isPointerMapPage($pageNumber)) {
            throw new \InvalidArgumentException("SQLite pointer-map page {$pageNumber} cannot be a b-tree page");
        }
        $visitedPages[$pageNumber] = true;

        if ($pageNumber > 1) {
            $updates[$pageNumber] = [
                'type' => $parentPageNumber === null
                    ? SQLitePointerMapEntry::ROOT_PAGE
                    : SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $parentPageNumber ?? 0,
            ];
        }

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );

        if ($header->pageType === 'table-leaf' || $header->pageType === 'index-leaf') {
            return;
        }

        if ($header->pageType === 'table-interior') {
            if ($header->rightMostPointer === null) {
                throw new \InvalidArgumentException("SQLite table interior page {$pageNumber} has an invalid right-most pointer");
            }

            foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $cell) {
                $this->collectBtreePointerMapUpdates($cell->leftChildPage, $pageNumber, $updates, $visitedPages, $databasePageCount);
            }
            $this->collectBtreePointerMapUpdates($header->rightMostPointer, $pageNumber, $updates, $visitedPages, $databasePageCount);

            return;
        }

        if ($header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a b-tree page");
        }
        if ($header->rightMostPointer === null) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        foreach (SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
            }

            $this->collectBtreePointerMapUpdates($cell->leftChildPage, $pageNumber, $updates, $visitedPages, $databasePageCount);
        }
        $this->collectBtreePointerMapUpdates($header->rightMostPointer, $pageNumber, $updates, $visitedPages, $databasePageCount);
    }

    /**
     * @param array<int, string> $pageImages
     */
    private function tableLeafPageNumberForRowIdInPlannedImages(int $rootPageNumber, int $rowId, array $pageImages): int
    {
        $visited = [];
        $pageNumber = $this->findTableLeafPageNumberForRowIdInPlannedImages($rootPageNumber, $rowId, $pageImages, $visited);
        if ($pageNumber === null) {
            throw new \InvalidArgumentException("SQLite app_settings replacement rowid {$rowId} is not present in the planned table image");
        }

        return $pageNumber;
    }

    /**
     * @param array<int, string> $pageImages
     * @param array<int, true> $visited
     */
    private function findTableLeafPageNumberForRowIdInPlannedImages(
        int $pageNumber,
        int $rowId,
        array $pageImages,
        array &$visited,
    ): ?int {
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite planned table traversal reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $pageImages[$pageNumber] ?? $this->page($pageNumber);
        if (!is_string($page) || strlen($page) !== $this->header->pageSize) {
            throw new \InvalidArgumentException('SQLite planned table page image length does not match page size');
        }

        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );

        if ($header->pageType === 'table-leaf') {
            $overflowReader = static fn (int $firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount);
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
                if ($cell->rowId === $rowId) {
                    return $pageNumber;
                }
            }

            return null;
        }
        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a planned table b-tree page");
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite planned table interior page {$pageNumber} has an invalid right-most pointer");
        }

        foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $interiorCell) {
            if ($rowId <= $interiorCell->key) {
                return $this->findTableLeafPageNumberForRowIdInPlannedImages(
                    $interiorCell->leftChildPage,
                    $rowId,
                    $pageImages,
                    $visited,
                );
            }
        }

        return $this->findTableLeafPageNumberForRowIdInPlannedImages(
            $header->rightMostPointer,
            $rowId,
            $pageImages,
            $visited,
        );
    }

    /**
     * @param array<int, string> $pageImages
     * @param array<int, SQLitePointerMapEntry|array{0:int,1:int}|array{type:int,parent_page_number:int}> $updatesByPage
     * @return array<int, string>
     */
    private function pointerMapPageImagesForUpdates(
        array $pageImages,
        array $updatesByPage,
        ?int $databasePageCount = null,
    ): array {
        if (!$this->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite pointer-map updates require an auto-vacuum database');
        }

        $databasePageCount ??= max($this->pageCount(), $this->header->databaseSizePages);
        foreach ($updatesByPage as $pageNumber => $update) {
            if (!is_int($pageNumber) || $pageNumber < 2) {
                throw new \InvalidArgumentException('SQLite pointer-map updates require one-based non-header page numbers');
            }
            if ($pageNumber > $databasePageCount) {
                throw new \InvalidArgumentException("SQLite pointer-map update page {$pageNumber} is outside the planned database image");
            }
            if ($pageNumber === $this->pendingBytePageNumber() || $this->isPointerMapPage($pageNumber)) {
                throw new \InvalidArgumentException("SQLite page {$pageNumber} does not have a pointer-map entry");
            }

            [$type, $parentPageNumber] = $this->normalizePointerMapUpdate($pageNumber, $update);
            $pointerMapPage = $this->pointerMapPageFor($pageNumber);
            if ($pointerMapPage === null || $pointerMapPage === $pageNumber) {
                throw new \InvalidArgumentException("SQLite page {$pageNumber} does not have a pointer-map entry");
            }
            if ($pointerMapPage > $databasePageCount) {
                throw new \InvalidArgumentException("SQLite pointer-map page {$pointerMapPage} is outside the planned database image");
            }

            $offset = $this->pointerMapOffsetFor($pageNumber);
            $page = $pageImages[$pointerMapPage]
                ?? ($pointerMapPage <= $this->pageCount() ? $this->page($pointerMapPage) : str_repeat("\0", $this->header->pageSize));
            if (strlen($page) !== $this->header->pageSize) {
                throw new \InvalidArgumentException('SQLite pointer-map page image length does not match page size');
            }

            $entryBytes = chr($type) . self::uint32Bytes($parentPageNumber);
            if (substr($page, $offset, 5) === $entryBytes) {
                continue;
            }

            $pageImages[$pointerMapPage] = substr_replace($page, $entryBytes, $offset, 5);
        }

        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function normalizePointerMapUpdate(int $pageNumber, mixed $update): array
    {
        if ($update instanceof SQLitePointerMapEntry) {
            if ($update->pageNumber !== $pageNumber) {
                throw new \InvalidArgumentException('SQLite pointer-map update key does not match the entry page number');
            }

            return [$update->type, $update->parentPageNumber];
        }

        if (!is_array($update)) {
            throw new \InvalidArgumentException('SQLite pointer-map update must be an entry object or array');
        }

        $type = $update['type'] ?? $update[0] ?? null;
        $parentPageNumber = $update['parent_page_number'] ?? $update[1] ?? null;
        if (!is_int($type) || !is_int($parentPageNumber)) {
            throw new \InvalidArgumentException('SQLite pointer-map update type and parent page number must be integers');
        }

        $pointerMapPage = $this->pointerMapPageFor($pageNumber);
        $offset = $this->pointerMapOffsetFor($pageNumber);
        new SQLitePointerMapEntry($pageNumber, $pointerMapPage ?? 0, $offset, $type, $parentPageNumber);

        return [$type, $parentPageNumber];
    }

    private function nextAppendPageNumber(int $databasePageCount): int
    {
        $nextPage = $databasePageCount + 1;
        while (true) {
            if ($nextPage > 0xffffffff) {
                throw new \InvalidArgumentException('SQLite database page count exceeds the 32-bit page number range');
            }
            if (
                $nextPage === $this->pendingBytePageNumber()
                || ($this->isAutoVacuum() && $this->pointerMapPageFor($nextPage) === $nextPage)
            ) {
                $nextPage++;
                continue;
            }

            return $nextPage;
        }
    }

    private static function uint32Bytes(int $value): string
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new \InvalidArgumentException('SQLite uint32 value is outside the supported range');
        }

        return pack('N', $value);
    }
}
