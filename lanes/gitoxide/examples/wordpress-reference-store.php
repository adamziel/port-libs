<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceStore;

$fixture = require __DIR__ . '/../fixtures/wordpress-packed-refs.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-ref-store-' . bin2hex(random_bytes(4));
$store = new ReferenceStore($dir, PackedReferences::fromBytes($fixture['content']));
$store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');

return [
    'head' => [
        'source' => $store->find('HEAD')->source,
        'target' => $store->find('HEAD')->target->value,
    ],
    'branch' => [
        'source' => $store->find($fixture['branch'])->source,
        'commit' => $store->find($fixture['branch'])->targetObjectId(),
    ],
    'releaseTag' => [
        'source' => $store->find($fixture['releaseTag'])->source,
        'peeledCommit' => $store->find($fixture['releaseTag'])->objectId(),
    ],
];
