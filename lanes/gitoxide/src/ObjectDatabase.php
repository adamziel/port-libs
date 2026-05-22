<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ObjectDatabase
{
    public const ORDER_PACK_LEXICOGRAPHICAL_THEN_LOOSE_LEXICOGRAPHICAL = 'pack-lexicographical-then-loose-lexicographical';
    public const ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL = 'pack-offset-then-loose-lexicographical';

    /**
     * @var null|list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string}>
     */
    private ?array $packs = null;
    private readonly LooseObjectStore $loose;

    public function __construct(private readonly string $gitDirectory)
    {
        $this->loose = new LooseObjectStore($gitDirectory);
    }

    public function contains(string $oid): bool
    {
        self::assertObjectId($oid);
        $oid = strtolower($oid);

        foreach ($this->packBundles() as $bundle) {
            if ($bundle['index']->lookup($oid) !== null) {
                return true;
            }
        }

        return $this->loose->contains($oid);
    }

    public function read(string $oid): GitObject
    {
        self::assertObjectId($oid);
        $oid = strtolower($oid);

        foreach ($this->packBundles() as $bundle) {
            if ($bundle['index']->lookup($oid) !== null) {
                return $bundle['data']->readObject($bundle['index'], $oid);
            }
        }

        $object = $this->loose->tryRead($oid);
        if ($object !== null) {
            return $object;
        }

        throw new \RuntimeException("Object not found in database: {$oid}");
    }

    public function packedObjectCount(): int
    {
        $count = 0;
        foreach ($this->packBundles() as $bundle) {
            $count += $bundle['index']->count();
        }

        return $count;
    }

    /**
     * @return array{status:'missing'}|array{status:'found',oid:string}|array{status:'ambiguous',matches:list<string>}
     */
    public function lookupPrefix(string $prefix): array
    {
        $prefix = strtolower($prefix);
        if (preg_match('/^[0-9a-f]{4,40}$/', $prefix) !== 1) {
            throw new \InvalidArgumentException('Lookup prefix must be 4 to 40 hexadecimal characters');
        }

        $matches = [];
        foreach ($this->packBundles() as $bundle) {
            $result = $bundle['index']->lookupPrefix($prefix);
            if ($result['status'] === 'found') {
                $matches[$result['entry']->oid] = true;
            } elseif ($result['status'] === 'ambiguous') {
                foreach ($result['matches'] as $entryIndex) {
                    $matches[$bundle['index']->entryAt($entryIndex)->oid] = true;
                }
            }
        }

        foreach ($this->loose->objectIds() as $oid) {
            if (str_starts_with($oid, $prefix)) {
                $matches[$oid] = true;
            }
        }

        $oids = array_keys($matches);
        sort($oids, SORT_STRING);
        if ($oids === []) {
            return ['status' => 'missing'];
        }
        if (count($oids) > 1) {
            return ['status' => 'ambiguous', 'matches' => $oids];
        }

        return ['status' => 'found', 'oid' => $oids[0]];
    }

    /**
     * @return list<string>
     */
    public function objectIds(string $ordering = self::ORDER_PACK_LEXICOGRAPHICAL_THEN_LOOSE_LEXICOGRAPHICAL): array
    {
        if (!in_array($ordering, [
            self::ORDER_PACK_LEXICOGRAPHICAL_THEN_LOOSE_LEXICOGRAPHICAL,
            self::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported object iteration ordering: {$ordering}");
        }

        $ids = [];
        foreach ($this->packBundles() as $bundle) {
            $entries = $bundle['index']->entries();
            if ($ordering === self::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL) {
                usort(
                    $entries,
                    static fn (PackIndexEntry $a, PackIndexEntry $b): int => $a->packOffset <=> $b->packOffset ?: strcmp($a->oid, $b->oid)
                );
            }
            foreach ($entries as $entry) {
                $ids[] = $entry->oid;
            }
        }

        return array_merge($ids, $this->loose->objectIds());
    }

    /**
     * @return list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string}>
     */
    private function packBundles(): array
    {
        if ($this->packs !== null) {
            return $this->packs;
        }

        $packDirectory = rtrim($this->gitDirectory, '/') . '/objects/pack';
        $indexPaths = is_dir($packDirectory) ? glob($packDirectory . '/*.idx') ?: [] : [];
        sort($indexPaths, SORT_STRING);

        $this->packs = [];
        foreach ($indexPaths as $indexPath) {
            $packPath = substr($indexPath, 0, -4) . '.pack';
            if (!is_file($packPath)) {
                throw new \RuntimeException("Pack data file not found for index: {$indexPath}");
            }

            $index = PackIndex::open($indexPath);
            $data = PackData::open($packPath);
            $index->verifyChecksum();
            $data->verifyChecksum();
            if ($index->packChecksum() !== $data->checksum()) {
                throw new \RuntimeException("Pack index checksum does not match pack data: {$indexPath}");
            }

            $this->packs[] = [
                'index' => $index,
                'data' => $data,
                'indexPath' => $indexPath,
                'packPath' => $packPath,
            ];
        }

        return $this->packs;
    }

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Object id must be a 40-character SHA-1 hex string');
        }
    }
}
