<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class TrackedNodeStore
{
    /**
     * @var array<int, array{keyHash: string, value: string, hash: string}>
     */
    private array $leaves = [];

    /**
     * @var array<int, string>
     */
    private array $branches = [];

    private int $nextLeafNodeId = 1;
    private int $nextBranchNodeId = 288230376151711744;

    public function createLeaf(string $keyHashHex, string $value, string $nodeHashHex): int
    {
        $nodeId = $this->nextLeafNodeId++;
        $this->leaves[$nodeId] = [
            'keyHash' => $keyHashHex,
            'value' => $value,
            'hash' => $nodeHashHex,
        ];

        return $nodeId;
    }

    public function createBranch(string $nodeHashHex): int
    {
        $nodeId = $this->nextBranchNodeId++;
        $this->branches[$nodeId] = $nodeHashHex;

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

    public function nodeHash(int $nodeId): string
    {
        if ($nodeId === 0) {
            return HashTree::EMPTY_HASH;
        }
        if (isset($this->leaves[$nodeId])) {
            return $this->leaves[$nodeId]['hash'];
        }
        if (isset($this->branches[$nodeId])) {
            return $this->branches[$nodeId];
        }

        throw new \InvalidArgumentException('unknown tracked node id');
    }
}
