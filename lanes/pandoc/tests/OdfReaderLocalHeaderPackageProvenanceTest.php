<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Local header provenance packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Local Header Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$localExtraFieldData = pack('vv', 0xcafe, 3) . 'odf';

$parts = [
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0, 'extraFieldData' => $localExtraFieldData],
];

$indexByPath = static function (array $entries, string $key): array {
    $indexed = [];
    foreach ($entries as $entry) {
        if (is_array($entry) && is_string($entry[$key] ?? null)) {
            $indexed[$entry[$key]] = $entry;
        }
    }

    return $indexed;
};

$assertHeroLocalHeader = static function (TestRunner $t, array $hero, string $extraFieldData): void {
    $t->same(30 + strlen('Pictures/hero.png') + strlen($extraFieldData), $hero['localHeaderLength']);
    $t->same(strlen('Pictures/hero.png') + strlen($extraFieldData), $hero['localVariableFieldsLength']);
    $t->same(strlen('Pictures/hero.png'), $hero['localNameLength']);
    $t->same(strlen($extraFieldData), $hero['localExtraFieldLength']);
    $t->same(true, $hero['hasLocalExtraFields']);
    $t->same(1, $hero['localExtraFieldRecordCount']);
    $t->same([0xcafe], $hero['localExtraFieldIds']);
    $t->same([], $hero['localExtraFieldStructureIssues']);

    $record = $hero['localExtraFieldRecords'][0];
    $t->same(0xcafe, $record['id']);
    $t->same('cafe', $record['idHex']);
    $t->same(3, $record['declaredDataLength']);
    $t->same(3, $record['availableDataBytes']);
    $t->same(false, $record['isTruncated']);
    $t->same($hero['localExtraFieldOffset'], $record['localExtraFieldRecordOffset']);
    $t->same($hero['localExtraFieldOffset'] + 4, $record['localExtraFieldDataOffset']);
    $t->same($hero['localExtraFieldOffset'] + strlen($extraFieldData), $record['localExtraFieldRecordEnd']);

    $t->same($hero['localHeaderOffset'] + $hero['localHeaderLength'], $hero['localDataStart']);
    $t->same($hero['localDataStart'] + $hero['compressedByteLength'], $hero['localCompressedDataEnd']);
    $t->same($hero['localCompressedDataEnd'], $hero['localRecordEnd']);
    $t->same(true, $hero['localRecordContiguousWithNext']);
    $t->same(false, $hero['localHeaderUsesDataDescriptor']);
    $t->same(null, $hero['localHeaderDescriptorOffset']);
    $t->same(null, $hero['localHeaderDescriptorLength']);
    $t->same(0x0800, $hero['localHeaderGeneralPurposeFlags']);
    $t->same(sprintf('%08x', crc32('PNGDATA')), $hero['localHeaderCrc32Hex']);
    $t->same(7, $hero['localHeaderCompressedSize']);
    $t->same(7, $hero['localHeaderUncompressedSize']);
};

return [
    'preserves ODT ZIP local header provenance in package review metadata' => static function (TestRunner $t) use ($parts, $localExtraFieldData, $indexByPath, $assertHeroLocalHeader): void {
        $compactPackage = ZipPackage::fromParts($parts, 'odt local header review');
        $compactSummary = OpenDocumentPackage::fromPackage($compactPackage)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactHero = $compactInventory['parts']['Pictures/hero.png'];
        $compactMimetype = $compactInventory['parts']['mimetype'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactIdentityParts = $indexByPath($compactIdentity['packageEntries'], 'path');

        $t->same($compactPackage->localHeaderPreflight(), $compactInventory['localHeaders']);
        $t->same(count($parts), $compactInventory['localHeaderEntryCount']);
        $t->same(1, $compactInventory['localExtraFieldEntryCount']);
        $t->same(1, $compactInventory['localExtraFieldRecordCount']);
        $t->same([0xcafe], $compactInventory['localExtraFieldRecordIds']);
        $t->same(false, $compactMimetype['hasLocalExtraFields']);
        $t->same(0, $compactMimetype['localExtraFieldLength']);
        $t->same([], $compactMimetype['localExtraFieldIds']);
        $assertHeroLocalHeader($t, $compactHero, $localExtraFieldData);
        $t->same($compactHero['localExtraFieldRecords'], $compactIdentityParts['Pictures/hero.png']['localExtraFieldRecords']);
        $t->same(1, $compactIdentity['localExtraFieldRecordCount']);
        $t->same([0xcafe], $compactIdentity['localExtraFieldRecordIds']);
        $t->same('odf-package-identity-metadata-only', $compactIdentity['byteExposurePolicy']);
        $t->same(false, $compactIdentity['canExposeBytes']);

        $richPackage = ZipPackage::fromParts($parts, 'odt local header review');
        $result = (new OdfReader())->readPackage($richPackage);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $richHero = $provenance['parts']['Pictures/hero.png'];
        $richMimetype = $provenance['parts']['mimetype'];
        $richIdentity = $provenance['packageIdentity'];
        $richIdentityParts = $indexByPath($richIdentity['packageEntries'], 'part');

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same($richPackage->localHeaderPreflight(), $provenance['localHeaders']);
        $t->same(count($parts), $provenance['localHeaderEntryCount']);
        $t->same(1, $provenance['localExtraFieldEntryCount']);
        $t->same(1, $provenance['localExtraFieldRecordCount']);
        $t->same([0xcafe], $provenance['localExtraFieldRecordIds']);
        $t->same(false, $richMimetype['hasLocalExtraFields']);
        $t->same(0, $richMimetype['localExtraFieldLength']);
        $t->same([], $richMimetype['localExtraFieldIds']);
        $assertHeroLocalHeader($t, $richHero, $localExtraFieldData);
        $t->same($richHero['localExtraFieldRecords'], $richIdentityParts['Pictures/hero.png']['localExtraFieldRecords']);
        $t->same(1, $richIdentity['localExtraFieldRecordCount']);
        $t->same([0xcafe], $richIdentity['localExtraFieldRecordIds']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
        $t->same(false, $richIdentity['canExposeBytes']);
    },
];
