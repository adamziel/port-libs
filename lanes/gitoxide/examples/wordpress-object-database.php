<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\LooseReferenceStore;
use PortLibs\Gitoxide\ObjectDatabase;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-data.php';
$gitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create pack example directory: {$packDir}");
}

$basename = 'pack-' . $fixture['packChecksum'];
file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);

$loose = new LooseObjectStore($gitDir);
$draftOid = $loose->write(new GitObject('blob', 'Draft block content pending the next packed snapshot.'));
$reviewedDraftOid = $loose->write(new GitObject('blob', 'Reviewed draft block content ready for publishing.'));
(new LooseReferenceStore($gitDir))->writeDirect('refs/replace/' . $draftOid, $reviewedDraftOid);
$alternateObjectsDir = dirname($gitDir) . '/shared-package-cache/.git/objects';
if (!mkdir($alternateObjectsDir, 0777, true) && !is_dir($alternateObjectsDir)) {
    throw new RuntimeException("Unable to create alternate object directory: {$alternateObjectsDir}");
}
$sharedPackageOid = LooseObjectStore::fromObjectsDirectory($alternateObjectsDir)
    ->write(new GitObject('blob', 'Shared plugin package object from an alternate cache.'));
$infoDir = $gitDir . '/objects/info';
if (!mkdir($infoDir, 0777, true) && !is_dir($infoDir)) {
    throw new RuntimeException("Unable to create objects info directory: {$infoDir}");
}
file_put_contents($infoDir . '/alternates', "# shared object cache\n{$alternateObjectsDir}\n");

$database = new ObjectDatabase($gitDir);
$packedCommitWriteOid = $database->writeCommit(Commit::parse($fixture['objects'][0]['body']));
$deploymentCommit = new Commit(
    'e90926b07092bccb7bf7da445fae6ffdfacf3eae',
    [$fixture['objects'][0]['oid']],
    'WordPress Importer <importer@example.test> 1710000000 +0000',
    'WordPress Deploy Bot <deploy@example.test> 1710000300 +0000',
    "Publish regenerated block snapshot\n",
    [],
);
$deploymentCommitOid = $database->writeCommit($deploymentCommit);
$deploymentCommitPath = $gitDir . '/objects/' . substr($deploymentCommitOid, 0, 2) . '/' . substr($deploymentCommitOid, 2);
$deploymentCommitRoundTrip = Commit::parse($database->read($deploymentCommitOid)->body);
$deltaBlob = $database->read($fixture['objects'][2]['oid']);
$draft = $database->read($draftOid);
$rawDraft = $database->withReplacementsIgnored()->read($draftOid);
$sharedPackage = $database->read($sharedPackageOid);
$prefix = $database->lookupPrefix(substr($fixture['objects'][2]['oid'], 0, 8));
$looseIntegrity = $database->verifyLooseIntegrity();
$looseIntegrityObjects = array_sum(array_map(
    static fn (array $row): int => $row['statistics']['numObjects'],
    $looseIntegrity
));

return [
    'packedObjects' => $database->packedObjectCount(),
    'totalIterableObjects' => count($database->objectIds()),
    'alternateObjectDatabases' => count($database->alternateObjectDirectories()),
    'deltaBlobOid' => $deltaBlob->oid(),
    'deltaBlobHasPackedEdit' => str_contains($deltaBlob->body, 'reconstructed packed edit'),
    'draftOid' => $draft->oid(),
    'draftSource' => 'replacement ref',
    'rawDraftOid' => $rawDraft->oid(),
    'replacementCount' => count($database->replacements()),
    'sharedPackageOid' => $sharedPackage->oid(),
    'sharedPackageSource' => 'alternate object database',
    'deltaPrefixStatus' => $prefix['status'],
    'firstPackOffsetOid' => $database->objectIds(ObjectDatabase::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL)[0],
    'packedCommitWriteSkippedLoose' => $packedCommitWriteOid === $fixture['objects'][0]['oid']
        && !is_file($gitDir . '/objects/' . substr($packedCommitWriteOid, 0, 2) . '/' . substr($packedCommitWriteOid, 2)),
    'deploymentCommitOid' => $deploymentCommitOid,
    'deploymentCommitStoredLoose' => is_file($deploymentCommitPath),
    'deploymentCommitSummary' => $deploymentCommitRoundTrip->messageSummary(),
    'deploymentCommitParent' => $deploymentCommitRoundTrip->parents[0],
    'looseIntegrityStores' => count($looseIntegrity),
    'looseIntegrityObjects' => $looseIntegrityObjects,
    'looseIntegrityVerifiedDeploymentCommit' => in_array($deploymentCommitOid, $looseIntegrity[0]['statistics']['verifiedObjectIds'], true),
    'looseIntegrityVerifiedSharedPackage' => in_array($sharedPackageOid, $looseIntegrity[1]['statistics']['verifiedObjectIds'], true),
];
