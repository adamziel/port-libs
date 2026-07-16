<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Focused native model of rclone's OneDrive makeOauthConfig().
 *
 * This is credential-free: it derives scopes and endpoint URLs from options,
 * but never reads or stores tokens, secrets, browser auth state, or remotes.
 */
final class OneDriveOAuthConfig
{
    public const REGION_GLOBAL = 'global';
    public const REGION_US = 'us';
    public const REGION_DE = 'de';
    public const REGION_CN = 'cn';

    public const CONFIG_CLIENT_CREDENTIALS = 'client_credentials';

    /** @var list<string> */
    public const SCOPE_ACCESS = [
        'Files.Read',
        'Files.ReadWrite',
        'Files.Read.All',
        'Files.ReadWrite.All',
        'Sites.Read.All',
        'offline_access',
    ];

    /** @var list<string> */
    public const SCOPE_ACCESS_WITHOUT_SITES = [
        'Files.Read',
        'Files.ReadWrite',
        'Files.Read.All',
        'Files.ReadWrite.All',
        'offline_access',
    ];

    /** @var list<string> */
    public const SCOPE_ACCESS_CLIENT_CREDENTIALS = ['.default'];

    /** @var array<string, string> */
    private const AUTH_ENDPOINTS = [
        self::REGION_GLOBAL => 'https://login.microsoftonline.com',
        self::REGION_US => 'https://login.microsoftonline.us',
        self::REGION_DE => 'https://login.microsoftonline.de',
        self::REGION_CN => 'https://login.chinacloudapi.cn',
    ];

    /** @var array<string, string> */
    private const GRAPH_ENDPOINTS = [
        self::REGION_GLOBAL => 'https://graph.microsoft.com',
        self::REGION_US => 'https://graph.microsoft.us',
        self::REGION_DE => 'https://graph.microsoft.de',
        self::REGION_CN => 'https://microsoftgraph.chinacloudapi.cn',
    ];

    /**
     * @param null|list<string> $accessScopes
     * @return array{scopes: list<string>, tokenUrl: string, authUrl: string, graphUrl: string, tenantPrefix: string, clientCredentials: bool}
     */
    public static function make(
        string $region = self::REGION_GLOBAL,
        string $tenant = '',
        bool $disableSitePermission = false,
        bool $clientCredentials = false,
        ?array $accessScopes = null,
    ): array {
        if (!isset(self::AUTH_ENDPOINTS[$region])) {
            throw new \InvalidArgumentException("unknown OneDrive region: {$region}");
        }

        $scopes = $accessScopes ?? self::SCOPE_ACCESS;
        if ($disableSitePermission) {
            $scopes = self::SCOPE_ACCESS_WITHOUT_SITES;
        }

        $prefix = $tenant !== '' ? '/' . trim($tenant, '/') : '/common';
        if ($clientCredentials) {
            $scopes = self::SCOPE_ACCESS_CLIENT_CREDENTIALS;
            if ($tenant === '') {
                throw new \InvalidArgumentException(
                    'tenant parameter must be set when using ' . self::CONFIG_CLIENT_CREDENTIALS,
                );
            }
        }

        return [
            'scopes' => array_values($scopes),
            'tokenUrl' => self::AUTH_ENDPOINTS[$region] . $prefix . '/oauth2/v2.0/token',
            'authUrl' => self::AUTH_ENDPOINTS[$region] . $prefix . '/oauth2/v2.0/authorize',
            'graphUrl' => self::GRAPH_ENDPOINTS[$region],
            'tenantPrefix' => $prefix,
            'clientCredentials' => $clientCredentials,
        ];
    }
}
