<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackBuildResult;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;
use PortLibs\Gitoxide\ProtocolCapabilities;
use PortLibs\Gitoxide\PushCommand;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$readPacketSequence = static function (string $bytes): array {
    $payloads = [];
    $offset = 0;
    $length = strlen($bytes);
    while ($offset + 4 <= $length) {
        $size = hexdec(substr($bytes, $offset, 4));
        $offset += 4;
        if ($size === 0) {
            break;
        }
        $payloads[] = substr($bytes, $offset, $size - 4);
        $offset += $size - 4;
    }

    return [$payloads, substr($bytes, $offset)];
};

$buildDeploymentObjects = static function (): array {
    $blob = new GitObject('blob', "Post title: Native PHP pack builder\n\nPack bytes generated for a WordPress deployment push.\n");
    $tree = (new Tree([
        new TreeEntry('100644', 'wp-content-export.txt', $blob->oid()),
    ]))->toObject();
    $commit = new GitObject(
        'commit',
        "tree {$tree->oid()}\n"
        . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
        . "committer WordPress <wordpress@example.test> 1700000000 +0000\n\n"
        . "Deploy WordPress content from native PHP pack builder\n"
    );

    return [$commit, $tree, $blob];
};

$buildSimilarBlobs = static function (): array {
    $stable = '';
    for ($i = 0; $i < 48; $i++) {
        $stable .= hash('sha1', 'wordpress-export-row-' . $i) . "\n";
    }

    return [
        new GitObject('blob', "wp_posts export\n{$stable}post_status=draft\nchecksum=old\n"),
        new GitObject('blob', "wp_posts export\n{$stable}post_status=publish\nchecksum=new\n"),
    ];
};

return [
    'builds v2 pack data and index for native git objects' => static function (TestRunner $t) use ($buildDeploymentObjects): void {
        $objects = $buildDeploymentObjects();
        $result = PackBuilder::build($objects);
        $pack = PackData::fromBytes($result->packBytes());
        $index = PackIndex::fromBytes($result->indexBytes());

        $t->same(2, $pack->version());
        $t->same(3, $pack->count());
        $t->same(3, $index->count());
        $t->same($result->packChecksum(), $pack->verifyChecksum());
        $t->same($result->indexChecksum(), $index->verifyChecksum());
        $t->same($result->packChecksum(), $index->packChecksum());
        $t->same(12, $result->entries()[0]['offset']);

        foreach ($objects as $object) {
            $read = $pack->readObject($index, $object->oid());
            $t->same($object->type, $read->type);
            $t->same($object->body, $read->body);
        }
    },
    'builds empty packs for already-present create or update pushes' => static function (TestRunner $t): void {
        $result = PackBuilder::build([]);
        $pack = PackData::fromBytes($result->packBytes());
        $index = PackIndex::fromBytes($result->indexBytes());

        $t->same(0, $pack->count());
        $t->same(0, $index->count());
        $t->same($result->packChecksum(), $pack->verifyChecksum());
        $t->same($result->indexChecksum(), $index->verifyChecksum());
    },
    'encodes large non-delta objects with multi-byte entry headers' => static function (TestRunner $t): void {
        $object = new GitObject('blob', str_repeat('WordPress pack builder ', 12));
        $result = PackBuilder::build([$object]);
        $entry = PackData::fromBytes($result->packBytes())->entryAtOffset($result->entries()[0]['offset']);

        $t->same('blob', $entry->kind);
        $t->same(strlen($object->body), $entry->decompressedSize);
        $t->true($entry->headerSize > 1, 'large object entries should use a multi-byte size header');
        $t->same($object->body, $entry->data);
    },
    'builds ref-delta pack entries when a similar base is available' => static function (TestRunner $t) use ($buildSimilarBlobs): void {
        [$base, $target] = $buildSimilarBlobs();
        $result = PackBuilder::buildWithRefDeltas([$base, $target]);
        $entries = $result->entries();
        $pack = PackData::fromBytes($result->packBytes());
        $index = PackIndex::fromBytes($result->indexBytes());
        $deltaEntry = $pack->entryAtOffset($entries[1]['offset']);
        $read = $pack->readObject($index, $target->oid());

        $t->same(false, $result->isThin());
        $t->same(true, $result->hasDeltaEntries());
        $t->same('whole', $entries[0]['storage']);
        $t->same('ref-delta', $entries[1]['storage']);
        $t->same($base->oid(), $entries[1]['baseOid']);
        $t->same('ref-delta', $deltaEntry->kind);
        $t->same($base->oid(), $deltaEntry->baseObjectId);
        $t->same('blob', $read->type);
        $t->same($target->body, $read->body);
        $t->true(strlen($result->packBytes()) < strlen(PackBuilder::build([$base, $target])->packBytes()), 'delta pack should be smaller than the whole-object pack');
    },
    'builds ofs-delta pack entries against already written bases' => static function (TestRunner $t) use ($buildSimilarBlobs): void {
        [$base, $target] = $buildSimilarBlobs();
        $result = PackBuilder::buildWithOffsetDeltas([$base, $target]);
        $entries = $result->entries();
        $pack = PackData::fromBytes($result->packBytes());
        $index = PackIndex::fromBytes($result->indexBytes());
        $deltaEntry = $pack->entryAtOffset($entries[1]['offset']);
        $read = $pack->readObject($index, $target->oid());

        $t->same(false, $result->isThin());
        $t->same(true, $result->hasDeltaEntries());
        $t->same('whole', $entries[0]['storage']);
        $t->same('ofs-delta', $entries[1]['storage']);
        $t->same($base->oid(), $entries[1]['baseOid']);
        $t->same($entries[0]['offset'], $entries[1]['baseOffset']);
        $t->same($entries[1]['offset'] - $entries[0]['offset'], $entries[1]['baseDistance']);
        $t->same('ofs-delta', $deltaEntry->kind);
        $t->same($entries[1]['baseDistance'], $deltaEntry->baseDistance);
        $t->same('blob', $read->type);
        $t->same($target->body, $read->body);
        $t->true(strlen($result->packBytes()) < strlen(PackBuilder::build([$base, $target])->packBytes()), 'ofs-delta pack should be smaller than the whole-object pack');
    },
    'builds thin ref-delta packs against remote bases' => static function (TestRunner $t) use ($buildSimilarBlobs): void {
        [$base, $target] = $buildSimilarBlobs();
        $result = PackBuilder::buildWithRefDeltas([$target], [$base]);
        $entries = $result->entries();
        $pack = PackData::fromBytes($result->packBytes());
        $index = PackIndex::fromBytes($result->indexBytes());
        $entry = $pack->entryAtOffset($entries[0]['offset']);

        $t->same(1, $pack->count());
        $t->same(true, $result->isThin());
        $t->same(true, $result->hasDeltaEntries());
        $t->same('ref-delta', $entries[0]['storage']);
        $t->same($base->oid(), $entries[0]['baseOid']);
        $t->same('ref-delta', $entry->kind);
        $t->same($base->oid(), $entry->baseObjectId);
        $t->throws(RuntimeException::class, static fn () => $pack->readObject($index, $target->oid()));
    },
    'guards invalid pack builder inputs and result metadata' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => PackBuilder::build(['not an object']));
        $t->throws(InvalidArgumentException::class, static fn () => new PackBuildResult('bad', 'bad', 'nope', 'nope', []));
    },
    'push command can append a generated pack after update commands' => static function (TestRunner $t) use ($readPacketSequence): void {
        $object = new GitObject('blob', 'Generated WordPress object for receive-pack.');
        $pack = PackBuilder::build([$object]);
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0report-status-v2 side-band-64k object-format=sha1")['capabilities'];
        $command = PushCommand::create($capabilities, 'port-libs/0.1');
        $command->createRef($object->oid(), 'refs/heads/wp-pack');

        [$commands, $remaining] = $readPacketSequence($command->requestWithPack($pack));

        $t->same(1, count($commands));
        $t->same(true, str_contains($commands[0], 'refs/heads/wp-pack'));
        $t->same($pack->packBytes(), $remaining);
        $read = PackData::fromBytes($remaining)->readObject(PackIndex::fromBytes($pack->indexBytes()), $object->oid());
        $t->same($object->body, $read->body);
    },
    'wordpress fixture builds receive-pack request with native pack bytes' => static function (TestRunner $t) use ($readPacketSequence): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v1-push-pack.php';
        $pack = PackData::fromBytes($fixture['packBytes']);
        $index = PackIndex::fromBytes($fixture['indexBytes']);
        [$commands, $afterCommands] = $readPacketSequence($fixture['requestBytes']);
        [$options, $packBytes] = $readPacketSequence($afterCommands);

        $t->same(3, $pack->count());
        $t->same($fixture['packChecksum'], $pack->verifyChecksum());
        $t->same($fixture['commandLines'], $commands);
        $t->same(['ci.skip'], $options);
        $t->same($fixture['packBytes'], $packBytes);
        $commit = $pack->readObject($index, $fixture['newCommit']);
        $blob = $pack->readObject($index, $fixture['objects']['blob']);
        $t->same('commit', $commit->type);
        $t->contains('Deploy WordPress content', $commit->body);
        $t->contains('Pack bytes generated for a WordPress deployment push.', $blob->body);
    },
    'wordpress fixture builds compact in-pack offset deltas' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-send-pack-ofs-delta.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-send-pack-ofs-delta.php';
        $pack = PackData::fromBytes($fixture['packBytes']);
        $index = PackIndex::fromBytes($fixture['indexBytes']);
        $read = $pack->readObject($index, $fixture['objects']['updatedBlob']);

        $t->same(2, $pack->count());
        $t->same(false, $fixture['thin']);
        $t->same(1, count($fixture['offsetDeltaEntries']));
        $t->same('ofs-delta', $fixture['offsetDeltaEntries'][0]['storage']);
        $t->same($fixture['objects']['baseBlob'], $fixture['offsetDeltaEntries'][0]['baseOid']);
        $t->same($fixture['offsetDeltaEntries'][0]['baseDistance'], $summary['offsetDeltaDistance']);
        $t->same($fixture['packChecksum'], $pack->verifyChecksum());
        $t->same('blob', $read->type);
        $t->contains('post_status=publish', $read->body);
        $t->same($fixture['packChecksum'], $summary['packChecksum']);
        $t->same($fixture['updatedBlob'], $summary['updatedBlob']);
    },
];
