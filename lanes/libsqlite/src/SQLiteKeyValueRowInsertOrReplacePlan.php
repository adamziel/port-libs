<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteKeyValueRowInsertOrReplacePlan
{
    /**
     * @param list<int> $deletedRowIds
     * @param list<string> $deletedKeyNames
     */
    public function __construct(
        public readonly SQLiteKeyValueRowWritePlan $insertPlan,
        public readonly array $deletedRowIds,
        public readonly array $deletedKeyNames,
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
     * @return array{table_root_page:int,rowid:int,key_name:string,load_policy:?string,overflow_page_numbers:list<int>,local_payload_length:int,database_page_count:int,updated_page_numbers:list<int>,deleted_rowids:list<int>,deleted_key_names:list<string>,change_count:int}
     */
    public function toArray(): array
    {
        return $this->insertPlan->toArray() + [
            'deleted_rowids' => $this->deletedRowIds,
            'deleted_key_names' => $this->deletedKeyNames,
            'change_count' => 1 + count($this->deletedRowIds),
        ];
    }
}
