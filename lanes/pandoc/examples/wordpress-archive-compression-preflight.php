<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStreams;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));

$gzipMember = static function (string $data, string $name, int $modifiedAt) use ($crc32): string {
    $header = "\x1f\x8b\x08\x08" . pack('VCC', $modifiedAt, 0, 3) . $name . "\0";

    return $header . gzdeflate($data) . pack('VV', $crc32($data), strlen($data) & 0xffffffff);
};

$tarString = static function (string $value, int $length): string {
    if (strlen($value) > $length) {
        throw new RuntimeException("Tar field is too long: {$value}");
    }

    return str_pad($value, $length, "\0");
};

$tarOctal = static fn (int $value, int $length): string => str_pad(decoct($value), $length - 1, '0', STR_PAD_LEFT) . "\0";

/**
 * @param list<array{name:string, data?:string, type?:string, mode?:int, mtime?:int}> $entries
 */
$tarArchive = static function (array $entries) use ($tarString, $tarOctal): string {
    $archive = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $prefix = '';
        if (strlen($name) > 100) {
            $split = strrpos(substr($name, 0, 156), '/');
            if ($split === false) {
                throw new RuntimeException("Tar entry name is too long: {$name}");
            }
            $prefix = substr($name, 0, $split);
            $name = substr($name, $split + 1);
        }

        $type = $entry['type'] ?? '0';
        $data = $entry['data'] ?? '';
        $size = $type === '5' ? 0 : strlen($data);
        $header = $tarString($name, 100)
            . $tarOctal($entry['mode'] ?? ($type === '5' ? 0755 : 0644), 8)
            . $tarOctal(0, 8)
            . $tarOctal(0, 8)
            . $tarOctal($size, 12)
            . $tarOctal($entry['mtime'] ?? 0, 12)
            . str_repeat(' ', 8)
            . $type
            . $tarString('', 100)
            . "ustar\0"
            . '00'
            . $tarString('wordpress', 32)
            . $tarString('wordpress', 32)
            . $tarString('', 8)
            . $tarString('', 8)
            . $tarString($prefix, 155)
            . $tarString('', 12);

        $checksum = 0;
        for ($offset = 0; $offset < strlen($header); $offset++) {
            $checksum += ord($header[$offset]);
        }
        $header = substr($header, 0, 148)
            . str_pad(decoct($checksum), 6, '0', STR_PAD_LEFT)
            . "\0 "
            . substr($header, 156);

        $paddingLength = $size === 0 ? 0 : (512 - ($size % 512)) % 512;
        $archive .= $header . ($type === '5' ? '' : $data) . str_repeat("\0", $paddingLength);
    }

    return $archive . str_repeat("\0", 1024);
};

$archiveBytes = $tarArchive([
    [
        'name' => 'content/',
        'type' => '5',
        'mtime' => 1780479000,
    ],
    [
        'name' => 'content/post.md',
        'data' => "# Imported Packet\n\nBody copied from a compressed WordPress migration bundle.\n",
        'mtime' => 1780479017,
    ],
    [
        'name' => 'content/media/hero.txt',
        'data' => 'hero media placeholder',
        'mtime' => 1780479020,
    ],
]);
$packet = $gzipMember($archiveBytes, 'wordpress-import.tar', 1780479021);
$entries = ArchiveCompressionStreams::tarGzipEntries($packet);
$files = ArchiveCompressionStreams::tarGzipFiles($packet);
$singleZeroEndMarkerPacket = $gzipMember(substr($archiveBytes, 0, -512), 'wordpress-truncated-end-marker.tar', 1780479022);

if (in_array('--self-test', $argv, true)) {
    if (($files['content/post.md'] ?? '') !== "# Imported Packet\n\nBody copied from a compressed WordPress migration bundle.\n") {
        throw new RuntimeException('Expected Markdown post body to be extracted from the compressed archive');
    }

    if (($files['content/media/hero.txt'] ?? '') !== 'hero media placeholder') {
        throw new RuntimeException('Expected media sidecar bytes to be extracted from the compressed archive');
    }

    if (($entries[0]['type'] ?? '') !== 'directory' || ($entries[0]['name'] ?? '') !== 'content/') {
        throw new RuntimeException('Expected source directory entry to be preserved in the archive preflight');
    }

    $singleZeroEndMarkerBlocked = false;
    try {
        ArchiveCompressionStreams::tarGzipEntries($singleZeroEndMarkerPacket);
    } catch (RuntimeException) {
        $singleZeroEndMarkerBlocked = true;
    }

    if (!$singleZeroEndMarkerBlocked) {
        throw new RuntimeException('Expected gzip-tar streams with a single zero end marker block to be rejected');
    }

    echo "archive compression preflight self-test passed\n";
    exit(0);
}

echo "Archive compression preflight for WordPress import:\n";
foreach ($entries as $entry) {
    echo '- ' . $entry['name']
        . ' type=' . $entry['type']
        . ' size=' . $entry['size']
        . ' modifiedAt=' . $entry['modifiedAt']
        . "\n";
}
echo 'post.md=' . $files['content/post.md'] . "\n";
