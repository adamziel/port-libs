<?php

declare(strict_types=1);

use PortLibs\Gitoxide\FetchAcknowledgement;
use PortLibs\Gitoxide\FetchCommand;
use PortLibs\Gitoxide\FetchResponse;
use PortLibs\Gitoxide\FetchShallowUpdate;
use PortLibs\Gitoxide\FetchWantedRef;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$delimiter = '0001';
$flush = '0000';

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
    'rejects malformed sideband-all response packets before section parsing' => static function (TestRunner $t) use ($packet): void {
        $t->throws(InvalidArgumentException::class, static fn () => FetchResponse::fromV2PacketLines($packet("\x09acknowledgments\n"), true));
        $t->throws(RuntimeException::class, static fn () => FetchResponse::fromV2PacketLines($packet("\x01ERR segmentation fault\n"), true));
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
        $t->same(['remote: preparing WordPress blobless pack', 'Enumerating objects: 1, done.'], $response->progressMessages());
    },
];
