<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackBuildResult
{
    /**
     * @param list<array{oid:string,type:string,size:int,offset:int,crc32:int}> $entries
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
            ) {
                throw new \InvalidArgumentException('Pack build entries must contain valid object metadata');
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
     * @return list<array{oid:string,type:string,size:int,offset:int,crc32:int}>
     */
    public function entries(): array
    {
        return $this->entries;
    }
}
