<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteKeyValueRow
{
    public function __construct(
        public readonly int $settingId,
        public readonly string $keyName,
        public readonly string $keyValue,
        public readonly ?string $loadPolicy,
        public readonly int $rowId,
    ) {
    }

    public static function fromTableRow(SQLiteTableRow $row): self
    {
        $values = $row->values();
        if (count($values) < 3) {
            throw new \InvalidArgumentException('app_settings row must contain setting_id, key_name, and key_value columns');
        }

        [$settingId, $keyName, $keyValue] = array_slice($values, 0, 3);
        $loadPolicy = $values[3] ?? null;

        if ($settingId !== null && !is_int($settingId)) {
            throw new \InvalidArgumentException('app_settings setting_id must be an integer rowid alias or null');
        }
        if (!is_string($keyName) || !is_string($keyValue)) {
            throw new \InvalidArgumentException('app_settings key_name and key_value must be text columns');
        }
        if ($loadPolicy !== null && !is_string($loadPolicy)) {
            throw new \InvalidArgumentException('app_settings load_policy must be text or null');
        }

        return new self($settingId ?? $row->rowId, $keyName, $keyValue, $loadPolicy, $row->rowId);
    }

    /**
     * @return array{setting_id:int,key_name:string,key_value:string,load_policy:?string,rowid:int}
     */
    public function toArray(): array
    {
        return [
            'setting_id' => $this->settingId,
            'key_name' => $this->keyName,
            'key_value' => $this->keyValue,
            'load_policy' => $this->loadPolicy,
            'rowid' => $this->rowId,
        ];
    }
}
