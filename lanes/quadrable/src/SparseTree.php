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

        return $this->values[$this->hashTree->keyHash($key)] ?? null;
    }

    public function getKey(Key $key): ?string
    {
        return $this->values[$key->hex()] ?? null;
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

    public function rootHash(): string
    {
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
     * @param array<string, array{delete: bool, value: string}> $updates
     */
    public function applyRawUpdates(array $updates): self
    {
        ksort($updates, SORT_STRING);
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
