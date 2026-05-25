<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveOAuthConfig;

require_once dirname(__DIR__) . '/src/OneDriveOAuthConfig.php';

$default = OneDriveOAuthConfig::make();
$sitePermissionDisabled = OneDriveOAuthConfig::make(disableSitePermission: true);
$clientCredentials = OneDriveOAuthConfig::make(
    OneDriveOAuthConfig::REGION_US,
    'wp-tenant',
    clientCredentials: true,
);

$missingTenantError = null;
try {
    OneDriveOAuthConfig::make(clientCredentials: true);
} catch (InvalidArgumentException $exception) {
    $missingTenantError = $exception->getMessage();
}

return [
    'source' => 'onedrive-oauth-preflight',
    'browserAuthUrl' => $default['authUrl'],
    'sitePermissionDisabledScopes' => $sitePermissionDisabled['scopes'],
    'clientCredentialsScopes' => $clientCredentials['scopes'],
    'clientCredentialsTokenUrl' => $clientCredentials['tokenUrl'],
    'graphEndpoint' => $clientCredentials['graphUrl'],
    'missingTenantError' => $missingTenantError,
    'secretInputsRead' => false,
];
