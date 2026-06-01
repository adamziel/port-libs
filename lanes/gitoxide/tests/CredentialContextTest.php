<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialContext;

return [
    'credential context encodes and decodes git helper protocol fields' => static function (TestRunner $t): void {
        $context = new CredentialContext(
            protocol: 'https',
            host: 'github.com',
            path: 'byron/gitoxide',
            username: 'user',
            password: 'pass',
            oauthRefreshToken: 'refresh',
            passwordExpiryUtc: 1711398853,
            url: 'https://github.com/byron/gitoxide',
            quit: true,
        );

        $bytes = $context->storageBytes();
        $t->same(
            "url=https://github.com/byron/gitoxide\npath=byron/gitoxide\nprotocol=https\nhost=github.com\nusername=user\npassword=pass\noauth_refresh_token=refresh\npassword_expiry_utc=1711398853\n",
            $bytes,
        );

        $t->same(false, str_contains($bytes, 'quit='));

        $decoded = CredentialContext::fromBytes($bytes);
        $t->same('https', $decoded->protocol);
        $t->same('github.com', $decoded->host);
        $t->same('byron/gitoxide', $decoded->path);
        $t->same('user', $decoded->username);
        $t->same('pass', $decoded->password);
        $t->same('refresh', $decoded->oauthRefreshToken);
        $t->same(1711398853, $decoded->passwordExpiryUtc);
        $t->same('https://github.com/byron/gitoxide', $decoded->url);
        $t->same(null, $decoded->quit, 'quit is not serialized by write_to');
        $t->same(true, CredentialContext::fromBytes("quit=true\nurl=https://example.com")->quit);
    },
    'credential context parser skips unknown fields and stops at blank line' => static function (TestRunner $t): void {
        $input = "protocol=https\nhost=example.com\nunknown=value\n\npassword=secr3t\nusername=bob";
        $context = CredentialContext::fromBytes($input);

        $t->same('https', $context->protocol);
        $t->same('example.com', $context->host);
        $t->same(null, $context->username);
        $t->same(null, $context->password);

        $t->same(true, CredentialContext::fromBytes("quit=42\n")->quit);
        $t->same(true, CredentialContext::fromBytes("quit=-42\n")->quit);
        $t->same(true, CredentialContext::fromBytes("quit=+10\n")->quit);
        $t->same(true, CredentialContext::fromBytes("quit=on\n")->quit);
        $t->same(true, CredentialContext::fromBytes("quit=YES\n")->quit);
        $t->same(false, CredentialContext::fromBytes("quit=\n")->quit);
        $t->same(false, CredentialContext::fromBytes("quit=0\n")->quit);
        $t->same(false, CredentialContext::fromBytes("quit=no\n")->quit);
        $t->same(null, CredentialContext::fromBytes("quit=yesn't\n")->quit);

        $t->same(10, CredentialContext::fromBytes("password_expiry_utc=+10\n")->passwordExpiryUtc);
        $t->same(null, CredentialContext::fromBytes("password_expiry_utc=never\n")->passwordExpiryUtc);
        $t->same(9223372036854775807, CredentialContext::fromBytes("password_expiry_utc=9223372036854775807\n")->passwordExpiryUtc);
        $t->same(PHP_INT_MIN, CredentialContext::fromBytes("password_expiry_utc=-9223372036854775808\n")->passwordExpiryUtc);
        $t->same(null, CredentialContext::fromBytes("password_expiry_utc=9223372036854775808\n")->passwordExpiryUtc);
        $t->same(null, CredentialContext::fromBytes("password_expiry_utc=-9223372036854775809\n")->passwordExpiryUtc);
        $t->same(null, CredentialContext::fromBytes("quit=9223372036854775808\n")->quit);
        $t->same(null, CredentialContext::fromBytes("quit=-9223372036854775809\n")->quit);
    },
    'credential context validates helper protocol bytes' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => CredentialContext::fromBytes("url=https://foo\0\n"));
        $t->throws(InvalidArgumentException::class, static fn () => CredentialContext::fromBytes("not-a-field\n"));
        $t->throws(InvalidArgumentException::class, static fn () => (new CredentialContext(path: "foo\nbar"))->storageBytes());
        $t->throws(InvalidArgumentException::class, static fn () => CredentialContext::fromBytes("username=\xff\n"));
        $t->throws(InvalidArgumentException::class, static fn () => CredentialContext::fromBytes("bad\xff=value\n"));
        $t->throws(InvalidArgumentException::class, static fn () => (new CredentialContext(username: "bad\xff"))->storageBytes());
        $t->throws(
            InvalidArgumentException::class,
            static fn () => CredentialContext::fromBytes("username=bad\xff\nusername=deploy\n"),
        );

        $duplicateBytePath = CredentialContext::fromBytes("path=wp-content\xff.git\npath=wp-content.git\n");
        $t->same('wp-content.git', $duplicateBytePath->path);
    },
    'credential context preserves byte string path and url fields' => static function (TestRunner $t): void {
        $context = CredentialContext::fromBytes(
            "url=https://git.example.test/wp-content\xff.git\n"
            . "path=wp-content\xff.git\n"
            . "protocol=https\n"
            . "host=git.example.test\n"
        );

        $t->same("https://git.example.test/wp-content\xff.git", $context->url);
        $t->same("wp-content\xff.git", $context->path);
        $t->contains("url=https://git.example.test/wp-content\xff.git\n", $context->storageBytes());
        $t->contains("path=wp-content\xff.git\n", $context->storageBytes());

        $bareCarriageReturn = CredentialContext::fromBytes("path=wp-content\r");
        $t->same("wp-content\r", $bareCarriageReturn->path);
        $t->contains("path=wp-content\r\n", $bareCarriageReturn->storageBytes());

        $crlfTerminated = CredentialContext::fromBytes("path=wp-content\r\n");
        $t->same('wp-content', $crlfTerminated->path);
    },
    'credential context url and prompt helpers match gix credentials context' => static function (TestRunner $t): void {
        $t->same(null, (new CredentialContext())->toUrl());
        $t->same('https://', (new CredentialContext(protocol: 'https'))->toUrl());
        $t->same('https://user@', (new CredentialContext(protocol: 'https', username: 'user'))->toUrl());
        $t->same('https://host', (new CredentialContext(protocol: 'https', host: 'host'))->toUrl());
        $t->same('file:///dir/git', (new CredentialContext(protocol: 'file', path: 'dir/git'))->toUrl());
        $t->same('file:///dir/git', (new CredentialContext(protocol: 'file', path: '/dir/git'))->toUrl());
        $t->same(
            'https://user@example.com:8080/GitoxideLabs/gitoxide',
            (new CredentialContext(
                protocol: 'https',
                host: 'example.com:8080',
                path: 'GitoxideLabs/gitoxide',
                username: 'user',
                password: 'secret',
            ))->toUrl(),
        );
        $t->same('Username: ', (new CredentialContext())->toPrompt('Username'));
        $t->same('Password for https://host: ', (new CredentialContext(protocol: 'https', host: 'host'))->toPrompt('Password'));
    },
    'credential context destructures urls with upstream http path rules' => static function (TestRunner $t): void {
        $ssh = (new CredentialContext(url: 'ssh://user@host:21/path'))->destructureUrl();
        $t->same('ssh', $ssh->protocol);
        $t->same('user', $ssh->username);
        $t->same('host:21', $ssh->host);
        $t->same('path', $ssh->path);

        $http = (new CredentialContext(url: 'http://user:password@host/path'))->destructureUrl();
        $t->same('http', $http->protocol);
        $t->same('user', $http->username);
        $t->same('password', $http->password);
        $t->same('host', $http->host);
        $t->same(null, $http->path);

        $withHttpPath = (new CredentialContext(url: 'https://github.com/byron/gitoxide/'))->destructureUrl(true);
        $t->same('github.com', $withHttpPath->host);
        $t->same('byron/gitoxide', $withHttpPath->path);

        $emptyHttpPath = (new CredentialContext(
            url: 'https://github.com/',
            path: 'stale/repository/path',
        ))->destructureUrl(true);
        $t->same(null, $emptyHttpPath->path);

        $decodedHttp = (new CredentialContext(
            url: 'https://USER%20name:p%40ss%3Aword@EXAMPLE.com:443/path/with%20spaces/file?token=abc#frag',
        ))->destructureUrl(true);
        $t->same('USER name', $decodedHttp->username);
        $t->same('p@ss:word', $decodedHttp->password);
        $t->same('example.com', $decodedHttp->host);
        $t->same('path/with spaces/file?token=abc#frag', $decodedHttp->path);

        $defaultGitPort = (new CredentialContext(url: 'git://HOST.xz:9418/~repo'))->destructureUrl();
        $t->same('git', $defaultGitPort->protocol);
        $t->same('host.xz', $defaultGitPort->host);
        $t->same('~repo', $defaultGitPort->path);

        $sshIpv6 = (new CredentialContext(url: 'ssh://user@[::1]:22/repo'))->destructureUrl();
        $t->same('ssh', $sshIpv6->protocol);
        $t->same('user', $sshIpv6->username);
        $t->same('::1', $sshIpv6->host);
        $t->same('repo', $sshIpv6->path);

        $scpLike = (new CredentialContext(url: 'User@HOST.xz:repo.git'))->destructureUrl();
        $t->same('ssh', $scpLike->protocol);
        $t->same('User', $scpLike->username);
        $t->same('host.xz', $scpLike->host);
        $t->same('repo.git', $scpLike->path);
        $t->same('User@HOST.xz:repo.git', $scpLike->url);

        $fileUrl = (new CredentialContext(url: 'file:///srv/repo.git'))->destructureUrl();
        $t->same('file', $fileUrl->protocol);
        $t->same(null, $fileUrl->host);
        $t->same('srv/repo.git', $fileUrl->path);

        $fileUrlClearsNetworkContext = (new CredentialContext(
            url: 'file:///srv/wp-content.git',
            host: 'stale.example.test',
            username: 'stale-user',
        ))->destructureUrl(true);
        $t->same('file', $fileUrlClearsNetworkContext->protocol);
        $t->same(null, $fileUrlClearsNetworkContext->host);
        $t->same(null, $fileUrlClearsNetworkContext->username);
        $t->same('srv/wp-content.git', $fileUrlClearsNetworkContext->path);

        $localPathClearsNetworkContext = (new CredentialContext(
            url: '/srv/wp-content.git',
            host: 'stale.example.test',
            username: 'stale-user',
            password: 'stale-token',
        ))->destructureUrl(true);
        $t->same('file', $localPathClearsNetworkContext->protocol);
        $t->same(null, $localPathClearsNetworkContext->host);
        $t->same(null, $localPathClearsNetworkContext->username);
        $t->same(null, $localPathClearsNetworkContext->password);
        $t->same('srv/wp-content.git', $localPathClearsNetworkContext->path);

        $fileAuthority = (new CredentialContext(
            url: 'file://Deploy@[::1]/var/cache/wp-content.git',
        ))->destructureUrl(true);
        $t->same('file', $fileAuthority->protocol);
        $t->same('Deploy', $fileAuthority->username);
        $t->same('[::1]', $fileAuthority->host);
        $t->same('var/cache/wp-content.git', $fileAuthority->path);

        $extensionPathless = (new CredentialContext(
            url: 'rad://deploy@example.git',
            path: 'stale/wp-content.git',
        ))->destructureUrl(true);
        $t->same('rad', $extensionPathless->protocol);
        $t->same('deploy', $extensionPathless->username);
        $t->same('example.git', $extensionPathless->host);
        $t->same(null, $extensionPathless->path);

        $extensionHostless = (new CredentialContext(
            url: 'abc:///wp-content/site.git',
            host: 'stale.example.test',
        ))->destructureUrl(true);
        $t->same('abc', $extensionHostless->protocol);
        $t->same(null, $extensionHostless->host);
        $t->same('wp-content/site.git', $extensionHostless->path);

        $composed = (new CredentialContext(
            protocol: 'https',
            host: 'github.com',
            path: 'org/repo',
            username: 'user',
            password: 'pass-to-be-ignored',
        ))->destructureUrl();
        $t->same('https://user@github.com/org/repo', $composed->url);
        $t->same('org/repo', $composed->path);
        $t->same(null, $composed->password);

        $t->throws(InvalidArgumentException::class, static fn () => (new CredentialContext(host: 'github.com'))->destructureUrl());
        $t->throws(InvalidArgumentException::class, static fn () => (new CredentialContext(protocol: 'https'))->destructureUrl());
    },
    'credential context redacts and clears secrets for logs' => static function (TestRunner $t): void {
        $context = new CredentialContext(
            protocol: 'https',
            host: 'git.example.test',
            username: 'deploy',
            password: 'secret',
            oauthRefreshToken: 'refresh',
        );

        $t->same('<redacted>', $context->redacted()->password);
        $t->same('<redacted>', $context->redacted()->oauthRefreshToken);
        $t->same(null, $context->clearSecrets()->password);
        $t->same(null, $context->clearSecrets()->oauthRefreshToken);
    },
    'wordpress credential context fixture supports deployment helper exchange' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-credential-context.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-credential-context.php';

        $t->contains("protocol=https\n", $fixture['requestBytes']);
        $t->contains("host=git.example.test\n", $fixture['requestBytes']);
        $t->same('https://deploy-bot@git.example.test/wp-content.git', $fixture['credentialUrl']);
        $t->same('git.example.test', $fixture['encodedContext']['host']);
        $t->same('wp-content deploy.git', $fixture['encodedContext']['path']);
        $t->same('Deploy Bot', $fixture['encodedContext']['username']);
        $t->same(true, $fixture['fileUrlClearedHost']);
        $t->same(true, $fixture['fileUrlClearedUsername']);
        $t->same('srv/wp-content.git', $fixture['fileUrlPath']);
        $t->same(true, $fixture['localPathClearedHost']);
        $t->same(true, $fixture['localPathClearedUsername']);
        $t->same('srv/wp-content.git', $fixture['localPathPath']);
        $t->same([
            'protocol' => 'file',
            'username' => 'Deploy',
            'host' => '[::1]',
            'path' => 'var/cache/wp-content.git',
        ], $fixture['fileAuthorityContext']);
        $t->same([
            'protocol' => 'rad',
            'username' => 'deploy',
            'host' => 'example.git',
            'path' => null,
        ], $fixture['pathlessExtensionContext']);
        $t->same([
            'protocol' => 'abc',
            'host' => null,
            'path' => 'wp-content/site.git',
        ], $fixture['hostlessExtensionContext']);
        $t->same(null, $fixture['clearedPassword']);
        $t->same(false, $fixture['emptyQuitFalse']);
        $t->same(1711398853, $fixture['passwordExpiryUtc']);
        $t->same(true, $fixture['overflowExpiryIgnored']);
        $t->same(true, $fixture['overflowQuitIgnored']);
        $t->contains('password=<redacted>', $fixture['redactedBytes']);
        $t->same(true, $fixture['rootHttpPathCleared']);
        $t->same(true, $fixture['duplicateInvalidStringRejected']);
        $t->same('wp-content.git', $fixture['duplicateBytePath']);
        $t->same(true, $fixture['bareCarriageReturnPathPreserved']);
        $t->same(true, $fixture['crlfPathTerminatorStripped']);
        $t->same([
            'action' => 'get',
            'protocol' => 'https',
            'host' => 'git.example.test',
            'url' => null,
        ], $fixture['helperProgramProtocolHost']);
        $t->same(true, $fixture['helperProgramMissingCredential']);
        $t->same([
            'action' => 'get',
            'url' => 'https://git.example.test/wp-content.git',
            'protocol' => null,
            'host' => null,
        ], $fixture['helperProgramUrlOnly']);
        $t->same("username=deploy-bot\npassword=wp-deploy-token\n", $fixture['helperProgramOutput']);
        $t->same(['username' => 'deploy-bot', 'password' => 'wp-deploy-token', 'oauthRefreshToken' => null], $fixture['helperInvocationIdentity']);
        $t->same(true, $fixture['helperInvocationQuit']);
        $t->same(true, $fixture['helperInvocationNextQuit']);
        $t->same("username=deploy-bot\npassword=wp-deploy-token\nquit=1\n\n", $fixture['helperInvocationStorePayload']);
        $t->same($fixture['helperInvocationStorePayload'], $fixture['helperInvocationErasePayload']);
        $t->same($fixture['credentialUrl'], $summary['credentialUrl']);
        $t->same($fixture['encodedContext']['path'], $summary['encodedPath']);
        $t->same(true, $summary['fileUrlClearedHost']);
        $t->same(true, $summary['localPathClearedHost']);
        $t->same($fixture['fileAuthorityContext'], $summary['fileAuthorityContext']);
        $t->same($fixture['pathlessExtensionContext'], $summary['pathlessExtensionContext']);
        $t->same($fixture['hostlessExtensionContext'], $summary['hostlessExtensionContext']);
        $t->same(true, $summary['rootHttpPathCleared']);
        $t->same($fixture['helperProgramProtocolHost'], $summary['helperProgramProtocolHost']);
        $t->same($fixture['helperProgramUrlOnly'], $summary['helperProgramUrlOnly']);
        $t->same($fixture['helperInvocationIdentity'], $summary['helperInvocationIdentity']);
        $t->same(true, $summary['helperInvocationQuit']);
        $t->same(true, $summary['helperInvocationNextQuit']);
        $t->same(true, $summary['bareCarriageReturnPathPreserved']);
        $t->same(true, $summary['crlfPathTerminatorStripped']);
        $t->same(false, $summary['emptyQuitFalse']);
        $t->same(true, $summary['overflowExpiryIgnored']);
        $t->same(true, $summary['overflowQuitIgnored']);
        $t->same(false, $summary['secretsInCleartextLog']);
    },
];
