<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\ReceivePackAdvertisement;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\GitDaemonReceivePackTransport;
use PortLibs\Gitoxide\ProtocolCapabilities;
use PortLibs\Gitoxide\PushCommand;
use PortLibs\Gitoxide\SmartHttpReceivePackTransport;
use PortLibs\Gitoxide\SmartHttpStatusException;
use PortLibs\Gitoxide\SshReceivePackTransport;
use PortLibs\Gitoxide\StreamReceivePackTransport;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$oldCommit = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$advertisementBytes = $packet("{$oldCommit} refs/heads/main\0report-status-v2 side-band-64k object-format=sha1 atomic push-options\n")
    . $flush;

$blob = new GitObject('blob', "Post title: Native PHP receive-pack transport\n\nStreamed request bytes for a WordPress deployment push.\n");
$tree = (new Tree([
    new TreeEntry('100644', 'wp-content-transport.txt', $blob->oid()),
]))->toObject();
$commit = new GitObject(
    'commit',
    "tree {$tree->oid()}\n"
    . "parent {$oldCommit}\n"
    . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
    . "committer WordPress <wordpress@example.test> 1700000000 +0000\n\n"
    . "Deploy WordPress content through native PHP receive-pack transport\n"
);

$progress = 'Writing objects: 100% (3/3), done.';
$responseBytes = $packet("\x02{$progress}\n")
    . $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . '0001';

$serverRead = fopen('php://temp', 'r+b');
$clientWrite = fopen('php://temp', 'r+b');
if ($serverRead === false || $clientWrite === false) {
    throw new RuntimeException('Unable to open receive-pack fixture streams');
}
fwrite($serverRead, $advertisementBytes . $responseBytes);
rewind($serverRead);

$client = new ReceivePackClient(new StreamReceivePackTransport($serverRead, $clientWrite), 'port-libs/wordpress');
$response = $client->run(static function (SendPackSession $session) use ($commit, $tree, $blob): mixed {
    $session->command()->useAtomic();
    $session->command()->addPushOption('ci.skip');
    $session->createOrUpdate('refs/heads/main', $commit->oid());

    return $session->buildRequest([$commit, $tree, $blob]);
});

rewind($clientWrite);
$requestBytes = (string) stream_get_contents($clientWrite);

$requestPacketLineMaxPayloadLength = 65516;
$requestLimitCapabilities = ProtocolCapabilities::fromV1Bytes("\0report-status push-options")['capabilities'];
$requestCommandPrefix = str_repeat('0', 40)
    . ' '
    . str_repeat('a', 40)
    . ' refs/heads/main'
    . "\0 report-status agent=";
$requestCommandMaxAgentLength = $requestPacketLineMaxPayloadLength - strlen($requestCommandPrefix);
$maxRequestCommand = PushCommand::create($requestLimitCapabilities, str_repeat('a', $requestCommandMaxAgentLength));
$maxRequestCommand->createRef(str_repeat('a', 40), 'refs/heads/main');
$maxRequestCommandHeader = substr($maxRequestCommand->requestBytes(), 0, 4);

return [
    'oldCommit' => $oldCommit,
    'newCommit' => $commit->oid(),
    'advertisementBytes' => $advertisementBytes,
    'requestBytes' => $requestBytes,
    'responseBytes' => $responseBytes,
    'responseTerminator' => substr($responseBytes, -4),
    'responseSuccessful' => $response->isSuccessful(),
    'requestPacketLineMaxPayloadLength' => $requestPacketLineMaxPayloadLength,
    'maxRequestCommandPacketHeader' => $maxRequestCommandHeader,
    'oversizeRequestCommandRejected' => (static function () use ($requestLimitCapabilities, $requestCommandMaxAgentLength): bool {
        $command = PushCommand::create($requestLimitCapabilities, str_repeat('a', $requestCommandMaxAgentLength + 1));
        $command->createRef(str_repeat('a', 40), 'refs/heads/main');
        try {
            $command->requestBytes();
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'oversizePushOptionRejected' => (static function () use ($requestLimitCapabilities, $requestPacketLineMaxPayloadLength): bool {
        $command = PushCommand::create($requestLimitCapabilities);
        $command->createRef(str_repeat('b', 40), 'refs/heads/main');
        $command->addPushOption(str_repeat('p', $requestPacketLineMaxPayloadLength + 1));
        try {
            $command->requestBytes();
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'progressMessages' => $response->progressMessages(),
    'acceptedRefs' => array_map(
        static fn ($status): string => $status->effectiveRefName(),
        $response->refStatuses()
    ),
    'sshTarget' => SshReceivePackTransport::parseRepositoryUrl('deploy@git.example.test:wp-content.git'),
    'sshIpv6Target' => SshReceivePackTransport::parseRepositoryUrl('ssh://deploy@[2001:db8::42]:2222/srv/wp-content.git'),
    'sshScpIpv6Target' => SshReceivePackTransport::parseRepositoryUrl('[2001:db8::42]:wp-content.git'),
    'sshLegacySchemeTarget' => SshReceivePackTransport::parseRepositoryUrl('ssh+git://deploy@git.example.test:2222/~/wp-content.git'),
    'sshLegacyGitSchemeTarget' => SshReceivePackTransport::parseRepositoryUrl('git+ssh://git.example.test:/~wp-content.git'),
    'sshScpLikeHomeTarget' => SshReceivePackTransport::parseRepositoryUrl('git.example.test:/~wp-content.git'),
    'sshNonNumericPortTarget' => SshReceivePackTransport::parseRepositoryUrl('ssh://deploy@git.example.test:tenant/wp-content.git'),
    'sshRootPathTarget' => SshReceivePackTransport::parseRepositoryUrl('ssh://host.xz:21/'),
    'sshOptionLikeHostWithUserTarget' => SshReceivePackTransport::parseRepositoryUrl('deploy@-git-proxy.example.test:wp-content.git'),
    'sshScpLikeAtUserTarget' => SshReceivePackTransport::parseRepositoryUrl('user@name@host.xz:wp-content.git'),
    'sshCommand' => SshReceivePackTransport::receivePackCommand('wp-content.git'),
    'sshHomeCommand' => SshReceivePackTransport::receivePackCommand('~/wp-content.git'),
    'sshBangPathCommand' => SshReceivePackTransport::receivePackCommand("wp content/important!repo's.git"),
    'sshProtocolV2Context' => SshReceivePackTransport::connectorContext(
        'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
        ['protocolVersion' => 2],
    ),
    'sshNonNumericPortContext' => SshReceivePackTransport::connectorContext(
        'ssh://deploy@git.example.test:tenant/wp-content.git',
        ['protocolVersion' => 2],
    ),
    'sshRootPathContext' => SshReceivePackTransport::connectorContext(
        'ssh://host.xz:21/',
        ['protocolVersion' => 2],
    ),
    'sshOptionLikeHostWithUserContext' => SshReceivePackTransport::connectorContext(
        'deploy@-git-proxy.example.test:wp-content.git',
        ['protocolVersion' => 2],
    ),
    'sshScpLikeAtUserContext' => SshReceivePackTransport::connectorContext(
        'user@name@host.xz:wp-content.git',
        ['protocolVersion' => 2],
    ),
    'sshPlinkContext' => SshReceivePackTransport::connectorContext(
        'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
        ['protocolVersion' => 2, 'programKind' => 'plink'],
    ),
    'sshExtensionStemContexts' => [
        'plinkCmd' => SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2226/var/www/wp-content.git',
            ['protocolVersion' => 2, 'sshCommand' => '/opt/bin/plink.cmd'],
        ),
        'puttyWrapper' => SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2226/var/www/wp-content.git',
            ['protocolVersion' => 2, 'sshCommand' => '/opt/bin/putty.wrapper'],
        ),
        'sshCustom' => SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2226/var/www/wp-content.git',
            ['protocolVersion' => 2, 'sshCommand' => '/opt/bin/ssh.custom'],
        ),
        'tortoisePlinkBat' => SshReceivePackTransport::connectorContext(
            'ssh://deploy@git.example.test:2226/var/www/wp-content.git',
            ['protocolVersion' => 2, 'sshCommand' => '/opt/bin/tortoiseplink.bat'],
        ),
        'dotfileCommand' => SshReceivePackTransport::connectorContext(
            'deploy@git.example.test:wp-content.git',
            ['sshCommand' => '.ssh'],
        ),
    ],
    'sshIdentityContext' => SshReceivePackTransport::connectorContext(
        'ssh://stale@git.example.test/var/www/wp-content.git',
        ['protocolVersion' => 2, 'identityUsername' => 'deploy'],
    ),
    'sshIdentityClearedContext' => SshReceivePackTransport::connectorContext(
        'ssh://stale@git.example.test/var/www/wp-content.git',
        ['identityUsername' => ''],
    ),
    'sshTortoisePlinkContext' => SshReceivePackTransport::connectorContext(
        'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
        ['programKind' => 'tortoiseplink'],
    ),
    'sshSimpleContext' => SshReceivePackTransport::connectorContext(
        'deploy@git.example.test:wp-content.git',
        ['protocolVersion' => 2, 'programKind' => 'simple', 'sshCommand' => 'simple'],
    ),
    'sshShellScriptContext' => SshReceivePackTransport::connectorContext(
        'deploy@git.example.test:wp-content.git',
        ['sshCommand' => 'echo hi'],
    ),
    'sshDisallowShellContext' => SshReceivePackTransport::connectorContext(
        'deploy@git.example.test:wp-content.git',
        ['sshCommand' => 'echo hi', 'disallowShell' => true],
    ),
    'sshFallbackContext' => SshReceivePackTransport::connectorContext(
        'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
        ['protocolVersion' => 2, 'programKind' => 'putty', 'commandWithoutShellFallback' => 'ssh --fallback'],
    ),
    'sshFallbackFeatureProbeContext' => SshReceivePackTransport::connectorContext(
        'deploy@git.example.test:wp-content.git',
        ['commandWithoutShellFallback' => 'ssh --fallback'],
    ),
    'sshCommandPrecedenceContext' => SshReceivePackTransport::connectorContext(
        'deploy@git.example.test:wp-content.git',
        ['sshCommand' => 'echo hi', 'commandWithoutShellFallback' => 'ssh --fallback'],
    ),
    'sshExplicitSimpleNoFeatureProbeContext' => SshReceivePackTransport::connectorContext(
        'deploy@-git-proxy.example.test:wp-content.git',
        ['programKind' => 'simple', 'sshCommand' => 'echo hi'],
    ),
    'sshExplicitKindOptionLikeHostContext' => SshReceivePackTransport::connectorContext(
        'deploy@-git-proxy.example.test:wp-content.git',
        ['protocolVersion' => 2, 'programKind' => 'ssh', 'sshCommand' => 'echo hi'],
    ),
    'sshSimplePortRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::connectorContext(
                'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
                ['programKind' => 'simple'],
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'sshErrorClassifications' => [
        'permissionDenied' => SshReceivePackTransport::classifyErrorLine('deploy@git.example.test: Permission denied (publickey).'),
        'resolveHost' => SshReceivePackTransport::classifyErrorLine('ssh: Could not resolve hostname git.example.test: nodename nor servname provided, or not known'),
        'connectionClosed' => SshReceivePackTransport::classifyErrorLine("Connection closed by 127.0.0.1 port 2222\n"),
        'plinkPublicKey' => SshReceivePackTransport::classifyErrorLine('FATAL ERROR: No supported authentication methods available (server sent: publickey)', 'plink'),
        'unclassifiedBanner' => SshReceivePackTransport::classifyErrorLine('remote banner: WordPress deployment gate ready'),
    ],
    'gitDaemonServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytes('/wp-content.git', 'git.example.test', 9418, ['version=2']),
    'gitDaemonUrlServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://git.example.test:9418/wp-content.git', ['version=2']),
    'gitDaemonValueOnlyExtraServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://git.example.test/wp-content.git', ['version=2', 'session-id', 'object-format=sha1']),
    'gitDaemonProtocolV2HomeServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytes('/~/wp-content.git', 'git.example.test', null, ['session-id', 'object-format=sha1'], 2),
    'gitDaemonProtocolV2NamedHomeUrlServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://git.example.test/%7Edeploy/wp-content.git', ['session-id'], 2),
    'gitDaemonEncodedUrlServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://git%2Dmirror.example.test/wp%2Dcontent.git', ['version=2']),
    'gitDaemonIpv6ServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytes('/wp-content.git', '2001:db8::42', null, ['version=2']),
    'unsafeGitDaemonPathRejected' => (static function (): bool {
        try {
            GitDaemonReceivePackTransport::serviceRequestBytes('wp-content.git', 'git.example.test');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeGitDaemonControlByteRejected' => (static function (): bool {
        try {
            GitDaemonReceivePackTransport::serviceRequestBytes("/wp-content.git\n", 'git.example.test');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeGitDaemonUrlRejected' => (static function (): bool {
        try {
            GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://deploy@git.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeGitDaemonEncodedControlByteRejected' => (static function (): bool {
        try {
            GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://git.example.test/wp%0acontent.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeGitDaemonEncodedHostDelimiterRejected' => (static function (): bool {
        try {
            GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://bad%20host.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeGitDaemonExtraParameterRejected' => (static function (): bool {
        try {
            GitDaemonReceivePackTransport::serviceRequestBytes('/wp-content.git', 'git.example.test', null, ['bad parameter']);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeGitDaemonProtocolVersionRejected' => (static function (): bool {
        try {
            GitDaemonReceivePackTransport::serviceRequestBytes('/wp-content.git', 'git.example.test', null, [], 3);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpCredentialControlByteRejected' => (static function (): bool {
        try {
            SmartHttpReceivePackTransport::infoRefsUrl('https://deploy:bad%0atoken@git.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpCredentialTabRejected' => (static function (): bool {
        try {
            SmartHttpReceivePackTransport::infoRefsUrl('https://bad%09deploy:token@git.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpHostDelimiterRejected' => (static function (): bool {
        try {
            \PortLibs\Gitoxide\SmartHttpReceivePackTransport::infoRefsUrl('https://bad%20host.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpProxyHostDelimiterRejected' => (static function (): bool {
        try {
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                null,
                [],
                30.0,
                [],
                ['proxy' => 'http://bad%2fproxy.example.test:8080']
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpEncodedPathControlByteRejected' => (static function (): bool {
        try {
            SmartHttpReceivePackTransport::infoRefsUrl('https://git.example.test/wp%0acontent.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpExtraParameterTabRejected' => (static function (): bool {
        try {
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                null,
                ["version\t=2"]
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpHeaderTabRejected' => (static function (): bool {
        try {
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                null,
                [],
                30.0,
                ['User-Agent' => "wp-deploy\ttool"]
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpNoProxyDelimiterRejected' => (static function (): bool {
        try {
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                null,
                [],
                30.0,
                [],
                ['proxy' => 'http://proxy.example.test:8080', 'noProxy' => 'git.example.test,bad host.test']
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpRawUrlControlByteRejected' => (static function (): bool {
        try {
            SmartHttpReceivePackTransport::infoRefsUrl("https://git.example.test/wp-content.git\t");
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpRawProxyControlByteRejected' => (static function (): bool {
        try {
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                null,
                [],
                30.0,
                [],
                ['proxy' => "http://proxy.example.test:8080\t"]
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'smartHttpAdvertisementWithoutServiceHeaderAccepted' => (static function () use ($advertisementBytes): bool {
        $transport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                'body' => $advertisementBytes,
            ],
        );

        return $transport->readAdvertisement() === $advertisementBytes;
    })(),
    'smartHttpDuplicateContentTypeAccepted' => (static function () use ($packet, $flush, $advertisementBytes, $blob): bool {
        $responseBytes = $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $requests = [];
        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$requests, $packet, $flush, $advertisementBytes, $responseBytes): array {
                    $requests[] = ['method' => $method, 'url' => $url, 'body' => $body];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => ['application/x-git-receive-pack-advertisement', 'text/plain']],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => ['text/plain', 'application/x-git-receive-pack-result; charset=utf-8']],
                        'body' => $responseBytes,
                    ];
                },
            ),
            'port-libs/wordpress'
        );

        try {
            $session = $client->handshake();
            $session->createOrUpdate('refs/heads/main', $blob->oid());
            $request = $session->buildRequest([$blob]);
            $response = $client->send($request);

            return $response->isSuccessful()
                && array_column($requests, 'method') === ['GET', 'POST']
                && $requests[1]['body'] === $request->requestBytes();
        } catch (Throwable) {
            return false;
        }
    })(),
    'smartHttpHeaderBoundary' => (static function () use ($packet, $flush, $advertisementBytes, $blob): array {
        $defaultRequests = [];
        $defaultTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body) use (&$defaultRequests, $packet, $flush, $advertisementBytes): array {
                $defaultRequests[] = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'body' => $body,
                ];

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            },
        );
        $defaultTransport->readAdvertisement();

        $responseBytes = $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $overrideRequests = [];
        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$overrideRequests, $packet, $flush, $advertisementBytes, $responseBytes): array {
                    $overrideRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                30.0,
                ['User-Agent' => 'wp-deploy/2', 'Expect' => '100-continue'],
            ),
            'port-libs/wordpress',
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);
        $response = $client->send($request);

        return [
            'defaultGetUserAgent' => $defaultRequests[0]['headers']['User-Agent'] ?? null,
            'defaultGetExpectHeader' => $defaultRequests[0]['headers']['Expect'] ?? null,
            'overrideGetUserAgent' => $overrideRequests[0]['headers']['User-Agent'] ?? null,
            'overridePostUserAgent' => $overrideRequests[1]['headers']['User-Agent'] ?? null,
            'overridePostExpectHeader' => $overrideRequests[1]['headers']['Expect'] ?? null,
            'overridePostBodyPreserved' => $overrideRequests[1]['body'] === $request->requestBytes(),
            'responseSuccessful' => $response->isSuccessful(),
        ];
    })(),
    'smartHttpTransportOptionsBoundary' => (static function () use ($packet, $flush, $advertisementBytes, $blob): array {
        $responseBytes = $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $requests = [];
        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'https://git.example.test/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$requests, $packet, $flush, $advertisementBytes, $responseBytes): array {
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
                            'headers' => [
                                'Content-Type' => 'application/x-git-receive-pack-advertisement',
                                'Set-Cookie' => 'wp_transport_options=observed; Path=/; Secure',
                            ],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                [],
                12.0,
                [],
                [
                    'proxy' => 'http://proxy.example.test:8080',
                    'connectTimeout' => 4.25,
                    'lowSpeedLimit' => 512,
                    'lowSpeedTime' => 7,
                    'httpVersion' => 'HTTP/2',
                    'verbose' => true,
                ],
            ),
            'port-libs/wordpress',
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);
        $response = $client->send($request);

        return [
            'requestMethods' => array_column($requests, 'method'),
            'getConnectTimeout' => $requests[0]['httpOptions']['connectTimeout'] ?? null,
            'postConnectTimeout' => $requests[1]['httpOptions']['connectTimeout'] ?? null,
            'lowSpeedLimit' => $requests[0]['httpOptions']['lowSpeedLimit'] ?? null,
            'lowSpeedTime' => $requests[0]['httpOptions']['lowSpeedTime'] ?? null,
            'httpVersion' => $requests[0]['httpOptions']['httpVersion'] ?? null,
            'verbose' => $requests[0]['httpOptions']['verbose'] ?? null,
            'proxy' => $requests[0]['httpOptions']['proxy'] ?? null,
            'timeout' => $requests[0]['timeout'] ?? null,
            'postCookie' => $requests[1]['headers']['Cookie'] ?? null,
            'postBodyPreserved' => $requests[1]['body'] === $request->requestBytes(),
            'responseSuccessful' => $response->isSuccessful(),
        ];
    })(),
    'smartHttpProtocolHeaderBoundary' => (static function () use ($packet, $flush, $advertisementBytes): array {
        $v2Advertisement = $packet("version 2\n") . $flush;
        $v2Requests = [];
        $v2Transport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body) use (&$v2Requests, $packet, $flush, $v2Advertisement): array {
                $v2Requests[] = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'body' => $body,
                ];

                if ($method === 'GET') {
                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                        'body' => $packet("# service=git-receive-pack\n") . $flush . $v2Advertisement,
                    ];
                }

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                    'body' => $flush,
                ];
            },
            ['session-id', 'object-format=sha1'],
            30.0,
            [],
            ['protocolVersion' => 2],
        );
        $v2Transport->readAdvertisement();
        $v2Transport->writeRequest('0000');
        $v2Transport->readResponse();

        $downgradeRequests = [];
        $downgradeTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body) use (&$downgradeRequests, $packet, $flush, $advertisementBytes): array {
                $downgradeRequests[] = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'body' => $body,
                ];

                if ($method === 'GET') {
                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                        'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                    ];
                }

                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                    'body' => $flush,
                ];
            },
            ['session-id'],
            30.0,
            [],
            ['protocolVersion' => 2],
        );
        $downgradeTransport->readAdvertisement();
        $downgradeTransport->writeRequest('0000');
        $downgradeTransport->readResponse();

        return [
            'v2RequestMethods' => array_column($v2Requests, 'method'),
            'v2DiscoveryGitProtocol' => $v2Requests[0]['headers']['Git-Protocol'] ?? null,
            'v2PostGitProtocol' => $v2Requests[1]['headers']['Git-Protocol'] ?? null,
            'v2PostBodyPreserved' => $v2Requests[1]['body'] === '0000',
            'downgradeDiscoveryGitProtocol' => $downgradeRequests[0]['headers']['Git-Protocol'] ?? null,
            'downgradePostGitProtocol' => $downgradeRequests[1]['headers']['Git-Protocol'] ?? null,
            'downgradePostBodyPreserved' => $downgradeRequests[1]['body'] === '0000',
        ];
    })(),
    'smartHttpSamePortRedirectBoundary' => (static function () use ($packet, $flush, $advertisementBytes, $blob): array {
        $responseBytes = $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush;
        $requests = [];
        $client = new ReceivePackClient(
            new SmartHttpReceivePackTransport(
                'http://git.example.test:8443/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$requests, $packet, $flush, $advertisementBytes, $responseBytes): array {
                    $requests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    if (count($requests) === 1) {
                        return [
                            'status' => 301,
                            'headers' => [
                                'Location' => 'https://git.example.test:8443/redirected.git/info/refs?service=git-receive-pack',
                                'Set-Cookie' => 'wp_same_port_redirect=ok; Path=/; Secure',
                            ],
                            'body' => '',
                        ];
                    }

                    if ($method === 'GET') {
                        return [
                            'status' => 200,
                            'headers' => [
                                'Content-Type' => 'application/x-git-receive-pack-advertisement',
                                'Set-Cookie' => 'wp_same_port_repo=ready; Path=/redirected.git; Secure',
                            ],
                            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                        'body' => $responseBytes,
                    ];
                },
                ['version=1'],
            ),
            'port-libs/wordpress',
        );
        $session = $client->handshake();
        $session->createOrUpdate('refs/heads/main', $blob->oid());
        $request = $session->buildRequest([$blob]);
        $response = $client->send($request);

        $mismatchRequests = [];
        $mismatchRejected = false;
        try {
            (new SmartHttpReceivePackTransport(
                'http://git.example.test:8080/wp-content.git',
                static function (string $method, string $url, array $headers, ?string $body) use (&$mismatchRequests): array {
                    $mismatchRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'headers' => $headers,
                        'body' => $body,
                    ];

                    return [
                        'status' => 301,
                        'headers' => ['Location' => 'https://git.example.test:8443/redirected.git/info/refs?service=git-receive-pack'],
                        'body' => '',
                    ];
                },
            ))->readAdvertisement();
        } catch (RuntimeException $exception) {
            $mismatchRejected = str_contains($exception->getMessage(), 'does not share authority');
        }

        return [
            'requestUrls' => array_column($requests, 'url'),
            'postCookie' => $requests[2]['headers']['Cookie'] ?? null,
            'postBodyPreserved' => $requests[2]['body'] === $request->requestBytes(),
            'responseSuccessful' => $response->isSuccessful(),
            'differentPortUpgradeRejected' => $mismatchRejected,
            'differentPortRequestCount' => count($mismatchRequests),
        ];
    })(),
    'smartHttpStatusBoundary' => (static function () use ($packet, $flush, $advertisementBytes): array {
        $captureStatus = static function (callable $operation): array {
            try {
                $operation();
            } catch (SmartHttpStatusException $exception) {
                return [
                    'status' => $exception->statusCode(),
                    'kind' => $exception->kind(),
                    'retryable' => $exception->retryable(),
                    'message' => $exception->getMessage(),
                ];
            }

            return ['status' => 0, 'kind' => 'none', 'retryable' => false, 'message' => ''];
        };

        $unauthorized = $captureStatus(static fn () => (new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 401,
                'headers' => ['Content-Type' => 'text/plain', 'WWW-Authenticate' => 'Basic realm="Git"'],
                'body' => 'Repository not found.',
            ],
        ))->readAdvertisement());

        $notFound = $captureStatus(static fn () => (new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => ['status' => 404, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'Not Found'],
        ))->readAdvertisement());

        $serverError = $captureStatus(static fn () => (new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => ['status' => 500, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'error'],
        ))->readAdvertisement());

        $postServerErrorRequests = [];
        $postServerErrorTransport = new SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static function (string $method, string $url, array $headers, ?string $body) use (&$postServerErrorRequests, $packet, $flush, $advertisementBytes): array {
                $postServerErrorRequests[] = ['method' => $method, 'url' => $url, 'body' => $body];

                if ($method === 'GET') {
                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                        'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                    ];
                }

                return ['status' => 503, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'temporarily unavailable'];
            },
        );
        $postServerErrorTransport->readAdvertisement();
        $postServerErrorTransport->writeRequest('0000');
        $postServerError = $captureStatus(static fn () => $postServerErrorTransport->readResponse());

        return [
            'unauthorized' => $unauthorized,
            'notFound' => $notFound,
            'serverError' => $serverError,
            'postServerError' => $postServerError,
            'postServerErrorRequestMethods' => array_column($postServerErrorRequests, 'method'),
            'classifications' => [
                401 => SmartHttpReceivePackTransport::classifyHttpStatus(401),
                404 => SmartHttpReceivePackTransport::classifyHttpStatus(404),
                500 => SmartHttpReceivePackTransport::classifyHttpStatus(500),
            ],
        ];
    })(),
    'streamWatchdogTimeoutReported' => (static function (): bool {
        if (!function_exists('stream_socket_pair')) {
            return false;
        }

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            return false;
        }

        [$read, $write] = $pair;
        stream_set_timeout($read, 0, 10_000);
        $transport = new StreamReceivePackTransport($read, $write);
        try {
            $transport->readAdvertisement();
        } catch (RuntimeException $exception) {
            fclose($read);
            fclose($write);

            return str_contains($exception->getMessage(), 'timed out while reading advertisement packet length');
        }

        fclose($read);
        fclose($write);

        return false;
    })(),
    'advertisementErrorReported' => (static function () use ($packet): bool {
        $read = fopen('php://temp', 'r+b');
        $write = fopen('php://temp', 'r+b');
        if ($read === false || $write === false) {
            return false;
        }
        fwrite($read, $packet("ERR repository access denied\n") . '0000');
        rewind($read);

        try {
            (new ReceivePackClient(new StreamReceivePackTransport($read, $write), 'port-libs/wordpress'))->handshake();
        } catch (RuntimeException $exception) {
            fclose($read);
            fclose($write);

            return str_contains($exception->getMessage(), 'receive-pack error repository access denied');
        }

        fclose($read);
        fclose($write);

        return false;
    })(),
    'oversizeAdvertisementRejected' => (static function (): bool {
        try {
            ReceivePackAdvertisement::fromV1PacketLines('ffff');
        } catch (InvalidArgumentException $exception) {
            return str_contains($exception->getMessage(), 'packet line exceeds maximum length ffff');
        }

        return false;
    })(),
    'unsafeSshTargetRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('git.example.test: -upload-pack=/tmp/helper');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshHostDelimiterRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('ssh://bad%20host.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshUserDelimiterRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('bad user@git.example.test:wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshScpIpv6UserRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('deploy@[2001:db8::42]:wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshEncodedUserDelimiterRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('ssh://deploy%40tenant@git.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshPasswordRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('ssh://deploy:secret@git.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshLegacyHostRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('git+ssh://-oProxyCommand=open$IFS-aCalculator/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshNumericPortOverflowRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('ssh://git.example.test:65536/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshAuthorityTooLongRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::parseRepositoryUrl('ssh://' . str_repeat('h', 1025) . '/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshFeatureProbeHostRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::connectorContext(
                'deploy@-git-proxy.example.test:wp-content.git',
                ['sshCommand' => 'echo hi'],
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshFallbackCommandRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::connectorContext(
                'deploy@git.example.test:wp-content.git',
                ['commandWithoutShellFallback' => "ssh\nfallback"],
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshIdentityUsernameRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::connectorContext(
                'ssh://git.example.test/var/www/wp-content.git',
                ['identityUsername' => '-deploy'],
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSshIdentityClearedOptionHostRejected' => (static function (): bool {
        try {
            SshReceivePackTransport::connectorContext(
                'ssh://stale@-git-proxy.example.test/var/www/wp-content.git',
                ['identityUsername' => ''],
            );
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'wordpressUse' => 'A PHP deployment tool can run a receive-pack handshake/request/response cycle over native stream resources, accept smart HTTP receive-pack advertisements with or without the optional service announcement, accept any matching receive-pack Content-Type header value when intermediaries duplicate response headers, surface receive-pack advertisement ERR packets and oversized packet-line boundaries before ref parsing, preflight SSH targets including explicit bracketed IPv6 URLs, scp-like bracketed IPv6 hosts without user info, scp-like usernames containing at-signs, legacy ssh+git/git+ssh receive-pack URLs, URL-form non-numeric port-looking host suffixes, upstream root-path receive-pack targets, and credential-helper identity username replacement/clearing before handing streams to a caller-approved SSH adapter, reject scp-like bracketed IPv6 hosts with user info like upstream Gitoxide, normalize scp-like /~ repository paths before remote git-receive-pack command construction, pass protocol-v2 GIT_PROTOCOL, locale, redacted credential-helper context metadata, and upstream-shaped Git environment-removal keys to an opted-in adapter without owning live SSH authentication, plan upstream SSH, plink, putty, tortoiseplink, simple-client, disallow-shell argv boundaries, command-without-shell fallback boundaries, and non-executing -G feature probes for unknown SSH commands, classify caller-provided SSH stderr lines into upstream permission, host-resolution, and connection failure buckets, allow option-looking SSH hosts only when an explicit user makes the combined user@host argument safe, reject option-looking hosts during unknown-command feature probing unless the caller pins an explicit program kind, reject unsafe or ambiguous SSH identity usernames, reject clearing an identity username when that exposes an option-looking host as a raw SSH command argument, reject numeric SSH port overflows, URL-form SSH authorities beyond the upstream 1024-byte pre-path boundary, decoded SSH host/user delimiters including encoded at-sign or colon username delimiters, reject unsupported SSH URL passwords, reject legacy SSH hosts that look like command-line options without an explicit user, reject decoded smart HTTP credential control bytes, URL/proxy/no-proxy host delimiters, raw URL/proxy control bytes, encoded URL path control bytes, Git-Protocol extra-parameter control bytes, and caller header control bytes, and construct git-daemon service requests from validated git:// URLs or explicit absolute repository URL paths with decoded URL components, upstream-style value-only or key=value extra parameters, no control bytes, decoded host delimiters, or malformed extra parameters, while preserving bracketed IPv6 virtual-host targets.',
];
