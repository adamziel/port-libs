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
      <text:p>Data descriptor package provenance.</text:p>
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
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Data Descriptor Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildZipPackage = static function (array $entries): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'] ?? '';
        $method = $entry['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $flags = $entry['generalPurposeFlags'] ?? 0x0800;
        $descriptor = ($entry['descriptor'] ?? false) === true;
        if ($descriptor) {
            $flags |= 0x0008;
        }

        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if ($compressed === false) {
            throw new RuntimeException("Unable to deflate {$name}");
        }

        $crc = $crc32($data);
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $offset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $descriptor ? 0 : $crc,
            $descriptor ? 0 : $compressedSize,
            $descriptor ? 0 : $uncompressedSize,
            strlen($name),
            0
        );
        $body .= $name . $compressed;
        if ($descriptor) {
            $body .= "PK\x07\x08" . pack('VVV', $crc, $compressedSize, $uncompressedSize);
        }

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $name;
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $centralOffset, 0)
    );
};

$buildPackage = static function () use ($buildZipPackage, $contentXml, $stylesXml, $metaXml): ZipPackage {
    $heroBytes = 'PNGDATA-DESCRIPTOR';
    $heroSize = strlen($heroBytes);
    $manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="{$heroSize}"/>
</manifest:manifest>
XML;

    return $buildZipPackage([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 8],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 8],
        ['name' => 'Pictures/hero.png', 'data' => $heroBytes, 'compressionMethod' => 8, 'descriptor' => true],
    ]);
};

return [
    'preserves ODT ZIP data descriptor provenance in package review metadata' => static function (TestRunner $t) use ($buildPackage): void {
        $package = $buildPackage();
        $descriptorPreflight = $package->dataDescriptorPreflight();
        $heroEntry = $package->entry('Pictures/hero.png');
        $compact = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compact['packageInventory'];
        $compactHero = $compactInventory['parts']['Pictures/hero.png'];
        $compactIdentityParts = [];
        foreach ($compact['packageIdentity']['packageEntries'] as $item) {
            $compactIdentityParts[$item['path']] = $item;
        }
        $compactMedia = $compact['mediaParts'][0];

        $t->same(1, $compactInventory['dataDescriptorEntryCount']);
        $t->same(1, $compactInventory['signedDataDescriptorEntryCount']);
        $t->same(0, $compactInventory['unsignedDataDescriptorEntryCount']);
        $t->same(16, $compactInventory['dataDescriptorByteLength']);
        $t->same(1, $compactInventory['matchedDataDescriptorEntryCount']);
        $t->same(0, $compactInventory['dataDescriptorIssueCount']);
        $t->same(1, $compactInventory['generalPurposeFlagDataDescriptorEntryCount']);
        $t->same(1, $compactInventory['generalPurposeFlagStrictReviewEntryCount']);
        $t->same('odf-zip-data-descriptor-metadata-only', $compactInventory['dataDescriptors']['byteExposurePolicy']);
        $t->same(false, $compactInventory['dataDescriptors']['canExposeBytes']);
        $t->same($descriptorPreflight['descriptorEntries'], $compactInventory['dataDescriptors']['descriptorEntries']);

        $t->same(0x0808, $compactHero['generalPurposeFlags']);
        $t->same(['data-descriptor', 'utf-8-names'], $compactHero['generalPurposeFlagNames']);
        $t->same(true, $compactHero['usesDataDescriptor']);
        $t->same(true, $compactHero['requiresGeneralPurposeFlagReview']);
        $t->same(['data-descriptor-entry'], $compactHero['generalPurposeFlagIssues']);
        $t->same(true, $compactHero['dataDescriptorHasSignature']);
        $t->same(16, $compactHero['dataDescriptorLength']);
        $t->same(true, $compactHero['dataDescriptorValuesMatchCentral']);
        $t->same(true, $compactHero['hasZeroLocalHeaderPlaceholders']);
        $t->same(0, $compactHero['dataDescriptorIssueCount']);
        $t->same($heroEntry->crc32Hex(), $compactHero['dataDescriptorCrc32Hex']);
        $t->same($heroEntry->compressedSize, $compactHero['dataDescriptorCompressedSize']);
        $t->same($heroEntry->uncompressedSize, $compactHero['dataDescriptorUncompressedSize']);
        $t->same(true, $compactMedia['canExposeBytes']);
        $t->same($heroEntry->crc32Hex(), $compactMedia['crc32']);
        $t->same(true, $compactIdentityParts['Pictures/hero.png']['usesDataDescriptor']);
        $t->same(true, $compactIdentityParts['Pictures/hero.png']['dataDescriptorValuesMatchCentral']);

        $result = (new OdfReader())->readPackage($package);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $richHero = $provenance['parts']['Pictures/hero.png'];
        $richIdentityParts = [];
        foreach ($provenance['packageIdentity']['packageEntries'] as $item) {
            $richIdentityParts[$item['part']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(1, $provenance['dataDescriptorEntryCount']);
        $t->same(1, $provenance['signedDataDescriptorEntryCount']);
        $t->same(1, $provenance['matchedDataDescriptorEntryCount']);
        $t->same(0, $provenance['dataDescriptorIssueCount']);
        $t->same(16, $provenance['dataDescriptorByteLength']);
        $t->same(1, $provenance['generalPurposeFlagDataDescriptorEntryCount']);
        $t->same(1, $provenance['generalPurposeFlagStrictReviewEntryCount']);
        $t->same('odf-zip-data-descriptor-metadata-only', $provenance['dataDescriptors']['byteExposurePolicy']);
        $t->same(false, $provenance['dataDescriptors']['canExposeBytes']);
        $t->same($descriptorPreflight['descriptorEntries'], $provenance['dataDescriptors']['descriptorEntries']);

        $t->same(0x0808, $richHero['generalPurposeFlags']);
        $t->same(['data-descriptor', 'utf-8-names'], $richHero['generalPurposeFlagNames']);
        $t->same(true, $richHero['usesDataDescriptor']);
        $t->same(true, $richHero['requiresGeneralPurposeFlagReview']);
        $t->same(['data-descriptor-entry'], $richHero['generalPurposeFlagIssues']);
        $t->same(true, $richHero['dataDescriptorHasSignature']);
        $t->same(16, $richHero['dataDescriptorLength']);
        $t->same(true, $richHero['dataDescriptorValuesMatchCentral']);
        $t->same(true, $richHero['hasZeroLocalHeaderPlaceholders']);
        $t->same(0, $richHero['dataDescriptorIssueCount']);
        $t->same($heroEntry->crc32Hex(), $richHero['dataDescriptorCrc32Hex']);
        $t->same($heroEntry->compressedSize, $richHero['dataDescriptorCompressedSize']);
        $t->same($heroEntry->uncompressedSize, $richHero['dataDescriptorUncompressedSize']);
        $t->same(1, $provenance['packageIdentity']['dataDescriptorEntryCount']);
        $t->same(1, $provenance['packageIdentity']['signedDataDescriptorEntryCount']);
        $t->same(16, $provenance['packageIdentity']['dataDescriptorByteLength']);
        $t->same(true, $richIdentityParts['Pictures/hero.png']['usesDataDescriptor']);
        $t->same(true, $richIdentityParts['Pictures/hero.png']['dataDescriptorValuesMatchCentral']);
    },
];
