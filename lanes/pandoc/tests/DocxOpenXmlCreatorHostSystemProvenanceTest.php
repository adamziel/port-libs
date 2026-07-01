<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

$docxCreatorHostCrc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
$docxCreatorHostBuildZipPackage = static function (array $entries, string $comment = '') use ($docxCreatorHostCrc32): string {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'] ?? '';
        $method = $entry['method'] ?? 0;
        $flags = $entry['flags'] ?? 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate ZIP entry {$name}");
        }

        $localExtra = $entry['localExtra'] ?? '';
        $centralExtra = $entry['centralExtra'] ?? $localExtra;
        $offset = strlen($body);
        $crc = $docxCreatorHostCrc32($data);
        $versionNeededToExtract = $entry['versionNeededToExtract'] ?? 20;
        $localVersionNeededToExtract = $entry['localVersionNeededToExtract'] ?? $versionNeededToExtract;
        $versionMadeBy = $entry['versionMadeBy'] ?? 0x0314;
        $externalAttributes = $entry['externalAttributes'] ?? 0;

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            $localVersionNeededToExtract,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            strlen($localExtra)
        );
        $body .= $name . $localExtra . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            $versionMadeBy,
            $versionNeededToExtract,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            strlen($centralExtra),
            0,
            0,
            $entry['internalAttributes'] ?? 0,
            $externalAttributes,
            $offset
        );
        $central .= $name . $centralExtra;
    }

    $centralOffset = strlen($body);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $centralOffset, strlen($comment))
        . $comment;
};
$docxCreatorHostVersionMadeBy = static fn (int $hostSystem, int $madeByVersion): int => ($hostSystem << 8) | $madeByVersion;
$docxCreatorHostBuildPackage = static function () use (
    $docxCreatorHostBuildZipPackage,
    $docxCreatorHostVersionMadeBy
): ZipPackage {
    return ZipPackage::fromString($docxCreatorHostBuildZipPackage([
        [
            'name' => '[Content_Types].xml',
            'method' => 0,
            'versionMadeBy' => $docxCreatorHostVersionMadeBy(3, 20),
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        ],
        [
            'name' => '_rels/.rels',
            'method' => 8,
            'versionMadeBy' => $docxCreatorHostVersionMadeBy(3, 45),
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/document.xml',
            'method' => 8,
            'versionMadeBy' => $docxCreatorHostVersionMadeBy(255, 10),
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>DOCX creator host system provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/_rels/document.xml.rels',
            'method' => 8,
            'versionMadeBy' => $docxCreatorHostVersionMadeBy(10, 45),
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUnixBelow" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/unix-below.png"/>
  <Relationship Id="rNtfsAbove" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/ntfs-above.png"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/media/unix-below.png',
            'method' => 0,
            'versionMadeBy' => $docxCreatorHostVersionMadeBy(3, 10),
            'data' => 'UNIX-BELOW-CREATOR-VERSION',
        ],
        [
            'name' => 'word/media/ntfs-above.png',
            'method' => 0,
            'versionMadeBy' => $docxCreatorHostVersionMadeBy(10, 45),
            'data' => 'NTFS-ABOVE-CREATOR-VERSION',
        ],
    ], 'docx creator host system provenance'));
};
$docxCreatorHostIndexByName = static function (array $entries): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $name = $entry['name'] ?? ($entry['partName'] ?? ($entry['packagePath'] ?? null));
        if (is_string($name) && $name !== '') {
            $indexed[$name] = $entry;
        }
    }

    return $indexed;
};
$docxCreatorHostIndexById = static function (array $entries): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $id = $entry['id'] ?? null;
        if (is_int($id)) {
            $indexed[$id] = $entry;
        }
    }

    ksort($indexed);

    return $indexed;
};

return [
    'records mapped DOCX ZIP creator host-system provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedDocxZipCreatorHostSystemProvenanceCases']);
        $t->same(115, $manifest['benchmarkDenominator']['breakdown']['docxZipCreatorHostSystemProvenanceAssertions']);
        $t->same(1, $manifest['inventory']['mappedDocxZipCreatorHostSystemProvenanceCases']);
        $t->same(115, $manifest['inventory']['docxZipCreatorHostSystemProvenanceAssertions']);
    },

    'carries DOCX ZIP creator host-system provenance through package identity' => static function (TestRunner $t) use (
        $docxCreatorHostBuildPackage,
        $docxCreatorHostIndexByName,
        $docxCreatorHostIndexById
    ): void {
        $package = $docxCreatorHostBuildPackage();
        $creatorPreflight = $package->creatorHostSystemPreflight();
        $document = (new DocxOpenXmlReader())->readZipPackage($package);
        $packageProvenance = $document->attr('docx')['packageProvenance'];
        $summary = $packageProvenance['summary'];
        $zipPackage = $packageProvenance['zipPackage'];
        $identity = $packageProvenance['packageIdentity'];
        $parts = $packageProvenance['parts'];
        $identityParts = $docxCreatorHostIndexByName($identity['packageEntries']);
        $zipEntries = $docxCreatorHostIndexByName($zipPackage['entries']);

        $t->same('DOCX creator host system provenance.', $document->children[0]->attr('text'));
        $t->same($creatorPreflight, $zipPackage['creatorHostSystems']);
        $t->same(6, $summary['zipCreatorHostSystemEntryCount']);
        $t->same(5, $summary['zipKnownCreatorHostSystemEntryCount']);
        $t->same(1, $summary['zipUnknownCreatorHostSystemEntryCount']);
        $t->same(4, $summary['zipCreatorVersionMeetsNeededEntryCount']);
        $t->same(2, $summary['zipCreatorVersionBelowNeededEntryCount']);
        $t->same(1, $summary['zipCreatorVersionEqualNeededEntryCount']);
        $t->same(3, $summary['zipCreatorVersionAboveNeededEntryCount']);
        $t->same(1, $summary['zipCreatorVersionBelowNeededKnownHostEntryCount']);
        $t->same(1, $summary['zipCreatorVersionBelowNeededUnknownHostEntryCount']);
        $t->same([
            'below-needed' => 2,
            'equals-needed' => 1,
            'above-needed' => 3,
        ], $summary['zipCreatorVersionComparisonCounts']);
        $t->same([
            3 => ['id' => 3, 'name' => 'unix', 'isKnown' => true, 'entryCount' => 3],
            10 => ['id' => 10, 'name' => 'windows-ntfs', 'isKnown' => true, 'entryCount' => 2],
            255 => ['id' => 255, 'name' => 'unknown', 'isKnown' => false, 'entryCount' => 1],
        ], $docxCreatorHostIndexById($summary['zipCreatorHostSystems']));
        $t->same($creatorPreflight['unknownEntries'], $summary['zipUnknownCreatorHostSystemEntries']);
        $t->same($creatorPreflight['creatorVersionBelowNeededEntries'], $summary['zipCreatorVersionBelowNeededEntries']);
        $t->same($creatorPreflight['entries'], $summary['zipCreatorHostSystemEntries']);
        $t->same('docx-zip-creator-host-system-metadata-only', $summary['zipCreatorHostSystemReviewPolicy']);
        $t->same(false, $summary['zipCreatorHostSystemCanExposeBytes']);

        foreach ([
            'zipCreatorHostSystemEntryCount',
            'zipKnownCreatorHostSystemEntryCount',
            'zipUnknownCreatorHostSystemEntryCount',
            'zipCreatorVersionMeetsNeededEntryCount',
            'zipCreatorVersionBelowNeededEntryCount',
            'zipCreatorVersionEqualNeededEntryCount',
            'zipCreatorVersionAboveNeededEntryCount',
            'zipCreatorVersionBelowNeededKnownHostEntryCount',
            'zipCreatorVersionBelowNeededUnknownHostEntryCount',
            'zipCreatorVersionComparisonCounts',
            'zipCreatorHostSystems',
            'zipUnknownCreatorHostSystemEntries',
            'zipCreatorVersionBelowNeededEntries',
            'zipCreatorHostSystemEntries',
            'zipCreatorHostSystemReviewPolicy',
            'zipCreatorHostSystemCanExposeBytes',
        ] as $key) {
            $t->same($summary[$key], $identity[$key]);
        }

        $t->same(['word/document.xml'], array_column($identity['zipUnknownCreatorHostSystemEntries'], 'name'));
        $t->same(
            ['word/document.xml', 'word/media/unix-below.png'],
            array_column($identity['zipCreatorVersionBelowNeededEntries'], 'name')
        );
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/media/unix-below.png',
            'word/media/ntfs-above.png',
        ], array_column($identity['zipCreatorHostSystemEntries'], 'name'));

        foreach ([$parts, $identityParts, $zipEntries] as $entries) {
            $unknown = $entries['word/document.xml'];
            $unixBelow = $entries['word/media/unix-below.png'];
            $ntfsAbove = $entries['word/media/ntfs-above.png'];

            $t->same(255, $unknown['zipMadeByHostSystem']);
            $t->same('unknown', $unknown['zipMadeByHostSystemName']);
            $t->same(10, $unknown['zipMadeByVersion']);
            $t->same(20, $unknown['zipVersionNeededToExtract']);
            $t->same(false, $unknown['zipCreatorVersionMeetsNeeded']);
            $t->same('below-needed', $unknown['zipCreatorVersionComparison']);
            $t->same(-10, $unknown['zipCreatorVersionDelta']);
            $t->same(false, $unknown['zipCreatorHostSystemKnown']);
            $t->same([
                'unknown-creator-host-system',
                'creator-version-below-version-needed',
            ], $unknown['zipCreatorHostSystemIssues']);

            $t->same(3, $unixBelow['zipMadeByHostSystem']);
            $t->same('unix', $unixBelow['zipMadeByHostSystemName']);
            $t->same(10, $unixBelow['zipMadeByVersion']);
            $t->same(20, $unixBelow['zipVersionNeededToExtract']);
            $t->same(false, $unixBelow['zipCreatorVersionMeetsNeeded']);
            $t->same(['creator-version-below-version-needed'], $unixBelow['zipCreatorHostSystemIssues']);

            $t->same(10, $ntfsAbove['zipMadeByHostSystem']);
            $t->same('windows-ntfs', $ntfsAbove['zipMadeByHostSystemName']);
            $t->same(45, $ntfsAbove['zipMadeByVersion']);
            $t->same(20, $ntfsAbove['zipVersionNeededToExtract']);
            $t->same(true, $ntfsAbove['zipCreatorVersionMeetsNeeded']);
            $t->same('above-needed', $ntfsAbove['zipCreatorVersionComparison']);
            $t->same(25, $ntfsAbove['zipCreatorVersionDelta']);
            $t->same([], $ntfsAbove['zipCreatorHostSystemIssues']);
            $t->same(false, array_key_exists('contents', $unknown));
        }

        $t->same(false, $identity['canExposeBytes']);
        $t->same('docx-package-identity-metadata-only', $identity['byteExposurePolicy']);
    },
];
