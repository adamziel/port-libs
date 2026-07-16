<?php

declare(strict_types=1);

use PortLibs\Gitoxide\IndexEntry;
use PortLibs\Gitoxide\IndexFile;

$emptyBlob = 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391';
$withChecksum = static function (string $payload): string {
    $checksum = hex2bin(hash('sha1', $payload));
    if ($checksum === false) {
        throw new RuntimeException('Unable to build index checksum fixture');
    }

    return $payload . $checksum;
};
$entryHeader = static function (string $oid, int $flags = 0, int $mode = 0o100644): string {
    $oidBytes = hex2bin($oid);
    if ($oidBytes === false || strlen($oidBytes) !== 20) {
        throw new RuntimeException('Invalid index test object id');
    }

    return pack('N10', 0, 0, 0, 0, 0, 0, $mode, 0, 0, 0) . $oidBytes . pack('n', $flags);
};
$encodeVarInt = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('Cannot encode negative index varint fixture');
    }

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
    $max = min(strlen($left), strlen($right));
    $length = 0;
    while ($length < $max && $left[$length] === $right[$length]) {
        $length++;
    }

    return $length;
};
$buildV2Index = static function (array $paths) use ($emptyBlob, $entryHeader, $withChecksum): string {
    $payload = 'DIRC' . pack('N2', 2, count($paths));
    foreach ($paths as $path) {
        $pathLength = min(strlen($path), 0x0fff);
        $entry = $entryHeader($emptyBlob, $pathLength) . $path . "\0";
        $padding = (8 - (strlen($entry) % 8)) % 8;
        $payload .= $entry . str_repeat("\0", $padding);
    }

    return $withChecksum($payload);
};
$buildV4Index = static function (array $paths, bool $withIeot = false) use (
    $emptyBlob,
    $entryHeader,
    $encodeVarInt,
    $commonPrefixLength,
    $withChecksum,
): string {
    $payload = 'DIRC' . pack('N2', 4, count($paths));
    $previous = null;
    foreach ($paths as $path) {
        $shared = $previous === null ? 0 : $commonPrefixLength($previous, $path);
        $stripLength = $previous === null ? 0 : strlen($previous) - $shared;
        $payload .= $entryHeader($emptyBlob) . $encodeVarInt($stripLength) . substr($path, $shared) . "\0";
        $previous = $path;
    }

    if ($withIeot) {
        $body = pack('N3', 1, 12, count($paths));
        $payload .= 'IEOT' . pack('N', strlen($body)) . $body;
    }

    return $withChecksum($payload);
};
$buildRawV4Index = static function (array $entries) use ($emptyBlob, $entryHeader, $encodeVarInt, $withChecksum): string {
    $payload = 'DIRC' . pack('N2', 4, count($entries));
    foreach ($entries as $entry) {
        $payload .= $entryHeader($emptyBlob) . $encodeVarInt($entry['strip']) . $entry['suffix'];
    }

    return $withChecksum($payload);
};
$pathsOf = static fn (array $entries): array => array_map(static fn (IndexEntry $entry): string => $entry->path, $entries);
$throwsMessage = static function (TestRunner $t, string $messageNeedle, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $t->contains($messageNeedle, $throwable->getMessage());

        return;
    }

    throw new RuntimeException('Expected exception was not thrown');
};

return [
    'parses upstream gitoxide v4 delta paths and ignores IEOT extension payloads' => static function (TestRunner $t) use ($buildV4Index, $pathsOf, $emptyBlob): void {
        $paths = [
            'a',
            'b',
            'c',
            'd/a',
            'd/b',
            'd/c',
            'd/last/123',
            'd/last/34',
            'd/last/6',
            'x',
        ];
        $bytes = $buildV4Index($paths, true);
        $entries = IndexFile::entriesFromBytes($bytes);

        $t->same(4, IndexFile::versionFromBytes($bytes));
        $t->same(hash('sha1', substr($bytes, 0, -20)), IndexFile::checksum($bytes));
        $t->same(10, count($entries));
        $t->same($paths, $pathsOf($entries));
        foreach ($entries as $entry) {
            $t->same(IndexEntry::STAGE_NORMAL, $entry->stage);
            $t->same('100644', $entry->mode);
            $t->same($emptyBlob, $entry->oid);
            $t->same(false, $entry->skipWorktree);
            $t->same(false, $entry->assumeValid);
        }
    },
    'v2 full paths and v4 compressed paths produce equivalent current index entries' => static function (TestRunner $t) use ($buildV2Index, $buildV4Index, $pathsOf): void {
        $paths = [
            'wp-admin/about.php',
            'wp-admin/includes/plugin.php',
            'wp-content/plugins/acme/acme.php',
            'wp-content/plugins/acme/assets/editor.js',
            'wp-content/themes/twentysixteen/style.css',
        ];
        $v2 = IndexFile::entriesFromBytes($buildV2Index($paths));
        $v4 = IndexFile::entriesFromBytes($buildV4Index($paths));

        $t->same($pathsOf($v2), $pathsOf($v4));
        foreach ($v2 as $index => $fullPathEntry) {
            $compressedEntry = $v4[$index];
            $t->same($fullPathEntry->path, $compressedEntry->path);
            $t->same($fullPathEntry->stage, $compressedEntry->stage);
            $t->same($fullPathEntry->mode, $compressedEntry->mode);
            $t->same($fullPathEntry->oid, $compressedEntry->oid);
            $t->same($fullPathEntry->skipWorktree, $compressedEntry->skipWorktree);
            $t->same($fullPathEntry->assumeValid, $compressedEntry->assumeValid);
        }
    },
    'decodes multi-byte v4 strip lengths when compressed paths share no prefix' => static function (TestRunner $t) use ($buildV4Index, $pathsOf): void {
        $long = str_repeat('a', 140) . '/plugin.php';
        $paths = [$long, 'z.php'];

        $t->same($paths, $pathsOf(IndexFile::entriesFromBytes($buildV4Index($paths))));
    },
    'rejects v4 strip length before a previous path exists' => static function (TestRunner $t) use ($buildRawV4Index, $throwsMessage): void {
        $bytes = $buildRawV4Index([
            ['strip' => 1, 'suffix' => "a\0"],
        ]);

        $throwsMessage(
            $t,
            'cannot strip without a previous path',
            static fn () => IndexFile::entriesFromBytes($bytes),
        );
    },
    'rejects v4 strip lengths that exceed the previous path length' => static function (TestRunner $t) use ($buildRawV4Index, $throwsMessage): void {
        $bytes = $buildRawV4Index([
            ['strip' => 0, 'suffix' => "a\0"],
            ['strip' => 2, 'suffix' => "b\0"],
        ]);

        $throwsMessage(
            $t,
            'strip length exceeds previous path length',
            static fn () => IndexFile::entriesFromBytes($bytes),
        );
    },
    'rejects truncated v4 path-compression varints' => static function (TestRunner $t) use ($emptyBlob, $entryHeader, $withChecksum, $throwsMessage): void {
        $bytes = $withChecksum('DIRC' . pack('N2', 4, 1) . $entryHeader($emptyBlob) . chr(0x80));

        $throwsMessage(
            $t,
            'varint is truncated',
            static fn () => IndexFile::entriesFromBytes($bytes),
        );
    },
    'rejects v4 compressed path suffixes without NUL terminators' => static function (TestRunner $t) use ($emptyBlob, $entryHeader, $withChecksum, $throwsMessage): void {
        $bytes = $withChecksum('DIRC' . pack('N2', 4, 1) . $entryHeader($emptyBlob) . "\0" . 'wp-content/plugin.php');

        $throwsMessage(
            $t,
            'compressed path suffix is not NUL-terminated',
            static fn () => IndexFile::entriesFromBytes($bytes),
        );
    },
];
