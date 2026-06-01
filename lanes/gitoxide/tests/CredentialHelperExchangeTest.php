<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialContext;
use PortLibs\Gitoxide\CredentialHelperExchange;
use PortLibs\Gitoxide\CredentialHelperInvocation;

return [
    'credential helper invocation keeps raw next action context for store and erase' => static function (TestRunner $t): void {
        $calls = [];
        $outcome = CredentialHelperInvocation::get(
            new CredentialContext(url: 'https://github.com/byron/gitoxide'),
            static function (string $action, string $payload) use (&$calls): string {
                $calls[] = [$action, $payload];

                return "username=user\npassword=pass\nquit=1\n";
            },
        );

        $t->same('user', $outcome->username);
        $t->same('pass', $outcome->password);
        $t->same(true, $outcome->quit);
        $t->same(['username' => 'user', 'password' => 'pass', 'oauthRefreshToken' => null], $outcome->identity());
        $t->same("username=user\npassword=pass\nquit=1\n", $outcome->nextActionBytes());
        $t->same(true, $outcome->nextActionContext()->quit);
        $t->contains("url=https://github.com/byron/gitoxide\n", $calls[0][1]);

        CredentialHelperInvocation::store(
            $outcome,
            static function (string $action, string $payload) use (&$calls): string {
                $calls[] = [$action, $payload];

                return "ignored=store\n";
            },
        );
        CredentialHelperInvocation::erase(
            $outcome,
            static function (string $action, string $payload) use (&$calls): string {
                $calls[] = [$action, $payload];

                return "ignored=erase\n";
            },
        );

        $t->same('store', $calls[1][0]);
        $t->same("username=user\npassword=pass\nquit=1\n\n", $calls[1][1]);
        $t->same('erase', $calls[2][0]);
        $t->same("username=user\npassword=pass\nquit=1\n\n", $calls[2][1]);
    },
    'credential helper invocation exposes partial helper stdout without completing identity' => static function (TestRunner $t): void {
        $outcome = CredentialHelperInvocation::get(
            new CredentialContext(protocol: 'https', host: 'github.com'),
            static fn (): string => "username=user\n",
        );

        $t->same('user', $outcome->username);
        $t->same(null, $outcome->password);
        $t->same(null, $outcome->identity());
        $t->same("username=user\n", $outcome->nextActionBytes());
    },
    'credential helper exchange accepts protocol and host without auto populating url' => static function (TestRunner $t): void {
        $called = false;
        $observed = null;
        $missingMessage = null;

        try {
            CredentialHelperExchange::invoke(
                ['get'],
                "protocol=https\nhost=github.com\n",
                static function (string $action, CredentialContext $context) use (&$called, &$observed): ?CredentialContext {
                    $called = true;

                    $observed = [
                        $action,
                        $context->protocol,
                        $context->host,
                        $context->url,
                    ];

                    return null;
                },
            );
        } catch (RuntimeException $error) {
            $missingMessage = $error->getMessage();
        }

        $t->contains('Credentials for https://github.com could not be obtained', $missingMessage ?? '');
        $t->same(true, $called);
        $t->same(['get', 'https', 'github.com', null], $observed);
    },
    'credential helper exchange accepts url alone without destructuring context' => static function (TestRunner $t): void {
        $observed = null;
        $output = CredentialHelperExchange::invoke(
            ['fill'],
            "url=https://github.com/byron/gitoxide\n",
            static function (string $action, CredentialContext $context) use (&$observed): CredentialContext {
                $observed = [
                    $action,
                    $context->url,
                    $context->protocol,
                    $context->host,
                ];

                return new CredentialContext(username: 'user', password: 'pass');
            },
        );

        $t->same(['get', 'https://github.com/byron/gitoxide', null, null], $observed);
        $t->same("username=user\npassword=pass\n", $output);
    },
    'credential helper exchange rejects missing context before invoking callback' => static function (TestRunner $t): void {
        foreach (["host=github.com\n", "protocol=https\n"] as $input) {
            $called = false;
            $t->throws(
                InvalidArgumentException::class,
                static function () use ($input, &$called): void {
                    CredentialHelperExchange::invoke(
                        ['get'],
                        $input,
                        static function () use (&$called): ?CredentialContext {
                            $called = true;

                            return null;
                        },
                    );
                },
            );
            $t->same(false, $called);
        }
    },
    'credential helper exchange maps store erase aliases and suppresses output' => static function (TestRunner $t): void {
        $actions = [];
        foreach ([['approve'], ['store'], ['reject'], ['erase']] as $args) {
            $output = CredentialHelperExchange::invoke(
                $args,
                "url=https://github.com/byron/gitoxide\nusername=user\npassword=pass\n",
                static function (string $action, CredentialContext $context) use (&$actions): ?CredentialContext {
                    $actions[] = [$action, $context->url, $context->username, $context->password];

                    return null;
                },
            );
            $t->same('', $output);
        }

        $t->same([
            ['store', 'https://github.com/byron/gitoxide', 'user', 'pass'],
            ['store', 'https://github.com/byron/gitoxide', 'user', 'pass'],
            ['erase', 'https://github.com/byron/gitoxide', 'user', 'pass'],
            ['erase', 'https://github.com/byron/gitoxide', 'user', 'pass'],
        ], $actions);
    },
];
