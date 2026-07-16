<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeInteriorRedistributionCurrentNextPlan
{
    /**
     * @param array<int, string> $currentPageImages
     * @param array<int, string> $nextPageImages
     * @param list<array<string, mixed>> $pageTransitions
     * @param list<array<string, mixed>> $pointerMapTransitions
     */
    private function __construct(
        public readonly SQLiteDatabase $currentDatabase,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly SQLiteBTreeInteriorRedistributionApplyPlan $applyPlan,
        public readonly array $currentPageImages,
        public readonly array $nextPageImages,
        public readonly array $pageTransitions,
        public readonly array $pointerMapTransitions,
    ) {
    }

    public static function tableInterior(
        SQLiteDatabase $database,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        int $dividerKey,
    ): self {
        return self::fromApplyPlan($database, SQLiteBTreeInteriorRedistributionApplyPlan::tableInterior(
            $database,
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $dividerKey,
        ));
    }

    public static function indexInterior(
        SQLiteDatabase $database,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        string $dividerPayload,
    ): self {
        return self::fromApplyPlan($database, SQLiteBTreeInteriorRedistributionApplyPlan::indexInterior(
            $database,
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $dividerPayload,
        ));
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->nextPageImages);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-interior-redistribute-current-next',
            'page_type' => $this->applyPlan->redistributionPlan->pageType,
            'current_page_count' => $this->currentDatabase->pageCount(),
            'next_page_count' => $this->nextDatabase->pageCount(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'parent_divider_update' => $this->applyPlan->parentDividerUpdate,
            'moved_child_page_numbers' => $this->applyPlan->redistributionPlan->movedChildPageNumbers,
            'page_transitions' => $this->pageTransitions,
            'pointer_map_transitions' => $this->pointerMapTransitions,
            'apply' => $this->applyPlan->toArray(),
        ];
    }

    private static function fromApplyPlan(SQLiteDatabase $database, SQLiteBTreeInteriorRedistributionApplyPlan $applyPlan): self
    {
        $currentPageImages = [];
        $nextPageImages = [];
        $pageTransitions = [];

        foreach ($applyPlan->pageImages as $pageNumber => $nextPage) {
            $currentPage = $database->page($pageNumber);
            $currentPageImages[$pageNumber] = $currentPage;
            $nextPageImages[$pageNumber] = $nextPage;
            $pageTransitions[] = [
                'page' => $pageNumber,
                'changed' => $currentPage !== $nextPage,
                'current_sha1' => sha1($currentPage),
                'next_sha1' => sha1($nextPage),
            ];
        }

        $pointerMapTransitions = [];
        foreach ($applyPlan->pointerMapEntries as $entry) {
            $pageNumber = $entry['page_number'] ?? null;
            if (!is_int($pageNumber)) {
                continue;
            }

            $before = $database->pointerMapEntryForPage($pageNumber);
            $after = $applyPlan->postDatabase->pointerMapEntryForPage($pageNumber);
            $pointerMapTransitions[] = [
                'page' => $pageNumber,
                'current_parent_page_number' => $before->parentPageNumber,
                'next_parent_page_number' => $after->parentPageNumber,
                'current_type_name' => $before->typeName(),
                'next_type_name' => $after->typeName(),
                'changed' => $before->type !== $after->type || $before->parentPageNumber !== $after->parentPageNumber,
            ];
        }

        return new self(
            $database,
            $applyPlan->postDatabase,
            $applyPlan,
            $currentPageImages,
            $nextPageImages,
            $pageTransitions,
            $pointerMapTransitions,
        );
    }
}
