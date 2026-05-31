<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
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
        $t->same([], $reads);
        $t->throws(InvalidArgumentException::class, static fn () => $mergeBase->mergeBasesAgainst($first, [123]));
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
    'wordpress fixture finds shared release baseline for multiple review branches' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-merge-base.php';
        $example = require dirname(__DIR__) . '/examples/wordpress-merge-base.php';
        $finder = new MergeBaseFinder(static function (string $oid) use ($fixture): Commit {
            if (!isset($fixture['commits'][$oid])) {
                throw new RuntimeException("Missing commit fixture: {$oid}");
            }

            return $fixture['commits'][$oid];
        });
        $timeOnlyFinder = new MergeBaseFinder(static function (string $oid) use ($fixture): Commit {
            if (!isset($fixture['commits'][$oid])) {
                throw new RuntimeException("Missing commit fixture: {$oid}");
            }

            return $fixture['commits'][$oid];
        }, useCommitGraphGenerations: false);

        $t->same($fixture['releaseBaseline'], $finder->mergeBaseMany($fixture['heads']));
        $t->same([$fixture['releaseBaseline']], $finder->mergeBasesMany($fixture['deploymentHeads']));
        $t->same([$fixture['releaseBaseline']], $finder->mergeBasesAgainst($fixture['pluginReview'], $fixture['graphWalkOthers']));
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
        $t->same($fixture['shallowReleaseBaseline'], $example['shallowGraphWalkBase']);
        $t->same($fixture['shallowReleaseBaseline'], $example['shallowPairwiseBase']);
        $t->same(true, $example['shallowGraphWalkStopsAtReleaseBaseline']);
        $t->same(true, $example['shallowPairwiseStopsAtReleaseBaseline']);
        $t->same($fixture['timestampSkewExpectedBase'], $finder->mergeBase($fixture['timestampSkewLeftReview'], $fixture['timestampSkewRightReview']));
        $t->same($fixture['timestampSkewExpectedBase'], $timeOnlyFinder->mergeBase($fixture['timestampSkewLeftReview'], $fixture['timestampSkewRightReview']));
        $t->same([$fixture['timestampSkewExpectedBase']], $finder->mergeBasesAgainst($fixture['timestampSkewLeftReview'], [$fixture['timestampSkewRightReview']]));
        $t->same($fixture['timestampSkewExpectedBase'], $example['timestampSkewBase']);
        $t->same($fixture['timestampSkewExpectedBase'], $example['timestampSkewNoCommitGraphBase']);
        $t->same(true, $example['timestampSkewPrunesNewerRoot']);
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
];
