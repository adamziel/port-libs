<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeEmptyLeafBatchFreePlan
{
    /**
     * @param list<array{leaf_page:int,leaf_page_type:string,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>}> $leafDeletes
     * @param list<int> $freedPageNumbers
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly array $leafDeletes,
        public readonly array $freedPageNumbers,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param list<array{leaf_page:int,leaf_page_type:string,delete_result:array<string,mixed>}> $leafDeletes
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $leafDeletes,
        bool $secureDelete = false,
    ): self {
        if ($leafDeletes === []) {
            throw new \InvalidArgumentException('SQLite empty leaf batch free plan requires at least one leaf delete result');
        }

        $normalizedDeletes = [];
        $freedPageNumbers = [];
        $seenFreedPages = [];
        foreach ($leafDeletes as $leafDelete) {
            if (!is_array($leafDelete)) {
                throw new \InvalidArgumentException('SQLite empty leaf batch entries must be arrays');
            }

            $leafPageNumber = $leafDelete['leaf_page'] ?? null;
            $leafPageType = $leafDelete['leaf_page_type'] ?? null;
            $deleteResult = $leafDelete['delete_result'] ?? null;
            if (!is_int($leafPageNumber) || !is_string($leafPageType) || !is_array($deleteResult)) {
                throw new \InvalidArgumentException('SQLite empty leaf batch entries require leaf_page, leaf_page_type, and delete_result');
            }

            $normalized = self::normalizeDeleteResult($database, $leafPageNumber, $leafPageType, $deleteResult);
            foreach (array_merge([$leafPageNumber], $normalized['obsolete_overflow_pages']) as $pageNumber) {
                if (isset($seenFreedPages[$pageNumber])) {
                    throw new \InvalidArgumentException("SQLite empty leaf batch page {$pageNumber} is released more than once");
                }
                $seenFreedPages[$pageNumber] = true;
                $freedPageNumbers[] = $pageNumber;
            }
            $normalizedDeletes[] = $normalized;
        }

        $freePlan = $database->planPageFreeList($freedPageNumbers, $secureDelete);
        $pageImages = $freePlan->pageImages();
        ksort($pageImages);

        return new self(
            $normalizedDeletes,
            $freedPageNumbers,
            $freePlan,
            $pageImages,
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
     * @return array{action:string,leaf_delete_count:int,leaf_deletes:list<array{leaf_page:int,leaf_page_type:string,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>}>,freed_pages:list<int>,freelist_page_count:int,first_freelist_trunk_page:int,new_trunk_page_numbers:list<int>,leaf_page_numbers:list<int>,updated_page_numbers:list<int>,updated_freelist_page_numbers:list<int>,updated_pointer_map_page_numbers:list<int>,secure_delete_cleared_pages:list<int>,freed_pointer_map_entries:list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}>}
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-empty-leaf-batch-free',
            'leaf_delete_count' => count($this->leafDeletes),
            'leaf_deletes' => $this->leafDeletes,
            'freed_pages' => $this->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'new_trunk_page_numbers' => $this->freePlan->newTrunkPageNumbers,
            'leaf_page_numbers' => $this->freePlan->leafPageNumbers,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_freelist_page_numbers' => array_keys($this->freePlan->updatedFreelistPages),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
            'freed_pointer_map_entries' => $this->freePlan->freedPointerMapEntries,
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return array{leaf_page:int,leaf_page_type:string,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>}
     */
    private static function normalizeDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        string $leafPageType,
        array $deleteResult,
    ): array {
        if (!in_array($leafPageType, ['table-leaf', 'index-leaf'], true)) {
            throw new \InvalidArgumentException('SQLite empty leaf batch only supports table-leaf and index-leaf pages');
        }
        if ($leafPageNumber < 2 || $leafPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite empty leaf batch leaf page is outside the database image');
        }

        $page = $deleteResult['page'] ?? null;
        if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
            throw new \InvalidArgumentException('SQLite empty leaf batch requires deleted page images');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize);
        if ($header->pageType !== $leafPageType) {
            throw new \InvalidArgumentException("SQLite empty leaf batch expected {$leafPageType} page image");
        }
        if ($header->cellCount !== 0) {
            throw new \InvalidArgumentException('SQLite empty leaf batch requires empty deleted leaves');
        }

        $deletedRowIds = [];
        $deletedRecordValues = [];
        if ($leafPageType === 'table-leaf') {
            $rowIds = $deleteResult['rowids'] ?? null;
            if (!is_array($rowIds)) {
                $rowId = $deleteResult['rowid'] ?? null;
                $rowIds = is_int($rowId) ? [$rowId] : null;
            }
            if (!is_array($rowIds)) {
                throw new \InvalidArgumentException('SQLite empty leaf batch table deletes require deleted rowids');
            }
            foreach (array_values($rowIds) as $rowId) {
                if (!is_int($rowId)) {
                    throw new \InvalidArgumentException('SQLite empty leaf batch rowids must be integers');
                }
                $deletedRowIds[] = $rowId;
            }
        } else {
            $recordValues = $deleteResult['record_values'] ?? null;
            if (!is_array($recordValues)) {
                throw new \InvalidArgumentException('SQLite empty leaf batch index deletes require deleted record values');
            }
            if ($recordValues !== [] && !is_array($recordValues[0] ?? null)) {
                $recordValues = [$recordValues];
            }
            foreach (array_values($recordValues) as $recordValue) {
                if (!is_array($recordValue)) {
                    throw new \InvalidArgumentException('SQLite empty leaf batch record values must be arrays');
                }
                $deletedRecordValues[] = array_values($recordValue);
            }
        }

        $obsoleteOverflowPages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($obsoleteOverflowPages)) {
            throw new \InvalidArgumentException('SQLite empty leaf batch requires obsolete overflow page numbers');
        }
        $normalizedOverflowPages = [];
        foreach (array_values($obsoleteOverflowPages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite empty leaf batch overflow page numbers must be integers');
            }
            $normalizedOverflowPages[] = $pageNumber;
        }

        return [
            'leaf_page' => $leafPageNumber,
            'leaf_page_type' => $leafPageType,
            'deleted_rowids' => $deletedRowIds,
            'deleted_record_values' => $deletedRecordValues,
            'obsolete_overflow_pages' => array_values(array_unique($normalizedOverflowPages)),
        ];
    }
}
