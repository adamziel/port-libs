<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;

$buildZipWithLocalSlack = static function (): string {
    $parts = [
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:p>local span issue provenance</w:p></w:document>',
            'method' => 8,
            'localSlack' => "PK\x06\x08" . pack('V', 4) . 'note',
        ],
        [
            'name' => 'word/media/review.png',
            'data' => 'PNGDATA',
            'method' => 0,
            'localSlack' => '',
        ],
    ];

    $body = '';
    $central = '';
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $modifiedTime = 0;
    $modifiedDate = 0x0021;

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'];
        $method = $part['method'];
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to deflate test fixture entry ' . $name);
        }

        $crc = $crc32($data);
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $localHeaderOffset = strlen($body);
        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            $modifiedTime,
            $modifiedDate,
            $crc,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0
        );
        $body .= $name . $compressed . $part['localSlack'];

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            (3 << 8) | 20,
            20,
            0x0800,
            $method,
            $modifiedTime,
            $modifiedDate,
            $crc,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $localHeaderOffset
        );
        $central .= $name;
    }

    $centralDirectoryOffset = strlen($body);

    return $body
        . $central
        . pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($parts),
            count($parts),
            strlen($central),
            $centralDirectoryOffset,
            0
        );
};

return [
    'summarizes ZIP local header span issues by entry before package handoff' => static function (TestRunner $t) use ($buildZipWithLocalSlack): void {
        $zip = $buildZipWithLocalSlack();
        $span = ZipPackage::localHeaderSpanPreflight($zip);
        $layout = ZipPackage::packageByteLayoutPreflight($zip);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);

        $t->same(false, $span['isSupportedByBoundedReader']);
        $t->same(['local-entry-unclaimed-bytes'], $span['issues']);
        $t->same(['local-entry-unclaimed-bytes' => 1], $span['issueCounts']);
        $t->same(['local-entry-unclaimed-bytes' => ['word/document.xml']], $span['entryNamesByIssue']);
        $t->same(1, $span['issueEntryCount']);
        $t->same('word/document.xml', $span['issueEntries'][0]['name']);
        $t->same('archive-extra-data-record', $span['issueEntries'][0]['unclaimedBytesSignature']);
        $t->same(false, $span['issueEntries'][0]['unclaimedBytesStartWithLocalHeader']);

        $t->same(false, $layout['isSupportedByBoundedReader']);
        $t->same(['local-entry-unclaimed-bytes'], $layout['issues']);
        $t->same(['local-entry-unclaimed-bytes' => 1], $layout['issueCounts']);
        $t->same(['local-entry-unclaimed-bytes' => ['word/document.xml']], $layout['entryNamesByIssue']);
        $t->same($span['unclaimedBytes'], $layout['unclaimedLocalBytes']);
        $t->same('archive-extra-data-record', $layout['entries'][0]['unclaimedBytesSignature']);

        $t->same(false, $raw['isValid']);
        $t->same(false, $raw['canInstantiate']);
        $t->same($span, $raw['localHeaderSpans']);
        $t->same($layout, $raw['packageByteLayout']);
        $t->contains('local-entry-unclaimed-bytes', implode(',', $raw['diagnostics']));
        $t->contains('package-byte-layout-issues', implode(',', $raw['diagnostics']));
    },
];
