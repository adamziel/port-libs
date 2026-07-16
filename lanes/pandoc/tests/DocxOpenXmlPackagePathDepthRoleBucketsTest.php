<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records docx package path-depth role bucket mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes DOCX package path-depth roles and byte policies for identity handoff' => static function (TestRunner $t): void {
        $parts = docx_package_path_depth_role_bucket_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(
            ZipPackage::fromParts(docx_package_path_depth_role_bucket_zip_parts(), 'docx path depth role review')
        );
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $expectedRoleCounts = [
            1 => [
                'content-types' => 1,
                'custom-xml-part' => 1,
                'root-relationship-target' => 1,
            ],
            2 => [
                'core-properties' => 1,
                'office-document' => 1,
                'package-relationships' => 1,
                'relationship-part' => 1,
                'root-relationship-target' => 2,
            ],
            3 => [
                'document-relationship-target' => 2,
                'embedded-package' => 1,
                'office-document-relationships' => 1,
                'package-part' => 1,
                'relationship-part' => 1,
            ],
            4 => [
                'document-relationship-target' => 1,
            ],
        ];
        $expectedArrayPolicyCounts = [
            1 => ['docx-package-part-bytes-blocked' => 2],
            2 => ['docx-package-part-bytes-blocked' => 3],
            3 => ['docx-package-part-bytes-blocked' => 4],
            4 => ['docx-package-part-bytes-blocked' => 1],
        ];
        $expectedZipPolicyCounts = [
            1 => ['docx-zip-entry-metadata-only' => 2],
            2 => ['docx-zip-entry-metadata-only' => 3],
            3 => ['docx-zip-entry-metadata-only' => 4],
            4 => ['docx-zip-entry-metadata-only' => 1],
        ];

        $t->same('DOCX path-depth role buckets.', $document->children[0]->attr('text'));
        $t->same($identity, $docx['packageIdentity']);
        $t->same(4, $summary['partPathDepthCount']);
        $t->same(14, $summary['partPathDepthRoleBucketCount']);
        $t->same(4, $summary['partPathDepthByteExposurePolicyBucketCount']);
        $t->same($expectedRoleCounts, $summary['partPathDepthRoleCounts']);
        $t->same($expectedArrayPolicyCounts, $summary['partPathDepthByteExposurePolicyCounts']);
        $t->same([
            'docProps/core.xml',
            'word/document.xml',
        ], $summary['partNamesByPartPathDepthRole'][2]['root-relationship-target']);
        $t->same([
            'word/embeddings/review.xlsx',
            'word/media/review.png',
        ], $summary['partNamesByPartPathDepthRole'][3]['document-relationship-target']);
        $t->same(['customXml/review/data.bin'], $summary['partNamesByPartPathDepthRole'][3]['package-part']);
        $t->same(['word/media/deep/scan.png'], $summary['partNamesByPartPathDepthRole'][4]['document-relationship-target']);
        $t->same([
            'customXml/review/data.bin',
            'word/_rels/document.xml.rels',
            'word/embeddings/review.xlsx',
            'word/media/review.png',
        ], $summary['partNamesByPartPathDepthByteExposurePolicy'][3]['docx-package-part-bytes-blocked']);

        $t->same($summary['partPathDepthRoleBucketCount'], $identity['packagePathDepthRoleBucketCount']);
        $t->same($summary['partPathDepthRoleCounts'], $identity['packagePathDepthRoleCounts']);
        $t->same($summary['partNamesByPartPathDepthRole'], $identity['entryNamesByPackagePathDepthRole']);
        $t->same(
            $summary['partPathDepthByteExposurePolicyCounts'],
            $identity['packagePathDepthByteExposurePolicyCounts']
        );
        $t->same(
            $summary['partNamesByPartPathDepthByteExposurePolicy'],
            $identity['entryNamesByPackagePathDepthByteExposurePolicy']
        );

        $identityEntries = docx_package_path_depth_role_bucket_index_by($identity['packageEntries'], 'partName');
        $t->same(3, $identityEntries['word/embeddings/review.xlsx']['pathSegmentCount']);
        $t->same(2, $identityEntries['word/embeddings/review.xlsx']['directoryDepth']);
        $t->same('docx-package-part-bytes-blocked', $identityEntries['word/embeddings/review.xlsx']['byteExposurePolicy']);
        $t->same(false, $identityEntries['word/embeddings/review.xlsx']['canExposeBytes']);
        $t->same(['document-relationship-target', 'embedded-package'], $identityEntries['word/embeddings/review.xlsx']['roles']);

        $t->same($expectedRoleCounts, $zipSummary['partPathDepthRoleCounts']);
        $t->same($expectedZipPolicyCounts, $zipSummary['partPathDepthByteExposurePolicyCounts']);
        $t->same($zipSummary['partPathDepthRoleCounts'], $zipIdentity['packagePathDepthRoleCounts']);
        $t->same(
            $zipSummary['partPathDepthByteExposurePolicyCounts'],
            $zipIdentity['packagePathDepthByteExposurePolicyCounts']
        );
        $t->same([
            'customXml/review/data.bin',
            'word/_rels/document.xml.rels',
            'word/embeddings/review.xlsx',
            'word/media/review.png',
        ], $zipIdentity['entryNamesByPackagePathDepthByteExposurePolicy'][3]['docx-zip-entry-metadata-only']);
        $zipIdentityEntries = docx_package_path_depth_role_bucket_index_by($zipIdentity['packageEntries'], 'partName');
        $t->same('docx-zip-entry-metadata-only', $zipIdentityEntries['word/embeddings/review.xlsx']['byteExposurePolicy']);
        $t->same(false, array_key_exists('contents', $zipIdentityEntries['word/embeddings/review.xlsx']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_path_depth_role_bucket_fixture_parts(): array
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
  <w:body><w:p><w:r><w:t>DOCX path-depth role buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Path depth role buckets</dc:title>
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
function docx_package_path_depth_role_bucket_zip_parts(): array
{
    $zipParts = [];
    foreach (docx_package_path_depth_role_bucket_fixture_parts() as $name => $data) {
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
function docx_package_path_depth_role_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}
