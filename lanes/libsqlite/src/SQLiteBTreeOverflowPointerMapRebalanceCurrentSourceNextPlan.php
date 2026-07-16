<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNextPlan
{
    private function __construct(
        public readonly SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan $rebalancePlan,
        public readonly SQLiteDatabase $databaseBefore,
    ) {
    }

    /**
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function tableDeleteRebalanceThenReplaceOverflow(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $currentPageNumber,
        int $nextPageNumber,
        int $dividerIndex,
        int $deleteRowId,
        callable $overflowPageNumbers,
        string $replacementOverflowPayload,
        bool $secureDelete = false,
        bool $allowAppend = true,
    ): self {
        return new self(
            SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan::tableDeleteRebalanceThenReplaceOverflow(
                $database,
                $parentPageNumber,
                $currentPageNumber,
                $nextPageNumber,
                $dividerIndex,
                $deleteRowId,
                $overflowPageNumbers,
                $replacementOverflowPayload,
                $secureDelete,
                $allowAppend,
            ),
            $database,
        );
    }

    /**
     * @return list<array{page_number:int,current_type_name:string|null,current_parent_page_number:int|null,release_type_name:string|null,release_parent_page_number:int|null,next_type_name:string|null,next_parent_page_number:int|null,freelist_role:string|null,allocation_source:string|null,allocation_position:int|null,rebalance_role:string,secure_deleted_before_reuse:bool,pointer_map_page:int|null,next_overflow_page:int|null}>
     */
    public function pointerMapTransitionRows(): array
    {
        $releaseByPage = [];
        foreach ($this->rebalancePlan->freePlan->freedPointerMapEntries as $entry) {
            $releaseByPage[(int) $entry['page_number']] = $entry;
        }

        $nextByPage = [];
        foreach ($this->rebalancePlan->allocationPlan->allocatedPointerMapEntries() as $entry) {
            $nextByPage[(int) $entry['page_number']] = $entry;
        }

        $allocationStepsByPage = [];
        foreach ($this->rebalancePlan->allocationPlan->allocationSteps() as $position => $step) {
            $allocationStepsByPage[(int) $step['allocated_page']] = [
                'source' => is_string($step['source'] ?? null) ? $step['source'] : null,
                'position' => $position,
            ];
        }

        $newTrunks = array_fill_keys($this->rebalancePlan->freePlan->newTrunkPageNumbers, true);
        $leaves = array_fill_keys($this->rebalancePlan->freePlan->leafPageNumbers, true);
        $cleared = array_fill_keys($this->rebalancePlan->freePlan->clearedPageNumbers, true);
        $reused = array_fill_keys($this->rebalancePlan->reusedObsoleteOverflowPages, true);
        $allocated = array_fill_keys($this->rebalancePlan->allocationPlan->allocatedPageNumbers, true);
        $obsolete = array_fill_keys($this->rebalancePlan->rebalancePlan->obsoleteOverflowPageNumbers, true);

        $nextPointers = [];
        foreach ($this->rebalancePlan->replacementChainLinks as $link) {
            $nextPointers[(int) $link['current_page']] = (int) $link['next_page'];
        }

        $pageNumbers = array_values(array_unique(array_merge(
            $this->rebalancePlan->rebalancePlan->obsoleteOverflowPageNumbers,
            $this->rebalancePlan->allocationPlan->allocatedPageNumbers,
        )));
        sort($pageNumbers);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $current = null;
            if ($this->databaseBefore->isAutoVacuum() && !$this->databaseBefore->isPointerMapPage($pageNumber) && $pageNumber <= $this->databaseBefore->pageCount()) {
                $current = $this->databaseBefore->pointerMapEntryForPage($pageNumber)->toArray();
            }
            $release = $releaseByPage[$pageNumber] ?? null;
            $next = $nextByPage[$pageNumber] ?? null;
            $step = $allocationStepsByPage[$pageNumber] ?? ['source' => null, 'position' => null];

            $freelistRole = null;
            if (isset($newTrunks[$pageNumber])) {
                $freelistRole = 'freelist-trunk';
            } elseif (isset($leaves[$pageNumber])) {
                $freelistRole = 'freelist-leaf';
            }

            $role = 'allocated-only';
            if (isset($obsolete[$pageNumber]) && isset($reused[$pageNumber])) {
                $role = 'obsolete-reused';
            } elseif (isset($obsolete[$pageNumber])) {
                $role = 'obsolete-released';
            } elseif (isset($allocated[$pageNumber])) {
                $role = 'replacement-appended';
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'current_type_name' => $current['type_name'] ?? null,
                'current_parent_page_number' => $current['parent_page_number'] ?? null,
                'release_type_name' => $release['type_name'] ?? null,
                'release_parent_page_number' => $release['parent_page_number'] ?? null,
                'next_type_name' => $next['type_name'] ?? null,
                'next_parent_page_number' => $next['parent_page_number'] ?? null,
                'freelist_role' => $freelistRole,
                'allocation_source' => $step['source'],
                'allocation_position' => $step['position'],
                'rebalance_role' => $role,
                'secure_deleted_before_reuse' => isset($cleared[$pageNumber]),
                'pointer_map_page' => $next['pointer_map_page'] ?? ($release['pointer_map_page'] ?? ($current['pointer_map_page'] ?? null)),
                'next_overflow_page' => $nextPointers[$pageNumber] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-pointermap-rebalance-current-source-next118',
            'parent_page' => $this->rebalancePlan->rebalancePlan->parentPageNumber,
            'current_page' => $this->rebalancePlan->rebalancePlan->leftPageNumber,
            'next_page' => $this->rebalancePlan->rebalancePlan->rightPageNumber,
            'deleted_rowid' => $this->rebalancePlan->rebalancePlan->deletedRowId,
            'obsolete_overflow_pages' => $this->rebalancePlan->rebalancePlan->obsoleteOverflowPageNumbers,
            'replacement_overflow_pages' => $this->rebalancePlan->replacementOverflowPageNumbers(),
            'reused_obsolete_overflow_pages' => $this->rebalancePlan->reusedObsoleteOverflowPages,
            'appended_page_numbers' => $this->rebalancePlan->allocationPlan->appendedPageNumbers,
            'pointer_map_transition_rows' => $this->pointerMapTransitionRows(),
            'updated_pointer_map_page_numbers' => array_values(array_unique(array_merge(
                array_keys($this->rebalancePlan->freePlan->updatedPointerMapPages),
                array_keys($this->rebalancePlan->allocationPlan->updatedPointerMapPages),
            ))),
            'updated_page_numbers' => $this->rebalancePlan->updatedPageNumbers(),
            'replacement_chain_links' => $this->rebalancePlan->replacementChainLinks,
        ];
    }
}
