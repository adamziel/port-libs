<?php

declare(strict_types=1);

use PortLibs\Rclone\GzipReader;

$readAll = static function (GzipReader $reader, int $chunkSize = 11): string {
    $bytes = '';
    while (($chunk = $reader->read($chunkSize)) !== '') {
        $bytes .= $chunk;
    }

    return $bytes;
};

return [
    'gzip reader maps upstream decompression and underlying close behavior' => static function (TestRunner $t) use ($readAll): void {
        $payload = str_repeat('WordPress export payload ', 40);
        $source = new class(gzencode($payload)) {
            public bool $closed = false;
            private int $offset = 0;

            public function __construct(private readonly string $bytes)
            {
            }

            public function read(int $length): string
            {
                $chunk = substr($this->bytes, $this->offset, min(7, $length));
                $this->offset += strlen($chunk);

                return $chunk;
            }

            public function close(): void
            {
                $this->closed = true;
            }
        };

        $reader = new GzipReader($source);

        $t->same($payload, $readAll($reader));
        $t->same(false, $source->closed);
        $reader->close();
        $t->same(true, $source->closed);
    },
    'gzip reader returns upstream close errors from the underlying provider body' => static function (TestRunner $t) use ($readAll): void {
        $source = new class(gzencode('compressed artifact')) {
            public bool $closeCalled = false;
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
                $this->closeCalled = true;
                throw new RuntimeException('provider close failed');
            }
        };
        $reader = new GzipReader($source);

        $t->same('compressed artifact', $readAll($reader));

        try {
            $reader->close();
            throw new RuntimeException('Expected underlying close error');
        } catch (RuntimeException $throwable) {
            $t->same('provider close failed', $throwable->getMessage());
        }
        $t->same(true, $source->closeCalled);
    },
    'gzip reader reports upstream-shaped invalid gzip header errors' => static function (TestRunner $t): void {
        try {
            new GzipReader('not gzip data');
            throw new RuntimeException('Expected invalid gzip error');
        } catch (RuntimeException $throwable) {
            $t->same(GzipReader::ERR_INVALID_GZIP, $throwable->getMessage());
        }
    },
    'gzip reader restores compressed wordpress export bodies and closes provider streams' => static function (TestRunner $t) use ($readAll): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $wxr = $tree['exports/site.wxr'];
        $source = new class(gzencode($wxr)) {
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

        $reader = new GzipReader($source);
        $restored = $readAll($reader, 5);
        $reader->close();

        $t->same($wxr, $restored);
        $t->same('<rss ', substr($restored, 0, 5));
        $t->same(true, $source->closed);
    },
];
