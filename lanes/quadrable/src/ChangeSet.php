<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class ChangeSet
{
    private HashTree $hashTree;

    /**
     * @var array<string, array{delete: bool, value: string, key?: string}>
     */
    private array $updates = [];

    public function __construct(private readonly SparseTree $tree)
    {
        $this->hashTree = new HashTree();
    }

    public function put(string $key, string $value): self
    {
        SparseTree::assertNonEmptyKey($key);
        $this->updates[$this->hashTree->keyHash($key)] = [
            'delete' => false,
            'value' => $value,
            'key' => $key,
        ];

        return $this;
    }

    public function putKey(Key $key, string $value): self
    {
        $this->updates[$key->hex()] = [
            'delete' => false,
            'value' => $value,
        ];

        return $this;
    }

    public function delete(string $key): self
    {
        SparseTree::assertNonEmptyKey($key);
        $this->updates[$this->hashTree->keyHash($key)] = [
            'delete' => true,
            'value' => '',
            'key' => $key,
        ];

        return $this;
    }

    public function deleteKey(Key $key): self
    {
        $this->updates[$key->hex()] = [
            'delete' => true,
            'value' => '',
        ];

        return $this;
    }

    public function apply(): SparseTree
    {
        return $this->tree->applyRawUpdates($this->updates);
    }
}
