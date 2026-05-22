<?php

declare(strict_types=1);

use PortLibs\Rclone\LimitedReadCloser;

return [
    'limited read closer maps upstream limited reader byte count' => static function (TestRunner $t): void {
        $reader = new LimitedReadCloser('abcdef', 4);

        $t->same('abc', $reader->read(3));
        $t->same(1, $reader->remaining());
        $t->same('d', $reader->read(3));
        $t->same(0, $reader->remaining());
        $t->same('', $reader->read(1));
    },
    'limited read closer wrap preserves upstream negative limit passthrough' => static function (TestRunner $t): void {
        $reader = new class {
            public function read(int $length): string
            {
                return 'unlimited';
            }

            public function close(): void
            {
            }
        };

        $t->same($reader, LimitedReadCloser::wrap($reader, -1));
    },
    'limited read closer reports close errors before the limit is consumed' => static function (TestRunner $t): void {
        $reader = new LimitedReadCloser(new class {
            private int $offset = 0;

            public function read(int $length): string
            {
                $chunk = substr('abcdef', $this->offset, $length);
                $this->offset += strlen($chunk);

                return $chunk;
            }

            public function close(): void
            {
                throw new RuntimeException('provider close failed');
            }
        }, 5);

        $t->same('ab', $reader->read(2));

        try {
            $reader->close();
            throw new RuntimeException('Expected close error to propagate');
        } catch (RuntimeException $throwable) {
            $t->same('provider close failed', $throwable->getMessage());
        }
    },
    'limited read closer ignores close errors once limited data is complete' => static function (TestRunner $t): void {
        $state = (object) ['closed' => false];
        $reader = new LimitedReadCloser(new class($state) {
            private int $offset = 0;

            public function __construct(private object $state)
            {
            }

            public function read(int $length): string
            {
                $chunk = substr('abcdef', $this->offset, $length);
                $this->offset += strlen($chunk);

                return $chunk;
            }

            public function close(): void
            {
                $this->state->closed = true;
                throw new RuntimeException('provider close failed');
            }
        }, 3);

        $t->same('abc', $reader->read(10));
        $reader->close();
        $t->true($state->closed);
    },
    'limited read closer supports wordpress archive member imports with trailing provider close errors' => static function (TestRunner $t): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $wxr = $tree['exports/site.wxr'];
        $reader = new LimitedReadCloser(new class($wxr . 'next-archive-member') {
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
                throw new RuntimeException('remote cleanup failed after artifact stream');
            }
        }, strlen($wxr));

        $restored = '';
        while (($chunk = $reader->read(8)) !== '') {
            $restored .= $chunk;
        }

        $reader->close();
        $t->same($wxr, $restored);
        $t->true(!str_contains($restored, 'next-archive-member'));
        $t->same(0, $reader->remaining());
    },
];
