<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\MergeBaseCommand;
use PortLibs\Gitoxide\MergeBaseFinder;
use PortLibs\Gitoxide\ObjectDatabase;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$commit = static fn (array $parents = []): Commit => new Commit(
    str_repeat('f', 40),
    $parents,
    'Ada <ada@example.test> 1700000000 +0000',
    'CI <ci@example.test> 1700000000 +0000',
    "commit\n",
    [
        'tree' => [str_repeat('f', 40)],
        'parent' => $parents,
        'author' => ['Ada <ada@example.test> 1700000000 +0000'],
        'committer' => ['CI <ci@example.test> 1700000000 +0000'],
    ],
);
$finder = static function (array $commits, bool $useCommitGraphGenerations = true): MergeBaseFinder {
    return new MergeBaseFinder(static function (string $oid) use ($commits): Commit {
        if (!isset($commits[$oid])) {
            throw new RuntimeException("Missing commit fixture: {$oid}");
        }

        return $commits[$oid];
    }, $useCommitGraphGenerations);
};

return [
    'finds nearest merge base in a linear history' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $root = $oid('1');
        $middle = $oid('2');
        $head = $oid('3');

        $mergeBase = $finder([
            $root => $commit(),
            $middle => $commit([$root]),
            $head => $commit([$middle]),
        ]);

        $t->same($middle, $mergeBase->mergeBase($head, $middle));
        $t->same([$middle], $mergeBase->mergeBases($head, $middle));
    },
    'returns independent criss-cross merge bases' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $root = $oid('1');
        $leftBase = $oid('2');
        $rightBase = $oid('3');
        $leftMerge = $oid('4');
        $rightMerge = $oid('5');

        $mergeBase = $finder([
            $root => $commit(),
            $leftBase => $commit([$root]),
            $rightBase => $commit([$root]),
            $leftMerge => $commit([$leftBase, $rightBase]),
            $rightMerge => $commit([$rightBase, $leftBase]),
        ]);

        $t->same([$leftBase, $rightBase], $mergeBase->mergeBases($leftMerge, $rightMerge));
    },
    'maps upstream graph walk commit time priority for independent bases' => static function (TestRunner $t) use ($oid, $finder): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $root = $oid('1');
        $olderBase = $oid('2');
        $newerBase = $oid('3');
        $leftMerge = $oid('4');
        $rightMerge = $oid('5');

        $mergeBase = $finder([
            $root => $timedCommit(1700000000),
            $olderBase => $timedCommit(1700000010, [$root]),
            $newerBase => $timedCommit(1700000020, [$root]),
            $leftMerge => $timedCommit(1700000030, [$olderBase, $newerBase]),
            $rightMerge => $timedCommit(1700000040, [$newerBase, $olderBase]),
        ]);

        $t->same([$newerBase, $olderBase], $mergeBase->mergeBases($leftMerge, $rightMerge));
        $t->same($newerBase, $mergeBase->mergeBase($leftMerge, $rightMerge));
        $t->same([$newerBase, $olderBase], $mergeBase->mergeBasesAgainst($leftMerge, [$rightMerge]));
        $t->same($newerBase, $mergeBase->mergeBaseAgainst($leftMerge, [$rightMerge]));
    },
    'maps upstream graph walk without commitgraph to commit time priority' => static function (TestRunner $t) use ($finder): void {
        $oid = static fn (string $hex): string => str_repeat($hex, 20);
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $root = $oid('10');
        $intermediate = $oid('20');
        $olderDeepBase = $oid('30');
        $newerShallowBase = $oid('40');
        $leftMerge = $oid('50');
        $rightMerge = $oid('60');
        $commits = [
            $root => $timedCommit(1700000000),
            $intermediate => $timedCommit(1700000010, [$root]),
            $olderDeepBase => $timedCommit(1700000020, [$intermediate]),
            $newerShallowBase => $timedCommit(1700000100, [$root]),
            $leftMerge => $timedCommit(1700000200, [$olderDeepBase, $newerShallowBase]),
            $rightMerge => $timedCommit(1700000300, [$newerShallowBase, $olderDeepBase]),
        ];

        $withCommitGraph = $finder($commits);
        $withoutCommitGraph = $finder($commits, false);

        $t->same([$olderDeepBase, $newerShallowBase], $withCommitGraph->mergeBases($leftMerge, $rightMerge));
        $t->same($olderDeepBase, $withCommitGraph->mergeBaseAgainst($leftMerge, [$rightMerge]));
        $t->same([$newerShallowBase, $olderDeepBase], $withoutCommitGraph->mergeBases($leftMerge, $rightMerge));
        $t->same($newerShallowBase, $withoutCommitGraph->mergeBase($leftMerge, $rightMerge));
        $t->same([$newerShallowBase, $olderDeepBase], $withoutCommitGraph->mergeBasesAgainst($leftMerge, [$rightMerge]));
        $t->same($newerShallowBase, $withoutCommitGraph->mergeBaseAgainst($leftMerge, [$rightMerge]));
    },
    'maps upstream graph walk against a hypothetical merge of other heads' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $root = $oid('1');
        $shared = $oid('2');
        $first = $oid('3');
        $relatedOther = $oid('4');
        $unrelatedRoot = $oid('8');
        $unrelatedOther = $oid('9');

        $mergeBase = $finder([
            $root => $commit(),
            $shared => $commit([$root]),
            $first => $commit([$shared]),
            $relatedOther => $commit([$shared]),
            $unrelatedRoot => $commit(),
            $unrelatedOther => $commit([$unrelatedRoot]),
        ]);

        $t->same([$shared], $mergeBase->mergeBasesAgainst($first, [$relatedOther, $unrelatedOther]));
        $t->same($shared, $mergeBase->mergeBaseAgainst($first, [$unrelatedOther, $relatedOther]));
        $t->same([], $mergeBase->mergeBasesMany([$first, $relatedOther, $unrelatedOther]));
    },
    'maps upstream repository merge-base human output over graph walk' => static function (TestRunner $t) use ($oid, $finder): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $release = $oid('1');
        $legacyBase = $oid('2');
        $securityBase = $oid('3');
        $pluginHotfix = $oid('4');
        $themeHotfix = $oid('5');
        $archivedReview = $oid('9');
        $mergeBase = $finder([
            $release => $timedCommit(1700000000),
            $legacyBase => $timedCommit(1700000010, [$release]),
            $securityBase => $timedCommit(1700000020, [$release]),
            $pluginHotfix => $timedCommit(1700000030, [$legacyBase, $securityBase]),
            $themeHotfix => $timedCommit(1700000040, [$securityBase, $legacyBase]),
            $archivedReview => $timedCommit(1700000050),
        ]);

        $t->same(
            $securityBase . "\n" . $legacyBase . "\n",
            MergeBaseCommand::humanOutput($mergeBase, $pluginHotfix, [$themeHotfix]),
        );
        $t->same($pluginHotfix . "\n", MergeBaseCommand::humanOutput($mergeBase, $pluginHotfix, []));
        $t->same(
            $pluginHotfix . "\n",
            MergeBaseCommand::humanOutput($mergeBase, $pluginHotfix, [$archivedReview, $pluginHotfix]),
        );

        try {
            MergeBaseCommand::humanOutput($mergeBase, $pluginHotfix, [$archivedReview]);
        } catch (RuntimeException $exception) {
            $t->contains(
                "No base found for {$pluginHotfix} and {$archivedReview}",
                $exception->getMessage(),
            );

            return;
        }

        throw new RuntimeException('Expected no-base merge-base command output to fail');
    },
    'maps upstream graph walk shortcuts without reading commits' => static function (TestRunner $t) use ($oid): void {
        $first = $oid('1');
        $unrelated = $oid('9');
        $reads = [];
        $mergeBase = new MergeBaseFinder(static function (string $oid) use (&$reads): Commit {
            $reads[] = $oid;
            throw new RuntimeException('The upstream shortcut should not read commit objects');
        });

        $t->same([$first], $mergeBase->mergeBasesAgainst($first, []));
        $t->same($first, $mergeBase->mergeBaseAgainst($first, [$unrelated, $first]));
        $t->same([$first], $mergeBase->mergeBasesAgainst($first, [$first, $unrelated]));
        $t->same($first, $mergeBase->mergeBaseAgainst($first, [$first, $unrelated]));
        $t->same([], $reads);
        $t->throws(InvalidArgumentException::class, static fn () => $mergeBase->mergeBasesAgainst($first, [123]));
    },
    'maps upstream generated disjoint shortcut baseline without graph reads' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $disjointA = $oid('a');
        $disjointB = $oid('b');
        $reads = [];
        $shortcutFinder = new MergeBaseFinder(static function (string $oid) use (&$reads): Commit {
            $reads[] = $oid;
            throw new RuntimeException('gix_revision::merge_base should shortcut before graph reads');
        });

        $t->same([$disjointA], $shortcutFinder->mergeBasesAgainst($disjointA, [$disjointA, $disjointB]));
        $t->same($disjointA, $shortcutFinder->mergeBaseAgainst($disjointA, [$disjointB, $disjointA]));
        $t->same([], $reads);

        $mergeBase = $finder([
            $disjointA => $commit(),
            $disjointB => $commit(),
        ]);

        $t->same([], $mergeBase->mergeBasesAgainst($disjointA, [$disjointB]));
        $t->same([], $mergeBase->mergeBases($disjointA, $disjointB));
        $t->same([$disjointA], $mergeBase->mergeBasesAgainst($disjointA, [$disjointA, $disjointB]));
        $t->same([$disjointB], $mergeBase->mergeBasesAgainst($disjointB, [$disjointB]));
    },
    'maps upstream graph walk stale-queue stop without reading deep shallow ancestors' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $missingGrandparent = $oid('0');
        $staleParent = $oid('1');
        $shared = $oid('2');
        $left = $oid('3');
        $right = $oid('4');
        $unrelated = $oid('5');
        $reads = [];
        $commits = [
            $staleParent => $timedCommit(1699999900, [$missingGrandparent]),
            $shared => $timedCommit(1700000000, [$staleParent]),
            $left => $timedCommit(1700000100, [$shared]),
            $right => $timedCommit(1700000200, [$shared]),
            $unrelated => $timedCommit(1700000300),
        ];
        $mergeBase = new MergeBaseFinder(static function (string $oid) use ($commits, $missingGrandparent, &$reads): Commit {
            $reads[] = $oid;
            if ($oid === $missingGrandparent) {
                throw new RuntimeException('Upstream graph walk should stop before reading stale shallow ancestors');
            }
            if (!isset($commits[$oid])) {
                throw new RuntimeException("Missing commit fixture: {$oid}");
            }

            return $commits[$oid];
        }, useCommitGraphGenerations: false);

        $t->same([$shared], $mergeBase->mergeBasesAgainst($left, [$right, $unrelated]));
        $t->same($shared, $mergeBase->mergeBase($left, $right));
        $t->same(true, in_array($staleParent, $reads, true));
        $t->same(false, in_array($missingGrandparent, $reads, true));
    },
    'maps upstream commit graph metadata without recursively inflating stale ancestors' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $missingGrandparent = $oid('0');
        $staleParent = $oid('1');
        $shared = $oid('2');
        $left = $oid('3');
        $right = $oid('4');
        $unrelated = $oid('5');
        $reads = [];
        $generationReads = [];
        $commits = [
            $staleParent => $timedCommit(1699999900, [$missingGrandparent]),
            $shared => $timedCommit(1700000000, [$staleParent]),
            $left => $timedCommit(1700000100, [$shared]),
            $right => $timedCommit(1700000200, [$shared]),
            $unrelated => $timedCommit(1700000300),
        ];
        $generations = [
            $staleParent => 1,
            $shared => 2,
            $left => 3,
            $right => 3,
            $unrelated => 1,
        ];
        $mergeBase = new MergeBaseFinder(
            static function (string $oid) use ($commits, $missingGrandparent, &$reads): Commit {
                $reads[] = $oid;
                if ($oid === $missingGrandparent) {
                    throw new RuntimeException('Commit-graph-backed walk should not recursively inflate stale ancestors');
                }
                if (!isset($commits[$oid])) {
                    throw new RuntimeException("Missing commit fixture: {$oid}");
                }

                return $commits[$oid];
            },
            commitGraphGeneration: static function (string $oid) use ($generations, &$generationReads): ?int {
                $generationReads[] = $oid;

                return $generations[$oid] ?? null;
            },
        );

        $t->same([$shared], $mergeBase->mergeBasesAgainst($left, [$right, $unrelated]));
        $t->same($shared, $mergeBase->mergeBase($left, $right));
        $t->same([$shared], $mergeBase->mergeBases($left, $right));
        $t->same(true, in_array($staleParent, $reads, true));
        $t->same(true, in_array($staleParent, $generationReads, true));
        $t->same(false, in_array($missingGrandparent, $reads, true));
        $t->same(false, in_array($missingGrandparent, $generationReads, true));
        $invalidGenerationFinder = new MergeBaseFinder(
            static fn (string $oid): Commit => $commits[$oid],
            commitGraphGeneration: static fn (string $oid): int => -1,
        );
        $t->throws(InvalidArgumentException::class, static fn () => $invalidGenerationFinder->mergeBase($left, $right));
    },
    'maps upstream missing commit graph generations as infinity without recursive inflation' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $missingGrandparent = $oid('0');
        $staleParent = $oid('1');
        $shared = $oid('2');
        $left = $oid('3');
        $right = $oid('4');
        $unrelated = $oid('5');
        $reads = [];
        $generationReads = [];
        $commits = [
            $staleParent => $timedCommit(1699999900, [$missingGrandparent]),
            $shared => $timedCommit(1700000000, [$staleParent]),
            $left => $timedCommit(1700000100, [$shared]),
            $right => $timedCommit(1700000200, [$shared]),
            $unrelated => $timedCommit(1700000300),
        ];
        $mergeBase = new MergeBaseFinder(
            static function (string $oid) use ($commits, $missingGrandparent, &$reads): Commit {
                $reads[] = $oid;
                if ($oid === $missingGrandparent) {
                    throw new RuntimeException('Missing commit-graph generation must not force recursive ancestor inflation');
                }
                if (!isset($commits[$oid])) {
                    throw new RuntimeException("Missing commit fixture: {$oid}");
                }

                return $commits[$oid];
            },
            commitGraphGeneration: static function (string $oid) use (&$generationReads): ?int {
                $generationReads[] = $oid;

                return null;
            },
        );

        $t->same([$shared], $mergeBase->mergeBasesAgainst($left, [$right, $unrelated]));
        $t->same($shared, $mergeBase->mergeBase($left, $right));
        $t->same(true, in_array($staleParent, $reads, true));
        $t->same(false, in_array($missingGrandparent, $reads, true));
        $t->same(true, in_array($staleParent, $generationReads, true));
        $t->same(false, in_array($missingGrandparent, $generationReads, true));
    },
    'maps upstream commit graph generation number bounds' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $release = $oid('1');
        $pluginReview = $oid('2');
        $themeReview = $oid('3');
        $commits = [
            $release => $timedCommit(1700000000),
            $pluginReview => $timedCommit(1700000100, [$release]),
            $themeReview => $timedCommit(1700000200, [$release]),
        ];
        $maxGenerations = [
            $release => 0x3fffffff,
            $pluginReview => 0x3fffffff,
            $themeReview => 0x3fffffff,
        ];
        $maxGenerationFinder = new MergeBaseFinder(
            static fn (string $oid): ?Commit => $commits[$oid] ?? null,
            commitGraphGeneration: static fn (string $oid): ?int => $maxGenerations[$oid] ?? null,
        );

        $t->same([$release], $maxGenerationFinder->mergeBases($pluginReview, $themeReview));
        $t->same($release, $maxGenerationFinder->mergeBaseAgainst($pluginReview, [$themeReview]));

        $invalidGenerationFinder = new MergeBaseFinder(
            static fn (string $oid): ?Commit => $commits[$oid] ?? null,
            commitGraphGeneration: static fn (string $oid): int => 0x40000000,
        );

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $invalidGenerationFinder->mergeBase($pluginReview, $themeReview),
        );
    },
    'maps upstream commit graph redundant pruning without inflating below result generation' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $missingGrandparent = $oid('0');
        $staleParent = $oid('1');
        $legacyBase = $oid('2');
        $securityBase = $oid('3');
        $pluginReview = $oid('4');
        $themeReview = $oid('5');
        $reads = [];
        $generationReads = [];
        $commits = [
            $staleParent => $timedCommit(1699999900, [$missingGrandparent]),
            $legacyBase => $timedCommit(1700000000, [$staleParent]),
            $securityBase => $timedCommit(1700000100),
            $pluginReview => $timedCommit(1700000200, [$legacyBase, $securityBase]),
            $themeReview => $timedCommit(1700000300, [$securityBase, $legacyBase]),
        ];
        $generations = [
            $staleParent => 1,
            $legacyBase => 2,
            $securityBase => 2,
            $pluginReview => 3,
            $themeReview => 3,
        ];
        $mergeBase = new MergeBaseFinder(
            static function (string $oid) use ($commits, $missingGrandparent, &$reads): Commit {
                $reads[] = $oid;
                if ($oid === $missingGrandparent) {
                    throw new RuntimeException('Commit-graph redundant pruning should stop below the lowest result generation');
                }
                if (!isset($commits[$oid])) {
                    throw new RuntimeException("Missing commit fixture: {$oid}");
                }

                return $commits[$oid];
            },
            commitGraphGeneration: static function (string $oid) use ($generations, &$generationReads): ?int {
                $generationReads[] = $oid;

                return $generations[$oid] ?? null;
            },
        );

        $t->same([$securityBase, $legacyBase], $mergeBase->mergeBases($pluginReview, $themeReview));
        $t->same([$securityBase, $legacyBase], $mergeBase->mergeBasesAgainst($pluginReview, [$themeReview]));
        $t->same(true, in_array($staleParent, $reads, true));
        $t->same(true, in_array($staleParent, $generationReads, true));
        $t->same(false, in_array($missingGrandparent, $reads, true));
        $t->same(false, in_array($missingGrandparent, $generationReads, true));
    },
    'maps upstream generated merge-base timestamp skew baselines' => static function (TestRunner $t) use ($oid, $finder): void {
        $timedCommit = static fn (int $offsetSeconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            'Ada <ada@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000',
            'CI <ci@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000',
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ['Ada <ada@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000'],
                'committer' => ['CI <ci@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000'],
            ],
        );

        $e = $oid('1');
        $d = $oid('2');
        $f = $oid('3');
        $c = $oid('4');
        $b = $oid('5');
        $a = $oid('6');
        $g = $oid('7');
        $h = $oid('8');
        $firstSkewGraph = [
            $e => $timedCommit(5),
            $d => $timedCommit(4, [$e]),
            $f => $timedCommit(6, [$e]),
            $c => $timedCommit(3, [$d]),
            $b => $timedCommit(2, [$c]),
            $a => $timedCommit(1, [$b]),
            $g => $timedCommit(7, [$b, $e]),
            $h => $timedCommit(8, [$a, $f]),
        ];

        $s = $oid('9');
        $c0 = $oid('a');
        $c1 = $oid('b');
        $c2 = $oid('c');
        $l0 = $oid('d');
        $l1 = $oid('e');
        $l2 = $oid('f');
        $r0 = str_repeat('10', 20);
        $r1 = str_repeat('11', 20);
        $r2 = str_repeat('12', 20);
        $pl = str_repeat('13', 20);
        $pr = str_repeat('14', 20);
        $secondSkewGraph = [
            $s => $timedCommit(0),
            $c0 => $timedCommit(-3, [$s]),
            $c1 => $timedCommit(-2, [$c0]),
            $c2 => $timedCommit(-1, [$c1]),
            $l0 => $timedCommit(1, [$s]),
            $l1 => $timedCommit(2, [$l0]),
            $l2 => $timedCommit(3, [$l1]),
            $r0 => $timedCommit(1, [$s]),
            $r1 => $timedCommit(2, [$r0]),
            $r2 => $timedCommit(3, [$r1]),
            $pl => $timedCommit(4, [$l2, $c2]),
            $pr => $timedCommit(4, [$c2, $r2]),
        ];

        foreach ([true, false] as $useCommitGraphGenerations) {
            $firstFinder = $finder($firstSkewGraph, $useCommitGraphGenerations);
            $secondFinder = $finder($secondSkewGraph, $useCommitGraphGenerations);

            $t->same([$b], $firstFinder->mergeBasesAgainst($g, [$h]));
            $t->same($b, $firstFinder->mergeBase($g, $h));
            $t->same([$e], $firstFinder->mergeBases($g, $f));
            $t->same([$c2], $secondFinder->mergeBasesAgainst($pl, [$pr]));
            $t->same($c2, $secondFinder->mergeBase($pl, $pr));
            $t->same([$c2], $secondFinder->mergeBasesMany([$pl, $pr]));
        }
    },
    'maps upstream generated merge-base permutation baseline archive' => static function (TestRunner $t) use ($finder): void {
        $oid = static fn (string $hex): string => str_repeat($hex, 20);
        $timedCommit = static fn (int $offsetSeconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            'Ada <ada@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000',
            'CI <ci@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000',
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ['Ada <ada@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000'],
                'committer' => ['CI <ci@example.test> ' . (1700000000 + $offsetSeconds) . ' +0000'],
            ],
        );

        $ids = [
            'DA' => $oid('01'),
            'DB' => $oid('02'),
            'E' => $oid('03'),
            'D' => $oid('04'),
            'F' => $oid('05'),
            'C' => $oid('06'),
            'B' => $oid('07'),
            'A' => $oid('08'),
            'G' => $oid('09'),
            'H' => $oid('0a'),
        ];
        $commits = [
            $ids['DA'] => $timedCommit(0),
            $ids['DB'] => $timedCommit(100),
            $ids['E'] => $timedCommit(5),
            $ids['D'] => $timedCommit(4, [$ids['E']]),
            $ids['F'] => $timedCommit(6, [$ids['E']]),
            $ids['C'] => $timedCommit(3, [$ids['D']]),
            $ids['B'] => $timedCommit(2, [$ids['C']]),
            $ids['A'] => $timedCommit(1, [$ids['B']]),
            $ids['G'] => $timedCommit(7, [$ids['B'], $ids['E']]),
            $ids['H'] => $timedCommit(8, [$ids['A'], $ids['F']]),
        ];
        $oidFor = static fn (string $label): string => $ids[$label]
            ?? throw new RuntimeException("Unknown upstream merge-base label: {$label}");
        $baseline = <<<'BASELINE'
DB DB => DB
DB H =>
DB G =>
DB F =>
DB E =>
DB D =>
DB C =>
DB B =>
DB A =>
DB DA =>
H DB =>
H H => H
H G => B
H F => F
H E => E
H D => D
H C => C
H B => B
H A => A
H DA =>
G DB =>
G H => B
G G => G
G F => E
G E => E
G D => D
G C => C
G B => B
G A => B
G DA =>
F DB =>
F H => F
F G => E
F F => F
F E => E
F D => E
F C => E
F B => E
F A => E
F DA =>
E DB =>
E H => E
E G => E
E F => E
E E => E
E D => E
E C => E
E B => E
E A => E
E DA =>
D DB =>
D H => D
D G => D
D F => E
D E => E
D D => D
D C => D
D B => D
D A => D
D DA =>
C DB =>
C H => C
C G => C
C F => E
C E => E
C D => D
C C => C
C B => C
C A => C
C DA =>
B DB =>
B H => B
B G => B
B F => E
B E => E
B D => D
B C => C
B B => B
B A => B
B DA =>
A DB =>
A H => A
A G => B
A F => E
A E => E
A D => D
A C => C
A B => B
A A => A
A DA =>
DA DB =>
DA H =>
DA G =>
DA F =>
DA E =>
DA D =>
DA C =>
DA B =>
DA A =>
DA DA => DA
BASELINE;

        foreach ([true, false] as $useCommitGraphGenerations) {
            $mergeBase = $finder($commits, $useCommitGraphGenerations);

            foreach (explode("\n", $baseline) as $line) {
                if (preg_match('/^([A-Z0-9-]+) ([A-Z0-9-]+) => ?(.*)$/', $line, $matches) !== 1) {
                    throw new RuntimeException("Malformed upstream merge-base baseline row: {$line}");
                }

                $expected = $matches[3] === ''
                    ? []
                    : array_map($oidFor, explode(' ', $matches[3]));

                $t->same(
                    [$expected, $expected],
                    [
                        $mergeBase->mergeBasesAgainst($oidFor($matches[1]), [$oidFor($matches[2])]),
                        $mergeBase->mergeBases($oidFor($matches[1]), $oidFor($matches[2])),
                    ],
                    "upstream make_merge_base_repos 3_permutations {$line}"
                        . ($useCommitGraphGenerations ? ' with commit graph' : ' without commit graph'),
                );
            }
        }
    },
    'maps upstream generated three-head baseline with union-side bases' => static function (TestRunner $t) use ($finder): void {
        $oid = static fn (string $hex): string => str_repeat($hex, 20);
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );

        $j = $oid('a0');
        $jb = $oid('a1');
        $jc = $oid('a2');
        $jTemp1 = $oid('a3');
        $ja = $oid('a4');
        $jaa = $oid('a5');
        $jTemp2 = $oid('a6');
        $jd = $oid('a7');
        $jdd = $oid('a8');
        $jTemp3 = $oid('a9');
        $je = $oid('ab');

        $commits = [
            $j => $timedCommit(1700000000),
            $jb => $timedCommit(1700000060, [$j]),
            $jc => $timedCommit(1700000120, [$j]),
            $jTemp1 => $timedCommit(1700000180, [$j]),
            $ja => $timedCommit(1700000240, [$jTemp1, $jb]),
            $jaa => $timedCommit(1700000300, [$ja, $jc]),
            $jTemp2 => $timedCommit(1700000360, [$j]),
            $jd => $timedCommit(1700000420, [$jTemp2, $jb]),
            $jdd => $timedCommit(1700000480, [$jd, $jc]),
            $jTemp3 => $timedCommit(1700000540, [$j]),
            $je => $timedCommit(1700000600, [$jTemp3, $jc]),
        ];

        foreach ([true, false] as $useCommitGraphGenerations) {
            $mergeBase = $finder($commits, $useCommitGraphGenerations);

            $t->same([$jc, $jb], $mergeBase->mergeBasesAgainst($jaa, [$jdd, $je]));
            $t->same($jc, $mergeBase->mergeBaseAgainst($jaa, [$jdd, $je]));
            $t->same([$jc], $mergeBase->mergeBasesMany([$jaa, $jdd, $je]));
            $t->same([$jc, $jb], $mergeBase->mergeBasesAgainst($jaa, [$jdd, $je]));
        }
    },
    'maps upstream graph walk with sha256 commit ids' => static function (TestRunner $t) use ($commit, $finder): void {
        $sha256 = static fn (string $hex): string => str_repeat($hex, 64);
        $root = $sha256('1');
        $release = $sha256('2');
        $pluginReview = $sha256('a');
        $themeReview = $sha256('b');
        $deployMerge = $sha256('c');
        $archiveRoot = $sha256('d');
        $archiveReview = $sha256('e');

        $mergeBase = $finder([
            $root => $commit(),
            $release => $commit([$root]),
            $pluginReview => $commit([$release]),
            $themeReview => $commit([$release]),
            $deployMerge => $commit([$pluginReview, $themeReview]),
            $archiveRoot => $commit(),
            $archiveReview => $commit([$archiveRoot]),
        ]);

        $t->same([$release], $mergeBase->mergeBases($pluginReview, $themeReview));
        $t->same($themeReview, $mergeBase->mergeBase($deployMerge, $themeReview));
        $t->same([$release], $mergeBase->mergeBasesAgainst($pluginReview, [$themeReview, $archiveReview]));
        $t->same($pluginReview, $mergeBase->mergeBaseAgainst($deployMerge, [$pluginReview, $archiveReview]));
        $t->same([$release], $mergeBase->mergeBasesMany([$deployMerge, $pluginReview, $themeReview]));
        $t->same($deployMerge, $mergeBase->mergeBaseMany([$deployMerge]));
        $t->same([], $mergeBase->mergeBasesMany([$pluginReview, $themeReview, $archiveReview]));

        $sha1Review = str_repeat('f', 40);
        $t->throws(InvalidArgumentException::class, static fn () => $mergeBase->mergeBases($pluginReview, $sha1Review));
        $mixedParentFinder = $finder([
            $pluginReview => $commit([$sha1Review]),
            $sha1Review => $commit(),
        ]);
        $t->throws(InvalidArgumentException::class, static fn () => $mixedParentFinder->mergeBase($pluginReview, $release));
    },
    'maps upstream octopus merge-base for three sequential heads' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $first = $oid('1');
        $second = $oid('2');
        $third = $oid('3');

        $mergeBase = $finder([
            $first => $commit(),
            $second => $commit([$first]),
            $third => $commit([$second]),
        ]);

        $t->same([$first], $mergeBase->mergeBasesMany([$third, $second, $first]));
        $t->same($first, $mergeBase->mergeBaseMany([$second, $third, $first]));
    },
    'maps upstream octopus merge-base for three parallel heads' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $base = $oid('1');
        $left = $oid('2');
        $middle = $oid('3');
        $right = $oid('4');

        $mergeBase = $finder([
            $base => $commit(),
            $left => $commit([$base]),
            $middle => $commit([$base]),
            $right => $commit([$base]),
        ]);

        $t->same([$base], $mergeBase->mergeBasesMany([$left, $middle, $right]));
        $t->same($base, $mergeBase->mergeBaseMany([$right, $middle, $left]));
    },
    'maps upstream octopus merge-base for forked and criss-cross heads' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $root = $oid('1');
        $leftBase = $oid('2');
        $rightBase = $oid('3');
        $leftMerge = $oid('4');
        $rightMerge = $oid('5');

        $mergeBase = $finder([
            $root => $commit(),
            $leftBase => $commit([$root]),
            $rightBase => $commit([$root]),
            $leftMerge => $commit([$leftBase, $rightBase]),
            $rightMerge => $commit([$rightBase, $leftBase]),
        ]);

        $t->same([$leftBase, $rightBase], $mergeBase->mergeBasesMany([$leftMerge, $rightMerge]));
        $t->same([$rightBase], $mergeBase->mergeBasesMany([$leftMerge, $rightMerge, $rightBase]));
        $t->same([$leftMerge], $mergeBase->mergeBasesMany([$leftMerge]));
        $t->throws(InvalidArgumentException::class, static fn () => $mergeBase->mergeBasesMany([]));
    },
    'maps upstream octopus merge-base sequential ordering special case' => static function (TestRunner $t) use ($finder): void {
        $oid = static fn (string $hex): string => str_repeat($hex, 20);
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );

        $release = $oid('10');
        $legacyBase = $oid('20');
        $securityBase = $oid('30');
        $pluginHotfix = $oid('40');
        $themeHotfix = $oid('50');
        $legacyOnly = $oid('60');

        $mergeBase = $finder([
            $release => $timedCommit(1700000000),
            $legacyBase => $timedCommit(1700000010, [$release]),
            $securityBase => $timedCommit(1700000020, [$release]),
            $pluginHotfix => $timedCommit(1700000030, [$legacyBase, $securityBase]),
            $themeHotfix => $timedCommit(1700000040, [$securityBase, $legacyBase]),
            $legacyOnly => $timedCommit(1700000050, [$legacyBase]),
        ]);

        $t->same([$legacyBase], $mergeBase->mergeBasesMany([$pluginHotfix, $themeHotfix, $legacyOnly]));
        $t->same($release, $mergeBase->mergeBaseOctopus([$pluginHotfix, $themeHotfix, $legacyOnly]));
        $t->same($legacyBase, $mergeBase->mergeBaseOctopus([$pluginHotfix, $legacyOnly, $themeHotfix]));
        $t->same($pluginHotfix, $mergeBase->mergeBaseOctopus([$pluginHotfix]));
        $t->throws(InvalidArgumentException::class, static fn () => $mergeBase->mergeBaseOctopus([]));
        $t->throws(InvalidArgumentException::class, static fn () => $mergeBase->mergeBaseOctopus([$pluginHotfix, 123]));
    },
    'returns null when histories are unrelated' => static function (TestRunner $t) use ($oid, $commit, $finder): void {
        $left = $oid('a');
        $right = $oid('b');
        $mergeBase = $finder([
            $left => $commit(),
            $right => $commit(),
        ]);

        $t->same([], $mergeBase->mergeBases($left, $right));
        $t->same(null, $mergeBase->mergeBase($left, $right));
    },
    'maps upstream shallow graph walk by skipping missing active commits' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $missingFirst = $oid('0');
        $missingParent = $oid('1');
        $release = $oid('2');
        $pluginReview = $oid('3');
        $themeReview = $oid('4');
        $archivedReview = $oid('5');
        $orphanReview = $oid('6');
        $reads = [];
        $commits = [
            $release => $timedCommit(1700000000),
            $pluginReview => $timedCommit(1700000100, [$release]),
            $themeReview => $timedCommit(1700000200, [$release]),
            $archivedReview => $timedCommit(1700000300, [$missingParent]),
            $orphanReview => $timedCommit(1700000400),
        ];
        $mergeBase = new MergeBaseFinder(
            static function (string $oid) use ($commits, &$reads): ?Commit {
                $reads[] = $oid;

                return $commits[$oid] ?? null;
            },
            useCommitGraphGenerations: false,
        );

        $t->same([$release], $mergeBase->mergeBasesAgainst($pluginReview, [$themeReview, $archivedReview]));
        $t->same($release, $mergeBase->mergeBase($pluginReview, $themeReview));
        $t->same([], $mergeBase->mergeBases($missingFirst, $themeReview));
        $t->same(null, $mergeBase->mergeBase($pluginReview, $missingFirst));
        $t->same([], $mergeBase->mergeBasesAgainst($pluginReview, [$archivedReview]));
        $t->same([], $mergeBase->mergeBases($archivedReview, $orphanReview));
        $t->same(true, in_array($missingParent, $reads, true));
        $t->same(true, in_array($missingFirst, $reads, true));
    },
    'maps upstream shallow parent skip in stable multi-head ancestor intersection' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $missingGrandparent = $oid('0');
        $staleParent = $oid('1');
        $release = $oid('2');
        $pluginReview = $oid('3');
        $themeReview = $oid('4');
        $contentReview = $oid('5');
        $reads = [];
        $commits = [
            $staleParent => $timedCommit(1699999900, [$missingGrandparent]),
            $release => $timedCommit(1700000000, [$staleParent]),
            $pluginReview => $timedCommit(1700000100, [$release]),
            $themeReview => $timedCommit(1700000200, [$release]),
            $contentReview => $timedCommit(1700000300, [$release]),
        ];
        $mergeBase = new MergeBaseFinder(
            static function (string $oid) use ($commits, &$reads): ?Commit {
                $reads[] = $oid;

                return $commits[$oid] ?? null;
            },
            useCommitGraphGenerations: false,
        );

        $t->same([$release], $mergeBase->mergeBasesMany([$pluginReview, $themeReview]));
        $t->same([$release], $mergeBase->mergeBasesMany([$pluginReview, $themeReview, $contentReview]));
        $t->same(true, in_array($missingGrandparent, $reads, true));
        $t->same([$release], $mergeBase->mergeBasesAgainst($pluginReview, [$themeReview, $contentReview]));
    },
    'maps upstream graph reuse by not pinning missing commits after hydration' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $release = $oid('1');
        $pluginReview = $oid('2');
        $themeReview = $oid('3');
        $reads = [];
        $commits = [
            $pluginReview => $timedCommit(1700000100, [$release]),
            $themeReview => $timedCommit(1700000200, [$release]),
        ];
        $mergeBase = new MergeBaseFinder(static function (string $oid) use (&$commits, &$reads): ?Commit {
            $reads[] = $oid;

            return $commits[$oid] ?? null;
        }, useCommitGraphGenerations: false);

        $t->same([], $mergeBase->mergeBases($pluginReview, $themeReview));
        $t->same(true, in_array($release, $reads, true));

        $commits[$release] = $timedCommit(1700000000);
        $reads = [];

        $t->same([$release], $mergeBase->mergeBases($pluginReview, $themeReview));
        $t->same($release, $mergeBase->mergeBase($pluginReview, $themeReview));
        $t->same(true, in_array($release, $reads, true));
    },
    'maps upstream stable ancestor reuse after hydrated shallow parent' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $release = $oid('1');
        $pluginReview = $oid('2');
        $themeReview = $oid('3');
        $contentReview = $oid('4');
        $reads = [];
        $commits = [
            $pluginReview => $timedCommit(1700000100, [$release]),
            $themeReview => $timedCommit(1700000200, [$release]),
            $contentReview => $timedCommit(1700000300, [$release]),
        ];
        $mergeBase = new MergeBaseFinder(static function (string $oid) use (&$commits, &$reads): ?Commit {
            $reads[] = $oid;

            return $commits[$oid] ?? null;
        }, useCommitGraphGenerations: false);

        $t->same([], $mergeBase->mergeBasesMany([$pluginReview, $themeReview, $contentReview]));
        $t->same(true, in_array($release, $reads, true));

        $commits[$release] = $timedCommit(1700000000);
        $reads = [];

        $t->same([$release], $mergeBase->mergeBasesMany([$pluginReview, $themeReview, $contentReview]));
        $t->same(true, in_array($release, $reads, true));
    },
    'maps upstream graph generation reuse after hydrated missing parent' => static function (TestRunner $t) use ($oid): void {
        $timedCommit = static fn (int $seconds, array $parents = []): Commit => new Commit(
            str_repeat('f', 40),
            $parents,
            "Ada <ada@example.test> {$seconds} +0000",
            "CI <ci@example.test> {$seconds} +0000",
            "commit\n",
            [
                'tree' => [str_repeat('f', 40)],
                'parent' => $parents,
                'author' => ["Ada <ada@example.test> {$seconds} +0000"],
                'committer' => ["CI <ci@example.test> {$seconds} +0000"],
            ],
        );
        $root = $oid('1');
        $intermediate = $oid('2');
        $legacyDeepBase = $oid('3');
        $securityShallowBase = $oid('4');
        $pluginReview = $oid('5');
        $themeReview = $oid('6');
        $reads = [];
        $commits = [
            $root => $timedCommit(1700000000),
            $legacyDeepBase => $timedCommit(1700000020, [$intermediate]),
            $securityShallowBase => $timedCommit(1700000100, [$root]),
            $pluginReview => $timedCommit(1700000200, [$legacyDeepBase, $securityShallowBase]),
            $themeReview => $timedCommit(1700000300, [$securityShallowBase, $legacyDeepBase]),
        ];
        $mergeBase = new MergeBaseFinder(static function (string $oid) use (&$commits, &$reads): ?Commit {
            $reads[] = $oid;

            return $commits[$oid] ?? null;
        });

        $t->same([$securityShallowBase, $legacyDeepBase], $mergeBase->mergeBases($pluginReview, $themeReview));
        $t->same(true, in_array($intermediate, $reads, true));

        $commits[$intermediate] = $timedCommit(1700000010, [$root]);
        $reads = [];

        $t->same([$legacyDeepBase, $securityShallowBase], $mergeBase->mergeBases($pluginReview, $themeReview));
        $t->same($legacyDeepBase, $mergeBase->mergeBaseAgainst($pluginReview, [$themeReview]));
        $t->same(true, in_array($intermediate, $reads, true));
    },
    'wordpress fixture finds shared release baseline for multiple review branches' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-merge-base.php';
        $example = require dirname(__DIR__) . '/examples/wordpress-merge-base.php';
        $finder = new MergeBaseFinder(static function (string $oid) use ($fixture): ?Commit {
            return $fixture['commits'][$oid] ?? null;
        });
        $timeOnlyFinder = new MergeBaseFinder(static function (string $oid) use ($fixture): ?Commit {
            return $fixture['commits'][$oid] ?? null;
        }, useCommitGraphGenerations: false);
        $commitGraphFinder = new MergeBaseFinder(
            static function (string $oid) use ($fixture): ?Commit {
                return $fixture['commits'][$oid] ?? null;
            },
            commitGraphGeneration: static fn (string $oid): ?int => $fixture['shallowCommitGraphGenerations'][$oid] ?? null,
        );
        $redundantPruneFinder = new MergeBaseFinder(
            static function (string $oid) use ($fixture): ?Commit {
                return $fixture['commits'][$oid] ?? null;
            },
            commitGraphGeneration: static fn (string $oid): ?int => $fixture['redundantPruneCommitGraphGenerations'][$oid] ?? null,
        );
        $missingGenerationFinder = new MergeBaseFinder(
            static function (string $oid) use ($fixture): ?Commit {
                return $fixture['commits'][$oid] ?? null;
            },
            commitGraphGeneration: static fn (string $oid): ?int => null,
        );

        $t->same($fixture['releaseBaseline'], $finder->mergeBaseMany($fixture['heads']));
        $t->same([$fixture['releaseBaseline']], $finder->mergeBasesMany($fixture['deploymentHeads']));
        $t->same([$fixture['releaseBaseline']], $finder->mergeBasesAgainst($fixture['pluginReview'], $fixture['graphWalkOthers']));
        $t->same([$fixture['pluginReview']], $example['disjointShortcutBases']);
        $t->same(true, $example['disjointShortcutAvoidsGraphReads']);
        $t->same(
            $fixture['securityBaseline'] . "\n" . $fixture['legacyBaseline'] . "\n",
            $example['hotfixCommandOutput'],
        );
        $t->same(true, $example['hotfixCommandPrintsAllBases']);
        $t->contains('No base found for ', $example['archiveCommandError']);
        $t->same([], $finder->mergeBasesMany($fixture['graphWalkHeads']));
        $t->same($fixture['releaseBaseline'], $example['reviewBase']);
        $t->same([$fixture['releaseBaseline']], $example['reviewBases']);
        $t->same($fixture['releaseBaseline'], $example['graphWalkBase']);
        $t->same(true, $example['graphWalkKeepsReleaseBaseline']);
        $t->same(true, $example['octopusRejectsArchiveBranch']);
        $t->same([$fixture['securityBaseline'], $fixture['legacyBaseline']], $finder->mergeBases($fixture['pluginHotfixReview'], $fixture['themeHotfixReview']));
        $t->same($fixture['securityBaseline'], $example['hotfixBase']);
        $t->same(true, $example['hotfixBasePrefersNewerSecurityBaseline']);
        $t->same(true, $example['reviewBaseIsReleaseBaseline']);
        $t->same(true, $example['deploymentBaseIsReleaseBaseline']);
        $t->same($fixture['sha256ReleaseBaseline'], $finder->mergeBase($fixture['sha256PluginReview'], $fixture['sha256ThemeReview']));
        $t->same($fixture['sha256ReleaseBaseline'], $finder->mergeBaseAgainst($fixture['sha256PluginReview'], $fixture['sha256GraphWalkOthers']));
        $t->same($fixture['sha256ReleaseBaseline'], $example['sha256ReviewBase']);
        $t->same($fixture['sha256ReleaseBaseline'], $example['sha256GraphWalkBase']);
        $t->same($fixture['sha256ReleaseBaseline'], $example['sha256DeployBase']);
        $t->same(true, $example['sha256ReviewBaseIsReleaseBaseline']);
        $t->same(true, $example['sha256GraphWalkKeepsReleaseBaseline']);
        $t->same(true, $example['sha256DeployBaseIsReleaseBaseline']);
        $t->same($fixture['legacyDeepBaseline'], $finder->mergeBase($fixture['pluginCompatibilityReview'], $fixture['themeCompatibilityReview']));
        $t->same($fixture['securityShallowBaseline'], $timeOnlyFinder->mergeBase($fixture['pluginCompatibilityReview'], $fixture['themeCompatibilityReview']));
        $t->same($fixture['legacyDeepBaseline'], $example['compatibilityCommitGraphBase']);
        $t->same($fixture['securityShallowBaseline'], $example['compatibilityNoCommitGraphBase']);
        $t->same(true, $example['commitGraphBasePrefersDeeperLegacyBaseline']);
        $t->same(true, $example['noCommitGraphBasePrefersNewerSecurityBaseline']);
        $t->same([$fixture['legacyBaseline']], $finder->mergeBasesMany($fixture['octopusSpecialHeads']));
        $t->same($fixture['releaseBaseline'], $finder->mergeBaseOctopus($fixture['octopusSpecialHeads']));
        $t->same($fixture['legacyBaseline'], $finder->mergeBaseOctopus($fixture['octopusReorderedHeads']));
        $t->same($fixture['releaseBaseline'], $example['sequentialOctopusBase']);
        $t->same($fixture['legacyBaseline'], $example['reorderedOctopusBase']);
        $t->same([$fixture['legacyBaseline']], $example['stableOctopusIntersectionBases']);
        $t->same(true, $example['sequentialOctopusFallsBackToReleaseBaseline']);
        $t->same(true, $example['reorderedOctopusKeepsLegacyBaseline']);
        $t->same(true, $example['stableIntersectionKeepsLegacyBaseline']);
        $t->same($fixture['shallowReleaseBaseline'], $timeOnlyFinder->mergeBaseAgainst($fixture['shallowPluginReview'], $fixture['shallowGraphWalkOthers']));
        $t->same($fixture['shallowReleaseBaseline'], $commitGraphFinder->mergeBaseAgainst($fixture['shallowPluginReview'], $fixture['shallowGraphWalkOthers']));
        $t->same($fixture['shallowReleaseBaseline'], $example['shallowGraphWalkBase']);
        $t->same($fixture['shallowReleaseBaseline'], $example['shallowCommitGraphBase']);
        $t->same($fixture['shallowReleaseBaseline'], $example['shallowPairwiseBase']);
        $t->same([$fixture['shallowReleaseBaseline']], $example['shallowStableIntersectionBases']);
        $t->same($fixture['shallowReleaseBaseline'], $timeOnlyFinder->mergeBaseAgainst($fixture['shallowPluginReview'], $fixture['shallowMissingArchiveGraphWalkOthers']));
        $t->same($fixture['shallowReleaseBaseline'], $example['shallowMissingArchiveBase']);
        $t->same(true, $example['shallowGraphWalkStopsAtReleaseBaseline']);
        $t->same(true, $example['shallowCommitGraphUsesMetadata']);
        $t->same(true, $example['shallowPairwiseStopsAtReleaseBaseline']);
        $t->same(true, $example['shallowStableIntersectionSkipsMissingParent']);
        $t->same(true, $example['shallowMissingArchiveParentIsSkipped']);
        $t->same($fixture['timestampSkewExpectedBase'], $finder->mergeBase($fixture['timestampSkewLeftReview'], $fixture['timestampSkewRightReview']));
        $t->same($fixture['timestampSkewExpectedBase'], $timeOnlyFinder->mergeBase($fixture['timestampSkewLeftReview'], $fixture['timestampSkewRightReview']));
        $t->same([$fixture['timestampSkewExpectedBase']], $finder->mergeBasesAgainst($fixture['timestampSkewLeftReview'], [$fixture['timestampSkewRightReview']]));
        $t->same($fixture['timestampSkewExpectedBase'], $example['timestampSkewBase']);
        $t->same($fixture['timestampSkewExpectedBase'], $example['timestampSkewReverseBase']);
        $t->same($fixture['timestampSkewExpectedBase'], $example['timestampSkewNoCommitGraphBase']);
        $t->same(true, $example['timestampSkewPrunesNewerRoot']);
        $t->same(true, $example['timestampSkewPermutationOrderIsStable']);
        $t->same([$fixture['junctionThemeBase'], $fixture['junctionContentBase']], $finder->mergeBasesAgainst($fixture['junctionPluginReview'], $fixture['junctionOtherReviews']));
        $t->same([$fixture['junctionThemeBase']], $finder->mergeBasesMany($fixture['junctionHeads']));
        $t->same([$fixture['junctionThemeBase'], $fixture['junctionContentBase']], $example['junctionGraphWalkBases']);
        $t->same([$fixture['junctionThemeBase']], $example['junctionStableIntersectionBases']);
        $t->same(true, $example['junctionGraphWalkKeepsUnionSideContentBase']);
        $t->same(true, $example['junctionStableIntersectionPrunesContentBase']);
        $t->same([$fixture['redundantSecurityBase'], $fixture['redundantLegacyBase']], $redundantPruneFinder->mergeBases($fixture['redundantPluginReview'], $fixture['redundantThemeReview']));
        $t->same([$fixture['redundantSecurityBase'], $fixture['redundantLegacyBase']], $example['redundantPruneBases']);
        $t->same(true, $example['redundantPruneKeepsIndependentBases']);
        $t->same($fixture['missingGenerationReleaseBaseline'], $missingGenerationFinder->mergeBaseAgainst($fixture['missingGenerationPluginReview'], $fixture['missingGenerationGraphWalkOthers']));
        $t->same($fixture['missingGenerationReleaseBaseline'], $missingGenerationFinder->mergeBase($fixture['missingGenerationPluginReview'], $fixture['missingGenerationThemeReview']));
        $t->same($fixture['missingGenerationReleaseBaseline'], $example['missingGenerationGraphWalkBase']);
        $t->same($fixture['missingGenerationReleaseBaseline'], $example['missingGenerationPairwiseBase']);
        $t->same(true, $example['missingGenerationProviderKeepsReleaseBaseline']);
        $maxGenerationFinder = new MergeBaseFinder(
            static function (string $oid) use ($fixture): ?Commit {
                return $fixture['commits'][$oid] ?? null;
            },
            commitGraphGeneration: static fn (string $oid): ?int => $fixture['maxCommitGraphGenerations'][$oid] ?? null,
        );
        $invalidGenerationFinder = new MergeBaseFinder(
            static function (string $oid) use ($fixture): ?Commit {
                return $fixture['commits'][$oid] ?? null;
            },
            commitGraphGeneration: static fn (string $oid): ?int => $fixture['invalidCommitGraphGenerations'][$oid] ?? null,
        );
        $t->same($fixture['releaseBaseline'], $maxGenerationFinder->mergeBase($fixture['pluginReview'], $fixture['themeReview']));
        $t->same($fixture['releaseBaseline'], $example['maxGenerationBase']);
        $t->same(true, $example['maxGenerationProviderKeepsReleaseBaseline']);
        $t->same(true, $example['invalidCommitGraphGenerationRejected']);
        $t->throws(InvalidArgumentException::class, static fn () => $invalidGenerationFinder->mergeBase($fixture['pluginReview'], $fixture['themeReview']));
        $hydratedPromisorCommits = $fixture['commits'];
        $hydratedPromisorReleaseCommit = $hydratedPromisorCommits[$fixture['hydratedPromisorReleaseBaseline']];
        unset($hydratedPromisorCommits[$fixture['hydratedPromisorReleaseBaseline']]);
        $hydratedPromisorFinder = new MergeBaseFinder(
            static function (string $oid) use (&$hydratedPromisorCommits): ?Commit {
                return $hydratedPromisorCommits[$oid] ?? null;
            },
            useCommitGraphGenerations: false,
        );
        $t->same([], $hydratedPromisorFinder->mergeBases($fixture['hydratedPromisorPluginReview'], $fixture['hydratedPromisorThemeReview']));
        $hydratedPromisorCommits[$fixture['hydratedPromisorReleaseBaseline']] = $hydratedPromisorReleaseCommit;
        $t->same([$fixture['hydratedPromisorReleaseBaseline']], $hydratedPromisorFinder->mergeBases($fixture['hydratedPromisorPluginReview'], $fixture['hydratedPromisorThemeReview']));
        $t->same([], $example['hydratedPromisorBeforeBases']);
        $t->same([$fixture['hydratedPromisorReleaseBaseline']], $example['hydratedPromisorAfterBases']);
        $t->same(true, $example['hydratedPromisorReusesFinderAfterMissingAncestor']);
        $t->same([], $example['stableHydrationBeforeBases']);
        $t->same([$fixture['hydratedPromisorReleaseBaseline']], $example['stableHydrationAfterBases']);
        $t->same(true, $example['stableHydrationReusesFinderAfterMissingAncestor']);
        $generationHydrationCommits = $fixture['commits'];
        $generationHydrationIntermediateCommit = $generationHydrationCommits[$fixture['generationHydrationIntermediate']];
        unset($generationHydrationCommits[$fixture['generationHydrationIntermediate']]);
        $generationHydrationFinder = new MergeBaseFinder(static function (string $oid) use (&$generationHydrationCommits): ?Commit {
            return $generationHydrationCommits[$oid] ?? null;
        });
        $t->same(
            [$fixture['generationHydrationSecurityBase'], $fixture['generationHydrationLegacyBase']],
            $generationHydrationFinder->mergeBases($fixture['generationHydrationPluginReview'], $fixture['generationHydrationThemeReview']),
        );
        $generationHydrationCommits[$fixture['generationHydrationIntermediate']] = $generationHydrationIntermediateCommit;
        $t->same(
            [$fixture['generationHydrationLegacyBase'], $fixture['generationHydrationSecurityBase']],
            $generationHydrationFinder->mergeBases($fixture['generationHydrationPluginReview'], $fixture['generationHydrationThemeReview']),
        );
        $t->same([$fixture['generationHydrationSecurityBase'], $fixture['generationHydrationLegacyBase']], $example['generationHydrationBeforeBases']);
        $t->same([$fixture['generationHydrationLegacyBase'], $fixture['generationHydrationSecurityBase']], $example['generationHydrationAfterBases']);
        $t->same(true, $example['generationHydrationRecomputesIncompleteGraph']);
        $t->same(2, count($example['objectDatabaseShallowHeads']));
        $t->same([], $example['objectDatabaseShallowBeforeBases']);
        $t->same([$example['objectDatabaseReleaseBaseline']], $example['objectDatabaseShallowAfterBases']);
        $t->same(true, $example['objectDatabaseFinderReusesHydratedParent']);
        $t->same(2, count($example['sha256ObjectDatabaseHeads']));
        $t->same(64, strlen($example['sha256ObjectDatabaseReleaseBaseline']));
        $t->same([$example['sha256ObjectDatabaseReleaseBaseline']], $example['sha256ObjectDatabaseBases']);
        $t->same($example['sha256ObjectDatabaseReleaseBaseline'], $example['sha256ObjectDatabaseGraphWalkBase']);
        $t->same(true, $example['sha256ObjectDatabaseBaseIsReleaseBaseline']);
    },
    'object database reader requires commit objects' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-merge-base-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir);
        $blobOid = $store->write(new GitObject('blob', 'not a commit'));
        $commitBody = "tree " . str_repeat('f', 40) . "\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "committer CI <ci@example.test> 1700000000 +0000\n"
            . "\n"
            . "fixture\n";
        $commitOid = $store->write(new GitObject('commit', $commitBody));

        $mergeBase = MergeBaseFinder::fromObjectDatabase(new ObjectDatabase($gitDir));
        $t->throws(InvalidArgumentException::class, static fn () => $mergeBase->mergeBase($blobOid, $commitOid));
    },
    'object database reader skips missing shallow parents and reuses hydrated parent' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-merge-base-shallow-db-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir);
        $body = static function (array $parents, int $seconds, string $message): string {
            $lines = ["tree " . str_repeat('f', 40)];
            foreach ($parents as $parent) {
                $lines[] = "parent {$parent}";
            }
            $lines[] = "author Release Bot <release@example.test> {$seconds} +0000";
            $lines[] = "committer Deploy Bot <deploy@example.test> {$seconds} +0000";
            $lines[] = '';
            $lines[] = $message;

            return implode("\n", $lines) . "\n";
        };
        $releaseObject = new GitObject('commit', $body([], 1700000000, 'release baseline'));
        $releaseOid = $releaseObject->oid();
        $pluginReviewOid = $store->write(new GitObject('commit', $body([$releaseOid], 1700000100, 'plugin review')));
        $themeReviewOid = $store->write(new GitObject('commit', $body([$releaseOid], 1700000200, 'theme review')));
        $mergeBase = MergeBaseFinder::fromObjectDatabase(new ObjectDatabase($gitDir));

        $t->same([], $mergeBase->mergeBases($pluginReviewOid, $themeReviewOid));
        $t->same(null, $mergeBase->mergeBase($pluginReviewOid, $themeReviewOid));

        $store->write($releaseObject);

        $t->same([$releaseOid], $mergeBase->mergeBases($pluginReviewOid, $themeReviewOid));
        $t->same($releaseOid, $mergeBase->mergeBaseAgainst($pluginReviewOid, [$themeReviewOid]));
    },
    'object database reader honors sha256 commit object format during graph walk' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-merge-base-sha256-db-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir, false, 'sha256');
        $tree = str_repeat('f', 64);
        $body = static function (array $parents, int $seconds, string $message) use ($tree): string {
            $lines = ["tree {$tree}"];
            foreach ($parents as $parent) {
                $lines[] = "parent {$parent}";
            }
            $lines[] = "author Release Bot <release@example.test> {$seconds} +0000";
            $lines[] = "committer Deploy Bot <deploy@example.test> {$seconds} +0000";
            $lines[] = '';
            $lines[] = $message;

            return implode("\n", $lines) . "\n";
        };
        $releaseOid = $store->write(new GitObject('commit', $body([], 1700000000, 'sha256 release baseline')));
        $pluginReviewOid = $store->write(new GitObject('commit', $body([$releaseOid], 1700000100, 'sha256 plugin review')));
        $themeReviewOid = $store->write(new GitObject('commit', $body([$releaseOid], 1700000200, 'sha256 theme review')));
        $archiveOid = $store->write(new GitObject('commit', $body([], 1700000300, 'sha256 archived branch')));
        $mergeBase = MergeBaseFinder::fromObjectDatabase(new ObjectDatabase($gitDir, objectHash: 'sha256'));

        $t->same(64, strlen($releaseOid));
        $t->same([$releaseOid], $mergeBase->mergeBases($pluginReviewOid, $themeReviewOid));
        $t->same($releaseOid, $mergeBase->mergeBaseAgainst($pluginReviewOid, [$themeReviewOid, $archiveOid]));
        $t->same([$releaseOid], $mergeBase->mergeBasesMany([$pluginReviewOid, $themeReviewOid]));
        $t->same([], $mergeBase->mergeBasesMany([$pluginReviewOid, $themeReviewOid, $archiveOid]));
        $t->throws(InvalidArgumentException::class, static fn () => MergeBaseFinder::fromObjectDatabase(new ObjectDatabase($gitDir))->mergeBase($pluginReviewOid, $themeReviewOid));
    },
];
