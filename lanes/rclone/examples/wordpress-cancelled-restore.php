<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\CancellationContext;
use PortLibs\Rclone\ContextReader;

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
$probe = $reader->read(5);

$context->cancel('wordpress import canceled');

$error = null;
try {
    $reader->read(5);
} catch (RuntimeException $throwable) {
    $error = $throwable->getMessage();
}

return [
    'probe' => $probe,
    'isWxr' => $probe === '<rss ',
    'error' => $error,
    'bytesReadBeforeCancel' => $source->bytesRead,
];
