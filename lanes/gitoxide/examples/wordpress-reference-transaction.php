<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\ReferenceName;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;

$fixture = require __DIR__ . '/../fixtures/wordpress-reference-transaction.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-' . bin2hex(random_bytes(4));
$store = new ReferenceStore($dir, null, $fixture['namespace']);
$prefix = ReferenceName::expandNamespace($fixture['namespace']);
$physicalHead = $dir . '/' . $prefix . 'HEAD';

$store->update(
    $fixture['reviewRef'],
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
);
$production = $store->update(
    $fixture['productionRef'],
    ReferenceTarget::object($fixture['productionCommit']),
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
);
$deleted = $store->deleteReference(
    $fixture['reviewRef'],
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['reviewCommit']),
);
mkdir($physicalHead . '/interrupted-deploy/empty', 0777, true);
$head = $store->update(
    'HEAD',
    ReferenceTarget::symbolic($fixture['headTarget']),
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
);
$prepared = $store->prepareLooseUpdateTransaction([
    'refs/heads/review/plugin-b/content' => ReferenceTarget::object($fixture['reviewCommit']),
    'refs/heads/review/plugin-b/assets' => ReferenceTarget::object($fixture['productionCommit']),
]);
$preparedHadLocks = is_file($dir . '/' . $prefix . 'refs/heads/review/plugin-b/content.lock')
    && is_file($dir . '/' . $prefix . 'refs/heads/review/plugin-b/assets.lock');
$preparedRollbackEdits = $prepared->rollback();
$preparedRollbackCleaned = !is_dir($dir . '/' . $prefix . 'refs/heads/review/plugin-b');
$preparedCommit = $store->prepareLooseUpdateTransaction([
    'refs/heads/review/plugin-c/content' => ReferenceTarget::object($fixture['reviewCommit']),
    'refs/heads/review/plugin-c/assets' => ReferenceTarget::object($fixture['productionCommit']),
], 'sha1', new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'), $fixture['preparedReflogMessage']);
$preparedCommitHadLocks = is_file($dir . '/' . $prefix . 'refs/heads/review/plugin-c/content.lock')
    && is_file($dir . '/' . $prefix . 'refs/heads/review/plugin-c/assets.lock');
$preparedCommitEdits = $preparedCommit->commit();
$preparedCommitCleanedLocks = !is_file($dir . '/' . $prefix . 'refs/heads/review/plugin-c/content.lock')
    && !is_file($dir . '/' . $prefix . 'refs/heads/review/plugin-c/assets.lock');
$preparedDeleteRef = $fixture['preparedDeleteRef'];
$store->update(
    $preparedDeleteRef,
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
    null,
    false,
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    'stale tenant review ref',
    true,
);
$preparedDelete = $store->prepareLooseDeleteTransaction(
    [$preparedDeleteRef],
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['reviewCommit']),
);
$preparedDeleteHadLock = is_file($dir . '/' . $prefix . $preparedDeleteRef . '.lock');
$preparedDeleteEdits = $preparedDelete->commit();
$preparedDeleteCleanedLock = !is_file($dir . '/' . $prefix . $preparedDeleteRef . '.lock');
$preparedBrokenDeleteRef = $fixture['preparedBrokenDeleteRef'];
$preparedBrokenPath = $dir . '/' . $prefix . $preparedBrokenDeleteRef;
if (!is_dir(dirname($preparedBrokenPath))) {
    mkdir(dirname($preparedBrokenPath), 0777, true);
}
file_put_contents($preparedBrokenPath, 'interrupted-deploy-left-a-broken-ref');
$preparedBrokenDelete = $store->prepareLooseDeleteTransaction(
    [$preparedBrokenDeleteRef],
    ReferenceStore::PREVIOUS_MUST_EXIST,
);
$preparedBrokenDeleteHadLock = is_file($preparedBrokenPath . '.lock');
$preparedBrokenDeleteEdits = $preparedBrokenDelete->commit();
$preparedBrokenDeleteCleanedLock = !is_file($preparedBrokenPath . '.lock');
$preparedNoOpRef = $fixture['preparedNoOpRef'];
$store->update(
    $preparedNoOpRef,
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
);
$preparedNoOpPath = $dir . '/' . $prefix . $preparedNoOpRef;
file_put_contents($preparedNoOpPath . '.lock', 'held by an idempotent deploy check');
$preparedNoOp = $store->prepareLooseUpdateTransaction(
    [$preparedNoOpRef => ReferenceTarget::object($fixture['reviewCommit'])],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedNoOpReflogMessage'],
    true,
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
);
$preparedNoOpEdits = $preparedNoOp->commit();

return [
    'namespace' => $fixture['namespace'],
    'productionCommit' => $production->targetObjectId(),
    'deletedReviewCommit' => $deleted?->targetObjectId(),
    'headTarget' => $head->target->value,
    'visibleRefs' => array_map(static fn ($reference): string => $reference->name, $store->all()),
    'physicalHead' => file_get_contents($physicalHead),
    'reviewRefStillExists' => $store->tryFind($fixture['reviewRef']) !== null,
    'headDirectoryRecovered' => is_file($physicalHead) && !is_dir($physicalHead . '/interrupted-deploy'),
    'preparedRollbackEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedRollbackEdits),
    'preparedRollbackHadLocks' => $preparedHadLocks,
    'preparedRollbackCleaned' => $preparedRollbackCleaned,
    'preparedCommitEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedCommitEdits),
    'preparedCommitHadLocks' => $preparedCommitHadLocks,
    'preparedCommitCleanedLocks' => $preparedCommitCleanedLocks,
    'preparedCommitOpenAfterCommit' => $preparedCommit->isOpen(),
    'preparedContentCommit' => $store->find('refs/heads/review/plugin-c/content')->targetObjectId(),
    'preparedAssetsCommit' => $store->find('refs/heads/review/plugin-c/assets')->targetObjectId(),
    'preparedContentReflog' => $store->reflogContents('refs/heads/review/plugin-c/content'),
    'preparedAssetsReflog' => $store->reflogContents('refs/heads/review/plugin-c/assets'),
    'preparedDeleteEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedDeleteEdits),
    'preparedDeleteHadLock' => $preparedDeleteHadLock,
    'preparedDeleteCleanedLock' => $preparedDeleteCleanedLock,
    'preparedDeleteRefStillExists' => $store->tryFind($preparedDeleteRef) !== null,
    'preparedDeleteReflogExists' => $store->reflogExists($preparedDeleteRef),
    'preparedBrokenDeleteEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedBrokenDeleteEdits),
    'preparedBrokenDeleteHadLock' => $preparedBrokenDeleteHadLock,
    'preparedBrokenDeleteCleanedLock' => $preparedBrokenDeleteCleanedLock,
    'preparedBrokenDeleteRefStillExists' => is_file($preparedBrokenPath),
    'preparedNoOpEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedNoOpEdits),
    'preparedNoOpCommit' => $store->find($preparedNoOpRef)->targetObjectId(),
    'preparedNoOpHeldLockPreserved' => is_file($preparedNoOpPath . '.lock')
        && file_get_contents($preparedNoOpPath . '.lock') === 'held by an idempotent deploy check',
    'preparedNoOpReflogExists' => $store->reflogExists($preparedNoOpRef),
    'wordpressUse' => $fixture['wordpressUse'],
];
