<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceStore;

$fixture = require __DIR__ . '/../fixtures/wordpress-packed-refs.php';
$packed = PackedReferences::fromBytes($fixture['content']);
$dir = sys_get_temp_dir() . '/port-libs-wp-packed-refs-' . bin2hex(random_bytes(4));
$store = new ReferenceStore($dir, $packed);
$store->looseStore()->writeSymbolic($fixture['releaseCandidateRef'], $packed->find($fixture['releaseTag'])->name);

return [
    'branch' => [
        'name' => $packed->find($fixture['branch'])->name,
        'commit' => $packed->find($fixture['branch'])->targetObjectId(),
    ],
    'remoteBranch' => [
        'name' => $packed->find($fixture['remoteBranch'])->name,
        'commit' => $packed->find($fixture['remoteBranch'])->targetObjectId(),
    ],
    'releaseTag' => [
        'name' => $packed->find($fixture['releaseTag'])->name,
        'tagObject' => $packed->find($fixture['releaseTag'])->targetObjectId(),
        'peeledCommit' => $packed->find($fixture['releaseTag'])->objectId(),
    ],
    'peeledHeads' => array_map(
        static fn ($reference): array => [
            'name' => $reference->name,
            'commit' => $reference->targetObjectId(),
            'source' => $reference->source,
        ],
        $store->prefixedPeeled('refs/heads/'),
    ),
];
