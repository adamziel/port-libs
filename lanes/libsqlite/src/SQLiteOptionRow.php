<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOptionRow
{
    public function __construct(
        public readonly int $optionId,
        public readonly string $optionName,
        public readonly string $optionValue,
        public readonly ?string $autoload,
        public readonly int $rowId,
    ) {
    }

    public static function fromTableRow(SQLiteTableRow $row): self
    {
        $values = $row->values();
        if (count($values) < 3) {
            throw new \InvalidArgumentException('wp_options row must contain option_id, option_name, and option_value columns');
        }

        [$optionId, $optionName, $optionValue] = array_slice($values, 0, 3);
        $autoload = $values[3] ?? null;

        if ($optionId !== null && !is_int($optionId)) {
            throw new \InvalidArgumentException('wp_options option_id must be an integer rowid alias or null');
        }
        if (!is_string($optionName) || !is_string($optionValue)) {
            throw new \InvalidArgumentException('wp_options option_name and option_value must be text columns');
        }
        if ($autoload !== null && !is_string($autoload)) {
            throw new \InvalidArgumentException('wp_options autoload must be text or null');
        }

        return new self($optionId ?? $row->rowId, $optionName, $optionValue, $autoload, $row->rowId);
    }

    /**
     * @return array{option_id:int,option_name:string,option_value:string,autoload:?string,rowid:int}
     */
    public function toArray(): array
    {
        return [
            'option_id' => $this->optionId,
            'option_name' => $this->optionName,
            'option_value' => $this->optionValue,
            'autoload' => $this->autoload,
            'rowid' => $this->rowId,
        ];
    }
}
