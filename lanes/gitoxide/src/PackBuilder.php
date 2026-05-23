<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackBuilder
{
    private const VERSION = 2;
    private const FANOUT_ENTRIES = 256;
    private const LARGE_OFFSET_FLAG = 0x80000000;
    private const OFS_DELTA_TYPE_ID = 6;
    private const REF_DELTA_TYPE_ID = 7;
    private const MAX_DELTA_INSERT = 127;
    private const MAX_DELTA_COPY = 0x10000;

    private const TYPE_IDS = [
        'commit' => 1,
        'tree' => 2,
        'blob' => 3,
        'tag' => 4,
    ];

    /**
     * @param list<GitObject> $objects
     */
    public static function build(array $objects): PackBuildResult
    {
        return self::buildInternal($objects, [], false);
    }

    /**
     * @param list<GitObject> $objects Objects to include in the pack.
     * @param list<GitObject> $baseObjects Objects the receiver already has; using them produces a thin pack.
     */
    public static function buildWithRefDeltas(array $objects, array $baseObjects = [], ?int $maxBaseCandidates = null): PackBuildResult
    {
        self::assertDeltaBaseCandidateLimit($maxBaseCandidates);

        return self::buildInternal($objects, $baseObjects, true, $maxBaseCandidates);
    }

    /**
     * @param list<GitObject> $objects Objects to include in the pack, with later objects allowed to delta against earlier ones.
     */
    public static function buildWithOffsetDeltas(array $objects, ?int $maxBaseCandidates = null): PackBuildResult
    {
        self::assertDeltaBaseCandidateLimit($maxBaseCandidates);

        $pack = 'PACK' . pack('N2', self::VERSION, count($objects));
        $entries = [];
        $availableBases = [];

        foreach ($objects as $object) {
            self::assertGitObject($object);

            $offset = strlen($pack);
            $encoded = self::encodeBestOffsetEntry($object, $availableBases, $offset, $maxBaseCandidates);
            $entryBytes = $encoded['bytes'];
            $pack .= $entryBytes;

            $entry = [
                'oid' => $object->oid(),
                'type' => $object->type,
                'size' => strlen($object->body),
                'offset' => $offset,
                'crc32' => hexdec(hash('crc32b', $entryBytes)),
                'storage' => $encoded['storage'],
            ];
            if ($encoded['baseOid'] !== null) {
                $entry['baseOid'] = $encoded['baseOid'];
            }
            if ($encoded['baseOffset'] !== null) {
                $entry['baseOffset'] = $encoded['baseOffset'];
            }
            if ($encoded['baseDistance'] !== null) {
                $entry['baseDistance'] = $encoded['baseDistance'];
            }

            $entries[] = $entry;
            $availableBases[$object->oid()] = ['object' => $object, 'offset' => $offset];
        }

        return self::finalizePack($pack, $entries);
    }

    /**
     * Rebuild a pack from objects that already exist in a source pack/index.
     *
     * The output follows the upstream pack-copy boundary: whole entries keep their compressed payloads, OFS_DELTA
     * entries keep their compressed delta payloads when their base is selected, and missing delta bases are only kept
     * as REF_DELTA entries when the caller explicitly asks for a thin transit pack.
     *
     * @param list<string> $oids
     */
    public static function buildFromExistingPack(PackData $sourcePack, PackIndex $sourceIndex, array $oids, bool $allowThinPack = false): PackBuildResult
    {
        if ($sourceIndex->packChecksum() !== $sourcePack->checksum()) {
            throw new \RuntimeException('Source pack index checksum does not match source pack data checksum');
        }

        $sourceEntries = [];
        $seen = [];
        foreach ($oids as $oid) {
            self::assertObjectId($oid);
            $oid = strtolower((string) $oid);
            if (isset($seen[$oid])) {
                throw new \InvalidArgumentException("PackBuilder cannot copy duplicate object id {$oid}");
            }
            $seen[$oid] = true;

            $entry = $sourceIndex->lookup($oid);
            if ($entry === null) {
                throw new \RuntimeException("Object id not found in source pack index: {$oid}");
            }
            $sourceEntries[] = $entry;
        }

        usort(
            $sourceEntries,
            static fn (PackIndexEntry $a, PackIndexEntry $b): int => $a->packOffset <=> $b->packOffset ?: strcmp($a->oid, $b->oid)
        );

        $selectedSourceOffsets = [];
        foreach ($sourceEntries as $sourceEntry) {
            $selectedSourceOffsets[$sourceEntry->packOffset] = $sourceEntry->oid;
        }

        $pack = 'PACK' . pack('N2', self::VERSION, count($sourceEntries));
        $entries = [];
        $writtenSourceOffsets = [];

        foreach ($sourceEntries as $sourceIndexEntry) {
            $offset = strlen($pack);
            $sourceEntry = $sourcePack->entryAtIndexOffset($sourceIndex, $sourceIndexEntry->packOffset);
            $object = $sourcePack->readObject($sourceIndex, $sourceIndexEntry->oid);
            $compressedData = $sourcePack->compressedDataAtIndexOffset($sourceIndex, $sourceIndexEntry->packOffset);
            $encoded = self::encodeCopiedPackEntry(
                $sourceEntry,
                $compressedData,
                $object,
                $sourcePack,
                $sourceIndex,
                $selectedSourceOffsets,
                $writtenSourceOffsets,
                $offset,
                $allowThinPack
            );
            $entryBytes = $encoded['bytes'];
            $pack .= $entryBytes;

            $entry = [
                'oid' => $object->oid(),
                'type' => $object->type,
                'size' => strlen($object->body),
                'offset' => $offset,
                'crc32' => hexdec(hash('crc32b', $entryBytes)),
                'storage' => $encoded['storage'],
                'reused' => $encoded['reused'],
                'sourceOffset' => $sourceIndexEntry->packOffset,
            ];
            if ($encoded['baseOid'] !== null) {
                $entry['baseOid'] = $encoded['baseOid'];
            }
            if ($encoded['baseOffset'] !== null) {
                $entry['baseOffset'] = $encoded['baseOffset'];
            }
            if ($encoded['baseDistance'] !== null) {
                $entry['baseDistance'] = $encoded['baseDistance'];
            }

            $entries[] = $entry;
            $writtenSourceOffsets[$sourceIndexEntry->packOffset] = $offset;
        }

        return self::finalizePack($pack, $entries);
    }

    /**
     * @param list<GitObject> $objects
     * @param list<GitObject> $baseObjects
     */
    private static function buildInternal(array $objects, array $baseObjects, bool $allowRefDeltas, ?int $maxBaseCandidates = null): PackBuildResult
    {
        $pack = 'PACK' . pack('N2', self::VERSION, count($objects));
        $entries = [];
        $availableBases = [];

        foreach ($baseObjects as $baseObject) {
            self::assertGitObject($baseObject);
            $availableBases[$baseObject->oid()] = $baseObject;
        }

        foreach ($objects as $object) {
            self::assertGitObject($object);

            $offset = strlen($pack);
            $encoded = $allowRefDeltas ? self::encodeBestEntry($object, $availableBases, $maxBaseCandidates) : self::encodeWholeEntry($object);
            $entryBytes = $encoded['bytes'];
            $pack .= $entryBytes;

            $entry = [
                'oid' => $object->oid(),
                'type' => $object->type,
                'size' => strlen($object->body),
                'offset' => $offset,
                'crc32' => hexdec(hash('crc32b', $entryBytes)),
                'storage' => $encoded['storage'],
            ];
            if ($encoded['baseOid'] !== null) {
                $entry['baseOid'] = $encoded['baseOid'];
            }

            $entries[] = $entry;
            $availableBases[$object->oid()] = $object;
        }

        return self::finalizePack($pack, $entries);
    }

    private static function assertGitObject(mixed $object): void
    {
        if (!$object instanceof GitObject) {
            throw new \InvalidArgumentException('PackBuilder expects GitObject instances');
        }
        if (!isset(self::TYPE_IDS[$object->type])) {
            throw new \InvalidArgumentException("PackBuilder cannot encode object type {$object->type}");
        }
    }

    private static function assertDeltaBaseCandidateLimit(?int $maxBaseCandidates): void
    {
        if ($maxBaseCandidates !== null && $maxBaseCandidates < 0) {
            throw new \InvalidArgumentException('Pack delta base candidate limit cannot be negative');
        }
    }

    private static function assertObjectId(mixed $oid): void
    {
        if (!is_string($oid) || preg_match('/^[0-9a-fA-F]{40}$/', $oid) !== 1) {
            throw new \InvalidArgumentException('PackBuilder object ids must be 40-character SHA-1 hex strings');
        }
    }

    /**
     * @param array<string,GitObject> $availableBases
     * @return array{bytes:string,storage:string,baseOid:?string}
     */
    private static function encodeBestEntry(GitObject $object, array $availableBases, ?int $maxBaseCandidates): array
    {
        $best = self::encodeWholeEntry($object);
        $bestLength = strlen($best['bytes']);

        foreach (self::deltaBaseCandidates($object, $availableBases, $maxBaseCandidates) as $baseOid => $baseObject) {
            $delta = self::encodeDelta($baseObject->body, $object->body);
            $baseOidBytes = hex2bin($baseOid);
            if ($baseOidBytes === false) {
                throw new \RuntimeException('PackBuilder could not decode delta base object id');
            }
            $bytes = self::encodeEntryHeader(self::REF_DELTA_TYPE_ID, strlen($delta))
                . $baseOidBytes
                . self::deflate($delta);

            if (strlen($bytes) < $bestLength) {
                $best = [
                    'bytes' => $bytes,
                    'storage' => 'ref-delta',
                    'baseOid' => $baseOid,
                ];
                $bestLength = strlen($bytes);
            }
        }

        return $best;
    }

    /**
     * @param array<string,array{object:GitObject,offset:int}> $availableBases
     * @return array{bytes:string,storage:string,baseOid:?string,baseOffset:?int,baseDistance:?int}
     */
    private static function encodeBestOffsetEntry(GitObject $object, array $availableBases, int $offset, ?int $maxBaseCandidates): array
    {
        $best = self::encodeWholeEntry($object) + ['baseOffset' => null, 'baseDistance' => null];
        $bestLength = strlen($best['bytes']);

        foreach (self::deltaOffsetBaseCandidates($object, $availableBases, $maxBaseCandidates) as $baseOid => $base) {
            $baseObject = $base['object'];
            $baseOffset = $base['offset'];

            $baseDistance = $offset - $baseOffset;
            if ($baseDistance <= 0) {
                continue;
            }

            $delta = self::encodeDelta($baseObject->body, $object->body);
            $bytes = self::encodeEntryHeader(self::OFS_DELTA_TYPE_ID, strlen($delta))
                . self::encodeOffsetDeltaDistance($baseDistance)
                . self::deflate($delta);

            if (strlen($bytes) < $bestLength) {
                $best = [
                    'bytes' => $bytes,
                    'storage' => 'ofs-delta',
                    'baseOid' => $baseOid,
                    'baseOffset' => $baseOffset,
                    'baseDistance' => $baseDistance,
                ];
                $bestLength = strlen($bytes);
            }
        }

        return $best;
    }

    /**
     * @param array<int,string> $selectedSourceOffsets
     * @param array<int,int> $writtenSourceOffsets
     * @return array{bytes:string,storage:string,baseOid:?string,baseOffset:?int,baseDistance:?int,reused:bool}
     */
    private static function encodeCopiedPackEntry(
        PackDataEntry $sourceEntry,
        string $compressedData,
        GitObject $object,
        PackData $sourcePack,
        PackIndex $sourceIndex,
        array $selectedSourceOffsets,
        array $writtenSourceOffsets,
        int $offset,
        bool $allowThinPack
    ): array {
        if (isset(self::TYPE_IDS[$sourceEntry->kind])) {
            return [
                'bytes' => self::encodeEntryHeader(self::TYPE_IDS[$sourceEntry->kind], $sourceEntry->decompressedSize) . $compressedData,
                'storage' => 'whole',
                'baseOid' => null,
                'baseOffset' => null,
                'baseDistance' => null,
                'reused' => true,
            ];
        }

        if ($sourceEntry->kind === 'ref-delta') {
            return self::encodeWholeEntry($object) + [
                'baseOffset' => null,
                'baseDistance' => null,
                'reused' => false,
            ];
        }

        if ($sourceEntry->kind !== 'ofs-delta' || $sourceEntry->baseDistance === null) {
            return self::encodeWholeEntry($object) + [
                'baseOffset' => null,
                'baseDistance' => null,
                'reused' => false,
            ];
        }

        $sourceBaseOffset = $sourceEntry->packOffset - $sourceEntry->baseDistance;
        $baseOid = $selectedSourceOffsets[$sourceBaseOffset] ?? $sourcePack->objectIdForOffset($sourceIndex, $sourceBaseOffset);
        if ($baseOid !== null && isset($writtenSourceOffsets[$sourceBaseOffset])) {
            $baseOffset = $writtenSourceOffsets[$sourceBaseOffset];
            $baseDistance = $offset - $baseOffset;

            return [
                'bytes' => self::encodeEntryHeader(self::OFS_DELTA_TYPE_ID, $sourceEntry->decompressedSize)
                    . self::encodeOffsetDeltaDistance($baseDistance)
                    . $compressedData,
                'storage' => 'ofs-delta',
                'baseOid' => $baseOid,
                'baseOffset' => $baseOffset,
                'baseDistance' => $baseDistance,
                'reused' => true,
            ];
        }

        if ($allowThinPack && $baseOid !== null) {
            $baseOidBytes = hex2bin($baseOid);
            if ($baseOidBytes === false) {
                throw new \RuntimeException('PackBuilder could not decode copied delta base object id');
            }

            return [
                'bytes' => self::encodeEntryHeader(self::REF_DELTA_TYPE_ID, $sourceEntry->decompressedSize)
                    . $baseOidBytes
                    . $compressedData,
                'storage' => 'ref-delta',
                'baseOid' => $baseOid,
                'baseOffset' => null,
                'baseDistance' => null,
                'reused' => true,
            ];
        }

        return self::encodeWholeEntry($object) + [
            'baseOffset' => null,
            'baseDistance' => null,
            'reused' => false,
        ];
    }

    /**
     * @param array<string,GitObject> $availableBases
     * @return array<string,GitObject>
     */
    private static function deltaBaseCandidates(GitObject $object, array $availableBases, ?int $maxBaseCandidates): array
    {
        $candidates = [];
        foreach ($availableBases as $baseOid => $baseObject) {
            if ($baseObject->type !== $object->type || $baseObject->oid() === $object->oid()) {
                continue;
            }
            $candidates[(string) $baseOid] = $baseObject;
        }

        return self::limitDeltaBaseCandidates($candidates, $maxBaseCandidates);
    }

    /**
     * @param array<string,array{object:GitObject,offset:int}> $availableBases
     * @return array<string,array{object:GitObject,offset:int}>
     */
    private static function deltaOffsetBaseCandidates(GitObject $object, array $availableBases, ?int $maxBaseCandidates): array
    {
        $candidates = [];
        foreach ($availableBases as $baseOid => $base) {
            $baseObject = $base['object'];
            if ($baseObject->type !== $object->type || $baseObject->oid() === $object->oid()) {
                continue;
            }
            $candidates[(string) $baseOid] = $base;
        }

        return self::limitDeltaBaseCandidates($candidates, $maxBaseCandidates);
    }

    /**
     * @template T
     * @param array<string,T> $candidates
     * @return array<string,T>
     */
    private static function limitDeltaBaseCandidates(array $candidates, ?int $maxBaseCandidates): array
    {
        if ($maxBaseCandidates === null || count($candidates) <= $maxBaseCandidates) {
            return $candidates;
        }
        if ($maxBaseCandidates === 0) {
            return [];
        }

        return array_slice($candidates, -$maxBaseCandidates, $maxBaseCandidates, true);
    }

    /**
     * @return array{bytes:string,storage:string,baseOid:?string}
     */
    private static function encodeWholeEntry(GitObject $object): array
    {
        return [
            'bytes' => self::encodeEntryHeader(self::TYPE_IDS[$object->type], strlen($object->body))
                . self::deflate($object->body),
            'storage' => 'whole',
            'baseOid' => null,
        ];
    }

    private static function encodeEntryHeader(int $typeId, int $size): string
    {
        if ($size < 0) {
            throw new \InvalidArgumentException('Pack object size cannot be negative');
        }

        $first = ($typeId << 4) | ($size & 0x0f);
        $size >>= 4;
        if ($size !== 0) {
            $first |= 0x80;
        }

        $bytes = chr($first);
        while ($size !== 0) {
            $byte = $size & 0x7f;
            $size >>= 7;
            if ($size !== 0) {
                $byte |= 0x80;
            }
            $bytes .= chr($byte);
        }

        return $bytes;
    }

    private static function encodeOffsetDeltaDistance(int $distance): string
    {
        if ($distance <= 0) {
            throw new \InvalidArgumentException('OFS_DELTA base distance must be greater than zero');
        }

        $buffer = array_fill(0, 10, 0);
        $index = 9;
        $buffer[$index] = $distance & 0x7f;
        while (true) {
            $distance >>= 7;
            if ($distance === 0) {
                break;
            }
            $distance--;
            $index--;
            if ($index < 0) {
                throw new \InvalidArgumentException('OFS_DELTA base distance is too large');
            }
            $buffer[$index] = 0x80 | ($distance & 0x7f);
        }

        $bytes = '';
        for (; $index < 10; $index++) {
            $bytes .= chr($buffer[$index]);
        }

        return $bytes;
    }

    private static function deflate(string $body): string
    {
        $compressed = gzcompress($body);
        if ($compressed === false) {
            throw new \RuntimeException('PackBuilder could not deflate object body');
        }

        return $compressed;
    }

    private static function encodeDelta(string $base, string $target): string
    {
        $prefix = self::commonPrefixLength($base, $target);
        $suffix = self::commonSuffixLength($base, $target, $prefix);

        $delta = self::encodeDeltaSize(strlen($base)) . self::encodeDeltaSize(strlen($target));
        if ($prefix > 0) {
            $delta .= self::encodeDeltaCopy(0, $prefix);
        }

        $insertLength = strlen($target) - $prefix - $suffix;
        if ($insertLength > 0) {
            $delta .= self::encodeDeltaInsert(substr($target, $prefix, $insertLength));
        }

        if ($suffix > 0) {
            $delta .= self::encodeDeltaCopy(strlen($base) - $suffix, $suffix);
        }

        return $delta;
    }

    private static function commonPrefixLength(string $a, string $b): int
    {
        $max = min(strlen($a), strlen($b));
        for ($index = 0; $index < $max; $index++) {
            if ($a[$index] !== $b[$index]) {
                return $index;
            }
        }

        return $max;
    }

    private static function commonSuffixLength(string $a, string $b, int $prefixLength): int
    {
        $max = min(strlen($a), strlen($b)) - $prefixLength;
        for ($index = 0; $index < $max; $index++) {
            if ($a[strlen($a) - 1 - $index] !== $b[strlen($b) - 1 - $index]) {
                return $index;
            }
        }

        return $max;
    }

    private static function encodeDeltaSize(int $size): string
    {
        if ($size < 0) {
            throw new \InvalidArgumentException('Delta size cannot be negative');
        }

        $bytes = '';
        do {
            $byte = $size & 0x7f;
            $size >>= 7;
            if ($size !== 0) {
                $byte |= 0x80;
            }
            $bytes .= chr($byte);
        } while ($size !== 0);

        return $bytes;
    }

    private static function encodeDeltaCopy(int $offset, int $size): string
    {
        if ($offset < 0 || $size < 0) {
            throw new \InvalidArgumentException('Delta copy offset and size cannot be negative');
        }

        $bytes = '';
        while ($size > 0) {
            $chunk = min($size, self::MAX_DELTA_COPY);
            $bytes .= self::encodeDeltaCopyChunk($offset, $chunk);
            $offset += $chunk;
            $size -= $chunk;
        }

        return $bytes;
    }

    private static function encodeDeltaCopyChunk(int $offset, int $size): string
    {
        $command = 0x80;
        $payload = '';
        for ($shift = 0; $shift <= 24; $shift += 8) {
            $byte = ($offset >> $shift) & 0xff;
            if ($byte !== 0) {
                $command |= 1 << intdiv($shift, 8);
                $payload .= chr($byte);
            }
        }

        if ($size !== self::MAX_DELTA_COPY) {
            for ($shift = 0; $shift <= 16; $shift += 8) {
                $byte = ($size >> $shift) & 0xff;
                if ($byte !== 0) {
                    $command |= 0x10 << intdiv($shift, 8);
                    $payload .= chr($byte);
                }
            }
        }

        return chr($command) . $payload;
    }

    private static function encodeDeltaInsert(string $bytes): string
    {
        $encoded = '';
        for ($offset = 0; $offset < strlen($bytes); $offset += self::MAX_DELTA_INSERT) {
            $chunk = substr($bytes, $offset, self::MAX_DELTA_INSERT);
            $encoded .= chr(strlen($chunk)) . $chunk;
        }

        return $encoded;
    }

    /**
     * @param list<array{oid:string,type:string,size:int,offset:int,crc32:int,storage?:string,baseOid?:string}> $entries
     */
    private static function buildIndexBytes(array $entries, string $packChecksum): string
    {
        $sorted = $entries;
        usort($sorted, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));

        $fanout = array_fill(0, self::FANOUT_ENTRIES, 0);
        foreach ($sorted as $entry) {
            $fanout[hexdec(substr($entry['oid'], 0, 2))]++;
        }
        $running = 0;
        foreach ($fanout as $index => $count) {
            $running += $count;
            $fanout[$index] = $running;
        }

        $largeOffsets = [];
        $offsetWords = [];
        foreach ($sorted as $entry) {
            if ($entry['offset'] < self::LARGE_OFFSET_FLAG) {
                $offsetWords[] = $entry['offset'];
                continue;
            }

            $largeIndex = count($largeOffsets);
            $largeOffsets[] = $entry['offset'];
            $offsetWords[] = self::LARGE_OFFSET_FLAG | $largeIndex;
        }

        $bytes = "\xfftOc" . pack('N', 2);
        foreach ($fanout as $count) {
            $bytes .= pack('N', $count);
        }
        foreach ($sorted as $entry) {
            $oidBytes = hex2bin($entry['oid']);
            if ($oidBytes === false) {
                throw new \RuntimeException('PackBuilder could not decode object id for index');
            }
            $bytes .= $oidBytes;
        }
        foreach ($sorted as $entry) {
            $bytes .= pack('N', $entry['crc32']);
        }
        foreach ($offsetWords as $offsetWord) {
            $bytes .= pack('N', $offsetWord);
        }
        foreach ($largeOffsets as $offset) {
            $bytes .= self::packUInt64($offset);
        }

        $packChecksumBytes = hex2bin($packChecksum);
        if ($packChecksumBytes === false) {
            throw new \RuntimeException('PackBuilder could not decode pack checksum');
        }
        $bytes .= $packChecksumBytes;

        return $bytes . hex2bin(hash('sha1', $bytes));
    }

    /**
     * @param list<array{oid:string,type:string,size:int,offset:int,crc32:int,storage?:string,baseOid?:string,baseOffset?:int,baseDistance?:int}> $entries
     */
    private static function finalizePack(string $pack, array $entries): PackBuildResult
    {
        $packChecksum = hash('sha1', $pack);
        $pack .= hex2bin($packChecksum);
        $indexBytes = self::buildIndexBytes($entries, $packChecksum);
        $indexChecksum = bin2hex(substr($indexBytes, -20));

        return new PackBuildResult($pack, $indexBytes, $packChecksum, $indexChecksum, $entries);
    }

    private static function packUInt64(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Pack offset cannot be negative');
        }

        $high = intdiv($value, 4294967296);
        $low = $value % 4294967296;
        if ($high > 0x7fffffff) {
            throw new \InvalidArgumentException('Pack offset exceeds this PHP integer platform');
        }

        return pack('N2', $high, $low);
    }
}
