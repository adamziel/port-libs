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
        self::assertObjectId($first);
        self::assertObjectId($second);
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
     * @return array<string, int>
     */
    private function ancestorsWithDistance(string $oid): array
    {
        if (isset($this->ancestorCache[$oid])) {
            return $this->ancestorCache[$oid];
        }

        $distances = [$oid => 0];
        $queue = [[$oid, 0]];
        for ($index = 0; $index < count($queue); $index++) {
            [$current, $distance] = $queue[$index];
            foreach ($this->commit($current)->parents as $parent) {
                $parent = strtolower($parent);
                self::assertObjectId($parent);
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

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Merge-base object id must be a 40-character SHA-1 hex string');
        }
    }
}
