<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;

$fixture = require __DIR__ . '/../fixtures/wordpress-object-database-multi-pack-sha256.php';
$gitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-midx-sha256-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create SHA-256 pack example directory: {$packDir}");
}

foreach ($fixture['packs'] as $pack) {
    file_put_contents($packDir . '/' . $pack['packName'], $pack['packBytes']);
    file_put_contents($packDir . '/' . $pack['indexName'], $pack['indexBytes']);
}
file_put_contents($packDir . '/multi-pack-index', $fixture['multiIndexBytes']);

$alternateObjectsDirectory = dirname($gitDir) . '/alternate-sha256-cache/objects';
$alternatePackDir = $alternateObjectsDirectory . '/pack';
if (!mkdir($alternatePackDir, 0777, true) && !is_dir($alternatePackDir)) {
    throw new RuntimeException("Unable to create SHA-256 alternate pack example directory: {$alternatePackDir}");
}
foreach ($fixture['packs'] as $pack) {
    file_put_contents($alternatePackDir . '/' . $pack['packName'], $pack['packBytes']);
    file_put_contents($alternatePackDir . '/' . $pack['indexName'], $pack['indexBytes']);
}
file_put_contents($alternatePackDir . '/multi-pack-index', $fixture['multiIndexBytes']);

$objectsInfoDirectory = $gitDir . '/objects/info';
if (!mkdir($objectsInfoDirectory, 0777, true) && !is_dir($objectsInfoDirectory)) {
    throw new RuntimeException("Unable to create SHA-256 objects info example directory: {$objectsInfoDirectory}");
}
file_put_contents($objectsInfoDirectory . '/alternates', $alternateObjectsDirectory . "\n");

$looseCandidateBody = 'midx-sha256-prefix-candidate-145428';
$alternateLooseCandidateOid = LooseObjectStore::fromObjectsDirectory($alternateObjectsDirectory, 'sha256')
    ->write(new GitObject('blob', $looseCandidateBody));

$database = new ObjectDatabase($gitDir, objectHash: 'sha256');
$content = $fixture['objectsByRole']['content'];
$media = $fixture['objectsByRole']['media'];
$contentObject = $database->read($content['oid']);
$mediaPrefix = $database->lookupPrefix(substr($media['oid'], 0, 12), true);
$contentDuplicatePrefix = $database->lookupPrefix(strtoupper(substr($content['oid'], 0, 12)), true);
$contentAmbiguousPrefix = $database->lookupPrefix(strtoupper(substr($content['oid'], 0, 4)), true);
$contentShortestPrefix = $database->disambiguatePrefix(strtoupper($content['oid']), 4);
$absentContentCandidate = substr($content['oid'], 0, 8) . str_repeat('f', 56);

return [
    'objectHash' => $fixture['objectHash'],
    'packedObjects' => $database->packedObjectCount(),
    'multiPackIndexChecksumLength' => strlen($fixture['multiIndexChecksum']),
    'contentOid' => $content['oid'],
    'contentOidLength' => strlen($contentObject->oid('sha256')),
    'contentReadable' => $contentObject->body === $content['body'],
    'mediaPrefixStatus' => $mediaPrefix['status'],
    'mediaPrefixCandidates' => $mediaPrefix['candidates'],
    'mediaShortestPrefix' => $database->disambiguatePrefix(strtoupper($media['oid']), 4),
    'mediaHeader' => $database->readHeader(strtoupper($media['oid'])),
    'contentDuplicatePrefixStatus' => $contentDuplicatePrefix['status'],
    'contentDuplicatePrefixCandidates' => $contentDuplicatePrefix['candidates'],
    'contentAmbiguousPrefixStatus' => $contentAmbiguousPrefix['status'],
    'contentAmbiguousPrefixCandidates' => $contentAmbiguousPrefix['candidates'],
    'alternateLooseCandidateOid' => $alternateLooseCandidateOid,
    'alternateLooseCandidateReadable' => $database->read($alternateLooseCandidateOid)->body === $looseCandidateBody,
    'contentShortestPrefixAfterAlternateCandidate' => $contentShortestPrefix,
    'absentContentCandidateShortestPrefix' => $database->disambiguatePrefix(strtoupper($absentContentCandidate), 8),
    'absentContentCandidateFullPrefixExists' => $database->disambiguatePrefix($absentContentCandidate, 64) !== null,
];
