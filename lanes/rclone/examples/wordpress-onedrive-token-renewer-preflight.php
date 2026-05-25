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

$bracketedUpload = $renewer->duringUpload(static function () use ($renewer): string {
    $uploadExpiry = $renewer->expire();

    return $uploadExpiry['refreshed'] ? 'wxr-upload-refreshed' : 'wxr-upload-not-refreshed';
});
$activeUploadsAfterBracket = $renewer->activeUploads();

$bracketedFailure = null;
try {
    $renewer->duringUpload(static function (): void {
        throw new RuntimeException('wxr upload failed');
    });
} catch (RuntimeException $exception) {
    $bracketedFailure = $exception->getMessage();
}
$activeUploadsAfterFailure = $renewer->activeUploads();

$renewer->shutdown();
$shutdownExpiry = $renewer->expire();

return [
    'source' => 'onedrive-token-renewer-preflight',
    'renewerName' => $renewer->name(),
    'metadataReads' => $metadataReads,
    'idleExpiryRefreshed' => $idleExpiry['refreshed'],
    'activeExpiryRefreshed' => $activeExpiry['refreshed'],
    'postUploadExpiryRefreshed' => $postUploadExpiry['refreshed'],
    'bracketedUpload' => $bracketedUpload,
    'bracketedFailure' => $bracketedFailure,
    'activeUploadsAfterBracket' => $activeUploadsAfterBracket,
    'activeUploadsAfterFailure' => $activeUploadsAfterFailure,
    'shutdownExpiryRefreshed' => $shutdownExpiry['refreshed'],
    'activeUploadsAfterStop' => $renewer->activeUploads(),
    'expirySignals' => $renewer->expirySignals(),
    'armedForNextExpiry' => $renewer->isArmedForNextExpiry(),
    'wasArmedAfterActiveExpiry' => $wasArmedAfterActiveExpiry,
    'events' => $renewer->events(),
    'secretInputsRead' => false,
];
