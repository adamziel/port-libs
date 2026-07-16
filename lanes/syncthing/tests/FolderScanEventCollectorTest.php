<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanEventCollector;
use PortLibs\Syncthing\FolderScanProgress;

return [
    'collector records folder-scoped scanner progress failures and path errors' => static function (TestRunner $t): void {
        $logged = [];
        $collector = new FolderScanEventCollector(
            'wordpress-media',
            static function (string $type, array $data) use (&$logged): void {
                $logged[] = ['type' => $type, 'data' => $data];
            },
        );

        $collector->recordProgress(new FolderScanProgress('', 8, 14));
        $collector->recordScanError('wp-content/uploads/private-cache', new RuntimeException('permission denied'), 'scan');
        $collector->recordFailure(FileInfoScanner::WALK_FAILURE_EVENT, [
            'description' => FileInfoScanner::WALK_FAILURE_EVENT_DESCRIPTION,
            'sub' => 'wp-content/uploads',
            'error' => 'stale filesystem handle',
        ]);

        $expectedEvents = [
            [
                'type' => 'FolderScanProgress',
                'data' => [
                    'folder' => 'wordpress-media',
                    'current' => 8,
                    'total' => 14,
                    'rate' => 0.0,
                ],
            ],
            [
                'type' => 'Failure',
                'data' => [
                    'folder' => 'wordpress-media',
                    'description' => FileInfoScanner::WALK_FAILURE_EVENT_DESCRIPTION,
                    'sub' => 'wp-content/uploads',
                    'error' => 'stale filesystem handle',
                ],
            ],
        ];

        $t->same($expectedEvents, $collector->events());
        $t->same([$expectedEvents[1]], $collector->failureEvents());
        $t->same($expectedEvents, $logged);
        $t->same([
            [
                'path' => 'wp-content/uploads/private-cache',
                'phase' => 'scan',
                'error' => 'permission denied',
            ],
        ], $collector->scanErrors());
        $t->same([
            'folder' => 'wordpress-media',
            'events' => $expectedEvents,
            'scanErrors' => $collector->scanErrors(),
        ], $collector->toArray());
        $t->throws(InvalidArgumentException::class, static fn () => new FolderScanEventCollector(''));
        $t->throws(InvalidArgumentException::class, static fn () => $collector->recordFailure('Unexpected', []));
    },
    'root walk aborts emit upstream Failure events through the folder collector' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_event_root();
        try {
            $logged = [];
            $collector = new FolderScanEventCollector(
                'wordpress-media',
                static function (string $type, array $data) use (&$logged): void {
                    $logged[] = ['type' => $type, 'data' => $data];
                },
            );
            $scanner = new FileInfoScanner(
                $root,
                directoryLister: static fn (string $path): array => throw new RuntimeException('stale filesystem handle'),
            );

            $t->throws(RuntimeException::class, static fn () => $scanner->walk(failureLogger: $collector->failureLogger()));

            $expected = [[
                'type' => 'Failure',
                'data' => [
                    'folder' => 'wordpress-media',
                    'description' => FileInfoScanner::WALK_FAILURE_EVENT_DESCRIPTION,
                    'sub' => '.',
                    'error' => 'stale filesystem handle',
                ],
            ]];
            $t->same($expected, $collector->events());
            $t->same($expected, $collector->failureEvents());
            $t->same($expected, $logged);

            $cancelled = new FolderScanEventCollector('wordpress-media');
            $cancelledScanner = new FileInfoScanner(
                $root,
                directoryLister: static fn (string $path): array => throw new RuntimeException('context canceled'),
            );

            $t->throws(RuntimeException::class, static fn () => $cancelledScanner->walk(failureLogger: $cancelled->failureLogger()));
            $t->same([], $cancelled->events());
        } finally {
            syncthing_folder_scan_event_rm($root);
        }
    },
];

function syncthing_folder_scan_event_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-scan-events-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder scan event root');
    }

    return $root;
}

function syncthing_folder_scan_event_rm(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        syncthing_folder_scan_event_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
