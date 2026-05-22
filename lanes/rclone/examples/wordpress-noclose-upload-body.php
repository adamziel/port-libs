<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\NoCloseReader;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$wxr = $tree['exports/site.wxr'];

$source = new class($wxr) {
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

$requestBody = NoCloseReader::wrap($source);
$probe = $requestBody->read(5);

if (method_exists($requestBody, 'close')) {
    $requestBody->close();
}

return [
    'probe' => $probe,
    'isWxr' => $probe === '<rss ',
    'requestBodyCanClose' => method_exists($requestBody, 'close'),
    'underlyingBodyClosedByRequestLayer' => $source->closed,
];
