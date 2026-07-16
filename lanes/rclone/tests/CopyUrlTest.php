<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'copyurl uploads explicit destinations and enforces no clobber' => static function (TestRunner $t): void {
        $target = new MemoryProvider();
        $target->put('file1', 'old contents');
        $response = [
            'url' => 'https://example.test/download',
            'status' => 200,
            'headers' => ['Last-Modified' => 'Fri, 22 May 2026 10:00:00 GMT'],
            'body' => "file contents\n",
            'contentLength' => 14,
        ];

        $stats = null;
        $object = (new SyncPlan())->copyUrl($target, 'file1', $response, stats: $stats);

        $t->same('file1', $object->path);
        $t->same("file contents\n", $target->get('file1'));
        $t->same('2026-05-22T10:00:00Z', $target->info('file1')->modTime);
        $t->same('put', $stats['uploadMode']);
        $t->same(14, $stats['contentLength']);

        $blockedStats = null;
        try {
            (new SyncPlan())->copyUrl($target, 'file1', $response + ['body' => 'new'], noClobber: true, stats: $blockedStats);
            throw new RuntimeException('expected no-clobber failure');
        } catch (RuntimeException $throwable) {
            $t->contains('file already exist', $throwable->getMessage());
        }

        $t->same("file contents\n", $target->get('file1'));
        $t->same(true, $blockedStats['skipped']);
    },
    'copyurl resolves filenames from final urls and content disposition headers' => static function (TestRunner $t): void {
        $target = new MemoryProvider();
        $plan = new SyncPlan();

        $urlObject = $plan->copyUrl($target, '', [
            'url' => 'https://example.test/redirect',
            'finalUrl' => 'https://cdn.example.test/downloads/filename.txt?token=abc',
            'body' => 'from url',
        ], autoFilename: true);

        $headerObject = $plan->copyUrl($target, '', [
            'url' => 'https://example.test/header',
            'headers' => ['Content-Disposition' => 'attachment; filename="folder\\headerfilename.txt"'],
            'body' => 'from header',
        ], autoFilename: true, headerFilename: true);

        $t->same('filename.txt', $urlObject->path);
        $t->same('from url', $target->get('filename.txt'));
        $t->same('headerfilename.txt', $headerObject->path);
        $t->same('from header', $target->get('headerfilename.txt'));
    },
    'copyurl reports missing filenames headers and http status failures' => static function (TestRunner $t): void {
        $target = new MemoryProvider();
        $plan = new SyncPlan();

        foreach ([
            ['response' => ['url' => 'https://example.test/', 'body' => ''], 'auto' => true, 'header' => false, 'message' => "file name wasn't found in url"],
            ['response' => ['url' => 'https://example.test/file', 'body' => ''], 'auto' => true, 'header' => true, 'message' => 'filename not found in the Content-Disposition header'],
            ['response' => ['url' => 'https://example.test/file', 'status' => 404, 'statusText' => '404 Not Found', 'body' => 'missing'], 'auto' => false, 'header' => false, 'message' => 'Not Found'],
        ] as $case) {
            try {
                $plan->copyUrl($target, 'file1', $case['response'], $case['auto'], $case['header']);
                throw new RuntimeException('expected copyurl failure');
            } catch (RuntimeException $throwable) {
                $t->contains($case['message'], $throwable->getMessage());
            }
        }

        $t->throws(RuntimeException::class, static fn () => $target->info('file1'));
    },
    'copyurl sends download headers and writer mode copies bytes only on success' => static function (TestRunner $t): void {
        $target = new MemoryProvider();
        $gotHeaders = [];
        $response = [
            'url' => 'https://example.test/export.wxr',
            'body' => '<rss></rss>',
            'headers' => ['Last-Modified' => 'Sat, 23 May 2026 12:34:56 GMT'],
            'onRequest' => static function (array $headers) use (&$gotHeaders): void {
                $gotHeaders = $headers;
            },
        ];

        $stats = null;
        (new SyncPlan())->copyUrl(
            $target,
            'exports/site.wxr',
            $response,
            downloadHeaders: ['X-Custom-Header' => 'test-value'],
            stats: $stats,
        );

        $t->same('test-value', $gotHeaders['X-Custom-Header']);
        $t->same('2026-05-23T12:34:56Z', $target->info('exports/site.wxr')->modTime);
        $t->same(1, $stats['downloadHeadersSent']);

        $writerStats = null;
        $output = (new SyncPlan())->copyUrlToWriter(['url' => 'https://example.test/stdout', 'body' => 'stdout bytes'], stats: $writerStats);
        $t->same('stdout bytes', $output);
        $t->same(true, $writerStats['stdout']);

        $t->throws(RuntimeException::class, static fn () => (new SyncPlan())->copyUrlToWriter([
            'url' => 'https://example.test/missing',
            'status' => 404,
            'statusText' => '404 Not Found',
            'body' => 'not emitted',
        ]));
    },
    'copyurl command resolves stdout auto filename and print filename flags' => static function (TestRunner $t): void {
        $target = new MemoryProvider();
        $plan = new SyncPlan();

        $t->throws(RuntimeException::class, static fn () => $plan->copyUrlCommand($target, ['url' => 'https://example.test/file', 'body' => 'x']));

        $stdout = $plan->copyUrlCommand($target, ['url' => 'https://example.test/file', 'body' => 'stdout body'], '-');
        $t->same('stdout body', $stdout['stdout']);
        $t->same(null, $stdout['object']);

        $command = $plan->copyUrlCommand(
            $target,
            [
                'url' => 'https://example.test/original',
                'finalUrl' => 'https://media.example.test/wp-content/image.jpg',
                'body' => 'image bytes',
            ],
            'remote-media',
            ['autoFilename' => true, 'printFilename' => true],
        );

        $t->same('remote-media/image.jpg', $command['object']->path);
        $t->same('remote-media/image.jpg', $command['printedFilename']);
        $t->same('image bytes', $target->get('remote-media/image.jpg'));
    },
    'copyurl urls csv maps explicit and autogenerated names while aggregating row errors' => static function (TestRunner $t): void {
        $target = new MemoryProvider();
        $plan = new SyncPlan();
        $csv = "https://example.test/a,local/path/a.json\nhttps://example.test/b\nhttps://example.test/c,broken/c.json\n";

        try {
            $plan->copyUrlsCsvCommand(
                $target,
                $csv,
                [
                    'https://example.test/a' => ['url' => 'https://example.test/a', 'body' => 'a'],
                    'https://example.test/b' => ['url' => 'https://example.test/b', 'finalUrl' => 'https://example.test/generated/b.json', 'body' => 'b'],
                    'https://example.test/c' => ['url' => 'https://example.test/c', 'status' => 503, 'statusText' => '503 Service Unavailable', 'body' => 'c'],
                ],
                'imports',
            );
            throw new RuntimeException('expected aggregate csv failure');
        } catch (RuntimeException $throwable) {
            $t->contains('not all URLs copied successfully', $throwable->getMessage());
            $t->contains('Service Unavailable', $throwable->getMessage());
        }

        $t->same('a', $target->get('imports/local/path/a.json'));
        $t->same('b', $target->get('imports/b.json'));
        $t->throws(RuntimeException::class, static fn () => $target->get('imports/broken/c.json'));
    },
    'wordpress copyurl remote media example imports without live http' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-copyurl-remote-media-import.php';

        $t->same('hero image bytes', $example['importedBytes']);
        $t->same('wp-content/uploads/2026/05/hero.jpg', $example['importedPath']);
        $t->same('2026-05-23T13:00:00Z', $example['importedModTime']);
        $t->same('WordPress/6.9 migration', $example['downloadHeaders']['User-Agent']);
        $t->same('wp-content/uploads/2026/05/hero.jpg', $example['printedFilename']);
        $t->same('existing image bytes', $example['noClobberPreservedBytes']);
        $t->contains('file already exist', $example['noClobberError']);
    },
];
