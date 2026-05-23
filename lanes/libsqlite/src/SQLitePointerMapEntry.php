<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePointerMapEntry
{
    public const ROOT_PAGE = 1;
    public const FREE_PAGE = 2;
    public const FIRST_OVERFLOW_PAGE = 3;
    public const OVERFLOW_PAGE = 4;
    public const BTREE_PAGE = 5;

    public function __construct(
        public readonly int $pageNumber,
        public readonly int $pointerMapPageNumber,
        public readonly int $offset,
        public readonly int $type,
        public readonly int $parentPageNumber,
    ) {
        if (!in_array($type, [
            self::ROOT_PAGE,
            self::FREE_PAGE,
            self::FIRST_OVERFLOW_PAGE,
            self::OVERFLOW_PAGE,
            self::BTREE_PAGE,
        ], true)) {
            throw new \InvalidArgumentException("Invalid SQLite pointer-map entry type: {$type}");
        }
    }

    public function typeName(): string
    {
        return match ($this->type) {
            self::ROOT_PAGE => 'root-page',
            self::FREE_PAGE => 'free-page',
            self::FIRST_OVERFLOW_PAGE => 'first-overflow-page',
            self::OVERFLOW_PAGE => 'overflow-page',
            self::BTREE_PAGE => 'btree-page',
        };
    }

    /**
     * @return array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}
     */
    public function toArray(): array
    {
        return [
            'page_number' => $this->pageNumber,
            'pointer_map_page' => $this->pointerMapPageNumber,
            'offset' => $this->offset,
            'type' => $this->type,
            'type_name' => $this->typeName(),
            'parent_page_number' => $this->parentPageNumber,
        ];
    }
}
