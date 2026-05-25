<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\GitDaemonReceivePackTransport;
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

return [
    'oldCommit' => $oldCommit,
    'newCommit' => $commit->oid(),
    'advertisementBytes' => $advertisementBytes,
    'requestBytes' => $requestBytes,
    'responseBytes' => $responseBytes,
    'responseSuccessful' => $response->isSuccessful(),
    'progressMessages' => $response->progressMessages(),
    'acceptedRefs' => array_map(
        static fn ($status): string => $status->effectiveRefName(),
        $response->refStatuses()
    ),
    'sshTarget' => SshReceivePackTransport::parseRepositoryUrl('deploy@git.example.test:wp-content.git'),
    'sshIpv6Target' => SshReceivePackTransport::parseRepositoryUrl('ssh://deploy@[2001:db8::42]:2222/srv/wp-content.git'),
    'sshCommand' => SshReceivePackTransport::receivePackCommand('wp-content.git'),
    'gitDaemonServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytes('/wp-content.git', 'git.example.test', 9418, ['version=2']),
    'gitDaemonUrlServiceRequest' => GitDaemonReceivePackTransport::serviceRequestBytesForUrl('git://git.example.test:9418/wp-content.git', ['version=2']),
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
    'wordpressUse' => 'A PHP deployment tool can run a receive-pack handshake/request/response cycle over native stream resources, preflight SSH targets including bracketed IPv6 URLs before handing streams to a caller-approved SSH adapter, reject decoded SSH host/user delimiters, reject decoded smart HTTP credential control bytes, and construct git-daemon service requests from validated git:// URLs or explicit absolute repository URL paths with decoded URL components, without control bytes, decoded host delimiters, or malformed extra parameters, while preserving bracketed IPv6 virtual-host targets.',
];
