<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialProgram;

return [
    'credential program parses upstream custom helper definitions' => static function (TestRunner $t): void {
        $empty = CredentialProgram::fromCustomDefinition('');
        $t->same(CredentialProgram::EXTERNAL_NAME, $empty->kind);
        $t->same(['git', 'credential-', 'store'], $empty->command('store'));

        $script = CredentialProgram::fromCustomDefinition('!exe');
        $t->same(CredentialProgram::EXTERNAL_SHELL_SCRIPT, $script->kind);
        $t->same(['exe', 'store'], $script->command('store'));

        $name = CredentialProgram::fromCustomDefinition('name');
        $t->same(CredentialProgram::EXTERNAL_NAME, $name->kind);
        $t->same(['git', 'credential-name', 'store'], $name->command('store'));

        $nameWithArgs = CredentialProgram::fromCustomDefinition('name --arg --bar="a b"');
        $t->same(['git', 'credential-name', '--arg', '--bar=a b', 'store'], $nameWithArgs->command('store'));

        $nameWithShellArgs = CredentialProgram::fromCustomDefinition('name --arg --bar=~/folder/in/home');
        $t->same(
            ['sh', '-c', 'git credential-name --arg --bar=~/folder/in/home "$@"', '--', 'store'],
            $nameWithShellArgs->command('store'),
        );
    },
    'credential program parses upstream absolute helper definitions' => static function (TestRunner $t): void {
        $path = CredentialProgram::fromCustomDefinition('/abs/name');
        $t->same(CredentialProgram::EXTERNAL_PATH, $path->kind);
        $t->same(['/abs/name', 'store'], $path->command('store'));

        $pathWithArgs = CredentialProgram::fromCustomDefinition('/abs/name a b');
        $t->same(['sh', '-c', '/abs/name a b "$@"', '--', 'store'], $pathWithArgs->command('store'));
        $t->same(['/abs/name', 'a', 'b', 'store'], $pathWithArgs->command('store', windows: true));

        $pathWithQuotedArgs = CredentialProgram::fromCustomDefinition('/abs/name --arg --bar="a b"');
        $t->same(
            ['sh', '-c', '/abs/name --arg --bar="a b" "$@"', '--', 'store'],
            $pathWithQuotedArgs->command('store'),
        );
    },
    'credential program maps builtin and external action arguments' => static function (TestRunner $t): void {
        $builtin = CredentialProgram::builtin();
        $external = CredentialProgram::fromCustomDefinition('cache --timeout=3600');

        $t->same(['git', 'credential', 'fill'], $builtin->command('get'));
        $t->same(['git', 'credential', 'approve'], $builtin->command('store'));
        $t->same(['git', 'credential', 'reject'], $builtin->command('erase'));
        $t->same(['git', 'credential-cache', '--timeout=3600', 'get'], $external->command('get'));
        $t->same(['git', 'credential-cache', '--timeout=3600', 'erase'], $external->command('erase'));
        $t->throws(InvalidArgumentException::class, static fn () => $external->command('refresh'));
    },
    'credential program selects upstream platform builtin helpers without invoking them' => static function (TestRunner $t): void {
        $linux = CredentialProgram::platformBuiltins('linux');
        $t->same(1, count($linux));
        $t->same(CredentialProgram::EXTERNAL_NAME, $linux[0]->kind);
        $t->same('libsecret', $linux[0]->definition);
        $t->same(['git', 'credential-libsecret', 'get'], $linux[0]->command('get'));

        $macos = CredentialProgram::platformBuiltins('darwin');
        $t->same(1, count($macos));
        $t->same('osxkeychain', $macos[0]->definition);
        $t->same(['git', 'credential-osxkeychain', 'store'], $macos[0]->command('store'));

        $windows = CredentialProgram::platformBuiltins('windows');
        $t->same(1, count($windows));
        $t->same('manager-core', $windows[0]->definition);
        $t->same(['git', 'credential-manager-core', 'erase'], $windows[0]->command('erase'));

        $t->same([], CredentialProgram::platformBuiltins('freebsd'));
    },
    'credential program preserves shell scripts for custom helper invocation' => static function (TestRunner $t): void {
        $script = CredentialProgram::fromCustomDefinition('!f() { test "$1" = get && echo "username=user"; }; f');

        $t->same(CredentialProgram::EXTERNAL_SHELL_SCRIPT, $script->kind);
        $t->same(
            ['sh', '-c', 'f() { test "$1" = get && echo "username=user"; }; f "$@"', '--', 'get'],
            $script->command('get'),
        );
        $t->same(false, $script->suppressStderr()->stderr);
    },
    'wordpress credential program fixture builds deployment helper commands' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-credential-program.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-credential-program.php';

        $t->same(['git', 'credential-cache', '--timeout=3600', 'get'], $fixture['commands']['cacheGet']);
        $t->same(['git', 'credential-oauth', '--scope=wp-deploy', 'store'], $fixture['commands']['oauthStore']);
        $t->same(
            ['sh', '-c', '/usr/local/bin/wp-credential-helper --tenant=site-a "$@"', '--', 'erase'],
            $fixture['commands']['tenantErase'],
        );
        $t->same(['git', 'credential', 'fill'], $fixture['commands']['builtinFill']);
        $t->same(['git', 'credential-libsecret', 'get'], $fixture['platformDefaults']['linux'][0]['get']);
        $t->same(['git', 'credential-osxkeychain', 'store'], $fixture['platformDefaults']['darwin'][0]['store']);
        $t->same(['git', 'credential-manager-core', 'erase'], $fixture['platformDefaults']['windows'][0]['erase']);
        $t->same([], $fixture['platformDefaults']['unknown']);
        $t->same($fixture['helperKinds'], $summary['helperKinds']);
        $t->same($fixture['platformDefaults'], $summary['platformDefaults']);
        $t->contains('git credential-cache', $summary['wordpressUse']);
    },
];
