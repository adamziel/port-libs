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

        $percent = GitUrl::parse('https://%20@%40:example.org/%20%25');
        $t->same(' ', $percent->user());
        $t->same('%40:example.org', $percent->host());
        $t->same('/ %', $percent->path());
        $t->same('https://%20@%40:example.org/%20%25', $percent->toBytes());
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
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('file://'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('ssh://host.xz:0/path'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('ssh://host.xz:65536/path'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('http://has a space/path'));
        $t->throws(InvalidArgumentException::class, static fn () => GitUrl::parse('user@[::1]:repo'));

        $invalidPortFormat = GitUrl::parse('ssh://host.xz:abc/path');
        $t->same('host.xz:abc', $invalidPortFormat->host());
        $t->same(null, $invalidPortFormat->port());
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
    'wordpress fixture normalizes deployment remote and fetch push refspecs without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-url-refspec-normalize.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-url-refspec-normalize.php';

        $t->same($fixture['expectedRemoteScheme'], $summary['remote']['scheme']);
        $t->same($fixture['expectedRemoteUser'], $summary['remote']['user']);
        $t->same($fixture['expectedRemoteHost'], $summary['remote']['host']);
        $t->same($fixture['expectedRemotePath'], $summary['remote']['path']);
        $t->same($fixture['expectedRemoteUrl'], $summary['remote']['normalized']);
        $t->same($fixture['expectedLocalMirrorScheme'], $summary['localMirror']['scheme']);
        $t->same($fixture['expectedLocalMirrorUser'], $summary['localMirror']['user']);
        $t->same($fixture['expectedLocalMirrorHost'], $summary['localMirror']['host']);
        $t->same($fixture['expectedLocalMirrorPath'], $summary['localMirror']['path']);
        $t->same($fixture['expectedLocalMirrorUrl'], $summary['localMirror']['normalized']);
        $t->same(true, $summary['deploymentRemoteSafe']);
        $t->same($fixture['expectedFetchInstructions'], array_column($summary['fetch'], 'instruction'));
        $t->same($fixture['expectedPushInstructions'], array_column($summary['push'], 'instruction'));
        $t->same($fixture['expectedFetchNormalized'], array_column($summary['fetch'], 'normalized'));
        $t->same($fixture['expectedPushNormalized'], array_column($summary['push'], 'normalized'));
        $t->same($fixture['expectedFetchPrefixes'], array_column($summary['fetch'], 'prefix'));
        $t->same($fixture['expectedPushPrefixes'], array_column($summary['push'], 'prefix'));
        $t->same('refs/remotes/origin/*', $summary['fetch'][0]['local']);
        $t->same('refs/heads/wp-release', $summary['push'][0]['remote']);
        $t->same('refs/heads/stale-preview', $summary['push'][1]['remote']);
        $t->same('refs/heads/old-preview', $summary['push'][2]['remote']);
    },
];
