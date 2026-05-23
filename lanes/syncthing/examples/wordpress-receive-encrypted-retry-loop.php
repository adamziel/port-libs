<?php

declare(strict_types=1);

use PortLibs\Syncthing\FolderErrorTracker;
use PortLibs\Syncthing\PullIterationRunner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$logged = [];
$errors = new FolderErrorTracker(
    'wordpress-private-media',
    static function (string $type, array $data) use (&$logged): void {
        $logged[] = [
            'type' => $type,
            'data' => $data,
        ];
    },
);
$runner = new PullIterationRunner($errors);
$secondIterationStartedClean = false;

$result = $runner->run(
    static function (int $try, FolderErrorTracker $errors) use (&$secondIterationStartedClean): int {
        if ($try === 1) {
            $errors->newPullError(
                'wp-content/uploads/private/2026/member-export.bin',
                'writing encrypted file trailer: open failed',
            );

            // Another metadata update landed, so Syncthing makes one more
            // bounded pull iteration where the private media item can retry.
            return 1;
        }

        $secondIterationStartedClean = $errors->tempPullErrors() === [];

        return 0;
    },
);

echo json_encode([
    'folder' => 'wordpress-private-media',
    'pullInSync' => $result->success,
    'secondIterationStartedClean' => $secondIterationStartedClean,
    'iterationSummaries' => $runner->iterationSummaries(),
    'persistentErrors' => $result->errors,
    'folderErrorsEvent' => $result->folderErrorsEvent,
    'loggedEvents' => $logged,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
