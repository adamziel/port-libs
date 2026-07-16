<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackBuildResult
{
    /**
     * @param list<array{oid:string,type:string,size:int,offset:int,crc32:int,storage?:string,baseOid?:string,baseOffset?:int,baseDistance?:int,reused?:bool,sourceOffset?:int}> $entries
     */
    public function __construct(
        private readonly string $packBytes,
        private readonly string $indexBytes,
        private readonly string $packChecksum,
        private readonly string $indexChecksum,
        private readonly array $entries,
    ) {
        if (!str_starts_with($packBytes, 'PACK')) {
            throw new \InvalidArgumentException('Pack build result must contain pack data bytes');
        }
        if (!str_starts_with($indexBytes, "\xfftOc")) {
            throw new \InvalidArgumentException('Pack build result must contain v2 pack index bytes');
        }
        if (preg_match('/^[0-9a-f]{40}$/', $packChecksum) !== 1 || preg_match('/^[0-9a-f]{40}$/', $indexChecksum) !== 1) {
            throw new \InvalidArgumentException('Pack build checksums must be SHA-1 hex strings');
        }
        foreach ($entries as $entry) {
            if (
                !isset($entry['oid'], $entry['type'], $entry['size'], $entry['offset'], $entry['crc32'])
                || preg_match('/^[0-9a-f]{40}$/', $entry['oid']) !== 1
                || !in_array($entry['type'], ['commit', 'tree', 'blob', 'tag'], true)
                || $entry['size'] < 0
                || $entry['offset'] < 0
                || $entry['crc32'] < 0
                || (isset($entry['storage']) && !in_array($entry['storage'], ['whole', 'ref-delta', 'ofs-delta'], true))
                || (isset($entry['baseOid']) && preg_match('/^[0-9a-f]{40}$/', $entry['baseOid']) !== 1)
                || (isset($entry['baseOffset']) && $entry['baseOffset'] < 0)
                || (isset($entry['baseDistance']) && $entry['baseDistance'] <= 0)
                || (isset($entry['sourceOffset']) && $entry['sourceOffset'] < 0)
                || (isset($entry['reused']) && !is_bool($entry['reused']))
            ) {
                throw new \InvalidArgumentException('Pack build entries must contain valid object metadata');
            }
            if (($entry['storage'] ?? 'whole') === 'ref-delta' && !isset($entry['baseOid'])) {
                throw new \InvalidArgumentException('Pack delta entries must include a base object id');
            }
            if (($entry['storage'] ?? 'whole') === 'ofs-delta' && (!isset($entry['baseOffset'], $entry['baseDistance']))) {
                throw new \InvalidArgumentException('Pack offset-delta entries must include base offset metadata');
            }
        }
    }

    public function packBytes(): string
    {
        return $this->packBytes;
    }

    public function indexBytes(): string
    {
        return $this->indexBytes;
    }

    public function packChecksum(): string
    {
        return $this->packChecksum;
    }

    public function indexChecksum(): string
    {
        return $this->indexChecksum;
    }

    /**
     * @return list<array{oid:string,type:string,size:int,offset:int,crc32:int,storage?:string,baseOid?:string,baseOffset?:int,baseDistance?:int,reused?:bool,sourceOffset?:int}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function hasDeltaEntries(): bool
    {
        foreach ($this->entries as $entry) {
            if (in_array($entry['storage'] ?? 'whole', ['ref-delta', 'ofs-delta'], true)) {
                return true;
            }
        }

        return false;
    }

    public function isThin(): bool
    {
        $contained = [];
        foreach ($this->entries as $entry) {
            $contained[$entry['oid']] = true;
        }
        foreach ($this->entries as $entry) {
            if (isset($entry['baseOid']) && !isset($contained[$entry['baseOid']])) {
                return true;
            }
        }

        return false;
    }
}
