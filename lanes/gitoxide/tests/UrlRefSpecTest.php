<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitUrl;
use PortLibs\Gitoxide\RefSpec;

return [
    'git url parses and serializes ssh http git file and scp forms like gix-url' => static function (TestRunner $t): void {
        $cases = [
            'ssh://host.xz/path/to/repo.git/' => [
                'scheme' => GitUrl::SCHEME_SSH,
                'user' => null,
                'password' => null,
                'host' => 'host.xz',
                'port' => null,
                'path' => '/path/to/repo.git/',
                'defaultPort' => 22,
                'normalized' => 'ssh://host.xz/path/to/repo.git/',
            ],
            'ssh://user.name@host.xz/.git' => [
                'scheme' => GitUrl::SCHEME_SSH,
                'user' => 'user.name',
                'password' => null,
                'host' => 'host.xz',
                'port' => null,
                'path' => '/.git',
                'defaultPort' => 22,
                'normalized' => 'ssh://user.name@host.xz/.git',
            ],
            'ssh://user@host.xz:42/.git' => [
                'scheme' => GitUrl::SCHEME_SSH,
                'user' => 'user',
                'password' => null,
                'host' => 'host.xz',
                'port' => 42,
                'path' => '/.git',
                'defaultPort' => 42,
                'normalized' => 'ssh://user@host.xz:42/.git',
            ],
            'ssh://example.com/~byron/hello/git' => [
                'scheme' => GitUrl::SCHEME_SSH,
                'user' => null,
                'password' => null,
                'host' => 'example.com',
                'port' => null,
                'path' => '~byron/hello/git',
                'defaultPort' => 22,
                'normalized' => 'ssh://example.com/~byron/hello/git',
            ],
            'host.xz:~/to/git' => [
                'scheme' => GitUrl::SCHEME_SSH,
                'user' => null,
                'password' => null,
                'host' => 'host.xz',
                'port' => null,
                'path' => '~/to/git',
                'defaultPort' => 22,
                'normalized' => 'host.xz:~/to/git',
            ],
            'user@host.xz:././relative' => [
                'scheme' => GitUrl::SCHEME_SSH,
                'user' => 'user',
                'password' => null,
                'host' => 'host.xz',
                'port' => null,
                'path' => '././relative',
                'defaultPort' => 22,
                'normalized' => 'user@host.xz:././relative',
            ],
            'user@name@host.xz:path' => [
                'scheme' => GitUrl::SCHEME_SSH,
                'user' => 'user@name',
                'password' => null,
                'host' => 'host.xz',
                'port' => null,
                'path' => 'path',
                'defaultPort' => 22,
                'normalized' => 'user@name@host.xz:path',
            ],
            'https://github.com/byron/gitoxide' => [
                'scheme' => GitUrl::SCHEME_HTTPS,
                'user' => null,
                'password' => null,
                'host' => 'github.com',
                'port' => null,
                'path' => '/byron/gitoxide',
                'defaultPort' => 443,
                'normalized' => 'https://github.com/byron/gitoxide',
            ],
            'http://host.xz' => [
                'scheme' => GitUrl::SCHEME_HTTP,
                'user' => null,
                'password' => null,
                'host' => 'host.xz',
                'port' => null,
                'path' => '/',
                'defaultPort' => 80,
                'normalized' => 'http://host.xz/',
            ],
            'git://example.com/~byron/hello' => [
                'scheme' => GitUrl::SCHEME_GIT,
                'user' => null,
                'password' => null,
                'host' => 'example.com',
                'port' => null,
                'path' => '~byron/hello',
                'defaultPort' => 9418,
                'normalized' => 'git://example.com/~byron/hello',
            ],
            'file:///path/to/git' => [
                'scheme' => GitUrl::SCHEME_FILE,
                'user' => null,
                'password' => null,
                'host' => null,
                'port' => null,
                'path' => '/path/to/git',
                'defaultPort' => null,
                'normalized' => 'file:///path/to/git',
            ],
            '../../path/to/git' => [
                'scheme' => GitUrl::SCHEME_FILE,
                'user' => null,
                'password' => null,
                'host' => null,
                'port' => null,
                'path' => '../../path/to/git',
                'defaultPort' => null,
                'normalized' => '../../path/to/git',
            ],
        ];

        foreach ($cases as $input => $expected) {
            $url = GitUrl::parse($input);
            $actual = $url->toArray();
            foreach ($expected as $key => $value) {
                $t->same($value, $actual[$key], "{$input} {$key}");
            }
            $t->same($expected['normalized'], $url->toBytes(), "{$input} bytes");
            $t->same($expected['normalized'], $actual['normalized'], "{$input} normalized");
        }
    },
    'git url decodes components re-encodes canonical http bytes and redacts display passwords' => static function (TestRunner $t): void {
        $url = GitUrl::parse('http://user%20name:password%20secret@example.com:8080/~byron/hello');

        $t->same('user name', $url->user());
        $t->same('password secret', $url->password());
        $t->same('example.com', $url->host());
        $t->same(8080, $url->port());
        $t->same('/~byron/hello', $url->path());
        $t->same('http://user%20name:password%20secret@example.com:8080/~byron/hello', $url->toBytes());
        $t->same('http://user%20name:redacted@example.com:8080/~byron/hello', $url->display());
        $t->same('http://user%20name:redacted@example.com:8080/~byron/hello', (string) $url);

        $emptyUser = GitUrl::parse('http://@example.com/~byron/hello');
        $t->same(null, $emptyUser->user());
        $t->same('http://example.com/~byron/hello', $emptyUser->toBytes());

        $emptyPassword = GitUrl::parse('http://user:@example.com/~byron/hello');
        $t->same('user', $emptyPassword->user());
        $t->same(null, $emptyPassword->password());
        $t->same('http://user@example.com/~byron/hello', $emptyPassword->toBytes());

        $encodedPath = GitUrl::parse('https://example.com/path/with%20spaces/file?token=abc#frag');
        $t->same('/path/with spaces/file?token=abc#frag', $encodedPath->path());
        $t->same('https://example.com/path/with%20spaces/file?token=abc#frag', $encodedPath->toBytes());

        $unicodePath = GitUrl::parse('https://example.com/wp-content/caf%C3%A9-plugin.git');
        $t->same("/wp-content/caf\xC3\xA9-plugin.git", $unicodePath->path());
        $t->same("https://example.com/wp-content/caf\xC3\xA9-plugin.git", $unicodePath->toBytes());

        $unicodeCredentials = GitUrl::parse('https://deploy%C3%A9:p%C3%A4ss@example.com/repo.git');
        $t->same("deploy\xC3\xA9", $unicodeCredentials->user());
        $t->same("p\xC3\xA4ss", $unicodeCredentials->password());
        $t->same("https://deploy\xC3\xA9:p\xC3\xA4ss@example.com/repo.git", $unicodeCredentials->toBytes());
        $t->same("https://deploy\xC3\xA9:redacted@example.com/repo.git", $unicodeCredentials->display());

        $percent = GitUrl::parse('https://%20@%40:example.org/%20%25');
        $t->same(' ', $percent->user());
        $t->same('%40:example.org', $percent->host());
        $t->same('/ %', $percent->path());
        $t->same('https://%20@%40:example.org/%20%25', $percent->toBytes());
    },
    'git url keeps password-only http userinfo and query fragment path delimiters like gix-url' => static function (TestRunner $t): void {
        $passwordOnly = GitUrl::parse('http://:password@example.com/~byron/hello');
        $t->same(GitUrl::SCHEME_HTTP, $passwordOnly->scheme());
        $t->same('', $passwordOnly->user(), 'empty HTTP user is retained when a password is present');
        $t->same('password', $passwordOnly->password());
        $t->same('example.com', $passwordOnly->host());
        $t->same('/~byron/hello', $passwordOnly->path());
        $t->same('http://:password@example.com/~byron/hello', $passwordOnly->toBytes());
        $t->same('http://:redacted@example.com/~byron/hello', $passwordOnly->display());

        $passwordOnlyRoundtrip = GitUrl::parse($passwordOnly->toBytes());
        $t->same('', $passwordOnlyRoundtrip->user());
        $t->same('password', $passwordOnlyRoundtrip->password());
        $t->same($passwordOnly->toBytes(), $passwordOnlyRoundtrip->toBytes());

        $queryPath = GitUrl::parse('https://host/repo.git?token=abc');
        $t->same('/repo.git?token=abc', $queryPath->path());
        $t->same('https://host/repo.git?token=abc', $queryPath->toBytes());

        $fragmentPath = GitUrl::parse('https://host/repo.git#section');
        $t->same('/repo.git#section', $fragmentPath->path());
        $t->same('https://host/repo.git#section', $fragmentPath->toBytes());

        $combinedPath = GitUrl::parse('https://host/repo.git?token=abc#section');
        $t->same('/repo.git?token=abc#section', $combinedPath->path());
        $t->same('https://host/repo.git?token=abc#section', $combinedPath->toBytes());

        foreach ([
            'http://example.com/ path',
            'http://user name@example.com/path',
            'http://user:pass word@example.com/path',
            "http://example.com/\tpath",
            "http://user\tname@example.com/path",
            "http://user:pass\tword@example.com/path",
        ] as $invalidWhitespaceUrl) {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => GitUrl::parse($invalidWhitespaceUrl),
                "{$invalidWhitespaceUrl} rejects raw URL whitespace"
            );
        }
    },
    'git url mutates credentials and roundtrips canonical bytes like gix-url access helpers' => static function (TestRunner $t): void {
        $url = GitUrl::parse('https://user@host/path');
        $mutated = $url
            ->withUser('new user')
            ->withPassword('p@ss:word');

        $t->same('user', $url->user(), 'immutable setter leaves original user untouched');
        $t->same(null, $url->password(), 'immutable setter leaves original password untouched');
        $t->same('new user', $mutated->user());
        $t->same('p@ss:word', $mutated->password());
        $t->same('https://new%20user:p%40ss:word@host/path', $mutated->toBytes());
        $t->same('https://new%20user:redacted@host/path', $mutated->display());

        $roundtrip = GitUrl::parse($mutated->toBytes());
        $t->same('new user', $roundtrip->user());
        $t->same('p@ss:word', $roundtrip->password());
        $t->same('host', $roundtrip->host());
        $t->same('/path', $roundtrip->path());
        $t->same($mutated->toBytes(), $roundtrip->toBytes());

        $renamed = $mutated->withUser('deploy');
        $t->same('deploy', $renamed->user());
        $t->same('p@ss:word', $renamed->password());
        $t->same('https://deploy:p%40ss:word@host/path', $renamed->toBytes());

        $passwordCleared = $renamed->withPassword(null);
        $t->same('deploy', $passwordCleared->user());
        $t->same(null, $passwordCleared->password());
        $t->same('https://deploy@host/path', $passwordCleared->toBytes());

        $userCleared = $passwordCleared->withUser(null);
        $t->same(null, $userCleared->user());
        $t->same(null, $userCleared->password());
        $t->same('https://host/path', $userCleared->toBytes());

        $t->throws(InvalidArgumentException::class, static fn () => $url->withUser("bad\xFF"));
        $t->throws(InvalidArgumentException::class, static fn () => $url->withPassword("bad\xFF"));
    },
    'git url deserializes bytes like gix-url from_bytes access helper' => static function (TestRunner $t): void {
        $canonical = 'https://user:password@example.com:8080/path/to/repo';
        $fromBytes = GitUrl::fromBytes($canonical);
        $fromParse = GitUrl::parse($canonical);

        $t->same($fromParse->toArray(), $fromBytes->toArray());
        $t->same($canonical, $fromBytes->toBytes());

        $rawLocalBytes = "/path/to\xFF/repo";
        $rawLocal = GitUrl::parse($rawLocalBytes);
        $rawFromBytes = GitUrl::fromBytes($rawLocal->toBytes());

        $t->same(GitUrl::SCHEME_FILE, $rawFromBytes->scheme());
        $t->same(true, $rawFromBytes->usesAlternativeForm());
        $t->same($rawLocal->path(), $rawFromBytes->path());
        $t->same($rawLocalBytes, $rawFromBytes->toBytes());
    },
    'git url builds from parts through upstream parse validation' => static function (TestRunner $t): void {
        $https = GitUrl::fromParts(
            GitUrl::SCHEME_HTTPS,
            'deploy user',
            'deploy token',
            'Git.Example.TEST',
            8443,
            '/wp-content/site.git'
        );
        $t->same(GitUrl::SCHEME_HTTPS, $https->scheme());
        $t->same('deploy user', $https->user());
        $t->same('deploy token', $https->password());
        $t->same('git.example.test', $https->host());
        $t->same(8443, $https->port());
        $t->same('/wp-content/site.git', $https->path());
        $t->same('https://deploy%20user:deploy%20token@git.example.test:8443/wp-content/site.git', $https->toBytes());

        $fileCanonical = GitUrl::fromParts(GitUrl::SCHEME_FILE, null, null, null, null, '/var/cache/site.git');
        $t->same(false, $fileCanonical->usesAlternativeForm());
        $t->same('file:///var/cache/site.git', $fileCanonical->toBytes());

        $fileAlternative = GitUrl::fromParts(GitUrl::SCHEME_FILE, null, null, null, null, "/var/cache/site\xFF.git", true);
        $t->same(true, $fileAlternative->usesAlternativeForm());
        $t->same("/var/cache/site\xFF.git", $fileAlternative->path());
        $t->same("/var/cache/site\xFF.git", $fileAlternative->toBytes());

        $sshAlternative = GitUrl::fromParts(
            GitUrl::SCHEME_SSH,
            'deploy',
            null,
            'git.example.test',
            null,
            'wp-content/site.git',
            true
        );
        $t->same(true, $sshAlternative->usesAlternativeForm());
        $t->same('deploy@git.example.test:wp-content/site.git', $sshAlternative->toBytes());
        $t->same('wp-content/site.git', $sshAlternative->path());

        $sshPasswordFallback = GitUrl::fromParts(
            GitUrl::SCHEME_SSH,
            'deploy',
            'secret',
            'git.example.test',
            null,
            'wp-content/site.git',
            true
        );
        $t->same(false, $sshPasswordFallback->usesAlternativeForm(), 'passwords force canonical URL serialization before validation');
        $t->same('/wp-content/site.git', $sshPasswordFallback->path());
        $t->same('ssh://deploy:secret@git.example.test/wp-content/site.git', $sshPasswordFallback->toBytes());

        $sshPortFallback = GitUrl::fromParts(
            GitUrl::SCHEME_SSH,
            null,
            null,
            'git.example.test',
            2222,
            '/srv/git/site.git',
            true
        );
        $t->same(false, $sshPortFallback->usesAlternativeForm(), 'ports force canonical URL serialization before validation');
        $t->same('ssh://git.example.test:2222/srv/git/site.git', $sshPortFallback->toBytes());

        $pathlessSsh = GitUrl::fromParts(GitUrl::SCHEME_SSH, null, null, 'git.example.test', null, '');
        $t->same('/', $pathlessSsh->path(), 'validated URL serialization supplies the repository root path');
        $t->same('ssh://git.example.test/', $pathlessSsh->toBytes());

        $t->throws(
            InvalidArgumentException::class,
            static fn () => GitUrl::fromParts(GitUrl::SCHEME_HTTPS, 'deploy', null, null, null, '/site.git')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => GitUrl::fromParts(GitUrl::SCHEME_SSH, null, null, 'git.example.test', 0, '/site.git')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => GitUrl::fromParts(GitUrl::SCHEME_FILE, null, null, null, null, 'relative.git')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => GitUrl::fromParts('bad scheme', null, null, null, null, '/site.git')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => GitUrl::fromParts(GitUrl::SCHEME_HTTPS, "bad\xFF", null, 'git.example.test', null, '/site.git')
        );
    },
    'git url toggles alternate serialization like gix-url serialize_alternate_form' => static function (TestRunner $t): void {
        $file = GitUrl::parse('file:///var/cache/wp-content/site.git');
        $fileAlternate = $file->withAlternativeForm(true);
        $t->same(false, $file->usesAlternativeForm());
        $t->same(true, $fileAlternate->usesAlternativeForm());
        $t->same('file:///var/cache/wp-content/site.git', $file->toBytes(), 'canonical file URL remains unchanged');
        $t->same('/var/cache/wp-content/site.git', $fileAlternate->toBytes(), 'file URL alternate form writes a path');
        $t->same('/var/cache/wp-content/site.git', $fileAlternate->display());

        $localPath = GitUrl::parse('/var/cache/wp-content/site.git');
        $localCanonical = $localPath->withAlternativeForm(false);
        $t->same(true, $localPath->usesAlternativeForm());
        $t->same(false, $localCanonical->usesAlternativeForm());
        $t->same('/var/cache/wp-content/site.git', $localPath->toBytes());
        $t->same('file:///var/cache/wp-content/site.git', $localCanonical->toBytes());

        $ssh = GitUrl::parse('ssh://deploy@git.example.test/~wp-content/site.git');
        $sshAlternate = $ssh->withAlternativeForm(true);
        $t->same(false, $ssh->usesAlternativeForm());
        $t->same(true, $sshAlternate->usesAlternativeForm());
        $t->same('ssh://deploy@git.example.test/~wp-content/site.git', $ssh->toBytes());
        $t->same('deploy@git.example.test:~wp-content/site.git', $sshAlternate->toBytes());

        $absoluteSsh = GitUrl::parse('ssh://deploy@git.example.test/var/git/site.git');
        $t->same('deploy@git.example.test:/var/git/site.git', $absoluteSsh->withAlternativeForm(true)->toBytes());

        $passwordSsh = GitUrl::parse('ssh://deploy:secret@git.example.test/site.git')->withAlternativeForm(true);
        $t->same('ssh://deploy:secret@git.example.test/site.git', $passwordSsh->toBytes(), 'passwords require canonical URL form');
        $t->same('ssh://deploy:redacted@git.example.test/site.git', $passwordSsh->display());

        $portSsh = GitUrl::parse('ssh://git.example.test:2222/site.git')->withAlternativeForm(true);
        $t->same('ssh://git.example.test:2222/site.git', $portSsh->toBytes(), 'ports require canonical URL form');

        $https = GitUrl::parse('https://git.example.test/wp-content/site.git')->withAlternativeForm(true);
        $t->same('https://git.example.test/wp-content/site.git', $https->toBytes(), 'non-file non-SSH schemes ignore alternate serialization');
    },
    'git url rejects invalid utf8 in url and scp forms while keeping raw local paths byte-safe' => static function (TestRunner $t): void {
        $internationalPath = GitUrl::parse('https://example.com/caf%C3%A9');
        $t->same("/caf\xC3\xA9", $internationalPath->path());
        $t->same("https://example.com/caf\xC3\xA9", $internationalPath->toBytes());

        foreach ([
            'https://example.com/%FF',
            'https://example.com/%C3%28',
            'http://user%FF@example.com/path',
            'http://user:p%FF@example.com/path',
            'ssh://host.xz/%FF',
        ] as $invalidPercentUrl) {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => GitUrl::parse($invalidPercentUrl),
                "{$invalidPercentUrl} rejects invalid percent-decoded UTF-8"
            );
        }

        foreach ([
            "ssh://host.xz/\xFF",
            "bad\xFF@host.xz:repo",
            "file://host/\xFF",
        ] as $invalidRawUrl) {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => GitUrl::parse($invalidRawUrl),
                'URL/SCP form rejects raw invalid UTF-8 bytes'
            );
        }

        $rawLocal = GitUrl::parse("/path/to\xFF/git");
        $t->same(GitUrl::SCHEME_FILE, $rawLocal->scheme());
        $t->same("/path/to\xFF/git", $rawLocal->path());
        $t->same("/path/to\xFF/git", $rawLocal->toBytes());
    },
    'git url normalizes ports ipv6 schemes and argument safety boundaries' => static function (TestRunner $t): void {
        $httpsIpv6 = GitUrl::parse('https://user@[2001:db8::1]:8443/repo');
        $t->same(GitUrl::SCHEME_HTTPS, $httpsIpv6->scheme());
        $t->same('user', $httpsIpv6->user());
        $t->same('[2001:db8::1]', $httpsIpv6->host());
        $t->same(8443, $httpsIpv6->port());
        $t->same('https://user@[2001:db8::1]:8443/repo', $httpsIpv6->toBytes());

        $sshIpv6 = GitUrl::parse('ssh://user@[2001:db8::1]:2222/repo');
        $t->same(GitUrl::SCHEME_SSH, $sshIpv6->scheme());
        $t->same('2001:db8::1', $sshIpv6->host());
        $t->same(2222, $sshIpv6->port());
        $t->same('ssh://user@[2001:db8::1]:2222/repo', $sshIpv6->toBytes());

        $scpIpv6 = GitUrl::parse('[::1]:repo');
        $t->same(GitUrl::SCHEME_SSH, $scpIpv6->scheme());
        $t->same('::1', $scpIpv6->host());
        $t->same('repo', $scpIpv6->path());
        $t->same('[::1]:repo', $scpIpv6->toBytes());

        $fileIpv6User = GitUrl::parse('file://User@[::1]/repo');
        $t->same(GitUrl::SCHEME_FILE, $fileIpv6User->scheme());
        $t->same('User', $fileIpv6User->user());
        $t->same('[::1]', $fileIpv6User->host());
        $t->same('/repo', $fileIpv6User->path());
        $t->same('file://User@[::1]/repo', $fileIpv6User->toBytes());

        $legacyScheme = GitUrl::parse('ssh+git://host.xz/repo');
        $t->same(GitUrl::SCHEME_SSH, $legacyScheme->scheme());
        $t->same('ssh://host.xz/repo', $legacyScheme->toBytes());

        $ftpRemote = GitUrl::parse('ftp://git.example.test/wp-content/site.git');
        $t->same('ftp', $ftpRemote->scheme());
        $t->same('git.example.test', $ftpRemote->host());
        $t->same('/wp-content/site.git', $ftpRemote->path());
        $t->same('ftp://git.example.test/wp-content/site.git', $ftpRemote->toBytes());
        $t->same(null, $ftpRemote->portOrDefault());

        $ftpsRemote = GitUrl::parse('ftps://git.example.test/wp-content/site.git');
        $t->same('ftps', $ftpsRemote->scheme());
        $t->same('git.example.test', $ftpsRemote->host());
        $t->same('/wp-content/site.git', $ftpsRemote->path());
        $t->same('ftps://git.example.test/wp-content/site.git', $ftpsRemote->toBytes());
        $t->same(null, $ftpsRemote->portOrDefault());

        $customHelperRemote = GitUrl::parse('abc:///wp-content/site.git');
        $t->same('abc', $customHelperRemote->scheme());
        $t->same(null, $customHelperRemote->host());
        $t->same('/wp-content/site.git', $customHelperRemote->path());
        $t->same('abc:///wp-content/site.git', $customHelperRemote->toBytes());

        $unsafe = GitUrl::parse('ssh://-Fconfigfile@-oProxyCommand=open$IFS-aCalculator/-oProxyCommand=open$IFS-aCalculator');
        $t->same(null, $unsafe->userArgumentSafe());
        $t->same(null, $unsafe->hostArgumentSafe());
        $t->same(null, $unsafe->pathArgumentSafe());

        $safe = GitUrl::parse('ssh://user.name@example.com/path/to/file');
        $t->same('user.name', $safe->userArgumentSafe());
        $t->same('example.com', $safe->hostArgumentSafe());
        $t->same('/path/to/file', $safe->pathArgumentSafe());

        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('ssh://host.xz'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('git://host.xz'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('ftp:///wp-content/site.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('ftps:///wp-content/site.git'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('file://'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('ssh://host.xz:0/path'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('ssh://host.xz:65536/path'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('http://has a space/path'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('user@[::1]:repo'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('[::1:repo'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('mirror@[2001:db8::1:repo'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse(str_repeat('a', 1025) . '://host.xz/path'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('https://' . str_repeat('a', 1025) . '/path'));

        $invalidPortFormat = GitUrl::parse('ssh://host.xz:abc/path');
        $t->same('host.xz:abc', $invalidPortFormat->host());
        $t->same(null, $invalidPortFormat->port());
    },
    'git url keeps pathless extension scheme remotes and empty path safety like gix-url' => static function (TestRunner $t): void {
        $radicle = GitUrl::parse('rad://hynkuwzskprmswzeo4qdtku7grdrs4ffj3g9tjdxomgmjzhtzpqf81@hwd1yregyf1dudqwkx85x5ps3qsrqw3ihxpx3ieopq6ukuuq597p6m8161c.git');

        $t->same('rad', $radicle->scheme());
        $t->same('hynkuwzskprmswzeo4qdtku7grdrs4ffj3g9tjdxomgmjzhtzpqf81', $radicle->user());
        $t->same(null, $radicle->password());
        $t->same('hwd1yregyf1dudqwkx85x5ps3qsrqw3ihxpx3ieopq6ukuuq597p6m8161c.git', $radicle->host());
        $t->same(null, $radicle->port());
        $t->same('', $radicle->path());
        $t->same(null, $radicle->portOrDefault());
        $t->same('rad://hynkuwzskprmswzeo4qdtku7grdrs4ffj3g9tjdxomgmjzhtzpqf81@hwd1yregyf1dudqwkx85x5ps3qsrqw3ihxpx3ieopq6ukuuq597p6m8161c.git', $radicle->toBytes());
        $t->same($radicle->toBytes(), $radicle->display());
        $t->same(null, $radicle->pathArgumentSafe(), 'empty extension-scheme paths are not usable shell arguments');

        $withPath = GitUrl::parse('abc://example.com/~byron/hello');
        $t->same('abc', $withPath->scheme());
        $t->same('example.com', $withPath->host());
        $t->same('/~byron/hello', $withPath->path());
        $t->same('/~byron/hello', $withPath->pathArgumentSafe());
        $t->same('abc://example.com/~byron/hello', $withPath->toBytes());
    },
    'git url reports argument safety classifications and root paths like gix-url access helpers' => static function (TestRunner $t): void {
        $unsafeUser = GitUrl::parse('ssh://-Fconfigfile@foo/bar');
        $t->same(['status' => GitUrl::ARGUMENT_DANGEROUS, 'value' => '-Fconfigfile'], $unsafeUser->userArgumentSafety());
        $t->same(null, $unsafeUser->userArgumentSafe());
        $t->same(['status' => GitUrl::ARGUMENT_USABLE, 'value' => 'foo'], $unsafeUser->hostArgumentSafety());
        $t->same('foo', $unsafeUser->hostArgumentSafe());
        $t->same(['status' => GitUrl::ARGUMENT_USABLE, 'value' => '/bar'], $unsafeUser->pathArgumentSafety());
        $t->same('/bar', $unsafeUser->pathArgumentSafe());

        $unsafeHost = GitUrl::parse('ssh://-oProxyCommand=open$IFS-aCalculator/foo');
        $t->same(['status' => GitUrl::ARGUMENT_ABSENT, 'value' => null], $unsafeHost->userArgumentSafety());
        $t->same(['status' => GitUrl::ARGUMENT_DANGEROUS, 'value' => '-oProxyCommand=open$IFS-aCalculator'], $unsafeHost->hostArgumentSafety());
        $t->same(null, $unsafeHost->hostArgumentSafe());
        $t->same(['status' => GitUrl::ARGUMENT_USABLE, 'value' => '/foo'], $unsafeHost->pathArgumentSafety());

        $unsafePath = GitUrl::parse('ssh://foo/-oProxyCommand=open$IFS-aCalculator');
        $t->same(['status' => GitUrl::ARGUMENT_DANGEROUS, 'value' => '/-oProxyCommand=open$IFS-aCalculator'], $unsafePath->pathArgumentSafety());
        $t->same(null, $unsafePath->pathArgumentSafe());

        $root = GitUrl::parse('http://host.xz');
        $t->same('/', $root->path());
        $t->same(true, $root->pathIsRoot());
        $t->same(['status' => GitUrl::ARGUMENT_USABLE, 'value' => '/'], $root->pathArgumentSafety());
        $t->same('/', $root->pathArgumentSafe());

        $pathless = GitUrl::parse('rad://wp-content-seed@example.git');
        $t->same('', $pathless->path());
        $t->same(false, $pathless->pathIsRoot());
        $t->same(['status' => GitUrl::ARGUMENT_ABSENT, 'value' => null], $pathless->pathArgumentSafety());
        $t->same(null, $pathless->pathArgumentSafe());
    },
    'git url normalizes empty ssh url port markers like gix-url' => static function (TestRunner $t): void {
        $hostWithEmptyPort = GitUrl::parse('ssh://host:/re/po');
        $t->same(GitUrl::SCHEME_SSH, $hostWithEmptyPort->scheme());
        $t->same(null, $hostWithEmptyPort->user());
        $t->same('host', $hostWithEmptyPort->host());
        $t->same(null, $hostWithEmptyPort->port());
        $t->same('/re/po', $hostWithEmptyPort->path());
        $t->same('ssh://host/re/po', $hostWithEmptyPort->toBytes());

        $userHostWithEmptyPort = GitUrl::parse('ssh://user@host:/~re/po');
        $t->same('user', $userHostWithEmptyPort->user());
        $t->same('host', $userHostWithEmptyPort->host());
        $t->same(null, $userHostWithEmptyPort->port());
        $t->same('~re/po', $userHostWithEmptyPort->path());
        $t->same('ssh://user@host/~re/po', $userHostWithEmptyPort->toBytes());

        $bracketedIpv6WithEmptyPort = GitUrl::parse('ssh://[::1]:/repo');
        $t->same('::1', $bracketedIpv6WithEmptyPort->host());
        $t->same(null, $bracketedIpv6WithEmptyPort->port());
        $t->same('/repo', $bracketedIpv6WithEmptyPort->path());

        $nonNumericPortRemainsHostText = GitUrl::parse('ssh://host.xz:abc/path');
        $t->same('host.xz:abc', $nonNumericPortRemainsHostText->host());
        $t->same(null, $nonNumericPortRemainsHostText->port());
        $t->same('ssh://host.xz:abc/path', $nonNumericPortRemainsHostText->toBytes());
    },
    'git url expands home paths like gix-url expand_path' => static function (TestRunner $t): void {
        $current = GitUrl::parseHomePath('/~/hello/git');
        $t->same(true, $current['currentUser']);
        $t->same(null, $current['user']);
        $t->same('/hello/git', $current['path']);
        $t->same('~/hello/git', GitUrl::forShellPath('/~/hello/git'));
        $t->same('/home/current/hello/git', GitUrl::expandHomePath(
            '/~/hello/git',
            static fn (?string $user): ?string => $user === null ? '/home/current' : null
        ));

        $named = GitUrl::parseHomePath('/~byron/hello/git');
        $t->same(false, $named['currentUser']);
        $t->same('byron', $named['user']);
        $t->same('/hello/git', $named['path']);
        $t->same('~byron/hello/git', GitUrl::forShellPath('/~byron/hello/git'));
        $t->same('/home/byron/hello/git', GitUrl::expandHomePath(
            '/~byron/hello/git',
            static fn (?string $user): ?string => $user === 'byron' ? '/home/byron' : null
        ));

        $namedRoot = GitUrl::parseHomePath('/~deploy');
        $t->same('deploy', $namedRoot['user']);
        $t->same('/', $namedRoot['path']);
        $t->same('/srv/deploy', GitUrl::expandHomePath(
            '/~deploy',
            static fn (?string $user): ?string => $user === 'deploy' ? '/srv/deploy' : null
        ));
        $t->same('~deploy/', GitUrl::forShellPath('/~deploy'));

        $plainAbsolute = GitUrl::parseHomePath('/srv/~deploy/site.git');
        $t->same(false, $plainAbsolute['currentUser']);
        $t->same(null, $plainAbsolute['user']);
        $t->same('/srv/~deploy/site.git', $plainAbsolute['path']);
        $t->same('/srv/~deploy/site.git', GitUrl::forShellPath('/srv/~deploy/site.git'));
        $t->same('/srv/~deploy/site.git', GitUrl::expandHomePath(
            '/srv/~deploy/site.git',
            static fn (?string $user): ?string => throw new RuntimeException('home lookup should not run')
        ));

        $relative = GitUrl::parseHomePath('~/hello/git');
        $t->same(false, $relative['currentUser']);
        $t->same(null, $relative['user']);
        $t->same('~/hello/git', $relative['path']);
        $t->same('~/hello/git', GitUrl::forShellPath('~/hello/git'));

        $t->throws(
            InvalidArgumentException::class,
            static fn () => GitUrl::expandHomePath('/~missing/repo.git', static fn (?string $user): ?string => null)
        );
    },
    'git url canonicalizes relative file paths like gix-url access helpers' => static function (TestRunner $t): void {
        $https = GitUrl::parse('https://github.com/byron/gitoxide');
        $t->same('https://github.com/byron/gitoxide', $https->canonicalized('/srv/www/current')->toBytes());
        $t->same('/byron/gitoxide', $https->canonicalized('/srv/www/current')->path());

        $absolute = GitUrl::parse('/this/path/does/not/exist');
        $t->same('/this/path/does/not/exist', $absolute->canonicalized('/srv/www/current')->path());
        $t->same('/this/path/does/not/exist', $absolute->canonicalized('/srv/www/current')->toBytes());

        $dot = GitUrl::parse('.');
        $dotCanonical = $dot->canonicalized('/srv/www/current');
        $t->same(GitUrl::SCHEME_FILE, $dotCanonical->scheme());
        $t->same('/srv/www/current', $dotCanonical->path());
        $t->same('/srv/www/current', $dotCanonical->toBytes());

        $relative = GitUrl::parse('../site.git');
        $relativeCanonical = $relative->canonicalized('/srv/www/current');
        $t->same('/srv/www/site.git', $relativeCanonical->path());
        $t->same('/srv/www/site.git', $relativeCanonical->toBytes());

        $nested = GitUrl::parse('./mirrors/../site.git');
        $nestedCanonical = $nested->canonicalized('/srv/www/current/');
        $t->same('/srv/www/current/site.git', $nestedCanonical->path());
        $t->same('/srv/www/current/site.git', $nestedCanonical->toBytes());

        $fileUrl = GitUrl::parse('file:///var/cache/site.git');
        $t->same('/var/cache/site.git', $fileUrl->canonicalized('/srv/www/current')->path());
        $t->same('file:///var/cache/site.git', $fileUrl->canonicalized('/srv/www/current')->toBytes());
    },
    'refspec parser maps upstream fetch instruction and prefix behavior' => static function (TestRunner $t): void {
        $cases = [
            'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391:' => [
                RefSpec::INSTRUCTION_FETCH_ONLY,
                'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391',
                null,
                false,
                null,
                [],
            ],
            'a:e69de29bb2d1d6434b8b29ae775ad8c2e48c5391' => [
                RefSpec::INSTRUCTION_FETCH_AND_UPDATE,
                'a',
                'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391',
                false,
                null,
                ['a', 'refs/a', 'refs/tags/a', 'refs/heads/a', 'refs/remotes/a', 'refs/remotes/a/HEAD'],
            ],
            '^refs/heads/a' => [
                RefSpec::INSTRUCTION_FETCH_EXCLUDE,
                'refs/heads/a',
                null,
                false,
                null,
                [],
            ],
            '@' => [
                RefSpec::INSTRUCTION_FETCH_ONLY,
                'HEAD',
                null,
                false,
                'HEAD',
                ['HEAD'],
            ],
            '+a:b' => [
                RefSpec::INSTRUCTION_FETCH_AND_UPDATE,
                'a',
                'b',
                true,
                null,
                ['a', 'refs/a', 'refs/tags/a', 'refs/heads/a', 'refs/remotes/a', 'refs/remotes/a/HEAD'],
            ],
            'refs/heads/*:refs/remotes/origin/*' => [
                RefSpec::INSTRUCTION_FETCH_AND_UPDATE,
                'refs/heads/*',
                'refs/remotes/origin/*',
                false,
                'refs/heads/',
                ['refs/heads/'],
            ],
            'refs/*/foo/*' => [
                RefSpec::INSTRUCTION_FETCH_ONLY,
                'refs/*/foo/*',
                null,
                false,
                null,
                [],
            ],
            '' => [
                RefSpec::INSTRUCTION_FETCH_ONLY,
                'HEAD',
                null,
                false,
                'HEAD',
                ['HEAD'],
            ],
            ':' => [
                RefSpec::INSTRUCTION_FETCH_ONLY,
                'HEAD',
                null,
                false,
                'HEAD',
                ['HEAD'],
            ],
        ];

        foreach ($cases as $input => [$instruction, $source, $destination, $force, $prefix, $expanded]) {
            $spec = RefSpec::parseFetch($input);
            $t->same(RefSpec::OP_FETCH, $spec->operation(), "{$input} operation");
            $t->same($instruction, $spec->instructionName(), "{$input} instruction");
            $t->same($source, $spec->source(), "{$input} source");
            $t->same($destination, $spec->destination(), "{$input} destination");
            $t->same($source, $spec->remote(), "{$input} remote");
            $t->same($destination, $spec->local(), "{$input} local");
            $t->same($force, $spec->allowNonFastForward(), "{$input} force");
            $t->same($prefix, $spec->prefix(), "{$input} prefix");
            $t->same($expanded, $spec->expandPrefixes(), "{$input} expanded");
        }

        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('main~1'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^e69de29bb2d1d6434b8b29ae775ad8c2e48c5391'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^a:b'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^a'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^a*'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('refs/*/foo/*:refs/remotes/origin/*'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('a:b/*'));
    },
    'refspec parser normalizes forced fetch-only instructions like gix-refspec writer' => static function (TestRunner $t): void {
        $empty = RefSpec::parseFetch('+');
        $t->same(RefSpec::INSTRUCTION_FETCH_ONLY, $empty->instructionName());
        $t->same('HEAD', $empty->source());
        $t->same(null, $empty->destination());
        $t->same(true, $empty->allowNonFastForward());
        $t->same('HEAD', $empty->prefix());
        $t->same(['HEAD'], $empty->expandPrefixes());
        $t->same('HEAD', $empty->toString());
        $t->same('HEAD', $empty->toArray()['normalized']);

        $leftOnly = RefSpec::parseFetch('+refs/heads/main:');
        $t->same(RefSpec::INSTRUCTION_FETCH_ONLY, $leftOnly->instructionName());
        $t->same('refs/heads/main', $leftOnly->source());
        $t->same(null, $leftOnly->destination());
        $t->same(true, $leftOnly->allowNonFastForward());
        $t->same('refs/heads/main', $leftOnly->prefix());
        $t->same(['refs/heads/main'], $leftOnly->expandPrefixes());
        $t->same('refs/heads/main', $leftOnly->toString());

        $complexPattern = RefSpec::parseFetch('+refs/heads/*/release/*');
        $t->same(RefSpec::INSTRUCTION_FETCH_ONLY, $complexPattern->instructionName());
        $t->same('refs/heads/*/release/*', $complexPattern->source());
        $t->same(null, $complexPattern->destination());
        $t->same(true, $complexPattern->allowNonFastForward());
        $t->same(null, $complexPattern->prefix());
        $t->same([], $complexPattern->expandPrefixes());
        $t->same('refs/heads/*/release/*', $complexPattern->toString());

        $negativeHead = RefSpec::parseFetch('^@');
        $t->same(RefSpec::INSTRUCTION_FETCH_EXCLUDE, $negativeHead->instructionName());
        $t->same('HEAD', $negativeHead->source());
        $t->same(null, $negativeHead->destination());
        $t->same('^HEAD', $negativeHead->toString());

        $forcedDelete = RefSpec::parsePush('+:refs/heads/old');
        $t->same(RefSpec::INSTRUCTION_PUSH_DELETE, $forcedDelete->instructionName());
        $t->same(null, $forcedDelete->source());
        $t->same('refs/heads/old', $forcedDelete->destination());
        $t->same(true, $forcedDelete->allowNonFastForward());
        $t->same(':refs/heads/old', $forcedDelete->toString());
    },
    'refspec parser maps upstream push instruction and prefix behavior' => static function (TestRunner $t): void {
        $cases = [
            ':' => [
                RefSpec::INSTRUCTION_PUSH_ALL_MATCHING_BRANCHES,
                null,
                null,
                false,
                null,
                [],
            ],
            '+:' => [
                RefSpec::INSTRUCTION_PUSH_ALL_MATCHING_BRANCHES,
                null,
                null,
                true,
                null,
                [],
            ],
            ':a' => [
                RefSpec::INSTRUCTION_PUSH_DELETE,
                null,
                'a',
                false,
                null,
                ['a', 'refs/a', 'refs/tags/a', 'refs/heads/a', 'refs/remotes/a', 'refs/remotes/a/HEAD'],
            ],
            '@' => [
                RefSpec::INSTRUCTION_PUSH_MATCHING,
                'HEAD',
                null,
                false,
                null,
                [],
            ],
            '+@' => [
                RefSpec::INSTRUCTION_PUSH_MATCHING,
                'HEAD',
                null,
                true,
                null,
                [],
            ],
            'a:b' => [
                RefSpec::INSTRUCTION_PUSH_MATCHING,
                'a',
                'b',
                false,
                null,
                ['b', 'refs/b', 'refs/tags/b', 'refs/heads/b', 'refs/remotes/b', 'refs/remotes/b/HEAD'],
            ],
            '+main~1:refs/heads/release' => [
                RefSpec::INSTRUCTION_PUSH_MATCHING,
                'main~1',
                'refs/heads/release',
                true,
                'refs/heads/release',
                ['refs/heads/release'],
            ],
            'a/*:refs/heads/*' => [
                RefSpec::INSTRUCTION_PUSH_MATCHING,
                'a/*',
                'refs/heads/*',
                false,
                'refs/heads/',
                ['refs/heads/'],
            ],
            '^refs/heads/a' => [
                RefSpec::INSTRUCTION_PUSH_EXCLUDE,
                'refs/heads/a',
                null,
                false,
                null,
                [],
            ],
        ];

        foreach ($cases as $input => [$instruction, $source, $destination, $force, $prefix, $expanded]) {
            $spec = RefSpec::parsePush($input);
            $t->same(RefSpec::OP_PUSH, $spec->operation(), "{$input} operation");
            $t->same($instruction, $spec->instructionName(), "{$input} instruction");
            $t->same($source, $spec->source(), "{$input} source");
            $t->same($destination, $spec->destination(), "{$input} destination");
            $t->same($destination, $spec->remote(), "{$input} remote");
            $t->same($source, $spec->local(), "{$input} local");
            $t->same($force, $spec->allowNonFastForward(), "{$input} force");
            $t->same($prefix, $spec->prefix(), "{$input} prefix");
            $t->same($expanded, $spec->expandPrefixes(), "{$input} expanded");
        }

        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush(''));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('HEAD:'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('a~1'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('a~1:b~1'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('^'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('^e69de29bb2d1d6434b8b29ae775ad8c2e48c5391'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('^a:b'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('^a'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('^a*'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush('a/*/c/*:x/*/y/*'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parsePush(':a/*'));
    },
    'refspec writer normalizes one sided push instructions with implicit destination' => static function (TestRunner $t): void {
        $head = RefSpec::parsePush('@');
        $t->same('HEAD', $head->source());
        $t->same(null, $head->destination(), 'parse shape keeps source-only push destination absent');
        $t->same(RefSpec::INSTRUCTION_PUSH_MATCHING, $head->instructionName());
        $t->same('HEAD:HEAD', $head->toString());
        $t->same('HEAD:HEAD', $head->toArray()['normalized']);

        $forcedHead = RefSpec::parsePush('+@');
        $t->same('HEAD', $forcedHead->source());
        $t->same(null, $forcedHead->destination(), 'forced parse shape keeps source-only push destination absent');
        $t->same(true, $forcedHead->allowNonFastForward());
        $t->same('+HEAD:HEAD', $forcedHead->toString());
        $t->same('+HEAD:HEAD', $forcedHead->toArray()['normalized']);

        $sameNamedBranch = RefSpec::parsePush('refs/heads/wp-content');
        $t->same('refs/heads/wp-content', $sameNamedBranch->source());
        $t->same(null, $sameNamedBranch->destination(), 'source-only branch push has implicit same-name destination');
        $t->same(RefSpec::INSTRUCTION_PUSH_MATCHING, $sameNamedBranch->instructionName());
        $t->same('refs/heads/wp-content:refs/heads/wp-content', $sameNamedBranch->toString());
        $t->same('refs/heads/wp-content:refs/heads/wp-content', $sameNamedBranch->toArray()['normalized']);

        $sameNamedPattern = RefSpec::parsePush('+refs/heads/wp-*:refs/heads/wp-*');
        $t->same('+refs/heads/wp-*:refs/heads/wp-*', $sameNamedPattern->toString());
        $t->same('refs/heads/wp-', $sameNamedPattern->prefix());

        $oneSidedPattern = RefSpec::parsePush('+refs/heads/wp-*');
        $t->same('refs/heads/wp-*', $oneSidedPattern->source());
        $t->same(null, $oneSidedPattern->destination());
        $t->same('+refs/heads/wp-*:refs/heads/wp-*', $oneSidedPattern->toString());
        $t->same(null, $oneSidedPattern->prefix(), 'prefix still follows the parsed destination side for pushes');
        $t->same([], $oneSidedPattern->expandPrefixes());
    },
    'refspec instruction identity matches upstream equality and hash normalization' => static function (TestRunner $t): void {
        $sourceOnly = RefSpec::parsePush('refs/heads/foo');
        $explicit = RefSpec::parsePush('refs/heads/foo:refs/heads/foo');

        $t->same(null, $sourceOnly->destination(), 'parse shape keeps the implicit destination absent');
        $t->same('refs/heads/foo:refs/heads/foo', $sourceOnly->toString());
        $t->same($explicit->instructionIdentity(), $sourceOnly->instructionIdentity());
        $t->same($explicit->instructionKey(), $sourceOnly->instructionKey());
        $t->true($sourceOnly->equivalentTo($explicit));
        $t->true($explicit->equivalentTo($sourceOnly));

        $identity = $sourceOnly->instructionIdentity();
        $t->same(RefSpec::OP_PUSH, $identity['operation']);
        $t->same(RefSpec::INSTRUCTION_PUSH_MATCHING, $identity['instruction']);
        $t->same('refs/heads/foo', $identity['source']);
        $t->same('refs/heads/foo', $identity['destination'], 'instruction identity materializes the implicit destination');
        $t->same(false, $identity['allowNonFastForward']);

        $dedup = [];
        foreach ([$sourceOnly, $explicit] as $spec) {
            $dedup[$spec->instructionKey()] = $spec->toString();
        }
        $t->same(1, count($dedup), 'instruction hash key deduplicates source-only and explicit same-name pushes');
        $t->same('refs/heads/foo:refs/heads/foo', array_values($dedup)[0]);

        $forcedSourceOnly = RefSpec::parsePush('+refs/heads/foo');
        $t->same(false, $sourceOnly->equivalentTo($forcedSourceOnly), 'matching push force mode is part of the instruction identity');
        $t->same(true, $forcedSourceOnly->instructionIdentity()['allowNonFastForward']);
        $t->same($forcedSourceOnly->instructionKey(), RefSpec::parsePush('+refs/heads/foo:refs/heads/foo')->instructionKey());

        $delete = RefSpec::parsePush(':refs/heads/old');
        $forcedDelete = RefSpec::parsePush('+:refs/heads/old');
        $t->same($delete->instructionIdentity(), $forcedDelete->instructionIdentity(), 'push-delete instructions ignore a leading force marker');
        $t->true($delete->equivalentTo($forcedDelete));

        $fetchOnly = RefSpec::parseFetch('@');
        $forcedFetchOnly = RefSpec::parseFetch('+@');
        $t->same($fetchOnly->instructionIdentity(), $forcedFetchOnly->instructionIdentity(), 'fetch-only instructions ignore a leading force marker');
        $t->true($fetchOnly->equivalentTo($forcedFetchOnly));
        $t->same('HEAD', $fetchOnly->instructionIdentity()['source']);

        $allMatching = RefSpec::parsePush(':');
        $forcedAllMatching = RefSpec::parsePush('+:');
        $t->same(false, $allMatching->equivalentTo($forcedAllMatching), 'push-all-matching branches retain force mode');
        $t->same(false, $allMatching->instructionIdentity()['allowNonFastForward']);
        $t->same(true, $forcedAllMatching->instructionIdentity()['allowNonFastForward']);
    },
    'refspec prefix expansion treats short hex names as partial refs and full object ids as objects' => static function (TestRunner $t): void {
        $shortHexFetch = RefSpec::parseFetch('dead');
        $t->same(null, $shortHexFetch->prefix());
        $t->same([
            'dead',
            'refs/dead',
            'refs/tags/dead',
            'refs/heads/dead',
            'refs/remotes/dead',
            'refs/remotes/dead/HEAD',
        ], $shortHexFetch->expandPrefixes());

        $shortHexPushDelete = RefSpec::parsePush(':dead');
        $t->same(RefSpec::INSTRUCTION_PUSH_DELETE, $shortHexPushDelete->instructionName());
        $t->same(null, $shortHexPushDelete->prefix());
        $t->same([
            'dead',
            'refs/dead',
            'refs/tags/dead',
            'refs/heads/dead',
            'refs/remotes/dead',
            'refs/remotes/dead/HEAD',
        ], $shortHexPushDelete->expandPrefixes());

        $shortNumericFetch = RefSpec::parseFetch('20260531');
        $t->same([
            '20260531',
            'refs/20260531',
            'refs/tags/20260531',
            'refs/heads/20260531',
            'refs/remotes/20260531',
            'refs/remotes/20260531/HEAD',
        ], $shortNumericFetch->expandPrefixes());

        $sha1Fetch = RefSpec::parseFetch('e69de29bb2d1d6434b8b29ae775ad8c2e48c5391');
        $t->same([], $sha1Fetch->expandPrefixes());

        $sha256Fetch = RefSpec::parseFetch('b071221ea854da2958fba3a37527ca5cf32c4ebcd71ab0b68b6b8f10f04e93ad');
        $t->same([], $sha256Fetch->expandPrefixes());

        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^dead'));
    },
    'wordpress fixture normalizes deployment remote and fetch push refspecs without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-url-refspec-normalize.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-url-refspec-normalize.php';

        $t->same($fixture['expectedRemoteScheme'], $summary['remote']['scheme']);
        $t->same($fixture['expectedRemoteUser'], $summary['remote']['user']);
        $t->same($fixture['expectedRemoteHost'], $summary['remote']['host']);
        $t->same($fixture['expectedRemotePath'], $summary['remote']['path']);
        $t->same($fixture['expectedRemoteUrl'], $summary['remote']['normalized']);
        $t->same($fixture['expectedRemoteAlternativeUrl'], $summary['remoteAlternativeUrl']);
        $t->same($fixture['expectedEmptyPortRemoteHost'], $summary['emptyPortRemote']['host']);
        $t->same($fixture['expectedEmptyPortRemotePath'], $summary['emptyPortRemote']['path']);
        $t->same($fixture['expectedEmptyPortRemoteUrl'], $summary['emptyPortRemote']['normalized']);
        $t->same($fixture['expectedLocalMirrorScheme'], $summary['localMirror']['scheme']);
        $t->same($fixture['expectedLocalMirrorUser'], $summary['localMirror']['user']);
        $t->same($fixture['expectedLocalMirrorHost'], $summary['localMirror']['host']);
        $t->same($fixture['expectedLocalMirrorPath'], $summary['localMirror']['path']);
        $t->same($fixture['expectedLocalMirrorUrl'], $summary['localMirror']['normalized']);
        $t->same($fixture['expectedCanonicalFileMirrorUrl'], $summary['canonicalFileMirror']['normalized']);
        $t->same($fixture['expectedCanonicalFileMirrorAlternativeUrl'], $summary['canonicalFileMirrorAlternativeUrl']);
        $t->same($fixture['expectedHomeMirrorUrl'], $summary['homeMirror']['normalized']);
        $t->same($fixture['expectedHomeMirrorUser'], $summary['homeMirrorHome']['user']);
        $t->same($fixture['expectedHomeMirrorTail'], $summary['homeMirrorHome']['path']);
        $t->same($fixture['expectedHomeMirrorShellPath'], $summary['homeMirrorShellPath']);
        $t->same($fixture['expectedHomeMirrorExpandedPath'], $summary['homeMirrorExpandedPath']);
        $t->same($fixture['expectedRelativeMirrorCanonicalPath'], $summary['relativeMirrorCanonical']['path']);
        $t->same($fixture['expectedRelativeMirrorCanonicalUrl'], $summary['relativeMirrorCanonical']['normalized']);
        $t->same(true, $summary['deploymentRemoteSafe']);
        $t->same($fixture['expectedCredentialRemoteUrl'], $summary['credentialRemote']['normalized']);
        $t->same($fixture['expectedCredentialRemoteDisplay'], $summary['credentialRemoteDisplay']);
        $t->same($fixture['expectedCredentialRemoteUrl'], $summary['credentialRemoteRoundtrip']['normalized']);
        $t->same($fixture['credentialRemoteUser'], $summary['credentialRemoteRoundtrip']['user']);
        $t->same($fixture['credentialRemotePassword'], $summary['credentialRemoteRoundtrip']['password']);
        $t->same($fixture['expectedPasswordOnlyRemoteUser'], $summary['passwordOnlyRemote']['user']);
        $t->same($fixture['expectedPasswordOnlyRemotePassword'], $summary['passwordOnlyRemote']['password']);
        $t->same($fixture['expectedPasswordOnlyRemotePath'], $summary['passwordOnlyRemote']['path']);
        $t->same($fixture['expectedPasswordOnlyRemoteUrl'], $summary['passwordOnlyRemote']['normalized']);
        $t->same($fixture['expectedPasswordOnlyRemoteDisplay'], $summary['passwordOnlyRemoteDisplay']);
        $t->same($fixture['expectedPasswordOnlyRemoteUrl'], $summary['passwordOnlyRemoteRoundtrip']['normalized']);
        $t->same($fixture['expectedPasswordOnlyRemoteUser'], $summary['passwordOnlyRemoteRoundtrip']['user']);
        $t->same($fixture['expectedPasswordOnlyRemotePassword'], $summary['passwordOnlyRemoteRoundtrip']['password']);
        $t->same($fixture['expectedByteRoundtripRemoteUrl'], $summary['byteRoundtripRemote']['normalized']);
        $t->same($summary['byteRoundtripRemoteFromParse']['normalized'], $summary['byteRoundtripRemote']['normalized']);
        $t->same($fixture['expectedPartsRemoteUrl'], $summary['partsRemote']['normalized']);
        $t->same($fixture['expectedPartsRemoteDisplay'], $summary['partsRemoteDisplay']);
        $t->same($fixture['expectedPartsSshAlternateUrl'], $summary['partsSshAlternate']['normalized']);
        $t->same(true, $summary['partsSshAlternate']['alternativeForm']);
        $t->same($fixture['expectedPartsSshPasswordUrl'], $summary['partsSshPassword']['normalized']);
        $t->same(false, $summary['partsSshPassword']['alternativeForm']);
        $t->same($fixture['expectedUnicodeRemoteUser'], $summary['unicodeRemote']['user']);
        $t->same($fixture['expectedUnicodeRemotePath'], $summary['unicodeRemote']['path']);
        $t->same($fixture['expectedUnicodeRemoteUrl'], $summary['unicodeRemote']['normalized']);
        $t->same($fixture['expectedRemoteArgumentSafety'], $summary['remoteArgumentSafety']);
        $t->same($fixture['expectedUnsafeRemoteArgumentSafety'], $summary['unsafeRemoteArgumentSafety']);
        $t->same($fixture['expectedRootRemotePathIsRoot'], $summary['rootRemotePathIsRoot']);
        $t->same($fixture['expectedRootRemotePathArgumentSafety'], $summary['rootRemotePathArgumentSafety']);
        $t->same($fixture['expectedFetchInstructions'], array_column($summary['fetch'], 'instruction'));
        $t->same($fixture['expectedPushInstructions'], array_column($summary['push'], 'instruction'));
        $t->same($fixture['expectedFetchNormalized'], array_column($summary['fetch'], 'normalized'));
        $t->same($fixture['expectedPushNormalized'], array_column($summary['push'], 'normalized'));
        $t->same($fixture['expectedPushInstructionIdentityUniqueCount'], $summary['pushInstructionIdentityUniqueCount']);
        $t->same($fixture['expectedSameNamedPushEquivalent'], $summary['sameNamedPushEquivalent']);
        $t->same($fixture['expectedDeleteForceEquivalent'], $summary['deleteForceEquivalent']);
        $t->same($fixture['expectedAllMatchingForceEquivalent'], $summary['allMatchingForceEquivalent']);
        $t->same($fixture['expectedFetchOnlyForceEquivalent'], $summary['fetchOnlyForceEquivalent']);
        $t->same($fixture['expectedOversizedRemoteRejected'], $summary['oversizedRemoteRejected']);
        $t->same($fixture['expectedMalformedBracketedRemoteRejected'], $summary['malformedBracketedRemoteRejected']);
        $t->same($fixture['expectedInvalidUtf8RemoteRejected'], $summary['invalidUtf8RemoteRejected']);
        $t->same($fixture['expectedHostlessFtpRemoteRejected'], $summary['hostlessFtpRemoteRejected']);
        $t->same($fixture['expectedFetchPrefixes'], array_column($summary['fetch'], 'prefix'));
        $t->same($fixture['expectedFetchExpandedPrefixes'], array_column($summary['fetch'], 'expandedPrefixes'));
        $t->same($fixture['expectedPushPrefixes'], array_column($summary['push'], 'prefix'));
        $t->same('refs/remotes/origin/*', $summary['fetch'][0]['local']);
        $t->same('refs/heads/wp-release', $summary['push'][0]['remote']);
        $t->same('refs/heads/stale-preview', $summary['push'][1]['remote']);
        $t->same('refs/heads/old-preview', $summary['push'][2]['remote']);
        $t->same('refs/heads/wp-content', $summary['push'][3]['local']);
        $t->same(null, $summary['push'][3]['remote'], 'same-name push keeps destination implicit in parse shape');
    },
];
