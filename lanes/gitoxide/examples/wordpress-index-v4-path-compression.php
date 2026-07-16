<?php

declare(strict_types=1);

use PortLibs\Gitoxide\IndexFile;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$oid = 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391';
$entryHeader = static function (int $flags = 0) use ($oid): string {
    $oidBytes = hex2bin($oid);
    if ($oidBytes === false) {
        throw new RuntimeException('Unable to decode fixture object id');
    }

    return pack('N10', 0, 0, 0, 0, 0, 0, 0o100644, 0, 0, 0) . $oidBytes . pack('n', $flags);
};
$encodeVarInt = static function (int $value): string {
    $bytes = array_fill(0, 10, 0);
    $cursor = 9;
    $bytes[$cursor] = $value & 0x7f;
    $written = 1;
    $value >>= 7;
    while ($value > 0) {
        $value--;
        $cursor--;
        $bytes[$cursor] = 0x80 | ($value & 0x7f);
        $written++;
        $value >>= 7;
    }

    return implode('', array_map(static fn (int $byte): string => chr($byte), array_slice($bytes, 10 - $written)));
};
$commonPrefixLength = static function (string $left, string $right): int {
    $limit = min(strlen($left), strlen($right));
    $length = 0;
    while ($length < $limit && $left[$length] === $right[$length]) {
        $length++;
    }

    return $length;
};
$buildV4Index = static function (array $paths) use ($entryHeader, $encodeVarInt, $commonPrefixLength): string {
    $payload = 'DIRC' . pack('N2', 4, count($paths));
    $previous = null;
    foreach ($paths as $path) {
        $shared = $previous === null ? 0 : $commonPrefixLength($previous, $path);
        $strip = $previous === null ? 0 : strlen($previous) - $shared;
        $payload .= $entryHeader() . $encodeVarInt($strip) . substr($path, $shared) . "\0";
        $previous = $path;
    }

    $checksum = hex2bin(hash('sha1', $payload));
    if ($checksum === false) {
        throw new RuntimeException('Unable to build fixture checksum');
    }

    return $payload . $checksum;
};

$expectedPaths = [
    'wp-admin/about.php',
    'wp-content/plugins/acme/acme.php',
    'wp-content/plugins/acme/assets/editor.js',
    'wp-content/themes/twentysixteen/style.css',
];
$bytes = $buildV4Index($expectedPaths);
$entries = IndexFile::entriesFromBytes($bytes);
$paths = array_map(static fn ($entry): string => $entry->path, $entries);

echo 'version=' . IndexFile::versionFromBytes($bytes) . "\n";
echo 'entries=' . count($entries) . "\n";
echo 'paths=' . implode(',', $paths) . "\n";
echo 'wordpressUse=Read a compact Git index v4 for WordPress deployment paths without shelling out to git.' . "\n";

if (in_array('--self-test', $argv, true)) {
    if (IndexFile::versionFromBytes($bytes) !== 4 || $paths !== $expectedPaths) {
        throw new RuntimeException('Git index v4 path-compression example self-test failed');
    }
}
