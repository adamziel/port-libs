<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowCurrentSourcePlan
{
    /**
     * @param list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}> $currentChainLinks
     * @param list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}> $nextChainLinks
     * @param list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}> $currentPointerMapEntries
     * @param list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}> $nextPointerMapEntries
     */
    private function __construct(
        public readonly int $firstOverflowPage,
        public readonly int $overflowPayloadLength,
        public readonly int $owningBtreePageNumber,
        public readonly string $currentPayload,
        public readonly string $nextPayload,
        public readonly array $currentChainLinks,
        public readonly array $nextChainLinks,
        public readonly array $currentPointerMapEntries,
        public readonly array $nextPointerMapEntries,
        public readonly bool $nextSourceDiffers,
        public readonly bool $currentPointerMapOwnsChain,
    ) {
    }

    public static function compareCurrentAndNext(
        SQLiteDatabase $currentDatabase,
        SQLiteDatabase $nextDatabase,
        int $firstOverflowPage,
        int $overflowPayloadLength,
        int $owningBtreePageNumber,
    ): self {
        if (!$currentDatabase->isAutoVacuum() || !$nextDatabase->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow current-source planning requires auto-vacuum databases');
        }
        if ($currentDatabase->header->pageSize !== $nextDatabase->header->pageSize) {
            throw new \InvalidArgumentException('SQLite overflow current-source planning requires matching page sizes');
        }
        if ($currentDatabase->usablePageSize() !== $nextDatabase->usablePageSize()) {
            throw new \InvalidArgumentException('SQLite overflow current-source planning requires matching usable page sizes');
        }
        if ($overflowPayloadLength < 1) {
            throw new \InvalidArgumentException('SQLite overflow current-source planning requires overflow payload bytes');
        }
        if ($firstOverflowPage < 2) {
            throw new \InvalidArgumentException('SQLite overflow current-source planning requires a valid first overflow page');
        }
        if ($owningBtreePageNumber < 1) {
            throw new \InvalidArgumentException('SQLite overflow current-source planning requires a valid owning B-tree page');
        }

        $currentChainLinks = SQLiteOverflowPage::chainLinksFromDatabase(
            $currentDatabase,
            $firstOverflowPage,
            $overflowPayloadLength,
        );
        $currentPageNumbers = array_column($currentChainLinks, 'current_page');
        $currentPointerMapEntries = self::pointerMapEntriesForPages($currentDatabase, $currentPageNumbers);
        $currentPointerMapOwnsChain = self::pointerMapOwnsChain($currentPointerMapEntries, $owningBtreePageNumber);
        if (!$currentPointerMapOwnsChain) {
            throw new \InvalidArgumentException('SQLite overflow current-source pointer-map entries do not own the current chain');
        }

        $nextChainLinks = SQLiteOverflowPage::chainLinksFromDatabase(
            $nextDatabase,
            $firstOverflowPage,
            $overflowPayloadLength,
        );
        $nextPageNumbers = array_column($nextChainLinks, 'current_page');
        $nextPointerMapEntries = self::pointerMapEntriesForPages($nextDatabase, $nextPageNumbers);
        $currentPayload = self::payloadFromChain($currentDatabase, $currentChainLinks, $overflowPayloadLength);
        $nextPayload = self::payloadFromChain($nextDatabase, $nextChainLinks, $overflowPayloadLength);

        return new self(
            $firstOverflowPage,
            $overflowPayloadLength,
            $owningBtreePageNumber,
            $currentPayload,
            $nextPayload,
            $currentChainLinks,
            $nextChainLinks,
            $currentPointerMapEntries,
            $nextPointerMapEntries,
            $currentPayload !== $nextPayload || $currentPageNumbers !== $nextPageNumbers || $currentPointerMapEntries !== $nextPointerMapEntries,
            $currentPointerMapOwnsChain,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-autovacuum-pointermap-overflow-current-source-next83',
            'first_overflow_page' => $this->firstOverflowPage,
            'overflow_payload_length' => $this->overflowPayloadLength,
            'owning_btree_page_number' => $this->owningBtreePageNumber,
            'current_chain_pages' => array_column($this->currentChainLinks, 'current_page'),
            'next_chain_pages' => array_column($this->nextChainLinks, 'current_page'),
            'current_payload_sha1' => sha1($this->currentPayload),
            'next_payload_sha1' => sha1($this->nextPayload),
            'current_pointer_map_entries' => $this->currentPointerMapEntries,
            'next_pointer_map_entries' => $this->nextPointerMapEntries,
            'next_source_differs' => $this->nextSourceDiffers,
            'current_pointer_map_owns_chain' => $this->currentPointerMapOwnsChain,
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}>
     */
    private static function pointerMapEntriesForPages(SQLiteDatabase $database, array $pageNumbers): array
    {
        $entries = [];
        foreach ($pageNumbers as $pageNumber) {
            $entries[] = $database->pointerMapEntryForPage($pageNumber)->toArray();
        }

        return $entries;
    }

    /**
     * @param list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}> $entries
     */
    private static function pointerMapOwnsChain(array $entries, int $owningBtreePageNumber): bool
    {
        foreach ($entries as $index => $entry) {
            if ($index === 0) {
                if ($entry['type_name'] !== 'first-overflow-page' || $entry['parent_page_number'] !== $owningBtreePageNumber) {
                    return false;
                }
                continue;
            }

            $previousPage = $entries[$index - 1]['page_number'];
            if ($entry['type_name'] !== 'overflow-page' || $entry['parent_page_number'] !== $previousPage) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}> $chainLinks
     */
    private static function payloadFromChain(SQLiteDatabase $database, array $chainLinks, int $overflowPayloadLength): string
    {
        $payload = '';
        foreach ($chainLinks as $link) {
            $payload .= substr($database->page($link['current_page']), 4, $link['payload_bytes']);
        }

        return substr($payload, 0, $overflowPayloadLength);
    }
}
