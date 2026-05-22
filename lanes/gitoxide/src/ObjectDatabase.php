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
    /**
     * @var null|list<string>
     */
    private ?array $objectDirectories = null;
    /**
     * @var null|list<LooseObjectStore>
     */
    private ?array $looseStores = null;

    public function __construct(private readonly string $gitDirectory)
    {
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

        foreach ($this->looseStores() as $store) {
            if ($store->contains($oid)) {
                return true;
            }
        }

        return false;
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

        foreach ($this->looseStores() as $store) {
            $object = $store->tryRead($oid);
            if ($object !== null) {
                return $object;
            }
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

        foreach ($this->looseStores() as $store) {
            foreach ($store->objectIds() as $oid) {
                if (str_starts_with($oid, $prefix)) {
                    $matches[$oid] = true;
                }
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

        foreach ($this->looseStores() as $store) {
            $ids = array_merge($ids, $store->objectIds());
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public function alternateObjectDirectories(): array
    {
        return array_slice($this->objectDirectories(), 1);
    }

    /**
     * @return list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string}>
     */
    private function packBundles(): array
    {
        if ($this->packs !== null) {
            return $this->packs;
        }

        $this->packs = [];
        foreach ($this->objectDirectories() as $objectsDirectory) {
            $packDirectory = $objectsDirectory . '/pack';
            $indexPaths = is_dir($packDirectory) ? glob($packDirectory . '/*.idx') ?: [] : [];
            sort($indexPaths, SORT_STRING);

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
        }

        return $this->packs;
    }

    /**
     * @return list<LooseObjectStore>
     */
    private function looseStores(): array
    {
        if ($this->looseStores !== null) {
            return $this->looseStores;
        }

        $this->looseStores = array_map(
            static fn (string $objectsDirectory): LooseObjectStore => LooseObjectStore::fromObjectsDirectory($objectsDirectory),
            $this->objectDirectories()
        );

        return $this->looseStores;
    }

    /**
     * @return list<string>
     */
    private function objectDirectories(): array
    {
        if ($this->objectDirectories !== null) {
            return $this->objectDirectories;
        }

        $primary = rtrim($this->gitDirectory, '/') . '/objects';
        $this->objectDirectories = array_merge([$primary], self::resolveAlternates($primary));

        return $this->objectDirectories;
    }

    /**
     * @return list<string>
     */
    private static function resolveAlternates(string $objectsDirectory): array
    {
        $stack = [[0, $objectsDirectory]];
        $out = [];
        $primaryRealPath = realpath($objectsDirectory);
        $seen = [$primaryRealPath === false ? $objectsDirectory : $primaryRealPath];

        while ($stack !== []) {
            [$depth, $directory] = array_pop($stack);
            $alternatesFile = $directory . '/info/alternates';
            if (is_file($alternatesFile)) {
                $contents = file_get_contents($alternatesFile);
                if ($contents === false) {
                    throw new \RuntimeException("Unable to read alternates file: {$alternatesFile}");
                }

                foreach (self::parseAlternates($contents) as $path) {
                    $candidate = str_starts_with($path, '/') ? $path : $directory . '/' . $path;
                    $realPath = realpath($candidate);
                    if ($realPath === false || !is_dir($realPath)) {
                        throw new \RuntimeException("Alternate object directory not found: {$candidate}");
                    }
                    if (in_array($realPath, $seen, true)) {
                        throw new \RuntimeException('Alternates form a cycle');
                    }
                    $seen[] = $realPath;
                    $stack[] = [$depth + 1, $realPath];
                }
            }

            if ($depth !== 0) {
                $out[] = $directory;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function parseAlternates(string $contents): array
    {
        $paths = [];
        foreach (explode("\n", $contents) as $line) {
            $line = rtrim($line, "\r");
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $paths[] = str_starts_with($line, '"') ? self::unquoteAlternatePath($line) : $line;
        }

        return $paths;
    }

    private static function unquoteAlternatePath(string $line): string
    {
        if (!str_ends_with($line, '"') || strlen($line) < 2) {
            throw new \RuntimeException('Could not unquote alternate path');
        }

        $body = substr($line, 1, -1);
        $out = '';
        $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }
            if ($i + 1 >= $length) {
                throw new \RuntimeException('Could not unquote alternate path');
            }
            $next = $body[++$i];
            $out .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                '\\' => '\\',
                '"' => '"',
                default => $next,
            };
        }

        return $out;
    }

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Object id must be a 40-character SHA-1 hex string');
        }
    }
}
