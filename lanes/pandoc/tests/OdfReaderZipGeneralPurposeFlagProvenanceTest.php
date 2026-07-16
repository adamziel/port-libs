<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>ZIP flag provenance packet.</text:p>
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
    <dc:title>ZIP Flag Provenance Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));

/**
 * @param list<array{name:string, data:string, method?:int, flags?:int, descriptor?:bool}> $entries
 */
$buildZipPackage = static function (array $entries) use ($crc32): string {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'] ?? 0;
        $flags = $entry['flags'] ?? 0x0800;
        $usesDescriptor = ($entry['descriptor'] ?? false) === true;
        if ($usesDescriptor) {
            $flags |= 0x0008;
        }

        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if ($compressed === false) {
            throw new RuntimeException('Unable to deflate ZIP fixture entry ' . $name);
        }

        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $crc = $crc32($data);
        $offset = strlen($body);
        $localCrc = $usesDescriptor ? 0 : $crc;
        $localCompressedSize = $usesDescriptor ? 0 : $compressedSize;
        $localUncompressedSize = $usesDescriptor ? 0 : $uncompressedSize;

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $localCrc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($name),
            0
        );
        $body .= $name . $compressed;
        if ($usesDescriptor) {
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
            0,
            $offset
        );
        $central .= $name;
    }

    $centralOffset = strlen($body);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $centralOffset, 0);
};

$buildPackage = static function (int $contentFlags = 0x080e) use (
    $buildZipPackage,
    $manifestXml,
    $contentXml,
    $stylesXml,
    $metaXml
): ZipPackage {
    return ZipPackage::fromString($buildZipPackage([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'method' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'method' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'method' => 8, 'flags' => $contentFlags, 'descriptor' => true],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'method' => 8],
        ['name' => 'meta.xml', 'data' => $metaXml, 'method' => 0],
    ]));
};

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$generalPurposeFlagSubset = static function (array $item): array {
    $keys = [
        'zipGeneralPurposeFlags',
        'zipGeneralPurposeFlagNames',
        'zipUnsupportedGeneralPurposeFlagBits',
        'zipGeneralPurposeFlagsSupported',
        'zipUsesUtf8Names',
        'zipGeneralPurposeUsesDataDescriptor',
        'zipDeflateOptionFlags',
        'zipDeflateOptionName',
        'zipGeneralPurposeRequiresStrictReview',
        'zipGeneralPurposeFlagIssues',
    ];
    $defaults = [
        'zipGeneralPurposeFlagNames' => [],
        'zipGeneralPurposeFlagIssues' => [],
    ];

    $subset = [];
    foreach ($keys as $key) {
        $subset[$key] = array_key_exists($key, $item) ? $item[$key] : ($defaults[$key] ?? null);
    }

    return $subset;
};

$expectedContentFlags = [
    'zipGeneralPurposeFlags' => 0x080e,
    'zipGeneralPurposeFlagNames' => ['deflate-super-fast', 'data-descriptor', 'utf-8-names'],
    'zipUnsupportedGeneralPurposeFlagBits' => 0,
    'zipGeneralPurposeFlagsSupported' => true,
    'zipUsesUtf8Names' => true,
    'zipGeneralPurposeUsesDataDescriptor' => true,
    'zipDeflateOptionFlags' => 0x0006,
    'zipDeflateOptionName' => 'deflate-super-fast',
    'zipGeneralPurposeRequiresStrictReview' => true,
    'zipGeneralPurposeFlagIssues' => ['data-descriptor-entry', 'deflate-option-flags'],
];

$expectedStylesFlags = [
    'zipGeneralPurposeFlags' => 0x0800,
    'zipGeneralPurposeFlagNames' => ['utf-8-names'],
    'zipUnsupportedGeneralPurposeFlagBits' => 0,
    'zipGeneralPurposeFlagsSupported' => true,
    'zipUsesUtf8Names' => true,
    'zipGeneralPurposeUsesDataDescriptor' => false,
    'zipDeflateOptionFlags' => 0,
    'zipDeflateOptionName' => null,
    'zipGeneralPurposeRequiresStrictReview' => false,
    'zipGeneralPurposeFlagIssues' => [],
];

return [
    'carries ODT ZIP general-purpose flag provenance through package inventories and identities' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $generalPurposeFlagSubset,
        $expectedContentFlags,
        $expectedStylesFlags
    ): void {
        $package = $buildPackage();
        $zipFlags = $package->generalPurposeFlagPreflight();
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactIdentityParts = $indexBy($compactIdentity['packageEntries'], 'path');
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');

        $t->same($richProvenance, $richResult['document']->attr('manifest')['packageProvenance']);
        $t->same($zipFlags, $richProvenance['generalPurposeFlags']);
        $t->same($zipFlags, $compactInventory['generalPurposeFlags']);
        $t->same(5, $richProvenance['generalPurposeFlagEntryCount']);
        $t->same(5, $compactInventory['generalPurposeFlagEntryCount']);
        $t->same(5, $richIdentity['generalPurposeFlagEntryCount']);
        $t->same(5, $compactIdentity['generalPurposeFlagEntryCount']);
        $t->same(5, $richProvenance['generalPurposeFlagSupportedEntryCount']);
        $t->same(0, $richProvenance['unsupportedGeneralPurposeFlagEntryCount']);
        $t->same(5, $richProvenance['utf8NameGeneralPurposeFlagEntryCount']);
        $t->same(1, $richProvenance['dataDescriptorGeneralPurposeFlagEntryCount']);
        $t->same(1, $richProvenance['deflateOptionGeneralPurposeFlagEntryCount']);
        $t->same(1, $richProvenance['strictGeneralPurposeFlagReviewEntryCount']);
        $t->same([], $richProvenance['unsupportedGeneralPurposeFlagEntries']);
        $t->same([$zipFlags['entries'][2]], $richProvenance['strictGeneralPurposeFlagReviewEntries']);
        $t->same([$zipFlags['entries'][2]], $compactInventory['strictGeneralPurposeFlagReviewEntries']);
        $t->same([$zipFlags['entries'][2]], $richIdentity['strictGeneralPurposeFlagReviewEntries']);
        $t->same([$zipFlags['entries'][2]], $compactIdentity['strictGeneralPurposeFlagReviewEntries']);

        foreach ([$richProvenance, $compactInventory] as $inventory) {
            $content = $inventory['parts']['content.xml'];
            $styles = $inventory['parts']['styles.xml'];

            $t->same($expectedContentFlags, $generalPurposeFlagSubset($content));
            $t->same($expectedStylesFlags, $generalPurposeFlagSubset($styles));
            $t->same(true, $content['zipUsesDataDescriptor']);
            $t->same(true, $content['zipLocalHeaderUsesDataDescriptor']);
            $t->same(true, $content['zipLocalHeaderHasZeroDataDescriptorPlaceholders']);
            $t->same(0x080e, $content['zipCentralGeneralPurposeFlags']);
            $t->same(0x080e, $content['zipLocalGeneralPurposeFlags']);
            $t->same(false, $styles['zipUsesDataDescriptor']);
            $t->same(false, $styles['zipLocalHeaderUsesDataDescriptor']);
        }

        foreach ([
            $richIdentityParts['content.xml'],
            $compactIdentityParts['content.xml'],
        ] as $identityContent) {
            $t->same($expectedContentFlags, $generalPurposeFlagSubset($identityContent));
            $t->same(true, $identityContent['zipUsesDataDescriptor']);
            $t->same(true, $identityContent['zipLocalHeaderUsesDataDescriptor']);
            $t->same(true, $identityContent['zipLocalHeaderHasZeroDataDescriptorPlaceholders']);
        }

        foreach ([
            $richIdentityParts['styles.xml'],
            $compactIdentityParts['styles.xml'],
        ] as $identityStyles) {
            $t->same($expectedStylesFlags, $generalPurposeFlagSubset($identityStyles));
            $t->same(false, $identityStyles['zipUsesDataDescriptor']);
            $t->same(false, $identityStyles['zipLocalHeaderUsesDataDescriptor']);
        }

        $changedPackage = $buildPackage(0x0808);
        $changedCompactIdentity = OpenDocumentPackage::fromPackage($changedPackage)->summarize()['packageIdentity'];
        $changedRichIdentity = (new OdfReader())
            ->readPackage($changedPackage)['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $changedCompactParts = $indexBy($changedCompactIdentity['packageEntries'], 'path');
        $changedRichParts = $indexBy($changedRichIdentity['packageEntries'], 'part');

        $t->same(['data-descriptor', 'utf-8-names'], $changedRichParts['content.xml']['zipGeneralPurposeFlagNames']);
        $t->same(['data-descriptor', 'utf-8-names'], $changedCompactParts['content.xml']['zipGeneralPurposeFlagNames']);
        $t->same(0, $changedRichParts['content.xml']['zipDeflateOptionFlags']);
        $t->same(null, $changedRichParts['content.xml']['zipDeflateOptionName'] ?? null);
        $t->same(null, $changedCompactParts['content.xml']['zipDeflateOptionName'] ?? null);
        $t->true($richIdentity['identitySha256'] !== $changedRichIdentity['identitySha256']);
        $t->true($compactIdentity['identitySha256'] !== $changedCompactIdentity['identitySha256']);
    },
];
