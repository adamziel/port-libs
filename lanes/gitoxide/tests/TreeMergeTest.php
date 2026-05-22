<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreeMerge;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$entry = static fn (string $filename, string $oid, string $mode = '100644'): TreeEntry => new TreeEntry($mode, $filename, $oid);
$names = static fn (Tree $tree): array => array_map(static fn (TreeEntry $entry): string => $entry->filename, $tree->entries);

return [
    'merges independent flat WordPress tree changes' => static function (TestRunner $t) use ($oid, $entry, $names): void {
        $base = new Tree([
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('3'), '40000'),
        ]);
        $ours = new Tree([
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('4'), '40000'),
        ]);
        $theirs = new Tree([
            $entry('.wp-env.json', $oid('5')),
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('3'), '40000'),
        ]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->true($result->isClean());
        $t->same(['.wp-env.json', 'index.php', 'wp-config.php', 'wp-content'], $names($result->tree));
        $t->same($oid('4'), $result->tree->entryNamed('wp-content', true)?->oid);
        $t->same($oid('5'), $result->tree->entryNamed('.wp-env.json', false)?->oid);
    },
    'reports modify modify conflicts and keeps base entry in result tree' => static function (TestRunner $t) use ($oid, $entry): void {
        $base = new Tree([$entry('theme.json', $oid('1'))]);
        $ours = new Tree([$entry('theme.json', $oid('2'))]);
        $theirs = new Tree([$entry('theme.json', $oid('3'))]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->same(false, $result->isClean());
        $t->same(1, count($result->conflicts));
        $t->same('modify-modify', $result->conflicts[0]->reason);
        $t->same($oid('1'), $result->tree->entryNamed('theme.json')?->oid);
    },
    'handles delete delete as a clean removal' => static function (TestRunner $t) use ($oid, $entry): void {
        $base = new Tree([$entry('wp-content/plugins/old.php', $oid('1'))]);
        $ours = new Tree([]);
        $theirs = new Tree([]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->true($result->isClean());
        $t->same([], $result->tree->entries);
    },
    'reports delete modify conflicts with staged sides' => static function (TestRunner $t) use ($oid, $entry): void {
        $base = new Tree([$entry('wp-content/plugins/acme.php', $oid('1'))]);
        $ours = new Tree([]);
        $theirs = new Tree([$entry('wp-content/plugins/acme.php', $oid('2'))]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->same('delete-modify', $result->conflicts[0]->reason);
        $t->same($oid('1'), $result->conflicts[0]->base?->oid);
        $t->same(null, $result->conflicts[0]->ours);
        $t->same($oid('2'), $result->conflicts[0]->theirs?->oid);
        $t->same($oid('1'), $result->tree->entryNamed('wp-content/plugins/acme.php')?->oid);
    },
    'resolves identical add add entries but reports divergent additions' => static function (TestRunner $t) use ($oid, $entry): void {
        $same = TreeMerge::mergeFlat(
            new Tree([]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('1'))]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('1'))]),
        );
        $different = TreeMerge::mergeFlat(
            new Tree([]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('1'))]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('2'))]),
        );

        $t->true($same->isClean());
        $t->same($oid('1'), $same->tree->entryNamed('wp-content/mu-plugins/bootstrap.php')?->oid);
        $t->same('add-add', $different->conflicts[0]->reason);
        $t->same([], $different->tree->entries);
    },
    'duplicate flat tree names are rejected' => static function (TestRunner $t) use ($oid, $entry): void {
        $tree = new Tree([$entry('wp-config.php', $oid('1')), $entry('wp-config.php', $oid('2'))]);

        $t->throws(InvalidArgumentException::class, static fn () => TreeMerge::mergeFlat(new Tree([]), $tree, new Tree([])));
    },
];
