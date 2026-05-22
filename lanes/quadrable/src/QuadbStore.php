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
        private array $trackedKeys = [],
        /** @var array<string, array{rootHash: string, proofRootHash: string, proofs: list<string>, updates: list<array{delete: bool, keyHash: string, value: string, key?: string}>}> */
        private array $partialProofHeads = [],
        /** @var array{rootHash: string, proofRootHash: string, proofs: list<string>, updates: list<array{delete: bool, keyHash: string, value: string, key?: string}>}|null */
        private ?array $partialDetachedHead = null
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

        $partialProofHeadsRaw = $quadbState['partialProofHeads'] ?? [];
        if (!is_array($partialProofHeadsRaw)) {
            throw new \InvalidArgumentException('partial proof heads must be an object');
        }

        $partialProofHeads = [];
        foreach ($partialProofHeadsRaw as $head => $partialState) {
            if (!is_string($head)) {
                throw new \InvalidArgumentException('partial proof head names must be strings');
            }
            self::assertHeadNameValue($head);
            $partialProofHeads[$head] = self::parsePartialProofState($partialState, 'partial proof head');
        }
        ksort($partialProofHeads, SORT_STRING);

        $partialDetachedHead = null;
        if (array_key_exists('partialDetachedHead', $quadbState) && $quadbState['partialDetachedHead'] !== null) {
            $partialDetachedHead = self::parsePartialProofState($quadbState['partialDetachedHead'], 'partial detached head');
        }

        return new self(
            $directory,
            $nodeStore,
            $currentHead,
            $detachedHeadNodeId,
            $trackedKeys,
            $partialProofHeads,
            $partialDetachedHead
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
        if ($this->currentPartialProofState() !== null) {
            throw new \RuntimeException('current head is a proof-backed partial tree');
        }

        $tree = new TrackedSparseTree($this->nodeStore);

        if ($this->currentHead === null) {
            return $tree->checkout($this->detachedHeadNodeId);
        }

        return $tree->checkout($this->currentHead);
    }

    public function checkout(?string $head = null): TrackedSparseTree|SparseTree
    {
        if ($head === null) {
            $this->currentHead = null;
            $this->detachedHeadNodeId = 0;
            $this->partialDetachedHead = null;
        } else {
            $this->assertHeadName($head);
            $this->currentHead = $head;
            $this->detachedHeadNodeId = 0;
        }

        $this->persist();

        return $this->currentPartialTree() ?? $this->tree();
    }

    public function fork(?string $head = null, ?string $from = null): TrackedSparseTree|SparseTree
    {
        if ($from !== null) {
            $this->assertHeadName($from);
        }

        $partialSource = $from !== null
            ? ($this->partialProofHeads[$from] ?? null)
            : $this->currentPartialProofState();
        if ($partialSource !== null) {
            if ($head === null) {
                $this->currentHead = null;
                $this->detachedHeadNodeId = 0;
                $this->partialDetachedHead = $partialSource;
            } else {
                $this->assertHeadName($head);
                $this->partialProofHeads[$head] = $partialSource;
                $this->nodeStore->deleteHead($head);
                $this->currentHead = $head;
                $this->detachedHeadNodeId = 0;
            }

            $this->persist();

            return $this->partialTreeFromState($partialSource);
        }

        if ($from !== null) {
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
            unset($this->partialProofHeads[$head]);
            $this->currentHead = $head;
            $this->detachedHeadNodeId = 0;
        }

        $this->persist();

        return $this->tree();
    }

    public function put(string $key, string $value): void
    {
        SparseTree::assertNonEmptyKey($key);

        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialStringUpdate($key, false, $value);

            return;
        }

        $tree = $this->tree();
        $tree->put($key, $value);
        $this->trackedKeys[$this->keyHash($key)] = $key;
        $this->save($tree);
    }

    public function delete(string $key): void
    {
        SparseTree::assertNonEmptyKey($key);

        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialStringUpdate($key, true, '');

            return;
        }

        $tree = $this->tree();
        $tree->delete($key);
        $this->save($tree);
    }

    public function get(string $key): string
    {
        SparseTree::assertNonEmptyKey($key);

        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            $value = $partial->get($key);
        } else {
            $value = $this->tree()->get($key);
        }
        if ($value === null) {
            throw new \RuntimeException('key not found in db');
        }

        return $value;
    }

    public function rootText(): string
    {
        return '0x' . $this->currentRootHash() . "\n";
    }

    public function statusText(): string
    {
        $head = $this->isDetachedHead()
            ? "Detached head\n"
            : 'Head: ' . $this->currentHead . "\n";

        return $head . 'Root: ' . $this->renderCurrentRootNode() . "\n";
    }

    public function headText(): string
    {
        $heads = [];
        foreach ($this->nodeStore->heads() as $head => $nodeId) {
            $heads[] = [
                'head' => $head,
                'nodeId' => $nodeId,
                'rootHash' => $this->nodeStore->nodeHash($nodeId),
            ];
        }
        foreach ($this->partialProofHeads as $head => $_partialState) {
            $partialTree = $this->partialTreeForHead($head);
            $heads[] = [
                'head' => $head,
                'nodeId' => $partialTree->partialRootNodeId(),
                'rootHash' => $partialTree->rootHash(),
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
            $output .= 'D> [detached] : ' . $this->renderCurrentRootNode() . "\n";
        }

        foreach ($heads as $head) {
            $prefix = !$this->isDetachedHead() && $this->currentHead === $head['head'] ? '=> ' : '   ';
            $output .= $prefix . $head['head'] . ' : ' . $this->renderRootNode($head['rootHash'], $head['nodeId']) . "\n";
        }

        return $output;
    }

    public function removeHead(?string $head = null): void
    {
        if ($head !== null) {
            $this->assertHeadName($head);
            $this->nodeStore->deleteHead($head);
            unset($this->partialProofHeads[$head]);
            $this->persist();

            return;
        }

        if ($this->isDetachedHead()) {
            $this->detachedHeadNodeId = 0;
            $this->partialDetachedHead = null;
            $this->persist();

            return;
        }

        $this->nodeStore->deleteHead((string) $this->currentHead);
        unset($this->partialProofHeads[(string) $this->currentHead]);
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
        $this->assertWritableFullHead();

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
        if ($this->currentPartialProofState() !== null) {
            throw new \RuntimeException('cannot export all records from a proof-backed partial tree');
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
        if ($this->currentPartialProofState() !== null || isset($this->partialProofHeads[$head])) {
            throw new \RuntimeException('cannot diff proof-backed partial trees');
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
        $this->assertWritableFullHead();

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
        $this->assertWritableFullHead();

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
        if ($this->currentPartialProofState() !== null) {
            throw new \RuntimeException('cannot export all records from a proof-backed partial tree');
        }

        $output = '';
        foreach ($this->tree()->orderedEntries() as $entry) {
            $output .= $entry->key()->toInteger() . $separator . $entry->value() . "\n";
        }

        return $output;
    }

    /**
     * @param list<string> $keys
     */
    public function exportProof(array $keys): Proof
    {
        return $this->sparseTreeForProofs()->exportProof($keys);
    }

    /**
     * @param list<string> $keys
     */
    public function exportProofBytes(array $keys, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return $this->exportProof($keys)->encode($encodingType);
    }

    /**
     * @param list<string> $keys
     */
    public function exportProofHex(array $keys, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return '0x' . bin2hex($this->exportProofBytes($keys, $encodingType)) . "\n";
    }

    /**
     * @param list<int> $integers
     */
    public function exportIntegerProof(array $integers): Proof
    {
        $keys = [];
        foreach ($integers as $integer) {
            if (!is_int($integer)) {
                throw new \InvalidArgumentException('exportIntegerProof expects integer keys');
            }

            $keys[] = Key::fromInteger($integer);
        }

        return $this->sparseTreeForProofs()->exportRawProof($keys);
    }

    /**
     * @param list<int> $integers
     */
    public function exportIntegerProofBytes(array $integers, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return $this->exportIntegerProof($integers)->encode($encodingType);
    }

    /**
     * @param list<int> $integers
     */
    public function exportIntegerProofHex(array $integers, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return '0x' . bin2hex($this->exportIntegerProofBytes($integers, $encodingType)) . "\n";
    }

    public function importProofHex(string $proofHex, ?string $expectedRoot = null): string
    {
        return $this->importProofBytes(self::decodeProofHexText($proofHex), $expectedRoot);
    }

    public function importProofBytes(string $encodedProof, ?string $expectedRoot = null): string
    {
        if ($this->currentPartialProofState() !== null || $this->tree()->rootHash() !== HashTree::EMPTY_HASH) {
            throw new \RuntimeException('current head must be empty before importing a proof');
        }

        $root = $this->normalizeOptionalRoot($expectedRoot);
        $proof = Proof::decode($encodedProof);
        $partial = SparseTree::importProof($proof, $root ?? '');
        $state = [
            'rootHash' => $partial->rootHash(),
            'proofRootHash' => $partial->rootHash(),
            'proofs' => [bin2hex($encodedProof)],
            'updates' => [],
        ];

        $this->storeCurrentPartialProofState($state);
        $this->persist();

        return $state['rootHash'];
    }

    public function mergeProofHex(string $proofHex): string
    {
        return $this->mergeProofBytes(self::decodeProofHexText($proofHex));
    }

    public function mergeProofBytes(string $encodedProof): string
    {
        $state = $this->currentPartialProofState();
        if ($state === null) {
            throw new \RuntimeException('current head is not a proof-backed partial tree');
        }
        if ($state['updates'] !== []) {
            throw new \RuntimeException('cannot merge proofs after proof-backed writes');
        }

        $partial = $this->partialTreeFromState($state);
        $partial->mergeProof(Proof::decode($encodedProof));

        $state['proofs'][] = bin2hex($encodedProof);
        $this->storeCurrentPartialProofState($state);
        $this->persist();

        return $partial->rootHash();
    }

    /**
     * @return array{detached: bool, head: ?string, rootHash: string, headNodeId: int}
     */
    public function status(): array
    {
        $partial = $this->currentPartialTree();

        return [
            'detached' => $this->isDetachedHead(),
            'head' => $this->currentHead,
            'rootHash' => $partial?->rootHash() ?? $this->tree()->rootHash(),
            'headNodeId' => $partial?->partialRootNodeId() ?? $this->tree()->headNodeId(),
        ];
    }

    private function sparseTreeForProofs(): SparseTree
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            return $partial;
        }

        $sparse = new SparseTree();
        $changes = $sparse->change();

        foreach ($this->tree()->orderedEntries() as $entry) {
            $trackedKey = $this->trackedKeys[$entry->keyHex()] ?? null;
            if ($trackedKey !== null) {
                $changes->put($trackedKey, $entry->value());
            } else {
                $changes->putKey($entry->key(), $entry->value());
            }
        }

        $changes->apply();

        return $sparse;
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
                'partialProofHeads' => $this->partialProofHeads,
                'partialDetachedHead' => $this->partialDetachedHead,
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
        self::assertHeadNameValue($head);
    }

    private static function assertHeadNameValue(string $head): void
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

    private function assertWritableFullHead(): void
    {
        if ($this->currentPartialProofState() !== null) {
            throw new \RuntimeException('current head is a proof-backed partial tree');
        }
    }

    /**
     * @return array{rootHash: string, proofRootHash: string, proofs: list<string>, updates: list<array{delete: bool, keyHash: string, value: string, key?: string}>}|null
     */
    private function currentPartialProofState(): ?array
    {
        if ($this->currentHead === null) {
            return $this->partialDetachedHead;
        }

        return $this->partialProofHeads[$this->currentHead] ?? null;
    }

    /**
     * @param array{rootHash: string, proofRootHash: string, proofs: list<string>, updates: list<array{delete: bool, keyHash: string, value: string, key?: string}>} $state
     */
    private function storeCurrentPartialProofState(array $state): void
    {
        if ($this->currentHead === null) {
            $this->detachedHeadNodeId = 0;
            $this->partialDetachedHead = $state;

            return;
        }

        $this->partialProofHeads[$this->currentHead] = $state;
        $this->nodeStore->deleteHead($this->currentHead);
    }

    private function currentPartialTree(): ?SparseTree
    {
        $state = $this->currentPartialProofState();
        if ($state === null) {
            return null;
        }

        return $this->partialTreeFromState($state);
    }

    private function partialTreeForHead(string $head): SparseTree
    {
        if (!isset($this->partialProofHeads[$head])) {
            throw new \RuntimeException('head is not proof-backed');
        }

        return $this->partialTreeFromState($this->partialProofHeads[$head]);
    }

    /**
     * @param array{rootHash: string, proofRootHash: string, proofs: list<string>, updates: list<array{delete: bool, keyHash: string, value: string, key?: string}>} $state
     */
    private function partialTreeFromState(array $state): SparseTree
    {
        $proofs = $state['proofs'];
        if ($proofs === []) {
            throw new \RuntimeException('partial proof state has no proofs');
        }

        $first = Proof::decode((string) hex2bin($proofs[0]));
        $partial = SparseTree::importProof($first, $state['proofRootHash']);

        for ($i = 1; $i < count($proofs); $i++) {
            $partial->mergeProof(Proof::decode((string) hex2bin($proofs[$i])));
        }

        foreach ($state['updates'] as $update) {
            $rawUpdate = [
                'delete' => $update['delete'],
                'value' => $update['value'],
            ];
            if (isset($update['key'])) {
                $rawUpdate['key'] = $update['key'];
            }

            $partial->applyRawUpdates([
                $update['keyHash'] => $rawUpdate,
            ]);
        }
        if ($partial->rootHash() !== $state['rootHash']) {
            throw new \RuntimeException('partial proof state root mismatch');
        }

        return $partial;
    }

    private function applyPartialStringUpdate(string $key, bool $delete, string $value): void
    {
        $state = $this->currentPartialProofState();
        if ($state === null) {
            throw new \RuntimeException('current head is not a proof-backed partial tree');
        }

        $keyHash = $this->keyHash($key);
        $partial = $this->partialTreeFromState($state);
        $partial->applyRawUpdates([
            $keyHash => [
                'delete' => $delete,
                'value' => $value,
                'key' => $key,
            ],
        ]);

        $state['rootHash'] = $partial->rootHash();
        $state['updates'][] = [
            'delete' => $delete,
            'keyHash' => $keyHash,
            'value' => $value,
            'key' => $key,
        ];
        if (!$delete) {
            $this->trackedKeys[$keyHash] = $key;
        }

        $this->storeCurrentPartialProofState($state);
        $this->persist();
    }

    private function currentRootHash(): string
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            return $partial->rootHash();
        }

        return $this->tree()->rootHash();
    }

    private function renderCurrentRootNode(): string
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            return $this->renderRootNode($partial->rootHash(), $partial->partialRootNodeId());
        }

        if ($this->currentHead === null) {
            return $this->renderNode($this->detachedHeadNodeId);
        }

        $tree = $this->tree();

        return $this->renderNode($tree->headNodeId());
    }

    private function normalizeOptionalRoot(?string $root): ?string
    {
        if ($root === null) {
            return null;
        }

        $root = trim($root);
        if (str_starts_with($root, '0x') || str_starts_with($root, '0X')) {
            $root = substr($root, 2);
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $root)) {
            throw new \InvalidArgumentException('expected root must be lowercase 32-byte hex');
        }

        return $root;
    }

    private static function decodeProofHexText(string $proofHex): string
    {
        $hex = preg_replace('/\s+/', '', $proofHex);
        if (!is_string($hex)) {
            throw new \InvalidArgumentException('invalid hex proof text');
        }
        if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X')) {
            $hex = substr($hex, 2);
        }
        if ($hex === '' || strlen($hex) % 2 !== 0 || !preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new \InvalidArgumentException('expected hexadecimal proof');
        }

        $decoded = hex2bin($hex);
        if ($decoded === false) {
            throw new \InvalidArgumentException('expected hexadecimal proof');
        }

        return $decoded;
    }

    /**
     * @return array{rootHash: string, proofRootHash: string, proofs: list<string>, updates: list<array{delete: bool, keyHash: string, value: string, key?: string}>}
     */
    private static function parsePartialProofState(mixed $state, string $label): array
    {
        if (!is_array($state)
            || !isset($state['rootHash'], $state['proofs'])
            || !is_string($state['rootHash'])
            || !preg_match('/^[0-9a-f]{64}$/', $state['rootHash'])
            || !is_array($state['proofs'])
            || $state['proofs'] === []
        ) {
            throw new \InvalidArgumentException($label . ' is malformed');
        }
        $proofRootHash = $state['proofRootHash'] ?? $state['rootHash'];
        if (!is_string($proofRootHash) || !preg_match('/^[0-9a-f]{64}$/', $proofRootHash)) {
            throw new \InvalidArgumentException($label . ' has malformed proof root');
        }

        $proofs = [];
        foreach ($state['proofs'] as $proofHex) {
            if (!is_string($proofHex)
                || $proofHex === ''
                || strlen($proofHex) % 2 !== 0
                || !preg_match('/^[0-9a-f]+$/', $proofHex)
            ) {
                throw new \InvalidArgumentException($label . ' has malformed proof bytes');
            }

            $proofs[] = $proofHex;
        }

        $updatesRaw = $state['updates'] ?? [];
        if (!is_array($updatesRaw)) {
            throw new \InvalidArgumentException($label . ' has malformed proof-backed updates');
        }

        $updates = [];
        $hashTree = new HashTree();
        foreach ($updatesRaw as $update) {
            if (!is_array($update)
                || !isset($update['delete'], $update['keyHash'], $update['value'])
                || !is_bool($update['delete'])
                || !is_string($update['keyHash'])
                || !preg_match('/^[0-9a-f]{64}$/', $update['keyHash'])
                || !is_string($update['value'])
            ) {
                throw new \InvalidArgumentException($label . ' has malformed proof-backed update');
            }

            $parsedUpdate = [
                'delete' => $update['delete'],
                'keyHash' => $update['keyHash'],
                'value' => $update['value'],
            ];
            if (array_key_exists('key', $update)) {
                if (!is_string($update['key']) || $update['key'] === '') {
                    throw new \InvalidArgumentException($label . ' has malformed proof-backed update key');
                }
                if ($hashTree->keyHash($update['key']) !== $update['keyHash']) {
                    throw new \InvalidArgumentException($label . ' has mismatched proof-backed update key');
                }

                $parsedUpdate['key'] = $update['key'];
            }

            $updates[] = $parsedUpdate;
        }

        return [
            'rootHash' => $state['rootHash'],
            'proofRootHash' => $proofRootHash,
            'proofs' => $proofs,
            'updates' => $updates,
        ];
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
        return $this->renderRootNode($this->nodeStore->nodeHash($nodeId), $nodeId);
    }

    private function renderRootNode(string $rootHash, int $nodeId): string
    {
        return '0x' . $rootHash . ' (' . $nodeId . ')';
    }
}
