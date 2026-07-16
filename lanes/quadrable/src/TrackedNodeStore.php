<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class TrackedNodeStore
{
    public const FIRST_INTERIOR_NODE_ID = 288230376151711744;
    public const FIRST_MEMSTORE_NODE_ID = 576460752303423488;

    /**
     * @var array<int, array{keyHash: string, value: string, hash: string}>
     */
    private array $leaves = [];

    /**
     * @var array<int, array{leftNodeId: int, rightNodeId: int, hash: string}>
     */
    private array $branches = [];

    /**
     * @var array<string, int>
     */
    private array $heads = [];

    private int $nextLeafNodeId = 1;
    private int $nextBranchNodeId = self::FIRST_INTERIOR_NODE_ID;
    private int $nextMemStoreNodeId = self::FIRST_MEMSTORE_NODE_ID;

    /**
     * @return array{
     *     leaves: array<int, array{keyHash: string, value: string, hash: string}>,
     *     branches: array<int, array{leftNodeId: int, rightNodeId: int, hash: string}>,
     *     heads: array<int|string, int>,
     *     nextLeafNodeId: int,
     *     nextBranchNodeId: int,
     *     nextMemStoreNodeId: int
     * }
     */
    public function exportSnapshot(): array
    {
        return [
            'leaves' => $this->leaves,
            'branches' => $this->branches,
            'heads' => $this->heads,
            'nextLeafNodeId' => $this->nextLeafNodeId,
            'nextBranchNodeId' => $this->nextBranchNodeId,
            'nextMemStoreNodeId' => $this->nextMemStoreNodeId,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        foreach (['leaves', 'branches', 'heads'] as $requiredKey) {
            if (!isset($snapshot[$requiredKey]) || !is_array($snapshot[$requiredKey])) {
                throw new \InvalidArgumentException('tracked node store snapshot is missing ' . $requiredKey);
            }
        }

        $store = new self();
        $maxRegularLeafNodeId = 0;
        $maxRegularBranchNodeId = self::FIRST_INTERIOR_NODE_ID - 1;
        $maxMemStoreNodeId = self::FIRST_MEMSTORE_NODE_ID - 1;

        foreach ($snapshot['leaves'] as $nodeIdRaw => $leaf) {
            $nodeId = self::parsePositiveNodeId($nodeIdRaw, 'leaf node id');
            if ($nodeId >= self::FIRST_INTERIOR_NODE_ID && $nodeId < self::FIRST_MEMSTORE_NODE_ID) {
                throw new \InvalidArgumentException('leaf node id is in the branch node range');
            }
            if (!is_array($leaf)
                || !isset($leaf['keyHash'], $leaf['value'], $leaf['hash'])
                || !is_string($leaf['keyHash'])
                || !is_string($leaf['value'])
                || !is_string($leaf['hash'])
            ) {
                throw new \InvalidArgumentException('tracked node store snapshot has a malformed leaf');
            }
            self::assertHash($leaf['keyHash']);
            self::assertHash($leaf['hash']);

            $store->leaves[$nodeId] = [
                'keyHash' => $leaf['keyHash'],
                'value' => $leaf['value'],
                'hash' => $leaf['hash'],
            ];

            if ($nodeId >= self::FIRST_MEMSTORE_NODE_ID) {
                $maxMemStoreNodeId = max($maxMemStoreNodeId, $nodeId);
            } elseif ($nodeId < self::FIRST_INTERIOR_NODE_ID) {
                $maxRegularLeafNodeId = max($maxRegularLeafNodeId, $nodeId);
            }
        }

        foreach ($snapshot['branches'] as $nodeIdRaw => $branch) {
            $nodeId = self::parsePositiveNodeId($nodeIdRaw, 'branch node id');
            if ($nodeId < self::FIRST_INTERIOR_NODE_ID) {
                throw new \InvalidArgumentException('branch node id is in the leaf node range');
            }
            if (isset($store->leaves[$nodeId])) {
                throw new \InvalidArgumentException('tracked node store snapshot has duplicate node ids');
            }
            if (!is_array($branch)
                || !isset($branch['leftNodeId'], $branch['rightNodeId'], $branch['hash'])
                || !is_string($branch['hash'])
            ) {
                throw new \InvalidArgumentException('tracked node store snapshot has a malformed branch');
            }

            $leftNodeId = self::parseNonNegativeNodeId($branch['leftNodeId'], 'left branch child node id');
            $rightNodeId = self::parseNonNegativeNodeId($branch['rightNodeId'], 'right branch child node id');
            self::assertHash($branch['hash']);

            $store->branches[$nodeId] = [
                'leftNodeId' => $leftNodeId,
                'rightNodeId' => $rightNodeId,
                'hash' => $branch['hash'],
            ];

            if ($nodeId >= self::FIRST_MEMSTORE_NODE_ID) {
                $maxMemStoreNodeId = max($maxMemStoreNodeId, $nodeId);
            } elseif ($nodeId >= self::FIRST_INTERIOR_NODE_ID) {
                $maxRegularBranchNodeId = max($maxRegularBranchNodeId, $nodeId);
            }
        }

        foreach ($snapshot['heads'] as $head => $nodeIdRaw) {
            if (!is_string($head) && !is_int($head)) {
                throw new \InvalidArgumentException('tracked node store snapshot head names must be strings');
            }
            $headName = (string) $head;
            self::assertHeadName($headName);
            $store->heads[$headName] = self::parseNonNegativeNodeId($nodeIdRaw, 'head node id');
        }

        foreach ($store->branches as $branch) {
            foreach ([$branch['leftNodeId'], $branch['rightNodeId']] as $childNodeId) {
                if ($childNodeId !== 0 && !isset($store->leaves[$childNodeId]) && !isset($store->branches[$childNodeId])) {
                    throw new \InvalidArgumentException('tracked node store snapshot branch references an unknown child');
                }
            }
        }
        foreach ($store->heads as $nodeId) {
            if ($nodeId !== 0 && !isset($store->leaves[$nodeId]) && !isset($store->branches[$nodeId])) {
                throw new \InvalidArgumentException('tracked node store snapshot head references an unknown node');
            }
        }

        $store->nextLeafNodeId = max(
            self::parsePositiveCounter($snapshot['nextLeafNodeId'] ?? 1, 'next leaf node id'),
            $maxRegularLeafNodeId + 1
        );
        $store->nextBranchNodeId = max(
            self::parsePositiveCounter($snapshot['nextBranchNodeId'] ?? self::FIRST_INTERIOR_NODE_ID, 'next branch node id'),
            self::FIRST_INTERIOR_NODE_ID,
            $maxRegularBranchNodeId + 1
        );
        $store->nextMemStoreNodeId = max(
            self::parsePositiveCounter($snapshot['nextMemStoreNodeId'] ?? self::FIRST_MEMSTORE_NODE_ID, 'next memStore node id'),
            self::FIRST_MEMSTORE_NODE_ID,
            $maxMemStoreNodeId + 1
        );

        return $store;
    }

    public function createLeaf(string $keyHashHex, string $value, string $nodeHashHex, bool $writeToMemStore = false): int
    {
        $nodeId = $writeToMemStore ? $this->nextMemStoreNodeId++ : $this->nextLeafNodeId++;
        $this->leaves[$nodeId] = [
            'keyHash' => $keyHashHex,
            'value' => $value,
            'hash' => $nodeHashHex,
        ];

        return $nodeId;
    }

    public function createBranch(int $leftNodeId, int $rightNodeId, string $nodeHashHex, bool $writeToMemStore = false): int
    {
        $nodeId = $writeToMemStore ? $this->nextMemStoreNodeId++ : $this->nextBranchNodeId++;
        $this->branches[$nodeId] = [
            'leftNodeId' => $leftNodeId,
            'rightNodeId' => $rightNodeId,
            'hash' => $nodeHashHex,
        ];

        return $nodeId;
    }

    public function headNodeId(string $head): int
    {
        self::assertHeadName($head);

        return $this->heads[$head] ?? 0;
    }

    /**
     * @return array<int|string, int>
     */
    public function heads(): array
    {
        $heads = $this->heads;
        ksort($heads, SORT_STRING);

        return $heads;
    }

    public function setHeadNodeId(string $head, int $nodeId): void
    {
        self::assertHeadName($head);
        if ($nodeId < 0) {
            throw new \InvalidArgumentException('head node id must be non-negative');
        }

        $this->heads[$head] = $nodeId;
    }

    public function deleteHead(string $head): void
    {
        self::assertHeadName($head);
        unset($this->heads[$head]);
    }

    /**
     * @param list<int> $extraRootNodeIds
     *
     * @return array{total: int, garbage: int}
     */
    public function garbageCollect(array $extraRootNodeIds = []): array
    {
        $marked = [];
        foreach ($this->heads as $nodeId) {
            $this->markReachableNode($nodeId, $marked);
        }

        foreach ($extraRootNodeIds as $nodeId) {
            if (!is_int($nodeId) || $nodeId < 0) {
                throw new \InvalidArgumentException('garbage collection root node id must be non-negative');
            }

            $this->markReachableNode($nodeId, $marked);
        }

        $total = count($this->leaves) + count($this->branches);
        $garbage = 0;

        foreach (array_keys($this->leaves) as $nodeId) {
            if (!isset($marked[$nodeId])) {
                unset($this->leaves[$nodeId]);
                $garbage++;
            }
        }

        foreach (array_keys($this->branches) as $nodeId) {
            if (!isset($marked[$nodeId])) {
                unset($this->branches[$nodeId]);
                $garbage++;
            }
        }

        if ($garbage > 0) {
            $this->resetNextNodeIds();
        }

        return [
            'total' => $total,
            'garbage' => $garbage,
        ];
    }

    /**
     * @return array{keyHash: string, value: string, hash: string}
     */
    public function leaf(int $nodeId): array
    {
        if (!isset($this->leaves[$nodeId])) {
            throw new \InvalidArgumentException('unknown tracked leaf node id');
        }

        return $this->leaves[$nodeId];
    }

    /**
     * @return array{leftNodeId: int, rightNodeId: int, hash: string}
     */
    public function branch(int $nodeId): array
    {
        if (!isset($this->branches[$nodeId])) {
            throw new \InvalidArgumentException('unknown tracked branch node id');
        }

        return $this->branches[$nodeId];
    }

    public function isLeaf(int $nodeId): bool
    {
        return isset($this->leaves[$nodeId]);
    }

    public function isBranch(int $nodeId): bool
    {
        return isset($this->branches[$nodeId]);
    }

    public function nodeHash(int $nodeId): string
    {
        if ($nodeId === 0) {
            return HashTree::EMPTY_HASH;
        }
        if (isset($this->leaves[$nodeId])) {
            return $this->leaves[$nodeId]['hash'];
        }
        if (isset($this->branches[$nodeId])) {
            return $this->branches[$nodeId]['hash'];
        }

        throw new \InvalidArgumentException('unknown tracked node id');
    }

    /**
     * @param array<int, true> $marked
     */
    private function markReachableNode(int $nodeId, array &$marked): void
    {
        if ($nodeId === 0 || isset($marked[$nodeId])) {
            return;
        }

        if (isset($this->leaves[$nodeId])) {
            $marked[$nodeId] = true;

            return;
        }

        if (!isset($this->branches[$nodeId])) {
            throw new \InvalidArgumentException('garbage collection root references an unknown node');
        }

        $marked[$nodeId] = true;
        $this->markReachableNode($this->branches[$nodeId]['leftNodeId'], $marked);
        $this->markReachableNode($this->branches[$nodeId]['rightNodeId'], $marked);
    }

    private function resetNextNodeIds(): void
    {
        $maxRegularLeafNodeId = 0;
        $maxRegularBranchNodeId = self::FIRST_INTERIOR_NODE_ID - 1;
        $maxMemStoreNodeId = self::FIRST_MEMSTORE_NODE_ID - 1;

        foreach (array_keys($this->leaves) as $nodeId) {
            if ($nodeId >= self::FIRST_MEMSTORE_NODE_ID) {
                $maxMemStoreNodeId = max($maxMemStoreNodeId, $nodeId);
            } elseif ($nodeId < self::FIRST_INTERIOR_NODE_ID) {
                $maxRegularLeafNodeId = max($maxRegularLeafNodeId, $nodeId);
            }
        }

        foreach (array_keys($this->branches) as $nodeId) {
            if ($nodeId >= self::FIRST_MEMSTORE_NODE_ID) {
                $maxMemStoreNodeId = max($maxMemStoreNodeId, $nodeId);
            } elseif ($nodeId >= self::FIRST_INTERIOR_NODE_ID) {
                $maxRegularBranchNodeId = max($maxRegularBranchNodeId, $nodeId);
            }
        }

        $this->nextLeafNodeId = $maxRegularLeafNodeId + 1;
        $this->nextBranchNodeId = $maxRegularBranchNodeId + 1;
        $this->nextMemStoreNodeId = $maxMemStoreNodeId + 1;
    }

    private static function assertHeadName(string $head): void
    {
        if ($head === '') {
            throw new \InvalidArgumentException('head name must be non-empty');
        }
    }

    private static function parsePositiveNodeId(mixed $value, string $label): int
    {
        $nodeId = self::parseNonNegativeNodeId($value, $label);
        if ($nodeId === 0) {
            throw new \InvalidArgumentException($label . ' must be positive');
        }

        return $nodeId;
    }

    private static function parsePositiveCounter(mixed $value, string $label): int
    {
        $counter = self::parsePositiveNodeId($value, $label);
        if ($counter >= PHP_INT_MAX) {
            throw new \InvalidArgumentException($label . ' exceeds PHP integer range');
        }

        return $counter;
    }

    private static function parseNonNegativeNodeId(mixed $value, string $label): int
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

    private static function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }
}
