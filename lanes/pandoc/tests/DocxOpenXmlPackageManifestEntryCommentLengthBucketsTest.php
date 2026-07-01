<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'projects DOCX ZIP package manifest entry comment length bucket provenance' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_package_manifest_entry_comment_length_bucket_zip_parts(),
            'docx entry comment length bucket review'
        );
        $manifest = $zip->packageManifestPreflight();
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $zipPackage = $package['zipPackage'];
        $buckets = docx_package_manifest_entry_comment_length_bucket_index_by(
            $manifest['entryCommentLengthBucketSummaries'],
            'entryCommentLengthBucket'
        );

        $t->same('Entry comment length buckets.', $document->children[0]->attr('text'));
        $t->same(
            $manifest['entryCommentLengthBucketSummaryCount'],
            $summary['zipPackageManifestEntryCommentLengthBucketSummaryCount']
        );
        $t->same($manifest['entryCommentLengthBuckets'], $summary['zipPackageManifestEntryCommentLengthBuckets']);
        $t->same($manifest['entryCommentLengthBucketCounts'], $summary['zipPackageManifestEntryCommentLengthBucketCounts']);
        $t->same(
            $manifest['entryCommentLengthBucketCommentedCounts'],
            $summary['zipPackageManifestEntryCommentLengthBucketCommentedCounts']
        );
        $t->same(
            $manifest['entryCommentLengthBucketSummaries'],
            $summary['zipPackageManifestEntryCommentLengthBucketSummaries']
        );
        $t->same(
            $manifest['entryCommentLengthBucketSummaryCount'],
            $zipPackage['packageManifestEntryCommentLengthBucketSummaryCount']
        );
        $t->same($manifest['entryCommentLengthBuckets'], $zipPackage['packageManifestEntryCommentLengthBuckets']);
        $t->same($manifest['entryCommentLengthBucketCounts'], $zipPackage['packageManifestEntryCommentLengthBucketCounts']);
        $t->same(
            $manifest['entryCommentLengthBucketCommentedCounts'],
            $zipPackage['packageManifestEntryCommentLengthBucketCommentedCounts']
        );
        $t->same(
            $manifest['entryCommentLengthBucketSummaries'],
            $zipPackage['packageManifestEntryCommentLengthBucketSummaries']
        );

        $t->same(4, $manifest['entryCommentLengthBucketSummaryCount']);
        $t->same(['up-to-15-bytes', '16-to-63-bytes', '64-to-127-bytes', '128-plus-bytes'], $manifest['entryCommentLengthBuckets']);
        $t->same([
            'up-to-15-bytes' => 2,
            '16-to-63-bytes' => 1,
            '64-to-127-bytes' => 1,
            '128-plus-bytes' => 1,
        ], $manifest['entryCommentLengthBucketCounts']);
        $t->same([
            'up-to-15-bytes' => 1,
            '16-to-63-bytes' => 1,
            '64-to-127-bytes' => 1,
            '128-plus-bytes' => 1,
        ], $manifest['entryCommentLengthBucketCommentedCounts']);

        $t->same(['[Content_Types].xml', '_rels/.rels'], $buckets['up-to-15-bytes']['entryNames']);
        $t->same(['_rels/.rels'], $buckets['up-to-15-bytes']['commentedEntryNames']);
        $t->same(12, $buckets['up-to-15-bytes']['centralDirectoryRawCommentBytes']);
        $t->same(['/', '_rels/'], $buckets['up-to-15-bytes']['directoryRoots']);
        $t->same(['rels', 'xml'], $buckets['up-to-15-bytes']['packagePartExtensionKeys']);
        $t->same(['deflated', 'stored'], $buckets['up-to-15-bytes']['compressionMethodNames']);
        $t->same('_rels/.rels', $buckets['up-to-15-bytes']['largestCommentEntryNames'][0]);
        $t->same(12, $buckets['up-to-15-bytes']['maxEntryRawCommentBytes']);

        $t->same(['word/document.xml'], $buckets['16-to-63-bytes']['entryNames']);
        $t->same(32, $buckets['16-to-63-bytes']['centralDirectoryRawCommentBytes']);
        $t->same(['word/media/review.png'], $buckets['64-to-127-bytes']['entryNames']);
        $t->same(80, $buckets['64-to-127-bytes']['centralDirectoryRawCommentBytes']);
        $t->same(['customXml/comment.bin'], $buckets['128-plus-bytes']['entryNames']);
        $t->same(140, $buckets['128-plus-bytes']['centralDirectoryRawCommentBytes']);
        $t->same('zip-entry-comment-source-metadata-only', $buckets['128-plus-bytes']['byteExposurePolicy']);
        $t->same(false, $buckets['128-plus-bytes']['canExposeBytes']);
        $t->same(4, $manifest['entryCommentCount']);
        $t->same(264, $manifest['centralDirectoryRawCommentBytes']);

        $encodedBuckets = json_encode($manifest['entryCommentLengthBucketSummaries'], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedBuckets, str_repeat('b', 40)));
        $t->true(!str_contains($encodedBuckets, str_repeat('p', 40)));
        $t->true(!str_contains($encodedBuckets, str_repeat('d', 20)));
        $t->true(!str_contains($encodedBuckets, str_repeat('r', 8)));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_package_manifest_entry_comment_length_bucket_zip_parts(): array
{
    $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
    $relationships = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
    $document = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Entry comment length buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

    return [
        ['name' => '[Content_Types].xml', 'data' => $contentTypes, 'compressionMethod' => 0],
        ['name' => '_rels/.rels', 'data' => $relationships, 'compressionMethod' => 8, 'comment' => str_repeat('r', 12)],
        ['name' => 'word/document.xml', 'data' => $document, 'compressionMethod' => 0, 'comment' => str_repeat('d', 32)],
        ['name' => 'word/media/review.png', 'data' => str_repeat('P', 16), 'compressionMethod' => 0, 'comment' => str_repeat('p', 80)],
        ['name' => 'customXml/comment.bin', 'data' => 'comment bucket bytes', 'compressionMethod' => 0, 'comment' => str_repeat('b', 140)],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_manifest_entry_comment_length_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (!is_array($item) || !is_string($item[$key] ?? null)) {
            continue;
        }

        $indexed[$item[$key]] = $item;
    }

    return $indexed;
}
