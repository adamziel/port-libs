<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Syncthing\DeviceId;

$rawCertificate = "-----BEGIN CERTIFICATE-----\nwordpress playground importer peer\n-----END CERTIFICATE-----";
$deviceId = DeviceId::fromRawCertificateBytes($rawCertificate);

$copiedFromAdminScreen = strtolower(str_replace('-', ' ', $deviceId->toString()));
$acceptedPeer = DeviceId::fromString($copiedFromAdminScreen);

echo 'canonical=' . $deviceId->toString() . PHP_EOL;
echo 'short=' . $deviceId->shortString() . PHP_EOL;
echo 'raw_hash=' . $deviceId->hex() . PHP_EOL;
echo 'accepted_copy=' . ($acceptedPeer->equals($deviceId) ? 'yes' : 'no') . PHP_EOL;
