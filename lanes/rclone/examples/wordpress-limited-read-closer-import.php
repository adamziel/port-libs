<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\LimitedReadCloser;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$wxr = $tree['exports/site.wxr'];
$providerBody = $wxr . 'next-archive-member';

$reader = new LimitedReadCloser(new class($providerBody) {
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
        throw new RuntimeException('remote cleanup failed after WXR artifact stream');
    }
}, strlen($wxr));

$restored = '';
while (($chunk = $reader->read(8)) !== '') {
    $restored .= $chunk;
}

$closeErrorIgnored = true;
try {
    $reader->close();
} catch (RuntimeException) {
    $closeErrorIgnored = false;
}

return [
    'expectedLength' => strlen($wxr),
    'restored' => $restored,
    'matchesOriginal' => $restored === $wxr,
    'trailingBytesHidden' => !str_contains($restored, 'next-archive-member'),
    'closeErrorIgnoredAfterCompleteRead' => $closeErrorIgnored,
];
