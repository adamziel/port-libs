<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeInteriorRedistributionApplyPlan
{
    /**
     * @param array<int, string> $pageImages
     * @param array<int, string> $pointerMapPageImages
     * @param list<array<string, mixed>> $pointerMapEntries
     * @param array{action:string,page:int,old_separator:mixed,new_separator:mixed} $parentDividerUpdate
     */
    private function __construct(
        public readonly SQLiteBTreeInteriorRedistributionPlan $redistributionPlan,
        public readonly array $pageImages,
        public readonly array $pointerMapPageImages,
        public readonly SQLiteDatabase $postDatabase,
        public readonly array $pointerMapEntries,
        public readonly array $parentDividerUpdate,
    ) {
    }

    public static function tableInterior(
        SQLiteDatabase $database,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        int $dividerKey,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree interior redistribution apply requires an auto-vacuum database');
        }

        $plan = SQLiteBTreeInteriorRedistributionPlan::tableInterior(
            $database->page($leftPageNumber),
            $database->page($rightPageNumber),
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $dividerKey,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $parentPage = self::tableParentWithReplacementDivider($database, $parentPageNumber, $leftPageNumber, $dividerKey, $plan->newDividerKey);

        return self::fromPlan($database, $plan, $parentPage, [
            'action' => 'replace-table-parent-divider',
            'page' => $parentPageNumber,
            'old_separator' => $dividerKey,
            'new_separator' => $plan->newDividerKey,
        ]);
    }

    public static function tableCurrentAndNext(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $currentPageNumber,
    ): self {
        if ($parentPageNumber < 1 || $currentPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution page numbers must be positive');
        }

        $parentPage = $database->page($parentPageNumber);
        $headerOffset = $parentPageNumber === 1 ? 100 : 0;
        $header = SQLiteBTreePageHeader::parsePage($parentPage, $database->header->pageSize, $headerOffset);
        if ($header->pageType !== 'table-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution requires a table interior parent');
        }

        $cells = SQLiteTableInteriorCell::parsePageCells($parentPage, $header);
        $children = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $cells);
        $children[] = $header->rightMostPointer;
        $currentIndex = array_search($currentPageNumber, $children, true);
        if (!is_int($currentIndex)) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution current child is not referenced by parent');
        }
        if ($currentIndex >= count($children) - 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution requires a next sibling to the right');
        }

        $divider = $cells[$currentIndex] ?? null;
        if (!$divider instanceof SQLiteTableInteriorCell) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution could not resolve the parent divider');
        }

        return self::tableInterior(
            $database,
            $currentPageNumber,
            $children[$currentIndex + 1],
            $parentPageNumber,
            $divider->key,
        );
    }

    public static function indexInterior(
        SQLiteDatabase $database,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        string $dividerPayload,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree interior redistribution apply requires an auto-vacuum database');
        }

        $plan = SQLiteBTreeInteriorRedistributionPlan::indexInterior(
            $database->page($leftPageNumber),
            $database->page($rightPageNumber),
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $dividerPayload,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $oldValues = SQLiteRecord::parse($dividerPayload, $database->header->textEncoding)->values;
        $newDividerPayload = self::payloadForValues($plan->newDividerValues, $database->header->textEncoding);
        $parentPage = self::indexParentWithReplacementDivider($database, $parentPageNumber, $leftPageNumber, $dividerPayload, $newDividerPayload);

        return self::fromPlan($database, $plan, $parentPage, [
            'action' => 'replace-index-parent-divider',
            'page' => $parentPageNumber,
            'old_separator' => $oldValues,
            'new_separator' => $plan->newDividerValues,
        ]);
    }

    public static function indexCurrentAndNext(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $currentPageNumber,
    ): self {
        if ($parentPageNumber < 1 || $currentPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution page numbers must be positive');
        }

        $parentPage = $database->page($parentPageNumber);
        $headerOffset = $parentPageNumber === 1 ? 100 : 0;
        $header = SQLiteBTreePageHeader::parsePage($parentPage, $database->header->pageSize, $headerOffset);
        if ($header->pageType !== 'index-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution requires an index interior parent');
        }

        $cells = SQLiteIndexCell::parsePageCells($parentPage, $header, $database->usablePageSize());
        $children = array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage ?? 0, $cells);
        $children[] = $header->rightMostPointer;
        $currentIndex = array_search($currentPageNumber, $children, true);
        if (!is_int($currentIndex)) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution current child is not referenced by parent');
        }
        if ($currentIndex >= count($children) - 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution requires a next sibling to the right');
        }

        $divider = $cells[$currentIndex] ?? null;
        if (!$divider instanceof SQLiteIndexCell || $divider->payload === '') {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior redistribution could not resolve the parent divider');
        }

        return self::indexInterior(
            $database,
            $currentPageNumber,
            $children[$currentIndex + 1],
            $parentPageNumber,
            $divider->payload,
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
            'action' => $this->redistributionPlan->pageType . '-sibling-redistribute-apply',
            'left_page' => $this->redistributionPlan->leftPageNumber,
            'right_page' => $this->redistributionPlan->rightPageNumber,
            'parent_page' => $this->redistributionPlan->parentPageNumber,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->pointerMapPageImages),
            'moved_child_page_numbers' => $this->redistributionPlan->movedChildPageNumbers,
            'left_child_page_numbers' => $this->redistributionPlan->leftChildPageNumbers,
            'right_child_page_numbers' => $this->redistributionPlan->rightChildPageNumbers,
            'parent_divider_update' => $this->parentDividerUpdate,
            'pointer_map_entries' => $this->pointerMapEntries,
            'redistribution' => $this->redistributionPlan->toArray(),
        ];
    }

    /**
     * @param array{action:string,page:int,old_separator:mixed,new_separator:mixed} $parentDividerUpdate
     */
    private static function fromPlan(
        SQLiteDatabase $database,
        SQLiteBTreeInteriorRedistributionPlan $plan,
        string $parentPage,
        array $parentDividerUpdate,
    ): self {
        $pageImages = $plan->pageImages();
        $pageImages[$plan->parentPageNumber] = $parentPage;
        $pointerMapPageImages = $database->planPointerMapUpdates($plan->pointerMapUpdates);
        foreach ($pointerMapPageImages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        $postDatabase = self::databaseWithPageImages($database, $pageImages);
        $entries = [];
        foreach (array_keys($plan->pointerMapUpdates) as $pageNumber) {
            $entries[] = $postDatabase->pointerMapEntryForPage($pageNumber)->toArray();
        }

        return new self($plan, $pageImages, $pointerMapPageImages, $postDatabase, $entries, $parentDividerUpdate);
    }

    private static function tableParentWithReplacementDivider(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $oldDividerKey,
        int $newDividerKey,
    ): string {
        $parentPage = $database->page($parentPageNumber);
        $header = SQLiteBTreePageHeader::parsePage($parentPage, $database->header->pageSize);
        if ($header->pageType !== 'table-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree interior redistribution parent must be a table interior page');
        }

        $replaced = false;
        $cells = [];
        foreach (SQLiteTableInteriorCell::parsePageCells($parentPage, $header) as $cell) {
            $key = $cell->key;
            if (!$replaced && $cell->leftChildPage === $leftPageNumber && $cell->key === $oldDividerKey) {
                $key = $newDividerKey;
                $replaced = true;
            }
            $cells[] = SQLiteTableInteriorCell::encode($cell->leftChildPage, $key);
        }
        if (!$replaced) {
            throw new \InvalidArgumentException('SQLite b-tree interior redistribution parent divider was not found');
        }

        return SQLiteTableInteriorPage::assemble($cells, $header->rightMostPointer, $database->header->pageSize);
    }

    private static function indexParentWithReplacementDivider(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        string $oldDividerPayload,
        string $newDividerPayload,
    ): string {
        $parentPage = $database->page($parentPageNumber);
        $header = SQLiteBTreePageHeader::parsePage($parentPage, $database->header->pageSize);
        if ($header->pageType !== 'index-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree interior redistribution parent must be an index interior page');
        }

        $replaced = false;
        $cells = [];
        foreach (SQLiteIndexCell::parsePageCells($parentPage, $header, $database->usablePageSize()) as $cell) {
            $payload = $cell->payload;
            if (!$replaced && $cell->leftChildPage === $leftPageNumber && $cell->payload === $oldDividerPayload) {
                $payload = $newDividerPayload;
                $replaced = true;
            }
            $cells[] = SQLiteIndexCell::encode($payload, $database->usablePageSize(), null, $cell->leftChildPage);
        }
        if (!$replaced) {
            throw new \InvalidArgumentException('SQLite b-tree interior redistribution parent divider was not found');
        }

        return SQLiteIndexInteriorPage::assemble($cells, $header->rightMostPointer, $database->header->pageSize);
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pageCount = $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite replacement page images must use one-based page numbers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite replacement page image length does not match page size');
            }
            $pageCount = max($pageCount, $pageNumber);
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    /**
     * @param list<mixed> $values
     */
    private static function payloadForValues(array $values, int $textEncoding): string
    {
        return SQLiteRecord::encode($values, $textEncoding);
    }
}
