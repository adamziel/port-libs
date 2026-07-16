<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceName;
use PortLibs\Gitoxide\ReferenceStore;

$fixture = require __DIR__ . '/../fixtures/wordpress-namespaced-refs.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-namespaced-ref-store-' . bin2hex(random_bytes(4));

foreach ($fixture['looseRefs'] as $name => $contents) {
    $path = $dir . '/' . $name;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);
}

$store = new ReferenceStore($dir, PackedReferences::fromBytes($fixture['packedRefs']), $fixture['namespace']);
$plainStore = new ReferenceStore($dir, PackedReferences::fromBytes($fixture['packedRefs']));
$alias = $store->find('review-alias');

return [
    'namespace' => $fixture['namespace'],
    'namespacedPrefix' => ReferenceName::expandNamespace($fixture['namespace']),
    'storeRelativeNames' => array_map(static fn ($reference): string => $reference->name, $store->all()),
    'storeRelativeLooseNames' => array_map(static fn ($reference): string => $reference->name, $store->looseAll()),
    'fullNamespacedNames' => array_map(
        static fn ($reference): string => $reference->name,
        $plainStore->prefixed(ReferenceName::expandNamespace($fixture['namespace'])),
    ),
    'reviewBranchCommit' => $store->find('heads/review/plugin-a')->targetObjectId(),
    'remoteReviewCommit' => $store->find('refs/remotes/origin/review/plugin-a')->targetObjectId(),
    'aliasTarget' => $alias->target->value,
    'redundantNamespaceLookup' => $store->tryFind(
        ReferenceName::prefixNamespace('refs/heads/review/plugin-a', $fixture['namespace'])
    ) === null,
];
