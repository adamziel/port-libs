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
    'expectedPreparedDeleteEditNames' => [
        'refs/heads/review/plugin-d/stale',
    ],
    'expectedPreparedBrokenDeleteEditNames' => [
        'refs/heads/review/plugin-e/broken',
    ],
    'expectedPreparedDeleteHadLock' => true,
    'expectedPreparedDeleteCleanedLock' => true,
    'expectedPreparedDeleteRefStillExists' => false,
    'expectedPreparedDeleteReflogExists' => false,
    'expectedPreparedBrokenDeleteHadLock' => true,
    'expectedPreparedBrokenDeleteCleanedLock' => true,
    'expectedPreparedBrokenDeleteRefStillExists' => false,
    'preparedReflogMessage' => 'prepared tenant review refs',
    'preparedReflogCommitter' => 'Deploy Bot <deploy@example.com> 1234 +0000',
    'wordpressUse' => 'A multisite WordPress deployment tool can promote a reviewed plugin snapshot, stage a pair of prepared tenant review refs with audit reflogs, prune stale and broken review refs through prepared delete locks, prune the old review ref, and recover from an interrupted deploy that left an empty tenant HEAD directory blocker without invoking git update-ref.',
];
