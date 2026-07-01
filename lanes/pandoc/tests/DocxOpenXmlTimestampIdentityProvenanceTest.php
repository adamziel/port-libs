<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

$docxTimestampIdentityNtfsFiletime = static function (int $timestamp): string {
    $filetime = ($timestamp + 11644473600) * 10000000;
    $low = $filetime % 4294967296;
    $high = intdiv($filetime, 4294967296);

    return pack('VV', $low, $high);
};
$docxTimestampIdentityNtfsExtra = static function (
    int $modifiedAt,
    int $accessedAt,
    int $createdAt
) use ($docxTimestampIdentityNtfsFiletime): string {
    $payload = pack('V', 0)
        . pack('vv', 0x0001, 24)
        . $docxTimestampIdentityNtfsFiletime($modifiedAt)
        . $docxTimestampIdentityNtfsFiletime($accessedAt)
        . $docxTimestampIdentityNtfsFiletime($createdAt);

    return pack('vv', 0x000a, strlen($payload)) . $payload;
};
$docxTimestampIdentityIndexByPart = static function (array $entries): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $part = $entry['partName'] ?? ($entry['packagePath'] ?? null);
        if (is_string($part) && $part !== '') {
            $indexed[$part] = $entry;
        }
    }

    return $indexed;
};

return [
    'carries DOCX ZIP timestamp provenance through deterministic package identity' => static function (TestRunner $t) use (
        $docxTimestampIdentityNtfsExtra,
        $docxTimestampIdentityIndexByPart
    ): void {
        $extendedModified = gmmktime(3, 4, 5, 4, 5, 2024);
        $dosModified = gmmktime(4, 5, 6, 2, 3, 2023);
        $ntfsModified = gmmktime(7, 8, 10, 1, 2, 2022);
        $ntfsAccessed = gmmktime(8, 9, 10, 1, 3, 2022);
        $ntfsCreated = gmmktime(9, 10, 12, 1, 4, 2022);
        $dosTime = (4 << 11) | (5 << 5) | intdiv(6, 2);
        $dosDate = ((2023 - 1980) << 9) | (2 << 5) | 3;
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>DOCX timestamp identity provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;
        $mediaBytes = 'TIMESTAMP-IDENTITY-PNG-BYTES';
        $package = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML,
            ],
            [
                'name' => '_rels/.rels',
                'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
            ],
            [
                'name' => 'docProps/core.xml',
                'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Timestamp identity review</dc:title>
</cp:coreProperties>
XML,
                'extraFieldData' => $docxTimestampIdentityNtfsExtra($ntfsModified, $ntfsAccessed, $ntfsCreated),
            ],
            [
                'name' => 'word/_rels/document.xml.rels',
                'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'modifiedAt' => $extendedModified,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => $mediaBytes,
                'modifiedDosTime' => $dosTime,
                'modifiedDosDate' => $dosDate,
            ],
        ], 'docx timestamp identity provenance');

        $document = (new DocxOpenXmlReader())->readZipPackage($package);
        $packageProvenance = $document->attr('docx')['packageProvenance'];
        $summary = $packageProvenance['summary'];
        $timestamps = $packageProvenance['zipPackage']['timestamps'];
        $identity = $packageProvenance['packageIdentity'];
        $identityParts = $docxTimestampIdentityIndexByPart($identity['packageEntries']);

        $t->same('DOCX timestamp identity provenance.', $document->children[0]->attr('text'));
        $t->same($timestamps, $identity['zipTimestamps']);
        $t->same(true, $identity['zipTimestampPreflightPresent']);
        $t->same(count($package->entries()), $identity['zipTimestampEntryCount']);
        $t->same(3, $identity['zipTimestampedEntryCount']);
        $t->same(2, $identity['zipDosTimestampEntryCount']);
        $t->same(1, $identity['zipExtendedTimestampEntryCount']);
        $t->same(1, $identity['zipNtfsTimestampEntryCount']);
        $t->same(1, $identity['zipAccessedTimestampEntryCount']);
        $t->same(1, $identity['zipCreatedTimestampEntryCount']);
        $t->same(['dos' => 1, 'extended' => 1, 'none' => count($package->entries()) - 3, 'ntfs' => 1], $identity['zipTimestampSourceCounts']);
        $t->same(['docProps/core.xml', 'word/document.xml', 'word/media/review.png'], $identity['zipTimestampedEntryNames']);
        $t->same($timestamps['timestampedEntries'], $identity['zipTimestampReviewEntries']);
        $t->same('docProps/core.xml', $identity['zipEarliestModifiedEntry']['packagePath']);
        $t->same($ntfsModified, $identity['zipEarliestModifiedEntry']['lastModifiedTimestamp']);
        $t->same('word/document.xml', $identity['zipLatestModifiedEntry']['packagePath']);
        $t->same($extendedModified, $identity['zipLatestModifiedEntry']['lastModifiedTimestamp']);
        $t->same('docx-zip-timestamp-metadata-only', $identity['zipTimestampReviewPolicy']);

        $t->same($identity['zipTimestampedEntryCount'], $summary['packageIdentityZipTimestampedEntryCount']);
        $t->same($identity['zipTimestampSourceCounts'], $summary['packageIdentityZipTimestampSourceCounts']);
        $t->same($identity['zipTimestampedEntryNames'], $summary['packageIdentityZipTimestampedEntryNames']);
        $t->same('docx-zip-timestamp-metadata-only', $summary['packageIdentityZipTimestampReviewPolicy']);

        $core = $identityParts['docProps/core.xml'];
        $doc = $identityParts['word/document.xml'];
        $media = $identityParts['word/media/review.png'];

        $t->same(true, $core['zipHasNtfsTimestamp']);
        $t->same($ntfsModified, $core['zipNtfsModifiedTimestamp']);
        $t->same($ntfsAccessed, $core['zipNtfsAccessedTimestamp']);
        $t->same($ntfsCreated, $core['zipNtfsCreatedTimestamp']);
        $t->same('ntfs', $core['zipLastModifiedSource']);
        $t->same(gmdate('Y-m-d\TH:i:s\Z', $ntfsModified), $core['zipLastModifiedIso8601']);

        $t->same(true, $doc['zipHasExtendedTimestamp']);
        $t->same($extendedModified, $doc['zipExtendedModifiedTimestamp']);
        $t->same($extendedModified, $doc['zipLastModifiedTimestamp']);
        $t->same('extended', $doc['zipLastModifiedSource']);
        $t->same('docx-zip-timestamp-metadata-only', $doc['zipTimestampReviewPolicy']);

        $t->same(true, $media['zipHasDosLastModifiedTimestamp']);
        $t->same($dosTime, $media['zipModifiedDosTime']);
        $t->same($dosDate, $media['zipModifiedDosDate']);
        $t->same(sprintf('%04x', $dosTime), $media['zipModifiedDosTimeHex']);
        $t->same(sprintf('%04x', $dosDate), $media['zipModifiedDosDateHex']);
        $t->same($dosModified, $media['zipDosLastModifiedTimestamp']);
        $t->same('dos', $media['zipLastModifiedSource']);

        $encoded = json_encode($identity);
        $t->true(is_string($encoded), 'identity timestamp metadata should encode for review');
        $t->true(!str_contains((string) $encoded, $mediaBytes), 'identity must not expose media bytes');
        $t->true(!str_contains((string) $encoded, $documentXml), 'identity must not expose document XML bytes');
        $t->same(false, $identity['canExposeBytes']);
        $t->same('docx-package-identity-metadata-only', $identity['byteExposurePolicy']);
    },
];
