<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialContext;

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
$redacted = $helperResponse->redacted();
$cleared = $helperResponse->clearSecrets();
$encodedContext = (new CredentialContext(
    url: 'https://Deploy%20Bot:wp%40token@GIT.example.test:443/wp-content%20deploy.git',
))->destructureUrl(true);
$rootHttpContext = (new CredentialContext(
    url: 'https://GIT.example.test/',
    path: 'stale/wp-content.git',
))->destructureUrl(true);

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
    'rootHttpPathCleared' => $rootHttpContext->path === null,
    'redactedBytes' => $redacted->storageBytes(),
    'clearedPassword' => $cleared->password,
    'clearedOauthRefreshToken' => $cleared->oauthRefreshToken,
    'wordpressUse' => 'A WordPress deployment tool can exchange Git credential-helper protocol fields, derive a safe display URL, and redact or clear deployment secrets before writing diagnostic logs.',
];
