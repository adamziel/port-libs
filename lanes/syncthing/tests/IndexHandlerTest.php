<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\DeviceDownloadState;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\Index;
use PortLibs\Syncthing\IndexHandler;
use PortLibs\Syncthing\IndexReceiveResult;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream initial index then delta prev sequence tracking' => static function (TestRunner $t): void {
        $handler = new IndexHandler('wordpress-media');

        $initial = $handler->buildIndexMessages([
            syncthing_index_handler_file('wp-content/uploads/2026/hero.jpg', 1),
            syncthing_index_handler_file('wp-content/uploads/2026/gallery.jpg', 2),
        ]);

        $t->same(1, count($initial));
        $t->true($initial[0] instanceof Index);
        $t->same(2, $initial[0]->lastSequence);
        $t->same(2, $handler->localPrevSequence());
        $t->same(2, $handler->sentPrevSequence());

        $delta = $handler->buildIndexMessages([
            syncthing_index_handler_file('wp-content/uploads/2026/hero-edited.jpg', 5),
        ]);

        $t->same(1, count($delta));
        $t->true($delta[0] instanceof IndexUpdate);
        $t->same(2, $delta[0]->prevSequence);
        $t->same(5, $delta[0]->lastSequence);
        $t->same(5, $handler->localPrevSequence());
        $t->same(5, $handler->sentPrevSequence());
    },
    'keeps upstream add delete rename pair in one full batch' => static function (TestRunner $t): void {
        $handler = new IndexHandler('wordpress-media');
        $files = [];
        for ($i = 1; $i <= 1000; $i++) {
            $files[] = syncthing_index_handler_file('wp-content/uploads/2026/gallery-' . $i . '.jpg', $i);
        }
        $files[] = syncthing_index_handler_file('wp-content/uploads/2026/renamed-old.jpg', 1001, deleted: true);
        $files[] = syncthing_index_handler_file('wp-content/uploads/2026/after-boundary.jpg', 1002);

        $messages = $handler->buildIndexMessages($files);
        $t->same(1, count($messages));
        $t->true($messages[0] instanceof Index);
        $t->same(1001, count($messages[0]->files));
        $t->same(1001, $messages[0]->lastSequence);
        $t->same(1001, $handler->localPrevSequence());
        $t->same(1001, $handler->sentPrevSequence());
        $t->true($messages[0]->files[1000]->isDeleted());

        $next = $handler->buildIndexMessages([
            syncthing_index_handler_file('wp-content/uploads/2026/after-boundary.jpg', 1002),
        ]);
        $t->same(1, count($next));
        $t->true($next[0] instanceof IndexUpdate);
        $t->same(1001, $next[0]->prevSequence);
        $t->same(1002, $next[0]->lastSequence);
    },
    'skips receive encrypted local changes while advancing sequences' => static function (TestRunner $t): void {
        $handler = new IndexHandler(
            folder: 'wordpress-private-media',
            localPrevSequence: 41,
            sentPrevSequence: 41,
            folderIsReceiveEncrypted: true,
        );

        $messages = $handler->buildIndexMessages([
            syncthing_index_handler_file('wp-content/uploads/private/local-note.txt', 42, flags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY),
            syncthing_index_handler_file('wp-content/uploads/private/remote-photo.jpg', 43),
        ]);

        $t->same(1, count($messages));
        $t->true($messages[0] instanceof IndexUpdate);
        $t->same(1, count($messages[0]->files));
        $t->same('wp-content/uploads/private/remote-photo.jpg', $messages[0]->files[0]->name);
        $t->same(41, $messages[0]->prevSequence);
        $t->same(43, $messages[0]->lastSequence);
        $t->same(43, $handler->localPrevSequence());
        $t->same(43, $handler->sentPrevSequence());
    },
    'prepares local receive only and finalized encrypted FileInfo for index' => static function (TestRunner $t): void {
        $changed = syncthing_index_handler_file(
            'wp-content/uploads/private/local-only.jpg',
            7,
            flags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
            version: VersionVector::fromCounters([101 => 77]),
            size: 2048,
        );

        $prepared = IndexHandler::prepareFileInfoForIndex($changed, encryptionTrailerSize: 128);
        $t->same([], $prepared->version->toArray());
        $t->same(1920, $prepared->size);
        $t->same(FileInfo::FLAG_LOCAL_RECEIVE_ONLY, $prepared->localFlags);
        $t->same($changed->name, $prepared->name);

        $t->throws(LengthException::class, static fn () => IndexHandler::prepareFileInfoForIndex($changed, 2049));
    },
    'builds wordpress BEP frames and forget updates for received regular files' => static function (TestRunner $t): void {
        $handler = new IndexHandler('wordpress-media', localPrevSequence: 8, sentPrevSequence: 8);
        $frames = $handler->buildIndexFrames([
            syncthing_index_handler_file('wp-content\\uploads\\2026\\hero.jpg', 9),
        ], directorySeparator: '\\');

        $t->same(1, count($frames));
        $decoded = BepWire::decodeIndexUpdateMessage($frames[0]);
        $t->same('wp-content/uploads/2026/hero.jpg', $decoded->files[0]->name);
        $t->same(8, $decoded->prevSequence);
        $t->same(9, $decoded->lastSequence);

        $updates = IndexHandler::forgetUpdatesForReceivedIndex([
            syncthing_index_handler_file('wp-content/uploads/2026/hero.jpg', 10),
            syncthing_index_handler_file('wp-content/uploads/2026', 11, type: FileInfo::TYPE_DIRECTORY),
            syncthing_index_handler_file('wp-content/uploads/2026/link', 12, type: FileInfo::TYPE_SYMLINK),
            syncthing_index_handler_file('wp-content/uploads/2026/deleted.jpg', 13, deleted: true),
        ]);

        $t->same(1, count($updates));
        $t->same(FileDownloadProgressUpdate::TYPE_FORGET, $updates[0]->updateType);
        $t->same('wp-content/uploads/2026/hero.jpg', $updates[0]->name);
        $t->same([101 => 10], $updates[0]->version->toArray());
    },
    'rejects non increasing database sequence streams' => static function (TestRunner $t): void {
        $handler = new IndexHandler('wordpress-media', localPrevSequence: 5, sentPrevSequence: 5);

        $t->throws(RuntimeException::class, static fn () => $handler->buildIndexMessages([
            syncthing_index_handler_file('wp-content/uploads/2026/stale.jpg', 5),
        ]));
    },
    'maps upstream receive full index reset event and temporary progress forget' => static function (TestRunner $t): void {
        $runner = new SyncthingIndexHandlerReceiveRunner();
        $downloads = new DeviceDownloadState();
        $version = VersionVector::fromCounters([202 => 1]);
        $downloads->update('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content/uploads/2026/hero.jpg',
                version: $version,
                blockIndexes: [0, 1],
                blockSize: 1024,
            ),
        ]);
        $handler = new IndexHandler('wordpress-media', runner: $runner);
        $events = [];

        $result = $handler->receiveIndex(
            files: [
                syncthing_index_handler_file('wp-content/uploads/2026/stale.jpg', 7, version: VersionVector::fromCounters([202 => 7])),
                syncthing_index_handler_file('wp-content/uploads/2026/hero.jpg', 8, version: $version),
            ],
            update: false,
            operation: 'Index',
            lastSequence: 8,
            downloads: $downloads,
            remoteDeviceIdHex: 'aa',
            eventLogger: static function (string $type, array $data) use (&$events): void {
                $events[] = [$type, $data];
            },
        );

        $t->true($result instanceof IndexReceiveResult);
        $t->same(8, $result->sequence);
        $t->same(2, $result->items);
        $t->same([], $result->anomalies);
        $t->same([], $downloads->getBlockCounts('wordpress-media'));
        $t->same(1, $runner->scheduledPulls);
        $t->same('RemoteIndexUpdated', $result->event['type']);
        $t->same(['RemoteIndexUpdated', [
            'device' => 'aa',
            'folder' => 'wordpress-media',
            'items' => 2,
            'sequence' => 8,
            'version' => 8,
        ]], $events[0]);
        $t->same(['wp-content/uploads/2026/hero.jpg', 'wp-content/uploads/2026/stale.jpg'], array_keys($handler->remoteFiles()));
    },
    'maps upstream receive index anomaly logging without rejecting the update' => static function (TestRunner $t): void {
        $handler = new IndexHandler('wordpress-media');
        $handler->receiveIndex(
            files: [syncthing_index_handler_file('wp-content/uploads/2026/base.jpg', 2)],
            update: false,
            operation: 'Index',
            lastSequence: 2,
        );

        $result = $handler->receiveIndex(
            files: [
                syncthing_index_handler_file('wp-content/uploads/2026/old.jpg', 1),
                syncthing_index_handler_file('wp-content/uploads/2026/future.jpg', 4),
                syncthing_index_handler_file('wp-content/uploads/2026/backfill.jpg', 3),
            ],
            update: true,
            operation: 'Index update',
            prevSequence: 9,
            lastSequence: 3,
        );

        $descriptions = array_map(static fn (array $event): string => $event['description'], $result->anomalies);
        $t->true(in_array('index update with unexpected sequence', $descriptions, true));
        $t->true(in_array('file with sequence before prevSequence', $descriptions, true));
        $t->true(in_array('file with sequence after lastSequence', $descriptions, true));
        $t->true(in_array('index update with non-increasing sequence', $descriptions, true));
        $t->same(3, $result->sequence);
        $t->same('wp-content/uploads/2026/future.jpg', $handler->remoteFile('wp-content/uploads/2026/future.jpg')?->name);
        $t->same(2, count($handler->receiveEvents()));
    },
    'rejects duplicate remote sequence numbers after scheduling a pull' => static function (TestRunner $t): void {
        $runner = new SyncthingIndexHandlerReceiveRunner();
        $handler = new IndexHandler('wordpress-media', runner: $runner);

        $t->throws(RuntimeException::class, static fn () => $handler->receiveIndex(
            files: [
                syncthing_index_handler_file('wp-content/uploads/2026/a.jpg', 3),
                syncthing_index_handler_file('wp-content/uploads/2026/b.jpg', 3),
            ],
            update: true,
            operation: 'Index update',
        ));
        $t->same(1, $runner->scheduledPulls);
        $t->same([], $handler->remoteFiles());
        $t->same('index update with non-increasing sequence', $handler->sequenceAnomalies()[0]['description']);
    },
];

function syncthing_index_handler_file(
    string $name,
    int $sequence,
    bool $deleted = false,
    int $flags = 0,
    ?VersionVector $version = null,
    int $type = FileInfo::TYPE_FILE,
    int $size = 1,
): FileInfo {
    return new FileInfo(
        name: $name,
        modifiedS: 1_700_003_000 + $sequence,
        version: $version ?? VersionVector::fromCounters([101 => $sequence]),
        deleted: $deleted,
        localFlags: $flags,
        size: $deleted || $type !== FileInfo::TYPE_FILE ? 0 : $size,
        type: $type,
        permissions: 0644,
        rawBlockSize: $deleted || $type !== FileInfo::TYPE_FILE ? 0 : $size,
        sequence: $sequence,
        modifiedBy: 101,
    );
}

final class SyncthingIndexHandlerReceiveRunner
{
    public int $scheduledPulls = 0;

    public function schedulePull(): void
    {
        $this->scheduledPulls++;
    }
}
