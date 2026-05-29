<?php
declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan
{
    private function __construct(private readonly object $inner)
    {
    }

    public static function __callStatic(string $name, array $args): self
    {
        $args = self::unwrapArgs($args);
        if (method_exists(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextExtendedVariantPlan::class, $name)) {
            return new self(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextExtendedVariantPlan::{$name}(...$args));
        }
        if (method_exists(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextBaseVariantPlan::class, $name)) {
            return new self(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextBaseVariantPlan::{$name}(...$args));
        }

        throw new \BadMethodCallException(sprintf('Unknown %s factory method %s', self::class, $name));
    }

    public static function next128FromDeleteResults(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextBaseVariantPlan::fromDeleteResults(...$args));
    }

    public static function next147TableAndIndexFromCurrentSourceDeleteResults(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextExtendedVariantPlan::tableAndIndexFromCurrentSourceDeleteResults(...$args));
    }

    /**
     * @param list<mixed> $args
     * @return list<mixed>
     */
    private static function unwrapArgs(array $args): array
    {
        return array_map(static fn (mixed $arg): mixed => $arg instanceof self ? $arg->inner : $arg, $args);
    }

    public function __call(string $name, array $args): mixed
    {
        return $this->inner->{$name}(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->inner->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->inner->{$name});
    }

    public function toArray(): array
    {
        return $this->inner->toArray();
    }
}

final class SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextBaseVariantPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $transitionRows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResults,
        int $parentBtreePageNumber,
        string $newOverflowPayload,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 requires an auto-vacuum database');
        }
        if ($newOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 parent b-tree page must be at page 2 or later');
        }

        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($coalescedDatabase, $deleteResults, $secureDelete);
        $databaseAfterRelease = $coalescedDatabase->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($newOverflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $newOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $database,
            $coalescePlan,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildTransitionRows(
                $database,
                $databaseAfterRelease,
                $databaseAfterAllocation,
                $coalescePlan,
                $releasePlan,
                $allocationPlan,
            ),
        );
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->releasePlan->releasedOverflowPages;
    }

    /**
     * @return list<int>
     */
    public function allocatedOverflowPages(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedOverflowPages(): array
    {
        return array_values(array_intersect($this->releasedOverflowPages(), $this->allocatedOverflowPages()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transitionRows(): array
    {
        return $this->transitionRows;
    }

    /**
     * @return array<int, string>
     */
    public function overflowPageImages(): array
    {
        return $this->overflowPageImages;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->coalescePlan->pageImages();
        foreach ($this->releasePlan->freePlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        ksort($images);

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freeblock-pointermap-current-source-next128',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'fragmented_bytes_before' => $this->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->coalescePlan->fragmentedBytesAfter,
            'freeblock_count_before' => count($this->coalescePlan->beforeFreeblocks),
            'freeblock_count_after' => count($this->coalescePlan->afterFreeblocks),
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_overflow_pages' => $this->reusedOverflowPages(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'coalesce' => $this->coalescePlan->toArray(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_overflow_freeblock_pointermap_current_source_next128' => $this->transitionRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $sourceByPage = self::releaseSourceByPage($releasePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            if (!isset($released[$pageNumber])) {
                continue;
            }

            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];
            $page = $databaseAfterAllocation->page($pageNumber);

            $rows[] = [
                'leaf_page' => $coalescePlan->pageNumber,
                'page_number' => $pageNumber,
                'chain_position' => $position,
                'release_source' => $sourceByPage[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
                'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
                'freeblock_count_before' => count($coalescePlan->beforeFreeblocks),
                'freeblock_count_after' => count($coalescePlan->afterFreeblocks),
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'payload_prefix' => substr($page, 4, 16),
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 did not reuse an obsolete overflow page');
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private static function releaseSourceByPage(SQLiteOverflowFreelistReleasePlan $releasePlan): array
    {
        $sources = [];
        foreach ($releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $sources[$pageNumber] = $source['source'];
            }
        }

        return $sources;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 could not read uint32');
        }

        return $value[1];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}

final class SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextExtendedVariantPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $nextRows
     */
    private function __construct(
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $currentSourceRows,
        private readonly array $nextRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function tableAndIndexFromCurrentSourceDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentOverflowChains,
        array $deleteResults,
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
        bool $secureDelete = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 requires an auto-vacuum database');
        }
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 parent b-tree page must be at page 2 or later');
        }
        if (count($deleteResults) < 2) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 requires table and index delete results');
        }

        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        self::assertDeleteResultsMatchCurrentSource($deleteResults, $currentSourceRows);

        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage($database, $leafPageNumber, true);
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($coalescedDatabase, $deleteResults, $secureDelete);
        $databaseAfterRelease = $coalescedDatabase->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $coalescePlan,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $currentSourceRows,
            self::buildNextRows(
                $database,
                $databaseAfterRelease,
                $databaseAfterAllocation,
                $coalescePlan,
                $releasePlan,
                $allocationPlan,
                $currentSourceRows,
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentSourceRows(): array
    {
        return $this->currentSourceRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nextRows(): array
    {
        return $this->nextRows;
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->releasePlan->releasedOverflowPages;
    }

    /**
     * @return list<int>
     */
    public function allocatedOverflowPages(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedReleasedOverflowPages(): array
    {
        return array_values(array_intersect($this->releasedOverflowPages(), $this->allocatedOverflowPages()));
    }

    /**
     * @return list<int>
     */
    public function allocatedExistingFreelistPages(): array
    {
        return array_values(array_diff($this->allocatedOverflowPages(), $this->releasedOverflowPages()));
    }

    /**
     * @return array<int, string>
     */
    public function overflowPageImages(): array
    {
        return $this->overflowPageImages;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->coalescePlan->pageImages();
        foreach ($this->releasePlan->freePlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        ksort($images);

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freeblock-pointermap-current-source-next147',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->reusedReleasedOverflowPages(),
            'allocated_existing_freelist_pages' => $this->allocatedExistingFreelistPages(),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_overflow_freeblock_pointermap_current_source_next147' => $this->nextRows,
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'coalesce' => $this->coalescePlan->toArray(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteDatabase $database, array $chains): array
    {
        if ($chains === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 requires current-source overflow chains');
        }

        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "current-overflow-chain-{$chainIndex}";
            foreach (SQLiteOverflowPage::chainLinksFromDatabase($database, $firstPage, $payloadBytes) as $position => $link) {
                $entry = $database->pointerMapEntryForPage($link['current_page'])->toArray();
                $rows[] = [
                    'source' => $source,
                    'chain_index' => $chainIndex,
                    'chain_position' => $position,
                    'page_number' => $link['current_page'],
                    'current_next_page' => $link['next_page'],
                    'current_terminal' => $link['terminal'],
                    'current_payload_bytes' => $link['payload_bytes'],
                    'current_pointer_map_type' => $entry['type_name'],
                    'current_pointer_map_parent' => $entry['parent_page_number'],
                ];
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 requires at least one current-source overflow page');
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @param list<array<string, mixed>> $currentSourceRows
     */
    private static function assertDeleteResultsMatchCurrentSource(array $deleteResults, array $currentSourceRows): void
    {
        $currentPages = array_map('intval', array_column($currentSourceRows, 'page_number'));
        sort($currentPages);
        $deletePages = [];
        foreach ($deleteResults as $deleteResult) {
            $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
            if (!is_array($pages)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 delete result is missing obsolete overflow pages');
            }
            foreach ($pages as $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 obsolete overflow page numbers must be integers');
                }
                $deletePages[] = $pageNumber;
            }
        }
        sort($deletePages);

        if ($deletePages !== $currentPages) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 delete results must match current-source overflow pages');
        }
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @return list<array<string, mixed>>
     */
    private static function buildNextRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        array $currentSourceRows,
    ): array {
        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $currentByPage[(int) $row['page_number']] = $row;
        }

        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $releaseSourceByPage = [];
        foreach ($releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $releaseSourceByPage[$pageNumber] = $source['source'];
            }
        }

        $allocationSteps = $allocationPlan->allocationSteps();
        $nextRows = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $current = $currentByPage[$pageNumber] ?? null;
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];
            $page = $databaseAfterAllocation->page($pageNumber);

            $nextRows[] = [
                'leaf_page' => $coalescePlan->pageNumber,
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'page_origin' => isset($released[$pageNumber]) ? 'released-overflow-page' : 'existing-freelist-page',
                'release_source' => $releaseSourceByPage[$pageNumber] ?? null,
                'current_source' => $current['source'] ?? null,
                'current_chain_index' => $current['chain_index'] ?? null,
                'current_chain_position' => $current['chain_position'] ?? null,
                'current_next_page' => $current['current_next_page'] ?? null,
                'current_terminal' => $current['current_terminal'] ?? null,
                'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
                'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'allocation_source' => $step['source'] ?? null,
                'allocation_trunk_page' => $step['trunk_page'] ?? null,
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'next_overflow_is_tail' => self::readUInt32($page, 0) === 0,
                'payload_prefix' => substr($page, 4, 12),
            ];
        }

        return $nextRows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock pointer-map next147 could not read uint32');
        }

        return $value[1];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
