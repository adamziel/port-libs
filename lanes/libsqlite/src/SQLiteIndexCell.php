<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexCell
{
    public function __construct(
        public readonly int $payloadLength,
        public readonly string $payload,
        public readonly int $offset,
        public readonly int $bytesRead,
        public readonly ?int $leftChildPage,
    ) {
    }

    public static function encode(
        string $payload,
        int $usableSize = 512,
        ?int $firstOverflowPage = null,
        ?int $leftChildPage = null,
    ): string {
        if ($leftChildPage !== null && $leftChildPage < 1) {
            throw new \InvalidArgumentException('SQLite index interior cell child page must be positive');
        }

        $payloadLength = strlen($payload);
        $localPayloadLength = self::localPayloadLength($payloadLength, $usableSize);
        $cell = ($leftChildPage === null ? '' : pack('N', $leftChildPage))
            . SQLiteVarint::encode($payloadLength)
            . substr($payload, 0, $localPayloadLength);

        if ($localPayloadLength < $payloadLength) {
            if ($firstOverflowPage === null || $firstOverflowPage < 2) {
                throw new \InvalidArgumentException('SQLite index overflow cell requires a valid first overflow page');
            }
            $cell .= pack('N', $firstOverflowPage);
        }

        return str_pad($cell, 4, "\0");
    }

    /**
     * @return array{cell: string, overflowPages: list<string>, localPayloadLength: int}
     */
    public static function encodeWithOverflowPages(
        string $payload,
        int $firstOverflowPage,
        int $pageSize = 512,
        ?int $usableSize = null,
        ?int $leftChildPage = null,
    ): array {
        $usableSize ??= $pageSize;
        $localPayloadLength = self::localPayloadLength(strlen($payload), $usableSize);
        if ($localPayloadLength === strlen($payload)) {
            return [
                'cell' => self::encode($payload, $usableSize, null, $leftChildPage),
                'overflowPages' => [],
                'localPayloadLength' => $localPayloadLength,
            ];
        }

        $overflowPayload = substr($payload, $localPayloadLength);

        return [
            'cell' => self::encode($payload, $usableSize, $firstOverflowPage, $leftChildPage),
            'overflowPages' => SQLiteOverflowPage::encodeChain($overflowPayload, $firstOverflowPage, $pageSize, $usableSize),
            'localPayloadLength' => $localPayloadLength,
        ];
    }

    /**
     * @param null|callable(int, int): string $overflowReader
     */
    public static function parse(
        string $page,
        SQLiteBTreePageHeader $header,
        int $offset,
        ?int $usableSize = null,
        ?callable $overflowReader = null,
    ): self {
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException('SQLite index cells require an index b-tree page');
        }

        $usableSize ??= $header->pageSize;
        if ($usableSize < 0 || $usableSize > strlen($page)) {
            throw new \InvalidArgumentException('SQLite index cell usable size is outside the page');
        }
        if ($offset < 0 || $offset >= $usableSize) {
            throw new \InvalidArgumentException('SQLite index cell offset is outside the page');
        }

        $cursor = $offset;
        $leftChildPage = null;
        if ($header->pageType === 'index-interior') {
            if ($cursor + 4 > $usableSize) {
                throw new \InvalidArgumentException('SQLite index interior cell child pointer is truncated');
            }
            $leftChildPage = self::readUInt32($page, $cursor);
            if ($leftChildPage < 1) {
                throw new \InvalidArgumentException('SQLite index interior cell child page must be positive');
            }
            $cursor += 4;
        }

        [$payloadLength, $payloadLengthBytes] = SQLiteVarint::decode($page, $cursor);
        $payloadOffset = $cursor + $payloadLengthBytes;
        $localPayloadLength = self::localPayloadLength($payloadLength, $usableSize);
        if ($payloadOffset + $localPayloadLength > $usableSize) {
            throw new \InvalidArgumentException('SQLite index cell local payload extends beyond the page');
        }

        $payload = substr($page, $payloadOffset, $localPayloadLength);
        $bytesRead = ($payloadOffset - $offset) + $localPayloadLength;
        if ($localPayloadLength < $payloadLength) {
            if ($payloadOffset + $localPayloadLength + 4 > $usableSize) {
                throw new \InvalidArgumentException('SQLite index cell overflow pointer extends beyond the page');
            }
            if ($overflowReader === null) {
                throw new \InvalidArgumentException('SQLite index overflow payloads require an overflow reader');
            }
            $firstOverflowPage = self::readUInt32($page, $payloadOffset + $localPayloadLength);
            if ($firstOverflowPage < 1) {
                throw new \InvalidArgumentException('SQLite index cell first overflow page is invalid');
            }
            $overflowPayload = $overflowReader($firstOverflowPage, $payloadLength - $localPayloadLength);
            if (strlen($overflowPayload) !== $payloadLength - $localPayloadLength) {
                throw new \InvalidArgumentException('SQLite index overflow reader returned the wrong byte count');
            }
            $payload .= $overflowPayload;
            $bytesRead += 4;
        }

        return new self(
            $payloadLength,
            $payload,
            $offset,
            $bytesRead,
            $leftChildPage,
        );
    }

    /**
     * @return list<self>
     */
    public static function parsePageCells(
        string $page,
        SQLiteBTreePageHeader $header,
        ?int $usableSize = null,
        ?callable $overflowReader = null,
    ): array {
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException('SQLite index cells require an index b-tree page');
        }
        $usableSize ??= $header->pageSize;

        $cells = [];
        foreach ($header->cellPointers($page) as $pointer) {
            $cells[] = self::parse($page, $header, $pointer, $usableSize, $overflowReader);
        }

        return $cells;
    }

    public function record(int $textEncoding = 1): SQLiteRecord
    {
        return SQLiteRecord::parse($this->payload, $textEncoding);
    }

    public static function localPayloadLength(int $payloadLength, int $usableSize): int
    {
        if ($payloadLength < 0) {
            throw new \InvalidArgumentException('SQLite index payload length cannot be negative');
        }

        $maxLocal = self::maxLocalIndexPayload($usableSize);
        if ($payloadLength <= $maxLocal) {
            return $payloadLength;
        }

        $minLocal = self::minLocalIndexPayload($usableSize);
        $surplus = $minLocal + (($payloadLength - $minLocal) % ($usableSize - 4));

        return $surplus <= $maxLocal ? $surplus : $minLocal;
    }

    private static function maxLocalIndexPayload(int $usableSize): int
    {
        if ($usableSize < 480) {
            throw new \InvalidArgumentException('SQLite index usable size must be at least 480 bytes');
        }

        return intdiv(($usableSize - 12) * 64, 255) - 23;
    }

    private static function minLocalIndexPayload(int $usableSize): int
    {
        if ($usableSize < 480) {
            throw new \InvalidArgumentException('SQLite index usable size must be at least 480 bytes');
        }

        return intdiv(($usableSize - 12) * 32, 255) - 23;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite uint32 field is truncated');
        }

        return unpack('N', substr($bytes, $offset, 4))[1];
    }
}
