<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\PatternReader;

$reader = new PatternReader(260);
$bytes = '';
while (($chunk = $reader->read(37)) !== '') {
    $bytes .= $chunk;
}

$provider = new MemoryProvider();
$provider->put('wp-content/uploads/generated/pattern.bin', $bytes);

return [
    'path' => 'wp-content/uploads/generated/pattern.bin',
    'bytes' => strlen($bytes),
    'wrapsModulo251' => bin2hex(substr($bytes, 248, 12)) === 'f8f9fa000102030405060708',
    'md5' => hash('md5', $provider->get('wp-content/uploads/generated/pattern.bin')),
];
