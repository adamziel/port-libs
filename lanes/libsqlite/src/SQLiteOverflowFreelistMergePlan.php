<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOverflowFreelistMergePlan
{
    /**
     * @param list<array{source:string,pages:list<int>,count:int}> $sources
     * @param list<array{page_number:int,next_trunk_page:?int,leaf_page_numbers:list<int>,leaf_count:int}> $trunksBefore
     * @param list<array{page_number:int,next_trunk_page:?int,leaf_page_numbers:list<int>,leaf_count:int}> $trunksAfter
     * @param list<array{page_number:int,disposition:string,trunk_page:int,leaf_count_before:int,leaf_count_after:int,next_trunk_page_before:?int,next_trunk_page_after:?int}> $mergeSteps
     */
    private function __construct(
        public readonly array $sources,
        public readonly array $trunksBefore,
        public readonly array $trunksAfter,
        public readonly array $mergeSteps,
        public readonly SQLiteFreelistFreePlan $freePlan,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        bool $secureDelete = false,
    ): self {
        $sources = self::sourcesFromDeleteResults($deleteResults);
        $releasedPages = [];
        foreach ($sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $releasedPages[] = $pageNumber;
            }
        }

        $trunksBefore = self::summarizeTrunks($database);
        $firstTrunkBefore = $trunksBefore[0] ?? null;
        $freePlan = $database->planPageFreeList($releasedPages, $secureDelete);
        $postDatabase = self::databaseWithPageImages($database, $freePlan->pageImages());
        $trunksAfter = self::summarizeTrunks($postDatabase);

        return new self(
            $sources,
            $trunksBefore,
            $trunksAfter,
            self::mergeSteps($releasedPages, $firstTrunkBefore, $freePlan),
            $freePlan,
        );
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $chains
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $chains,
        bool $secureDelete = false,
    ): self {
        $deleteResults = [];
        foreach (array_values($chains) as $index => $chain) {
            if (!is_array($chain)) {
                throw new \InvalidArgumentException('SQLite overflow freelist merge chains must be arrays');
            }
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite overflow freelist merge chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite overflow freelist merge chain is missing an overflow payload byte count');
            }
            $source = $chain['source'] ?? "overflow-chain-{$index}";
            if (!is_string($source) || $source === '') {
                throw new \InvalidArgumentException('SQLite overflow freelist merge source labels must be non-empty strings');
            }

            $deleteResults[] = [
                'source' => $source,
                'obsolete_overflow_page_numbers' => SQLiteOverflowPage::pageNumbersFromDatabase(
                    $database,
                    $firstPage,
                    $payloadBytes,
                ),
            ];
        }

        return self::fromDeleteResults($database, $deleteResults, $secureDelete);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freelist-merge-current-next',
            'sources' => $this->sources,
            'released_overflow_pages' => $this->freePlan->freedPageNumbers,
            'trunks_before' => $this->trunksBefore,
            'trunks_after' => $this->trunksAfter,
            'merge_steps' => $this->mergeSteps,
            'merged_leaf_pages' => $this->freePlan->leafPageNumbers,
            'new_current_trunk_pages' => $this->freePlan->newTrunkPageNumbers,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'updated_freelist_page_numbers' => array_keys($this->freePlan->updatedFreelistPages),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
        ];
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @return list<array{source:string,pages:list<int>,count:int}>
     */
    private static function sourcesFromDeleteResults(array $deleteResults): array
    {
        if ($deleteResults === []) {
            throw new \InvalidArgumentException('SQLite overflow freelist merge requires at least one delete result');
        }

        $sources = [];
        $seen = [];
        foreach (array_values($deleteResults) as $index => $deleteResult) {
            if (!is_array($deleteResult)) {
                throw new \InvalidArgumentException('SQLite overflow freelist merge delete results must be arrays');
            }
            $source = $deleteResult['source'] ?? "delete-{$index}";
            if (!is_string($source) || $source === '') {
                throw new \InvalidArgumentException('SQLite overflow freelist merge source labels must be non-empty strings');
            }
            $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
            if (!is_array($pages)) {
                throw new \InvalidArgumentException('SQLite overflow freelist merge delete result is missing obsolete overflow pages');
            }

            $sourcePages = [];
            foreach (array_values($pages) as $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite overflow freelist merge page numbers must be integers');
                }
                if (isset($seen[$pageNumber])) {
                    throw new \InvalidArgumentException("SQLite overflow freelist merge page {$pageNumber} appears more than once");
                }
                $seen[$pageNumber] = true;
                $sourcePages[] = $pageNumber;
            }

            $sources[] = [
                'source' => $source,
                'pages' => $sourcePages,
                'count' => count($sourcePages),
            ];
        }
        if ($seen === []) {
            throw new \InvalidArgumentException('SQLite overflow freelist merge has no obsolete overflow pages');
        }

        return $sources;
    }

    /**
     * @return list<array{page_number:int,next_trunk_page:?int,leaf_page_numbers:list<int>,leaf_count:int}>
     */
    private static function summarizeTrunks(SQLiteDatabase $database): array
    {
        $summary = [];
        foreach ($database->freelistTrunkPages() as $trunkPage) {
            $summary[] = [
                'page_number' => $trunkPage->pageNumber,
                'next_trunk_page' => $trunkPage->nextTrunkPage,
                'leaf_page_numbers' => $trunkPage->leafPageNumbers,
                'leaf_count' => count($trunkPage->leafPageNumbers),
            ];
        }

        return $summary;
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
        ksort($pages);

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    /**
     * @param list<int> $releasedPages
     * @param array{page_number:int,next_trunk_page:?int,leaf_page_numbers:list<int>,leaf_count:int}|null $firstTrunkBefore
     * @return list<array{page_number:int,disposition:string,trunk_page:int,leaf_count_before:int,leaf_count_after:int,next_trunk_page_before:?int,next_trunk_page_after:?int}>
     */
    private static function mergeSteps(array $releasedPages, ?array $firstTrunkBefore, SQLiteFreelistFreePlan $freePlan): array
    {
        $leafLookup = array_fill_keys($freePlan->leafPageNumbers, true);
        $newTrunkLookup = array_fill_keys($freePlan->newTrunkPageNumbers, true);
        $leafCount = $firstTrunkBefore['leaf_count'] ?? 0;
        $currentTrunk = $firstTrunkBefore['page_number'] ?? 0;
        $nextTrunk = $firstTrunkBefore['next_trunk_page'] ?? null;
        $steps = [];

        foreach ($releasedPages as $pageNumber) {
            if (isset($leafLookup[$pageNumber])) {
                $steps[] = [
                    'page_number' => $pageNumber,
                    'disposition' => 'merged-into-current-trunk',
                    'trunk_page' => $currentTrunk,
                    'leaf_count_before' => $leafCount,
                    'leaf_count_after' => $leafCount + 1,
                    'next_trunk_page_before' => $nextTrunk,
                    'next_trunk_page_after' => $nextTrunk,
                ];
                $leafCount++;
                continue;
            }

            if (isset($newTrunkLookup[$pageNumber])) {
                $steps[] = [
                    'page_number' => $pageNumber,
                    'disposition' => 'became-current-trunk',
                    'trunk_page' => $pageNumber,
                    'leaf_count_before' => 0,
                    'leaf_count_after' => 0,
                    'next_trunk_page_before' => $currentTrunk === 0 ? null : $currentTrunk,
                    'next_trunk_page_after' => $currentTrunk === 0 ? null : $currentTrunk,
                ];
                $currentTrunk = $pageNumber;
                $nextTrunk = $steps[array_key_last($steps)]['next_trunk_page_after'];
                $leafCount = 0;
            }
        }

        return $steps;
    }
}
