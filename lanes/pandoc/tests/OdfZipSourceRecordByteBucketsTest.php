<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>ZIP source record byte bucket review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BucketBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>ZIP Source Record Byte Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/medium.bin" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:full-path="Objects/big.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'a', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Pictures/medium.bin', 'data' => str_repeat('M', 650), 'compressionMethod' => 0],
    ['name' => 'Objects/big.bin', 'data' => str_repeat('B', 2300), 'compressionMethod' => 0],
], 'odf source record byte bucket package');

return [
    'summarizes ODF ZIP source records by byte bucket across compact and rich handoff' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expected = odf_zip_source_record_byte_bucket_expected($compactInventory['parts']);
        $expectedBuckets = [
            'up-to-127-bytes',
            '128-to-511-bytes',
            '512-to-2047-bytes',
            '2048-plus-bytes',
        ];

        $t->same($expectedBuckets, $expected['buckets']);
        $t->same('up-to-127-bytes', odf_zip_source_record_byte_bucket_for_part($compactInventory['parts']['a']));
        $t->same('512-to-2047-bytes', odf_zip_source_record_byte_bucket_for_part($compactInventory['parts']['Pictures/medium.bin']));
        $t->same('2048-plus-bytes', odf_zip_source_record_byte_bucket_for_part($compactInventory['parts']['Objects/big.bin']));

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
        ] as $label => $handoff) {
            $t->same(4, $handoff['packageZipSourceRecordByteBucketCount'], "{$label} bucket count");
            $t->same($expected['buckets'], $handoff['packageZipSourceRecordByteBuckets'], "{$label} bucket order");
            $t->same($expected['counts'], $handoff['packageZipSourceRecordByteBucketCounts'], "{$label} bucket counts");
            $t->same($expected['bytes'], $handoff['packageZipSourceRecordByteBucketBytes'], "{$label} bucket bytes");
            $t->same($expected['entryNames'], $handoff['entryNamesByPackageZipSourceRecordByteBucket'], "{$label} names by bucket");
            $t->same(0, $handoff['packageZipSourceRecordByteBucketDataDescriptorEntryCount'], "{$label} descriptor count");
            $t->same(0, $handoff['packageZipSourceRecordByteBucketIssueEntryCount'], "{$label} issue count");
            $t->same($expected['summaries'], $handoff['packageZipSourceRecordByteBucketSummaries'], "{$label} bucket summaries");
        }

        $compactBuckets = odf_zip_source_record_byte_bucket_index_by($compactInventory['packageZipSourceRecordByteBucketSummaries']);
        $richBuckets = odf_zip_source_record_byte_bucket_index_by($richProvenance['packageZipSourceRecordByteBucketSummaries']);

        foreach ([$compactBuckets['up-to-127-bytes'], $richBuckets['up-to-127-bytes']] as $smallBucket) {
            $t->same(['a'], $smallBucket['entryNames']);
            $t->same(['/' => 1], $smallBucket['directoryRootCounts']);
            $t->same(['(extensionless)' => 1], $smallBucket['packagePartExtensionCounts']);
            $t->same(['undeclared-package-entry' => 1], $smallBucket['roleCounts']);
            $t->same('a', $smallBucket['largestSourceRecordEntry']['entryName']);
            $t->same('up-to-127-bytes', $smallBucket['largestSourceRecordEntry']['sourceRecordByteBucket']);
            $t->same(false, array_key_exists('contents', $smallBucket['largestSourceRecordEntry']));
        }

        foreach ([$compactBuckets['2048-plus-bytes'], $richBuckets['2048-plus-bytes']] as $largeBucket) {
            $t->same(['Objects/big.bin'], $largeBucket['entryNames']);
            $t->same(['Objects/' => 1], $largeBucket['directoryRootCounts']);
            $t->same(['bin' => 1], $largeBucket['packagePartExtensionCounts']);
            $t->same(['manifest-declared' => 1], $largeBucket['roleCounts']);
            $t->same('Objects/big.bin', $largeBucket['largestSourceRecordEntry']['entryName']);
            $t->same('2048-plus-bytes', $largeBucket['largestSourceRecordEntry']['sourceRecordByteBucket']);
            $t->same(false, array_key_exists('data', $largeBucket['largestSourceRecordEntry']));
        }

        $t->same(
            $compactInventory['packageZipSourceRecordByteBucketSummaries'],
            $compactIdentity['packageZipSourceRecordByteBucketSummaries']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordByteBucketBytes'],
            $documentProvenance['packageZipSourceRecordByteBucketBytes']
        );
    },
];

/**
 * @param array<string, array<string, mixed>> $parts
 * @return array{buckets:list<string>, counts:array<string, int>, bytes:array<string, int>, entryNames:array<string, list<string>>, summaries:list<array<string, mixed>>}
 */
function odf_zip_source_record_byte_bucket_expected(array $parts): array
{
    $summaries = [];
    foreach ($parts as $name => $part) {
        if (!is_array($part) || ($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }

        $entryName = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
        $sourceRecordBytes = odf_zip_source_record_int_field($part, 'zipSourceRecordBytes');
        $bucket = odf_zip_source_record_byte_bucket($sourceRecordBytes);
        $bucketKey = $bucket['sourceRecordByteBucket'];
        if (!isset($summaries[$bucketKey])) {
            $summaries[$bucketKey] = [
                'sourceRecordByteBucket' => $bucketKey,
                'minSourceRecordBytes' => $bucket['minSourceRecordBytes'],
                'maxSourceRecordBytes' => $bucket['maxSourceRecordBytes'],
                'entryCount' => 0,
                'fileEntryCount' => 0,
                'directoryEntryCount' => 0,
                'manifestDeclaredEntryCount' => 0,
                'undeclaredEntryCount' => 0,
                'exposableEntryCount' => 0,
                'blockedEntryCount' => 0,
                'sourceRecordBytes' => 0,
                'localRecordBytes' => 0,
                'localHeaderBytes' => 0,
                'localHeaderFixedHeaderBytes' => 0,
                'localHeaderVariableFieldBytes' => 0,
                'localHeaderRawNameBytes' => 0,
                'localHeaderExtraFieldBytes' => 0,
                'localHeaderReviewFieldBytes' => 0,
                'compressedDataBytes' => 0,
                'dataDescriptorBytes' => 0,
                'dataDescriptorEntryCount' => 0,
                'centralDirectoryRecordBytes' => 0,
                'centralDirectoryFixedHeaderBytes' => 0,
                'centralDirectoryVariableFieldBytes' => 0,
                'centralDirectoryRawNameBytes' => 0,
                'centralDirectoryExtraFieldBytes' => 0,
                'centralDirectoryRawCommentBytes' => 0,
                'centralDirectoryReviewFieldBytes' => 0,
                'compressedByteLength' => 0,
                'uncompressedByteLength' => 0,
                'sourceRecordIssueEntryCount' => 0,
                'sourceRecordIssueCount' => 0,
                'directoryRootCounts' => [],
                'packagePartExtensionCounts' => [],
                'compressionMethodCounts' => [],
                'byteExposurePolicyCounts' => [],
                'manifestMediaFamilyCounts' => [],
                'manifestMediaTypeBaseCounts' => [],
                'roleCounts' => [],
                'entryNames' => [],
                'largestSourceRecordEntry' => null,
            ];
        }

        $roles = odf_zip_source_record_part_roles($part);
        $directoryRoot = is_string($part['zipPackageManifestDirectoryRoot'] ?? null) && $part['zipPackageManifestDirectoryRoot'] !== ''
            ? $part['zipPackageManifestDirectoryRoot']
            : odf_zip_source_record_directory_root($entryName);
        $extension = is_string($part['packagePartExtension'] ?? null) && $part['packagePartExtension'] !== ''
            ? $part['packagePartExtension']
            : odf_zip_source_record_extension($entryName);
        $extensionKey = $extension ?? '(extensionless)';
        $compressionMethod = is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
        $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== ''
            ? $part['byteExposurePolicy']
            : '(missing)';
        $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== ''
            ? $part['manifestMediaFamily']
            : '(missing)';
        $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) && $part['manifestMediaTypeBase'] !== ''
            ? $part['manifestMediaTypeBase']
            : '(missing)';

        ++$summaries[$bucketKey]['entryCount'];
        if (($part['isDirectory'] ?? false) === true) {
            ++$summaries[$bucketKey]['directoryEntryCount'];
        } else {
            ++$summaries[$bucketKey]['fileEntryCount'];
        }
        if (($part['declaredInManifest'] ?? false) === true) {
            ++$summaries[$bucketKey]['manifestDeclaredEntryCount'];
        }
        if (($part['undeclared'] ?? false) === true) {
            ++$summaries[$bucketKey]['undeclaredEntryCount'];
        }
        if (($part['canExposeBytes'] ?? false) === true) {
            ++$summaries[$bucketKey]['exposableEntryCount'];
        } else {
            ++$summaries[$bucketKey]['blockedEntryCount'];
        }
        foreach ([
            'zipSourceRecordBytes' => 'sourceRecordBytes',
            'zipLocalRecordBytes' => 'localRecordBytes',
            'zipLocalHeaderBytes' => 'localHeaderBytes',
            'zipLocalHeaderFixedHeaderBytes' => 'localHeaderFixedHeaderBytes',
            'zipLocalHeaderVariableFieldBytes' => 'localHeaderVariableFieldBytes',
            'zipLocalHeaderRawNameBytes' => 'localHeaderRawNameBytes',
            'zipLocalHeaderExtraFieldBytes' => 'localHeaderExtraFieldBytes',
            'zipLocalHeaderReviewFieldBytes' => 'localHeaderReviewFieldBytes',
            'zipCompressedDataBytes' => 'compressedDataBytes',
            'zipDataDescriptorBytes' => 'dataDescriptorBytes',
            'zipCentralDirectoryRecordBytes' => 'centralDirectoryRecordBytes',
            'zipCentralDirectoryFixedHeaderBytes' => 'centralDirectoryFixedHeaderBytes',
            'zipCentralDirectoryVariableFieldBytes' => 'centralDirectoryVariableFieldBytes',
            'zipCentralDirectoryRawNameBytes' => 'centralDirectoryRawNameBytes',
            'zipCentralDirectoryExtraFieldBytes' => 'centralDirectoryExtraFieldBytes',
            'zipCentralDirectoryRawCommentBytes' => 'centralDirectoryRawCommentBytes',
            'zipCentralDirectoryReviewFieldBytes' => 'centralDirectoryReviewFieldBytes',
            'compressedByteLength' => 'compressedByteLength',
            'byteLength' => 'uncompressedByteLength',
        ] as $partField => $summaryField) {
            $summaries[$bucketKey][$summaryField] += odf_zip_source_record_int_field($part, $partField);
        }
        $summaries[$bucketKey]['entryNames'][] = $entryName;
        $summaries[$bucketKey]['directoryRootCounts'][$directoryRoot] =
            ($summaries[$bucketKey]['directoryRootCounts'][$directoryRoot] ?? 0) + 1;
        $summaries[$bucketKey]['packagePartExtensionCounts'][$extensionKey] =
            ($summaries[$bucketKey]['packagePartExtensionCounts'][$extensionKey] ?? 0) + 1;
        $summaries[$bucketKey]['compressionMethodCounts'][$compressionMethod] =
            ($summaries[$bucketKey]['compressionMethodCounts'][$compressionMethod] ?? 0) + 1;
        $summaries[$bucketKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
            ($summaries[$bucketKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
        $summaries[$bucketKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
            ($summaries[$bucketKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
        $summaries[$bucketKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
            ($summaries[$bucketKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
        foreach ($roles as $role) {
            $summaries[$bucketKey]['roleCounts'][$role] = ($summaries[$bucketKey]['roleCounts'][$role] ?? 0) + 1;
        }

        $entrySummary = [
            'entryName' => $entryName,
            'sourceRecordByteBucket' => $bucketKey,
            'directoryRoot' => $directoryRoot,
            'packageDirectory' => is_string($part['packageDirectory'] ?? null)
                ? $part['packageDirectory']
                : (is_array($part['pathShape'] ?? null) && is_string($part['pathShape']['directory'] ?? null) ? $part['pathShape']['directory'] : null),
            'packageBasename' => is_string($part['packageBasename'] ?? null)
                ? $part['packageBasename']
                : (is_array($part['pathShape'] ?? null) && is_string($part['pathShape']['basename'] ?? null) ? $part['pathShape']['basename'] : null),
            'packagePartExtension' => $extension,
            'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
            'isDirectory' => ($part['isDirectory'] ?? false) === true,
            'byteLength' => odf_zip_source_record_int_field($part, 'byteLength'),
            'compressedByteLength' => odf_zip_source_record_int_field($part, 'compressedByteLength'),
            'compressionMethod' => is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : null,
            'compressionMethodName' => is_string($part['compressionMethodName'] ?? null) ? $part['compressionMethodName'] : null,
            'sourceRecordBytes' => $sourceRecordBytes,
            'localRecordBytes' => odf_zip_source_record_int_field($part, 'zipLocalRecordBytes'),
            'localHeaderBytes' => odf_zip_source_record_int_field($part, 'zipLocalHeaderBytes'),
            'localHeaderFixedHeaderBytes' => odf_zip_source_record_int_field($part, 'zipLocalHeaderFixedHeaderBytes'),
            'localHeaderVariableFieldBytes' => odf_zip_source_record_int_field($part, 'zipLocalHeaderVariableFieldBytes'),
            'localHeaderRawNameBytes' => odf_zip_source_record_int_field($part, 'zipLocalHeaderRawNameBytes'),
            'localHeaderExtraFieldBytes' => odf_zip_source_record_int_field($part, 'zipLocalHeaderExtraFieldBytes'),
            'localHeaderReviewFieldBytes' => odf_zip_source_record_int_field($part, 'zipLocalHeaderReviewFieldBytes'),
            'compressedDataBytes' => odf_zip_source_record_int_field($part, 'zipCompressedDataBytes'),
            'dataDescriptorBytes' => odf_zip_source_record_int_field($part, 'zipDataDescriptorBytes'),
            'centralDirectoryRecordBytes' => odf_zip_source_record_int_field($part, 'zipCentralDirectoryRecordBytes'),
            'centralDirectoryFixedHeaderBytes' => odf_zip_source_record_int_field($part, 'zipCentralDirectoryFixedHeaderBytes'),
            'centralDirectoryVariableFieldBytes' => odf_zip_source_record_int_field($part, 'zipCentralDirectoryVariableFieldBytes'),
            'centralDirectoryRawNameBytes' => odf_zip_source_record_int_field($part, 'zipCentralDirectoryRawNameBytes'),
            'centralDirectoryExtraFieldBytes' => odf_zip_source_record_int_field($part, 'zipCentralDirectoryExtraFieldBytes'),
            'centralDirectoryRawCommentBytes' => odf_zip_source_record_int_field($part, 'zipCentralDirectoryRawCommentBytes'),
            'centralDirectoryReviewFieldBytes' => odf_zip_source_record_int_field($part, 'zipCentralDirectoryReviewFieldBytes'),
            'sourceRecordIssueCount' => 0,
            'sourceRecordIssues' => [],
            'roles' => $roles,
            'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
            'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
            'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
            'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
            'undeclared' => ($part['undeclared'] ?? false) === true,
            'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
        ];
        $largestEntry = $summaries[$bucketKey]['largestSourceRecordEntry'];
        if (
            !is_array($largestEntry)
            || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
            || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
        ) {
            $summaries[$bucketKey]['largestSourceRecordEntry'] = $entrySummary;
        }
    }

    $buckets = [];
    $counts = [];
    $bytes = [];
    $entryNames = [];
    $orderedSummaries = [];
    foreach (['up-to-127-bytes', '128-to-511-bytes', '512-to-2047-bytes', '2048-plus-bytes'] as $bucketKey) {
        if (!isset($summaries[$bucketKey])) {
            continue;
        }

        sort($summaries[$bucketKey]['entryNames'], SORT_STRING);
        ksort($summaries[$bucketKey]['directoryRootCounts'], SORT_STRING);
        ksort($summaries[$bucketKey]['packagePartExtensionCounts'], SORT_STRING);
        ksort($summaries[$bucketKey]['compressionMethodCounts'], SORT_STRING);
        ksort($summaries[$bucketKey]['byteExposurePolicyCounts'], SORT_STRING);
        ksort($summaries[$bucketKey]['manifestMediaFamilyCounts'], SORT_STRING);
        ksort($summaries[$bucketKey]['manifestMediaTypeBaseCounts'], SORT_STRING);
        ksort($summaries[$bucketKey]['roleCounts'], SORT_STRING);
        $buckets[] = $bucketKey;
        $counts[$bucketKey] = $summaries[$bucketKey]['entryCount'];
        $bytes[$bucketKey] = $summaries[$bucketKey]['sourceRecordBytes'];
        $entryNames[$bucketKey] = $summaries[$bucketKey]['entryNames'];
        $orderedSummaries[] = $summaries[$bucketKey];
    }

    return [
        'buckets' => $buckets,
        'counts' => $counts,
        'bytes' => $bytes,
        'entryNames' => $entryNames,
        'summaries' => $orderedSummaries,
    ];
}

/**
 * @param array<string, mixed> $part
 */
function odf_zip_source_record_byte_bucket_for_part(array $part): string
{
    return odf_zip_source_record_byte_bucket(odf_zip_source_record_int_field($part, 'zipSourceRecordBytes'))['sourceRecordByteBucket'];
}

/**
 * @return array{sourceRecordByteBucket:string,minSourceRecordBytes:int,maxSourceRecordBytes:int|null}
 */
function odf_zip_source_record_byte_bucket(int $sourceRecordBytes): array
{
    if ($sourceRecordBytes <= 127) {
        return ['sourceRecordByteBucket' => 'up-to-127-bytes', 'minSourceRecordBytes' => 0, 'maxSourceRecordBytes' => 127];
    }
    if ($sourceRecordBytes <= 511) {
        return ['sourceRecordByteBucket' => '128-to-511-bytes', 'minSourceRecordBytes' => 128, 'maxSourceRecordBytes' => 511];
    }
    if ($sourceRecordBytes <= 2047) {
        return ['sourceRecordByteBucket' => '512-to-2047-bytes', 'minSourceRecordBytes' => 512, 'maxSourceRecordBytes' => 2047];
    }

    return ['sourceRecordByteBucket' => '2048-plus-bytes', 'minSourceRecordBytes' => 2048, 'maxSourceRecordBytes' => null];
}

/**
 * @param list<array<string, mixed>> $summaries
 * @return array<string, array<string, mixed>>
 */
function odf_zip_source_record_byte_bucket_index_by(array $summaries): array
{
    $indexed = [];
    foreach ($summaries as $summary) {
        $indexed[(string) $summary['sourceRecordByteBucket']] = $summary;
    }

    return $indexed;
}

/**
 * @param array<string, mixed> $part
 */
function odf_zip_source_record_int_field(array $part, string $field): int
{
    return is_int($part[$field] ?? null) ? $part[$field] : 0;
}

function odf_zip_source_record_directory_root(string $entryName): string
{
    $position = strpos($entryName, '/');

    return $position === false ? '/' : substr($entryName, 0, $position + 1);
}

function odf_zip_source_record_extension(string $entryName): ?string
{
    $basename = basename($entryName);
    $position = strrpos($basename, '.');
    if ($position === false || $position === strlen($basename) - 1) {
        return null;
    }

    return strtolower(substr($basename, $position + 1));
}

/**
 * @param array<string, mixed> $part
 * @return list<string>
 */
function odf_zip_source_record_part_roles(array $part): array
{
    $roles = array_values(array_unique(array_filter(
        array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
        static fn (string $role): bool => $role !== ''
    )));

    return $roles === [] ? ['package-part'] : $roles;
}
