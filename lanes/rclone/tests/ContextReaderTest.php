<?php

declare(strict_types=1);

use PortLibs\Rclone\CancellationContext;
use PortLibs\Rclone\ContextReader;
use PortLibs\Rclone\PatternReader;

return [
    'context reader maps upstream read before and after cancellation' => static function (TestRunner $t): void {
        $context = new CancellationContext();
        $reader = new ContextReader($context, new PatternReader(100));

        $t->same("\x00\x01\x02", $reader->read(3));

        $context->cancel();

        try {
            $reader->read(3);
            throw new RuntimeException('Expected canceled context read to fail');
        } catch (RuntimeException $throwable) {
            $t->same(CancellationContext::ERR_CANCELED, $throwable->getMessage());
        }
    },
    'context reader checks cancellation before reading underlying bytes' => static function (TestRunner $t): void {
        $source = new class {
            public int $reads = 0;

            public function read(int $length): string
            {
                $this->reads++;

                return str_repeat('x', $length);
            }
        };
        $context = new CancellationContext();
        $reader = new ContextReader($context, $source);

        $t->same('xx', $reader->read(2));
        $context->cancel(new RuntimeException('restore aborted'));

        try {
            $reader->read(2);
            throw new RuntimeException('Expected canceled context read to fail');
        } catch (RuntimeException $throwable) {
            $t->same('restore aborted', $throwable->getMessage());
        }

        $t->same(1, $source->reads);
    },
    'context reader cancellation protects wordpress restore streams' => static function (TestRunner $t): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $context = new CancellationContext();
        $source = new class($tree['exports/site.wxr']) {
            public int $bytesRead = 0;
            private int $offset = 0;

            public function __construct(private readonly string $bytes)
            {
            }

            public function read(int $length): string
            {
                $chunk = substr($this->bytes, $this->offset, $length);
                $this->offset += strlen($chunk);
                $this->bytesRead += strlen($chunk);

                return $chunk;
            }
        };
        $reader = new ContextReader($context, $source);

        $t->same('<rss ', $reader->read(5));
        $context->cancel('wordpress import canceled');

        try {
            $reader->read(5);
            throw new RuntimeException('Expected canceled restore stream to fail');
        } catch (RuntimeException $throwable) {
            $t->same('wordpress import canceled', $throwable->getMessage());
        }

        $t->same(5, $source->bytesRead);
    },
];
