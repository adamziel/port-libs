<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\LooseReferenceStore;

$fixture = require __DIR__ . '/../fixtures/wordpress-references.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-git-refs-' . bin2hex(random_bytes(4));
$store = new LooseReferenceStore($dir);

$store->writeSymbolic('HEAD', $fixture['defaultBranch']);
$store->writeDirect($fixture['defaultBranch'], $fixture['commitOid']);
$store->writeSymbolic('refs/remotes/origin/HEAD', $fixture['defaultBranch']);

return [
    'gitDirectory' => $dir,
    'head' => [
        'kind' => $store->read('HEAD')->kind(),
        'target' => $store->read('HEAD')->target->value,
    ],
    'branch' => [
        'name' => $fixture['defaultBranch'],
        'target' => $store->read($fixture['defaultBranch'])->target->value,
    ],
    'remoteHead' => $store->read('refs/remotes/origin/HEAD')->target->value,
];
