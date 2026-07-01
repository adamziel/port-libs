<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP modification time sources by loaded package part' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_timestamp_source_fixture_parts(),
            'docx timestamp source review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $sources = docx_zip_timestamp_source_index_by($summary['partZipTimestampSources'], 'timestampSourceKey');
        $expectedCounts = docx_zip_timestamp_source_counts($inventory);

        $t->same('ZIP timestamp source buckets.', $document->children[0]->attr('text'));
        $t->same(count($expectedCounts), $summary['partZipTimestampSourceCount']);
        $t->same($expectedCounts, $summary['partZipTimestampSourceCounts']);
        $t->same(
            docx_zip_timestamp_source_sums($inventory, 'bytes'),
            $summary['partZipTimestampSourceByteLengths']
        );
        $t->same(
            docx_zip_timestamp_source_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipTimestampSourceRecordBytes']
        );
        $t->same(4, $summary['partZipTimestampSourceModifiedPartCount']);
        $t->same(0, $summary['partZipTimestampSourceIssuePartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipTimestampSourceCounts']));

        $extended = $sources['extended-timestamp'];
        $t->same('extended-timestamp', $extended['timestampSource']);
        $t->same(2, $extended['partCount']);
        $t->same(2, $extended['modifiedPartCount']);
        $t->same([
            '[Content_Types].xml',
            'word/document.xml',
        ], $extended['partNames']);
        $t->same(['/' => 1, 'word/' => 1], $extended['directoryRootCounts']);
        $t->same(['extended-timestamp' => 2], $extended['localTimestampSourceCounts']);
        $t->same(['extended-timestamp' => 2], $extended['centralTimestampSourceCounts']);
        $t->same(['default' => 1, 'override' => 1], $extended['contentTypeSourceCounts']);
        $t->same(1, $extended['roleCounts']['content-types']);
        $t->same(1, $extended['roleCounts']['office-document']);
        $t->same('word/document.xml', $extended['latestModifiedPart']['partName']);
        $t->same(false, array_key_exists('contents', $extended['latestModifiedPart']));
        $t->same(
            $inventory['word/document.xml']['sourceRecordBytes'],
            $extended['latestModifiedPart']['sourceRecordBytes']
        );

        $dos = $sources['dos'];
        $t->same('dos', $dos['timestampSource']);
        $t->same(2, $dos['partCount']);
        $t->same(2, $dos['modifiedPartCount']);
        $t->same([
            'word/_rels/document.xml.rels',
            'word/media/review.png',
        ], $dos['partNames']);
        $t->same(['word/' => 2], $dos['directoryRootCounts']);
        $t->same(['dos' => 2], $dos['localTimestampSourceCounts']);
        $t->same(['dos' => 2], $dos['centralTimestampSourceCounts']);
        $t->same(2, $dos['dosTimestampPartCount']);
        $t->same(0, $dos['extendedTimestampPartCount']);
        $t->same(1, $dos['roleCounts']['document-relationship-target']);
        $t->same(1, $dos['roleCounts']['office-document-relationships']);
        $t->same(2, count($dos['partNames']));

        $missing = $sources['(missing)'];
        $t->same(null, $missing['timestampSource']);
        $t->same(2, $missing['partCount']);
        $t->same(0, $missing['modifiedPartCount']);
        $t->same([
            '_rels/.rels',
            'customXml/raw.bin',
        ], $missing['partNames']);
        $t->same(['(missing)' => 2], $missing['localTimestampSourceCounts']);
        $t->same(['(missing)' => 2], $missing['centralTimestampSourceCounts']);
        $t->same(['_rels/' => 1, 'customXml/' => 1], $missing['directoryRootCounts']);
        $t->same(['default' => 1, 'missing' => 1], $missing['contentTypeSourceCounts']);
        $t->same(null, $missing['earliestModifiedPart']);
        $t->same('_rels/.rels', $missing['largestSourceRecordPart']['partName']);
        $t->same(false, array_key_exists('contents', $missing['largestSourceRecordPart']));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, modifiedAt?:int, modifiedDosTime?:int, modifiedDosDate?:int}>
 */
function docx_zip_timestamp_source_fixture_parts(): array
{
    return [
        [
            'name' => '[Content_Types].xml',
            'compressionMethod' => 0,
            'modifiedAt' => 1780478000,
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
        ['name' => '_rels/.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/_rels/document.xml.rels',
            'compressionMethod' => 8,
            'modifiedDosTime' => 19400,
            'modifiedDosDate' => 23747,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/document.xml',
            'compressionMethod' => 8,
            'modifiedAt' => 1780479017,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP timestamp source buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('T', 320),
            'compressionMethod' => 0,
            'modifiedDosTime' => 19400,
            'modifiedDosDate' => 23747,
        ],
        ['name' => 'customXml/raw.bin', 'data' => 'raw timestamp-source payload', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_timestamp_source_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_timestamp_source_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $key = docx_zip_timestamp_source_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_timestamp_source_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $key = docx_zip_timestamp_source_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_timestamp_source_key(array $part): string
{
    return is_string($part['zipTimestampSource'] ?? null) && $part['zipTimestampSource'] !== ''
        ? $part['zipTimestampSource']
        : '(missing)';
}
