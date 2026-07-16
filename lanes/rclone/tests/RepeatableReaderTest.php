<?php

declare(strict_types=1);

use PortLibs\Rclone\RepeatableReader;

$readFull = static function (RepeatableReader $reader, int $length): string {
    $bytes = '';
    while (strlen($bytes) < $length) {
        $chunk = $reader->read($length - strlen($bytes));
        if ($chunk === '') {
            break;
        }
        $bytes .= $chunk;
    }

    return $bytes;
};

return [
    'repeatable reader replays cached bytes like upstream' => static function (TestRunner $t): void {
        $bytes = 'Testbuffer';
        $reader = new RepeatableReader($bytes);

        $t->same($bytes, $reader->read(100));
        $t->same('', $reader->read(100));
        $t->same(10, $reader->cacheLength());

        $t->same(0, $reader->seek(0, SEEK_SET));
        $t->same($bytes, $reader->read(10));

        $reader = new RepeatableReader($bytes);
        $t->same('Testb', $reader->read(5));
        $t->same('uffer', $reader->read(5));
        $t->same(10, $reader->tell());
    },
    'repeatable reader honors upstream seek cache boundaries' => static function (TestRunner $t) use ($readFull): void {
        $reader = new RepeatableReader('Testbuffer');

        $t->throws(RuntimeException::class, static fn () => $reader->seek(5, SEEK_CUR));
        $t->same(0, $reader->tell());
        $t->throws(RuntimeException::class, static fn () => $reader->seek(-1, SEEK_CUR));
        $t->throws(InvalidArgumentException::class, static fn () => $reader->seek(0, 3));

        $t->same('Testb', $reader->read(5));
        $t->same(2, $reader->seek(-3, SEEK_CUR));
        $t->same(3, $reader->seek(1, SEEK_CUR));
        $t->same(2, $reader->seek(-3, SEEK_END));
        $t->same('stbuf', $readFull($reader, 5));
        $t->same(7, $reader->tell());
    },
    'repeatable reader preserves cached wordpress artifact probes' => static function (TestRunner $t) use ($readFull): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $source = new class($tree['exports/site.wxr']) {
            public int $reads = 0;
            private int $offset = 0;

            public function __construct(private readonly string $bytes)
            {
            }

            public function read(int $length): string
            {
                if ($length <= 0 || $this->offset >= strlen($this->bytes)) {
                    return '';
                }

                $this->reads++;
                $chunk = substr($this->bytes, $this->offset, $length);
                $this->offset += strlen($chunk);

                return $chunk;
            }
        };
        $reader = new RepeatableReader($source);

        $t->same('<rss ', $reader->read(5));
        $t->same(1, $source->reads);
        $t->same(0, $reader->seek(0, SEEK_SET));
        $t->same('<rss ', $reader->read(5));
        $t->same(1, $source->reads);
        $t->same(0, $reader->seek(0, SEEK_SET));
        $t->same($tree['exports/site.wxr'], $readFull($reader, strlen($tree['exports/site.wxr'])));
        $t->same(true, $source->reads > 1);
    },
    'repeatable limit reader stops at upstream bounded byte count' => static function (TestRunner $t) use ($readFull): void {
        $reader = RepeatableReader::limit('Testbuffer-extra-bytes', 10);

        $t->same('Testbuffer', $readFull($reader, 100));
        $t->same('', $reader->read(1));
        $t->same(10, $reader->cacheLength());
        $t->same(0, $reader->seek(0, SEEK_SET));
        $t->same('Testbuffer', $readFull($reader, 100));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(11, SEEK_SET));

        $empty = RepeatableReader::limit('Testbuffer', 0);
        $t->same('', $empty->read(1));
        $t->same(0, $empty->cacheLength());
    },
    'repeatable buffer factories treat supplied bytes as capacity only' => static function (TestRunner $t): void {
        $sized = RepeatableReader::sized('abcdef', 1024);
        $t->same('ab', $sized->read(2));
        $t->same(2, $sized->cacheLength());

        $reader = RepeatableReader::buffer('abcdef', 'already cached?');

        $t->same(0, $reader->cacheLength());
        $t->throws(RuntimeException::class, static fn () => $reader->seek(1, SEEK_SET));
        $t->same('abc', $reader->read(3));
        $t->same(3, $reader->cacheLength());
        $t->same(0, $reader->seek(0, SEEK_SET));
        $t->same('abc', $reader->read(3));
    },
    'repeatable limit buffer bounds wordpress artifact preflight streams' => static function (TestRunner $t) use ($readFull): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $artifact = $tree['exports/site.wxr'];
        $reader = RepeatableReader::limitBuffer($artifact . 'next-archive-member', str_repeat("\0", 64), strlen($artifact));

        $t->same('<rss ', $reader->read(5));
        $t->same(0, $reader->seek(0, SEEK_SET));
        $t->same($artifact, $readFull($reader, strlen($artifact) + 100));
        $t->same(strlen($artifact), $reader->cacheLength());
        $t->same('', $reader->read(1));
    },
];
