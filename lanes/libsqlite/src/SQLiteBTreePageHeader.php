<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePageHeader
{
    private const PAGE_TYPES = [
        0x02 => 'index-interior',
        0x05 => 'table-interior',
        0x0a => 'index-leaf',
        0x0d => 'table-leaf',
    ];

    public function __construct(
        public readonly int $pageSize,
        public readonly int $headerOffset,
        public readonly int $pageTypeFlag,
        public readonly string $pageType,
        public readonly int $firstFreeblockOffset,
        public readonly int $cellCount,
        public readonly int $cellContentAreaStart,
        public readonly int $fragmentedFreeBytes,
        public readonly ?int $rightMostPointer,
    ) {
    }

    public static function parseFirstPage(string $firstPage): self
    {
        $databaseHeader = SQLiteHeader::parse($firstPage);

        return self::parsePage($firstPage, $databaseHeader->pageSize, 100);
    }

    public static function parsePage(string $page, int $pageSize, int $headerOffset = 0): self
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException("Invalid SQLite page size: {$pageSize}");
        }
        if ($headerOffset < 0 || $headerOffset >= $pageSize) {
            throw new \InvalidArgumentException("Invalid SQLite b-tree header offset: {$headerOffset}");
        }
        if (strlen($page) < $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree parser requires a complete page image');
        }

        $flag = ord($page[$headerOffset]);
        if (!isset(self::PAGE_TYPES[$flag])) {
            throw new \InvalidArgumentException(sprintf('Invalid SQLite b-tree page type flag: 0x%02x', $flag));
        }

        $isLeaf = ($flag & 0x08) !== 0;
        $headerSize = $isLeaf ? 8 : 12;
        if ($headerOffset + $headerSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree header extends beyond page boundary');
        }

        $firstFreeblockOffset = self::readUInt16($page, $headerOffset + 1);
        $cellCount = self::readUInt16($page, $headerOffset + 3);
        $rawCellContentStart = self::readUInt16($page, $headerOffset + 5);
        $cellContentAreaStart = $rawCellContentStart === 0 ? 65536 : $rawCellContentStart;
        $fragmentedFreeBytes = ord($page[$headerOffset + 7]);
        $rightMostPointer = $isLeaf ? null : self::readUInt32($page, $headerOffset + 8);

        if ($cellContentAreaStart > $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree cell content area starts beyond page boundary');
        }
        if ($fragmentedFreeBytes > 60) {
            throw new \InvalidArgumentException('SQLite b-tree fragmented free bytes cannot exceed 60');
        }

        $cellPointerArrayEnd = $headerOffset + $headerSize + ($cellCount * 2);
        if ($cellPointerArrayEnd > $cellContentAreaStart) {
            throw new \InvalidArgumentException('SQLite b-tree cell pointer array overlaps cell content area');
        }
        if ($firstFreeblockOffset !== 0 && ($firstFreeblockOffset < $cellContentAreaStart || $firstFreeblockOffset >= $pageSize)) {
            throw new \InvalidArgumentException('SQLite b-tree first freeblock offset is outside the cell content area');
        }

        return new self(
            $pageSize,
            $headerOffset,
            $flag,
            self::PAGE_TYPES[$flag],
            $firstFreeblockOffset,
            $cellCount,
            $cellContentAreaStart,
            $fragmentedFreeBytes,
            $rightMostPointer,
        );
    }

    /**
     * @return list<int>
     */
    public function cellPointers(string $page): array
    {
        if (strlen($page) < $this->pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree parser requires a complete page image');
        }

        $pointers = [];
        $offset = $this->cellPointerArrayOffset();
        for ($i = 0; $i < $this->cellCount; $i++) {
            $pointer = self::readUInt16($page, $offset + ($i * 2));
            if ($pointer < $this->cellContentAreaStart || $pointer >= $this->pageSize) {
                throw new \InvalidArgumentException('SQLite b-tree cell pointer is outside the cell content area');
            }
            $pointers[] = $pointer;
        }

        return $pointers;
    }

    public function headerSize(): int
    {
        return $this->isLeaf() ? 8 : 12;
    }

    public function cellPointerArrayOffset(): int
    {
        return $this->headerOffset + $this->headerSize();
    }

    public function cellPointerArrayEnd(): int
    {
        return $this->cellPointerArrayOffset() + ($this->cellCount * 2);
    }

    /**
     * @return list<SQLiteBTreeFreeblock>
     */
    public function freeblocks(string $page, ?int $usableSize = null): array
    {
        $usableSize ??= $this->pageSize;
        $this->validatePageAndUsableSize($page, $usableSize);

        $freeblocks = [];
        $offset = $this->firstFreeblockOffset;
        if ($offset === 0) {
            return [];
        }

        if ($offset < $this->cellContentAreaStart) {
            throw new \InvalidArgumentException('SQLite b-tree first freeblock offset is before the cell content area');
        }

        $lastFreeblockOffset = $usableSize - 4;
        while ($offset !== 0) {
            if ($offset > $lastFreeblockOffset) {
                throw new \InvalidArgumentException('SQLite b-tree freeblock offset extends beyond usable page space');
            }

            $nextOffset = self::readUInt16($page, $offset);
            $size = self::readUInt16($page, $offset + 2);
            if ($size < 4) {
                throw new \InvalidArgumentException('SQLite b-tree freeblock size is too small');
            }
            if ($offset + $size > $usableSize) {
                throw new \InvalidArgumentException('SQLite b-tree freeblock extends beyond usable page space');
            }
            if ($nextOffset !== 0 && $nextOffset <= $offset + $size + 3) {
                throw new \InvalidArgumentException('SQLite b-tree freeblock chain is not in ascending non-overlapping order');
            }

            $freeblocks[] = new SQLiteBTreeFreeblock($offset, $size, $nextOffset === 0 ? null : $nextOffset);
            $offset = $nextOffset;
        }

        return $freeblocks;
    }

    public function freeSpaceBytes(string $page, ?int $usableSize = null): int
    {
        $usableSize ??= $this->pageSize;
        $this->validatePageAndUsableSize($page, $usableSize);

        $freeBytes = $this->fragmentedFreeBytes + $this->cellContentAreaStart;
        foreach ($this->freeblocks($page, $usableSize) as $freeblock) {
            $freeBytes += $freeblock->size;
        }

        $cellContentFloor = $this->cellPointerArrayEnd();
        if ($freeBytes > $usableSize || $freeBytes < $cellContentFloor) {
            throw new \InvalidArgumentException('SQLite b-tree free-space accounting is corrupt');
        }

        return $freeBytes - $cellContentFloor;
    }

    public function isLeaf(): bool
    {
        return ($this->pageTypeFlag & 0x08) !== 0;
    }

    public function isInterior(): bool
    {
        return !$this->isLeaf();
    }

    public function hasIntegerKeys(): bool
    {
        return ($this->pageTypeFlag & 0x01) !== 0;
    }

    public function hasLeafData(): bool
    {
        return $this->isLeaf() && $this->hasIntegerKeys();
    }

    private function validatePageAndUsableSize(string $page, int $usableSize): void
    {
        if (strlen($page) < $this->pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree parser requires a complete page image');
        }
        if ($usableSize < 0 || $usableSize > $this->pageSize) {
            throw new \InvalidArgumentException('Invalid SQLite usable page size for b-tree freeblock inspection');
        }
        if ($this->cellContentAreaStart > $usableSize) {
            throw new \InvalidArgumentException('SQLite b-tree cell content area starts beyond usable page space');
        }
    }

    private static function readUInt16(string $bytes, int $offset): int
    {
        return unpack('n', substr($bytes, $offset, 2))[1];
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        return unpack('N', substr($bytes, $offset, 4))[1];
    }
}
