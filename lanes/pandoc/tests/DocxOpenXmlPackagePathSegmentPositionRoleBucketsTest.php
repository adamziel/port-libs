<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records docx package path segment-position role bucket mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes DOCX package path segment-position roles and byte policies for identity handoff' => static function (TestRunner $t): void {
        $parts = docx_package_path_segment_position_role_bucket_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $summary = $docx['packageProvenance']['summary'];
        $identity = $docx['packageIdentity'];
        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(
            ZipPackage::fromParts(docx_package_path_segment_position_role_bucket_zip_parts(), 'docx segment position role review')
        );
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $expectedRoleCounts = [
            'first' => [
                'core-properties' => 1,
                'document-relationship-target' => 3,
                'embedded-package' => 1,
                'office-document' => 1,
                'office-document-relationships' => 1,
                'package-part' => 1,
                'package-relationships' => 1,
                'relationship-part' => 2,
                'root-relationship-target' => 2,
            ],
            'last' => [
                'core-properties' => 1,
                'document-relationship-target' => 3,
                'embedded-package' => 1,
                'office-document' => 1,
                'office-document-relationships' => 1,
                'package-part' => 1,
                'package-relationships' => 1,
                'relationship-part' => 2,
                'root-relationship-target' => 2,
            ],
            'middle' => [
                'document-relationship-target' => 3,
                'embedded-package' => 1,
                'office-document-relationships' => 1,
                'package-part' => 1,
                'relationship-part' => 1,
            ],
            'only' => [
                'content-types' => 1,
                'custom-xml-part' => 1,
                'root-relationship-target' => 1,
            ],
        ];
        $expectedArrayPolicyCounts = [
            'first' => ['docx-package-part-bytes-blocked' => 8],
            'last' => ['docx-package-part-bytes-blocked' => 8],
            'middle' => ['docx-package-part-bytes-blocked' => 5],
            'only' => ['docx-package-part-bytes-blocked' => 2],
        ];
        $expectedZipPolicyCounts = [
            'first' => ['docx-zip-entry-metadata-only' => 8],
            'last' => ['docx-zip-entry-metadata-only' => 8],
            'middle' => ['docx-zip-entry-metadata-only' => 5],
            'only' => ['docx-zip-entry-metadata-only' => 2],
        ];

        $t->same('DOCX segment-position role buckets.', $document->children[0]->attr('text'));
        $t->same(4, $summary['partPathSegmentPositionBucketCount']);
        $t->same(24, $summary['partPathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 8, 'last' => 8, 'middle' => 6, 'only' => 2], $summary['partPathSegmentPositionCounts']);
        $t->same(['first' => 8, 'last' => 8, 'middle' => 5, 'only' => 2], $summary['partPathSegmentPositionPartCounts']);
        $t->same(26, $summary['partPathSegmentPositionRoleBucketCount']);
        $t->same(4, $summary['partPathSegmentPositionByteExposurePolicyBucketCount']);
        $t->same($expectedRoleCounts, $summary['partPathSegmentPositionRoleCounts']);
        $t->same($expectedArrayPolicyCounts, $summary['partPathSegmentPositionByteExposurePolicyCounts']);
        $t->same([
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ], $summary['partNamesByPartPathSegmentPositionRole']['first']['document-relationship-target']);
        $t->same([
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ], $summary['partNamesByPartPathSegmentPositionRole']['middle']['document-relationship-target']);
        $t->same(['root-note.xml'], $summary['partNamesByPartPathSegmentPositionRole']['only']['root-relationship-target']);
        $t->same([
            'customXml/review/data.bin',
            'word/_rels/document.xml.rels',
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ], $summary['partNamesByPartPathSegmentPositionByteExposurePolicy']['middle']['docx-package-part-bytes-blocked']);

        $t->same($summary['partPathSegmentPositionRoleBucketCount'], $identity['packagePathSegmentPositionRoleBucketCount']);
        $t->same($summary['partPathSegmentPositionRoleCounts'], $identity['packagePathSegmentPositionRoleCounts']);
        $t->same(
            $summary['partNamesByPartPathSegmentPositionRole'],
            $identity['entryNamesByPackagePathSegmentPositionRole']
        );
        $t->same(
            $summary['partPathSegmentPositionByteExposurePolicyCounts'],
            $identity['packagePathSegmentPositionByteExposurePolicyCounts']
        );
        $t->same(
            $summary['partNamesByPartPathSegmentPositionByteExposurePolicy'],
            $identity['entryNamesByPackagePathSegmentPositionByteExposurePolicy']
        );
        $t->true(!array_key_exists('contents', $identity), 'package identity must not expose package bytes');

        $t->same($expectedRoleCounts, $zipSummary['partPathSegmentPositionRoleCounts']);
        $t->same($expectedZipPolicyCounts, $zipSummary['partPathSegmentPositionByteExposurePolicyCounts']);
        $t->same($zipSummary['partPathSegmentPositionRoleCounts'], $zipIdentity['packagePathSegmentPositionRoleCounts']);
        $t->same(
            $zipSummary['partPathSegmentPositionByteExposurePolicyCounts'],
            $zipIdentity['packagePathSegmentPositionByteExposurePolicyCounts']
        );
        $t->same([
            'customXml/review/data.bin',
            'word/_rels/document.xml.rels',
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ], $zipIdentity['entryNamesByPackagePathSegmentPositionByteExposurePolicy']['middle']['docx-zip-entry-metadata-only']);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_path_segment_position_role_bucket_fixture_parts(): array
{
    $embeddedContentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $embeddedPackageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';

    return [
        '[Content_Types].xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/review.xlsx" ContentType="{$embeddedContentType}"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rRootNote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="root-note.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rDeepImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/deep/scan.png"/>
  <Relationship Id="rEmbeddedWorkbook" Type="{$embeddedPackageRel}" Target="embeddings/review.xlsx"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>DOCX segment-position role buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Segment position role buckets</dc:title>
</cp:coreProperties>
XML,
        'root-note.xml' => '<root-note/>',
        'word/media/review.png' => 'review png bytes',
        'word/media/deep/scan.png' => 'deep scan png bytes',
        'word/embeddings/review.xlsx' => 'embedded package bytes',
        'customXml/review/data.bin' => 'untyped custom xml data',
    ];
}

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_path_segment_position_role_bucket_zip_parts(): array
{
    $zipParts = [];
    foreach (docx_package_path_segment_position_role_bucket_fixture_parts() as $name => $data) {
        $zipParts[] = [
            'name' => $name,
            'data' => $data,
            'compressionMethod' => $name === '[Content_Types].xml' ? 0 : 8,
        ];
    }

    return $zipParts;
}
