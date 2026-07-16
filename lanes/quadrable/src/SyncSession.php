<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class SyncSession
{
    private ?SparseTree $shadow = null;
    private bool $initialized = false;

    /**
     * @var array<string, true>
     */
    private array $scanDiffsSeen = [];

    public function __construct(
        private readonly SparseTree $local,
        private readonly int $initialDepthLimit = 4,
        private readonly int $laterDepthLimit = 4
    ) {
        if ($initialDepthLimit < 0 || $initialDepthLimit > 255 || $laterDepthLimit < 0 || $laterDepthLimit > 255) {
            throw new \InvalidArgumentException('sync depth limits must be between 0 and 255');
        }
    }

    /**
     * @return list<SyncRequest>
     */
    public function getRequests(int $bytesBudget = PHP_INT_MAX, ?callable $onDiff = null): array
    {
        if ($bytesBudget === 0) {
            throw new \InvalidArgumentException("bytesBudget can't be 0");
        }

        if (!$this->initialized) {
            return [
                new SyncRequest(Key::null(), 0, $this->initialDepthLimit, false),
            ];
        }

        if ($this->shadow === null) {
            throw new \RuntimeException('sync shadow missing after initialization');
        }

        return $this->local->syncRequestsForShadow(
            $this->shadow,
            $this->laterDepthLimit,
            $bytesBudget,
            $this->dedupeScanDiffCallback($onDiff)
        );
    }

    /**
     * @param list<SyncRequest> $requests
     * @param list<Proof> $responses
     */
    public function addResponses(array $requests, array $responses): void
    {
        $this->shadow ??= new SparseTree();
        $this->shadow->importSyncResponses($requests, $responses);
        $this->initialized = true;
    }

    public function shadow(): SparseTree
    {
        if ($this->shadow === null) {
            throw new \RuntimeException('sync shadow is not initialized');
        }

        return $this->shadow;
    }

    public function shadowNodeId(): int
    {
        return $this->shadow()->partialRootNodeId();
    }

    /**
     * @return list<int>
     */
    public function shadowNodeIds(): array
    {
        return $this->shadow()->partialNodeIds();
    }

    private function dedupeScanDiffCallback(?callable $onDiff): ?callable
    {
        if ($onDiff === null) {
            return null;
        }

        return function (DiffEntry $diff) use ($onDiff): void {
            $signature = $diff->type . "\0" . $diff->keyHex() . "\0" . $diff->value;
            if (isset($this->scanDiffsSeen[$signature])) {
                return;
            }

            $this->scanDiffsSeen[$signature] = true;
            $onDiff($diff);
        };
    }
}
