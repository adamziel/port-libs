<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\GitObject;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$entry = static fn (string $filename, string $oid, string $mode = '100644'): TreeEntry => new TreeEntry($mode, $filename, $oid);
$objects = [];
$write = static function (GitObject $object) use (&$objects): string {
    $oid = $object->oid();
    $objects[$oid] = $object;

    return $oid;
};
$read = static function (string $oid) use (&$objects): GitObject {
    if (!isset($objects[$oid])) {
        throw new RuntimeException("Fixture object not found: {$oid}");
    }

    return $objects[$oid];
};
$blob = static fn (string $filename, string $content): TreeEntry => new TreeEntry('100644', $filename, $write(new GitObject('blob', $content)));

return [
    'clean' => [
        'base' => new Tree([
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('3'), '40000'),
        ]),
        'ours' => new Tree([
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('4'), '40000'),
        ]),
        'theirs' => new Tree([
            $entry('.wp-env.json', $oid('5')),
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('3'), '40000'),
        ]),
    ],
    'conflict' => [
        'base' => new Tree([$entry('theme.json', $oid('6'))]),
        'ours' => new Tree([$entry('theme.json', $oid('7'))]),
        'theirs' => new Tree([$entry('theme.json', $oid('8'))]),
    ],
    'virtualBase' => [
        'read' => $read,
        'write' => $write,
        'mergeBaseAncestor' => new Tree([$blob('content', "name: demo\nversion: 1.0\nstatus: draft\n")]),
        'mergeBases' => [
            new Tree([$blob('content', "name: demo\nversion: 1.0\nstatus: review\n")]),
            new Tree([$blob('content', "name: demo\nversion: 1.1\nstatus: draft\n")]),
        ],
        'ours' => new Tree([$blob('content', "name: demo\nversion: 1.1\nstatus: publish\n")]),
        'theirs' => new Tree([$blob('renamed-content', "name: demo\nversion: 2.0\nstatus: review\n")]),
    ],
    'renameAddDelete' => [
        'read' => $read,
        'write' => $write,
        'base' => new Tree([$blob('acme.php', "Plugin: Acme\nStatus: stable\n")]),
        'ours' => new Tree([$blob('acme-review.php', "Plugin: Acme\nStatus: review\n")]),
        'theirs' => new Tree([$blob('acme-review.php', "Plugin: Acme\nStatus: stable\n")]),
    ],
    'renameAdd' => [
        'read' => $read,
        'write' => $write,
        'base' => new Tree([$blob('legacy-widget.php', "Plugin: Legacy Widget\nStable tag: trunk\nRequires PHP: 8.1\nVersion: 1.0\n")]),
        'ours' => new Tree([
            $blob('legacy-widget.php', "Plugin: Legacy Widget\nStable tag: trunk\nRequires PHP: 8.1\nVersion: 1.1\n"),
            $blob('review-widget.php', "Plugin: Review Widget\nStatus: review build\n"),
        ]),
        'theirs' => new Tree([$blob('review-widget.php', "Plugin: Legacy Widget\nStable tag: trunk\nRequires PHP: 8.1\nVersion: 1.2\n")]),
    ],
];
