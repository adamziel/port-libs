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
        ?HashTree $hashTree = null
    ) {
        $this->hashTree = $hashTree ?? new HashTree();
    }

    public function checkoutEmpty(): self
    {
        return new self($this->nodeStore, $this->hashTree);
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

    public function rootHash(): string
    {
        return $this->toSparseTree()->rootHash();
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
     * @param array<string, array{delete: bool, value: string, reuseNodeId: int, outputNodeId: ?int}> $updates
     */
    public function applyTrackedUpdates(array $updates): self
    {
        ksort($updates, SORT_STRING);

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
                $this->hashTree->leafHashForKeyHash($keyHashHex, $update['value'])
            );
            $update['outputNodeId'] = $nodeId;
            $this->values[$keyHashHex] = $update['value'];
            $this->leafNodeIds[$keyHashHex] = $nodeId;
            $changed = true;
        }
        unset($update);

        if ($changed) {
            $this->refreshHeadNodeId();
        }

        return $this;
    }

    private function getByKeyHash(string $keyHashHex, ?int &$outputNodeId): ?string
    {
        self::assertHash($keyHashHex);
        $outputNodeId = $this->leafNodeIds[$keyHashHex] ?? 0;

        return $this->values[$keyHashHex] ?? null;
    }

    private function refreshHeadNodeId(): void
    {
        $count = count($this->values);
        if ($count === 0) {
            $this->headNodeId = 0;
            return;
        }

        if ($count === 1) {
            $this->headNodeId = $this->leafNodeIds[array_key_first($this->values)];
            return;
        }

        $this->headNodeId = $this->nodeStore->createBranch($this->rootHash());
    }

    private function toSparseTree(): SparseTree
    {
        $tree = new SparseTree($this->hashTree);
        $changes = $tree->change();

        foreach ($this->values as $keyHashHex => $value) {
            $changes->putKey(Key::fromHex($keyHashHex), $value);
        }
        $changes->apply();

        return $tree;
    }

    private static function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }
}
