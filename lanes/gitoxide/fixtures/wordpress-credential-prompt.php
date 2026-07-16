<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialCascade;
use PortLibs\Gitoxide\CredentialContext;

$actions = [];
$prompts = [];

$oauthHelper = static function (string $action, string $payload) use (&$actions): ?string {
    $actions[] = "{$action}:" . trim(str_replace("\n", ';', $payload));
    if ($action !== 'get') {
        return null;
    }

    return "oauth_refresh_token=oauth-refresh\n";
};

$cascade = new CredentialCascade(
    [$oauthHelper],
    useHttpPath: true,
    usernamePrompt: static function (CredentialContext $context, string $message, string $mode) use (&$prompts): string {
        $prompts[] = [
            'field' => 'username',
            'mode' => $mode,
            'message' => $message,
            'url' => $context->url,
        ];

        return 'site-a-deploy';
    },
    passwordPrompt: static function (CredentialContext $context, string $message, string $mode) use (&$prompts): string {
        $prompts[] = [
            'field' => 'password',
            'mode' => $mode,
            'message' => $message,
            'url' => $context->url,
        ];

        return 'manual-deploy-token';
    },
);

$result = $cascade->get(new CredentialContext(url: 'https://git.example.test/wp-content.git'));

return [
    'identity' => $result->identity(),
    'oauthRefreshToken' => $result->oauthRefreshToken,
    'prompts' => $prompts,
    'promptModes' => array_column($prompts, 'mode'),
    'actions' => $actions,
    'nextActionBytes' => $result->nextActionBytes(),
    'shellOutUsed' => false,
    'wordpressUse' => 'A WordPress deployment tool can ask for a tenant deploy username visibly and token secretly after configured helpers return only OAuth metadata, then store the completed credential context without invoking git credential.',
];
