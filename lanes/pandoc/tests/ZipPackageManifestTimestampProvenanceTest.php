<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;

$packNtfsFileTime = static function (int $timestamp): string {
    $filetime = ($timestamp + 11644473600) * 10000000;
    $low = $filetime & 0xffffffff;
    $high = intdiv($filetime, 0x100000000);

    return pack('VV', $low, $high);
};

$buildNtfsExtra = static function (int $modifiedAt, int $accessedAt, int $createdAt) use ($packNtfsFileTime): string {
    $payload = pack('Vvv', 0, 0x0001, 24)
        . $packNtfsFileTime($modifiedAt)
        . $packNtfsFileTime($accessedAt)
        . $packNtfsFileTime($createdAt);

    return pack('vv', 0x000a, strlen($payload)) . $payload;
};

return [
    'summarizes zip package manifest timestamp provenance without changing manifest hash contract' => static function (TestRunner $t) use ($buildNtfsExtra): void {
        $documentModifiedAt = 1780479016;
        $mediaModifiedAt = 1780479027;
        $mediaAccessedAt = 1780479028;
        $mediaCreatedAt = 1780479029;
        $documentXml = '<w:document><w:body><w:p>manifest timestamp provenance</w:p></w:body></w:document>';
        $relsXml = '<Relationships><Relationship Id="rIdMedia" Target="media/review.bin"/></Relationships>';
        $mediaBytes = "timestamped manifest media bytes\n";
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 0,
                'modifiedAt' => $documentModifiedAt,
            ],
            [
                'name' => 'word/_rels/document.xml.rels',
                'data' => $relsXml,
                'compressionMethod' => 0,
                'modifiedDosTime' => 19400,
                'modifiedDosDate' => 23747,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'compressionMethod' => 0,
                'modifiedDosTime' => 19400,
                'modifiedDosDate' => 23747,
                'extraFieldData' => $buildNtfsExtra($mediaModifiedAt, $mediaAccessedAt, $mediaCreatedAt),
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'compressionMethod' => 0,
            ],
        ]);

        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $raw = ZipPackage::rawStrictImportPreflight($package->bytes(), 4096, 100.0, 4096);
        $entriesByName = array_column($manifest['entries'], null, 'name');
        $sourcesByName = array_column($manifest['timestampSourceSummaries'], null, 'timestampSource');

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
        $t->same(4, $manifest['entryCount']);
        $t->same(3, $manifest['timestampProvenanceEntryCount']);
        $t->same(3, $manifest['timestampEntryCount']);
        $t->same(3, $manifest['dosTimestampEntryCount']);
        $t->same(1, $manifest['extendedTimestampEntryCount']);
        $t->same(1, $manifest['ntfsTimestampEntryCount']);
        $t->same(3, $manifest['localTimestampEntryCount']);
        $t->same(1, $manifest['localExtendedTimestampEntryCount']);
        $t->same(1, $manifest['localNtfsTimestampEntryCount']);
        $t->same(0, $manifest['invalidDosTimestampEntryCount']);
        $t->same(0, $manifest['timestampIssueEntryCount']);
        $t->same(0, $manifest['timestampIssueCount']);
        $t->same([], $manifest['timestampIssues']);
        $t->same(3, $manifest['timestampSourceSummaryCount']);
        $t->same(['dos', 'extended-timestamp', 'ntfs'], array_keys($sourcesByName));

        $t->same([
            'timestampSource' => 'extended-timestamp',
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'compressedBytes' => strlen($documentXml),
            'uncompressedBytes' => strlen($documentXml),
            'roles' => [],
            'entryNames' => ['word/document.xml'],
        ], $sourcesByName['extended-timestamp']);
        $t->same([
            'timestampSource' => 'dos',
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'compressedBytes' => strlen($relsXml),
            'uncompressedBytes' => strlen($relsXml),
            'roles' => [],
            'entryNames' => ['word/_rels/document.xml.rels'],
        ], $sourcesByName['dos']);
        $t->same([
            'timestampSource' => 'ntfs',
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'compressedBytes' => strlen($mediaBytes),
            'uncompressedBytes' => strlen($mediaBytes),
            'roles' => [],
            'entryNames' => ['word/media/review.bin'],
        ], $sourcesByName['ntfs']);

        $documentEntry = $entriesByName['word/document.xml'];
        $relsEntry = $entriesByName['word/_rels/document.xml.rels'];
        $mediaEntry = $entriesByName['word/media/review.bin'];
        $directoryEntry = $entriesByName['word/media/'];

        $t->same(19400, $documentEntry['modifiedDosTime']);
        $t->same(23747, $documentEntry['modifiedDosDate']);
        $t->same(true, $documentEntry['hasDosTimestamp']);
        $t->same($documentModifiedAt, $documentEntry['modifiedAt']);
        $t->same('extended-timestamp', $documentEntry['timestampSource']);
        $t->same($documentModifiedAt, $documentEntry['localExtendedModifiedAt']);
        $t->same('extended-timestamp', $documentEntry['localTimestampSource']);
        $t->same(true, $documentEntry['hasTimestampProvenance']);

        $t->same(19400, $relsEntry['modifiedDosTime']);
        $t->same(23747, $relsEntry['modifiedDosDate']);
        $t->same(1780479016, $relsEntry['dosModifiedAt']);
        $t->same('dos', $relsEntry['timestampSource']);
        $t->same('dos', $relsEntry['localTimestampSource']);

        $t->same($mediaModifiedAt, $mediaEntry['modifiedAt']);
        $t->same('ntfs', $mediaEntry['timestampSource']);
        $t->same($mediaAccessedAt, $mediaEntry['localNtfsAccessedAt']);
        $t->same($mediaCreatedAt, $mediaEntry['localNtfsCreatedAt']);
        $t->same('ntfs', $mediaEntry['localTimestampSource']);
        $t->same(true, $mediaEntry['hasTimestampProvenance']);

        $t->same(false, $directoryEntry['hasDosTimestamp']);
        $t->same(null, $directoryEntry['modifiedAt']);
        $t->same(false, $directoryEntry['hasTimestampProvenance']);
        $t->same([], $directoryEntry['timestampIssues']);

        $t->same([
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/media/review.bin',
        ], array_map(
            static fn (array $entry): string => $entry['name'],
            $manifest['timestampProvenanceEntries']
        ));
        $t->same(19400, $manifest['timestampProvenanceEntries'][0]['modifiedDosTime']);
        $t->same(23747, $manifest['timestampProvenanceEntries'][0]['modifiedDosDate']);
        $t->same([], $manifest['timestampIssueEntries']);
        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },
];
