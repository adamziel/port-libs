<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceName;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;
use PortLibs\Gitoxide\ReferenceTransactionEdit;

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

$preparedBrokenDerefDeleteDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-broken-deref-delete-' . bin2hex(random_bytes(4));
$preparedBrokenDerefDeleteStore = new ReferenceStore($preparedBrokenDerefDeleteDir, null, $fixture['namespace']);
$preparedBrokenDerefDeletePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedBrokenDerefDeletePath = $preparedBrokenDerefDeleteDir . '/' . $preparedBrokenDerefDeletePrefix . $fixture['preparedBrokenDerefDeleteRef'];
if (!is_dir(dirname($preparedBrokenDerefDeletePath))) {
    mkdir(dirname($preparedBrokenDerefDeletePath), 0777, true);
}
file_put_contents($preparedBrokenDerefDeletePath, 'interrupted-checkout-left-broken-head');
$preparedBrokenDerefDelete = $preparedBrokenDerefDeleteStore->prepareLooseDeleteTransaction(
    [$fixture['preparedBrokenDerefDeleteRef']],
    ReferenceStore::PREVIOUS_ANY,
    null,
    true,
);
$preparedBrokenDerefDeleteHadLock = is_file($preparedBrokenDerefDeletePath . '.lock');
$preparedBrokenDerefDeleteEdits = $preparedBrokenDerefDelete->commit();
$preparedBrokenDerefDeleteCleanedLock = !is_file($preparedBrokenDerefDeletePath . '.lock');

$preparedBrokenDerefStrictDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-broken-deref-strict-' . bin2hex(random_bytes(4));
$preparedBrokenDerefStrictStore = new ReferenceStore($preparedBrokenDerefStrictDir, null, $fixture['namespace']);
$preparedBrokenDerefStrictPrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedBrokenDerefStrictPath = $preparedBrokenDerefStrictDir . '/' . $preparedBrokenDerefStrictPrefix . $fixture['preparedBrokenDerefDeleteRef'];
if (!is_dir(dirname($preparedBrokenDerefStrictPath))) {
    mkdir(dirname($preparedBrokenDerefStrictPath), 0777, true);
}
file_put_contents($preparedBrokenDerefStrictPath, 'strict-broken-head');
$preparedBrokenDerefStrictError = null;
try {
    $preparedBrokenDerefStrictStore->prepareLooseDeleteTransaction(
        [$fixture['preparedBrokenDerefDeleteRef']],
        ReferenceStore::PREVIOUS_MUST_EXIST,
        null,
        true,
    );
} catch (RuntimeException $exception) {
    $preparedBrokenDerefStrictError = $exception->getMessage();
}
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

$packedLockDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-packed-lock-' . bin2hex(random_bytes(4));
mkdir($packedLockDir, 0777, true);
file_put_contents($packedLockDir . '/packed-refs', "{$fixture['productionCommit']} refs/heads/production\n");
$packedLockStore = ReferenceStore::at($packedLockDir);
$preparedPackedLock = $packedLockStore->prepareLooseUpdateTransaction([
    $fixture['preparedPackedLockRef'] => ReferenceTarget::object($fixture['reviewCommit']),
]);
$preparedPackedLockHeld = is_file($packedLockDir . '/packed-refs.lock');
$preparedPackedLockBlocked = null;
try {
    $packedLockStore->prepareLooseUpdateTransaction([
        'refs/heads/review/plugin-g/concurrent' => ReferenceTarget::object($fixture['productionCommit']),
    ]);
} catch (RuntimeException $exception) {
    $preparedPackedLockBlocked = $exception->getMessage();
}
$preparedPackedRollbackEdits = $preparedPackedLock->rollback();
$preparedPackedLockCleanedRollback = !is_file($packedLockDir . '/packed-refs.lock');

$packedDeleteDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-packed-delete-' . bin2hex(random_bytes(4));
mkdir($packedDeleteDir, 0777, true);
file_put_contents(
    $packedDeleteDir . '/packed-refs',
    "{$fixture['reviewCommit']} {$fixture['preparedPackedDeleteRef']}\n"
    . "{$fixture['productionCommit']} {$fixture['preparedPackedDeleteSideRef']}\n",
);
$packedDeleteStore = ReferenceStore::at($packedDeleteDir);
$preparedPackedDelete = $packedDeleteStore->prepareLooseDeleteTransaction(
    [$fixture['preparedPackedDeleteRef']],
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['reviewCommit']),
);
$preparedPackedDeletePath = $packedDeleteDir . '/' . $fixture['preparedPackedDeleteRef'];
$preparedPackedDeleteHadLocks = is_file($packedDeleteDir . '/packed-refs.lock')
    && is_file($preparedPackedDeletePath . '.lock');
$preparedPackedDeleteEdits = $preparedPackedDelete->commit();
$preparedPackedDeleteCleanedLocks = !is_file($packedDeleteDir . '/packed-refs.lock')
    && !is_file($preparedPackedDeletePath . '.lock');
$preparedPackedDeletePackedNames = is_file($packedDeleteDir . '/packed-refs')
    ? PackedReferences::open($packedDeleteDir . '/packed-refs')->names()
    : [];

$packedUpdateDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-packed-update-' . bin2hex(random_bytes(4));
$packedUpdateStore = new ReferenceStore($packedUpdateDir, null, $fixture['namespace']);
$packedUpdatePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$packedUpdateRef = $fixture['preparedPackedUpdateRef'];
$packedUpdateStore->update(
    $packedUpdateRef,
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
);
$preparedPackedUpdate = $packedUpdateStore->prepareLooseUpdateTransaction(
    [$packedUpdateRef => ReferenceTarget::object($fixture['productionCommit'])],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedPackedUpdateReflogMessage'],
    true,
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['reviewCommit']),
    false,
    ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
);
$preparedPackedUpdatePath = $packedUpdateDir . '/' . $packedUpdatePrefix . $packedUpdateRef;
$preparedPackedUpdateHadPackedLock = is_file($packedUpdateDir . '/packed-refs.lock');
$preparedPackedUpdateNoLooseLock = !is_file($preparedPackedUpdatePath . '.lock');
$preparedPackedUpdateEdits = $preparedPackedUpdate->commit();
$preparedPackedUpdateCleanedPackedLock = !is_file($packedUpdateDir . '/packed-refs.lock');
$preparedPackedUpdatePackedNames = PackedReferences::open($packedUpdateDir . '/packed-refs')->names();

$packedMixedDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-packed-mixed-' . bin2hex(random_bytes(4));
$packedMixedStore = new ReferenceStore($packedMixedDir, null, $fixture['namespace']);
$packedMixedPrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedPackedMixed = $packedMixedStore->prepareLooseUpdateTransaction(
    [
        $fixture['preparedPackedMixedContentRef'] => ReferenceTarget::object($fixture['reviewCommit']),
        $fixture['preparedPackedMixedAssetRef'] => ReferenceTarget::object($fixture['productionCommit']),
        $fixture['preparedPackedMixedSymbolicRef'] => ReferenceTarget::symbolic($fixture['preparedPackedMixedSymbolicTargetRef']),
    ],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedPackedMixedReflogMessage'],
    true,
    ReferenceStore::PREVIOUS_ANY,
    null,
    false,
    ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
);
$preparedPackedMixedContentPath = $packedMixedDir . '/' . $packedMixedPrefix . $fixture['preparedPackedMixedContentRef'];
$preparedPackedMixedAssetPath = $packedMixedDir . '/' . $packedMixedPrefix . $fixture['preparedPackedMixedAssetRef'];
$preparedPackedMixedSymbolicPath = $packedMixedDir . '/' . $packedMixedPrefix . $fixture['preparedPackedMixedSymbolicRef'];
$preparedPackedMixedHadPackedLock = is_file($packedMixedDir . '/packed-refs.lock');
$preparedPackedMixedNoObjectLocks = !is_file($preparedPackedMixedContentPath . '.lock')
    && !is_file($preparedPackedMixedAssetPath . '.lock');
$preparedPackedMixedHadSymbolicLock = is_file($preparedPackedMixedSymbolicPath . '.lock');
$preparedPackedMixedEdits = $preparedPackedMixed->commit();
$preparedPackedMixedCleanedPackedLock = !is_file($packedMixedDir . '/packed-refs.lock');
$preparedPackedMixedPackedNames = PackedReferences::open($packedMixedDir . '/packed-refs')->names();

$packedShadowDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-packed-shadow-' . bin2hex(random_bytes(4));
mkdir($packedShadowDir, 0777, true);
file_put_contents(
    $packedShadowDir . '/packed-refs',
    "{$fixture['productionCommit']} {$fixture['preparedPackedShadowRef']}\n",
);
$packedShadowStore = ReferenceStore::at($packedShadowDir);
$preparedPackedShadow = $packedShadowStore->prepareLooseUpdateTransaction(
    [$fixture['preparedPackedShadowRef'] => ReferenceTarget::object($fixture['reviewCommit'])],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedPackedShadowReflogMessage'],
    false,
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['productionCommit']),
);
$preparedPackedShadowPath = $packedShadowDir . '/' . $fixture['preparedPackedShadowRef'];
$preparedPackedShadowHadLocks = is_file($packedShadowDir . '/packed-refs.lock')
    && is_file($preparedPackedShadowPath . '.lock');
$preparedPackedShadowEdits = $preparedPackedShadow->commit();
$preparedPackedShadowCleanedLocks = !is_file($packedShadowDir . '/packed-refs.lock')
    && !is_file($preparedPackedShadowPath . '.lock');
$preparedPackedShadowPackedCommit = PackedReferences::open($packedShadowDir . '/packed-refs')
    ->find($fixture['preparedPackedShadowRef'])
    ->targetObjectId();

$packedPseudoDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-packed-pseudo-' . bin2hex(random_bytes(4));
mkdir($packedPseudoDir, 0777, true);
file_put_contents($packedPseudoDir . '/packed-refs.lock', 'held by packed ref compaction');
$packedPseudoStore = new ReferenceStore($packedPseudoDir, null, $fixture['namespace']);
$packedPseudoPrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedPackedPseudo = $packedPseudoStore->prepareLooseUpdateTransaction(
    [$fixture['preparedPackedPseudoRef'] => ReferenceTarget::object($fixture['reviewCommit'])],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedPackedPseudoReflogMessage'],
    true,
    ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
    null,
    false,
    ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
);
$preparedPackedPseudoPath = $packedPseudoDir . '/' . $packedPseudoPrefix . $fixture['preparedPackedPseudoRef'];
$preparedPackedPseudoHadLooseLock = is_file($preparedPackedPseudoPath . '.lock');
$preparedPackedPseudoEdits = $preparedPackedPseudo->commit();

$logOnlyDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-log-only-lock-' . bin2hex(random_bytes(4));
mkdir($logOnlyDir, 0777, true);
file_put_contents($logOnlyDir . '/packed-refs', "{$fixture['productionCommit']} refs/heads/production\n");
file_put_contents($logOnlyDir . '/packed-refs.lock', 'held by packed ref compaction');
$logOnlyStore = ReferenceStore::at($logOnlyDir);
$logOnlyRef = $fixture['preparedLogOnlyRef'];
$logOnlyStore->looseStore()->writeDirect($logOnlyRef, $fixture['reviewCommit']);
$logOnlyStore->appendReflog(
    $logOnlyRef,
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceTarget::object($fixture['productionCommit']),
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    'audit before packed compaction',
    true,
);
$preparedLogOnlyDelete = $logOnlyStore->prepareLooseDeleteTransaction(
    [$logOnlyRef],
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['reviewCommit']),
    false,
    'sha1',
    ReferenceTransactionEdit::REFLOG_ONLY,
);
$preparedLogOnlyDeleteEdits = $preparedLogOnlyDelete->commit();

$preparedSymbolicDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-symbolic-lock-' . bin2hex(random_bytes(4));
$preparedSymbolicStore = new ReferenceStore($preparedSymbolicDir, null, $fixture['namespace']);
$preparedSymbolicPrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedSymbolic = $preparedSymbolicStore->prepareLooseUpdateTransaction(
    [$fixture['preparedSymbolicRef'] => ReferenceTarget::symbolic($fixture['preparedSymbolicTargetRef'])],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedSymbolicReflogMessage'],
    false,
    ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
    ReferenceTarget::object($fixture['productionCommit']),
);
$preparedSymbolicPath = $preparedSymbolicDir . '/' . $preparedSymbolicPrefix . $fixture['preparedSymbolicRef'];
$preparedSymbolicHadLock = is_file($preparedSymbolicPath . '.lock');
$preparedSymbolicEdits = $preparedSymbolic->commit();
$preparedSymbolicCleanedLock = !is_file($preparedSymbolicPath . '.lock');

$preparedDerefDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-deref-lock-' . bin2hex(random_bytes(4));
$preparedDerefStore = new ReferenceStore($preparedDerefDir, null, $fixture['namespace']);
$preparedDerefPrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedDerefStore->looseStore()->writeSymbolic($preparedDerefPrefix . $fixture['preparedDerefHeadRef'], $preparedDerefPrefix . $fixture['preparedDerefTargetRef']);
$preparedDerefStore->looseStore()->writeDirect($preparedDerefPrefix . $fixture['preparedDerefTargetRef'], $fixture['productionCommit']);
$preparedDeref = $preparedDerefStore->prepareLooseUpdateTransaction(
    [$fixture['preparedDerefHeadRef'] => ReferenceTarget::object($fixture['reviewCommit'])],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedDerefReflogMessage'],
    true,
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['productionCommit']),
    true,
);
$preparedDerefHadLocks = is_file($preparedDerefDir . '/' . $preparedDerefPrefix . 'HEAD.lock')
    && is_file($preparedDerefDir . '/' . $preparedDerefPrefix . $fixture['preparedDerefTargetRef'] . '.lock');
$preparedDerefEdits = $preparedDeref->commit();
$preparedDerefCleanedLocks = !is_file($preparedDerefDir . '/' . $preparedDerefPrefix . 'HEAD.lock')
    && !is_file($preparedDerefDir . '/' . $preparedDerefPrefix . $fixture['preparedDerefTargetRef'] . '.lock');

$preparedRecursiveDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-recursive-deref-' . bin2hex(random_bytes(4));
$preparedRecursiveStore = new ReferenceStore($preparedRecursiveDir, null, $fixture['namespace']);
$preparedRecursivePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedRecursiveCommitter = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
$preparedRecursiveStore->looseStore()->writeSymbolic(
    $preparedRecursivePrefix . $fixture['preparedRecursiveHeadRef'],
    $preparedRecursivePrefix . $fixture['preparedRecursiveStageRef'],
);
$preparedRecursiveStore->looseStore()->writeSymbolic(
    $preparedRecursivePrefix . $fixture['preparedRecursiveStageRef'],
    $preparedRecursivePrefix . $fixture['preparedRecursiveLeafRef'],
);
$preparedRecursiveStore->looseStore()->writeDirect(
    $preparedRecursivePrefix . $fixture['preparedRecursiveLeafRef'],
    $fixture['productionCommit'],
);
$preparedRecursive = $preparedRecursiveStore->prepareLooseUpdateTransaction(
    [$fixture['preparedRecursiveHeadRef'] => ReferenceTarget::object($fixture['reviewCommit'])],
    'sha1',
    $preparedRecursiveCommitter,
    $fixture['preparedRecursiveReflogMessage'],
    true,
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['productionCommit']),
    true,
);
$preparedRecursiveHeadPath = $preparedRecursiveDir . '/' . $preparedRecursivePrefix . $fixture['preparedRecursiveHeadRef'];
$preparedRecursiveStagePath = $preparedRecursiveDir . '/' . $preparedRecursivePrefix . $fixture['preparedRecursiveStageRef'];
$preparedRecursiveLeafPath = $preparedRecursiveDir . '/' . $preparedRecursivePrefix . $fixture['preparedRecursiveLeafRef'];
$preparedRecursiveHadLocks = is_file($preparedRecursiveHeadPath . '.lock')
    && is_file($preparedRecursiveStagePath . '.lock')
    && is_file($preparedRecursiveLeafPath . '.lock');
$preparedRecursiveEdits = $preparedRecursive->commit();
$preparedRecursiveCleanedLocks = !is_file($preparedRecursiveHeadPath . '.lock')
    && !is_file($preparedRecursiveStagePath . '.lock')
    && !is_file($preparedRecursiveLeafPath . '.lock');

$preparedRecursiveDeleteDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-recursive-deref-delete-' . bin2hex(random_bytes(4));
$preparedRecursiveDeleteStore = new ReferenceStore($preparedRecursiveDeleteDir, null, $fixture['namespace']);
$preparedRecursiveDeletePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedRecursiveDeleteCommitter = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
$preparedRecursiveDeleteStore->looseStore()->writeSymbolic(
    $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveHeadRef'],
    $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveStageRef'],
);
$preparedRecursiveDeleteStore->looseStore()->writeSymbolic(
    $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveStageRef'],
    $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveLeafRef'],
);
$preparedRecursiveDeleteStore->looseStore()->writeDirect(
    $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveLeafRef'],
    $fixture['productionCommit'],
);
foreach ([
    $fixture['preparedRecursiveHeadRef'],
    $fixture['preparedRecursiveStageRef'],
    $fixture['preparedRecursiveLeafRef'],
] as $preparedRecursiveDeleteRef) {
    $preparedRecursiveDeleteStore->appendReflog(
        $preparedRecursiveDeleteRef,
        ReferenceTarget::object($fixture['productionCommit']),
        ReferenceTarget::object($fixture['reviewCommit']),
        $preparedRecursiveDeleteCommitter,
        $fixture['preparedRecursiveDeleteReflogMessage'],
        true,
    );
}
$preparedRecursiveDelete = $preparedRecursiveDeleteStore->prepareLooseDeleteTransaction(
    [$fixture['preparedRecursiveHeadRef']],
    ReferenceStore::PREVIOUS_MUST_EXIST,
    null,
    true,
    'sha1',
    ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
);
$preparedRecursiveDeleteHeadPath = $preparedRecursiveDeleteDir . '/' . $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveHeadRef'];
$preparedRecursiveDeleteStagePath = $preparedRecursiveDeleteDir . '/' . $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveStageRef'];
$preparedRecursiveDeleteLeafPath = $preparedRecursiveDeleteDir . '/' . $preparedRecursiveDeletePrefix . $fixture['preparedRecursiveLeafRef'];
$preparedRecursiveDeleteHadLocks = is_file($preparedRecursiveDeleteHeadPath . '.lock')
    && is_file($preparedRecursiveDeleteStagePath . '.lock')
    && is_file($preparedRecursiveDeleteLeafPath . '.lock');
$preparedRecursiveDeleteEdits = $preparedRecursiveDelete->commit();
$preparedRecursiveDeleteCleanedLocks = !is_file($preparedRecursiveDeleteHeadPath . '.lock')
    && !is_file($preparedRecursiveDeleteStagePath . '.lock')
    && !is_file($preparedRecursiveDeleteLeafPath . '.lock');

$preparedQuietDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-quiet-lock-' . bin2hex(random_bytes(4));
$preparedQuietStore = new ReferenceStore($preparedQuietDir, null, $fixture['namespace'], ReferenceStore::WRITE_REFLOG_DISABLE);
$preparedQuietPrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedQuietStore->looseStore()->writeSymbolic($preparedQuietPrefix . $fixture['preparedQuietHeadRef'], $preparedQuietPrefix . $fixture['preparedQuietTargetRef']);
$preparedQuiet = $preparedQuietStore->prepareLooseUpdateTransaction(
    [$fixture['preparedQuietHeadRef'] => ReferenceTarget::object($fixture['reviewCommit'])],
    'sha1',
    new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
    $fixture['preparedQuietReflogMessage'],
    true,
    ReferenceStore::PREVIOUS_ANY,
    null,
    true,
);
$preparedQuietHadLocks = is_file($preparedQuietDir . '/' . $preparedQuietPrefix . 'HEAD.lock')
    && is_file($preparedQuietDir . '/' . $preparedQuietPrefix . $fixture['preparedQuietTargetRef'] . '.lock');
$preparedQuietEdits = $preparedQuiet->commit();
$preparedQuietCleanedLocks = !is_file($preparedQuietDir . '/' . $preparedQuietPrefix . 'HEAD.lock')
    && !is_file($preparedQuietDir . '/' . $preparedQuietPrefix . $fixture['preparedQuietTargetRef'] . '.lock');

$preparedReferentDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-referent-lock-' . bin2hex(random_bytes(4));
$preparedReferentStore = new ReferenceStore($preparedReferentDir, null, $fixture['namespace']);
$preparedReferentPrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedReferentCommitter = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
$preparedReferentStore->looseStore()->writeSymbolic($preparedReferentPrefix . 'HEAD', $preparedReferentPrefix . $fixture['preparedReferentRef']);
$preparedReferentStore->looseStore()->writeDirect($preparedReferentPrefix . $fixture['preparedReferentRef'], $fixture['productionCommit']);
$preparedReferentStore->appendReflog(
    'HEAD',
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceTarget::object($fixture['productionCommit']),
    $preparedReferentCommitter,
    $fixture['preparedReferentHeadReflogMessage'],
    true,
);
$preparedReferentHeadReflogBefore = $preparedReferentStore->reflogContents('HEAD');
$preparedReferent = $preparedReferentStore->prepareLooseUpdateTransaction(
    [$fixture['preparedReferentRef'] => ReferenceTarget::object($fixture['reviewCommit'])],
    'sha1',
    $preparedReferentCommitter,
    $fixture['preparedReferentReflogMessage'],
    false,
    ReferenceStore::PREVIOUS_MUST_EXIST,
);
$preparedReferentHadLock = is_file($preparedReferentDir . '/' . $preparedReferentPrefix . $fixture['preparedReferentRef'] . '.lock');
$preparedReferentEdits = $preparedReferent->commit();
$preparedReferentCleanedLock = !is_file($preparedReferentDir . '/' . $preparedReferentPrefix . $fixture['preparedReferentRef'] . '.lock');
$preparedReferentHeadReflogAfter = $preparedReferentStore->reflogContents('HEAD');

$preparedDuplicateDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-duplicate-deref-' . bin2hex(random_bytes(4));
mkdir($preparedDuplicateDir, 0777, true);
file_put_contents($preparedDuplicateDir . '/packed-refs.lock', 'held by packed compaction');
$preparedDuplicateStore = new ReferenceStore($preparedDuplicateDir, null, $fixture['namespace']);
$preparedDuplicatePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedDuplicateStore->looseStore()->writeSymbolic(
    $preparedDuplicatePrefix . $fixture['preparedDuplicateHeadRef'],
    $preparedDuplicatePrefix . $fixture['preparedDuplicateTargetRef'],
);
$preparedDuplicateStore->looseStore()->writeDirect(
    $preparedDuplicatePrefix . $fixture['preparedDuplicateTargetRef'],
    $fixture['productionCommit'],
);
$preparedDuplicateError = null;
try {
    $preparedDuplicateStore->prepareLooseUpdateTransaction(
        [
            $fixture['preparedDuplicateHeadRef'] => ReferenceTarget::object($fixture['reviewCommit']),
            $fixture['preparedDuplicateTargetRef'] => ReferenceTarget::object($fixture['productionCommit']),
        ],
        deref: true,
    );
} catch (RuntimeException $exception) {
    $preparedDuplicateError = $exception->getMessage();
}
$preparedDuplicateNoLooseLocks = !is_file($preparedDuplicateDir . '/' . $preparedDuplicatePrefix . $fixture['preparedDuplicateHeadRef'] . '.lock')
    && !is_file($preparedDuplicateDir . '/' . $preparedDuplicatePrefix . $fixture['preparedDuplicateTargetRef'] . '.lock');

$preparedDisabledDeleteDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-disabled-delete-' . bin2hex(random_bytes(4));
$preparedDisabledDeleteSetup = new ReferenceStore($preparedDisabledDeleteDir, null, $fixture['namespace']);
$preparedDisabledDeletePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedDisabledDeleteCommitter = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
$preparedDisabledDeleteSetup->looseStore()->writeSymbolic(
    $preparedDisabledDeletePrefix . $fixture['preparedDisabledDeleteHeadRef'],
    $preparedDisabledDeletePrefix . $fixture['preparedDisabledDeleteTargetRef'],
);
$preparedDisabledDeleteSetup->looseStore()->writeDirect($preparedDisabledDeletePrefix . $fixture['preparedDisabledDeleteTargetRef'], $fixture['productionCommit']);
$preparedDisabledDeleteSetup->appendReflog(
    $fixture['preparedDisabledDeleteHeadRef'],
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceTarget::object($fixture['productionCommit']),
    $preparedDisabledDeleteCommitter,
    $fixture['preparedDisabledDeleteReflogMessage'],
    true,
);
$preparedDisabledDeleteSetup->appendReflog(
    $fixture['preparedDisabledDeleteTargetRef'],
    ReferenceTarget::object($fixture['reviewCommit']),
    ReferenceTarget::object($fixture['productionCommit']),
    $preparedDisabledDeleteCommitter,
    'production branch audit stays',
    true,
);
$preparedDisabledDeleteStore = new ReferenceStore($preparedDisabledDeleteDir, null, $fixture['namespace'], ReferenceStore::WRITE_REFLOG_DISABLE);
$preparedDisabledDelete = $preparedDisabledDeleteStore->prepareLooseDeleteTransaction(
    [$fixture['preparedDisabledDeleteHeadRef']],
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::symbolic($fixture['preparedDisabledDeleteTargetRef']),
    false,
    'sha1',
    ReferenceTransactionEdit::REFLOG_ONLY,
);
$preparedDisabledDeletePath = $preparedDisabledDeleteDir . '/' . $preparedDisabledDeletePrefix . $fixture['preparedDisabledDeleteHeadRef'];
$preparedDisabledDeleteHadLock = is_file($preparedDisabledDeletePath . '.lock');
$preparedDisabledDeleteEdits = $preparedDisabledDelete->commit();
$preparedDisabledDeleteCleanedLock = !is_file($preparedDisabledDeletePath . '.lock');

$preparedWindowsDeviceDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-device-guard-' . bin2hex(random_bytes(4));
$preparedWindowsDeviceStore = new ReferenceStore(
    $preparedWindowsDeviceDir,
    null,
    null,
    ReferenceStore::WRITE_REFLOG_NORMAL,
    true,
);
$preparedWindowsDeviceError = null;
try {
    $preparedWindowsDeviceStore->prepareLooseUpdateTransaction(
        [$fixture['preparedWindowsDeviceRef'] => ReferenceTarget::object($fixture['reviewCommit'])],
        'sha1',
        new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
        'protected tenant ref should not be written',
        true,
    );
} catch (RuntimeException $exception) {
    $preparedWindowsDeviceError = $exception->getMessage();
}

$preparedPhasedDeleteDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-phased-delete-' . bin2hex(random_bytes(4));
$preparedPhasedDeleteStore = new ReferenceStore($preparedPhasedDeleteDir, null, $fixture['namespace']);
$preparedPhasedDeletePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedPhasedDeleteCommitter = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
foreach ($fixture['preparedPhasedDeleteRefs'] as $ref) {
    $preparedPhasedDeleteStore->looseStore()->writeDirect($preparedPhasedDeletePrefix . $ref, $fixture['reviewCommit']);
    $preparedPhasedDeleteStore->appendReflog(
        $ref,
        ReferenceTarget::object($fixture['reviewCommit']),
        ReferenceTarget::object($fixture['productionCommit']),
        $preparedPhasedDeleteCommitter,
        'tenant review audit before pruning',
        true,
    );
}
$preparedPhasedDelete = $preparedPhasedDeleteStore->prepareLooseDeleteTransaction(
    $fixture['preparedPhasedDeleteRefs'],
    ReferenceStore::PREVIOUS_MUST_EXIST,
);
$preparedPhasedDeleteFirstPath = $preparedPhasedDeleteDir . '/' . $preparedPhasedDeletePrefix . $fixture['preparedPhasedDeleteRefs'][0];
$preparedPhasedDeleteSecondPath = $preparedPhasedDeleteDir . '/' . $preparedPhasedDeletePrefix . $fixture['preparedPhasedDeleteRefs'][1];
$preparedPhasedDeleteSecondLogPath = $preparedPhasedDeleteDir . '/logs/' . $preparedPhasedDeletePrefix . $fixture['preparedPhasedDeleteRefs'][1];
unlink($preparedPhasedDeleteSecondLogPath);
mkdir($preparedPhasedDeleteSecondLogPath, 0777, true);
$preparedPhasedDeleteError = null;
try {
    $preparedPhasedDelete->commit();
} catch (RuntimeException $exception) {
    $preparedPhasedDeleteError = $exception->getMessage();
}

$preparedPhasedUpdateDir = sys_get_temp_dir() . '/port-libs-wp-ref-transaction-phased-update-' . bin2hex(random_bytes(4));
$preparedPhasedUpdateStore = new ReferenceStore($preparedPhasedUpdateDir, null, $fixture['namespace']);
$preparedPhasedUpdatePrefix = ReferenceName::expandNamespace($fixture['namespace']);
$preparedPhasedUpdateCommitter = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
$preparedPhasedUpdate = $preparedPhasedUpdateStore->prepareLooseUpdateTransaction(
    [
        $fixture['preparedPhasedUpdateRefs'][0] => ReferenceTarget::object($fixture['reviewCommit']),
        $fixture['preparedPhasedUpdateRefs'][1] => ReferenceTarget::object($fixture['productionCommit']),
    ],
    'sha1',
    $preparedPhasedUpdateCommitter,
    $fixture['preparedPhasedUpdateReflogMessage'],
    true,
);
$preparedPhasedUpdateFirstPath = $preparedPhasedUpdateDir . '/' . $preparedPhasedUpdatePrefix . $fixture['preparedPhasedUpdateRefs'][0];
$preparedPhasedUpdateSecondPath = $preparedPhasedUpdateDir . '/' . $preparedPhasedUpdatePrefix . $fixture['preparedPhasedUpdateRefs'][1];
mkdir($preparedPhasedUpdateSecondPath, 0777, true);
file_put_contents($preparedPhasedUpdateSecondPath . '/blocker.txt', 'not empty');
$preparedPhasedUpdateError = null;
try {
    $preparedPhasedUpdate->commit();
} catch (RuntimeException $exception) {
    $preparedPhasedUpdateError = $exception->getMessage();
}

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
    'preparedBrokenDerefDeleteEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedBrokenDerefDeleteEdits),
    'preparedBrokenDerefDeleteHadLock' => $preparedBrokenDerefDeleteHadLock,
    'preparedBrokenDerefDeleteCleanedLock' => $preparedBrokenDerefDeleteCleanedLock,
    'preparedBrokenDerefDeleteRefStillExists' => is_file($preparedBrokenDerefDeletePath),
    'preparedBrokenDerefStrictError' => $preparedBrokenDerefStrictError,
    'preparedBrokenDerefStrictRefPreserved' => is_file($preparedBrokenDerefStrictPath)
        && file_get_contents($preparedBrokenDerefStrictPath) === 'strict-broken-head',
    'preparedNoOpEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedNoOpEdits),
    'preparedNoOpCommit' => $store->find($preparedNoOpRef)->targetObjectId(),
    'preparedNoOpHeldLockPreserved' => is_file($preparedNoOpPath . '.lock')
        && file_get_contents($preparedNoOpPath . '.lock') === 'held by an idempotent deploy check',
    'preparedNoOpReflogExists' => $store->reflogExists($preparedNoOpRef),
    'preparedPackedRollbackEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedPackedRollbackEdits),
    'preparedPackedLockHeld' => $preparedPackedLockHeld,
    'preparedPackedLockBlocked' => $preparedPackedLockBlocked,
    'preparedPackedLockCleanedRollback' => $preparedPackedLockCleanedRollback,
    'preparedPackedDeleteEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedPackedDeleteEdits),
    'preparedPackedDeleteHadLocks' => $preparedPackedDeleteHadLocks,
    'preparedPackedDeleteCleanedLocks' => $preparedPackedDeleteCleanedLocks,
    'preparedPackedDeletePackedNames' => $preparedPackedDeletePackedNames,
    'preparedPackedDeleteRefStillExists' => $packedDeleteStore->tryFind($fixture['preparedPackedDeleteRef']) !== null,
    'preparedPackedDeleteSideRefStillExists' => $packedDeleteStore->tryFind($fixture['preparedPackedDeleteSideRef']) !== null,
    'preparedPackedUpdateEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedPackedUpdateEdits),
    'preparedPackedUpdateHadPackedLock' => $preparedPackedUpdateHadPackedLock,
    'preparedPackedUpdateNoLooseLock' => $preparedPackedUpdateNoLooseLock,
    'preparedPackedUpdateCleanedPackedLock' => $preparedPackedUpdateCleanedPackedLock,
    'preparedPackedUpdatePackedNames' => $preparedPackedUpdatePackedNames,
    'preparedPackedUpdateLooseSourceRemoved' => !is_file($preparedPackedUpdatePath),
    'preparedPackedUpdateSource' => $packedUpdateStore->find($packedUpdateRef)->source,
    'preparedPackedUpdateCommit' => $packedUpdateStore->find($packedUpdateRef)->targetObjectId(),
    'preparedPackedUpdateReflog' => $packedUpdateStore->reflogContents($packedUpdateRef),
    'preparedPackedMixedEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedPackedMixedEdits),
    'preparedPackedMixedHadPackedLock' => $preparedPackedMixedHadPackedLock,
    'preparedPackedMixedNoObjectLocks' => $preparedPackedMixedNoObjectLocks,
    'preparedPackedMixedHadSymbolicLock' => $preparedPackedMixedHadSymbolicLock,
    'preparedPackedMixedCleanedPackedLock' => $preparedPackedMixedCleanedPackedLock,
    'preparedPackedMixedPackedNames' => $preparedPackedMixedPackedNames,
    'preparedPackedMixedObjectLooseSourcesRemoved' => !is_file($preparedPackedMixedContentPath)
        && !is_file($preparedPackedMixedAssetPath),
    'preparedPackedMixedContentSource' => $packedMixedStore->find($fixture['preparedPackedMixedContentRef'])->source,
    'preparedPackedMixedAssetSource' => $packedMixedStore->find($fixture['preparedPackedMixedAssetRef'])->source,
    'preparedPackedMixedSymbolicSource' => $packedMixedStore->find($fixture['preparedPackedMixedSymbolicRef'])->source,
    'preparedPackedMixedSymbolicTarget' => $packedMixedStore->find($fixture['preparedPackedMixedSymbolicRef'])->target->value,
    'preparedPackedMixedContentReflog' => $packedMixedStore->reflogContents($fixture['preparedPackedMixedContentRef']),
    'preparedPackedMixedAssetReflog' => $packedMixedStore->reflogContents($fixture['preparedPackedMixedAssetRef']),
    'preparedPackedMixedSymbolicReflogExists' => $packedMixedStore->reflogExists($fixture['preparedPackedMixedSymbolicRef']),
    'preparedPackedShadowEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedPackedShadowEdits),
    'preparedPackedShadowHadLocks' => $preparedPackedShadowHadLocks,
    'preparedPackedShadowCleanedLocks' => $preparedPackedShadowCleanedLocks,
    'preparedPackedShadowPackedCommit' => $preparedPackedShadowPackedCommit,
    'preparedPackedShadowSource' => $packedShadowStore->find($fixture['preparedPackedShadowRef'])->source,
    'preparedPackedShadowLooseCommit' => $packedShadowStore->find($fixture['preparedPackedShadowRef'])->targetObjectId(),
    'preparedPackedShadowReflog' => $packedShadowStore->reflogContents($fixture['preparedPackedShadowRef']),
    'preparedPackedPseudoEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedPackedPseudoEdits),
    'preparedPackedPseudoHadLooseLock' => $preparedPackedPseudoHadLooseLock,
    'preparedPackedPseudoPackedLockPreserved' => is_file($packedPseudoDir . '/packed-refs.lock')
        && file_get_contents($packedPseudoDir . '/packed-refs.lock') === 'held by packed ref compaction',
    'preparedPackedPseudoPackedRefsExists' => is_file($packedPseudoDir . '/packed-refs'),
    'preparedPackedPseudoHeadCommit' => $packedPseudoStore->find($fixture['preparedPackedPseudoRef'])->targetObjectId(),
    'preparedPackedPseudoReflog' => $packedPseudoStore->reflogContents($fixture['preparedPackedPseudoRef']),
    'preparedLogOnlyDeleteEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedLogOnlyDeleteEdits),
    'preparedLogOnlyPackedLockPreserved' => is_file($logOnlyDir . '/packed-refs.lock')
        && file_get_contents($logOnlyDir . '/packed-refs.lock') === 'held by packed ref compaction',
    'preparedLogOnlyRefStillExists' => $logOnlyStore->tryFind($logOnlyRef) !== null,
    'preparedLogOnlyReflogExists' => $logOnlyStore->reflogExists($logOnlyRef),
    'preparedSymbolicEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedSymbolicEdits),
    'preparedSymbolicHadLock' => $preparedSymbolicHadLock,
    'preparedSymbolicCleanedLock' => $preparedSymbolicCleanedLock,
    'preparedSymbolicContents' => file_get_contents($preparedSymbolicPath),
    'preparedSymbolicTarget' => $preparedSymbolicStore->find($fixture['preparedSymbolicRef'])->target->value,
    'preparedSymbolicReflog' => $preparedSymbolicStore->reflogContents($fixture['preparedSymbolicRef']),
    'preparedDerefEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedDerefEdits),
    'preparedDerefEditModes' => array_map(static fn ($edit): string => $edit->reflogMode, $preparedDerefEdits),
    'preparedDerefUpdatesReference' => array_map(static fn ($edit): bool => $edit->updatesReference, $preparedDerefEdits),
    'preparedDerefHadLocks' => $preparedDerefHadLocks,
    'preparedDerefCleanedLocks' => $preparedDerefCleanedLocks,
    'preparedDerefHeadContents' => file_get_contents($preparedDerefDir . '/' . $preparedDerefPrefix . 'HEAD'),
    'preparedDerefProductionCommit' => $preparedDerefStore->find($fixture['preparedDerefTargetRef'])->targetObjectId(),
    'preparedDerefHeadReflog' => $preparedDerefStore->reflogContents($fixture['preparedDerefHeadRef']),
    'preparedDerefProductionReflog' => $preparedDerefStore->reflogContents($fixture['preparedDerefTargetRef']),
    'preparedRecursiveEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedRecursiveEdits),
    'preparedRecursiveEditModes' => array_map(static fn ($edit): string => $edit->reflogMode, $preparedRecursiveEdits),
    'preparedRecursiveUpdatesReference' => array_map(static fn ($edit): bool => $edit->updatesReference, $preparedRecursiveEdits),
    'preparedRecursiveHadLocks' => $preparedRecursiveHadLocks,
    'preparedRecursiveCleanedLocks' => $preparedRecursiveCleanedLocks,
    'preparedRecursiveHeadContents' => file_get_contents($preparedRecursiveHeadPath),
    'preparedRecursiveStageContents' => file_get_contents($preparedRecursiveStagePath),
    'preparedRecursiveLeafCommit' => $preparedRecursiveStore->find($fixture['preparedRecursiveLeafRef'])->targetObjectId(),
    'preparedRecursiveHeadReflog' => $preparedRecursiveStore->reflogContents($fixture['preparedRecursiveHeadRef']),
    'preparedRecursiveStageReflog' => $preparedRecursiveStore->reflogContents($fixture['preparedRecursiveStageRef']),
    'preparedRecursiveLeafReflog' => $preparedRecursiveStore->reflogContents($fixture['preparedRecursiveLeafRef']),
    'preparedRecursiveDeleteEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedRecursiveDeleteEdits),
    'preparedRecursiveDeleteEditModes' => array_map(static fn ($edit): string => $edit->reflogMode, $preparedRecursiveDeleteEdits),
    'preparedRecursiveDeleteUpdatesReference' => array_map(static fn ($edit): bool => $edit->updatesReference, $preparedRecursiveDeleteEdits),
    'preparedRecursiveDeleteHadLocks' => $preparedRecursiveDeleteHadLocks,
    'preparedRecursiveDeleteCleanedLocks' => $preparedRecursiveDeleteCleanedLocks,
    'preparedRecursiveDeleteHeadContents' => file_get_contents($preparedRecursiveDeleteHeadPath),
    'preparedRecursiveDeleteStageContents' => file_get_contents($preparedRecursiveDeleteStagePath),
    'preparedRecursiveDeleteLeafRefStillExists' => $preparedRecursiveDeleteStore->tryFind($fixture['preparedRecursiveLeafRef']) !== null,
    'preparedRecursiveDeleteReflogsExist' => [
        $preparedRecursiveDeleteStore->reflogExists($fixture['preparedRecursiveHeadRef']),
        $preparedRecursiveDeleteStore->reflogExists($fixture['preparedRecursiveStageRef']),
        $preparedRecursiveDeleteStore->reflogExists($fixture['preparedRecursiveLeafRef']),
    ],
    'preparedQuietEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedQuietEdits),
    'preparedQuietEditModes' => array_map(static fn ($edit): string => $edit->reflogMode, $preparedQuietEdits),
    'preparedQuietUpdatesReference' => array_map(static fn ($edit): bool => $edit->updatesReference, $preparedQuietEdits),
    'preparedQuietHadLocks' => $preparedQuietHadLocks,
    'preparedQuietCleanedLocks' => $preparedQuietCleanedLocks,
    'preparedQuietHeadContents' => file_get_contents($preparedQuietDir . '/' . $preparedQuietPrefix . 'HEAD'),
    'preparedQuietProductionCommit' => $preparedQuietStore->find($fixture['preparedQuietTargetRef'])->targetObjectId(),
    'preparedQuietHeadReflogExists' => $preparedQuietStore->reflogExists($fixture['preparedQuietHeadRef']),
    'preparedQuietProductionReflogExists' => $preparedQuietStore->reflogExists($fixture['preparedQuietTargetRef']),
    'preparedReferentEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedReferentEdits),
    'preparedReferentHadLock' => $preparedReferentHadLock,
    'preparedReferentCleanedLock' => $preparedReferentCleanedLock,
    'preparedReferentHeadReflogUnchanged' => $preparedReferentHeadReflogBefore === $preparedReferentHeadReflogAfter,
    'preparedReferentHeadContents' => file_get_contents($preparedReferentDir . '/' . $preparedReferentPrefix . 'HEAD'),
    'preparedReferentProductionCommit' => $preparedReferentStore->find($fixture['preparedReferentRef'])->targetObjectId(),
    'preparedReferentHeadReflog' => $preparedReferentHeadReflogAfter,
    'preparedReferentProductionReflog' => $preparedReferentStore->reflogContents($fixture['preparedReferentRef']),
    'preparedDuplicateError' => $preparedDuplicateError,
    'preparedDuplicatePackedLockPreserved' => is_file($preparedDuplicateDir . '/packed-refs.lock')
        && file_get_contents($preparedDuplicateDir . '/packed-refs.lock') === 'held by packed compaction',
    'preparedDuplicateNoLooseLocks' => $preparedDuplicateNoLooseLocks,
    'preparedDisabledDeleteEditNames' => array_map(static fn ($edit): string => $edit->name, $preparedDisabledDeleteEdits),
    'preparedDisabledDeleteHadLock' => $preparedDisabledDeleteHadLock,
    'preparedDisabledDeleteCleanedLock' => $preparedDisabledDeleteCleanedLock,
    'preparedDisabledDeleteHeadContents' => file_get_contents($preparedDisabledDeletePath),
    'preparedDisabledDeleteReflogExists' => $preparedDisabledDeleteStore->reflogExists($fixture['preparedDisabledDeleteHeadRef']),
    'preparedDisabledDeleteReferentReflogExists' => $preparedDisabledDeleteStore->reflogExists($fixture['preparedDisabledDeleteTargetRef']),
    'preparedWindowsDeviceError' => $preparedWindowsDeviceError,
    'preparedWindowsDeviceNoRefSideEffects' => !is_dir($preparedWindowsDeviceDir . '/refs'),
    'preparedWindowsDeviceNoReflogSideEffects' => !is_dir($preparedWindowsDeviceDir . '/logs'),
    'preparedPhasedDeleteError' => $preparedPhasedDeleteError,
    'preparedPhasedDeleteFirstRefStillExists' => $preparedPhasedDeleteStore->tryFind($fixture['preparedPhasedDeleteRefs'][0]) !== null,
    'preparedPhasedDeleteSecondRefStillExists' => $preparedPhasedDeleteStore->tryFind($fixture['preparedPhasedDeleteRefs'][1]) !== null,
    'preparedPhasedDeleteFirstReflogExists' => $preparedPhasedDeleteStore->reflogExists($fixture['preparedPhasedDeleteRefs'][0]),
    'preparedPhasedDeleteSecondReflogBlocked' => is_dir($preparedPhasedDeleteSecondLogPath),
    'preparedPhasedDeleteLocksPreserved' => is_file($preparedPhasedDeleteFirstPath . '.lock')
        && is_file($preparedPhasedDeleteSecondPath . '.lock'),
    'preparedPhasedUpdateError' => $preparedPhasedUpdateError,
    'preparedPhasedUpdateFirstRefStillExists' => $preparedPhasedUpdateStore->tryFind($fixture['preparedPhasedUpdateRefs'][0]) !== null,
    'preparedPhasedUpdateSecondRefStillExists' => $preparedPhasedUpdateStore->tryFind($fixture['preparedPhasedUpdateRefs'][1]) !== null,
    'preparedPhasedUpdateFirstLockCleaned' => !is_file($preparedPhasedUpdateFirstPath . '.lock'),
    'preparedPhasedUpdateSecondLockPreserved' => is_file($preparedPhasedUpdateSecondPath . '.lock'),
    'preparedPhasedUpdateSecondBlockerPreserved' => is_file($preparedPhasedUpdateSecondPath . '/blocker.txt'),
    'preparedPhasedUpdateFirstReflog' => $preparedPhasedUpdateStore->reflogContents($fixture['preparedPhasedUpdateRefs'][0]),
    'preparedPhasedUpdateSecondReflog' => $preparedPhasedUpdateStore->reflogContents($fixture['preparedPhasedUpdateRefs'][1]),
    'wordpressUse' => $fixture['wordpressUse'],
];
