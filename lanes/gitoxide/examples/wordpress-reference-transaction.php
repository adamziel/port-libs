<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

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
    'wordpressUse' => $fixture['wordpressUse'],
];
