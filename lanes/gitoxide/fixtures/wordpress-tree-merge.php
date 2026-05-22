<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$entry = static fn (string $filename, string $oid, string $mode = '100644'): TreeEntry => new TreeEntry($mode, $filename, $oid);

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
];
