<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
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
];
