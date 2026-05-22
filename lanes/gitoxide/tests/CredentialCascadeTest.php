<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialCascade;
use PortLibs\Gitoxide\CredentialContext;

return [
    'credential cascade fills partial credentials and stops when complete' => static function (TestRunner $t): void {
        $calls = [];
        $payloads = [];
        $cascade = new CredentialCascade([
            static function (string $action, string $payload) use (&$calls, &$payloads): string {
                $calls[] = $action;
                $payloads[] = $payload;

                return "username=user\n";
            },
            static function (string $action, string $payload) use (&$calls, &$payloads): string {
                $calls[] = $action;
                $payloads[] = $payload;

                return "password=pass\n";
            },
            static function (string $action) use (&$calls): string {
                $calls[] = $action;

                return "username=unused\npassword=unused\n";
            },
        ], useHttpPath: true, nowUtc: 200);

        $result = $cascade->get(new CredentialContext(url: 'https://example.com:8080/path/git/'));

        $t->same(['get', 'get'], $calls);
        $t->same('user', $result->username);
        $t->same('pass', $result->password);
        $t->contains("protocol=https\n", $payloads[0]);
        $t->contains("host=example.com:8080\n", $payloads[0]);
        $t->contains("path=path/git\n", $payloads[0]);
        $t->same(false, str_contains($payloads[0], 'url='));
        $t->contains("username=user\n", $payloads[1]);
        $t->same(false, str_contains($result->nextActionBytes(), 'url='));
    },
    'credential cascade ignores failed helpers and lets complete helpers overwrite partial fields' => static function (TestRunner $t): void {
        $cascade = new CredentialCascade([
            static function (): string {
                throw new RuntimeException('helper missing');
            },
            static fn (): string => "username=user\n",
            static fn (): string => "username=user-script\npassword=pass-script\n",
        ], useHttpPath: true);

        $result = $cascade->get(new CredentialContext(url: 'https://host.test/repo.git'));

        $t->same('user-script', $result->username);
        $t->same('pass-script', $result->password);
    },
    'credential cascade destructures helper urls and ignores expired secrets' => static function (TestRunner $t): void {
        $cascade = new CredentialCascade([
            static fn (): string => "protocol=ftp\nhost=github.com\npath=byron/gitoxide\nurl=http://example.com:8080/path/to/git/\n",
            static fn (): string => "username=user-expired\npassword=pass-expired\npassword_expiry_utc=1\n",
            static fn (): string => "oauth_refresh_token=oauth-token\n",
            static fn (): string => "username=user-script\npassword=pass-script\n",
        ], useHttpPath: true, nowUtc: 200);

        $result = $cascade->get(new CredentialContext(url: 'http://github.com'));

        $t->same('http', $result->context->protocol);
        $t->same('example.com:8080', $result->context->host);
        $t->same('path/to/git', $result->context->path);
        $t->same('user-script', $result->username);
        $t->same('pass-script', $result->password);
        $t->same('oauth-token', $result->oauthRefreshToken);
        $t->same(null, $result->context->passwordExpiryUtc);
    },
    'credential cascade honors quit and query user only boundaries' => static function (TestRunner $t): void {
        $calls = [];
        $quit = new CredentialCascade([
            static function () use (&$calls): string {
                $calls[] = 'last-pass';

                return "username=user\npassword=pass\nquit=1\n";
            },
            static function () use (&$calls): string {
                $calls[] = 'unused';

                return "username=unused\npassword=unused\n";
            },
        ]);

        $quitResult = $quit->get(new CredentialContext(url: 'https://host.test/repo.git'));
        $t->same(['last-pass'], $calls);
        $t->same(true, $quitResult->quit);
        $t->same('user', $quitResult->username);

        $incompleteQuit = new CredentialCascade([static fn (): string => "quit=yes\n"]);
        $t->throws(RuntimeException::class, static fn () => $incompleteQuit->get(new CredentialContext(url: 'https://host.test/repo.git')));

        $queryCalls = [];
        $queryUserOnly = new CredentialCascade([
            static function () use (&$queryCalls): string {
                $queryCalls[] = 'username';

                return "username=user\n";
            },
            static function () use (&$queryCalls): string {
                $queryCalls[] = 'password';

                return "password=pass\n";
            },
        ], queryUserOnly: true);
        $queryResult = $queryUserOnly->get(new CredentialContext(url: 'ssh://git@host.test/repo.git'));
        $t->same(['username'], $queryCalls);
        $t->same('user', $queryResult->username);
        $t->same('', $queryResult->password);
    },
    'credential cascade sends store and erase payloads to every helper' => static function (TestRunner $t): void {
        $actions = [];
        $payloads = [];
        $cascade = new CredentialCascade([
            static function (string $action, string $payload) use (&$actions, &$payloads): ?string {
                $actions[] = "a:{$action}";
                $payloads[] = $payload;

                return $action === 'get' ? "username=user\npassword=pass\n" : null;
            },
            static function (string $action, string $payload) use (&$actions, &$payloads): ?string {
                $actions[] = "b:{$action}";
                $payloads[] = $payload;

                return null;
            },
        ]);

        $result = $cascade->get(new CredentialContext(url: 'https://host.test/repo.git'));
        $cascade->store($result);
        $cascade->erase($result);

        $t->same(['a:get', 'a:store', 'b:store', 'a:erase', 'b:erase'], $actions);
        $t->same($result->nextActionBytes() . "\n", $payloads[1]);
        $t->same($result->nextActionBytes() . "\n", $payloads[3]);
    },
    'wordpress credential cascade fixture obtains stores and erases deployment credentials' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-credential-cascade.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-credential-cascade.php';

        $t->same('deploy-bot', $fixture['identity']['username']);
        $t->same('wp-deploy-token', $fixture['identity']['password']);
        $t->same('wp-refresh-token', $fixture['identity']['oauthRefreshToken']);
        $t->same('wp-content.git', $fixture['contextPath']);
        $t->same(null, $fixture['passwordExpiryUtc']);
        $t->same(['cache:get', 'oauth:get', 'deploy:get', 'cache:store', 'oauth:store', 'deploy:store', 'cache:erase', 'oauth:erase', 'deploy:erase'], $fixture['actions']);
        $t->same(false, $fixture['secretsInDiagnosticLog']);
        $t->same($fixture['identity'], $summary['identity']);
        $t->contains('credential cascade', $summary['wordpressUse']);
    },
];
