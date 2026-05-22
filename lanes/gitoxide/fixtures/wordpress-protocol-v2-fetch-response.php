<?php

declare(strict_types=1);

$main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
$installed = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$packData = 'PACK' . pack('N', 2) . pack('N', 1) . 'wordpress-blobless-pack' . hex2bin('3b4b12f4cf6262d95e165b4517d71d0b9df20789');

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$delimiter = '0001';
$flush = '0000';

return [
    'response' => $packet("acknowledgments\n")
        . $packet("ACK {$installed} common\n")
        . $packet("ready\n")
        . $delimiter
        . $packet("shallow-info\n")
        . $packet("shallow {$main}\n")
        . $delimiter
        . $packet("wanted-refs\n")
        . $packet("{$main} refs/heads/main\n")
        . $delimiter
        . $packet("packfile\n")
        . $packet("\x02Enumerating objects: 1, done.\n")
        . $packet("\x01" . $packData)
        . $flush,
    'objects' => [
        'main' => $main,
        'installed' => $installed,
    ],
    'packData' => $packData,
    'wordpressUse' => 'A PHP deployment tool can parse protocol v2 fetch response sections, confirm the wanted WordPress branch object, collect shallow boundary updates, surface remote progress, and hand sideband pack bytes to the object database layer.',
];
