<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));

/**
 * @param list<array{name:string, data:string, method?:int, descriptor?:bool, descriptorSignature?:bool}> $entries
 */
$buildZipWithDescriptors = static function (array $entries) use ($crc32): string {
    $body = '';
    $centralRecords = [];
    $dosTime = 0;
    $dosDate = 0;

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'] ?? 0;
        $descriptor = (bool) ($entry['descriptor'] ?? false);
        $flags = 0x0800 | ($descriptor ? 0x0008 : 0);
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if ($compressed === false) {
            throw new RuntimeException('Unable to deflate ZIP fixture entry');
        }
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $actualCrc32 = $crc32($data);
        $localHeaderOffset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            $dosTime,
            $dosDate,
            $descriptor ? 0 : $actualCrc32,
            $descriptor ? 0 : $compressedSize,
            $descriptor ? 0 : $uncompressedSize,
            strlen($name),
            0
        );
        $body .= $name . $compressed;
        if ($descriptor) {
            if ($entry['descriptorSignature'] ?? true) {
                $body .= "PK\x07\x08";
            }
            $body .= pack('VVV', $actualCrc32, $compressedSize, $uncompressedSize);
        }

        $centralRecords[] = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            $flags,
            $method,
            $dosTime,
            $dosDate,
            $actualCrc32,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $localHeaderOffset
        ) . $name;
    }

    $centralDirectory = implode('', $centralRecords);
    $centralDirectoryOffset = strlen($body);
    $centralDirectorySize = strlen($centralDirectory);

    return $body
        . $centralDirectory
        . pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($entries),
            count($entries),
            $centralDirectorySize,
            $centralDirectoryOffset,
            0
        );
};

return [
    'summarizes zip package manifest data descriptor review without changing manifest hash contract' => static function (TestRunner $t) use ($buildZipWithDescriptors, $crc32): void {
        $documentXml = '<w:document><w:body><w:p>manifest descriptor review</w:p></w:body></w:document>';
        $commentsXml = '<w:comments><w:comment>signed descriptor</w:comment></w:comments>';
        $footnotesXml = '<w:footnotes><w:footnote>unsigned descriptor</w:footnote></w:footnotes>';
        $zip = $buildZipWithDescriptors([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => $footnotesXml,
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
        ]);
        $commentsCompressed = gzdeflate($commentsXml);
        if ($commentsCompressed === false) {
            throw new RuntimeException('Unable to deflate comments fixture');
        }

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);
        $documentEntry = $manifest['entries'][0];
        $commentsEntry = $manifest['entries'][1];
        $footnotesEntry = $manifest['entries'][2];
        $commentsDescriptor = $manifest['dataDescriptorEntries'][0];
        $footnotesDescriptor = $manifest['dataDescriptorEntries'][1];

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
        $t->same('ok', $manifest['dataDescriptorReviewStatus']);
        $t->same([], $manifest['dataDescriptorIssueCodes']);
        $t->same(0, $manifest['dataDescriptorIssueCount']);
        $t->same(0, $manifest['dataDescriptorIssueEntryCount']);
        $t->same(2, $manifest['dataDescriptorEntryCount']);
        $t->same(1, $manifest['signedDataDescriptorEntryCount']);
        $t->same(1, $manifest['unsignedDataDescriptorEntryCount']);
        $t->same(0, $manifest['zip64SizedDataDescriptorEntryCount']);
        $t->same(2, $manifest['zeroLocalHeaderPlaceholderEntryCount']);
        $t->same(2, $manifest['dataDescriptorValuesMatchCentralEntryCount']);
        $t->same(28, $manifest['sourceDataDescriptorBytes']);
        $t->same([], $manifest['dataDescriptorIssueEntries']);

        $t->same(false, $documentEntry['usesDataDescriptor']);
        $t->same(null, $documentEntry['dataDescriptorHasSignature']);
        $t->same(null, $documentEntry['dataDescriptorLength']);
        $t->same(0, $documentEntry['dataDescriptorBytes']);
        $t->same(null, $documentEntry['dataDescriptorSha256']);
        $t->same($crc32($documentXml), $documentEntry['localHeaderCrc32']);

        $t->same(true, $commentsEntry['usesDataDescriptor']);
        $t->same(true, $commentsEntry['dataDescriptorHasSignature']);
        $t->same(16, $commentsEntry['dataDescriptorLength']);
        $t->same(16, $commentsEntry['dataDescriptorBytes']);
        $t->same($commentsEntry['compressedDataEnd'], $commentsEntry['dataDescriptorOffset']);
        $t->same(hash('sha256', substr($zip, $commentsEntry['dataDescriptorOffset'], 16)), $commentsEntry['dataDescriptorSha256']);
        $t->same($crc32($commentsXml), $commentsEntry['dataDescriptorCrc32']);
        $t->same(sprintf('%08x', $crc32($commentsXml)), $commentsEntry['dataDescriptorCrc32Hex']);
        $t->same(strlen($commentsCompressed), $commentsEntry['dataDescriptorCompressedSize']);
        $t->same(strlen($commentsXml), $commentsEntry['dataDescriptorUncompressedSize']);
        $t->same(true, $commentsEntry['dataDescriptorValuesMatchCentral']);
        $t->same([], $commentsEntry['dataDescriptorIssues']);
        $t->same(true, $commentsEntry['hasZeroLocalHeaderPlaceholders']);

        $t->same(true, $footnotesEntry['usesDataDescriptor']);
        $t->same(false, $footnotesEntry['dataDescriptorHasSignature']);
        $t->same(12, $footnotesEntry['dataDescriptorLength']);
        $t->same(12, $footnotesEntry['dataDescriptorBytes']);
        $t->same($footnotesEntry['compressedDataEnd'], $footnotesEntry['dataDescriptorOffset']);
        $t->same(hash('sha256', substr($zip, $footnotesEntry['dataDescriptorOffset'], 12)), $footnotesEntry['dataDescriptorSha256']);
        $t->same($crc32($footnotesXml), $footnotesEntry['dataDescriptorCrc32']);
        $t->same(sprintf('%08x', $crc32($footnotesXml)), $footnotesEntry['dataDescriptorCrc32Hex']);
        $t->same(strlen($footnotesXml), $footnotesEntry['dataDescriptorCompressedSize']);
        $t->same(strlen($footnotesXml), $footnotesEntry['dataDescriptorUncompressedSize']);
        $t->same(true, $footnotesEntry['dataDescriptorValuesMatchCentral']);
        $t->same([], $footnotesEntry['dataDescriptorIssues']);
        $t->same(true, $footnotesEntry['hasZeroLocalHeaderPlaceholders']);

        $t->same([
            'name' => 'word/comments.xml',
            'isDirectory' => false,
            'centralDirectoryIndex' => 1,
            'localHeaderOrder' => 1,
            'compressionMethod' => 8,
            'compressionMethodName' => 'deflated',
            'compressedSize' => strlen($commentsCompressed),
            'uncompressedSize' => strlen($commentsXml),
            'sourceByteSpanIncludesDataDescriptor' => true,
            'dataDescriptorBytes' => 16,
            'dataDescriptorSha256' => $commentsEntry['dataDescriptorSha256'],
            'usesDataDescriptor' => true,
            'dataDescriptorHasSignature' => true,
            'dataDescriptorOffset' => $commentsEntry['dataDescriptorOffset'],
            'dataDescriptorValueOffset' => $commentsEntry['dataDescriptorOffset'] + 4,
            'dataDescriptorLength' => 16,
            'dataDescriptorNextOffset' => $footnotesEntry['localHeaderOffset'],
            'dataDescriptorSpan' => 16,
            'dataDescriptorEnd' => $footnotesEntry['localHeaderOffset'],
            'dataDescriptorSurplusBytes' => 0,
            'dataDescriptorTruncatedBytes' => 0,
            'dataDescriptorCrc32' => $crc32($commentsXml),
            'dataDescriptorCrc32Hex' => sprintf('%08x', $crc32($commentsXml)),
            'dataDescriptorCompressedSize' => strlen($commentsCompressed),
            'dataDescriptorUncompressedSize' => strlen($commentsXml),
            'dataDescriptorUsesZip64SizedFields' => false,
            'dataDescriptorValuesMatchCentral' => true,
            'dataDescriptorIssues' => [],
            'localHeaderCrc32' => 0,
            'localHeaderCompressedSize' => 0,
            'localHeaderUncompressedSize' => 0,
            'hasZeroLocalHeaderPlaceholders' => true,
        ], $commentsDescriptor);
        $t->same('word/footnotes.xml', $footnotesDescriptor['name']);
        $t->same(false, $footnotesDescriptor['dataDescriptorHasSignature']);
        $t->same(12, $footnotesDescriptor['dataDescriptorLength']);
        $t->same($footnotesEntry['dataDescriptorSha256'], $footnotesDescriptor['dataDescriptorSha256']);

        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },
];
