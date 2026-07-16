<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DeviceDownloadState;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream device download append forget replace and byte counting semantics' => static function (TestRunner $t): void {
        $state = new DeviceDownloadState();
        $v1 = VersionVector::fromCounters([101 => 1]);
        $v2 = VersionVector::fromCounters([202 => 1]);

        $state->update('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'hero.jpg',
                version: $v1,
                blockIndexes: [0, 1, 2],
                blockSize: 4096,
            ),
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'hero.jpg',
                version: $v1,
                blockIndexes: [3, 4],
                blockSize: 4096,
            ),
        ]);

        $t->true($state->has('wordpress-media', 'hero.jpg', $v1, 0));
        $t->true($state->has('wordpress-media', 'hero.jpg', $v1, 4));
        $t->true(!$state->has('wordpress-media', 'hero.jpg', $v2, 4));
        $t->same(['hero.jpg' => 5], $state->getBlockCounts('wordpress-media'));
        $t->same(5 * 4096, $state->bytesDownloaded('wordpress-media'));

        $state->update('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_FORGET,
                name: 'hero.jpg',
                version: $v2,
            ),
        ]);
        $t->same(['hero.jpg' => 5], $state->getBlockCounts('wordpress-media'));

        $state->update('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'hero.jpg',
                version: $v2,
                blockIndexes: [10, 11],
            ),
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_FORGET,
                name: 'old-hero.jpg',
                version: $v1,
            ),
        ]);

        $t->true(!$state->has('wordpress-media', 'hero.jpg', $v1, 1));
        $t->true($state->has('wordpress-media', 'hero.jpg', $v2, 10));
        $t->same(['hero.jpg' => 2], $state->getBlockCounts('wordpress-media'));
        $t->same(2 * BlockList::MIN_BLOCK_SIZE, $state->bytesDownloaded('wordpress-media'));

        $state->update('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_FORGET,
                name: 'hero.jpg',
                version: $v2,
            ),
        ]);
        $t->same([], $state->getBlockCounts('wordpress-media'));
        $t->same(0, $state->bytesDownloaded('wordpress-media'));
    },
];
