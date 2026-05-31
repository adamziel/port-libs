<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeBaseFinder
{
    /**
     * @var \Closure(string): Commit
     */
    private readonly \Closure $readCommit;

    /**
     * @var array<string, Commit>
     */
    private array $commitCache = [];

    /**
     * @var array<string, array<string, int>>
     */
    private array $ancestorCache = [];

    public function __construct(callable $readCommit)
    {
        $this->readCommit = \Closure::fromCallable($readCommit);
    }

    public static function fromObjectDatabase(ObjectDatabase $database): self
    {
        return new self(static function (string $oid) use ($database): Commit {
            $object = $database->read($oid);
            if ($object->type !== 'commit') {
                throw new \InvalidArgumentException("Expected a commit object for {$oid}, got {$object->type}");
            }

            return Commit::parse($object->body);
        });
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

        $firstAncestors = $this->ancestorsWithDistance($first);
        $secondAncestors = $this->ancestorsWithDistance($second);
        $candidates = array_intersect_key($firstAncestors, $secondAncestors);
        if ($candidates === []) {
            return [];
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
            if (!$redundant) {
                $best[$candidate] = [
                    'first' => $firstAncestors[$candidate],
                    'second' => $secondAncestors[$candidate],
                ];
            }
        }

        uksort($best, static function (string $left, string $right) use ($best): int {
            $leftDistance = max($best[$left]['first'], $best[$left]['second']);
            $rightDistance = max($best[$right]['first'], $best[$right]['second']);

            return $leftDistance <=> $rightDistance
                ?: min($best[$left]['first'], $best[$left]['second']) <=> min($best[$right]['first'], $best[$right]['second'])
                ?: strcmp($left, $right);
        });

        return array_keys($best);
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

        $firstAncestors = $this->ancestorsWithDistance($first);
        $candidates = [];
        foreach ($normalizedOthers as $otherIndex => $other) {
            foreach ($this->ancestorsWithDistance($other) as $candidate => $otherDistance) {
                if (!isset($firstAncestors[$candidate])) {
                    continue;
                }

                if (!isset($candidates[$candidate]) || $otherDistance < $candidates[$candidate]['other']) {
                    $candidates[$candidate] = [
                        'first' => $firstAncestors[$candidate],
                        'other' => $otherDistance,
                        'otherIndex' => $otherIndex,
                    ];
                }
            }
        }

        if ($candidates === []) {
            return [];
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
            if (!$redundant) {
                $best[$candidate] = $candidates[$candidate];
            }
        }

        uksort($best, static function (string $left, string $right) use ($best): int {
            $leftDistance = max($best[$left]['first'], $best[$left]['other']);
            $rightDistance = max($best[$right]['first'], $best[$right]['other']);

            return $leftDistance <=> $rightDistance
                ?: ($best[$left]['first'] + $best[$left]['other']) <=> ($best[$right]['first'] + $best[$right]['other'])
                ?: $best[$left]['otherIndex'] <=> $best[$right]['otherIndex']
                ?: strcmp($left, $right);
        });

        return array_keys($best);
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
        if (!isset($this->commitCache[$oid])) {
            $commit = ($this->readCommit)($oid);
            if (!$commit instanceof Commit) {
                throw new \InvalidArgumentException('Commit reader must return ' . Commit::class);
            }
            $this->commitCache[$oid] = $commit;
        }

        return $this->commitCache[$oid];
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
