<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeCellPayloadSplitPlan
{
    /**
     * @param list<int> $overflowPageNumbers
     * @param list<array{page:int,next_page:int,payload_bytes:int,terminal:bool}> $overflowLinks
     */
    private function __construct(
        public readonly string $cellType,
        public readonly int $payloadLength,
        public readonly int $usableSize,
        public readonly int $localPayloadLength,
        public readonly int $overflowPayloadLength,
        public readonly ?int $firstOverflowPage,
        public readonly array $overflowPageNumbers,
        public readonly array $overflowLinks,
        public readonly bool $hasOverflow,
    ) {
    }

    /**
     * @param list<int>|null $overflowPageNumbers
     */
    public static function tableLeaf(
        int $payloadLength,
        int $usableSize = 512,
        ?array $overflowPageNumbers = null,
    ): self {
        $localPayloadLength = SQLiteTableLeafCell::localPayloadLength($payloadLength, $usableSize);

        return self::fromLocalPayloadLength('table-leaf', $payloadLength, $usableSize, $localPayloadLength, $overflowPageNumbers);
    }

    /**
     * @param list<int>|null $overflowPageNumbers
     */
    public static function index(
        int $payloadLength,
        int $usableSize = 512,
        ?array $overflowPageNumbers = null,
    ): self {
        $localPayloadLength = SQLiteIndexCell::localPayloadLength($payloadLength, $usableSize);

        return self::fromLocalPayloadLength('index', $payloadLength, $usableSize, $localPayloadLength, $overflowPageNumbers);
    }

    /**
     * @param list<int>|null $overflowPageNumbers
     */
    public static function fromTableLeafCell(SQLiteTableLeafCell $cell, int $usableSize = 512, ?array $overflowPageNumbers = null): self
    {
        return self::fromLocalPayloadLength('table-leaf', $cell->payloadLength, $usableSize, $cell->localPayloadLength, $overflowPageNumbers);
    }

    /**
     * @param list<int>|null $overflowPageNumbers
     */
    public static function fromIndexCell(SQLiteIndexCell $cell, int $usableSize = 512, ?array $overflowPageNumbers = null): self
    {
        return self::fromLocalPayloadLength('index', $cell->payloadLength, $usableSize, $cell->localPayloadLength, $overflowPageNumbers);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cell_type' => $this->cellType,
            'payload_length' => $this->payloadLength,
            'usable_size' => $this->usableSize,
            'local_payload_length' => $this->localPayloadLength,
            'overflow_payload_length' => $this->overflowPayloadLength,
            'has_overflow' => $this->hasOverflow,
            'first_overflow_page' => $this->firstOverflowPage,
            'overflow_page_numbers' => $this->overflowPageNumbers,
            'overflow_links' => $this->overflowLinks,
        ];
    }

    /**
     * @param list<int>|null $overflowPageNumbers
     */
    private static function fromLocalPayloadLength(
        string $cellType,
        int $payloadLength,
        int $usableSize,
        int $localPayloadLength,
        ?array $overflowPageNumbers,
    ): self {
        if ($payloadLength < 0) {
            throw new \InvalidArgumentException('SQLite b-tree cell payload length cannot be negative');
        }
        if ($localPayloadLength < 0 || $localPayloadLength > $payloadLength) {
            throw new \InvalidArgumentException('SQLite b-tree cell local payload length is outside the payload');
        }

        $overflowPayloadLength = $payloadLength - $localPayloadLength;
        $requiredPages = SQLiteOverflowPage::requiredPageCount($overflowPayloadLength, $usableSize, $usableSize);
        if ($overflowPayloadLength === 0) {
            if ($overflowPageNumbers !== null && $overflowPageNumbers !== []) {
                throw new \InvalidArgumentException('SQLite b-tree local payload cannot reserve overflow pages');
            }

            return new self($cellType, $payloadLength, $usableSize, $localPayloadLength, 0, null, [], [], false);
        }

        if ($overflowPageNumbers === null) {
            throw new \InvalidArgumentException('SQLite b-tree overflow payload split requires overflow page numbers');
        }
        if (count($overflowPageNumbers) !== $requiredPages) {
            throw new \InvalidArgumentException("SQLite b-tree overflow payload requires exactly {$requiredPages} page numbers");
        }

        $seen = [];
        foreach ($overflowPageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 2) {
                throw new \InvalidArgumentException('SQLite b-tree overflow page numbers must be integers at page 2 or later');
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite b-tree overflow page {$pageNumber} appears more than once");
            }
            $seen[$pageNumber] = true;
        }

        $links = [];
        $remaining = $overflowPayloadLength;
        $payloadCapacity = $usableSize - 4;
        foreach ($overflowPageNumbers as $index => $pageNumber) {
            $payloadBytes = min($payloadCapacity, $remaining);
            $remaining -= $payloadBytes;
            $links[] = [
                'page' => $pageNumber,
                'next_page' => $overflowPageNumbers[$index + 1] ?? 0,
                'payload_bytes' => $payloadBytes,
                'terminal' => $index === count($overflowPageNumbers) - 1,
            ];
        }

        return new self(
            $cellType,
            $payloadLength,
            $usableSize,
            $localPayloadLength,
            $overflowPayloadLength,
            $overflowPageNumbers[0],
            array_values($overflowPageNumbers),
            $links,
            true,
        );
    }
}
