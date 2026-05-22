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
