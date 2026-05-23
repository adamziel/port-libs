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
     * @var array<string, string>
     */
    private array $trackedKeys = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $partialRoot = null;
    private int $nextPartialNodeId = TrackedNodeStore::FIRST_MEMSTORE_NODE_ID;

    /**
     * @var array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}|null
     */
    private ?array $fullProofTreeCache = null;

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

    /**
     * @param list<Key> $keys
     *
     * @return array<string, array{exists: bool, value: ?string}>
     */
    public function getMultiRaw(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            if (!$key instanceof Key) {
                throw new \InvalidArgumentException('getMultiRaw expects Key instances');
            }

            $value = $this->getKey($key);
            $results[$key->hex()] = [
                'exists' => $value !== null,
                'value' => $value,
            ];
        }

        ksort($results, SORT_STRING);

        return $results;
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

        [$root, $nodesById] = $this->proofTreeForExport();
        $items = [];
        $reverseMap = [];
        $currentPath = Key::null();

        $this->exportProofRangeAux($root, 0, $begin, $end, $currentPath, $items, $reverseMap);

        return new Proof(
            array_map(static fn (array $item): ProofStrand => $item['strand'], $items),
            $this->exportProofCommands($items, $reverseMap, $nodesById, $this->nodeIdFromPartialNode($root))
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

            $pathHex = $request->pathHex();
            if ($lastPath !== null && strcmp($pathHex, $lastPath) <= 0) {
                throw new \InvalidArgumentException('fragments request out of order');
            }
            $lastPath = $pathHex;
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
        $tree->partialRoot = self::importProofRoot($proof, $tree->hashTree, 0, $tree->nextPartialNodeId);

        if ($expectedRoot !== '' && $tree->partialRoot['hash'] !== $expectedRoot) {
            throw new \RuntimeException('proof invalid');
        }

        return $tree;
    }

    /**
     * Rebuilds an imported partial tree from persisted storage records. This is
     * used when restoring upstream LMDB cursor dumps that contain proof-backed
     * heads but not the original proof event history.
     *
     * @param array{
     *     rootNodeId: int|string,
     *     leaves: array<int|string, array<string, mixed>>,
     *     branches: array<int|string, array<string, mixed>>
     * } $snapshot
     */
    public static function fromPartialStorageSnapshot(array $snapshot, ?HashTree $hashTree = null): self
    {
        if (!isset($snapshot['rootNodeId'], $snapshot['leaves'], $snapshot['branches'])
            || !is_array($snapshot['leaves'])
            || !is_array($snapshot['branches'])
        ) {
            throw new \InvalidArgumentException('partial storage snapshot is malformed');
        }

        $tree = new self($hashTree);
        $maxNodeId = 0;
        $leaves = [];
        foreach ($snapshot['leaves'] as $nodeIdRaw => $record) {
            $nodeId = self::parseStorageNodeId($nodeIdRaw, 'partial storage leaf node id');
            if (isset($leaves[$nodeId])) {
                throw new \InvalidArgumentException('partial storage snapshot contains a duplicate leaf node id');
            }
            if ($nodeId >= TrackedNodeStore::FIRST_INTERIOR_NODE_ID
                && $nodeId < TrackedNodeStore::FIRST_MEMSTORE_NODE_ID
            ) {
                throw new \InvalidArgumentException('partial storage leaf node id is in the branch node range');
            }
            if (!is_array($record) || !isset($record['type']) || !is_string($record['type'])) {
                throw new \InvalidArgumentException('partial storage leaf record is malformed');
            }

            if ($record['type'] === 'leaf') {
                if (!isset($record['keyHash'], $record['value'], $record['hash'])
                    || !is_string($record['keyHash'])
                    || !is_string($record['value'])
                    || !is_string($record['hash'])
                ) {
                    throw new \InvalidArgumentException('partial storage leaf record is malformed');
                }
                self::assertHash($record['keyHash']);
                self::assertHash($record['hash']);
                $expectedHash = $tree->hashTree->leafHashForKeyHash($record['keyHash'], $record['value']);
                if ($record['hash'] !== $expectedHash) {
                    throw new \InvalidArgumentException('partial storage leaf hash does not match key/value bytes');
                }

                $key = '';
                if (array_key_exists('key', $record) && $record['key'] !== null) {
                    if (!is_string($record['key'])) {
                        throw new \InvalidArgumentException('partial storage leaf key is malformed');
                    }
                    if ($record['key'] !== '') {
                        self::assertNonEmptyKey($record['key']);
                    }
                    $key = $record['key'];
                }

                $leaves[$nodeId] = [
                    'nodeId' => $nodeId,
                    'type' => 'leaf',
                    'keyHash' => $record['keyHash'],
                    'value' => $record['value'],
                    'key' => $key,
                    'hash' => $record['hash'],
                ];
            } elseif ($record['type'] === 'witnessLeaf') {
                if (!isset($record['keyHash'], $record['valueHash'], $record['hash'])
                    || !is_string($record['keyHash'])
                    || !is_string($record['valueHash'])
                    || !is_string($record['hash'])
                ) {
                    throw new \InvalidArgumentException('partial storage witness leaf record is malformed');
                }
                self::assertHash($record['keyHash']);
                self::assertHash($record['valueHash']);
                self::assertHash($record['hash']);
                $expectedHash = $tree->hashTree->leafHashForKeyHashAndValueHash(
                    $record['keyHash'],
                    $record['valueHash']
                );
                if ($record['hash'] !== $expectedHash) {
                    throw new \InvalidArgumentException('partial storage witness leaf hash does not match key/value hashes');
                }

                $leaves[$nodeId] = [
                    'nodeId' => $nodeId,
                    'type' => 'witnessLeaf',
                    'keyHash' => $record['keyHash'],
                    'valueHash' => $record['valueHash'],
                    'hash' => $record['hash'],
                ];
            } else {
                throw new \InvalidArgumentException('partial storage leaf bucket contains an unknown node type');
            }

            $maxNodeId = max($maxNodeId, $nodeId);
        }

        $branches = [];
        foreach ($snapshot['branches'] as $nodeIdRaw => $record) {
            $nodeId = self::parseStorageNodeId($nodeIdRaw, 'partial storage branch node id');
            if (isset($leaves[$nodeId]) || isset($branches[$nodeId])) {
                throw new \InvalidArgumentException('partial storage snapshot contains duplicate node ids');
            }
            if ($nodeId < TrackedNodeStore::FIRST_INTERIOR_NODE_ID) {
                throw new \InvalidArgumentException('partial storage branch node id is in the leaf node range');
            }
            if (!is_array($record) || !isset($record['type']) || !is_string($record['type'])) {
                throw new \InvalidArgumentException('partial storage branch record is malformed');
            }

            if ($record['type'] === 'witness') {
                if (!isset($record['hash']) || !is_string($record['hash'])) {
                    throw new \InvalidArgumentException('partial storage witness branch record is malformed');
                }
                self::assertHash($record['hash']);
                $branches[$nodeId] = [
                    'nodeId' => $nodeId,
                    'type' => 'witness',
                    'hash' => $record['hash'],
                ];
            } elseif ($record['type'] === 'branch') {
                if (!isset($record['leftNodeId'], $record['rightNodeId'], $record['hash'])
                    || !is_string($record['hash'])
                ) {
                    throw new \InvalidArgumentException('partial storage branch record is malformed');
                }
                self::assertHash($record['hash']);
                $leftNodeId = self::parseStorageChildNodeId($record['leftNodeId'], 'partial storage left child node id');
                $rightNodeId = self::parseStorageChildNodeId($record['rightNodeId'], 'partial storage right child node id');
                if ($leftNodeId === 0 && $rightNodeId === 0) {
                    throw new \InvalidArgumentException('partial storage branch must reference at least one child');
                }
                $branches[$nodeId] = [
                    'nodeId' => $nodeId,
                    'type' => 'branch',
                    'leftNodeId' => $leftNodeId,
                    'rightNodeId' => $rightNodeId,
                    'hash' => $record['hash'],
                ];
            } else {
                throw new \InvalidArgumentException('partial storage interior bucket contains an unknown node type');
            }

            $maxNodeId = max($maxNodeId, $nodeId);
        }

        $rootNodeId = self::parseStorageChildNodeId($snapshot['rootNodeId'], 'partial storage root node id');
        $visiting = [];
        $build = function (int $nodeId, int $depth) use (&$build, &$visiting, $leaves, $branches, $tree): array {
            if ($nodeId === 0) {
                return [
                    'nodeId' => 0,
                    'type' => 'empty',
                    'depth' => $depth,
                    'hash' => HashTree::EMPTY_HASH,
                ];
            }
            if (isset($visiting[$nodeId])) {
                throw new \InvalidArgumentException('partial storage snapshot contains a node cycle');
            }

            if (isset($leaves[$nodeId])) {
                return ['depth' => $depth] + $leaves[$nodeId];
            }
            if (!isset($branches[$nodeId])) {
                throw new \InvalidArgumentException('partial storage snapshot references an unknown node');
            }

            $record = $branches[$nodeId];
            if ($record['type'] === 'witness') {
                return ['depth' => $depth] + $record;
            }

            $visiting[$nodeId] = true;
            $left = $build($record['leftNodeId'], $depth + 1);
            $right = $build($record['rightNodeId'], $depth + 1);
            unset($visiting[$nodeId]);

            $expectedHash = $tree->hashTree->branchHash($left['hash'], $right['hash']);
            if ($record['hash'] !== $expectedHash) {
                throw new \InvalidArgumentException('partial storage branch hash does not match child nodes');
            }

            return [
                'nodeId' => $nodeId,
                'type' => 'branch',
                'depth' => $depth,
                'left' => $left,
                'right' => $right,
                'hash' => $record['hash'],
            ];
        };

        $tree->partialRoot = $build($rootNodeId, 0);
        $tree->nextPartialNodeId = max(
            TrackedNodeStore::FIRST_MEMSTORE_NODE_ID,
            $maxNodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID ? $maxNodeId + 1 : TrackedNodeStore::FIRST_MEMSTORE_NODE_ID
        );

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

            $fragmentRoot = self::importProofRoot($response, $this->hashTree, $request->startDepth, $this->nextPartialNodeId);

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
        $newRoot = self::importProofRoot($proof, $this->hashTree, 0, $this->nextPartialNodeId);
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

        $diffs = [];
        $this->appendLeafRecordDiffs($this->leafRecordMapForDiff(), $target->leafRecordMapForDiff(), $diffs);

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

        [$root] = $this->fullProofTree();

        return $root['hash'];
    }

    public function partialRootNodeId(): int
    {
        if ($this->partialRoot === null) {
            throw new \RuntimeException('partial tree root node id is unavailable');
        }

        return $this->nodeIdFromPartialNode($this->partialRoot);
    }

    /**
     * @return list<int>
     */
    public function partialNodeIds(): array
    {
        if ($this->partialRoot === null) {
            throw new \RuntimeException('partial tree node ids are unavailable');
        }

        $nodeIds = [];
        $this->collectPartialNodeIds($this->partialRoot, $nodeIds);
        $nodeIds = array_values(array_unique($nodeIds));
        sort($nodeIds, SORT_NUMERIC);

        return $nodeIds;
    }

    /**
     * Returns the imported partial tree as structured nodes suitable for
     * upstream-shaped storage projections.
     *
     * @return array{
     *     rootNodeId: int,
     *     leaves: array<int, array<string, mixed>>,
     *     branches: array<int, array<string, mixed>>
     * }
     */
    public function partialStorageSnapshot(): array
    {
        if ($this->partialRoot === null) {
            throw new \RuntimeException('partial tree storage snapshot is unavailable');
        }

        $leaves = [];
        $branches = [];
        $this->collectPartialStorageNodes($this->partialRoot, $leaves, $branches);
        ksort($leaves, SORT_NUMERIC);
        ksort($branches, SORT_NUMERIC);

        return [
            'rootNodeId' => $this->nodeIdFromPartialNode($this->partialRoot),
            'leaves' => $leaves,
            'branches' => $branches,
        ];
    }

    /**
     * @return array{numNodes: int, numLeafNodes: int, numBranchNodes: int, numWitnessNodes: int, maxDepth: int, numBytes: int}
     */
    public function stats(): array
    {
        if ($this->partialRoot !== null) {
            return $this->statsForPartialNode($this->partialRoot, 0);
        }

        [$tree] = $this->fullProofTree();

        return [
            'numNodes' => $tree['nodes'],
            'numLeafNodes' => $tree['leaves'],
            'numBranchNodes' => $tree['branches'],
            'numWitnessNodes' => 0,
            'maxDepth' => $tree['maxDepth'],
            'numBytes' => $tree['bytes'],
        ];
    }

    public function dumpText(): string
    {
        if ($this->partialRoot !== null) {
            return $this->dumpNodeText($this->partialRoot, 0);
        }

        [$tree] = $this->fullProofTree();

        return $this->dumpNodeText($tree, 0);
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
        if ($this->partialRoot !== null) {
            $records = [];
            $this->collectEnumerablePartialLeafRecords($this->partialRoot, $records);
            ksort($records, SORT_STRING);
            foreach ($records as $keyHashHex => $record) {
                $entries[] = new SparseTreeEntry(Key::fromHex($keyHashHex), $record['value'], $record['key']);
            }

            return $entries;
        }

        foreach ($this->entries() as $keyHashHex => $value) {
            $entries[] = new SparseTreeEntry(Key::fromHex($keyHashHex), $value, $this->trackedKeys[$keyHashHex] ?? null);
        }

        return $entries;
    }

    /**
     * @param array<string, array{delete: bool, value: string, key?: string}> $updates
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
            if (!isset($update['delete'], $update['value'])
                || !is_bool($update['delete'])
                || !is_string($update['value'])
                || (array_key_exists('key', $update) && !is_string($update['key']))
            ) {
                throw new \InvalidArgumentException('Malformed sparse tree update');
            }

            if ($update['delete']) {
                unset($this->values[$keyHashHex]);
                unset($this->trackedKeys[$keyHashHex]);
                continue;
            }

            $this->values[$keyHashHex] = $update['value'];
            if (array_key_exists('key', $update) && $update['key'] !== '') {
                $this->trackedKeys[$keyHashHex] = $update['key'];
            } else {
                unset($this->trackedKeys[$keyHashHex]);
            }
        }

        ksort($this->values, SORT_STRING);
        $this->fullProofTreeCache = null;

        return $this;
    }

    public static function assertNonEmptyKey(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('zero-length keys not allowed');
        }
    }

    private static function parseStorageNodeId(mixed $value, string $label): int
    {
        $nodeId = self::parseStorageChildNodeId($value, $label);
        if ($nodeId === 0) {
            throw new \InvalidArgumentException($label . ' must be positive');
        }

        return $nodeId;
    }

    private static function parseStorageChildNodeId(mixed $value, string $label): int
    {
        if (is_int($value)) {
            $nodeId = $value;
        } elseif (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value)) {
            $max = (string) PHP_INT_MAX;
            if (strlen($value) > strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
                throw new \InvalidArgumentException($label . ' exceeds PHP integer range');
            }

            $nodeId = (int) $value;
        } else {
            throw new \InvalidArgumentException($label . ' must be a non-negative integer');
        }

        if ($nodeId < 0) {
            throw new \InvalidArgumentException($label . ' must be non-negative');
        }

        return $nodeId;
    }

    /**
     * @param array<string, string> $keyHashes
     */
    private function exportProofForKeyHashes(array $keyHashes): Proof
    {
        [$root, $nodesById] = $this->proofTreeForExport();
        $items = [];
        $reverseMap = [];

        $this->exportProofAux($root, 0, $keyHashes, $items, $reverseMap);

        return new Proof(
            array_map(static fn (array $item): ProofStrand => $item['strand'], $items),
            $this->exportProofCommands($items, $reverseMap, $nodesById, $this->nodeIdFromPartialNode($root))
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function proofTreeForExport(): array
    {
        if ($this->partialRoot === null) {
            return $this->fullProofTree();
        }

        $nodesById = [];
        $this->indexProofNodes($this->partialRoot, $nodesById);

        return [$this->partialRoot, $nodesById];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function fullProofTree(): array
    {
        if ($this->fullProofTreeCache !== null) {
            return $this->fullProofTreeCache;
        }

        $records = [];
        foreach ($this->values as $keyHashHex => $value) {
            $records[] = [
                'keyHash' => $keyHashHex,
                'value' => $value,
                'key' => $this->trackedKeys[$keyHashHex] ?? '',
            ];
        }
        usort($records, static fn (array $a, array $b): int => $a['keyHash'] <=> $b['keyHash']);

        $nodesById = [];
        $nextId = 1;

        $this->fullProofTreeCache = [$this->buildFullNode($records, 0, $nextId, $nodesById), $nodesById];

        return $this->fullProofTreeCache;
    }

    /**
     * @param list<array{keyHash: string, value: string, key: string}> $records
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
                'nodes' => 0,
                'leaves' => 0,
                'branches' => 0,
                'maxDepth' => 0,
                'bytes' => 0,
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
                'key' => $records[0]['key'],
                'hash' => $this->hashTree->leafHashForKeyHash($records[0]['keyHash'], $records[0]['value']),
                'nodes' => 1,
                'leaves' => 1,
                'branches' => 0,
                'maxDepth' => $depth,
                'bytes' => 72 + strlen($records[0]['value']),
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
            'nodes' => 1 + $left['nodes'] + $right['nodes'],
            'leaves' => $left['leaves'] + $right['leaves'],
            'branches' => 1 + $left['branches'] + $right['branches'],
            'maxDepth' => max($depth, $left['maxDepth'], $right['maxDepth']),
            'bytes' => 48 + $left['bytes'] + $right['bytes'],
        ];
        $nodesById[$id] = $node;

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array{numNodes: int, numLeafNodes: int, numBranchNodes: int, numWitnessNodes: int, maxDepth: int, numBytes: int}
     */
    private function statsForPartialNode(array $node, int $depth): array
    {
        if ($node['type'] === 'empty') {
            return [
                'numNodes' => 0,
                'numLeafNodes' => 0,
                'numBranchNodes' => 0,
                'numWitnessNodes' => 0,
                'maxDepth' => 0,
                'numBytes' => 0,
            ];
        }

        if ($node['type'] === 'leaf') {
            return [
                'numNodes' => 1,
                'numLeafNodes' => 1,
                'numBranchNodes' => 0,
                'numWitnessNodes' => 0,
                'maxDepth' => $depth,
                'numBytes' => 72 + strlen($node['value']),
            ];
        }

        if ($node['type'] === 'witnessLeaf') {
            return [
                'numNodes' => 1,
                'numLeafNodes' => 0,
                'numBranchNodes' => 0,
                'numWitnessNodes' => 1,
                'maxDepth' => $depth,
                'numBytes' => 104,
            ];
        }

        if ($node['type'] === 'witness') {
            return [
                'numNodes' => 1,
                'numLeafNodes' => 0,
                'numBranchNodes' => 0,
                'numWitnessNodes' => 1,
                'maxDepth' => $depth,
                'numBytes' => 48,
            ];
        }

        if ($node['type'] === 'branch') {
            $left = $this->statsForPartialNode($node['left'], $depth + 1);
            $right = $this->statsForPartialNode($node['right'], $depth + 1);

            return [
                'numNodes' => 1 + $left['numNodes'] + $right['numNodes'],
                'numLeafNodes' => $left['numLeafNodes'] + $right['numLeafNodes'],
                'numBranchNodes' => 1 + $left['numBranchNodes'] + $right['numBranchNodes'],
                'numWitnessNodes' => $left['numWitnessNodes'] + $right['numWitnessNodes'],
                'maxDepth' => max($depth, $left['maxDepth'], $right['maxDepth']),
                'numBytes' => 48 + $left['numBytes'] + $right['numBytes'],
            ];
        }

        throw new \RuntimeException('unrecognized partial tree node type');
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

        $nodeId = $this->nodeIdFromPartialNode($node);

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

        if ($node['type'] === 'leaf' || $node['type'] === 'witnessLeaf') {
            if (array_key_exists($node['keyHash'], $keyHashes)) {
                if ($node['type'] === 'witnessLeaf') {
                    throw new \RuntimeException('incomplete tree, missing leaf to make proof');
                }

                $leafKey = $node['key'] ?? $keyHashes[$node['keyHash']];

                $items[] = [
                    'nodeId' => $nodeId,
                    'parentNodeId' => $parentNodeId,
                    'strand' => new ProofStrand(ProofStrand::LEAF, $node['depth'], $node['keyHash'], $node['value'], $leafKey),
                ];
                return;
            }

            $items[] = [
                'nodeId' => $nodeId,
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(
                    ProofStrand::WITNESS_LEAF,
                    $node['depth'],
                    $node['keyHash'],
                    $node['type'] === 'witnessLeaf' ? $node['valueHash'] : $this->hashTree->valueHash($node['value'])
                ),
            ];
            return;
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('encountered witness node: incomplete tree');
        }

        $leftNodeId = $this->nodeIdFromPartialNode($node['left']);
        $rightNodeId = $this->nodeIdFromPartialNode($node['right']);

        if ($leftNodeId !== 0) {
            $reverseMap[$leftNodeId] = $nodeId;
        }
        if ($rightNodeId !== 0) {
            $reverseMap[$rightNodeId] = $nodeId;
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

        if ($leftNodeId !== 0 || $rightKeys === []) {
            $this->exportProofAux($node['left'], $nodeId, $leftKeys, $items, $reverseMap);
        }
        if ($rightNodeId !== 0 || $leftKeys === []) {
            $this->exportProofAux($node['right'], $nodeId, $rightKeys, $items, $reverseMap);
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

        $nodeId = $this->nodeIdFromPartialNode($node);

        if ($node['type'] === 'leaf') {
            $items[] = [
                'nodeId' => $nodeId,
                'parentNodeId' => $parentNodeId,
                'strand' => new ProofStrand(ProofStrand::LEAF, $node['depth'], $node['keyHash'], $node['value'], $node['key'] ?? ''),
            ];
            return;
        }

        if ($node['type'] === 'witnessLeaf') {
            throw new \RuntimeException('incomplete tree, missing leaf to make proof');
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('encountered witness node: incomplete tree');
        }

        $leftNodeId = $this->nodeIdFromPartialNode($node['left']);
        $rightNodeId = $this->nodeIdFromPartialNode($node['right']);

        if ($leftNodeId !== 0) {
            $reverseMap[$leftNodeId] = $nodeId;
        }
        if ($rightNodeId !== 0) {
            $reverseMap[$rightNodeId] = $nodeId;
        }

        $boundary = clone $currentPath;
        $boundary->setBit($node['depth'], 1);
        $doLeft = strcmp($begin->hex(), $boundary->hex()) < 0;
        $doRight = strcmp($end->hex(), $boundary->hex()) >= 0;

        $currentPath->setBit($node['depth'], 0);
        if ($doLeft) {
            $this->exportProofRangeAux($node['left'], $nodeId, $begin, $end, $currentPath, $items, $reverseMap);
        }

        $currentPath->setBit($node['depth'], 1);
        if ($doRight) {
            $this->exportProofRangeAux($node['right'], $nodeId, $begin, $end, $currentPath, $items, $reverseMap);
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
                    'strand' => new ProofStrand(ProofStrand::LEAF, $node['depth'], $node['keyHash'], $node['value'], $node['key'] ?? ''),
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
                $siblingNode = $this->nodeIdFromPartialNode($parentNode['left']) === $accums[$i]['nodeId'] ? $parentNode['right'] : $parentNode['left'];

                if ($this->nodeIdFromPartialNode($siblingNode) !== 0) {
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
     * @param array<string, array{delete: bool, value: string, key?: string}> $updates
     *
     * @return array{node: array<string, mixed>, bubble: bool}
     */
    private function applyPartialUpdates(array $node, array $updates): array
    {
        foreach ($updates as $keyHashHex => $update) {
            self::assertHash($keyHashHex);
            if (!isset($update['delete'], $update['value'])
                || !is_bool($update['delete'])
                || !is_string($update['value'])
                || (array_key_exists('key', $update) && !is_string($update['key']))
            ) {
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
     * @param array<string, array{delete: bool, value: string, key?: string}> $updates
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
                'node' => $this->leafPartialNode($nodeKeyHash, $update['value'], $node['depth'], $update['key'] ?? ($node['key'] ?? '')),
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
     * @param array<string, array{delete: bool, value: string|array<string, mixed>, key?: string}> $updates
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

            return $this->leafPartialNode($keyHashHex, $update['value'], $depth, $update['key'] ?? '');
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
    private function leafPartialNode(string $keyHashHex, string $value, int $depth, string $key = ''): array
    {
        self::assertHash($keyHashHex);

        return [
            'nodeId' => $this->allocatePartialNodeId(),
            'type' => 'leaf',
            'depth' => $depth,
            'keyHash' => $keyHashHex,
            'value' => $value,
            'key' => $key,
            'hash' => $this->hashTree->leafHashForKeyHash($keyHashHex, $value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPartialNode(int $depth): array
    {
        return [
            'nodeId' => 0,
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
    private function branchPartialNode(int $depth, array $left, array $right, int $nodeId = 0): array
    {
        return [
            'nodeId' => $nodeId !== 0 ? $nodeId : $this->allocatePartialNodeId(),
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

            return $this->branchPartialNode($original['depth'], $left, $right, $this->nodeIdFromPartialNode($original));
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
                $node['right'],
                $this->allocatePartialNodeId()
            );
        }

        return $this->branchPartialNode(
            $node['depth'],
            $node['left'],
            $this->replaceSyncFragment($node['right'], $path, $depth + 1, $targetDepth, $fragmentRoot),
            $this->allocatePartialNodeId()
        );
    }

    /**
     * @return array<string, array{value: string, nodeId: int}>
     */
    private function leafRecordMapForDiff(): array
    {
        if ($this->partialRoot === null) {
            $entries = [];
            foreach ($this->entries() as $keyHashHex => $value) {
                $entries[$keyHashHex] = [
                    'value' => $value,
                    'nodeId' => 0,
                ];
            }

            return $entries;
        }

        $entries = [];
        $this->collectFullPartialLeafRecords($this->partialRoot, $entries);
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

        if ($ours['type'] === 'branch' && $this->isPartialLeaf($theirs)) {
            $empty = $this->emptyPartialNode($ours['depth'] + 1);
            if ($this->hashTree->bitAt($theirs['keyHash'], $ours['depth']) === 0) {
                $this->diffNodeToPartial($ours['left'], $theirs, $diffs);
                $this->diffNodeToPartial($ours['right'], $empty, $diffs);
            } else {
                $this->diffNodeToPartial($ours['left'], $empty, $diffs);
                $this->diffNodeToPartial($ours['right'], $theirs, $diffs);
            }

            return;
        }

        if ($this->isPartialLeaf($ours) && $theirs['type'] === 'branch') {
            $empty = $this->emptyPartialNode($theirs['depth'] + 1);
            if ($this->hashTree->bitAt($ours['keyHash'], $theirs['depth']) === 0) {
                $this->diffNodeToPartial($ours, $theirs['left'], $diffs);
                $this->diffNodeToPartial($empty, $theirs['right'], $diffs);
            } else {
                $this->diffNodeToPartial($empty, $theirs['left'], $diffs);
                $this->diffNodeToPartial($ours, $theirs['right'], $diffs);
            }

            return;
        }

        if ($ours['type'] === 'branch' && $theirs['type'] === 'empty') {
            $empty = $this->emptyPartialNode($ours['depth'] + 1);
            $this->diffNodeToPartial($ours['left'], $empty, $diffs);
            $this->diffNodeToPartial($ours['right'], $empty, $diffs);
            return;
        }

        if ($ours['type'] === 'empty' && $theirs['type'] === 'branch') {
            $empty = $this->emptyPartialNode($theirs['depth'] + 1);
            $this->diffNodeToPartial($empty, $theirs['left'], $diffs);
            $this->diffNodeToPartial($empty, $theirs['right'], $diffs);
            return;
        }

        $this->appendLeafRecordDiffs(
            $this->leafRecordMapFromNode($ours),
            $this->leafRecordMapFromNode($theirs),
            $diffs
        );
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, array{value: string, nodeId: int}>
     */
    private function leafRecordMapFromNode(array $node): array
    {
        $entries = [];
        $this->collectFullPartialLeafRecords($node, $entries);
        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @param array<string, array{value: string, nodeId: int}> $ours
     * @param array<string, array{value: string, nodeId: int}> $theirs
     * @param list<DiffEntry> $diffs
     */
    private function appendLeafRecordDiffs(array $ours, array $theirs, array &$diffs): void
    {
        $keys = array_values(array_unique(array_merge(array_keys($ours), array_keys($theirs))));
        sort($keys, SORT_STRING);

        foreach ($keys as $keyHashHex) {
            $oursHas = array_key_exists($keyHashHex, $ours);
            $theirsHas = array_key_exists($keyHashHex, $theirs);

            if (!$oursHas && $theirsHas) {
                $diffs[] = new DiffEntry(DiffEntry::ADDED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]['value'], $theirs[$keyHashHex]['nodeId']);
            } elseif ($oursHas && !$theirsHas) {
                $diffs[] = new DiffEntry(DiffEntry::DELETED, Key::fromHex($keyHashHex), $ours[$keyHashHex]['value'], $ours[$keyHashHex]['nodeId']);
            } elseif ($oursHas && $theirsHas && $ours[$keyHashHex]['value'] !== $theirs[$keyHashHex]['value']) {
                $diffs[] = new DiffEntry(DiffEntry::CHANGED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]['value'], $theirs[$keyHashHex]['nodeId']);
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array{value: string, key: ?string}> $entries
     */
    private function collectEnumerablePartialLeafRecords(array $node, array &$entries): void
    {
        if ($node['type'] === 'empty' || $this->isWitnessAny($node)) {
            return;
        }

        if ($node['type'] === 'leaf') {
            $entries[$node['keyHash']] = [
                'value' => $node['value'],
                'key' => ($node['key'] ?? '') !== '' ? $node['key'] : null,
            ];
            return;
        }

        if ($node['type'] === 'branch') {
            $this->collectEnumerablePartialLeafRecords($node['left'], $entries);
            $this->collectEnumerablePartialLeafRecords($node['right'], $entries);
            return;
        }

        throw new \RuntimeException('unrecognized partial tree node type');
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array{value: string, nodeId: int}> $entries
     */
    private function collectFullPartialLeafRecords(array $node, array &$entries): void
    {
        if ($node['type'] === 'empty') {
            return;
        }

        if ($node['type'] === 'leaf') {
            $entries[$node['keyHash']] = [
                'value' => $node['value'],
                'nodeId' => $this->nodeIdFromPartialNode($node),
            ];
            return;
        }

        if ($node['type'] === 'branch') {
            $this->collectFullPartialLeafRecords($node['left'], $entries);
            $this->collectFullPartialLeafRecords($node['right'], $entries);
            return;
        }

        if ($this->isWitnessAny($node)) {
            throw new \RuntimeException('encountered witness during diff');
        }

        throw new \RuntimeException('unrecognized partial tree node type');
    }

    /**
     * @param array<string, mixed> $node
     * @param list<int> $nodeIds
     */
    private function collectPartialNodeIds(array $node, array &$nodeIds): void
    {
        $nodeId = $this->nodeIdFromPartialNode($node);
        if ($nodeId !== 0) {
            $nodeIds[] = $nodeId;
        }

        if ($node['type'] === 'branch') {
            $this->collectPartialNodeIds($node['left'], $nodeIds);
            $this->collectPartialNodeIds($node['right'], $nodeIds);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array<string, mixed>> $leaves
     * @param array<int, array<string, mixed>> $branches
     */
    private function collectPartialStorageNodes(array $node, array &$leaves, array &$branches): void
    {
        $nodeId = $this->nodeIdFromPartialNode($node);

        if ($node['type'] === 'empty') {
            return;
        }

        if ($node['type'] === 'leaf') {
            $record = [
                'type' => 'leaf',
                'keyHash' => $node['keyHash'],
                'value' => $node['value'],
                'hash' => $node['hash'],
            ];
            if (($node['key'] ?? '') !== '') {
                $record['key'] = $node['key'];
            }
            $leaves[$nodeId] = $record;

            return;
        }

        if ($node['type'] === 'witnessLeaf') {
            $leaves[$nodeId] = [
                'type' => 'witnessLeaf',
                'keyHash' => $node['keyHash'],
                'valueHash' => $node['valueHash'],
                'hash' => $node['hash'],
            ];

            return;
        }

        if ($node['type'] === 'witness') {
            $branches[$nodeId] = [
                'type' => 'witness',
                'hash' => $node['hash'],
            ];

            return;
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('unrecognized partial tree node type');
        }

        $this->collectPartialStorageNodes($node['left'], $leaves, $branches);
        $this->collectPartialStorageNodes($node['right'], $leaves, $branches);
        $branches[$nodeId] = [
            'type' => 'branch',
            'leftNodeId' => $this->nodeIdFromPartialNode($node['left']),
            'rightNodeId' => $this->nodeIdFromPartialNode($node['right']),
            'hash' => $node['hash'],
        ];
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

    private function allocatePartialNodeId(): int
    {
        return $this->nextPartialNodeId++;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array<string, mixed>> $nodesById
     */
    private function indexProofNodes(array $node, array &$nodesById): void
    {
        $nodeId = $this->nodeIdFromPartialNode($node);
        if ($nodeId !== 0) {
            $nodesById[$nodeId] = $node;
        }

        if ($node['type'] === 'branch') {
            $this->indexProofNodes($node['left'], $nodesById);
            $this->indexProofNodes($node['right'], $nodesById);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function nodeIdFromPartialNode(array $node): int
    {
        return (int) ($node['nodeId'] ?? $node['id'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function importProofRoot(Proof $proof, HashTree $hashTree, int $expectedDepth, int &$nextNodeId): array
    {
        $accums = [];

        $allocateNodeId = static function () use (&$nextNodeId): int {
            return $nextNodeId++;
        };

        foreach ($proof->strands as $index => $strand) {
            self::assertHash($strand->keyHash);
            $next = $index + 1;

            if ($strand->type === ProofStrand::LEAF) {
                $node = [
                    'nodeId' => $allocateNodeId(),
                    'type' => 'leaf',
                    'depth' => $strand->depth,
                    'keyHash' => $strand->keyHash,
                    'value' => $strand->value,
                    'key' => $strand->key,
                    'hash' => $hashTree->leafHashForKeyHash($strand->keyHash, $strand->value),
                ];
            } elseif ($strand->type === ProofStrand::WITNESS_LEAF) {
                self::assertHash($strand->value);
                $node = [
                    'nodeId' => $allocateNodeId(),
                    'type' => 'witnessLeaf',
                    'depth' => $strand->depth,
                    'keyHash' => $strand->keyHash,
                    'valueHash' => $strand->value,
                    'hash' => $hashTree->leafHashForKeyHashAndValueHash($strand->keyHash, $strand->value),
                ];
            } elseif ($strand->type === ProofStrand::WITNESS_EMPTY) {
                $node = [
                    'nodeId' => 0,
                    'type' => 'empty',
                    'depth' => $strand->depth,
                    'keyHash' => $strand->keyHash,
                    'hash' => HashTree::EMPTY_HASH,
                ];
            } elseif ($strand->type === ProofStrand::WITNESS) {
                self::assertHash($strand->value);
                $node = [
                    'nodeId' => $allocateNodeId(),
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
                    'nodeId' => $allocateNodeId(),
                    'type' => 'witness',
                    'depth' => $accum['depth'],
                    'hash' => $command->hash,
                ];
            } elseif ($command->operation === ProofCommand::HASH_EMPTY) {
                $sibling = [
                    'nodeId' => 0,
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
                'nodeId' => $allocateNodeId(),
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

    /**
     * @param array<string, mixed> $node
     */
    private function dumpNodeText(array $node, int $depth): string
    {
        $output = str_repeat(' ', $depth * 2)
            . self::renderAbbreviatedNode((string) $node['hash'], $this->nodeIdFromPartialNode($node))
            . ' ';

        if ($node['type'] === 'empty') {
            return $output . "empty\n";
        }

        if ($node['type'] === 'leaf') {
            $renderedKey = ($node['key'] ?? '') !== ''
                ? (string) $node['key']
                : self::renderUnknownKey((string) $node['keyHash']);

            return $output . 'leaf: ' . $renderedKey . ' = ' . $node['value'] . "\n";
        }

        if ($node['type'] === 'witnessLeaf') {
            return $output . 'witness leaf: 0x' . $node['keyHash']
                . ' hash(val) = 0x' . $node['valueHash'] . "\n";
        }

        if ($node['type'] === 'witness') {
            return $output . "witness\n";
        }

        if ($node['type'] !== 'branch') {
            throw new \RuntimeException('unrecognized sparse tree node type');
        }

        return $output . "branch:\n"
            . $this->dumpNodeText($node['left'], $depth + 1)
            . $this->dumpNodeText($node['right'], $depth + 1);
    }

    private static function renderUnknownKey(string $keyHex): string
    {
        self::assertHash($keyHex);

        return 'H(?)=0x' . substr($keyHex, 0, 12) . '...';
    }

    private static function renderAbbreviatedNode(string $hashHex, int $nodeId): string
    {
        self::assertHash($hashHex);

        return '0x' . substr($hashHex, 0, 8) . '... (' . $nodeId . ')';
    }

    private static function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }
}
