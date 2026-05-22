<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;

$fixture = require __DIR__ . '/../fixtures/wordpress-packed-reference-transaction.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-packed-ref-transaction-' . bin2hex(random_bytes(4));
mkdir($dir, 0777, true);
file_put_contents($dir . '/packed-refs', $fixture['packedRefs']);

$store = ReferenceStore::at($dir);
$committer = new CommitSignature(
    $fixture['committer']['name'],
    $fixture['committer']['email'],
    $fixture['committer']['time'],
);

$updated = $store->update(
    $fixture['productionRef'],
    ReferenceTarget::object($fixture['newProductionCommit']),
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['oldProductionCommit']),
    false,
    'sha1',
    $committer,
    $fixture['message'],
    true,
    ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
);

$deleted = $store->deleteReference(
    $fixture['reviewRef'],
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['reviewCommit']),
);

$packed = PackedReferences::open($dir . '/packed-refs');

return [
    'productionRef' => $updated->name,
    'productionCommit' => $updated->targetObjectId(),
    'deletedReviewCommit' => $deleted?->targetObjectId(),
    'packedNames' => $packed->names(),
    'packedProductionCommit' => $packed->find($fixture['productionRef'])->targetObjectId(),
    'looseProductionExists' => is_file($dir . '/' . $fixture['productionRef']),
    'reviewRefStillExists' => $store->tryFind($fixture['reviewRef']) !== null,
    'productionReflog' => $store->reflogContents($fixture['productionRef']),
    'wordpressUse' => $fixture['wordpressUse'],
];
