<?php

declare(strict_types=1);

use PortLibs\Rclone\CountingReader;

$readAll = static function (CountingReader $reader, int $chunkSize = 8): string {
    $bytes = '';
    while (($chunk = $reader->read($chunkSize)) !== '') {
        $bytes .= $chunk;
    }

    return $bytes;
};

return [
    'counting reader tracks upstream bytes returned by reads' => static function (TestRunner $t): void {
        $reader = new CountingReader('abcdef');

        $t->same(0, $reader->bytesRead());
        $t->same('ab', $reader->read(2));
        $t->same(2, $reader->bytesRead());
        $t->same('cdef', $reader->read(99));
        $t->same(6, $reader->bytesRead());
        $t->same('', $reader->read(1));
        $t->same(6, $reader->bytesRead());
        $t->true($reader->eof());
    },
    'counting reader counts actual short reads from the underlying reader' => static function (TestRunner $t): void {
        $source = new class {
            private string $bytes = 'abcdef';
            private int $offset = 0;

            public function read(int $length): string
            {
                if ($length <= 0 || $this->offset >= strlen($this->bytes)) {
                    return '';
                }

                $chunk = substr($this->bytes, $this->offset, min(2, $length));
                $this->offset += strlen($chunk);

                return $chunk;
            }
        };
        $reader = new CountingReader($source);

        $t->same('ab', $reader->read(10));
        $t->same(2, $reader->bytesRead());
        $t->same('cd', $reader->read(10));
        $t->same(4, $reader->bytesRead());
    },
    'counting reader preserves count when the underlying reader fails before returning bytes' => static function (TestRunner $t): void {
        $source = new class {
            private bool $failed = false;

            public function read(int $length): string
            {
                if (!$this->failed) {
                    $this->failed = true;

                    return 'ok';
                }

                throw new RuntimeException('provider read failed');
            }
        };
        $reader = new CountingReader($source);

        $t->same('ok', $reader->read(16));
        $t->same(2, $reader->bytesRead());

        try {
            $reader->read(16);
            throw new RuntimeException('Expected provider read failure');
        } catch (RuntimeException $throwable) {
            $t->same('provider read failed', $throwable->getMessage());
        }

        $t->same(2, $reader->bytesRead());
    },
    'counting reader accounts wordpress wxr upload bodies' => static function (TestRunner $t) use ($readAll): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $wxr = $tree['exports/site.wxr'];
        $reader = new CountingReader($wxr);

        $t->same('<rss ', $reader->read(5));
        $t->same(5, $reader->bytesRead());
        $reader = new CountingReader($wxr);

        $t->same($wxr, $readAll($reader, 7));
        $t->same(strlen($wxr), $reader->bytesRead());
    },
];
