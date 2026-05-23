<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FolderErrorTracker;
use PortLibs\Syncthing\PullIterationRunner;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\PullFinisher;
use PortLibs\Syncthing\PullJobQueue;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

return [
    'promotes final iteration tempPullErrors into sorted FolderErrors event' => static function (TestRunner $t): void {
        $logged = [];
        $tracker = new FolderErrorTracker(
            'wordpress-media',
            static function (string $type, array $data) use (&$logged): void {
                $logged[] = [$type, $data];
            },
        );

        $tracker->startPull();
        $tracker->addScanError('wp-content/uploads/2026/.stignore', 'scan: permission denied');
        $tracker->startPullerIteration();
        $tracker->newPullError('wp-content/uploads/2026/old-transient.jpg', 'no connected device has the required version');
        $tracker->startPullerIteration();
        $tracker->newPullError('wp-content/uploads/2026/hero.jpg', 'finishing: peer response hash mismatch');
        $tracker->newPullError('wp-content/uploads/2026/hero.jpg', 'later close error');
        $tracker->newPullError('wp-content/uploads/2026/canceled.jpg', 'context canceled');

        $result = $tracker->completePull(changed: 0);

        $t->true(!$result->success);
        $t->same(1, $result->promotedPullErrors);
        $t->same([], $tracker->tempPullErrors());
        $t->same([
            [
                'path' => 'wp-content/uploads/2026/hero.jpg',
                'error' => 'syncing: finishing: peer response hash mismatch',
            ],
        ], $tracker->pullErrors());
        $t->same([
            [
                'path' => 'wp-content/uploads/2026/.stignore',
                'error' => 'scan: permission denied',
            ],
            [
                'path' => 'wp-content/uploads/2026/hero.jpg',
                'error' => 'syncing: finishing: peer response hash mismatch',
            ],
        ], $result->errors);
        $t->same('FolderErrors', $result->folderErrorsEvent['type'] ?? null);
        $t->same('wordpress-media', $result->folderErrorsEvent['data']['folder'] ?? null);
        $t->same([[$result->folderErrorsEvent['type'], $result->folderErrorsEvent['data']]], $logged);
        $t->same([$result->folderErrorsEvent], $tracker->folderErrorsEvents());
    },
    'pull success requires zero changes and no promoted pull errors' => static function (TestRunner $t): void {
        $tracker = new FolderErrorTracker('wordpress-media');

        $tracker->startPull();
        $tracker->startPullerIteration();
        $changed = $tracker->completePull(changed: 2);
        $t->true(!$changed->success);
        $t->same(null, $changed->folderErrorsEvent);
        $t->same([], $tracker->pullErrors());

        $tracker->startPullerIteration();
        $clean = $tracker->completePull(changed: 0);
        $t->true($clean->success);
        $t->same([], $clean->errors);

        $tracker->startPullerIteration();
        $tracker->newPullError('wp-content/uploads/2026/failed.jpg', new RuntimeException('temporary file close failed'));
        $failed = $tracker->completePull(changed: 0);
        $t->true(!$failed->success);
        $t->same(1, count($tracker->pullErrors()));

        $tracker->startPull();
        $t->same([], $tracker->pullErrors());
    },
    'folder errors combine and clear scan-error subtrees like upstream Errors' => static function (TestRunner $t): void {
        $tracker = new FolderErrorTracker('wordpress-media');
        $tracker->addScanError('wp-content/cache/review/a.tmp', 'scan: denied');
        $tracker->addScanError('wp-content/uploads/2026/a.jpg', 'scan: stale stat');
        $tracker->startPull();
        $tracker->startPullerIteration();
        $tracker->newPullError('wp-content/uploads/2026/b.jpg', 'sync source unavailable');
        $tracker->completePull(changed: 0);

        $t->same([
            ['path' => 'wp-content/cache/review/a.tmp', 'error' => 'scan: denied'],
            ['path' => 'wp-content/uploads/2026/a.jpg', 'error' => 'scan: stale stat'],
            ['path' => 'wp-content/uploads/2026/b.jpg', 'error' => 'syncing: sync source unavailable'],
        ], $tracker->errors());

        $tracker->clearScanErrors(['wp-content/cache']);
        $t->same([
            ['path' => 'wp-content/uploads/2026/a.jpg', 'error' => 'scan: stale stat'],
            ['path' => 'wp-content/uploads/2026/b.jpg', 'error' => 'syncing: sync source unavailable'],
        ], $tracker->errors());

        $tracker->clearScanErrors();
        $t->same([
            ['path' => 'wp-content/uploads/2026/b.jpg', 'error' => 'syncing: sync source unavailable'],
        ], $tracker->errors());
    },
    'finisherRoutine temp errors feed folder error promotion' => static function (TestRunner $t): void {
        $root = syncthing_folder_error_root();
        try {
            $bytes = str_repeat('wordpress failed media ', 9000);
            $file = syncthing_folder_error_file('wp-content/uploads/2026/failed-hero.jpg', $bytes);
            $state = new PullTemporaryFile($file, $root);
            $state->fail('peer response hash mismatch');

            $queue = new PullJobQueue();
            $queue->push($file->name);
            $queue->pop();

            $emitter = new ProgressEmitter();
            $emitter->register(new ActiveDownload('wordpress-media', $file, [], availableUpdated: 1, created: 1));

            $tracker = new FolderErrorTracker('wordpress-media');
            $tracker->startPull();
            $tracker->startPullerIteration();

            $finisher = new PullFinisher(
                $queue,
                $emitter,
                folderId: 'wordpress-media',
                folderErrors: $tracker,
            );
            $finish = $finisher->finish($state);
            $result = $tracker->completePull(changed: 0);

            $t->true($finish->handled);
            $t->same('finishing: peer response hash mismatch', $finish->pullError);
            $t->same([
                [
                    'path' => $file->name,
                    'error' => 'syncing: finishing: peer response hash mismatch',
                ],
            ], $result->errors);
            $t->same('FolderErrors', $result->folderErrorsEvent['type'] ?? null);
            $t->same(0, $queue->progressCount());
            $t->same(0, $emitter->registeredCount());
            $t->true(file_exists($state->tempPath()));
        } finally {
            syncthing_folder_error_rm($root);
        }
    },
    'pull iteration runner clears transient errors before retry success' => static function (TestRunner $t): void {
        $tracker = new FolderErrorTracker('wordpress-private-media');
        $runner = new PullIterationRunner($tracker);
        $sawClearedSecondIteration = false;

        $result = $runner->run(static function (int $try, FolderErrorTracker $errors) use (&$sawClearedSecondIteration): int {
            if ($try === 1) {
                $errors->newPullError(
                    'wp-content/uploads/private/2026/member-retry.bin',
                    'writing encrypted file trailer: open failed',
                );

                return 1;
            }

            $sawClearedSecondIteration = $errors->tempPullErrors() === [];

            return 0;
        });

        $t->true($result->success);
        $t->true($sawClearedSecondIteration);
        $t->same([], $result->errors);
        $t->same(null, $result->folderErrorsEvent);
        $t->same([
            ['try' => 1, 'changed' => 1, 'tempPullErrors' => 1],
            ['try' => 2, 'changed' => 0, 'tempPullErrors' => 0],
        ], $runner->iterationSummaries());
    },
    'pull iteration runner promotes only final iteration errors' => static function (TestRunner $t): void {
        $logged = [];
        $tracker = new FolderErrorTracker(
            'wordpress-private-media',
            static function (string $type, array $data) use (&$logged): void {
                $logged[] = [$type, $data];
            },
        );
        $runner = new PullIterationRunner($tracker);

        $result = $runner->run(static function (int $try, FolderErrorTracker $errors): int {
            if ($try === 1) {
                $errors->newPullError('wp-content/uploads/private/2026/old-failure.bin', 'stale peer hash');

                return 1;
            }

            $errors->newPullError('wp-content/uploads/private/2026/final-failure.bin', 'no connected device has the required version');

            return 0;
        });

        $t->true(!$result->success);
        $t->same(1, $result->promotedPullErrors);
        $t->same([
            [
                'path' => 'wp-content/uploads/private/2026/final-failure.bin',
                'error' => 'syncing: no connected device has the required version',
            ],
        ], $result->errors);
        $t->same('FolderErrors', $result->folderErrorsEvent['type'] ?? null);
        $t->same([[$result->folderErrorsEvent['type'], $result->folderErrorsEvent['data']]], $logged);
        $t->same([
            ['try' => 1, 'changed' => 1, 'tempPullErrors' => 1],
            ['try' => 2, 'changed' => 0, 'tempPullErrors' => 1],
        ], $runner->iterationSummaries());
    },
    'pull iteration runner stops after upstream maximum changed iterations' => static function (TestRunner $t): void {
        $tracker = new FolderErrorTracker('wordpress-media');
        $runner = new PullIterationRunner($tracker);
        $tries = [];

        $result = $runner->run(static function (int $try, FolderErrorTracker $errors) use (&$tries): int {
            $tries[] = $try;

            return 1;
        });

        $t->true(!$result->success);
        $t->same([1, 2, 3], $tries);
        $t->same([], $result->errors);
        $t->same(null, $result->folderErrorsEvent);
        $t->same([
            ['try' => 1, 'changed' => 1, 'tempPullErrors' => 0],
            ['try' => 2, 'changed' => 1, 'tempPullErrors' => 0],
            ['try' => 3, 'changed' => 1, 'tempPullErrors' => 0],
        ], $runner->iterationSummaries());
    },
];

function syncthing_folder_error_file(string $name, string $bytes): FileInfo
{
    $blocks = (new BlockList())->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);

    return new FileInfo(
        name: $name,
        modifiedS: 1_700_004_000,
        version: VersionVector::fromCounters([202 => 88]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        sequence: 88,
        blocks: $blocks,
        modifiedBy: 202,
    );
}

function syncthing_folder_error_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-errors-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder error root');
    }

    return $root;
}

function syncthing_folder_error_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}
