<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOptionRowInsertOrReplacePlan
{
    /**
     * @param list<int> $deletedRowIds
     * @param list<string> $deletedOptionNames
     */
    public function __construct(
        public readonly SQLiteOptionRowWritePlan $insertPlan,
        public readonly array $deletedRowIds,
        public readonly array $deletedOptionNames,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return $this->insertPlan->pageImages();
    }

    /**
     * @return array{table_root_page:int,rowid:int,option_name:string,autoload:?string,overflow_page_numbers:list<int>,local_payload_length:int,database_page_count:int,updated_page_numbers:list<int>,deleted_rowids:list<int>,deleted_option_names:list<string>,change_count:int}
     */
    public function toArray(): array
    {
        return $this->insertPlan->toArray() + [
            'deleted_rowids' => $this->deletedRowIds,
            'deleted_option_names' => $this->deletedOptionNames,
            'change_count' => 1 + count($this->deletedRowIds),
        ];
    }
}
