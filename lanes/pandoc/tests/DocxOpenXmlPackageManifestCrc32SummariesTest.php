<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'projects DOCX ZIP package manifest CRC32 provenance' => static function (TestRunner $t): void {
        $duplicateBytes = "shared media package bytes\n";
        $duplicateCrc32Hex = sprintf('%08x', crc32($duplicateBytes));
        $zip = ZipPackage::fromParts(
            docx_package_manifest_crc32_zip_parts($duplicateBytes),
            'docx crc32 package manifest review'
        );
        $manifest = $zip->packageManifestPreflight();
        $manifestCrc32Summaries = docx_package_manifest_crc32_index_by(
            $manifest['crc32Summaries'],
            'crc32Hex'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $zipPackage = $package['zipPackage'];

        $t->same('CRC32 package provenance.', $document->children[0]->attr('text'));
        $t->same(1, $manifest['duplicateCrc32HexCount']);
        $t->same(2, $manifest['duplicateCrc32EntryCount']);
        $t->same(true, $manifest['hasDuplicateCrc32Entries']);
        $t->same([$duplicateCrc32Hex], $manifest['duplicateCrc32Hexes']);
        $t->same($manifest['crc32SummaryCount'], $summary['zipPackageManifestCrc32SummaryCount']);
        $t->same($manifest['crc32Summaries'], $summary['zipPackageManifestCrc32Summaries']);
        $t->same($manifest['duplicateCrc32HexCount'], $summary['zipPackageManifestDuplicateCrc32HexCount']);
        $t->same($manifest['duplicateCrc32EntryCount'], $summary['zipPackageManifestDuplicateCrc32EntryCount']);
        $t->same($manifest['hasDuplicateCrc32Entries'], $summary['zipPackageManifestHasDuplicateCrc32Entries']);
        $t->same($manifest['duplicateCrc32Hexes'], $summary['zipPackageManifestDuplicateCrc32Hexes']);
        $t->same($manifest['duplicateCrc32Summaries'], $summary['zipPackageManifestDuplicateCrc32Summaries']);
        $t->same($manifest['crc32SummaryCount'], $zipPackage['packageManifestCrc32SummaryCount']);
        $t->same($manifest['crc32Summaries'], $zipPackage['packageManifestCrc32Summaries']);
        $t->same($manifest['duplicateCrc32HexCount'], $zipPackage['packageManifestDuplicateCrc32HexCount']);
        $t->same($manifest['duplicateCrc32EntryCount'], $zipPackage['packageManifestDuplicateCrc32EntryCount']);
        $t->same($manifest['hasDuplicateCrc32Entries'], $zipPackage['packageManifestHasDuplicateCrc32Entries']);
        $t->same($manifest['duplicateCrc32Hexes'], $zipPackage['packageManifestDuplicateCrc32Hexes']);
        $t->same($manifest['duplicateCrc32Summaries'], $zipPackage['packageManifestDuplicateCrc32Summaries']);
        $t->same(false, array_key_exists('contents', $summary['zipPackageManifestCrc32Summaries'][0]));
        $t->same(false, array_key_exists('contents', $zipPackage['packageManifestDuplicateCrc32Summaries'][0]));

        $duplicateSummary = $manifestCrc32Summaries[$duplicateCrc32Hex];
        $t->same(2, $duplicateSummary['entryCount']);
        $t->same(2, $duplicateSummary['fileEntryCount']);
        $t->same(0, $duplicateSummary['directoryEntryCount']);
        $t->same(['word/media/shared-a.bin', 'word/media/shared-b.bin'], $duplicateSummary['entryNames']);
        $t->same(['deflated', 'stored'], $duplicateSummary['compressionMethodNames']);
        $t->same(['word/'], $duplicateSummary['directoryRoots']);
        $t->same(strlen($duplicateBytes) * 2, $duplicateSummary['uncompressedBytes']);
        $t->true($duplicateSummary['sourceRecordBytes'] > $duplicateSummary['uncompressedBytes']);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_manifest_crc32_zip_parts(string $duplicateBytes): array
{
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
    <w:p><w:r><w:t>CRC32 package provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

    return [
        ['name' => '[Content_Types].xml', 'data' => $contentTypes, 'compressionMethod' => 0],
        ['name' => '_rels/.rels', 'data' => $relationships, 'compressionMethod' => 0],
        ['name' => 'word/document.xml', 'data' => $document, 'compressionMethod' => 0],
        ['name' => 'word/media/shared-a.bin', 'data' => $duplicateBytes, 'compressionMethod' => 0],
        ['name' => 'word/media/shared-b.bin', 'data' => $duplicateBytes, 'compressionMethod' => 8],
        ['name' => 'word/media/unique.bin', 'data' => "unique media package bytes\n", 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_manifest_crc32_index_by(array $items, string $key): array
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
