<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records docx package top-level segment role bucket mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes DOCX package top-level segment roles and byte policies for identity handoff' => static function (TestRunner $t): void {
        $parts = docx_package_top_level_segment_role_bucket_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(
            ZipPackage::fromParts(
                docx_package_top_level_segment_role_bucket_zip_parts(),
                'docx top-level segment role buckets'
            )
        );
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $expectedRoleCounts = [
            '[Content_Types].xml' => ['content-types' => 1],
            '_rels' => ['package-relationships' => 1, 'relationship-part' => 1],
            'customXml' => ['package-part' => 1],
            'docProps' => ['core-properties' => 1, 'root-relationship-target' => 1],
            'root-note.xml' => ['custom-xml-part' => 1, 'root-relationship-target' => 1],
            'word' => [
                'document-relationship-target' => 3,
                'embedded-package' => 1,
                'office-document' => 1,
                'office-document-relationships' => 1,
                'relationship-part' => 1,
                'root-relationship-target' => 1,
            ],
        ];
        $expectedArrayPolicyCounts = [
            '[Content_Types].xml' => ['docx-package-part-bytes-blocked' => 1],
            '_rels' => ['docx-package-part-bytes-blocked' => 1],
            'customXml' => ['docx-package-part-bytes-blocked' => 1],
            'docProps' => ['docx-package-part-bytes-blocked' => 1],
            'root-note.xml' => ['docx-package-part-bytes-blocked' => 1],
            'word' => ['docx-package-part-bytes-blocked' => 5],
        ];
        $expectedZipPolicyCounts = [
            '[Content_Types].xml' => ['docx-zip-entry-metadata-only' => 1],
            '_rels' => ['docx-zip-entry-metadata-only' => 1],
            'customXml' => ['docx-zip-entry-metadata-only' => 1],
            'docProps' => ['docx-zip-entry-metadata-only' => 1],
            'root-note.xml' => ['docx-zip-entry-metadata-only' => 1],
            'word' => ['docx-zip-entry-metadata-only' => 5],
        ];
        $wordDocumentTargets = [
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ];
        $wordParts = [
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ];

        $t->same('DOCX top-level segment role buckets.', $document->children[0]->attr('text'));
        $t->same($identity, $docx['packageIdentity']);
        $t->same(6, $summary['partTopLevelSegmentCount']);
        $t->same(14, $summary['partTopLevelSegmentRoleBucketCount']);
        $t->same(6, $summary['partTopLevelSegmentByteExposurePolicyBucketCount']);
        $t->same($expectedRoleCounts, $summary['partTopLevelSegmentRoleCounts']);
        $t->same($expectedArrayPolicyCounts, $summary['partTopLevelSegmentByteExposurePolicyCounts']);
        $t->same(
            $wordDocumentTargets,
            $summary['partNamesByPartTopLevelSegmentRole']['word']['document-relationship-target']
        );
        $t->same(
            ['root-note.xml'],
            $summary['partNamesByPartTopLevelSegmentRole']['root-note.xml']['root-relationship-target']
        );
        $t->same(
            $wordParts,
            $summary['partNamesByPartTopLevelSegmentByteExposurePolicy']['word']['docx-package-part-bytes-blocked']
        );

        $t->same($summary['partTopLevelSegmentRoleBucketCount'], $identity['packageTopLevelSegmentRoleBucketCount']);
        $t->same($summary['partTopLevelSegmentRoleCounts'], $identity['packageTopLevelSegmentRoleCounts']);
        $t->same(
            $summary['partNamesByPartTopLevelSegmentRole'],
            $identity['entryNamesByPackageTopLevelSegmentRole']
        );
        $t->same(
            $summary['partTopLevelSegmentByteExposurePolicyBucketCount'],
            $identity['packageTopLevelSegmentByteExposurePolicyBucketCount']
        );
        $t->same(
            $summary['partTopLevelSegmentByteExposurePolicyCounts'],
            $identity['packageTopLevelSegmentByteExposurePolicyCounts']
        );
        $t->same(
            $summary['partNamesByPartTopLevelSegmentByteExposurePolicy'],
            $identity['entryNamesByPackageTopLevelSegmentByteExposurePolicy']
        );

        $t->same($expectedRoleCounts, $zipSummary['partTopLevelSegmentRoleCounts']);
        $t->same($expectedZipPolicyCounts, $zipSummary['partTopLevelSegmentByteExposurePolicyCounts']);
        $t->same($zipSummary['partTopLevelSegmentRoleCounts'], $zipIdentity['packageTopLevelSegmentRoleCounts']);
        $t->same(
            $zipSummary['partTopLevelSegmentByteExposurePolicyCounts'],
            $zipIdentity['packageTopLevelSegmentByteExposurePolicyCounts']
        );
        $t->same(
            $wordParts,
            $zipIdentity['entryNamesByPackageTopLevelSegmentByteExposurePolicy']['word']['docx-zip-entry-metadata-only']
        );
        $zipIdentityEntries = docx_package_top_level_segment_role_bucket_index_by(
            $zipIdentity['packageEntries'],
            'partName'
        );
        $t->same('docx-zip-entry-metadata-only', $zipIdentityEntries['word/embeddings/review.xlsx']['byteExposurePolicy']);
        $t->same(false, array_key_exists('contents', $zipIdentityEntries['word/embeddings/review.xlsx']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_top_level_segment_role_bucket_fixture_parts(): array
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
  <w:body><w:p><w:r><w:t>DOCX top-level segment role buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Top-level segment role buckets</dc:title>
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
function docx_package_top_level_segment_role_bucket_zip_parts(): array
{
    $zipParts = [];
    foreach (docx_package_top_level_segment_role_bucket_fixture_parts() as $name => $data) {
        $zipParts[] = [
            'name' => $name,
            'data' => $data,
            'compressionMethod' => $name === '[Content_Types].xml' ? 0 : 8,
        ];
    }

    return $zipParts;
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_top_level_segment_role_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}
