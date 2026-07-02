<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by package part top-level segments' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_top_level_segment_fixture_parts(),
            'docx zip source top-level segment review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $segments = docx_zip_source_record_top_level_segment_index_by(
            $summary['partZipSourceRecordPackagePartTopLevelSegments'],
            'topLevelSegment'
        );
        $caseFoldSegments = docx_zip_source_record_top_level_segment_index_by(
            $summary['partZipSourceRecordPackagePartCaseFoldTopLevelSegments'],
            'caseFoldTopLevelSegment'
        );

        $t->same('ZIP source top-level segment buckets.', $document->children[0]->attr('text'));
        $t->same(
            docx_zip_source_record_top_level_segment_counts($inventory, false),
            $summary['partZipSourceRecordPackagePartTopLevelSegmentCounts']
        );
        $t->same(6, $summary['partZipSourceRecordPackagePartTopLevelSegmentCount']);
        $t->same(
            docx_zip_source_record_top_level_segment_sums($inventory, false, 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartTopLevelSegmentBytes']
        );
        $t->same(1, $summary['partZipSourceRecordDuplicatePackagePartTopLevelSegmentCount']);
        $t->same(3, $summary['partZipSourceRecordDuplicatePackagePartTopLevelSegmentPartCount']);
        $t->same(['word'], $summary['partZipSourceRecordDuplicatePackagePartTopLevelSegments']);
        $t->same(0, $summary['partZipSourceRecordPackagePartTopLevelSegmentDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartTopLevelSegmentIssuePartCount']);

        $t->same(
            docx_zip_source_record_top_level_segment_counts($inventory, true),
            $summary['partZipSourceRecordPackagePartCaseFoldTopLevelSegmentCounts']
        );
        $t->same(4, $summary['partZipSourceRecordPackagePartCaseFoldTopLevelSegmentCount']);
        $t->same(
            docx_zip_source_record_top_level_segment_sums($inventory, true, 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartCaseFoldTopLevelSegmentBytes']
        );
        $t->same(1, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldTopLevelSegmentCount']);
        $t->same(5, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldTopLevelSegmentPartCount']);
        $t->same(['word'], $summary['partZipSourceRecordDuplicatePackagePartCaseFoldTopLevelSegments']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldTopLevelSegmentDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldTopLevelSegmentIssuePartCount']);

        foreach ([
            'partZipSourceRecordPackagePartTopLevelSegmentCount',
            'partZipSourceRecordPackagePartTopLevelSegmentCounts',
            'partZipSourceRecordPackagePartTopLevelSegmentBytes',
            'partZipSourceRecordDuplicatePackagePartTopLevelSegments',
            'partZipSourceRecordPackagePartTopLevelSegments',
            'partZipSourceRecordPackagePartCaseFoldTopLevelSegmentCount',
            'partZipSourceRecordPackagePartCaseFoldTopLevelSegmentCounts',
            'partZipSourceRecordPackagePartCaseFoldTopLevelSegmentBytes',
            'partZipSourceRecordDuplicatePackagePartCaseFoldTopLevelSegments',
            'partZipSourceRecordPackagePartCaseFoldTopLevelSegments',
        ] as $identityField) {
            $t->same($summary[$identityField], $identity[$identityField]);
        }

        $word = $segments['word'];
        $t->same(3, $word['partCount']);
        $t->same(['word/' => 3], $word['directoryRootCounts']);
        $t->same([2 => 1, 3 => 2], $word['pathDepthCounts']);
        $t->same([
            'word' => 1,
            'word/_rels' => 1,
            'word/media' => 1,
        ], $word['directoryCounts']);
        $t->same(['png' => 1, 'rels' => 1, 'xml' => 1], $word['partExtensionCounts']);
        $t->same(['default' => 2, 'override' => 1], $word['contentTypeSourceCounts']);
        $t->same(1, $word['roleCounts']['document-relationship-target']);
        $t->same(1, $word['roleCounts']['office-document']);
        $t->same(1, $word['roleCounts']['office-document-relationships']);
        $t->same(1, $word['roleCounts']['relationship-part']);
        $t->same(1, $word['roleCounts']['root-relationship-target']);
        $t->same([
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/lower.png',
        ], $word['partNames']);
        $t->same(false, array_key_exists('contents', $word['largestSourceRecordPart']));

        $caseFoldWord = $caseFoldSegments['word'];
        $t->same(5, $caseFoldWord['partCount']);
        $t->same(3, $caseFoldWord['topLevelSegmentVariantCount']);
        $t->same(['WORD' => 1, 'Word' => 1, 'word' => 3], $caseFoldWord['topLevelSegmentCounts']);
        $t->same(['WORD/' => 1, 'Word/' => 1, 'word/' => 3], $caseFoldWord['directoryRootCounts']);
        $t->same(['png' => 3, 'rels' => 1, 'xml' => 1], $caseFoldWord['partExtensionCounts']);
        $t->same(['default' => 4, 'override' => 1], $caseFoldWord['contentTypeSourceCounts']);
        $t->same(3, $caseFoldWord['roleCounts']['document-relationship-target']);
        $t->same([
            'WORD/media/caps.png',
            'Word/media/upper.png',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/lower.png',
        ], $caseFoldWord['partNames']);
        $t->same('Word/media/upper.png', $caseFoldWord['largestSourceRecordPart']['partName']);
        $t->same('Word', $caseFoldWord['largestSourceRecordPart']['topLevelSegment']);
        $t->same('word', $caseFoldWord['largestSourceRecordPart']['caseFoldTopLevelSegment']);
        $t->same(
            $inventory['Word/media/upper.png']['sourceRecordBytes'],
            $caseFoldWord['largestSourceRecordPart']['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $caseFoldWord['largestSourceRecordPart']));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_top_level_segment_fixture_parts(): array
{
    return [
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => <<<'XML'
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
        ['name' => 'word/_rels/document.xml.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
  <Relationship Id="rUpperMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../Word/media/upper.png"/>
  <Relationship Id="rCapsMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../WORD/media/caps.png"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP source top-level segment buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        ['name' => 'word/media/lower.png', 'data' => str_repeat('L', 64), 'compressionMethod' => 8],
        ['name' => 'Word/media/upper.png', 'data' => str_repeat('U', 512), 'compressionMethod' => 0],
        ['name' => 'WORD/media/caps.png', 'data' => str_repeat('C', 128), 'compressionMethod' => 0],
        ['name' => 'customXml/data.xml', 'data' => '<data>top-level</data>', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_top_level_segment_index_by(array $items, string $key): array
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
function docx_zip_source_record_top_level_segment_counts(array $inventory, bool $caseFold): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_top_level_segment_key($part, $caseFold);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_top_level_segment_sums(array $inventory, bool $caseFold, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_top_level_segment_key($part, $caseFold);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_top_level_segment_key(array $part, bool $caseFold): string
{
    $segment = is_string($part['topLevelSegment'] ?? null) ? $part['topLevelSegment'] : '';

    return $caseFold ? strtolower($segment) : $segment;
}
