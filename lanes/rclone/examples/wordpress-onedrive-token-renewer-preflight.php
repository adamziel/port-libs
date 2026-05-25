<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveTokenRenewer;

require_once dirname(__DIR__) . '/src/OneDriveTokenRenewer.php';

$metadataReads = [];
$renewer = new OneDriveTokenRenewer(
    'wordpress-media-upload',
    static function () use (&$metadataReads): void {
        $metadataReads[] = 'read-root-metadata';
    },
);

$idleExpiry = $renewer->expire();
$renewer->startUpload();
$activeExpiry = $renewer->expire();
$wasArmedAfterActiveExpiry = $renewer->isArmedForNextExpiry();
$renewer->stopUpload();
$postUploadExpiry = $renewer->expire();
$renewer->shutdown();
$shutdownExpiry = $renewer->expire();

return [
    'source' => 'onedrive-token-renewer-preflight',
    'renewerName' => $renewer->name(),
    'metadataReads' => $metadataReads,
    'idleExpiryRefreshed' => $idleExpiry['refreshed'],
    'activeExpiryRefreshed' => $activeExpiry['refreshed'],
    'postUploadExpiryRefreshed' => $postUploadExpiry['refreshed'],
    'shutdownExpiryRefreshed' => $shutdownExpiry['refreshed'],
    'activeUploadsAfterStop' => $renewer->activeUploads(),
    'expirySignals' => $renewer->expirySignals(),
    'armedForNextExpiry' => $renewer->isArmedForNextExpiry(),
    'wasArmedAfterActiveExpiry' => $wasArmedAfterActiveExpiry,
    'events' => $renewer->events(),
    'secretInputsRead' => false,
];
