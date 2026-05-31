<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialContext;
use PortLibs\Gitoxide\CredentialHelperExchange;

$request = new CredentialContext(
    protocol: 'https',
    host: 'git.example.test',
    path: 'wp-content.git',
    username: 'deploy-bot',
);

$helperResponse = CredentialContext::fromBytes(
    "url=https://deploy-bot@git.example.test/wp-content.git\n"
    . "protocol=https\n"
    . "host=git.example.test\n"
    . "path=wp-content.git\n"
    . "username=deploy-bot\n"
    . "password=wp-deploy-token\n"
    . "oauth_refresh_token=wp-refresh-token\n"
    . "password_expiry_utc=+1711398853\n"
);
$emptyQuit = CredentialContext::fromBytes("quit=\n");
$overflowExpiry = CredentialContext::fromBytes("password_expiry_utc=-9223372036854775809\n");
$overflowQuit = CredentialContext::fromBytes("quit=9223372036854775808\n");
$redacted = $helperResponse->redacted();
$cleared = $helperResponse->clearSecrets();
$encodedContext = (new CredentialContext(
    url: 'https://Deploy%20Bot:wp%40token@GIT.example.test:443/wp-content%20deploy.git',
))->destructureUrl(true);
$rootHttpContext = (new CredentialContext(
    url: 'https://GIT.example.test/',
    path: 'stale/wp-content.git',
))->destructureUrl(true);
$fileUrlContext = (new CredentialContext(
    url: 'file:///srv/wp-content.git',
    host: 'stale.git.example.test',
    username: 'deploy-bot',
))->destructureUrl(true);
$helperProgramProtocolHost = [];
$helperProgramMissingCredential = false;
try {
    CredentialHelperExchange::invoke(
        ['get'],
        "protocol=https\nhost=git.example.test\n",
        static function (string $action, CredentialContext $context) use (&$helperProgramProtocolHost): ?CredentialContext {
            $helperProgramProtocolHost = [
                'action' => $action,
                'protocol' => $context->protocol,
                'host' => $context->host,
                'url' => $context->url,
            ];

            return null;
        },
    );
} catch (RuntimeException) {
    $helperProgramMissingCredential = true;
}
$helperProgramUrlOnly = [];
$helperProgramOutput = CredentialHelperExchange::invoke(
    ['fill'],
    "url=https://git.example.test/wp-content.git\n",
    static function (string $action, CredentialContext $context) use (&$helperProgramUrlOnly): CredentialContext {
        $helperProgramUrlOnly = [
            'action' => $action,
            'url' => $context->url,
            'protocol' => $context->protocol,
            'host' => $context->host,
        ];

        return new CredentialContext(username: 'deploy-bot', password: 'wp-deploy-token');
    },
);

return [
    'requestBytes' => $request->storageBytes(),
    'credentialUrl' => $helperResponse->toUrl(),
    'encodedContext' => [
        'host' => $encodedContext->host,
        'path' => $encodedContext->path,
        'username' => $encodedContext->username,
    ],
    'passwordExpiryUtc' => $helperResponse->passwordExpiryUtc,
    'emptyQuitFalse' => $emptyQuit->quit,
    'overflowExpiryIgnored' => $overflowExpiry->passwordExpiryUtc === null,
    'overflowQuitIgnored' => $overflowQuit->quit === null,
    'rootHttpPathCleared' => $rootHttpContext->path === null,
    'fileUrlClearedHost' => $fileUrlContext->host === null,
    'fileUrlClearedUsername' => $fileUrlContext->username === null,
    'fileUrlPath' => $fileUrlContext->path,
    'helperProgramProtocolHost' => $helperProgramProtocolHost,
    'helperProgramMissingCredential' => $helperProgramMissingCredential,
    'helperProgramUrlOnly' => $helperProgramUrlOnly,
    'helperProgramOutput' => $helperProgramOutput,
    'redactedBytes' => $redacted->storageBytes(),
    'clearedPassword' => $cleared->password,
    'clearedOauthRefreshToken' => $cleared->oauthRefreshToken,
    'wordpressUse' => 'A WordPress deployment tool can exchange Git credential-helper protocol fields, derive a safe display URL, and redact or clear deployment secrets before writing diagnostic logs.',
];
