<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;

$fixture = require __DIR__ . '/../fixtures/wordpress-object-header.php';
$object = new GitObject('blob', $fixture['blockBlobBody']);
$storage = $object->storageBytes();
$header = GitObject::decodeLooseHeader($storage);
$readAhead = GitObject::fromLooseBytes($storage . 'next loose object bytes already buffered');
$positiveSizeStorage = $fixture['positiveSizeLooseHeader'] . $fixture['blockBlobBody'];
$positiveSizeObject = GitObject::fromStorageBytes($positiveSizeStorage);
$strictRejectsReadAhead = false;

try {
    GitObject::fromStorageBytes($storage . 'next loose object bytes already buffered');
} catch (InvalidArgumentException) {
    $strictRejectsReadAhead = true;
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
];
