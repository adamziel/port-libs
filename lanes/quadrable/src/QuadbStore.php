<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class QuadbStore
{
    private const STATE_FILE = 'quadb-state.json';

    private function __construct(
        private readonly string $directory,
        private readonly TrackedNodeStore $nodeStore,
        private ?string $currentHead,
        private int $detachedHeadNodeId,
        /** @var array<string, string> */
        private array $trackedKeys = []
    ) {
    }

    public static function init(string $directory): self
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory '{$directory}'");
        }

        $statePath = self::statePath($directory);
        if (is_file($statePath)) {
            return self::open($directory);
        }

        $store = new self($directory, new TrackedNodeStore(), 'master', 0);
        $store->persist();

        return $store;
    }

    public static function open(string $directory): self
    {
        if (!is_dir($directory)) {
            throw new \RuntimeException("Could not access directory '{$directory}'");
        }

        $statePath = self::statePath($directory);
        if (!is_file($statePath)) {
            throw new \RuntimeException("Quadrable store is not initialized: '{$directory}'");
        }

        $decoded = json_decode((string) file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)
            || ($decoded['schemaVersion'] ?? null) !== 1
            || !isset($decoded['trackedNodeStore'], $decoded['quadbState'])
            || !is_array($decoded['trackedNodeStore'])
            || !is_array($decoded['quadbState'])
        ) {
            throw new \InvalidArgumentException('Malformed quadrable file-backed store state');
        }

        $quadbState = $decoded['quadbState'];
        $currentHead = array_key_exists('currentHead', $quadbState) ? $quadbState['currentHead'] : 'master';
        if ($currentHead !== null && !is_string($currentHead)) {
            throw new \InvalidArgumentException('quadb current head must be a string or null');
        }
        if ($currentHead === '') {
            throw new \InvalidArgumentException('head name must be non-empty');
        }

        $detachedHeadNodeId = self::parseNonNegativeNodeId(
            $quadbState['detachedHeadNodeId'] ?? 0,
            'detached head node id'
        );

        $nodeStore = TrackedNodeStore::fromSnapshot($decoded['trackedNodeStore']);
        if ($detachedHeadNodeId !== 0) {
            $nodeStore->nodeHash($detachedHeadNodeId);
        }

        $trackedKeys = [];
        foreach (($quadbState['trackedKeys'] ?? []) as $keyHashHex => $key) {
            if (!is_string($keyHashHex) || !preg_match('/^[0-9a-f]{64}$/', $keyHashHex)) {
                throw new \InvalidArgumentException('tracked key hash must be lowercase 32-byte hex');
            }
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('tracked key must be a non-empty string');
            }

            $trackedKeys[$keyHashHex] = $key;
        }

        return new self(
            $directory,
            $nodeStore,
            $currentHead,
            $detachedHeadNodeId,
            $trackedKeys
        );
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function nodeStore(): TrackedNodeStore
    {
        return $this->nodeStore;
    }

    public function currentHeadName(): ?string
    {
        return $this->currentHead;
    }

    public function isDetachedHead(): bool
    {
        return $this->currentHead === null;
    }

    public function tree(): TrackedSparseTree
    {
        $tree = new TrackedSparseTree($this->nodeStore);

        if ($this->currentHead === null) {
            return $tree->checkout($this->detachedHeadNodeId);
        }

        return $tree->checkout($this->currentHead);
    }

    public function checkout(?string $head = null): TrackedSparseTree
    {
        if ($head === null) {
            $this->currentHead = null;
            $this->detachedHeadNodeId = 0;
        } else {
            $this->assertHeadName($head);
            $this->currentHead = $head;
            $this->detachedHeadNodeId = 0;
        }

        $this->persist();

        return $this->tree();
    }

    public function fork(?string $head = null, ?string $from = null): TrackedSparseTree
    {
        if ($from !== null) {
            $this->assertHeadName($from);
            $base = (new TrackedSparseTree($this->nodeStore))->checkout($from);
        } else {
            $base = $this->tree();
        }

        $nodeId = $base->headNodeId();
        if ($head === null) {
            $this->currentHead = null;
            $this->detachedHeadNodeId = $nodeId;
        } else {
            $this->assertHeadName($head);
            if ($nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID) {
                throw new \RuntimeException('attempted to store MemStore node into LMDB');
            }
            $this->nodeStore->setHeadNodeId($head, $nodeId);
            $this->currentHead = $head;
            $this->detachedHeadNodeId = 0;
        }

        $this->persist();

        return $this->tree();
    }

    public function put(string $key, string $value): void
    {
        SparseTree::assertNonEmptyKey($key);

        $tree = $this->tree();
        $tree->put($key, $value);
        $this->trackedKeys[$this->keyHash($key)] = $key;
        $this->save($tree);
    }

    public function delete(string $key): void
    {
        SparseTree::assertNonEmptyKey($key);

        $tree = $this->tree();
        $tree->delete($key);
        $this->save($tree);
    }

    public function get(string $key): string
    {
        SparseTree::assertNonEmptyKey($key);

        $value = $this->tree()->get($key);
        if ($value === null) {
            throw new \RuntimeException('key not found in db');
        }

        return $value;
    }

    public function rootText(): string
    {
        return '0x' . $this->tree()->rootHash() . "\n";
    }

    public function statusText(): string
    {
        $tree = $this->tree();
        $head = $this->isDetachedHead()
            ? "Detached head\n"
            : 'Head: ' . $this->currentHead . "\n";

        return $head . 'Root: ' . $this->renderNode($tree->headNodeId()) . "\n";
    }

    public function headText(): string
    {
        $heads = [];
        foreach ($this->nodeStore->heads() as $head => $nodeId) {
            $heads[] = [
                'head' => $head,
                'nodeId' => $nodeId,
            ];
        }

        usort($heads, static function (array $a, array $b): int {
            if ($a['nodeId'] === $b['nodeId']) {
                return $a['head'] <=> $b['head'];
            }

            return $b['nodeId'] <=> $a['nodeId'];
        });

        $output = '';
        if ($this->isDetachedHead()) {
            $output .= 'D> [detached] : ' . $this->renderNode($this->detachedHeadNodeId) . "\n";
        }

        foreach ($heads as $head) {
            $prefix = !$this->isDetachedHead() && $this->currentHead === $head['head'] ? '=> ' : '   ';
            $output .= $prefix . $head['head'] . ' : ' . $this->renderNode($head['nodeId']) . "\n";
        }

        return $output;
    }

    public function removeHead(?string $head = null): void
    {
        if ($head !== null) {
            $this->assertHeadName($head);
            $this->nodeStore->deleteHead($head);
            $this->persist();

            return;
        }

        if ($this->isDetachedHead()) {
            $this->detachedHeadNodeId = 0;
            $this->persist();

            return;
        }

        $this->nodeStore->deleteHead((string) $this->currentHead);
        $this->persist();
    }

    public function save(?TrackedSparseTree $tree = null): void
    {
        if ($this->currentHead === null && $tree !== null) {
            $this->detachedHeadNodeId = $tree->headNodeId();
        }

        $this->persist();
    }

    public function importLines(string $input, string $separator = ','): int
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $tree = $this->tree();
        $changes = $tree->change();
        $trackedKeys = [];
        $count = 0;

        foreach ($this->splitInputLines($input) as $line) {
            [$key, $value] = $this->splitSeparatedLine($line, $separator);
            SparseTree::assertNonEmptyKey($key);
            $changes->put($key, $value);
            $trackedKeys[$this->keyHash($key)] = $key;
            $count++;
        }

        if ($count > 0) {
            $changes->apply();
            $this->trackedKeys = array_replace($this->trackedKeys, $trackedKeys);
            $this->save($tree);
        }

        return $count;
    }

    public function exportLines(string $separator = ','): string
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $output = '';
        foreach ($this->tree()->orderedEntries() as $entry) {
            $output .= $this->renderTrackedKey($entry->keyHex()) . $separator . $entry->value() . "\n";
        }

        return $output;
    }

    public function diffLines(string $head, string $separator = ','): string
    {
        $this->assertHeadName($head);
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $base = (new TrackedSparseTree($this->nodeStore))->checkout($head);
        $current = $this->tree();
        $baseEntries = $this->entriesByKeyHex($base);
        $currentEntries = $this->entriesByKeyHex($current);
        $keys = array_values(array_unique(array_merge(array_keys($baseEntries), array_keys($currentEntries))));
        sort($keys, SORT_STRING);

        $output = '';
        foreach ($keys as $keyHex) {
            $baseEntry = $baseEntries[$keyHex] ?? null;
            $currentEntry = $currentEntries[$keyHex] ?? null;
            $renderedKey = $this->renderTrackedKey($keyHex);

            if ($baseEntry === null && $currentEntry !== null) {
                $output .= '+' . $renderedKey . $separator . $currentEntry['value'] . "\n";
                continue;
            }

            if ($baseEntry !== null && $currentEntry === null) {
                $output .= '-' . $renderedKey . $separator . $baseEntry['value'] . "\n";
                continue;
            }

            if ($baseEntry !== null && $currentEntry !== null && $baseEntry['value'] !== $currentEntry['value']) {
                $output .= '-' . $renderedKey . $separator . $baseEntry['value'] . "\n";
                $output .= '+' . $renderedKey . $separator . $currentEntry['value'] . "\n";
            }
        }

        return $output;
    }

    public function applyPatchLines(string $input, string $separator = ','): int
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $tree = $this->tree();
        $changes = $tree->change();
        $trackedKeys = [];
        $count = 0;

        foreach ($this->splitInputLines($input) as $line) {
            if ($line === '') {
                throw new \RuntimeException('empty line in patch');
            }
            if ($line[0] === '#') {
                continue;
            }

            $operation = $line[0];
            [$key, $value] = $this->splitSeparatedLine(substr($line, 1), $separator);

            if ($operation === '+') {
                SparseTree::assertNonEmptyKey($key);
                $changes->put($key, $value);
                $trackedKeys[$this->keyHash($key)] = $key;
            } elseif ($operation === '-') {
                SparseTree::assertNonEmptyKey($key);
                $changes->delete($key);
            } else {
                throw new \RuntimeException('unexpected line in patch');
            }

            $count++;
        }

        if ($count > 0) {
            $changes->apply();
            $this->trackedKeys = array_replace($this->trackedKeys, $trackedKeys);
            $this->save($tree);
        }

        return $count;
    }

    public function importIntegerLines(string $input, string $separator = ','): int
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $input);
        $lines = explode("\n", $normalized);
        if ($normalized !== '' && str_ends_with($normalized, "\n")) {
            array_pop($lines);
        }

        $tree = $this->tree();
        $changes = $tree->change();
        $count = 0;

        foreach ($lines as $line) {
            if ($line === '' && $normalized === '') {
                continue;
            }

            $separatorOffset = strpos($line, $separator);
            if ($separatorOffset === false) {
                throw new \RuntimeException("couldn't find separator in input line");
            }

            $key = substr($line, 0, $separatorOffset);
            $value = substr($line, $separatorOffset + strlen($separator));
            if (!preg_match('/^(0|[1-9][0-9]*)$/', $key)) {
                throw new \InvalidArgumentException('integer import key must be a non-negative integer');
            }

            $changes->putKey(Key::fromInteger((int) $key), $value);
            $count++;
        }

        if ($count > 0) {
            $changes->apply();
            $this->save($tree);
        }

        return $count;
    }

    public function exportIntegerLines(string $separator = ','): string
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $output = '';
        foreach ($this->tree()->orderedEntries() as $entry) {
            $output .= $entry->key()->toInteger() . $separator . $entry->value() . "\n";
        }

        return $output;
    }

    /**
     * @return array{detached: bool, head: ?string, rootHash: string, headNodeId: int}
     */
    public function status(): array
    {
        $tree = $this->tree();

        return [
            'detached' => $this->isDetachedHead(),
            'head' => $this->currentHead,
            'rootHash' => $tree->rootHash(),
            'headNodeId' => $tree->headNodeId(),
        ];
    }

    private function persist(): void
    {
        $trackedKeys = $this->trackedKeys;
        ksort($trackedKeys, SORT_STRING);

        $encoded = json_encode([
            'schemaVersion' => 1,
            'trackedNodeStore' => $this->nodeStore->exportSnapshot(),
            'quadbState' => [
                'currentHead' => $this->currentHead,
                'detachedHeadNodeId' => $this->detachedHeadNodeId,
                'trackedKeys' => $trackedKeys,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        $statePath = self::statePath($this->directory);
        $tmpPath = $statePath . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpPath, $encoded, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write Quadrable store state '{$tmpPath}'");
        }
        if (!rename($tmpPath, $statePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Unable to replace Quadrable store state '{$statePath}'");
        }
    }

    private static function statePath(string $directory): string
    {
        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::STATE_FILE;
    }

    private function assertHeadName(string $head): void
    {
        if ($head === '') {
            throw new \InvalidArgumentException('head name must be non-empty');
        }
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

    private function keyHash(string $key): string
    {
        return (new HashTree())->keyHash($key);
    }

    /**
     * @return list<string>
     */
    private function splitInputLines(string $input): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $input);
        if ($normalized === '') {
            return [];
        }

        $lines = explode("\n", $normalized);
        if (str_ends_with($normalized, "\n")) {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitSeparatedLine(string $line, string $separator): array
    {
        $separatorOffset = strpos($line, $separator);
        if ($separatorOffset === false) {
            throw new \RuntimeException("couldn't find separator in input line");
        }

        return [
            substr($line, 0, $separatorOffset),
            substr($line, $separatorOffset + strlen($separator)),
        ];
    }

    /**
     * @return array<string, array{value: string}>
     */
    private function entriesByKeyHex(TrackedSparseTree $tree): array
    {
        $entries = [];
        foreach ($tree->orderedEntries() as $entry) {
            $entries[$entry->keyHex()] = [
                'value' => $entry->value(),
            ];
        }

        return $entries;
    }

    private function renderTrackedKey(string $keyHex): string
    {
        return $this->trackedKeys[$keyHex] ?? self::renderUnknownKey($keyHex);
    }

    private static function renderUnknownKey(string $keyHex): string
    {
        return 'H(?)=0x' . substr($keyHex, 0, 12) . '...';
    }

    private function renderNode(int $nodeId): string
    {
        return '0x' . $this->nodeStore->nodeHash($nodeId) . ' (' . $nodeId . ')';
    }
}
