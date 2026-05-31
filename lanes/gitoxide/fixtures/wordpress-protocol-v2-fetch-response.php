<?php

declare(strict_types=1);

$main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
$installed = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$packData = 'PACK' . pack('N', 2) . pack('N', 1) . 'wordpress-blobless-pack' . hex2bin('3b4b12f4cf6262d95e165b4517d71d0b9df20789');

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$delimiter = '0001';
$flush = '0000';

return [
    'sidebandAll' => true,
    'response' => $packet("\x02remote: preparing WordPress blobless pack\n")
        . $packet("\x01acknowledgments\n")
        . $packet("\x01ACK {$installed} common\n")
        . $packet("\x01ready\n")
        . $delimiter
        . $packet("\x01shallow-info\n")
        . $packet("\x01shallow {$main}\n")
        . $delimiter
        . $packet("\x01wanted-refs\n")
        . $packet("\x01{$main} refs/heads/main\n")
        . $delimiter
        . $packet("\x01packfile\n")
        . $packet("\x01")
        . $packet("\x02Enumerating objects: 1, done.\n")
        . $packet("\x01" . $packData)
        . $flush,
    'rawUploadPackErrorResponse' => $packet('ERR raw WordPress fetch failure' . "\n"),
    'emptyErrorSidebandResponse' => $packet("packfile\n")
        . $packet("\x03")
        . $packet("\x01" . $packData)
        . $flush,
    'objects' => [
        'main' => $main,
        'installed' => $installed,
    ],
    'packData' => $packData,
    'packetLineMaxBytes' => 65520,
    'wordpressUse' => 'A PHP deployment tool can parse protocol v2 sideband-all fetch response sections, confirm the wanted WordPress branch object, collect shallow boundary updates, surface remote progress, and hand channel-1 pack bytes to the object database layer.',
    'packetLineBoundUse' => 'Fetch response packet-lines are bounded to Gitoxide gix-packetline 64k framing before sideband decoding, so an oversized remote payload cannot be interpreted as pack or progress data.',
    'rawUploadPackErrorUse' => 'Raw upload-pack ERR pkt-lines are surfaced before sideband decoding, so WordPress deployment fetch diagnostics report the server failure text instead of a misleading sideband channel error.',
    'emptyErrorSidebandUse' => 'Empty channel-3 sideband keepalive/error packets are ignored instead of creating a blank deployment error, matching Gitoxide remote-progress handling.',
];
