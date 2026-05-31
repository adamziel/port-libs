<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;

$fixture = require __DIR__ . '/../fixtures/wordpress-object-header.php';
$object = new GitObject('blob', $fixture['blockBlobBody']);
$storage = $object->storageBytes();
$header = GitObject::decodeLooseHeader($storage);
$readAhead = GitObject::fromLooseBytes($storage . 'next loose object bytes already buffered');
$positiveSizeStorage = $fixture['positiveSizeLooseHeader'] . $fixture['blockBlobBody'];
$positiveSizeObject = GitObject::fromStorageBytes($positiveSizeStorage);
$strictRejectsReadAhead = false;
$allocationLimitRejected = false;
$allocationLimitMessage = null;

try {
    GitObject::fromStorageBytes($storage . 'next loose object bytes already buffered');
} catch (InvalidArgumentException) {
    $strictRejectsReadAhead = true;
}

$objectsDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-' . bin2hex(random_bytes(4)) . '/objects';
$oversizedPath = $objectsDirectory . '/' . substr($fixture['oversizedLooseObjectOid'], 0, 2) . '/' . substr($fixture['oversizedLooseObjectOid'], 2);
if (!is_dir(dirname($oversizedPath)) && !mkdir(dirname($oversizedPath), 0777, true) && !is_dir(dirname($oversizedPath))) {
    throw new RuntimeException('Unable to create object-header allocation-limit fixture directory');
}
$oversizedCompressed = gzcompress($fixture['oversizedLooseHeader'] . 'tiny');
if ($oversizedCompressed === false) {
    throw new RuntimeException('Unable to compress object-header allocation-limit fixture');
}
file_put_contents($oversizedPath, $oversizedCompressed);
$boundedStore = LooseObjectStore::fromObjectsDirectory($objectsDirectory, allocationLimitBytes: $fixture['allocationLimitBytes']);
$oversizedHeader = $boundedStore->readHeader($fixture['oversizedLooseObjectOid']);

try {
    $boundedStore->read($fixture['oversizedLooseObjectOid']);
} catch (RuntimeException $exception) {
    $allocationLimitRejected = true;
    $allocationLimitMessage = $exception->getMessage();
}

return [
    'type' => $header['type'],
    'size' => $header['size'],
    'headerLength' => $header['headerLength'],
    'looseHeader' => substr($storage, 0, $header['headerLength']),
    'oid' => $object->oid(),
    'sha256Oid' => $object->oid('sha256'),
    'readAheadIgnored' => $readAhead->body === $fixture['blockBlobBody'],
    'strictStorageRejectsReadAhead' => $strictRejectsReadAhead,
    'positiveSizeHeaderAccepted' => $positiveSizeObject->body === $fixture['blockBlobBody'],
    'positiveSizeCanonicalOid' => $positiveSizeObject->oid(),
    'positiveSizeRawHeaderOid' => hash('sha1', $positiveSizeStorage),
    'allocationLimitBytes' => $boundedStore->allocationLimitBytes(),
    'oversizedHeaderSize' => $oversizedHeader['size'],
    'allocationLimitRejected' => $allocationLimitRejected,
    'allocationLimitMessage' => $allocationLimitMessage,
];
