<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\NoLowLevelRetryException;
use PortLibs\Rclone\ReOpenReader;

$readAll = static function (ReOpenReader $reader, int $chunkSize = 1024): string {
    $bytes = '';
    while (!$reader->eof()) {
        $chunk = $reader->read($chunkSize);
        if ($chunk === '') {
            break;
        }
        $bytes .= $chunk;
    }

    return $bytes;
};

return [
    'reopen reader retries transient read failures at upstream offsets' => static function (TestRunner $t) use ($readAll): void {
        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789', [
            'readError' => 'test error',
            'readBreaks' => [2, 1, 3],
        ]);

        $reader = new ReOpenReader($provider, 'potato', 10);

        $t->same('0123456789', $readAll($reader, 20));
        $t->same([
            ['path' => 'potato', 'offset' => 0, 'length' => 10],
            ['path' => 'potato', 'offset' => 2, 'length' => 8],
            ['path' => 'potato', 'offset' => 3, 'length' => 7],
            ['path' => 'potato', 'offset' => 6, 'length' => 4],
        ], $provider->openLog());
    },
    'reopen reader applies upstream range and seek option boundaries' => static function (TestRunner $t) use ($readAll): void {
        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789');

        $range = new ReOpenReader($provider, 'potato', 10, ['rangeStart' => 1, 'rangeEnd' => 7]);
        $t->same('1234567', $readAll($range, 20));

        $seek = new ReOpenReader($provider, 'potato', 10, ['seekOffset' => 2]);
        $t->same('23456789', $readAll($seek, 20));
    },
    'reopen reader maps upstream unknown-size range and seek behavior' => static function (TestRunner $t) use ($readAll): void {
        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789', [
            'unknownSize' => true,
            'readError' => 'test error',
            'readBreaks' => [2, 1],
        ]);

        $reader = new ReOpenReader($provider, 'potato', 10, ['rangeStart' => 1, 'rangeEnd' => -1]);

        $t->same(-1, $provider->info('potato')->size);
        $t->same('123456789', $readAll($reader, 20));
        $t->same([
            ['path' => 'potato', 'offset' => 1, 'length' => null],
            ['path' => 'potato', 'offset' => 3, 'length' => null],
            ['path' => 'potato', 'offset' => 4, 'length' => null],
        ], $provider->openLog());

        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789', ['unknownSize' => true]);
        $reader = new ReOpenReader($provider, 'potato', 10, ['rangeStart' => 1, 'rangeEnd' => -1]);

        $t->same(10, $reader->seek(10, SEEK_SET));
        $t->same('', $reader->read(1));
        $t->true($reader->eof());
        $t->throws(RuntimeException::class, static fn () => $reader->seek(-1, SEEK_END));
    },
    'reopen reader reports open failures and too many retries like upstream' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789', [
            'readError' => 'test error',
            'readBreaks' => [0],
        ]);

        $t->throws(RuntimeException::class, static fn () => new ReOpenReader($provider, 'potato', 10));

        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789', [
            'readError' => 'test error',
            'readBreaks' => [2, 1, 3],
        ]);

        $reader = new ReOpenReader($provider, 'potato', 3);
        $t->same('012345', $reader->read(20));
        $t->throws(RuntimeException::class, static fn () => $reader->read(1));
        $t->throws(RuntimeException::class, static fn () => $reader->close());
    },
    'reopen reader supports readAt seek close and delayed accounting' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789');
        $reader = new ReOpenReader($provider, 'potato', 10);

        $t->same('01234', $reader->readAt(5, 0));
        $t->same('123', $reader->readAt(3, 1));
        $t->same(0, $reader->seek(0, SEEK_CUR));
        $t->same('01234', $reader->read(5));
        $t->same(2, $reader->seek(-3, SEEK_CUR));
        $t->same('23456', $reader->read(5));
        $t->same(7, $reader->seek(-3, SEEK_END));
        $t->same('789', $reader->read(3));
        $t->throws(InvalidArgumentException::class, static fn () => $reader->seek(0, 3));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(-1, SEEK_SET));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(11, SEEK_SET));

        $reader = new ReOpenReader($provider, 'potato', 10);
        $total = 0;
        $reader->setAccounting(static function (int $bytes) use (&$total): void {
            $total += $bytes;
        });
        $reader->delayAccounting(3);

        $t->same('0123456789', $reader->read(20));
        $t->same(0, $total);
        $reader->seek(0, SEEK_SET);
        $t->same('0123456789', $reader->read(20));
        $t->same(0, $total);
        $reader->seek(0, SEEK_SET);
        $t->same('0123456789', $reader->read(20));
        $t->same(10, $total);
        $reader->seek(0, SEEK_SET);
        $t->same('0123456789', $reader->read(20));
        $t->same(20, $total);

        $reader->close();
        $t->throws(RuntimeException::class, static fn () => $reader->read(1));
        $t->throws(RuntimeException::class, static fn () => $reader->close());
    },
    'reopen reader does not retry upstream no low level retry errors' => static function (TestRunner $t): void {
        $error = new NoLowLevelRetryException('provider rejected retryable range request');
        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789', [
            'readError' => $error,
            'readBreaks' => [4],
        ]);

        $reader = new ReOpenReader($provider, 'potato', 10);

        $t->same('0123', $reader->read(20));
        $t->same([
            ['path' => 'potato', 'offset' => 0, 'length' => 10],
        ], $provider->openLog());

        try {
            $reader->read(1);
            throw new RuntimeException('Expected no-low-level-retry read error was not thrown');
        } catch (NoLowLevelRetryException $throwable) {
            $t->same($error, $throwable);
            $t->same('provider rejected retryable range request', $throwable->getMessage());
        }

        $t->same(1, count($provider->openLog()));
    },
    'reopen reader propagates upstream accounting errors without reopening' => static function (TestRunner $t): void {
        $error = new RuntimeException('accounting failed');
        $provider = new MemoryProvider();
        $provider->put('potato', '0123456789');
        $reader = new ReOpenReader($provider, 'potato', 10);
        $reader->setAccounting(static fn (int $bytes): RuntimeException => $error);

        try {
            $reader->read(3);
            throw new RuntimeException('Expected accounting error was not thrown');
        } catch (RuntimeException $throwable) {
            $t->same($error, $throwable);
        }

        $t->same([
            ['path' => 'potato', 'offset' => 0, 'length' => 10],
        ], $provider->openLog());
        $t->same(3, $reader->seek(0, SEEK_CUR));

        $reader->setAccounting(static fn (int $bytes): null => null);
        $t->same('345', $reader->read(3));
    },
    'reopen reader resumes interrupted wordpress backup artifact downloads' => static function (TestRunner $t) use ($readAll): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', $tree['exports/site.wxr'], [
            'readError' => 'temporary shared-hosting stream interruption',
            'readBreaks' => [5, 7],
        ]);

        $reader = new ReOpenReader($provider, 'exports/site.wxr', 10);

        $t->same($tree['exports/site.wxr'], $readAll($reader, 8));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => strlen($tree['exports/site.wxr'])],
            ['path' => 'exports/site.wxr', 'offset' => 5, 'length' => strlen($tree['exports/site.wxr']) - 5],
            ['path' => 'exports/site.wxr', 'offset' => 12, 'length' => strlen($tree['exports/site.wxr']) - 12],
        ], $provider->openLog());
    },
    'reopen reader restores unknown-size wordpress export streams' => static function (TestRunner $t) use ($readAll): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', $tree['exports/site.wxr'], [
            'unknownSize' => true,
            'readError' => 'temporary unknown-length object interruption',
            'readBreaks' => [5, 7],
        ]);

        $reader = new ReOpenReader($provider, 'exports/site.wxr', 10, [
            'rangeStart' => 0,
            'rangeEnd' => -1,
        ]);

        $t->same($tree['exports/site.wxr'], $readAll($reader, 8));
        $t->same(-1, $provider->info('exports/site.wxr')->size);
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => null],
            ['path' => 'exports/site.wxr', 'offset' => 5, 'length' => null],
            ['path' => 'exports/site.wxr', 'offset' => 12, 'length' => null],
        ], $provider->openLog());
    },
];
