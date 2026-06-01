<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CredentialContext;
use PortLibs\Gitoxide\CredentialHelperExchange;
use PortLibs\Gitoxide\CredentialHelperInvocation;
use PortLibs\Gitoxide\CredentialHelperOutcome;

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
$localPathContext = (new CredentialContext(
    url: '/srv/wp-content.git',
    host: 'stale.git.example.test',
    username: 'deploy-bot',
    password: 'stale-token',
))->destructureUrl(true);
$fileAuthorityContext = (new CredentialContext(
    url: 'file://Deploy@[::1]/var/cache/wp-content.git',
))->destructureUrl(true);
$pathlessExtensionContext = (new CredentialContext(
    url: 'rad://deploy@example.git',
    path: 'stale/wp-content.git',
))->destructureUrl(true);
$hostlessExtensionContext = (new CredentialContext(
    url: 'abc:///wp-content/site.git',
    host: 'stale.git.example.test',
))->destructureUrl(true);
$httpPathDisabledContext = (new CredentialContext(
    url: 'https://git.example.test/ignored/wp-content.git',
    path: 'cached/wp-content.git',
))->destructureUrl(false);
$passwordOnlyHttpContext = (new CredentialContext(
    url: 'http://:password@example.com/~byron/hello',
))->destructureUrl(true);
$emptyUserHttpContext = (new CredentialContext(
    url: 'http://@example.com/~byron/hello',
))->destructureUrl(true);
$nonUtf8LocalPathContext = (new CredentialContext(
    url: "/srv/wp-content\xff.git",
    host: 'stale.git.example.test',
    username: 'deploy-bot',
))->destructureUrl(true);
$localRelativeContext = (new CredentialContext(
    url: 'wp-content deploy.git',
    host: 'stale.git.example.test',
    username: 'deploy-bot',
))->destructureUrl(true);
$localAbsoluteWhitespaceContext = (new CredentialContext(
    url: '/srv/wp-content deploy.git ',
))->destructureUrl(true);
$localTildeContext = (new CredentialContext(
    url: '~/wp-content.git',
))->destructureUrl(true);
$fileRelativeAuthorityRootContext = (new CredentialContext(
    url: 'file://../',
    path: 'stale/wp-content.git',
))->destructureUrl(true);
$duplicateInvalidStringRejected = false;
try {
    CredentialContext::fromBytes("username=bad\xff\nusername=deploy-bot\n");
} catch (InvalidArgumentException) {
    $duplicateInvalidStringRejected = true;
}
$constructorInvalidStringRejected = false;
try {
    new CredentialContext(username: "deploy-bot\xff");
} catch (InvalidArgumentException) {
    $constructorInvalidStringRejected = true;
}
$constructorByteContext = new CredentialContext(
    url: "file:///srv/wp-content\xff.git",
    path: "srv/wp-content\xff.git",
);
$duplicateBytePath = CredentialContext::fromBytes("path=wp-content\xff.git\npath=wp-content.git\n");
$bareCarriageReturnPath = CredentialContext::fromBytes("path=wp-content\r");
$crlfTerminatedPath = CredentialContext::fromBytes("path=wp-content\r\n");
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
$helperProgramFirstArgOnly = [];
$helperProgramFirstArgOnlyOutput = CredentialHelperExchange::invoke(
    ['get', 'ignored-by-program-main'],
    "url=https://git.example.test/wp-content.git\n",
    static function (string $action, CredentialContext $context) use (&$helperProgramFirstArgOnly): CredentialContext {
        $helperProgramFirstArgOnly = [
            'action' => $action,
            'url' => $context->url,
        ];

        return new CredentialContext(username: 'deploy-bot', password: 'wp-deploy-token');
    },
);
$helperProgramMissingActionRejected = false;
try {
    CredentialHelperExchange::invoke(
        [],
        "url=https://git.example.test/wp-content.git\n",
        static function (): ?CredentialContext {
            throw new RuntimeException('missing helper action must reject before callback execution');
        },
    );
} catch (InvalidArgumentException) {
    $helperProgramMissingActionRejected = true;
}
$helperProgramInvalidActionRejected = false;
try {
    CredentialHelperExchange::invoke(
        ['credential-store'],
        "url=https://git.example.test/wp-content.git\n",
        static function (): ?CredentialContext {
            throw new RuntimeException('invalid helper action must reject before callback execution');
        },
    );
} catch (InvalidArgumentException) {
    $helperProgramInvalidActionRejected = true;
}
$helperProgramStoreContextRejected = false;
try {
    CredentialHelperExchange::invoke(
        ['store'],
        "url=https://git.example.test/wp-content.git\nusername=deploy-bot\npassword=wp-deploy-token\n",
        static fn (): CredentialContext => new CredentialContext(username: 'unexpected', password: 'unexpected'),
    );
} catch (LogicException) {
    $helperProgramStoreContextRejected = true;
}
$helperInvocationCalls = [];
$helperInvocationOutcome = CredentialHelperInvocation::get(
    new CredentialContext(url: 'https://git.example.test/wp-content.git'),
    static function (string $action, string $payload) use (&$helperInvocationCalls): string {
        $helperInvocationCalls[] = [$action, $payload];

        return "username=deploy-bot\npassword=wp-deploy-token\nquit=1\n";
    },
);
CredentialHelperInvocation::store(
    $helperInvocationOutcome,
    static function (string $action, string $payload) use (&$helperInvocationCalls): string {
        $helperInvocationCalls[] = [$action, $payload];

        return '';
    },
);
CredentialHelperInvocation::erase(
    $helperInvocationOutcome,
    static function (string $action, string $payload) use (&$helperInvocationCalls): string {
        $helperInvocationCalls[] = [$action, $payload];

        return '';
    },
);
$helperRequiredIdentity = CredentialHelperOutcome::requireIdentity($helperInvocationOutcome, $request);
$helperMissingIdentityMessage = '';
try {
    CredentialHelperOutcome::requireIdentity(
        new CredentialHelperOutcome(
            username: 'deploy-bot',
            password: null,
            oauthRefreshToken: null,
            quit: false,
            nextActionBytes: "username=deploy-bot\n",
        ),
        new CredentialContext(
            protocol: 'https',
            host: 'git.example.test',
            path: 'wp-content.git',
            username: 'deploy-bot',
            password: 'wp-deploy-token',
            oauthRefreshToken: 'wp-refresh-token',
        ),
    );
} catch (RuntimeException $error) {
    $helperMissingIdentityMessage = $error->getMessage();
}
$helperQuitMessage = '';
try {
    CredentialHelperOutcome::requireIdentity(
        new CredentialHelperOutcome(
            username: null,
            password: null,
            oauthRefreshToken: null,
            quit: true,
            nextActionBytes: "quit=1\n",
        ),
        $request,
    );
} catch (RuntimeException $error) {
    $helperQuitMessage = $error->getMessage();
}

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
    'localPathClearedHost' => $localPathContext->host === null,
    'localPathClearedUsername' => $localPathContext->username === null,
    'localPathClearedPassword' => $localPathContext->password === null,
    'localPathPath' => $localPathContext->path,
    'fileAuthorityContext' => [
        'protocol' => $fileAuthorityContext->protocol,
        'username' => $fileAuthorityContext->username,
        'host' => $fileAuthorityContext->host,
        'path' => $fileAuthorityContext->path,
    ],
    'pathlessExtensionContext' => [
        'protocol' => $pathlessExtensionContext->protocol,
        'username' => $pathlessExtensionContext->username,
        'host' => $pathlessExtensionContext->host,
        'path' => $pathlessExtensionContext->path,
    ],
    'hostlessExtensionContext' => [
        'protocol' => $hostlessExtensionContext->protocol,
        'host' => $hostlessExtensionContext->host,
        'path' => $hostlessExtensionContext->path,
    ],
    'httpPathDisabledContext' => [
        'protocol' => $httpPathDisabledContext->protocol,
        'host' => $httpPathDisabledContext->host,
        'path' => $httpPathDisabledContext->path,
    ],
    'passwordOnlyHttpContext' => [
        'protocol' => $passwordOnlyHttpContext->protocol,
        'username' => $passwordOnlyHttpContext->username,
        'password' => $passwordOnlyHttpContext->password,
        'host' => $passwordOnlyHttpContext->host,
        'path' => $passwordOnlyHttpContext->path,
        'promptUrl' => $passwordOnlyHttpContext->toUrl(),
    ],
    'emptyUserHttpContext' => [
        'protocol' => $emptyUserHttpContext->protocol,
        'username' => $emptyUserHttpContext->username,
        'password' => $emptyUserHttpContext->password,
        'host' => $emptyUserHttpContext->host,
        'path' => $emptyUserHttpContext->path,
        'promptUrl' => $emptyUserHttpContext->toUrl(),
    ],
    'localRelativeContext' => [
        'protocol' => $localRelativeContext->protocol,
        'host' => $localRelativeContext->host,
        'username' => $localRelativeContext->username,
        'path' => $localRelativeContext->path,
        'url' => $localRelativeContext->url,
    ],
    'localAbsoluteWhitespaceContext' => [
        'protocol' => $localAbsoluteWhitespaceContext->protocol,
        'host' => $localAbsoluteWhitespaceContext->host,
        'path' => $localAbsoluteWhitespaceContext->path,
    ],
    'localTildeContext' => [
        'protocol' => $localTildeContext->protocol,
        'username' => $localTildeContext->username,
        'path' => $localTildeContext->path,
    ],
    'fileRelativeAuthorityRootContext' => [
        'protocol' => $fileRelativeAuthorityRootContext->protocol,
        'host' => $fileRelativeAuthorityRootContext->host,
        'path' => $fileRelativeAuthorityRootContext->path,
    ],
    'nonUtf8LocalPathPreserved' => $nonUtf8LocalPathContext->protocol === 'file'
        && $nonUtf8LocalPathContext->host === null
        && $nonUtf8LocalPathContext->username === null
        && $nonUtf8LocalPathContext->path === "srv/wp-content\xff.git"
        && str_contains($nonUtf8LocalPathContext->storageBytes(), "path=srv/wp-content\xff.git\n"),
    'duplicateInvalidStringRejected' => $duplicateInvalidStringRejected,
    'constructorInvalidStringRejected' => $constructorInvalidStringRejected,
    'constructorByteFieldsPreserved' => $constructorByteContext->path === "srv/wp-content\xff.git"
        && $constructorByteContext->url === "file:///srv/wp-content\xff.git"
        && str_contains($constructorByteContext->storageBytes(), "path=srv/wp-content\xff.git\n")
        && str_contains($constructorByteContext->storageBytes(), "url=file:///srv/wp-content\xff.git\n"),
    'duplicateBytePath' => $duplicateBytePath->path,
    'bareCarriageReturnPathPreserved' => $bareCarriageReturnPath->path === "wp-content\r",
    'crlfPathTerminatorStripped' => $crlfTerminatedPath->path === 'wp-content',
    'helperProgramProtocolHost' => $helperProgramProtocolHost,
    'helperProgramMissingCredential' => $helperProgramMissingCredential,
    'helperProgramUrlOnly' => $helperProgramUrlOnly,
    'helperProgramOutput' => $helperProgramOutput,
    'helperProgramFirstArgOnly' => $helperProgramFirstArgOnly,
    'helperProgramFirstArgOnlyOutput' => $helperProgramFirstArgOnlyOutput,
    'helperProgramMissingActionRejected' => $helperProgramMissingActionRejected,
    'helperProgramInvalidActionRejected' => $helperProgramInvalidActionRejected,
    'helperProgramStoreContextRejected' => $helperProgramStoreContextRejected,
    'helperInvocationIdentity' => $helperInvocationOutcome->identity(),
    'helperRequiredIdentity' => $helperRequiredIdentity,
    'helperInvocationQuit' => $helperInvocationOutcome->quit,
    'helperInvocationNextQuit' => $helperInvocationOutcome->nextActionContext()->quit,
    'helperInvocationStorePayload' => $helperInvocationCalls[1][1],
    'helperInvocationErasePayload' => $helperInvocationCalls[2][1],
    'helperMissingIdentityRedacted' => str_contains($helperMissingIdentityMessage, "password=<redacted>\n")
        && str_contains($helperMissingIdentityMessage, "oauth_refresh_token=<redacted>\n")
        && !str_contains($helperMissingIdentityMessage, 'wp-deploy-token')
        && !str_contains($helperMissingIdentityMessage, 'wp-refresh-token'),
    'helperQuitMessage' => $helperQuitMessage,
    'redactedBytes' => $redacted->storageBytes(),
    'clearedPassword' => $cleared->password,
    'clearedOauthRefreshToken' => $cleared->oauthRefreshToken,
    'wordpressUse' => 'A WordPress deployment tool can exchange Git credential-helper protocol fields, destructure local mirror and extension-scheme remotes, preserve an explicit repository path when HTTP path matching is disabled, distinguish empty HTTP userinfo from password-only helper URLs, preserve byte-oriented and whitespace-bearing local mirror paths without username expansion, enforce string-field UTF-8 and helper action boundaries before callbacks, derive a safe display URL, and redact or clear deployment secrets before writing diagnostic logs.',
];
