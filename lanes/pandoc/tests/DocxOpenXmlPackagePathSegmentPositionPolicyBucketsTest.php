<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records docx package path segment position policy bucket mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes DOCX package path-position content types and byte policies for identity handoff' => static function (TestRunner $t): void {
        $parts = docx_package_path_segment_position_policy_bucket_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(
            ZipPackage::fromParts(
                docx_package_path_segment_position_policy_bucket_zip_parts(),
                'docx path-position policy buckets'
            )
        );
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $firstLastPartNames = [
            '_rels/.rels',
            'customXml/review/data.bin',
            'customXml/review/item.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ];
        $middlePartNames = [
            'customXml/review/data.bin',
            'customXml/review/item.xml',
            'word/_rels/document.xml.rels',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ];
        $onlyPartNames = ['[Content_Types].xml', 'root-note.xml'];
        $expectedContentTypeSourceCounts = [
            'first' => ['default' => 5, 'missing' => 1, 'override' => 1],
            'last' => ['default' => 5, 'missing' => 1, 'override' => 1],
            'middle' => ['default' => 4, 'missing' => 1],
            'only' => ['default' => 1, 'override' => 1],
        ];
        $expectedEntryNamesByContentTypeSource = [
            'first' => [
                'default' => [
                    '_rels/.rels',
                    'customXml/review/item.xml',
                    'word/_rels/document.xml.rels',
                    'word/media/deep/scan.png',
                    'word/media/review.png',
                ],
                'missing' => ['customXml/review/data.bin'],
                'override' => ['word/document.xml'],
            ],
            'last' => [
                'default' => [
                    '_rels/.rels',
                    'customXml/review/item.xml',
                    'word/_rels/document.xml.rels',
                    'word/media/deep/scan.png',
                    'word/media/review.png',
                ],
                'missing' => ['customXml/review/data.bin'],
                'override' => ['word/document.xml'],
            ],
            'middle' => [
                'default' => [
                    'customXml/review/item.xml',
                    'word/_rels/document.xml.rels',
                    'word/media/deep/scan.png',
                    'word/media/review.png',
                ],
                'missing' => ['customXml/review/data.bin'],
            ],
            'only' => [
                'default' => ['[Content_Types].xml'],
                'override' => ['root-note.xml'],
            ],
        ];
        $expectedArrayPolicyCounts = [
            'first' => ['docx-package-part-bytes-blocked' => 7],
            'last' => ['docx-package-part-bytes-blocked' => 7],
            'middle' => ['docx-package-part-bytes-blocked' => 5],
            'only' => ['docx-package-part-bytes-blocked' => 2],
        ];
        $expectedZipPolicyCounts = [
            'first' => ['docx-zip-entry-metadata-only' => 7],
            'last' => ['docx-zip-entry-metadata-only' => 7],
            'middle' => ['docx-zip-entry-metadata-only' => 5],
            'only' => ['docx-zip-entry-metadata-only' => 2],
        ];

        $t->same('Package path-position policy buckets.', $document->children[0]->attr('text'));
        $t->same($identity, $docx['packageIdentity']);
        $t->same(10, $summary['packagePathSegmentPositionContentTypeSourceBucketCount']);
        $t->same($expectedContentTypeSourceCounts, $summary['packagePathSegmentPositionContentTypeSourceCounts']);
        $t->same(
            $expectedEntryNamesByContentTypeSource,
            $summary['entryNamesByPackagePathSegmentPositionContentTypeSource']
        );
        $t->same(4, $summary['packagePathSegmentPositionByteExposurePolicyBucketCount']);
        $t->same($expectedArrayPolicyCounts, $summary['packagePathSegmentPositionByteExposurePolicyCounts']);
        $t->same(
            $middlePartNames,
            $summary['entryNamesByPackagePathSegmentPositionByteExposurePolicy']['middle']['docx-package-part-bytes-blocked']
        );
        $t->same($summary['packagePathSegmentPositionContentTypeSourceBucketCount'], $identity['packagePathSegmentPositionContentTypeSourceBucketCount']);
        $t->same($summary['packagePathSegmentPositionContentTypeSourceCounts'], $identity['packagePathSegmentPositionContentTypeSourceCounts']);
        $t->same(
            $summary['entryNamesByPackagePathSegmentPositionContentTypeSource'],
            $identity['entryNamesByPackagePathSegmentPositionContentTypeSource']
        );
        $t->same($summary['packagePathSegmentPositionByteExposurePolicyBucketCount'], $identity['packagePathSegmentPositionByteExposurePolicyBucketCount']);
        $t->same($summary['packagePathSegmentPositionByteExposurePolicyCounts'], $identity['packagePathSegmentPositionByteExposurePolicyCounts']);
        $t->same(
            $summary['entryNamesByPackagePathSegmentPositionByteExposurePolicy'],
            $identity['entryNamesByPackagePathSegmentPositionByteExposurePolicy']
        );
        $t->same($firstLastPartNames, $identity['entryNamesByPackagePathSegmentPositionByteExposurePolicy']['first']['docx-package-part-bytes-blocked']);
        $t->same($onlyPartNames, $identity['entryNamesByPackagePathSegmentPositionByteExposurePolicy']['only']['docx-package-part-bytes-blocked']);

        $t->same($expectedContentTypeSourceCounts, $zipSummary['packagePathSegmentPositionContentTypeSourceCounts']);
        $t->same($expectedZipPolicyCounts, $zipSummary['packagePathSegmentPositionByteExposurePolicyCounts']);
        $t->same($zipSummary['packagePathSegmentPositionByteExposurePolicyCounts'], $zipIdentity['packagePathSegmentPositionByteExposurePolicyCounts']);
        $t->same(
            $middlePartNames,
            $zipIdentity['entryNamesByPackagePathSegmentPositionByteExposurePolicy']['middle']['docx-zip-entry-metadata-only']
        );
        $zipIdentityEntries = docx_package_path_segment_position_policy_bucket_index_by($zipIdentity['packageEntries'], 'partName');
        $t->same('docx-zip-entry-metadata-only', $zipIdentityEntries['word/media/deep/scan.png']['byteExposurePolicy']);
        $t->same(false, array_key_exists('contents', $zipIdentityEntries['word/media/deep/scan.png']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_path_segment_position_policy_bucket_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/root-note.xml" ContentType="application/xml; profile=position-policy"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rRootNote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="root-note.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rDeepScan" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/deep/scan.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Package path-position policy buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'root-note.xml' => '<root-note/>',
        'word/media/review.png' => 'review png bytes',
        'word/media/deep/scan.png' => 'deep scan png bytes',
        'customXml/review/item.xml' => '<item/>',
        'customXml/review/data.bin' => 'missing binary bytes',
    ];
}

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_path_segment_position_policy_bucket_zip_parts(): array
{
    $zipParts = [];
    foreach (docx_package_path_segment_position_policy_bucket_fixture_parts() as $name => $data) {
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
function docx_package_path_segment_position_policy_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}
