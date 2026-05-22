<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackBuilder
{
    private const VERSION = 2;
    private const FANOUT_ENTRIES = 256;
    private const LARGE_OFFSET_FLAG = 0x80000000;

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
        $pack = 'PACK' . pack('N2', self::VERSION, count($objects));
        $entries = [];

        foreach ($objects as $object) {
            if (!$object instanceof GitObject) {
                throw new \InvalidArgumentException('PackBuilder expects GitObject instances');
            }
            if (!isset(self::TYPE_IDS[$object->type])) {
                throw new \InvalidArgumentException("PackBuilder cannot encode object type {$object->type}");
            }

            $offset = strlen($pack);
            $entryBytes = self::encodeEntryHeader(self::TYPE_IDS[$object->type], strlen($object->body))
                . self::deflate($object->body);
            $pack .= $entryBytes;

            $entries[] = [
                'oid' => $object->oid(),
                'type' => $object->type,
                'size' => strlen($object->body),
                'offset' => $offset,
                'crc32' => hexdec(hash('crc32b', $entryBytes)),
            ];
        }

        $packChecksum = hash('sha1', $pack);
        $pack .= hex2bin($packChecksum);
        $indexBytes = self::buildIndexBytes($entries, $packChecksum);
        $indexChecksum = bin2hex(substr($indexBytes, -20));

        return new PackBuildResult($pack, $indexBytes, $packChecksum, $indexChecksum, $entries);
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

    private static function deflate(string $body): string
    {
        $compressed = gzcompress($body);
        if ($compressed === false) {
            throw new \RuntimeException('PackBuilder could not deflate object body');
        }

        return $compressed;
    }

    /**
     * @param list<array{oid:string,type:string,size:int,offset:int,crc32:int}> $entries
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
