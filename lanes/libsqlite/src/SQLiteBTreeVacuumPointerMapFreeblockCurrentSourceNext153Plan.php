<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext153Plan
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNext147Plan $basePlan,
        public readonly array $rows,
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
        return self::fromBasePlan(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNext147Plan::tableAndIndexFromCurrentSourceDeleteResults(
            $database,
            $leafPageNumber,
            $currentOverflowChains,
            $deleteResults,
            $parentBtreePageNumber,
            $replacementOverflowPayload,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNext147Plan $basePlan): self
    {
        $rows = [];
        $leafPageNumber = $basePlan->coalescePlan->pageNumber;
        $leafPage = $basePlan->databaseAfterAllocation->page($leafPageNumber);
        $leafHeader = SQLiteBTreePageHeader::parsePage($leafPage, $basePlan->databaseAfterAllocation->header->pageSize);
        $leafReport = $leafHeader->freeblockIntegrityReport($leafPage);
        $rows[] = [
            'kind' => 'deleted-leaf-freeblock',
            'page_number' => $leafPageNumber,
            'next_page_hash' => hash('sha256', $leafPage),
            'freeblock_count' => count($leafHeader->freeblocks($leafPage)),
            'freeblock_bytes' => $leafReport['freeblock_bytes'],
            'fragmented_bytes_before' => $basePlan->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $basePlan->coalescePlan->fragmentedBytesAfter,
            'coalesced_fragment_bytes' => $basePlan->coalescePlan->coalescedFragmentBytes,
            'freeblock_status' => $leafReport['status'],
            'secure_delete_zeroed' => $leafHeader->freeblockSecureDeleteReport($leafPage)['secure_delete_payload_zeroed'],
            'pointer_map_type' => $basePlan->databaseAfterAllocation->pointerMapEntryForPage($leafPageNumber)->typeName(),
            'pointer_map_parent' => $basePlan->databaseAfterAllocation->pointerMapEntryForPage($leafPageNumber)->parentPageNumber,
            'vacuum_reuse_status' => 'leaf-freeblock-materialized',
        ];

        $freeRowsByPage = [];
        foreach ($basePlan->releasePlan->freePlan->freedPointerMapEntries as $transition) {
            $freeRowsByPage[(int) $transition['page_number']] = $transition;
        }

        foreach ($basePlan->nextRows() as $row) {
            $pageNumber = (int) $row['page_number'];
            $freeTransition = $freeRowsByPage[$pageNumber] ?? [];
            $page = $basePlan->databaseAfterAllocation->page($pageNumber);
            $rows[] = [
                'kind' => 'overflow-page-vacuum-reuse',
                'page_number' => $pageNumber,
                'allocation_position' => $row['allocation_position'],
                'page_origin' => $row['page_origin'],
                'release_source' => $row['release_source'],
                'current_source' => $row['current_source'],
                'current_chain_index' => $row['current_chain_index'],
                'current_chain_position' => $row['current_chain_position'],
                'current_next_page' => $row['current_next_page'],
                'before_pointer_map_type' => $row['before_pointer_map_type'],
                'before_pointer_map_parent' => $row['before_pointer_map_parent'],
                'free_pointer_map_type' => $freeTransition['type_name'] ?? $row['free_pointer_map_type'],
                'free_pointer_map_parent' => $freeTransition['parent_page_number'] ?? $row['free_pointer_map_parent'],
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'next_overflow_next_page' => $row['next_overflow_next_page'],
                'next_overflow_is_tail' => $row['next_overflow_is_tail'],
                'payload_prefix' => $row['payload_prefix'],
                'free_page_hash' => hash('sha256', $basePlan->databaseAfterRelease->page($pageNumber)),
                'next_page_hash' => hash('sha256', $page),
                'vacuum_reuse_status' => $row['page_origin'] === 'released-overflow-page'
                    ? 'released-overflow-reused'
                    : 'existing-freelist-reused',
            ];
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reusedReleasedRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => ($row['vacuum_reuse_status'] ?? null) === 'released-overflow-reused',
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function existingFreelistRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => ($row['vacuum_reuse_status'] ?? null) === 'existing-freelist-reused',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next153',
            'leaf_page' => $this->basePlan->coalescePlan->pageNumber,
            'released_overflow_pages' => $this->basePlan->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->basePlan->reusedReleasedOverflowPages(),
            'allocated_existing_freelist_pages' => $this->basePlan->allocatedExistingFreelistPages(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->basePlan->pageImages()),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }
}
