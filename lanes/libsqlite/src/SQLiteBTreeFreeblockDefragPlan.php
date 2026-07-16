<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblockDefragPlan
{
    /**
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $freeblocksBefore
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $freeblocksAfter
     * @param list<int> $cellPointersBefore
     * @param list<int> $cellPointersAfter
     */
    private function __construct(
        public readonly int $pageNumber,
        public readonly string $pageType,
        public readonly int $fragmentedBytesBefore,
        public readonly int $fragmentedBytesAfter,
        public readonly int $firstFreeblockOffsetBefore,
        public readonly int $firstFreeblockOffsetAfter,
        public readonly int $cellContentStartBefore,
        public readonly int $cellContentStartAfter,
        public readonly int $freeSpaceBytesBefore,
        public readonly int $freeSpaceBytesAfter,
        public readonly array $freeblocksBefore,
        public readonly array $freeblocksAfter,
        public readonly array $cellPointersBefore,
        public readonly array $cellPointersAfter,
        public readonly string $pageImage,
    ) {
    }

    public static function fromDatabasePage(
        SQLiteDatabase $database,
        int $pageNumber,
        bool $clearFreeSpace = true,
    ): self {
        if ($pageNumber < 1 || $pageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite b-tree defrag page is outside the database image');
        }

        return self::fromPage(
            $pageNumber,
            $database->page($pageNumber),
            $database->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
            $database->usablePageSize(),
            $clearFreeSpace,
        );
    }

    public static function fromPage(
        int $pageNumber,
        string $page,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        bool $clearFreeSpace = true,
    ): self {
        $usableSize ??= $pageSize;
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree defrag page number must be positive');
        }
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree defrag requires a complete page image');
        }

        $before = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        if ($before->pageType !== 'table-leaf' && $before->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite b-tree defrag currently supports leaf pages only');
        }

        $freeblocksBefore = array_map(
            static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(),
            $before->freeblocks($page, $usableSize),
        );
        $cellPointersBefore = $before->cellPointers($page);

        if ($before->pageType === 'table-leaf') {
            $pageImage = SQLiteTableLeafPage::defragment($page, $pageSize, $headerOffset, $usableSize, $clearFreeSpace);
        } else {
            $pageImage = SQLiteIndexLeafPage::defragment($page, $pageSize, $headerOffset, $usableSize, $clearFreeSpace);
        }

        $after = SQLiteBTreePageHeader::parsePage($pageImage, $pageSize, $headerOffset);
        $freeblocksAfter = array_map(
            static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(),
            $after->freeblocks($pageImage, $usableSize),
        );

        return new self(
            $pageNumber,
            $before->pageType,
            $before->fragmentedFreeBytes,
            $after->fragmentedFreeBytes,
            $before->firstFreeblockOffset,
            $after->firstFreeblockOffset,
            $before->cellContentAreaStart,
            $after->cellContentAreaStart,
            $before->freeSpaceBytes($page, $usableSize),
            $after->freeSpaceBytes($pageImage, $usableSize),
            $freeblocksBefore,
            $freeblocksAfter,
            $cellPointersBefore,
            $after->cellPointers($pageImage),
            $pageImage,
        );
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return [$this->pageNumber => $this->pageImage];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freeblock-defrag-current-next',
            'page' => $this->pageNumber,
            'page_type' => $this->pageType,
            'fragmented_bytes_before' => $this->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->fragmentedBytesAfter,
            'first_freeblock_offset_before' => $this->firstFreeblockOffsetBefore,
            'first_freeblock_offset_after' => $this->firstFreeblockOffsetAfter,
            'cell_content_start_before' => $this->cellContentStartBefore,
            'cell_content_start_after' => $this->cellContentStartAfter,
            'free_space_bytes_before' => $this->freeSpaceBytesBefore,
            'free_space_bytes_after' => $this->freeSpaceBytesAfter,
            'freeblock_count_before' => count($this->freeblocksBefore),
            'freeblock_count_after' => count($this->freeblocksAfter),
            'freeblocks_before' => $this->freeblocksBefore,
            'freeblocks_after' => $this->freeblocksAfter,
            'cell_pointers_before' => $this->cellPointersBefore,
            'cell_pointers_after' => $this->cellPointersAfter,
            'updated_page_numbers' => array_keys($this->pageImages()),
        ];
    }
}
