<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ObjectDatabase
{
    public const ORDER_PACK_LEXICOGRAPHICAL_THEN_LOOSE_LEXICOGRAPHICAL = 'pack-lexicographical-then-loose-lexicographical';
    public const ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL = 'pack-offset-then-loose-lexicographical';

    /**
     * @var null|list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}>
     */
    private ?array $packs = null;
    /**
     * @var null|list<array{index:MultiPackIndex,path:string,packDirectory:string,bundlesByIndexName:array<string,array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}>}>
     */
    private ?array $multiPacks = null;
    /**
     * @var null|list<string>
     */
    private ?array $objectDirectories = null;
    /**
     * @var null|list<LooseObjectStore>
     */
    private ?array $looseStores = null;
    /**
     * @var null|array<string,string>
     */
    private ?array $replacementMap = null;
    /**
     * @var null|list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string,promisorPath:string}>
     */
    private ?array $promisorPacks = null;
    /**
     * @var null|list<array{name:string,url:?string,partialCloneFilter:?string}>
     */
    private ?array $promisorRemotes = null;

    private readonly string $gitDirectory;
    private readonly bool $ignoreReplacements;
    private readonly string $replacementRefBase;
    private readonly ?PromisorObjectResolver $promisorResolver;
    private readonly string $objectHash;
    private readonly ?int $looseObjectAllocationLimitBytes;
    private readonly bool $refreshObjectStorageOnMiss;

    public function __construct(
        string $gitDirectory,
        bool $ignoreReplacements = false,
        string $replacementRefBase = 'refs/replace',
        ?PromisorObjectResolver $promisorResolver = null,
        string $objectHash = 'sha1',
        ?int $looseObjectAllocationLimitBytes = null,
        bool $refreshObjectStorageOnMiss = true,
    )
    {
        $this->gitDirectory = $gitDirectory;
        $this->ignoreReplacements = $ignoreReplacements;
        $this->replacementRefBase = $replacementRefBase;
        $this->promisorResolver = $promisorResolver;
        $this->objectHash = self::normalizeObjectHash($objectHash);
        $this->looseObjectAllocationLimitBytes = self::normalizeLooseObjectAllocationLimit($looseObjectAllocationLimitBytes);
        $this->refreshObjectStorageOnMiss = $refreshObjectStorageOnMiss;
    }

    public function contains(string $oid): bool
    {
        $this->assertObjectId($oid);
        $oid = strtolower($oid);

        if ($this->tryContainsLocal($oid)) {
            return true;
        }

        if (!$this->refreshObjectStorageOnMiss) {
            return false;
        }

        $this->refreshObjectStorage();

        return $this->tryContainsLocal($oid);
    }

    private function tryContainsLocal(string $oid): bool
    {
        foreach ($this->multiPackIndexes() as $multiPack) {
            $entry = $multiPack['index']->lookup($oid);
            if ($entry !== null) {
                return $this->bundleForMultiPackEntry($multiPack, $entry) !== null;
            }
        }

        foreach ($this->standalonePackBundles() as $bundle) {
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
        $this->assertObjectId($oid);
        $oid = strtolower($oid);
        $oid = $this->replacementFor($oid) ?? $oid;

        $object = $this->tryReadLocalObject($oid);
        if ($object !== null) {
            return $object;
        }

        if ($this->hasPromisorConfiguration()) {
            $object = $this->resolvePromisedObject($oid);
            if ($object !== null) {
                return $object;
            }

            if ($this->refreshObjectStorageOnMiss) {
                $this->refreshObjectStorage();
                $object = $this->tryReadLocalObject($oid);
                if ($object !== null) {
                    return $object;
                }
            }

            throw new \RuntimeException("Object promised by partial clone filter but not present locally: {$oid}");
        }

        throw new \RuntimeException("Object not found in database: {$oid}");
    }

    /**
     * @return array{type:string,size:int,source:'pack'|'loose'|'promisor'}
     */
    public function readHeader(string $oid): array
    {
        $this->assertObjectId($oid);
        $oid = strtolower($oid);
        $oid = $this->replacementFor($oid) ?? $oid;

        $header = $this->tryReadLocalHeader($oid);
        if ($header !== null) {
            return $header;
        }

        if ($this->hasPromisorConfiguration()) {
            $object = $this->resolvePromisedObject($oid);
            if ($object !== null) {
                return self::headerFromObject($object, 'promisor');
            }

            if ($this->refreshObjectStorageOnMiss) {
                $this->refreshObjectStorage();
                $header = $this->tryReadLocalHeader($oid);
                if ($header !== null) {
                    return $header;
                }
            }

            throw new \RuntimeException("Object promised by partial clone filter but not present locally: {$oid}");
        }

        throw new \RuntimeException("Object not found in database: {$oid}");
    }

    /**
     * Return a commit object's first gpgsig value and exact signed bytes.
     *
     * This is the repository/object-database boundary for
     * gix::object::Commit::signature(): object lookup follows this database's
     * replacement policy, and object-id validation follows its hash format.
     *
     * @return array{signature:string,signedData:string}|null
     */
    public function commitSignatureForVerification(string $oid): ?array
    {
        return Commit::signatureForVerificationFromObject($this->read($oid), $this->objectHash);
    }

    public function packedObjectCount(): int
    {
        if ($this->refreshObjectStorageOnMiss) {
            $this->refreshObjectStorage();
        }

        $count = 0;
        foreach ($this->multiPackIndexes() as $multiPack) {
            $count += $multiPack['index']->count();
        }
        foreach ($this->standalonePackBundles() as $bundle) {
            $count += $bundle['index']->count();
        }

        return $count;
    }

    /**
     * @return array{status:'missing',candidates?:list<string>}|array{status:'found',oid:string,candidates?:list<string>}|array{status:'ambiguous',matches:list<string>,candidates?:list<string>}
     */
    public function lookupPrefix(string $prefix, bool $includeCandidates = false): array
    {
        $prefix = strtolower($prefix);
        $maxLength = ReferenceTarget::hashHexLength($this->objectHash);
        if (preg_match('/^[0-9a-f]{4,' . $maxLength . '}$/', $prefix) !== 1) {
            throw new \InvalidArgumentException("Lookup prefix must be 4 to {$maxLength} hexadecimal characters");
        }

        if ($includeCandidates) {
            if ($this->refreshObjectStorageOnMiss) {
                $this->refreshObjectStorage();
            }
            $oids = $this->prefixMatches($prefix);
        } else {
            $oids = $this->prefixMatches($prefix);
            if ($this->refreshObjectStorageOnMiss && count($oids) <= 1) {
                $this->refreshObjectStorage();
                $oids = $this->prefixMatches($prefix);
            }
        }

        if ($oids === []) {
            $result = ['status' => 'missing'];
            if ($includeCandidates) {
                $result['candidates'] = [];
            }

            return $result;
        }
        if (count($oids) > 1) {
            $result = ['status' => 'ambiguous', 'matches' => $oids];
            if ($includeCandidates) {
                $result['candidates'] = $oids;
            }

            return $result;
        }

        $result = ['status' => 'found', 'oid' => $oids[0]];
        if ($includeCandidates) {
            $result['candidates'] = $oids;
        }

        return $result;
    }

    public function disambiguatePrefix(string $oid, int $minimumHexLength): ?string
    {
        $this->assertObjectId($oid);
        $hexLength = ReferenceTarget::hashHexLength($this->objectHash);
        if ($minimumHexLength < 4 || $minimumHexLength > $hexLength) {
            throw new \InvalidArgumentException("Disambiguation prefix length must be between 4 and {$hexLength} hexadecimal characters");
        }

        $oid = strtolower($oid);
        if ($minimumHexLength === $hexLength) {
            return $this->contains($oid) ? $oid : null;
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
     * @return list<string>
     */
    private function prefixMatches(string $prefix): array
    {
        $matches = [];
        foreach ($this->multiPackIndexes() as $multiPack) {
            $result = $multiPack['index']->lookupPrefix($prefix);
            if ($result['status'] === 'found') {
                $matches[$result['entry']->oid] = true;
            } elseif ($result['status'] === 'ambiguous') {
                foreach ($result['matches'] as $entryIndex) {
                    $matches[$multiPack['index']->entryAt($entryIndex)->oid] = true;
                }
            }
        }

        foreach ($this->standalonePackBundles() as $bundle) {
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
            foreach ($store->prefixObjectIds($prefix) as $oid) {
                $matches[$oid] = true;
            }
        }

        $oids = array_keys($matches);
        sort($oids, SORT_STRING);

        return $oids;
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

        if ($this->refreshObjectStorageOnMiss) {
            $this->refreshObjectStorage();
        }

        $ids = [];
        foreach ($this->multiPackIndexes() as $multiPack) {
            $entries = $multiPack['index']->entries();
            if ($ordering === self::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL) {
                usort(
                    $entries,
                    static fn (MultiPackIndexEntry $a, MultiPackIndexEntry $b): int => $a->packIndex <=> $b->packIndex
                        ?: $a->packOffset <=> $b->packOffset
                        ?: strcmp($a->oid, $b->oid)
                );
            }
            foreach ($entries as $entry) {
                $ids[] = $entry->oid;
            }
        }

        foreach ($this->standalonePackBundles() as $bundle) {
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
     * @param null|callable(string,int,string):bool $shouldInterrupt
     * @return list<array{path:string,statistics:array{numObjects:int,verifiedObjectIds:list<string>}}>
     */
    public function verifyLooseIntegrity(?callable $shouldInterrupt = null): array
    {
        $out = [];
        foreach ($this->looseStores() as $store) {
            $interrupt = $shouldInterrupt === null
                ? null
                : static fn (string $oid, int $verifiedCount): bool => $shouldInterrupt(
                    $oid,
                    $verifiedCount,
                    $store->objectsDirectory()
                );
            $out[] = [
                'path' => $store->objectsDirectory(),
                'statistics' => $store->verifyIntegrity($interrupt),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function alternateObjectDirectories(): array
    {
        return array_slice($this->objectDirectories(), 1);
    }

    /**
     * @return list<array{from:string,to:string}>
     */
    public function replacements(): array
    {
        $replacements = [];
        foreach ($this->replacementMap() as $from => $to) {
            $replacements[] = ['from' => $from, 'to' => $to];
        }

        return $replacements;
    }

    public function withReplacementsIgnored(): self
    {
        return new self(
            $this->gitDirectory,
            true,
            $this->replacementRefBase,
            $this->promisorResolver,
            $this->objectHash,
            $this->looseObjectAllocationLimitBytes,
            $this->refreshObjectStorageOnMiss,
        );
    }

    public function withPromisorResolver(PromisorObjectResolver $resolver): self
    {
        return new self(
            $this->gitDirectory,
            $this->ignoreReplacements,
            $this->replacementRefBase,
            $resolver,
            $this->objectHash,
            $this->looseObjectAllocationLimitBytes,
            $this->refreshObjectStorageOnMiss,
        );
    }

    public function withObjectStorageRefreshDisabled(): self
    {
        return $this->withObjectStorageRefreshOnMiss(false);
    }

    public function withObjectStorageRefreshEnabled(): self
    {
        return $this->withObjectStorageRefreshOnMiss(true);
    }

    public function objectStorageRefreshesOnMiss(): bool
    {
        return $this->refreshObjectStorageOnMiss;
    }

    private function withObjectStorageRefreshOnMiss(bool $refreshObjectStorageOnMiss): self
    {
        return new self(
            $this->gitDirectory,
            $this->ignoreReplacements,
            $this->replacementRefBase,
            $this->promisorResolver,
            $this->objectHash,
            $this->looseObjectAllocationLimitBytes,
            $refreshObjectStorageOnMiss,
        );
    }

    public function write(GitObject $object): string
    {
        $oid = $object->oid($this->objectHash);
        if ($this->contains($oid)) {
            return $oid;
        }

        return $this->primaryLooseStore()->write($object);
    }

    public function writeCommit(Commit $commit): string
    {
        return $this->write($commit->object());
    }

    /**
     * @return array{packName:string,indexName:string,promisorName:string,keepName:?string,packChecksum:string,indexChecksum:string,objectIds:list<string>,objectCount:int,alreadyPresent:bool}
     */
    public function writePromisorPackBundle(PackBuildResult $pack, string $promisorNote = '', bool $keep = true): array
    {
        if ($this->objectHash !== 'sha1') {
            throw new \RuntimeException('Promisor pack bundle writing currently supports SHA-1 pack build results');
        }

        $packDirectory = $this->primaryPackDirectory();
        $basename = 'pack-' . $pack->packChecksum();
        $packName = $basename . '.pack';
        $indexName = $basename . '.idx';
        $promisorName = $basename . '.promisor';
        $keepName = $basename . '.keep';
        $packPath = $packDirectory . '/' . $packName;
        $indexPath = $packDirectory . '/' . $indexName;
        $promisorPath = $packDirectory . '/' . $promisorName;
        $keepPath = $packDirectory . '/' . $keepName;
        $packAlreadyExists = is_file($packPath);
        $indexAlreadyExists = is_file($indexPath);

        if (!$packAlreadyExists && $keep) {
            self::writeBytes($keepPath, '');
        }
        if (!$packAlreadyExists) {
            self::writeBytes($packPath, $pack->packBytes());
        }
        if (!$indexAlreadyExists) {
            self::writeBytes($indexPath, $pack->indexBytes());
        }
        self::writeBytes($promisorPath, $promisorNote);

        if ($this->refreshObjectStorageOnMiss) {
            $this->refreshObjectStorage();
        }

        return [
            'packName' => $packName,
            'indexName' => $indexName,
            'promisorName' => $promisorName,
            'keepName' => !$packAlreadyExists && $keep ? $keepName : null,
            'packChecksum' => $pack->packChecksum(),
            'indexChecksum' => $pack->indexChecksum(),
            'objectIds' => array_values(array_map(
                static fn (array $entry): string => $entry['oid'],
                $pack->entries()
            )),
            'objectCount' => count($pack->entries()),
            'alreadyPresent' => $packAlreadyExists && $indexAlreadyExists,
        ];
    }

    /**
     * @return list<string>
     */
    public function promisorPackNames(): array
    {
        $this->refreshObjectStorageBeforePromisorInventory();

        return array_map(
            static fn (array $bundle): string => basename($bundle['promisorPath']),
            $this->promisorPackBundles()
        );
    }

    public function hasPromisorPacks(): bool
    {
        $this->refreshObjectStorageBeforePromisorInventory();

        return $this->promisorPackBundles() !== [];
    }

    /**
     * @return list<array{name:string,url:?string,partialCloneFilter:?string}>
     */
    public function promisorRemotes(): array
    {
        if ($this->promisorRemotes !== null) {
            return $this->promisorRemotes;
        }

        $configPath = rtrim($this->gitDirectory, '/\\') . '/config';
        if (!is_file($configPath)) {
            return $this->promisorRemotes = [];
        }

        $config = GitConfig::fromFile($configPath, [
            'gitDir' => $this->gitDirectory,
            'errOnMissingConfigPath' => false,
            'errOnInterpolationFailure' => false,
        ]);
        $remoteConfig = [];
        foreach ($config->sections() as $section) {
            if ($section['name'] !== 'remote' || $section['subsection'] === null) {
                continue;
            }

            $name = $section['subsection'];
            $remoteConfig[$name] ??= [
                'name' => $name,
                'url' => null,
                'promisor' => null,
                'partialCloneFilter' => null,
            ];
            foreach ($section['entries'] as $entry) {
                if ($entry['key'] === 'url') {
                    $remoteConfig[$name]['url'] = $entry['value'];
                } elseif ($entry['key'] === 'promisor') {
                    $remoteConfig[$name]['promisor'] = $entry['value'];
                } elseif ($entry['key'] === 'partialclonefilter') {
                    $remoteConfig[$name]['partialCloneFilter'] = $entry['value'];
                }
            }
        }

        $promisorRemotes = [];
        foreach ($remoteConfig as $remote) {
            if (!self::configBooleanIsTrue($remote['promisor'])) {
                continue;
            }

            $promisorRemotes[] = [
                'name' => $remote['name'],
                'url' => $remote['url'],
                'partialCloneFilter' => $remote['partialCloneFilter'],
            ];
        }

        return $this->promisorRemotes = $promisorRemotes;
    }

    /**
     * @return list<string>
     */
    public function promisorObjectIds(): array
    {
        $this->refreshObjectStorageBeforePromisorInventory();

        $ids = [];
        foreach ($this->promisorPackBundles() as $bundle) {
            foreach ($bundle['index']->entries() as $entry) {
                $ids[$entry->oid] = true;
            }
        }

        $ids = array_keys($ids);
        sort($ids, SORT_STRING);

        return $ids;
    }

    public function isPromisorObject(string $oid): bool
    {
        $this->assertObjectId($oid);
        $oid = strtolower($oid);

        $this->refreshObjectStorageBeforePromisorInventory();

        foreach ($this->promisorPackBundles() as $bundle) {
            if ($bundle['index']->lookup($oid) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status:'present'|'promisor-present'|'promised-missing'|'missing',oid:string}
     */
    public function objectState(string $oid): array
    {
        $this->assertObjectId($oid);
        $oid = strtolower($oid);

        if ($this->contains($oid)) {
            return [
                'status' => $this->isPromisorObject($oid) ? 'promisor-present' : 'present',
                'oid' => $oid,
            ];
        }

        return [
            'status' => $this->hasPromisorConfiguration() ? 'promised-missing' : 'missing',
            'oid' => $oid,
        ];
    }

    /**
     * @return list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}>
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

                $index = PackIndex::open($indexPath, $this->objectHash);
                $data = PackData::open($packPath, $this->objectHash);
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
                    'indexName' => basename($indexPath),
                    'packDirectory' => $packDirectory,
                ];
            }
        }

        return $this->packs;
    }

    /**
     * @return list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string,promisorPath:string}>
     */
    private function promisorPackBundles(): array
    {
        if ($this->promisorPacks !== null) {
            return $this->promisorPacks;
        }

        $this->promisorPacks = [];
        foreach ($this->packBundles() as $bundle) {
            $promisorPath = substr($bundle['indexPath'], 0, -4) . '.promisor';
            if (!is_file($promisorPath)) {
                continue;
            }
            $this->promisorPacks[] = $bundle + ['promisorPath' => $promisorPath];
        }

        return $this->promisorPacks;
    }

    private function resolvePromisedObject(string $oid): ?GitObject
    {
        if ($this->promisorResolver === null) {
            return null;
        }

        $object = $this->promisorResolver->resolvePromisedObject($oid, $this);
        if ($object === null) {
            return null;
        }

        $actualOid = $object->oid($this->objectHash);
        if ($actualOid !== $oid) {
            throw new \RuntimeException("Promisor resolver returned {$actualOid} for requested object: {$oid}");
        }

        $this->primaryLooseStore()->write($object);
        if ($this->refreshObjectStorageOnMiss) {
            $this->refreshObjectStorage();
        }

        return $object;
    }

    /**
     * @param list<string> $externalBaseStack
     */
    private function tryReadLocalObject(string $oid, array $externalBaseStack = []): ?GitObject
    {
        foreach ($this->multiPackIndexes() as $multiPack) {
            $entry = $multiPack['index']->lookup($oid);
            if ($entry === null) {
                continue;
            }

            $bundle = $this->bundleForMultiPackEntry($multiPack, $entry);
            if ($bundle === null) {
                throw new \RuntimeException("Pack referenced by multi-pack-index was not found for object: {$oid}");
            }

            return $this->readPackObjectWithExternalBases($bundle, $oid, $externalBaseStack);
        }

        foreach ($this->standalonePackBundles() as $bundle) {
            if ($bundle['index']->lookup($oid) !== null) {
                return $this->readPackObjectWithExternalBases($bundle, $oid, $externalBaseStack);
            }
        }

        foreach ($this->looseStores() as $store) {
            $object = $store->tryRead($oid);
            if ($object !== null) {
                return $object;
            }
        }

        return null;
    }

    /**
     * @return null|array{type:string,size:int,source:'pack'|'loose'}
     */
    private function tryReadLocalHeader(string $oid, array $externalBaseStack = []): ?array
    {
        foreach ($this->multiPackIndexes() as $multiPack) {
            $entry = $multiPack['index']->lookup($oid);
            if ($entry === null) {
                continue;
            }

            $bundle = $this->bundleForMultiPackEntry($multiPack, $entry);
            if ($bundle === null) {
                throw new \RuntimeException("Pack referenced by multi-pack-index was not found for object: {$oid}");
            }

            return $this->readPackObjectHeaderWithExternalBases($bundle, $oid, $externalBaseStack);
        }

        foreach ($this->standalonePackBundles() as $bundle) {
            if ($bundle['index']->lookup($oid) !== null) {
                return $this->readPackObjectHeaderWithExternalBases($bundle, $oid, $externalBaseStack);
            }
        }

        foreach ($this->looseStores() as $store) {
            $header = $store->tryReadHeader($oid);
            if ($header !== null) {
                return [
                    'type' => $header['type'],
                    'size' => $header['size'],
                    'source' => 'loose',
                ];
            }
        }

        return null;
    }

    /**
     * @param array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string} $bundle
     * @param list<string> $externalBaseStack
     */
    private function readPackObjectWithExternalBases(array $bundle, string $oid, array $externalBaseStack): GitObject
    {
        $stack = array_merge($externalBaseStack, [$oid]);

        return $bundle['data']->readObjectWithExternalBaseResolver(
            $bundle['index'],
            $oid,
            fn (string $baseOid): ?GitObject => $this->resolveExternalDeltaBaseObject($baseOid, $stack)
        );
    }

    /**
     * @param array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string} $bundle
     * @param list<string> $externalBaseStack
     * @return array{type:string,size:int,source:'pack'}
     */
    private function readPackObjectHeaderWithExternalBases(array $bundle, string $oid, array $externalBaseStack): array
    {
        $stack = array_merge($externalBaseStack, [$oid]);

        return self::headerFromPack($bundle['data']->readObjectHeaderWithExternalBaseResolver(
            $bundle['index'],
            $oid,
            fn (string $baseOid): null|GitObject|array => $this->resolveExternalDeltaBaseHeader($baseOid, $stack)
        ));
    }

    /**
     * @param list<string> $externalBaseStack
     */
    private function resolveExternalDeltaBaseObject(string $baseOid, array $externalBaseStack): ?GitObject
    {
        $this->assertObjectId($baseOid);
        $baseOid = strtolower($baseOid);
        if (in_array($baseOid, $externalBaseStack, true)) {
            throw new \RuntimeException("REF_DELTA external base cycle while resolving: {$baseOid}");
        }

        $stack = array_merge($externalBaseStack, [$baseOid]);
        $object = $this->tryReadLocalObject($baseOid, $stack);
        if ($object !== null) {
            return $object;
        }

        if (!$this->hasPromisorConfiguration()) {
            return null;
        }

        $object = $this->resolvePromisedObject($baseOid);
        if ($object !== null) {
            return $object;
        }

        if (!$this->refreshObjectStorageOnMiss) {
            return null;
        }

        $this->refreshObjectStorage();

        return $this->tryReadLocalObject($baseOid, $stack);
    }

    /**
     * @param list<string> $externalBaseStack
     * @return null|GitObject|array{type:string,size:int,numDeltas:int}
     */
    private function resolveExternalDeltaBaseHeader(string $baseOid, array $externalBaseStack): null|GitObject|array
    {
        $this->assertObjectId($baseOid);
        $baseOid = strtolower($baseOid);
        if (in_array($baseOid, $externalBaseStack, true)) {
            throw new \RuntimeException("REF_DELTA external base cycle while resolving header: {$baseOid}");
        }

        $stack = array_merge($externalBaseStack, [$baseOid]);
        $header = $this->tryReadLocalHeader($baseOid, $stack);
        if ($header !== null) {
            return [
                'type' => $header['type'],
                'size' => $header['size'],
                'numDeltas' => 0,
            ];
        }

        if (!$this->hasPromisorConfiguration()) {
            return null;
        }

        $object = $this->resolvePromisedObject($baseOid);
        if ($object !== null) {
            return $object;
        }

        if (!$this->refreshObjectStorageOnMiss) {
            return null;
        }

        $this->refreshObjectStorage();
        $header = $this->tryReadLocalHeader($baseOid, $stack);
        if ($header === null) {
            return null;
        }

        return [
            'type' => $header['type'],
            'size' => $header['size'],
            'numDeltas' => 0,
        ];
    }

    private function refreshObjectStorage(): void
    {
        $this->packs = null;
        $this->multiPacks = null;
        $this->promisorPacks = null;
        $this->objectDirectories = null;
        $this->looseStores = null;
    }

    private function refreshObjectStorageBeforePromisorInventory(): void
    {
        if ($this->refreshObjectStorageOnMiss) {
            $this->refreshObjectStorage();
        }
    }

    private function hasPromisorConfiguration(): bool
    {
        return $this->hasPromisorPacks() || $this->promisorRemotes() !== [];
    }

    private function primaryLooseStore(): LooseObjectStore
    {
        return LooseObjectStore::fromObjectsDirectory(
            $this->objectDirectories()[0],
            $this->objectHash,
            $this->looseObjectAllocationLimitBytes,
        );
    }

    private function primaryPackDirectory(): string
    {
        $directory = $this->objectDirectories()[0] . '/pack';
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create pack directory: {$directory}");
        }

        return $directory;
    }

    private static function writeBytes(string $path, string $bytes): void
    {
        if (file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException("Unable to write file: {$path}");
        }
    }

    /**
     * @return list<array{index:MultiPackIndex,path:string,packDirectory:string,bundlesByIndexName:array<string,array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}>}>
     */
    private function multiPackIndexes(): array
    {
        if ($this->multiPacks !== null) {
            return $this->multiPacks;
        }

        $bundlesByDirectory = [];
        foreach ($this->packBundles() as $bundle) {
            $bundlesByDirectory[$bundle['packDirectory']][$bundle['indexName']] = $bundle;
        }

        $this->multiPacks = [];
        foreach ($this->objectDirectories() as $objectsDirectory) {
            $packDirectory = $objectsDirectory . '/pack';
            $path = $packDirectory . '/multi-pack-index';
            if (!is_file($path)) {
                continue;
            }

            $index = MultiPackIndex::open($path);
            if ($index->objectHash() !== $this->objectHash) {
                continue;
            }
            $index->verifyIntegrityFast();
            $bundlesByIndexName = $bundlesByDirectory[$packDirectory] ?? [];
            foreach ($index->indexNames() as $indexName) {
                if (!isset($bundlesByIndexName[$indexName])) {
                    throw new \RuntimeException("Pack index referenced by multi-pack-index not found: {$packDirectory}/{$indexName}");
                }
            }
            self::assertMultiPackEntriesMatchPackIndexes($index, $bundlesByIndexName, $packDirectory);

            $this->multiPacks[] = [
                'index' => $index,
                'path' => $path,
                'packDirectory' => $packDirectory,
                'bundlesByIndexName' => $bundlesByIndexName,
            ];
        }

        return $this->multiPacks;
    }

    /**
     * @param array<string,array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}> $bundlesByIndexName
     */
    private static function assertMultiPackEntriesMatchPackIndexes(MultiPackIndex $index, array $bundlesByIndexName, string $packDirectory): void
    {
        $indexNames = $index->indexNames();
        foreach ($index->entries() as $entry) {
            $indexName = $indexNames[$entry->packIndex] ?? null;
            if ($indexName === null || !isset($bundlesByIndexName[$indexName])) {
                throw new \RuntimeException("Pack index referenced by multi-pack-index not found: {$packDirectory}/{$indexName}");
            }

            $packEntry = $bundlesByIndexName[$indexName]['index']->lookup($entry->oid);
            if ($packEntry === null) {
                throw new \RuntimeException("Multi-pack-index object {$entry->oid} was not found in referenced pack index: {$packDirectory}/{$indexName}");
            }
            if ($packEntry->packOffset !== $entry->packOffset) {
                throw new \RuntimeException("Multi-pack-index object offset mismatch for {$entry->oid}: multi-pack-index has {$entry->packOffset}, referenced pack index has {$packEntry->packOffset}");
            }
        }
    }

    /**
     * @param array{index:MultiPackIndex,path:string,packDirectory:string,bundlesByIndexName:array<string,array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}>} $multiPack
     * @return null|array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}
     */
    private function bundleForMultiPackEntry(array $multiPack, MultiPackIndexEntry $entry): ?array
    {
        $indexName = $multiPack['index']->indexNames()[$entry->packIndex] ?? null;
        if ($indexName === null) {
            return null;
        }

        return $multiPack['bundlesByIndexName'][$indexName] ?? null;
    }

    /**
     * @return list<array{index:PackIndex,data:PackData,indexPath:string,packPath:string,indexName:string,packDirectory:string}>
     */
    private function standalonePackBundles(): array
    {
        $referencedIndexPaths = [];
        foreach ($this->multiPackIndexes() as $multiPack) {
            foreach ($multiPack['index']->indexNames() as $indexName) {
                $bundle = $multiPack['bundlesByIndexName'][$indexName] ?? null;
                if ($bundle !== null) {
                    $referencedIndexPaths[$bundle['indexPath']] = true;
                }
            }
        }

        if ($referencedIndexPaths === []) {
            return $this->packBundles();
        }

        $standalone = [];
        foreach ($this->packBundles() as $bundle) {
            if (!isset($referencedIndexPaths[$bundle['indexPath']])) {
                $standalone[] = $bundle;
            }
        }

        return $standalone;
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
            fn (string $objectsDirectory): LooseObjectStore => LooseObjectStore::fromObjectsDirectory(
                $objectsDirectory,
                $this->objectHash,
                $this->looseObjectAllocationLimitBytes,
            ),
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

    private function replacementFor(string $oid): ?string
    {
        if ($this->ignoreReplacements) {
            return null;
        }

        return $this->replacementMap()[$oid] ?? null;
    }

    /**
     * @return array<string,string>
     */
    private function replacementMap(): array
    {
        if ($this->replacementMap !== null) {
            return $this->replacementMap;
        }

        $this->replacementMap = [];
        $prefix = trim($this->replacementRefBase, '/');
        if ($prefix === '') {
            throw new \InvalidArgumentException('Replacement ref base cannot be empty');
        }

        $packedRefsPath = rtrim($this->gitDirectory, '/\\') . '/packed-refs';
        if (is_file($packedRefsPath)) {
            foreach (PackedReferences::open($packedRefsPath, $this->objectHash)->all() as $reference) {
                $this->recordReplacementRef($prefix, $reference->name, $reference->targetObjectId());
            }
        }

        $looseBase = rtrim($this->gitDirectory, '/\\') . '/' . $prefix;
        if (is_dir($looseBase)) {
            $files = glob($looseBase . '/*') ?: [];
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                $source = basename($file);
                if (!is_file($file) || preg_match('/^[0-9a-fA-F]{40}$/', $source) !== 1) {
                    continue;
                }
                try {
                    $reference = LooseReference::parse($prefix . '/' . $source, (string) file_get_contents($file), $this->objectHash);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                if ($reference->target->isObject()) {
                    $this->replacementMap[strtolower($source)] = $reference->target->value;
                }
            }
        }

        ksort($this->replacementMap, SORT_STRING);

        return $this->replacementMap;
    }

    private function recordReplacementRef(string $prefix, string $name, string $target): void
    {
        $prefixWithSlash = $prefix . '/';
        if (!str_starts_with($name, $prefixWithSlash)) {
            return;
        }
        $source = substr($name, strlen($prefixWithSlash));
        $length = ReferenceTarget::hashHexLength($this->objectHash);
        if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $source) !== 1) {
            return;
        }

        $this->replacementMap[strtolower($source)] = strtolower($target);
    }

    /**
     * @return array{type:string,size:int,source:'pack'|'loose'|'promisor'}
     */
    private static function headerFromObject(GitObject $object, string $source): array
    {
        return [
            'type' => $object->type,
            'size' => strlen($object->body),
            'source' => $source,
        ];
    }

    /**
     * @param array{type:string,size:int,numDeltas:int} $header
     * @return array{type:string,size:int,source:'pack'}
     */
    private static function headerFromPack(array $header): array
    {
        return [
            'type' => $header['type'],
            'size' => $header['size'],
            'source' => 'pack',
        ];
    }

    private function assertObjectId(string $oid): void
    {
        $length = ReferenceTarget::hashHexLength($this->objectHash);
        if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $oid) !== 1) {
            throw new \InvalidArgumentException("Object id must be a {$length}-character " . strtoupper($this->objectHash) . ' hex string');
        }
    }

    private static function normalizeObjectHash(string $objectHash): string
    {
        $normalized = strtolower($objectHash);
        ReferenceTarget::hashHexLength($normalized);

        return $normalized;
    }

    private static function normalizeLooseObjectAllocationLimit(?int $limit): ?int
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('Loose object allocation limit must not be negative');
        }

        return $limit;
    }

    private static function configBooleanIsTrue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['true', 'yes', 'on'], true)) {
            return true;
        }
        if ($normalized === '' || in_array($normalized, ['false', 'no', 'off'], true)) {
            return false;
        }
        if (preg_match('/^[+-]?\d+$/', $normalized) === 1) {
            return preg_match('/^[+-]?0+$/', $normalized) !== 1;
        }

        return false;
    }
}
