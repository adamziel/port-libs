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
$zeroPaddedSizeStorage = $fixture['zeroPaddedSizeLooseHeader'] . $fixture['blockBlobBody'];
$zeroPaddedSizeObject = GitObject::fromStorageBytes($zeroPaddedSizeStorage);
$zeroPaddedSizeIntegrityVerified = false;
$zeroPaddedSizeNonCanonicalRejected = false;
$zeroPaddedSizeNonCanonicalMessage = null;
$negativeZeroSizeStorage = $fixture['negativeZeroSizeLooseHeader'] . $fixture['emptyBlobBody'];
$negativeZeroSizeObject = GitObject::fromStorageBytes($negativeZeroSizeStorage);
$lfSizeStorage = $fixture['lfSizeLooseHeader'] . $fixture['blockBlobBody'];
$strictRejectsReadAhead = false;
$lfSizeHeaderRejected = false;
$lfSizeHeaderMessage = null;
$lfSizeReadRejected = false;
$lfSizeReadMessage = null;
$lfSizeIntegrityRejected = false;
$lfSizeIntegrityMessage = null;
$missingNulHeaderRejected = false;
$missingNulHeaderMessage = null;
$missingNulReadRejected = false;
$missingNulIntegrityRejected = false;
$missingNulIntegrityMessage = null;
$noTypeSizeDelimiterHeaderRejected = false;
$noTypeSizeDelimiterHeaderMessage = null;
$noTypeSizeDelimiterReadRejected = false;
$noTypeSizeDelimiterIntegrityRejected = false;
$noTypeSizeDelimiterIntegrityMessage = null;
$nulBeforeSpaceUnknownKindHeaderRejected = false;
$nulBeforeSpaceUnknownKindHeaderMessage = null;
$nulBeforeSpaceUnknownKindReadRejected = false;
$nulBeforeSpaceUnknownKindIntegrityRejected = false;
$nulBeforeSpaceUnknownKindIntegrityMessage = null;
$unknownKindHeaderRejected = false;
$unknownKindHeaderMessage = null;
$unknownKindReadRejected = false;
$unknownKindIntegrityRejected = false;
$unknownKindIntegrityMessage = null;
$allocationLimitRejected = false;
$allocationLimitMessage = null;
$trailingStreamIgnored = false;
$trailingStreamIntegrityVerified = false;
$lateSameStreamOverrunIgnored = false;
$lateSameStreamIntegrityVerified = false;
$truncatedHeaderInflateRejected = false;
$truncatedHeaderMessage = null;
$corruptFirstWindowHeaderRejected = false;
$corruptFirstWindowHeaderMessage = null;
$finalizedReadOnly = false;
$finalizedExistingObjectPreserved = false;
$integrityInterruptHandled = false;
$integrityInterruptChecks = 0;
$integrityInterruptMessage = null;

$truncatedBeforeHeaderWindowCompletes = static function (string $storageBytes): string {
    $compressed = gzcompress($storageBytes);
    if ($compressed === false) {
        throw new RuntimeException('Unable to compress object-header truncated-stream fixture');
    }

    $length = strlen($compressed);
    for ($candidateLength = 2; $candidateLength < $length; $candidateLength++) {
        $candidate = substr($compressed, 0, $candidateLength);
        $context = inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new RuntimeException('Unable to initialize object-header truncated-stream probe');
        }
        $decoded = @inflate_add($context, $candidate, ZLIB_FINISH);
        if (
            $decoded !== false
            && strpos($decoded, "\0") !== false
            && strlen($decoded) < 64
            && inflate_get_status($context) !== ZLIB_STREAM_END
        ) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to derive object-header truncated-stream fixture');
};

try {
    GitObject::fromStorageBytes($storage . 'next loose object bytes already buffered');
} catch (InvalidArgumentException) {
    $strictRejectsReadAhead = true;
}

$zeroPaddedObjectsDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-zero-padded-' . bin2hex(random_bytes(4)) . '/objects';
$zeroPaddedOid = $zeroPaddedSizeObject->oid();
$zeroPaddedPath = $zeroPaddedObjectsDirectory . '/' . substr($zeroPaddedOid, 0, 2) . '/' . substr($zeroPaddedOid, 2);
if (!is_dir(dirname($zeroPaddedPath)) && !mkdir(dirname($zeroPaddedPath), 0777, true) && !is_dir(dirname($zeroPaddedPath))) {
    throw new RuntimeException('Unable to create object-header zero-padded size fixture directory');
}
$zeroPaddedCompressed = gzcompress($zeroPaddedSizeStorage);
if ($zeroPaddedCompressed === false) {
    throw new RuntimeException('Unable to compress object-header zero-padded size fixture');
}
file_put_contents($zeroPaddedPath, $zeroPaddedCompressed);
$zeroPaddedStore = LooseObjectStore::fromObjectsDirectory($zeroPaddedObjectsDirectory);
$zeroPaddedIntegrity = $zeroPaddedStore->verifyIntegrity();
$zeroPaddedSizeIntegrityVerified = $zeroPaddedIntegrity['numObjects'] === 1
    && $zeroPaddedIntegrity['verifiedObjectIds'] === [$zeroPaddedOid];

$zeroPaddedNonCanonicalDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-zero-padded-raw-' . bin2hex(random_bytes(4)) . '/objects';
$zeroPaddedRawOid = hash('sha1', $zeroPaddedSizeStorage);
$zeroPaddedRawPath = $zeroPaddedNonCanonicalDirectory . '/' . substr($zeroPaddedRawOid, 0, 2) . '/' . substr($zeroPaddedRawOid, 2);
if (!is_dir(dirname($zeroPaddedRawPath)) && !mkdir(dirname($zeroPaddedRawPath), 0777, true) && !is_dir(dirname($zeroPaddedRawPath))) {
    throw new RuntimeException('Unable to create object-header raw zero-padded size fixture directory');
}
file_put_contents($zeroPaddedRawPath, $zeroPaddedCompressed);
try {
    LooseObjectStore::fromObjectsDirectory($zeroPaddedNonCanonicalDirectory)->verifyIntegrity();
} catch (RuntimeException $exception) {
    $zeroPaddedSizeNonCanonicalRejected = str_contains($exception->getMessage(), 'Loose object hash mismatch')
        && str_contains($exception->getMessage(), $zeroPaddedRawOid)
        && str_contains($exception->getMessage(), $zeroPaddedOid);
    $zeroPaddedSizeNonCanonicalMessage = $exception->getMessage();
}

try {
    GitObject::decodeLooseHeader($lfSizeStorage);
} catch (InvalidArgumentException $exception) {
    $lfSizeHeaderRejected = true;
    $lfSizeHeaderMessage = $exception->getMessage();
}

$lfSizeDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-lf-size-' . bin2hex(random_bytes(4)) . '/objects';
$lfSizePath = $lfSizeDirectory . '/' . substr($object->oid(), 0, 2) . '/' . substr($object->oid(), 2);
if (!is_dir(dirname($lfSizePath)) && !mkdir(dirname($lfSizePath), 0777, true) && !is_dir(dirname($lfSizePath))) {
    throw new RuntimeException('Unable to create object-header LF-size fixture directory');
}
$lfSizeCompressed = gzcompress($lfSizeStorage);
if ($lfSizeCompressed === false) {
    throw new RuntimeException('Unable to compress object-header LF-size fixture');
}
file_put_contents($lfSizePath, $lfSizeCompressed);
$lfSizeStore = LooseObjectStore::fromObjectsDirectory($lfSizeDirectory);
try {
    $lfSizeStore->read($object->oid());
} catch (InvalidArgumentException $exception) {
    $lfSizeReadRejected = true;
    $lfSizeReadMessage = $exception->getMessage();
}
try {
    $lfSizeStore->verifyIntegrity();
} catch (RuntimeException $exception) {
    $lfSizeIntegrityRejected = true;
    $lfSizeIntegrityMessage = $exception->getMessage();
}

$missingNulDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-missing-nul-' . bin2hex(random_bytes(4)) . '/objects';
$missingNulPath = $missingNulDirectory . '/' . substr($fixture['missingNulHeaderOid'], 0, 2) . '/' . substr($fixture['missingNulHeaderOid'], 2);
if (!is_dir(dirname($missingNulPath)) && !mkdir(dirname($missingNulPath), 0777, true) && !is_dir(dirname($missingNulPath))) {
    throw new RuntimeException('Unable to create object-header missing-NUL fixture directory');
}
$missingNulCompressed = gzcompress($fixture['missingNulHeaderStorage']);
if ($missingNulCompressed === false) {
    throw new RuntimeException('Unable to compress object-header missing-NUL fixture');
}
file_put_contents($missingNulPath, $missingNulCompressed);
$missingNulStore = LooseObjectStore::fromObjectsDirectory($missingNulDirectory);
try {
    $missingNulStore->readHeader($fixture['missingNulHeaderOid']);
} catch (InvalidArgumentException $exception) {
    $missingNulHeaderRejected = true;
    $missingNulHeaderMessage = $exception->getMessage();
}
try {
    $missingNulStore->read($fixture['missingNulHeaderOid']);
} catch (InvalidArgumentException $exception) {
    $missingNulReadRejected = $exception->getMessage() === 'Did not find 0 byte in header';
}
try {
    $missingNulStore->verifyIntegrity();
} catch (RuntimeException $exception) {
    $missingNulIntegrityRejected = true;
    $missingNulIntegrityMessage = $exception->getMessage();
}

$noTypeSizeDelimiterDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-no-delimiter-' . bin2hex(random_bytes(4)) . '/objects';
$noTypeSizeDelimiterPath = $noTypeSizeDelimiterDirectory . '/' . substr($fixture['noTypeSizeDelimiterOid'], 0, 2) . '/' . substr($fixture['noTypeSizeDelimiterOid'], 2);
if (!is_dir(dirname($noTypeSizeDelimiterPath)) && !mkdir(dirname($noTypeSizeDelimiterPath), 0777, true) && !is_dir(dirname($noTypeSizeDelimiterPath))) {
    throw new RuntimeException('Unable to create object-header no-delimiter fixture directory');
}
$noTypeSizeDelimiterCompressed = gzcompress($fixture['noTypeSizeDelimiterStorage']);
if ($noTypeSizeDelimiterCompressed === false) {
    throw new RuntimeException('Unable to compress object-header no-delimiter fixture');
}
file_put_contents($noTypeSizeDelimiterPath, $noTypeSizeDelimiterCompressed);
$noTypeSizeDelimiterStore = LooseObjectStore::fromObjectsDirectory($noTypeSizeDelimiterDirectory);
try {
    $noTypeSizeDelimiterStore->readHeader($fixture['noTypeSizeDelimiterOid']);
} catch (InvalidArgumentException $exception) {
    $noTypeSizeDelimiterHeaderRejected = true;
    $noTypeSizeDelimiterHeaderMessage = $exception->getMessage();
}
try {
    $noTypeSizeDelimiterStore->read($fixture['noTypeSizeDelimiterOid']);
} catch (InvalidArgumentException $exception) {
    $noTypeSizeDelimiterReadRejected = $exception->getMessage() === "Expected '<type> <size>'";
}
try {
    $noTypeSizeDelimiterStore->verifyIntegrity();
} catch (RuntimeException $exception) {
    $noTypeSizeDelimiterIntegrityRejected = true;
    $noTypeSizeDelimiterIntegrityMessage = $exception->getMessage();
}

$nulBeforeSpaceUnknownKindDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-nul-before-space-' . bin2hex(random_bytes(4)) . '/objects';
$nulBeforeSpaceUnknownKindPath = $nulBeforeSpaceUnknownKindDirectory . '/' . substr($fixture['nulBeforeSpaceUnknownKindOid'], 0, 2) . '/' . substr($fixture['nulBeforeSpaceUnknownKindOid'], 2);
if (!is_dir(dirname($nulBeforeSpaceUnknownKindPath)) && !mkdir(dirname($nulBeforeSpaceUnknownKindPath), 0777, true) && !is_dir(dirname($nulBeforeSpaceUnknownKindPath))) {
    throw new RuntimeException('Unable to create object-header NUL-before-space fixture directory');
}
$nulBeforeSpaceUnknownKindCompressed = gzcompress($fixture['nulBeforeSpaceUnknownKindStorage']);
if ($nulBeforeSpaceUnknownKindCompressed === false) {
    throw new RuntimeException('Unable to compress object-header NUL-before-space fixture');
}
file_put_contents($nulBeforeSpaceUnknownKindPath, $nulBeforeSpaceUnknownKindCompressed);
$nulBeforeSpaceUnknownKindStore = LooseObjectStore::fromObjectsDirectory($nulBeforeSpaceUnknownKindDirectory);
try {
    $nulBeforeSpaceUnknownKindStore->readHeader($fixture['nulBeforeSpaceUnknownKindOid']);
} catch (InvalidArgumentException $exception) {
    $nulBeforeSpaceUnknownKindHeaderRejected = true;
    $nulBeforeSpaceUnknownKindHeaderMessage = $exception->getMessage();
}
try {
    $nulBeforeSpaceUnknownKindStore->read($fixture['nulBeforeSpaceUnknownKindOid']);
} catch (InvalidArgumentException $exception) {
    $nulBeforeSpaceUnknownKindReadRejected = $exception->getMessage() === "Unknown object kind: blob\0";
}
try {
    $nulBeforeSpaceUnknownKindStore->verifyIntegrity();
} catch (RuntimeException $exception) {
    $nulBeforeSpaceUnknownKindIntegrityRejected = true;
    $nulBeforeSpaceUnknownKindIntegrityMessage = $exception->getMessage();
}

$unknownKindDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-unknown-kind-' . bin2hex(random_bytes(4)) . '/objects';
$unknownKindPath = $unknownKindDirectory . '/' . substr($fixture['unknownKindNoNulOid'], 0, 2) . '/' . substr($fixture['unknownKindNoNulOid'], 2);
if (!is_dir(dirname($unknownKindPath)) && !mkdir(dirname($unknownKindPath), 0777, true) && !is_dir(dirname($unknownKindPath))) {
    throw new RuntimeException('Unable to create object-header unknown-kind fixture directory');
}
$unknownKindCompressed = gzcompress($fixture['unknownKindNoNulStorage']);
if ($unknownKindCompressed === false) {
    throw new RuntimeException('Unable to compress object-header unknown-kind fixture');
}
file_put_contents($unknownKindPath, $unknownKindCompressed);
$unknownKindStore = LooseObjectStore::fromObjectsDirectory($unknownKindDirectory);
try {
    $unknownKindStore->readHeader($fixture['unknownKindNoNulOid']);
} catch (InvalidArgumentException $exception) {
    $unknownKindHeaderRejected = true;
    $unknownKindHeaderMessage = $exception->getMessage();
}
try {
    $unknownKindStore->read($fixture['unknownKindNoNulOid']);
} catch (InvalidArgumentException $exception) {
    $unknownKindReadRejected = $exception->getMessage() === 'Unknown object kind: wordpress';
}
try {
    $unknownKindStore->verifyIntegrity();
} catch (RuntimeException $exception) {
    $unknownKindIntegrityRejected = true;
    $unknownKindIntegrityMessage = $exception->getMessage();
}

$trailingObjectsDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-trailing-' . bin2hex(random_bytes(4)) . '/objects';
$trailingPath = $trailingObjectsDirectory . '/' . substr($object->oid(), 0, 2) . '/' . substr($object->oid(), 2);
if (!is_dir(dirname($trailingPath)) && !mkdir(dirname($trailingPath), 0777, true) && !is_dir(dirname($trailingPath))) {
    throw new RuntimeException('Unable to create object-header trailing-stream fixture directory');
}
$trailingPrimaryStream = gzcompress($storage);
$trailingIgnoredStream = gzcompress("blob 13\0stale payload");
if ($trailingPrimaryStream === false || $trailingIgnoredStream === false) {
    throw new RuntimeException('Unable to compress object-header trailing-stream fixture');
}
file_put_contents($trailingPath, $trailingPrimaryStream . $trailingIgnoredStream);
$trailingStore = LooseObjectStore::fromObjectsDirectory($trailingObjectsDirectory);
$trailingStreamIgnored = $trailingStore->read($object->oid())->body === $fixture['blockBlobBody'];
$trailingIntegrity = $trailingStore->verifyIntegrity();
$trailingStreamIntegrityVerified = $trailingIntegrity['numObjects'] === 1
    && $trailingIntegrity['verifiedObjectIds'] === [$object->oid()];

$lateSameStreamObject = new GitObject('blob', $fixture['lateSameStreamBody']);
$lateSameStreamDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-late-overrun-' . bin2hex(random_bytes(4)) . '/objects';
$lateSameStreamPath = $lateSameStreamDirectory . '/' . substr($lateSameStreamObject->oid(), 0, 2) . '/' . substr($lateSameStreamObject->oid(), 2);
if (!is_dir(dirname($lateSameStreamPath)) && !mkdir(dirname($lateSameStreamPath), 0777, true) && !is_dir(dirname($lateSameStreamPath))) {
    throw new RuntimeException('Unable to create object-header late-overrun fixture directory');
}
$lateSameStreamCompressed = gzcompress($lateSameStreamObject->storageBytes() . 'ignored late same-stream overrun');
if ($lateSameStreamCompressed === false) {
    throw new RuntimeException('Unable to compress object-header late-overrun fixture');
}
file_put_contents($lateSameStreamPath, $lateSameStreamCompressed);
$lateSameStreamStore = LooseObjectStore::fromObjectsDirectory($lateSameStreamDirectory);
$lateSameStreamOverrunIgnored = $lateSameStreamStore->read($lateSameStreamObject->oid())->body === $fixture['lateSameStreamBody'];
$lateSameStreamIntegrity = $lateSameStreamStore->verifyIntegrity();
$lateSameStreamIntegrityVerified = $lateSameStreamIntegrity['numObjects'] === 1
    && $lateSameStreamIntegrity['verifiedObjectIds'] === [$lateSameStreamObject->oid()];

$truncatedHeaderDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-truncated-' . bin2hex(random_bytes(4)) . '/objects';
$truncatedHeaderPath = $truncatedHeaderDirectory . '/' . substr($fixture['truncatedHeaderOid'], 0, 2) . '/' . substr($fixture['truncatedHeaderOid'], 2);
if (!is_dir(dirname($truncatedHeaderPath)) && !mkdir(dirname($truncatedHeaderPath), 0777, true) && !is_dir(dirname($truncatedHeaderPath))) {
    throw new RuntimeException('Unable to create object-header truncated-stream fixture directory');
}
file_put_contents(
    $truncatedHeaderPath,
    $truncatedBeforeHeaderWindowCompletes($fixture['truncatedHeaderStorage'])
);
$truncatedHeaderStore = LooseObjectStore::fromObjectsDirectory($truncatedHeaderDirectory);
try {
    $truncatedHeaderStore->readHeader($fixture['truncatedHeaderOid']);
} catch (RuntimeException $exception) {
    $truncatedHeaderInflateRejected = true;
    $truncatedHeaderMessage = $exception->getMessage();
}

$corruptHeaderDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-corrupt-window-' . bin2hex(random_bytes(4)) . '/objects';
$corruptHeaderOid = str_repeat('8', 40);
$corruptHeaderPath = $corruptHeaderDirectory . '/' . substr($corruptHeaderOid, 0, 2) . '/' . substr($corruptHeaderOid, 2);
if (!is_dir(dirname($corruptHeaderPath)) && !mkdir(dirname($corruptHeaderPath), 0777, true) && !is_dir(dirname($corruptHeaderPath))) {
    throw new RuntimeException('Unable to create object-header corrupt first-window fixture directory');
}
$corruptHeaderCompressed = gzcompress("blob 3\0abc");
if ($corruptHeaderCompressed === false) {
    throw new RuntimeException('Unable to compress object-header corrupt first-window fixture');
}
$corruptHeaderCompressed[strlen($corruptHeaderCompressed) - 1] = chr(ord($corruptHeaderCompressed[strlen($corruptHeaderCompressed) - 1]) ^ 0xff);
file_put_contents($corruptHeaderPath, $corruptHeaderCompressed);
$corruptHeaderStore = LooseObjectStore::fromObjectsDirectory($corruptHeaderDirectory);
try {
    $corruptHeaderStore->readHeader($corruptHeaderOid);
} catch (RuntimeException $exception) {
    $corruptFirstWindowHeaderRejected = true;
    $corruptFirstWindowHeaderMessage = $exception->getMessage();
}

$finalizedObjectsDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-finalized-' . bin2hex(random_bytes(4)) . '/objects';
$finalizedStore = LooseObjectStore::fromObjectsDirectory($finalizedObjectsDirectory);
$finalizedObject = new GitObject('blob', "Read-only WordPress export object\n");
$finalizedOid = $finalizedStore->write($finalizedObject);
$finalizedPath = $finalizedObjectsDirectory . '/' . substr($finalizedOid, 0, 2) . '/' . substr($finalizedOid, 2);
$finalizedBytes = (string) file_get_contents($finalizedPath);
$finalizedReadOnly = (fileperms($finalizedPath) & 0777) === 0444;
$finalizedExistingObjectPreserved = $finalizedStore->write($finalizedObject) === $finalizedOid
    && (string) file_get_contents($finalizedPath) === $finalizedBytes;

$interruptObjectsDirectory = sys_get_temp_dir() . '/port-libs-git-object-header-interrupt-' . bin2hex(random_bytes(4)) . '/objects';
$interruptStore = LooseObjectStore::fromObjectsDirectory($interruptObjectsDirectory);
$interruptStore->write(new GitObject('blob', $fixture['blockBlobBody']));
$interruptStore->write(new GitObject('blob', "Queued WordPress export block\n"));
try {
    $interruptStore->verifyIntegrity(static function (string $oid, int $verifiedCount) use (&$integrityInterruptChecks): bool {
        $integrityInterruptChecks = $verifiedCount;

        return true;
    });
} catch (RuntimeException $exception) {
    $integrityInterruptHandled = true;
    $integrityInterruptMessage = $exception->getMessage();
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
    'zeroPaddedSizeHeaderAccepted' => $zeroPaddedSizeObject->body === $fixture['blockBlobBody'],
    'zeroPaddedSizeCanonicalOid' => $zeroPaddedOid,
    'zeroPaddedSizeRawHeaderOid' => $zeroPaddedRawOid,
    'zeroPaddedSizeIntegrityVerified' => $zeroPaddedSizeIntegrityVerified,
    'zeroPaddedSizeNonCanonicalRejected' => $zeroPaddedSizeNonCanonicalRejected,
    'zeroPaddedSizeNonCanonicalMessage' => $zeroPaddedSizeNonCanonicalMessage,
    'negativeZeroSizeHeaderAccepted' => $negativeZeroSizeObject->body === $fixture['emptyBlobBody'],
    'negativeZeroSizeCanonicalOid' => $negativeZeroSizeObject->oid(),
    'negativeZeroSizeRawHeaderOid' => hash('sha1', $negativeZeroSizeStorage),
    'lfSizeHeaderRejected' => $lfSizeHeaderRejected,
    'lfSizeHeaderMessage' => $lfSizeHeaderMessage,
    'lfSizeReadRejected' => $lfSizeReadRejected,
    'lfSizeReadMessage' => $lfSizeReadMessage,
    'lfSizeIntegrityRejected' => $lfSizeIntegrityRejected,
    'lfSizeIntegrityMessage' => $lfSizeIntegrityMessage,
    'missingNulHeaderRejected' => $missingNulHeaderRejected,
    'missingNulHeaderMessage' => $missingNulHeaderMessage,
    'missingNulReadRejected' => $missingNulReadRejected,
    'missingNulIntegrityRejected' => $missingNulIntegrityRejected,
    'missingNulIntegrityMessage' => $missingNulIntegrityMessage,
    'noTypeSizeDelimiterHeaderRejected' => $noTypeSizeDelimiterHeaderRejected,
    'noTypeSizeDelimiterHeaderMessage' => $noTypeSizeDelimiterHeaderMessage,
    'noTypeSizeDelimiterReadRejected' => $noTypeSizeDelimiterReadRejected,
    'noTypeSizeDelimiterIntegrityRejected' => $noTypeSizeDelimiterIntegrityRejected,
    'noTypeSizeDelimiterIntegrityMessage' => $noTypeSizeDelimiterIntegrityMessage,
    'nulBeforeSpaceUnknownKindHeaderRejected' => $nulBeforeSpaceUnknownKindHeaderRejected,
    'nulBeforeSpaceUnknownKindHeaderMessage' => $nulBeforeSpaceUnknownKindHeaderMessage,
    'nulBeforeSpaceUnknownKindReadRejected' => $nulBeforeSpaceUnknownKindReadRejected,
    'nulBeforeSpaceUnknownKindIntegrityRejected' => $nulBeforeSpaceUnknownKindIntegrityRejected,
    'nulBeforeSpaceUnknownKindIntegrityMessage' => $nulBeforeSpaceUnknownKindIntegrityMessage,
    'unknownKindHeaderRejected' => $unknownKindHeaderRejected,
    'unknownKindHeaderMessage' => $unknownKindHeaderMessage,
    'unknownKindReadRejected' => $unknownKindReadRejected,
    'unknownKindIntegrityRejected' => $unknownKindIntegrityRejected,
    'unknownKindIntegrityMessage' => $unknownKindIntegrityMessage,
    'allocationLimitBytes' => $boundedStore->allocationLimitBytes(),
    'oversizedHeaderSize' => $oversizedHeader['size'],
    'allocationLimitRejected' => $allocationLimitRejected,
    'allocationLimitMessage' => $allocationLimitMessage,
    'trailingStreamIgnored' => $trailingStreamIgnored,
    'trailingStreamIntegrityVerified' => $trailingStreamIntegrityVerified,
    'lateSameStreamOverrunIgnored' => $lateSameStreamOverrunIgnored,
    'lateSameStreamIntegrityVerified' => $lateSameStreamIntegrityVerified,
    'truncatedHeaderInflateRejected' => $truncatedHeaderInflateRejected,
    'truncatedHeaderMessage' => $truncatedHeaderMessage,
    'corruptFirstWindowHeaderRejected' => $corruptFirstWindowHeaderRejected,
    'corruptFirstWindowHeaderMessage' => $corruptFirstWindowHeaderMessage,
    'finalizedReadOnly' => $finalizedReadOnly,
    'finalizedExistingObjectPreserved' => $finalizedExistingObjectPreserved,
    'integrityInterruptHandled' => $integrityInterruptHandled,
    'integrityInterruptChecks' => $integrityInterruptChecks,
    'integrityInterruptMessage' => $integrityInterruptMessage,
];
