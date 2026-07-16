<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAutoVacuumPointerMapApplyPlan
{
    /**
     * @param array<int, SQLitePointerMapEntry|array{0:int,1:int}|array{type:int,parent_page_number:int}> $updatesByPage
     * @param array<int, string> $basePageImages
     * @param array<int, string> $pointerMapPageImages
     * @param list<SQLitePointerMapEntry> $appliedEntries
     */
    private function __construct(
        public readonly array $updatesByPage,
        public readonly array $basePageImages,
        public readonly array $pointerMapPageImages,
        public readonly array $appliedEntries,
        public readonly string $databaseBytes,
        public readonly SQLiteDatabase $database,
        public readonly int $databasePageCount,
    ) {
    }

    /**
     * @param array<int, SQLitePointerMapEntry|array{0:int,1:int}|array{type:int,parent_page_number:int}> $updatesByPage
     * @param array<int, string> $basePageImages
     */
    public static function apply(
        SQLiteDatabase $database,
        array $updatesByPage,
        array $basePageImages = [],
        ?int $databasePageCount = null,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite auto-vacuum pointer-map apply requires an auto-vacuum database');
        }
        if ($updatesByPage === []) {
            throw new \InvalidArgumentException('SQLite auto-vacuum pointer-map apply requires at least one update');
        }

        $databasePageCount ??= max($database->pageCount(), $database->header->databaseSizePages);
        if ($databasePageCount < $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite pointer-map apply page count cannot truncate the database image');
        }

        foreach ($basePageImages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $databasePageCount) {
                throw new \InvalidArgumentException('SQLite pointer-map apply base page images must use pages inside the planned database image');
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite pointer-map apply base page image length does not match page size');
            }
        }

        $pointerMapPageImages = $database->planPointerMapUpdates($updatesByPage, $databasePageCount);
        $pageImages = $basePageImages;
        foreach ($pointerMapPageImages as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        ksort($pageImages);

        $bytes = self::databaseBytesWithPageImages($database, $pageImages, $databasePageCount);
        $postDatabase = SQLiteDatabase::fromBytes($bytes);
        $appliedEntries = [];
        foreach (array_keys($updatesByPage) as $pageNumber) {
            $appliedEntries[] = $postDatabase->pointerMapEntryForPage($pageNumber);
        }

        return new self(
            $updatesByPage,
            $basePageImages,
            $pointerMapPageImages,
            $appliedEntries,
            $bytes,
            $postDatabase,
            $databasePageCount,
        );
    }

    /**
     * @return list<int>
     */
    public function updatedPointerMapPageNumbers(): array
    {
        return array_keys($this->pointerMapPageImages);
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        $pageNumbers = array_unique(array_merge(array_keys($this->basePageImages), array_keys($this->pointerMapPageImages)));
        sort($pageNumbers);

        return $pageNumbers;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'auto-vacuum-pointer-map-apply',
            'database_page_count' => $this->databasePageCount,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => $this->updatedPointerMapPageNumbers(),
            'applied_entries' => array_map(
                static fn (SQLitePointerMapEntry $entry): array => $entry->toArray(),
                $this->appliedEntries,
            ),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseBytesWithPageImages(SQLiteDatabase $database, array $pageImages, int $pageCount): string
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            if (isset($pageImages[$pageNumber])) {
                $pages[] = $pageImages[$pageNumber];
                continue;
            }

            $pages[] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $database->header->pageSize);
        }

        return implode('', $pages);
    }
}
