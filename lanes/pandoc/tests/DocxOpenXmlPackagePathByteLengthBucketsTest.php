<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

$longPackagePath = 'customXml/review/long-path-name-that-exceeds-sixty-four-characters/data.bin';
$mediumPackagePath = 'word/media/review-image-with-medium-package-path.png';

return [
    'summarizes DOCX package path byte-length buckets for identity handoff' => static function (TestRunner $t) use (
        $longPackagePath,
        $mediumPackagePath
    ): void {
        $parts = docx_package_path_byte_length_bucket_fixture_parts($longPackagePath, $mediumPackagePath);
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(ZipPackage::fromParts(
            docx_package_path_byte_length_bucket_zip_parts($parts),
            'docx path byte-length bucket review'
        ));
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $expectedBuckets = [
            'up-to-8-bytes',
            '9-to-16-bytes',
            '17-to-32-bytes',
            '33-to-64-bytes',
            'over-64-bytes',
        ];
        $expectedBucketCounts = [
            'up-to-8-bytes' => 1,
            '9-to-16-bytes' => 2,
            '17-to-32-bytes' => 7,
            '33-to-64-bytes' => 1,
            'over-64-bytes' => 1,
        ];
        $expectedEntryNames = [
            '17-to-32-bytes' => [
                '[Content_Types].xml',
                'customXml/review/data.bin',
                'docProps/core.xml',
                'word/_rels/document.xml.rels',
                'word/document.xml',
                'word/embeddings/review.xlsx',
                'word/media/review.png',
            ],
            '33-to-64-bytes' => [$mediumPackagePath],
            '9-to-16-bytes' => ['_rels/.rels', 'root-note.xml'],
            'over-64-bytes' => [$longPackagePath],
            'up-to-8-bytes' => ['a/b.xml'],
        ];
        $expectedRoleCounts = [
            '17-to-32-bytes' => [
                'content-types' => 1,
                'core-properties' => 1,
                'document-relationship-target' => 2,
                'embedded-package' => 1,
                'office-document' => 1,
                'office-document-relationships' => 1,
                'package-part' => 1,
                'relationship-part' => 1,
                'root-relationship-target' => 2,
            ],
            '33-to-64-bytes' => [
                'document-relationship-target' => 1,
            ],
            '9-to-16-bytes' => [
                'custom-xml-part' => 1,
                'package-relationships' => 1,
                'relationship-part' => 1,
                'root-relationship-target' => 1,
            ],
            'over-64-bytes' => [
                'package-part' => 1,
            ],
            'up-to-8-bytes' => [
                'custom-xml-part' => 1,
                'root-relationship-target' => 1,
            ],
        ];
        $expectedArrayPolicyCounts = [
            '17-to-32-bytes' => ['docx-package-part-bytes-blocked' => 7],
            '33-to-64-bytes' => ['docx-package-part-bytes-blocked' => 1],
            '9-to-16-bytes' => ['docx-package-part-bytes-blocked' => 2],
            'over-64-bytes' => ['docx-package-part-bytes-blocked' => 1],
            'up-to-8-bytes' => ['docx-package-part-bytes-blocked' => 1],
        ];
        $expectedZipPolicyCounts = [
            '17-to-32-bytes' => ['docx-zip-entry-metadata-only' => 7],
            '33-to-64-bytes' => ['docx-zip-entry-metadata-only' => 1],
            '9-to-16-bytes' => ['docx-zip-entry-metadata-only' => 2],
            'over-64-bytes' => ['docx-zip-entry-metadata-only' => 1],
            'up-to-8-bytes' => ['docx-zip-entry-metadata-only' => 1],
        ];

        $t->same('DOCX path byte-length buckets.', $document->children[0]->attr('text'));
        $t->same($identity, $docx['packageIdentity']);
        $t->same(5, $summary['packagePathByteLengthBucketCount']);
        $t->same($expectedBuckets, $summary['packagePathByteLengthBuckets']);
        $t->same($expectedBucketCounts, $summary['packagePathByteLengthBucketCounts']);
        $t->same($expectedEntryNames, $summary['entryNamesByPackagePathByteLengthBucket']);
        $t->same($expectedRoleCounts, $summary['packagePathByteLengthRoleCounts']);
        $t->same($expectedArrayPolicyCounts, $summary['packagePathByteLengthByteExposurePolicyCounts']);
        $t->same([$longPackagePath], $summary['entryNamesByPackagePathByteLengthRole']['over-64-bytes']['package-part']);
        $t->same(
            [$mediumPackagePath],
            $summary['entryNamesByPackagePathByteLengthByteExposurePolicy']['33-to-64-bytes']['docx-package-part-bytes-blocked']
        );

        $summaries = docx_package_path_byte_length_bucket_index_by(
            $summary['packagePathByteLengthBucketSummaries'],
            'packagePathByteLengthBucket'
        );
        $t->same($mediumPackagePath, $summaries['33-to-64-bytes']['longestEntryName']);
        $t->same(strlen($mediumPackagePath), $summaries['33-to-64-bytes']['longestPackagePathByteLength']);
        $t->same($longPackagePath, $summaries['over-64-bytes']['longestEntryName']);
        $t->same(strlen($longPackagePath), $summaries['over-64-bytes']['longestPackagePathByteLength']);
        $t->same(1, $summaries['over-64-bytes']['missingContentTypePartCount']);
        $t->same(['package-part'], $summaries['over-64-bytes']['roles']);

        $t->same($summary['packagePathByteLengthBucketCount'], $identity['packagePathByteLengthBucketCount']);
        $t->same($summary['packagePathByteLengthBuckets'], $identity['packagePathByteLengthBuckets']);
        $t->same($summary['packagePathByteLengthBucketCounts'], $identity['packagePathByteLengthBucketCounts']);
        $t->same(
            $summary['entryNamesByPackagePathByteLengthBucket'],
            $identity['entryNamesByPackagePathByteLengthBucket']
        );
        $t->same($summary['packagePathByteLengthRoleCounts'], $identity['packagePathByteLengthRoleCounts']);
        $t->same(
            $summary['entryNamesByPackagePathByteLengthRole'],
            $identity['entryNamesByPackagePathByteLengthRole']
        );
        $t->same(
            $summary['packagePathByteLengthByteExposurePolicyCounts'],
            $identity['packagePathByteLengthByteExposurePolicyCounts']
        );
        $t->same(
            $summary['entryNamesByPackagePathByteLengthByteExposurePolicy'],
            $identity['entryNamesByPackagePathByteLengthByteExposurePolicy']
        );

        $identityEntries = docx_package_path_byte_length_bucket_index_by($identity['packageEntries'], 'partName');
        $t->same(strlen($longPackagePath), $package['parts'][$longPackagePath]['packagePathByteLength']);
        $t->same('over-64-bytes', $package['parts'][$longPackagePath]['packagePathByteLengthBucket']);
        $t->same(strlen($longPackagePath), $identityEntries[$longPackagePath]['packagePathByteLength']);
        $t->same('over-64-bytes', $identityEntries[$longPackagePath]['packagePathByteLengthBucket']);
        $t->same(65, $identityEntries[$longPackagePath]['packagePathByteLengthBucketMin']);
        $t->same(null, $identityEntries[$longPackagePath]['packagePathByteLengthBucketMax']);
        $t->same('docx-package-part-bytes-blocked', $identityEntries[$longPackagePath]['byteExposurePolicy']);

        $t->same($expectedBuckets, $zipSummary['packagePathByteLengthBuckets']);
        $t->same($expectedBucketCounts, $zipSummary['packagePathByteLengthBucketCounts']);
        $t->same($expectedEntryNames, $zipSummary['entryNamesByPackagePathByteLengthBucket']);
        $t->same($expectedRoleCounts, $zipSummary['packagePathByteLengthRoleCounts']);
        $t->same($expectedZipPolicyCounts, $zipSummary['packagePathByteLengthByteExposurePolicyCounts']);
        $t->same(
            $zipSummary['packagePathByteLengthByteExposurePolicyCounts'],
            $zipIdentity['packagePathByteLengthByteExposurePolicyCounts']
        );
        $zipIdentityEntries = docx_package_path_byte_length_bucket_index_by($zipIdentity['packageEntries'], 'partName');
        $t->same('docx-zip-entry-metadata-only', $zipIdentityEntries[$longPackagePath]['byteExposurePolicy']);
        $t->same('over-64-bytes', $zipIdentityEntries[$longPackagePath]['packagePathByteLengthBucket']);
        $t->same(false, array_key_exists('contents', $zipIdentityEntries[$longPackagePath]));
        $t->same(false, array_key_exists('contents', $zipIdentity['packagePathByteLengthBucketSummaries'][0]));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_path_byte_length_bucket_fixture_parts(string $longPackagePath, string $mediumPackagePath): array
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
  <Relationship Id="rShort" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="a/b.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rMediumImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../{$mediumPackagePath}"/>
  <Relationship Id="rEmbeddedWorkbook" Type="{$embeddedPackageRel}" Target="embeddings/review.xlsx"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>DOCX path byte-length buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Path byte-length buckets</dc:title>
</cp:coreProperties>
XML,
        'root-note.xml' => '<root-note/>',
        'a/b.xml' => '<short/>',
        'word/media/review.png' => 'review png bytes',
        $mediumPackagePath => 'medium path png bytes',
        'word/embeddings/review.xlsx' => 'embedded package bytes',
        'customXml/review/data.bin' => 'untyped custom xml data',
        $longPackagePath => 'long path untyped custom xml data',
    ];
}

/**
 * @param array<string, string> $parts
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_path_byte_length_bucket_zip_parts(array $parts): array
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
 * @return array<string, array<string, mixed>>
 */
function docx_package_path_byte_length_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}
