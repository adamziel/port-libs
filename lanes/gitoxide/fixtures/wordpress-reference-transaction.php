<?php

declare(strict_types=1);

$review = '9902e3c3e8f0c569b4ab295ddf473e6de763e1e7';
$production = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$namespace = 'site-a';
$headTarget = 'refs/heads/production';

return [
    'namespace' => $namespace,
    'reviewRef' => 'refs/heads/review/plugin-a',
    'productionRef' => 'refs/heads/production',
    'reviewCommit' => $review,
    'productionCommit' => $production,
    'headTarget' => $headTarget,
    'expectedVisibleRefs' => [
        'refs/heads/production',
        'refs/heads/review/plugin-c/assets',
        'refs/heads/review/plugin-c/content',
        'refs/heads/review/plugin-f/no-op',
    ],
    'expectedPhysicalHead' => "ref: refs/namespaces/{$namespace}/{$headTarget}\n",
    'expectedHeadDirectoryRecovered' => true,
    'expectedPreparedRollbackEditNames' => [
        'refs/heads/review/plugin-b/content',
        'refs/heads/review/plugin-b/assets',
    ],
    'expectedPreparedRollbackHadLocks' => true,
    'expectedPreparedRollbackCleaned' => true,
    'expectedPreparedCommitEditNames' => [
        'refs/heads/review/plugin-c/content',
        'refs/heads/review/plugin-c/assets',
    ],
    'expectedPreparedCommitHadLocks' => true,
    'expectedPreparedCommitCleanedLocks' => true,
    'expectedPreparedCommitOpenAfterCommit' => false,
    'preparedDeleteRef' => 'refs/heads/review/plugin-d/stale',
    'preparedBrokenDeleteRef' => 'refs/heads/review/plugin-e/broken',
    'preparedNoOpRef' => 'refs/heads/review/plugin-f/no-op',
    'preparedPackedLockRef' => 'refs/heads/review/plugin-g/packed-lock',
    'preparedLogOnlyRef' => 'refs/heads/review/plugin-h/log-only',
    'preparedSymbolicRef' => 'refs/heads/review/plugin-i/symbolic',
    'preparedSymbolicTargetRef' => 'refs/heads/production',
    'preparedDerefHeadRef' => 'HEAD',
    'preparedDerefTargetRef' => 'refs/heads/production',
    'preparedQuietHeadRef' => 'HEAD',
    'preparedQuietTargetRef' => 'refs/heads/quiet-production',
    'preparedReferentRef' => 'refs/heads/production',
    'expectedPreparedDeleteEditNames' => [
        'refs/heads/review/plugin-d/stale',
    ],
    'expectedPreparedBrokenDeleteEditNames' => [
        'refs/heads/review/plugin-e/broken',
    ],
    'expectedPreparedNoOpEditNames' => [
        'refs/heads/review/plugin-f/no-op',
    ],
    'expectedPreparedPackedRollbackEditNames' => [
        'refs/heads/review/plugin-g/packed-lock',
    ],
    'expectedPreparedLogOnlyDeleteEditNames' => [
        'refs/heads/review/plugin-h/log-only',
    ],
    'expectedPreparedSymbolicEditNames' => [
        'refs/heads/review/plugin-i/symbolic',
    ],
    'expectedPreparedDerefEditNames' => [
        'HEAD',
        'refs/heads/production',
    ],
    'expectedPreparedQuietEditNames' => [
        'HEAD',
        'refs/heads/quiet-production',
    ],
    'expectedPreparedReferentEditNames' => [
        'refs/heads/production',
    ],
    'expectedPreparedDerefEditModes' => [
        'only',
        'and-reference',
    ],
    'expectedPreparedDerefUpdatesReference' => [
        false,
        true,
    ],
    'expectedPreparedQuietEditModes' => [
        'only',
        'and-reference',
    ],
    'expectedPreparedQuietUpdatesReference' => [
        false,
        true,
    ],
    'expectedPreparedQuietHeadContents' => "ref: refs/namespaces/{$namespace}/refs/heads/quiet-production\n",
    'expectedPreparedReferentHeadContents' => "ref: refs/namespaces/{$namespace}/refs/heads/production\n",
    'expectedPreparedDeleteHadLock' => true,
    'expectedPreparedDeleteCleanedLock' => true,
    'expectedPreparedDeleteRefStillExists' => false,
    'expectedPreparedDeleteReflogExists' => false,
    'expectedPreparedBrokenDeleteHadLock' => true,
    'expectedPreparedBrokenDeleteCleanedLock' => true,
    'expectedPreparedBrokenDeleteRefStillExists' => false,
    'expectedPreparedNoOpHeldLockPreserved' => true,
    'expectedPreparedNoOpReflogExists' => false,
    'expectedPreparedPackedLockHeld' => true,
    'expectedPreparedPackedLockBlockedPrefix' => 'The lock for the packed-ref file could not be obtained',
    'expectedPreparedPackedLockCleanedRollback' => true,
    'expectedPreparedLogOnlyPackedLockPreserved' => true,
    'expectedPreparedLogOnlyRefStillExists' => true,
    'expectedPreparedLogOnlyReflogExists' => false,
    'expectedPreparedSymbolicHadLock' => true,
    'expectedPreparedSymbolicCleanedLock' => true,
    'expectedPreparedReferentHadLock' => true,
    'expectedPreparedReferentCleanedLock' => true,
    'expectedPreparedReferentHeadReflogUnchanged' => true,
    'preparedReflogMessage' => 'prepared tenant review refs',
    'preparedNoOpReflogMessage' => 'idempotent tenant review ref',
    'preparedSymbolicReflogMessage' => 'prepared symbolic review pointer',
    'preparedDerefReflogMessage' => 'prepared symbolic production publish',
    'preparedQuietReflogMessage' => 'quiet symbolic production publish',
    'preparedReferentHeadReflogMessage' => 'initial production checkout',
    'preparedReferentReflogMessage' => '',
    'preparedReflogCommitter' => 'Deploy Bot <deploy@example.com> 1234 +0000',
    'expectedPreparedReferentHeadReflog' => "{$review} {$production} Deploy Bot <deploy@example.com> 1234 +0000\tinitial production checkout\n",
    'expectedPreparedReferentReflogLine' => "{$production} {$review} Deploy Bot <deploy@example.com> 1234 +0000\n",
    'wordpressUse' => 'A multisite WordPress deployment tool can promote a reviewed plugin snapshot, stage a pair of prepared tenant review refs with audit reflogs, stage a clone-style symbolic review pointer whose reflog records the peeled production commit before publishing the symbolic lock, stage a dereferenced symbolic HEAD publish that logs both HEAD and the production branch while preserving the symbolic parent, stage a direct production referent publish that updates only the branch reflog while leaving HEAD audit history untouched, disable prepared deref reflog writes for quiet publish previews, hold packed-ref transaction locks while prepared ref updates are in flight, skip idempotent prepared writes without disturbing a held ref lock or adding reflog noise, prune stale and broken review refs through prepared delete locks, remove reflog-only audit trails even while packed refs are locked for compaction, prune the old review ref, and recover from an interrupted deploy that left an empty tenant HEAD directory blocker without invoking git update-ref.',
];
