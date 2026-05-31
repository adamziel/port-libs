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

$looseObjectPath = static fn (string $gitDir, string $oid): string => $gitDir . '/objects/' . substr($oid, 0, 2) . '/' . substr($oid, 2);

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

        $ambiguous = $database->lookupPrefix(substr($fixture['objects'][0]['oid'], 0, 4));
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([
            $fixture['objects'][0]['oid'],
            $ambiguousOid,
        ], $ambiguous['matches']);
        $t->same('missing', $database->lookupPrefix('ffff')['status']);
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
    },
    'object database rejects multi-pack-index entries that reference missing packs' => static function (TestRunner $t) use ($writeWordPressMultiPackFixture): void {
        [$gitDir] = $writeWordPressMultiPackFixture(true);
        $database = new ObjectDatabase($gitDir);

        $t->throws(RuntimeException::class, static fn () => $database->packedObjectCount());
    },
    'wordpress object database example writes deployment commits through the database' => static function (TestRunner $t): void {
        $summary = require dirname(__DIR__) . '/examples/wordpress-object-database.php';

        $t->same(true, $summary['packedCommitWriteSkippedLoose']);
        $t->same(true, $summary['deploymentCommitStoredLoose']);
        $t->same('Publish regenerated block snapshot', $summary['deploymentCommitSummary']);
        $t->same(40, strlen($summary['deploymentCommitOid']));
        $t->same(40, strlen($summary['deploymentCommitParent']));
        $t->same($summary['deploymentCommitParent'], $summary['firstPackOffsetOid']);
    },
];
