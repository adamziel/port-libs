<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class TrackedChangeSet
{
    private HashTree $hashTree;

    /**
     * @var array<string, array{delete: bool, value: string, reuseNodeId: int, outputNodeId: ?int}>
     */
    private array $updates = [];

    public function __construct(private readonly TrackedSparseTree $tree)
    {
        $this->hashTree = new HashTree();
    }

    public function put(string $key, string $value, ?int &$outputNodeId = null): self
    {
        SparseTree::assertNonEmptyKey($key);

        return $this->putTracked($this->hashTree->keyHash($key), $value, 0, $outputNodeId);
    }

    public function putKey(Key $key, string $value, ?int &$outputNodeId = null): self
    {
        return $this->putTracked($key->hex(), $value, 0, $outputNodeId);
    }

    public function putReuse(Key $key, int $nodeId, ?int &$outputNodeId = null): self
    {
        if ($nodeId <= 0) {
            throw new \InvalidArgumentException('reused node id must be a leaf node');
        }

        return $this->putTracked($key->hex(), '', $nodeId, $outputNodeId);
    }

    public function delete(string $key, ?int &$outputNodeId = null): self
    {
        SparseTree::assertNonEmptyKey($key);

        return $this->deleteTracked($this->hashTree->keyHash($key), $outputNodeId);
    }

    public function deleteKey(Key $key, ?int &$outputNodeId = null): self
    {
        return $this->deleteTracked($key->hex(), $outputNodeId);
    }

    public function apply(): TrackedSparseTree
    {
        return $this->tree->applyTrackedUpdates($this->updates);
    }

    private function putTracked(string $keyHashHex, string $value, int $reuseNodeId, ?int &$outputNodeId): self
    {
        $this->updates[$keyHashHex] = [
            'delete' => false,
            'value' => $value,
            'reuseNodeId' => $reuseNodeId,
            'outputNodeId' => &$outputNodeId,
        ];

        return $this;
    }

    private function deleteTracked(string $keyHashHex, ?int &$outputNodeId): self
    {
        $this->updates[$keyHashHex] = [
            'delete' => true,
            'value' => '',
            'reuseNodeId' => 0,
            'outputNodeId' => &$outputNodeId,
        ];

        return $this;
    }
}
