<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitTag;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;

$fixture = require __DIR__ . '/../fixtures/wordpress-packed-reference-transaction.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-packed-ref-transaction-' . bin2hex(random_bytes(4));
mkdir($dir, 0777, true);
file_put_contents($dir . '/packed-refs', $fixture['packedRefs']);

$objects = new LooseObjectStore($dir);
$releaseCommitId = $objects->write(new GitObject('commit', $fixture['releaseCommitBody']));
$releaseTag = new GitTag(
    $releaseCommitId,
    'commit',
    $fixture['releaseTagName'],
    $fixture['releaseTagger'],
    $fixture['releaseTagMessage'],
);
$releaseTagId = $objects->write($releaseTag->object());

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

$release = $store->update(
    $fixture['releaseRef'],
    ReferenceTarget::object($releaseTagId),
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
    null,
    false,
    'sha1',
    null,
    '',
    false,
    ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
    new ObjectDatabase($dir),
);

$staleSidecarDir = sys_get_temp_dir() . '/port-libs-wp-packed-ref-stale-sidecar-' . bin2hex(random_bytes(4));
mkdir($staleSidecarDir, 0777, true);
file_put_contents(
    $staleSidecarDir . '/packed-refs',
    "# pack-refs with: peeled fully-peeled sorted \n"
    . "{$releaseTagId} {$fixture['releaseRef']}\n"
    . "^{$fixture['oldProductionCommit']}\n",
);
$staleSidecarObjects = new LooseObjectStore($staleSidecarDir);
$staleSidecarObjects->write(new GitObject('commit', $fixture['releaseCommitBody']));
$staleSidecarObjects->write($releaseTag->object());
$staleSidecarStore = ReferenceStore::at($staleSidecarDir);
$staleSidecarBefore = PackedReferences::open($staleSidecarDir . '/packed-refs')
    ->find($fixture['releaseRef'])
    ->objectId();
$staleSidecarUpdate = $staleSidecarStore->update(
    $fixture['releaseRef'],
    ReferenceTarget::object($releaseTagId),
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($releaseTagId),
    false,
    'sha1',
    null,
    '',
    false,
    ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
    new ObjectDatabase($staleSidecarDir),
);
$staleSidecarAfter = PackedReferences::open($staleSidecarDir . '/packed-refs')
    ->find($fixture['releaseRef'])
    ->objectId();

$store->looseStore()->writeSymbolic('refs/heads/release-candidate', $fixture['releaseRef']);
$releaseCandidateTagObject = $store->followToObjectId('refs/heads/release-candidate');
$releaseCandidatePeeledCommit = $store->peelToObjectId('refs/heads/release-candidate');

$packed = PackedReferences::open($dir . '/packed-refs');

$externalRefreshDir = sys_get_temp_dir() . '/port-libs-wp-packed-ref-refresh-' . bin2hex(random_bytes(4));
mkdir($externalRefreshDir, 0777, true);
file_put_contents($externalRefreshDir . '/packed-refs', $fixture['oldProductionCommit'] . ' ' . $fixture['productionRef'] . "\n");
$refreshStore = ReferenceStore::at($externalRefreshDir);
$beforeExternalRefresh = $refreshStore->find($fixture['productionRef'])->targetObjectId();
file_put_contents($externalRefreshDir . '/packed-refs', $fixture['newProductionCommit'] . ' ' . $fixture['productionRef'] . "\n");
$afterExternalRefresh = $refreshStore->find($fixture['productionRef'])->targetObjectId();
unlink($externalRefreshDir . '/packed-refs');
$afterExternalRemoval = $refreshStore->tryFind($fixture['productionRef']) === null;

$lockedDir = sys_get_temp_dir() . '/port-libs-wp-packed-ref-lock-' . bin2hex(random_bytes(4));
mkdir($lockedDir, 0777, true);
file_put_contents($lockedDir . '/packed-refs', $fixture['packedRefs']);
file_put_contents($lockedDir . '/packed-refs.lock', 'held by another deployment');
$lockedStore = ReferenceStore::at($lockedDir);
$packedLockFailure = null;
try {
    $lockedStore->update(
        $fixture['productionRef'],
        ReferenceTarget::object($fixture['newProductionCommit']),
        ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
        ReferenceTarget::object($fixture['oldProductionCommit']),
        false,
        'sha1',
        null,
        '',
        false,
        ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
    );
} catch (RuntimeException $exception) {
    $packedLockFailure = $exception->getMessage();
}

$directLockedDir = sys_get_temp_dir() . '/port-libs-wp-packed-ref-direct-lock-' . bin2hex(random_bytes(4));
mkdir($directLockedDir, 0777, true);
file_put_contents($directLockedDir . '/packed-refs', $fixture['packedRefs']);
file_put_contents($directLockedDir . '/packed-refs.lock', 'held by another deployment');
$directLockedStore = ReferenceStore::at($directLockedDir);
$directPackedLockFailure = null;
try {
    $directLockedStore->update(
        $fixture['productionRef'],
        ReferenceTarget::object($fixture['newProductionCommit']),
        ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
        ReferenceTarget::object($fixture['oldProductionCommit']),
        false,
        'sha1',
        $committer,
        $fixture['message'],
        true,
    );
} catch (RuntimeException $exception) {
    $directPackedLockFailure = $exception->getMessage();
}

return [
    'productionRef' => $updated->name,
    'productionCommit' => $updated->targetObjectId(),
    'deletedReviewCommit' => $deleted?->targetObjectId(),
    'releaseRef' => $release->name,
    'releaseTagObject' => $release->targetObjectId(),
    'releasePeeledCommit' => $release->objectId(),
    'staleSidecarBefore' => $staleSidecarBefore,
    'staleSidecarAfter' => $staleSidecarAfter,
    'staleSidecarUpdatePeeledCommit' => $staleSidecarUpdate->objectId(),
    'releaseCandidateTagObject' => $releaseCandidateTagObject,
    'releaseCandidatePeeledCommit' => $releaseCandidatePeeledCommit,
    'packedNames' => $packed->names(),
    'packedProductionCommit' => $packed->find($fixture['productionRef'])->targetObjectId(),
    'packedReleaseTagObject' => $packed->find($fixture['releaseRef'])->targetObjectId(),
    'packedReleasePeeledCommit' => $packed->find($fixture['releaseRef'])->objectId(),
    'externalPackedBeforeRefresh' => $beforeExternalRefresh,
    'externalPackedAfterRefresh' => $afterExternalRefresh,
    'externalPackedAfterRemovalMissing' => $afterExternalRemoval,
    'looseProductionExists' => is_file($dir . '/' . $fixture['productionRef']),
    'looseReleaseTagExists' => is_file($dir . '/' . $fixture['releaseRef']),
    'reviewRefStillExists' => $store->tryFind($fixture['reviewRef']) !== null,
    'productionReflog' => $store->reflogContents($fixture['productionRef']),
    'packedLockFailure' => $packedLockFailure,
    'packedLockStillPresent' => is_file($lockedDir . '/packed-refs.lock'),
    'lockedPackedRefsAfterFailure' => file_get_contents($lockedDir . '/packed-refs'),
    'lockedLooseProductionExists' => is_file($lockedDir . '/' . $fixture['productionRef']),
    'directPackedLockFailure' => $directPackedLockFailure,
    'directLockedPackedRefsAfterFailure' => file_get_contents($directLockedDir . '/packed-refs'),
    'directLockedLooseProductionExists' => is_file($directLockedDir . '/' . $fixture['productionRef']),
    'directLockedProductionReflogExists' => $directLockedStore->reflogExists($fixture['productionRef']),
    'wordpressUse' => $fixture['wordpressUse'],
];
