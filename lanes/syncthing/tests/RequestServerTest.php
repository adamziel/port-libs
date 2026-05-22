<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\RequestServingResult;
use PortLibs\Syncthing\Response;

return [
    'serves valid fromTemporary requests from the temporary file first' => static function (TestRunner $t): void {
        $root = syncthing_request_server_root();
        try {
            $name = 'wp-content/uploads/2026/hero.jpg';
            $temporaryBytes = str_repeat('temporary-wordpress-media-block', 4);
            $finalBytes = str_repeat('final-wordpress-media-block', 4);
            syncthing_request_server_write($root, RequestServer::temporaryName($name), $temporaryBytes);
            syncthing_request_server_write($root, $name, $finalBytes);

            $server = new RequestServer('wordpress-media', $root, ['playground-importer']);
            $result = $server->serve('playground-importer', new Request(
                id: 9,
                folder: 'wordpress-media',
                name: $name,
                offset: 0,
                size: strlen($temporaryBytes),
                hashHex: hash('sha256', $temporaryBytes),
                fromTemporary: true,
                blockNo: 0,
            ));

            $t->true($result->successful());
            $t->same(RequestServingResult::SOURCE_TEMPORARY, $result->source);
            $t->same($temporaryBytes, $result->response->data);

            $decoded = BepWire::decodeResponseMessage(BepWire::encodeResponseMessage($result->response));
            $t->same(9, $decoded->id);
            $t->same(Response::CODE_NO_ERROR, $decoded->code);
            $t->same($temporaryBytes, $decoded->data);
        } finally {
            syncthing_request_server_rm($root);
        }
    },
    'falls back to the final file when temporary data does not validate' => static function (TestRunner $t): void {
        $root = syncthing_request_server_root();
        try {
            $name = 'wp-content/uploads/2026/hero.jpg';
            $finalBytes = str_repeat('restored-final-media-block', 5);
            syncthing_request_server_write($root, RequestServer::temporaryName($name), 'stale partial bytes');
            syncthing_request_server_write($root, $name, $finalBytes);

            $server = new RequestServer('wordpress-media', $root, ['playground-importer']);
            $result = $server->serve('playground-importer', new Request(
                id: 10,
                folder: 'wordpress-media',
                name: $name,
                offset: 0,
                size: strlen($finalBytes),
                hashHex: hash('sha256', $finalBytes),
                fromTemporary: true,
                blockNo: 0,
            ));

            $t->true($result->successful());
            $t->same(RequestServingResult::SOURCE_FINAL, $result->source);
            $t->same($finalBytes, $result->response->data);
        } finally {
            syncthing_request_server_rm($root);
        }
    },
    'maps final-file hash mismatch and empty hash short reads' => static function (TestRunner $t): void {
        $root = syncthing_request_server_root();
        try {
            $name = 'wp-content/uploads/2026/short.jpg';
            syncthing_request_server_write($root, $name, 'short-media');
            $server = new RequestServer('wordpress-media', $root, ['peer-a']);

            $mismatch = $server->serve('peer-a', new Request(
                id: 11,
                folder: 'wordpress-media',
                name: $name,
                offset: 0,
                size: 64,
                hashHex: hash('sha256', 'different'),
            ));
            $t->same(Response::CODE_NO_SUCH_FILE, $mismatch->response->code);
            $t->same('hash mismatch', $mismatch->reason);

            $emptyHash = $server->serve('peer-a', new Request(
                id: 12,
                folder: 'wordpress-media',
                name: $name,
                offset: 0,
                size: 64,
            ));
            $t->same(Response::CODE_NO_ERROR, $emptyHash->response->code);
            $t->same('short-media', $emptyHash->response->data);
            $t->same(RequestServingResult::SOURCE_FINAL, $emptyHash->source);
        } finally {
            syncthing_request_server_rm($root);
        }
    },
    'rejects unshared devices internal paths traversal and negative ranges' => static function (TestRunner $t): void {
        $root = syncthing_request_server_root();
        try {
            syncthing_request_server_write($root, 'wp-content/uploads/2026/hero.jpg', 'media');
            $server = new RequestServer('wordpress-media', $root, ['peer-a']);

            $unshared = $server->serve('peer-b', new Request(
                folder: 'wordpress-media',
                name: 'wp-content/uploads/2026/hero.jpg',
                size: 5,
            ));
            $t->same(Response::CODE_GENERIC, $unshared->response->code);
            $t->same('unshared device', $unshared->reason);

            $internal = $server->serve('peer-a', new Request(
                folder: 'wordpress-media',
                name: '.stversions/hero.jpg',
                size: 5,
            ));
            $t->same(Response::CODE_INVALID_FILE, $internal->response->code);

            $invalid = $server->serve('peer-a', new Request(
                folder: 'wordpress-media',
                name: '../wp-config.php',
                size: 5,
            ));
            $t->same(Response::CODE_GENERIC, $invalid->response->code);

            $negative = $server->serve('peer-a', new Request(
                folder: 'wordpress-media',
                name: 'wp-content/uploads/2026/hero.jpg',
                size: -1,
            ));
            $t->same(Response::CODE_INVALID_FILE, $negative->response->code);
        } finally {
            syncthing_request_server_rm($root);
        }
    },
    'uses upstream temporary filename hashing for long basenames' => static function (TestRunner $t): void {
        $short = RequestServer::temporaryName('wp-content/uploads/hero.jpg');
        $t->same('wp-content/uploads/.syncthing.hero.jpg.tmp', $short);

        $longBase = str_repeat('l', 300);
        $long = RequestServer::temporaryName('wp-content/uploads/' . $longBase);
        $t->same('wp-content/uploads/.syncthing.' . hash('sha256', $longBase) . '.tmp', $long);
        $t->true(strlen(basename($long)) <= 160);
    },
];

function syncthing_request_server_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-request-server-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary test root');
    }

    return $root;
}

function syncthing_request_server_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write test file');
    }
}

function syncthing_request_server_rm(string $path): void
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
