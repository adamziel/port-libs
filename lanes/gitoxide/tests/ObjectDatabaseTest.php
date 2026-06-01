<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\LooseReferenceStore;
use PortLibs\Gitoxide\ObjectDatabase;

$writeWordPressPackFixture = static function (): array {
    $fixture = require dirname(__DIR__) . '/fixtures/wordpress-pack-data.php';
    $gitDir = sys_get_temp_dir() . '/port-libs-git-odb-' . bin2hex(random_bytes(4)) . '/.git';
    $packDir = $gitDir . '/objects/pack';
    if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
        throw new RuntimeException("Unable to create pack fixture directory: {$packDir}");
    }

    $basename = 'pack-' . $fixture['packChecksum'];
    file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
    file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);

    return [$gitDir, $fixture];
};

$writePackFixtureToObjectsDirectory = static function (string $objectsDirectory): array {
    $fixture = require dirname(__DIR__) . '/fixtures/wordpress-pack-data.php';
    $packDir = $objectsDirectory . '/pack';
    if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
        throw new RuntimeException("Unable to create pack fixture directory: {$packDir}");
    }

    $basename = 'pack-' . $fixture['packChecksum'];
    file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
    file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);

    return $fixture;
};

$writeWordPressMultiPackFixture = static function (bool $omitMediaPack = false): array {
    $fixture = require dirname(__DIR__) . '/fixtures/wordpress-object-database-multi-pack.php';
    $gitDir = sys_get_temp_dir() . '/port-libs-git-odb-midx-' . bin2hex(random_bytes(4)) . '/.git';
    $packDir = $gitDir . '/objects/pack';
    if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
        throw new RuntimeException("Unable to create multi-pack fixture directory: {$packDir}");
    }

    foreach ($fixture['packs'] as $pack) {
        if ($omitMediaPack && $pack['indexName'] === 'pack-1b-media.idx') {
            continue;
        }
        file_put_contents($packDir . '/' . $pack['packName'], $pack['packBytes']);
        file_put_contents($packDir . '/' . $pack['indexName'], $pack['indexBytes']);
    }
    file_put_contents($packDir . '/multi-pack-index', $fixture['multiIndexBytes']);

    return [$gitDir, $fixture];
};

$writeWordPressSha256MultiPackFixture = static function (): array {
    $fixture = require dirname(__DIR__) . '/fixtures/wordpress-object-database-multi-pack-sha256.php';
    $gitDir = sys_get_temp_dir() . '/port-libs-git-odb-midx-sha256-' . bin2hex(random_bytes(4)) . '/.git';
    $packDir = $gitDir . '/objects/pack';
    if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
        throw new RuntimeException("Unable to create SHA-256 multi-pack fixture directory: {$packDir}");
    }

    foreach ($fixture['packs'] as $pack) {
        file_put_contents($packDir . '/' . $pack['packName'], $pack['packBytes']);
        file_put_contents($packDir . '/' . $pack['indexName'], $pack['indexBytes']);
    }
    file_put_contents($packDir . '/multi-pack-index', $fixture['multiIndexBytes']);

    return [$gitDir, $fixture];
};

$rewriteMultiPackIndexOffset = static function (string $bytes, string $oid, int $packIndex, int $packOffset): string {
    $readUInt64 = static function (string $data, int $offset): int {
        $parts = unpack('Nhigh/Nlow', substr($data, $offset, 8));

        return (int) $parts['high'] * 4294967296 + (int) $parts['low'];
    };

    if (substr($bytes, 0, 4) !== 'MIDX') {
        throw new RuntimeException('Test fixture did not contain a multi-pack-index');
    }

    $hashKind = ord($bytes[5]);
    $hash = match ($hashKind) {
        1 => ['name' => 'sha1', 'bytes' => 20],
        2 => ['name' => 'sha256', 'bytes' => 32],
        default => throw new RuntimeException('Unsupported test multi-pack-index hash kind'),
    };
    $chunkCount = ord($bytes[6]);
    $entries = [];
    $tableOffset = 12;
    for ($i = 0; $i <= $chunkCount; $i++) {
        $entries[] = [
            'id' => substr($bytes, $tableOffset, 4),
            'offset' => $readUInt64($bytes, $tableOffset + 4),
        ];
        $tableOffset += 12;
    }

    $chunks = [];
    for ($i = 0; $i < $chunkCount; $i++) {
        $chunks[$entries[$i]['id']] = [
            'start' => $entries[$i]['offset'],
            'end' => $entries[$i + 1]['offset'],
        ];
    }
    if (!isset($chunks['OIDL'], $chunks['OOFF'])) {
        throw new RuntimeException('Test multi-pack-index is missing object-id or offset chunks');
    }

    $objectCount = intdiv($chunks['OIDL']['end'] - $chunks['OIDL']['start'], $hash['bytes']);
    $entryIndex = null;
    for ($i = 0; $i < $objectCount; $i++) {
        $entryOid = bin2hex(substr($bytes, $chunks['OIDL']['start'] + $i * $hash['bytes'], $hash['bytes']));
        if ($entryOid === strtolower($oid)) {
            $entryIndex = $i;
            break;
        }
    }
    if ($entryIndex === null) {
        throw new RuntimeException("Test multi-pack-index did not contain object {$oid}");
    }

    $offsetEntry = $chunks['OOFF']['start'] + $entryIndex * 8;
    $bytes = substr_replace($bytes, pack('N2', $packIndex, $packOffset), $offsetEntry, 8);

    $checksumOffset = max(array_map(static fn (array $range): int => $range['end'], $chunks));
    $checksum = hex2bin(hash($hash['name'], substr($bytes, 0, $checksumOffset)));
    if ($checksum === false) {
        throw new RuntimeException('Unable to encode test multi-pack-index checksum');
    }

    return substr($bytes, 0, $checksumOffset) . $checksum;
};

$looseObjectPath = static fn (string $gitDir, string $oid): string => $gitDir . '/objects/' . substr($oid, 0, 2) . '/' . substr($oid, 2);
$writeCompressedLooseBytes = static function (string $gitDir, string $oid, string $bytes) use ($looseObjectPath): void {
    $path = $looseObjectPath($gitDir, $oid);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
        throw new RuntimeException("Unable to create loose object fixture directory: " . dirname($path));
    }
    $compressed = gzcompress($bytes);
    if ($compressed === false) {
        throw new RuntimeException('Unable to compress loose object fixture');
    }
    file_put_contents($path, $compressed);
};

return [
    'object database reads packed delta and loose objects' => static function (TestRunner $t) use ($writeWordPressPackFixture): void {
        [$gitDir, $fixture] = $writeWordPressPackFixture();
        $loose = new LooseObjectStore($gitDir);
        $looseOid = $loose->write(new GitObject('blob', 'Local WordPress draft'));

        $database = new ObjectDatabase($gitDir);
        $commit = $database->read($fixture['objects'][0]['oid']);
        $deltaBlob = $database->read($fixture['objects'][2]['oid']);
        $looseBlob = $database->read($looseOid);

        $t->same(3, $database->packedObjectCount());
        $t->same('commit', $commit->type);
        $t->contains('Import WordPress content', $commit->body);
        $t->same('blob', $deltaBlob->type);
        $t->contains('reconstructed packed edit', $deltaBlob->body);
        $t->same('Local WordPress draft', $looseBlob->body);
        $t->true($database->contains($fixture['objects'][1]['oid']));
        $t->true($database->contains($looseOid));
        $t->same(false, $database->contains(str_repeat('f', 40)));
    },
    'object database writes commit objects through the primary loose store like gix write object' => static function (TestRunner $t) use ($writeWordPressPackFixture, $looseObjectPath): void {
        [$gitDir, $fixture] = $writeWordPressPackFixture();
        $database = new ObjectDatabase($gitDir);
        $packedCommitBody = $fixture['objects'][0]['body'];
        $packedCommitOid = $fixture['objects'][0]['oid'];

        $t->same($packedCommitOid, $database->writeCommit(Commit::parse($packedCommitBody)));
        $t->same(false, is_file($looseObjectPath($gitDir, $packedCommitOid)));

        $commit = new Commit(
            'e90926b07092bccb7bf7da445fae6ffdfacf3eae',
            [$packedCommitOid],
            'WordPress Importer <importer@example.test> 1710000000 +0000',
            'WordPress Deploy Bot <deploy@example.test> 1710000300 +0000',
            "Publish regenerated block snapshot\n",
            [],
        );
        $object = $commit->object();
        $expectedOid = $object->oid();

        $t->same(false, $database->contains($expectedOid));
        $t->same($expectedOid, $database->writeCommit($commit));
        $t->true(is_file($looseObjectPath($gitDir, $expectedOid)));
        $t->same($object->storageBytes(), gzuncompress((string) file_get_contents($looseObjectPath($gitDir, $expectedOid))));
        $t->same('commit', $database->read($expectedOid)->type);
        $t->same($commit->storageBytes(), $database->read($expectedOid)->body);
        $t->same('Publish regenerated block snapshot', Commit::parse($database->read($expectedOid)->body)->messageSummary());
        $t->true($database->contains($expectedOid));
        $t->same($expectedOid, $database->write($object));
        $t->same($object->storageBytes(), gzuncompress((string) file_get_contents($looseObjectPath($gitDir, $expectedOid))));

        $idsByOffset = $database->objectIds(ObjectDatabase::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL);
        $t->same($expectedOid, $idsByOffset[array_key_last($idsByOffset)]);
    },
    'object database commit writes reject invalid writers before storage' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-odb-write-invalid-' . bin2hex(random_bytes(4)) . '/.git';
        $database = new ObjectDatabase($gitDir);
        $badCommit = new Commit(
            '0123456789abcdef0123456789abcdef01234567',
            [],
            'Bad < Actor <bad@example.test> 1710000000 +0000',
            'WordPress Deploy Bot <deploy@example.test> 1710000300 +0000',
            "Invalid actor should not create a loose object\n",
            [],
        );

        $t->throws(InvalidArgumentException::class, static fn () => $database->writeCommit($badCommit));
        $t->same(false, is_dir($gitDir . '/objects/01'));
        $t->same([], glob($gitDir . '/objects/[0-9a-f][0-9a-f]', GLOB_ONLYDIR) ?: []);
    },
    'object database iterates packs before loose objects with selectable ordering' => static function (TestRunner $t) use ($writeWordPressPackFixture): void {
        [$gitDir, $fixture] = $writeWordPressPackFixture();
        $looseOid = (new LooseObjectStore($gitDir))->write(new GitObject('blob', 'Loose object after packs'));
        $database = new ObjectDatabase($gitDir);

        $lexicographicalPackOids = array_column($fixture['objects'], 'oid');
        sort($lexicographicalPackOids, SORT_STRING);
        $t->same(
            $lexicographicalPackOids,
            array_slice($database->objectIds(), 0, 3)
        );
        $t->same($looseOid, $database->objectIds()[3]);
        $t->same(
            array_column($fixture['objects'], 'oid'),
            array_slice($database->objectIds(ObjectDatabase::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL), 0, 3)
        );
    },
    'object database lookup prefixes across packed and loose objects' => static function (TestRunner $t) use ($writeWordPressPackFixture): void {
        [$gitDir, $fixture] = $writeWordPressPackFixture();
        $loose = new LooseObjectStore($gitDir);
        $duplicatePackedOid = $loose->write(new GitObject('blob', $fixture['objects'][1]['body']));
        $ambiguousOid = $loose->write(new GitObject('blob', 'ambiguous-prefix-50976'));
        $database = new ObjectDatabase($gitDir);

        $found = $database->lookupPrefix(strtoupper(substr($fixture['objects'][1]['oid'], 0, 8)));
        $t->same('found', $found['status']);
        $t->same($duplicatePackedOid, $found['oid']);
        $t->same([
            'status' => 'found',
            'oid' => $duplicatePackedOid,
            'candidates' => [$duplicatePackedOid],
        ], $database->lookupPrefix(strtoupper(substr($fixture['objects'][1]['oid'], 0, 8)), true));

        $ambiguous = $database->lookupPrefix(substr($fixture['objects'][0]['oid'], 0, 4));
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([
            $fixture['objects'][0]['oid'],
            $ambiguousOid,
        ], $ambiguous['matches']);
        $ambiguousWithCandidates = $database->lookupPrefix(substr($fixture['objects'][0]['oid'], 0, 4), true);
        $t->same('ambiguous', $ambiguousWithCandidates['status']);
        $t->same([
            $fixture['objects'][0]['oid'],
            $ambiguousOid,
        ], $ambiguousWithCandidates['matches']);
        $t->same($ambiguousWithCandidates['matches'], $ambiguousWithCandidates['candidates']);

        $packedPrefix = $database->disambiguatePrefix(strtoupper($fixture['objects'][0]['oid']), 4);
        $t->true($packedPrefix !== null);
        $t->same(substr($fixture['objects'][0]['oid'], 0, strlen($packedPrefix)), $packedPrefix);
        $t->true(strlen($packedPrefix) > 4);
        $t->same(['status' => 'found', 'oid' => $fixture['objects'][0]['oid']], $database->lookupPrefix($packedPrefix));
        $t->same($duplicatePackedOid, $database->disambiguatePrefix($duplicatePackedOid, 40));
        $t->same(null, $database->disambiguatePrefix(str_repeat('f', 40), 4));
        $t->throws(InvalidArgumentException::class, static fn () => $database->disambiguatePrefix($fixture['objects'][0]['oid'], 3));

        $t->same('missing', $database->lookupPrefix('ffff')['status']);
        $t->same(['status' => 'missing', 'candidates' => []], $database->lookupPrefix('ffff', true));
    },
    'object database rejects incomplete pack pairs' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-pack-data.php';
        $gitDir = sys_get_temp_dir() . '/port-libs-git-odb-' . bin2hex(random_bytes(4)) . '/.git';
        $packDir = $gitDir . '/objects/pack';
        if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
            throw new RuntimeException("Unable to create pack fixture directory: {$packDir}");
        }
        file_put_contents($packDir . '/pack-missing-data.idx', $fixture['indexBytes']);

        $database = new ObjectDatabase($gitDir);
        $t->throws(RuntimeException::class, static fn () => $database->packedObjectCount());
    },
    'object database resolves loose and packed alternates' => static function (TestRunner $t) use ($writePackFixtureToObjectsDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/package-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }
        if (!mkdir($alternateObjectsDir, 0777, true) && !is_dir($alternateObjectsDir)) {
            throw new RuntimeException("Unable to create alternate objects directory: {$alternateObjectsDir}");
        }

        $fixture = $writePackFixtureToObjectsDirectory($alternateObjectsDir);
        $sharedOid = LooseObjectStore::fromObjectsDirectory($alternateObjectsDir)->write(new GitObject('blob', 'Shared plugin package from cache'));
        file_put_contents($objectsDir . '/info/alternates', "# shared package object database\n{$alternateObjectsDir}\n");

        $database = new ObjectDatabase($gitDir);
        $t->same([realpath($alternateObjectsDir)], $database->alternateObjectDirectories());
        $t->same(3, $database->packedObjectCount());
        $t->same('Shared plugin package from cache', $database->read($sharedOid)->body);
        $t->contains('reconstructed packed edit', $database->read($fixture['objects'][2]['oid'])->body);
        $t->true($database->contains($fixture['objects'][0]['oid']));
        $t->same('found', $database->lookupPrefix(substr($sharedOid, 0, 8))['status']);
    },
    'object database resolves relative quoted alternates and rejects cycles' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/cache with tab' . "\t" . '/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }
        if (!mkdir($alternateObjectsDir . '/info', 0777, true) && !is_dir($alternateObjectsDir . '/info')) {
            throw new RuntimeException("Unable to create alternate objects info directory: {$alternateObjectsDir}/info");
        }

        $alternateOid = LooseObjectStore::fromObjectsDirectory($alternateObjectsDir)->write(new GitObject('blob', 'Relative alternate object'));
        file_put_contents($objectsDir . '/info/alternates', "\"../../../cache with tab\\t/objects\"\n");
        $database = new ObjectDatabase($gitDir);
        $t->same('Relative alternate object', $database->read($alternateOid)->body);

        file_put_contents($alternateObjectsDir . '/info/alternates', "../../site/.git/objects\n");
        $cycleDatabase = new ObjectDatabase($gitDir);
        $t->throws(RuntimeException::class, static fn () => $cycleDatabase->alternateObjectDirectories());
    },
    'object database verifies loose object integrity across primary and alternate stores' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-integrity-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/shared-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }
        if (!mkdir($alternateObjectsDir, 0777, true) && !is_dir($alternateObjectsDir)) {
            throw new RuntimeException("Unable to create alternate objects directory: {$alternateObjectsDir}");
        }

        $primaryOid = (new LooseObjectStore($gitDir))->write(new GitObject('blob', 'Primary WordPress content object'));
        $alternateOid = LooseObjectStore::fromObjectsDirectory($alternateObjectsDir)->write(new GitObject('blob', 'Alternate package cache object'));
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        $integrity = (new ObjectDatabase($gitDir))->verifyLooseIntegrity();
        $t->same(2, count($integrity));
        $t->same([$objectsDir, realpath($alternateObjectsDir)], array_column($integrity, 'path'));
        $t->same([1, 1], array_map(static fn (array $row): int => $row['statistics']['numObjects'], $integrity));
        $t->same([$primaryOid], $integrity[0]['statistics']['verifiedObjectIds']);
        $t->same([$alternateOid], $integrity[1]['statistics']['verifiedObjectIds']);
    },
    'object database verifies sha256 loose object integrity across primary and alternate stores' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-sha256-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/shared-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }
        if (!mkdir($alternateObjectsDir, 0777, true) && !is_dir($alternateObjectsDir)) {
            throw new RuntimeException("Unable to create alternate objects directory: {$alternateObjectsDir}");
        }

        $primaryStore = new LooseObjectStore($gitDir, false, 'sha256');
        $alternateStore = LooseObjectStore::fromObjectsDirectory($alternateObjectsDir, 'sha256');
        $primaryOid = $primaryStore->write(new GitObject('blob', 'Primary WordPress SHA-256 content object'));
        $alternateOid = $alternateStore->write(new GitObject('blob', 'Alternate SHA-256 package cache object'));
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        $database = new ObjectDatabase($gitDir, objectHash: 'sha256');
        $writtenOid = $database->write(new GitObject('blob', 'New SHA-256 deployment object'));
        $t->same(64, strlen($primaryOid));
        $t->same(64, strlen($alternateOid));
        $t->same(64, strlen($writtenOid));
        $t->same(true, $database->contains(strtoupper($primaryOid)));
        $t->same('Primary WordPress SHA-256 content object', $database->read(strtoupper($primaryOid))->body);
        $t->same('Alternate SHA-256 package cache object', $database->read($alternateOid)->body);
        $t->same('blob', $database->readHeader($writtenOid)['type']);
        $t->same('loose', $database->readHeader($writtenOid)['source']);
        $t->same([
            'status' => 'found',
            'oid' => $alternateOid,
        ], $database->lookupPrefix(substr($alternateOid, 0, 48)));

        $integrity = $database->verifyLooseIntegrity();
        $expectedPrimary = [$primaryOid, $writtenOid];
        sort($expectedPrimary, SORT_STRING);
        $t->same([$objectsDir, realpath($alternateObjectsDir)], array_column($integrity, 'path'));
        $t->same([2, 1], array_map(static fn (array $row): int => $row['statistics']['numObjects'], $integrity));
        $t->same($expectedPrimary, $integrity[0]['statistics']['verifiedObjectIds']);
        $t->same([$alternateOid], $integrity[1]['statistics']['verifiedObjectIds']);
        $t->throws(InvalidArgumentException::class, static fn () => (new ObjectDatabase($gitDir))->contains($primaryOid));
        $t->throws(InvalidArgumentException::class, static fn () => $database->lookupPrefix(str_repeat('f', 65)));
    },
    'object database loose integrity rejects alternate object path directory blockers' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-directory-blocker-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/shared-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }

        $blockedOid = str_repeat('d', 40);
        $blockedPath = $alternateObjectsDir . '/' . substr($blockedOid, 0, 2) . '/' . substr($blockedOid, 2);
        if (!mkdir($blockedPath, 0777, true) && !is_dir($blockedPath)) {
            throw new RuntimeException('Unable to create alternate loose object directory blocker fixture');
        }
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        try {
            (new ObjectDatabase($gitDir))->verifyLooseIntegrity();
            throw new RuntimeException('Expected alternate loose object directory blocker to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$blockedOid} could not be read exactly", $exception->getMessage());
            $t->contains('Loose object path is not a regular file', $exception->getMessage());
        }
    },
    'object database loose integrity rejects nested alternate iterator candidates' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-nested-candidate-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/shared-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }

        $staleOid = str_repeat('3', 40);
        $nestedPath = $alternateObjectsDir . '/transient/' . substr($staleOid, 0, 2) . '/' . substr($staleOid, 2);
        if (!mkdir(dirname($nestedPath), 0777, true) && !is_dir(dirname($nestedPath))) {
            throw new RuntimeException('Unable to create nested alternate loose object candidate fixture');
        }
        file_put_contents($nestedPath, 'stale loose object candidate');
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        try {
            (new ObjectDatabase($gitDir))->verifyLooseIntegrity();
            throw new RuntimeException('Expected nested alternate loose object candidate to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$staleOid} could not be read exactly", $exception->getMessage());
            $t->contains('Loose object not found', $exception->getMessage());
        }
    },
    'object database loose integrity rejects empty primary and alternate object files' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-empty-loose-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/shared-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }

        $primaryOid = str_repeat('4', 40);
        $primaryPath = $objectsDir . '/' . substr($primaryOid, 0, 2) . '/' . substr($primaryOid, 2);
        if (!is_dir(dirname($primaryPath)) && !mkdir(dirname($primaryPath), 0777, true) && !is_dir(dirname($primaryPath))) {
            throw new RuntimeException('Unable to create primary empty loose object directory');
        }
        file_put_contents($primaryPath, '');

        $alternateOid = str_repeat('5', 40);
        $alternatePath = $alternateObjectsDir . '/' . substr($alternateOid, 0, 2) . '/' . substr($alternateOid, 2);
        if (!is_dir(dirname($alternatePath)) && !mkdir(dirname($alternatePath), 0777, true) && !is_dir(dirname($alternatePath))) {
            throw new RuntimeException('Unable to create alternate empty loose object directory');
        }
        file_put_contents($alternatePath, '');
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        $database = new ObjectDatabase($gitDir);
        $t->same(true, $database->contains($primaryOid));
        $t->same(true, $database->contains($alternateOid));

        try {
            $database->readHeader($primaryOid);
            throw new RuntimeException('Expected primary empty loose object header read to fail');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object file is empty: {$primaryOid}", $exception->getMessage());
        }

        unlink($primaryPath);
        try {
            $database->verifyLooseIntegrity();
            throw new RuntimeException('Expected alternate empty loose object to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$alternateOid} could not be read exactly", $exception->getMessage());
            $t->contains("Loose object file is empty: {$alternateOid}", $exception->getMessage());
        }
    },
    'object database loose integrity applies allocation limits to primary and alternate stores' => static function (TestRunner $t) use ($looseObjectPath): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-alloc-limit-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/shared-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }
        if (!mkdir($alternateObjectsDir, 0777, true) && !is_dir($alternateObjectsDir)) {
            throw new RuntimeException("Unable to create alternate objects directory: {$alternateObjectsDir}");
        }

        $primaryObject = new GitObject('blob', 'bounded primary body');
        $primaryOid = (new LooseObjectStore($gitDir))->write($primaryObject);
        $oversizedOid = str_repeat('2', 40);
        $oversizedPath = $looseObjectPath($root . '/shared-cache/.git', $oversizedOid);
        if (!is_dir(dirname($oversizedPath)) && !mkdir(dirname($oversizedPath), 0777, true) && !is_dir(dirname($oversizedPath))) {
            throw new RuntimeException('Unable to create oversized alternate loose object directory');
        }
        $compressed = gzcompress("blob 2048\0small");
        if ($compressed === false) {
            throw new RuntimeException('Unable to compress oversized alternate loose object fixture');
        }
        file_put_contents($oversizedPath, $compressed);
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        $database = new ObjectDatabase($gitDir, looseObjectAllocationLimitBytes: 32);
        try {
            $database->verifyLooseIntegrity();
            throw new RuntimeException('Expected alternate loose object allocation limit to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$oversizedOid} could not be read exactly", $exception->getMessage());
            $t->contains('Loose object declared size 2048 exceeds allocation limit 32 bytes', $exception->getMessage());
        }
        $t->same('bounded primary body', $database->read($primaryOid)->body);

        $t->throws(InvalidArgumentException::class, static fn () => new ObjectDatabase($gitDir, looseObjectAllocationLimitBytes: -1));
    },
    'object database loose integrity rejects inflated size mismatches before hash verification' => static function (TestRunner $t) use ($writeCompressedLooseBytes): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-size-mismatch-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateGitDir = $root . '/shared-cache/.git';
        $alternateObjectsDir = $alternateGitDir . '/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }

        $overrunOid = hash('sha1', "blob 3\0abc");
        $writeCompressedLooseBytes($gitDir, $overrunOid, "blob 3\0abcdef");
        $underrunOid = hash('sha1', "blob 6\0abcdef");
        $writeCompressedLooseBytes($alternateGitDir, $underrunOid, "blob 6\0abc");
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        $database = new ObjectDatabase($gitDir);
        $overrunHeader = $database->readHeader($overrunOid);
        $t->same('blob', $overrunHeader['type']);
        $t->same(3, $overrunHeader['size']);
        $t->same('loose', $overrunHeader['source']);
        try {
            $database->read($overrunOid);
            throw new RuntimeException('Expected overrun loose object to fail exact inflation');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object inflated size mismatch', $exception->getMessage());
            $t->contains('expected 10', $exception->getMessage());
            $t->contains('got 13', $exception->getMessage());
        }

        $underrunHeader = $database->readHeader($underrunOid);
        $t->same('blob', $underrunHeader['type']);
        $t->same(6, $underrunHeader['size']);
        $t->same('loose', $underrunHeader['source']);
        try {
            $database->read($underrunOid);
            throw new RuntimeException('Expected underrun loose object to fail exact inflation');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object inflated size mismatch', $exception->getMessage());
            $t->contains('expected 13', $exception->getMessage());
            $t->contains('got 10', $exception->getMessage());
        }

        try {
            $database->verifyLooseIntegrity();
            throw new RuntimeException('Expected loose object inflated size mismatch to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$overrunOid} could not be read exactly", $exception->getMessage());
            $t->contains('Loose object inflated size mismatch', $exception->getMessage());
        }
    },
    'object database loose integrity counts duplicate case-normalized candidates in primary and alternates' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/port-libs-git-odb-case-duplicate-' . bin2hex(random_bytes(4));
        $gitDir = $root . '/site/.git';
        $objectsDir = $gitDir . '/objects';
        $alternateObjectsDir = $root . '/shared-cache/.git/objects';
        if (!mkdir($objectsDir . '/info', 0777, true) && !is_dir($objectsDir . '/info')) {
            throw new RuntimeException("Unable to create objects info directory: {$objectsDir}/info");
        }
        if (!mkdir($alternateObjectsDir, 0777, true) && !is_dir($alternateObjectsDir)) {
            throw new RuntimeException("Unable to create alternate objects directory: {$alternateObjectsDir}");
        }

        $createMixedCaseObject = static function (string $label): GitObject {
            for ($i = 0; $i < 100; $i++) {
                $object = new GitObject('blob', "WordPress {$label} loose candidate {$i}");
                if (strtoupper($object->oid()) !== $object->oid()) {
                    return $object;
                }
            }

            throw new RuntimeException("Unable to create mixed-case {$label} loose object id fixture");
        };
        $writeCaseVariantCandidate = static function (string $objectsDirectory, string $oid): void {
            $caseVariant = strtoupper($oid);
            $path = $objectsDirectory . '/' . substr($caseVariant, 0, 2) . '/' . substr($caseVariant, 2);
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
                throw new RuntimeException("Unable to create case-variant loose object candidate directory: " . dirname($path));
            }
            file_put_contents($path, 'stale case-variant loose object candidate');
        };

        $primaryStore = new LooseObjectStore($gitDir);
        $primaryOid = $primaryStore->write($createMixedCaseObject('primary'));
        $writeCaseVariantCandidate($objectsDir, $primaryOid);
        $alternateStore = LooseObjectStore::fromObjectsDirectory($alternateObjectsDir);
        $alternateOid = $alternateStore->write($createMixedCaseObject('alternate'));
        $writeCaseVariantCandidate($alternateObjectsDir, $alternateOid);
        file_put_contents($objectsDir . '/info/alternates', "{$alternateObjectsDir}\n");

        $integrity = (new ObjectDatabase($gitDir))->verifyLooseIntegrity();
        $t->same([$objectsDir, realpath($alternateObjectsDir)], array_column($integrity, 'path'));
        $t->same([2, 2], array_map(static fn (array $row): int => $row['statistics']['numObjects'], $integrity));
        $t->same([$primaryOid, $primaryOid], $integrity[0]['statistics']['verifiedObjectIds']);
        $t->same([$alternateOid, $alternateOid], $integrity[1]['statistics']['verifiedObjectIds']);
    },
    'object database reads object headers across packed loose and replacement stores' => static function (TestRunner $t) use ($writeWordPressPackFixture): void {
        [$gitDir, $fixture] = $writeWordPressPackFixture();
        $loose = new LooseObjectStore($gitDir);
        $originalOid = $loose->write(new GitObject('blob', 'Draft block header'));
        $replacement = new GitObject('blob', 'Reviewed block header for publishing');
        $replacementOid = $loose->write($replacement);
        (new LooseReferenceStore($gitDir))->writeDirect('refs/replace/' . $originalOid, $replacementOid);

        $database = new ObjectDatabase($gitDir);
        $packedHeader = $database->readHeader(strtoupper($fixture['objects'][0]['oid']));
        $t->same('commit', $packedHeader['type']);
        $t->same(strlen($fixture['objects'][0]['body']), $packedHeader['size']);
        $t->same('pack', $packedHeader['source']);
        $packedDeltaHeader = $database->readHeader(strtoupper($fixture['objects'][2]['oid']));
        $t->same('blob', $packedDeltaHeader['type']);
        $t->same(strlen($fixture['objects'][2]['body']), $packedDeltaHeader['size']);
        $t->same('pack', $packedDeltaHeader['source']);

        $replacementHeader = $database->readHeader($originalOid);
        $t->same('blob', $replacementHeader['type']);
        $t->same(strlen($replacement->body), $replacementHeader['size']);
        $t->same('loose', $replacementHeader['source']);

        $ignoredHeader = $database->withReplacementsIgnored()->readHeader($originalOid);
        $t->same('blob', $ignoredHeader['type']);
        $t->same(strlen('Draft block header'), $ignoredHeader['size']);
        $t->same('loose', $ignoredHeader['source']);
        $t->throws(RuntimeException::class, static fn () => $database->readHeader(str_repeat('f', 40)));
    },
    'object database applies loose replacement refs and can ignore them' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-odb-' . bin2hex(random_bytes(4)) . '/.git';
        $loose = new LooseObjectStore($gitDir);
        $originalOid = $loose->write(new GitObject('blob', 'Published WordPress block'));
        $replacementOid = $loose->write(new GitObject('blob', 'Moderated WordPress block'));
        (new LooseReferenceStore($gitDir))->writeDirect('refs/replace/' . $originalOid, $replacementOid);

        $database = new ObjectDatabase($gitDir);
        $t->same('Moderated WordPress block', $database->read($originalOid)->body);
        $t->same('Published WordPress block', $database->withReplacementsIgnored()->read($originalOid)->body);
        $t->same([
            ['from' => $originalOid, 'to' => $replacementOid],
        ], $database->replacements());
    },
    'object database applies packed replacement refs and sorts replacement mappings' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-odb-' . bin2hex(random_bytes(4)) . '/.git';
        $loose = new LooseObjectStore($gitDir);
        $firstOriginal = $loose->write(new GitObject('blob', 'First original package metadata'));
        $firstReplacement = $loose->write(new GitObject('blob', 'First replaced package metadata'));
        $secondOriginal = $loose->write(new GitObject('blob', 'Second original package metadata'));
        $secondReplacement = $loose->write(new GitObject('blob', 'Second replaced package metadata'));
        if (!is_dir($gitDir) && !mkdir($gitDir, 0777, true) && !is_dir($gitDir)) {
            throw new RuntimeException("Unable to create git directory: {$gitDir}");
        }
        file_put_contents(
            $gitDir . '/packed-refs',
            "# pack-refs with: sorted\n"
                . "{$secondReplacement} refs/replace/{$secondOriginal}\n"
                . "{$firstReplacement} refs/replace/{$firstOriginal}\n"
        );

        $database = new ObjectDatabase($gitDir);
        $t->same('First replaced package metadata', $database->read($firstOriginal)->body);
        $t->same('Second replaced package metadata', $database->read($secondOriginal)->body);

        $expected = [
            ['from' => $firstOriginal, 'to' => $firstReplacement],
            ['from' => $secondOriginal, 'to' => $secondReplacement],
        ];
        usort($expected, static fn (array $a, array $b): int => strcmp($a['from'], $b['from']));
        $t->same($expected, $database->replacements());
    },
    'object database uses multi-pack-index for packed counts reads prefixes and iteration' => static function (TestRunner $t) use ($writeWordPressMultiPackFixture): void {
        [$gitDir, $fixture] = $writeWordPressMultiPackFixture();
        $database = new ObjectDatabase($gitDir);
        $rawPackIndexObjects = array_sum(array_map(static fn (array $pack): int => count($pack['objects']), $fixture['packs']));

        $t->same(4, $rawPackIndexObjects);
        $t->same(3, $database->packedObjectCount());
        $t->same(true, $database->contains($fixture['objectsByRole']['shared']['oid']));

        $content = $database->read($fixture['objectsByRole']['content']['oid']);
        $media = $database->read($fixture['objectsByRole']['media']['oid']);
        $shared = $database->read($fixture['objectsByRole']['shared']['oid']);
        $t->contains('wp_posts export chunk', $content->body);
        $t->contains('Large media attachment metadata', $media->body);
        $t->contains('Shared plugin package object', $shared->body);

        $expectedLexicographic = array_column($fixture['multiIndexObjects'], 'oid');
        $t->same($expectedLexicographic, $database->objectIds());
        $t->same([
            $fixture['objectsByRole']['content']['oid'],
            $fixture['objectsByRole']['shared']['oid'],
            $fixture['objectsByRole']['media']['oid'],
        ], $database->objectIds(ObjectDatabase::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL));

        $prefix = $database->lookupPrefix(strtoupper(substr($fixture['objectsByRole']['media']['oid'], 0, 8)));
        $t->same('found', $prefix['status']);
        $t->same($fixture['objectsByRole']['media']['oid'], $prefix['oid']);

        $shortestMediaPrefix = $database->disambiguatePrefix(strtoupper($fixture['objectsByRole']['media']['oid']), 4);
        $t->true($shortestMediaPrefix !== null);
        $t->same(substr($fixture['objectsByRole']['media']['oid'], 0, strlen($shortestMediaPrefix)), $shortestMediaPrefix);
        $t->same(['status' => 'found', 'oid' => $fixture['objectsByRole']['media']['oid']], $database->lookupPrefix($shortestMediaPrefix));
        $t->same(null, $database->disambiguatePrefix(str_repeat('f', 40), 4));

        $looseCandidateOid = (new LooseObjectStore($gitDir))->write(new GitObject('blob', 'midx-prefix-candidate-128814'));
        $contentPrefixCandidates = $database->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 4), true);
        $t->same('ambiguous', $contentPrefixCandidates['status']);
        $t->same([
            $fixture['objectsByRole']['content']['oid'],
            $looseCandidateOid,
        ], $contentPrefixCandidates['matches']);
        $t->same($contentPrefixCandidates['matches'], $contentPrefixCandidates['candidates']);

        $uniqueContentCandidates = $database->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 5), true);
        $t->same([
            'status' => 'found',
            'oid' => $fixture['objectsByRole']['content']['oid'],
            'candidates' => [$fixture['objectsByRole']['content']['oid']],
        ], $uniqueContentCandidates);
        $t->same(['status' => 'missing', 'candidates' => []], $database->lookupPrefix('ffff', true));
    },
    'object database rejects multi-pack-index entries that reference missing packs' => static function (TestRunner $t) use ($writeWordPressMultiPackFixture): void {
        [$gitDir] = $writeWordPressMultiPackFixture(true);
        $database = new ObjectDatabase($gitDir);

        $t->throws(RuntimeException::class, static fn () => $database->packedObjectCount());
    },
    'object database validates multi-pack-index object offsets against referenced pack indexes before prefix lookup' => static function (TestRunner $t) use ($writeWordPressMultiPackFixture, $rewriteMultiPackIndexOffset): void {
        [$gitDir, $fixture] = $writeWordPressMultiPackFixture();
        $media = $fixture['objectsByRole']['media'];
        $wrongOffset = $media['offset'] + 1;
        file_put_contents(
            $gitDir . '/objects/pack/multi-pack-index',
            $rewriteMultiPackIndexOffset($fixture['multiIndexBytes'], $media['oid'], $media['packIndex'], $wrongOffset)
        );

        $database = new ObjectDatabase($gitDir);
        try {
            $database->lookupPrefix(substr($media['oid'], 0, 8));
            throw new RuntimeException('Expected stale multi-pack-index object offset to be rejected');
        } catch (RuntimeException $exception) {
            $t->contains('Multi-pack-index object offset mismatch', $exception->getMessage());
            $t->contains($media['oid'], $exception->getMessage());
            $t->contains((string) $wrongOffset, $exception->getMessage());
            $t->contains((string) $media['offset'], $exception->getMessage());
        }
    },
    'object database uses sha256 multi-pack-index prefixes with matching store hash' => static function (TestRunner $t) use ($writeWordPressSha256MultiPackFixture): void {
        [$gitDir, $fixture] = $writeWordPressSha256MultiPackFixture();
        $database = new ObjectDatabase($gitDir, objectHash: 'sha256');
        $content = $fixture['objectsByRole']['content'];
        $media = $fixture['objectsByRole']['media'];

        $t->same(2, $database->packedObjectCount());
        $t->same(64, strlen($fixture['multiIndexChecksum']));
        $t->same(true, $database->contains(strtoupper($content['oid'])));

        $contentPrefix = $database->lookupPrefix(strtoupper(substr($content['oid'], 0, 12)), true);
        $t->same([
            'status' => 'found',
            'oid' => $content['oid'],
            'candidates' => [$content['oid']],
        ], $contentPrefix);

        $shortestMediaPrefix = $database->disambiguatePrefix(strtoupper($media['oid']), 4);
        $t->true($shortestMediaPrefix !== null);
        $t->same(substr($media['oid'], 0, strlen($shortestMediaPrefix)), $shortestMediaPrefix);
        $t->same(['status' => 'found', 'oid' => $media['oid']], $database->lookupPrefix($shortestMediaPrefix));
        $t->same(['status' => 'missing', 'candidates' => []], $database->lookupPrefix(str_repeat('f', 64), true));

        $contentObject = $database->read($content['oid']);
        $mediaHeader = $database->readHeader(strtoupper($media['oid']));
        $t->same($content['body'], $contentObject->body);
        $t->same($content['oid'], $contentObject->oid('sha256'));
        $t->same(['type' => 'blob', 'size' => strlen($media['body']), 'source' => 'pack'], $mediaHeader);
    },
    'wordpress object database multi-pack example verifies referenced pack offsets' => static function (TestRunner $t): void {
        $summary = require dirname(__DIR__) . '/examples/wordpress-object-database-multi-pack.php';

        $t->same(true, $summary['multiPackIndexOffsetsVerified']);
        $t->same('found', $summary['mediaPrefixStatus']);
        $t->same('ambiguous', $summary['contentPrefixCandidateStatus']);
        $t->same([
            $summary['contentOid'],
            $summary['loosePrefixCandidateOid'],
        ], $summary['contentPrefixCandidates']);
        $t->same(3, $summary['packedObjects']);
        $t->same(4, $summary['rawPackIndexObjects']);
    },
    'wordpress object database sha256 multi-pack example resolves prefixes without git binary' => static function (TestRunner $t): void {
        $summary = require dirname(__DIR__) . '/examples/wordpress-object-database-multi-pack-sha256.php';

        $t->same('sha256', $summary['objectHash']);
        $t->same(2, $summary['packedObjects']);
        $t->same(64, $summary['multiPackIndexChecksumLength']);
        $t->same(64, $summary['contentOidLength']);
        $t->same(true, $summary['contentReadable']);
        $t->same('found', $summary['mediaPrefixStatus']);
        $t->same(1, count($summary['mediaPrefixCandidates']));
        $t->same('pack', $summary['mediaHeader']['source']);
    },
    'wordpress object database example writes deployment commits through the database' => static function (TestRunner $t): void {
        $summary = require dirname(__DIR__) . '/examples/wordpress-object-database.php';

        $t->same(true, $summary['packedCommitWriteSkippedLoose']);
        $t->same(true, $summary['deploymentCommitStoredLoose']);
        $t->same('Publish regenerated block snapshot', $summary['deploymentCommitSummary']);
        $t->same(40, strlen($summary['deploymentCommitOid']));
        $t->same(40, strlen($summary['deploymentCommitParent']));
        $t->same($summary['deploymentCommitParent'], $summary['firstPackOffsetOid']);
        $t->same('commit', $summary['deploymentCommitHeaderType']);
        $t->same(true, $summary['deploymentCommitHeaderSize'] > 0);
        $t->same(true, $summary['replacementHeaderUsesReviewedDraft']);
        $t->same(2, $summary['looseIntegrityStores']);
        $t->same(4, $summary['looseIntegrityObjects']);
        $t->same(true, $summary['looseIntegrityVerifiedDeploymentCommit']);
        $t->same(true, $summary['looseIntegrityVerifiedSharedPackage']);
        $t->same(64, $summary['sha256LooseObjectOidLength']);
        $t->same(true, $summary['sha256LooseObjectReadable']);
        $t->same('loose', $summary['sha256LooseHeaderSource']);
        $t->same(4, $summary['sha256LooseIntegrityObjects']);
        $t->same(true, $summary['sha256LooseIntegrityVerified']);
        $t->same(64, $summary['sha256StructuredTreeEntryOidLength']);
        $t->same(true, $summary['sha256StructuredIntegrityVerified']);
        $t->same(true, $summary['looseIntegrityDirectoryBlockerRejected']);
        $t->same(true, $summary['looseIntegrityNestedCandidateRejected']);
        $t->same(true, $summary['looseIntegritySizeMismatchRejected']);
        $t->same(true, $summary['looseIntegrityEmptyFileRejected']);
        $t->same(true, $summary['looseIntegrityTraversalErrorIgnored']);
        $t->same(2, $summary['looseIntegrityCaseDuplicateCount']);
        $t->same([
            $summary['looseIntegrityCaseDuplicateVerifiedIds'][0],
            $summary['looseIntegrityCaseDuplicateVerifiedIds'][0],
        ], $summary['looseIntegrityCaseDuplicateVerifiedIds']);
    },
];
