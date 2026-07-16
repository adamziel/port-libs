<?php

declare(strict_types=1);

use PortLibs\Syncthing\FolderCompletion;
use PortLibs\Syncthing\FolderCounts;

return [
    'maps upstream aggregate folder completion math' => static function (TestRunner $t): void {
        $empty = FolderCompletion::fromCounts(
            global: new FolderCounts(),
            need: new FolderCounts(),
            remoteState: FolderCompletion::REMOTE_VALID,
        );
        $pausedEmpty = FolderCompletion::fromCounts(
            global: new FolderCounts(),
            need: new FolderCounts(),
            remoteState: FolderCompletion::REMOTE_PAUSED,
        );

        $t->same(100.0, $empty->add($pausedEmpty)->completionPct);

        $complete = FolderCompletion::fromCounts(
            global: new FolderCounts(bytes: 100, files: 1),
            need: new FolderCounts(),
            sequence: 7,
            remoteState: FolderCompletion::REMOTE_VALID,
        );
        $partial = FolderCompletion::fromCounts(
            global: new FolderCounts(bytes: 400, files: 4),
            need: new FolderCounts(bytes: 50, files: 1),
            sequence: 9,
            remoteState: FolderCompletion::REMOTE_VALID,
        );

        $combined = $complete->add($partial);
        $t->same(90.0, $combined->completionPct);
        $t->same(500, $combined->globalBytes);
        $t->same(50, $combined->needBytes);
        $t->same(5, $combined->globalItems);
        $t->same(1, $combined->needItems);
        $t->same(7, $combined->sequence);
    },
    'maps upstream delete-only need completion as ninety-five percent' => static function (TestRunner $t): void {
        $completion = FolderCompletion::fromCounts(
            global: new FolderCounts(),
            need: new FolderCounts(deleted: 1),
            sequence: 42,
            remoteState: FolderCompletion::REMOTE_VALID,
        );

        $t->same(95.0, $completion->completionPct);
        $t->same([
            'completion' => 95.0,
            'globalBytes' => 0,
            'needBytes' => 0,
            'globalItems' => 0,
            'needItems' => 0,
            'needDeletes' => 1,
            'sequence' => 42,
            'remoteState' => 'valid',
        ], $completion->map());
    },
    'subtracts downloaded temporary bytes without underflow for wordpress progress' => static function (TestRunner $t): void {
        $completion = FolderCompletion::fromCounts(
            global: new FolderCounts(bytes: 4096, files: 2, directories: 1),
            need: new FolderCounts(bytes: 2048, files: 1),
            sequence: 9,
            remoteState: FolderCompletion::REMOTE_VALID,
            downloadedBytes: 1024,
        );

        $t->same(75.0, $completion->completionPct);
        $t->same(1024, $completion->needBytes);
        $t->same(3, $completion->globalItems);
        $t->same(1, $completion->needItems);

        $complete = FolderCompletion::fromCounts(
            global: new FolderCounts(bytes: 4096, files: 1),
            need: new FolderCounts(bytes: 2048, files: 1),
            downloadedBytes: 8192,
        );
        $t->same(100.0, $complete->completionPct);

        $t->throws(
            InvalidArgumentException::class,
            static fn () => FolderCompletion::fromCounts(new FolderCounts(), new FolderCounts(), remoteState: 'offline'),
        );
    },
];
