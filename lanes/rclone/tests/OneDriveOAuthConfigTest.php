<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveOAuthConfig;

return [
    'onedrive oauth config uses common tenant and default scopes' => static function (TestRunner $t): void {
        $config = OneDriveOAuthConfig::make();

        $t->same(OneDriveOAuthConfig::SCOPE_ACCESS, $config['scopes']);
        $t->same('/common', $config['tenantPrefix']);
        $t->same('https://login.microsoftonline.com/common/oauth2/v2.0/token', $config['tokenUrl']);
        $t->same('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $config['authUrl']);
        $t->same('https://graph.microsoft.com', $config['graphUrl']);
        $t->same(false, $config['clientCredentials']);
    },
    'onedrive oauth config removes Sites scope when requested' => static function (TestRunner $t): void {
        $config = OneDriveOAuthConfig::make(
            tenant: 'tenant-id',
            disableSitePermission: true,
            accessScopes: ['Files.Read', 'Sites.Read.All', 'offline_access'],
        );

        $t->same(OneDriveOAuthConfig::SCOPE_ACCESS_WITHOUT_SITES, $config['scopes']);
        $t->same('/tenant-id', $config['tenantPrefix']);
        $t->same('https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token', $config['tokenUrl']);
    },
    'onedrive oauth config maps national cloud endpoints' => static function (TestRunner $t): void {
        $us = OneDriveOAuthConfig::make(OneDriveOAuthConfig::REGION_US, 'agency');
        $de = OneDriveOAuthConfig::make(OneDriveOAuthConfig::REGION_DE, 'tenant');
        $cn = OneDriveOAuthConfig::make(OneDriveOAuthConfig::REGION_CN, 'tenant');

        $t->same('https://login.microsoftonline.us/agency/oauth2/v2.0/token', $us['tokenUrl']);
        $t->same('https://graph.microsoft.us', $us['graphUrl']);
        $t->same('https://login.microsoftonline.de/tenant/oauth2/v2.0/authorize', $de['authUrl']);
        $t->same('https://graph.microsoft.de', $de['graphUrl']);
        $t->same('https://login.chinacloudapi.cn/tenant/oauth2/v2.0/token', $cn['tokenUrl']);
        $t->same('https://microsoftgraph.chinacloudapi.cn', $cn['graphUrl']);
    },
    'onedrive oauth config requires tenant for client credentials' => static function (TestRunner $t): void {
        $config = OneDriveOAuthConfig::make(
            tenant: 'contoso',
            disableSitePermission: true,
            clientCredentials: true,
            accessScopes: ['Files.Read'],
        );

        $t->same(['.default'], $config['scopes']);
        $t->same('/contoso', $config['tenantPrefix']);
        $t->same(true, $config['clientCredentials']);

        try {
            OneDriveOAuthConfig::make(clientCredentials: true);
            $t->fail('expected client credentials without tenant to fail');
        } catch (InvalidArgumentException $exception) {
            $t->same('tenant parameter must be set when using client_credentials', $exception->getMessage());
        }
    },
    'wordpress onedrive oauth preflight example exposes endpoint decisions' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-oauth-preflight.php';

        $t->same('onedrive-oauth-preflight', $example['source']);
        $t->same('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $example['browserAuthUrl']);
        $t->same(false, in_array('Sites.Read.All', $example['sitePermissionDisabledScopes'], true));
        $t->same(['.default'], $example['clientCredentialsScopes']);
        $t->same('https://login.microsoftonline.us/wp-tenant/oauth2/v2.0/token', $example['clientCredentialsTokenUrl']);
        $t->same('tenant parameter must be set when using client_credentials', $example['missingTenantError']);
    },
];
