<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeEmptyLeafFreePlan
{
    /**
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param list<int> $freedPageNumbers
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $leafPageNumber,
        public readonly string $leafPageType,
        public readonly array $deletedRowIds,
        public readonly array $deletedRecordValues,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $freedPageNumbers,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        $rowIds = $deleteResult['rowids'] ?? null;
        if (!is_array($rowIds)) {
            $rowId = $deleteResult['rowid'] ?? null;
            $rowIds = is_int($rowId) ? [$rowId] : null;
        }
        if (!is_array($rowIds)) {
            throw new \InvalidArgumentException('SQLite empty table leaf free plan requires deleted rowids');
        }

        return self::fromDeleteResult($database, $leafPageNumber, $deleteResult, 'table-leaf', $rowIds, [], $secureDelete);
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function indexLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        $recordValues = $deleteResult['record_values'] ?? null;
        if (!is_array($recordValues)) {
            throw new \InvalidArgumentException('SQLite empty index leaf free plan requires deleted record values');
        }
        $recordValuesList = $recordValues;
        if ($recordValuesList !== [] && !is_array($recordValuesList[0] ?? null)) {
            $recordValuesList = [$recordValuesList];
        }

        return self::fromDeleteResult($database, $leafPageNumber, $deleteResult, 'index-leaf', [], $recordValuesList, $secureDelete);
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array{action:string,leaf_page:int,leaf_page_type:string,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>,freed_pages:list<int>,freelist_page_count:int,first_freelist_trunk_page:int,updated_page_numbers:list<int>,updated_pointer_map_page_numbers:list<int>,secure_delete_cleared_pages:list<int>}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->leafPageType . '-empty-leaf-free',
            'leaf_page' => $this->leafPageNumber,
            'leaf_page_type' => $this->leafPageType,
            'deleted_rowids' => $this->deletedRowIds,
            'deleted_record_values' => $this->deletedRecordValues,
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'freed_pages' => $this->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     */
    private static function fromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        string $expectedPageType,
        array $deletedRowIds,
        array $deletedRecordValues,
        bool $secureDelete,
    ): self {
        if ($leafPageNumber < 2 || $leafPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite empty leaf free plan leaf page is outside the database image');
        }

        $page = $deleteResult['page'] ?? null;
        if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
            throw new \InvalidArgumentException('SQLite empty leaf free plan requires a deleted page image');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize);
        if ($header->pageType !== $expectedPageType) {
            throw new \InvalidArgumentException("SQLite empty leaf free plan requires a {$expectedPageType} page image");
        }
        if ($header->cellCount !== 0) {
            throw new \InvalidArgumentException('SQLite empty leaf free plan requires the deleted leaf to be empty');
        }

        $obsoleteOverflowPageNumbers = self::obsoleteOverflowPageNumbers($deleteResult);
        $freedPageNumbers = array_values(array_unique(array_merge([$leafPageNumber], $obsoleteOverflowPageNumbers)));
        $freePlan = $database->planPageFreeList($freedPageNumbers, $secureDelete);
        $pageImages = $freePlan->pageImages();
        ksort($pageImages);

        return new self(
            $leafPageNumber,
            $expectedPageType,
            array_values($deletedRowIds),
            array_values($deletedRecordValues),
            $obsoleteOverflowPageNumbers,
            $freePlan,
            $freedPageNumbers,
            $pageImages,
        );
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return list<int>
     */
    private static function obsoleteOverflowPageNumbers(array $deleteResult): array
    {
        $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($pages)) {
            throw new \InvalidArgumentException('SQLite empty leaf free plan requires obsolete overflow page numbers');
        }

        $normalized = [];
        foreach (array_values($pages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite empty leaf free plan overflow page numbers must be integers');
            }
            $normalized[] = $pageNumber;
        }

        return array_values(array_unique($normalized));
    }
}
