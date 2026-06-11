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
    'summarizes compact ODT package provenance roles before document handoff' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $chartBytes = '<chart/>';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="" manifest:full-path="Pictures/"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.chart" manifest:full-path="Object Chart/"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Object Chart/content.xml" manifest:size="' . strlen($chartBytes) . '"/>',
            $manifestXml
        );
        $parts = [
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifest],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Object Chart/content.xml', 'data' => $chartBytes, 'compressionMethod' => 0],
            ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMBNAIL', 'compressionMethod' => 0],
        ];
        $centralOrder = [
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Object Chart/content.xml',
            'Thumbnails/thumbnail.png',
            'Pictures/',
            'Object Chart/',
            'mimetype',
        ];

        $summary = OpenDocumentPackage::fromPackage(
            $buildZipPackageWithCentralDirectoryOrder($parts, $centralOrder)
        )->summarize();
        $provenance = $summary['packageProvenance'];
        $inventory = $provenance['parts'];

        $t->same(10, $provenance['entryCount']);
        $t->same(7, $provenance['manifestDeclaredPartCount']);
        $t->same(1, $provenance['undeclaredEntryCount']);
        $t->same(2, $provenance['packageDirectoryCount']);
        $t->same(false, $provenance['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same('mimetype', $provenance['mimetypeEntry']['firstLocalEntryName']);
        $t->same(true, $provenance['mimetypeEntry']['isValid']);
        $t->same($centralOrder, $provenance['localHeaderOrder']['centralDirectoryOrderNames']);
        $t->same(array_column($parts, 'name'), $provenance['localHeaderOrder']['localHeaderOrderNames']);
        $t->same(10, $provenance['compressionMethods']['entryCount']);
        $t->same(6, $provenance['compressionMethods']['storedEntryCount']);
        $t->same(4, $provenance['compressionMethods']['deflatedEntryCount']);

        $t->same(2, $provenance['roleCounts']['embedded-object-package']);
        $t->same(7, $provenance['roleCounts']['manifest-declared']);
        $t->same(2, $provenance['roleCounts']['media-resource']);
        $t->same(1, $provenance['roleCounts']['undeclared-package-entry']);
        $t->same(2, $provenance['roleCounts']['zip-directory']);

        $t->same(['odf-mimetype'], $inventory['mimetype']['roles']);
        $t->same(['odf-manifest'], $inventory['META-INF/manifest.xml']['roles']);
        $t->same(['odf-content', 'manifest-declared'], $inventory['content.xml']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $inventory['Pictures/hero.png']['roles']);
        $t->same(['zip-directory', 'manifest-declared', 'media-resource'], $inventory['Pictures/']['roles']);
        $t->same(['zip-directory', 'manifest-declared', 'embedded-object-package'], $inventory['Object Chart/']['roles']);
        $t->same(['manifest-declared', 'embedded-object-package'], $inventory['Object Chart/content.xml']['roles']);
        $t->same(['undeclared-package-entry'], $inventory['Thumbnails/thumbnail.png']['roles']);
        $t->same(0, $inventory['mimetype']['localHeaderOrder']);
        $t->same(9, $inventory['mimetype']['centralDirectoryIndex']);
        $t->same(false, $inventory['mimetype']['matchesCentralDirectoryOrder']);
        $t->same(true, $inventory['Object Chart/content.xml']['declaredInManifest']);
        $t->same('Object Chart/content.xml', $inventory['Object Chart/content.xml']['manifestFullPath']);
        $t->same('text/xml', $inventory['Object Chart/content.xml']['manifestMediaType']);
        $t->same(false, $inventory['Thumbnails/thumbnail.png']['declaredInManifest']);
        $t->same(true, $inventory['Thumbnails/thumbnail.png']['undeclared']);
        $t->same(sprintf('%08x', crc32('THUMBNAIL')), $inventory['Thumbnails/thumbnail.png']['crc32']);
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
