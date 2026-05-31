<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackData
{
    private const HEADER_BYTES = 12;
    private const HASHES = [
        'sha1' => 20,
        'sha256' => 32,
    ];

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
        private readonly string $hashName,
        private readonly int $hashBytes,
        private readonly string $checksum,
    ) {
    }

    public static function fromBytes(string $bytes, string $objectHash = 'sha1'): self
    {
        $objectHash = self::normalizeObjectHash($objectHash);
        $hashBytes = self::HASHES[$objectHash];

        if (strlen($bytes) < self::HEADER_BYTES + $hashBytes) {
            throw new \InvalidArgumentException('Pack data is too small to contain a header and checksum');
        }
        if (!str_starts_with($bytes, 'PACK')) {
            throw new \InvalidArgumentException('Pack data type not recognized');
        }

        $version = self::readUInt32($bytes, 4);
        if ($version !== 2 && $version !== 3) {
            throw new \InvalidArgumentException("Unsupported pack version: {$version}");
        }

        return new self($bytes, $version, self::readUInt32($bytes, 8), $objectHash, $hashBytes, bin2hex(substr($bytes, -$hashBytes)));
    }

    public static function open(string $path, string $objectHash = 'sha1'): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Pack data file not found: {$path}");
        }

        return self::fromBytes((string) file_get_contents($path), $objectHash);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function count(): int
    {
        return $this->objectCount;
    }

    public function objectHash(): string
    {
        return $this->hashName;
    }

    public function hashBytes(): int
    {
        return $this->hashBytes;
    }

    public function checksum(): string
    {
        return $this->checksum;
    }

    public function verifyChecksum(): string
    {
        $actual = hash($this->hashName, substr($this->bytes, 0, -$this->hashBytes));
        if ($actual !== $this->checksum) {
            throw new \RuntimeException('Pack data checksum mismatch');
        }

        return $actual;
    }

    public function entryAtOffset(int $packOffset, ?int $nextOffset = null): PackDataEntry
    {
        $entry = $this->entryMetadataAtOffset($packOffset, $nextOffset);
        $data = self::inflateEntryData($this->compressedDataForEntryMetadata($entry), $entry['decompressedSize']);

        return new PackDataEntry(
            $entry['kind'],
            $entry['decompressedSize'],
            $entry['packOffset'],
            $entry['dataOffset'],
            $entry['headerSize'],
            $data,
            $entry['baseDistance'],
            $entry['baseObjectId'],
        );
    }

    public function entryAtIndexOffset(PackIndex $index, int $packOffset): PackDataEntry
    {
        return $this->entryAtOffset($packOffset, $this->nextOffset($index, $packOffset));
    }

    public function compressedDataAtIndexOffset(PackIndex $index, int $packOffset): string
    {
        $nextOffset = $this->nextOffset($index, $packOffset);
        $entry = $this->entryAtOffset($packOffset, $nextOffset);

        return substr($this->bytes, $entry->dataOffset, $nextOffset - $entry->dataOffset);
    }

    public function objectIdForOffset(PackIndex $index, int $packOffset): ?string
    {
        foreach ($index->entries() as $entry) {
            if ($entry->packOffset === $packOffset) {
                return $entry->oid;
            }
        }

        return null;
    }

    public function readObject(PackIndex $index, string $oid): GitObject
    {
        $entry = $index->lookup($oid);
        if ($entry === null) {
            throw new \RuntimeException("Object id not found in pack index: {$oid}");
        }

        return $this->readObjectAtVerifiedOffset($index, $oid, $entry->packOffset);
    }

    /**
     * @return array{type:string,size:int,numDeltas:int}
     */
    public function readObjectHeader(PackIndex $index, string $oid): array
    {
        $entry = $index->lookup($oid);
        if ($entry === null) {
            throw new \RuntimeException("Object id not found in pack index: {$oid}");
        }

        return $this->readObjectHeaderAtVerifiedOffset($index, $entry->packOffset);
    }

    /**
     * @return array{type:string,size:int,numDeltas:int}
     */
    public function readObjectHeaderAtOffset(PackIndex $index, string $oid, int $packOffset): array
    {
        $entry = $index->lookup($oid);
        if ($entry === null) {
            throw new \RuntimeException("Object id not found in pack index: {$oid}");
        }
        if ($entry->packOffset !== $packOffset) {
            throw new \RuntimeException('Multi-pack-index offset does not match pack index lookup');
        }

        return $this->readObjectHeaderAtVerifiedOffset($index, $packOffset);
    }

    /**
     * @param array<string,GitObject> $externalBases
     */
    public function readObjectWithExternalBases(PackIndex $index, string $oid, array $externalBases): GitObject
    {
        return $this->readObjectWithExternalBaseResolver($index, $oid, self::externalBaseResolver($externalBases));
    }

    /**
     * @param callable(string):(?GitObject) $externalBaseResolver
     */
    public function readObjectWithExternalBaseResolver(PackIndex $index, string $oid, callable $externalBaseResolver): GitObject
    {
        $entry = $index->lookup($oid);
        if ($entry === null) {
            throw new \RuntimeException("Object id not found in pack index: {$oid}");
        }

        return $this->readObjectAtVerifiedOffset($index, $oid, $entry->packOffset, $externalBaseResolver);
    }

    /**
     * @param array<string,GitObject> $externalBases
     * @return array{type:string,size:int,numDeltas:int}
     */
    public function readObjectHeaderWithExternalBases(PackIndex $index, string $oid, array $externalBases): array
    {
        return $this->readObjectHeaderWithExternalBaseResolver($index, $oid, self::externalBaseHeaderResolver($externalBases));
    }

    /**
     * @param callable(string):(null|GitObject|array{type:string,size:int,numDeltas?:int}) $externalBaseHeaderResolver
     * @return array{type:string,size:int,numDeltas:int}
     */
    public function readObjectHeaderWithExternalBaseResolver(PackIndex $index, string $oid, callable $externalBaseHeaderResolver): array
    {
        $entry = $index->lookup($oid);
        if ($entry === null) {
            throw new \RuntimeException("Object id not found in pack index: {$oid}");
        }

        return $this->readObjectHeaderAtVerifiedOffset($index, $entry->packOffset, $externalBaseHeaderResolver);
    }

    /**
     * @param array<string,GitObject> $externalBases
     */
    public function repairThinPack(PackIndex $index, array $externalBases): PackBuildResult
    {
        $this->assertIndexMatchesPackData($index);

        $resolver = self::externalBaseResolver($externalBases);
        $indexEntries = $index->entries();
        usort($indexEntries, static fn (PackIndexEntry $a, PackIndexEntry $b): int => $a->packOffset <=> $b->packOffset);

        $objects = [];
        $emitted = [];
        foreach ($indexEntries as $indexEntry) {
            $packEntry = $this->entryAtOffset($indexEntry->packOffset, $this->nextOffset($index, $indexEntry->packOffset));
            if (
                $packEntry->kind === 'ref-delta'
                && $packEntry->baseObjectId !== null
                && $index->lookup($packEntry->baseObjectId) === null
                && !isset($emitted[$packEntry->baseObjectId])
            ) {
                $base = self::resolveExternalBase($packEntry->baseObjectId, $resolver);
                $objects[] = $base;
                $emitted[$base->oid($index->objectHash())] = true;
            }

            $object = $this->readObjectAtVerifiedOffset($index, $indexEntry->oid, $indexEntry->packOffset, $resolver);
            $objectId = $object->oid($index->objectHash());
            if (!isset($emitted[$objectId])) {
                $objects[] = $object;
                $emitted[$objectId] = true;
            }
        }

        return PackBuilder::buildWithOffsetDeltas($objects);
    }

    public function readObjectAtOffset(PackIndex $index, string $oid, int $packOffset): GitObject
    {
        $entry = $index->lookup($oid);
        if ($entry === null) {
            throw new \RuntimeException("Object id not found in pack index: {$oid}");
        }
        if ($entry->packOffset !== $packOffset) {
            throw new \RuntimeException('Multi-pack-index offset does not match pack index lookup');
        }

        return $this->readObjectAtVerifiedOffset($index, $oid, $packOffset);
    }

    private function readObjectAtVerifiedOffset(PackIndex $index, string $oid, int $packOffset, ?callable $externalBaseResolver = null): GitObject
    {
        $this->assertIndexMatchesPackData($index);

        $packEntry = $this->entryAtOffset($packOffset, $this->nextOffset($index, $packOffset));
        $object = $this->resolveEntry($index, $packEntry, 0, $externalBaseResolver);
        if ($object->oid($index->objectHash()) !== strtolower($oid)) {
            throw new \RuntimeException('Pack entry object id does not match index lookup');
        }

        return $object;
    }

    /**
     * @param null|callable(string):(null|GitObject|array{type:string,size:int,numDeltas?:int}) $externalBaseHeaderResolver
     * @return array{type:string,size:int,numDeltas:int}
     */
    private function readObjectHeaderAtVerifiedOffset(PackIndex $index, int $packOffset, ?callable $externalBaseHeaderResolver = null): array
    {
        $this->assertIndexMatchesPackData($index);

        return $this->resolveEntryHeader(
            $index,
            $this->entryMetadataAtOffset($packOffset, $this->nextOffset($index, $packOffset)),
            0,
            $externalBaseHeaderResolver,
        );
    }

    private function resolveEntry(PackIndex $index, PackDataEntry $entry, int $depth = 0, ?callable $externalBaseResolver = null): GitObject
    {
        if ($depth > 50) {
            throw new \RuntimeException('Pack delta chain is too deep');
        }
        if (!$entry->isDelta()) {
            return $entry->object();
        }

        if ($entry->kind === 'ofs-delta') {
            if ($entry->baseDistance === null || $entry->baseDistance <= 0) {
                throw new \RuntimeException('OFS_DELTA entry has an invalid base distance');
            }
            $baseOffset = $entry->packOffset - $entry->baseDistance;
            if ($baseOffset < self::HEADER_BYTES) {
                throw new \RuntimeException('OFS_DELTA base offset points before pack data');
            }
            $base = $this->resolveEntry(
                $index,
                $this->entryAtOffset($baseOffset, $this->nextOffset($index, $baseOffset)),
                $depth + 1,
                $externalBaseResolver
            );
        } elseif ($entry->kind === 'ref-delta') {
            if ($entry->baseObjectId === null) {
                throw new \RuntimeException('REF_DELTA entry is missing its base object id');
            }
            $baseIndexEntry = $index->lookup($entry->baseObjectId);
            if ($baseIndexEntry === null) {
                if ($externalBaseResolver === null) {
                    throw new \RuntimeException("REF_DELTA base object not found in pack index: {$entry->baseObjectId}");
                }
                $base = self::resolveExternalBase($entry->baseObjectId, $externalBaseResolver);
            } else {
                $base = $this->resolveEntry(
                    $index,
                    $this->entryAtOffset($baseIndexEntry->packOffset, $this->nextOffset($index, $baseIndexEntry->packOffset)),
                    $depth + 1,
                    $externalBaseResolver
                );
            }
        } else {
            throw new \RuntimeException("Unsupported delta entry kind: {$entry->kind}");
        }

        return new GitObject($base->type, self::applyDelta($base->body, $entry->data));
    }

    /**
     * @param array{kind:string,decompressedSize:int,packOffset:int,dataOffset:int,headerSize:int,baseDistance:?int,baseObjectId:?string,nextOffset:int} $entry
     * @param null|callable(string):(null|GitObject|array{type:string,size:int,numDeltas?:int}) $externalBaseHeaderResolver
     * @return array{type:string,size:int,numDeltas:int}
     */
    private function resolveEntryHeader(PackIndex $index, array $entry, int $depth = 0, ?callable $externalBaseHeaderResolver = null): array
    {
        if ($depth > 50) {
            throw new \RuntimeException('Pack delta chain is too deep');
        }
        if ($entry['kind'] !== 'ofs-delta' && $entry['kind'] !== 'ref-delta') {
            return [
                'type' => $entry['kind'],
                'size' => $entry['decompressedSize'],
                'numDeltas' => 0,
            ];
        }

        $resultSize = $this->decodeDeltaResultSizeForEntry($entry);
        if ($entry['kind'] === 'ofs-delta') {
            if ($entry['baseDistance'] === null || $entry['baseDistance'] <= 0) {
                throw new \RuntimeException('OFS_DELTA entry has an invalid base distance');
            }
            $baseOffset = $entry['packOffset'] - $entry['baseDistance'];
            if ($baseOffset < self::HEADER_BYTES) {
                throw new \RuntimeException('OFS_DELTA base offset points before pack data');
            }
            $baseHeader = $this->resolveEntryHeader(
                $index,
                $this->entryMetadataAtOffset($baseOffset, $this->nextOffset($index, $baseOffset)),
                $depth + 1,
                $externalBaseHeaderResolver,
            );
        } elseif ($entry['kind'] === 'ref-delta') {
            if ($entry['baseObjectId'] === null) {
                throw new \RuntimeException('REF_DELTA entry is missing its base object id');
            }
            $baseIndexEntry = $index->lookup($entry['baseObjectId']);
            if ($baseIndexEntry === null) {
                if ($externalBaseHeaderResolver === null) {
                    throw new \RuntimeException("REF_DELTA base object not found in pack index: {$entry['baseObjectId']}");
                }
                $baseHeader = self::resolveExternalBaseHeader($entry['baseObjectId'], $externalBaseHeaderResolver);
            } else {
                $baseHeader = $this->resolveEntryHeader(
                    $index,
                    $this->entryMetadataAtOffset($baseIndexEntry->packOffset, $this->nextOffset($index, $baseIndexEntry->packOffset)),
                    $depth + 1,
                    $externalBaseHeaderResolver,
                );
            }
        } else {
            throw new \RuntimeException("Unsupported delta entry kind: {$entry['kind']}");
        }

        return [
            'type' => $baseHeader['type'],
            'size' => $resultSize,
            'numDeltas' => $baseHeader['numDeltas'] + 1,
        ];
    }

    private static function applyDelta(string $base, string $delta): string
    {
        $cursor = 0;
        [$baseSize, $cursor] = self::readDeltaSize($delta, $cursor);
        [$resultSize, $cursor] = self::readDeltaSize($delta, $cursor);
        if ($baseSize !== strlen($base)) {
            throw new \RuntimeException("Delta base size mismatch: expected {$baseSize}, got " . strlen($base));
        }

        $result = '';
        while ($cursor < strlen($delta)) {
            $command = ord($delta[$cursor++]);
            if (($command & 0x80) !== 0) {
                $offset = 0;
                $size = 0;
                if (($command & 0x01) !== 0) {
                    $offset = self::readDeltaCommandByte($delta, $cursor);
                }
                if (($command & 0x02) !== 0) {
                    $offset |= self::readDeltaCommandByte($delta, $cursor) << 8;
                }
                if (($command & 0x04) !== 0) {
                    $offset |= self::readDeltaCommandByte($delta, $cursor) << 16;
                }
                if (($command & 0x08) !== 0) {
                    $offset |= self::readDeltaCommandByte($delta, $cursor) << 24;
                }
                if (($command & 0x10) !== 0) {
                    $size = self::readDeltaCommandByte($delta, $cursor);
                }
                if (($command & 0x20) !== 0) {
                    $size |= self::readDeltaCommandByte($delta, $cursor) << 8;
                }
                if (($command & 0x40) !== 0) {
                    $size |= self::readDeltaCommandByte($delta, $cursor) << 16;
                }
                if ($size === 0) {
                    $size = 0x10000;
                }
                if ($offset + $size > strlen($base)) {
                    throw new \RuntimeException('Delta copy range exceeds base object size');
                }
                if (strlen($result) + $size > $resultSize) {
                    throw new \RuntimeException('Delta copy exceeds declared result size');
                }
                $result .= substr($base, $offset, $size);
                continue;
            }

            if ($command === 0) {
                throw new \RuntimeException('Delta command 0 is reserved and invalid');
            }

            if ($cursor + $command > strlen($delta)) {
                throw new \RuntimeException('Delta insert data is truncated');
            }
            if (strlen($result) + $command > $resultSize) {
                throw new \RuntimeException('Delta insert exceeds declared result size');
            }
            $result .= substr($delta, $cursor, $command);
            $cursor += $command;
        }

        if (strlen($result) < $resultSize) {
            throw new \RuntimeException('Delta instructions produced fewer bytes than promised');
        }
        if (strlen($result) > $resultSize) {
            throw new \RuntimeException("Delta result size mismatch: expected {$resultSize}, got " . strlen($result));
        }

        return $result;
    }

    private static function inflateEntryData(string $compressed, int $expectedSize): string
    {
        if ($expectedSize < 0) {
            throw new \RuntimeException('Pack entry decompressed size cannot be negative');
        }

        $limit = $expectedSize === PHP_INT_MAX ? $expectedSize : $expectedSize + 1;
        $data = @zlib_decode($compressed, $limit);
        if ($data === false) {
            throw new \RuntimeException('Unable to inflate pack data entry to its declared size');
        }

        $actualSize = strlen($data);
        if ($actualSize !== $expectedSize) {
            throw new \RuntimeException("Pack entry decompressed size mismatch: expected {$expectedSize}, got {$actualSize}");
        }

        return $data;
    }

    private static function inflateDeltaHeaderPrefix(string $compressed, int $expectedSize): string
    {
        if ($expectedSize < 0) {
            throw new \RuntimeException('Pack entry decompressed size cannot be negative');
        }
        if ($expectedSize <= 20) {
            return self::inflateEntryData($compressed, $expectedSize);
        }
        if (!function_exists('inflate_init') || !function_exists('inflate_add')) {
            return substr(self::inflateEntryData($compressed, $expectedSize), 0, 20);
        }

        $context = @inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new \RuntimeException('Unable to initialize pack delta header inflater');
        }

        $prefix = '';
        $length = strlen($compressed);
        for ($index = 0; $index < $length && strlen($prefix) < 20; $index++) {
            $chunk = @inflate_add($context, $compressed[$index], ZLIB_SYNC_FLUSH);
            if ($chunk === false) {
                throw new \RuntimeException('Unable to inflate pack delta header prefix');
            }
            $prefix .= $chunk;
        }

        if (strlen($prefix) < 20) {
            throw new \RuntimeException('Pack entry decompressed to fewer bytes than declared in the entry header');
        }

        return substr($prefix, 0, 20);
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function readDeltaSize(string $delta, int $cursor): array
    {
        $shift = 0;
        $size = 0;
        $valueBits = PHP_INT_SIZE * 8 - 1;
        while ($cursor < strlen($delta)) {
            if ($shift > $valueBits) {
                throw new \RuntimeException('Delta header size uses too many bits');
            }
            $byte = ord($delta[$cursor++]);
            $value = $byte & 0x7f;
            if ($value !== 0 && ($shift >= $valueBits || $value > (PHP_INT_MAX >> $shift))) {
                throw new \RuntimeException('Delta header size exceeds platform integer range');
            }
            $size |= $value << $shift;
            if (($byte & 0x80) === 0) {
                return [$size, $cursor];
            }
            $shift += 7;
        }

        throw new \RuntimeException('Delta header size is truncated');
    }

    private static function readDeltaCommandByte(string $delta, int &$cursor): int
    {
        if ($cursor >= strlen($delta)) {
            throw new \RuntimeException('Delta copy instruction is truncated');
        }

        return ord($delta[$cursor++]);
    }

    private function nextOffset(PackIndex $index, int $packOffset): int
    {
        foreach ($index->sortedOffsets() as $candidate) {
            if ($candidate > $packOffset) {
                return $candidate;
            }
        }

        return $this->dataEndOffset();
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $chunk = substr($bytes, $offset, 4);
        if (strlen($chunk) !== 4) {
            throw new \InvalidArgumentException('Pack data ended while reading a 32-bit value');
        }

        return (int) unpack('N', $chunk)[1];
    }

    /**
     * @return array{kind:string,decompressedSize:int,packOffset:int,dataOffset:int,headerSize:int,baseDistance:?int,baseObjectId:?string,nextOffset:int}
     */
    private function entryMetadataAtOffset(int $packOffset, ?int $nextOffset = null): array
    {
        $dataEndOffset = $this->dataEndOffset();
        if ($packOffset < self::HEADER_BYTES || $packOffset >= $dataEndOffset) {
            throw new \InvalidArgumentException('Pack entry offset is outside the data section');
        }
        $nextOffset ??= $dataEndOffset;
        if ($nextOffset <= $packOffset || $nextOffset > $dataEndOffset) {
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
            $componentValue = $current & 0x7f;
            $component = self::shiftedPackEntrySizeComponent($componentValue, $shift);
            if ($component > PHP_INT_MAX - $size) {
                throw new \InvalidArgumentException('Pack entry size header overflowed while decoding');
            }
            $size += $component;
            $shift += 7;
        }
        if ($cursor - $packOffset !== self::canonicalPackEntryHeaderSize($size)) {
            throw new \InvalidArgumentException('Pack entry size header uses a non-canonical encoding');
        }

        $kind = self::TYPE_IDS[$typeId];
        $baseDistance = null;
        $baseObjectId = null;
        if ($kind === 'ofs-delta') {
            $baseDistance = self::readOffsetDeltaDistance($this->bytes, $cursor, $nextOffset);
        } elseif ($kind === 'ref-delta') {
            if ($cursor + $this->hashBytes > $nextOffset) {
                throw new \InvalidArgumentException('Ref-delta pack entry is missing its base object id');
            }
            $baseObjectId = bin2hex(substr($this->bytes, $cursor, $this->hashBytes));
            $cursor += $this->hashBytes;
        }

        return [
            'kind' => $kind,
            'decompressedSize' => $size,
            'packOffset' => $packOffset,
            'dataOffset' => $cursor,
            'headerSize' => $cursor - $packOffset,
            'baseDistance' => $baseDistance,
            'baseObjectId' => $baseObjectId,
            'nextOffset' => $nextOffset,
        ];
    }

    /**
     * @param array{dataOffset:int,nextOffset:int} $entry
     */
    private function compressedDataForEntryMetadata(array $entry): string
    {
        return substr($this->bytes, $entry['dataOffset'], $entry['nextOffset'] - $entry['dataOffset']);
    }

    private static function normalizeObjectHash(string $objectHash): string
    {
        $normalized = strtolower($objectHash);
        if (!isset(self::HASHES[$normalized])) {
            throw new \InvalidArgumentException("Unsupported pack data object hash: {$objectHash}");
        }

        return $normalized;
    }

    private function dataEndOffset(): int
    {
        return strlen($this->bytes) - $this->hashBytes;
    }

    private function assertIndexMatchesPackData(PackIndex $index): void
    {
        if ($index->objectHash() !== $this->hashName) {
            throw new \RuntimeException('Pack index object hash does not match pack data object hash');
        }
        if ($index->packChecksum() !== $this->checksum) {
            throw new \RuntimeException('Pack index checksum does not match pack data checksum');
        }
    }

    /**
     * @param array{decompressedSize:int,dataOffset:int,nextOffset:int} $entry
     */
    private function decodeDeltaResultSizeForEntry(array $entry): int
    {
        $prefix = self::inflateDeltaHeaderPrefix(
            $this->compressedDataForEntryMetadata($entry),
            $entry['decompressedSize'],
        );
        [, $cursor] = self::readDeltaSize($prefix, 0);
        [$resultSize] = self::readDeltaSize($prefix, $cursor);

        return $resultSize;
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
            if ($distance >= PHP_INT_MAX || $distance + 1 > (PHP_INT_MAX >> 7)) {
                throw new \InvalidArgumentException('Ofs-delta base distance overflowed while decoding');
            }
            $distance = (($distance + 1) << 7) | ($byte & 0x7f);
        }

        return $distance;
    }

    private static function shiftedPackEntrySizeComponent(int $value, int $shift): int
    {
        if ($value === 0) {
            return 0;
        }
        $valueBits = PHP_INT_SIZE * 8 - 1;
        if ($shift >= $valueBits || $value > (PHP_INT_MAX >> $shift)) {
            throw new \InvalidArgumentException('Pack entry size header overflowed while decoding');
        }

        return $value << $shift;
    }

    private static function canonicalPackEntryHeaderSize(int $size): int
    {
        $bytes = 1;
        $size >>= 4;
        while ($size !== 0) {
            $bytes++;
            $size >>= 7;
        }

        return $bytes;
    }

    /**
     * @param array<string,GitObject> $externalBases
     * @return callable(string):(?GitObject)
     */
    private static function externalBaseResolver(array $externalBases): callable
    {
        $normalized = [];
        foreach ($externalBases as $oid => $object) {
            if (!$object instanceof GitObject) {
                throw new \InvalidArgumentException('External pack bases must be GitObject instances keyed by object id');
            }
            $oid = strtolower((string) $oid);
            if (preg_match('/^[0-9a-f]{40}$/', $oid) !== 1) {
                throw new \InvalidArgumentException('External pack base keys must be SHA-1 object ids');
            }
            if ($object->oid() !== $oid) {
                throw new \InvalidArgumentException('External pack base object id does not match its key');
            }
            $normalized[$oid] = $object;
        }

        return static fn (string $baseOid): ?GitObject => $normalized[strtolower($baseOid)] ?? null;
    }

    /**
     * @param array<string,GitObject> $externalBases
     * @return callable(string):(null|array{type:string,size:int,numDeltas:int})
     */
    private static function externalBaseHeaderResolver(array $externalBases): callable
    {
        $normalized = [];
        foreach ($externalBases as $oid => $object) {
            if (!$object instanceof GitObject) {
                throw new \InvalidArgumentException('External pack base headers must be GitObject instances keyed by object id');
            }
            $oid = strtolower((string) $oid);
            if (preg_match('/^[0-9a-f]{40}$/', $oid) !== 1) {
                throw new \InvalidArgumentException('External pack base header keys must be SHA-1 object ids');
            }
            if ($object->oid() !== $oid) {
                throw new \InvalidArgumentException('External pack base header object id does not match its key');
            }
            $normalized[$oid] = [
                'type' => $object->type,
                'size' => strlen($object->body),
                'numDeltas' => 0,
            ];
        }

        return static fn (string $baseOid): ?array => $normalized[strtolower($baseOid)] ?? null;
    }

    /**
     * @param callable(string):(?GitObject) $externalBaseResolver
     */
    private static function resolveExternalBase(string $baseOid, callable $externalBaseResolver): GitObject
    {
        $base = $externalBaseResolver($baseOid);
        if ($base === null) {
            throw new \RuntimeException("REF_DELTA base object not found in pack index or external bases: {$baseOid}");
        }
        if (!$base instanceof GitObject) {
            throw new \RuntimeException('External REF_DELTA base resolver must return GitObject or null');
        }
        if ($base->oid() !== strtolower($baseOid)) {
            throw new \RuntimeException('External REF_DELTA base object id does not match requested base');
        }

        return $base;
    }

    /**
     * @param callable(string):(null|GitObject|array{type:string,size:int,numDeltas?:int}) $externalBaseHeaderResolver
     * @return array{type:string,size:int,numDeltas:int}
     */
    private static function resolveExternalBaseHeader(string $baseOid, callable $externalBaseHeaderResolver): array
    {
        $header = $externalBaseHeaderResolver($baseOid);
        if ($header === null) {
            throw new \RuntimeException("REF_DELTA base object not found in pack index or external base headers: {$baseOid}");
        }
        if ($header instanceof GitObject) {
            if ($header->oid() !== strtolower($baseOid)) {
                throw new \RuntimeException('External REF_DELTA base object id does not match requested base');
            }

            return [
                'type' => $header->type,
                'size' => strlen($header->body),
                'numDeltas' => 0,
            ];
        }
        if (!is_array($header)) {
            throw new \RuntimeException('External REF_DELTA base header resolver must return GitObject, header array, or null');
        }

        $type = $header['type'] ?? null;
        $size = $header['size'] ?? null;
        $numDeltas = $header['numDeltas'] ?? 0;
        if (!is_string($type) || !in_array($type, ['commit', 'tree', 'blob', 'tag'], true)) {
            throw new \RuntimeException('External REF_DELTA base header type is invalid');
        }
        if (!is_int($size) || $size < 0) {
            throw new \RuntimeException('External REF_DELTA base header size is invalid');
        }
        if (!is_int($numDeltas) || $numDeltas < 0) {
            throw new \RuntimeException('External REF_DELTA base header delta count is invalid');
        }

        return [
            'type' => $type,
            'size' => $size,
            'numDeltas' => $numDeltas,
        ];
    }
}
