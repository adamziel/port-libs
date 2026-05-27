<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblockCoalescePlan
{
    /**
     * @param list<array{current_offset:int,current_end_offset:int,next_offset:int,fragment_bytes:int}> $coalescedFragments
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $beforeFreeblocks
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $afterFreeblocks
     */
    private function __construct(
        public readonly int $pageNumber,
        public readonly string $pageType,
        public readonly int $fragmentedBytesBefore,
        public readonly int $fragmentedBytesAfter,
        public readonly int $coalescedFragmentBytes,
        public readonly array $coalescedFragments,
        public readonly array $beforeFreeblocks,
        public readonly array $afterFreeblocks,
        public readonly string $pageImage,
    ) {
    }

    public static function fromDatabasePage(SQLiteDatabase $database, int $pageNumber, bool $clearCoalescedFragments = false): self
    {
        if ($pageNumber < 1 || $pageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite freeblock coalesce page is outside the database image');
        }

        return self::fromPage(
            $pageNumber,
            $database->page($pageNumber),
            $database->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
            $database->usablePageSize(),
            $clearCoalescedFragments,
        );
    }

    public static function fromPage(
        int $pageNumber,
        string $page,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        bool $clearCoalescedFragments = false,
    ): self {
        $usableSize ??= $pageSize;
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite freeblock coalesce page number must be positive');
        }
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite freeblock coalesce requires a complete page image');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        $beforeFreeblocks = $header->freeblocks($page, $usableSize);
        if (count($beforeFreeblocks) < 2) {
            throw new \InvalidArgumentException('SQLite freeblock coalesce requires at least two freeblocks');
        }

        $beforeArrays = array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $beforeFreeblocks);
        $blocks = [];
        foreach ($beforeFreeblocks as $freeblock) {
            $blocks[] = [
                'offset' => $freeblock->offset,
                'size' => $freeblock->size,
            ];
        }

        $coalescedFragments = [];
        $coalescedBytes = 0;
        $coalesced = [];
        foreach ($blocks as $block) {
            $lastIndex = count($coalesced) - 1;
            if ($lastIndex >= 0) {
                $currentEnd = $coalesced[$lastIndex]['offset'] + $coalesced[$lastIndex]['size'];
                $gap = $block['offset'] - $currentEnd;
                if ($gap >= 0 && $gap < 4) {
                    if ($gap > 0) {
                        $coalescedFragments[] = [
                            'current_offset' => $coalesced[$lastIndex]['offset'],
                            'current_end_offset' => $currentEnd,
                            'next_offset' => $block['offset'],
                            'fragment_bytes' => $gap,
                        ];
                        $coalescedBytes += $gap;
                    }
                    $coalesced[$lastIndex]['size'] += $gap + $block['size'];
                    continue;
                }
            }

            $coalesced[] = $block;
        }

        if ($coalescedBytes === 0) {
            throw new \InvalidArgumentException('SQLite freeblock coalesce found no current/next fragments');
        }
        if ($coalescedBytes > $header->fragmentedFreeBytes) {
            throw new \InvalidArgumentException('SQLite freeblock coalesce fragments exceed header fragmented-byte count');
        }

        $page = substr_replace($page, pack('n', $coalesced[0]['offset']), $header->headerOffset + 1, 2);
        $page[$header->headerOffset + 7] = chr($header->fragmentedFreeBytes - $coalescedBytes);
        foreach ($coalesced as $index => $block) {
            $nextOffset = $coalesced[$index + 1]['offset'] ?? 0;
            if ($clearCoalescedFragments) {
                $page = substr_replace($page, str_repeat("\0", $block['size']), $block['offset'], $block['size']);
            }
            $page = substr_replace($page, pack('n', $nextOffset) . pack('n', $block['size']), $block['offset'], 4);
        }

        $afterHeader = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        $afterArrays = array_map(
            static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(),
            $afterHeader->freeblocks($page, $usableSize),
        );

        return new self(
            $pageNumber,
            $header->pageType,
            $header->fragmentedFreeBytes,
            $afterHeader->fragmentedFreeBytes,
            $coalescedBytes,
            $coalescedFragments,
            $beforeArrays,
            $afterArrays,
            $page,
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
            'action' => 'btree-freeblock-coalesce-current-next',
            'page' => $this->pageNumber,
            'page_type' => $this->pageType,
            'fragmented_bytes_before' => $this->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->fragmentedBytesAfter,
            'coalesced_fragment_bytes' => $this->coalescedFragmentBytes,
            'coalesced_fragments' => $this->coalescedFragments,
            'freeblock_count_before' => count($this->beforeFreeblocks),
            'freeblock_count_after' => count($this->afterFreeblocks),
            'freeblocks_before' => $this->beforeFreeblocks,
            'freeblocks_after' => $this->afterFreeblocks,
            'updated_page_numbers' => array_keys($this->pageImages()),
        ];
    }
}
