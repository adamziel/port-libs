<?php

declare(strict_types=1);

use PortLibs\Syncthing\Availability;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DeviceActivity;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\Response;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream model download progress sharing guard and event summary' => static function (TestRunner $t): void {
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a'],
        ]);
        $version = VersionVector::fromCounters([101 => 1]);
        $update = new FileDownloadProgressUpdate(
            updateType: FileDownloadProgressUpdate::TYPE_APPEND,
            name: 'wp-content/uploads/2026/hero.jpg',
            version: $version,
            blockIndexes: [0, 2],
            blockSize: 4096,
        );

        $t->same(null, $tracker->receiveDownloadProgress('peer-b', new DownloadProgress('wordpress-media', [$update])));
        $t->same(null, $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('private-folder', [$update])));
        $t->same([], $tracker->remoteDownloadProgressEvents());
        $t->same([], $tracker->remoteBlockCounts('peer-a', 'wordpress-media'));

        $event = $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [$update]));
        $t->same([
            'device' => 'peer-a',
            'folder' => 'wordpress-media',
            'state' => ['wp-content/uploads/2026/hero.jpg' => 2],
        ], $event);
        $t->same([$event], $tracker->remoteDownloadProgressEvents());
        $t->same(8192, $tracker->bytesDownloaded('peer-a', 'wordpress-media'));
    },
    'maps temporary block availability and fromTemporary request planning' => static function (TestRunner $t): void {
        $version = VersionVector::fromCounters([101 => 1]);
        $file = remoteProgressFile('wp-content/uploads/2026/hero.jpg', $version, 4);
        $block = $file->blocks[2];
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a', 'peer-b'],
        ]);

        $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: $file->name,
                version: $version,
                blockIndexes: [2],
                blockSize: $file->blockSize(),
            ),
        ]));

        $t->same([
            ['device' => 'peer-a', 'fromTemporary' => true],
        ], array_map(static fn (Availability $availability): array => $availability->toArray(), $tracker->availability('wordpress-media', $file, $block)));

        $plan = $tracker->planBlockRequest('wordpress-media', $file, $block, requestId: 42);
        $t->true($plan !== null);
        $t->same('peer-a', $plan->deviceId);
        $t->true($plan->fromTemporary());
        $t->same(42, $plan->request->id);
        $t->same(2, $plan->request->blockNo);
        $t->same($block->offset, $plan->request->offset);
        $t->same($block->size, $plan->request->size);
        $t->same($block->hashHex, $plan->request->hashHex);

        $decoded = BepWire::decodeRequestMessage(BepWire::encodeRequestMessage($plan->request));
        $t->true($decoded->fromTemporary);
        $t->same(2, $decoded->blockNo);
    },
    'prefers full-file availability before temporary candidates and validates inputs' => static function (TestRunner $t): void {
        $version = VersionVector::fromCounters([101 => 1]);
        $file = remoteProgressFile('wp-content/uploads/2026/hero.jpg', $version, 3);
        $block = $file->blocks[1];
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a', 'peer-b'],
        ]);
        $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: $file->name,
                version: $version,
                blockIndexes: [1],
                blockSize: $file->blockSize(),
            ),
        ]));

        $availability = $tracker->availability('wordpress-media', $file, $block, ['unshared-peer', 'peer-b']);
        $t->same([
            ['device' => 'peer-b', 'fromTemporary' => false],
            ['device' => 'peer-a', 'fromTemporary' => true],
        ], array_map(static fn (Availability $item): array => $item->toArray(), $availability));

        $plan = $tracker->planBlockRequest('wordpress-media', $file, $block, ['peer-b']);
        $t->true($plan !== null);
        $t->same('peer-b', $plan->deviceId);
        $t->true(!$plan->fromTemporary());
        $t->same(null, $tracker->planBlockRequest('unknown-folder', $file, $block));

        $t->throws(InvalidArgumentException::class, static fn () => new RemoteDownloadProgressTracker(['wordpress-media' => ['']]));
        $t->throws(InvalidArgumentException::class, static fn () => $tracker->availability('wordpress-media', $file, $block, ['']));
        $t->throws(InvalidArgumentException::class, static fn () => $tracker->requestForBlock('wordpress-media', $file, new Block(-1, 1, $block->hashHex), true));
    },
    'maps upstream disconnected device availability and temporary state cleanup' => static function (TestRunner $t): void {
        $version = VersionVector::fromCounters([101 => 6]);
        $file = remoteProgressFile('wp-content/uploads/2026/disconnect.jpg', $version, 2);
        $block = $file->blocks[1];
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a', 'peer-b'],
        ]);
        $tracker->connectDevice('peer-a');
        $tracker->connectDevice('peer-b');

        $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: $file->name,
                version: $version,
                blockIndexes: [1],
                blockSize: $file->blockSize(),
            ),
        ]));

        $t->same([
            ['device' => 'peer-a', 'fromTemporary' => true],
        ], array_map(static fn (Availability $item): array => $item->toArray(), $tracker->availability('wordpress-media', $file, $block)));

        $tracker->disconnectDevice('peer-a');

        $t->same(['peer-b'], $tracker->connectedDeviceIds());
        $t->same([], $tracker->remoteBlockCounts('peer-a', 'wordpress-media'));
        $t->same(0, $tracker->bytesDownloaded('peer-a', 'wordpress-media'));
        $t->same([
            ['device' => 'peer-b', 'fromTemporary' => false],
        ], array_map(static fn (Availability $item): array => $item->toArray(), $tracker->availability('wordpress-media', $file, $block, ['peer-a', 'peer-b'])));
        $t->same(null, $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: $file->name,
                version: $version,
                blockIndexes: [0],
                blockSize: $file->blockSize(),
            ),
        ])));
    },
    'fails pullBlock before requesting unavailable connected-device candidates' => static function (TestRunner $t): void {
        $bytes = 'connected-peer-block';
        $block = new Block(0, strlen($bytes), hash('sha256', $bytes));
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/offline.jpg',
            version: VersionVector::fromCounters([101 => 7]),
            size: strlen($bytes),
            rawBlockSize: strlen($bytes),
            blocks: [$block],
        );
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['offline-peer'],
        ]);
        $tracker->connectDevice('offline-peer');
        $tracker->disconnectDevice('offline-peer');
        $called = false;

        $result = $tracker->pullBlock(
            'wordpress-media',
            $file,
            $block,
            ['offline-peer'],
            static function () use (&$called): string {
                $called = true;

                return 'should-not-request';
            },
        );

        $t->true(!$result->successful());
        $t->same('no connected device has the required version of this file', $result->error);
        $t->same([], $result->attempts);
        $t->true(!$called);
    },
    'maps pullBlock retry activity and response hash validation' => static function (TestRunner $t): void {
        $activity = new DeviceActivity();
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a', 'peer-b'],
        ], $activity);
        $bytes = str_repeat('fresh-wordpress-media-block', 3);
        $block = new Block(0, strlen($bytes), hash('sha256', $bytes));
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            version: VersionVector::fromCounters([101 => 2]),
            size: strlen($bytes),
            rawBlockSize: strlen($bytes),
            blocks: [$block],
        );
        $attemptUsage = [];

        $result = $tracker->pullBlock(
            'wordpress-media',
            $file,
            $block,
            ['peer-a', 'peer-b'],
            static function ($plan) use ($activity, $bytes, &$attemptUsage): Response {
                $attemptUsage[] = [
                    'device' => $plan->deviceId,
                    'usage' => $activity->usage($plan->deviceId),
                    'requestId' => $plan->request->id,
                ];

                return $plan->deviceId === 'peer-a'
                    ? new Response($plan->request->id, str_repeat('stale-wordpress-media-block', 3))
                    : new Response($plan->request->id, $bytes);
            },
            requestId: 700,
        );

        $t->true($result->successful());
        $t->same('peer-b', $result->plan?->deviceId);
        $t->same($bytes, $result->data);
        $t->same(['peer-a', 'peer-b'], $result->attemptedDeviceIds());
        $t->same(['hash mismatch'], $result->errors);
        $t->same([
            ['device' => 'peer-a', 'usage' => 1, 'requestId' => 700],
            ['device' => 'peer-b', 'usage' => 1, 'requestId' => 701],
        ], $attemptUsage);
        $t->same(0, $activity->usage('peer-a'));
        $t->same(0, $activity->usage('peer-b'));
    },
    'maps pullBlock final failure after response and callback errors' => static function (TestRunner $t): void {
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a', 'peer-b'],
        ]);
        $bytes = 'wanted-block';
        $block = new Block(0, strlen($bytes), hash('sha256', $bytes));
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/missing.jpg',
            version: VersionVector::fromCounters([101 => 3]),
            size: strlen($bytes),
            rawBlockSize: strlen($bytes),
            blocks: [$block],
        );

        $result = $tracker->pullBlock(
            'wordpress-media',
            $file,
            $block,
            ['peer-a', 'peer-b'],
            static function ($plan): Response {
                if ($plan->deviceId === 'peer-a') {
                    return new Response($plan->request->id, code: Response::CODE_NO_SUCH_FILE);
                }

                throw new RuntimeException('temporary peer disconnected');
            },
        );

        $t->true(!$result->successful());
        $t->same(['peer-a', 'peer-b'], $result->attemptedDeviceIds());
        $t->same([Response::ERROR_NO_SUCH_FILE, 'temporary peer disconnected'], $result->errors);
        $t->same('temporary peer disconnected', $result->error);
    },
    'skips network requests for upstream all-zero pull blocks' => static function (TestRunner $t): void {
        $zeroBytes = str_repeat("\0", BlockList::MIN_BLOCK_SIZE);
        $zeroBlock = new Block(0, BlockList::MIN_BLOCK_SIZE, hash('sha256', $zeroBytes));
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/sparse-video.dat',
            version: VersionVector::fromCounters([101 => 4]),
            size: BlockList::MIN_BLOCK_SIZE,
            rawBlockSize: BlockList::MIN_BLOCK_SIZE,
            blocks: [$zeroBlock],
        );
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a'],
        ]);
        $called = false;

        $result = $tracker->pullBlock(
            'wordpress-media',
            $file,
            $zeroBlock,
            ['peer-a'],
            static function () use (&$called): null {
                $called = true;

                return null;
            },
        );

        $t->true($result->successful());
        $t->true($result->zeroBlock);
        $t->same([], $result->attempts);
        $t->same(BlockList::MIN_BLOCK_SIZE, strlen($result->data));
        $t->same(hash('sha256', $zeroBytes), hash('sha256', $result->data));
        $t->true(!$called);
    },
    'receive-encrypted pullBlock accepts opaque hash-token responses' => static function (TestRunner $t): void {
        $encryptedBytes = str_repeat('ciphertext', 8);
        $opaqueHashToken = str_repeat('ab', 32);
        $block = new Block(0, strlen($encryptedBytes), $opaqueHashToken);
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/private.enc',
            version: VersionVector::fromCounters([101 => 5]),
            size: strlen($encryptedBytes),
            rawBlockSize: strlen($encryptedBytes),
            blocks: [$block],
        );
        $tracker = new RemoteDownloadProgressTracker([
            'private-media' => ['untrusted-peer'],
        ]);

        $plain = $tracker->pullBlock(
            'private-media',
            $file,
            $block,
            ['untrusted-peer'],
            static fn ($plan): string => $encryptedBytes,
        );
        $encrypted = $tracker->pullBlock(
            'private-media',
            $file,
            $block,
            ['untrusted-peer'],
            static fn ($plan): string => $encryptedBytes,
            receiveEncrypted: true,
        );

        $t->true(!$plain->successful());
        $t->same('hash mismatch', $plain->error);
        $t->true($encrypted->successful());
        $t->same($encryptedBytes, $encrypted->data);
        $t->same('untrusted-peer', $encrypted->plan?->deviceId);
    },
];

/**
 * @return list<Block>
 */
function remoteProgressBlocks(int $count): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'remote-progress-block-' . $i));
    }

    return $blocks;
}

function remoteProgressFile(string $name, VersionVector $version, int $blocks): FileInfo
{
    return new FileInfo(
        name: $name,
        version: $version,
        size: $blocks * BlockList::MIN_BLOCK_SIZE,
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        blocks: remoteProgressBlocks($blocks),
    );
}
