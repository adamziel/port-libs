<?php

declare(strict_types=1);

use PortLibs\Rclone\FakeSeeker;
use PortLibs\Rclone\NoCloseReader;
use PortLibs\Rclone\NoSeeker;

return [
    'fake seeker passes through native read seeker objects' => static function (TestRunner $t): void {
        $reader = new class {
            public function read(int $length): string
            {
                return '';
            }

            public function seek(int $offset, int $whence = SEEK_SET): int
            {
                return 0;
            }
        };

        $t->same($reader, FakeSeeker::wrap($reader, 5));
    },
    'fake seeker allows upstream pre-read length seeks then requires start for reads' => static function (TestRunner $t): void {
        $reader = new FakeSeeker('hello', 5);

        $t->same(0, $reader->seek(0, SEEK_CUR));
        $t->same(2, $reader->seek(2, SEEK_SET));
        $t->same(2, $reader->tell());
        $t->same(4, $reader->seek(-1, SEEK_END));

        try {
            $reader->read(16);
            throw new RuntimeException('Expected non-start fake seeker read to fail');
        } catch (RuntimeException $throwable) {
            $t->same(FakeSeeker::ERR_NOT_AT_START, $throwable->getMessage());
        }

        $t->same(0, $reader->seek(-4, SEEK_CUR));
        $t->throws(InvalidArgumentException::class, static fn () => $reader->seek(42, 17));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(-1, SEEK_SET));

        $t->same('hello', $reader->read(16));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(-1, SEEK_END));
    },
    'fake seeker keeps upstream eof read error sticky for later seeks' => static function (TestRunner $t): void {
        $reader = new FakeSeeker('hello', 5);

        $t->same('hello', $reader->read(16));
        $t->same('', $reader->read(16));

        try {
            $reader->read(16);
            throw new RuntimeException('Expected sticky EOF read error');
        } catch (RuntimeException $throwable) {
            $t->same(FakeSeeker::ERR_EOF, $throwable->getMessage());
        }

        try {
            $reader->seek(0, SEEK_SET);
            throw new RuntimeException('Expected sticky EOF seek error');
        } catch (RuntimeException $throwable) {
            $t->same(FakeSeeker::ERR_EOF, $throwable->getMessage());
        }
    },
    'no seeker reads while rejecting upstream seek attempts' => static function (TestRunner $t): void {
        $reader = new NoSeeker('hello');

        $t->same('hell', $reader->read(4));

        try {
            $reader->seek(0, SEEK_CUR);
            throw new RuntimeException('Expected no seeker seek error');
        } catch (RuntimeException $throwable) {
            $t->same(NoSeeker::ERR_CANT_SEEK, $throwable->getMessage());
        }
    },
    'no close reader maps upstream nil and read-only passthrough' => static function (TestRunner $t): void {
        $readOnly = new class {
            public function read(int $length): string
            {
                return '';
            }
        };

        $t->same(null, NoCloseReader::wrap(null));
        $t->same($readOnly, NoCloseReader::wrap($readOnly));
    },
    'no close reader hides close while preserving upstream read errors' => static function (TestRunner $t): void {
        $reader = new class {
            public int $closeCalls = 0;

            public function read(int $length): string
            {
                throw new RuntimeException('read error');
            }

            public function close(): void
            {
                $this->closeCalls++;
                throw new RuntimeException('close should stay hidden');
            }
        };

        $wrapped = NoCloseReader::wrap($reader);

        $t->true($wrapped !== $reader);
        $t->same(false, method_exists($wrapped, 'close'));

        try {
            $wrapped->read(1);
            throw new RuntimeException('Expected read error to pass through');
        } catch (RuntimeException $throwable) {
            $t->same('read error', $throwable->getMessage());
        }

        $t->same(0, $reader->closeCalls);
    },
    'fake seeker supports wordpress import length preflight before streaming' => static function (TestRunner $t): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $wxr = $tree['exports/site.wxr'];
        $reader = new FakeSeeker($wxr, strlen($wxr));

        $t->same(strlen($wxr), $reader->seek(0, SEEK_END));
        $t->same(0, $reader->seek(0, SEEK_SET));
        $t->same('<rss ', $reader->read(5));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(0, SEEK_SET));
    },
    'no close reader protects wordpress upload bodies from request-side close upgrades' => static function (TestRunner $t): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $source = new class($tree['exports/site.wxr']) {
            public bool $closed = false;
            private int $offset = 0;

            public function __construct(private readonly string $bytes)
            {
            }

            public function read(int $length): string
            {
                $chunk = substr($this->bytes, $this->offset, $length);
                $this->offset += strlen($chunk);

                return $chunk;
            }

            public function close(): void
            {
                $this->closed = true;
            }
        };

        $body = NoCloseReader::wrap($source);

        $t->same('<rss ', $body->read(5));
        if (method_exists($body, 'close')) {
            $body->close();
        }

        $t->same(false, $source->closed);
    },
];
