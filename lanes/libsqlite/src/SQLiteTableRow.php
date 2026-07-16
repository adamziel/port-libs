<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTableRow
{
    public function __construct(
        public readonly int $rowId,
        public readonly SQLiteRecord $record,
    ) {
    }

    public static function fromTableLeafCell(SQLiteTableLeafCell $cell, int $textEncoding = 1): self
    {
        return new self($cell->rowId, SQLiteRecord::parse($cell->payload, $textEncoding));
    }

    /**
     * @return list<mixed>
     */
    public function values(): array
    {
        return $this->record->values;
    }
}
