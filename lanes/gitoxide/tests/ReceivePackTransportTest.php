<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitDaemonReceivePackTransport;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\SmartHttpReceivePackTransport;
use PortLibs\Gitoxide\SshReceivePackTransport;
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
    'git-daemon receive-pack transport sends service request and delegates client flow' => static function (TestRunner $t) use ($packet, $flush, $streamWith, $streamBytes, $readPacketSequence): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress git-daemon transport payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02Writing objects: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $read = $streamWith($advertisement . $responseBytes);
        $write = $streamWith('');
        $transport = new GitDaemonReceivePackTransport($read, $write, '/wp-content.git', 'git.example.test', 9440);
        $client = new ReceivePackClient($transport, 'port-libs/0.1');
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);
        $written = $streamBytes($write);
        $serviceLength = hexdec(substr($written, 0, 4));
        $servicePayload = substr($written, 4, $serviceLength - 4);
        $requestBytes = substr($written, $serviceLength);
        [$commands, $packBytes] = $readPacketSequence($requestBytes);

        $t->same(true, $response->isSuccessful());
        $t->same(['Writing objects: 100% (1/1)'], $response->progressMessages());
        $t->same('refs/heads/main', $response->refStatuses()[0]->effectiveRefName());
        $t->same("git-receive-pack /wp-content.git\0host=git.example.test:9440\0", $servicePayload);
        $t->same($request->requestBytes(), $requestBytes);
        $t->same("{$old} {$blob->oid()} refs/heads/main\0 report-status side-band-64k object-format=sha1 agent=port-libs/0.1", $commands[0]);
        $t->same($request->pack()?->packBytes(), $packBytes);
    },
    'git-daemon receive-pack service request validates urls and parameters' => static function (TestRunner $t): void {
        $packet = GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test');
        $t->same("git-receive-pack /repo.git\0host=example.test\0", substr($packet, 4));

        $packetWithExtra = GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', 9440, ['version=1']);
        $t->same("git-receive-pack /repo.git\0host=example.test:9440\0\0version=1\0", substr($packetWithExtra, 4));

        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::connect('https://example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::connect('git://example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', ''));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes("/bad\0repo", 'example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', 0));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ["bad\0extra"]));
    },
    'smart http receive-pack transport strips service advertisement and posts request' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress smart HTTP transport payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02Writing objects: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $requests = [];
        $requester = static function (string $method, string $url, array $headers, ?string $body, float $timeout) use (&$requests, $packet, $flush, $advertisement, $responseBytes): array {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => ['application/x-git-receive-pack-result; charset=utf-8']],
                'body' => $responseBytes,
            ];
        };

        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport('https://git.example.test/wp-content.git/', $requester),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);

        $t->same(true, $response->isSuccessful());
        $t->same(['Writing objects: 100% (1/1)'], $response->progressMessages());
        $t->same('GET', $requests[0]['method']);
        $t->same('https://git.example.test/wp-content.git/info/refs?service=git-receive-pack', $requests[0]['url']);
        $t->same('application/x-git-receive-pack-advertisement', $requests[0]['headers']['Accept']);
        $t->same(null, $requests[0]['body']);
        $t->same('POST', $requests[1]['method']);
        $t->same('https://git.example.test/wp-content.git/git-receive-pack', $requests[1]['url']);
        $t->same('application/x-git-receive-pack-request', $requests[1]['headers']['Content-Type']);
        $t->same('application/x-git-receive-pack-result', $requests[1]['headers']['Accept']);
        $t->same((string) strlen($request->requestBytes()), $requests[1]['headers']['Content-Length']);
        $t->same($request->requestBytes(), $requests[1]['body']);
    },
    'smart http receive-pack urls headers and response validation follow git http protocol' => static function (TestRunner $t) use ($packet, $flush): void {
        $t->same(
            'https://example.test/repo.git/info/refs?service=git-receive-pack',
            SmartHttpReceivePackTransport::infoRefsUrl('https://user:pass@example.test/repo.git/')
        );
        $t->same(
            'https://example.test/repo.git/git-receive-pack',
            SmartHttpReceivePackTransport::receivePackUrl('https://example.test/repo.git/')
        );
        $t->same(
            'http://example.test/daemon.cgi?svc=git&q=/info/refs&service=git-receive-pack',
            SmartHttpReceivePackTransport::infoRefsUrl('http://example.test/daemon.cgi?svc=git&q=')
        );

        $requests = [];
        $transport = new SmartHttpReceivePackTransport(
            'https://word%20press:s3cret@example.test/repo.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout) use (&$requests, $packet, $flush): array {
                $requests[] = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'body' => $body,
                    'timeout' => $timeout,
                ];

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            ['version=1'],
            12.5
        );

        $transport->readAdvertisement();

        $t->same('https://example.test/repo.git/info/refs?service=git-receive-pack', $requests[0]['url']);
        $t->same('Basic ' . base64_encode('word press:s3cret'), $requests[0]['headers']['Authorization']);
        $t->same('version=1', $requests[0]['headers']['Git-Protocol']);
        $t->same(12.5, $requests[0]['timeout']);

        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('git://example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl("https://example.test/repo.git\n"));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://example.test/repo.git#refs'));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, ['bad:param']));

        $badType = new SmartHttpReceivePackTransport(
            'https://example.test/repo.git',
            static fn (): array => ['status' => 200, 'headers' => ['Content-Type' => 'text/plain'], 'body' => '']
        );
        $t->throws(RuntimeException::class, static fn () => $badType->readAdvertisement());

        $badService = new SmartHttpReceivePackTransport(
            'https://example.test/repo.git',
            static function () use ($packet, $flush): array {
                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-upload-pack\n") . $flush,
                ];
            }
        );
        $t->throws(RuntimeException::class, static fn () => $badService->readAdvertisement());
    },
    'smart http receive-pack preserves auth headers and session cookies across requests' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress smart HTTP session payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $requests = [];
        $requester = static function (string $method, string $url, array $headers, ?string $body, float $timeout) use (&$requests, $packet, $flush, $advertisement, $responseBytes): array {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => ['wp_session=abc123; Path=/; HttpOnly', 'deploy=blue; Secure'],
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $responseBytes,
            ];
        };

        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                $requester,
                ['version=1'],
                9.5,
                ['Authorization' => 'Bearer wp-token', 'User-Agent' => 'port-libs-test/1']
            ),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);

        $t->same(true, $response->isSuccessful());
        $t->same('Bearer wp-token', $requests[0]['headers']['Authorization']);
        $t->same('port-libs-test/1', $requests[0]['headers']['User-Agent']);
        $t->same('version=1', $requests[0]['headers']['Git-Protocol']);
        $t->same(null, $requests[0]['headers']['Cookie'] ?? null);
        $t->same('Bearer wp-token', $requests[1]['headers']['Authorization']);
        $t->same('port-libs-test/1', $requests[1]['headers']['User-Agent']);
        $t->same('version=1', $requests[1]['headers']['Git-Protocol']);
        $t->same('wp_session=abc123; deploy=blue', $requests[1]['headers']['Cookie']);
        $t->same($request->requestBytes(), $requests[1]['body']);
        $t->same(9.5, $requests[1]['timeout']);

        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, ['Content-Type' => 'text/plain']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, ["Bad\nHeader" => 'x']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, ['User-Agent' => "bad\nvalue"]));
    },
    'ssh receive-pack transport connects through injected exec streams' => static function (TestRunner $t) use ($packet, $flush, $streamWith, $streamBytes, $readPacketSequence): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress SSH transport payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02Writing objects: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $read = $streamWith($advertisement . $responseBytes);
        $write = $streamWith('');
        $connection = null;
        $connector = static function (string $host, ?string $user, ?int $port, string $command, float $timeout) use (&$connection, $read, $write): array {
            $connection = [
                'host' => $host,
                'user' => $user,
                'port' => $port,
                'command' => $command,
                'timeout' => $timeout,
            ];

            return ['read' => $read, 'write' => $write];
        };

        $client = new ReceivePackClient(
            SshReceivePackTransport::connect('ssh://deploy@git.example.test:2222/var/www/wp-content.git', $connector, 11.5),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);
        [$commands, $packBytes] = $readPacketSequence($streamBytes($write));

        $t->same(true, $response->isSuccessful());
        $t->same(['Writing objects: 100% (1/1)'], $response->progressMessages());
        $t->same([
            'host' => 'git.example.test',
            'user' => 'deploy',
            'port' => 2222,
            'command' => "git-receive-pack '/var/www/wp-content.git'",
            'timeout' => 11.5,
        ], $connection);
        $t->same($request->requestBytes(), $streamBytes($write));
        $t->same("{$old} {$blob->oid()} refs/heads/main\0 report-status side-band-64k object-format=sha1 agent=port-libs/0.1", $commands[0]);
        $t->same($request->pack()?->packBytes(), $packBytes);
    },
    'ssh receive-pack urls and commands are validated without shelling out' => static function (TestRunner $t): void {
        $t->same([
            'host' => 'git.example.test',
            'user' => 'deploy',
            'port' => 2222,
            'path' => '/var/www/wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('ssh://deploy@git.example.test:2222/var/www/wp-content.git'));
        $t->same([
            'host' => 'git.example.test',
            'user' => 'deploy',
            'port' => null,
            'path' => 'wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('deploy@git.example.test:wp-content.git'));
        $t->same('~/wp-content.git', SshReceivePackTransport::parseRepositoryUrl('ssh://git.example.test/~/wp-content.git')['path']);
        $t->same("git-receive-pack 'wp content/repo'\\''s.git'", SshReceivePackTransport::receivePackCommand("wp content/repo's.git"));

        $badConnector = static fn (): array => ['read' => 'not a stream', 'write' => 'not a stream'];

        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('https://example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl("ssh://example.test/repo.git\n"));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://example.test/repo.git?service=git-receive-pack'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::receivePackCommand("repo\0.git"));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connect('ssh://example.test/repo.git', static fn (): array => [], 0.0));
        $t->throws(RuntimeException::class, static fn () => SshReceivePackTransport::connect('ssh://example.test/repo.git', $badConnector));
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
