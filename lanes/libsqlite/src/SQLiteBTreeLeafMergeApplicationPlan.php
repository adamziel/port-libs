<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeLeafMergeApplicationPlan
{
    /**
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteBTreeLeafMergePlan $mergePlan,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $pageImages,
    ) {
    }

    public static function apply(
        SQLiteDatabase $database,
        SQLiteBTreeLeafMergePlan $mergePlan,
        bool $secureDelete = false,
    ): self {
        if ($database->header->pageSize !== $mergePlan->pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree leaf merge application page size does not match database');
        }
        if ($database->usablePageSize() !== $mergePlan->usableSize) {
            throw new \InvalidArgumentException('SQLite b-tree leaf merge application usable size does not match database');
        }

        $database->page($mergePlan->leftPageNumber);
        $database->page($mergePlan->rightPageNumber);

        $freePlan = $database->planPageFree($mergePlan->rightPageNumber, $secureDelete);
        $pageImages = $freePlan->pageImages();
        $pageImages[$mergePlan->leftPageNumber] = $mergePlan->mergedPage;
        ksort($pageImages);

        return new self($mergePlan, $freePlan, $pageImages);
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array{action:string,merged_page:int,obsolete_page:int,parent_page:int,freed_pages:list<int>,freelist_page_count:int,first_freelist_trunk_page:int,updated_page_numbers:list<int>,updated_pointer_map_page_numbers:list<int>,secure_delete_cleared_pages:list<int>}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->mergePlan->pageType . '-sibling-merge-apply',
            'merged_page' => $this->mergePlan->leftPageNumber,
            'obsolete_page' => $this->mergePlan->rightPageNumber,
            'parent_page' => $this->mergePlan->parentPageNumber,
            'freed_pages' => $this->freePlan->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
        ];
    }
}
