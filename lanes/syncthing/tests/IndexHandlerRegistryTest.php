<?php

declare(strict_types=1);

use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\DeviceDownloadState;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\FolderIndexState;
use PortLibs\Syncthing\IndexHandler;
use PortLibs\Syncthing\IndexHandlerRegistry;
use PortLibs\Syncthing\IndexHandlerStartInfo;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream cluster config device info start sequence decisions' => static function (TestRunner $t): void {
        $folder = new Folder(
            id: 'wordpress-media',
            devices: [
                new Device(idHex: 'aa', name: 'remote', indexId: 900, maxSequence: 33),
                new Device(idHex: 'bb', name: 'local', indexId: 77, maxSequence: 12),
            ],
        );
        $info = IndexHandlerStartInfo::fromClusterFolder($folder, localDeviceIdHex: 'bb', remoteDeviceIdHex: 'aa');

        $t->same(12, $info->localStartSequence(localIndexId: 77, localCurrentSequence: 20));
        $t->same(0, $info->localStartSequence(localIndexId: 77, localCurrentSequence: 11));
        $t->same(0, $info->localStartSequence(localIndexId: 88, localCurrentSequence: 20));
        $t->same(IndexHandlerStartInfo::REMOTE_INDEX_KEEP, $info->remoteIndexAction(900));
        $t->same(IndexHandlerStartInfo::REMOTE_INDEX_DROP_AND_STORE, $info->remoteIndexAction(901));
        $t->same(
            IndexHandlerStartInfo::REMOTE_INDEX_DROP,
            (new IndexHandlerStartInfo($info->local, new Device(idHex: 'aa', indexId: 0)))->remoteIndexAction(900),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => IndexHandlerStartInfo::fromClusterFolder($folder, localDeviceIdHex: 'cc', remoteDeviceIdHex: 'aa'),
        );
    },
    'stores pending add index info until shared folder starts' => static function (TestRunner $t): void {
        $runner = new SyncthingIndexHandlerRegistryRunner();
        $registry = new IndexHandlerRegistry('aa', localIndexId: 77, localCurrentSequence: 20);
        $info = syncthing_index_handler_start_info(localMaxSequence: 12);

        $t->same(null, $registry->addIndexInfo('wordpress-media', $info));
        $t->same(['wordpress-media'], $registry->pendingFolders());
        $t->same([], $registry->handlerFolders());

        $handler = $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $runner);

        $t->true($handler instanceof IndexHandler);
        $t->same(['wordpress-media'], $registry->handlerFolders());
        $t->same(['wordpress-media'], $registry->runningFolders());
        $t->same([], $registry->pendingFolders());
        $t->same(12, $handler->localPrevSequence());
        $t->same(12, $handler->sentPrevSequence());
        $t->same(1, $runner->scheduledPulls);
    },
    'pauses and resumes registered folder state without replacing handler' => static function (TestRunner $t): void {
        $firstRunner = new SyncthingIndexHandlerRegistryRunner();
        $registry = new IndexHandlerRegistry('aa', localIndexId: 77, localCurrentSequence: 20);
        $registry->addIndexInfo('wordpress-media', syncthing_index_handler_start_info(localMaxSequence: 9));
        $handler = $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $firstRunner);

        $paused = $registry->registerFolderState(syncthing_index_handler_registry_folder(
            'wordpress-media',
            stopReason: Folder::STOP_REASON_PAUSED,
        ));

        $t->same($handler, $paused);
        $t->true($handler->isPaused());
        $t->same([], $registry->runningFolders());
        $t->same(['wordpress-media'], $registry->handlerFolders());
        $t->same([], $registry->registeredFolders());

        $secondRunner = new SyncthingIndexHandlerRegistryRunner();
        $resumed = $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $secondRunner);

        $t->same($handler, $resumed);
        $t->true(!$handler->isPaused());
        $t->same($secondRunner, $handler->runner());
        $t->same(1, $firstRunner->scheduledPulls);
        $t->same(0, $secondRunner->scheduledPulls);
    },
    'replaces a running handler when new cluster index info arrives' => static function (TestRunner $t): void {
        $runner = new SyncthingIndexHandlerRegistryRunner();
        $registry = new IndexHandlerRegistry('aa', localIndexId: 77, localCurrentSequence: 50);

        $registry->addIndexInfo('wordpress-media', syncthing_index_handler_start_info(localMaxSequence: 10));
        $first = $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $runner);
        $second = $registry->addIndexInfo('wordpress-media', syncthing_index_handler_start_info(localMaxSequence: 20));

        $t->true($first instanceof IndexHandler);
        $t->true($second instanceof IndexHandler);
        $t->true($second !== $first);
        $t->same(20, $second->localPrevSequence());
        $t->same(20, $second->sentPrevSequence());
        $t->same($second, $registry->handler('wordpress-media'));
        $t->same(2, $runner->scheduledPulls);
    },
    'removes running and pending handlers outside the active share set' => static function (TestRunner $t): void {
        $runner = new SyncthingIndexHandlerRegistryRunner();
        $registry = new IndexHandlerRegistry('aa', localIndexId: 77, localCurrentSequence: 50);

        $registry->addIndexInfo('wordpress-media', syncthing_index_handler_start_info(localMaxSequence: 10));
        $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $runner);
        $registry->addIndexInfo('wordpress-private', syncthing_index_handler_start_info(localMaxSequence: 11));
        $registry->addIndexInfo('wordpress-themes', syncthing_index_handler_start_info(localMaxSequence: 12));
        $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-themes'), $runner);

        $registry->removeAllExcept(['wordpress-media' => 'valid']);

        $t->same(['wordpress-media'], $registry->handlerFolders());
        $t->same(['wordpress-media'], $registry->registeredFolders());
        $t->same([], $registry->pendingFolders());

        $registry->registerFolderState(new Folder(id: 'wordpress-media', devices: []), $runner);

        $t->same([], $registry->handlerFolders());
        $t->same([], $registry->registeredFolders());
        $t->same(null, $registry->handler('wordpress-media'));
    },
    'maps upstream ReceiveIndex missing paused and running folder boundaries' => static function (TestRunner $t): void {
        $events = [];
        $downloads = new DeviceDownloadState();
        $runner = new SyncthingIndexHandlerRegistryRunner();
        $registry = new IndexHandlerRegistry(
            remoteDeviceIdHex: 'aa',
            localIndexId: 77,
            localCurrentSequence: 50,
            downloads: $downloads,
            eventLogger: static function (string $type, array $data) use (&$events): void {
                $events[] = [$type, $data];
            },
        );

        $t->throws(RuntimeException::class, static fn () => $registry->receiveIndex(
            folder: 'wordpress-media',
            files: [],
            update: false,
            operation: 'Index',
        ));

        $registry->addIndexInfo('wordpress-media', syncthing_index_handler_start_info(localMaxSequence: 10));
        $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $runner);
        $registry->registerFolderState(syncthing_index_handler_registry_folder(
            'wordpress-media',
            stopReason: Folder::STOP_REASON_PAUSED,
        ));

        $t->throws(RuntimeException::class, static fn () => $registry->receiveIndex(
            folder: 'wordpress-media',
            files: [],
            update: true,
            operation: 'Index update',
        ));

        $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $runner);
        $version = VersionVector::fromCounters([202 => 4]);
        $downloads->update('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content/uploads/2026/hero.jpg',
                version: $version,
                blockIndexes: [0],
                blockSize: 2048,
            ),
        ]);
        $result = $registry->receiveIndex(
            folder: 'wordpress-media',
            files: [
                new FileInfo(
                    name: 'wp-content/uploads/2026/hero.jpg',
                    version: $version,
                    size: 2048,
                    sequence: 4,
                ),
            ],
            update: false,
            operation: 'Index',
            lastSequence: 4,
        );

        $t->same(4, $result->sequence);
        $t->same([], $downloads->getBlockCounts('wordpress-media'));
        $t->same(2, $runner->scheduledPulls);
        $t->same('RemoteIndexUpdated', $events[0][0]);
        $t->same('aa', $events[0][1]['device']);
        $t->same('wordpress-media', $events[0][1]['folder']);
    },
    'updates attached folder index state from received Index and IndexUpdate batches' => static function (TestRunner $t): void {
        $runner = new SyncthingIndexHandlerRegistryRunner();
        $state = new FolderIndexState(localDeviceId: 'bb');
        $localBase = VersionVector::fromCounters([101 => 1]);
        $remoteHero = VersionVector::fromCounters([101 => 1, 202 => 2]);
        $remotePoster = VersionVector::fromCounters([202 => 3]);

        $state->update('bb', [
            syncthing_index_handler_registry_file('wp-content/uploads/2026/hero.jpg', 1, 1024, $localBase),
        ]);

        $registry = new IndexHandlerRegistry(
            remoteDeviceIdHex: 'aa',
            localIndexId: 77,
            localCurrentSequence: 20,
            folderIndexStates: ['wordpress-media' => $state],
        );
        $registry->addIndexInfo('wordpress-media', syncthing_index_handler_start_info(localMaxSequence: 10));
        $registry->registerFolderState(syncthing_index_handler_registry_folder('wordpress-media'), $runner);

        $registry->receiveIndex(
            folder: 'wordpress-media',
            files: [
                syncthing_index_handler_registry_file('wp-content/uploads/2026/hero.jpg', 51, 2048, $remoteHero),
            ],
            update: false,
            operation: 'Index',
            lastSequence: 51,
        );

        $t->same(['aa'], $state->globalAvailability('wp-content/uploads/2026/hero.jpg'));
        $t->same(['wp-content/uploads/2026/hero.jpg'], syncthing_index_handler_registry_names($state->neededFiles('bb')));

        $registry->receiveIndex(
            folder: 'wordpress-media',
            files: [
                syncthing_index_handler_registry_file('wp-content/uploads/2026/poster.jpg', 52, 4096, $remotePoster),
            ],
            update: true,
            operation: 'Index update',
            prevSequence: 51,
            lastSequence: 52,
        );

        $t->same([
            'wp-content/uploads/2026/hero.jpg',
            'wp-content/uploads/2026/poster.jpg',
        ], syncthing_index_handler_registry_names($state->neededFiles('bb')));

        $registry->receiveIndex(
            folder: 'wordpress-media',
            files: [
                syncthing_index_handler_registry_file('wp-content/uploads/2026/poster.jpg', 53, 4096, $remotePoster),
            ],
            update: false,
            operation: 'Index',
            lastSequence: 53,
        );

        $t->same([], $state->globalAvailability('wp-content/uploads/2026/hero.jpg'));
        $t->same(['aa'], $state->globalAvailability('wp-content/uploads/2026/poster.jpg'));
        $t->same(['wp-content/uploads/2026/poster.jpg'], syncthing_index_handler_registry_names($state->neededFiles('bb')));
        $t->same(4, $runner->scheduledPulls);
    },
];

function syncthing_index_handler_start_info(int $localMaxSequence): IndexHandlerStartInfo
{
    return new IndexHandlerStartInfo(
        local: new Device(idHex: 'bb', name: 'local', indexId: 77, maxSequence: $localMaxSequence),
        remote: new Device(idHex: 'aa', name: 'remote', indexId: 900, maxSequence: 33),
    );
}

function syncthing_index_handler_registry_folder(
    string $folder,
    int $stopReason = Folder::STOP_REASON_RUNNING,
): Folder {
    return new Folder(
        id: $folder,
        type: $folder === 'wordpress-private' ? Folder::TYPE_RECEIVE_ENCRYPTED : Folder::TYPE_SEND_RECEIVE,
        stopReason: $stopReason,
        devices: [new Device(idHex: 'aa', name: 'remote-peer')],
    );
}

function syncthing_index_handler_registry_file(
    string $name,
    int $sequence,
    int $size,
    VersionVector $version,
): FileInfo {
    return new FileInfo(
        name: $name,
        modifiedS: 1_700_005_000 + $sequence,
        modifiedNs: $sequence,
        version: $version,
        size: $size,
        rawBlockSize: $size,
        sequence: $sequence,
    );
}

/**
 * @param list<FileInfo> $files
 *
 * @return list<string>
 */
function syncthing_index_handler_registry_names(array $files): array
{
    return array_map(static fn (FileInfo $file): string => $file->name, $files);
}

final class SyncthingIndexHandlerRegistryRunner
{
    public int $scheduledPulls = 0;

    public function schedulePull(): void
    {
        $this->scheduledPulls++;
    }
}
