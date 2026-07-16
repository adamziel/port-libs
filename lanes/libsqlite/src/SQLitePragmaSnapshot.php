<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaSnapshot
{
    /**
     * @param array<string, int|string> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromDatabase(SQLiteDatabase $database): self
    {
        $header = $database->header;

        return new self([
            'page_size' => $header->pageSize,
            'page_count' => $database->pageCount(),
            'freelist_count' => $header->freelistPageCount,
            'encoding' => self::encodingName($header->textEncoding),
            'journal_mode' => self::journalMode($header),
            'locking_mode' => 'normal',
            'auto_vacuum' => self::autoVacuumMode($database),
            'incremental_vacuum' => $database->isIncrementalVacuum() ? 1 : 0,
            'application_id' => $header->applicationId,
            'user_version' => $header->userVersion,
            'schema_version' => $header->schemaCookie,
            'data_version' => $header->fileChangeCounter,
        ]);
    }

    public function value(string $pragmaName): int|string|null
    {
        $name = str_replace('-', '_', strtolower(trim($pragmaName)));

        return $this->values[$name] ?? null;
    }

    /**
     * @param list<string>|null $names
     * @return array<string, int|string>
     */
    public function toArray(?array $names = null): array
    {
        if ($names === null) {
            return $this->values;
        }

        $selected = [];
        foreach ($names as $name) {
            $key = str_replace('-', '_', strtolower(trim($name)));
            $value = $this->value($name);
            if ($value !== null) {
                $selected[$key] = $value;
            }
        }

        return $selected;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16le',
            3 => 'UTF-16be',
            default => 'unknown',
        };
    }

    private static function journalMode(SQLiteHeader $header): string
    {
        return $header->writeVersion === 2 || $header->readVersion === 2 ? 'wal' : 'delete';
    }

    private static function autoVacuumMode(SQLiteDatabase $database): string
    {
        if (!$database->isAutoVacuum()) {
            return 'none';
        }

        return $database->isIncrementalVacuum() ? 'incremental' : 'full';
    }
}
