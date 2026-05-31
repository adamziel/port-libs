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
$finder = static function (array $commits): MergeBaseFinder {
    return new MergeBaseFinder(static function (string $oid) use ($commits): Commit {
        if (!isset($commits[$oid])) {
            throw new RuntimeException("Missing commit fixture: {$oid}");
        }

        return $commits[$oid];
    });
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
