<?php

declare(strict_types=1);

use PortLibs\Gitoxide\FetchAcknowledgement;
use PortLibs\Gitoxide\FetchCommand;
use PortLibs\Gitoxide\FetchResponse;
use PortLibs\Gitoxide\FetchShallowUpdate;
use PortLibs\Gitoxide\FetchWantedRef;
use PortLibs\Gitoxide\RemoteProgress;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$delimiter = '0001';
$flush = '0000';
$invalidArgumentMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        return $error->getMessage();
    }

    throw new RuntimeException('Expected InvalidArgumentException was not thrown');
};

return [
    'parses fetch acknowledgement lines' => static function (TestRunner $t): void {
        $common = FetchAcknowledgement::fromLine("ACK FF333369DE1221F9BFBBE03A3A13E9A09BC1FFFF common\n");
        $readyFromAck = FetchAcknowledgement::fromLine("ACK ff333369de1221f9bfbbe03a3a13e9a09bc1ffff ready\n");
        $ready = FetchAcknowledgement::fromLine("ready\n");
        $nak = FetchAcknowledgement::fromLine("NAK\n");

        $t->same(FetchAcknowledgement::COMMON, $common->kind);
        $t->same('ff333369de1221f9bfbbe03a3a13e9a09bc1ffff', $common->id());
        $t->same(FetchAcknowledgement::READY, $readyFromAck->kind);
        $t->same(null, $readyFromAck->id());
        $t->same(FetchAcknowledgement::READY, $ready->kind);
        $t->same(FetchAcknowledgement::NAK, $nak->kind);
        $t->throws(InvalidArgumentException::class, static fn () => FetchAcknowledgement::fromLine('ACK not-an-object common'));
    },
    'parses shallow updates and wanted refs' => static function (TestRunner $t): void {
        $shallow = FetchShallowUpdate::fromLine('shallow 808e50d724f604f69ab93c6da2919c014667bedb');
        $unshallow = FetchShallowUpdate::fromLine('unshallow DFD0954DABEF3B64F458321EF15571CC1A46D552');
        $wanted = FetchWantedRef::fromLine('73a6868963993a3328e7d8fe94e5a6ac5078a944 refs/heads/main');

        $t->same(FetchShallowUpdate::SHALLOW, $shallow->kind);
        $t->same('808e50d724f604f69ab93c6da2919c014667bedb', $shallow->object);
        $t->same(FetchShallowUpdate::UNSHALLOW, $unshallow->kind);
        $t->same('dfd0954dabef3b64f458321ef15571cc1a46d552', $unshallow->object);
        $t->same('refs/heads/main', $wanted->path);
        $t->same('73a6868963993a3328e7d8fe94e5a6ac5078a944', $wanted->object);
        $t->throws(InvalidArgumentException::class, static fn () => FetchShallowUpdate::fromLine('deepen 1'));
        $t->throws(InvalidArgumentException::class, static fn () => FetchWantedRef::fromLine('refs/heads/main'));
    },
    'checks fetch response required features like gix-protocol' => static function (TestRunner $t): void {
        FetchResponse::checkRequiredFeatures(FetchCommand::PROTOCOL_V2, []);
        FetchResponse::checkRequiredFeatures(FetchCommand::PROTOCOL_V1, ['multi_ack_detailed', 'side-band-64k']);

        $t->throws(LogicException::class, static fn () => FetchResponse::checkRequiredFeatures(FetchCommand::PROTOCOL_V1, ['side-band-64k']));
        $t->throws(LogicException::class, static fn () => FetchResponse::checkRequiredFeatures(FetchCommand::PROTOCOL_V1, ['multi_ack_detailed']));
    },
    'parses protocol v2 response sections and sideband pack data' => static function (TestRunner $t) use ($packet, $delimiter, $flush): void {
        $pack = 'PACK' . pack('N', 2) . pack('N', 1) . 'payload';
        $bytes = $packet("acknowledgments\n")
            . $packet("ACK 190c3f6b2319c1f4ec854215533caf8623f8f870 common\n")
            . $packet("ready\n")
            . $delimiter
            . $packet("shallow-info\n")
            . $packet("unshallow 2d9d136fb0765f2e24c44a0f91984318d580d03b\n")
            . $delimiter
            . $packet("wanted-refs\n")
            . $packet("97c5a932b3940a09683e924ef6a92b31a6f7c6de refs/heads/main\n")
            . $delimiter
            . $packet("packfile\n")
            . $packet("\x02Enumerating objects: 1, done.\n")
            . $packet("\x01" . substr($pack, 0, 7))
            . $packet("\x01" . substr($pack, 7))
            . $flush;

        $response = FetchResponse::fromV2PacketLines($bytes);

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::UNSHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($pack, $response->packData());
        $t->same(['Enumerating objects: 1, done.'], $response->progressMessages());
        $t->same([], $response->errorMessages());
    },
    'parses protocol v2 acknowledgements without a pack section' => static function (TestRunner $t) use ($packet, $flush): void {
        $bytes = $packet("acknowledgments\n") . $packet("NAK\n") . $flush;
        $response = FetchResponse::fromV2PacketLines($bytes);

        $t->same(false, $response->hasPack());
        $t->same(FetchAcknowledgement::NAK, $response->acknowledgements()[0]->kind);
        $t->same('', $response->packData());
    },
    'rejects unknown protocol v2 response sections and invalid sidebands' => static function (TestRunner $t) use ($packet, $flush): void {
        $t->throws(InvalidArgumentException::class, static fn () => FetchResponse::fromV2PacketLines($packet("mystery\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => FetchResponse::fromV2PacketLines($packet("packfile\n") . $packet("\x09bad band") . $flush));
        $t->throws(RuntimeException::class, static fn () => FetchResponse::fromV2PacketLines($packet("ERR segmentation fault\n")));
    },
    'caps fetch response packet lines at the gix-packetline 64k maximum' => static function (TestRunner $t) use ($packet, $flush, $invalidArgumentMessage): void {
        $maxPacketLength = 65520;
        $maxSidebandPayload = $maxPacketLength - 4;
        $maxSidebandData = $maxSidebandPayload - 1;

        $maxHeader = sprintf('%04x', $maxPacketLength);
        $response = FetchResponse::fromV2PacketLines(
            $packet("packfile\n")
            . $maxHeader . "\x01" . str_repeat('x', $maxSidebandData)
            . $flush
        );

        $t->same(str_repeat('x', $maxSidebandData), $response->packData());
        $t->same($maxSidebandData, strlen($response->packData()));

        $tooLargeHeader = sprintf('%04x', $maxPacketLength + 1);
        $t->contains(
            'packet line exceeds maximum length',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("packfile\n")
                . $tooLargeHeader . "\x01" . str_repeat('x', $maxSidebandData + 1)
                . $flush
            ))
        );
    },
    'captures sideband progress and error channels without mixing them into pack data' => static function (TestRunner $t) use ($packet, $flush): void {
        $response = FetchResponse::fromV2PacketLines(
            $packet("packfile\n")
            . $packet("\x02Counting objects: 100% (1/1)\n")
            . $packet("\x03remote rejected a sideband\n")
            . $packet("\x01PACKtiny")
            . $flush
        );

        $t->same('PACKtiny', $response->packData());
        $t->same(['Counting objects: 100% (1/1)'], $response->progressMessages());
        $t->same(['remote rejected a sideband'], $response->errorMessages());
    },
    'parses protocol v2 sideband-all response sections before pack data' => static function (TestRunner $t) use ($packet, $delimiter, $flush): void {
        $pack = 'PACK' . pack('N', 2) . pack('N', 2) . 'sideband-all-pack';
        $bytes = $packet("\x02remote: preparing blobless pack\n")
            . $packet("\x01acknowledgments\n")
            . $packet("\x01ACK 190c3f6b2319c1f4ec854215533caf8623f8f870 common\n")
            . $packet("\x01ready\n")
            . $delimiter
            . $packet("\x01shallow-info\n")
            . $packet("\x01shallow 808e50d724f604f69ab93c6da2919c014667bedb\n")
            . $delimiter
            . $packet("\x01wanted-refs\n")
            . $packet("\x0197c5a932b3940a09683e924ef6a92b31a6f7c6de refs/heads/main\n")
            . $delimiter
            . $packet("\x01packfile\n")
            . $packet("\x01")
            . $packet("\x02Counting objects:  50% (1/2)\r")
            . $packet("\x03remote: warning: reused promisor pack\n")
            . $packet("\x01" . substr($pack, 0, 9))
            . $packet("\x02Counting objects: 100% (2/2)\n")
            . $packet("\x01" . substr($pack, 9))
            . $flush;

        $response = FetchResponse::fromV2PacketLines($bytes, true);

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($pack, $response->packData());
        $t->same([
            'remote: preparing blobless pack',
            "Counting objects:  50% (1/2)\r",
            'Counting objects: 100% (2/2)',
        ], $response->progressMessages());
        $t->same(['remote: warning: reused promisor pack'], $response->errorMessages());
    },
    'maps remote progress chunks like gix-protocol sideband readers' => static function (TestRunner $t): void {
        $counting = RemoteProgress::fromText("Counting objects:  25% (1/4)\rCounting objects:  50% (2/4)\r");
        $enumerating = RemoteProgress::fromText('Enumerating objects: 4, done.');

        $t->same('Counting objects', $counting?->action);
        $t->same(25, $counting?->percent);
        $t->same(1, $counting?->step);
        $t->same(4, $counting?->max);
        $t->same('Enumerating objects', $enumerating?->action);
        $t->same(null, $enumerating?->percent);
        $t->same(4, $enumerating?->step);
        $t->same(null, $enumerating?->max);
        $t->same(null, RemoteProgress::fromText('Total 4 (delta 0), reused 4 (delta 0), pack-reused 0'));
        $t->same(null, RemoteProgress::fromText('remote: preparing blobless pack'));
    },
    'parses upstream gix-protocol v2 sideband fixtures with pack trailers' => static function (TestRunner $t): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-sideband.php';

        foreach (['cloneOnlyWithKeepalive', 'cloneOnly2'] as $key) {
            $response = FetchResponse::fromV2PacketLines($fixtures[$key]['response']);

            $t->same(true, $response->hasPack());
            $t->same('PACK', substr($response->packData(), 0, 4));
            $t->same($fixtures[$key]['packBytes'], strlen($response->packData()));
            $t->same($fixtures[$key]['packTrailer'], bin2hex(substr($response->packData(), -20)));
            $t->same($fixtures[$key]['progressCount'], count($response->progressMessages()));
            $t->same([], $response->errorMessages());
            $t->same([], $response->acknowledgements());
            $t->same([], $response->shallowUpdates());
        }

        $keepaliveResponse = $fixtures['cloneOnlyWithKeepalive']['response'];
        $t->same(true, str_contains($keepaliveResponse, "0005\x01"));
        $t->same('150a1045f04dc0fc2dbf72313699fda696bf4126', bin2hex(substr(FetchResponse::fromV2PacketLines($keepaliveResponse)->packData(), -20)));
    },
    'exposes parsed progress from upstream sideband chunks' => static function (TestRunner $t): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-sideband.php';
        $response = FetchResponse::fromV2PacketLines($fixtures['cloneOnly2']['response']);
        $progress = $response->remoteProgress();

        $t->same(6, count($progress));
        $t->same('Enumerating objects', $progress[0]->action);
        $t->same(4, $progress[0]->step);
        $t->same(null, $progress[0]->max);
        $t->same('Counting objects', $progress[1]->action);
        $t->same(25, $progress[1]->percent);
        $t->same(1, $progress[1]->step);
        $t->same(4, $progress[1]->max);
        $t->same('Compressing objects', $progress[3]->action);
        $t->same(50, $progress[3]->percent);
        $t->same(1, $progress[3]->step);
        $t->same(2, $progress[3]->max);
    },
    'rejects malformed sideband-all response packets before section parsing' => static function (TestRunner $t) use ($packet): void {
        $t->throws(InvalidArgumentException::class, static fn () => FetchResponse::fromV2PacketLines($packet("\x09acknowledgments\n"), true));
        $t->throws(RuntimeException::class, static fn () => FetchResponse::fromV2PacketLines($packet("\x01ERR segmentation fault\n"), true));
        $t->same(
            [],
            FetchResponse::fromV2PacketLines($packet("\x03") . $packet("\x01acknowledgments\n") . $packet("\x01NAK\n") . '0000', true)->errorMessages()
        );
    },
    'wordpress fixture parses fetch response sections and sideband pack bytes' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-fetch-response.php';
        $response = FetchResponse::fromV2PacketLines($fixture['response'], $fixture['sidebandAll'] ?? false);

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same($fixture['objects']['installed'], $response->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same($fixture['objects']['main'], $response->shallowUpdates()[0]->object);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($fixture['objects']['main'], $response->wantedRefs()[0]->object);
        $t->same($fixture['packData'], $response->packData());
        $t->same(65520, $fixture['packetLineMaxBytes']);
        $t->same(['remote: preparing WordPress blobless pack', 'Enumerating objects: 1, done.'], $response->progressMessages());
    },
];
