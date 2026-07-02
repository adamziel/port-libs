<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes zip package manifest name byte length review without changing manifest hash contract' => static function (TestRunner $t): void {
        $mimetypeName = 'mimetype';
        $documentName = 'word/document.xml';
        $directoryName = 'word/media/';
        $mediumName = 'docProps/' . str_repeat('meta-', 12) . 'core.xml';
        $longName = 'word/embeddings/' . str_repeat('section-', 18) . 'payload.bin';
        $mimetype = 'application/epub+zip';
        $documentXml = '<w:document><w:p>name length review</w:p></w:document>';
        $mediumXml = '<cp:coreProperties/>';
        $longBytes = 'review-cache-payload';
        $zip = ZipPackage::build([
            ['name' => $mimetypeName, 'data' => $mimetype, 'compressionMethod' => 0],
            ['name' => $documentName, 'data' => $documentXml, 'compressionMethod' => 0],
            ['name' => $directoryName, 'data' => '', 'compressionMethod' => 0],
            ['name' => $mediumName, 'data' => $mediumXml, 'compressionMethod' => 0],
            ['name' => $longName, 'data' => $longBytes, 'compressionMethod' => 0],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);
        $entriesByName = array_column($manifest['entries'], null, 'name');
        $buckets = array_column($manifest['nameByteLengthSummaries'], null, 'nameByteLengthBucket');
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

        $shortNameBytes = strlen($mimetypeName) + strlen($documentName) + strlen($directoryName);
        $shortPayloadBytes = strlen($mimetype) + strlen($documentXml);
        $mediumNameBytes = strlen($mediumName);
        $longNameBytes = strlen($longName);

        $t->same(hash('sha256', $expectedManifestJson), $manifest['manifestSha256']);
        $t->same('zip-package-manifest-name-byte-length-review', $manifest['nameByteLengthReviewPolicy']);
        $t->same(3, $manifest['nameByteLengthBucketCount']);
        $t->same(128, $manifest['longNameThresholdBytes']);
        $t->same(1, $manifest['longNameEntryCount']);
        $t->same($longNameBytes, $manifest['longestNameByteLength']);
        $t->same([$longName], $manifest['longestEntryNames']);
        $t->same(['up-to-31-bytes', '64-to-127-bytes', '128-to-255-bytes'], array_keys($buckets));

        $t->same(strlen($mimetypeName), $entriesByName[$mimetypeName]['nameByteLength']);
        $t->same('up-to-31-bytes', $entriesByName[$documentName]['nameByteLengthBucket']);
        $t->same($mediumNameBytes, $entriesByName[$mediumName]['nameByteLength']);
        $t->same('64-to-127-bytes', $entriesByName[$mediumName]['nameByteLengthBucket']);
        $t->same($longNameBytes, $entriesByName[$longName]['nameByteLength']);
        $t->same('128-to-255-bytes', $entriesByName[$longName]['nameByteLengthBucket']);

        $t->same([
            'nameByteLengthBucket' => 'up-to-31-bytes',
            'minNameBytes' => 0,
            'maxNameBytes' => 31,
            'entryCount' => 3,
            'fileEntryCount' => 2,
            'directoryEntryCount' => 1,
            'longNameEntryCount' => 0,
            'decodedNameBytes' => $shortNameBytes,
            'localRawNameBytes' => $shortNameBytes,
            'centralDirectoryRawNameBytes' => $shortNameBytes,
            'compressedBytes' => $shortPayloadBytes,
            'uncompressedBytes' => $shortPayloadBytes,
            'longestNameByteLength' => strlen($documentName),
            'longestEntryNames' => [$documentName],
            'entryNames' => [$mimetypeName, $documentName, $directoryName],
        ], $buckets['up-to-31-bytes']);

        $t->same([
            'nameByteLengthBucket' => '64-to-127-bytes',
            'minNameBytes' => 64,
            'maxNameBytes' => 127,
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'longNameEntryCount' => 0,
            'decodedNameBytes' => $mediumNameBytes,
            'localRawNameBytes' => $mediumNameBytes,
            'centralDirectoryRawNameBytes' => $mediumNameBytes,
            'compressedBytes' => strlen($mediumXml),
            'uncompressedBytes' => strlen($mediumXml),
            'longestNameByteLength' => $mediumNameBytes,
            'longestEntryNames' => [$mediumName],
            'entryNames' => [$mediumName],
        ], $buckets['64-to-127-bytes']);

        $t->same([
            'name' => $longName,
            'nameByteLength' => $longNameBytes,
            'nameByteLengthBucket' => '128-to-255-bytes',
            'localRawNameBytes' => $longNameBytes,
            'centralDirectoryRawNameBytes' => $longNameBytes,
            'isDirectory' => false,
            'centralDirectoryIndex' => 4,
            'localHeaderOrder' => 4,
            'pathDepth' => 3,
            'directoryRoot' => 'word/',
            'parentDirectory' => 'word/embeddings/',
            'leafName' => str_repeat('section-', 18) . 'payload.bin',
            'packagePartKind' => 'package-part',
        ], $manifest['longNameEntries'][0]);

        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
        json_encode($manifest, JSON_THROW_ON_ERROR);
    },
];
