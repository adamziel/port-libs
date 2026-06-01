<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;
use PortLibs\Gitoxide\PushRefStatus;
use PortLibs\Gitoxide\ReceivePackAdvertisement;
use PortLibs\Gitoxide\SendPackSession;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

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

$buildSimilarBlobs = static function (): array {
    $stable = '';
    for ($i = 0; $i < 48; $i++) {
        $stable .= hash('sha1', 'wordpress-thin-send-pack-row-' . $i) . "\n";
    }

    return [
        new GitObject('blob', "wp_posts export\n{$stable}post_status=draft\nchecksum=old\n"),
        new GitObject('blob', "wp_posts export\n{$stable}post_status=publish\nchecksum=new\n"),
    ];
};

return [
    'parses receive-pack v1 advertisement refs and capabilities' => static function (TestRunner $t) use ($packet, $flush): void {
        $main = '58F4F2BE1F149A49F7234F4BBD3B1B8C92A6D61A';
        $release = '7B333369DE1221F9BFBBE03A3A13E9A09BC1C907';
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$main} refs/heads/main\0report-status report-status-v2 side-band-64k object-format=sha1 atomic push-options\n")
            . $packet("{$release} refs/tags/wp-release\n")
            . $flush
        );

        $t->same(['report-status', 'report-status-v2', 'side-band-64k', 'object-format', 'atomic', 'push-options'], $advertisement->capabilities()->names());
        $t->same(strtolower($main), $advertisement->objectFor('refs/heads/main'));
        $t->same(strtolower($release), $advertisement->objectFor('refs/tags/wp-release'));
        $t->same(null, $advertisement->objectFor('refs/heads/missing'));
        $t->same('sha1', $advertisement->objectFormat());
        $t->same(2, count($advertisement->refs()));
    },
    'parses receive-pack v1 dummy capabilities symrefs peeled refs and shallow boundaries like gix handshake' => static function (TestRunner $t) use ($packet, $flush): void {
        $zero = str_repeat('0', 40);
        $head = '73A6868963993A3328E7D8FE94E5A6AC5078A944';
        $missing = '21C9B7500CB144B3169A6537961EC2B9E865BE81';
        $tag = 'DCE0EA858EEF7FF61AD345CC5CDAC62203FB3C10';
        $peeled = '21C9B7500CB144B3169A6537961EC2B9E865BE81';
        $shallow = '8E472F9CCC7D745927426CBB2D9D077DE545AA4E';
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$zero} capabilities^{}\0report-status side-band-64k shallow symref=HEAD:refs/heads/main symref=MISSING_NAMESPACE_TARGET:(null) object-format=sha1\n")
            . $packet("{$head} HEAD\n")
            . $packet("{$missing} MISSING_NAMESPACE_TARGET\n")
            . $packet("{$head} refs/heads/main\n")
            . $packet("{$tag} refs/tags/gix-commitgraph-v0.0.0\n")
            . $packet("{$peeled} refs/tags/gix-commitgraph-v0.0.0^{}\n")
            . $packet("shallow {$shallow}\n")
            . $flush
        );

        $refs = $advertisement->refs();
        $t->same(4, count($refs));
        $t->same('symbolic', $refs[0]->kind);
        $t->same('HEAD', $refs[0]->name);
        $t->same('refs/heads/main', $refs[0]->target);
        $t->same(strtolower($head), $refs[0]->object);
        $t->same('direct', $refs[1]->kind);
        $t->same('MISSING_NAMESPACE_TARGET', $refs[1]->name);
        $t->same(strtolower($missing), $refs[1]->object);
        $t->same('direct', $refs[2]->kind);
        $t->same('refs/heads/main', $refs[2]->name);
        $t->same('peeled', $refs[3]->kind);
        $t->same('refs/tags/gix-commitgraph-v0.0.0', $refs[3]->name);
        $t->same(strtolower($tag), $refs[3]->tag);
        $t->same(strtolower($peeled), $refs[3]->object);
        $t->same(strtolower($shallow), $advertisement->shallowUpdates()[0]->object);
        $t->same([], array_values(array_filter(
            array_map(static fn ($ref): string => $ref->name, $refs),
            static fn (string $name): bool => $name === 'capabilities^{}'
        )));
    },
    'parses sha256 receive-pack advertisements and delete-only requests' => static function (TestRunner $t) use ($packet, $flush, $readPacketSequence): void {
        $old = str_repeat('a', 64);
        $zero = str_repeat('0', 64);
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$old} refs/heads/main\0report-status-v2 object-format=sha256\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement, 'port-libs/sha256');

        $t->same('sha256', $advertisement->objectFormat());
        $t->same('sha256', $session->objectFormat());
        $t->same('sha256', $session->command()->objectFormat());
        $t->same(true, $session->delete('refs/heads/main'));

        $request = $session->buildRequest([]);
        [$commands, $remaining] = $readPacketSequence($request->requestBytes());

        $t->same(false, $request->hasPack());
        $t->same(["{$old} {$zero} refs/heads/main\0 report-status-v2 object-format=sha256 agent=port-libs/sha256"], $commands);
        $t->same('', $remaining);

        $oldStatus = str_repeat('c', 64);
        $newStatus = str_repeat('d', 64);
        $response = $session->parseReportStatusResponse(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-deploy\n")
            . $packet("option old-oid {$oldStatus} trailing\n")
            . $packet("option new-oid {$newStatus}\n")
            . $flush
        );
        $t->same($oldStatus, $response->refStatuses()[0]->oldObject);
        $t->same($newStatus, $response->refStatuses()[0]->newObject);
    },
    'guards malformed receive-pack advertisements' => static function (TestRunner $t) use ($packet, $flush): void {
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet("bad refs/heads/main\0report-status\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet(str_repeat('a', 40) . " refs/heads/main\0report-status\n") . $packet(str_repeat('b', 40) . " refs/heads/next\0atomic\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet(str_repeat('a', 40) . " main\0report-status\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet(str_repeat('a', 40) . " refs/heads/main\0report-status object-format=sha256\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet(str_repeat('a', 64) . " refs/heads/main\0report-status object-format=sha512\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet(str_repeat('a', 40) . " refs/heads/main\0report-status\n") . $packet('shallow bad') . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet(str_repeat('a', 40) . " refs/tags/missing^{}\0report-status\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ReceivePackAdvertisement::fromV1PacketLines($packet(str_repeat('a', 40) . " refs/heads/main\0report-status symref=HEAD\n") . $flush));
    },
    'send-pack session plans create update and no-op refs from advertisement' => static function (TestRunner $t) use ($packet, $flush, $readPacketSequence): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $newObject = new GitObject('blob', 'WordPress update payload');
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$old} refs/heads/main\0report-status-v2 side-band-64k object-format=sha1 atomic push-options\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement, 'port-libs/0.1');
        $session->command()->useAtomic();
        $session->command()->addPushOption('ci.skip');

        $t->same(true, $session->createOrUpdate('refs/heads/main', $newObject->oid()));
        $t->same(false, $session->createOrUpdate('refs/heads/main', $old));
        $t->same(true, $session->createOrUpdate('refs/tags/wp-release', $newObject->oid()));

        $request = $session->buildRequest([$newObject]);
        [$commands, $afterCommands] = $readPacketSequence($request->requestBytes());
        [$options, $packBytes] = $readPacketSequence($afterCommands);

        $t->same(true, $request->hasPack());
        $t->same("{$old} {$newObject->oid()} refs/heads/main\0 report-status-v2 side-band-64k object-format=sha1 agent=port-libs/0.1 atomic push-options", $commands[0]);
        $t->same('0000000000000000000000000000000000000000 ' . $newObject->oid() . ' refs/tags/wp-release', $commands[1]);
        $t->same(['ci.skip'], $options);
        $t->same($request->pack()?->packBytes(), $packBytes);
    },
    'send-pack session omits pack data for delete-only requests' => static function (TestRunner $t) use ($packet, $flush, $readPacketSequence): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$old} refs/heads/old-release\0report-status side-band-64k\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement);

        $t->same(true, $session->delete('refs/heads/old-release'));
        $t->same(false, $session->delete('refs/heads/missing'));

        $request = $session->buildRequest([]);
        [$commands, $remaining] = $readPacketSequence($request->requestBytes());

        $t->same(false, $request->hasPack());
        $t->same(["{$old} 0000000000000000000000000000000000000000 refs/heads/old-release\0 report-status side-band-64k"], $commands);
        $t->same('', $remaining);
        $t->throws(InvalidArgumentException::class, static fn () => $session->buildRequest([new GitObject('blob', 'unexpected')]));
    },
    'send-pack session sends an empty pack for update requests with no new objects' => static function (TestRunner $t) use ($packet, $flush, $readPacketSequence): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $new = '7b333369de1221f9bfbbe03a3a13e9a09bc1c907';
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$old} refs/heads/main\0report-status-v2 side-band-64k\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement);
        $session->createOrUpdate('refs/heads/main', $new);

        $request = $session->buildRequest([]);
        [, $packBytes] = $readPacketSequence($request->requestBytes());
        $pack = PackData::fromBytes($packBytes);

        $t->same(true, $request->hasPack());
        $t->same(0, $pack->count());
        $t->same($request->pack()?->packChecksum(), $pack->verifyChecksum());
    },
    'send-pack session guards sha256 update requests before sha1 pack generation' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = str_repeat('a', 64);
        $new = str_repeat('b', 64);
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$old} refs/heads/main\0report-status-v2 object-format=sha256\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement);

        $t->same(true, $session->createOrUpdate('refs/heads/main', $new));
        $t->throws(InvalidArgumentException::class, static fn () => $session->buildRequest([]));
    },
    'send-pack session parses receive-pack sideband status responses' => static function (TestRunner $t) use ($packet, $flush): void {
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet(str_repeat('a', 40) . " refs/heads/main\0report-status-v2 side-band-64k\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement);
        $response = $session->parseSidebandResponse(
            $packet("\x02Resolving deltas: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush
        );

        $t->same(true, $response->isSuccessful());
        $t->same(['Resolving deltas: 100% (1/1)'], $response->progressMessages());
        $t->same('refs/heads/main', $response->refStatuses()[0]->effectiveRefName());
    },
    'send-pack session parses report-status-v2 object options with negotiated object format' => static function (TestRunner $t) use ($packet, $flush): void {
        $oldObject = str_repeat('a', 40);
        $oldStatusPrefix = str_repeat('b', 40);
        $newStatusPrefix = str_repeat('c', 40);
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$oldObject} refs/heads/main\0report-status-v2 side-band-64k object-format=sha1\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement);
        $direct = $session->parseReportStatusResponse(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release accepted with hook object diagnostics\n")
            . $packet("option old-oid {$oldStatusPrefix}feed\n")
            . $packet("option new-oid {$newStatusPrefix}cafe\n")
            . $flush
        );
        $sideband = $session->parseSidebandResponse(
            $packet("\x02remote: proc-receive appended sha1 diagnostics\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/for/wp-release accepted with hook object diagnostics\n"))
            . $packet("\x01" . $packet("option old-oid {$oldStatusPrefix}feed\n"))
            . $packet("\x01" . $packet("option new-oid {$newStatusPrefix}cafe\n"))
            . $packet("\x01" . $flush)
            . $flush
        );

        $t->same(true, $direct->isSuccessful());
        $t->same('refs/for/wp-release', $direct->refStatuses()[0]->effectiveRefName());
        $t->same($oldStatusPrefix, $direct->refStatuses()[0]->oldObject);
        $t->same($newStatusPrefix, $direct->refStatuses()[0]->newObject);
        $t->same(true, $direct->refStatuses()[0]->hasReportOption());
        $t->same(true, $sideband->isSuccessful());
        $t->same(['remote: proc-receive appended sha1 diagnostics'], $sideband->progressMessages());
        $t->same($oldStatusPrefix, $sideband->refStatuses()[0]->oldObject);
        $t->same($newStatusPrefix, $sideband->refStatuses()[0]->newObject);
    },
    'send-pack session builds thin ref-delta requests from remote bases' => static function (TestRunner $t) use ($packet, $flush, $readPacketSequence, $buildSimilarBlobs): void {
        [$base, $target] = $buildSimilarBlobs();
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines(
            $packet("{$base->oid()} refs/heads/main\0report-status side-band-64k\n")
            . $flush
        );
        $session = SendPackSession::create($advertisement, 'port-libs/0.1');
        $session->createOrUpdate('refs/heads/main', $target->oid());

        $request = $session->buildThinRequest([$target], [$base]);
        [$commands, $packBytes] = $readPacketSequence($request->requestBytes());
        $entries = $request->pack()?->entries() ?? [];
        $pack = PackData::fromBytes($packBytes);
        $entry = $pack->entryAtOffset($entries[0]['offset']);

        $t->same(true, $request->hasPack());
        $t->same(true, $request->pack()?->isThin());
        $t->same("{$base->oid()} {$target->oid()} refs/heads/main\0 report-status side-band-64k agent=port-libs/0.1", $commands[0]);
        $t->same('ref-delta', $entries[0]['storage']);
        $t->same($base->oid(), $entry->baseObjectId);
    },
    'wordpress fixture orchestrates advertised refs generated pack request and status response' => static function (TestRunner $t) use ($readPacketSequence): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-send-pack-session.php';
        $advertisement = ReceivePackAdvertisement::fromV1PacketLines($fixture['advertisementBytes']);
        $response = SendPackSession::create($advertisement)->parseSidebandResponse($fixture['responseBytes']);
        [$commands, $afterCommands] = $readPacketSequence($fixture['requestBytes']);
        [$options, $packBytes] = $readPacketSequence($afterCommands);
        $pack = PackData::fromBytes($packBytes);
        $index = PackIndex::fromBytes($fixture['indexBytes']);
        $commit = $pack->readObject($index, $fixture['newCommit']);

        $t->same($fixture['oldCommit'], $advertisement->objectFor('refs/heads/main'));
        $t->same($fixture['commandLines'], $commands);
        $t->same(['ci.skip'], $options);
        $t->same(3, $pack->count());
        $t->same('commit', $commit->type);
        $t->contains('Deploy WordPress content', $commit->body);
        $t->same(true, $response->isSuccessful());
        $t->same($fixture['expectedRefs'], array_map(
            static fn (PushRefStatus $status): string => $status->effectiveRefName(),
            $response->refStatuses()
        ));
        $t->same($fixture['expectedStatusObjects']['oldObject'], $response->refStatuses()[0]->oldObject);
        $t->same($fixture['expectedStatusObjects']['newObject'], $response->refStatuses()[0]->newObject);
    },
    'wordpress fixture builds a thin ref-delta send-pack request' => static function (TestRunner $t) use ($readPacketSequence): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-send-pack-thin.php';
        [$commands, $packBytes] = $readPacketSequence($fixture['requestBytes']);
        $pack = PackData::fromBytes($packBytes);
        $blobEntry = null;
        foreach ($fixture['packEntries'] as $entry) {
            if ($entry['oid'] === $fixture['objects']['newBlob']) {
                $blobEntry = $entry;
                break;
            }
        }

        $t->same(true, $fixture['thin']);
        $t->same($fixture['commandLines'], $commands);
        $t->same(3, $pack->count());
        $t->true($blobEntry !== null, 'fixture should include the new WordPress blob');
        $t->same('ref-delta', $blobEntry['storage']);
        $t->same($fixture['objects']['oldBlob'], $blobEntry['baseOid']);
        $t->same($fixture['packChecksum'], $pack->verifyChecksum());
    },
];
