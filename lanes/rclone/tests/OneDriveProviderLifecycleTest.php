<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveProviderLifecycle;
use PortLibs\Rclone\OneDriveTokenRenewer;

return [
    'onedrive provider shutdown stops change notify and token renewal once' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });
        $provider = new OneDriveProviderLifecycle($renewer);

        $provider->startChangeNotify();
        $renewer->startUpload();
        $shutdown = $provider->shutdown();
        $afterShutdownExpiry = $renewer->expire();
        $secondShutdown = $provider->shutdown();

        $t->same([
            'tokenRenewerShutdown' => true,
            'changeNotifyStopped' => true,
            'alreadyShutdown' => false,
        ], $shutdown);
        $t->same([
            'tokenRenewerShutdown' => false,
            'changeNotifyStopped' => false,
            'alreadyShutdown' => true,
        ], $secondShutdown);
        $t->same(false, $afterShutdownExpiry['refreshed']);
        $t->same(0, $reads);
        $t->same(false, $provider->isChangeNotifyRunning());
        $t->same(true, $provider->isShutdown());
        $t->same([
            'change-notify-started',
            'change-notify-stopped',
            'token-renewer-shutdown',
            'provider-shutdown',
            'shutdown-ignored-already-closed',
        ], $provider->events());
        $t->same([
            'upload-started',
            'shutdown',
            'expiry-ignored-after-shutdown',
        ], $renewer->events());
    },
    'onedrive provider shutdown does not start unsupported change notify' => static function (TestRunner $t): void {
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function (): void {
        });
        $provider = new OneDriveProviderLifecycle($renewer, false);

        $provider->startChangeNotify();
        $shutdown = $provider->shutdown();

        $t->same(false, $shutdown['changeNotifyStopped']);
        $t->same(false, $provider->isChangeNotifyRunning());
        $t->same([
            'change-notify-unsupported',
            'token-renewer-shutdown',
            'provider-shutdown',
        ], $provider->events());
    },
    'onedrive provider ignores change notification starts after shutdown' => static function (TestRunner $t): void {
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function (): void {
        });
        $provider = new OneDriveProviderLifecycle($renewer);

        $provider->shutdown();
        $provider->startChangeNotify();
        $renewer->startUpload();

        $t->same(false, $provider->isChangeNotifyRunning());
        $t->same([
            'token-renewer-shutdown',
            'provider-shutdown',
            'change-notify-start-ignored-after-shutdown',
        ], $provider->events());
        $t->same([
            'shutdown',
            'start-ignored-after-shutdown',
        ], $renewer->events());
    },
    'wordpress onedrive provider shutdown preflight closes upload lifecycle' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-provider-shutdown-preflight.php';

        $t->same('onedrive-provider-shutdown-preflight', $example['source']);
        $t->same(true, $example['firstShutdown']['tokenRenewerShutdown']);
        $t->same(true, $example['firstShutdown']['changeNotifyStopped']);
        $t->same(true, $example['secondShutdown']['alreadyShutdown']);
        $t->same(false, $example['expiryAfterShutdownRefreshed']);
        $t->same(false, $example['changeNotifyRunningAfterShutdown']);
        $t->same(0, $example['metadataReads']);
        $t->same(false, $example['secretInputsRead']);
    },
];
