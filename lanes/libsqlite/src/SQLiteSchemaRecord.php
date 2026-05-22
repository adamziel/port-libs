<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSchemaRecord
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $tableName,
        public readonly ?int $rootPage,
        public readonly ?string $sql,
        public readonly int $rowId,
    ) {
    }

    public static function fromTableLeafCell(SQLiteTableLeafCell $cell, int $textEncoding = 1): self
    {
        $record = SQLiteRecord::parse($cell->payload, $textEncoding);
        if (count($record->values) < 5) {
            throw new \InvalidArgumentException('sqlite_schema record must contain at least five columns');
        }

        [$type, $name, $tableName, $rootPage, $sql] = array_slice($record->values, 0, 5);
        if (!is_string($type) || !is_string($name) || !is_string($tableName)) {
            throw new \InvalidArgumentException('sqlite_schema type, name, and tbl_name must be text columns');
        }
        if ($rootPage !== null && !is_int($rootPage)) {
            throw new \InvalidArgumentException('sqlite_schema rootpage must be an integer or null');
        }
        if ($sql !== null && !is_string($sql)) {
            throw new \InvalidArgumentException('sqlite_schema sql must be text or null');
        }

        return new self($type, $name, $tableName, $rootPage, $sql, $cell->rowId);
    }

    public function isTable(string $name): bool
    {
        return $this->type === 'table' && $this->name === $name;
    }

    public function isIndexForTable(string $tableName): bool
    {
        return $this->type === 'index' && $this->tableName === $tableName;
    }
}
