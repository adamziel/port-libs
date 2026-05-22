<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class TrackedSparseTree
{
    private HashTree $hashTree;

    /**
     * @var array<string, string>
     */
    private array $values = [];

    /**
     * @var array<string, int>
     */
    private array $leafNodeIds = [];

    private int $headNodeId = 0;

    public function __construct(
        private readonly TrackedNodeStore $nodeStore = new TrackedNodeStore(),
        ?HashTree $hashTree = null,
        int $headNodeId = 0,
        private readonly bool $writeToMemStore = false,
        private readonly ?string $headName = null
    ) {
        $this->hashTree = $hashTree ?? new HashTree();
        if ($this->headName !== null) {
            $this->loadHeadNode($this->nodeStore->headNodeId($this->headName));
        } elseif ($headNodeId !== 0) {
            $this->loadHeadNode($headNodeId);
        }
    }

    public static function memoryOnly(?HashTree $hashTree = null): self
    {
        return new self(new TrackedNodeStore(), $hashTree, 0, true);
    }

    public function checkoutEmpty(): self
    {
        return $this->checkout();
    }

    public function checkout(int|string $nodeId = 0): self
    {
        if (is_string($nodeId)) {
            return new self($this->nodeStore, $this->hashTree, 0, $this->writeToMemStore, $nodeId);
        }

        return new self($this->nodeStore, $this->hashTree, $nodeId, $this->writeToMemStore);
    }

    public function withMemStoreWrites(bool $enabled = true): self
    {
        return new self($this->nodeStore, $this->hashTree, $this->headNodeId, $enabled, $this->headName);
    }

    public function writesToMemStore(): bool
    {
        return $this->writeToMemStore;
    }

    public function isDetachedHead(): bool
    {
        return $this->headName === null;
    }

    public function headName(): string
    {
        if ($this->headName === null) {
            throw new \RuntimeException('in detached head mode');
        }

        return $this->headName;
    }

    public function fork(?string $newHead = null): self
    {
        if ($newHead === null) {
            return $this->checkout($this->headNodeId);
        }

        $fork = new self($this->nodeStore, $this->hashTree, 0, $this->writeToMemStore, $newHead);
        $fork->loadHeadNode($this->headNodeId);
        $fork->persistNamedHead();

        return $fork;
    }

    public function change(): TrackedChangeSet
    {
        return new TrackedChangeSet($this);
    }

    public function put(string $key, string $value, ?int &$outputNodeId = null): self
    {
        return $this->change()->put($key, $value, $outputNodeId)->apply();
    }

    public function putKey(Key $key, string $value, ?int &$outputNodeId = null): self
    {
        return $this->change()->putKey($key, $value, $outputNodeId)->apply();
    }

    public function delete(string $key, ?int &$outputNodeId = null): self
    {
        return $this->change()->delete($key, $outputNodeId)->apply();
    }

    public function deleteKey(Key $key, ?int &$outputNodeId = null): self
    {
        return $this->change()->deleteKey($key, $outputNodeId)->apply();
    }

    public function get(string $key, ?int &$outputNodeId = null): ?string
    {
        SparseTree::assertNonEmptyKey($key);

        return $this->getByKeyHash($this->hashTree->keyHash($key), $outputNodeId);
    }

    public function getKey(Key $key, ?int &$outputNodeId = null): ?string
    {
        return $this->getByKeyHash($key->hex(), $outputNodeId);
    }

    public function headNodeId(): int
    {
        return $this->headNodeId;
    }

    public function leafValueForNodeId(int $nodeId): string
    {
        return $this->nodeStore->leaf($nodeId)['value'];
    }

    public function nodeHash(int $nodeId): string
    {
        return $this->nodeStore->nodeHash($nodeId);
    }

    /**
     * @return array{leftNodeId: int, rightNodeId: int}
     */
    public function branchChildren(int $nodeId): array
    {
        $branch = $this->nodeStore->branch($nodeId);

        return [
            'leftNodeId' => $branch['leftNodeId'],
            'rightNodeId' => $branch['rightNodeId'],
        ];
    }

    /**
     * @return list<int>
     */
    public function walkNodeIds(): array
    {
        $nodeIds = [];
        $this->walkNodeIdsFrom($this->headNodeId, $nodeIds);

        return $nodeIds;
    }

    public function rootHash(): string
    {
        return $this->nodeStore->nodeHash($this->headNodeId);
    }

    /**
     * @return array{numNodes: int, numLeafNodes: int, numBranchNodes: int, maxDepth: int}
     */
    public function stats(): array
    {
        return $this->statsForNode($this->headNodeId, 0);
    }

    public function iterate(Key $target, bool $reverse = false): TrackedSparseTreeIterator
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

            return new TrackedSparseTreeIterator($entries, $position, true);
        }

        $position = count($entries);
        foreach ($entries as $index => $entry) {
            if (strcmp($entry->keyHex(), $targetHex) >= 0) {
                $position = $index;
                break;
            }
        }

        return new TrackedSparseTreeIterator($entries, $position, false);
    }

    /**
     * @return list<TrackedSparseTreeEntry>
     */
    public function orderedEntries(): array
    {
        $entries = [];
        $keys = array_keys($this->values);
        sort($keys, SORT_STRING);

        foreach ($keys as $keyHashHex) {
            $entries[] = new TrackedSparseTreeEntry(
                Key::fromHex($keyHashHex),
                $this->values[$keyHashHex],
                $this->leafNodeIds[$keyHashHex]
            );
        }

        return $entries;
    }

    /**
     * @return list<DiffEntry>
     */
    public function diffTo(TrackedSparseTree $target, ?callable $onDiff = null): array
    {
        $ours = $this->leafRecordsByKeyHash();
        $theirs = $target->leafRecordsByKeyHash();
        $keys = array_values(array_unique(array_merge(array_keys($ours), array_keys($theirs))));
        sort($keys, SORT_STRING);

        $diffs = [];
        foreach ($keys as $keyHashHex) {
            $oursHas = array_key_exists($keyHashHex, $ours);
            $theirsHas = array_key_exists($keyHashHex, $theirs);

            if (!$oursHas && $theirsHas) {
                $diff = new DiffEntry(DiffEntry::ADDED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]['value'], $theirs[$keyHashHex]['nodeId']);
            } elseif ($oursHas && !$theirsHas) {
                $diff = new DiffEntry(DiffEntry::DELETED, Key::fromHex($keyHashHex), $ours[$keyHashHex]['value'], $ours[$keyHashHex]['nodeId']);
            } elseif ($oursHas && $theirsHas && $ours[$keyHashHex]['value'] !== $theirs[$keyHashHex]['value']) {
                $diff = new DiffEntry(DiffEntry::CHANGED, Key::fromHex($keyHashHex), $theirs[$keyHashHex]['value'], $theirs[$keyHashHex]['nodeId']);
            } else {
                continue;
            }

            $diffs[] = $diff;
            if ($onDiff !== null) {
                $onDiff($diff);
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

    /**
     * @param array<string, array{delete: bool, value: string, reuseNodeId: int, outputNodeId: ?int}> $updates
     */
    public function applyTrackedUpdates(array $updates): self
    {
        ksort($updates, SORT_STRING);
        $oldHeadNodeId = $this->headNodeId;
        $oldValues = $this->values;
        $oldLeafNodeIds = $this->leafNodeIds;

        foreach ($updates as &$update) {
            if (!array_key_exists('outputNodeId', $update)) {
                continue;
            }

            $update['outputNodeId'] = 0;
        }
        unset($update);

        $changed = false;

        foreach ($updates as $keyHashHex => &$update) {
            self::assertHash($keyHashHex);
            if (!isset($update['delete'], $update['value'], $update['reuseNodeId'])
                || !is_bool($update['delete'])
                || !is_string($update['value'])
                || !is_int($update['reuseNodeId'])
            ) {
                throw new \InvalidArgumentException('Malformed tracked sparse tree update');
            }

            if ($update['delete']) {
                if (isset($this->leafNodeIds[$keyHashHex])) {
                    $update['outputNodeId'] = $this->leafNodeIds[$keyHashHex];
                    unset($this->leafNodeIds[$keyHashHex], $this->values[$keyHashHex]);
                    $changed = true;
                }
                continue;
            }

            if ($update['reuseNodeId'] !== 0) {
                $leaf = $this->nodeStore->leaf($update['reuseNodeId']);
                if ($leaf['keyHash'] !== $keyHashHex) {
                    throw new \RuntimeException('non-matching leaf key when re-using leaf node');
                }

                $update['outputNodeId'] = $update['reuseNodeId'];
                if (($this->values[$keyHashHex] ?? null) !== $leaf['value']
                    || ($this->leafNodeIds[$keyHashHex] ?? 0) !== $update['reuseNodeId']
                ) {
                    $this->values[$keyHashHex] = $leaf['value'];
                    $this->leafNodeIds[$keyHashHex] = $update['reuseNodeId'];
                    $changed = true;
                }
                continue;
            }

            if (isset($this->values[$keyHashHex]) && $this->values[$keyHashHex] === $update['value']) {
                continue;
            }

            $nodeId = $this->nodeStore->createLeaf(
                $keyHashHex,
                $update['value'],
                $this->hashTree->leafHashForKeyHash($keyHashHex, $update['value']),
                $this->writeToMemStore
            );
            $update['outputNodeId'] = $nodeId;
            $this->values[$keyHashHex] = $update['value'];
            $this->leafNodeIds[$keyHashHex] = $nodeId;
            $changed = true;
        }
        unset($update);

        if ($changed) {
            $this->refreshHeadNodeId($oldHeadNodeId);
            if ($this->headName !== null && $this->headNodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID) {
                $this->values = $oldValues;
                $this->leafNodeIds = $oldLeafNodeIds;
                $this->headNodeId = $oldHeadNodeId;
                throw new \RuntimeException('attempted to store MemStore node into LMDB');
            }

            $this->persistNamedHead();
        }

        return $this;
    }

    private function getByKeyHash(string $keyHashHex, ?int &$outputNodeId): ?string
    {
        self::assertHash($keyHashHex);
        $outputNodeId = $this->leafNodeIds[$keyHashHex] ?? 0;

        return $this->values[$keyHashHex] ?? null;
    }

    /**
     * @return array<string, array{value: string, nodeId: int}>
     */
    private function leafRecordsByKeyHash(): array
    {
        $records = [];
        foreach ($this->values as $keyHashHex => $value) {
            $records[$keyHashHex] = [
                'value' => $value,
                'nodeId' => $this->leafNodeIds[$keyHashHex],
            ];
        }
        ksort($records, SORT_STRING);

        return $records;
    }

    private function refreshHeadNodeId(int $oldHeadNodeId): void
    {
        $records = [];
        foreach ($this->values as $keyHashHex => $value) {
            $records[] = [
                'keyHash' => $keyHashHex,
                'value' => $value,
                'nodeId' => $this->leafNodeIds[$keyHashHex],
            ];
        }
        usort($records, static fn (array $a, array $b): int => $a['keyHash'] <=> $b['keyHash']);

        $this->headNodeId = $this->buildTrackedNode($records, 0, $oldHeadNodeId);
    }

    /**
     * @param list<array{keyHash: string, value: string, nodeId: int}> $records
     */
    private function buildTrackedNode(array $records, int $depth, int $oldNodeId): int
    {
        $count = count($records);
        if ($count === 0) {
            return 0;
        }

        if ($count === 1) {
            return $records[0]['nodeId'];
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

        $oldLeftNodeId = 0;
        $oldRightNodeId = 0;
        if ($oldNodeId !== 0 && $this->nodeStore->isBranch($oldNodeId)) {
            $oldBranch = $this->nodeStore->branch($oldNodeId);
            $oldLeftNodeId = $oldBranch['leftNodeId'];
            $oldRightNodeId = $oldBranch['rightNodeId'];
        }

        $leftNodeId = $this->buildTrackedNode($leftRecords, $depth + 1, $oldLeftNodeId);
        $rightNodeId = $this->buildTrackedNode($rightRecords, $depth + 1, $oldRightNodeId);

        if ($oldNodeId !== 0
            && $this->nodeStore->isBranch($oldNodeId)
            && $oldLeftNodeId === $leftNodeId
            && $oldRightNodeId === $rightNodeId
        ) {
            return $oldNodeId;
        }

        return $this->nodeStore->createBranch(
            $leftNodeId,
            $rightNodeId,
            $this->hashTree->branchHash(
                $this->nodeStore->nodeHash($leftNodeId),
                $this->nodeStore->nodeHash($rightNodeId)
            ),
            $this->writeToMemStore
        );
    }

    private function loadHeadNode(int $nodeId): void
    {
        if ($nodeId === 0) {
            $this->values = [];
            $this->leafNodeIds = [];
            $this->headNodeId = 0;
            return;
        }

        $this->values = [];
        $this->leafNodeIds = [];
        $this->loadNode($nodeId);
        ksort($this->values, SORT_STRING);
        ksort($this->leafNodeIds, SORT_STRING);
        $this->headNodeId = $nodeId;
    }

    private function persistNamedHead(): void
    {
        if ($this->headName !== null) {
            if ($this->headNodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID) {
                throw new \RuntimeException('attempted to store MemStore node into LMDB');
            }

            $this->nodeStore->setHeadNodeId($this->headName, $this->headNodeId);
        }
    }

    private function loadNode(int $nodeId): void
    {
        if ($nodeId === 0) {
            return;
        }

        if ($this->nodeStore->isLeaf($nodeId)) {
            $leaf = $this->nodeStore->leaf($nodeId);
            $this->values[$leaf['keyHash']] = $leaf['value'];
            $this->leafNodeIds[$leaf['keyHash']] = $nodeId;

            return;
        }

        if (!$this->nodeStore->isBranch($nodeId)) {
            throw new \InvalidArgumentException('unknown tracked node id');
        }

        $branch = $this->nodeStore->branch($nodeId);
        $this->loadNode($branch['leftNodeId']);
        $this->loadNode($branch['rightNodeId']);
    }

    /**
     * @param list<int> $nodeIds
     */
    private function walkNodeIdsFrom(int $nodeId, array &$nodeIds): void
    {
        if ($nodeId === 0) {
            return;
        }

        $nodeIds[] = $nodeId;
        if (!$this->nodeStore->isBranch($nodeId)) {
            return;
        }

        $branch = $this->nodeStore->branch($nodeId);
        $this->walkNodeIdsFrom($branch['leftNodeId'], $nodeIds);
        $this->walkNodeIdsFrom($branch['rightNodeId'], $nodeIds);
    }

    /**
     * @return array{numNodes: int, numLeafNodes: int, numBranchNodes: int, maxDepth: int}
     */
    private function statsForNode(int $nodeId, int $depth): array
    {
        if ($nodeId === 0) {
            return [
                'numNodes' => 0,
                'numLeafNodes' => 0,
                'numBranchNodes' => 0,
                'maxDepth' => 0,
            ];
        }

        if ($this->nodeStore->isLeaf($nodeId)) {
            return [
                'numNodes' => 1,
                'numLeafNodes' => 1,
                'numBranchNodes' => 0,
                'maxDepth' => $depth,
            ];
        }

        $branch = $this->nodeStore->branch($nodeId);
        $left = $this->statsForNode($branch['leftNodeId'], $depth + 1);
        $right = $this->statsForNode($branch['rightNodeId'], $depth + 1);

        return [
            'numNodes' => 1 + $left['numNodes'] + $right['numNodes'],
            'numLeafNodes' => $left['numLeafNodes'] + $right['numLeafNodes'],
            'numBranchNodes' => 1 + $left['numBranchNodes'] + $right['numBranchNodes'],
            'maxDepth' => max($depth, $left['maxDepth'], $right['maxDepth']),
        ];
    }

    private static function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }
}
