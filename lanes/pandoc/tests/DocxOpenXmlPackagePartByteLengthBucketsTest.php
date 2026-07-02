<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX package part byte-length buckets for identity handoff' => static function (TestRunner $t): void {
        $parts = docx_package_part_byte_length_bucket_fixture_parts();
        $directDocument = (new DocxOpenXmlReader())->readPackage($parts);
        $directPackage = $directDocument->attr('docx')['packageProvenance'];
        $directSummary = $directPackage['summary'];
        $directIdentity = $directPackage['packageIdentity'];

        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(
            ZipPackage::fromParts(
                docx_package_part_byte_length_bucket_zip_parts($parts),
                'docx package part byte-length bucket review'
            )
        );
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $expectedBuckets = ['zero-bytes', '1-to-127-bytes', '128-to-1023-bytes', '1024-plus-bytes'];
        $directExpected = docx_package_part_byte_length_bucket_expectations($directPackage['parts']);
        $zipExpected = docx_package_part_byte_length_bucket_expectations($zipPackage['parts']);

        $t->same('DOCX package part byte-length buckets.', $directDocument->children[0]->attr('text'));
        $t->same($expectedBuckets, $directSummary['packagePartByteLengthBuckets']);
        $t->same($directExpected['bucketCounts'], $directSummary['packagePartByteLengthBucketCounts']);
        $t->same($directExpected['entryNamesByBucket'], $directSummary['entryNamesByPackagePartByteLengthBucket']);
        $t->same($directExpected['roleCounts'], $directSummary['packagePartByteLengthRoleCounts']);
        $t->same(
            $directExpected['entryNamesByRole'],
            $directSummary['entryNamesByPackagePartByteLengthRole']
        );
        $t->same(
            $directExpected['byteExposurePolicyCounts'],
            $directSummary['packagePartByteLengthByteExposurePolicyCounts']
        );
        $t->same(
            $directExpected['entryNamesByByteExposurePolicy'],
            $directSummary['entryNamesByPackagePartByteLengthByteExposurePolicy']
        );
        $t->same(
            array_values($directExpected['summaries']),
            $directSummary['packagePartByteLengthBucketSummaries']
        );

        $t->same($directSummary['packagePartByteLengthBucketCount'], $directIdentity['packagePartByteLengthBucketCount']);
        $t->same($directSummary['packagePartByteLengthBuckets'], $directIdentity['packagePartByteLengthBuckets']);
        $t->same(
            $directSummary['packagePartByteLengthBucketCounts'],
            $directIdentity['packagePartByteLengthBucketCounts']
        );
        $t->same(
            $directSummary['entryNamesByPackagePartByteLengthBucket'],
            $directIdentity['entryNamesByPackagePartByteLengthBucket']
        );
        $t->same($directSummary['packagePartByteLengthRoleCounts'], $directIdentity['packagePartByteLengthRoleCounts']);
        $t->same(
            $directSummary['entryNamesByPackagePartByteLengthRole'],
            $directIdentity['entryNamesByPackagePartByteLengthRole']
        );
        $t->same(
            $directSummary['packagePartByteLengthByteExposurePolicyCounts'],
            $directIdentity['packagePartByteLengthByteExposurePolicyCounts']
        );
        $t->same(
            $directSummary['entryNamesByPackagePartByteLengthByteExposurePolicy'],
            $directIdentity['entryNamesByPackagePartByteLengthByteExposurePolicy']
        );

        $directEntries = docx_package_part_byte_length_bucket_index_by($directIdentity['packageEntries'], 'partName');
        $t->same('zero-bytes', $directEntries['word/media/empty.bin']['packagePartByteLengthBucket']);
        $t->same(0, $directEntries['word/media/empty.bin']['packagePartByteLengthBucketMin']);
        $t->same(0, $directEntries['word/media/empty.bin']['packagePartByteLengthBucketMax']);
        $t->same('1-to-127-bytes', $directEntries['word/media/tiny.bin']['packagePartByteLengthBucket']);
        $t->same('128-to-1023-bytes', $directEntries['customXml/review-medium.bin']['packagePartByteLengthBucket']);
        $t->same('1024-plus-bytes', $directEntries['word/media/large.bin']['packagePartByteLengthBucket']);
        $t->same(1024, $directEntries['word/media/large.bin']['packagePartByteLengthBucketMin']);
        $t->same(null, $directEntries['word/media/large.bin']['packagePartByteLengthBucketMax']);

        $t->same($expectedBuckets, $zipSummary['packagePartByteLengthBuckets']);
        $t->same($zipExpected['bucketCounts'], $zipSummary['packagePartByteLengthBucketCounts']);
        $t->same($zipExpected['entryNamesByBucket'], $zipSummary['entryNamesByPackagePartByteLengthBucket']);
        $t->same($zipExpected['roleCounts'], $zipSummary['packagePartByteLengthRoleCounts']);
        $t->same(
            $zipExpected['byteExposurePolicyCounts'],
            $zipSummary['packagePartByteLengthByteExposurePolicyCounts']
        );
        $t->same(array_values($zipExpected['summaries']), $zipSummary['packagePartByteLengthBucketSummaries']);
        $t->same($zipSummary['packagePartByteLengthBucketSummaries'], $zipIdentity['packagePartByteLengthBucketSummaries']);
        $t->same(
            $zipSummary['packagePartByteLengthByteExposurePolicyCounts'],
            $zipIdentity['packagePartByteLengthByteExposurePolicyCounts']
        );

        $zipSummaries = docx_package_part_byte_length_bucket_index_by(
            $zipSummary['packagePartByteLengthBucketSummaries'],
            'packagePartByteLengthBucket'
        );
        $t->same('word/media/large.bin', $zipSummaries['1024-plus-bytes']['largestPartName']);
        $t->same(strlen($parts['word/media/large.bin']), $zipSummaries['1024-plus-bytes']['largestPartByteLength']);
        $t->same(
            ['docx-zip-entry-metadata-only' => 1],
            $zipSummaries['zero-bytes']['byteExposurePolicyCounts']
        );
        $t->same(false, array_key_exists('contents', $zipSummaries['1024-plus-bytes']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_part_byte_length_bucket_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rEmpty" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/empty.bin"/>
  <Relationship Id="rTiny" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/tiny.bin"/>
  <Relationship Id="rLarge" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/large.bin"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>DOCX package part byte-length buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'word/media/empty.bin' => '',
        'word/media/tiny.bin' => str_repeat('t', 64),
        'customXml/review-medium.bin' => str_repeat('m', 512),
        'word/media/large.bin' => str_repeat('l', 1500),
    ];
}

/**
 * @param array<string, string> $parts
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_part_byte_length_bucket_zip_parts(array $parts): array
{
    $zipParts = [];
    foreach ($parts as $name => $data) {
        $zipParts[] = [
            'name' => $name,
            'data' => $data,
            'compressionMethod' => str_starts_with($name, 'word/media/') ? 8 : 0,
        ];
    }

    return $zipParts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, mixed>
 */
function docx_package_part_byte_length_bucket_expectations(array $inventory): array
{
    $summaries = [];
    $bucketCounts = [];
    $entryNamesByBucket = [];
    $roleCounts = [];
    $entryNamesByRole = [];
    $byteExposurePolicyCounts = [];
    $entryNamesByByteExposurePolicy = [];

    foreach ($inventory as $fallbackName => $part) {
        if (!is_array($part)) {
            continue;
        }

        $partName = is_string($part['partName'] ?? null) && $part['partName'] !== ''
            ? $part['partName']
            : (string) $fallbackName;
        $byteLength = (int) ($part['bytes'] ?? 0);
        $compressedByteLength = is_int($part['compressedByteLength'] ?? null)
            ? (int) $part['compressedByteLength']
            : $byteLength;
        $bucket = docx_package_part_byte_length_bucket_for($byteLength);
        $bucketKey = $bucket['packagePartByteLengthBucket'];
        if (!isset($summaries[$bucketKey])) {
            $summaries[$bucketKey] = [
                'packagePartByteLengthBucket' => $bucketKey,
                'minPackagePartByteLength' => $bucket['minPackagePartByteLength'],
                'maxPackagePartByteLength' => $bucket['maxPackagePartByteLength'],
                'entryCount' => 0,
                'partCount' => 0,
                'relationshipPartCount' => 0,
                'missingContentTypePartCount' => 0,
                'parameterizedPartCount' => 0,
                'byteLength' => 0,
                'compressedByteLength' => 0,
                'sourceRecordBytes' => 0,
                'largestPartByteLength' => 0,
                'largestPartName' => null,
                'entryNames' => [],
                'partNames' => [],
                'contentTypeSourceCounts' => [],
                'roleCounts' => [],
                'byteExposurePolicyCounts' => [],
            ];
        }

        ++$summaries[$bucketKey]['entryCount'];
        ++$summaries[$bucketKey]['partCount'];
        $summaries[$bucketKey]['byteLength'] += $byteLength;
        $summaries[$bucketKey]['compressedByteLength'] += $compressedByteLength;
        $summaries[$bucketKey]['sourceRecordBytes'] += (int) ($part['sourceRecordBytes'] ?? 0);
        $summaries[$bucketKey]['entryNames'][$partName] = true;
        $summaries[$bucketKey]['partNames'][$partName] = true;
        $bucketCounts[$bucketKey] = ($bucketCounts[$bucketKey] ?? 0) + 1;
        $entryNamesByBucket[$bucketKey][$partName] = true;

        if (($part['isRelationshipPart'] ?? false) === true) {
            ++$summaries[$bucketKey]['relationshipPartCount'];
        }
        if (($part['contentTypeHasParameters'] ?? false) === true) {
            ++$summaries[$bucketKey]['parameterizedPartCount'];
        }

        $contentTypeSource = is_string($part['contentTypeSource'] ?? null) ? $part['contentTypeSource'] : 'missing';
        if ($contentTypeSource === '') {
            $contentTypeSource = 'missing';
        }
        $summaries[$bucketKey]['contentTypeSourceCounts'][$contentTypeSource] =
            ($summaries[$bucketKey]['contentTypeSourceCounts'][$contentTypeSource] ?? 0) + 1;
        if ($contentTypeSource === 'missing') {
            ++$summaries[$bucketKey]['missingContentTypePartCount'];
        }

        if (
            $byteLength > (int) $summaries[$bucketKey]['largestPartByteLength']
            || (
                $byteLength === (int) $summaries[$bucketKey]['largestPartByteLength']
                && (
                    $summaries[$bucketKey]['largestPartName'] === null
                    || strcmp($partName, (string) $summaries[$bucketKey]['largestPartName']) < 0
                )
            )
        ) {
            $summaries[$bucketKey]['largestPartByteLength'] = $byteLength;
            $summaries[$bucketKey]['largestPartName'] = $partName;
        }

        $roles = array_values(array_unique(array_filter(
            array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
            static fn (string $role): bool => $role !== ''
        )));
        if ($roles === []) {
            $roles = ['package-part'];
        }
        foreach ($roles as $role) {
            $summaries[$bucketKey]['roleCounts'][$role] =
                ($summaries[$bucketKey]['roleCounts'][$role] ?? 0) + 1;
            $roleCounts[$bucketKey][$role] = ($roleCounts[$bucketKey][$role] ?? 0) + 1;
            $entryNamesByRole[$bucketKey][$role][$partName] = true;
        }

        $policy = docx_package_part_byte_length_bucket_policy($part);
        $summaries[$bucketKey]['byteExposurePolicyCounts'][$policy] =
            ($summaries[$bucketKey]['byteExposurePolicyCounts'][$policy] ?? 0) + 1;
        $byteExposurePolicyCounts[$bucketKey][$policy] =
            ($byteExposurePolicyCounts[$bucketKey][$policy] ?? 0) + 1;
        $entryNamesByByteExposurePolicy[$bucketKey][$policy][$partName] = true;
    }

    $orderedSummaries = [];
    $orderedBucketCounts = [];
    foreach (['zero-bytes', '1-to-127-bytes', '128-to-1023-bytes', '1024-plus-bytes'] as $bucketKey) {
        if (!isset($summaries[$bucketKey])) {
            continue;
        }

        $summary = $summaries[$bucketKey];
        ksort($summary['contentTypeSourceCounts'], SORT_STRING);
        ksort($summary['roleCounts'], SORT_STRING);
        ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
        $summary['entryNames'] = array_keys($summary['entryNames']);
        sort($summary['entryNames'], SORT_STRING);
        $summary['partNames'] = array_keys($summary['partNames']);
        sort($summary['partNames'], SORT_STRING);
        $summary['contentTypeSourceCount'] = count($summary['contentTypeSourceCounts']);
        $summary['contentTypeSources'] = array_keys($summary['contentTypeSourceCounts']);
        $summary['roleCount'] = count($summary['roleCounts']);
        $summary['roles'] = array_keys($summary['roleCounts']);
        $summary['byteExposurePolicyCount'] = count($summary['byteExposurePolicyCounts']);
        $summary['byteExposurePolicies'] = array_keys($summary['byteExposurePolicyCounts']);
        $orderedSummaries[$bucketKey] = $summary;
        $orderedBucketCounts[$bucketKey] = $bucketCounts[$bucketKey] ?? 0;
    }

    docx_package_part_byte_length_bucket_sort_string_set_map($entryNamesByBucket);
    docx_package_part_byte_length_bucket_sort_nested_count_map($roleCounts);
    docx_package_part_byte_length_bucket_sort_nested_string_set_map($entryNamesByRole);
    docx_package_part_byte_length_bucket_sort_nested_count_map($byteExposurePolicyCounts);
    docx_package_part_byte_length_bucket_sort_nested_string_set_map($entryNamesByByteExposurePolicy);

    return [
        'bucketCounts' => $orderedBucketCounts,
        'entryNamesByBucket' => $entryNamesByBucket,
        'roleCounts' => $roleCounts,
        'entryNamesByRole' => $entryNamesByRole,
        'byteExposurePolicyCounts' => $byteExposurePolicyCounts,
        'entryNamesByByteExposurePolicy' => $entryNamesByByteExposurePolicy,
        'summaries' => $orderedSummaries,
    ];
}

/**
 * @return array{packagePartByteLengthBucket:string,minPackagePartByteLength:int,maxPackagePartByteLength:?int}
 */
function docx_package_part_byte_length_bucket_for(int $byteLength): array
{
    if ($byteLength <= 0) {
        return [
            'packagePartByteLengthBucket' => 'zero-bytes',
            'minPackagePartByteLength' => 0,
            'maxPackagePartByteLength' => 0,
        ];
    }

    if ($byteLength <= 127) {
        return [
            'packagePartByteLengthBucket' => '1-to-127-bytes',
            'minPackagePartByteLength' => 1,
            'maxPackagePartByteLength' => 127,
        ];
    }

    if ($byteLength <= 1023) {
        return [
            'packagePartByteLengthBucket' => '128-to-1023-bytes',
            'minPackagePartByteLength' => 128,
            'maxPackagePartByteLength' => 1023,
        ];
    }

    return [
        'packagePartByteLengthBucket' => '1024-plus-bytes',
        'minPackagePartByteLength' => 1024,
        'maxPackagePartByteLength' => null,
    ];
}

/**
 * @param array<string, mixed> $part
 */
function docx_package_part_byte_length_bucket_policy(array $part): string
{
    if (is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== '') {
        return $part['byteExposurePolicy'];
    }

    if (is_string($part['zipByteExposurePolicy'] ?? null) && $part['zipByteExposurePolicy'] !== '') {
        return $part['zipByteExposurePolicy'];
    }

    return 'docx-package-part-bytes-blocked';
}

/**
 * @param array<string, array<string, bool>> $map
 */
function docx_package_part_byte_length_bucket_sort_string_set_map(array &$map): void
{
    ksort($map, SORT_STRING);
    foreach ($map as &$values) {
        $values = array_keys($values);
        sort($values, SORT_STRING);
    }
    unset($values);
}

/**
 * @param array<string, array<string, int>> $map
 */
function docx_package_part_byte_length_bucket_sort_nested_count_map(array &$map): void
{
    ksort($map, SORT_STRING);
    foreach ($map as &$counts) {
        ksort($counts, SORT_STRING);
    }
    unset($counts);
}

/**
 * @param array<string, array<string, array<string, bool>>> $map
 */
function docx_package_part_byte_length_bucket_sort_nested_string_set_map(array &$map): void
{
    ksort($map, SORT_STRING);
    foreach ($map as &$innerMap) {
        ksort($innerMap, SORT_STRING);
        foreach ($innerMap as &$values) {
            $values = array_keys($values);
            sort($values, SORT_STRING);
        }
        unset($values);
    }
    unset($innerMap);
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_part_byte_length_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}
