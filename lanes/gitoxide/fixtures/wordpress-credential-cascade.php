<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialCascade;
use PortLibs\Gitoxide\CredentialContext;

$actions = [];
$storePayloads = [];
$erasePayloads = [];

$cacheHelper = static function (string $action, string $payload) use (&$actions, &$storePayloads, &$erasePayloads): ?string {
    $actions[] = "cache:{$action}";
    if ($action === 'store') {
        $storePayloads[] = $payload;

        return null;
    }
    if ($action === 'erase') {
        $erasePayloads[] = $payload;

        return null;
    }

    return "username=expired-deploy\npassword=expired-token\npassword_expiry_utc=1\n";
};

$oauthHelper = static function (string $action, string $payload) use (&$actions, &$storePayloads, &$erasePayloads): ?string {
    $actions[] = "oauth:{$action}";
    if ($action === 'store') {
        $storePayloads[] = $payload;

        return null;
    }
    if ($action === 'erase') {
        $erasePayloads[] = $payload;

        return null;
    }

    return "oauth_refresh_token=wp-refresh-token\n";
};

$deployHelper = static function (string $action, string $payload) use (&$actions, &$storePayloads, &$erasePayloads): ?string {
    $actions[] = "deploy:{$action}";
    if ($action === 'store') {
        $storePayloads[] = $payload;

        return null;
    }
    if ($action === 'erase') {
        $erasePayloads[] = $payload;

        return null;
    }

    return "username=deploy-bot\npassword=wp-deploy-token\n";
};

$cascade = new CredentialCascade(
    [$cacheHelper, $oauthHelper, $deployHelper],
    useHttpPath: true,
    nowUtc: 1711398853,
);
$result = $cascade->get(new CredentialContext(url: 'https://git.example.test/wp-content.git'));
$cascade->store($result);
$cascade->erase($result);
$nextActionContext = $result->nextActionContext();

$verbatimHelperCascade = new CredentialCascade([
    static fn (): string => "protocol=ftp\nhost=media.example.test:2121\npath=/srv/wp-content.git/\n",
    static fn (): string => "username=ftp-deploy\npassword=ftp-token\n",
], useHttpPath: true);
$verbatimHelperResult = $verbatimHelperCascade->get(new CredentialContext(url: 'https://git.example.test/wp-content.git'));
$verbatimHelperContext = $verbatimHelperResult->nextActionContext();

$completeQuitCascade = new CredentialCascade([
    static fn (): string => "username=emergency-deploy\npassword=emergency-token\nquit=1\n",
    static function (): string {
        throw new RuntimeException('upstream should stop after complete credentials');
    },
], useHttpPath: true);
$completeQuitResult = $completeQuitCascade->get(new CredentialContext(url: 'https://git.example.test/wp-content.git'));

$diagnosticContext = $result->context->redacted();
$diagnosticBytes = $diagnosticContext->storageBytes();

return [
    'identity' => $result->identity(),
    'contextPath' => $result->context->path,
    'nextActionContext' => [
        'protocol' => $nextActionContext->protocol,
        'host' => $nextActionContext->host,
        'path' => $nextActionContext->path,
        'username' => $nextActionContext->username,
    ],
    'verbatimHelperContext' => [
        'protocol' => $verbatimHelperContext->protocol,
        'host' => $verbatimHelperContext->host,
        'path' => $verbatimHelperContext->path,
        'username' => $verbatimHelperContext->username,
    ],
    'passwordExpiryUtc' => $result->context->passwordExpiryUtc,
    'nextActionBytes' => $result->nextActionBytes(),
    'actions' => $actions,
    'storePayloads' => $storePayloads,
    'erasePayloads' => $erasePayloads,
    'completeQuitIdentity' => $completeQuitResult->identity(),
    'completeQuitPropagated' => $completeQuitResult->quit,
    'diagnosticBytes' => $diagnosticBytes,
    'secretsInDiagnosticLog' => str_contains($diagnosticBytes, 'wp-deploy-token')
        || str_contains($diagnosticBytes, 'wp-refresh-token')
        || str_contains($diagnosticBytes, 'expired-token'),
    'wordpressUse' => 'A WordPress deployment tool can run a native credential cascade, ignore expired cached credentials, merge OAuth refresh metadata, and store or erase the final helper context without invoking git credential.',
];
