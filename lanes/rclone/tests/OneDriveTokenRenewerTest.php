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
    'onedrive token renewer keeps upload count atomic for concurrent uploads' => static function (TestRunner $t): void {
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
        $t->same(-1, $renewer->activeUploads());

        $underflow = $renewer->expire();
        $t->same(false, $underflow['refreshed']);
        $t->same(-1, $underflow['activeUploads']);
        $t->same(1, $reads);
    },
    'onedrive token renewer treats stop underflow as idle for watchdog refresh' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $renewer->stopUpload();
        $cycle = $renewer->watchdogCycle();

        $t->same(-1, $renewer->activeUploads());
        $t->same(false, $cycle['refreshed']);
        $t->same(true, $cycle['running']);
        $t->same(0, $reads);
        $t->same([
            'upload-stopped',
            'expiry-no-active-upload',
            'expiry-rearmed',
        ], $renewer->events());
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
        $t->same(true, $renewer->isArmedForNextExpiry());
        $t->same([
            'upload-started',
            'expiry-refresh-started',
            'expiry-refresh-error',
            'expiry-rearmed',
        ], $renewer->events());
    },
    'onedrive token renewer brackets successful upload work with start and stop' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $result = $renewer->duringUpload(static function () use ($renewer): string {
            $active = $renewer->expire();

            return $active['refreshed'] ? 'uploaded' : 'not-refreshed';
        });

        $t->same('uploaded', $result);
        $t->same(1, $reads);
        $t->same(0, $renewer->activeUploads());
        $t->same([
            'upload-started',
            'expiry-refresh-started',
            'expiry-refresh-ok',
            'expiry-rearmed',
            'upload-stopped',
        ], $renewer->events());
    },
    'onedrive token renewer stops upload accounting when upload work throws' => static function (TestRunner $t): void {
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function (): void {
        });

        $t->throws(RuntimeException::class, static fn () => $renewer->duringUpload(static function (): void {
            throw new RuntimeException('upload failed');
        }));
        $t->same(0, $renewer->activeUploads());
        $t->same([
            'upload-started',
            'upload-stopped',
        ], $renewer->events());
    },
    'onedrive token renewer still stops bracketed upload when shutdown happens inside work' => static function (TestRunner $t): void {
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function (): void {
        });

        $renewer->duringUpload(static function () use ($renewer): void {
            $renewer->shutdown();
        });
        $renewer->duringUpload(static function (): void {
        });

        $t->same(0, $renewer->activeUploads());
        $t->same([
            'upload-started',
            'shutdown',
            'upload-stopped',
            'start-ignored-after-shutdown',
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
    'onedrive token renewer watchdog is not armed without an expiry source' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        }, false);

        $renewer->startUpload();
        $expiry = $renewer->expire();
        $cycle = $renewer->watchdogCycle();

        $t->same(false, $expiry['refreshed']);
        $t->same(false, $cycle['refreshed']);
        $t->same(false, $cycle['running']);
        $t->same(0, $reads);
        $t->same(1, $renewer->activeUploads());
        $t->same(false, $renewer->isArmedForNextExpiry());
        $t->same([
            'watchdog-not-started-no-expiry-source',
            'upload-started',
            'expiry-ignored-no-expiry-source',
            'watchdog-not-running-no-expiry-source',
        ], $renewer->events());
    },
    'onedrive token renewer watchdog stops when expiry channel closes' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $renewer->startUpload();
        $active = $renewer->watchdogCycle();
        $closed = $renewer->watchdogCycle(false);
        $afterClosed = $renewer->watchdogCycle();

        $t->same(true, $active['refreshed']);
        $t->same(true, $active['running']);
        $t->same(false, $closed['refreshed']);
        $t->same(false, $closed['running']);
        $t->same(false, $afterClosed['refreshed']);
        $t->same(false, $afterClosed['running']);
        $t->same(1, $reads);
        $t->same(false, $renewer->isArmedForNextExpiry());
        $t->same([
            'upload-started',
            'expiry-refresh-started',
            'expiry-refresh-ok',
            'expiry-rearmed',
            'watchdog-expiry-channel-closed',
            'expiry-ignored-after-shutdown',
        ], $renewer->events());
    },
    'onedrive token renewer closed watchdog still allows deferred upload stop' => static function (TestRunner $t): void {
        $reads = 0;
        $renewer = new OneDriveTokenRenewer('onedrive:test', static function () use (&$reads): void {
            ++$reads;
        });

        $closed = $renewer->duringUpload(static function () use ($renewer): array {
            return $renewer->watchdogCycle(false);
        });
        $afterClosed = $renewer->watchdogCycle();
        $renewer->startUpload();

        $t->same(false, $closed['refreshed']);
        $t->same(false, $closed['running']);
        $t->same(1, $closed['activeUploads']);
        $t->same(false, $afterClosed['running']);
        $t->same(0, $reads);
        $t->same(0, $renewer->activeUploads());
        $t->same(true, $renewer->isShutdown());
        $t->same(false, $renewer->isArmedForNextExpiry());
        $t->same([
            'upload-started',
            'watchdog-expiry-channel-closed',
            'upload-stopped',
            'expiry-ignored-after-shutdown',
            'start-ignored-after-shutdown',
        ], $renewer->events());
    },
    'wordpress onedrive token renewer preflight exposes refresh lifecycle' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-token-renewer-preflight.php';

        $t->same('onedrive-token-renewer-preflight', $example['source']);
        $t->same(['read-root-metadata', 'read-root-metadata', 'read-root-metadata'], $example['metadataReads']);
        $t->same(false, $example['idleExpiryRefreshed']);
        $t->same(true, $example['activeExpiryRefreshed']);
        $t->same(false, $example['postUploadExpiryRefreshed']);
        $t->same('wxr-upload-refreshed', $example['bracketedUpload']);
        $t->same('wxr upload failed', $example['bracketedFailure']);
        $t->same(0, $example['activeUploadsAfterBracket']);
        $t->same(0, $example['activeUploadsAfterFailure']);
        $t->same(false, $example['shutdownExpiryRefreshed']);
        $t->same(0, $example['activeUploadsAfterStop']);
        $t->same(4, $example['expirySignals']);
        $t->same(false, $example['armedForNextExpiry']);
        $t->same(true, $example['wasArmedAfterActiveExpiry']);
        $t->same(false, $example['watchdogAfterClosedRunning']);
        $t->same(false, $example['watchdogNoExpirySourceRunning']);
        $t->same(false, $example['watchdogUnderflowRefreshed']);
        $t->same(-1, $example['watchdogUnderflowActiveUploads']);
        $t->same(false, $example['watchdogClosedDuringUploadRunning']);
        $t->same(0, $example['watchdogClosedDuringUploadActiveUploadsAfterStop']);
        $t->same(false, $example['secretInputsRead']);
    },
];
