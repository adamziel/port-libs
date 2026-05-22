<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;
use PortLibs\Gitoxide\ReferenceTransactionEdit;

$fixture = require __DIR__ . '/../fixtures/wordpress-deref-reference-transaction.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-deref-ref-transaction-' . bin2hex(random_bytes(4));
$store = new ReferenceStore($dir);

$store->looseStore()->writeSymbolic($fixture['headRef'], $fixture['productionRef']);
$store->looseStore()->writeDirect($fixture['productionRef'], $fixture['oldProductionCommit']);

$committer = new CommitSignature(
    $fixture['committer']['name'],
    $fixture['committer']['email'],
    $fixture['committer']['time'],
);

$result = $store->updateWithReport(
    $fixture['headRef'],
    ReferenceTarget::object($fixture['newProductionCommit']),
    ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
    ReferenceTarget::object($fixture['oldProductionCommit']),
    true,
    'sha1',
    $committer,
    $fixture['message'],
    true,
);

$deleteDir = sys_get_temp_dir() . '/port-libs-wp-deref-ref-delete-' . bin2hex(random_bytes(4));
$deleteStore = new ReferenceStore($deleteDir);
$deleteStore->looseStore()->writeSymbolic($fixture['headRef'], $fixture['productionRef']);
$deleteStore->looseStore()->writeDirect($fixture['productionRef'], $fixture['oldProductionCommit']);
$deleteStore->appendReflog(
    $fixture['headRef'],
    ReferenceTarget::object($fixture['oldProductionCommit']),
    ReferenceTarget::object($fixture['newProductionCommit']),
    $committer,
    $fixture['message'],
    true,
);
$deleteStore->appendReflog(
    $fixture['productionRef'],
    ReferenceTarget::object($fixture['oldProductionCommit']),
    ReferenceTarget::object($fixture['newProductionCommit']),
    $committer,
    $fixture['message'],
    true,
);
$deleteResult = $deleteStore->deleteWithReport(
    $fixture['headRef'],
    ReferenceStore::PREVIOUS_MUST_EXIST,
    null,
    true,
    'sha1',
    ReferenceTransactionEdit::REFLOG_ONLY,
);

return [
    'editNames' => array_map(static fn ($edit): string => $edit->name, $result->edits),
    'editModes' => array_map(static fn ($edit): string => $edit->reflogMode, $result->edits),
    'oldProductionCommit' => $result->edits[1]->previousTarget?->value,
    'productionCommit' => $result->reference->targetObjectId(),
    'headContents' => file_get_contents($dir . '/' . $fixture['headRef']),
    'productionFileCommit' => trim((string) file_get_contents($dir . '/' . $fixture['productionRef'])),
    'headReflog' => $store->reflogContents($fixture['headRef']),
    'productionReflog' => $store->reflogContents($fixture['productionRef']),
    'deleteEditNames' => array_map(static fn ($edit): string => $edit->name, $deleteResult->edits),
    'deleteEditModes' => array_map(static fn ($edit): string => $edit->reflogMode, $deleteResult->edits),
    'deleteUpdatesReference' => array_map(static fn ($edit): bool => $edit->updatesReference, $deleteResult->edits),
    'deletedProductionCommit' => $deleteResult->reference?->targetObjectId(),
    'deleteHeadContents' => file_get_contents($deleteDir . '/' . $fixture['headRef']),
    'deleteProductionFileCommit' => trim((string) file_get_contents($deleteDir . '/' . $fixture['productionRef'])),
    'deleteHeadReflogExists' => $deleteStore->reflogExists($fixture['headRef']),
    'deleteProductionReflogExists' => $deleteStore->reflogExists($fixture['productionRef']),
    'wordpressUse' => $fixture['wordpressUse'],
];
