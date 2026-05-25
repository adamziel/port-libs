<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveTokenRenewer;

return [
    'onedrive token renewer refreshes only while uploads are active' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $idle = $renewer->expire();
        $t->same(false, $idle['refreshed']);
        $t->same(0, $reads);

        $renewer->startUpload();
        $active = $renewer->expire();
        $t->same(true, $active['refreshed']);
        $t->same(null, $active['error']);
        $t->same(1, $active['activeUploads']);
        $t->same(1, $reads);

        $renewer->stopUpload();
        $afterStop = $renewer->expire();
        $t->same(false, $afterStop['refreshed']);
        $t->same(1, $reads);
        $t->same(0, $renewer->activeUploads());
        $t->same(3, $renewer->expirySignals());
        $t->same(true, $renewer->isArmedForNextExpiry());
    },
    'onedrive token renewer keeps upload count balanced for concurrent uploads' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $renewer->startUpload();
        $renewer->startUpload();
        $t->same(2, $renewer->activeUploads());

        $renewer->stopUpload();
        $result = $renewer->expire();
        $t->same(true, $result['refreshed']);
        $t->same(1, $result['activeUploads']);
        $t->same(1, $reads);

        $renewer->stopUpload();
        $renewer->stopUpload();
        $t->same(0, $renewer->activeUploads());
    },
    'onedrive token renewer reports callback errors without stopping upload accounting' => static function (TestRunner $t): void {
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function (): void {
            throw new RuntimeException('root metadata unavailable');
        });

        $renewer->startUpload();
        $result = $renewer->expire();

        $t->same(true, $result['refreshed']);
        $t->same('root metadata unavailable', $result['error']);
        $t->same(1, $result['activeUploads']);
        $t->same([
            'upload-started',
            'expiry-refresh-started',
            'expiry-refresh-error',
            'expiry-rearmed',
        ], $renewer->events());
    },
    'onedrive token renewer rearms after each handled expiry until shutdown' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $idle = $renewer->expire();
        $renewer->startUpload();
        $firstActive = $renewer->expire();
        $secondActive = $renewer->expire();
        $renewer->shutdown();
        $afterShutdown = $renewer->expire();

        $t->same(false, $idle['refreshed']);
        $t->same(true, $firstActive['refreshed']);
        $t->same(true, $secondActive['refreshed']);
        $t->same(false, $afterShutdown['refreshed']);
        $t->same(2, $reads);
        $t->same(3, $renewer->expirySignals());
        $t->same(false, $renewer->isArmedForNextExpiry());
        $t->same([
            'expiry-no-active-upload',
            'expiry-rearmed',
            'upload-started',
            'expiry-refresh-started',
            'expiry-refresh-ok',
            'expiry-rearmed',
            'expiry-refresh-started',
            'expiry-refresh-ok',
            'expiry-rearmed',
            'shutdown',
            'expiry-ignored-after-shutdown',
        ], $renewer->events());
    },
    'onedrive token renewer shutdown suppresses later refresh attempts' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $renewer->startUpload();
        $renewer->shutdown();

        $result = $renewer->expire();
        $renewer->startUpload();

        $t->same(false, $result['refreshed']);
        $t->same(0, $reads);
        $t->same(1, $renewer->activeUploads());
        $t->same([
            'upload-started',
            'shutdown',
            'expiry-ignored-after-shutdown',
            'start-ignored-after-shutdown',
        ], $renewer->events());
        $t->same(false, $renewer->isArmedForNextExpiry());
    },
    'wordpress onedrive token renewer preflight exposes refresh lifecycle' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-token-renewer-preflight.php';

        $t->same('onedrive-token-renewer-preflight', $example['source']);
        $t->same(['read-root-metadata'], $example['metadataReads']);
        $t->same(false, $example['idleExpiryRefreshed']);
        $t->same(true, $example['activeExpiryRefreshed']);
        $t->same(false, $example['postUploadExpiryRefreshed']);
        $t->same(false, $example['shutdownExpiryRefreshed']);
        $t->same(0, $example['activeUploadsAfterStop']);
        $t->same(3, $example['expirySignals']);
        $t->same(false, $example['armedForNextExpiry']);
        $t->same(false, $example['secretInputsRead']);
    },
];
