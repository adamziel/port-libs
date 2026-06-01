<?php

declare(strict_types=1);

use PortLibs\Gitoxide\FetchAcknowledgement;
use PortLibs\Gitoxide\FetchCommand;
use PortLibs\Gitoxide\FetchResponse;
use PortLibs\Gitoxide\FetchShallowUpdate;
use PortLibs\Gitoxide\FetchWantedRef;
use PortLibs\Gitoxide\ProtocolV2FetchExchange;
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
$runtimeMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (RuntimeException $error) {
        return $error->getMessage();
    }

    throw new RuntimeException('Expected RuntimeException was not thrown');
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
        $t->same('flush', $response->terminator());
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
        $t->same('flush', $response->terminator());
        $t->same(FetchAcknowledgement::NAK, $response->acknowledgements()[0]->kind);
        $t->same('', $response->packData());
    },
    'rejects unknown protocol v2 response sections and invalid sidebands' => static function (TestRunner $t) use ($packet, $flush): void {
        $t->throws(InvalidArgumentException::class, static fn () => FetchResponse::fromV2PacketLines($packet("mystery\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => FetchResponse::fromV2PacketLines($packet("packfile\n") . $packet("\x09bad band") . $flush));
        $t->throws(RuntimeException::class, static fn () => FetchResponse::fromV2PacketLines($packet("ERR segmentation fault\n")));
    },
    'rejects empty packet lines before sideband decoding like gix-packetline' => static function (TestRunner $t) use ($packet, $flush, $invalidArgumentMessage): void {
        $t->contains(
            'fetch response: empty packet line',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines('0004'))
        );
        $t->contains(
            'fetch response: empty packet line',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines($packet("packfile\n") . '0004' . $flush))
        );
        $t->contains(
            'fetch response: empty packet line',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines('0004', true))
        );
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

        $keepalive = FetchResponse::fromV2PacketLines(
            $packet("packfile\n")
            . $packet("\x03")
            . $packet("\x01PACKtiny")
            . $flush
        );
        $t->same('PACKtiny', $keepalive->packData());
        $t->same([], $keepalive->errorMessages());
    },
    'passes sideband progress and errors to caller handlers like gix-packetline readers' => static function (TestRunner $t) use ($packet, $flush, $runtimeMessage): void {
        $calls = [];
        $response = FetchResponse::fromV2PacketLines(
            $packet("packfile\n")
            . $packet("\x02Counting objects: 100% (1/1)\n")
            . $packet("\x03remote: retained warning\n")
            . $packet("\x01PACKhandler")
            . $flush,
            false,
            static function (bool $isError, string $text) use (&$calls): bool {
                $calls[] = [$isError, $text];

                return true;
            }
        );

        $t->same('PACKhandler', $response->packData());
        $t->same([
            [false, 'Counting objects: 100% (1/1)'],
            [true, 'remote: retained warning'],
        ], $calls);
        $t->same(['Counting objects: 100% (1/1)'], $response->progressMessages());
        $t->same(['remote: retained warning'], $response->errorMessages());

        $emptyErrorCalls = [];
        $emptyError = FetchResponse::fromV2PacketLines(
            $packet("packfile\n")
            . $packet("\x03")
            . $packet("\x01PACKempty-error")
            . $flush,
            false,
            static function (bool $isError, string $text) use (&$emptyErrorCalls): bool {
                $emptyErrorCalls[] = [$isError, $text];

                return true;
            }
        );

        $t->same('PACKempty-error', $emptyError->packData());
        $t->same([], $emptyError->errorMessages());
        $t->same([[true, '']], $emptyErrorCalls);

        $abortedCalls = [];
        $t->same(
            'fetch response: interrupted by user',
            $runtimeMessage(static function () use ($packet, $flush, &$abortedCalls): void {
                FetchResponse::fromV2PacketLines(
                    $packet("packfile\n")
                    . $packet("\x02Resolving deltas:  50% (1/2)\r")
                    . $packet("\x01PACKhandler")
                    . $flush,
                    false,
                    static function (bool $isError, string $text) use (&$abortedCalls): bool {
                        $abortedCalls[] = [$isError, $text];

                        return false;
                    }
                );
            })
        );
        $t->same([[false, "Resolving deltas:  50% (1/2)\r"]], $abortedCalls);

        $sidebandAllCalls = [];
        $sidebandAll = FetchResponse::fromV2PacketLines(
            $packet("\x02remote: negotiating sideband-all\n")
            . $packet("\x01acknowledgments\n")
            . $packet("\x01NAK\n")
            . $flush,
            true,
            static function (bool $isError, string $text) use (&$sidebandAllCalls): bool {
                $sidebandAllCalls[] = [$isError, $text];

                return true;
            }
        );

        $t->same(false, $sidebandAll->hasPack());
        $t->same(FetchAcknowledgement::NAK, $sidebandAll->acknowledgements()[0]->kind);
        $t->same([[false, 'remote: negotiating sideband-all']], $sidebandAllCalls);

        $sidebandAllEmptyErrorCalls = [];
        $sidebandAllEmptyError = FetchResponse::fromV2PacketLines(
            $packet("\x03")
            . $packet("\x01packfile\n")
            . $packet("\x01PACKsideband-all-empty-error")
            . $flush,
            true,
            static function (bool $isError, string $text) use (&$sidebandAllEmptyErrorCalls): bool {
                $sidebandAllEmptyErrorCalls[] = [$isError, $text];

                return true;
            }
        );

        $t->same('PACKsideband-all-empty-error', $sidebandAllEmptyError->packData());
        $t->same([], $sidebandAllEmptyError->errorMessages());
        $t->same([[true, '']], $sidebandAllEmptyErrorCalls);

        $emptyErrorAbortCalls = [];
        $t->same(
            'fetch response: interrupted by user',
            $runtimeMessage(static function () use ($packet, $flush, &$emptyErrorAbortCalls): void {
                FetchResponse::fromV2PacketLines(
                    $packet("\x03")
                    . $packet("\x01packfile\n")
                    . $packet("\x01PACKsideband-all-empty-error")
                    . $flush,
                    true,
                    static function (bool $isError, string $text) use (&$emptyErrorAbortCalls): bool {
                        $emptyErrorAbortCalls[] = [$isError, $text];

                        return false;
                    }
                );
            })
        );
        $t->same([[true, '']], $emptyErrorAbortCalls);

        $body = $packet("packfile\n")
            . $packet("\x02Enumerating objects: 1, done.\n")
            . $packet("\x01PACKhttp")
            . $flush;
        $httpCalls = [];
        $smartHttp = FetchResponse::fromSmartHttpUploadPackResult(
            "HTTP/1.1 200 OK\r\n"
            . "Content-Type: application/x-git-upload-pack-result\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n"
            . "\r\n"
            . $body,
            false,
            static function (bool $isError, string $text) use (&$httpCalls): bool {
                $httpCalls[] = [$isError, $text];

                return true;
            }
        );

        $t->same('PACKhttp', $smartHttp->packData());
        $t->same([[false, 'Enumerating objects: 1, done.']], $httpCalls);
    },
    'rejects truncated fetch response sections and sideband streams before flush' => static function (TestRunner $t) use ($packet, $runtimeMessage): void {
        $t->contains(
            'fetch response: missing section terminator',
            $runtimeMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("acknowledgments\n")
                . $packet("NAK\n")
            ))
        );
        $t->contains(
            'fetch response: missing sideband flush packet',
            $runtimeMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("packfile\n")
                . $packet("\x02Counting objects: 100% (1/1)\r")
                . $packet("\x01PACKtiny")
            ))
        );
        $t->contains(
            'fetch response: missing sideband flush packet',
            $runtimeMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("\x02remote: preparing pack\n")
                . $packet("\x01packfile\n")
                . $packet("\x01PACKtiny"),
                true
            ))
        );
    },
    'accepts protocol v2 response-end terminators after sections and sideband packs' => static function (TestRunner $t) use ($packet, $delimiter): void {
        $pack = 'PACK' . pack('N', 2) . pack('N', 1) . 'response-end-pack';

        $noPack = FetchResponse::fromV2PacketLines(
            $packet("acknowledgments\n")
            . $packet("NAK\n")
            . '0002'
        );

        $t->same(false, $noPack->hasPack());
        $t->same('response-end', $noPack->terminator());
        $t->same(FetchAcknowledgement::NAK, $noPack->acknowledgements()[0]->kind);
        $t->same('', $noPack->packData());
        $t->same([], $noPack->progressMessages());

        $withPack = FetchResponse::fromV2PacketLines(
            $packet("acknowledgments\n")
            . $packet("ACK 190c3f6b2319c1f4ec854215533caf8623f8f870\n")
            . $delimiter
            . $packet("packfile\n")
            . $packet("\x02Counting objects: 100% (1/1)\n")
            . $packet("\x01" . $pack)
            . '0002'
        );

        $t->same(true, $withPack->hasPack());
        $t->same('response-end', $withPack->terminator());
        $t->same(FetchAcknowledgement::COMMON, $withPack->acknowledgements()[0]->kind);
        $t->same($pack, $withPack->packData());
        $t->same(['Counting objects: 100% (1/1)'], $withPack->progressMessages());
        $t->same([], $withPack->errorMessages());

        $sidebandAllCalls = [];
        $sidebandAll = FetchResponse::fromV2PacketLines(
            $packet("\x02remote: response-end aware negotiation\n")
            . $packet("\x01packfile\n")
            . $packet("\x03remote: deployment warning before pack\n")
            . $packet("\x01" . $pack)
            . '0002',
            true,
            static function (bool $isError, string $text) use (&$sidebandAllCalls): bool {
                $sidebandAllCalls[] = [$isError, $text];

                return true;
            }
        );

        $t->same(true, $sidebandAll->hasPack());
        $t->same('response-end', $sidebandAll->terminator());
        $t->same($pack, $sidebandAll->packData());
        $t->same(['remote: response-end aware negotiation'], $sidebandAll->progressMessages());
        $t->same(['remote: deployment warning before pack'], $sidebandAll->errorMessages());
        $t->same([
            [false, 'remote: response-end aware negotiation'],
            [true, 'remote: deployment warning before pack'],
        ], $sidebandAllCalls);
    },
    'preserves protocol v2 delimiter sideband terminators like gix packetline stopped-at state' => static function (TestRunner $t) use ($packet, $delimiter): void {
        $pack = 'PACK' . pack('N', 2) . pack('N', 1) . 'delimiter-pack';

        $withPack = FetchResponse::fromV2PacketLines(
            $packet("packfile\n")
            . $packet("\x02Counting objects: 100% (1/1)\n")
            . $packet("\x01" . $pack)
            . $delimiter
        );

        $t->same(true, $withPack->hasPack());
        $t->same('delimiter', $withPack->terminator());
        $t->same($pack, $withPack->packData());
        $t->same(['Counting objects: 100% (1/1)'], $withPack->progressMessages());
        $t->same([], $withPack->errorMessages());

        $sidebandAllCalls = [];
        $sidebandAll = FetchResponse::fromV2PacketLines(
            $packet("\x02remote: delimiter-aware negotiation\n")
            . $packet("\x01packfile\n")
            . $packet("\x01")
            . $packet("\x01" . substr($pack, 0, 8))
            . $packet("\x03remote: delimiter warning\n")
            . $packet("\x01" . substr($pack, 8))
            . $delimiter,
            true,
            static function (bool $isError, string $text) use (&$sidebandAllCalls): bool {
                $sidebandAllCalls[] = [$isError, $text];

                return true;
            }
        );

        $t->same(true, $sidebandAll->hasPack());
        $t->same('delimiter', $sidebandAll->terminator());
        $t->same($pack, $sidebandAll->packData());
        $t->same(['remote: delimiter-aware negotiation'], $sidebandAll->progressMessages());
        $t->same(['remote: delimiter warning'], $sidebandAll->errorMessages());
        $t->same([
            [false, 'remote: delimiter-aware negotiation'],
            [true, 'remote: delimiter warning'],
        ], $sidebandAllCalls);
    },
    'exposes protocol v2 fetch response consumed bytes like gix streaming readers' => static function (TestRunner $t) use ($packet, $delimiter, $flush): void {
        $first = $packet("packfile\n")
            . $packet("\x02Counting objects: 100% (1/1)\n")
            . $packet("\x01PACKcursor")
            . $flush;
        $second = $packet("acknowledgments\n")
            . $packet("NAK\n")
            . $flush;
        $firstResponse = FetchResponse::fromV2PacketLines($first . $second);

        $t->same(true, $firstResponse->hasPack());
        $t->same('PACKcursor', $firstResponse->packData());
        $t->same('flush', $firstResponse->terminator());
        $t->same(strlen($first), $firstResponse->consumedBytes());
        $t->same($second, substr($first . $second, $firstResponse->consumedBytes()));

        $secondResponse = FetchResponse::fromV2PacketLines(substr($first . $second, $firstResponse->consumedBytes()));
        $t->same(false, $secondResponse->hasPack());
        $t->same('flush', $secondResponse->terminator());
        $t->same(strlen($second), $secondResponse->consumedBytes());
        $t->same(FetchAcknowledgement::NAK, $secondResponse->acknowledgements()[0]->kind);

        $sidebandAllFirst = $packet("\x02remote: cursor progress\n")
            . $packet("\x01packfile\n")
            . $packet("\x01PACKsideband-all-cursor")
            . $delimiter;
        $sidebandAllSecond = $packet("\x01acknowledgments\n")
            . $packet("\x01NAK\n")
            . $flush;
        $calls = [];
        $sidebandAllResponse = FetchResponse::fromV2PacketLines(
            $sidebandAllFirst . $sidebandAllSecond,
            true,
            static function (bool $isError, string $text) use (&$calls): bool {
                $calls[] = [$isError, $text];

                return true;
            }
        );

        $t->same(true, $sidebandAllResponse->hasPack());
        $t->same('PACKsideband-all-cursor', $sidebandAllResponse->packData());
        $t->same('delimiter', $sidebandAllResponse->terminator());
        $t->same(strlen($sidebandAllFirst), $sidebandAllResponse->consumedBytes());
        $t->same([[false, 'remote: cursor progress']], $calls);
        $t->same($sidebandAllSecond, substr($sidebandAllFirst . $sidebandAllSecond, $sidebandAllResponse->consumedBytes()));

        $sidebandAllSecondResponse = FetchResponse::fromV2PacketLines(
            substr($sidebandAllFirst . $sidebandAllSecond, $sidebandAllResponse->consumedBytes()),
            true
        );
        $t->same(false, $sidebandAllSecondResponse->hasPack());
        $t->same(FetchAcknowledgement::NAK, $sidebandAllSecondResponse->acknowledgements()[0]->kind);
        $t->same(strlen($sidebandAllSecond), $sidebandAllSecondResponse->consumedBytes());
    },
    'surfaces raw upload-pack ERR packets before sideband decoding' => static function (TestRunner $t) use ($packet, $flush, $runtimeMessage): void {
        $t->contains(
            'fetch response: upload-pack error backend died',
            rtrim($runtimeMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("packfile\n")
                . $packet("ERR backend died\n")
                . $flush
            )))
        );
        $t->contains(
            'fetch response: upload-pack error sideband-all negotiation died',
            rtrim($runtimeMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("ERR sideband-all negotiation died\n"),
                true
            )))
        );
        $t->contains(
            'fetch response: upload-pack error sideband-all pack died',
            rtrim($runtimeMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("\x01packfile\n")
                . $packet("ERR sideband-all pack died\n")
                . $flush,
                true
            )))
        );

        $response = FetchResponse::fromV2PacketLines(
            $packet("\x01packfile\n")
            . $packet("\x01PACK")
            . $packet("\x01ERR bytes inside a sidebanded pack chunk")
            . $flush,
            true
        );

        $t->same(true, $response->hasPack());
        $t->same('PACKERR bytes inside a sidebanded pack chunk', $response->packData());
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
    'trims protocol v2 sideband-all response section lines like gix-protocol' => static function (TestRunner $t) use ($packet, $delimiter, $flush): void {
        $main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
        $installed = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $pack = 'PACK' . pack('N', 2) . pack('N', 1) . 'trimmed-fetch';

        $response = FetchResponse::fromV2PacketLines(
            $packet("\x01acknowledgments \t\r\n")
            . $packet("\x01ACK {$installed} common \t\r\n")
            . $packet("\x01ready \t\r\n")
            . $delimiter
            . $packet("\x01shallow-info \t\r\n")
            . $packet("\x01shallow {$main} \t\r\n")
            . $delimiter
            . $packet("\x01wanted-refs \t\r\n")
            . $packet("\x01{$main} refs/heads/main \t\r\n")
            . $delimiter
            . $packet("\x01packfile \t\r\n")
            . $packet("\x02Counting objects: 100% (1/1) \t\r\n")
            . $packet("\x01" . $pack)
            . $flush,
            true
        );

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same($installed, $response->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same($main, $response->shallowUpdates()[0]->object);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($main, $response->wantedRefs()[0]->object);
        $t->same($pack, $response->packData());
        $t->same(["Counting objects: 100% (1/1) \t\r"], $response->progressMessages());
        $t->same(100, $response->remoteProgress()[0]->percent);
    },
    'trims unicode whitespace on protocol v2 sideband-all section lines like rust trim end' => static function (TestRunner $t) use ($packet, $delimiter, $flush): void {
        $main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
        $installed = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $unicodeSpace = "\xE2\x80\x83";
        $pack = 'PACK' . pack('N', 2) . pack('N', 1) . 'unicode-trim-fetch';

        $response = FetchResponse::fromV2PacketLines(
            $packet("\x01acknowledgments{$unicodeSpace}\n")
            . $packet("\x01ACK {$installed} common{$unicodeSpace}\n")
            . $packet("\x01ready{$unicodeSpace}\n")
            . $delimiter
            . $packet("\x01shallow-info{$unicodeSpace}\n")
            . $packet("\x01shallow {$main}{$unicodeSpace}\n")
            . $delimiter
            . $packet("\x01wanted-refs{$unicodeSpace}\n")
            . $packet("\x01{$main} refs/heads/main{$unicodeSpace}\n")
            . $delimiter
            . $packet("\x01packfile{$unicodeSpace}\n")
            . $packet("\x02Counting objects: 100% (1/1)\n")
            . $packet("\x01" . $pack)
            . $flush,
            true
        );

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same($installed, $response->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same($main, $response->shallowUpdates()[0]->object);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($main, $response->wantedRefs()[0]->object);
        $t->same($pack, $response->packData());
        $t->same(['Counting objects: 100% (1/1)'], $response->progressMessages());
        $t->same(100, $response->remoteProgress()[0]->percent);
    },
    'parses protocol v2 sideband-all response lines without trailing linefeeds' => static function (TestRunner $t) use ($packet, $delimiter, $flush): void {
        $main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
        $installed = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $pack = 'PACK' . pack('N', 2) . pack('N', 1) . 'no-newline-fetch';
        $calls = [];

        $response = FetchResponse::fromV2PacketLines(
            $packet("\x01acknowledgments")
            . $packet("\x01ACK {$installed} common")
            . $packet("\x01ready")
            . $delimiter
            . $packet("\x01shallow-info")
            . $packet("\x01shallow {$main}")
            . $delimiter
            . $packet("\x01wanted-refs")
            . $packet("\x01{$main} refs/heads/main")
            . $delimiter
            . $packet("\x01packfile")
            . $packet("\x02Counting objects: 100% (1/1)")
            . $packet("\x01" . $pack)
            . $flush,
            true,
            static function (bool $isError, string $text) use (&$calls): bool {
                $calls[] = [$isError, $text];

                return true;
            }
        );

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same($installed, $response->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same($main, $response->shallowUpdates()[0]->object);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($main, $response->wantedRefs()[0]->object);
        $t->same($pack, $response->packData());
        $t->same(['Counting objects: 100% (1/1)'], $response->progressMessages());
        $t->same([[false, 'Counting objects: 100% (1/1)']], $calls);
        $t->same(100, $response->remoteProgress()[0]->percent);
    },
    'rejects invalid utf8 protocol lines while preserving binary sideband payloads' => static function (TestRunner $t) use ($packet, $delimiter, $flush, $invalidArgumentMessage): void {
        $main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
        $pack = 'PACK' . pack('N', 2) . pack('N', 1) . "binary-pack-\xFF";

        $t->same(
            'fetch response: invalid UTF-8 protocol line',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("wanted-refs\n")
                . $packet("{$main} refs/heads/wp-\xFF\n")
                . $delimiter
                . $packet("packfile\n")
                . $packet("\x01" . $pack)
                . $flush
            ))
        );
        $t->same(
            'fetch response: invalid UTF-8 protocol line',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("\x01wanted-refs\n")
                . $packet("\x01{$main} refs/heads/wp-\xFF\n")
                . $delimiter
                . $packet("\x01packfile\n")
                . $packet("\x01" . $pack)
                . $flush,
                true
            ))
        );

        $ordinary = FetchResponse::fromV2PacketLines(
            $packet("packfile\n")
            . $packet("\x02remote: byte \xFF progress\n")
            . $packet("\x03remote: byte \xFE warning\n")
            . $packet("\x01" . $pack)
            . $flush
        );

        $t->same($pack, $ordinary->packData());
        $t->same(["remote: byte \xFF progress"], $ordinary->progressMessages());
        $t->same(["remote: byte \xFE warning"], $ordinary->errorMessages());

        $sidebandAll = FetchResponse::fromV2PacketLines(
            $packet("\x02remote: sideband-all byte \xFF progress\n")
            . $packet("\x01packfile\n")
            . $packet("\x01" . $pack)
            . $flush,
            true
        );

        $t->same($pack, $sidebandAll->packData());
        $t->same(["remote: sideband-all byte \xFF progress"], $sidebandAll->progressMessages());
        $t->same([], $sidebandAll->errorMessages());
    },
    'maps remote progress chunks like gix-protocol sideband readers' => static function (TestRunner $t): void {
        $counting = RemoteProgress::fromText("Counting objects:  25% (1/4)\rCounting objects:  50% (2/4)\r");
        $enumerating = RemoteProgress::fromText('Enumerating objects: 4, done.');
        $maxU32Percentage = RemoteProgress::fromText('Counting objects: 4294967295% (4/10)');
        $overflowPercentage = RemoteProgress::fromText('Counting objects: 4294967296% (5/10)');

        $t->same('Counting objects', $counting?->action);
        $t->same(25, $counting?->percent);
        $t->same(1, $counting?->step);
        $t->same(4, $counting?->max);
        $t->same('Enumerating objects', $enumerating?->action);
        $t->same(null, $enumerating?->percent);
        $t->same(4, $enumerating?->step);
        $t->same(null, $enumerating?->max);
        $t->same(4294967295, $maxU32Percentage?->percent);
        $t->same(4, $maxU32Percentage?->step);
        $t->same(null, $overflowPercentage?->percent);
        $t->same(5, $overflowPercentage?->step);
        $t->same(10, $overflowPercentage?->max);
        $t->same(null, RemoteProgress::fromText('Total 4 (delta 0), reused 4 (delta 0), pack-reused 0'));
        $t->same(null, RemoteProgress::fromText('remote: preparing blobless pack'));
    },
    'parses upstream gix-protocol v2 sideband fixtures with pack trailers' => static function (TestRunner $t): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-sideband.php';

        foreach (['cloneOnly', 'cloneOnlyWithKeepalive', 'cloneOnly2'] as $key) {
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
    'parses upstream protocol v2 clone exchange before sideband fetch response' => static function (TestRunner $t): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-sideband.php';
        $fixture = $fixtures['cloneExchange'];
        $exchange = ProtocolV2FetchExchange::fromPacketLines($fixture['response']);
        $response = $exchange->fetchResponse();

        $remoteRefs = array_map(
            static fn ($ref): array => [
                'kind' => $ref->kind,
                'name' => $ref->name,
                'object' => $ref->object,
                'target' => $ref->target,
            ],
            $exchange->remoteRefs()
        );

        $t->same($fixture['capabilities'], $exchange->capabilities()->names());
        $t->same($fixture['fetchCapabilities'], $exchange->capabilities()->capability('fetch')?->values());
        $t->same($fixture['remoteRefs'], $remoteRefs);
        $t->same($fixture['messageBytes']['capabilityAdvertisement'], strlen($exchange->capabilityAdvertisementBytes()));
        $t->same($fixture['messageBytes']['lsRefsAdvertisement'], strlen($exchange->lsRefsAdvertisementBytes()));
        $t->same($fixture['messageBytes']['fetchResponse'], strlen($exchange->fetchResponseBytes()));
        $t->same(true, $response->hasPack());
        $t->same([], $response->acknowledgements());
        $t->same([], $response->shallowUpdates());
        $t->same([], $response->wantedRefs());
        $t->same($fixture['packBytes'], strlen($response->packData()));
        $t->same($fixture['packTrailer'], bin2hex(substr($response->packData(), -20)));
        $t->same($fixture['progressCount'], count($response->progressMessages()));
        $t->same($fixture['remoteProgressCount'], count($response->remoteProgress()));
        $t->same([], $response->errorMessages());
    },
    'passes protocol v2 exchange fetch sideband progress through caller handlers' => static function (TestRunner $t) use ($runtimeMessage): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-fetch-response.php';
        $calls = [];
        $exchange = ProtocolV2FetchExchange::fromPacketLines(
            $fixture['cloneExchangeResponse'],
            false,
            null,
            static function (bool $isError, string $text) use (&$calls): bool {
                $calls[] = ['isError' => $isError, 'text' => $text];

                return true;
            }
        );

        $t->same($fixture['packData'], $exchange->fetchResponse()->packData());
        $t->same(['Enumerating objects: 1, done.'], $exchange->fetchResponse()->progressMessages());
        $t->same([
            ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
        ], $calls);

        $abortedCalls = [];
        $t->same(
            'fetch response: interrupted by user',
            rtrim($runtimeMessage(static function () use ($fixture, &$abortedCalls): void {
                ProtocolV2FetchExchange::fromPacketLines(
                    $fixture['cloneExchangeResponse'],
                    false,
                    null,
                    static function (bool $isError, string $text) use (&$abortedCalls): bool {
                        $abortedCalls[] = ['isError' => $isError, 'text' => $text];

                        return false;
                    }
                );
            }))
        );
        $t->same([
            ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
        ], $abortedCalls);
    },
    'derives protocol v2 exchange sideband-all decoding from fetch capability' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-fetch-response.php';
        $calls = [];
        $exchange = ProtocolV2FetchExchange::fromPacketLines(
            $fixture['sidebandAllCapabilityExchangeResponse'],
            null,
            null,
            static function (bool $isError, string $text) use (&$calls): bool {
                $calls[] = ['isError' => $isError, 'text' => $text];

                return true;
            }
        );
        $response = $exchange->fetchResponse();

        $t->same(['agent', 'fetch', 'object-format'], $exchange->capabilities()->names());
        $t->same(['shallow', 'sideband-all'], $exchange->capabilities()->capability('fetch')?->values());
        $t->same([], $exchange->remoteRefs());
        $t->same('', $exchange->lsRefsAdvertisementBytes());
        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same($fixture['objects']['installed'], $response->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same($fixture['objects']['main'], $response->shallowUpdates()[0]->object);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($fixture['objects']['main'], $response->wantedRefs()[0]->object);
        $t->same($fixture['packData'], $response->packData());
        $t->same('flush', $response->terminator());
        $t->same([
            'remote: preparing WordPress blobless pack',
            'Enumerating objects: 1, done.',
        ], $response->progressMessages());
        $t->same([
            ['isError' => false, 'text' => 'remote: preparing WordPress blobless pack'],
            ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
        ], $calls);
    },
    'preserves sideband-all progress and error event order between protocol v2 sections' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-fetch-response.php';
        $calls = [];
        $response = FetchResponse::fromV2PacketLines(
            $fixture['interleavedSidebandAllResponse'],
            true,
            static function (bool $isError, string $text) use (&$calls): bool {
                $calls[] = ['isError' => $isError, 'text' => $text];

                return true;
            }
        );

        $events = [
            ['isError' => false, 'text' => 'remote: preparing interleaved WordPress fetch'],
            ['isError' => true, 'text' => 'remote: advisory before ACK rows'],
            ['isError' => false, 'text' => 'remote: ACKs continue'],
            ['isError' => true, 'text' => 'remote: shallow boundary advisory'],
            ['isError' => false, 'text' => 'remote: wanted refs follow'],
            ['isError' => true, 'text' => 'remote: warning before pack data'],
            ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
        ];

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same($fixture['objects']['installed'], $response->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[1]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same($fixture['objects']['main'], $response->shallowUpdates()[0]->object);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($fixture['packData'], $response->packData());
        $t->same([
            'remote: preparing interleaved WordPress fetch',
            'remote: ACKs continue',
            'remote: wanted refs follow',
            'Enumerating objects: 1, done.',
        ], $response->progressMessages());
        $t->same([
            'remote: advisory before ACK rows',
            'remote: shallow boundary advisory',
            'remote: warning before pack data',
        ], $response->errorMessages());
        $t->same($events, $response->sidebandEvents());
        $t->same($events, $calls);
        $t->same('flush', $response->terminator());
    },
    'parses upstream v2 ref-in-want wanted-refs response with sideband pack' => static function (TestRunner $t): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-ref-in-want-sideband.php';
        $fixture = $fixtures['refInWant'];
        $response = FetchResponse::fromV2PacketLines($fixture['response']);

        $wantedRefs = array_map(
            static fn (FetchWantedRef $wantedRef): array => ['object' => $wantedRef->object, 'path' => $wantedRef->path],
            $response->wantedRefs()
        );

        $t->same($fixture['wantedRefs'], $wantedRefs);
        $t->same([], $response->acknowledgements());
        $t->same([], $response->shallowUpdates());
        $t->same($fixture['hasPack'], $response->hasPack());
        $t->same('PACK', substr($response->packData(), 0, 4));
        $t->same($fixture['packBytes'], strlen($response->packData()));
        $t->same($fixture['packTrailer'], bin2hex(substr($response->packData(), -20)));
        $t->same($fixture['progressCount'], count($response->progressMessages()));
        $t->same([], $response->remoteProgress());
        $t->same([], $response->errorMessages());
        $t->same($fixture['responseBytesAfterCapabilityFlush'], strlen($fixture['response']));
    },
    'parses upstream v2 ref-in-want fetch exchange without ls-refs advertisement' => static function (TestRunner $t) use ($packet, $flush): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-ref-in-want-sideband.php';
        $fixture = $fixtures['refInWant'];
        $exchangeBytes = $packet("version 2\n")
            . $packet("agent=git/2.28.0\n")
            . $packet("ls-refs\n")
            . $packet("fetch=shallow\n")
            . $packet("server-option\n")
            . $packet("object-format=sha1\n")
            . $flush
            . $fixture['response'];

        $exchange = ProtocolV2FetchExchange::fromPacketLines($exchangeBytes);
        $response = $exchange->fetchResponse();
        $wantedRefs = array_map(
            static fn (FetchWantedRef $wantedRef): array => ['object' => $wantedRef->object, 'path' => $wantedRef->path],
            $response->wantedRefs()
        );

        $t->same(860, strlen($exchangeBytes));
        $t->same(['agent', 'ls-refs', 'fetch', 'server-option', 'object-format'], $exchange->capabilities()->names());
        $t->same(['shallow'], $exchange->capabilities()->capability('fetch')?->values());
        $t->same([], $exchange->remoteRefs());
        $t->same(110, strlen($exchange->capabilityAdvertisementBytes()));
        $t->same('', $exchange->lsRefsAdvertisementBytes());
        $t->same(749, strlen($exchange->fetchResponseBytes()));
        $t->same($fixture['wantedRefs'], $wantedRefs);
        $t->same(true, $response->hasPack());
        $t->same($fixture['packBytes'], strlen($response->packData()));
        $t->same($fixture['packTrailer'], bin2hex(substr($response->packData(), -20)));
        $t->same($fixture['progressCount'], count($response->progressMessages()));
        $t->same('flush', $response->terminator());
    },
    'parses upstream v2 fetch section fixtures with sideband pack bytes' => static function (TestRunner $t): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-section-sideband.php';

        foreach (['fetchUnshallow', 'cloneDeepen1', 'cloneDeepen5', 'fetchNoPack'] as $key) {
            $fixture = $fixtures[$key];
            $response = FetchResponse::fromV2PacketLines($fixture['response']);

            $acks = array_map(
                static fn (FetchAcknowledgement $ack): array => ['kind' => $ack->kind, 'object' => $ack->object],
                $response->acknowledgements()
            );
            $shallowUpdates = array_map(
                static fn (FetchShallowUpdate $update): array => ['kind' => $update->kind, 'object' => $update->object],
                $response->shallowUpdates()
            );

            $t->same($fixture['acknowledgements'], $acks);
            $t->same($fixture['shallowUpdates'], $shallowUpdates);
            $t->same($fixture['hasPack'], $response->hasPack());
            $t->same($fixture['packBytes'], strlen($response->packData()));
            $t->same($fixture['progressCount'], count($response->progressMessages()));
            $t->same([], $response->errorMessages());

            if ($fixture['hasPack']) {
                $t->same('PACK', substr($response->packData(), 0, 4));
                $t->same($fixture['packTrailer'], bin2hex(substr($response->packData(), -20)));
                $t->same(true, count($response->remoteProgress()) > 0);
            } else {
                $t->same('', $response->packData());
                $t->same([], $response->remoteProgress());
            }
        }

        try {
            FetchResponse::fromV2PacketLines($fixtures['fetchErrLine']['response']);
        } catch (RuntimeException $error) {
            $t->same($fixtures['fetchErrLine']['errorMessage'], rtrim($error->getMessage()));

            return;
        }

        throw new RuntimeException('Expected upstream fetch ERR line to fail');
    },
    'parses upstream v2 fetch response fixture with suffixless acks and fragmented sidebands' => static function (TestRunner $t): void {
        $fixtures = require dirname(__DIR__) . '/fixtures/upstream-gix-protocol-v2-fetch-response-sideband.php';
        $fixture = $fixtures['fetchAcksAndPack'];
        $response = FetchResponse::fromV2PacketLines($fixture['response']);

        $acks = array_map(
            static fn (FetchAcknowledgement $ack): array => ['kind' => $ack->kind, 'object' => $ack->object],
            $response->acknowledgements()
        );

        $t->same($fixture['acknowledgements'], $acks);
        $t->same([], $response->shallowUpdates());
        $t->same([], $response->wantedRefs());
        $t->same($fixture['hasPack'], $response->hasPack());
        $t->same($fixture['packBytes'], strlen($response->packData()));
        $t->same($fixture['packTrailer'], bin2hex(substr($response->packData(), -20)));
        $t->same($fixture['progressCount'], count($response->progressMessages()));
        $t->same([], $response->errorMessages());
        $t->contains($fixture['fragmentedProgressSamples']['firstCountingFragment'], $response->progressMessages()[2]);
        $t->same(true, str_starts_with($response->progressMessages()[3], $fixture['fragmentedProgressSamples']['continuedCountingFragmentPrefix']));
        $t->same($fixture['fragmentedProgressSamples']['splitDoneSuffix'], $response->progressMessages()[5]);
        $t->same($fixture['remoteProgressCount'], count($response->remoteProgress()));
        $t->same('Counting objects', $response->remoteProgress()[1]->action);
        $t->same(7, $response->remoteProgress()[1]->percent);
        $t->same(1, $response->remoteProgress()[1]->step);
        $t->same(13, $response->remoteProgress()[1]->max);
        $t->same(' objects', $response->remoteProgress()[3]->action);
        $t->same(46, $response->remoteProgress()[3]->percent);
    },
    'parses upstream smart http v2 fetch result with sideband pack data' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/upstream-gix-transport-v2-http-fetch-response.php';
        $response = FetchResponse::fromSmartHttpUploadPackResult($fixture['httpResponse']);

        $t->same(true, $response->hasPack());
        $t->same([], $response->acknowledgements());
        $t->same([], $response->shallowUpdates());
        $t->same([], $response->wantedRefs());
        $t->same($fixture['packBytes'], strlen($response->packData()));
        $t->same($fixture['packTrailer'], bin2hex(substr($response->packData(), -20)));
        $t->same($fixture['progressCount'], count($response->progressMessages()));
        $t->same($fixture['remoteProgressCount'], count($response->remoteProgress()));
        $t->same([], $response->errorMessages());
        $t->same($fixture['firstProgress'], $response->progressMessages()[0]);
        $t->same($fixture['combinedCountingProgress'], $response->progressMessages()[1]);
        $t->same($fixture['finalProgress'], $response->progressMessages()[4]);
        $t->same('Enumerating objects', $response->remoteProgress()[0]->action);
        $t->same(3, $response->remoteProgress()[0]->step);
        $t->same('Counting objects', $response->remoteProgress()[1]->action);
        $t->same(33, $response->remoteProgress()[1]->percent);
        $t->same(1, $response->remoteProgress()[1]->step);
        $t->same(3, $response->remoteProgress()[1]->max);
        $t->same(100, $response->remoteProgress()[3]->percent);
        $t->same(3, $response->remoteProgress()[3]->step);
        $t->same(3, $response->remoteProgress()[3]->max);

        $t->throws(
            RuntimeException::class,
            static fn () => FetchResponse::fromSmartHttpUploadPackResult(str_replace('application/x-git-upload-pack-result', 'text/plain', $fixture['httpResponse']))
        );
        $t->throws(
            RuntimeException::class,
            static fn () => FetchResponse::fromSmartHttpUploadPackResult(str_replace('Content-Length: 1135', 'Content-Length: 1134', $fixture['httpResponse']))
        );
    },
    'parses protocol v2 sha256 response object ids before sideband pack data' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-fetch-response.php';
        $response = FetchResponse::fromV2PacketLines($fixture['sha256Response']);

        $t->same(true, $response->hasPack());
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[0]->kind);
        $t->same($fixture['objectsSha256']['installed'], $response->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::COMMON, $response->acknowledgements()[1]->kind);
        $t->same($fixture['objectsSha256']['main'], $response->acknowledgements()[1]->object);
        $t->same(FetchAcknowledgement::READY, $response->acknowledgements()[2]->kind);
        $t->same(FetchShallowUpdate::SHALLOW, $response->shallowUpdates()[0]->kind);
        $t->same($fixture['objectsSha256']['shallow'], $response->shallowUpdates()[0]->object);
        $t->same('refs/heads/main', $response->wantedRefs()[0]->path);
        $t->same($fixture['objectsSha256']['main'], $response->wantedRefs()[0]->object);
        $t->same($fixture['sha256PackData'], $response->packData());
        $t->same($fixture['sha256PackTrailer'], bin2hex(substr($response->packData(), -32)));
        $t->same(['Resolving deltas: 100% (1/1)'], $response->progressMessages());
        $t->same('Resolving deltas', $response->remoteProgress()[0]->action);
        $t->same(100, $response->remoteProgress()[0]->percent);
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
    'rejects malformed sideband-all response packets before section parsing' => static function (TestRunner $t) use ($packet, $delimiter, $invalidArgumentMessage): void {
        $t->throws(InvalidArgumentException::class, static fn () => FetchResponse::fromV2PacketLines($packet("\x09acknowledgments\n"), true));
        $t->contains(
            'fetch response: unknown or unsupported section header ERR segmentation fault',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines($packet("\x01ERR segmentation fault\n"), true))
        );
        $t->contains(
            'fetch response: unknown line prefix in ERR common collapsed',
            $invalidArgumentMessage(static fn () => FetchResponse::fromV2PacketLines(
                $packet("\x01acknowledgments\n")
                . $packet("\x01ERR common collapsed\n")
                . $delimiter,
                true
            ))
        );
        $t->same(
            [],
            FetchResponse::fromV2PacketLines($packet("\x03") . $packet("\x01acknowledgments\n") . $packet("\x01NAK\n") . '0000', true)->errorMessages()
        );
    },
    'wordpress fixture parses fetch response sections and sideband pack bytes' => static function (TestRunner $t) use ($runtimeMessage): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-fetch-response.php';
        $response = FetchResponse::fromV2PacketLines($fixture['response'], $fixture['sidebandAll'] ?? false);
        $suffixlessAckResponse = FetchResponse::fromV2PacketLines($fixture['suffixlessAckResponse']);
        $refInWantResponse = FetchResponse::fromV2PacketLines($fixture['refInWantResponse']);
        $emptyErrorSidebandMessages = [];
        $emptyErrorSidebandResponse = FetchResponse::fromV2PacketLines(
            $fixture['emptyErrorSidebandResponse'],
            false,
            static function (bool $isError, string $text) use (&$emptyErrorSidebandMessages): bool {
                $emptyErrorSidebandMessages[] = ['isError' => $isError, 'text' => $text];

                return true;
            }
        );
        $overflowProgressResponse = FetchResponse::fromV2PacketLines($fixture['overflowProgressResponse']);
        $cloneExchange = ProtocolV2FetchExchange::fromPacketLines($fixture['cloneExchangeResponse']);
        $smartHttpUploadPackResponse = FetchResponse::fromSmartHttpUploadPackResult($fixture['smartHttpUploadPackResponse']);

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
        $t->same(FetchAcknowledgement::COMMON, $suffixlessAckResponse->acknowledgements()[0]->kind);
        $t->same($fixture['objects']['installed'], $suffixlessAckResponse->acknowledgements()[0]->object);
        $t->same(FetchAcknowledgement::COMMON, $suffixlessAckResponse->acknowledgements()[1]->kind);
        $t->same($fixture['objects']['main'], $suffixlessAckResponse->acknowledgements()[1]->object);
        $t->same(FetchAcknowledgement::READY, $suffixlessAckResponse->acknowledgements()[2]->kind);
        $t->same($fixture['packData'], $suffixlessAckResponse->packData());
        $t->same(1, count($suffixlessAckResponse->progressMessages()));
        $t->same('refs/heads/main', $refInWantResponse->wantedRefs()[0]->path);
        $t->same($fixture['objects']['main'], $refInWantResponse->wantedRefs()[0]->object);
        $t->same($fixture['packData'], $refInWantResponse->packData());
        $t->same([], $refInWantResponse->progressMessages());
        $t->same(['agent', 'ls-refs', 'fetch', 'object-format'], $cloneExchange->capabilities()->names());
        $t->same('refs/heads/main', $cloneExchange->remoteRefs()[0]->target);
        $t->same('refs/heads/main', $cloneExchange->remoteRefs()[1]->name);
        $t->same($fixture['packData'], $cloneExchange->fetchResponse()->packData());
        $t->same(['Enumerating objects: 1, done.'], $cloneExchange->fetchResponse()->progressMessages());
        $t->same(
            'fetch response: upload-pack error raw WordPress fetch failure',
            rtrim($runtimeMessage(static fn () => FetchResponse::fromV2PacketLines($fixture['rawUploadPackErrorResponse'], true)))
        );
        $t->same(
            'fetch response: missing sideband flush packet',
            rtrim($runtimeMessage(static fn () => FetchResponse::fromV2PacketLines($fixture['truncatedPackResponse'])))
        );
        $t->same(['remote: preparing WordPress blobless pack', 'Enumerating objects: 1, done.'], $response->progressMessages());

        $summary = require dirname(__DIR__) . '/examples/wordpress-protocol-v2-fetch-response.php';
        $t->same('fetch response: upload-pack error raw WordPress fetch failure', $summary['uploadPackError']);
        $t->same(true, $summary['sidebandAllDecodedErrLineRejectedAsProtocolText']);
        $t->same(
            'fetch response: unknown or unsupported section header ERR decoded WordPress fetch text',
            $summary['sidebandAllDecodedErrLineError']
        );
        $t->same(true, $summary['truncatedPackRejected']);
        $t->same('fetch response: missing sideband flush packet', $summary['truncatedPackError']);
        $t->same(true, $summary['progressCancellationRejected']);
        $t->same('fetch response: interrupted by user', $summary['progressCancellationError']);
        $t->same([
            ['isError' => false, 'text' => 'remote: WordPress deployment fetch can be cancelled'],
        ], $summary['progressCancellationMessages']);
        $t->same($fixture['packData'], $emptyErrorSidebandResponse->packData());
        $t->same([], $emptyErrorSidebandResponse->errorMessages());
        $t->same([['isError' => true, 'text' => '']], $emptyErrorSidebandMessages);
        $t->same(true, $summary['emptyErrorKeepaliveIgnored']);
        $t->same(true, $summary['emptyErrorKeepaliveHandled']);
        $t->same([['isError' => true, 'text' => '']], $summary['emptyErrorKeepaliveMessages']);
        $t->same(4294967295, $overflowProgressResponse->remoteProgress()[0]->percent);
        $t->same(null, $overflowProgressResponse->remoteProgress()[1]->percent);
        $t->same(5, $overflowProgressResponse->remoteProgress()[1]->step);
        $t->same(10, $overflowProgressResponse->remoteProgress()[1]->max);
        $t->same($fixture['packData'], $overflowProgressResponse->packData());
        $t->same(true, $summary['overflowPercentageBounded']);
        $t->same(true, $summary['suffixlessAckParsed']);
        $t->same(true, $summary['refInWantParsed']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['refInWantPackTrailer']);
        $t->same(true, $summary['sha256ObjectFormatParsed']);
        $t->same($fixture['sha256PackTrailer'], $summary['sha256PackTrailer']);
        $t->same(true, $summary['cloneExchangeParsed']);
        $t->same(true, $summary['cloneExchangeProgressHandled']);
        $t->same([
            ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
        ], $summary['cloneExchangeProgressMessages']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['cloneExchangePackTrailer']);
        $t->same(true, $summary['sidebandAllCapabilityExchangeParsed']);
        $t->same([
            ['isError' => false, 'text' => 'remote: preparing WordPress blobless pack'],
            ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
        ], $summary['sidebandAllCapabilityExchangeMessages']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['sidebandAllCapabilityExchangePackTrailer']);
        $t->same(true, $summary['interleavedSidebandAllParsed']);
        $t->same([
            'remote: preparing interleaved WordPress fetch',
            'remote: ACKs continue',
            'remote: wanted refs follow',
            'Enumerating objects: 1, done.',
        ], $summary['interleavedSidebandAllProgress']);
        $t->same([
            'remote: advisory before ACK rows',
            'remote: shallow boundary advisory',
            'remote: warning before pack data',
        ], $summary['interleavedSidebandAllErrors']);
        $t->same([
            ['isError' => false, 'text' => 'remote: preparing interleaved WordPress fetch'],
            ['isError' => true, 'text' => 'remote: advisory before ACK rows'],
            ['isError' => false, 'text' => 'remote: ACKs continue'],
            ['isError' => true, 'text' => 'remote: shallow boundary advisory'],
            ['isError' => false, 'text' => 'remote: wanted refs follow'],
            ['isError' => true, 'text' => 'remote: warning before pack data'],
            ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
        ], $summary['interleavedSidebandAllEvents']);
        $t->same($summary['interleavedSidebandAllEvents'], $summary['interleavedSidebandAllMessages']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['interleavedSidebandAllPackTrailer']);
        $t->same(true, $summary['responseEndNoPackParsed']);
        $t->same(true, $summary['responseEndPackParsed']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['responseEndPackTrailer']);
        $t->same(true, $summary['sidebandAllResponseEndParsed']);
        $t->same(true, $summary['delimiterPackParsed']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['delimiterPackTrailer']);
        $t->same(true, $summary['persistentFetchCursorParsed']);
        $t->same(strlen($fixture['persistentFirstFetchResponse']), $summary['persistentFetchConsumedBytes']);
        $t->same(strlen($fixture['persistentSecondFetchResponse']), $summary['persistentFetchTailBytes']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['persistentFetchFirstPackTrailer']);
        $t->same([
            ['isError' => false, 'text' => 'remote: response-end aware negotiation'],
            ['isError' => true, 'text' => 'remote: deployment warning before pack'],
        ], $summary['sidebandAllResponseEndMessages']);
        $t->same(true, $summary['trailingWhitespaceParsed']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['trailingWhitespacePackTrailer']);
        $t->same(true, $summary['unicodeWhitespaceParsed']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['unicodeWhitespacePackTrailer']);
        $noNewlineSidebandAllResponse = FetchResponse::fromV2PacketLines($fixture['noNewlineSidebandAllResponse'], true);
        $t->same(true, $summary['noNewlineSidebandAllParsed']);
        $t->same($fixture['packData'], $noNewlineSidebandAllResponse->packData());
        $t->same(['Counting objects: 100% (1/1)'], $noNewlineSidebandAllResponse->progressMessages());
        $t->same(100, $noNewlineSidebandAllResponse->remoteProgress()[0]->percent);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['noNewlineSidebandAllPackTrailer']);
        $t->same(true, $summary['invalidUtf8ProtocolLineRejected']);
        $t->same('fetch response: invalid UTF-8 protocol line', $summary['invalidUtf8ProtocolLineError']);
        $t->same(true, $summary['binarySidebandPayloadsPreserved']);
        $t->same('ff7369646562616e642d6279746573', $summary['binarySidebandPackSuffix']);
        $t->same($fixture['packData'], $smartHttpUploadPackResponse->packData());
        $t->same(['Counting objects: 100% (1/1)' . "\r" . 'Counting objects: 100% (1/1), done.'], $smartHttpUploadPackResponse->progressMessages());
        $t->same(true, $summary['smartHttpUploadPackParsed']);
        $t->same('3b4b12f4cf6262d95e165b4517d71d0b9df20789', $summary['smartHttpUploadPackTrailer']);
    },
];
