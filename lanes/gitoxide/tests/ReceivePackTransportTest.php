<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitDaemonReceivePackTransport;
use PortLibs\Gitoxide\PushRefStatus;
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

$readExactFromStream = static function (mixed $stream, int $length): string {
    $bytes = '';
    while (strlen($bytes) < $length && !feof($stream)) {
        $chunk = fread($stream, $length - strlen($bytes));
        if ($chunk === false) {
            throw new RuntimeException('Unable to read from test stream');
        }
        if ($chunk === '') {
            $meta = stream_get_meta_data($stream);
            if (!empty($meta['timed_out'])) {
                throw new RuntimeException('Timed out reading from test stream');
            }
            continue;
        }
        $bytes .= $chunk;
    }
    if (strlen($bytes) !== $length) {
        throw new RuntimeException("Expected {$length} bytes from test stream");
    }

    return $bytes;
};

$writeAllToStream = static function (mixed $stream, string $bytes): void {
    $offset = 0;
    while ($offset < strlen($bytes)) {
        $written = fwrite($stream, substr($bytes, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Unable to write to test stream');
        }
        $offset += $written;
    }
};

$readHttpRequestHeader = static function (mixed $stream): string {
    $bytes = '';
    while (!str_contains($bytes, "\r\n\r\n") && !feof($stream)) {
        $chunk = fread($stream, 512);
        if ($chunk === false) {
            throw new RuntimeException('Unable to read HTTP request header');
        }
        if ($chunk === '') {
            $meta = stream_get_meta_data($stream);
            if (!empty($meta['timed_out'])) {
                throw new RuntimeException('Timed out reading HTTP request header');
            }
            continue;
        }
        $bytes .= $chunk;
    }

    return $bytes;
};

$runLocalTcpServer = static function (callable $serverHandler, callable $clientHandler): array {
    if (!function_exists('pcntl_fork')) {
        throw new RuntimeException('pcntl_fork is required for local SOCKS transport tests');
    }

    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($server === false) {
        throw new RuntimeException("Unable to start local TCP server: {$errstr}", $errno);
    }

    $address = stream_socket_get_name($server, false);
    if (!is_string($address) || !str_contains($address, ':')) {
        throw new RuntimeException('Unable to determine local TCP server address');
    }
    $port = (int) substr($address, strrpos($address, ':') + 1);
    $logPath = tempnam(sys_get_temp_dir(), 'gitoxide-socks-');
    if ($logPath === false) {
        throw new RuntimeException('Unable to create SOCKS test log');
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
        fclose($server);
        @unlink($logPath);
        throw new RuntimeException('Unable to fork local TCP server');
    }

    if ($pid === 0) {
        try {
            $connection = stream_socket_accept($server, 5);
            if ($connection === false) {
                throw new RuntimeException('Local TCP server did not receive a connection');
            }
            stream_set_timeout($connection, 5);
            $log = $serverHandler($connection);
            fclose($connection);
            file_put_contents($logPath, json_encode(['ok' => true, 'log' => $log], JSON_THROW_ON_ERROR));
            fclose($server);
            exit(0);
        } catch (Throwable $throwable) {
            file_put_contents($logPath, json_encode(['ok' => false, 'error' => $throwable->getMessage()], JSON_THROW_ON_ERROR));
            fclose($server);
            exit(1);
        }
    }

    fclose($server);
    try {
        $result = $clientHandler($port);
    } finally {
        pcntl_waitpid($pid, $status);
    }

    $payload = json_decode((string) file_get_contents($logPath), true);
    @unlink($logPath);
    if (!is_array($payload) || ($payload['ok'] ?? false) !== true) {
        throw new RuntimeException((string) ($payload['error'] ?? 'Local TCP server failed'));
    }

    return ['result' => $result, 'log' => $payload['log']];
};

$temporaryTlsCertificate = static function (string $commonName): array {
    if (!extension_loaded('openssl')) {
        throw new RuntimeException('openssl extension is required for local TLS transport tests');
    }

    $dir = sys_get_temp_dir() . '/gitoxide-socks-tls-' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create TLS test directory: {$dir}");
    }

    $configPath = $dir . '/openssl.cnf';
    file_put_contents($configPath, <<<CFG
[req]
distinguished_name = dn
req_extensions = v3_req
prompt = no
[dn]
CN = {$commonName}
[v3_req]
subjectAltName = DNS:{$commonName}
basicConstraints = critical,CA:TRUE
keyUsage = digitalSignature,keyEncipherment,keyCertSign
extendedKeyUsage = serverAuth
CFG);

    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    if ($key === false) {
        throw new RuntimeException('Unable to generate TLS test private key');
    }

    $csr = openssl_csr_new(
        ['commonName' => $commonName],
        $key,
        ['config' => $configPath, 'digest_alg' => 'sha256', 'req_extensions' => 'v3_req']
    );
    if ($csr === false) {
        throw new RuntimeException('Unable to generate TLS test CSR');
    }

    $cert = openssl_csr_sign(
        $csr,
        null,
        $key,
        2,
        ['config' => $configPath, 'digest_alg' => 'sha256', 'x509_extensions' => 'v3_req']
    );
    if ($cert === false) {
        throw new RuntimeException('Unable to sign TLS test certificate');
    }
    if (!openssl_x509_export($cert, $certPem) || !openssl_pkey_export($key, $keyPem, null, ['config' => $configPath])) {
        throw new RuntimeException('Unable to export TLS test certificate');
    }

    $caPath = $dir . '/ca.pem';
    $serverPath = $dir . '/server.pem';
    file_put_contents($caPath, $certPem);
    file_put_contents($serverPath, $certPem . $keyPem);

    return ['dir' => $dir, 'ca' => $caPath, 'server' => $serverPath];
};

$removeDirectory = static function (string $directory) use (&$removeDirectory): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . '/' . $entry;
        is_dir($path) ? $removeDirectory($path) : @unlink($path);
    }
    @rmdir($directory);
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
    'receive-pack client filters response statuses to requested refs like send-pack' => static function (TestRunner $t) use ($packet, $flush, $streamWith): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress expected ref status payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02remote: validating deployment refs\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/ghost ignored by send-pack\n"))
            . $packet("\x01" . $packet("ng refs/heads/main stale lock\n"))
            . $packet("\x01" . $packet("ok refs/heads/main post-update hook accepted\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $client = new ReceivePackClient(
            new StreamReceivePackTransport($streamWith($advertisement . $responseBytes), $streamWith('')),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());

        $response = $client->send($session->buildRequest([$blob]));

        $t->same(true, $response->isSuccessful());
        $t->same(1, count($response->refStatuses()));
        $t->same('refs/heads/main', $response->refStatuses()[0]->refName);
        $t->same('ok', $response->refStatuses()[0]->status);
        $t->same('post-update hook accepted', $response->refStatuses()[0]->message);
        $t->same([], $response->rejectedRefs());
        $t->same(['remote: validating deployment refs'], $response->progressMessages());
    },
    'receive-pack client preserves repeated proc-receive reports for one requested ref' => static function (TestRunner $t) use ($packet, $flush, $streamWith): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $siteAOld = str_repeat('6', 40);
        $siteANew = str_repeat('7', 40);
        $siteBOld = str_repeat('8', 40);
        $siteBNew = str_repeat('9', 40);
        $blob = new GitObject('blob', 'WordPress proc-receive multi-report payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status-v2 side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02remote: proc-receive expanded deployment refs\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/for/wp-deploy\n"))
            . $packet("\x01" . $packet("option refname refs/heads/site-a\n"))
            . $packet("\x01" . $packet("option old-oid {$siteAOld}\n"))
            . $packet("\x01" . $packet("option new-oid {$siteANew}\n"))
            . $packet("\x01" . $packet("ok refs/for/wp-deploy\n"))
            . $packet("\x01" . $packet("option refname refs/heads/site-b\n"))
            . $packet("\x01" . $packet("option old-oid {$siteBOld}\n"))
            . $packet("\x01" . $packet("option new-oid {$siteBNew}\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $client = new ReceivePackClient(
            new StreamReceivePackTransport($streamWith($advertisement . $responseBytes), $streamWith('')),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/for/wp-deploy', $blob->oid());

        $response = $client->send($session->buildRequest([$blob]));

        $t->same(true, $response->isSuccessful());
        $t->same(2, count($response->refStatuses()));
        $t->same(['remote: proc-receive expanded deployment refs'], $response->progressMessages());
        $t->same('refs/heads/site-a', $response->refStatuses()[0]->effectiveRefName());
        $t->same($siteAOld, $response->refStatuses()[0]->oldObject);
        $t->same($siteANew, $response->refStatuses()[0]->newObject);
        $t->same('refs/heads/site-b', $response->refStatuses()[1]->effectiveRefName());
        $t->same($siteBOld, $response->refStatuses()[1]->oldObject);
        $t->same($siteBNew, $response->refStatuses()[1]->newObject);
        $t->same([], $response->rejectedRefs());
    },
    'receive-pack client marks missing requested status refs as remote failures' => static function (TestRunner $t) use ($packet, $flush, $streamWith): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress missing status payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02remote: accepted pack without ref report\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/ghost ignored by send-pack\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $client = new ReceivePackClient(
            new StreamReceivePackTransport($streamWith($advertisement . $responseBytes), $streamWith('')),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());

        $response = $client->send($session->buildRequest([$blob]));

        $t->same(false, $response->isSuccessful());
        $t->same(1, count($response->refStatuses()));
        $t->same('refs/heads/main', $response->refStatuses()[0]->refName);
        $t->same(PushRefStatus::REJECTED, $response->refStatuses()[0]->status);
        $t->same('remote failed to report status', $response->refStatuses()[0]->message);
        $t->same('refs/heads/main', $response->rejectedRefs()[0]->refName);
        $t->same(['remote: accepted pack without ref report'], $response->progressMessages());
    },
    'receive-pack client rejects options after unrequested status refs like send-pack' => static function (TestRunner $t) use ($packet, $flush, $streamWith): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress unexpected status option payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status-v2 side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02remote: validating deployment refs\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $packet("ok refs/heads/ghost ignored by send-pack\n"))
            . $packet("\x01" . $packet("option refname refs/heads/other\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $client = new ReceivePackClient(
            new StreamReceivePackTransport($streamWith($advertisement . $responseBytes), $streamWith('')),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());

        $t->throws(InvalidArgumentException::class, static fn () => $client->send($session->buildRequest([$blob])));
    },
    'receive-pack client treats sideband error as fatal even after status report' => static function (TestRunner $t) use ($packet, $flush, $streamWith, $streamBytes): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress fatal sideband after status payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02remote: validating deployment refs\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x03pre-receive hook declined after status\n")
            . $packet("\x01" . $flush)
            . $flush;
        $write = $streamWith('');
        $client = new ReceivePackClient(
            new StreamReceivePackTransport($streamWith($advertisement . $responseBytes), $write),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());

        try {
            $client->send($session->buildRequest([$blob]));
        } catch (RuntimeException $exception) {
            $t->contains('sideband error pre-receive hook declined after status', $exception->getMessage());
            $t->contains('refs/heads/main', $streamBytes($write));

            return;
        }

        throw new RuntimeException('Expected receive-pack sideband error to be fatal');
    },
    'stream receive-pack transport reports watchdog timeout while reading packet length' => static function (TestRunner $t): void {
        if (!function_exists('stream_socket_pair')) {
            throw new RuntimeException('stream_socket_pair is required for stream timeout watchdog tests');
        }

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new RuntimeException('Unable to create stream socket pair');
        }

        [$read, $write] = $pair;
        stream_set_timeout($read, 0, 10_000);
        $transport = new StreamReceivePackTransport($read, $write);

        try {
            try {
                $transport->readAdvertisement();
                throw new RuntimeException('Expected stream timeout watchdog exception');
            } catch (RuntimeException $exception) {
                $t->contains('timed out while reading advertisement packet length', $exception->getMessage());
            }
        } finally {
            fclose($read);
            fclose($write);
        }
    },
    'stream receive-pack transport reports watchdog timeout while reading packet payload' => static function (TestRunner $t): void {
        if (!function_exists('stream_socket_pair')) {
            throw new RuntimeException('stream_socket_pair is required for stream timeout watchdog tests');
        }

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new RuntimeException('Unable to create stream socket pair');
        }

        [$read, $write] = $pair;
        fwrite($write, '0008ab');
        stream_set_timeout($read, 0, 10_000);
        $transport = new StreamReceivePackTransport($read, $write);

        try {
            try {
                $transport->readAdvertisement();
                throw new RuntimeException('Expected stream timeout watchdog exception');
            } catch (RuntimeException $exception) {
                $t->contains('timed out while reading advertisement packet payload', $exception->getMessage());
            }
        } finally {
            fclose($read);
            fclose($write);
        }
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

        $packetWithValueOnlyExtra = GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ['version=2', 'value-only', 'key=value']);
        $t->same("git-receive-pack /repo.git\0host=example.test\0\0version=2\0value-only\0key=value\0", substr($packetWithValueOnlyExtra, 4));

        $ipv6Packet = GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', '2001:db8::1', null, ['version=2']);
        $t->same("git-receive-pack /repo.git\0host=[2001:db8::1]\0\0version=2\0", substr($ipv6Packet, 4));

        $ipv6PortPacket = GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', '[2001:db8::1]', 9440);
        $t->same("git-receive-pack /repo.git\0host=[2001:db8::1]:9440\0", substr($ipv6PortPacket, 4));

        $urlPacket = GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://example.test:9440/repo.git', ['version=2']);
        $t->same("git-receive-pack /repo.git\0host=example.test:9440\0\0version=2\0", substr($urlPacket, 4));

        $urlPacketWithValueOnlyExtra = GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://example.test/repo.git', ['value-only', 'key=value']);
        $t->same("git-receive-pack /repo.git\0host=example.test\0\0value-only\0key=value\0", substr($urlPacketWithValueOnlyExtra, 4));

        $protocolV2Packet = GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, [], 2);
        $t->same("git-receive-pack /repo.git\0host=example.test\0\0version=2\0", substr($protocolV2Packet, 4));

        $protocolV2ExtraPacket = GitDaemonReceivePackTransport::serviceRequestBytes('/~/repo.git', 'example.test', null, ['session-id', 'object-format=sha1'], 2);
        $t->same("git-receive-pack ~/repo.git\0host=example.test\0\0version=2\0session-id\0object-format=sha1\0", substr($protocolV2ExtraPacket, 4));

        $namedHomeUrlPacket = GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://example.test/%7Edeploy/repo.git', ['session-id'], 2);
        $t->same("git-receive-pack ~deploy/repo.git\0host=example.test\0\0version=2\0session-id\0", substr($namedHomeUrlPacket, 4));

        $encodedUrlPacket = GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://git%2Dmirror.example.test/wp%2Dcontent.git', ['version=2']);
        $t->same("git-receive-pack /wp-content.git\0host=git-mirror.example.test\0\0version=2\0", substr($encodedUrlPacket, 4));

        $ipv6UrlPacket = GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://[2001:db8::1]/repo.git');
        $t->same("git-receive-pack /repo.git\0host=[2001:db8::1]\0", substr($ipv6UrlPacket, 4));

        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::connect('https://example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::connect('git://example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('https://example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://user@example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://example.test/repo.git?service=git-upload-pack'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://example.test/'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://example.test/%0arepo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://bad%0ahost.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://bad%20host.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://bad%2fhost.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', ''));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'bad host.example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'bad/host.example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('repo.git', 'example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes("/bad\0repo", 'example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes("/bad\nrepo", 'example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', "example.test\r"));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', 0));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ["bad\0extra"]));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ["bad\nextra"]));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ['=2']));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ['1bad']));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ['bad=']));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, ['bad key=1']));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytes('/repo.git', 'example.test', null, [], 0));
        $t->throws(InvalidArgumentException::class, static fn () => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://example.test/repo.git', [], 3));
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
    'smart http receive-pack accepts advertisement without service announcement' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress smart HTTP optional service announcement payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $requests = [];
        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$requests, $advertisement, $responseBytes): array {
                    $requests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $advertisement,
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
            ),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);

        $t->same(true, $response->isSuccessful());
        $t->same('refs/heads/main', $response->refStatuses()[0]->effectiveRefName());
        $t->same(['GET', 'POST'], array_column($requests, 'method'));
        $t->same('https://git.example.test/wp-content.git/info/refs?service=git-receive-pack', $requests[0]['url']);
        $t->same(null, $requests[0]['body']);
        $t->same('https://git.example.test/wp-content.git/git-receive-pack', $requests[1]['url']);
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

        $cleartextRequesterCalls = 0;
        $cleartextCredentials = new SmartHttpReceivePackTransport(
            'http://deploy:s3cret@example.test/repo.git',
            static function () use (&$cleartextRequesterCalls): array {
                $cleartextRequesterCalls++;

                return ['status' => 500, 'headers' => [], 'body' => ''];
            }
        );
        $t->throws(RuntimeException::class, static fn () => $cleartextCredentials->readAdvertisement());
        $t->same(0, $cleartextRequesterCalls);

        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('git://example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl("https://example.test/repo.git\n"));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl("https://example.test/repo.git\t"));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl("https://example.test/repo.git\x7f"));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://example.test/repo.git#refs'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://bad%0auser@example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://deploy:bad%0dtoken@example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://bad%09user@example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://deploy:bad%7ftoken@example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://bad%20host.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://bad%2fhost.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://bad%5chost.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://example.test/wp%0acontent.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SmartHttpReceivePackTransport::infoRefsUrl('https://example.test/repo.git?service=%0dgit-receive-pack'));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, ['bad:param']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, ["version\t=1"]));

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
                        'Set-Cookie' => ['wp_session=abc123; Path=/; HttpOnly', 'deploy=blue; Path=/; Secure', 'info_only=skip; Secure'],
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
        $t->same(false, str_contains($requests[1]['headers']['Cookie'], 'info_only='));
        $t->same($request->requestBytes(), $requests[1]['body']);
        $t->same(9.5, $requests[1]['timeout']);

        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, ['Content-Type' => 'text/plain']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, ["Bad\nHeader" => 'x']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, ['User-Agent' => "bad\nvalue"]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, ['User-Agent' => "bad\tvalue"]));
    },
    'smart http receive-pack follows safe initial redirects and reuses effective base' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress smart HTTP redirect payload');
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

            if (count($requests) === 1) {
                return [
                    'status' => 301,
                    'headers' => [
                        'Location' => 'https://git.example.test/redirected.git/info/refs?service=git-receive-pack',
                        'Set-Cookie' => 'redirect_gate=opened; Path=/; Secure',
                    ],
                    'body' => '',
                ];
            }

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => ['redirected_session=ok; Path=/; Secure', 'redirected_info_only=skip; Secure'],
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
                'http://git.example.test/wp-content.git',
                $requester,
                ['version=1'],
                7.0,
                ['User-Agent' => 'port-libs-test/redirect']
            ),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);

        $t->same(true, $response->isSuccessful());
        $t->same(3, count($requests));
        $t->same('http://git.example.test/wp-content.git/info/refs?service=git-receive-pack', $requests[0]['url']);
        $t->same('https://git.example.test/redirected.git/info/refs?service=git-receive-pack', $requests[1]['url']);
        $t->same('https://git.example.test/redirected.git/git-receive-pack', $requests[2]['url']);
        $t->same(null, $requests[0]['headers']['Authorization'] ?? null);
        $t->same(null, $requests[1]['headers']['Authorization'] ?? null);
        $t->same(null, $requests[2]['headers']['Authorization'] ?? null);
        $t->same('redirect_gate=opened', $requests[1]['headers']['Cookie']);
        $t->same('redirect_gate=opened; redirected_session=ok', $requests[2]['headers']['Cookie']);
        $t->same(false, str_contains($requests[2]['headers']['Cookie'], 'redirected_info_only='));
        $t->same('version=1', $requests[2]['headers']['Git-Protocol']);
        $t->same($request->requestBytes(), $requests[2]['body']);

        $noRedirects = new SmartHttpReceivePackTransport(
            'http://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 301,
                'headers' => ['Location' => 'https://git.example.test/redirected.git/info/refs?service=git-receive-pack'],
                'body' => '',
            ],
            [],
            30.0,
            [],
            ['followRedirects' => false]
        );
        $t->throws(RuntimeException::class, static fn () => $noRedirects->readAdvertisement());

        $postRedirectRequests = [];
        $postRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$postRedirectRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $postRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($postRedirectRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'https://git.example.test/redirected.git/git-receive-pack',
                                'Set-Cookie' => 'post_redirect_gate=opened; Path=/; Secure',
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                ['User-Agent' => 'port-libs-test/redirect-all'],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $postRedirectSession = $postRedirectClient->handshake();
        $postRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $postRedirectRequest = $postRedirectSession->buildRequest([$blob]);

        $postRedirectResponse = $postRedirectClient->send($postRedirectRequest);

        $t->same(true, $postRedirectResponse->isSuccessful());
        $t->same(3, count($postRedirectRequests));
        $t->same('https://git.example.test/wp-content.git/git-receive-pack', $postRedirectRequests[1]['url']);
        $t->same('https://git.example.test/redirected.git/git-receive-pack', $postRedirectRequests[2]['url']);
        $t->same('POST', $postRedirectRequests[2]['method']);
        $t->same('post_redirect_gate=opened', $postRedirectRequests[2]['headers']['Cookie']);
        $t->same($postRedirectRequest->requestBytes(), $postRedirectRequests[2]['body']);

        $callerCookieRedirectRequests = [];
        $callerCookieRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$callerCookieRedirectRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $callerCookieRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($callerCookieRedirectRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'https://git.example.test/redirected.git/git-receive-pack',
                                'Set-Cookie' => 'redirect_gate=opened; Path=/; Secure',
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                ['Cookie' => 'wp_logged_in=editor; wp_nonce=abc'],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $callerCookieRedirectSession = $callerCookieRedirectClient->handshake();
        $callerCookieRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $callerCookieRedirectRequest = $callerCookieRedirectSession->buildRequest([$blob]);

        $callerCookieRedirectResponse = $callerCookieRedirectClient->send($callerCookieRedirectRequest);

        $t->same(true, $callerCookieRedirectResponse->isSuccessful());
        $t->same('wp_logged_in=editor; wp_nonce=abc', $callerCookieRedirectRequests[1]['headers']['Cookie']);
        $t->same(
            'wp_logged_in=editor; wp_nonce=abc; redirect_gate=opened',
            $callerCookieRedirectRequests[2]['headers']['Cookie']
        );
        $t->same($callerCookieRedirectRequest->requestBytes(), $callerCookieRedirectRequests[2]['body']);

        $maxAgePrecedenceRequests = [];
        $maxAgePrecedenceClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$maxAgePrecedenceRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $maxAgePrecedenceRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($maxAgePrecedenceRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'https://git.example.test/redirected.git/git-receive-pack',
                                'Set-Cookie' => [
                                    'legacy_gate=opened; Max-Age=60; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; Secure',
                                    'expired_gate=closed; Max-Age=0; Expires=Wed, 31 Dec 2099 23:59:59 GMT; Path=/; Secure',
                                    'admin_gate=closed; Path=/wp-admin; Secure',
                                    'foreign_gate=closed; Domain=example.org; Path=/; Secure',
                                ],
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $maxAgePrecedenceSession = $maxAgePrecedenceClient->handshake();
        $maxAgePrecedenceSession->createOrUpdate('refs/heads/main', $blob->oid());
        $maxAgePrecedenceRequest = $maxAgePrecedenceSession->buildRequest([$blob]);

        $maxAgePrecedenceResponse = $maxAgePrecedenceClient->send($maxAgePrecedenceRequest);

        $t->same(true, $maxAgePrecedenceResponse->isSuccessful());
        $t->same('legacy_gate=opened', $maxAgePrecedenceRequests[2]['headers']['Cookie']);
        $t->same(false, str_contains($maxAgePrecedenceRequests[2]['headers']['Cookie'], 'expired_gate='));
        $t->same(false, str_contains($maxAgePrecedenceRequests[2]['headers']['Cookie'], 'admin_gate='));
        $t->same(false, str_contains($maxAgePrecedenceRequests[2]['headers']['Cookie'], 'foreign_gate='));
        $t->same($maxAgePrecedenceRequest->requestBytes(), $maxAgePrecedenceRequests[2]['body']);

        $secureCookieDowngradeRequests = [];
        $secureCookieDowngradeClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'http://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$secureCookieDowngradeRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $secureCookieDowngradeRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($secureCookieDowngradeRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'http://git.example.test/redirected.git/git-receive-pack',
                                'Set-Cookie' => [
                                    'plain_redirect_gate=opened; Path=/',
                                    'secure_redirect_gate=closed; Path=/; Secure',
                                ],
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $secureCookieDowngradeSession = $secureCookieDowngradeClient->handshake();
        $secureCookieDowngradeSession->createOrUpdate('refs/heads/main', $blob->oid());
        $secureCookieDowngradeRequest = $secureCookieDowngradeSession->buildRequest([$blob]);

        $secureCookieDowngradeResponse = $secureCookieDowngradeClient->send($secureCookieDowngradeRequest);

        $t->same(true, $secureCookieDowngradeResponse->isSuccessful());
        $t->same('plain_redirect_gate=opened', $secureCookieDowngradeRequests[2]['headers']['Cookie']);
        $t->same(false, str_contains($secureCookieDowngradeRequests[2]['headers']['Cookie'], 'secure_redirect_gate='));
        $t->same($secureCookieDowngradeRequest->requestBytes(), $secureCookieDowngradeRequests[2]['body']);

        $defaultPathRedirectRequests = [];
        $defaultPathRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$defaultPathRedirectRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $defaultPathRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($defaultPathRedirectRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'https://git.example.test/redirected.git/git-receive-pack',
                                'Set-Cookie' => [
                                    'redirect_default_gate=closed; Secure',
                                    'redirect_root_gate=opened; Path=/; Secure',
                                ],
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $defaultPathRedirectSession = $defaultPathRedirectClient->handshake();
        $defaultPathRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $defaultPathRedirectRequest = $defaultPathRedirectSession->buildRequest([$blob]);

        $defaultPathRedirectResponse = $defaultPathRedirectClient->send($defaultPathRedirectRequest);

        $t->same(true, $defaultPathRedirectResponse->isSuccessful());
        $t->same('redirect_root_gate=opened', $defaultPathRedirectRequests[2]['headers']['Cookie']);
        $t->same(false, str_contains($defaultPathRedirectRequests[2]['headers']['Cookie'], 'redirect_default_gate='));
        $t->same($defaultPathRedirectRequest->requestBytes(), $defaultPathRedirectRequests[2]['body']);

        $sameNameScopedRequests = [];
        $sameNameScopedClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$sameNameScopedRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $sameNameScopedRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($sameNameScopedRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'https://git.example.test/redirected.git/git-receive-pack',
                                'Set-Cookie' => [
                                    'deploy_gate=root; Path=/; Secure',
                                    'deploy_gate=admin; Path=/wp-admin; Secure',
                                    'deploy_gate=; Max-Age=0; Path=/wp-admin; Secure',
                                ],
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $sameNameScopedSession = $sameNameScopedClient->handshake();
        $sameNameScopedSession->createOrUpdate('refs/heads/main', $blob->oid());
        $sameNameScopedRequest = $sameNameScopedSession->buildRequest([$blob]);

        $sameNameScopedResponse = $sameNameScopedClient->send($sameNameScopedRequest);

        $t->same(true, $sameNameScopedResponse->isSuccessful());
        $t->same('deploy_gate=root', $sameNameScopedRequests[2]['headers']['Cookie']);
        $t->same(false, str_contains($sameNameScopedRequests[2]['headers']['Cookie'], 'deploy_gate=admin'));
        $t->same($sameNameScopedRequest->requestBytes(), $sameNameScopedRequests[2]['body']);

        $pathSpecificOrderRequests = [];
        $pathSpecificOrderClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$pathSpecificOrderRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $pathSpecificOrderRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($pathSpecificOrderRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'https://git.example.test/redirected.git/git-receive-pack',
                                'Set-Cookie' => [
                                    'deploy_gate=root; Path=/; Secure',
                                    'deploy_gate=redirect; Path=/redirected.git; Secure',
                                    'spaced_path_gate=redirect; Path= /redirected.git ; Secure',
                                    'deploy_gate=receive; Path=/redirected.git/git-receive-pack; Secure',
                                    'replace_gate=stale; Path=/redirected.git; Secure',
                                    'replace_gate=fresh; Path=/redirected.git; Secure',
                                ],
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $pathSpecificOrderSession = $pathSpecificOrderClient->handshake();
        $pathSpecificOrderSession->createOrUpdate('refs/heads/main', $blob->oid());
        $pathSpecificOrderRequest = $pathSpecificOrderSession->buildRequest([$blob]);

        $pathSpecificOrderResponse = $pathSpecificOrderClient->send($pathSpecificOrderRequest);

        $t->same(true, $pathSpecificOrderResponse->isSuccessful());
        $t->same(
            'deploy_gate=receive; deploy_gate=redirect; spaced_path_gate=redirect; replace_gate=fresh; deploy_gate=root',
            $pathSpecificOrderRequests[2]['headers']['Cookie']
        );
        $t->same(false, str_contains($pathSpecificOrderRequests[2]['headers']['Cookie'], 'replace_gate=stale'));
        $t->same($pathSpecificOrderRequest->requestBytes(), $pathSpecificOrderRequests[2]['body']);

        $chainedRedirectRequests = [];
        $chainedRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$chainedRedirectRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $chainedRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => [
                                'Content-Type' => 'application/x-git-receive-pack-advertisement',
                                'Set-Cookie' => 'repo_gate=repo; Path=/wp-content.git; Secure',
                            ],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($chainedRedirectRequests) === 2) {
                        return [
                            'status' => 307,
                            'headers' => [
                                'Location' => 'https://git.example.test/gate-one.git/git-receive-pack',
                                'Set-Cookie' => [
                                    'gate_one=one; Path=/gate-one.git; Secure',
                                    'stale_repo_gate=closed; Path=/wp-content.git; Secure',
                                ],
                            ],
                            'body' => '',
                        ];
                    }

                    if (count($chainedRedirectRequests) === 3) {
                        return [
                            'status' => 308,
                            'headers' => [
                                'Location' => 'https://git.example.test/gate-two.git/git-receive-pack',
                                'Set-Cookie' => 'gate_two=two; Path=/gate-two.git; Secure',
                            ],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                ['Cookie' => 'wp_logged_in=editor'],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $chainedRedirectSession = $chainedRedirectClient->handshake();
        $chainedRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $chainedRedirectRequest = $chainedRedirectSession->buildRequest([$blob]);

        $chainedRedirectResponse = $chainedRedirectClient->send($chainedRedirectRequest);

        $t->same(true, $chainedRedirectResponse->isSuccessful());
        $t->same(4, count($chainedRedirectRequests));
        $t->same('wp_logged_in=editor; repo_gate=repo', $chainedRedirectRequests[1]['headers']['Cookie']);
        $t->same('wp_logged_in=editor; gate_one=one', $chainedRedirectRequests[2]['headers']['Cookie']);
        $t->same(false, str_contains($chainedRedirectRequests[2]['headers']['Cookie'], 'repo_gate='));
        $t->same(false, str_contains($chainedRedirectRequests[2]['headers']['Cookie'], 'stale_repo_gate='));
        $t->same('wp_logged_in=editor; gate_two=two', $chainedRedirectRequests[3]['headers']['Cookie']);
        $t->same(false, str_contains($chainedRedirectRequests[3]['headers']['Cookie'], 'gate_one='));
        $t->same(false, str_contains($chainedRedirectRequests[3]['headers']['Cookie'], 'repo_gate='));
        $t->same($chainedRedirectRequest->requestBytes(), $chainedRedirectRequests[3]['body']);

        $relativePermanentRedirectRequests = [];
        $relativePermanentRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$relativePermanentRedirectRequests, $packet, $flush, $advertisement, $responseBytes): array {
                    $relativePermanentRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    if (count($relativePermanentRedirectRequests) === 2) {
                        return [
                            'status' => 308,
                            'headers' => ['Location' => '/redirected.git/git-receive-pack'],
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                7.0,
                ['User-Agent' => 'port-libs-test/redirect-relative'],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $relativePermanentRedirectSession = $relativePermanentRedirectClient->handshake();
        $relativePermanentRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $relativePermanentRedirectRequest = $relativePermanentRedirectSession->buildRequest([$blob]);

        $relativePermanentRedirectResponse = $relativePermanentRedirectClient->send($relativePermanentRedirectRequest);

        $t->same(true, $relativePermanentRedirectResponse->isSuccessful());
        $t->same(3, count($relativePermanentRedirectRequests));
        $t->same('https://git.example.test/redirected.git/git-receive-pack', $relativePermanentRedirectRequests[2]['url']);
        $t->same('POST', $relativePermanentRedirectRequests[2]['method']);
        $t->same($relativePermanentRedirectRequest->requestBytes(), $relativePermanentRedirectRequests[2]['body']);

        $rewritingPostRedirectRequests = [];
        $rewritingPostRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$rewritingPostRedirectRequests, $packet, $flush, $advertisement): array {
                    $rewritingPostRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    return [
                        'status' => 302,
                        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack'],
                        'body' => '',
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $rewritingPostRedirectSession = $rewritingPostRedirectClient->handshake();
        $rewritingPostRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $rewritingPostRedirectRequest = $rewritingPostRedirectSession->buildRequest([$blob]);

        $t->throws(RuntimeException::class, static fn () => $rewritingPostRedirectClient->send($rewritingPostRedirectRequest));
        $t->same(['GET', 'POST'], array_column($rewritingPostRedirectRequests, 'method'));
        $t->same($rewritingPostRedirectRequest->requestBytes(), $rewritingPostRedirectRequests[1]['body']);

        $permanentPostRedirectRequests = [];
        $permanentPostRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$permanentPostRedirectRequests, $packet, $flush, $advertisement): array {
                    $permanentPostRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    return [
                        'status' => 301,
                        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack'],
                        'body' => '',
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $permanentPostRedirectSession = $permanentPostRedirectClient->handshake();
        $permanentPostRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $permanentPostRedirectRequest = $permanentPostRedirectSession->buildRequest([$blob]);

        $t->throws(RuntimeException::class, static fn () => $permanentPostRedirectClient->send($permanentPostRedirectRequest));
        $t->same(['GET', 'POST'], array_column($permanentPostRedirectRequests, 'method'));
        $t->same($permanentPostRedirectRequest->requestBytes(), $permanentPostRedirectRequests[1]['body']);

        $seeOtherPostRedirectRequests = [];
        $seeOtherPostRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$seeOtherPostRedirectRequests, $packet, $flush, $advertisement): array {
                    $seeOtherPostRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    return [
                        'status' => 303,
                        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack'],
                        'body' => '',
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $seeOtherPostRedirectSession = $seeOtherPostRedirectClient->handshake();
        $seeOtherPostRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $seeOtherPostRedirectRequest = $seeOtherPostRedirectSession->buildRequest([$blob]);

        $t->throws(RuntimeException::class, static fn () => $seeOtherPostRedirectClient->send($seeOtherPostRedirectRequest));
        $t->same(['GET', 'POST'], array_column($seeOtherPostRedirectRequests, 'method'));

        $redirectFixture = require dirname(__DIR__) . '/fixtures/wordpress-smart-http-follow-redirects.php';
        $redirectExample = require dirname(__DIR__) . '/examples/wordpress-smart-http-follow-redirects.php';
        $t->same(['GET', 'POST', 'POST'], $redirectExample['requestMethods']);
        $t->same([
            'https://git.example.test/wp-content.git/info/refs?service=git-receive-pack',
            'https://git.example.test/wp-content.git/git-receive-pack',
            'https://git.example.test/redirected.git/git-receive-pack',
        ], $redirectExample['requestUrls']);
        $t->same(true, $redirectFixture['postBodyPreserved']);
        $t->contains('deploy_gate=opened', $redirectExample['redirectCookieHeader']);
        $t->same(true, $redirectExample['expiredRedirectCookieOmitted']);
        $t->same(true, $redirectExample['maxAgeRedirectCookieRetained']);
        $t->same(true, $redirectExample['pathScopedRedirectCookieOmitted']);
        $t->same(true, $redirectExample['foreignDomainRedirectCookieOmitted']);
        $t->same(true, $redirectExample['malformedPathRedirectCookiesOmitted']);
        $t->same(true, $redirectExample['secureCookiePlainRedirectOmitted']);
        $t->same(true, $redirectExample['defaultPathRedirectCookieOmitted']);
        $t->same(true, $redirectExample['postRedirectDefaultPathCookieOmitted']);
        $t->same(true, $redirectExample['redirectChainCookiesRecomputed']);
        $t->same(['GET', 'POST', 'POST', 'POST'], $redirectExample['redirectChainRequestMethods']);
        $t->same('wp_logged_in=editor; gate_one=one', $redirectExample['redirectChainFirstRetryCookieHeader']);
        $t->same('wp_logged_in=editor; gate_two=two', $redirectExample['redirectChainFinalCookieHeader']);
        $t->same(true, $redirectExample['sameNameScopedRedirectCookieRetained']);
        $t->same(true, $redirectExample['sameScopeRedirectCookieReplaced']);
        $t->same(true, $redirectExample['callerCookieHeaderPreserved']);
        $t->same(true, $redirectExample['pathSpecificRedirectCookiesFirst']);
        $t->same(true, $redirectFixture['rewritingPostRedirectRejected']);
        $t->same(true, $redirectFixture['permanentPostRedirectRejected']);
        $t->same(true, $redirectFixture['seeOtherPostRedirectRejected']);
        $t->same(true, $redirectFixture['wrongEndpointPostRedirectRejected']);
        $t->same(true, $redirectFixture['credentialPostRedirectRejected']);
        $t->same(true, $redirectFixture['missingLocationPostRedirectRejected']);
        $t->same(['GET', 'POST'], $redirectExample['rewritingRequestMethods']);
        $t->same(['GET', 'POST'], $redirectExample['permanentRequestMethods']);
        $t->same(['GET', 'POST'], $redirectExample['seeOtherRequestMethods']);
        $t->same(['GET', 'POST'], $redirectExample['wrongEndpointRequestMethods']);
        $t->same(['GET', 'POST'], $redirectExample['credentialRequestMethods']);
        $t->same(['GET', 'POST'], $redirectExample['fragmentRequestMethods']);
        $t->same(['GET', 'POST'], $redirectExample['missingLocationRequestMethods']);
        $t->same(true, $redirectExample['responseSuccessful']);

        $crossHost = new SmartHttpReceivePackTransport(
            'https://deploy:s3cret@git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 302,
                'headers' => ['Location' => 'https://attacker.example.test/wp-content.git/info/refs?service=git-receive-pack'],
                'body' => '',
            ]
        );
        $t->throws(RuntimeException::class, static fn () => $crossHost->readAdvertisement());

        $downgrade = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 302,
                'headers' => ['Location' => 'http://git.example.test/wp-content.git/info/refs?service=git-receive-pack'],
                'body' => '',
            ]
        );
        $t->throws(RuntimeException::class, static fn () => $downgrade->readAdvertisement());

        $encodedControlPathRedirect = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 302,
                'headers' => ['Location' => 'https://git.example.test/wp%0acontent.git/info/refs?service=git-receive-pack'],
                'body' => '',
            ]
        );
        $t->throws(InvalidArgumentException::class, static fn () => $encodedControlPathRedirect->readAdvertisement());

        $encodedControlQueryRedirect = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 302,
                'headers' => ['Location' => 'https://git.example.test/wp-content.git/info/refs?service=%0dgit-receive-pack'],
                'body' => '',
            ]
        );
        $t->throws(InvalidArgumentException::class, static fn () => $encodedControlQueryRedirect->readAdvertisement());

        $rawTabRedirect = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 302,
                'headers' => ['Location' => "https://git.example.test/wp-content.git/info/refs?service=git-receive-pack\t"],
                'body' => '',
            ]
        );
        $t->throws(RuntimeException::class, static fn () => $rawTabRedirect->readAdvertisement());

        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['followRedirects' => 'sometimes']));

        $wrongEndpointPostRedirectRequests = [];
        $wrongEndpointPostRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$wrongEndpointPostRedirectRequests, $packet, $flush, $advertisement): array {
                    $wrongEndpointPostRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    return [
                        'status' => 307,
                        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-upload-pack'],
                        'body' => '',
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $wrongEndpointPostRedirectSession = $wrongEndpointPostRedirectClient->handshake();
        $wrongEndpointPostRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $wrongEndpointPostRedirectRequest = $wrongEndpointPostRedirectSession->buildRequest([$blob]);

        $t->throws(RuntimeException::class, static fn () => $wrongEndpointPostRedirectClient->send($wrongEndpointPostRedirectRequest));
        $t->same(['GET', 'POST'], array_column($wrongEndpointPostRedirectRequests, 'method'));
        $t->same($wrongEndpointPostRedirectRequest->requestBytes(), $wrongEndpointPostRedirectRequests[1]['body']);

        $credentialPostRedirectRequests = [];
        $credentialPostRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$credentialPostRedirectRequests, $packet, $flush, $advertisement): array {
                    $credentialPostRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    return [
                        'status' => 307,
                        'headers' => ['Location' => 'https://redirect-user:redirect-pass@git.example.test/redirected.git/git-receive-pack'],
                        'body' => '',
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $credentialPostRedirectSession = $credentialPostRedirectClient->handshake();
        $credentialPostRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $credentialPostRedirectRequest = $credentialPostRedirectSession->buildRequest([$blob]);

        $t->throws(RuntimeException::class, static fn () => $credentialPostRedirectClient->send($credentialPostRedirectRequest));
        $t->same(['GET', 'POST'], array_column($credentialPostRedirectRequests, 'method'));
        $t->same($credentialPostRedirectRequest->requestBytes(), $credentialPostRedirectRequests[1]['body']);

        $fragmentPostRedirectRequests = [];
        $fragmentPostRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$fragmentPostRedirectRequests, $packet, $flush, $advertisement): array {
                    $fragmentPostRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    return [
                        'status' => 307,
                        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack#pack'],
                        'body' => '',
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $fragmentPostRedirectSession = $fragmentPostRedirectClient->handshake();
        $fragmentPostRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $fragmentPostRedirectRequest = $fragmentPostRedirectSession->buildRequest([$blob]);

        $t->throws(RuntimeException::class, static fn () => $fragmentPostRedirectClient->send($fragmentPostRedirectRequest));
        $t->same(['GET', 'POST'], array_column($fragmentPostRedirectRequests, 'method'));
        $t->same($fragmentPostRedirectRequest->requestBytes(), $fragmentPostRedirectRequests[1]['body']);

        $missingLocationPostRedirectRequests = [];
        $missingLocationPostRedirectClient = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$missingLocationPostRedirectRequests, $packet, $flush, $advertisement): array {
                    $missingLocationPostRedirectRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
                        ];
                    }

                    return [
                        'status' => 307,
                        'headers' => [],
                        'body' => '',
                    ];
                },
                [],
                7.0,
                [],
                ['followRedirects' => true]
            ),
            'port-libs/0.1'
        );
        $missingLocationPostRedirectSession = $missingLocationPostRedirectClient->handshake();
        $missingLocationPostRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
        $missingLocationPostRedirectRequest = $missingLocationPostRedirectSession->buildRequest([$blob]);

        $t->throws(RuntimeException::class, static fn () => $missingLocationPostRedirectClient->send($missingLocationPostRedirectRequest));
        $t->same(['GET', 'POST'], array_column($missingLocationPostRedirectRequests, 'method'));
        $t->same($missingLocationPostRedirectRequest->requestBytes(), $missingLocationPostRedirectRequests[1]['body']);
    },
    'smart http receive-pack applies proxy options and credential helpers' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress smart HTTP proxy payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $requests = [];
        $requester = static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$requests, $packet, $flush, $advertisement, $responseBytes): array {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
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
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $responseBytes,
            ];
        };

        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                $requester,
                ['version=1'],
                6.0,
                [],
                [
                    'proxy' => 'http://proxy-user:proxy-pass@proxy.example.test:8080',
                    'noProxy' => 'localhost,.bypass.test',
                    'proxyAuthMethod' => 'digest',
                ]
            ),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);

        $t->same(true, $response->isSuccessful());
        $t->same('tcp://proxy.example.test:8080', $requests[0]['httpOptions']['proxy']);
        $t->same('http://proxy.example.test:8080', $requests[0]['httpOptions']['proxyUrl']);
        $t->same('http', $requests[0]['httpOptions']['proxyType']);
        $t->same(true, $requests[0]['httpOptions']['requestFullUri']);
        $t->same('digest', $requests[0]['httpOptions']['proxyAuthMethod']);
        $t->same('Basic ' . base64_encode('proxy-user:proxy-pass'), $requests[0]['httpOptions']['proxyAuthorization']);
        $t->same($requests[0]['httpOptions'], $requests[1]['httpOptions']);
        $t->same(null, $requests[0]['headers']['Proxy-Authorization'] ?? null);
        $t->same(null, $requests[1]['headers']['Proxy-Authorization'] ?? null);
        $t->same($request->requestBytes(), $requests[1]['body']);

        $directRequests = [];
        $directTransport = new SmartHttpReceivePackTransport(
            'https://git.bypass.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$directRequests, $packet, $flush): array {
                $directRequests[] = ['url' => $url, 'httpOptions' => $httpOptions];

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            [],
            30.0,
            [],
            ['proxy' => 'proxy.example.test:8080', 'noProxy' => ['.bypass.test']]
        );
        $directTransport->readAdvertisement();
        $t->same([], $directRequests[0]['httpOptions']);

        $defaultPortRequests = [];
        $defaultPortTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$defaultPortRequests, $packet, $flush): array {
                $defaultPortRequests[] = $httpOptions;

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            [],
            30.0,
            [],
            ['proxy' => 'proxy.example.test']
        );
        $defaultPortTransport->readAdvertisement();
        $t->same('tcp://proxy.example.test:80', $defaultPortRequests[0]['proxy']);

        $helperCalls = [];
        $helperRequests = [];
        $storedCredentials = [];
        $erasedCredentials = [];
        $helperTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$helperRequests, $packet, $flush): array {
                $helperRequests[] = $httpOptions;

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            [],
            30.0,
            [],
            [
                'proxy' => 'http://proxy.example.test:8080',
                'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$helperCalls): array {
                    $helperCalls[] = [$proxyUrl, $requestHost];

                    return ['username' => 'helper-user', 'password' => 'helper-pass'];
                },
                'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$storedCredentials): void {
                    $storedCredentials[] = [$proxyUrl, $requestHost, $credentials];
                },
                'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$erasedCredentials): void {
                    $erasedCredentials[] = [$proxyUrl, $requestHost, $credentials];
                },
            ]
        );
        $helperTransport->readAdvertisement();
        $t->same([['http://proxy.example.test:8080', 'git.example.test']], $helperCalls);
        $t->same('Basic ' . base64_encode('helper-user:helper-pass'), $helperRequests[0]['proxyAuthorization']);
        $t->same([['http://proxy.example.test:8080', 'git.example.test', ['username' => 'helper-user', 'password' => 'helper-pass']]], $storedCredentials);
        $t->same([], $erasedCredentials);

        $usernameOnlyProxyCalls = [];
        $usernameOnlyProxyRequests = [];
        $usernameOnlyProxyStores = [];
        $usernameOnlyProxyTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$usernameOnlyProxyRequests, $packet, $flush): array {
                $usernameOnlyProxyRequests[] = [
                    'headers' => $headers,
                    'httpOptions' => $httpOptions,
                ];

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            [],
            30.0,
            [],
            [
                'proxy' => 'http://proxy-user@proxy.example.test:8080',
                'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$usernameOnlyProxyCalls): array {
                    $usernameOnlyProxyCalls[] = [$proxyUrl, $requestHost];

                    return ['username' => 'proxy-user', 'password' => 'helper-secret'];
                },
                'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$usernameOnlyProxyStores): void {
                    $usernameOnlyProxyStores[] = [$proxyUrl, $requestHost, $credentials];
                },
            ]
        );
        $usernameOnlyProxyTransport->readAdvertisement();
        $t->same([['http://proxy-user@proxy.example.test:8080', 'git.example.test']], $usernameOnlyProxyCalls);
        $t->same('http://proxy-user@proxy.example.test:8080', $usernameOnlyProxyRequests[0]['httpOptions']['proxyUrl']);
        $t->same('Basic ' . base64_encode('proxy-user:helper-secret'), $usernameOnlyProxyRequests[0]['httpOptions']['proxyAuthorization']);
        $t->same(null, $usernameOnlyProxyRequests[0]['headers']['Proxy-Authorization'] ?? null);
        $t->same([['http://proxy-user@proxy.example.test:8080', 'git.example.test', ['username' => 'proxy-user', 'password' => 'helper-secret']]], $usernameOnlyProxyStores);

        $urlCredentialOverrideCalls = [];
        $urlCredentialOverrideRequests = [];
        $urlCredentialOverrideStores = [];
        $urlCredentialOverrideTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$urlCredentialOverrideRequests, $packet, $flush): array {
                $urlCredentialOverrideRequests[] = [
                    'headers' => $headers,
                    'httpOptions' => $httpOptions,
                ];

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            [],
            30.0,
            [],
            [
                'proxy' => 'http://url-user:url-pass@proxy.example.test:8080',
                'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$urlCredentialOverrideCalls): array {
                    $urlCredentialOverrideCalls[] = [$proxyUrl, $requestHost];

                    return ['username' => 'helper-user', 'password' => 'helper-pass'];
                },
                'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$urlCredentialOverrideStores): void {
                    $urlCredentialOverrideStores[] = [$proxyUrl, $requestHost, $credentials];
                },
            ]
        );
        $urlCredentialOverrideTransport->readAdvertisement();
        $t->same([['http://url-user:url-pass@proxy.example.test:8080', 'git.example.test']], $urlCredentialOverrideCalls);
        $t->same('http://proxy.example.test:8080', $urlCredentialOverrideRequests[0]['httpOptions']['proxyUrl']);
        $t->same('Basic ' . base64_encode('helper-user:helper-pass'), $urlCredentialOverrideRequests[0]['httpOptions']['proxyAuthorization']);
        $t->same(null, $urlCredentialOverrideRequests[0]['headers']['Proxy-Authorization'] ?? null);
        $t->same([['http://url-user:url-pass@proxy.example.test:8080', 'git.example.test', ['username' => 'helper-user', 'password' => 'helper-pass']]], $urlCredentialOverrideStores);

        $redirectHelperCalls = [];
        $redirectHelperRequests = [];
        $redirectHelperStores = [];
        $redirectHelperErasures = [];
        $redirectHelperTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$redirectHelperRequests, $packet, $flush): array {
                $redirectHelperRequests[] = [
                    'url' => $url,
                    'httpOptions' => $httpOptions,
                ];

                if (count($redirectHelperRequests) === 1) {
                    return [
                        'status' => 302,
                        'headers' => ['Location' => 'https://git.example.test/redirected.git/info/refs?service=git-receive-pack'],
                        'body' => '',
                    ];
                }

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            [],
            30.0,
            [],
            [
                'proxy' => 'http://proxy.example.test:8080',
                'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$redirectHelperCalls): array {
                    $redirectHelperCalls[] = [$proxyUrl, $requestHost];

                    return ['username' => 'redirect-user', 'password' => 'redirect-pass'];
                },
                'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$redirectHelperStores): void {
                    $redirectHelperStores[] = [$proxyUrl, $requestHost, $credentials];
                },
                'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$redirectHelperErasures): void {
                    $redirectHelperErasures[] = [$proxyUrl, $requestHost, $credentials];
                },
            ]
        );
        $redirectHelperTransport->readAdvertisement();
        $t->same([
            'https://git.example.test/wp-content.git/info/refs?service=git-receive-pack',
            'https://git.example.test/redirected.git/info/refs?service=git-receive-pack',
        ], array_column($redirectHelperRequests, 'url'));
        $t->same([['http://proxy.example.test:8080', 'git.example.test']], $redirectHelperCalls);
        $t->same($redirectHelperRequests[0]['httpOptions']['proxyAuthorization'], $redirectHelperRequests[1]['httpOptions']['proxyAuthorization']);
        $t->same('Basic ' . base64_encode('redirect-user:redirect-pass'), $redirectHelperRequests[1]['httpOptions']['proxyAuthorization']);
        $t->same([['http://proxy.example.test:8080', 'git.example.test', ['username' => 'redirect-user', 'password' => 'redirect-pass']]], $redirectHelperStores);
        $t->same([], $redirectHelperErasures);

        $redirectFailureCalls = 0;
        $redirectFailureErasures = [];
        $redirectFailureTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function () use (&$redirectFailureCalls): array {
                $redirectFailureCalls++;

                return $redirectFailureCalls === 1
                    ? ['status' => 302, 'headers' => ['Location' => 'https://git.example.test/redirected.git/info/refs?service=git-receive-pack'], 'body' => '']
                    : ['status' => 403, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'forbidden'];
            },
            [],
            30.0,
            [],
            [
                'proxy' => 'http://proxy.example.test:8080',
                'proxyCredentialHelper' => static fn (): array => ['username' => 'redirect-fail-user', 'password' => 'redirect-fail-pass'],
                'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$redirectFailureErasures): void {
                    $redirectFailureErasures[] = [$proxyUrl, $requestHost, $credentials];
                },
            ]
        );
        $t->throws(RuntimeException::class, static fn () => $redirectFailureTransport->readAdvertisement());
        $t->same([['http://proxy.example.test:8080', 'git.example.test', ['username' => 'redirect-fail-user', 'password' => 'redirect-fail-pass']]], $redirectFailureErasures);

        $failedErasures = [];
        $failedTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => ['status' => 407, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'proxy auth required'],
            [],
            30.0,
            [],
            [
                'proxy' => 'http://proxy.example.test:8080',
                'proxyCredentialHelper' => static fn (): array => ['username' => 'bad-user', 'password' => 'bad-pass'],
                'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$failedErasures): void {
                    $failedErasures[] = [$proxyUrl, $requestHost, $credentials];
                },
            ]
        );
        $t->throws(RuntimeException::class, static fn () => $failedTransport->readAdvertisement());
        $t->same([['http://proxy.example.test:8080', 'git.example.test', ['username' => 'bad-user', 'password' => 'bad-pass']]], $failedErasures);

        $unexpectedStatusStores = [];
        $unexpectedStatusErasures = [];
        $unexpectedStatusTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 204,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                'body' => '',
            ],
            [],
            30.0,
            [],
            [
                'proxy' => 'http://proxy.example.test:8080',
                'proxyCredentialHelper' => static fn (): array => ['username' => 'unexpected-user', 'password' => 'unexpected-pass'],
                'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$unexpectedStatusStores): void {
                    $unexpectedStatusStores[] = [$proxyUrl, $requestHost, $credentials];
                },
                'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$unexpectedStatusErasures): void {
                    $unexpectedStatusErasures[] = [$proxyUrl, $requestHost, $credentials];
                },
            ]
        );
        $t->throws(RuntimeException::class, static fn () => $unexpectedStatusTransport->readAdvertisement());
        $t->same([], $unexpectedStatusStores);
        $t->same([['http://proxy.example.test:8080', 'git.example.test', ['username' => 'unexpected-user', 'password' => 'unexpected-pass']]], $unexpectedStatusErasures);

        $helperControlByteTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => ['status' => 500, 'headers' => [], 'body' => 'should not run'],
            [],
            30.0,
            [],
            [
                'proxy' => 'http://proxy.example.test:8080',
                'proxyCredentialHelper' => static fn (): array => ['username' => 'bad-user', 'password' => "bad\tpass"],
            ]
        );
        $t->throws(InvalidArgumentException::class, static fn () => $helperControlByteTransport->readAdvertisement());

        $socksRequests = [];
        $socksTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$socksRequests, $packet, $flush): array {
                $socksRequests[] = $httpOptions;

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $packet("0000000000000000000000000000000000000000 capabilities^{}\0report-status\n") . $flush,
                ];
            },
            [],
            30.0,
            [],
            [
                'proxy' => 'socks5h://socks.example.test',
                'proxyAuthMethod' => 'gss-negotiate',
            ]
        );
        $socksTransport->readAdvertisement();
        $t->same('socks5h', $socksRequests[0]['proxyType']);
        $t->same('tcp://socks.example.test:1080', $socksRequests[0]['proxy']);
        $t->same('socks5h://socks.example.test:1080', $socksRequests[0]['proxyUrl']);
        $t->same(false, $socksRequests[0]['requestFullUri']);
        $t->same('negotiate', $socksRequests[0]['proxyAuthMethod']);

        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxy' => 'ftp://proxy.example.test:21']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxyAuthMethod' => 'bearer']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['noProxy' => "bad\nhost"]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxyCredentials' => ['username' => "bad\nuser", 'password' => 'secret']]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxyCredentials' => ['username' => "bad\tuser", 'password' => 'secret']]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxy' => 'http://proxy-user:bad%7fpass@proxy.example.test:8080']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxy' => "http://proxy.example.test:8080\t"]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxy' => "http://proxy.example.test:8080\x7f"]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxy' => 'http://bad%20proxy.example.test:8080']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxy' => 'socks5h://bad%2fproxy.example.test:1080']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['noProxy' => "example.test,bad\t.test"]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['noProxy' => 'example.test,bad host.test']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['noProxy' => ['example.test', 'bad/host.test']]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['noProxy' => ['example.test', 'bad\\host.test']]));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['proxyCredentialStore' => 'not callable']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['sslCaInfo' => __DIR__ . '/missing-ca.pem']));
        $t->throws(InvalidArgumentException::class, static fn () => new SmartHttpReceivePackTransport('https://example.test/repo.git', null, [], 30.0, [], ['sslVerify' => 'no']));
    },
    'smart http default requester performs socks5h handshake with proxy credentials' => static function (TestRunner $t) use ($packet, $flush, $readExactFromStream, $writeAllToStream, $readHttpRequestHeader, $runLocalTcpServer): void {
        $advertisement = $packet("58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a refs/heads/main\0report-status side-band-64k object-format=sha1\n")
            . $flush;
        $result = $runLocalTcpServer(
            static function (mixed $connection) use ($packet, $flush, $advertisement, $readExactFromStream, $writeAllToStream, $readHttpRequestHeader): array {
                $greeting = $readExactFromStream($connection, 2);
                $methods = $readExactFromStream($connection, ord($greeting[1]));
                $writeAllToStream($connection, "\x05\x02");

                $authHeader = $readExactFromStream($connection, 2);
                $username = $readExactFromStream($connection, ord($authHeader[1]));
                $passwordLength = ord($readExactFromStream($connection, 1));
                $password = $readExactFromStream($connection, $passwordLength);
                $writeAllToStream($connection, "\x01\x00");

                $connectHeader = $readExactFromStream($connection, 4);
                $hostLength = ord($readExactFromStream($connection, 1));
                $host = $readExactFromStream($connection, $hostLength);
                $portBytes = $readExactFromStream($connection, 2);
                $port = unpack('n', $portBytes)[1];
                $writeAllToStream($connection, "\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00");

                $httpRequest = $readHttpRequestHeader($connection);
                $body = $packet("# service=git-receive-pack\n") . $flush . $advertisement;
                $writeAllToStream(
                    $connection,
                    "HTTP/1.1 200 OK\r\n"
                    . "Content-Type: application/x-git-receive-pack-advertisement\r\n"
                    . 'Content-Length: ' . strlen($body) . "\r\n"
                    . "Connection: close\r\n\r\n"
                    . $body,
                );

                return [
                    'greeting' => bin2hex($greeting . $methods),
                    'authVersion' => ord($authHeader[0]),
                    'username' => $username,
                    'password' => $password,
                    'connectVersion' => ord($connectHeader[0]),
                    'connectCommand' => ord($connectHeader[1]),
                    'connectAddressType' => ord($connectHeader[3]),
                    'connectHost' => $host,
                    'connectPort' => $port,
                    'httpRequest' => $httpRequest,
                ];
            },
            static function (int $port): string {
                $transport = new SmartHttpReceivePackTransport(
                    'http://git.example.test/wp-content.git',
                    null,
                    [],
                    5.0,
                    ['User-Agent' => 'port-libs-socks-test/1'],
                    ['proxy' => "socks5h://wp-proxy-user:wp-proxy-pass@127.0.0.1:{$port}"],
                );

                return $transport->readAdvertisement();
            },
        );

        $t->same($advertisement, $result['result']);
        $t->same('05020002', $result['log']['greeting']);
        $t->same(1, $result['log']['authVersion']);
        $t->same('wp-proxy-user', $result['log']['username']);
        $t->same('wp-proxy-pass', $result['log']['password']);
        $t->same(5, $result['log']['connectVersion']);
        $t->same(1, $result['log']['connectCommand']);
        $t->same(3, $result['log']['connectAddressType']);
        $t->same('git.example.test', $result['log']['connectHost']);
        $t->same(80, $result['log']['connectPort']);
        $t->contains("GET /wp-content.git/info/refs?service=git-receive-pack HTTP/1.1\r\n", $result['log']['httpRequest']);
        $t->contains("Host: git.example.test\r\n", $result['log']['httpRequest']);
        $t->contains("User-Agent: port-libs-socks-test/1\r\n", $result['log']['httpRequest']);
        $t->same(false, str_contains($result['log']['httpRequest'], 'Proxy-Authorization:'));
    },
    'smart http default requester performs https through socks5h with trusted ca' => static function (TestRunner $t) use ($packet, $flush, $readExactFromStream, $writeAllToStream, $readHttpRequestHeader, $runLocalTcpServer, $temporaryTlsCertificate, $removeDirectory): void {
        $tls = $temporaryTlsCertificate('git.example.test');
        try {
            $advertisement = $packet("58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a refs/heads/main\0report-status side-band-64k object-format=sha1\n")
                . $flush;
            $result = $runLocalTcpServer(
                static function (mixed $connection) use ($packet, $flush, $advertisement, $readExactFromStream, $writeAllToStream, $readHttpRequestHeader, $tls): array {
                    $greeting = $readExactFromStream($connection, 2);
                    $methods = $readExactFromStream($connection, ord($greeting[1]));
                    $writeAllToStream($connection, "\x05\x00");

                    $connectHeader = $readExactFromStream($connection, 4);
                    $hostLength = ord($readExactFromStream($connection, 1));
                    $host = $readExactFromStream($connection, $hostLength);
                    $port = unpack('n', $readExactFromStream($connection, 2))[1];
                    $writeAllToStream($connection, "\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00");

                    stream_context_set_option($connection, 'ssl', 'local_cert', $tls['server']);
                    stream_context_set_option($connection, 'ssl', 'allow_self_signed', true);
                    $enabled = @stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
                    if ($enabled !== true) {
                        throw new RuntimeException('Unable to enable local TLS server stream');
                    }

                    $httpRequest = $readHttpRequestHeader($connection);
                    $body = $packet("# service=git-receive-pack\n") . $flush . $advertisement;
                    $writeAllToStream(
                        $connection,
                        "HTTP/1.1 200 OK\r\n"
                        . "Content-Type: application/x-git-receive-pack-advertisement\r\n"
                        . 'Content-Length: ' . strlen($body) . "\r\n"
                        . "Connection: close\r\n\r\n"
                        . $body,
                    );

                    return [
                        'greeting' => bin2hex($greeting . $methods),
                        'connectVersion' => ord($connectHeader[0]),
                        'connectCommand' => ord($connectHeader[1]),
                        'connectAddressType' => ord($connectHeader[3]),
                        'connectHost' => $host,
                        'connectPort' => $port,
                        'httpRequest' => $httpRequest,
                    ];
                },
                static function (int $port) use ($tls): string {
                    $transport = new SmartHttpReceivePackTransport(
                        'https://git.example.test/wp-content.git',
                        null,
                        [],
                        5.0,
                        ['User-Agent' => 'port-libs-socks-tls-test/1'],
                        ['proxy' => "socks5h://127.0.0.1:{$port}", 'sslCaInfo' => $tls['ca']],
                    );

                    return $transport->readAdvertisement();
                },
            );

            $t->same($advertisement, $result['result']);
            $t->same('050100', $result['log']['greeting']);
            $t->same(5, $result['log']['connectVersion']);
            $t->same(1, $result['log']['connectCommand']);
            $t->same(3, $result['log']['connectAddressType']);
            $t->same('git.example.test', $result['log']['connectHost']);
            $t->same(443, $result['log']['connectPort']);
            $t->contains("GET /wp-content.git/info/refs?service=git-receive-pack HTTP/1.1\r\n", $result['log']['httpRequest']);
            $t->contains("Host: git.example.test\r\n", $result['log']['httpRequest']);
            $t->contains("User-Agent: port-libs-socks-tls-test/1\r\n", $result['log']['httpRequest']);
            $t->same(false, str_contains($result['log']['httpRequest'], 'Proxy-Authorization:'));
        } finally {
            $removeDirectory($tls['dir']);
        }
    },
    'smart http default requester performs socks4a remote host handshake' => static function (TestRunner $t) use ($packet, $flush, $readExactFromStream, $writeAllToStream, $readHttpRequestHeader, $runLocalTcpServer): void {
        $advertisement = $packet("58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a refs/heads/main\0report-status\n")
            . $flush;
        $result = $runLocalTcpServer(
            static function (mixed $connection) use ($packet, $flush, $advertisement, $readExactFromStream, $writeAllToStream, $readHttpRequestHeader): array {
                $header = $readExactFromStream($connection, 8);
                $userId = '';
                while (($byte = $readExactFromStream($connection, 1)) !== "\x00") {
                    $userId .= $byte;
                }
                $remoteHost = '';
                while (($byte = $readExactFromStream($connection, 1)) !== "\x00") {
                    $remoteHost .= $byte;
                }
                $writeAllToStream($connection, "\x00\x5a\x00\x00\x00\x00\x00\x00");

                $httpRequest = $readHttpRequestHeader($connection);
                $body = $packet("# service=git-receive-pack\n") . $flush . $advertisement;
                $writeAllToStream(
                    $connection,
                    "HTTP/1.1 200 OK\r\n"
                    . "Content-Type: application/x-git-receive-pack-advertisement\r\n"
                    . "Transfer-Encoding: chunked\r\n"
                    . "Connection: close\r\n\r\n"
                    . dechex(strlen($body)) . "\r\n"
                    . $body . "\r\n0\r\n\r\n",
                );

                return [
                    'version' => ord($header[0]),
                    'command' => ord($header[1]),
                    'port' => unpack('n', substr($header, 2, 2))[1],
                    'address' => implode('.', array_map(ord(...), str_split(substr($header, 4, 4)))),
                    'userId' => $userId,
                    'remoteHost' => $remoteHost,
                    'httpRequest' => $httpRequest,
                ];
            },
            static function (int $port): string {
                $transport = new SmartHttpReceivePackTransport(
                    'http://git.example.test/wp-content.git',
                    null,
                    [],
                    5.0,
                    [],
                    ['proxy' => "socks4a://wp-proxy-user@127.0.0.1:{$port}"],
                );

                return $transport->readAdvertisement();
            },
        );

        $t->same($advertisement, $result['result']);
        $t->same(4, $result['log']['version']);
        $t->same(1, $result['log']['command']);
        $t->same(80, $result['log']['port']);
        $t->same('0.0.0.1', $result['log']['address']);
        $t->same('wp-proxy-user', $result['log']['userId']);
        $t->same('git.example.test', $result['log']['remoteHost']);
        $t->contains("GET /wp-content.git/info/refs?service=git-receive-pack HTTP/1.1\r\n", $result['log']['httpRequest']);
        $t->same(false, str_contains($result['log']['httpRequest'], 'Proxy-Authorization:'));
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
            SshReceivePackTransport::connect('ssh+git://deploy@git.example.test:2222/~/wp-content.git', $connector, 11.5),
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
            'command' => "git-receive-pack '~/wp-content.git'",
            'timeout' => 11.5,
        ], $connection);
        $t->same($request->requestBytes(), $streamBytes($write));
        $t->same("{$old} {$blob->oid()} refs/heads/main\0 report-status side-band-64k object-format=sha1 agent=port-libs/0.1", $commands[0]);
        $t->same($request->pack()?->packBytes(), $packBytes);
    },
    'ssh receive-pack connector receives protocol v2 auth boundary context' => static function (TestRunner $t) use ($packet, $flush, $streamWith, $streamBytes, $readPacketSequence): void {
        $old = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
        $blob = new GitObject('blob', 'WordPress SSH protocol v2 boundary payload');
        $advertisement = $packet("{$old} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
        $responseBytes = $packet("\x02Writing objects: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $read = $streamWith($advertisement . $responseBytes);
        $write = $streamWith('');
        $connection = null;
        $connector = static function (
            string $host,
            ?string $user,
            ?int $port,
            string $command,
            float $timeout,
            array $context,
        ) use (&$connection, $read, $write): array {
            $connection = [
                'host' => $host,
                'user' => $user,
                'port' => $port,
                'command' => $command,
                'timeout' => $timeout,
                'context' => $context,
            ];

            return ['read' => $read, 'write' => $write];
        };

        $client = new ReceivePackClient(
            SshReceivePackTransport::connect(
                'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
                $connector,
                11.5,
                ['protocolVersion' => 2],
            ),
            'port-libs/0.1'
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);

        $response = $client->send($request);
        [$commands, $packBytes] = $readPacketSequence($streamBytes($write));

        $t->same(true, $response->isSuccessful());
        $t->same('git.example.test', $connection['host']);
        $t->same('deploy', $connection['user']);
        $t->same(2222, $connection['port']);
        $t->same("git-receive-pack '/var/www/wp-content.git'", $connection['command']);
        $t->same(2, $connection['context']['protocolVersion']);
        $t->same(['GIT_PROTOCOL' => 'version=2', 'LANG' => 'C', 'LC_ALL' => 'C'], $connection['context']['environment']);
        $t->same(['-o', 'SendEnv=GIT_PROTOCOL', '-p2222', 'deploy@git.example.test'], $connection['context']['sshArguments']);
        $t->same('caller-provided-ssh-connector', $connection['context']['authenticationBoundary']);
        $t->same(
            "path=var/www/wp-content.git\nprotocol=ssh\nhost=git.example.test:2222\nusername=deploy\n",
            $connection['context']['credentialContext']->storageBytes()
        );
        $t->same($connection['context']['credentialContext']->storageBytes(), $connection['context']['redactedCredentialContext']);
        $t->same(false, str_contains($connection['context']['redactedCredentialContext'], 'password='));
        $t->same($request->requestBytes(), $streamBytes($write));
        $t->same("{$old} {$blob->oid()} refs/heads/main\0 report-status side-band-64k object-format=sha1 agent=port-libs/0.1", $commands[0]);
        $t->same($request->pack()?->packBytes(), $packBytes);

        $v1Context = SshReceivePackTransport::connectorContext('deploy@git.example.test:wp-content.git');
        $t->same(1, $v1Context['protocolVersion']);
        $t->same(['LANG' => 'C', 'LC_ALL' => 'C'], $v1Context['environment']);
        $t->same(['deploy@git.example.test'], $v1Context['sshArguments']);
        $t->same(false, $v1Context['useShell']);
        $t->same('ssh://deploy@git.example.test/wp-content.git', $v1Context['credentialContext']->toUrl());
        $optionLikeHostContext = SshReceivePackTransport::connectorContext('deploy@-git-proxy.example.test:wp-content.git', ['protocolVersion' => 2]);
        $t->same('-git-proxy.example.test', $optionLikeHostContext['host']);
        $t->same('deploy', $optionLikeHostContext['user']);
        $t->same(['-o', 'SendEnv=GIT_PROTOCOL', 'deploy@-git-proxy.example.test'], $optionLikeHostContext['sshArguments']);
        $t->same("path=wp-content.git\nprotocol=ssh\nhost=-git-proxy.example.test\nusername=deploy\n", $optionLikeHostContext['credentialContext']->storageBytes());

        $plinkContext = SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
            ['protocolVersion' => 2, 'programKind' => 'plink'],
        );
        $t->same('plink', $plinkContext['programKind']);
        $t->same('plink', $plinkContext['sshCommand']);
        $t->same(['LANG' => 'C', 'LC_ALL' => 'C'], $plinkContext['environment']);
        $t->same(['-P', '2222', 'deploy@git.example.test'], $plinkContext['sshArguments']);
        $t->same("git-receive-pack '/var/www/wp-content.git'", $plinkContext['command']);

        $puttyContext = SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2223/var/www/wp-content.git',
            ['programKind' => 'putty'],
        );
        $t->same('putty', $puttyContext['programKind']);
        $t->same(['-P', '2223', 'deploy@git.example.test'], $puttyContext['sshArguments']);

        $tortoiseContext = SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2224/var/www/wp-content.git',
            ['programKind' => 'tortoiseplink'],
        );
        $t->same('tortoiseplink', $tortoiseContext['programKind']);
        $t->same('tortoiseplink.exe', $tortoiseContext['sshCommand']);
        $t->same(['-batch', '-P', '2224', 'deploy@git.example.test'], $tortoiseContext['sshArguments']);

        $inferredPlinkContext = SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2225/var/www/wp-content.git',
            ['sshCommand' => '/opt/bin/Plink.exe'],
        );
        $t->same('plink', $inferredPlinkContext['programKind']);
        $t->same('/opt/bin/Plink.exe', $inferredPlinkContext['sshCommand']);
        $t->same(['-P', '2225', 'deploy@git.example.test'], $inferredPlinkContext['sshArguments']);
        $t->same(false, $inferredPlinkContext['useShell']);

        $simpleContext = SshReceivePackTransport::connectorContext(
            'deploy@git.example.test:wp-content.git',
            ['protocolVersion' => 2, 'programKind' => 'simple', 'sshCommand' => 'simple'],
        );
        $t->same('simple', $simpleContext['programKind']);
        $t->same('simple', $simpleContext['sshCommand']);
        $t->same(['LANG' => 'C', 'LC_ALL' => 'C'], $simpleContext['environment']);
        $t->same(['deploy@git.example.test'], $simpleContext['sshArguments']);
        $t->same(false, $simpleContext['useShell']);

        $shellContext = SshReceivePackTransport::connectorContext(
            'deploy@git.example.test:wp-content.git',
            ['sshCommand' => 'echo hi'],
        );
        $t->same('simple', $shellContext['programKind']);
        $t->same('echo hi', $shellContext['sshCommand']);
        $t->same(false, $shellContext['disallowShell']);
        $t->same(true, $shellContext['useShell']);
        $t->same(['deploy@git.example.test'], $shellContext['sshArguments']);

        $shellDisabledContext = SshReceivePackTransport::connectorContext(
            'deploy@git.example.test:wp-content.git',
            ['sshCommand' => 'echo hi', 'disallowShell' => true],
        );
        $t->same('simple', $shellDisabledContext['programKind']);
        $t->same(true, $shellDisabledContext['disallowShell']);
        $t->same(false, $shellDisabledContext['useShell']);
        $t->same(['deploy@git.example.test'], $shellDisabledContext['sshArguments']);

        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connectorContext('ssh://example.test/repo.git', ['protocolVersion' => 0]));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connectorContext('ssh://example.test/repo.git', ['protocolVersion' => 3]));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connectorContext('ssh://deploy@git.example.test:2222/repo.git', ['programKind' => 'simple']));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connectorContext('ssh://deploy@git.example.test/repo.git', ['programKind' => 'unknown']));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connectorContext('ssh://deploy@git.example.test/repo.git', ['sshCommand' => "ssh\n"]));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connectorContext('ssh://deploy@git.example.test/repo.git', ['disallowShell' => 'yes']));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::connectorContext('ssh://deploy:secret@git.example.test/repo.git'));
    },
    'ssh receive-pack classifies upstream ssh program stderr lines' => static function (TestRunner $t): void {
        $permission = 'byron@github.com: Permission denied (publickey).';
        $t->same([
            'kind' => 'permission_denied',
            'message' => $permission,
        ], SshReceivePackTransport::classifyErrorLine($permission));
        $t->same('permission_denied', SshReceivePackTransport::classifyErrorLine('something permission denied something', 'simple')['kind']);

        $resolve = 'ssh: Could not resolve hostname hostfoobar: nodename nor servname provided, or not known';
        $t->same([
            'kind' => 'connection_refused',
            'message' => $resolve,
        ], SshReceivePackTransport::classifyErrorLine($resolve));

        $noRoute = 'ssh: connect to host example.org port 22: No route to host';
        $t->same('not_found', SshReceivePackTransport::classifyErrorLine($noRoute)['kind']);
        $t->same('not_found', SshReceivePackTransport::classifyErrorLine('banner exchange: Connection to 127.0.0.1 port 61024: Software caused connection abort')['kind']);
        $t->same('not_found', SshReceivePackTransport::classifyErrorLine("Connection closed by 127.0.0.1 port 8888\n")['kind']);
        $t->same('Connection closed by 127.0.0.1 port 8888', SshReceivePackTransport::classifyErrorLine("Connection closed by 127.0.0.1 port 8888\n")['message']);

        $plink = 'FATAL ERROR: No supported authentication methods available (server sent: publickey)';
        $t->same('permission_denied', SshReceivePackTransport::classifyErrorLine($plink, 'plink')['kind']);
        $t->same('permission_denied', SshReceivePackTransport::classifyErrorLine('publickey', 'putty')['kind']);
        $t->same('permission_denied', SshReceivePackTransport::classifyErrorLine('publickey', 'tortoiseplink')['kind']);

        $t->same(null, SshReceivePackTransport::classifyErrorLine('remote banner: ready'));
        $t->same(null, SshReceivePackTransport::classifyErrorLine('', 'ssh'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::classifyErrorLine('publickey', 'unknown'));
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
        $t->same([
            'host' => '2001:db8::42',
            'user' => 'deploy',
            'port' => 2222,
            'path' => '/srv/wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('ssh://deploy@[2001:db8::42]:2222/srv/wp-content.git'));
        $t->same([
            'host' => '2001:db8::42',
            'user' => 'deploy',
            'port' => null,
            'path' => 'wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('deploy@[2001:db8::42]:wp-content.git'));
        $t->same([
            'host' => 'git.example.test',
            'user' => 'deploy',
            'port' => 2222,
            'path' => '~/wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('ssh+git://deploy@git.example.test:2222/~/wp-content.git'));
        $t->same([
            'host' => 'git.example.test',
            'user' => null,
            'port' => null,
            'path' => '~wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('git+ssh://git.example.test:/~wp-content.git'));
        $t->same([
            'host' => 'git.example.test',
            'user' => null,
            'port' => null,
            'path' => '~wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('git.example.test:/~wp-content.git'));
        $t->same([
            'host' => '-git-proxy.example.test',
            'user' => 'deploy',
            'port' => null,
            'path' => 'wp-content.git',
        ], SshReceivePackTransport::parseRepositoryUrl('deploy@-git-proxy.example.test:wp-content.git'));
        $t->same([
            'host' => '-arg',
            'user' => 'user',
            'port' => null,
            'path' => '/p',
        ], SshReceivePackTransport::parseRepositoryUrl('ssh://user@-arg/p'));
        $t->same('~/wp-content.git', SshReceivePackTransport::parseRepositoryUrl('ssh://git.example.test/~/wp-content.git')['path']);
        $t->same("git-receive-pack '~/wp-content.git'", SshReceivePackTransport::receivePackCommand('~/wp-content.git'));
        $t->same("git-receive-pack 'wp content/repo'\\''s.git'", SshReceivePackTransport::receivePackCommand("wp content/repo's.git"));

        $badConnector = static fn (): array => ['read' => 'not a stream', 'write' => 'not a stream'];

        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('https://example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl("ssh://example.test/repo.git\n"));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://example.test/repo.git?service=git-receive-pack'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://-oProxyCommand=open$IFS-aCalculator/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://-deploy@example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://bad%20host.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://bad%2fhost.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('bad user@example.test:repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('bad/user@example.test:repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('bad%20user@example.test:repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://deploy%40tenant@git.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh://deploy%3Atenant@git.example.test/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('ssh+git://-oProxyCommand=open$IFS-aCalculator/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('git+ssh://-oProxyCommand=open$IFS-aCalculator/repo.git'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::parseRepositoryUrl('example.test: -upload-pack=/tmp/helper'));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::receivePackCommand("repo\0.git"));
        $t->throws(InvalidArgumentException::class, static fn () => SshReceivePackTransport::receivePackCommand(' -upload-pack=/tmp/helper'));
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
    'receive-pack client reports advertisement ERR packets before ref parsing' => static function (TestRunner $t) use ($packet, $streamWith): void {
        $client = new ReceivePackClient(
            new StreamReceivePackTransport($streamWith($packet("ERR repository access denied\n") . '0000'), $streamWith('')),
            'port-libs/0.1'
        );

        try {
            $client->handshake();
        } catch (RuntimeException $exception) {
            $t->contains('receive-pack error repository access denied', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected advertisement ERR packet to fail the handshake');
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
        $t->same("git-receive-pack /wp-content.git\0host=git-mirror.example.test\0\0version=2\0", substr($fixture['gitDaemonEncodedUrlServiceRequest'], 4));
        $t->same("git-receive-pack /wp-content.git\0host=git.example.test\0\0version=2\0session-id\0object-format=sha1\0", substr($fixture['gitDaemonValueOnlyExtraServiceRequest'], 4));
        $t->same("git-receive-pack ~/wp-content.git\0host=git.example.test\0\0version=2\0session-id\0object-format=sha1\0", substr($fixture['gitDaemonProtocolV2HomeServiceRequest'], 4));
        $t->same("git-receive-pack ~deploy/wp-content.git\0host=git.example.test\0\0version=2\0session-id\0", substr($fixture['gitDaemonProtocolV2NamedHomeUrlServiceRequest'], 4));
        $t->same(true, $fixture['unsafeGitDaemonEncodedControlByteRejected']);
        $t->same(true, $fixture['unsafeGitDaemonEncodedHostDelimiterRejected']);
        $t->same(true, $fixture['unsafeGitDaemonExtraParameterRejected']);
        $t->same(true, $fixture['unsafeGitDaemonProtocolVersionRejected']);
        $t->same(true, $fixture['unsafeSmartHttpCredentialTabRejected']);
        $t->same(true, $fixture['unsafeSmartHttpExtraParameterTabRejected']);
        $t->same(true, $fixture['unsafeSmartHttpHeaderTabRejected']);
        $t->same(true, $fixture['unsafeSmartHttpNoProxyDelimiterRejected']);
        $t->same(true, $fixture['unsafeSmartHttpRawUrlControlByteRejected']);
        $t->same(true, $fixture['unsafeSmartHttpRawProxyControlByteRejected']);
        $t->same(true, $fixture['smartHttpAdvertisementWithoutServiceHeaderAccepted']);
        $t->same(true, $fixture['advertisementErrorReported']);
        $t->same(true, $fixture['unsafeSshHostDelimiterRejected']);
        $t->same(true, $fixture['unsafeSshUserDelimiterRejected']);
        $t->same(true, $fixture['unsafeSshEncodedUserDelimiterRejected']);
        $t->same(true, $fixture['unsafeSshPasswordRejected']);
        $t->same('~/wp-content.git', $fixture['sshLegacySchemeTarget']['path']);
        $t->same('~wp-content.git', $fixture['sshLegacyGitSchemeTarget']['path']);
        $t->same('~wp-content.git', $fixture['sshScpLikeHomeTarget']['path']);
        $t->same('-git-proxy.example.test', $fixture['sshOptionLikeHostWithUserTarget']['host']);
        $t->same(['-o', 'SendEnv=GIT_PROTOCOL', 'deploy@-git-proxy.example.test'], $fixture['sshOptionLikeHostWithUserContext']['sshArguments']);
        $t->same('plink', $fixture['sshPlinkContext']['programKind']);
        $t->same(['-P', '2222', 'deploy@git.example.test'], $fixture['sshPlinkContext']['sshArguments']);
        $t->same('tortoiseplink.exe', $fixture['sshTortoisePlinkContext']['sshCommand']);
        $t->same(['-batch', '-P', '2222', 'deploy@git.example.test'], $fixture['sshTortoisePlinkContext']['sshArguments']);
        $t->same('simple', $fixture['sshSimpleContext']['programKind']);
        $t->same(['deploy@git.example.test'], $fixture['sshSimpleContext']['sshArguments']);
        $t->same(false, $fixture['sshSimpleContext']['useShell']);
        $t->same(true, $fixture['sshShellScriptContext']['useShell']);
        $t->same(false, $fixture['sshDisallowShellContext']['useShell']);
        $t->same(true, $fixture['sshSimplePortRejected']);
        $t->same(true, $fixture['unsafeSshLegacyHostRejected']);
        $t->same(['GIT_PROTOCOL' => 'version=2', 'LANG' => 'C', 'LC_ALL' => 'C'], $fixture['sshProtocolV2Context']['environment']);
        $t->same(['-o', 'SendEnv=GIT_PROTOCOL', '-p2222', 'deploy@git.example.test'], $fixture['sshProtocolV2Context']['sshArguments']);
        $t->same('caller-provided-ssh-connector', $fixture['sshProtocolV2Context']['authenticationBoundary']);
        $t->same(false, str_contains($fixture['sshProtocolV2Context']['redactedCredentialContext'], 'password='));
        $t->same('permission_denied', $fixture['sshErrorClassifications']['permissionDenied']['kind']);
        $t->same('connection_refused', $fixture['sshErrorClassifications']['resolveHost']['kind']);
        $t->same('not_found', $fixture['sshErrorClassifications']['connectionClosed']['kind']);
        $t->same('permission_denied', $fixture['sshErrorClassifications']['plinkPublicKey']['kind']);
        $t->same(null, $fixture['sshErrorClassifications']['unclassifiedBanner']);
    },
    'wordpress fixture stores smart http proxy credentials without leaking origin headers' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-smart-http-proxy-credentials.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-smart-http-proxy-credentials.php';

        $t->same([['http://wp-proxy.example.test:8080', 'git.example.test']], $fixture['helperCalls']);
        $t->same([['http://wp-proxy.example.test:8080', 'git.example.test', ['username' => 'wp-proxy-user', 'password' => 'wp-proxy-pass']]], $fixture['storedCredentials']);
        $t->same([], $fixture['erasedCredentials']);
        $t->same('Basic ' . base64_encode('wp-proxy-user:wp-proxy-pass'), $fixture['proxyAuthorizationSent']);
        $t->same(false, $fixture['originProxyHeaderLeaked']);
        $t->same([['http://wp-proxy-user@wp-proxy.example.test:8080', 'git.example.test']], $fixture['usernameOnlyProxyHelperCalls']);
        $t->same('http://wp-proxy-user@wp-proxy.example.test:8080', $fixture['usernameOnlyProxyUrl']);
        $t->same('Basic ' . base64_encode('wp-proxy-user:helper-secret'), $fixture['usernameOnlyProxyAuthorizationSent']);
        $t->same([['http://wp-proxy-user@wp-proxy.example.test:8080', 'git.example.test', ['username' => 'wp-proxy-user', 'password' => 'helper-secret']]], $fixture['usernameOnlyProxyStores']);
        $t->same(false, $fixture['usernameOnlyOriginProxyHeaderLeaked']);
        $t->same($fixture['usernameOnlyProxyHelperCalls'], $summary['usernameOnlyProxyHelperCalls']);
        $t->same($fixture['usernameOnlyProxyUrl'], $summary['usernameOnlyProxyCredentialUrl']);
        $t->same($fixture['usernameOnlyProxyAuthorizationSent'], $summary['usernameOnlyProxyAuthorizationSent']);
        $t->same(false, $summary['usernameOnlyOriginProxyHeaderLeaked']);
        $t->same([['http://stale-user:stale-pass@wp-proxy.example.test:8080', 'git.example.test']], $fixture['urlCredentialProxyHelperCalls']);
        $t->same('http://wp-proxy.example.test:8080', $fixture['urlCredentialProxyUrl']);
        $t->same('Basic ' . base64_encode('helper-proxy-user:helper-proxy-pass'), $fixture['urlCredentialProxyAuthorizationSent']);
        $t->same([['http://stale-user:stale-pass@wp-proxy.example.test:8080', 'git.example.test', ['username' => 'helper-proxy-user', 'password' => 'helper-proxy-pass']]], $fixture['urlCredentialProxyStores']);
        $t->same(false, $fixture['urlCredentialOriginProxyHeaderLeaked']);
        $t->contains('refs/heads/main', $fixture['urlCredentialProxyAdvertisementBytes']);
        $t->same($fixture['urlCredentialProxyHelperCalls'], $summary['urlCredentialProxyHelperCalls']);
        $t->same($fixture['urlCredentialProxyUrl'], $summary['urlCredentialProxyUrl']);
        $t->same($fixture['urlCredentialProxyAuthorizationSent'], $summary['urlCredentialProxyAuthorizationSent']);
        $t->same([
            [
                'proxyUrl' => 'http://stale-user:stale-pass@wp-proxy.example.test:8080',
                'requestHost' => 'git.example.test',
                'username' => 'helper-proxy-user',
            ],
        ], $summary['urlCredentialProxyCredentialsStored']);
        $t->same(false, $summary['urlCredentialOriginProxyHeaderLeaked']);
        $t->same([
            'https://git.example.test/wp-content.git/info/refs?service=git-receive-pack',
            'https://git.example.test/redirected.git/info/refs?service=git-receive-pack',
        ], $fixture['redirectRequestUrls']);
        $t->same([['http://wp-proxy.example.test:8080', 'git.example.test']], $fixture['redirectHelperCalls']);
        $t->same(true, $fixture['redirectProxyAuthorizationReused']);
        $t->same([['http://wp-proxy.example.test:8080', 'git.example.test', ['username' => 'redirect-proxy-user', 'password' => 'redirect-proxy-pass']]], $fixture['redirectStoredCredentials']);
        $t->same([], $fixture['redirectErasedCredentials']);
        $t->contains('refs/heads/main', $fixture['redirectAdvertisementBytes']);
        $t->same(true, $fixture['unexpectedStatusRejected']);
        $t->same([], $fixture['unexpectedStatusStores']);
        $t->same([['http://wp-proxy.example.test:8080', 'git.example.test', ['username' => 'stale-proxy-user', 'password' => 'stale-proxy-pass']]], $fixture['unexpectedStatusErasures']);
        $t->contains('refs/heads/main', $fixture['advertisementBytes']);
        $t->contains('refs/heads/main', $fixture['usernameOnlyProxyAdvertisementBytes']);
    },
    'wordpress fixture documents smart http socks tls receive-pack discovery' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-smart-http-socks-tls.php';

        $t->same('https://git.example.test/wp-content.git', $fixture['repositoryUrl']);
        $t->same('socks5h://wp-proxy.example.test:1080', $fixture['proxyUrl']);
        $t->same('git.example.test', $fixture['tlsPeerName']);
        $t->same('sslCaInfo', $fixture['caOption']);
        $t->same('sslVerify', $fixture['verifyOption']);
        $t->same(443, $fixture['connectPort']);
        $t->same('/wp-content.git/info/refs?service=git-receive-pack', $fixture['requestTarget']);
        $t->contains('WordPress deployment tool', $fixture['wordpressUse']);
    },
    'wordpress fixture refuses cleartext url credentials before smart http discovery' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-smart-http-cleartext-credentials.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-smart-http-cleartext-credentials.php';

        $t->same(0, $fixture['requesterCalls']);
        $t->contains('will not send URL credentials over cleartext HTTP', $fixture['error']);
        $t->same($fixture['error'], $summary['error']);
        $t->same(false, $summary['requesterReached']);
        $t->contains('not leak deployment credentials', $summary['wordpressUse']);
    },
];
