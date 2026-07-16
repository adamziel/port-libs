<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveProviderLifecycle;
use PortLibs\Rclone\OneDriveTokenRenewer;

require_once dirname(__DIR__) . '/src/OneDriveTokenRenewer.php';
require_once dirname(__DIR__) . '/src/OneDriveProviderLifecycle.php';

$metadataReads = 0;
$renewer = new OneDriveTokenRenewer(
    'wordpress-provider-shutdown',
    static function () use (&$metadataReads): void {
        ++$metadataReads;
    },
);
$provider = new OneDriveProviderLifecycle($renewer);

$provider->startChangeNotify(true);
$maskedChangeNotifyRunning = $provider->isChangeNotifyRunning();
$provider->startChangeNotify();
$renewer->startUpload();
$firstShutdown = $provider->shutdown();
$expiryAfterShutdown = $renewer->expire();
$provider->startChangeNotify();
$secondShutdown = $provider->shutdown();

return [
    'source' => 'onedrive-provider-shutdown-preflight',
    'firstShutdown' => $firstShutdown,
    'secondShutdown' => $secondShutdown,
    'expiryAfterShutdownRefreshed' => $expiryAfterShutdown['refreshed'],
    'maskedChangeNotifyRunning' => $maskedChangeNotifyRunning,
    'changeNotifyRunningAfterShutdown' => $provider->isChangeNotifyRunning(),
    'providerEvents' => $provider->events(),
    'renewerEvents' => $renewer->events(),
    'metadataReads' => $metadataReads,
    'secretInputsRead' => false,
];
