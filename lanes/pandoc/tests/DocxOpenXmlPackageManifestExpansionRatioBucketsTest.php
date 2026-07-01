<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'projects DOCX ZIP package manifest expansion-ratio bucket provenance' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_package_manifest_expansion_ratio_bucket_zip_parts(),
            'docx expansion bucket review'
        );
        $manifest = $zip->packageManifestPreflight();
        $manifestBuckets = docx_package_manifest_expansion_ratio_bucket_index_by(
            $manifest['expansionRatioBucketSummaries'],
            'expansionRatioBucket'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $zipPackage = $package['zipPackage'];

        $t->same('Expansion bucket provenance.', $document->children[0]->attr('text'));
        $t->same(['zero-byte', 'up-to-1x', 'over-100x'], $manifest['expansionRatioBuckets']);
        $t->same(3, $manifest['expansionRatioBucketSummaryCount']);
        $t->same($manifest['expansionRatioBucketSummaryCount'], $summary['zipPackageManifestExpansionRatioBucketSummaryCount']);
        $t->same($manifest['expansionRatioBuckets'], $summary['zipPackageManifestExpansionRatioBuckets']);
        $t->same($manifest['expansionRatioBucketSummaries'], $summary['zipPackageManifestExpansionRatioBucketSummaries']);
        $t->same($manifest['expansionRatioBucketSummaryCount'], $zipPackage['packageManifestExpansionRatioBucketSummaryCount']);
        $t->same($manifest['expansionRatioBuckets'], $zipPackage['packageManifestExpansionRatioBuckets']);
        $t->same($manifest['expansionRatioBucketSummaries'], $zipPackage['packageManifestExpansionRatioBucketSummaries']);
        $t->same(false, array_key_exists('contents', $summary['zipPackageManifestExpansionRatioBucketSummaries'][0]));
        $t->same(false, array_key_exists('contents', $zipPackage['packageManifestExpansionRatioBucketSummaries'][0]));

        $t->same(['word/media/zero.bin'], $manifestBuckets['zero-byte']['entryNames']);
        $t->same(['stored'], $manifestBuckets['zero-byte']['compressionMethodNames']);
        $t->same(0.0, $manifestBuckets['zero-byte']['largestExpansionRatio']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/media/stored.bin',
        ], $manifestBuckets['up-to-1x']['entryNames']);
        $t->same(['stored'], $manifestBuckets['up-to-1x']['compressionMethodNames']);
        $t->same(['word/media/high.txt'], $manifestBuckets['over-100x']['entryNames']);
        $t->same(['deflated'], $manifestBuckets['over-100x']['compressionMethodNames']);
        $t->same('word/media/high.txt', $manifestBuckets['over-100x']['largestExpansionRatioEntryName']);
        $t->true(
            $manifestBuckets['over-100x']['largestExpansionRatio'] > 100.0,
            'deflated fixture part should land in the over-100x expansion-ratio bucket'
        );
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_manifest_expansion_ratio_bucket_zip_parts(): array
{
    $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Default Extension="txt" ContentType="text/plain"/>
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
    <w:p><w:r><w:t>Expansion bucket provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

    return [
        ['name' => '[Content_Types].xml', 'data' => $contentTypes, 'compressionMethod' => 0],
        ['name' => '_rels/.rels', 'data' => $relationships, 'compressionMethod' => 0],
        ['name' => 'word/document.xml', 'data' => $document, 'compressionMethod' => 0],
        ['name' => 'word/media/zero.bin', 'data' => '', 'compressionMethod' => 0],
        ['name' => 'word/media/stored.bin', 'data' => 'stored package manifest review bytes', 'compressionMethod' => 0],
        ['name' => 'word/media/high.txt', 'data' => str_repeat('A', 8192), 'compressionMethod' => 8],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_manifest_expansion_ratio_bucket_index_by(array $items, string $key): array
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
