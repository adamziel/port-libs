<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'projects DOCX ZIP package manifest name-length bucket provenance' => static function (TestRunner $t): void {
        $longName = 'word/media/' . str_repeat('a', 52) . '.png';
        $veryLongName = 'word/media/' . str_repeat('b', 116) . '.bin';
        $zip = ZipPackage::fromParts(
            docx_package_manifest_name_length_bucket_zip_parts($longName, $veryLongName),
            'docx name-length bucket review'
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

        $t->same('Name-length bucket provenance.', $document->children[0]->attr('text'));
        $t->same([
            'up-to-15-bytes',
            '16-to-63-bytes',
            '64-to-127-bytes',
            '128-plus-bytes',
        ], $manifest['nameLengthBuckets']);
        $t->same(4, $manifest['nameLengthBucketSummaryCount']);
        $t->same($manifest['nameLengthBucketSummaryCount'], $summary['zipPackageManifestNameLengthBucketSummaryCount']);
        $t->same($manifest['nameLengthBuckets'], $summary['zipPackageManifestNameLengthBuckets']);
        $t->same($manifest['nameLengthBucketSummaries'], $summary['zipPackageManifestNameLengthBucketSummaries']);
        $t->same($manifest['nameLengthBucketSummaryCount'], $zipPackage['packageManifestNameLengthBucketSummaryCount']);
        $t->same($manifest['nameLengthBuckets'], $zipPackage['packageManifestNameLengthBuckets']);
        $t->same($manifest['nameLengthBucketSummaries'], $zipPackage['packageManifestNameLengthBucketSummaries']);
        $t->same(false, array_key_exists('contents', $summary['zipPackageManifestNameLengthBucketSummaries'][0]));
        $t->same(false, array_key_exists('contents', $zipPackage['packageManifestNameLengthBucketSummaries'][0]));

        $t->same(strlen('_rels/.rels'), $zipEntries['_rels/.rels']['entryNameBytes']);
        $t->same('up-to-15-bytes', $zipEntries['_rels/.rels']['entryNameLengthBucket']);
        $t->same(strlen('word/'), $zipEntries['word/']['entryNameBytes']);
        $t->same('up-to-15-bytes', $zipEntries['word/']['entryNameLengthBucket']);
        $t->same(strlen('word/document.xml'), $zipEntries['word/document.xml']['entryNameBytes']);
        $t->same('16-to-63-bytes', $zipEntries['word/document.xml']['entryNameLengthBucket']);
        $t->same(strlen($longName), $zipEntries[$longName]['entryNameBytes']);
        $t->same('64-to-127-bytes', $zipEntries[$longName]['entryNameLengthBucket']);
        $t->same(strlen($veryLongName), $zipEntries[$veryLongName]['entryNameBytes']);
        $t->same('128-plus-bytes', $zipEntries[$veryLongName]['entryNameLengthBucket']);

        $t->same(2, $manifestBuckets['up-to-15-bytes']['entryCount']);
        $t->same(1, $manifestBuckets['up-to-15-bytes']['fileEntryCount']);
        $t->same(1, $manifestBuckets['up-to-15-bytes']['directoryEntryCount']);
        $t->same(['_rels/.rels', 'word/'], $manifestBuckets['up-to-15-bytes']['entryNames']);
        $t->same(['(directory)', 'rels'], $manifestBuckets['up-to-15-bytes']['packagePartExtensionKeys']);
        $t->same(strlen('_rels/.rels') + strlen('word/'), $manifestBuckets['up-to-15-bytes']['entryNameBytes']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $manifestBuckets['16-to-63-bytes']['entryNames']);
        $t->same([$longName], $manifestBuckets['64-to-127-bytes']['entryNames']);
        $t->same(strlen($longName), $manifestBuckets['64-to-127-bytes']['entryNameBytes']);
        $t->same([$veryLongName], $manifestBuckets['128-plus-bytes']['entryNames']);
        $t->same([$veryLongName], $manifestBuckets['128-plus-bytes']['longestEntryNames']);
        $t->same(strlen($veryLongName), $manifestBuckets['128-plus-bytes']['entryNameBytes']);
        $t->same($manifestBuckets['64-to-127-bytes'], $summary['zipPackageManifestNameLengthBucketSummaries'][2]);
        $t->same($manifestBuckets['128-plus-bytes'], $zipPackage['packageManifestNameLengthBucketSummaries'][3]);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_manifest_name_length_bucket_zip_parts(string $longName, string $veryLongName): array
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
    <w:p><w:r><w:t>Name-length bucket provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

    return [
        ['name' => '[Content_Types].xml', 'data' => $contentTypes, 'compressionMethod' => 0],
        ['name' => '_rels/.rels', 'data' => $relationships, 'compressionMethod' => 0],
        ['name' => 'word/', 'data' => '', 'compressionMethod' => 0],
        ['name' => 'word/document.xml', 'data' => $document, 'compressionMethod' => 0],
        ['name' => $longName, 'data' => 'PNGDATA', 'compressionMethod' => 0],
        ['name' => $veryLongName, 'data' => 'BINDATA', 'compressionMethod' => 0],
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
        if (!is_array($item) || !is_string($item[$key] ?? null)) {
            continue;
        }

        $indexed[$item[$key]] = $item;
    }

    return $indexed;
}
