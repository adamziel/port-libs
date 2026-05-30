<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOptionRowWritePlan
{
    /**
     * @param list<int> $overflowPageNumbers
     * @param array<int, string> $pageImages
     */
    public function __construct(
        public readonly int $tableRootPage,
        public readonly int $rowId,
        public readonly string $optionName,
        public readonly string $optionValue,
        public readonly ?string $autoload,
        public readonly array $overflowPageNumbers,
        public readonly int $localPayloadLength,
        public readonly int $databasePageCount,
        private readonly array $pageImages,
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
     * @return array{table_root_page:int,rowid:int,option_name:string,autoload:?string,overflow_page_numbers:list<int>,local_payload_length:int,database_page_count:int,updated_page_numbers:list<int>}
     */
    public function toArray(): array
    {
        return [
            'table_root_page' => $this->tableRootPage,
            'rowid' => $this->rowId,
            'option_name' => $this->optionName,
            'autoload' => $this->autoload,
            'overflow_page_numbers' => $this->overflowPageNumbers,
            'local_payload_length' => $this->localPayloadLength,
            'database_page_count' => $this->databasePageCount,
            'updated_page_numbers' => array_keys($this->pageImages),
        ];
    }
}
