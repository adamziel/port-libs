<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Close;
use PortLibs\Syncthing\Device;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$reason = 'wordpress media import paused before database maintenance';
$frame = BepWire::encodeCloseMessage(new Close($reason), Device::COMPRESSION_METADATA);
$message = BepWire::decodeMessageFrame($frame);
$close = BepWire::decodeCloseMessage($frame);

echo json_encode([
    'messageType' => $message['type'],
    'compression' => $message['compression'],
    'reason' => $close->reason,
    'wireBytes' => strlen($frame),
    'canNotifyPeerBeforeDisconnect' => $close->reason === $reason,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
