<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeBaseFinder
{
    private const FLAG_COMMIT1 = 1;
    private const FLAG_COMMIT2 = 2;
    private const FLAG_STALE = 4;
    private const FLAG_RESULT = 8;

    /**
     * @var \Closure(string): (?Commit)
     */
    private readonly \Closure $readCommit;

    /**
     * @var ?\Closure(string): (?int)
     */
    private readonly ?\Closure $commitGraphGeneration;

    /**
     * @var array<string, Commit>
     */
    private array $commitCache = [];

    /**
     * @var array<string, array<string, int>>
     */
    private array $ancestorCache = [];

    /**
     * @var array<string, int>
     */
    private array $generationCache = [];

    /**
     * @var array<string, ?int>
     */
    private array $commitGraphGenerationCache = [];

    /**
     * @var array<string, int>
     */
    private array $commitTimeCache = [];

    public function __construct(
        callable $readCommit,
        private readonly bool $useCommitGraphGenerations = true,
        ?callable $commitGraphGeneration = null,
    )
    {
        $this->readCommit = \Closure::fromCallable($readCommit);
        $this->commitGraphGeneration = $commitGraphGeneration === null
            ? null
            : \Closure::fromCallable($commitGraphGeneration);
    }

    public static function fromObjectDatabase(
        ObjectDatabase $database,
        bool $useCommitGraphGenerations = true,
        ?callable $commitGraphGeneration = null,
    ): self
    {
        return new self(static function (string $oid) use ($database): Commit {
            $object = $database->read($oid);
            if ($object->type !== 'commit') {
                throw new \InvalidArgumentException("Expected a commit object for {$oid}, got {$object->type}");
            }

            return Commit::parse($object->body);
        }, $useCommitGraphGenerations, $commitGraphGeneration);
    }

    /**
     * @return list<string>
     */
    public function mergeBases(string $first, string $second): array
    {
        $hashLength = self::assertObjectId($first);
        self::assertSameObjectFormat($hashLength, $second);
        $first = strtolower($first);
        $second = strtolower($second);

        if ($first === $second) {
            return [$first];
        }

        $candidates = $this->paintDownToCommon($first, [$second], $hashLength);
        if ($candidates === []) {
            return [];
        }
        if (count($candidates) === 1) {
            return $candidates;
        }

        return $this->orderMergeBaseCandidates($this->removeRedundantCandidates($candidates, $hashLength));
    }

    public function mergeBase(string $first, string $second): ?string
    {
        return $this->mergeBases($first, $second)[0] ?? null;
    }

    /**
     * Return merge bases between one commit and a hypothetical merge of other commits.
     *
     * This mirrors gix_revision::merge_base(first, others): the other heads are
     * painted as one side of the graph walk. For a stable all-head intersection,
     * use mergeBasesMany().
     *
     * @param list<string> $others
     * @return list<string>
     */
    public function mergeBasesAgainst(string $first, array $others): array
    {
        $hashLength = self::assertObjectId($first);
        $first = strtolower($first);

        $normalizedOthers = [];
        foreach ($others as $other) {
            if (!is_string($other)) {
                throw new \InvalidArgumentException('Merge-base other heads must be object id strings');
            }
            self::assertSameObjectFormat($hashLength, $other);
            $normalizedOthers[] = strtolower($other);
        }

        if ($normalizedOthers === [] || in_array($first, $normalizedOthers, true)) {
            return [$first];
        }

        $candidates = $this->paintDownToCommon($first, $normalizedOthers, $hashLength);
        if ($candidates === []) {
            return [];
        }
        if (count($candidates) === 1) {
            return $candidates;
        }

        return $this->orderMergeBaseCandidates($this->removeRedundantCandidates($candidates, $hashLength));
    }

    /**
     * Paint the graph from `first` and the union of `others` until all queued
     * commits are stale, mirroring gix_revision::merge_base()'s lazy walk.
     *
     * @param list<string> $others
     * @return list<string>
     */
    private function paintDownToCommon(string $first, array $others, int $hashLength): array
    {
        $flagsByCommit = [];
        $queue = [];
        $results = [];

        $enqueue = function (string $oid, int $flags) use (&$flagsByCommit, &$queue, $hashLength): void {
            $oid = strtolower($oid);
            self::assertSameObjectFormat($hashLength, $oid);
            $currentFlags = $flagsByCommit[$oid] ?? 0;
            if (($currentFlags & $flags) === $flags) {
                return;
            }

            $priority = $this->walkPriorityIfPresent($oid);
            if ($priority === null) {
                return;
            }

            $flagsByCommit[$oid] = $currentFlags | $flags;
            $queue[] = [
                'oid' => $oid,
                'priority' => $priority,
            ];
        };

        $enqueue($first, self::FLAG_COMMIT1);
        foreach ($others as $other) {
            $enqueue($other, self::FLAG_COMMIT2);
        }

        while ($this->queueContainsNonStaleCommit($queue, $flagsByCommit)) {
            $item = $this->popHighestPriority($queue);
            $commitId = $item['oid'];
            $flags = $flagsByCommit[$commitId] ?? 0;
            $flagsWithoutResult = $flags & (self::FLAG_COMMIT1 | self::FLAG_COMMIT2 | self::FLAG_STALE);

            if ($flagsWithoutResult === (self::FLAG_COMMIT1 | self::FLAG_COMMIT2)) {
                if (($flags & self::FLAG_RESULT) === 0) {
                    $flagsByCommit[$commitId] = $flags | self::FLAG_RESULT;
                    $results[] = $commitId;
                }
                $flagsWithoutResult |= self::FLAG_STALE;
            }

            foreach ($this->commit($commitId)->parents as $parent) {
                $parent = strtolower($parent);
                self::assertSameObjectFormat($hashLength, $parent);
                $enqueue($parent, $flagsWithoutResult);
            }
        }

        return $results;
    }

    /**
     * Remove candidates reachable from another candidate with a bounded
     * generation walk, matching gix_revision::merge_base::remove_redundant().
     *
     * @param list<string> $candidates
     * @return list<string>
     */
    private function removeRedundantCandidates(array $candidates, int $hashLength): array
    {
        if (count($candidates) <= 1) {
            return $candidates;
        }

        $flagsByCommit = [];
        $candidateGenerations = [];
        $walkStart = [];
        foreach ($candidates as $candidate) {
            self::assertSameObjectFormat($hashLength, $candidate);
            $flagsByCommit[$candidate] = ($flagsByCommit[$candidate] ?? 0) | self::FLAG_RESULT;
            $candidateGenerations[$candidate] = $this->redundantWalkGeneration($candidate);

            foreach ($this->commit($candidate)->parents as $parent) {
                $parent = strtolower($parent);
                self::assertSameObjectFormat($hashLength, $parent);
                if ((($flagsByCommit[$parent] ?? 0) & self::FLAG_STALE) !== 0) {
                    continue;
                }
                if ($this->tryCommit($parent) === null) {
                    continue;
                }
                $flagsByCommit[$parent] = ($flagsByCommit[$parent] ?? 0) | self::FLAG_STALE;
                $walkStart[] = $parent;
            }
        }

        sort($walkStart, SORT_STRING);
        foreach ($walkStart as $commitId) {
            $flagsByCommit[$commitId] = ($flagsByCommit[$commitId] ?? 0) & ~self::FLAG_STALE;
        }
        $remaining = count($candidates);
        $minGeneration = $this->lowestLiveCandidateGeneration($candidates, $candidateGenerations, $flagsByCommit);
        while ($walkStart !== [] && $remaining > 1) {
            $commitId = array_pop($walkStart);
            $flagsByCommit[$commitId] = ($flagsByCommit[$commitId] ?? 0) | self::FLAG_STALE;
            $stack = [$commitId];

            while ($stack !== [] && $remaining > 1) {
                $current = $stack[count($stack) - 1];
                $currentFlags = $flagsByCommit[$current] ?? 0;
                if (($currentFlags & self::FLAG_RESULT) !== 0) {
                    $flagsByCommit[$current] = $currentFlags & ~self::FLAG_RESULT;
                    $remaining--;
                    if ($remaining <= 1) {
                        break;
                    }
                    $minGeneration = $this->lowestLiveCandidateGeneration($candidates, $candidateGenerations, $flagsByCommit);
                }

                if ($this->redundantWalkGeneration($current) < $minGeneration) {
                    array_pop($stack);
                    continue;
                }

                $previousCount = count($stack);
                foreach ($this->commit($current)->parents as $parent) {
                    $parent = strtolower($parent);
                    self::assertSameObjectFormat($hashLength, $parent);
                    if ((($flagsByCommit[$parent] ?? 0) & self::FLAG_STALE) !== 0) {
                        continue;
                    }
                    if ($this->tryCommit($parent) === null) {
                        continue;
                    }
                    $flagsByCommit[$parent] = ($flagsByCommit[$parent] ?? 0) | self::FLAG_STALE;
                    $stack[] = $parent;
                    break;
                }

                if ($previousCount === count($stack)) {
                    array_pop($stack);
                }
            }
        }

        return array_values(array_filter(
            $candidates,
            static fn (string $candidate): bool => (($flagsByCommit[$candidate] ?? 0) & self::FLAG_STALE) === 0,
        ));
    }

    /**
     * @param list<string> $candidates
     * @return list<string>
     */
    private function orderMergeBaseCandidates(array $candidates): array
    {
        usort($candidates, function (string $left, string $right): int {
            return $this->compareCommitPriority($left, $right)
                ?: strcmp($left, $right);
        });

        return $candidates;
    }

    /**
     * @param list<string> $candidates
     * @param array<string, int> $candidateGenerations
     * @param array<string, int> $flagsByCommit
     */
    private function lowestLiveCandidateGeneration(array $candidates, array $candidateGenerations, array $flagsByCommit): int
    {
        $minGeneration = PHP_INT_MAX;
        foreach ($candidates as $candidate) {
            if ((($flagsByCommit[$candidate] ?? 0) & self::FLAG_STALE) !== 0) {
                continue;
            }
            $minGeneration = min($minGeneration, $candidateGenerations[$candidate]);
        }

        return $minGeneration;
    }

    private function redundantWalkGeneration(string $oid): int
    {
        if (!$this->useCommitGraphGenerations) {
            return PHP_INT_MAX;
        }

        return $this->graphWalkGeneration($oid);
    }

    /**
     * @param list<string> $others
     */
    public function mergeBaseAgainst(string $first, array $others): ?string
    {
        return $this->mergeBasesAgainst($first, $others)[0] ?? null;
    }

    /**
     * @param list<string> $heads
     * @return list<string>
     */
    public function mergeBasesMany(array $heads): array
    {
        if ($heads === []) {
            throw new \InvalidArgumentException('At least one merge-base head is required');
        }

        $normalized = [];
        $hashLength = null;
        foreach ($heads as $head) {
            if (!is_string($head)) {
                throw new \InvalidArgumentException('Merge-base heads must be object id strings');
            }
            $headLength = self::assertObjectId($head);
            if ($hashLength === null) {
                $hashLength = $headLength;
            } elseif ($headLength !== $hashLength) {
                throw new \InvalidArgumentException('Merge-base object ids must all use the same hash algorithm');
            }
            $normalized[] = strtolower($head);
        }

        if (count($normalized) === 1) {
            return [$normalized[0]];
        }

        $ancestorSets = [];
        foreach ($normalized as $head) {
            $ancestorSets[] = $this->ancestorsWithDistance($head);
        }

        $candidates = $ancestorSets[0];
        foreach (array_slice($ancestorSets, 1) as $ancestors) {
            $candidates = array_intersect_key($candidates, $ancestors);
            if ($candidates === []) {
                return [];
            }
        }

        $best = [];
        foreach (array_keys($candidates) as $candidate) {
            $redundant = false;
            foreach (array_keys($candidates) as $other) {
                if ($candidate === $other) {
                    continue;
                }
                if (isset($this->ancestorsWithDistance($other)[$candidate])) {
                    $redundant = true;
                    break;
                }
            }
            if ($redundant) {
                continue;
            }

            $best[$candidate] = array_map(
                static fn (array $ancestors): int => $ancestors[$candidate],
                $ancestorSets,
            );
        }

        uksort($best, static function (string $left, string $right) use ($best): int {
            $leftDistances = $best[$left];
            $rightDistances = $best[$right];

            return max($leftDistances) <=> max($rightDistances)
                ?: array_sum($leftDistances) <=> array_sum($rightDistances)
                ?: strcmp($left, $right);
        });

        return array_keys($best);
    }

    /**
     * @param list<string> $heads
     */
    public function mergeBaseMany(array $heads): ?string
    {
        return $this->mergeBasesMany($heads)[0] ?? null;
    }

    /**
     * Return the upstream octopus merge-base result for an ordered head list.
     *
     * This mirrors gix_revision::merge_base::octopus(): it repeatedly merges
     * the current result with the next head and keeps only the first base from
     * each pairwise graph walk. For a stable all-head intersection, use
     * mergeBasesMany().
     *
     * @param list<string> $heads
     */
    public function mergeBaseOctopus(array $heads): ?string
    {
        if ($heads === []) {
            throw new \InvalidArgumentException('At least one merge-base head is required');
        }

        $normalized = [];
        $hashLength = null;
        foreach ($heads as $head) {
            if (!is_string($head)) {
                throw new \InvalidArgumentException('Merge-base heads must be object id strings');
            }
            $headLength = self::assertObjectId($head);
            if ($hashLength === null) {
                $hashLength = $headLength;
            } elseif ($headLength !== $hashLength) {
                throw new \InvalidArgumentException('Merge-base object ids must all use the same hash algorithm');
            }
            $normalized[] = strtolower($head);
        }

        $current = array_shift($normalized);
        if ($current === null) {
            return null;
        }

        foreach ($normalized as $other) {
            $next = $this->mergeBaseAgainst($current, [$other]);
            if ($next === null) {
                return null;
            }
            $current = $next;
        }

        return $current;
    }

    /**
     * @return array<string, int>
     */
    private function ancestorsWithDistance(string $oid): array
    {
        $hashLength = self::assertObjectId($oid);
        if (isset($this->ancestorCache[$oid])) {
            return $this->ancestorCache[$oid];
        }

        $distances = [$oid => 0];
        $queue = [[$oid, 0]];
        for ($index = 0; $index < count($queue); $index++) {
            [$current, $distance] = $queue[$index];
            foreach ($this->commit($current)->parents as $parent) {
                $parent = strtolower($parent);
                self::assertSameObjectFormat($hashLength, $parent);
                $parentDistance = $distance + 1;
                if (isset($distances[$parent]) && $distances[$parent] <= $parentDistance) {
                    continue;
                }
                $distances[$parent] = $parentDistance;
                $queue[] = [$parent, $parentDistance];
            }
        }

        return $this->ancestorCache[$oid] = $distances;
    }

    private function commit(string $oid): Commit
    {
        $commit = $this->tryCommit($oid);
        if ($commit === null) {
            throw new \RuntimeException("Missing commit object: {$oid}");
        }

        return $commit;
    }

    private function tryCommit(string $oid): ?Commit
    {
        $oid = strtolower($oid);
        if (!isset($this->commitCache[$oid])) {
            $commit = ($this->readCommit)($oid);
            if ($commit === null) {
                return null;
            }
            if (!$commit instanceof Commit) {
                throw new \InvalidArgumentException('Commit reader must return ' . Commit::class . ' or null');
            }
            $this->commitCache[$oid] = $commit;
        }

        return $this->commitCache[$oid];
    }

    /**
     * @param list<array{oid: string, priority: array{int, int, string}}> $queue
     * @param array<string, int> $flagsByCommit
     */
    private function queueContainsNonStaleCommit(array $queue, array $flagsByCommit): bool
    {
        foreach ($queue as $item) {
            $flags = $flagsByCommit[$item['oid']] ?? 0;
            if (($flags & self::FLAG_STALE) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{oid: string, priority: array{int, int, string}}> $queue
     * @return array{oid: string, priority: array{int, int, string}}
     */
    private function popHighestPriority(array &$queue): array
    {
        $bestIndex = 0;
        foreach ($queue as $index => $item) {
            if ($this->compareWalkPriority($item['priority'], $queue[$bestIndex]['priority']) > 0) {
                $bestIndex = $index;
            }
        }

        $best = $queue[$bestIndex];
        array_splice($queue, $bestIndex, 1);

        return $best;
    }

    /**
     * @return ?array{int, int, string}
     */
    private function walkPriorityIfPresent(string $oid): ?array
    {
        $commitTime = $this->tryCommitTime($oid);
        if ($commitTime === null) {
            return null;
        }

        return [
            $this->useCommitGraphGenerations ? $this->graphWalkGeneration($oid) : 0,
            $commitTime,
            $oid,
        ];
    }

    /**
     * @param array{int, int, string} $left
     * @param array{int, int, string} $right
     */
    private function compareWalkPriority(array $left, array $right): int
    {
        return $left[0] <=> $right[0]
            ?: $left[1] <=> $right[1]
            ?: strcmp($left[2], $right[2]);
    }

    private function compareCommitPriority(string $left, string $right): int
    {
        if (!$this->useCommitGraphGenerations) {
            return $this->commitTime($right) <=> $this->commitTime($left);
        }

        return $this->graphWalkGeneration($right) <=> $this->graphWalkGeneration($left)
            ?: $this->commitTime($right) <=> $this->commitTime($left);
    }

    private function graphWalkGeneration(string $oid): int
    {
        if ($this->commitGraphGeneration !== null) {
            return $this->providedCommitGraphGeneration($oid) ?? PHP_INT_MAX;
        }

        return $this->commitGeneration($oid) ?? PHP_INT_MAX;
    }

    private function providedCommitGraphGeneration(string $oid): ?int
    {
        $oid = strtolower($oid);
        if (array_key_exists($oid, $this->commitGraphGenerationCache)) {
            return $this->commitGraphGenerationCache[$oid];
        }

        $generation = ($this->commitGraphGeneration)($oid);
        if ($generation !== null && (!is_int($generation) || $generation < 0)) {
            throw new \InvalidArgumentException('Commit graph generation provider must return a non-negative integer or null');
        }

        return $this->commitGraphGenerationCache[$oid] = $generation;
    }

    private function commitGeneration(string $oid): ?int
    {
        $generation = $this->commitGenerationInfo($oid);

        return $generation === null ? null : $generation['generation'];
    }

    /**
     * @param array<string, true> $visiting
     * @return ?array{generation: int, complete: bool}
     */
    private function commitGenerationInfo(string $oid, array &$visiting = []): ?array
    {
        $oid = strtolower($oid);
        if (isset($this->generationCache[$oid])) {
            return [
                'generation' => $this->generationCache[$oid],
                'complete' => true,
            ];
        }

        $hashLength = self::assertObjectId($oid);
        $commit = $this->tryCommit($oid);
        if ($commit === null) {
            return null;
        }

        if (isset($visiting[$oid])) {
            return [
                'generation' => 1,
                'complete' => false,
            ];
        }

        $visiting[$oid] = true;
        $generation = 1;
        $complete = true;
        foreach ($commit->parents as $parent) {
            $parent = strtolower($parent);
            self::assertSameObjectFormat($hashLength, $parent);
            $parentGeneration = $this->commitGenerationInfo($parent, $visiting);
            if ($parentGeneration === null) {
                $complete = false;
                continue;
            }
            if (!$parentGeneration['complete']) {
                $complete = false;
            }
            $generation = max($generation, $parentGeneration['generation'] + 1);
        }
        unset($visiting[$oid]);

        if ($complete) {
            $this->generationCache[$oid] = $generation;
        }

        return [
            'generation' => $generation,
            'complete' => $complete,
        ];
    }

    private function commitTime(string $oid): int
    {
        $commitTime = $this->tryCommitTime($oid);
        if ($commitTime === null) {
            throw new \RuntimeException("Missing commit object: {$oid}");
        }

        return $commitTime;
    }

    private function tryCommitTime(string $oid): ?int
    {
        $oid = strtolower($oid);
        if (!isset($this->commitTimeCache[$oid])) {
            $commit = $this->tryCommit($oid);
            if ($commit === null) {
                return null;
            }
            $this->commitTimeCache[$oid] = $commit->committerSignature()->seconds();
        }

        return $this->commitTimeCache[$oid];
    }

    private static function assertObjectId(string $oid): int
    {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Merge-base object id must be a 40-character SHA-1 or 64-character SHA-256 hex string');
        }

        return strlen($oid);
    }

    private static function assertSameObjectFormat(int $hashLength, string $oid): void
    {
        if (self::assertObjectId($oid) !== $hashLength) {
            throw new \InvalidArgumentException('Merge-base object ids must all use the same hash algorithm');
        }
    }
}
