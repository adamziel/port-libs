<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFreelistTrunkPage
{
    /**
     * @param list<int> $leafPageNumbers
     */
    public function __construct(
        public readonly int $pageNumber,
        public readonly ?int $nextTrunkPage,
        public readonly array $leafPageNumbers,
    ) {
    }

    public static function parse(int $pageNumber, string $page, int $usableSize, int $databasePageCount): self
    {
        if ($pageNumber < 2) {
            throw new \InvalidArgumentException('SQLite freelist trunk page numbers must be at page 2 or later');
        }
        if ($pageNumber > $databasePageCount) {
            throw new \InvalidArgumentException("SQLite freelist trunk page {$pageNumber} is beyond the database image");
        }
        self::validatePageShape(strlen($page), $usableSize);

        $nextTrunkPage = self::readUInt32($page, 0);
        if ($nextTrunkPage !== 0 && ($nextTrunkPage < 2 || $nextTrunkPage > $databasePageCount)) {
            throw new \InvalidArgumentException('SQLite freelist next trunk page is outside the database image');
        }

        $leafCount = self::readUInt32($page, 4);
        $maxLeafCount = intdiv($usableSize, 4) - 2;
        if ($leafCount > $maxLeafCount) {
            throw new \InvalidArgumentException('SQLite freelist trunk leaf count is too large');
        }

        $leafPageNumbers = [];
        for ($i = 0; $i < $leafCount; $i++) {
            $leafPageNumber = self::readUInt32($page, 8 + ($i * 4));
            if ($leafPageNumber < 2 || $leafPageNumber > $databasePageCount) {
                throw new \InvalidArgumentException('SQLite freelist leaf page is outside the database image');
            }
            if ($leafPageNumber === $pageNumber) {
                throw new \InvalidArgumentException('SQLite freelist leaf page duplicates its trunk page');
            }
            $leafPageNumbers[] = $leafPageNumber;
        }

        return new self($pageNumber, $nextTrunkPage === 0 ? null : $nextTrunkPage, $leafPageNumbers);
    }

    /**
     * @param list<int> $leafPageNumbers
     */
    public static function assemble(
        ?int $nextTrunkPage,
        array $leafPageNumbers,
        int $pageSize = 512,
        ?int $usableSize = null,
    ): string {
        $usableSize ??= $pageSize;
        self::validatePageShape($pageSize, $usableSize);

        $maxLeafCount = intdiv($usableSize, 4) - 2;
        if (count($leafPageNumbers) > $maxLeafCount) {
            throw new \InvalidArgumentException('SQLite freelist trunk leaf count is too large');
        }
        if ($nextTrunkPage !== null && ($nextTrunkPage < 2 || $nextTrunkPage > 0xffffffff)) {
            throw new \InvalidArgumentException('SQLite freelist next trunk page must fit in 32 bits');
        }

        $seen = [];
        foreach ($leafPageNumbers as $leafPageNumber) {
            if (!is_int($leafPageNumber) || $leafPageNumber < 2 || $leafPageNumber > 0xffffffff) {
                throw new \InvalidArgumentException('SQLite freelist leaf page numbers must fit in 32 bits');
            }
            if (isset($seen[$leafPageNumber])) {
                throw new \InvalidArgumentException("SQLite freelist leaf page {$leafPageNumber} appears more than once");
            }
            $seen[$leafPageNumber] = true;
        }

        $page = str_repeat("\0", $pageSize);
        $page = substr_replace($page, pack('N', $nextTrunkPage ?? 0), 0, 4);
        $page = substr_replace($page, pack('N', count($leafPageNumbers)), 4, 4);
        foreach ($leafPageNumbers as $index => $leafPageNumber) {
            $page = substr_replace($page, pack('N', $leafPageNumber), 8 + ($index * 4), 4);
        }

        return $page;
    }

    public function pageCount(): int
    {
        return 1 + count($this->leafPageNumbers);
    }

    /**
     * @return list<int>
     */
    public function allocationOrder(): array
    {
        if ($this->leafPageNumbers === []) {
            return [$this->pageNumber];
        }

        return array_merge(
            [$this->leafPageNumbers[0]],
            array_reverse(array_slice($this->leafPageNumbers, 1)),
            [$this->pageNumber],
        );
    }

    /**
     * @return array{page_number:int,next_trunk_page:?int,leaf_page_numbers:list<int>,page_count:int,allocation_order:list<int>}
     */
    public function toArray(): array
    {
        return [
            'page_number' => $this->pageNumber,
            'next_trunk_page' => $this->nextTrunkPage,
            'leaf_page_numbers' => $this->leafPageNumbers,
            'page_count' => $this->pageCount(),
            'allocation_order' => $this->allocationOrder(),
        ];
    }

    private static function validatePageShape(int $pageSize, int $usableSize): void
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite freelist page size must be a power of two between 512 and 65536 bytes');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite freelist usable size is outside the page');
        }
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite freelist uint32 field is truncated');
        }

        return unpack('N', substr($bytes, $offset, 4))[1];
    }
}
