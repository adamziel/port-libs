<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\ReceivePackAdvertisement;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\GitDaemonReceivePackTransport;
use PortLibs\Gitoxide\ProtocolCapabilities;
use PortLibs\Gitoxide\PushCommand;
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
    . $flush;

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
    'sshOptionLikeHostWithUserTarget' => SshReceivePackTransport::parseRepositoryUrl('deploy@-git-proxy.example.test:wp-content.git'),
    'sshScpLikeAtUserTarget' => SshReceivePackTransport::parseRepositoryUrl('user@name@host.xz:wp-content.git'),
    'sshCommand' => SshReceivePackTransport::receivePackCommand('wp-content.git'),
    'sshHomeCommand' => SshReceivePackTransport::receivePackCommand('~/wp-content.git'),
    'sshProtocolV2Context' => SshReceivePackTransport::connectorContext(
        'ssh://deploy@git.example.test:2222/var/www/wp-content.git',
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
            \PortLibs\Gitoxide\SmartHttpReceivePackTransport::infoRefsUrl('https://deploy:bad%0atoken@git.example.test/wp-content.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpCredentialTabRejected' => (static function (): bool {
        try {
            \PortLibs\Gitoxide\SmartHttpReceivePackTransport::infoRefsUrl('https://bad%09deploy:token@git.example.test/wp-content.git');
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
            new \PortLibs\Gitoxide\SmartHttpReceivePackTransport(
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
            \PortLibs\Gitoxide\SmartHttpReceivePackTransport::infoRefsUrl('https://git.example.test/wp%0acontent.git');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpExtraParameterTabRejected' => (static function (): bool {
        try {
            new \PortLibs\Gitoxide\SmartHttpReceivePackTransport(
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
            new \PortLibs\Gitoxide\SmartHttpReceivePackTransport(
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
            new \PortLibs\Gitoxide\SmartHttpReceivePackTransport(
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
            \PortLibs\Gitoxide\SmartHttpReceivePackTransport::infoRefsUrl("https://git.example.test/wp-content.git\t");
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(),
    'unsafeSmartHttpRawProxyControlByteRejected' => (static function (): bool {
        try {
            new \PortLibs\Gitoxide\SmartHttpReceivePackTransport(
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
        $transport = new \PortLibs\Gitoxide\SmartHttpReceivePackTransport(
            'https://git.example.test/wp-content.git',
            static fn (): array => [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                'body' => $advertisementBytes,
            ],
        );

        return $transport->readAdvertisement() === $advertisementBytes;
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
    'wordpressUse' => 'A PHP deployment tool can run a receive-pack handshake/request/response cycle over native stream resources, accept smart HTTP receive-pack advertisements with or without the optional service announcement, surface receive-pack advertisement ERR packets and oversized packet-line boundaries before ref parsing, preflight SSH targets including explicit bracketed IPv6 URLs, scp-like bracketed IPv6 hosts without user info, scp-like usernames containing at-signs, and legacy ssh+git/git+ssh receive-pack URLs before handing streams to a caller-approved SSH adapter, reject scp-like bracketed IPv6 hosts with user info like upstream Gitoxide, normalize scp-like /~ repository paths before remote git-receive-pack command construction, pass protocol-v2 GIT_PROTOCOL and redacted credential-helper context metadata to an opted-in adapter without owning live SSH authentication, plan upstream SSH, plink, putty, tortoiseplink, simple-client, disallow-shell argv boundaries, and non-executing -G feature probes for unknown SSH commands, classify caller-provided SSH stderr lines into upstream permission, host-resolution, and connection failure buckets, allow option-looking SSH hosts only when an explicit user makes the combined user@host argument safe, reject option-looking hosts during unknown-command feature probing unless the caller pins an explicit program kind, reject decoded SSH host/user delimiters including encoded at-sign or colon username delimiters, reject unsupported SSH URL passwords, reject legacy SSH hosts that look like command-line options without an explicit user, reject decoded smart HTTP credential control bytes, URL/proxy/no-proxy host delimiters, raw URL/proxy control bytes, encoded URL path control bytes, Git-Protocol extra-parameter control bytes, and caller header control bytes, and construct git-daemon service requests from validated git:// URLs or explicit absolute repository URL paths with decoded URL components, upstream-style value-only or key=value extra parameters, no control bytes, decoded host delimiters, or malformed extra parameters, while preserving bracketed IPv6 virtual-host targets.',
];
