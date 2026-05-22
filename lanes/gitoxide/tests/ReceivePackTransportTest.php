<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\StreamReceivePackTransport;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$streamWith = static function (string $bytes): mixed {
    $stream = fopen('php://temp', 'r+b');
    if ($stream === false) {
        throw new RuntimeException('Unable to open test stream');
    }
    fwrite($stream, $bytes);
    rewind($stream);

    return $stream;
};

$streamBytes = static function (mixed $stream): string {
    rewind($stream);

    return (string) stream_get_contents($stream);
};

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

return [
    'stream receive-pack transport reads advertisement writes request and reads sideband response' => static function (TestRunner $t) use ($packet, $flush, $streamWith, $streamBytes, $readPacketSequence): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress stream transport payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02Writing objects: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $read = $streamWith($advertisement . $responseBytes);
        $write = $streamWith('');
        $client = new ReceivePackClient(new StreamReceivePackTransport($read, $write), 'port-libs/0.1');
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);
        [$commands, $packBytes] = $readPacketSequence($streamBytes($write));

        $t->same(true, $response->isSuccessful());
        $t->same(['Writing objects: 100% (1/1)'], $response->progressMessages());
        $t->same('refs/heads/main', $response->refStatuses()[0]->effectiveRefName());
        $t->same($request->requestBytes(), $streamBytes($write));
        $t->same("{$old} {$blob->oid()} refs/heads/main\0 report-status side-band-64k object-format=sha1 agent=port-libs/0.1", $commands[0]);
        $t->same($request->pack()?->packBytes(), $packBytes);
    },
    'receive-pack client parses direct report-status responses without sideband' => static function (TestRunner $t) use ($packet, $flush, $streamWith, $streamBytes): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress direct report-status payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status object-format=sha1\n") . $flush;
        $responseBytes = $packet("unpack ok\n") . $packet("ok refs/heads/main\n") . $flush;
        $read = $streamWith($advertisement . $responseBytes);
        $write = $streamWith('');
        $client = new ReceivePackClient(new StreamReceivePackTransport($read, $write), 'port-libs/0.1');

        $response = $client->run(static function (SendPackSession $session) use ($blob): mixed {
            $session->createOrUpdate('refs/heads/main', $blob->oid());

            return $session->buildRequest([$blob]);
        });

        $t->same(true, $response->isSuccessful());
        $t->same([], $response->progressMessages());
        $t->same('refs/heads/main', $response->refStatuses()[0]->effectiveRefName());
        $t->contains('refs/heads/main', $streamBytes($write));
        $t->contains('PACK', $streamBytes($write));
    },
    'receive-pack transport guards state order and truncated packet streams' => static function (TestRunner $t) use ($streamWith): void {
        $transport = new StreamReceivePackTransport($streamWith(''), $streamWith(''));
        $t->throws(LogicException::class, static fn () => $transport->readResponse());
        $t->throws(LogicException::class, static fn () => $transport->writeRequest('0000'));

        $truncated = new StreamReceivePackTransport($streamWith('000aabc'), $streamWith(''));
        $t->throws(RuntimeException::class, static fn () => $truncated->readAdvertisement());
    },
    'receive-pack client refuses responses without report-status negotiation' => static function (TestRunner $t) use ($packet, $flush, $streamWith, $streamBytes): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress no report status payload');
        $advertisement = $packet("{$old} refs/heads/main\0side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02No report-status response\n") . $flush;
        $write = $streamWith('');
        $client = new ReceivePackClient(
            new StreamReceivePackTransport($streamWith($advertisement . $responseBytes), $write),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $t->throws(LogicException::class, static fn () => $client->send($request));
        $t->same('', $streamBytes($write));
    },
    'wordpress fixture runs receive-pack over native stream transport' => static function (TestRunner $t) use ($readPacketSequence): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-receive-pack-transport.php';
        [$commands, $afterCommands] = $readPacketSequence($fixture['requestBytes']);
        [$options, $packBytes] = $readPacketSequence($afterCommands);

        $t->same(true, $fixture['responseSuccessful']);
        $t->same(['Writing objects: 100% (3/3), done.'], $fixture['progressMessages']);
        $t->same(['refs/heads/main'], $fixture['acceptedRefs']);
        $t->contains($fixture['newCommit'], $commands[0]);
        $t->same(['ci.skip'], $options);
        $t->contains('PACK', $packBytes);
    },
];
