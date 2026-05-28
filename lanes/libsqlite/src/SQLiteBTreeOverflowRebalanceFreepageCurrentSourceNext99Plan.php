<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext99Plan
{
    private function __construct(
        public readonly SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext94Plan $plan,
    ) {
    }

    /**
     * @param list<array{rowid:int,obsolete_overflow_page_numbers:list<int>}> $deletions
     */
    public static function tableLeaf(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deletions,
        bool $secureDelete = false,
    ): self {
        return new self(SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext94Plan::tableLeaf(
            $database,
            $leafPageNumber,
            $deletions,
            $secureDelete,
        ));
    }

    /**
     * @param list<array{record_values:list<mixed>,obsolete_overflow_page_numbers:list<int>}> $deletions
     */
    public static function indexLeaf(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deletions,
        bool $secureDelete = false,
    ): self {
        return new self(SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext94Plan::indexLeaf(
            $database,
            $leafPageNumber,
            $deletions,
            $secureDelete,
        ));
    }

    public function databaseAfter(): SQLiteDatabase
    {
        return $this->plan->databaseAfter;
    }

    /**
     * @return list<int>
     */
    public function materializedPageNumbers(): array
    {
        return $this->plan->materializedPageNumbers();
    }

    /**
     * @return list<int>
     */
    public function releasedPageNumbers(): array
    {
        return $this->plan->releasedPageNumbers();
    }

    public function finalFreelistPageCount(): int
    {
        return $this->plan->finalFreelistPageCount();
    }

    /**
     * @return list<array{step:int,phase:string,step_type:string,freed_pages:list<int>,freelist_page_count:int,updated_page_numbers:list<int>}>
     */
    public function transitionRows(): array
    {
        return array_map(
            static fn (array $event): array => [
                'step' => (int) $event['index'],
                'phase' => (string) $event['phase'],
                'step_type' => (string) $event['step_type'],
                'freed_pages' => $event['freed_pages'],
                'freelist_page_count' => (int) $event['freelist_page_count'],
                'updated_page_numbers' => $event['updated_page_numbers'],
            ],
            $this->plan->events,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $summary = $this->plan->toArray();
        $summary['action'] = 'btree-overflow-rebalance-freepage-current-source-next99';
        $summary['current_source_transition_rows'] = $this->transitionRows();

        return $summary;
    }
}
