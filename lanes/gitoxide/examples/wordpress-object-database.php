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
$deploymentCommitHeader = $database->readHeader($deploymentCommitOid);
$deltaBlob = $database->read($fixture['objects'][2]['oid']);
$draft = $database->read($draftOid);
$rawDraft = $database->withReplacementsIgnored()->read($draftOid);
$draftHeader = $database->readHeader($draftOid);
$rawDraftHeader = $database->withReplacementsIgnored()->readHeader($draftOid);
$sharedPackage = $database->read($sharedPackageOid);
$prefix = $database->lookupPrefix(substr($fixture['objects'][2]['oid'], 0, 8));
$looseIntegrity = $database->verifyLooseIntegrity();
$looseIntegrityObjects = array_sum(array_map(
    static fn (array $row): int => $row['statistics']['numObjects'],
    $looseIntegrity
));

$sha256GitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-sha256-' . bin2hex(random_bytes(4)) . '/.git';
$sha256Database = new ObjectDatabase($sha256GitDir, objectHash: 'sha256');
$sha256Object = new GitObject('blob', 'SHA-256-addressed WordPress deployment snapshot.');
$sha256Oid = $sha256Database->write($sha256Object);
$sha256Header = $sha256Database->readHeader(strtoupper($sha256Oid));
$sha256Integrity = $sha256Database->verifyLooseIntegrity();

$blockedGitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-directory-blocker-' . bin2hex(random_bytes(4)) . '/.git';
$blockedOid = str_repeat('a', 40);
$blockedPath = $blockedGitDir . '/objects/' . substr($blockedOid, 0, 2) . '/' . substr($blockedOid, 2);
if (!mkdir($blockedPath, 0777, true) && !is_dir($blockedPath)) {
    throw new RuntimeException("Unable to create loose object blocker example directory: {$blockedPath}");
}
$looseIntegrityDirectoryBlockerRejected = false;
try {
    (new ObjectDatabase($blockedGitDir))->verifyLooseIntegrity();
} catch (RuntimeException $exception) {
    $looseIntegrityDirectoryBlockerRejected = str_contains($exception->getMessage(), "Loose object {$blockedOid} could not be read exactly")
        && str_contains($exception->getMessage(), 'Loose object path is not a regular file');
}

$nestedCandidateGitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-nested-candidate-' . bin2hex(random_bytes(4)) . '/.git';
$nestedCandidateOid = str_repeat('b', 40);
$nestedCandidatePath = $nestedCandidateGitDir . '/objects/transient/' . substr($nestedCandidateOid, 0, 2) . '/' . substr($nestedCandidateOid, 2);
if (!mkdir(dirname($nestedCandidatePath), 0777, true) && !is_dir(dirname($nestedCandidatePath))) {
    throw new RuntimeException("Unable to create nested loose object candidate directory: " . dirname($nestedCandidatePath));
}
file_put_contents($nestedCandidatePath, 'stale loose object candidate');
$looseIntegrityNestedCandidateRejected = false;
try {
    (new ObjectDatabase($nestedCandidateGitDir))->verifyLooseIntegrity();
} catch (RuntimeException $exception) {
    $looseIntegrityNestedCandidateRejected = str_contains($exception->getMessage(), "Loose object {$nestedCandidateOid} could not be read exactly")
        && str_contains($exception->getMessage(), 'Loose object not found');
}

$sizeMismatchGitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-size-mismatch-' . bin2hex(random_bytes(4)) . '/.git';
$sizeMismatchOid = hash('sha1', "blob 3\0abc");
$sizeMismatchPath = $sizeMismatchGitDir . '/objects/' . substr($sizeMismatchOid, 0, 2) . '/' . substr($sizeMismatchOid, 2);
if (!mkdir(dirname($sizeMismatchPath), 0777, true) && !is_dir(dirname($sizeMismatchPath))) {
    throw new RuntimeException("Unable to create loose object size-mismatch directory: " . dirname($sizeMismatchPath));
}
$sizeMismatchBytes = gzcompress("blob 3\0abcdef");
if ($sizeMismatchBytes === false) {
    throw new RuntimeException('Unable to compress loose object size-mismatch fixture');
}
file_put_contents($sizeMismatchPath, $sizeMismatchBytes);
$looseIntegritySizeMismatchRejected = false;
try {
    (new ObjectDatabase($sizeMismatchGitDir))->verifyLooseIntegrity();
} catch (RuntimeException $exception) {
    $looseIntegritySizeMismatchRejected = str_contains($exception->getMessage(), "Loose object {$sizeMismatchOid} could not be read exactly")
        && str_contains($exception->getMessage(), 'Loose object inflated size mismatch');
}

$emptyLooseGitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-empty-loose-' . bin2hex(random_bytes(4)) . '/.git';
$emptyLooseOid = str_repeat('c', 40);
$emptyLoosePath = $emptyLooseGitDir . '/objects/' . substr($emptyLooseOid, 0, 2) . '/' . substr($emptyLooseOid, 2);
if (!mkdir(dirname($emptyLoosePath), 0777, true) && !is_dir(dirname($emptyLoosePath))) {
    throw new RuntimeException('Unable to create empty loose object example directory');
}
file_put_contents($emptyLoosePath, '');
$looseIntegrityEmptyFileRejected = false;
try {
    (new ObjectDatabase($emptyLooseGitDir))->verifyLooseIntegrity();
} catch (RuntimeException $exception) {
    $looseIntegrityEmptyFileRejected = str_contains($exception->getMessage(), "Loose object {$emptyLooseOid} could not be read exactly")
        && str_contains($exception->getMessage(), "Loose object file is empty: {$emptyLooseOid}");
}

$unwalkableGitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-unwalkable-' . bin2hex(random_bytes(4)) . '/.git';
$unwalkableStore = new LooseObjectStore($unwalkableGitDir);
$unwalkableOid = $unwalkableStore->write(new GitObject('blob', 'Verified deployment object beside transient scratch data.'));
$unwalkableDirectory = $unwalkableGitDir . '/objects/transient-unwalkable';
if (!mkdir($unwalkableDirectory, 0777, true) && !is_dir($unwalkableDirectory)) {
    throw new RuntimeException("Unable to create unwalkable loose object directory: {$unwalkableDirectory}");
}
file_put_contents($unwalkableDirectory . '/ignored.tmp', 'transient deployment scratch file');
chmod($unwalkableDirectory, 0000);
try {
    $looseIntegrityTraversalSummary = (new ObjectDatabase($unwalkableGitDir))->verifyLooseIntegrity();
} finally {
    chmod($unwalkableDirectory, 0777);
}
$looseIntegrityTraversalErrorIgnored = $looseIntegrityTraversalSummary[0]['statistics']['numObjects'] === 1
    && in_array($unwalkableOid, $looseIntegrityTraversalSummary[0]['statistics']['verifiedObjectIds'], true);

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
    'deploymentCommitHeaderType' => $deploymentCommitHeader['type'],
    'deploymentCommitHeaderSize' => $deploymentCommitHeader['size'],
    'replacementHeaderUsesReviewedDraft' => $draftHeader['size'] === strlen('Reviewed draft block content ready for publishing.')
        && $rawDraftHeader['size'] === strlen('Draft block content pending the next packed snapshot.'),
    'looseIntegrityStores' => count($looseIntegrity),
    'looseIntegrityObjects' => $looseIntegrityObjects,
    'looseIntegrityVerifiedDeploymentCommit' => in_array($deploymentCommitOid, $looseIntegrity[0]['statistics']['verifiedObjectIds'], true),
    'looseIntegrityVerifiedSharedPackage' => in_array($sharedPackageOid, $looseIntegrity[1]['statistics']['verifiedObjectIds'], true),
    'sha256LooseObjectOidLength' => strlen($sha256Oid),
    'sha256LooseObjectReadable' => $sha256Database->read($sha256Oid)->body === $sha256Object->body,
    'sha256LooseHeaderSource' => $sha256Header['source'],
    'sha256LooseIntegrityObjects' => $sha256Integrity[0]['statistics']['numObjects'],
    'sha256LooseIntegrityVerified' => in_array($sha256Oid, $sha256Integrity[0]['statistics']['verifiedObjectIds'], true),
    'looseIntegrityDirectoryBlockerRejected' => $looseIntegrityDirectoryBlockerRejected,
    'looseIntegrityNestedCandidateRejected' => $looseIntegrityNestedCandidateRejected,
    'looseIntegritySizeMismatchRejected' => $looseIntegritySizeMismatchRejected,
    'looseIntegrityEmptyFileRejected' => $looseIntegrityEmptyFileRejected,
    'looseIntegrityTraversalErrorIgnored' => $looseIntegrityTraversalErrorIgnored,
];
