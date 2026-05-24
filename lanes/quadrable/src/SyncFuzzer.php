<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class SyncFuzzer
{
    public function __construct(
        private readonly int $initialDepthLimit = 4,
        private readonly int $laterDepthLimit = 4,
        private readonly int $maxRoundTrips = 200
    ) {
        if ($initialDepthLimit < 0 || $initialDepthLimit > 255 || $laterDepthLimit < 0 || $laterDepthLimit > 255) {
            throw new \InvalidArgumentException('sync fuzzer depth limits must be between 0 and 255');
        }
        if ($maxRoundTrips < 1) {
            throw new \InvalidArgumentException('sync fuzzer maxRoundTrips must be positive');
        }
    }

    /**
     * Runs the upstream `sync fuzz` trial shape. The default 500-trial count matches
     * upstream check.cpp; callers may pass a smaller count for fast CI smoke tests.
     *
     * @return list<array{
     *     trial: int,
     *     numElems: int,
     *     numAlterations: int,
     *     roundTrips: int,
     *     requests: int,
     *     responses: int,
     *     diffCount: int,
     *     scanDiffCount: int,
     *     rootHash: string,
     *     shadowNodeId: int,
     *     maxShadowNodeId: int
     * }>
     */
    public function run(int $trials = 500, int $seed = 0): array
    {
        if ($trials < 0) {
            throw new \InvalidArgumentException('sync fuzzer trials must be non-negative');
        }

        $rng = new Mt19937($seed);
        $results = [];

        for ($trial = 0; $trial < $trials; $trial++) {
            $results[] = $this->runTrial($trial, $rng);
        }

        return $results;
    }

    /**
     * Runs the upstream-shaped sync fuzzer while also persisting named tracked
     * node heads through a JSON snapshot on every trial.
     *
     * @return list<array{
     *     trial: int,
     *     numElems: int,
     *     numAlterations: int,
     *     roundTrips: int,
     *     requests: int,
     *     responses: int,
     *     diffCount: int,
     *     scanDiffCount: int,
     *     rootHash: string,
     *     shadowNodeId: int,
     *     maxShadowNodeId: int,
     *     snapshotBytes: int,
     *     trackedLocalHeadNodeId: int,
     *     trackedRemoteHeadNodeId: int,
     *     restoredLocalHeadNodeId: int,
     *     restoredRemoteHeadNodeId: int,
     *     trackedDiffCount: int,
     *     trackedScanDiffCount: int,
     *     trackedSharedNodeCount: int
     * }>
     */
    public function runWithPersistedTrackedSnapshots(int $trials = 500, int $seed = 0): array
    {
        if ($trials < 0) {
            throw new \InvalidArgumentException('sync fuzzer trials must be non-negative');
        }

        $rng = new Mt19937($seed);
        $results = [];

        for ($trial = 0; $trial < $trials; $trial++) {
            $results[] = $this->runPersistedTrackedTrial($trial, $rng);
        }

        return $results;
    }

    /**
     * @param list<array{
     *     trial: int,
     *     numElems: int,
     *     numAlterations: int,
     *     roundTrips: int,
     *     requests: int,
     *     responses: int,
     *     diffCount: int,
     *     scanDiffCount: int,
     *     rootHash: string
     * }> $results
     *
     * @return array{
     *     trials: int,
     *     firstRoot: ?string,
     *     lastRoot: ?string,
     *     maxRoundTrips: int,
     *     totalRequests: int,
     *     totalResponses: int,
     *     totalDiffs: int,
     *     totalScanDiffs: int,
     *     maxRecords: int,
     *     maxEdits: int
     * }
     */
    public static function summarizeResults(array $results): array
    {
        $first = $results[0] ?? null;
        $last = $results === [] ? null : $results[array_key_last($results)];
        $summary = [
            'trials' => count($results),
            'firstRoot' => $first['rootHash'] ?? null,
            'lastRoot' => $last['rootHash'] ?? null,
            'maxRoundTrips' => 0,
            'totalRequests' => 0,
            'totalResponses' => 0,
            'totalDiffs' => 0,
            'totalScanDiffs' => 0,
            'maxRecords' => 0,
            'maxEdits' => 0,
        ];

        foreach ($results as $result) {
            $summary['maxRoundTrips'] = max($summary['maxRoundTrips'], $result['roundTrips']);
            $summary['totalRequests'] += $result['requests'];
            $summary['totalResponses'] += $result['responses'];
            $summary['totalDiffs'] += $result['diffCount'];
            $summary['totalScanDiffs'] += $result['scanDiffCount'];
            $summary['maxRecords'] = max($summary['maxRecords'], $result['numElems']);
            $summary['maxEdits'] = max($summary['maxEdits'], $result['numAlterations']);
        }

        return $summary;
    }

    /**
     * @return array{
     *     trial: int,
     *     numElems: int,
     *     numAlterations: int,
     *     roundTrips: int,
     *     requests: int,
     *     responses: int,
     *     diffCount: int,
     *     scanDiffCount: int,
     *     rootHash: string,
     *     shadowNodeId: int,
     *     maxShadowNodeId: int
     * }
     */
    private function runTrial(int $trial, Mt19937 $rng): array
    {
        $seedTree = new SparseTree();
        $changes = $seedTree->change();
        $numElems = $rng->nextModulo(800);
        $maxElem = 1000;
        $numAlterations = $rng->nextModulo(200);

        for ($i = 0; $i < $numElems; $i++) {
            $number = $rng->nextModulo($maxElem);
            $changes->putKey(Key::fromInteger($number), (string) $number . str_repeat('A', $rng->nextModulo(60)));
        }
        $changes->apply();

        $local = clone $seedTree;
        $remote = clone $seedTree;
        $remoteChanges = $remote->change();

        for ($i = 0; $i < $numAlterations; $i++) {
            $number = $rng->nextModulo($maxElem);
            if ($rng->nextModulo(2) === 0) {
                $remoteChanges->putKey(Key::fromInteger($number), (string) $number . ' new');
            } else {
                $remoteChanges->deleteKey(Key::fromInteger($number));
            }
        }
        $remoteChanges->apply();

        $session = new SyncSession($local, $this->initialDepthLimit, $this->laterDepthLimit);
        $scanDiffs = [];
        $roundTrips = 0;
        $requestCount = 0;
        $responseCount = 0;
        $converged = false;

        for (; $roundTrips < $this->maxRoundTrips; $roundTrips++) {
            $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
                $rng->nextModulo(1000) + 100,
                static function (DiffEntry $diff) use (&$scanDiffs): void {
                    $scanDiffs[] = $diff;
                }
            )));
            if ($requests === []) {
                $converged = true;
                break;
            }

            $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests(
                $requests,
                $rng->nextModulo(10000) + 2000
            )));

            $requestCount += count($requests);
            $responseCount += count($responses);
            $session->addResponses($requests, $responses);
        }

        if (!$converged) {
            throw new \RuntimeException('upstream-shaped sync fuzz trial did not converge: ' . $trial);
        }

        $shadow = $session->shadow();
        $finalDiffs = $local->diffTo($shadow);
        $reconstructed = clone $local;
        $reconstructed->applyDiffs($finalDiffs);

        if ($remote->rootHash() !== $shadow->rootHash()) {
            throw new \RuntimeException('shadow root mismatch on upstream-shaped trial ' . $trial);
        }
        if ($remote->rootHash() !== $reconstructed->rootHash()) {
            throw new \RuntimeException('reconstructed root mismatch on upstream-shaped trial ' . $trial);
        }
        if (self::diffSignature($finalDiffs) !== self::diffSignature($scanDiffs)) {
            throw new \RuntimeException('scan diff mismatch on upstream-shaped trial ' . $trial);
        }
        if (self::nodeIdSignature($finalDiffs) !== self::nodeIdSignature($scanDiffs)) {
            throw new \RuntimeException('scan diff node id mismatch on upstream-shaped trial ' . $trial);
        }

        $shadowNodeIds = $session->shadowNodeIds();

        return [
            'trial' => $trial,
            'numElems' => $numElems,
            'numAlterations' => $numAlterations,
            'roundTrips' => $roundTrips,
            'requests' => $requestCount,
            'responses' => $responseCount,
            'diffCount' => count($finalDiffs),
            'scanDiffCount' => count($scanDiffs),
            'rootHash' => $remote->rootHash(),
            'shadowNodeId' => $session->shadowNodeId(),
            'maxShadowNodeId' => $shadowNodeIds === [] ? 0 : max($shadowNodeIds),
        ];
    }

    /**
     * @return array{
     *     trial: int,
     *     numElems: int,
     *     numAlterations: int,
     *     roundTrips: int,
     *     requests: int,
     *     responses: int,
     *     diffCount: int,
     *     scanDiffCount: int,
     *     rootHash: string,
     *     shadowNodeId: int,
     *     maxShadowNodeId: int,
     *     snapshotBytes: int,
     *     trackedLocalHeadNodeId: int,
     *     trackedRemoteHeadNodeId: int,
     *     restoredLocalHeadNodeId: int,
     *     restoredRemoteHeadNodeId: int,
     *     trackedDiffCount: int,
     *     trackedScanDiffCount: int,
     *     trackedSharedNodeCount: int
     * }
     */
    private function runPersistedTrackedTrial(int $trial, Mt19937 $rng): array
    {
        $seedTree = new SparseTree();
        $seedChanges = $seedTree->change();
        $trackedStore = new TrackedNodeStore();
        $trackedLocalHead = 'sync-fuzz-local-' . $trial;
        $trackedRemoteHead = 'sync-fuzz-remote-' . $trial;
        $trackedSeed = (new TrackedSparseTree($trackedStore))->checkout($trackedLocalHead);
        $trackedSeedChanges = $trackedSeed->change();
        $numElems = $rng->nextModulo(800);
        $maxElem = 1000;
        $numAlterations = $rng->nextModulo(200);

        for ($i = 0; $i < $numElems; $i++) {
            $number = $rng->nextModulo($maxElem);
            $key = Key::fromInteger($number);
            $value = (string) $number . str_repeat('A', $rng->nextModulo(60));
            $seedChanges->putKey($key, $value);
            $trackedSeedChanges->putKey($key, $value);
        }
        $seedChanges->apply();
        $trackedSeedChanges->apply();

        $local = clone $seedTree;
        $remote = clone $seedTree;
        $trackedRemote = $trackedSeed->fork($trackedRemoteHead);
        $remoteChanges = $remote->change();
        $trackedRemoteChanges = $trackedRemote->change();

        for ($i = 0; $i < $numAlterations; $i++) {
            $number = $rng->nextModulo($maxElem);
            $key = Key::fromInteger($number);
            if ($rng->nextModulo(2) === 0) {
                $value = (string) $number . ' new';
                $remoteChanges->putKey($key, $value);
                $trackedRemoteChanges->putKey($key, $value);
            } else {
                $remoteChanges->deleteKey($key);
                $trackedRemoteChanges->deleteKey($key);
            }
        }
        $remoteChanges->apply();
        $trackedRemoteChanges->apply();

        $trackedLocalHeadNodeId = $trackedSeed->headNodeId();
        $trackedRemoteHeadNodeId = $trackedRemote->headNodeId();
        $snapshotJson = json_encode($trackedStore->exportSnapshot(), JSON_THROW_ON_ERROR);
        $restoredStore = TrackedNodeStore::fromSnapshot(json_decode($snapshotJson, true, flags: JSON_THROW_ON_ERROR));
        $restoredLocal = (new TrackedSparseTree($restoredStore))->checkout($trackedLocalHead);
        $restoredRemote = (new TrackedSparseTree($restoredStore))->checkout($trackedRemoteHead);

        if ($restoredLocal->headNodeId() !== $trackedLocalHeadNodeId) {
            throw new \RuntimeException('persisted tracked local head node id mismatch on trial ' . $trial);
        }
        if ($restoredRemote->headNodeId() !== $trackedRemoteHeadNodeId) {
            throw new \RuntimeException('persisted tracked remote head node id mismatch on trial ' . $trial);
        }
        if ($restoredLocal->rootHash() !== $local->rootHash()) {
            throw new \RuntimeException('persisted tracked local root mismatch on trial ' . $trial);
        }
        if ($restoredRemote->rootHash() !== $remote->rootHash()) {
            throw new \RuntimeException('persisted tracked remote root mismatch on trial ' . $trial);
        }

        $session = new SyncSession($local, $this->initialDepthLimit, $this->laterDepthLimit);
        $scanDiffs = [];
        $roundTrips = 0;
        $requestCount = 0;
        $responseCount = 0;
        $converged = false;

        for (; $roundTrips < $this->maxRoundTrips; $roundTrips++) {
            $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
                $rng->nextModulo(1000) + 100,
                static function (DiffEntry $diff) use (&$scanDiffs): void {
                    $scanDiffs[] = $diff;
                }
            )));
            if ($requests === []) {
                $converged = true;
                break;
            }

            $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests(
                $requests,
                $rng->nextModulo(10000) + 2000
            )));

            $requestCount += count($requests);
            $responseCount += count($responses);
            $session->addResponses($requests, $responses);
        }

        if (!$converged) {
            throw new \RuntimeException('persisted tracked sync fuzz trial did not converge: ' . $trial);
        }

        $shadow = $session->shadow();
        $finalDiffs = $local->diffTo($shadow);
        $reconstructed = clone $local;
        $reconstructed->applyDiffs($finalDiffs);
        $trackedScanDiffs = [];
        $trackedFinalDiffs = $restoredLocal->diffTo(
            $restoredRemote,
            static function (DiffEntry $diff) use (&$trackedScanDiffs): void {
                $trackedScanDiffs[] = $diff;
            }
        );
        $trackedReconstructed = $restoredLocal->checkout($restoredLocal->headNodeId());
        $trackedReconstructed->applyDiffs($trackedFinalDiffs);

        if ($remote->rootHash() !== $shadow->rootHash()) {
            throw new \RuntimeException('shadow root mismatch on persisted tracked trial ' . $trial);
        }
        if ($remote->rootHash() !== $reconstructed->rootHash()) {
            throw new \RuntimeException('reconstructed root mismatch on persisted tracked trial ' . $trial);
        }
        if ($restoredRemote->rootHash() !== $trackedReconstructed->rootHash()) {
            throw new \RuntimeException('tracked reconstructed root mismatch on persisted tracked trial ' . $trial);
        }
        if (self::diffSignature($finalDiffs) !== self::diffSignature($scanDiffs)) {
            throw new \RuntimeException('scan diff mismatch on persisted tracked trial ' . $trial);
        }
        if (self::nodeIdSignature($finalDiffs) !== self::nodeIdSignature($scanDiffs)) {
            throw new \RuntimeException('scan diff node id mismatch on persisted tracked trial ' . $trial);
        }
        if (self::diffSignature($finalDiffs) !== self::diffSignature($trackedFinalDiffs)) {
            throw new \RuntimeException('tracked diff mismatch on persisted tracked trial ' . $trial);
        }
        if (self::diffSignature($trackedFinalDiffs) !== self::diffSignature($trackedScanDiffs)) {
            throw new \RuntimeException('tracked scan diff mismatch on persisted tracked trial ' . $trial);
        }

        $shadowNodeIds = $session->shadowNodeIds();

        return [
            'trial' => $trial,
            'numElems' => $numElems,
            'numAlterations' => $numAlterations,
            'roundTrips' => $roundTrips,
            'requests' => $requestCount,
            'responses' => $responseCount,
            'diffCount' => count($finalDiffs),
            'scanDiffCount' => count($scanDiffs),
            'rootHash' => $remote->rootHash(),
            'shadowNodeId' => $session->shadowNodeId(),
            'maxShadowNodeId' => $shadowNodeIds === [] ? 0 : max($shadowNodeIds),
            'snapshotBytes' => strlen($snapshotJson),
            'trackedLocalHeadNodeId' => $trackedLocalHeadNodeId,
            'trackedRemoteHeadNodeId' => $trackedRemoteHeadNodeId,
            'restoredLocalHeadNodeId' => $restoredLocal->headNodeId(),
            'restoredRemoteHeadNodeId' => $restoredRemote->headNodeId(),
            'trackedDiffCount' => count($trackedFinalDiffs),
            'trackedScanDiffCount' => count($trackedScanDiffs),
            'trackedSharedNodeCount' => count(array_intersect(
                $restoredLocal->walkNodeIds(),
                $restoredRemote->walkNodeIds()
            )),
        ];
    }

    /**
     * @param list<DiffEntry> $diffs
     *
     * @return list<string>
     */
    private static function diffSignature(array $diffs): array
    {
        $signature = array_map(
            static fn (DiffEntry $diff): string => $diff->type . ':' . $diff->keyHex() . ':' . $diff->value,
            $diffs
        );
        sort($signature, SORT_STRING);

        return $signature;
    }

    /**
     * @param list<DiffEntry> $diffs
     *
     * @return list<int>
     */
    private static function nodeIdSignature(array $diffs): array
    {
        $signature = array_map(static fn (DiffEntry $diff): int => $diff->nodeId, $diffs);
        usort($signature, static fn (int $a, int $b): int => $a <=> $b);

        return $signature;
    }
}
