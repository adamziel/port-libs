<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackData
{
    private const HEADER_BYTES = 12;
    private const CHECKSUM_BYTES = 20;

    private const TYPE_IDS = [
        1 => 'commit',
        2 => 'tree',
        3 => 'blob',
        4 => 'tag',
        6 => 'ofs-delta',
        7 => 'ref-delta',
    ];

    private function __construct(
        private readonly string $bytes,
        private readonly int $version,
        private readonly int $objectCount,
        private readonly string $checksum,
    ) {
    }

    public static function fromBytes(string $bytes): self
    {
        if (strlen($bytes) < self::HEADER_BYTES + self::CHECKSUM_BYTES) {
            throw new \InvalidArgumentException('Pack data is too small to contain a header and checksum');
        }
        if (!str_starts_with($bytes, 'PACK')) {
            throw new \InvalidArgumentException('Pack data type not recognized');
        }

        $version = self::readUInt32($bytes, 4);
        if ($version !== 2 && $version !== 3) {
            throw new \InvalidArgumentException("Unsupported pack version: {$version}");
        }

        return new self($bytes, $version, self::readUInt32($bytes, 8), bin2hex(substr($bytes, -self::CHECKSUM_BYTES)));
    }

    public static function open(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Pack data file not found: {$path}");
        }

        return self::fromBytes((string) file_get_contents($path));
    }

    public function version(): int
    {
        return $this->version;
    }

    public function count(): int
    {
        return $this->objectCount;
    }

    public function checksum(): string
    {
        return $this->checksum;
    }

    public function verifyChecksum(): string
    {
        $actual = hash('sha1', substr($this->bytes, 0, -self::CHECKSUM_BYTES));
        if ($actual !== $this->checksum) {
            throw new \RuntimeException('Pack data checksum mismatch');
        }

        return $actual;
    }

    public function entryAtOffset(int $packOffset, ?int $nextOffset = null): PackDataEntry
    {
        if ($packOffset < self::HEADER_BYTES || $packOffset >= strlen($this->bytes) - self::CHECKSUM_BYTES) {
            throw new \InvalidArgumentException('Pack entry offset is outside the data section');
        }
        $nextOffset ??= strlen($this->bytes) - self::CHECKSUM_BYTES;
        if ($nextOffset <= $packOffset || $nextOffset > strlen($this->bytes) - self::CHECKSUM_BYTES) {
            throw new \InvalidArgumentException('Pack entry next offset is outside the data section');
        }

        $cursor = $packOffset;
        $first = ord($this->bytes[$cursor++]);
        $typeId = ($first >> 4) & 0x07;
        if (!isset(self::TYPE_IDS[$typeId])) {
            throw new \InvalidArgumentException("Unsupported pack entry type id: {$typeId}");
        }

        $size = $first & 0x0f;
        $shift = 4;
        $current = $first;
        while (($current & 0x80) !== 0) {
            if ($cursor >= $nextOffset) {
                throw new \InvalidArgumentException('Pack entry size header ended unexpectedly');
            }
            $current = ord($this->bytes[$cursor++]);
            $size |= ($current & 0x7f) << $shift;
            $shift += 7;
            if ($shift > 63) {
                throw new \InvalidArgumentException('Pack entry size header is too large');
            }
        }

        $kind = self::TYPE_IDS[$typeId];
        $baseDistance = null;
        $baseObjectId = null;
        if ($kind === 'ofs-delta') {
            $baseDistance = self::readOffsetDeltaDistance($this->bytes, $cursor, $nextOffset);
        } elseif ($kind === 'ref-delta') {
            if ($cursor + 20 > $nextOffset) {
                throw new \InvalidArgumentException('Ref-delta pack entry is missing its base object id');
            }
            $baseObjectId = bin2hex(substr($this->bytes, $cursor, 20));
            $cursor += 20;
        }

        $compressed = substr($this->bytes, $cursor, $nextOffset - $cursor);
        $data = zlib_decode($compressed, $size);
        if ($data === false) {
            throw new \RuntimeException('Unable to inflate pack data entry');
        }
        if (strlen($data) !== $size) {
            throw new \RuntimeException("Pack entry decompressed size mismatch: expected {$size}, got " . strlen($data));
        }

        return new PackDataEntry($kind, $size, $packOffset, $cursor, $cursor - $packOffset, $data, $baseDistance, $baseObjectId);
    }

    public function readObject(PackIndex $index, string $oid): GitObject
    {
        $entry = $index->lookup($oid);
        if ($entry === null) {
            throw new \RuntimeException("Object id not found in pack index: {$oid}");
        }
        if ($index->packChecksum() !== $this->checksum) {
            throw new \RuntimeException('Pack index checksum does not match pack data checksum');
        }

        $packEntry = $this->entryAtOffset($entry->packOffset, $this->nextOffset($index, $entry->packOffset));
        $object = $packEntry->object();
        if ($object->oid() !== strtolower($oid)) {
            throw new \RuntimeException('Pack entry object id does not match index lookup');
        }

        return $object;
    }

    private function nextOffset(PackIndex $index, int $packOffset): int
    {
        foreach ($index->sortedOffsets() as $candidate) {
            if ($candidate > $packOffset) {
                return $candidate;
            }
        }

        return strlen($this->bytes) - self::CHECKSUM_BYTES;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $chunk = substr($bytes, $offset, 4);
        if (strlen($chunk) !== 4) {
            throw new \InvalidArgumentException('Pack data ended while reading a 32-bit value');
        }

        return (int) unpack('N', $chunk)[1];
    }

    private static function readOffsetDeltaDistance(string $bytes, int &$cursor, int $nextOffset): int
    {
        if ($cursor >= $nextOffset) {
            throw new \InvalidArgumentException('Ofs-delta pack entry is missing its base distance');
        }
        $byte = ord($bytes[$cursor++]);
        $distance = $byte & 0x7f;
        while (($byte & 0x80) !== 0) {
            if ($cursor >= $nextOffset) {
                throw new \InvalidArgumentException('Ofs-delta base distance ended unexpectedly');
            }
            $byte = ord($bytes[$cursor++]);
            $distance = (($distance + 1) << 7) | ($byte & 0x7f);
        }

        return $distance;
    }
}
