<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOptionRowReplacementPlan
{
    /**
     * @param list<int> $overflowPageNumbers
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param array<int, string> $pageImages
     * @param list<array<string, mixed>> $btreeRebalanceActions
     */
    public function __construct(
        public readonly int $tableRootPage,
        public readonly int $rowId,
        public readonly string $optionName,
        public readonly string $optionValue,
        public readonly ?string $autoload,
        public readonly array $overflowPageNumbers,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly int $localPayloadLength,
        public readonly int $databasePageCount,
        private readonly array $pageImages,
        private readonly array $btreeRebalanceActions = [],
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return $this->pageImages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function btreeRebalanceActions(): array
    {
        return $this->btreeRebalanceActions;
    }

    /**
     * @return array{action_count:int,action_types:list<string>,updated_page_numbers:list<int>,freed_page_numbers:list<int>,merged_page_numbers:list<int>,removed_divider_page_numbers:list<int>,rightmost_pointer_updates:list<array{page:int,before:int,after:int}>,total_free_space_delta:int}
     */
    public function btreeRebalanceSummary(): array
    {
        $actionTypes = [];
        $updatedPageNumbers = [];
        $freedPageNumbers = [];
        $mergedPageNumbers = [];
        $removedDividerPageNumbers = [];
        $rightmostPointerUpdates = [];
        $totalFreeSpaceDelta = 0;

        foreach ($this->btreeRebalanceActions as $action) {
            $type = (string) ($action['action'] ?? '');
            $pageNumber = (int) ($action['page'] ?? 0);
            if ($type === '' || $pageNumber <= 0) {
                continue;
            }

            $actionTypes[$type] = true;
            $updatedPageNumbers[$pageNumber] = true;

            if (isset($action['delta_free_space_bytes']) && is_int($action['delta_free_space_bytes'])) {
                $totalFreeSpaceDelta += $action['delta_free_space_bytes'];
            }

            if ($type === 'free-page') {
                $freedPageNumbers[$pageNumber] = true;
                continue;
            }

            if (str_ends_with($type, '-merge')) {
                $mergedPageNumbers[$pageNumber] = true;
            }
            if (str_ends_with($type, 'divider-removal')) {
                $removedDividerPageNumbers[$pageNumber] = true;
            }
            if (str_ends_with($type, 'rightmost-pointer-update')) {
                $rightmostPointerUpdates[] = [
                    'page' => $pageNumber,
                    'before' => (int) ($action['before_rightmost_pointer'] ?? 0),
                    'after' => (int) ($action['after_rightmost_pointer'] ?? 0),
                ];
            }
        }

        $actionTypeList = array_keys($actionTypes);
        sort($actionTypeList);

        return [
            'action_count' => count($this->btreeRebalanceActions),
            'action_types' => $actionTypeList,
            'updated_page_numbers' => $this->sortedIntKeys($updatedPageNumbers),
            'freed_page_numbers' => $this->sortedIntKeys($freedPageNumbers),
            'merged_page_numbers' => $this->sortedIntKeys($mergedPageNumbers),
            'removed_divider_page_numbers' => $this->sortedIntKeys($removedDividerPageNumbers),
            'rightmost_pointer_updates' => $rightmostPointerUpdates,
            'total_free_space_delta' => $totalFreeSpaceDelta,
        ];
    }

    /**
     * @param array<int, true> $values
     * @return list<int>
     */
    private function sortedIntKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys, SORT_NUMERIC);

        return $keys;
    }

    /**
     * @return array{table_root_page:int,rowid:int,option_name:string,autoload:?string,overflow_page_numbers:list<int>,obsolete_overflow_page_numbers:list<int>,local_payload_length:int,database_page_count:int,updated_page_numbers:list<int>}
     */
    public function toArray(): array
    {
        return [
            'table_root_page' => $this->tableRootPage,
            'rowid' => $this->rowId,
            'option_name' => $this->optionName,
            'autoload' => $this->autoload,
            'overflow_page_numbers' => $this->overflowPageNumbers,
            'obsolete_overflow_page_numbers' => $this->obsoleteOverflowPageNumbers,
            'local_payload_length' => $this->localPayloadLength,
            'database_page_count' => $this->databasePageCount,
            'updated_page_numbers' => array_keys($this->pageImages),
        ];
    }
}
