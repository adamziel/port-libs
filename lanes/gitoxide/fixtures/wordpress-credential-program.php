<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialProgram;

$cache = CredentialProgram::fromCustomDefinition('cache --timeout=3600');
$oauth = CredentialProgram::fromCustomDefinition('oauth --scope=wp-deploy');
$tenant = CredentialProgram::fromCustomDefinition('/usr/local/bin/wp-credential-helper --tenant=site-a');
$builtin = CredentialProgram::builtin();

$platformDefault = static function (string $platform): array {
    $programs = [];
    foreach (CredentialProgram::platformBuiltins($platform) as $program) {
        $programs[] = [
            'kind' => $program->kind,
            'definition' => $program->definition,
            'get' => $program->command('get'),
            'store' => $program->command('store'),
            'erase' => $program->command('erase'),
        ];
    }

    return $programs;
};

return [
    'helperKinds' => [
        'cache' => $cache->kind,
        'oauth' => $oauth->kind,
        'tenant' => $tenant->kind,
        'builtin' => $builtin->kind,
    ],
    'commands' => [
        'cacheGet' => $cache->command('get'),
        'oauthStore' => $oauth->command('store'),
        'tenantErase' => $tenant->command('erase'),
        'builtinFill' => $builtin->command('get'),
    ],
    'platformDefaults' => [
        'linux' => $platformDefault('linux'),
        'darwin' => $platformDefault('darwin'),
        'windows' => $platformDefault('windows'),
        'unknown' => $platformDefault('freebsd'),
    ],
    'wordpressUse' => 'A WordPress deployment tool can preflight configured Git credential helpers such as git credential-cache, git credential-oauth, platform default helpers, an absolute tenant helper, and builtin git credential fill/approve/reject action names before invoking any helper process.',
];
