<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeIndexDeleteRebalancePlan
{
    /**
     * @param list<mixed> $deletedRecordValues
     * @param list<array{values:list<mixed>,payload:string}> $remainingLeftEntries
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $parentPageNumber,
        public readonly int $leftPageNumber,
        public readonly int $rightPageNumber,
        public readonly int $dividerIndex,
        public readonly array $deletedRecordValues,
        public readonly int $deletedPayloadBytes,
        public readonly int $beforeLeftCellCount,
        public readonly int $afterDeleteLeftCellCount,
        public readonly array $remainingLeftEntries,
        public readonly SQLiteBTreeIndexLeafBalanceApplyPlan $rebalancePlan,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param list<mixed> $recordValues
     */
    public static function deleteFromLeftAndRebalanceRight(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $rightPageNumber,
        int $dividerIndex,
        array $recordValues,
        int $textEncoding = 1,
    ): self {
        if ($recordValues === []) {
            throw new \InvalidArgumentException('SQLite index delete rebalance requires record values');
        }

        $pageSize = $database->header->pageSize;
        $usableSize = $database->usablePageSize();
        $leftPage = $database->page($leftPageNumber);
        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $leftPageNumber === 1 ? 100 : 0);
        if ($leftHeader->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite index delete rebalance requires an index-leaf delete page');
        }

        $deletedPayload = null;
        $remainingEntries = [];
        foreach (SQLiteIndexCell::parsePageCells($leftPage, $leftHeader, $usableSize) as $cell) {
            $payload = $cell->payload;
            $values = $cell->record($textEncoding)->values;
            if ($deletedPayload === null && self::compareValues($values, $recordValues) === 0) {
                $deletedPayload = $payload;
                continue;
            }
            $remainingEntries[] = [
                'values' => $values,
                'payload' => $payload,
            ];
        }
        if ($deletedPayload === null) {
            throw new \InvalidArgumentException('SQLite index delete rebalance did not find the requested record');
        }
        if ($remainingEntries === []) {
            throw new \InvalidArgumentException('SQLite index delete rebalance keeps non-empty leaves; empty leaves should use a merge/free plan');
        }

        $deletedLeftPage = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => SQLiteIndexCell::encode($entry['payload'], $usableSize), $remainingEntries),
            $pageSize,
            $leftPageNumber === 1 ? 100 : 0,
            $leftPage,
            $usableSize,
        );
        $rebalanceDatabase = self::databaseWithPageImages($database, [$leftPageNumber => $deletedLeftPage]);
        $rebalancePlan = SQLiteBTreeIndexLeafBalanceApplyPlan::apply(
            $rebalanceDatabase,
            $parentPageNumber,
            $leftPageNumber,
            $rightPageNumber,
            $dividerIndex,
            $textEncoding,
        );

        return new self(
            $parentPageNumber,
            $leftPageNumber,
            $rightPageNumber,
            $dividerIndex,
            array_values($recordValues),
            strlen($deletedPayload),
            $leftHeader->cellCount,
            count($remainingEntries),
            $remainingEntries,
            $rebalancePlan,
            $rebalancePlan->pageImages,
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
        $rebalance = $this->rebalancePlan->toArray();

        return [
            'action' => 'index-delete-rebalance-apply',
            'parent_page' => $this->parentPageNumber,
            'left_page' => $this->leftPageNumber,
            'right_page' => $this->rightPageNumber,
            'divider_index' => $this->dividerIndex,
            'deleted_record_values' => $this->deletedRecordValues,
            'deleted_payload_bytes' => $this->deletedPayloadBytes,
            'before_left_cell_count' => $this->beforeLeftCellCount,
            'after_delete_left_cell_count' => $this->afterDeleteLeftCellCount,
            'after_rebalance_cells' => $rebalance['after_cells'],
            'updated_parent_divider' => $rebalance['updated_parent_divider'],
            'moved_cell_count' => $rebalance['moved_cell_count'],
            'updated_page_numbers' => $this->updatedPageNumbers(),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function compareValues(array $left, array $right): int
    {
        $count = min(count($left), count($right));
        for ($index = 0; $index < $count; $index++) {
            $comparison = self::compareValue($left[$index], $right[$index]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return count($left) <=> count($right);
    }

    private static function compareValue(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if (is_int($left) && is_int($right)) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }
}
