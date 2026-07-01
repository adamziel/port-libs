<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

$longPackagePath = 'customXml/review/long-path-name-that-exceeds-sixty-four-characters/data.bin';
$mediumPackagePath = 'word/media/review-image-with-medium-package-path.png';

return [
    'summarizes DOCX ZIP source records by package path byte-length bucket' => static function (
        TestRunner $t
    ) use (
        $longPackagePath,
        $mediumPackagePath
    ): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_package_path_byte_length_bucket_fixture_parts(
                $longPackagePath,
                $mediumPackagePath
            ),
            'docx source path byte-length bucket review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $buckets = docx_zip_source_record_package_path_byte_length_bucket_index_by(
            $summary['partZipSourceRecordPackagePathByteLengthBucketSummaries'],
            'packagePathByteLengthBucket'
        );
        $identityEntries = docx_zip_source_record_package_path_byte_length_bucket_index_by(
            $identity['packageEntries'],
            'partName'
        );

        $expectedBuckets = [
            'up-to-8-bytes',
            '9-to-16-bytes',
            '17-to-32-bytes',
            '33-to-64-bytes',
            'over-64-bytes',
        ];
        $expectedCounts = docx_zip_source_record_package_path_byte_length_bucket_counts($inventory);

        $t->same('Source path length buckets.', $document->children[0]->attr('text'));
        $t->same($expectedBuckets, $summary['partZipSourceRecordPackagePathByteLengthBuckets']);
        $t->same(
            count($expectedCounts),
            $summary['partZipSourceRecordPackagePathByteLengthBucketSummaryCount']
        );
        $t->same($expectedCounts, $summary['partZipSourceRecordPackagePathByteLengthBucketCounts']);
        $t->same(
            docx_zip_source_record_package_path_byte_length_bucket_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePathByteLengthBucketBytes']
        );
        $t->same(
            docx_zip_source_record_package_path_byte_length_bucket_sums($inventory, 'compressedByteLength'),
            $summary['partZipSourceRecordPackagePathByteLengthBucketCompressedByteLengths']
        );
        $t->same(
            docx_zip_source_record_package_path_byte_length_bucket_sums($inventory, 'bytes'),
            $summary['partZipSourceRecordPackagePathByteLengthBucketUncompressedByteLengths']
        );
        $t->same(0, $summary['partZipSourceRecordPackagePathByteLengthBucketDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePathByteLengthBucketIssuePartCount']);
        $t->same(
            $summary['partZipSourceRecordPartCount'],
            array_sum($summary['partZipSourceRecordPackagePathByteLengthBucketCounts'])
        );

        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketSummaryCount'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketSummaryCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBuckets'],
            $identity['partZipSourceRecordPackagePathByteLengthBuckets']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketCounts'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketBytes'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketBytes']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketCompressedByteLengths'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketCompressedByteLengths']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketUncompressedByteLengths'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketUncompressedByteLengths']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketDataDescriptorPartCount'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketIssuePartCount'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketIssuePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePathByteLengthBucketSummaries'],
            $identity['partZipSourceRecordPackagePathByteLengthBucketSummaries']
        );

        $t->same(['a/b.xml'], $buckets['up-to-8-bytes']['partNames']);
        $t->same(['_rels/.rels'], $buckets['9-to-16-bytes']['partNames']);
        $t->same([
            '[Content_Types].xml',
            'customXml/review/data.bin',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ], $buckets['17-to-32-bytes']['partNames']);
        $t->same([$mediumPackagePath], $buckets['33-to-64-bytes']['partNames']);
        $t->same([$longPackagePath], $buckets['over-64-bytes']['partNames']);

        $t->same($longPackagePath, $buckets['over-64-bytes']['longestEntryName']);
        $t->same(
            strlen($longPackagePath),
            $buckets['over-64-bytes']['longestPackagePathByteLength']
        );
        $t->same(['missing' => 1], $buckets['over-64-bytes']['contentTypeSourceCounts']);
        $t->same(['(missing)' => 1], $buckets['over-64-bytes']['contentTypeBaseCounts']);
        $t->same([8 => 1], $buckets['over-64-bytes']['compressionMethodCounts']);
        $t->same(['package-part' => 1], $buckets['over-64-bytes']['roleCounts']);
        $t->same($longPackagePath, $buckets['over-64-bytes']['largestSourceRecordPart']['partName']);
        $t->same(
            false,
            array_key_exists('contents', $buckets['over-64-bytes']['largestSourceRecordPart'])
        );

        $t->same($mediumPackagePath, $buckets['33-to-64-bytes']['longestEntryName']);
        $t->same(['default' => 1], $buckets['33-to-64-bytes']['contentTypeSourceCounts']);
        $t->same(['document-relationship-target' => 1], $buckets['33-to-64-bytes']['roleCounts']);
        $t->same(
            $inventory[$mediumPackagePath]['sourceRecordBytes'],
            $buckets['33-to-64-bytes']['sourceRecordBytes']
        );

        $t->same(strlen($longPackagePath), $inventory[$longPackagePath]['packagePathByteLength']);
        $t->same('over-64-bytes', $inventory[$longPackagePath]['packagePathByteLengthBucket']);
        $t->same('over-64-bytes', $identityEntries[$longPackagePath]['packagePathByteLengthBucket']);
        $t->same(65, $identityEntries[$longPackagePath]['packagePathByteLengthBucketMin']);
        $t->same(null, $identityEntries[$longPackagePath]['packagePathByteLengthBucketMax']);
        $t->same(false, array_key_exists('contents', $identityEntries[$longPackagePath]));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_zip_source_record_package_path_byte_length_bucket_fixture_parts(
    string $longPackagePath,
    string $mediumPackagePath
): array {
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
  <Relationship Id="rShort" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="a/b.xml"/>
</Relationships>
XML,
        ],
        ['name' => 'word/_rels/document.xml.rels', 'compressionMethod' => 8, 'data' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-image-with-medium-package-path.png"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Source path length buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        ['name' => 'a/b.xml', 'data' => '<review>short</review>', 'compressionMethod' => 0],
        ['name' => 'customXml/review/data.bin', 'data' => 'source path length payload', 'compressionMethod' => 0],
        ['name' => $mediumPackagePath, 'data' => str_repeat('M', 256), 'compressionMethod' => 0],
        ['name' => $longPackagePath, 'data' => str_repeat('L', 128), 'compressionMethod' => 8],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_package_path_byte_length_bucket_index_by(array $items, string $key): array
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
function docx_zip_source_record_package_path_byte_length_bucket_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $bucket = docx_zip_source_record_package_path_byte_length_bucket_for_part($part);
        $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_package_path_byte_length_bucket_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $bucket = docx_zip_source_record_package_path_byte_length_bucket_for_part($part);
        $sums[$bucket] = ($sums[$bucket] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_package_path_byte_length_bucket_for_part(array $part): string
{
    return is_string($part['packagePathByteLengthBucket'] ?? null)
        ? $part['packagePathByteLengthBucket']
        : 'unknown';
}
