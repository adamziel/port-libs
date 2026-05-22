<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\GzipReader;

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
$restored = '';
while (($chunk = $reader->read(8)) !== '') {
    $restored .= $chunk;
}
$reader->close();

return [
    'compressedBytes' => strlen(gzencode($wxr)),
    'restoredBytes' => strlen($restored),
    'matchesOriginal' => $restored === $wxr,
    'isWxr' => str_starts_with($restored, '<rss '),
    'underlyingProviderClosed' => $source->closed,
];
