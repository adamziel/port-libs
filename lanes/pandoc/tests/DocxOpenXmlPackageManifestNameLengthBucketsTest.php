<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

$longPackagePath = 'word/media/' . str_repeat('l', 54) . '.bin';
$veryLongPackagePath = 'word/media/' . str_repeat('v', 118) . '.bin';

return [
    'projects DOCX ZIP package manifest entry-name length bucket provenance' => static function (
        TestRunner $t
    ) use ($longPackagePath, $veryLongPackagePath): void {
        $zip = ZipPackage::fromParts(
            docx_package_manifest_name_length_bucket_zip_parts($longPackagePath, $veryLongPackagePath),
            'docx name bucket review'
        );
        $manifest = $zip->packageManifestPreflight();
        $manifestBuckets = docx_package_manifest_name_length_bucket_index_by(
            $manifest['nameLengthBucketSummaries'],
            'nameLengthBucket'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $zipPackage = $package['zipPackage'];
        $zipEntries = docx_package_manifest_name_length_bucket_index_by($zipPackage['entries'], 'packagePath');

        $t->same('Name bucket provenance.', $document->children[0]->attr('text'));
        $t->same(4, $manifest['nameLengthBucketSummaryCount']);
        $t->same([
            'up-to-15-bytes',
            '16-to-63-bytes',
            '64-to-127-bytes',
            '128-plus-bytes',
        ], $manifest['nameLengthBuckets']);
        $t->same($manifest['nameLengthBucketSummaryCount'], $summary['zipPackageManifestNameLengthBucketSummaryCount']);
        $t->same($manifest['nameLengthBuckets'], $summary['zipPackageManifestNameLengthBuckets']);
        $t->same($manifest['nameLengthBucketSummaries'], $summary['zipPackageManifestNameLengthBucketSummaries']);
        $t->same($manifest['nameLengthBucketSummaryCount'], $zipPackage['packageManifestNameLengthBucketSummaryCount']);
        $t->same($manifest['nameLengthBuckets'], $zipPackage['packageManifestNameLengthBuckets']);
        $t->same($manifest['nameLengthBucketSummaries'], $zipPackage['packageManifestNameLengthBucketSummaries']);
        $t->same(false, array_key_exists('contents', $summary['zipPackageManifestNameLengthBucketSummaries'][0]));
        $t->same(false, array_key_exists('contents', $zipPackage['packageManifestNameLengthBucketSummaries'][0]));

        $t->same(['_rels/.rels', 'word/media/'], $manifestBuckets['up-to-15-bytes']['entryNames']);
        $t->same(2, $manifestBuckets['up-to-15-bytes']['entryCount']);
        $t->same(1, $manifestBuckets['up-to-15-bytes']['directoryEntryCount']);
        $t->same(['_rels/', 'word/'], $manifestBuckets['up-to-15-bytes']['directoryRoots']);
        $t->same(['(directory)', 'rels'], $manifestBuckets['up-to-15-bytes']['packagePartExtensionKeys']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $manifestBuckets['16-to-63-bytes']['entryNames']);
        $t->same(2, $manifestBuckets['16-to-63-bytes']['fileEntryCount']);
        $t->same([$longPackagePath], $manifestBuckets['64-to-127-bytes']['entryNames']);
        $t->same(strlen($longPackagePath), $manifestBuckets['64-to-127-bytes']['entryNameBytes']);
        $t->same([$veryLongPackagePath], $manifestBuckets['128-plus-bytes']['entryNames']);
        $t->same(strlen($veryLongPackagePath), $manifestBuckets['128-plus-bytes']['entryNameBytes']);

        $t->same(strlen('word/media/'), $zipEntries['word/media/']['entryNameBytes']);
        $t->same('up-to-15-bytes', $zipEntries['word/media/']['entryNameLengthBucket']);
        $t->same(null, $zipEntries['word/media/']['partName']);
        $t->same(false, $zipEntries['word/media/']['loadedPart']);
        $t->same(strlen($longPackagePath), $zipEntries[$longPackagePath]['entryNameBytes']);
        $t->same('64-to-127-bytes', $zipEntries[$longPackagePath]['entryNameLengthBucket']);
        $t->same(strlen($veryLongPackagePath), $zipEntries[$veryLongPackagePath]['entryNameBytes']);
        $t->same('128-plus-bytes', $zipEntries[$veryLongPackagePath]['entryNameLengthBucket']);
        $t->same('docx-zip-entry-metadata-only', $zipEntries[$veryLongPackagePath]['byteExposurePolicy']);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_manifest_name_length_bucket_zip_parts(
    string $longPackagePath,
    string $veryLongPackagePath
): array {
    $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
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
    <w:p><w:r><w:t>Name bucket provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

    return [
        ['name' => '_rels/.rels', 'data' => $relationships, 'compressionMethod' => 0],
        ['name' => 'word/media/', 'data' => '', 'compressionMethod' => 0],
        ['name' => '[Content_Types].xml', 'data' => $contentTypes, 'compressionMethod' => 0],
        ['name' => 'word/document.xml', 'data' => $document, 'compressionMethod' => 8],
        ['name' => $longPackagePath, 'data' => 'long name package payload', 'compressionMethod' => 0],
        ['name' => $veryLongPackagePath, 'data' => 'very long name package payload', 'compressionMethod' => 8],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_manifest_name_length_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}
