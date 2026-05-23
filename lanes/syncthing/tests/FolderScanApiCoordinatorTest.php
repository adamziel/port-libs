<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanApiCoordinator;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;

return [
    'scan API accepts selected WordPress folder map with normalized subdirs' => static function (TestRunner $t): void {
        $mediaRoot = syncthing_folder_scan_api_root();
        $contentRoot = syncthing_folder_scan_api_root();
        try {
            syncthing_folder_scan_api_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_api_write($contentRoot, 'wp-content/plugins/local-first/plugin.php', '<?php');

            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore(), ttlSeconds: 60));
            $scheduler->addFolder('wordpress-content', new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), new FolderScanCheckpointStore(), ttlSeconds: 60));

            $response = (new FolderScanApiCoordinator($scheduler))->postDbScan([
                'folders' => [
                    'wordpress-media' => ['\\wp-content\\uploads\\2026\\05\\', 'wp-content/uploads/2026/05'],
                    'wordpress-content' => 'wp-content/plugins/local-first',
                ],
                'hashBlocks' => true,
                'blockSize' => '4',
            ], now: 1000);
            $body = $response->body;

            $t->same(FolderScanApiCoordinator::HTTP_OK, $response->statusCode);
            $t->true($response->successful());
            $t->same('ok', $body['status']);
            $t->same([
                'wordpress-media' => ['wp-content/uploads/2026/05'],
                'wordpress-content' => ['wp-content/plugins/local-first'],
            ], $body['request']['folders']);
            $t->same(['wp-content/uploads/2026/05', 'wp-content/uploads/2026/05/hero.jpg'], $body['result']['folders']['wordpress-media']['completedPaths']);
            $t->same(['wp-content/plugins/local-first', 'wp-content/plugins/local-first/plugin.php'], $body['result']['folders']['wordpress-content']['completedPaths']);
            $t->true(!str_contains(json_encode($body, JSON_UNESCAPED_SLASHES), $mediaRoot));
            $t->true(!str_contains(json_encode($body, JSON_UNESCAPED_SLASHES), $contentRoot));

            $all = (new FolderScanApiCoordinator($scheduler))->postDbScan([], now: 1010);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $all->statusCode);
            $t->same(true, $all->body['request']['allFolders']);
            $t->same(2, $all->body['result']['folderCount']);
        } finally {
            syncthing_folder_scan_api_rm($mediaRoot);
            syncthing_folder_scan_api_rm($contentRoot);
        }
    },
    'scan API returns multi-status for paused folders while scanning neighbors' => static function (TestRunner $t): void {
        $mediaRoot = syncthing_folder_scan_api_root();
        $contentRoot = syncthing_folder_scan_api_root();
        try {
            syncthing_folder_scan_api_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_api_write($contentRoot, 'wp-content/themes/block-theme/theme.json', '{}');

            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore()));
            $scheduler->addFolder('wordpress-content', new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), new FolderScanCheckpointStore()), running: false);

            $response = (new FolderScanApiCoordinator($scheduler))->postDbScan([
                'folders' => ['wordpress-media' => ['wp-content/uploads/2026/05'], 'wordpress-content' => ['wp-content/themes/block-theme']],
                'hashBlocks' => true,
                'blockSize' => 4,
            ], now: 1100);
            $body = $response->body;

            $t->same(FolderScanApiCoordinator::HTTP_MULTI_STATUS, $response->statusCode);
            $t->same('partial', $body['status']);
            $t->same(false, $body['ok']);
            $t->same(1, $body['result']['completedCount']);
            $t->same(1, $body['result']['errorCount']);
            $t->same('complete', $body['result']['folders']['wordpress-media']['state']);
            $t->same('error', $body['result']['folders']['wordpress-content']['state']);
            $t->contains('folder paused', $body['result']['errors']['wordpress-content']);
            $t->throws(RuntimeException::class, static fn () => $scheduler->scanFolder('wordpress-content', now: 1110));
        } finally {
            syncthing_folder_scan_api_rm($mediaRoot);
            syncthing_folder_scan_api_rm($contentRoot);
        }
    },
    'scan API rejects unknown folders and traversal subdirs before scanning' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_api_root();
        try {
            syncthing_folder_scan_api_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $scheduler = new FolderScanScheduler();
            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore());
            $scheduler->addFolder('wordpress-media', $service);
            $api = new FolderScanApiCoordinator($scheduler);

            $missing = $api->postDbScan(['folder' => 'private-media'], now: 1200);
            $t->same(FolderScanApiCoordinator::HTTP_NOT_FOUND, $missing->statusCode);
            $t->same('folder_missing', $missing->body['error']);
            $t->same(['private-media'], $missing->body['details']['folders']);
            $t->same(null, $service->checkpoint(1200));

            $invalid = $api->postDbScan(['folder' => 'wordpress-media', 'sub' => '../wp-config.php'], now: 1200);
            $t->same(FolderScanApiCoordinator::HTTP_BAD_REQUEST, $invalid->statusCode);
            $t->same('invalid_request', $invalid->body['error']);
            $t->contains('must not traverse', $invalid->body['message']);
            $t->same(null, $service->checkpoint(1200));
        } finally {
            syncthing_folder_scan_api_rm($root);
        }
    },
    'scan API redacts absolute paths from scanner status payloads' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_api_root();
        try {
            $scanner = new FileInfoScanner(
                $root,
                directoryLister: static function (string $path): array {
                    throw new RuntimeException('open ' . $path . '/private-media failed');
                },
            );
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', $scanner, new FolderScanCheckpointStore()));

            $response = (new FolderScanApiCoordinator($scheduler))->postDbScan(['folder' => 'wordpress-media'], now: 1300);
            $encoded = json_encode($response->body, JSON_UNESCAPED_SLASHES);

            $t->same(FolderScanApiCoordinator::HTTP_OK, $response->statusCode);
            $t->same(1, $response->body['result']['folders']['wordpress-media']['scanErrorCount']);
            $t->contains('[absolute-path]', $encoded);
            $t->true(!str_contains($encoded, $root));
        } finally {
            syncthing_folder_scan_api_rm($root);
        }
    },
    'scan API accepts upstream next delay and exposes scheduled scan status' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_api_root();
        try {
            syncthing_folder_scan_api_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore(), ttlSeconds: 60);
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $api = new FolderScanApiCoordinator($scheduler);

            $response = $api->postDbScan([
                'folder' => 'wordpress-media',
                'sub' => 'wp-content/uploads/2026/05',
                'hashBlocks' => true,
                'blockSize' => 4,
                'next' => '45',
            ], now: 1400);

            $t->same(FolderScanApiCoordinator::HTTP_OK, $response->statusCode);
            $t->same(45, $response->body['request']['nextSeconds']);
            $t->same(1, $response->body['result']['folders']['wordpress-media']['revision']);
            $t->same(1445, $response->body['scheduledScans']['wordpress-media']['scheduledAt']);
            $t->same(45, $response->body['scheduledScans']['wordpress-media']['remainingSeconds']);
            $t->same(false, $response->body['scheduledScans']['wordpress-media']['due']);
            $t->same(1, $service->checkpoint(1400)?->revision);

            $early = $scheduler->scanDueDelayedFolders(hashBlocks: true, blockSize: 4, now: 1444);
            $t->same([], $early->snapshots());
            $t->same(1, $service->checkpoint(1444)?->revision);

            $due = $scheduler->scanDueDelayedFolders(hashBlocks: true, blockSize: 4, now: 1445);
            $t->same(2, $due->snapshot('wordpress-media')?->revision);
            $t->same(null, $scheduler->scheduledScanStatus('wordpress-media', 1445));

            $invalidNext = $api->postDbScan([
                'folder' => 'wordpress-media',
                'next' => 'later',
            ], now: 1450);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $invalidNext->statusCode);
            $t->same(null, $invalidNext->body['request']['nextSeconds']);
            $t->same([], $invalidNext->body['scheduledScans']);
        } finally {
            syncthing_folder_scan_api_rm($root);
        }
    },
];

function syncthing_folder_scan_api_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-scan-api-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder scan API root');
    }

    return $root;
}

function syncthing_folder_scan_api_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan API test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan API test file');
    }
}

function syncthing_folder_scan_api_rm(string $path): void
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
        syncthing_folder_scan_api_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
