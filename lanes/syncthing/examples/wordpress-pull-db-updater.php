<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FolderIndexState;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-pull-db-updater-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $bytes = str_repeat('remote WordPress media after pull ', 2600);
    $blocks = (new BlockList())->fromBytes($bytes);
    $remote = new FileInfo(
        name: 'wp-content/uploads/2026/05/finalized-db-update.jpg',
        modifiedS: 1700003100,
        version: VersionVector::fromCounters([202 => 9]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        sequence: 91,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $state = new FolderIndexState();
    $fsyncs = [];
    $updated = [];
    $received = [];
    $remoteChanges = [];
    $updater = new PullDbUpdater(
        updateLocalsFromPulling: static function (array $files) use ($state, &$updated): ?Throwable {
            $state->update('local', $files);
            $updated[] = array_map(static fn (FileInfo $file): string => $file->name, $files);

            return null;
        },
        syncDirectory: static function (string $dir) use (&$fsyncs): ?Throwable {
            $fsyncs[] = $dir;

            return null;
        },
        receivedFile: static function (string $name, bool $deleted) use (&$received): void {
            $received[] = ['name' => $name, 'deleted' => $deleted];
        },
        remoteChangeDetected: static function (array $event) use (&$remoteChanges): void {
            $remoteChanges[] = [
                'action' => $event['action'],
                'type' => $event['type'],
                'path' => $event['path'],
            ];
        },
        folderId: 'wordpress-media',
        folderLabel: 'WordPress Media',
    );

    $assembler = new PullTemporaryFile($remote, $root);
    $assembler->writeBlock($blocks[0], $bytes, source: 'playgroundPeer');
    $result = $assembler->finalize();
    if ($result->finalized) {
        $updater->append($remote, $result->dbUpdateType);
    }
    $changed = $updater->close();

    echo json_encode([
        'media' => $remote->name,
        'finalized' => $result->toArray(),
        'changedJobs' => $changed,
        'updateLocalsBatches' => $updated,
        'fsyncedDirectories' => $fsyncs,
        'remoteChangeDetected' => $remoteChanges,
        'receivedFiles' => $received,
        'localIndexSequenceReset' => $state->deviceFile('local', $remote->name)?->sequence === 0,
        'finalSha256' => hash_file('sha256', $assembler->finalPath()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }
}
