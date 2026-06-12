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
    'preserves compact ODT manifest media type parameter provenance for package review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/jpeg; charset=UTF-8; profile=&quot;review cover&quot;" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest));
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $summary = $odt->summarize();
        $media = $summary['mediaParts'][0];
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventoryHero = $summary['packageInventory']['parts']['Pictures/hero.png'];

        $expectedParameters = [
            ['name' => 'charset', 'value' => 'UTF-8', 'raw' => 'charset=UTF-8'],
            ['name' => 'profile', 'value' => 'review cover', 'raw' => 'profile="review cover"'],
        ];
        $expectedMap = ['charset' => 'UTF-8', 'profile' => 'review cover'];

        $t->same('image/jpeg; charset=UTF-8; profile="review cover"', $hero['mediaType']);
        $t->same('image/jpeg', $hero['mediaTypeBase']);
        $t->same(true, $hero['mediaTypeHasParameters']);
        $t->same(2, $hero['mediaTypeParameterCount']);
        $t->same($expectedParameters, $hero['mediaTypeParameters']);
        $t->same($expectedMap, $hero['mediaTypeParameterMap']);

        $t->same('image/jpeg', $media['mediaTypeBase']);
        $t->same(true, $media['mediaTypeHasParameters']);
        $t->same($expectedMap, $media['mediaTypeParameterMap']);
        $t->same('image/jpeg', $reviewByPath['Pictures/hero.png']['mediaTypeBase']);
        $t->same($expectedParameters, $reviewByPath['Pictures/hero.png']['mediaTypeParameters']);
        $t->same('image/jpeg', $inventoryHero['manifestMediaTypeBase']);
        $t->same(2, $inventoryHero['manifestMediaTypeParameterCount']);
        $t->same($expectedMap, $inventoryHero['manifestMediaTypeParameterMap']);
        $t->same(['manifest-declared', 'media-resource'], $inventoryHero['roles']);
    },
    'preserves compact ODT manifest version and preferred view provenance in review packets' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">',
                '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            ],
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.4">',
                '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.4" manifest:preferred-view-mode="edit"/>',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml" manifest:version="1.2" manifest:preferred-view-mode="page-preview"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7" manifest:version="1.1" manifest:preferred-view-mode="thumbnail"/>',
            ],
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest));
        $summary = $odt->summarize();
        $root = $odt->manifestEntry('/');
        $content = $odt->manifestEntry('content.xml');
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $media) {
            $mediaByPath[$media['path']] = $media;
        }
        $inventory = $summary['packageInventory']['parts'];

        $t->same('1.4', $odt->manifestVersion());
        $t->same('1.4', $summary['manifestVersion']);
        $t->same('1.4', $root['version']);
        $t->same('edit', $root['preferredViewMode']);
        $t->same('1.2', $content['version']);
        $t->same('page-preview', $content['preferredViewMode']);
        $t->same('1.1', $hero['version']);
        $t->same('thumbnail', $hero['preferredViewMode']);

        $t->same('1.4', $reviewByPath['/']['version']);
        $t->same('edit', $reviewByPath['/']['preferredViewMode']);
        $t->same('1.2', $reviewByPath['content.xml']['version']);
        $t->same('page-preview', $reviewByPath['content.xml']['preferredViewMode']);
        $t->same('1.1', $reviewByPath['Pictures/hero.png']['version']);
        $t->same('thumbnail', $reviewByPath['Pictures/hero.png']['preferredViewMode']);

        $t->same('1.1', $mediaByPath['Pictures/hero.png']['version']);
        $t->same('thumbnail', $mediaByPath['Pictures/hero.png']['preferredViewMode']);
        $t->same('1.2', $inventory['content.xml']['manifestVersion']);
        $t->same('page-preview', $inventory['content.xml']['manifestPreferredViewMode']);
        $t->same('1.1', $inventory['Pictures/hero.png']['manifestVersion']);
        $t->same('thumbnail', $inventory['Pictures/hero.png']['manifestPreferredViewMode']);
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
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="16" manifest:iteration-count="1024" manifest:salt="salt-base64"/>
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
        $t->same(16, $hero['encryption']['keyDerivation']['keySize']);
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
    'reports compact ODT audio and video manifest media resources' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $audioBytes = 'AUDIODATA';
        $videoBytes = 'VIDEODATA!';
        $manifest = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:media-type="audio/ogg; codecs=&quot;opus&quot;" manifest:full-path="Media/narration.ogg" manifest:size="' . strlen($audioBytes) . '"/>'
            . "\n  "
            . '<manifest:file-entry manifest:media-type="video/mp4" manifest:full-path="Media/clip.mp4" manifest:size="' . strlen($videoBytes) . '"/>'
            . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Media/narration.ogg', 'data' => $audioBytes, 'compressionMethod' => 0],
                ['name' => 'Media/clip.mp4', 'data' => $videoBytes, 'compressionMethod' => 0],
            ],
        ));
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

        $t->same(3, count($summary['mediaParts']));
        $t->same(['Pictures/hero.png', 'Media/narration.ogg', 'Media/clip.mp4'], array_column($summary['mediaParts'], 'path'));
        $t->same(0, $summary['missingMediaPartCount']);
        $t->same(3, $summary['exposableMediaPartCount']);

        $audio = $mediaByPath['Media/narration.ogg'];
        $t->same('audio/ogg; codecs="opus"', $audio['mediaType']);
        $t->same('audio/ogg', $audio['mediaTypeBase']);
        $t->same(true, $audio['mediaTypeHasParameters']);
        $t->same(['codecs' => 'opus'], $audio['mediaTypeParameterMap']);
        $t->same(true, $audio['exists']);
        $t->same(strlen($audioBytes), $audio['byteLength']);
        $t->same(strlen($audioBytes), $audio['declaredSize']);
        $t->same(0, $audio['compressionMethod']);
        $t->same('stored', $audio['compressionMethodName']);
        $t->same(sprintf('%08x', crc32($audioBytes)), $audio['crc32']);
        $t->same(true, $audio['canExposeBytes']);
        $t->same('package-bytes-exposable', $audio['byteExposurePolicy']);
        $t->same([], $audio['diagnostics']);

        $video = $mediaByPath['Media/clip.mp4'];
        $t->same('video/mp4', $video['mediaType']);
        $t->same('video/mp4', $video['mediaTypeBase']);
        $t->same(strlen($videoBytes), $video['byteLength']);
        $t->same(sprintf('%08x', crc32($videoBytes)), $video['crc32']);
        $t->same(true, $video['canExposeBytes']);

        $t->same('audio/ogg', $reviewByPath['Media/narration.ogg']['mediaTypeBase']);
        $t->same(['codecs' => 'opus'], $reviewByPath['Media/narration.ogg']['mediaTypeParameterMap']);
        $t->same(true, $reviewByPath['Media/narration.ogg']['canExposeBytes']);
        $t->same('video/mp4', $reviewByPath['Media/clip.mp4']['mediaTypeBase']);
        $t->same(true, $reviewByPath['Media/clip.mp4']['canExposeBytes']);

        $t->same(['manifest-declared', 'media-resource'], $inventory['Media/narration.ogg']['roles']);
        $t->same('audio/ogg', $inventory['Media/narration.ogg']['manifestMediaTypeBase']);
        $t->same(['codecs' => 'opus'], $inventory['Media/narration.ogg']['manifestMediaTypeParameterMap']);
        $t->same(true, $inventory['Media/narration.ogg']['canExposeBytes']);
        $t->same(['manifest-declared', 'media-resource'], $inventory['Media/clip.mp4']['roles']);
        $t->same('video/mp4', $inventory['Media/clip.mp4']['manifestMediaTypeBase']);
        $t->same(true, $inventory['Media/clip.mp4']['canExposeBytes']);
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
    'summarizes compact ODT manifest suffix references without marking stripped parts undeclared' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml): void {
        $manifest = str_replace(
            [
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            ],
            [
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml?role=body#content"/>',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml#styledefs"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
                . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/missing.png?missing=true"/>',
            ],
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest))->summarize();
        $review = $summary['manifestReview'];
        $inventory = $summary['packageInventory'];
        $suffixItems = [];
        foreach ($review['manifestPartReferenceSuffixItems'] as $item) {
            $suffixItems[$item['fullPath']] = $item;
        }

        $t->same(6, $review['manifestFileEntryCount']);
        $t->same(3, $review['manifestPartReferenceSuffixCount']);
        $t->same(2, $review['manifestPartReferenceQueryCount']);
        $t->same(2, $review['manifestPartReferenceFragmentCount']);
        $t->same([
            '/',
            'content.xml?role=body#content',
            'styles.xml#styledefs',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/missing.png?missing=true',
        ], array_column($review['manifestFileEntryOrder'], 'fullPath'));

        $content = $suffixItems['content.xml?role=body#content'];
        $t->same(1, $content['manifestIndex']);
        $t->same('content.xml', $content['part']);
        $t->same('content.xml', $content['partReference']);
        $t->same('?role=body#content', $content['partSuffix']);
        $t->same('role=body', $content['partQuery']);
        $t->same('content', $content['partFragment']);
        $t->same(true, $content['exists']);
        $t->same(true, $content['canExposeBytes']);
        $t->same(strlen($contentXml), $review['items'][1]['byteLength']);

        $styles = $suffixItems['styles.xml#styledefs'];
        $t->same(2, $styles['manifestIndex']);
        $t->same('styles.xml', $styles['part']);
        $t->same('#styledefs', $styles['partSuffix']);
        $t->same(null, $styles['partQuery']);
        $t->same('styledefs', $styles['partFragment']);
        $t->same(true, $styles['exists']);

        $missing = $suffixItems['Pictures/missing.png?missing=true'];
        $t->same(5, $missing['manifestIndex']);
        $t->same('Pictures/missing.png', $missing['part']);
        $t->same('?missing=true', $missing['partSuffix']);
        $t->same('missing=true', $missing['partQuery']);
        $t->same(null, $missing['partFragment']);
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same('Pictures/missing.png?missing=true', $review['missingItems'][0]['fullPath']);

        $t->same(0, $inventory['undeclaredEntryCount']);
        $t->same(0, $summary['undeclaredPackageEntryCount']);
        $t->same(['odf-content', 'manifest-declared'], $inventory['parts']['content.xml']['roles']);
        $t->same(1, $inventory['parts']['content.xml']['manifestIndex']);
        $t->same('content.xml?role=body#content', $inventory['parts']['content.xml']['manifestPath']);
        $t->same('?role=body#content', $inventory['parts']['content.xml']['manifestPathSuffix']);
        $t->same('content', $inventory['parts']['content.xml']['manifestPathFragment']);
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
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $parts['Thumbnails/thumbnail.png']['roles']);
        $t->same(false, $parts['Thumbnails/thumbnail.png']['declaredInManifest']);
        $t->same(true, $parts['Thumbnails/thumbnail.png']['undeclared']);
        $t->same(0, $parts['Thumbnails/thumbnail.png']['compressionMethod']);
        $t->same('stored', $parts['Thumbnails/thumbnail.png']['compressionMethodName']);
        $t->same(sprintf('%08x', crc32('THUMBNAIL')), $parts['Thumbnails/thumbnail.png']['crc32']);
    },
    'summarizes compact ODT package inventory role buckets for review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings/>
</office:document-settings>
XML;
        $signatureXml = '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="settings.xml"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="META-INF/documentsignatures.xml"/>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0],
                ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml, 'compressionMethod' => 0],
                ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMB', 'compressionMethod' => 0],
                ['name' => 'Scripts/review/basic.xba', 'data' => 'macro', 'compressionMethod' => 0],
            ],
        ))->summarize();
        $inventory = $summary['packageInventory'];

        $t->same(10, $inventory['entryCount']);
        $t->same(6, $inventory['manifestDeclaredPartCount']);
        $t->same(2, $inventory['undeclaredEntryCount']);
        $t->same(6, $inventory['corePackagePartCount']);
        $t->same(1, $inventory['mediaResourcePartCount']);
        $t->same(1, $inventory['packageThumbnailPartCount']);
        $t->same(1, $inventory['packageSignaturePartCount']);
        $t->same([
            'manifest-declared' => 6,
            'media-resource' => 1,
            'odf-content' => 1,
            'odf-manifest' => 1,
            'odf-meta' => 1,
            'odf-mimetype' => 1,
            'odf-settings' => 1,
            'odf-styles' => 1,
            'package-signature' => 1,
            'package-thumbnail' => 1,
            'undeclared-package-entry' => 2,
        ], $inventory['roleCounts']);
        $t->same([
            'package-thumbnail' => 1,
            'undeclared-package-entry' => 2,
        ], $inventory['undeclaredRoleCounts']);
        $t->same(['odf-settings', 'manifest-declared'], $inventory['parts']['settings.xml']['roles']);
        $t->same(['package-signature', 'manifest-declared'], $inventory['parts']['META-INF/documentsignatures.xml']['roles']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $inventory['parts']['Thumbnails/thumbnail.png']['roles']);
        $t->same(['undeclared-package-entry'], $inventory['parts']['Scripts/review/basic.xba']['roles']);
        $t->same(1, $summary['packageSignatures']['count']);
        $t->same(1, $summary['packageThumbnails']['count']);
    },
    'reports compact ODT package thumbnails as metadata-only package review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $thumbnailBytes = 'THUMBNAIL';
        $encryptedBytes = 'ENCRYPTEDPNG';
        $invalidBytes = 'NOT-IMAGE';
        $orphanBytes = 'WEBPTHUMB';
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Thumbnails/encrypted.png" manifest:size="12">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="thumbnail-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="thumbnail-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Thumbnails/thumbnail.png" manifest:size="' . strlen($thumbnailBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="Thumbnails/missing.jpg"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Thumbnails/not-image.png"/>'
            . $encryptedEntry,
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
                ['name' => 'Thumbnails/encrypted.png', 'data' => $encryptedBytes, 'compressionMethod' => 0],
                ['name' => 'Thumbnails/not-image.png', 'data' => $invalidBytes, 'compressionMethod' => 0],
                ['name' => 'Thumbnails/orphan.webp', 'data' => $orphanBytes, 'compressionMethod' => 0],
            ]
        ))->summarize();
        $thumbnails = $summary['packageThumbnails'];
        $itemsByPath = [];
        foreach ($thumbnails['items'] as $item) {
            $itemsByPath[$item['packagePath']] = $item;
        }
        $inventory = $summary['packageInventory']['parts'];

        $t->same(5, $thumbnails['count']);
        $t->same(3, $thumbnails['readableCount']);
        $t->same(4, $thumbnails['declaredCount']);
        $t->same(1, $thumbnails['undeclaredCount']);
        $t->same(1, $thumbnails['missingCount']);
        $t->same(1, $thumbnails['encryptedCount']);
        $t->same(1, $thumbnails['invalidMediaTypeCount']);
        $t->same(4, $thumbnails['issueCount']);
        $t->same([
            'odf-thumbnail-encrypted-package-part',
            'odf-thumbnail-invalid-media-type',
            'odf-thumbnail-missing-package-part',
            'odf-thumbnail-undeclared-package-part',
        ], $thumbnails['issueCodes']);
        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));

        $declared = $itemsByPath['Thumbnails/thumbnail.png'];
        $t->same('Thumbnails/thumbnail.png', $declared['fullPath']);
        $t->same('image/png', $declared['mediaType']);
        $t->same(true, $declared['declared']);
        $t->same(false, $declared['undeclared']);
        $t->same(true, $declared['valid']);
        $t->same(strlen($thumbnailBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-metadata-only', $declared['reviewPolicy']);
        $t->same([], $declared['issues']);

        $missing = $itemsByPath['Thumbnails/missing.jpg'];
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-thumbnail-missing-package-part'], $missing['issues']);

        $encrypted = $itemsByPath['Thumbnails/encrypted.png'];
        $t->same(true, $encrypted['exists']);
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(null, $encrypted['crc32']);
        $t->same(sprintf('%08x', crc32($encryptedBytes)), $encrypted['storedCrc32']);
        $t->same(['odf-thumbnail-encrypted-package-part'], $encrypted['issues']);

        $invalid = $itemsByPath['Thumbnails/not-image.png'];
        $t->same('application/octet-stream', $invalid['mediaType']);
        $t->same('application/octet-stream', $invalid['mediaTypeBase']);
        $t->same(false, $invalid['valid']);
        $t->same(['odf-thumbnail-invalid-media-type'], $invalid['issues']);

        $orphan = $itemsByPath['Thumbnails/orphan.webp'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('image/webp', $orphan['mediaType']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-thumbnail-undeclared-package-part'], $orphan['issues']);

        $t->same(['package-thumbnail', 'manifest-declared'], $inventory['Thumbnails/thumbnail.png']['roles']);
        $t->same(['package-thumbnail', 'manifest-declared'], $inventory['Thumbnails/not-image.png']['roles']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $inventory['Thumbnails/orphan.webp']['roles']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('Thumbnails/orphan.webp', $summary['undeclaredPackageEntries'][0]['path']);
    },
    'reports compact ODT package signatures as metadata-only package review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $documentSignatureBytes = '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>';
        $encryptedBytes = '<encrypted-signatures/>';
        $invalidBytes = 'PNG-SIGNATURE-SIDECAR';
        $orphanBytes = '<orphan-signatures/>';
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="META-INF/encrypted-signatures.xml" manifest:size="21">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="signature-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="signature-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="META-INF/documentsignatures.xml" manifest:size="' . strlen($documentSignatureBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="application/xml" manifest:full-path="META-INF/macrosignatures.xml"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="META-INF/packagesignatures.xml"/>'
            . $encryptedEntry,
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'META-INF/documentsignatures.xml', 'data' => $documentSignatureBytes, 'compressionMethod' => 0],
                ['name' => 'META-INF/encrypted-signatures.xml', 'data' => $encryptedBytes, 'compressionMethod' => 0],
                ['name' => 'META-INF/packagesignatures.xml', 'data' => $invalidBytes, 'compressionMethod' => 0],
                ['name' => 'META-INF/orphan-signatures.xml', 'data' => $orphanBytes, 'compressionMethod' => 0],
            ]
        ))->summarize();
        $signatures = $summary['packageSignatures'];
        $itemsByPath = [];
        foreach ($signatures['items'] as $item) {
            $itemsByPath[$item['packagePath']] = $item;
        }
        $inventory = $summary['packageInventory']['parts'];

        $t->same(5, $signatures['count']);
        $t->same(3, $signatures['readableCount']);
        $t->same(4, $signatures['declaredCount']);
        $t->same(1, $signatures['undeclaredCount']);
        $t->same(1, $signatures['missingCount']);
        $t->same(1, $signatures['encryptedCount']);
        $t->same(1, $signatures['invalidMediaTypeCount']);
        $t->same(4, $signatures['issueCount']);
        $t->same([
            'odf-signature-encrypted-package-part',
            'odf-signature-invalid-media-type',
            'odf-signature-missing-package-part',
            'odf-signature-undeclared-package-part',
        ], $signatures['issueCodes']);
        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));

        $declared = $itemsByPath['META-INF/documentsignatures.xml'];
        $t->same('META-INF/documentsignatures.xml', $declared['fullPath']);
        $t->same('text/xml', $declared['mediaType']);
        $t->same(['text/xml', 'application/xml'], $declared['expectedMediaTypes']);
        $t->same(true, $declared['declared']);
        $t->same(true, $declared['valid']);
        $t->same(strlen($documentSignatureBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($documentSignatureBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('package-signature-metadata-only', $declared['reviewPolicy']);
        $t->same([], $declared['issues']);

        $missing = $itemsByPath['META-INF/macrosignatures.xml'];
        $t->same(false, $missing['exists']);
        $t->same('application/xml', $missing['mediaType']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-signature-missing-package-part'], $missing['issues']);

        $encrypted = $itemsByPath['META-INF/encrypted-signatures.xml'];
        $t->same(true, $encrypted['exists']);
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(null, $encrypted['crc32']);
        $t->same(sprintf('%08x', crc32($encryptedBytes)), $encrypted['storedCrc32']);
        $t->same(['odf-signature-encrypted-package-part'], $encrypted['issues']);

        $invalid = $itemsByPath['META-INF/packagesignatures.xml'];
        $t->same('image/png', $invalid['mediaType']);
        $t->same('image/png', $invalid['mediaTypeBase']);
        $t->same(false, $invalid['valid']);
        $t->same(strlen($invalidBytes), $invalid['byteLength']);
        $t->same(['odf-signature-invalid-media-type'], $invalid['issues']);

        $orphan = $itemsByPath['META-INF/orphan-signatures.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(null, $orphan['mediaType']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-signature-undeclared-package-part'], $orphan['issues']);

        $t->same(['package-signature', 'manifest-declared'], $inventory['META-INF/documentsignatures.xml']['roles']);
        $t->same(['package-signature', 'manifest-declared'], $inventory['META-INF/packagesignatures.xml']['roles']);
        $t->same(['package-signature', 'undeclared-package-entry'], $inventory['META-INF/orphan-signatures.xml']['roles']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('META-INF/orphan-signatures.xml', $summary['undeclaredPackageEntries'][0]['path']);
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
    'preserves ODT meta link policy metadata and repeated keywords' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $meta = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  office:version="1.3">
  <office:meta>
    <dc:title>Linked Metadata Packet</dc:title>
    <meta:keyword>import, odt</meta:keyword>
    <meta:keyword>review queue</meta:keyword>
    <meta:template
      xlink:type="simple"
      xlink:href="../Templates/source-template.ott"
      xlink:title="Source Template"
      xlink:actuate="onRequest"
      xlink:show="replace"
      meta:date="2026-06-09T10:00:00Z"
      meta:name="source-template"/>
    <meta:auto-reload
      xlink:type="simple"
      xlink:href="https://example.test/reload.odt"
      xlink:actuate="onLoad"
      xlink:show="replace"
      meta:delay="PT5M"/>
    <meta:hyperlink-behaviour
      office:target-frame-name="_blank"
      xlink:show="new"/>
  </office:meta>
</office:document-meta>
XML;

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(meta: $meta));
        $metadata = $odt->metadata();
        $summary = $odt->summarize();
        $documentMetadata = $odt->readContentDocument()->attr('metadata');

        $t->same('Linked Metadata Packet', $metadata['title']);
        $t->same(['import', 'odt', 'review queue'], $metadata['keywords']);

        $t->same('../Templates/source-template.ott', $metadata['template']['href']);
        $t->same('Source Template', $metadata['template']['title']);
        $t->same('simple', $metadata['template']['type']);
        $t->same('onRequest', $metadata['template']['actuate']);
        $t->same('replace', $metadata['template']['show']);
        $t->same('2026-06-09T10:00:00Z', $metadata['template']['date']);
        $t->same('source-template', $metadata['template']['name']);

        $t->same('https://example.test/reload.odt', $metadata['autoReload']['href']);
        $t->same('simple', $metadata['autoReload']['type']);
        $t->same('onLoad', $metadata['autoReload']['actuate']);
        $t->same('replace', $metadata['autoReload']['show']);
        $t->same('PT5M', $metadata['autoReload']['delay']);

        $t->same('_blank', $metadata['hyperlinkBehaviour']['targetFrameName']);
        $t->same('new', $metadata['hyperlinkBehaviour']['show']);

        $t->same($metadata['keywords'], $summary['metadata']['keywords']);
        $t->same($metadata['template'], $summary['metadata']['template']);
        $t->same($metadata['autoReload'], $summary['metadata']['autoReload']);
        $t->same($metadata['hyperlinkBehaviour'], $summary['metadata']['hyperlinkBehaviour']);
        $t->same($metadata['template'], $documentMetadata['template']);
        $t->same($metadata['autoReload'], $documentMetadata['autoReload']);
        $t->same($metadata['hyperlinkBehaviour'], $documentMetadata['hyperlinkBehaviour']);
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
    'classifies compact ODT settings XML as a package inventory role' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings>
    <config:config-item-set config:name="ooo:view-settings">
      <config:config-item config:name="ZoomValue" config:type="int">125</config:config-item>
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

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifestWithSettings,
            extraParts: [['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0]],
        ))->summarize();
        $settingsPart = $summary['packageInventory']['parts']['settings.xml'];

        $t->same(true, $summary['settingsXml']);
        $t->same(['odf-settings', 'manifest-declared'], $settingsPart['roles']);
        $t->same(true, $settingsPart['declaredInManifest']);
        $t->same(false, $settingsPart['undeclared']);
        $t->same('settings.xml', $settingsPart['manifestPath']);
        $t->same('text/xml', $settingsPart['manifestMediaType']);
        $t->same(0, $settingsPart['compressionMethod']);
        $t->same('stored', $settingsPart['compressionMethodName']);
        $t->same(strlen($settingsXml), $settingsPart['byteLength']);
        $t->same(true, $settingsPart['canExposeBytes']);
        $t->same(0, $summary['undeclaredPackageEntryCount']);
        $t->same(1, count($summary['mediaParts']), 'settings.xml must remain outside media handoff');
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
