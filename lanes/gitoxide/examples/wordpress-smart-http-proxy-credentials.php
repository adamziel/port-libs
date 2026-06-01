<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-smart-http-proxy-credentials.php';

return [
    'proxyHelperCalls' => $fixture['helperCalls'],
    'storedProxyCredentials' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['storedCredentials']
    ),
    'usernameOnlyProxyHelperCalls' => $fixture['usernameOnlyProxyHelperCalls'],
    'usernameOnlyProxyCredentialUrl' => $fixture['usernameOnlyProxyUrl'],
    'usernameOnlyProxyAuthorizationSent' => $fixture['usernameOnlyProxyAuthorizationSent'],
    'usernameOnlyProxyCredentialsStored' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['usernameOnlyProxyStores']
    ),
    'usernameOnlyOriginProxyHeaderLeaked' => $fixture['usernameOnlyOriginProxyHeaderLeaked'],
    'defaultPortProxyResponseSuccessful' => $fixture['defaultPortProxyResponseSuccessful'],
    'defaultPortProxyHelperCalls' => $fixture['defaultPortProxyHelperCalls'],
    'defaultPortProxyCredentialUrl' => $fixture['defaultPortProxyHelperCalls'][0][0] ?? null,
    'defaultPortProxyRequestProxyUrl' => $fixture['defaultPortProxyRequestProxyUrl'],
    'defaultPortProxyRequestProxyStream' => $fixture['defaultPortProxyRequestProxyStream'],
    'defaultPortProxyAuthorizationSent' => $fixture['defaultPortProxyAuthorizationSent'],
    'defaultPortProxyCredentialsStored' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['defaultPortProxyStores']
    ),
    'defaultPortProxyOriginProxyHeaderLeaked' => $fixture['defaultPortProxyOriginProxyHeaderLeaked'],
    'defaultPortProxyPostCookieHeader' => $fixture['defaultPortProxyPostCookieHeader'],
    'pathNoSlashCookieResponseSuccessful' => $fixture['pathNoSlashCookieResponseSuccessful'],
    'pathNoSlashCookieUsedProxy' => $fixture['pathNoSlashCookieUsedProxy'],
    'pathNoSlashCookieAuthorizationSent' => $fixture['pathNoSlashCookieAuthorizationSent'],
    'pathNoSlashCookieOriginProxyHeaderLeaked' => $fixture['pathNoSlashCookieOriginProxyHeaderLeaked'],
    'pathNoSlashCookiePostCookieHeader' => $fixture['pathNoSlashCookiePostCookieHeader'],
    'pathNoSlashCookieEmptyPathOmitted' => $fixture['pathNoSlashCookieEmptyPathOmitted'],
    'cidrNoProxyBypassedProxy' => $fixture['cidrNoProxyBypassedProxy'],
    'cidrNoProxyHelperCalls' => $fixture['cidrNoProxyHelperCalls'],
    'cidrNoProxyPostCookieHeader' => $fixture['cidrNoProxyPostCookieHeader'],
    'ipv6LiteralNoProxyBypassedProxy' => $fixture['ipv6LiteralNoProxyBypassedProxy'],
    'ipv6LiteralNoProxyHelperCalls' => $fixture['ipv6LiteralNoProxyHelperCalls'],
    'ipv6LiteralNoProxyPostCookieHeader' => $fixture['ipv6LiteralNoProxyPostCookieHeader'],
    'wildcardLiteralNoProxyUsedProxy' => $fixture['wildcardLiteralNoProxyUsedProxy'],
    'wildcardLiteralNoProxyHelperCalls' => $fixture['wildcardLiteralNoProxyHelperCalls'],
    'wildcardLiteralNoProxyAuthorizationSent' => $fixture['wildcardLiteralNoProxyAuthorizationSent'],
    'starNoProxyBypassedProxy' => $fixture['starNoProxyBypassedProxy'],
    'starNoProxyHelperCalls' => $fixture['starNoProxyHelperCalls'],
    'starNoProxyPostCookieHeader' => $fixture['starNoProxyPostCookieHeader'],
    'trailingDotNoProxyBypassedProxy' => $fixture['trailingDotNoProxyBypassedProxy'],
    'trailingDotNoProxyHelperCalls' => $fixture['trailingDotNoProxyHelperCalls'],
    'trailingDotNoProxyPostCookieHeader' => $fixture['trailingDotNoProxyPostCookieHeader'],
    'trailingDotDomainCookieBypassedProxy' => $fixture['trailingDotDomainCookieBypassedProxy'],
    'trailingDotDomainCookieHelperCalls' => $fixture['trailingDotDomainCookieHelperCalls'],
    'trailingDotDomainCookiePostCookieHeader' => $fixture['trailingDotDomainCookiePostCookieHeader'],
    'trailingDotDomainAttributeCookieBypassedProxy' => $fixture['trailingDotDomainAttributeCookieBypassedProxy'],
    'trailingDotDomainAttributeCookieHelperCalls' => $fixture['trailingDotDomainAttributeCookieHelperCalls'],
    'trailingDotDomainAttributeCookiePostCookieHeader' => $fixture['trailingDotDomainAttributeCookiePostCookieHeader'],
    'portQualifiedNoProxyUsedProxy' => $fixture['portQualifiedNoProxyUsedProxy'],
    'portQualifiedNoProxyHelperCalls' => $fixture['portQualifiedNoProxyHelperCalls'],
    'portQualifiedNoProxyAuthorizationSent' => $fixture['portQualifiedNoProxyAuthorizationSent'],
    'portQualifiedNoProxyPostCookieHeader' => $fixture['portQualifiedNoProxyPostCookieHeader'],
    'httpsProxyFallbackUsedProxy' => $fixture['httpsProxyFallbackUsedProxy'],
    'httpsProxyFallbackHelperCalls' => $fixture['httpsProxyFallbackHelperCalls'],
    'httpsProxyFallbackAuthorizationSent' => $fixture['httpsProxyFallbackAuthorizationSent'],
    'httpsProxyFallbackPostCookieHeader' => $fixture['httpsProxyFallbackPostCookieHeader'],
    'httpsAllProxyUsedProxy' => $fixture['httpsAllProxyUsedProxy'],
    'httpsAllProxyHelperCalls' => $fixture['httpsAllProxyHelperCalls'],
    'httpsAllProxyAuthorizationSent' => $fixture['httpsAllProxyAuthorizationSent'],
    'httpsAllProxyPostCookieHeader' => $fixture['httpsAllProxyPostCookieHeader'],
    'httpsProxyEmptyAllProxyBypassedProxy' => $fixture['httpsProxyEmptyAllProxyBypassedProxy'],
    'httpsProxyEmptyAllProxyHelperCalls' => $fixture['httpsProxyEmptyAllProxyHelperCalls'],
    'httpsProxyEmptyAllProxyPostCookieHeader' => $fixture['httpsProxyEmptyAllProxyPostCookieHeader'],
    'upgradeRedirectUsedHttpsProxy' => $fixture['upgradeRedirectUsedHttpsProxy'],
    'upgradeRedirectHelperCalls' => $fixture['upgradeRedirectHelperCalls'],
    'upgradeRedirectRequestUrls' => $fixture['upgradeRedirectRequestUrls'],
    'upgradeRedirectPostCookieHeader' => $fixture['upgradeRedirectPostCookieHeader'],
    'upgradeRedirectResponseSuccessful' => $fixture['upgradeRedirectResponseSuccessful'],
    'protocolRelativeRedirectResponseSuccessful' => $fixture['protocolRelativeRedirectResponseSuccessful'],
    'protocolRelativeRedirectRequestUrls' => $fixture['protocolRelativeRedirectRequestUrls'],
    'protocolRelativeRedirectMethods' => $fixture['protocolRelativeRedirectMethods'],
    'protocolRelativeRedirectUsedProxy' => $fixture['protocolRelativeRedirectUsedProxy'],
    'protocolRelativeRedirectHelperCalls' => $fixture['protocolRelativeRedirectHelperCalls'],
    'protocolRelativeRedirectPostCookieHeader' => $fixture['protocolRelativeRedirectPostCookieHeader'],
    'protocolRelativeRedirectOriginProxyHeaderLeaked' => $fixture['protocolRelativeRedirectOriginProxyHeaderLeaked'],
    'protocolRelativeRedirectCredentialsStored' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['protocolRelativeRedirectStores']
    ),
    'urlCredentialProxyHelperCalls' => $fixture['urlCredentialProxyHelperCalls'],
    'urlCredentialProxyUrl' => $fixture['urlCredentialProxyUrl'],
    'urlCredentialProxyAuthorizationSent' => $fixture['urlCredentialProxyAuthorizationSent'],
    'urlCredentialProxyCredentialsStored' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['urlCredentialProxyStores']
    ),
    'urlCredentialOriginProxyHeaderLeaked' => $fixture['urlCredentialOriginProxyHeaderLeaked'],
    'proxyAuthorizationSent' => $fixture['proxyAuthorizationSent'],
    'originProxyHeaderLeaked' => $fixture['originProxyHeaderLeaked'],
    'redirectRequestUrls' => $fixture['redirectRequestUrls'],
    'redirectProxyHelperCalls' => $fixture['redirectHelperCalls'],
    'redirectProxyAuthorizationReused' => $fixture['redirectProxyAuthorizationReused'],
    'redirectStoredProxyCredentials' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['redirectStoredCredentials']
    ),
    'redirectErasedProxyCredentials' => $fixture['redirectErasedCredentials'],
    'unexpectedStatusRejected' => $fixture['unexpectedStatusRejected'],
    'unexpectedStatusStores' => $fixture['unexpectedStatusStores'],
    'unexpectedStatusErasures' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['unexpectedStatusErasures']
    ),
    'notModifiedProxyResponseSuccessful' => $fixture['notModifiedProxyResponseSuccessful'],
    'notModifiedProxyHelperCalls' => $fixture['notModifiedProxyHelperCalls'],
    'notModifiedProxyCredentialsStored' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['notModifiedProxyStores']
    ),
    'notModifiedProxyCredentialsErased' => $fixture['notModifiedProxyErasures'],
    'notModifiedProxyPostCookieHeader' => $fixture['notModifiedProxyPostCookieHeader'],
    'notModifiedNoRedirectRejected' => $fixture['notModifiedNoRedirectRejected'],
    'notModifiedNoRedirectStatusCode' => $fixture['notModifiedNoRedirectStatusCode'],
    'notModifiedNoRedirectKind' => $fixture['notModifiedNoRedirectKind'],
    'notModifiedNoRedirectHelperCalls' => $fixture['notModifiedNoRedirectHelperCalls'],
    'notModifiedNoRedirectCredentialsStored' => $fixture['notModifiedNoRedirectStores'],
    'notModifiedNoRedirectCredentialsErased' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['notModifiedNoRedirectErasures']
    ),
    'notModifiedNoRedirectRequestCount' => $fixture['notModifiedNoRedirectRequestCount'],
    'wordpressUse' => $fixture['wordpressUse'],
];
