<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;

/**
 * @param list<array{name:string, data:string, versionMadeBy:int, versionNeededToExtract:int}> $parts
 */
$buildStoredZipWithCreatorHosts = static function (array $parts): string {
    $body = '';
    $central = '';
    $flags = 0x0800;
    $method = 0;
    $dosTime = 0;
    $dosDate = 0;

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'];
        $versionMadeBy = $part['versionMadeBy'];
        $versionNeededToExtract = $part['versionNeededToExtract'];
        $crc32 = (int) sprintf('%u', crc32($data));
        $size = strlen($data);
        $localHeaderOffset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            $versionNeededToExtract,
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

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            $versionMadeBy,
            $versionNeededToExtract,
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
            $localHeaderOffset
        );
        $central .= $name;
    }

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
    'summarizes zip package manifest creator host systems without changing manifest hash contract' => static function (TestRunner $t) use ($buildStoredZipWithCreatorHosts): void {
        $documentXml = '<w:document><w:body><w:p>creator host manifest</w:p></w:body></w:document>';
        $themeXml = '<a:theme><a:themeElements/></a:theme>';
        $mediaBytes = "windows media bytes\n";
        $legacyXml = '<item>unknown creator host</item>';
        $zip = $buildStoredZipWithCreatorHosts([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'versionMadeBy' => 0x0314,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/theme/theme1.xml',
                'data' => $themeXml,
                'versionMadeBy' => 0x030a,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/media/image.png',
                'data' => $mediaBytes,
                'versionMadeBy' => 0x0a14,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'customXml/item1.xml',
                'data' => $legacyXml,
                'versionMadeBy' => 0x3f14,
                'versionNeededToExtract' => 20,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $profile = $package->packagePartProfilePreflight();
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $manifestByHost = array_column($manifest['creatorHostSystemSummaries'], null, 'madeByHostSystem');
        $profileByHost = array_column($profile['creatorHostSystemSummaries'], null, 'madeByHostSystem');
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
        $t->same(3, $manifest['creatorHostSystemBucketCount']);
        $t->same(1, $manifest['unknownCreatorHostSystemEntryCount']);
        $t->same(1, $manifest['creatorVersionBelowNeededEntryCount']);
        $t->same(2, $manifest['creatorHostSystemIssueCount']);
        $t->same([
            'creator-version-below-version-needed',
            'unknown-creator-host-system',
        ], $manifest['creatorHostSystemIssues']);

        $t->same($manifest['creatorHostSystemSummaries'], $profile['creatorHostSystemSummaries']);
        $t->same($manifest['creatorHostSystemIssues'], $profile['creatorHostSystemIssues']);
        $t->same(3, $profile['creatorHostSystemBucketCount']);
        $t->same(1, $profile['unknownCreatorHostSystemEntryCount']);
        $t->same(1, $profile['creatorVersionBelowNeededEntryCount']);

        $t->same([
            'madeByHostSystem' => 3,
            'madeByHostSystemName' => 'unix',
            'isKnown' => true,
            'entryCount' => 2,
            'fileEntryCount' => 2,
            'directoryEntryCount' => 0,
            'compressedBytes' => strlen($documentXml) + strlen($themeXml),
            'uncompressedBytes' => strlen($documentXml) + strlen($themeXml),
            'creatorVersionMeetsNeededEntryCount' => 1,
            'creatorVersionBelowNeededEntryCount' => 1,
            'creatorVersionComparisonCounts' => [
                'below-needed' => 1,
                'equals-needed' => 1,
                'above-needed' => 0,
            ],
            'roles' => [],
            'entryNames' => ['word/document.xml', 'word/theme/theme1.xml'],
            'issues' => ['creator-version-below-version-needed'],
            'issueCounts' => ['creator-version-below-version-needed' => 1],
        ], $manifestByHost[3]);
        $t->same($manifestByHost[3], $profileByHost[3]);

        $t->same([
            'madeByHostSystem' => 10,
            'madeByHostSystemName' => 'windows-ntfs',
            'isKnown' => true,
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'compressedBytes' => strlen($mediaBytes),
            'uncompressedBytes' => strlen($mediaBytes),
            'creatorVersionMeetsNeededEntryCount' => 1,
            'creatorVersionBelowNeededEntryCount' => 0,
            'creatorVersionComparisonCounts' => [
                'below-needed' => 0,
                'equals-needed' => 1,
                'above-needed' => 0,
            ],
            'roles' => [],
            'entryNames' => ['word/media/image.png'],
            'issues' => [],
            'issueCounts' => [],
        ], $manifestByHost[10]);

        $t->same([
            'madeByHostSystem' => 63,
            'madeByHostSystemName' => 'unknown',
            'isKnown' => false,
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'compressedBytes' => strlen($legacyXml),
            'uncompressedBytes' => strlen($legacyXml),
            'creatorVersionMeetsNeededEntryCount' => 1,
            'creatorVersionBelowNeededEntryCount' => 0,
            'creatorVersionComparisonCounts' => [
                'below-needed' => 0,
                'equals-needed' => 1,
                'above-needed' => 0,
            ],
            'roles' => [],
            'entryNames' => ['customXml/item1.xml'],
            'issues' => ['unknown-creator-host-system'],
            'issueCounts' => ['unknown-creator-host-system' => 1],
        ], $manifestByHost[63]);

        $themeEntry = $manifest['entries'][1];
        $t->same('word/theme/theme1.xml', $themeEntry['name']);
        $t->same(3, $themeEntry['madeByHostSystem']);
        $t->same('unix', $themeEntry['madeByHostSystemName']);
        $t->same(10, $themeEntry['madeByVersion']);
        $t->same(0x030a, $themeEntry['versionMadeBy']);
        $t->same(20, $themeEntry['versionNeededToExtract']);
        $t->same(false, $themeEntry['creatorVersionMeetsNeeded']);

        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
        $t->same($profile, $strict['packagePartProfile']);
        $t->same($profile, $raw['packagePartProfile']);
        $t->same($profile, $raw['strictImport']['packagePartProfile']);
        $t->same(false, $strict['isValid']);
        $t->contains('creator-version-below-version-needed', implode(',', $strict['diagnostics']));
        $t->same(false, $raw['isValid']);
        $t->same(true, $raw['canInstantiate']);
        $t->contains('unknown-creator-host-systems', implode(',', $raw['diagnostics']));
        $t->contains('creator-version-below-version-needed', implode(',', $raw['diagnostics']));
    },
];
