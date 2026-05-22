<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackedReferences;

$fixture = require __DIR__ . '/../fixtures/wordpress-packed-refs.php';
$packed = PackedReferences::fromBytes($fixture['content']);

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
];
