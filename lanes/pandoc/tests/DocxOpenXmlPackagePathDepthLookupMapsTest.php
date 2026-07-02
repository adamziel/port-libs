<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'carries DOCX package path-depth lookup maps through package identity' => static function (TestRunner $t): void {
        $parts = docx_package_path_depth_lookup_map_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $identityDepths = docx_package_path_depth_lookup_map_index_by(
            $identity['packagePathDepths'],
            'pathSegmentCount'
        );
        $identityEntries = docx_package_path_depth_lookup_map_index_by($identity['packageEntries'], 'partName');

        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(
            ZipPackage::fromParts(docx_package_path_depth_lookup_map_zip_parts($parts), 'docx path-depth identity review')
        );
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];
        $zipIdentityDepths = docx_package_path_depth_lookup_map_index_by(
            $zipIdentity['packagePathDepths'],
            'pathSegmentCount'
        );
        $zipIdentityEntries = docx_package_path_depth_lookup_map_index_by($zipIdentity['packageEntries'], 'partName');

        $expectedCounts = [
            1 => 2,
            2 => 3,
            3 => 4,
            4 => 1,
        ];
        $expectedNames = [
            1 => ['[Content_Types].xml', 'root-note.xml'],
            2 => ['_rels/.rels', 'docProps/core.xml', 'word/document.xml'],
            3 => [
                'customXml/review/data.bin',
                'word/_rels/document.xml.rels',
                'word/embeddings/review.xlsx',
                'word/media/review.png',
            ],
            4 => ['word/media/deep/scan.png'],
        ];

        $t->same('DOCX path-depth identity lookup maps.', $document->children[0]->attr('text'));
        $t->same($identity, $docx['packageIdentity']);
        $t->same(4, $summary['partPathDepthCount']);
        $t->same($expectedCounts, $summary['partPathDepthCounts']);
        $t->same($expectedNames, $summary['partNamesByPartPathDepth']);
        $t->same($summary['partPathDepthCounts'], $identity['packagePathDepthCounts']);
        $t->same($summary['partNamesByPartPathDepth'], $identity['entryNamesByPackagePathDepth']);
        $t->same(4, $identity['packagePathDepthCount']);
        $t->same(4, count($identity['packagePathDepths']));
        $t->same('word/media/deep/scan.png', $identityDepths[4]['largestPart']['partName']);
        $t->same(4, $identityDepths[4]['largestPart']['pathSegmentCount']);
        $t->same(3, $identityDepths[4]['largestPart']['directoryDepth']);
        $t->same(['document-relationship-target'], $identityDepths[4]['largestPart']['roles']);
        $t->same(4, $identityEntries['word/media/deep/scan.png']['pathSegmentCount']);
        $t->same(3, $identityEntries['word/media/deep/scan.png']['directoryDepth']);
        $t->same('docx-package-part-bytes-blocked', $identityEntries['word/media/deep/scan.png']['byteExposurePolicy']);

        $t->same($expectedCounts, $zipSummary['partPathDepthCounts']);
        $t->same($expectedNames, $zipSummary['partNamesByPartPathDepth']);
        $t->same($zipSummary['partPathDepthCounts'], $zipIdentity['packagePathDepthCounts']);
        $t->same($zipSummary['partNamesByPartPathDepth'], $zipIdentity['entryNamesByPackagePathDepth']);
        $t->same('word/media/deep/scan.png', $zipIdentityDepths[4]['largestPart']['partName']);
        $t->same(4, $zipIdentityEntries['word/media/deep/scan.png']['pathSegmentCount']);
        $t->same('docx-zip-entry-metadata-only', $zipIdentityEntries['word/media/deep/scan.png']['byteExposurePolicy']);
        $t->same(false, array_key_exists('contents', $zipIdentityEntries['word/media/deep/scan.png']));
        $t->same(false, array_key_exists('contents', $zipIdentityDepths[4]['largestPart']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_path_depth_lookup_map_fixture_parts(): array
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
  <w:body><w:p><w:r><w:t>DOCX path-depth identity lookup maps.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Path depth identity lookup maps</dc:title>
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
 * @param array<string, string> $parts
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_path_depth_lookup_map_zip_parts(array $parts): array
{
    $zipParts = [];
    foreach ($parts as $name => $data) {
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
 * @return array<int|string, array<string, mixed>>
 */
function docx_package_path_depth_lookup_map_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (!is_array($item) || (!is_string($item[$key] ?? null) && !is_int($item[$key] ?? null))) {
            continue;
        }

        $indexed[$item[$key]] = $item;
    }

    return $indexed;
}
