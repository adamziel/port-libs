<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackIndex
{
    private const V2_SIGNATURE = "\xfftOc";
    private const FANOUT_ENTRIES = 256;
    private const UINT32_BYTES = 4;
    private const UINT64_BYTES = 8;
    private const LARGE_OFFSET_FLAG = 0x80000000;
    private const HASHES = [
        'sha1' => 20,
        'sha256' => 32,
    ];

    /**
     * @param list<int> $fanout
     * @param list<string> $oids
     * @param list<null|int> $crc32s
     * @param list<int> $packOffsets
     */
    private function __construct(
        private readonly string $bytes,
        private readonly int $version,
        private readonly string $hashName,
        private readonly int $hashBytes,
        private readonly array $fanout,
        private readonly array $oids,
        private readonly array $crc32s,
        private readonly array $packOffsets,
        private readonly string $packChecksum,
        private readonly string $indexChecksum,
    ) {
    }

    public static function fromBytes(string $bytes, string $objectHash = 'sha1'): self
    {
        $objectHash = self::normalizeObjectHash($objectHash);
        $hashBytes = self::HASHES[$objectHash];
        if (!str_starts_with($bytes, self::V2_SIGNATURE)) {
            return self::fromV1Bytes($bytes, $objectHash, $hashBytes);
        }

        return self::fromV2Bytes($bytes, $objectHash, $hashBytes);
    }

    private static function fromV1Bytes(string $bytes, string $objectHash, int $hashBytes): self
    {
        $length = strlen($bytes);
        $minimum = self::FANOUT_ENTRIES * self::UINT32_BYTES + $hashBytes * 2;
        if ($length < $minimum) {
            throw new \InvalidArgumentException("Pack index of size {$length} is too small for a v1 index");
        }

        $offset = 0;
        $fanout = [];
        for ($i = 0; $i < self::FANOUT_ENTRIES; $i++) {
            $fanout[] = self::readUInt32($bytes, $offset);
        }
        self::assertMonotonicFanout($fanout);

        $count = $fanout[255];
        $entryBytes = $count * (self::UINT32_BYTES + $hashBytes);
        $expectedSize = self::FANOUT_ENTRIES * self::UINT32_BYTES
            + $entryBytes
            + $hashBytes * 2;
        if ($length !== $expectedSize) {
            throw new \InvalidArgumentException("Pack index size is incorrect, expected {$expectedSize} bytes for {$count} objects in version 1, got {$length} bytes");
        }

        $oids = [];
        $crc32s = [];
        $packOffsets = [];
        for ($i = 0; $i < $count; $i++) {
            $packOffsets[] = self::readUInt32($bytes, $offset);
            $oid = substr($bytes, $offset, $hashBytes);
            if (strlen($oid) !== $hashBytes) {
                throw new \InvalidArgumentException('Pack index ended while reading a v1 object id');
            }
            $oids[] = bin2hex($oid);
            $crc32s[] = null;
            $offset += $hashBytes;
        }

        $packChecksum = bin2hex(substr($bytes, $offset, $hashBytes));
        $offset += $hashBytes;
        $indexChecksum = bin2hex(substr($bytes, $offset, $hashBytes));

        return new self($bytes, 1, $objectHash, $hashBytes, $fanout, $oids, $crc32s, $packOffsets, $packChecksum, $indexChecksum);
    }

    private static function fromV2Bytes(string $bytes, string $objectHash, int $hashBytes): self
    {
        $length = strlen($bytes);
        $minimum = strlen(self::V2_SIGNATURE) + self::UINT32_BYTES + self::FANOUT_ENTRIES * self::UINT32_BYTES + $hashBytes * 2;
        if ($length < $minimum) {
            throw new \InvalidArgumentException("Pack index of size {$length} is too small for a v2 index");
        }

        $offset = strlen(self::V2_SIGNATURE);
        $version = self::readUInt32($bytes, $offset);
        if ($version !== 2) {
            throw new \InvalidArgumentException("Unsupported pack index version: {$version}");
        }

        $fanout = [];
        for ($i = 0; $i < self::FANOUT_ENTRIES; $i++) {
            $fanout[] = self::readUInt32($bytes, $offset);
        }
        self::assertMonotonicFanout($fanout);

        $count = $fanout[255];
        $oidTableBytes = $count * $hashBytes;
        $crcTableBytes = $count * self::UINT32_BYTES;
        $offsetTableBytes = $count * self::UINT32_BYTES;
        $offset32Start = $offset + $oidTableBytes + $crcTableBytes;
        $offset32End = $offset32Start + $offsetTableBytes;
        if ($offset32End > $length) {
            throw new \InvalidArgumentException("Pack index of size {$length} is too small for {$count} objects");
        }

        $oids = [];
        for ($i = 0; $i < $count; $i++) {
            $oids[] = bin2hex(substr($bytes, $offset, $hashBytes));
            $offset += $hashBytes;
        }

        $crc32s = [];
        for ($i = 0; $i < $count; $i++) {
            $crc32s[] = self::readUInt32($bytes, $offset);
        }

        $offset32s = [];
        $largeOffsetCount = 0;
        $maxLargeOffsetIndex = -1;
        for ($i = 0; $i < $count; $i++) {
            $offset32 = self::readUInt32($bytes, $offset);
            $offset32s[] = $offset32;
            if (($offset32 & self::LARGE_OFFSET_FLAG) !== 0) {
                $largeIndex = $offset32 ^ self::LARGE_OFFSET_FLAG;
                $largeOffsetCount++;
                $maxLargeOffsetIndex = max($maxLargeOffsetIndex, $largeIndex);
            }
        }

        if ($largeOffsetCount > 0 && $maxLargeOffsetIndex >= $largeOffsetCount) {
            throw new \InvalidArgumentException("Pack index references large offset {$maxLargeOffsetIndex}, but only {$largeOffsetCount} large offsets are present");
        }

        $largeOffsetBytes = $largeOffsetCount * self::UINT64_BYTES;
        $expectedSize = strlen(self::V2_SIGNATURE)
            + self::UINT32_BYTES
            + self::FANOUT_ENTRIES * self::UINT32_BYTES
            + $oidTableBytes
            + $crcTableBytes
            + $offsetTableBytes
            + $largeOffsetBytes
            + $hashBytes * 2;
        if ($length !== $expectedSize) {
            throw new \InvalidArgumentException("Pack index size is incorrect, expected {$expectedSize} bytes for {$count} objects in version 2, got {$length} bytes");
        }

        $largeOffsetsStart = $offset;
        $packOffsets = [];
        foreach ($offset32s as $offset32) {
            if (($offset32 & self::LARGE_OFFSET_FLAG) === 0) {
                $packOffsets[] = $offset32;
                continue;
            }

            $largeIndex = $offset32 ^ self::LARGE_OFFSET_FLAG;
            $largeOffsetCursor = $largeOffsetsStart + $largeIndex * self::UINT64_BYTES;
            $packOffsets[] = self::readUInt64($bytes, $largeOffsetCursor);
        }
        $offset += $largeOffsetBytes;

        $packChecksum = bin2hex(substr($bytes, $offset, $hashBytes));
        $offset += $hashBytes;
        $indexChecksum = bin2hex(substr($bytes, $offset, $hashBytes));

        return new self($bytes, $version, $objectHash, $hashBytes, $fanout, $oids, $crc32s, $packOffsets, $packChecksum, $indexChecksum);
    }

    public static function open(string $path, string $objectHash = 'sha1'): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Pack index file not found: {$path}");
        }

        return self::fromBytes((string) file_get_contents($path), $objectHash);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function count(): int
    {
        return $this->fanout[255];
    }

    public function objectHash(): string
    {
        return $this->hashName;
    }

    public function hashBytes(): int
    {
        return $this->hashBytes;
    }

    public function packChecksum(): string
    {
        return $this->packChecksum;
    }

    public function indexChecksum(): string
    {
        return $this->indexChecksum;
    }

    public function verifyChecksum(): string
    {
        $actual = hash($this->hashName, substr($this->bytes, 0, -$this->hashBytes));
        if ($actual !== $this->indexChecksum) {
            throw new \RuntimeException('Pack index checksum mismatch');
        }

        return $actual;
    }

    public function entryAt(int $index): PackIndexEntry
    {
        if ($index < 0 || $index >= $this->count()) {
            throw new \OutOfBoundsException("Pack index entry {$index} is out of bounds");
        }

        return new PackIndexEntry($this->oids[$index], $this->packOffsets[$index], $this->crc32s[$index], $index);
    }

    public function lookup(string $oid): ?PackIndexEntry
    {
        $hexLength = $this->hashBytes * 2;
        if (preg_match('/\A[0-9a-fA-F]{' . $hexLength . '}\z/', $oid) !== 1) {
            throw new \InvalidArgumentException("Lookup object id must be a {$hexLength}-character {$this->hashName} hex string");
        }
        $oid = strtolower($oid);
        $firstByte = hexdec(substr($oid, 0, 2));
        $lower = $firstByte === 0 ? 0 : $this->fanout[$firstByte - 1];
        $upper = $this->fanout[$firstByte];

        while ($lower < $upper) {
            $middle = intdiv($lower + $upper, 2);
            $comparison = strcmp($oid, $this->oids[$middle]);
            if ($comparison === 0) {
                return $this->entryAt($middle);
            }
            if ($comparison < 0) {
                $upper = $middle;
            } else {
                $lower = $middle + 1;
            }
        }

        return null;
    }

    /**
     * @return array{status:'missing',candidateRange:array{start:int,end:int}}|array{status:'ambiguous',matches:list<int>,candidateRange:array{start:int,end:int}}|array{status:'found',entry:PackIndexEntry,candidateRange:array{start:int,end:int}}
     */
    public function lookupPrefix(string $prefix): array
    {
        $prefix = $this->normalizePrefix($prefix);
        ['matches' => $matches, 'candidateRange' => $candidateRange] = $this->matchingPrefixIndexes($prefix);

        if ($matches === []) {
            return ['status' => 'missing', 'candidateRange' => $candidateRange];
        }
        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'matches' => $matches, 'candidateRange' => $candidateRange];
        }

        return ['status' => 'found', 'entry' => $this->entryAt($matches[0]), 'candidateRange' => $candidateRange];
    }

    public function disambiguatePrefix(string $oid, int $minimumHexLength): ?string
    {
        $hexLength = $this->hashBytes * 2;
        if (preg_match('/\A[0-9a-fA-F]{' . $hexLength . '}\z/', $oid) !== 1) {
            throw new \InvalidArgumentException("Disambiguation object id must be a {$hexLength}-character {$this->hashName} hex string");
        }
        if ($minimumHexLength < 4 || $minimumHexLength > $hexLength) {
            throw new \InvalidArgumentException("Disambiguation prefix length must be between 4 and {$hexLength} hexadecimal characters");
        }

        $oid = strtolower($oid);
        if ($minimumHexLength === $hexLength) {
            return $this->lookup($oid) === null ? null : $oid;
        }

        for ($length = $minimumHexLength; $length < $hexLength; $length++) {
            $prefix = substr($oid, 0, $length);
            $result = $this->lookupPrefix($prefix);
            if ($result['status'] === 'missing') {
                return null;
            }
            if ($result['status'] === 'found') {
                return $prefix;
            }
        }

        return $oid;
    }

    /**
     * @return list<int>
     */
    public function sortedOffsets(): array
    {
        $offsets = $this->packOffsets;
        sort($offsets, SORT_NUMERIC);

        return $offsets;
    }

    /**
     * @return list<PackIndexEntry>
     */
    public function entries(): array
    {
        $entries = [];
        for ($i = 0; $i < $this->count(); $i++) {
            $entries[] = $this->entryAt($i);
        }

        return $entries;
    }

    /**
     * @param list<int> $fanout
     */
    private static function assertMonotonicFanout(array $fanout): void
    {
        $previous = 0;
        foreach ($fanout as $index => $value) {
            if ($value < $previous) {
                throw new \InvalidArgumentException("Pack index fan-out table must be monotonically increasing at index {$index}");
            }
            $previous = $value;
        }
    }

    private static function normalizeObjectHash(string $objectHash): string
    {
        $normalized = strtolower($objectHash);
        if (!isset(self::HASHES[$normalized])) {
            throw new \InvalidArgumentException("Unsupported pack index object hash: {$objectHash}");
        }

        return $normalized;
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = strtolower($prefix);
        $hexLength = $this->hashBytes * 2;
        if (preg_match('/\A[0-9a-f]{4,' . $hexLength . '}\z/', $prefix) !== 1) {
            throw new \InvalidArgumentException("Lookup prefix must be 4 to {$hexLength} hexadecimal characters");
        }

        return $prefix;
    }

    /**
     * @return array{matches:list<int>,candidateRange:array{start:int,end:int}}
     */
    private function matchingPrefixIndexes(string $prefix): array
    {
        $firstByte = hexdec(substr($prefix, 0, 2));
        $lower = $firstByte === 0 ? 0 : $this->fanout[$firstByte - 1];
        $upper = $this->fanout[$firstByte];

        $found = null;
        while ($lower < $upper) {
            $middle = intdiv($lower + $upper, 2);
            $comparison = self::comparePrefix($prefix, $this->oids[$middle]);
            if ($comparison === 0) {
                $found = $middle;
                break;
            }
            if ($comparison < 0) {
                $upper = $middle;
            } else {
                $lower = $middle + 1;
            }
        }

        if ($found === null) {
            return ['matches' => [], 'candidateRange' => ['start' => 0, 'end' => 0]];
        }

        $start = $found;
        while ($start > 0 && self::comparePrefix($prefix, $this->oids[$start - 1]) === 0) {
            $start--;
        }
        $end = $found + 1;
        $count = $this->count();
        while ($end < $count && self::comparePrefix($prefix, $this->oids[$end]) === 0) {
            $end++;
        }

        return [
            'matches' => range($start, $end - 1),
            'candidateRange' => [
                'start' => $start,
                'end' => $end,
            ],
        ];
    }

    private static function comparePrefix(string $prefix, string $oid): int
    {
        $comparison = strncmp($prefix, $oid, strlen($prefix));
        if ($comparison < 0) {
            return -1;
        }
        if ($comparison > 0) {
            return 1;
        }

        return 0;
    }

    private static function readUInt32(string $bytes, int &$offset): int
    {
        $chunk = substr($bytes, $offset, self::UINT32_BYTES);
        if (strlen($chunk) !== self::UINT32_BYTES) {
            throw new \InvalidArgumentException('Pack index ended while reading a 32-bit value');
        }
        $offset += self::UINT32_BYTES;

        return (int) unpack('N', $chunk)[1];
    }

    private static function readUInt64(string $bytes, int $offset): int
    {
        $chunk = substr($bytes, $offset, self::UINT64_BYTES);
        if (strlen($chunk) !== self::UINT64_BYTES) {
            throw new \InvalidArgumentException('Pack index ended while reading a 64-bit offset');
        }
        $parts = unpack('Nhigh/Nlow', $chunk);
        $high = (int) $parts['high'];
        $low = (int) $parts['low'];
        if ($high > 0x7fffffff) {
            throw new \InvalidArgumentException('Pack index large offset exceeds this PHP integer platform');
        }

        return $high * 4294967296 + $low;
    }
}
