<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;

/**
 * @param list<array{name:string, data:string, centralIndex:int}> $parts
 */
$buildStoredZipWithCentralOrder = static function (array $parts): string {
    $body = '';
    $centralRecords = [];
    $flags = 0x0800;
    $method = 0;
    $dosTime = 0;
    $dosDate = 0;

    foreach ($parts as $localOrder => $part) {
        $name = $part['name'];
        $data = $part['data'];
        $crc32 = (int) sprintf('%u', crc32($data));
        $size = strlen($data);
        $offset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            $dosTime,
            $dosDate,
            $crc32,
            $size,
            $size,
            strlen($name),
            0
        );
        $body .= $name . $data;

        $centralRecords[] = [
            'centralIndex' => $part['centralIndex'],
            'localOrder' => $localOrder,
            'bytes' => pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                $flags,
                $method,
                $dosTime,
                $dosDate,
                $crc32,
                $size,
                $size,
                strlen($name),
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $name,
        ];
    }

    usort(
        $centralRecords,
        static fn (array $left, array $right): int => [
            $left['centralIndex'],
            $left['localOrder'],
        ] <=> [
            $right['centralIndex'],
            $right['localOrder'],
        ]
    );

    $central = implode('', array_column($centralRecords, 'bytes'));
    $centralDirectoryOffset = strlen($body);
    $centralDirectorySize = strlen($central);

    return $body
        . $central
        . pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($parts),
            count($parts),
            $centralDirectorySize,
            $centralDirectoryOffset,
            0
        );
};

return [
    'summarizes zip package manifest order review without changing manifest hash contract' => static function (TestRunner $t) use ($buildStoredZipWithCentralOrder): void {
        $zip = $buildStoredZipWithCentralOrder([
            [
                'name' => 'mimetype',
                'data' => 'application/vnd.oasis.opendocument.text',
                'centralIndex' => 2,
            ],
            [
                'name' => 'content.xml',
                'data' => '<office:document-content/>',
                'centralIndex' => 0,
            ],
            [
                'name' => 'styles.xml',
                'data' => '<office:document-styles/>',
                'centralIndex' => 1,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $summary = $manifest['manifestOrderSummary'];
        $firstMismatch = $summary['mismatchedEntries'][0];

        $expectedManifestJson = json_encode([
            'manifestVersion' => 'zip-package-manifest-v1',
            'centralDirectoryOrderNames' => $manifest['centralDirectoryOrderNames'],
            'localHeaderOrderNames' => $manifest['localHeaderOrderNames'],
            'entries' => array_map(
                static fn (array $entry): array => [
                    'name' => $entry['name'],
                    'isDirectory' => $entry['isDirectory'],
                    'centralDirectoryIndex' => $entry['centralDirectoryIndex'],
                    'localHeaderOrder' => $entry['localHeaderOrder'],
                    'compressionMethod' => $entry['compressionMethod'],
                    'crc32Hex' => $entry['crc32Hex'],
                    'compressedSize' => $entry['compressedSize'],
                    'uncompressedSize' => $entry['uncompressedSize'],
                    'localHeaderSha256' => $entry['localHeaderSha256'],
                    'compressedDataSha256' => $entry['compressedDataSha256'],
                    'centralDirectoryRecordSha256' => $entry['centralDirectoryRecordSha256'],
                ],
                $manifest['entries']
            ),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->same(hash('sha256', $expectedManifestJson), $manifest['manifestSha256']);
        $t->same(false, $manifest['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same('review', $manifest['manifestOrderReviewStatus']);
        $t->same(['central-directory-local-header-order-mismatch'], $manifest['manifestOrderIssueCodes']);
        $t->same(1, $manifest['manifestOrderIssueCount']);
        $t->same(3, $manifest['manifestOrderMismatchEntryCount']);

        $t->same(3, $summary['entryCount']);
        $t->same(['content.xml', 'styles.xml', 'mimetype'], $summary['requestOrderNames']);
        $t->same(['content.xml', 'styles.xml', 'mimetype'], $summary['centralDirectoryOrderNames']);
        $t->same(['mimetype', 'content.xml', 'styles.xml'], $summary['localHeaderOrderNames']);
        $t->same(true, $summary['requestOrderMatchesCentralDirectoryOrder']);
        $t->same(false, $summary['requestOrderMatchesLocalHeaderOrder']);
        $t->same(false, $summary['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same(3, $summary['mismatchEntryCount']);

        $t->same('content.xml', $firstMismatch['name']);
        $t->same(0, $firstMismatch['requestOrder']);
        $t->same(0, $firstMismatch['centralDirectoryIndex']);
        $t->same(1, $firstMismatch['localHeaderOrder']);
        $t->same(0, $firstMismatch['centralDirectorySubsetOrder']);
        $t->same(1, $firstMismatch['localHeaderSubsetOrder']);
        $t->same(false, $firstMismatch['matchesCentralDirectoryOrder']);
        $t->same([], $firstMismatch['roles']);

        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
        $t->contains('central-directory-local-header-order-mismatch', implode(',', $strict['diagnostics']));
        $t->contains('central-directory-local-header-order-mismatch', implode(',', $raw['diagnostics']));

        $matchingManifest = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'content.opf', 'data' => '<package/>', 'compressionMethod' => 0],
        ])->packageManifestPreflight();
        $t->same('ok', $matchingManifest['manifestOrderReviewStatus']);
        $t->same([], $matchingManifest['manifestOrderIssueCodes']);
        $t->same(0, $matchingManifest['manifestOrderIssueCount']);
        $t->same(0, $matchingManifest['manifestOrderMismatchEntryCount']);
        $t->same(true, $matchingManifest['manifestOrderSummary']['centralDirectoryOrderMatchesLocalHeaderOrder']);
    },
];
