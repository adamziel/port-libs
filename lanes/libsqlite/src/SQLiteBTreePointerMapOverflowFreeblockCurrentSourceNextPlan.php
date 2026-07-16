<?php
declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan
{
    private function __construct(private readonly object $inner)
    {
    }

    public static function __callStatic(string $name, array $args): self
    {
        $args = self::unwrapArgs($args);
        if (method_exists(SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextExtendedVariantPlan::class, $name)) {
            return new self(SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextExtendedVariantPlan::{$name}(...$args));
        }
        if (method_exists(SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextBaseVariantPlan::class, $name)) {
            return new self(SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextBaseVariantPlan::{$name}(...$args));
        }

        throw new \BadMethodCallException(sprintf('Unknown %s factory method %s', self::class, $name));
    }

    public static function pointerMapOverflowFreeblockFromDeleteResults(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextBaseVariantPlan::fromDeleteResults(...$args));
    }

    public static function currentSourceOverflowFreeblockFromDeleteResults(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextExtendedVariantPlan::fromCurrentSourceDeleteResults(...$args));
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

final class SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextBaseVariantPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        public readonly array $rows,
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
        bool $secureDelete = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next131 requires an auto-vacuum database');
        }
        if ($newOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next131 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next131 parent b-tree page must be at page 2 or later');
        }

        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage($database, $leafPageNumber, true);
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
        $rows = self::rows($database, $databaseAfterRelease, $databaseAfterAllocation, $coalescePlan, $releasePlan, $allocationPlan);

        $origins = array_unique(array_column($rows, 'page_origin'));
        sort($origins);
        if ($origins !== ['existing-freelist-page', 'released-overflow-page']) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next131 requires both released overflow and existing freelist allocation sources');
        }

        return new self(
            $coalescePlan,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $rows,
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
            'action' => 'btree-pointermap-overflow-freeblock-current-source-next131',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->reusedReleasedOverflowPages(),
            'allocated_existing_freelist_pages' => $this->allocatedExistingFreelistPages(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'coalesce' => $this->coalescePlan->toArray(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_pointermap_overflow_freeblock_current_source_next131' => $this->rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $releaseSources = self::releaseSourcesByPage($releasePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $page = $databaseAfterAllocation->page($pageNumber);
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'leaf_page' => $coalescePlan->pageNumber,
                'page_number' => $pageNumber,
                'chain_position' => $position,
                'page_origin' => isset($released[$pageNumber]) ? 'released-overflow-page' : 'existing-freelist-page',
                'release_source' => $releaseSources[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
                'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'next_overflow_is_tail' => self::readUInt32($page, 0) === 0,
                'payload_prefix' => substr($page, 4, 14),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private static function releaseSourcesByPage(SQLiteOverflowFreelistReleasePlan $releasePlan): array
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
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next131 could not read uint32');
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

final class SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextExtendedVariantPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $currentSourceRows
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
        private readonly array $currentSourceRows,
        private readonly array $transitionRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromCurrentSourceDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentOverflowChains,
        array $deleteResults,
        int $parentBtreePageNumber,
        string $replacementPayload,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 requires an auto-vacuum database');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 parent b-tree page must be at page 2 or later');
        }

        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($coalescedDatabase, $deleteResults, $secureDelete);
        $databaseAfterRelease = $coalescedDatabase->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $overflowPageImages);
        $transitionRows = self::buildTransitionRows(
            $database,
            $databaseAfterRelease,
            $databaseAfterAllocation,
            $currentSourceRows,
            $coalescePlan,
            $releasePlan,
            $allocationPlan,
        );

        return new self(
            $database,
            $coalescePlan,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $currentSourceRows,
            $transitionRows,
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
    public function transitionRows(): array
    {
        return $this->transitionRows;
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
    public function reusedCurrentSourcePages(): array
    {
        $currentPages = array_fill_keys(array_column($this->currentSourceRows, 'page_number'), true);

        return array_values(array_filter(
            $this->allocatedOverflowPages(),
            static fn (int $pageNumber): bool => isset($currentPages[$pageNumber]),
        ));
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
            'action' => 'btree-pointermap-overflow-freeblock-current-source-next138',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_current_source_pages' => $this->reusedCurrentSourcePages(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_pointermap_overflow_freeblock_current_source_next138' => $this->transitionRows,
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteDatabase $database, array $chains): array
    {
        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 chain is missing an overflow payload byte count');
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
                    'current_payload_bytes' => $link['payload_bytes'],
                    'current_terminal' => $link['terminal'],
                    'current_pointer_map_type' => $entry['type_name'],
                    'current_pointer_map_parent' => $entry['parent_page_number'],
                ];
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 requires at least one current-source overflow page');
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $databaseBefore,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        array $currentSourceRows,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $currentByPage[(int) $row['page_number']] = $row;
        }
        $releaseSources = self::releaseSourceByPage($releasePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            if (!isset($currentByPage[$pageNumber])) {
                continue;
            }

            $current = $currentByPage[$pageNumber];
            $beforeEntry = $databaseBefore->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'source' => $current['source'],
                'page_number' => $pageNumber,
                'current_chain_position' => $current['chain_position'],
                'replacement_chain_position' => $position,
                'current_next_page' => $current['current_next_page'],
                'replacement_next_page' => self::readUInt32($databaseAfterAllocation->page($pageNumber), 0),
                'release_source' => $releaseSources[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                'current_pointer_map_type' => $current['current_pointer_map_type'],
                'current_pointer_map_parent' => $current['current_pointer_map_parent'],
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'payload_prefix' => substr($databaseAfterAllocation->page($pageNumber), 4, 16),
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 did not reuse a current-source overflow page');
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

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[$pageNumber] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 could not read uint32');
        }

        return $value[1];
    }
}
