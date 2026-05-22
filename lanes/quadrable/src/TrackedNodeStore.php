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

    private int $nextLeafNodeId = 1;
    private int $nextBranchNodeId = self::FIRST_INTERIOR_NODE_ID;
    private int $nextMemStoreNodeId = self::FIRST_MEMSTORE_NODE_ID;

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
}
