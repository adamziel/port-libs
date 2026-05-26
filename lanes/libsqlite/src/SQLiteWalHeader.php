<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHeader
{
    public const MAGIC_BIG_ENDIAN = 0x377f0683;
    public const MAGIC_LITTLE_ENDIAN = 0x377f0682;

    public function __construct(
        public readonly int $magic,
        public readonly int $formatVersion,
        public readonly int $pageSize,
        public readonly int $checkpointSequence,
        public readonly int $salt1,
        public readonly int $salt2,
        public readonly int $checksum1,
        public readonly int $checksum2,
    ) {
        if (!in_array($magic, [self::MAGIC_BIG_ENDIAN, self::MAGIC_LITTLE_ENDIAN], true)) {
            throw new \InvalidArgumentException('SQLite WAL header has an unsupported magic value');
        }
        if ($pageSize !== 0 && ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite WAL header page size must be zero or a power of two between 512 and 65536');
        }
    }

    public static function parse(string $bytes): self
    {
        if (strlen($bytes) < 32) {
            throw new \InvalidArgumentException('SQLite WAL header requires 32 bytes');
        }

        /** @var array{magic:int,format:int,pageSize:int,checkpoint:int,salt1:int,salt2:int,checksum1:int,checksum2:int} $fields */
        $fields = unpack('Nmagic/Nformat/NpageSize/Ncheckpoint/Nsalt1/Nsalt2/Nchecksum1/Nchecksum2', substr($bytes, 0, 32));

        return new self(
            $fields['magic'],
            $fields['format'],
            $fields['pageSize'],
            $fields['checkpoint'],
            $fields['salt1'],
            $fields['salt2'],
            $fields['checksum1'],
            $fields['checksum2'],
        );
    }

    public function byteOrder(): string
    {
        return $this->magic === self::MAGIC_BIG_ENDIAN ? 'big-endian' : 'little-endian';
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'magic' => $this->magic,
            'format_version' => $this->formatVersion,
            'page_size' => $this->pageSize,
            'checkpoint_sequence' => $this->checkpointSequence,
            'salt1' => $this->salt1,
            'salt2' => $this->salt2,
            'checksum1' => $this->checksum1,
            'checksum2' => $this->checksum2,
            'byte_order' => $this->byteOrder(),
        ];
    }
}
