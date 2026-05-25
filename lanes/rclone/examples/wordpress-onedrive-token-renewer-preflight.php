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

$watchdogRenewer = new OneDriveTokenRenewer(
    'wordpress-watchdog-upload',
    static function () use (&$metadataReads): void {
        $metadataReads[] = 'read-root-metadata';
    },
);
$watchdogRenewer->startUpload();
$watchdogActive = $watchdogRenewer->watchdogCycle();
$watchdogAfterClosed = $watchdogRenewer->watchdogCycle(false);

$noExpirySource = new OneDriveTokenRenewer(
    'wordpress-no-expiry-source',
    static function () use (&$metadataReads): void {
        $metadataReads[] = 'unexpected-read';
    },
    false,
);
$watchdogNoExpirySource = $noExpirySource->watchdogCycle();

$underflowRenewer = new OneDriveTokenRenewer(
    'wordpress-upload-accounting-underflow',
    static function () use (&$metadataReads): void {
        $metadataReads[] = 'unexpected-underflow-read';
    },
);
$underflowRenewer->stopUpload();
$watchdogUnderflow = $underflowRenewer->watchdogCycle();

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
    'watchdogActiveRefreshed' => $watchdogActive['refreshed'],
    'watchdogAfterClosedRunning' => $watchdogAfterClosed['running'],
    'watchdogNoExpirySourceRunning' => $watchdogNoExpirySource['running'],
    'watchdogUnderflowRefreshed' => $watchdogUnderflow['refreshed'],
    'watchdogUnderflowActiveUploads' => $watchdogUnderflow['activeUploads'],
    'watchdogEvents' => $watchdogRenewer->events(),
    'noExpirySourceEvents' => $noExpirySource->events(),
    'underflowEvents' => $underflowRenewer->events(),
    'events' => $renewer->events(),
    'secretInputsRead' => false,
];
