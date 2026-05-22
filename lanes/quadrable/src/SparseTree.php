<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class SparseTree
{
    private HashTree $hashTree;

    /**
     * @var array<string, string>
     */
    private array $values = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $partialRoot = null;

    public function __construct(?HashTree $hashTree = null)
    {
        $this->hashTree = $hashTree ?? new HashTree();
    }

    public function change(): ChangeSet
    {
        return new ChangeSet($this);
    }

    public function put(string $key, string $value): self
    {
        return $this->change()->put($key, $value)->apply();
    }

    public function putKey(Key $key, string $value): self
    {
        return $this->change()->putKey($key, $value)->apply();
    }

    public function delete(string $key): self
    {
        return $this->change()->delete($key)->apply();
    }

    public function deleteKey(Key $key): self
    {
        return $this->change()->deleteKey($key)->apply();
    }

    public function get(string $key): ?string
    {
        self::assertNonEmptyKey($key);

        if ($this->partialRoot !== null) {
            return $this->getPartialByKeyHash($this->hashTree->keyHash($key));
        }

        return $this->values[$this->hashTree->keyHash($key)] ?? null;
    }

    public function getKey(Key $key): ?string
    {
        if ($this->partialRoot !== null) {
            return $this->getPartialByKeyHash($key->hex());
        }

        return $this->values[$key->hex()] ?? null;
    }

    public function iterate(Key $target, bool $reverse = false): SparseTreeIterator
    {
        $entries = $this->orderedEntries();
        $targetHex = $target->hex();

        if ($reverse) {
            $position = -1;
            foreach ($entries as $index => $entry) {
                if (strcmp($entry->keyHex(), $targetHex) <= 0) {
                    $position = $index;
                    continue;
                }

                break;
            }

            return new SparseTreeIterator($entries, $position, true);
        }

        $position = count($entries);
        foreach ($entries as $index => $entry) {
            if (strcmp($entry->keyHex(), $targetHex) >= 0) {
                $position = $index;
                break;
            }
        }

        return new SparseTreeIterator($entries, $position, false);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, array{exists: bool, value: ?string}>
     */
    public function getMulti(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('getMulti expects string keys');
            }

            $value = $this->get($key);
            $results[$key] = [
                'exists' => $value !== null,
                'value' => $value,
            ];
        }

        return $results;
    }

    /**
     * @param list<string> $keys
     */
    public function exportProof(array $keys): Proof
    {
        $keyHashes = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('exportProof expects string keys');
            }
            self::assertNonEmptyKey($key);
            $keyHashes[$this->hashTree->keyHash($key)] = $key;
        }
        ksort($keyHashes, SORT_STRING);

        return $this->exportProofForKeyHashes($keyHashes);
    }

    /**
     * @param list<Key> $keys
     */
    public function exportRawProof(array $keys): Proof
    {
        $keyHashes = [];
        foreach ($keys as $key) {
            if (!$key instanceof Key) {
                throw new \InvalidArgumentException('exportRawProof expects Key instances');
            }
            $keyHashes[$key->hex()] = '';
        }
        ksort($keyHashes, SORT_STRING);

        return $this->exportProofForKeyHashes($keyHashes);
    }

    public function exportProofRange(Key $begin, Key $end): Proof
    {
        if (strcmp($begin->hex(), $end->hex()) > 0) {
            throw new \InvalidArgumentException('proof range begin must be <= end');
        }

        [$root, $nodesById] = $this->fullProofTree();
        $items = [];
        $reverseMap = [];
        $currentPath = Key::null();

        $this->exportProofRangeAux($root, 0, $begin, $end, $currentPath, $items, $reverseMap);

        return new Proof(
            array_map(static fn (array $item): ProofStrand => $item['strand'], $items),
            $this->exportProofCommands($items, $reverseMap, $nodesById, $root['id'])
        );
    }

    /**
     * @param list<SyncRequest> $requests
     *
     * @return list<Proof>
     */
    public function handleSyncRequests(array $requests, int $bytesBudget = PHP_INT_MAX): array
    {
        if ($bytesBudget === 0) {
            throw new \InvalidArgumentException("bytesBudget can't be 0");
        }
        if ($requests === []) {
            throw new \InvalidArgumentException('empty fragments request');
        }

        $lastPath = null;
        foreach ($requests as $request) {
            if (!$request instanceof SyncRequest) {
                throw new \InvalidArgumentException('handleSyncRequests expects SyncRequest instances');
            }

            $pathKey = $request->pathHex() . ':' . str_pad((string) $request->startDepth, 3, '0', STR_PAD_LEFT);
            if ($lastPath !== null && strcmp($pathKey, $lastPath) <= 0) {
                throw new \InvalidArgumentException('fragments request out of order');
            }
            $lastPath = $pathKey;
        }

        $responses = [];
        foreach ($requests as $request) {
            if ($bytesBudget === 0) {
                break;
            }

            $proof = $this->exportSyncProofFragment($request);
            $responses[] = $proof;

            $estimate = self::estimateSizeProof($proof);
            $bytesBudget = $bytesBudget > $estimate ? $bytesBudget - $estimate : 0;
        }

        return $responses;
    }

    public function exportSyncProofFragment(SyncRequest $request): Proof
    {
        [$root, $nodesById] = $this->fullProofTree();
        $path = $request->path();
        $path->keepPrefixBits($request->startDepth);
        $subtree = $this->subtreeAtPath($root, $path, 0, $request->startDepth);

        $items = [];
        $reverseMap = [];

        $this->exportSyncProofFragmentAux($subtree, 0, $request->depthLimit, $request->expandLeaves, $path, $items, $reverseMap);

        return new Proof(
            array_map(static fn (array $item): ProofStrand => $item['strand'], $items),
            $this->exportProofCommands($items, $reverseMap, $nodesById, $subtree['id'], $request->startDepth)
        );
    }

    public static function importProof(Proof $proof, string $expectedRoot = ''): self
    {
        if ($expectedRoot !== '') {
            self::assertHash($expectedRoot);
        }

        $tree = new self();
        $tree->partialRoot = self::importProofRoot($proof, $tree->hashTree);

        if ($expectedRoot !== '' && $tree->partialRoot['hash'] !== $expectedRoot) {
            throw new \RuntimeException('proof invalid');
        }

        return $tree;
    }

    /**
     * @param list<SyncRequest> $requests
     * @param list<Proof> $responses
     */
    public function importSyncResponses(array $requests, array $responses): self
    {
        if (count($responses) > count($requests)) {
            throw new \RuntimeException('too many resps when importing fragments');
        }
        if ($responses === []) {
            throw new \RuntimeException('no fragments to import');
        }

        foreach ($responses as $index => $response) {
            $request = $requests[$index] ?? null;
            if (!$request instanceof SyncRequest || !$response instanceof Proof) {
                throw new \InvalidArgumentException('importSyncResponses expects paired SyncRequest and Proof instances');
            }

            $fragmentRoot = self::importProofRoot($response, $this->hashTree, $request->startDepth);

            if ($this->partialRoot === null) {
                if ($request->startDepth !== 0) {
                    throw new \RuntimeException('initial sync fragment must start at depth 0');
                }

                $this->partialRoot = $fragmentRoot;
                continue;
            }

            $this->partialRoot = $this->replaceSyncFragment(
                $this->partialRoot,
                $request->path(),
                0,
                $request->startDepth,
                $fragmentRoot
            );
        }

        return $this;
    }

    public function mergeProof(Proof $proof): self
    {
        $newRoot = self::importProofRoot($proof, $this->hashTree);
        if ($newRoot['hash'] !== $this->rootHash()) {
            throw new \RuntimeException('different roots, unable to merge proofs');
        }

        if ($this->partialRoot === null) {
            return $this;
        }

        $this->partialRoot = $this->mergePartialProofNodes($this->partialRoot, $newRoot);

        return $this;
    }

    /**
     * @return list<SyncRequest>
     */
    public function syncRequestsForShadow(
        SparseTree $shadow,
        int $laterDepthLimit = 4,
        int $bytesBudget = PHP_INT_MAX,
        ?callable $onDiff = null
    ): array
    {
        if ($laterDepthLimit < 0 || $laterDepthLimit > 255) {
            throw new \InvalidArgumentException('sync depth limit must be between 0 and 255');
        }
        if ($bytesBudget === 0) {
            throw new \InvalidArgumentException("bytesBudget can't be 0");
        }
        if ($shadow->partialRoot === null) {
            throw new \RuntimeException('sync shadow must be an imported partial tree');
        }

        [$root] = $this->fullProofTree();
        $requests = [];
        $path = Key::null();
        $seenDiffs = [];
        $emitDiff = null;

        if ($onDiff !== null) {
            $emitDiff = static function (DiffEntry $diff) use (&$seenDiffs, $onDiff): void {
                $signature = $diff->type . "\0" . $diff->keyHex() . "\0" . $diff->value;
                if (isset($seenDiffs[$signature])) {
                    return;
                }

                $seenDiffs[$signature] = true;
                $onDiff($diff);
            };
        }

        $this->collectSyncRequests($root, $shadow->partialRoot, $path, $laterDepthLimit, $bytesBudget, $requests, $emitDiff);

        return $requests;
    }

    /**
     * @return list<DiffEntry>
     */
    public function diffTo(SparseTree $target): array
    {
        if ($this->partialRoot === null && $target->partialRoot !== null) {
            [$root] = $this->fullProofTree();
            $diffs = [];
            $this->diffNodeToPartial($root, $target->partialRoot, $diffs);

            return $diffs;
        }

        $ours = $this->leafValueMapForDiff();
        $theirs = $target->leafValueMapForDiff();
        $keys = array_values(array_unique(array_merge(array_keys($ours), array_keys($theirs))));
        sort($keys, SORT_STRING);

        $diffs = [];
        foreach ($keys as $keyHashHex) {
            $oursHas = array_key_exists($keyHashHex, $ours);
            $theirsHas = array_key_exists($keyHashHex, $theirs);

            if (!$oursHas && $theirsHas) {
                $diffs[] = new DiffEntry(DiffEntry::ADDED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]);
            } elseif ($oursHas && !$theirsHas) {
                $diffs[] = new DiffEntry(DiffEntry::DELETED, Key::fromHex($keyHashHex), $ours[$keyHashHex]);
            } elseif ($oursHas && $theirsHas && $ours[$keyHashHex] !== $theirs[$keyHashHex]) {
                $diffs[] = new DiffEntry(DiffEntry::CHANGED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]);
            }
        }

        return $diffs;
    }

    /**
     * @param list<DiffEntry> $diffs
     */
    public function applyDiffs(array $diffs): self
    {
        $changes = $this->change();

        foreach ($diffs as $diff) {
            if (!$diff instanceof DiffEntry) {
                throw new \InvalidArgumentException('applyDiffs expects DiffEntry instances');
            }

            if ($diff->type === DiffEntry::DELETED) {
                $changes->deleteKey($diff->key());
            } else {
                $changes->putKey($diff->key(), $diff->value);
            }
        }

        return $changes->apply();
    }

    public function rootHash(): string
    {
        if ($this->partialRoot !== null) {
            return $this->partialRoot['hash'];
        }

        return $this->buildTree($this->leafRecords(), 0)['hash'];
    }

    /**
     * @return array{numNodes: int, numLeafNodes: int, numBranchNodes: int, maxDepth: int}
     */
    public function stats(): array
    {
        $tree = $this->buildTree($this->leafRecords(), 0);

        return [
            'numNodes' => $tree['nodes'],
            'numLeafNodes' => $tree['leaves'],
            'numBranchNodes' => $tree['branches'],
            'maxDepth' => $tree['maxDepth'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function entries(): array
    {
        $entries = $this->values;
        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @return list<SparseTreeEntry>
     */
    public function orderedEntries(): array
    {
        $entries = [];
        foreach ($this->entries() as $keyHashHex => $value) {
            $entries[] = new SparseTreeEntry(Key::fromHex($keyHashHex), $value);
        }

        return $entries;
    }

    /**
     * @param array<string, array{delete: bool, value: string}> $updates
     */
    public function applyRawUpdates(array $updates): self
    {
        ksort($updates, SORT_STRING);
        if ($this->partialRoot !== null) {
            $this->partialRoot = $this->applyPartialUpdates($this->partialRoot, $updates)['node'];

            return $this;
        }

        foreach ($updates as $keyHashHex => $update) {
            self::assertHash($keyHashHex);
            if (!isset($update['delete'], $update['value']) || !is_bool($update['delete']) || !is_string($update['value'])) {
                throw new \InvalidArgumentException('Malformed sparse tree update');
            }

            if ($update['delete']) {
                unset($this->values[$keyHashHex]);
                continue;
            }

            $this->values[$keyHashHex] = $update['value'];
        }

        ksort($this->values, SORT_STRING);

        return $this;
    }

    public static function assertNonEmptyKey(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('zero-length keys not allowed');
        }
    }

    /**
     * @param array<string, string> $keyHashes
     */
    private function exportProofForKeyHashes(array $keyHashes): Proof
    {
        [$root, $nodesById] = $this->fullProofTree();
        $items = [];
        $reverseMap = [];

        $this->exportProofAux($root, 0, $keyHashes, $items, $reverseMap);

        return new Proof(
            array_map(static fn (array $item): ProofStrand => $item['strand'], $items),
            $this->exportProofCommands($items, $reverseMap, $nodesById, $root['id'])
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function fullProofTree(): array
    {
        $records = [];
        foreach ($this->values as $keyHashHex => $value) {
            $records[] = [
                'keyHash' => $keyHashHex,
                'value' => $value,
            ];
        }
        usort($records, static fn (array $a, array $b): int => $a['keyHash'] <=> $b['keyHash']);

        $nodesById = [];
        $nextId = 1;

        return [$this->buildFullNode($records, 0, $nextId, $nodesById), $nodesById];
    }

    /**
     * @param list<array{keyHash: string, value: string}> $records
     * @param array<int, array<string, mixed>> $nodesById
     *
     * @return array<string, mixed>
     */
    private function buildFullNode(array $records, int $depth, int &$nextId, array &$nodesById): array
    {
        $count = count($records);
        if ($count === 0) {
            return [
                'id' => 0,
                'type' => 'empty',
                'depth' => $depth,
                'hash' => HashTree::EMPTY_HASH,
            ];
        }

        if ($count === 1) {
            $id = $nextId++;
            $node = [
                'id' => $id,
                'type' => 'leaf',
                'depth' => $depth,
                'keyHash' => $records[0]['keyHash'],
                'value' => $records[0]['value'],
                'hash' => $this->hashTree->leafHashForKeyHash($records[0]['keyHash'], $records[0]['value']),
            ];
            $nodesById[$id] = $node;

            return $node;
        }

        if ($depth > 255) {
            throw new \RuntimeException('Sparse tree key collision exceeded 256 bits');
        }

        $leftRecords = [];
        $rightRecords = [];
        foreach ($records as $record) {
            if ($this->hashTree->bitAt($record['keyHash'], $depth) === 0) {
                $leftRecords[] = $record;
            } else {
                $rightRecords[] = $record;
            }
        }

        $left = $this->buildFullNode($leftRecords, $depth + 1, $nextId, $nodesById);
        $right = $this->buildFullNode($rightRecords, $depth + 1, $nextId, $nodesById);
        $id = $nextId++;
        $node = [
            'id' => $id,
            'type' => 'branch',
            'depth' => $depth,
            'left' => $left,
            'right' => $right,
            'hash' => $this->hashTree->branchHash($left['hash'], $right['hash']),
        ];
        $nodesById[$id] = $node;

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, string> $keyHashes
     * @param list<array{nodeId: int, parentNodeId: int, strand: ProofStrand}> $items
     * @param array<int, int> $reverseMap
     */
    private function exportProofAux(array $node, int $parentNodeId, array $keyHashes, array &$items, array &$reverseMap): void
    {
        if ($keyHashes === []) {
            return;
        }

        if ($node['type'] === 'empty') {
            $key = Key::fromHex(array_key_first($keyHashes));
            $key->keepPrefixBits($node['depth']);
            $items[] = [
                'nodeId' => 0,
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::WITNESS_EMPTY, $node['depth'], $key->hex()),
            ];
            return;
        }

        if ($node['type'] === 'leaf') {
            if (array_key_exists($node['keyHash'], $keyHashes)) {
                $items[] = [
                    'nodeId' => $node['id'],
                    'parentNodeId' => $parentNodeId,
                    'strand' => new ProofStrand(ProofStrand::LEAF, $node['depth'], $node['keyHash'], $node['value'], $keyHashes[$node['keyHash']]),
                ];
                return;
            }

            $items[] = [
                'nodeId' => $node['id'],
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::WITNESS_LEAF, $node['depth'], $node['keyHash'], $this->hashTree->valueHash($node['value'])),
            ];
            return;
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('encountered witness node: incomplete tree');
        }

        if ($node['left']['id'] !== 0) {
            $reverseMap[$node['left']['id']] = $node['id'];
        }
        if ($node['right']['id'] !== 0) {
            $reverseMap[$node['right']['id']] = $node['id'];
        }

        $leftKeys = [];
        $rightKeys = [];
        foreach ($keyHashes as $keyHashHex => $key) {
            if ($this->hashTree->bitAt($keyHashHex, $node['depth']) === 0) {
                $leftKeys[$keyHashHex] = $key;
            } else {
                $rightKeys[$keyHashHex] = $key;
            }
        }

        if ($node['left']['id'] !== 0 || $rightKeys === []) {
            $this->exportProofAux($node['left'], $node['id'], $leftKeys, $items, $reverseMap);
        }
        if ($node['right']['id'] !== 0 || $leftKeys === []) {
            $this->exportProofAux($node['right'], $node['id'], $rightKeys, $items, $reverseMap);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array{nodeId: int, parentNodeId: int, strand: ProofStrand}> $items
     * @param array<int, int> $reverseMap
     */
    private function exportProofRangeAux(array $node, int $parentNodeId, Key $begin, Key $end, Key $currentPath, array &$items, array &$reverseMap): void
    {
        if ($node['type'] === 'empty') {
            $items[] = [
                'nodeId' => 0,
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::WITNESS_EMPTY, $node['depth'], $currentPath->hex()),
            ];
            return;
        }

        if ($node['type'] === 'leaf') {
            $items[] = [
                'nodeId' => $node['id'],
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::LEAF, $node['depth'], $node['keyHash'], $node['value']),
            ];
            return;
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('encountered witness node: incomplete tree');
        }

        if ($node['left']['id'] !== 0) {
            $reverseMap[$node['left']['id']] = $node['id'];
        }
        if ($node['right']['id'] !== 0) {
            $reverseMap[$node['right']['id']] = $node['id'];
        }

        $boundary = clone $currentPath;
        $boundary->setBit($node['depth'], 1);
        $doLeft = strcmp($begin->hex(), $boundary->hex()) < 0;
        $doRight = strcmp($end->hex(), $boundary->hex()) >= 0;

        $currentPath->setBit($node['depth'], 0);
        if ($doLeft) {
            $this->exportProofRangeAux($node['left'], $node['id'], $begin, $end, $currentPath, $items, $reverseMap);
        }

        $currentPath->setBit($node['depth'], 1);
        if ($doRight) {
            $this->exportProofRangeAux($node['right'], $node['id'], $begin, $end, $currentPath, $items, $reverseMap);
        }

        $currentPath->setBit($node['depth'], 0);
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function subtreeAtPath(array $node, Key $path, int $depth, int $targetDepth): array
    {
        if ($depth === $targetDepth) {
            return $node;
        }

        if ($node['type'] === 'empty') {
            return [
                'id' => 0,
                'type' => 'empty',
                'depth' => $targetDepth,
                'hash' => HashTree::EMPTY_HASH,
            ];
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('fragment path not available');
        }

        return $this->subtreeAtPath(
            $path->getBit($depth) === 0 ? $node['left'] : $node['right'],
            $path,
            $depth + 1,
            $targetDepth
        );
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array{nodeId: int, parentNodeId: int, strand: ProofStrand}> $items
     * @param array<int, int> $reverseMap
     */
    private function exportSyncProofFragmentAux(array $node, int $parentNodeId, int $depthLimit, bool $expandLeaves, Key $currentPath, array &$items, array &$reverseMap): void
    {
        if ($node['type'] === 'empty') {
            $items[] = [
                'nodeId' => 0,
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::WITNESS_EMPTY, $node['depth'], $currentPath->hex()),
            ];
            return;
        }

        if ($node['type'] === 'leaf') {
            if ($expandLeaves || strlen($node['value']) <= 32) {
                $items[] = [
                    'nodeId' => $node['id'],
                    'parentNodeId' => $parentNodeId,
                    'strand' => new ProofStrand(ProofStrand::LEAF, $node['depth'], $node['keyHash'], $node['value']),
                ];
                return;
            }

            $items[] = [
                'nodeId' => $node['id'],
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::WITNESS_LEAF, $node['depth'], $node['keyHash'], $this->hashTree->valueHash($node['value'])),
            ];
            return;
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('encountered witness node: incomplete tree');
        }

        if ($node['left']['id'] !== 0) {
            $reverseMap[$node['left']['id']] = $node['id'];
        }
        if ($node['right']['id'] !== 0) {
            $reverseMap[$node['right']['id']] = $node['id'];
        }

        if ($depthLimit === 0) {
            $items[] = [
                'nodeId' => $node['id'],
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::WITNESS, $node['depth'], $currentPath->hex(), $node['hash']),
            ];
            return;
        }

        if ($node['left']['id'] !== 0 && $node['right']['id'] !== 0) {
            $depthLimit--;
        }

        $currentPath->setBit($node['depth'], 0);
        $this->exportSyncProofFragmentAux($node['left'], $node['id'], $depthLimit, $expandLeaves, $currentPath, $items, $reverseMap);

        $currentPath->setBit($node['depth'], 1);
        $this->exportSyncProofFragmentAux($node['right'], $node['id'], $depthLimit, $expandLeaves, $currentPath, $items, $reverseMap);

        $currentPath->setBit($node['depth'], 0);
    }

    /**
     * @param list<array{nodeId: int, parentNodeId: int, strand: ProofStrand}> $items
     * @param array<int, int> $reverseMap
     * @param array<int, array<string, mixed>> $nodesById
     *
     * @return list<ProofCommand>
     */
    private function exportProofCommands(array $items, array $reverseMap, array $nodesById, int $headNodeId, int $startDepth = 0): array
    {
        if ($items === []) {
            return [];
        }

        $accums = [];
        $maxDepth = 0;
        foreach ($items as $index => $item) {
            $maxDepth = max($maxDepth, $item['strand']->depth);
            $accums[] = [
                'index' => $index,
                'depth' => $item['strand']->depth,
                'nodeId' => $item['nodeId'],
                'next' => $index + 1,
                'mergedOrder' => 0,
                'commands' => [],
            ];
        }
        $accums[array_key_last($accums)]['next'] = -1;

        $mergeOrder = 0;
        for ($currentDepth = $maxDepth; $currentDepth > $startDepth; $currentDepth--) {
            for ($i = 0; $i !== -1; $i = $accums[$i]['next']) {
                if ($accums[$i]['depth'] !== $currentDepth) {
                    continue;
                }

                $currentParent = $accums[$i]['nodeId'] !== 0
                    ? $reverseMap[$accums[$i]['nodeId']]
                    : $items[$i]['parentNodeId'];

                if ($accums[$i]['next'] !== -1) {
                    $nextIndex = $accums[$i]['next'];
                    $nextParent = $accums[$nextIndex]['nodeId'] !== 0
                        ? $reverseMap[$accums[$nextIndex]['nodeId']]
                        : $items[$nextIndex]['parentNodeId'];

                    if ($currentParent === $nextParent) {
                        $accums[$i]['commands'][] = new ProofCommand(ProofCommand::MERGE, $i);
                        $accums[$nextIndex]['mergedOrder'] = $mergeOrder++;
                        $accums[$i]['next'] = $accums[$nextIndex]['next'];
                        $accums[$i]['nodeId'] = $currentParent;
                        $accums[$i]['depth']--;
                        continue;
                    }
                }

                if (!isset($nodesById[$currentParent])) {
                    throw new \RuntimeException('proof command generation reached a missing parent node');
                }

                $parentNode = $nodesById[$currentParent];
                $siblingNode = $parentNode['left']['id'] === $accums[$i]['nodeId'] ? $parentNode['right'] : $parentNode['left'];

                if ($siblingNode['id'] !== 0) {
                    $accums[$i]['commands'][] = new ProofCommand(ProofCommand::HASH_PROVIDED, $i, $siblingNode['hash']);
                } else {
                    $accums[$i]['commands'][] = new ProofCommand(ProofCommand::HASH_EMPTY, $i);
                }

                $accums[$i]['nodeId'] = $currentParent;
                $accums[$i]['depth']--;
            }
        }

        if ($accums[0]['depth'] !== $startDepth || $accums[0]['nodeId'] !== $headNodeId || $accums[0]['next'] !== -1) {
            throw new \RuntimeException('proof command generation did not reach the root');
        }
        $accums[0]['mergedOrder'] = $mergeOrder;

        usort($accums, static fn (array $a, array $b): int => $a['mergedOrder'] <=> $b['mergedOrder']);

        $commands = [];
        foreach ($accums as $accum) {
            foreach ($accum['commands'] as $command) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    private function getPartialByKeyHash(string $keyHashHex): ?string
    {
        self::assertHash($keyHashHex);
        if ($this->partialRoot === null) {
            return null;
        }

        return $this->queryPartialNode($this->partialRoot, $keyHashHex);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function queryPartialNode(array $node, string $keyHashHex): ?string
    {
        if ($node['type'] === 'empty') {
            return null;
        }

        if ($node['type'] === 'leaf') {
            return $node['keyHash'] === $keyHashHex ? $node['value'] : null;
        }

        if ($node['type'] === 'witnessLeaf') {
            if ($node['keyHash'] === $keyHashHex) {
                throw new \RuntimeException('encountered witness node: incomplete tree');
            }

            return null;
        }

        if ($node['type'] === 'witness') {
            throw new \RuntimeException('encountered witness node: incomplete tree');
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('unrecognized partial tree node type');
        }

        return $this->queryPartialNode(
            $this->hashTree->bitAt($keyHashHex, $node['depth']) === 0 ? $node['left'] : $node['right'],
            $keyHashHex
        );
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array{delete: bool, value: string}> $updates
     *
     * @return array{node: array<string, mixed>, bubble: bool}
     */
    private function applyPartialUpdates(array $node, array $updates): array
    {
        foreach ($updates as $keyHashHex => $update) {
            self::assertHash($keyHashHex);
            if (!isset($update['delete'], $update['value']) || !is_bool($update['delete']) || !is_string($update['value'])) {
                throw new \InvalidArgumentException('Malformed sparse tree update');
            }
        }

        if ($updates === []) {
            return ['node' => $node, 'bubble' => false];
        }

        if ($node['type'] === 'witness') {
            throw new \RuntimeException('encountered witness during update: partial tree');
        }

        if ($node['type'] === 'empty') {
            $kept = array_filter($updates, static fn (array $update): bool => !$update['delete']);

            if ($kept === []) {
                return ['node' => $node, 'bubble' => false];
            }

            return [
                'node' => $this->buildPartialNodeFromUpdates($kept, $node['depth']),
                'bubble' => false,
            ];
        }

        if ($node['type'] === 'leaf' || $node['type'] === 'witnessLeaf') {
            return $this->applyPartialLeafUpdates($node, $updates);
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('unrecognized partial tree node type');
        }

        $leftUpdates = [];
        $rightUpdates = [];
        foreach ($updates as $keyHashHex => $update) {
            if ($this->hashTree->bitAt($keyHashHex, $node['depth']) === 0) {
                $leftUpdates[$keyHashHex] = $update;
            } else {
                $rightUpdates[$keyHashHex] = $update;
            }
        }

        $leftResult = $this->applyPartialUpdates($node['left'], $leftUpdates);
        $rightResult = $this->applyPartialUpdates($node['right'], $rightUpdates);
        $bubble = $leftResult['bubble'] || $rightResult['bubble'];

        if ($bubble) {
            if ($leftResult['node']['type'] === 'witness' || $rightResult['node']['type'] === 'witness') {
                throw new \RuntimeException("can't bubble a witness node");
            }
            if ($leftResult['node']['type'] === 'empty' && $rightResult['node']['type'] === 'empty') {
                return [
                    'node' => $this->emptyPartialNode($node['depth']),
                    'bubble' => true,
                ];
            }
            if ($this->isPartialLeaf($leftResult['node']) && $rightResult['node']['type'] === 'empty') {
                return [
                    'node' => $leftResult['node'],
                    'bubble' => true,
                ];
            }
            if ($leftResult['node']['type'] === 'empty' && $this->isPartialLeaf($rightResult['node'])) {
                return [
                    'node' => $rightResult['node'],
                    'bubble' => true,
                ];
            }
        }

        return [
            'node' => $this->branchPartialNode($node['depth'], $leftResult['node'], $rightResult['node']),
            'bubble' => false,
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array{delete: bool, value: string}> $updates
     *
     * @return array{node: array<string, mixed>, bubble: bool}
     */
    private function applyPartialLeafUpdates(array $node, array $updates): array
    {
        $nodeKeyHash = $node['keyHash'];

        if (count($updates) === 1 && array_key_exists($nodeKeyHash, $updates)) {
            $update = $updates[$nodeKeyHash];
            if ($update['delete']) {
                return [
                    'node' => $this->emptyPartialNode($node['depth']),
                    'bubble' => true,
                ];
            }

            if ($node['type'] === 'leaf' && $node['value'] === $update['value']) {
                return ['node' => $node, 'bubble' => false];
            }

            return [
                'node' => $this->leafPartialNode($nodeKeyHash, $update['value'], $node['depth']),
                'bubble' => false,
            ];
        }

        $nextUpdates = $updates;
        $deleteThisLeaf = false;
        foreach ($nextUpdates as $keyHashHex => $update) {
            if (!$update['delete']) {
                continue;
            }

            if ($keyHashHex === $nodeKeyHash) {
                $deleteThisLeaf = true;
            }
            unset($nextUpdates[$keyHashHex]);
        }

        if ($nextUpdates === []) {
            if ($deleteThisLeaf) {
                return [
                    'node' => $this->emptyPartialNode($node['depth']),
                    'bubble' => true,
                ];
            }

            return ['node' => $node, 'bubble' => false];
        }

        if (!$deleteThisLeaf && !array_key_exists($nodeKeyHash, $nextUpdates)) {
            $nextUpdates[$nodeKeyHash] = [
                'delete' => false,
                'value' => $node,
            ];
        }
        ksort($nextUpdates, SORT_STRING);

        return [
            'node' => $this->buildPartialNodeFromUpdates($nextUpdates, $node['depth']),
            'bubble' => false,
        ];
    }

    /**
     * @param array<string, array{delete: bool, value: string|array<string, mixed>}> $updates
     *
     * @return array<string, mixed>
     */
    private function buildPartialNodeFromUpdates(array $updates, int $depth): array
    {
        if ($updates === []) {
            return $this->emptyPartialNode($depth);
        }

        if (count($updates) === 1) {
            $keyHashHex = array_key_first($updates);
            $update = $updates[$keyHashHex];
            if (is_array($update['value'])) {
                return $update['value'];
            }

            return $this->leafPartialNode($keyHashHex, $update['value'], $depth);
        }

        if ($depth > 255) {
            throw new \RuntimeException('Sparse tree key collision exceeded 256 bits');
        }

        $leftUpdates = [];
        $rightUpdates = [];
        foreach ($updates as $keyHashHex => $update) {
            if ($this->hashTree->bitAt($keyHashHex, $depth) === 0) {
                $leftUpdates[$keyHashHex] = $update;
            } else {
                $rightUpdates[$keyHashHex] = $update;
            }
        }

        return $this->branchPartialNode(
            $depth,
            $this->buildPartialNodeFromUpdates($leftUpdates, $depth + 1),
            $this->buildPartialNodeFromUpdates($rightUpdates, $depth + 1)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function leafPartialNode(string $keyHashHex, string $value, int $depth): array
    {
        self::assertHash($keyHashHex);

        return [
            'type' => 'leaf',
            'depth' => $depth,
            'keyHash' => $keyHashHex,
            'value' => $value,
            'hash' => $this->hashTree->leafHashForKeyHash($keyHashHex, $value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPartialNode(int $depth): array
    {
        return [
            'type' => 'empty',
            'depth' => $depth,
            'hash' => HashTree::EMPTY_HASH,
        ];
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array<string, mixed>
     */
    private function branchPartialNode(int $depth, array $left, array $right): array
    {
        return [
            'type' => 'branch',
            'depth' => $depth,
            'left' => $left,
            'right' => $right,
            'hash' => $this->hashTree->branchHash($left['hash'], $right['hash']),
        ];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isPartialLeaf(array $node): bool
    {
        return $node['type'] === 'leaf' || $node['type'] === 'witnessLeaf';
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $new
     *
     * @return array<string, mixed>
     */
    private function mergePartialProofNodes(array $original, array $new): array
    {
        if (($this->isWitnessAny($original) && !$this->isWitnessAny($new))
            || ($original['type'] === 'witness' && $new['type'] === 'witnessLeaf')) {
            return $new;
        }

        if ($original['type'] === 'branch' && $new['type'] === 'branch') {
            if ($original['depth'] !== $new['depth']) {
                throw new \RuntimeException('proof branch depth mismatch');
            }

            $left = $this->mergePartialProofNodes($original['left'], $new['left']);
            $right = $this->mergePartialProofNodes($original['right'], $new['right']);

            if ($left === $original['left'] && $right === $original['right']) {
                return $original;
            }

            if ($left === $new['left'] && $right === $new['right']) {
                return $new;
            }

            return $this->branchPartialNode($original['depth'], $left, $right);
        }

        return $original;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isWitnessAny(array $node): bool
    {
        return $node['type'] === 'witness' || $node['type'] === 'witnessLeaf';
    }

    /**
     * @param array<string, mixed> $localNode
     * @param array<string, mixed> $shadowNode
     * @param list<SyncRequest> $requests
     */
    private function collectSyncRequests(
        array $localNode,
        array $shadowNode,
        Key $currentPath,
        int $laterDepthLimit,
        int &$bytesBudget,
        array &$requests,
        ?callable $onDiff
    ): bool
    {
        if ($localNode['hash'] === $shadowNode['hash']) {
            return true;
        }
        if ($bytesBudget === 0) {
            return false;
        }

        if ($shadowNode['type'] === 'branch') {
            $leftLocal = $localNode['type'] === 'branch' ? $localNode['left'] : $localNode;
            $rightLocal = $localNode['type'] === 'branch' ? $localNode['right'] : $localNode;

            $currentPath->setBit($shadowNode['depth'], 0);
            $leftDone = $this->collectSyncRequests($leftLocal, $shadowNode['left'], $currentPath, $laterDepthLimit, $bytesBudget, $requests, $onDiff);

            $currentPath->setBit($shadowNode['depth'], 1);
            $rightDone = $this->collectSyncRequests($rightLocal, $shadowNode['right'], $currentPath, $laterDepthLimit, $bytesBudget, $requests, $onDiff);

            $currentPath->setBit($shadowNode['depth'], 0);

            $done = $leftDone && $rightDone;
            if ($done && $onDiff !== null && (($localNode['type'] === 'branch' && $shadowNode['type'] === 'branch') || $shadowNode['depth'] === 0)) {
                $this->emitSyncScanDiffs($localNode, $shadowNode, $onDiff);
            }

            return $done;
        }

        if ($shadowNode['type'] === 'witnessLeaf' || $shadowNode['type'] === 'witness') {
            $path = new Key($currentPath->bytes());
            $path->keepPrefixBits($shadowNode['depth']);

            $requests[] = new SyncRequest(
                $path,
                $shadowNode['depth'],
                $shadowNode['type'] === 'witnessLeaf' ? 1 : $laterDepthLimit,
                $shadowNode['type'] === 'witnessLeaf'
            );

            $bytesBudget = $bytesBudget > 16 ? $bytesBudget - 16 : 0;

            return false;
        }

        if ($onDiff !== null && $shadowNode['depth'] === 0) {
            $this->emitSyncScanDiffs($localNode, $shadowNode, $onDiff);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $localNode
     * @param array<string, mixed> $shadowNode
     */
    private function emitSyncScanDiffs(array $localNode, array $shadowNode, callable $onDiff): void
    {
        $diffs = [];
        $this->diffNodeToPartial($localNode, $shadowNode, $diffs);

        foreach ($diffs as $diff) {
            $onDiff($diff);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $fragmentRoot
     *
     * @return array<string, mixed>
     */
    private function replaceSyncFragment(array $node, Key $path, int $depth, int $targetDepth, array $fragmentRoot): array
    {
        if ($depth === $targetDepth) {
            if (!$this->isWitnessAny($node)) {
                throw new \RuntimeException('import proof fragment tried to expand non-witness');
            }
            if ($node['hash'] !== $fragmentRoot['hash']) {
                throw new \RuntimeException('import proof fragment incompatible tree');
            }

            return $fragmentRoot;
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('fragment path not available');
        }

        if ($path->getBit($depth) === 0) {
            return $this->branchPartialNode(
                $node['depth'],
                $this->replaceSyncFragment($node['left'], $path, $depth + 1, $targetDepth, $fragmentRoot),
                $node['right']
            );
        }

        return $this->branchPartialNode(
            $node['depth'],
            $node['left'],
            $this->replaceSyncFragment($node['right'], $path, $depth + 1, $targetDepth, $fragmentRoot)
        );
    }

    /**
     * @return array<string, string>
     */
    private function leafValueMapForDiff(): array
    {
        if ($this->partialRoot === null) {
            return $this->entries();
        }

        $entries = [];
        $this->collectFullPartialLeaves($this->partialRoot, $entries);
        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @param array<string, mixed> $ours
     * @param array<string, mixed> $theirs
     * @param list<DiffEntry> $diffs
     */
    private function diffNodeToPartial(array $ours, array $theirs, array &$diffs): void
    {
        if ($ours['hash'] === $theirs['hash']) {
            return;
        }

        if ($this->isWitnessAny($ours) || $this->isWitnessAny($theirs)) {
            throw new \RuntimeException('encountered witness during diff');
        }

        if ($ours['type'] === 'branch' && $theirs['type'] === 'branch') {
            $this->diffNodeToPartial($ours['left'], $theirs['left'], $diffs);
            $this->diffNodeToPartial($ours['right'], $theirs['right'], $diffs);
            return;
        }

        $this->appendLeafMapDiffs(
            $this->leafValueMapFromNode($ours),
            $this->leafValueMapFromNode($theirs),
            $diffs
        );
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, string>
     */
    private function leafValueMapFromNode(array $node): array
    {
        $entries = [];
        $this->collectFullPartialLeaves($node, $entries);
        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @param array<string, string> $ours
     * @param array<string, string> $theirs
     * @param list<DiffEntry> $diffs
     */
    private function appendLeafMapDiffs(array $ours, array $theirs, array &$diffs): void
    {
        $keys = array_values(array_unique(array_merge(array_keys($ours), array_keys($theirs))));
        sort($keys, SORT_STRING);

        foreach ($keys as $keyHashHex) {
            $oursHas = array_key_exists($keyHashHex, $ours);
            $theirsHas = array_key_exists($keyHashHex, $theirs);

            if (!$oursHas && $theirsHas) {
                $diffs[] = new DiffEntry(DiffEntry::ADDED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]);
            } elseif ($oursHas && !$theirsHas) {
                $diffs[] = new DiffEntry(DiffEntry::DELETED, Key::fromHex($keyHashHex), $ours[$keyHashHex]);
            } elseif ($oursHas && $theirsHas && $ours[$keyHashHex] !== $theirs[$keyHashHex]) {
                $diffs[] = new DiffEntry(DiffEntry::CHANGED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]);
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, string> $entries
     */
    private function collectFullPartialLeaves(array $node, array &$entries): void
    {
        if ($node['type'] === 'empty') {
            return;
        }

        if ($node['type'] === 'leaf') {
            $entries[$node['keyHash']] = $node['value'];
            return;
        }

        if ($node['type'] === 'branch') {
            $this->collectFullPartialLeaves($node['left'], $entries);
            $this->collectFullPartialLeaves($node['right'], $entries);
            return;
        }

        if ($this->isWitnessAny($node)) {
            throw new \RuntimeException('encountered witness during diff');
        }

        throw new \RuntimeException('unrecognized partial tree node type');
    }

    private static function estimateSizeProof(Proof $proof): int
    {
        $output = count($proof->strands) * 10;

        foreach ($proof->strands as $strand) {
            $output += strlen($strand->value);
            $output += strlen($strand->key);
        }

        $output += count($proof->commands);

        foreach ($proof->commands as $command) {
            if ($command->operation === ProofCommand::HASH_PROVIDED) {
                $output += 32;
            }
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    private static function importProofRoot(Proof $proof, HashTree $hashTree, int $expectedDepth = 0): array
    {
        $accums = [];

        foreach ($proof->strands as $index => $strand) {
            self::assertHash($strand->keyHash);
            $next = $index + 1;

            if ($strand->type === ProofStrand::LEAF) {
                $node = [
                    'type' => 'leaf',
                    'depth' => $strand->depth,
                    'keyHash' => $strand->keyHash,
                    'value' => $strand->value,
                    'hash' => $hashTree->leafHashForKeyHash($strand->keyHash, $strand->value),
                ];
            } elseif ($strand->type === ProofStrand::WITNESS_LEAF) {
                self::assertHash($strand->value);
                $node = [
                    'type' => 'witnessLeaf',
                    'depth' => $strand->depth,
                    'keyHash' => $strand->keyHash,
                    'valueHash' => $strand->value,
                    'hash' => $hashTree->leafHashForKeyHashAndValueHash($strand->keyHash, $strand->value),
                ];
            } elseif ($strand->type === ProofStrand::WITNESS_EMPTY) {
                $node = [
                    'type' => 'empty',
                    'depth' => $strand->depth,
                    'keyHash' => $strand->keyHash,
                    'hash' => HashTree::EMPTY_HASH,
                ];
            } elseif ($strand->type === ProofStrand::WITNESS) {
                self::assertHash($strand->value);
                $node = [
                    'type' => 'witness',
                    'depth' => $strand->depth,
                    'keyHash' => $strand->keyHash,
                    'hash' => $strand->value,
                ];
            } else {
                throw new \RuntimeException('unrecognized ProofItem type: ' . $strand->type);
            }

            $accums[] = [
                'depth' => $strand->depth,
                'node' => $node,
                'next' => $next,
                'keyHash' => $strand->keyHash,
                'merged' => false,
            ];
        }

        if ($accums === []) {
            throw new \RuntimeException('empty proof');
        }
        $accums[array_key_last($accums)]['next'] = -1;

        foreach ($proof->commands as $command) {
            if ($command->nodeOffset >= count($proof->strands)) {
                throw new \RuntimeException('nodeOffset in cmd is out of range');
            }

            if ($accums[$command->nodeOffset]['merged']) {
                throw new \RuntimeException('strand already merged');
            }
            if ($accums[$command->nodeOffset]['depth'] === 0) {
                throw new \RuntimeException('node depth underflow');
            }

            $accum = &$accums[$command->nodeOffset];

            if ($command->operation === ProofCommand::HASH_PROVIDED) {
                self::assertHash($command->hash);
                $sibling = [
                    'type' => 'witness',
                    'depth' => $accum['depth'],
                    'hash' => $command->hash,
                ];
            } elseif ($command->operation === ProofCommand::HASH_EMPTY) {
                $sibling = [
                    'type' => 'empty',
                    'depth' => $accum['depth'],
                    'hash' => HashTree::EMPTY_HASH,
                ];
            } elseif ($command->operation === ProofCommand::MERGE) {
                if ($accum['next'] < 0) {
                    throw new \RuntimeException('no nodes left to merge with');
                }

                $nextIndex = $accum['next'];
                if ($accum['depth'] !== $accums[$nextIndex]['depth']) {
                    throw new \RuntimeException('merge depth mismatch');
                }

                $accum['next'] = $accums[$nextIndex]['next'];
                $accums[$nextIndex]['merged'] = true;
                $sibling = $accums[$nextIndex]['node'];
            } else {
                throw new \RuntimeException('unrecognized ProofCmd op: ' . $command->operation);
            }

            $accumNode = $accum['node'];
            if ($command->operation === ProofCommand::MERGE || $hashTree->bitAt($accum['keyHash'], $accum['depth'] - 1) === 0) {
                $left = $accumNode;
                $right = $sibling;
            } else {
                $left = $sibling;
                $right = $accumNode;
            }

            $accum['depth']--;
            $accum['node'] = [
                'type' => 'branch',
                'depth' => $accum['depth'],
                'left' => $left,
                'right' => $right,
                'hash' => $hashTree->branchHash($left['hash'], $right['hash']),
            ];

            unset($accum);
        }

        if ($accums[0]['next'] !== -1) {
            throw new \RuntimeException('not all proof strands were merged');
        }
        if ($accums[0]['depth'] !== $expectedDepth) {
            throw new \RuntimeException("proof didn't reach expected depth");
        }

        return $accums[0]['node'];
    }

    /**
     * @return list<array{keyHash: string, leafHash: string}>
     */
    private function leafRecords(): array
    {
        $records = [];
        foreach ($this->values as $keyHashHex => $value) {
            $records[] = [
                'keyHash' => $keyHashHex,
                'leafHash' => $this->hashTree->leafHashForKeyHash($keyHashHex, $value),
            ];
        }

        usort($records, static fn (array $a, array $b): int => $a['keyHash'] <=> $b['keyHash']);

        return $records;
    }

    /**
     * @param list<array{keyHash: string, leafHash: string}> $records
     *
     * @return array{hash: string, nodes: int, leaves: int, branches: int, maxDepth: int}
     */
    private function buildTree(array $records, int $depth): array
    {
        $count = count($records);
        if ($count === 0) {
            return [
                'hash' => HashTree::EMPTY_HASH,
                'nodes' => 0,
                'leaves' => 0,
                'branches' => 0,
                'maxDepth' => 0,
            ];
        }

        if ($count === 1) {
            return [
                'hash' => $records[0]['leafHash'],
                'nodes' => 1,
                'leaves' => 1,
                'branches' => 0,
                'maxDepth' => $depth,
            ];
        }

        if ($depth > 255) {
            throw new \RuntimeException('Sparse tree key collision exceeded 256 bits');
        }

        $left = [];
        $right = [];
        foreach ($records as $record) {
            if ($this->hashTree->bitAt($record['keyHash'], $depth) === 0) {
                $left[] = $record;
            } else {
                $right[] = $record;
            }
        }

        $leftTree = $this->buildTree($left, $depth + 1);
        $rightTree = $this->buildTree($right, $depth + 1);

        return [
            'hash' => $this->hashTree->branchHash($leftTree['hash'], $rightTree['hash']),
            'nodes' => 1 + $leftTree['nodes'] + $rightTree['nodes'],
            'leaves' => $leftTree['leaves'] + $rightTree['leaves'],
            'branches' => 1 + $leftTree['branches'] + $rightTree['branches'],
            'maxDepth' => max($depth, $leftTree['maxDepth'], $rightTree['maxDepth']),
        ];
    }

    private static function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }
}
