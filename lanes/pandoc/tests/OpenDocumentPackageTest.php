<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:h text:outline-level="2" text:style-name="Heading_20_2">Migration Packet</text:h>
      <text:p text:style-name="Text_20_body">Imported <text:a xlink:href="https://example.test/source">source</text:a><text:s text:c="2"/>packet<text:line-break/>continued.</text:p>
      <text:p text:style-name="Text_20_body"><draw:frame draw:name="hero"><draw:image xlink:href="Pictures/hero.png"><svg:title>Hero image</svg:title></draw:image></draw:frame></text:p>
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
    <style:style style:name="Text_20_body" style:display-name="Text body" style:family="paragraph"/>
    <style:style style:name="Heading_20_2" style:display-name="Heading 2" style:family="paragraph" style:parent-style-name="Heading"/>
    <style:style style:name="Emphasis" style:family="text"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  office:version="1.3">
  <office:meta>
    <meta:generator>Pandoc/3.8-test</meta:generator>
    <dc:title>Migration Packet</dc:title>
    <dc:description>Imported ODT for WordPress review</dc:description>
    <dc:subject>Data Liberation</dc:subject>
    <meta:keyword>import, odt, review</meta:keyword>
    <dc:language>en-US</dc:language>
    <meta:initial-creator>Archivist</meta:initial-creator>
    <dc:creator>Reviewer</dc:creator>
    <meta:creation-date>2026-06-03T22:11:51Z</meta:creation-date>
    <dc:date>2026-06-03T22:12:30Z</dc:date>
    <meta:user-defined meta:name="source-system" meta:value-type="string">LibreOffice export</meta:user-defined>
  </office:meta>
</office:document-meta>
XML;

$buildOdtPackage = static function (
    ?string $manifest = null,
    ?string $content = null,
    ?string $styles = null,
    ?string $meta = null,
    string $mimetype = OpenDocumentPackage::TEXT_MIMETYPE,
    bool $mimetypeFirst = true,
    int $mimetypeCompression = 0,
    array $extraParts = [],
) use ($manifestXml, $contentXml, $stylesXml, $metaXml): ZipPackage {
    $parts = [
        ['name' => 'mimetype', 'data' => $mimetype, 'compressionMethod' => $mimetypeCompression],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifest ?? $manifestXml],
        ['name' => 'content.xml', 'data' => $content ?? $contentXml],
        ['name' => 'styles.xml', 'data' => $styles ?? $stylesXml],
        ['name' => 'meta.xml', 'data' => $meta ?? $metaXml],
        ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA'],
    ];
    array_push($parts, ...$extraParts);

    if (!$mimetypeFirst) {
        $parts = [$parts[1], $parts[0], $parts[2], $parts[3], $parts[4], $parts[5]];
    }

    return ZipPackage::fromParts($parts, 'odt package');
};

$buildZipPackageWithCentralDirectoryOrder = static function (array $parts, array $centralOrder): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $centralRecords = [];

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $offset = strlen($body);
        $crc = $crc32($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $centralRecords[$name] = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $name;
    }

    $central = '';
    foreach ($centralOrder as $name) {
        if (!isset($centralRecords[$name])) {
            throw new RuntimeException("Missing central directory record for {$name}");
        }
        $central .= $centralRecords[$name];
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts), strlen($central), $centralOffset, 0)
    );
};

return [
    'maps ODT manifest root and package parts from a ZIP package' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage());
        $entries = $odt->manifestEntries();
        $summary = $odt->summarize();

        $t->same(5, count($entries));
        $t->same('1.3', $odt->manifestVersion());
        $t->same('/', $entries[0]['path']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $entries[0]['mediaType']);
        $t->same('1.3', $entries[0]['version']);
        $t->same('content.xml', $entries[1]['path']);
        $t->same('text/xml', $odt->mediaTypeForPath('content.xml'));
        $t->same('image/png', $odt->mediaTypeForPath('Pictures/hero.png'));
        $t->same(7, $odt->manifestEntry('Pictures/hero.png')['size']);
        $t->same(true, $summary['contentXml']);
        $t->same(true, $summary['stylesXml']);
        $t->same(true, $summary['metaXml']);
        $t->same(1, count($summary['mediaParts']));
        $t->same('Pictures/hero.png', $summary['mediaParts'][0]['path']);
        $t->same('image/png', $summary['mediaParts'][0]['mediaType']);
        $t->same(true, $summary['mediaParts'][0]['exists']);
        $t->same(7, $summary['mediaParts'][0]['byteLength']);
        $t->same(7, $summary['mediaParts'][0]['declaredSize']);
        $t->same('db1a1847', $summary['mediaParts'][0]['crc32']);
        $t->same(true, $summary['mediaParts'][0]['canExposeBytes']);
        $t->same(0, $summary['missingMediaPartCount']);
        $t->same([], $summary['missingMediaParts']);
        $t->same(1, $summary['exposableMediaPartCount']);
        $t->same(0, $summary['encryptedCount']);
        $t->same([], $summary['encryptedParts']);
        $t->same(3, $summary['contentBlocks']);
    },
    'preserves ODT compact manifest root version preferred view and encryption provenance' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifest = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.4">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="2048" manifest:preferred-view-mode="presentation-slide-show">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="checksum-base64">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="iv-base64"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:iteration-count="1024" manifest:salt="salt-base64"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
</manifest:manifest>
XML;

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest));
        $summary = $odt->summarize();
        $root = $odt->manifestEntry('/');
        $hero = $odt->manifestEntry('Pictures/hero.png');

        $t->same('1.4', $odt->manifestVersion());
        $t->same('1.4', $summary['manifestVersion']);
        $t->same(null, $root['version']);
        $t->same('edit', $root['preferredViewMode']);
        $t->same(false, $root['encrypted']);
        $t->same('presentation-slide-show', $hero['preferredViewMode']);
        $t->same(true, $hero['encrypted']);
        $t->same(2048, $hero['size']);
        $t->same(true, $hero['exists']);
        $t->same(null, $hero['byteLength']);
        $t->same(7, $hero['storedByteLength']);
        $t->same(false, $hero['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $hero['byteExposurePolicy']);
        $t->same(['odf-manifest-encrypted-package-part', 'odf-manifest-declared-size-mismatch'], $hero['diagnostics']);
        $t->same('SHA1/1K', $hero['encryption']['checksumType']);
        $t->same('checksum-base64', $hero['encryption']['checksum']);
        $t->same('Blowfish CFB', $hero['encryption']['algorithm']['name']);
        $t->same('iv-base64', $hero['encryption']['algorithm']['initialisationVector']);
        $t->same('PBKDF2', $hero['encryption']['keyDerivation']['name']);
        $t->same(1024, $hero['encryption']['keyDerivation']['iterationCount']);
        $t->same('salt-base64', $hero['encryption']['keyDerivation']['salt']);
        $t->same('SHA1', $hero['encryption']['startKeyGeneration']['name']);
        $t->same(20, $hero['encryption']['startKeyGeneration']['keySize']);
        $t->same(1, $summary['encryptedCount']);
        $t->same(['Pictures/hero.png'], $summary['encryptedParts']);
    },
    'reports compact ODT manifest media package exposure and missing parts' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            '</manifest:manifest>',
            <<<'XML'
  <manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="Pictures/missing.jpg" manifest:size="12"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/secret.png" manifest:size="9">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="secret-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML,
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [['name' => 'Pictures/secret.png', 'data' => 'SECRETDAT']],
        ));
        $summary = $odt->summarize();
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $missing = $odt->manifestEntry('Pictures/missing.jpg');
        $secret = $odt->manifestEntry('Pictures/secret.png');

        $t->same(true, $hero['exists']);
        $t->same(false, $hero['encrypted']);
        $t->same(true, $hero['canExposeBytes']);
        $t->same(7, $hero['byteLength']);
        $t->same('db1a1847', $hero['crc32']);

        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same(null, $missing['crc32']);
        $t->same(false, $missing['canExposeBytes']);

        $t->same(true, $secret['exists']);
        $t->same(true, $secret['encrypted']);
        $t->same(null, $secret['byteLength']);
        $t->same(9, $secret['storedByteLength']);
        $t->same(false, $secret['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $secret['byteExposurePolicy']);

        $t->same(3, count($summary['mediaParts']));
        $t->same(1, $summary['missingMediaPartCount']);
        $t->same([['path' => 'Pictures/missing.jpg', 'mediaType' => 'image/jpeg']], $summary['missingMediaParts']);
        $t->same(1, $summary['exposableMediaPartCount']);
        $t->same(1, $summary['encryptedCount']);
        $t->same(['Pictures/secret.png'], $summary['encryptedParts']);
    },
    'reports compact ODT manifest entry byte exposure provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $encryptedHero = <<<'XML'
<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="checksum-base64">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="iv-base64"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="" manifest:full-path="Pictures/"/>'
            . $encryptedHero
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/cover.png"/>'
            . '<manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="Pictures/missing.jpg"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Pictures/cover.png', 'data' => 'COVERPNG', 'compressionMethod' => 0],
            ]
        ));
        $summary = $odt->summarize()['manifestReview'];
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $cover = $odt->manifestEntry('Pictures/cover.png');
        $directory = $odt->manifestEntry('Pictures/');
        $missing = $odt->manifestEntry('Pictures/missing.jpg');
        $root = $odt->manifestEntry('/');

        $t->same(8, $summary['count']);
        $t->same(7, $summary['existsCount']);
        $t->same(1, $summary['missingCount']);
        $t->same(1, $summary['directoryCount']);
        $t->same(1, $summary['encryptedCount']);
        $t->same(1, $summary['declaredSizeMismatchCount']);
        $t->same(2048, $summary['declaredSize']);
        $t->same(strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + 15, $summary['storedByteLength']);
        $t->same(strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + 8, $summary['exposableByteLength']);

        $t->same('Pictures/missing.jpg', $summary['missingItems'][0]['path']);
        $t->same('Pictures/', $summary['directoryItems'][0]['path']);
        $t->same('Pictures/hero.png', $summary['encryptedItems'][0]['path']);
        $t->same('Pictures/hero.png', $summary['declaredSizeMismatches'][0]['path']);

        $t->same(true, $root['exists']);
        $t->same(false, $root['canExposeBytes']);
        $t->same('package-root-no-bytes', $root['byteExposurePolicy']);

        $t->same(true, $directory['exists']);
        $t->same(true, $directory['isDirectory']);
        $t->same(false, $directory['canExposeBytes']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);
        $t->same(['odf-manifest-directory-entry'], $directory['diagnostics']);

        $t->same(true, $hero['exists']);
        $t->same(true, $hero['encrypted']);
        $t->same(false, $hero['canExposeBytes']);
        $t->same(null, $hero['byteLength']);
        $t->same(7, $hero['storedByteLength']);
        $t->same(2048, $hero['declaredSize']);
        $t->same(true, $hero['declaredSizeMismatch']);
        $t->same('encrypted-resource-bytes-blocked', $hero['byteExposurePolicy']);
        $t->same(['odf-manifest-encrypted-package-part', 'odf-manifest-declared-size-mismatch'], $hero['diagnostics']);
        $t->same('SHA1/1K', $hero['encryption']['checksumType']);
        $t->same('checksum-base64', $hero['encryption']['checksum']);
        $t->same('Blowfish CFB', $hero['encryption']['algorithm']['name']);
        $t->same('iv-base64', $hero['encryption']['algorithm']['initialisationVector']);

        $t->same(true, $cover['canExposeBytes']);
        $t->same(8, $cover['byteLength']);
        $t->same('package-bytes-exposable', $cover['byteExposurePolicy']);
        $t->same([], $cover['diagnostics']);

        $t->same(false, $missing['exists']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same('missing-package-part', $missing['byteExposurePolicy']);
        $t->same(['odf-manifest-missing-package-part'], $missing['diagnostics']);
    },
    'reports compact ODT ZIP compression provenance without exposing unsupported bytes' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $sourceBytes = 'SIDECAR-RAW';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Pictures/source.raw" manifest:size="' . strlen($sourceBytes) . '"/>',
            $manifestXml
        );
        $parts = [
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifest],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/source.raw', 'data' => $sourceBytes, 'compressionMethod' => 12],
        ];

        $odt = OpenDocumentPackage::fromPackage($buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name')));
        $summary = $odt->summarize();
        $review = $summary['manifestReview'];
        $raw = $odt->manifestEntry('Pictures/source.raw');
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $media) {
            $mediaByPath[$media['path']] = $media;
        }

        $t->same(true, $raw['exists']);
        $t->same(null, $raw['byteLength']);
        $t->same(strlen($sourceBytes), $raw['storedByteLength']);
        $t->same(strlen($sourceBytes), $raw['compressedByteLength']);
        $t->same(12, $raw['compressionMethod']);
        $t->same('unsupported', $raw['compressionMethodName']);
        $t->same(null, $raw['crc32']);
        $t->same(sprintf('%08x', crc32($sourceBytes)), $raw['storedCrc32']);
        $t->same(false, $raw['canExposeBytes']);
        $t->same('unsupported-compression-bytes-blocked', $raw['byteExposurePolicy']);
        $t->same(['odf-manifest-unsupported-compression-method'], $raw['diagnostics']);

        $t->same(null, $mediaByPath['Pictures/source.raw']['byteLength']);
        $t->same(strlen($sourceBytes), $mediaByPath['Pictures/source.raw']['storedByteLength']);
        $t->same(12, $mediaByPath['Pictures/source.raw']['compressionMethod']);
        $t->same('unsupported', $mediaByPath['Pictures/source.raw']['compressionMethodName']);
        $t->same(null, $mediaByPath['Pictures/source.raw']['crc32']);
        $t->same(false, $mediaByPath['Pictures/source.raw']['canExposeBytes']);
        $t->same('unsupported-compression-bytes-blocked', $mediaByPath['Pictures/source.raw']['byteExposurePolicy']);

        $t->same(6, $review['count']);
        $t->same(1, $review['storedCompressionMethodCount']);
        $t->same(3, $review['deflatedCompressionMethodCount']);
        $t->same(1, $review['unsupportedCompressionMethodCount']);
        $t->same(strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + strlen('PNGDATA') + strlen($sourceBytes), $review['storedByteLength']);
        $t->same(strlen(gzdeflate($contentXml)) + strlen(gzdeflate($stylesXml)) + strlen(gzdeflate($metaXml)) + strlen('PNGDATA') + strlen($sourceBytes), $review['compressedByteLength']);
        $t->same(strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + strlen('PNGDATA'), $review['exposableByteLength']);
        $t->same('Pictures/source.raw', $review['items'][5]['path']);
        $t->same(12, $review['items'][5]['compressionMethod']);
        $t->same('unsupported', $review['items'][5]['compressionMethodName']);
    },
    'reports compact ODT undeclared ZIP package entries without exposing bytes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $orphanBytes = 'ORPHANPNG';
        $settingsBytes = '<config:config-item-set/>';
        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(extraParts: [
            ['name' => 'Pictures/orphan.png', 'data' => $orphanBytes, 'compressionMethod' => 0],
            ['name' => 'Configurations2/status.xml', 'data' => $settingsBytes, 'compressionMethod' => 0],
        ]));
        $summary = $odt->summarize();
        $review = $summary['manifestReview'];
        $undeclaredByPath = [];
        foreach ($summary['undeclaredPackageEntries'] as $entry) {
            $undeclaredByPath[$entry['path']] = $entry;
        }

        $t->same(2, $summary['undeclaredPackageEntryCount']);
        $t->same(2, $review['undeclaredPackageEntryCount']);
        $t->same(['Pictures/orphan.png', 'Configurations2/status.xml'], array_column($summary['undeclaredPackageEntries'], 'path'));
        $t->same($summary['undeclaredPackageEntries'], $review['undeclaredPackageEntries']);
        $t->same(5, $review['count']);
        $t->same(1, count($summary['mediaParts']));
        $t->same('Pictures/hero.png', $summary['mediaParts'][0]['path']);

        $t->same(false, isset($undeclaredByPath['mimetype']));
        $t->same(false, isset($undeclaredByPath['META-INF/manifest.xml']));

        $orphan = $undeclaredByPath['Pictures/orphan.png'];
        $t->same(false, $orphan['isDirectory']);
        $t->same(strlen($orphanBytes), $orphan['storedByteLength']);
        $t->same(strlen($orphanBytes), $orphan['compressedByteLength']);
        $t->same(0, $orphan['compressionMethod']);
        $t->same('stored', $orphan['compressionMethodName']);
        $t->same(sprintf('%08x', crc32($orphanBytes)), $orphan['crc32']);
        $t->same(false, $orphan['canExposeBytes']);
        $t->same('undeclared-package-entry-no-bytes', $orphan['byteExposurePolicy']);
        $t->same(['odf-manifest-undeclared-package-entry'], $orphan['diagnostics']);

        $settings = $undeclaredByPath['Configurations2/status.xml'];
        $t->same(strlen($settingsBytes), $settings['storedByteLength']);
        $t->same('undeclared-package-entry-no-bytes', $settings['byteExposurePolicy']);
    },
    'reports compact ODT Configurations2 sidecars as metadata only' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $toolbarBytes = '<config:config-item-set config:name="toolbar"/>';
        $acceleratorBytes = '<config:config-item-set config:name="accelerator"/>';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/toolbar/statusbar.xml" manifest:size="' . strlen($toolbarBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/missing.xml"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Configurations2/toolbar/statusbar.xml', 'data' => $toolbarBytes, 'compressionMethod' => 0],
                ['name' => 'Configurations2/accelerator/current.xml', 'data' => $acceleratorBytes, 'compressionMethod' => 0],
            ],
        ));
        $summary = $odt->summarize();
        $sidecarsByPath = [];
        foreach ($summary['configurationSidecars'] as $sidecar) {
            $sidecarsByPath[$sidecar['path']] = $sidecar;
        }
        $inventoryParts = $summary['packageInventory']['parts'];

        $t->same(3, $summary['configurationSidecarCount']);
        $t->same(1, $summary['missingConfigurationSidecarCount']);
        $t->same(1, $summary['undeclaredConfigurationSidecarCount']);
        $t->same(0, $summary['encryptedConfigurationSidecarCount']);
        $t->same(1, count($summary['mediaParts']), 'Configurations2 sidecars must stay out of media byte handoff');
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('Configurations2/accelerator/current.xml', $summary['undeclaredPackageEntries'][0]['path']);
        $t->same(7, $summary['manifestReview']['count']);

        $toolbar = $sidecarsByPath['Configurations2/toolbar/statusbar.xml'];
        $t->same('Configurations2/toolbar/statusbar.xml', $toolbar['packagePath']);
        $t->same('text/xml', $toolbar['mediaType']);
        $t->same(true, $toolbar['declaredInManifest']);
        $t->same(false, $toolbar['undeclared']);
        $t->same(true, $toolbar['exists']);
        $t->same(strlen($toolbarBytes), $toolbar['storedByteLength']);
        $t->same(sprintf('%08x', crc32($toolbarBytes)), $toolbar['crc32']);
        $t->same(false, $toolbar['canExposeBytes']);
        $t->same('odf-configuration-sidecar-metadata-only', $toolbar['byteExposurePolicy']);
        $t->same('package-bytes-exposable', $toolbar['sourceByteExposurePolicy']);
        $t->same([], $toolbar['diagnostics']);

        $missing = $sidecarsByPath['Configurations2/missing.xml'];
        $t->same(true, $missing['declaredInManifest']);
        $t->same(false, $missing['exists']);
        $t->same('missing-package-part', $missing['byteExposurePolicy']);
        $t->same(['odf-manifest-missing-package-part'], $missing['diagnostics']);

        $accelerator = $sidecarsByPath['Configurations2/accelerator/current.xml'];
        $t->same(false, $accelerator['declaredInManifest']);
        $t->same(true, $accelerator['undeclared']);
        $t->same(null, $accelerator['mediaType']);
        $t->same(strlen($acceleratorBytes), $accelerator['storedByteLength']);
        $t->same(sprintf('%08x', crc32($acceleratorBytes)), $accelerator['storedCrc32']);
        $t->same('undeclared-package-entry-no-bytes', $accelerator['byteExposurePolicy']);
        $t->same(['odf-manifest-undeclared-package-entry'], $accelerator['diagnostics']);

        $t->same(['odf-configuration-sidecar', 'manifest-declared'], $inventoryParts['Configurations2/toolbar/statusbar.xml']['roles']);
        $t->same(['odf-configuration-sidecar', 'undeclared-package-entry'], $inventoryParts['Configurations2/accelerator/current.xml']['roles']);
    },
    'resolves compact ODT URI encoded manifest paths to ZIP package parts' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $sourceBytes = 'SRCIMAGE';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/source%20hero.png" manifest:size="' . strlen($sourceBytes) . '"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [['name' => 'Pictures/source hero.png', 'data' => $sourceBytes, 'compressionMethod' => 0]],
        ));
        $encoded = $odt->manifestEntry('Pictures/source%20hero.png');
        $decoded = $odt->manifestEntry('Pictures/source hero.png');
        $summary = $odt->summarize();
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $media) {
            $mediaByPath[$media['path']] = $media;
        }

        $t->same($encoded, $decoded);
        $t->same('Pictures/source%20hero.png', $encoded['path']);
        $t->same('Pictures/source hero.png', $encoded['packagePath']);
        $t->same(true, $encoded['exists']);
        $t->same(strlen($sourceBytes), $encoded['byteLength']);
        $t->same(strlen($sourceBytes), $encoded['storedByteLength']);
        $t->same(sprintf('%08x', crc32($sourceBytes)), $encoded['crc32']);
        $t->same(true, $encoded['canExposeBytes']);
        $t->same('package-bytes-exposable', $encoded['byteExposurePolicy']);

        $t->same(2, count($summary['mediaParts']));
        $t->same('Pictures/source hero.png', $mediaByPath['Pictures/source%20hero.png']['packagePath']);
        $t->same(strlen($sourceBytes), $mediaByPath['Pictures/source%20hero.png']['byteLength']);
        $t->same(0, $summary['undeclaredPackageEntryCount']);
        $t->same(0, $summary['manifestReview']['undeclaredPackageEntryCount']);
        $t->same('Pictures/source hero.png', $summary['manifestReview']['items'][5]['packagePath']);

        $duplicateDecodedManifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/source hero.png"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/source%20hero.png"/>',
            $manifestXml
        );
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $duplicateDecodedManifest)));

        $encodedDotSegmentManifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/%2e%2e/evil.png"/>',
            $manifestXml
        );
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $encodedDotSegmentManifest)));
    },
    'resolves compact ODT manifest path suffixes while preserving query and fragment provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $sourceBytes = 'SOURCEPNG';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png?cache=1#review" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/source%20hero.png?download=true#asset" manifest:size="' . strlen($sourceBytes) . '"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [['name' => 'Pictures/source hero.png', 'data' => $sourceBytes, 'compressionMethod' => 0]],
        ));
        $hero = $odt->manifestEntry('Pictures/hero.png?cache=1#review');
        $source = $odt->manifestEntry('Pictures/source hero.png');
        $summary = $odt->summarize();
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $media) {
            $mediaByPath[$media['path']] = $media;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory']['parts'];

        $t->same($hero, $odt->manifestEntry('Pictures/hero.png'));
        $t->same('Pictures/hero.png?cache=1#review', $hero['path']);
        $t->same('Pictures/hero.png', $hero['packagePath']);
        $t->same('Pictures/hero.png', $hero['pathReference']);
        $t->same('?cache=1#review', $hero['pathSuffix']);
        $t->same('cache=1', $hero['pathQuery']);
        $t->same('review', $hero['pathFragment']);
        $t->same(true, $hero['exists']);
        $t->same(7, $hero['byteLength']);

        $t->same($source, $odt->manifestEntry('Pictures/source%20hero.png'));
        $t->same('Pictures/source%20hero.png?download=true#asset', $source['path']);
        $t->same('Pictures/source hero.png', $source['packagePath']);
        $t->same('Pictures/source%20hero.png', $source['pathReference']);
        $t->same('?download=true#asset', $source['pathSuffix']);
        $t->same('download=true', $source['pathQuery']);
        $t->same('asset', $source['pathFragment']);
        $t->same(strlen($sourceBytes), $source['byteLength']);

        $t->same(2, count($summary['mediaParts']));
        $t->same('Pictures/hero.png', $mediaByPath['Pictures/hero.png?cache=1#review']['packagePath']);
        $t->same('?cache=1#review', $mediaByPath['Pictures/hero.png?cache=1#review']['pathSuffix']);
        $t->same('review', $mediaByPath['Pictures/hero.png?cache=1#review']['pathFragment']);
        $t->same('Pictures/source hero.png', $mediaByPath['Pictures/source%20hero.png?download=true#asset']['packagePath']);
        $t->same('download=true', $mediaByPath['Pictures/source%20hero.png?download=true#asset']['pathQuery']);

        $t->same('Pictures/hero.png', $reviewByPath['Pictures/hero.png?cache=1#review']['packagePath']);
        $t->same('Pictures/hero.png', $reviewByPath['Pictures/hero.png?cache=1#review']['pathReference']);
        $t->same('?cache=1#review', $reviewByPath['Pictures/hero.png?cache=1#review']['pathSuffix']);
        $t->same('cache=1', $reviewByPath['Pictures/hero.png?cache=1#review']['pathQuery']);
        $t->same('review', $reviewByPath['Pictures/hero.png?cache=1#review']['pathFragment']);

        $t->same('Pictures/hero.png?cache=1#review', $inventory['Pictures/hero.png']['manifestPath']);
        $t->same('Pictures/hero.png', $inventory['Pictures/hero.png']['manifestPathReference']);
        $t->same('?cache=1#review', $inventory['Pictures/hero.png']['manifestPathSuffix']);
        $t->same('cache=1', $inventory['Pictures/hero.png']['manifestPathQuery']);
        $t->same('review', $inventory['Pictures/hero.png']['manifestPathFragment']);
    },
    'reports compact ODT ZIP inventory and undeclared package entries' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="" manifest:full-path="Pictures/"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMBNAIL', 'compressionMethod' => 0],
            ]
        ));
        $summary = $odt->summarize();
        $inventory = $summary['packageInventory'];
        $parts = $inventory['parts'];

        $t->same(8, $inventory['entryCount']);
        $t->same(5, $inventory['manifestDeclaredPartCount']);
        $t->same(1, $inventory['undeclaredEntryCount']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('Thumbnails/thumbnail.png', $summary['undeclaredPackageEntries'][0]['path']);
        $t->same(1, $inventory['packageDirectoryCount']);
        $t->same(true, $inventory['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same([
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/',
            'Thumbnails/thumbnail.png',
        ], $inventory['localHeaderOrder']['localHeaderOrderNames']);

        $t->same(3, $inventory['compressionMethods']['storedEntryCount']);
        $t->same(5, $inventory['compressionMethods']['deflatedEntryCount']);
        $t->same(['odf-mimetype'], $parts['mimetype']['roles']);
        $t->same(['odf-content', 'manifest-declared'], $parts['content.xml']['roles']);
        $t->same(['zip-directory', 'manifest-declared'], $parts['Pictures/']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $parts['Pictures/hero.png']['roles']);
        $t->same(['undeclared-package-entry'], $parts['Thumbnails/thumbnail.png']['roles']);
        $t->same(false, $parts['Thumbnails/thumbnail.png']['declaredInManifest']);
        $t->same(true, $parts['Thumbnails/thumbnail.png']['undeclared']);
        $t->same(0, $parts['Thumbnails/thumbnail.png']['compressionMethod']);
        $t->same('stored', $parts['Thumbnails/thumbnail.png']['compressionMethodName']);
        $t->same(sprintf('%08x', crc32('THUMBNAIL')), $parts['Thumbnails/thumbnail.png']['crc32']);
    },
    'rejects malformed ODT manifest size metadata before package exposure' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $leadingZeroSize = str_replace('manifest:size="7"', 'manifest:size="0007"', $manifestXml);
        $leadingZero = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $leadingZeroSize));
        $t->same(7, $leadingZero->manifestEntry('Pictures/hero.png')['size']);

        foreach (['7bytes', '-7', '+7', '7.0', '922337203685477580799'] as $size) {
            $manifest = str_replace('manifest:size="7"', 'manifest:size="' . $size . '"', $manifestXml);
            $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest)));
        }
    },
    'maps ODT content headings paragraphs links spaces breaks and images into the shared AST' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $document = OpenDocumentPackage::fromPackage($buildOdtPackage())->readContentDocument();

        $t->same('document', $document->type);
        $t->same('odt', $document->attr('format'));
        $t->same(3, count($document->children));
        $t->same('heading', $document->children[0]->type);
        $t->same(2, $document->children[0]->attr('level'));
        $t->same('Migration Packet', $document->children[0]->attr('text'));
        $t->same('Heading_20_2', $document->children[0]->attr('styleName'));

        $paragraph = $document->children[1];
        $t->same('paragraph', $paragraph->type);
        $t->same('Imported source  packet' . "\n" . 'continued.', $paragraph->attr('text'));
        $t->same(['text', 'link', 'text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('https://example.test/source', $paragraph->children[1]->attr('url'));
        $t->same('source', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('  packet', $paragraph->children[2]->attr('text'));

        $image = $document->children[2]->children[0];
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png', $image->attr('url'));
        $t->same('Hero image', $image->attr('alt'));
    },
    'maps ODT meta XML fields and styles XML style names' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage());
        $metadata = $odt->metadata();
        $styles = $odt->stylesByName();

        $t->same('Pandoc/3.8-test', $metadata['generator']);
        $t->same('Migration Packet', $metadata['title']);
        $t->same('Imported ODT for WordPress review', $metadata['description']);
        $t->same('Data Liberation', $metadata['subject']);
        $t->same(['import', 'odt', 'review'], $metadata['keywords']);
        $t->same('en-US', $metadata['language']);
        $t->same('Archivist', $metadata['initialCreator']);
        $t->same('Reviewer', $metadata['creator']);
        $t->same('2026-06-03T22:11:51Z', $metadata['creationDate']);
        $t->same('2026-06-03T22:12:30Z', $metadata['date']);
        $t->same('LibreOffice export', $metadata['userDefined']['source-system']['value']);
        $t->same('string', $metadata['userDefined']['source-system']['valueType']);
        $t->same(['Text_20_body', 'Heading_20_2', 'Emphasis'], array_keys($styles));
        $t->same('paragraph', $styles['Heading_20_2']['family']);
        $t->same('Heading', $styles['Heading_20_2']['parent']);
        $t->same('Heading 2', $styles['Heading_20_2']['displayName']);
    },
    'maps ODT editing metadata and document statistics from meta xml' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $meta = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  office:version="1.3">
  <office:meta>
    <dc:title>Statistic Packet</dc:title>
    <meta:editing-duration>PT1H2M3S</meta:editing-duration>
    <meta:editing-cycles>7</meta:editing-cycles>
    <meta:document-statistic
      meta:page-count="12"
      meta:word-count="128"
      meta:paragraph-count="9"
      meta:non-whitespace-character-count="600"
      meta:syllable-count="210"
      meta:image-count="1"/>
  </office:meta>
</office:document-meta>
XML;

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(meta: $meta));
        $metadata = $odt->metadata();
        $summary = $odt->summarize();

        $t->same('Statistic Packet', $metadata['title']);
        $t->same('PT1H2M3S', $metadata['editingDuration']);
        $t->same('7', $metadata['editingCycles']);
        $t->same(12, $metadata['statistics']['pageCount']);
        $t->same(128, $metadata['statistics']['wordCount']);
        $t->same(9, $metadata['statistics']['paragraphCount']);
        $t->same(600, $metadata['statistics']['nonWhitespaceCharacterCount']);
        $t->same(210, $metadata['statistics']['syllableCount']);
        $t->same(1, $metadata['statistics']['imageCount']);
        $t->same($metadata['statistics'], $summary['metadata']['statistics']);
    },
    'maps compact ODT settings XML config items into package summary metadata' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings>
    <config:config-item-set config:name="ooo:view-settings">
      <config:config-item config:name="ViewAreaTop" config:type="int">1440</config:config-item>
      <config:config-item config:name="ShowRedlineChanges" config:type="boolean">true</config:config-item>
      <config:config-item-map-indexed config:name="Views">
        <config:config-item-map-entry>
          <config:config-item config:name="ViewId" config:type="string">view-1</config:config-item>
          <config:config-item config:name="ViewLeft" config:type="int">120</config:config-item>
        </config:config-item-map-entry>
        <config:config-item-map-entry>
          <config:config-item config:name="ViewId" config:type="string">view-2</config:config-item>
          <config:config-item config:name="ViewLeft" config:type="int">240</config:config-item>
        </config:config-item-map-entry>
      </config:config-item-map-indexed>
    </config:config-item-set>
    <config:config-item-set config:name="ooo:configuration-settings">
      <config:config-item config:name="LoadReadonly" config:type="boolean">false</config:config-item>
      <config:config-item-map-named config:name="ForbiddenCharacters">
        <config:config-item-map-entry config:name="en-US">
          <config:config-item config:name="Language" config:type="string">en</config:config-item>
        </config:config-item-map-entry>
      </config:config-item-map-named>
    </config:config-item-set>
  </office:settings>
</office:document-settings>
XML;
        $manifestWithSettings = str_replace(
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>',
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>'
            . "\n  "
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="settings.xml"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifestWithSettings,
            extraParts: [['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0]],
        ));
        $summary = $odt->summarize();
        $settings = $odt->settings();
        $view = $settings['setsByName']['ooo:view-settings'];
        $configuration = $settings['setsByName']['ooo:configuration-settings'];
        $views = $view['mapsByName']['Views'];
        $forbiddenCharacters = $configuration['mapsByName']['ForbiddenCharacters'];

        $t->same(true, $summary['settingsXml']);
        $t->same($settings, $summary['settings']);
        $t->same($settings, $odt->readContentDocument()->attr('settings'));
        $t->same('settings.xml', $odt->manifestEntry('settings.xml')['path']);
        $t->same(true, $odt->manifestEntry('settings.xml')['exists']);
        $t->same('text/xml', $odt->mediaTypeForPath('settings.xml'));
        $t->same(1, count($summary['mediaParts']), 'settings.xml must stay out of media byte handoff');

        $t->same(2, $settings['count']);
        $t->same(8, $settings['itemCount']);
        $t->same(3, $settings['mapEntryCount']);
        $t->same(['ooo:view-settings', 'ooo:configuration-settings'], array_column($settings['sets'], 'name'));
        $t->same(6, $view['itemCount']);
        $t->same(2, $view['mapEntryCount']);
        $t->same(1440, $view['itemsByName']['ViewAreaTop']['typedValue']);
        $t->same('1440', $view['itemsByName']['ViewAreaTop']['value']);
        $t->same(true, $view['itemsByName']['ShowRedlineChanges']['typedValue']);
        $t->same('indexed', $views['type']);
        $t->same(2, $views['entryCount']);
        $t->same('view-1', $views['entries'][0]['itemsByName']['ViewId']['typedValue']);
        $t->same(240, $views['entries'][1]['itemsByName']['ViewLeft']['typedValue']);
        $t->same(2, $configuration['itemCount']);
        $t->same(false, $configuration['itemsByName']['LoadReadonly']['typedValue']);
        $t->same('named', $forbiddenCharacters['type']);
        $t->same(1, $forbiddenCharacters['entryCount']);
        $t->same('en-US', $forbiddenCharacters['entries'][0]['name']);
        $t->same('en', $forbiddenCharacters['entriesByName']['en-US']['itemsByName']['Language']['typedValue']);

        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifestWithSettings)));
        $badSettingsXml = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>';
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifestWithSettings,
            extraParts: [['name' => 'settings.xml', 'data' => $badSettingsXml]],
        )));
    },
    'renders mapped ODT content through the WordPress block writer' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $document = OpenDocumentPackage::fromPackage($buildOdtPackage())->readContentDocument();
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('<h2>Migration Packet</h2>', $blocks);
        $t->contains('<p>Imported <a href="https://example.test/source">source</a>  packet<br/>continued.</p>', $blocks);
        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains('<img src="Pictures/hero.png" alt="Hero image"/>', $blocks);
    },
    'rejects malformed or unsafe ODT package inputs' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));
        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(mimetype: 'application/vnd.oasis.opendocument.spreadsheet')));
        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(mimetypeFirst: false)));
        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(mimetypeCompression: 8)));
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: '<manifest xmlns="urn:bad"/>')));
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: str_replace('manifest:full-path="content.xml"', 'manifest:full-path="../content.xml"', $manifestXml))));
        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: str_replace('application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.presentation', $manifestXml))));
        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => OpenDocumentPackage::fromPackage($buildOdtPackage(content: '<office:document-content xmlns:office="' . OpenDocumentPackage::OFFICE_NAMESPACE . '"/>'))->readContentDocument());
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(styles: '<office:document-styles xmlns:office="urn:bad"/>')));
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(meta: '<office:document-meta xmlns:office="urn:bad"/>')));
    },
];
