<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\Tree;

$fixture = require __DIR__ . '/../fixtures/wordpress-content-tree.php';
$tree = Tree::parse($fixture['rootTreeBody']);

return [
    'rootOid' => $tree->toObject()->oid(),
    'entries' => array_map(
        static fn ($entry): array => [
            'mode' => $entry->mode,
            'kind' => $entry->kind(),
            'path' => $entry->filename,
            'oid' => $entry->oid,
        ],
        $tree->entries,
    ),
];
