<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\OdfReader;
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
    string $mimetypeExtraFieldData = '',
) use ($manifestXml, $contentXml, $stylesXml, $metaXml): ZipPackage {
    $mimetypePart = ['name' => 'mimetype', 'data' => $mimetype, 'compressionMethod' => $mimetypeCompression];
    if ($mimetypeExtraFieldData !== '') {
        $mimetypePart['extraFieldData'] = $mimetypeExtraFieldData;
    }

    $parts = [
        $mimetypePart,
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
        $rawName = $part['rawName'] ?? $name;
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $flags = $part['generalPurposeFlags'] ?? 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $offset = strlen($body);
        $crc = $crc32($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($rawName),
            0
        );
        $body .= $rawName . $compressed;

        $centralRecords[$name] = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($rawName),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $rawName;
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
    'preserves compact ODT mimetype local header provenance for package review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage());
        $summary = $odt->summarize();
        $mimetype = $summary['mimetypeEntry'];

        $t->same('mimetype', $mimetype['name']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $mimetype['mediaType']);
        $t->same(true, $mimetype['firstLocalEntry']);
        $t->same(true, $mimetype['firstCentralDirectoryEntry']);
        $t->same(0, $mimetype['localHeaderOrder']);
        $t->same(0, $mimetype['centralDirectoryIndex']);
        $t->same(0, $mimetype['localHeaderOffset']);
        $t->same(30 + strlen('mimetype'), $mimetype['localHeaderLength']);
        $t->same(strlen('mimetype'), $mimetype['localNameLength']);
        $t->same(0, $mimetype['localExtraFieldLength']);
        $t->same(0, $mimetype['localExtraFieldRecordCount']);
        $t->same([], $mimetype['localExtraFieldIds']);
        $t->same([], $mimetype['localExtraFieldRecords']);
        $t->same(0, $mimetype['centralExtraFieldLength']);
        $t->same(0, $mimetype['centralExtraFieldRecordCount']);
        $t->same([], $mimetype['centralExtraFieldIds']);
        $t->same([], $mimetype['centralExtraFieldRecords']);
        $t->same(false, $mimetype['hasLocalExtraFields']);
        $t->same(false, $mimetype['hasCentralExtraFields']);
        $t->same(0, $mimetype['compressionMethod']);
        $t->same('stored', $mimetype['compressionMethodName']);
        $t->same(strlen(OpenDocumentPackage::TEXT_MIMETYPE), $mimetype['byteLength']);
        $t->same(strlen(OpenDocumentPackage::TEXT_MIMETYPE), $mimetype['compressedByteLength']);
        $t->same($odt->package()->entry('mimetype')->crc32Hex(), $mimetype['crc32']);
        $t->same(false, $mimetype['canExposeBytes']);
        $t->same('odf-mimetype-validation-only', $mimetype['byteExposurePolicy']);
        $t->same([], $mimetype['diagnostics']);
    },
    'rejects compact ODT packages whose mimetype is not first in local header order' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $package = $buildZipPackageWithCentralDirectoryOrder(
            [
                ['name' => 'content.xml', 'data' => $contentXml],
                ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
                ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
                ['name' => 'styles.xml', 'data' => $stylesXml],
                ['name' => 'meta.xml', 'data' => $metaXml],
                ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA'],
            ],
            ['mimetype', 'META-INF/manifest.xml', 'content.xml', 'styles.xml', 'meta.xml', 'Pictures/hero.png']
        );

        $t->throws(\RuntimeException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($package));
    },
    'rejects compact ODT mimetype local header extra fields before package exposure' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $extraField = pack('vva*', 0xcafe, strlen('odf-review'), 'odf-review');

        $t->throws(
            \RuntimeException::class,
            static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage(
                $buildOdtPackage(mimetypeExtraFieldData: $extraField)
            )
        );
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
    'preserves compact ODT manifest custom file-entry attributes in review packets' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            ],
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:loext="urn:libreoffice:names:experimental:office:xmlns:loext:1.0" xmlns:wp="urn:wordpress:review" manifest:version="1.3" loext:generator="LibreOffice 24.2" wp:review-source="migration-queue" xml:lang="en-US">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml" loext:checksum="sha256-content" wp:review-priority="high"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7" loext:media-type-hint="review-cover" wp:empty-note="" xml:lang="en-US"/>',
            ],
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest));
        $summary = $odt->summarize();
        $rootAttributeProvenance = $odt->manifestRootAttributes();
        $content = $odt->manifestEntry('content.xml');
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $rootAttributes = [];
        foreach ($rootAttributeProvenance['attributes'] as $attribute) {
            $rootAttributes[$attribute['name']] = $attribute;
        }
        $contentAttributes = [];
        foreach ($content['manifestAttributes'] as $attribute) {
            $contentAttributes[$attribute['name']] = $attribute;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $order = $summary['manifestReview']['manifestFileEntryOrder'];
        $inventory = $summary['packageInventory']['parts'];

        $t->same(4, $rootAttributeProvenance['attributeCount']);
        $t->same(['loext:generator', 'manifest:version', 'wp:review-source', 'xml:lang'], $rootAttributeProvenance['attributeNames']);
        $t->same(true, $rootAttributes['manifest:version']['structural']);
        $t->same(false, $rootAttributes['wp:review-source']['structural']);
        $t->same('urn:wordpress:review', $rootAttributes['wp:review-source']['namespaceUri']);
        $t->same('migration-queue', $rootAttributes['wp:review-source']['value']);
        $t->same(3, $rootAttributeProvenance['customAttributeCount']);
        $t->same(['loext:generator', 'wp:review-source', 'xml:lang'], $rootAttributeProvenance['customAttributeNames']);
        $t->same([
            'loext:generator' => 'LibreOffice 24.2',
            'wp:review-source' => 'migration-queue',
            'xml:lang' => 'en-US',
        ], $rootAttributeProvenance['customAttributeMap']);
        $t->same($rootAttributeProvenance, $summary['manifestRootAttributes']);
        $t->same(4, $summary['manifestReview']['manifestRootAttributeCount']);
        $t->same(['loext:generator', 'wp:review-source', 'xml:lang'], $summary['manifestReview']['manifestRootCustomAttributeNames']);
        $t->same('LibreOffice 24.2', $summary['manifestReview']['manifestRootCustomAttributeMap']['loext:generator']);
        $t->same(4, $content['manifestAttributeCount']);
        $t->same(['loext:checksum', 'manifest:full-path', 'manifest:media-type', 'wp:review-priority'], $content['manifestAttributeNames']);
        $t->same(true, $contentAttributes['manifest:full-path']['structural']);
        $t->same(false, $contentAttributes['loext:checksum']['structural']);
        $t->same('urn:libreoffice:names:experimental:office:xmlns:loext:1.0', $contentAttributes['loext:checksum']['namespaceUri']);
        $t->same('loext', $contentAttributes['loext:checksum']['prefix']);
        $t->same('sha256-content', $contentAttributes['loext:checksum']['value']);
        $t->same(2, $content['customManifestAttributeCount']);
        $t->same(['loext:checksum', 'wp:review-priority'], $content['customManifestAttributeNames']);
        $t->same([
            'loext:checksum' => 'sha256-content',
            'wp:review-priority' => 'high',
        ], $content['customManifestAttributeMap']);

        $t->same(6, $hero['manifestAttributeCount']);
        $t->same(['loext:media-type-hint', 'wp:empty-note', 'xml:lang'], $hero['customManifestAttributeNames']);
        $t->same('review-cover', $hero['customManifestAttributeMap']['loext:media-type-hint']);
        $t->same('', $hero['customManifestAttributeMap']['wp:empty-note']);
        $t->same('en-US', $hero['customManifestAttributeMap']['xml:lang']);

        $t->same(2, $summary['manifestReview']['manifestCustomAttributeEntryCount']);
        $t->same(5, $summary['manifestReview']['manifestCustomAttributeCount']);
        $t->same(['loext:checksum', 'loext:media-type-hint', 'wp:empty-note', 'wp:review-priority', 'xml:lang'], $summary['manifestReview']['manifestCustomAttributeNames']);
        $t->same(['content.xml', 'Pictures/hero.png'], array_column($summary['manifestReview']['manifestCustomAttributeItems'], 'path'));
        $t->same(['loext:checksum', 'wp:review-priority'], $reviewByPath['content.xml']['customManifestAttributeNames']);
        $t->same(['loext:media-type-hint', 'wp:empty-note', 'xml:lang'], $reviewByPath['Pictures/hero.png']['customManifestAttributeNames']);
        $t->same(['loext:checksum', 'wp:review-priority'], $order[1]['customManifestAttributeNames']);
        $t->same(['loext:media-type-hint', 'wp:empty-note', 'xml:lang'], $order[4]['customManifestAttributeNames']);
        $t->same(2, $inventory['content.xml']['customManifestAttributeCount']);
        $t->same('sha256-content', $inventory['content.xml']['customManifestAttributeMap']['loext:checksum']);
        $t->same(3, $inventory['Pictures/hero.png']['customManifestAttributeCount']);
        $t->same('en-US', $inventory['Pictures/hero.png']['customManifestAttributeMap']['xml:lang']);
    },
    'preserves compact ODT manifest file-entry child element provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            ],
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:loext="urn:libreoffice:manifest" xmlns:wp="urn:wordpress:review" manifest:version="1.3">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"><wp:review-hint wp:state="manual"><wp:nested/></wp:review-hint></manifest:file-entry>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="hero-checksum"/><loext:media-policy loext:role="review"/></manifest:file-entry>',
            ],
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest));
        $summary = $odt->summarize();
        $content = $odt->manifestEntry('content.xml');
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $review = $summary['manifestReview'];
        $reviewByPath = [];
        foreach ($review['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $order = $review['manifestFileEntryOrder'];
        $inventory = $summary['packageInventory']['parts'];
        $identityByPath = [];
        foreach ($summary['packageIdentity']['manifestEntries'] as $item) {
            $identityByPath[$item['path']] = $item;
        }

        $t->same(1, $content['manifestChildElementCount']);
        $t->same(['wp:review-hint'], $content['manifestChildElementNames']);
        $t->same(1, $content['customManifestChildElementCount']);
        $t->same(['wp:review-hint'], $content['customManifestChildElementNames']);
        $t->same('urn:wordpress:review', $content['customManifestChildElements'][0]['namespaceUri']);
        $t->same('wp', $content['customManifestChildElements'][0]['prefix']);
        $t->same(1, $content['customManifestChildElements'][0]['attributeCount']);
        $t->same(1, $content['customManifestChildElements'][0]['childElementCount']);

        $t->same(2, $hero['manifestChildElementCount']);
        $t->same(['manifest:encryption-data', 'loext:media-policy'], $hero['manifestChildElementNames']);
        $t->same(true, $hero['manifestChildElements'][0]['structural']);
        $t->same(false, $hero['manifestChildElements'][1]['structural']);
        $t->same(1, $hero['customManifestChildElementCount']);
        $t->same(['loext:media-policy'], $hero['customManifestChildElementNames']);
        $t->same('media-policy', $hero['customManifestChildElements'][0]['localName']);

        $t->same(2, $review['manifestCustomChildElementEntryCount']);
        $t->same(2, $review['manifestCustomChildElementCount']);
        $t->same(['loext:media-policy', 'wp:review-hint'], $review['manifestCustomChildElementNames']);
        $t->same(['content.xml', 'Pictures/hero.png'], array_column($review['manifestCustomChildElementItems'], 'part'));
        $t->same(['wp:review-hint'], $reviewByPath['content.xml']['customManifestChildElementNames']);
        $t->same(['loext:media-policy'], $reviewByPath['Pictures/hero.png']['customManifestChildElementNames']);
        $t->same(['wp:review-hint'], $order[1]['customManifestChildElementNames']);
        $t->same(['loext:media-policy'], $order[4]['customManifestChildElementNames']);
        $t->same(['wp:review-hint'], $inventory['content.xml']['customManifestChildElementNames']);
        $t->same(['loext:media-policy'], $inventory['Pictures/hero.png']['customManifestChildElementNames']);
        $t->same(['wp:review-hint'], $identityByPath['content.xml']['customManifestChildElementNames']);
        $t->same(['loext:media-policy'], $identityByPath['Pictures/hero.png']['customManifestChildElementNames']);
    },
    'preserves compact ODT manifest root extension child provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">',
                '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>',
            ],
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:loext="urn:libreoffice:manifest" xmlns:wp="urn:wordpress:review" manifest:version="1.3">',
                '<loext:package-policy loext:state="manual"><wp:handoff/></loext:package-policy>'
                . '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>',
            ],
            $manifestXml
        );
        $manifest = str_replace(
            '</manifest:manifest>',
            '<wp:queue wp:priority="high"/></manifest:manifest>',
            $manifest
        );

        $package = $buildOdtPackage(manifest: $manifest);
        $odt = OpenDocumentPackage::fromPackage($package);
        $summary = $odt->summarize();
        $rootExtensions = $summary['manifestRootExtensionElements'];
        $review = $summary['manifestReview'];
        $identity = $summary['packageIdentity'];

        $t->same(2, $rootExtensions['extensionElementCount']);
        $t->same(['loext:package-policy', 'wp:queue'], $rootExtensions['extensionElementNames']);
        $t->same('urn:libreoffice:manifest', $rootExtensions['extensionElements'][0]['namespaceUri']);
        $t->same('loext', $rootExtensions['extensionElements'][0]['prefix']);
        $t->same(1, $rootExtensions['extensionElements'][0]['attributeCount']);
        $t->same(1, $rootExtensions['extensionElements'][0]['childElementCount']);
        $t->same('urn:wordpress:review', $rootExtensions['extensionElements'][1]['namespaceUri']);
        $t->same('wp', $rootExtensions['extensionElements'][1]['prefix']);
        $t->same(1, $rootExtensions['extensionElements'][1]['attributeCount']);

        $t->same(2, $review['manifestRootExtensionElementCount']);
        $t->same(['loext:package-policy', 'wp:queue'], $review['manifestRootExtensionElementNames']);
        $t->same($rootExtensions['extensionElements'], $review['manifestRootExtensionElements']);
        $t->same(5, $review['manifestFileEntryCount']);
        $t->same(['/', 'content.xml', 'styles.xml', 'meta.xml', 'Pictures/hero.png'], array_column($review['manifestFileEntryOrder'], 'fullPath'));

        $t->same(2, $identity['manifestRootExtensionElementCount']);
        $t->same(['loext:package-policy', 'wp:queue'], $identity['manifestRootExtensionElementNames']);
        $t->same($rootExtensions['extensionElements'], $identity['manifestRootExtensionElements']);
        $changedManifest = str_replace('wp:priority="high"', 'wp:priority="low"', $manifest);
        $changedIdentity = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $changedManifest))->summarize()['packageIdentity'];
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);

        $invalidManifestNamespaceChild = str_replace(
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
            '<manifest:package-policy/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
            $manifestXml
        );
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $invalidManifestNamespaceChild)));
    },
    'keeps compact ODT manifest custom attribute collision provenance stable' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            [
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            ],
            [
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml" xmlns:wp="urn:wordpress:review:content" wp:media-type="application/x-content-shadow" wp:priority="1"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7" xmlns:wp="urn:wordpress:review:hero" xmlns:alt="urn:wordpress:review:content" wp:media-type="application/x-hero-shadow" wp:priority="2" alt:priority="1" alt:full-path="Pictures/shadow.png"/>',
            ],
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest));
        $summary = $odt->summarize();
        $review = $summary['manifestReview'];
        $content = $odt->manifestEntry('content.xml');
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $contentCustomAttributes = [];
        foreach ($content['customManifestAttributes'] as $attribute) {
            $contentCustomAttributes[$attribute['name']] = $attribute;
        }
        $heroCustomAttributes = [];
        foreach ($hero['customManifestAttributes'] as $attribute) {
            $heroCustomAttributes[$attribute['name']] = $attribute;
        }
        $reviewByPath = [];
        foreach ($review['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $order = $review['manifestFileEntryOrder'];
        $inventory = $summary['packageInventory']['parts'];
        $readerProvenance = (new OdfReader())->readPackage($buildOdtPackage(manifest: $manifest))['importReport']['manifest']['packageProvenance'];

        $t->same('text/xml', $content['mediaType']);
        $t->same('content.xml', $content['packagePath']);
        $t->same(4, $content['manifestAttributeCount']);
        $t->same(['manifest:full-path', 'manifest:media-type', 'wp:media-type', 'wp:priority'], $content['manifestAttributeNames']);
        $t->same(2, $content['customManifestAttributeCount']);
        $t->same(['wp:media-type', 'wp:priority'], $content['customManifestAttributeNames']);
        $t->same([
            'wp:media-type' => 'application/x-content-shadow',
            'wp:priority' => '1',
        ], $content['customManifestAttributeMap']);
        $t->same('urn:wordpress:review:content', $contentCustomAttributes['wp:media-type']['namespaceUri']);
        $t->same('wp', $contentCustomAttributes['wp:media-type']['prefix']);
        $t->same('media-type', $contentCustomAttributes['wp:media-type']['localName']);
        $t->same(false, $contentCustomAttributes['wp:media-type']['structural']);

        $t->same('image/png', $hero['mediaType']);
        $t->same('Pictures/hero.png', $hero['packagePath']);
        $t->same(7, $hero['manifestAttributeCount']);
        $t->same(['alt:full-path', 'alt:priority', 'manifest:full-path', 'manifest:media-type', 'manifest:size', 'wp:media-type', 'wp:priority'], $hero['manifestAttributeNames']);
        $t->same(4, $hero['customManifestAttributeCount']);
        $t->same(['alt:full-path', 'alt:priority', 'wp:media-type', 'wp:priority'], $hero['customManifestAttributeNames']);
        $t->same([
            'alt:full-path' => 'Pictures/shadow.png',
            'alt:priority' => '1',
            'wp:media-type' => 'application/x-hero-shadow',
            'wp:priority' => '2',
        ], $hero['customManifestAttributeMap']);
        $t->same('urn:wordpress:review:hero', $heroCustomAttributes['wp:media-type']['namespaceUri']);
        $t->same('urn:wordpress:review:content', $heroCustomAttributes['alt:priority']['namespaceUri']);
        $t->same('full-path', $heroCustomAttributes['alt:full-path']['localName']);
        $t->same(false, $heroCustomAttributes['alt:full-path']['structural']);

        $t->same(2, $review['manifestCustomAttributeEntryCount']);
        $t->same(6, $review['manifestCustomAttributeCount']);
        $t->same(['alt:full-path', 'alt:priority', 'wp:media-type', 'wp:priority'], $review['manifestCustomAttributeNames']);
        $t->same([1, 4], array_column($review['manifestCustomAttributeItems'], 'manifestIndex'));
        $t->same(['content.xml', 'Pictures/hero.png'], array_column($review['manifestCustomAttributeItems'], 'path'));
        $t->same(['wp:media-type', 'wp:priority'], $reviewByPath['content.xml']['customManifestAttributeNames']);
        $t->same(['alt:full-path', 'alt:priority', 'wp:media-type', 'wp:priority'], $reviewByPath['Pictures/hero.png']['customManifestAttributeNames']);
        $t->same(['wp:media-type', 'wp:priority'], $order[1]['customManifestAttributeNames']);
        $t->same(['alt:full-path', 'alt:priority', 'wp:media-type', 'wp:priority'], $order[4]['customManifestAttributeNames']);
        $t->same('text/xml', $inventory['content.xml']['manifestMediaType']);
        $t->same('image/png', $inventory['Pictures/hero.png']['manifestMediaType']);
        $t->same('content.xml', $inventory['content.xml']['manifestPackagePath']);
        $t->same('Pictures/hero.png', $inventory['Pictures/hero.png']['manifestPackagePath']);
        $t->same('application/x-content-shadow', $inventory['content.xml']['customManifestAttributeMap']['wp:media-type']);
        $t->same('Pictures/shadow.png', $inventory['Pictures/hero.png']['customManifestAttributeMap']['alt:full-path']);

        $t->same($review['manifestCustomAttributeEntryCount'], $readerProvenance['manifestCustomAttributeEntryCount']);
        $t->same($review['manifestCustomAttributeCount'], $readerProvenance['manifestCustomAttributeCount']);
        $t->same($review['manifestCustomAttributeNames'], $readerProvenance['manifestCustomAttributeNames']);
        $t->same($order[1]['customManifestAttributeNames'], $readerProvenance['manifestFileEntryOrder'][1]['customManifestAttributeNames']);
        $t->same($order[4]['customManifestAttributeMap'], $readerProvenance['manifestFileEntryOrder'][4]['customManifestAttributeMap']);
        $t->same($inventory['content.xml']['customManifestAttributeMap'], $readerProvenance['parts']['content.xml']['customManifestAttributeMap']);
        $t->same($inventory['Pictures/hero.png']['customManifestAttributeNames'], $readerProvenance['parts']['Pictures/hero.png']['customManifestAttributeNames']);

        $decodedPathConflictManifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero%2epng"/>',
            $manifestXml
        );
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $decodedPathConflictManifest)));
    },
    'keeps duplicate compact ODT manifest custom attribute names and prefix collisions stable' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            ],
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:wp="urn:wordpress:review:root" manifest:version="1.3" wp:review-source="root">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml" xmlns:wp="urn:wordpress:review:content" wp:media-type="application/x-content-shadow" wp:priority="1"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7" xmlns:wp="urn:wordpress:review:hero" xmlns:asset="urn:wordpress:review:content" wp:media-type="application/x-hero-shadow" wp:priority="2" asset:media-type="application/x-content-shadow" asset:priority="1" asset:full-path="Pictures/shadow.png"/>',
            ],
            $manifestXml
        );
        $package = $buildOdtPackage(manifest: $manifest);
        $odt = OpenDocumentPackage::fromPackage($package);
        $summary = $odt->summarize();
        $review = $summary['manifestReview'];
        $root = $odt->manifestRootAttributes();
        $content = $odt->manifestEntry('content.xml');
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $reviewByPath = [];
        foreach ($review['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $order = $review['manifestFileEntryOrder'];
        $inventory = $summary['packageInventory']['parts'];
        $readerResult = (new OdfReader())->readPackage($package);
        $readerManifest = $readerResult['document']->attr('manifest');
        $readerProvenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $customNamesByLocalName = static function (array $attributes): array {
            $namesByLocalName = [];
            foreach ($attributes as $attribute) {
                $localName = $attribute['localName'] ?? null;
                $name = $attribute['name'] ?? null;
                if (!is_string($localName) || $localName === '' || !is_string($name) || $name === '') {
                    continue;
                }

                $namesByLocalName[$localName][] = $name;
            }
            foreach ($namesByLocalName as &$names) {
                sort($names, SORT_STRING);
            }
            unset($names);
            ksort($namesByLocalName, SORT_STRING);

            return $namesByLocalName;
        };
        $expectedContentCustom = [
            'wp:media-type' => 'application/x-content-shadow',
            'wp:priority' => '1',
        ];
        $expectedHeroCustom = [
            'asset:full-path' => 'Pictures/shadow.png',
            'asset:media-type' => 'application/x-content-shadow',
            'asset:priority' => '1',
            'wp:media-type' => 'application/x-hero-shadow',
            'wp:priority' => '2',
        ];
        $heroCustomNamesByLocalName = $customNamesByLocalName($hero['customManifestAttributes']);

        $t->same('urn:wordpress:review:root', $root['namespaceDeclarationMap']['xmlns:wp']);
        $t->same(['wp:review-source' => 'root'], $root['customAttributeMap']);
        $t->same('text/xml', $content['mediaType']);
        $t->same('content.xml', $content['packagePath']);
        $t->same($expectedContentCustom, $content['customManifestAttributeMap']);
        $t->same('urn:wordpress:review:content', $content['manifestNamespaceDeclarationMap']['xmlns:wp']);
        $t->same('image/png', $hero['mediaType']);
        $t->same('Pictures/hero.png', $hero['packagePath']);
        $t->same($expectedHeroCustom, $hero['customManifestAttributeMap']);
        $t->same('urn:wordpress:review:hero', $hero['manifestNamespaceDeclarationMap']['xmlns:wp']);
        $t->same('urn:wordpress:review:content', $hero['manifestNamespaceDeclarationMap']['xmlns:asset']);
        $t->same(['asset:full-path'], $heroCustomNamesByLocalName['full-path']);
        $t->same(['asset:media-type', 'wp:media-type'], $heroCustomNamesByLocalName['media-type']);
        $t->same(['asset:priority', 'wp:priority'], $heroCustomNamesByLocalName['priority']);

        $t->same(2, $review['manifestCustomAttributeEntryCount']);
        $t->same(7, $review['manifestCustomAttributeCount']);
        $t->same(['asset:full-path', 'asset:media-type', 'asset:priority', 'wp:media-type', 'wp:priority'], $review['manifestCustomAttributeNames']);
        $t->same([1, 4], array_column($review['manifestCustomAttributeItems'], 'manifestIndex'));
        $t->same(['content.xml', 'Pictures/hero.png'], array_column($review['manifestCustomAttributeItems'], 'path'));
        $t->same($expectedContentCustom, $reviewByPath['content.xml']['customManifestAttributeMap']);
        $t->same($expectedHeroCustom, $reviewByPath['Pictures/hero.png']['customManifestAttributeMap']);
        $t->same('urn:wordpress:review:content', $order[1]['manifestNamespaceDeclarationMap']['xmlns:wp']);
        $t->same('urn:wordpress:review:hero', $order[4]['manifestNamespaceDeclarationMap']['xmlns:wp']);
        $t->same($expectedHeroCustom, $order[4]['customManifestAttributeMap']);

        $t->same('image/png', $inventory['Pictures/hero.png']['manifestMediaType']);
        $t->same('Pictures/hero.png', $inventory['Pictures/hero.png']['manifestPackagePath']);
        $t->same($expectedContentCustom, $inventory['content.xml']['customManifestAttributeMap']);
        $t->same($expectedHeroCustom, $inventory['Pictures/hero.png']['customManifestAttributeMap']);
        $t->same('urn:wordpress:review:content', $inventory['Pictures/hero.png']['manifestNamespaceDeclarationMap']['xmlns:asset']);

        $t->same($root['customAttributeMap'], $readerManifest['rootCustomAttributeMap']);
        $t->same($root['namespaceDeclarationMap'], $readerProvenance['manifestRootNamespaceDeclarationMap']);
        $t->same($review['manifestCustomAttributeCount'], $readerProvenance['manifestCustomAttributeCount']);
        $t->same($review['manifestCustomAttributeNames'], $readerProvenance['manifestCustomAttributeNames']);
        $t->same($order[1]['customManifestAttributeMap'], $readerProvenance['manifestFileEntryOrder'][1]['customManifestAttributeMap']);
        $t->same($order[4]['customManifestAttributeMap'], $readerProvenance['manifestFileEntryOrder'][4]['customManifestAttributeMap']);
        $t->same($order[4]['manifestNamespaceDeclarationMap'], $readerProvenance['manifestFileEntryOrder'][4]['manifestNamespaceDeclarationMap']);
        $t->same($inventory['Pictures/hero.png']['customManifestAttributeMap'], $readerProvenance['parts']['Pictures/hero.png']['customManifestAttributeMap']);

        $duplicateQNameManifest = str_replace(
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml" xmlns:wp="urn:wordpress:review:content" wp:priority="1" wp:priority="2"/>',
            $manifestXml
        );
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $duplicateQNameManifest)));
    },
    'preserves compact ODT manifest namespace declarations for package review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            ],
            [
                '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:loext="urn:libreoffice:manifest" xmlns:wp="urn:wordpress:review" manifest:version="1.3" wp:packet="root-review">',
                '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml" xmlns:asset="urn:wordpress:asset-review" asset:state="canonical"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7" xmlns:media="urn:wordpress:media-review" media:role="cover"/>',
            ],
            $manifestXml
        );

        $package = $buildOdtPackage(manifest: $manifest);
        $odt = OpenDocumentPackage::fromPackage($package);
        $summary = $odt->summarize();
        $root = $odt->manifestRootAttributes();
        $rootDeclarations = [];
        foreach ($root['namespaceDeclarations'] as $declaration) {
            $rootDeclarations[$declaration['name']] = $declaration;
        }
        $content = $odt->manifestEntry('content.xml');
        $hero = $odt->manifestEntry('Pictures/hero.png');
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $order = $summary['manifestReview']['manifestFileEntryOrder'];
        $inventory = $summary['packageInventory']['parts'];
        $readerResult = (new OdfReader())->readPackage($package);
        $readerManifest = $readerResult['document']->attr('manifest');
        $readerProvenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $expectedRootNamespaceMap = [
            'xmlns:loext' => 'urn:libreoffice:manifest',
            'xmlns:manifest' => OpenDocumentPackage::MANIFEST_NAMESPACE,
            'xmlns:wp' => 'urn:wordpress:review',
        ];
        $expectedContentNamespaceMap = [
            'xmlns:asset' => 'urn:wordpress:asset-review',
            'xmlns:loext' => 'urn:libreoffice:manifest',
            'xmlns:manifest' => OpenDocumentPackage::MANIFEST_NAMESPACE,
            'xmlns:wp' => 'urn:wordpress:review',
        ];
        $expectedHeroNamespaceMap = [
            'xmlns:loext' => 'urn:libreoffice:manifest',
            'xmlns:manifest' => OpenDocumentPackage::MANIFEST_NAMESPACE,
            'xmlns:media' => 'urn:wordpress:media-review',
            'xmlns:wp' => 'urn:wordpress:review',
        ];

        $t->same(3, $root['namespaceDeclarationCount']);
        $t->same(['xmlns:loext', 'xmlns:manifest', 'xmlns:wp'], $root['namespaceDeclarationNames']);
        $t->same($expectedRootNamespaceMap, $root['namespaceDeclarationMap']);
        $t->same('wp', $rootDeclarations['xmlns:wp']['declaredPrefix']);
        $t->same('urn:wordpress:review', $rootDeclarations['xmlns:wp']['namespaceUri']);
        $t->same(false, $rootDeclarations['xmlns:wp']['default']);
        $t->same(1, $root['customAttributeCount']);
        $t->same(['wp:packet' => 'root-review'], $root['customAttributeMap']);

        $t->same(3, $summary['manifestReview']['manifestRootNamespaceDeclarationCount']);
        $t->same($expectedRootNamespaceMap, $summary['manifestRootAttributes']['namespaceDeclarationMap']);
        $t->same($expectedRootNamespaceMap, $summary['manifestReview']['manifestRootNamespaceDeclarationMap']);

        $t->same(4, $content['manifestNamespaceDeclarationCount']);
        $t->same(['xmlns:asset', 'xmlns:loext', 'xmlns:manifest', 'xmlns:wp'], $content['manifestNamespaceDeclarationNames']);
        $t->same($expectedContentNamespaceMap, $content['manifestNamespaceDeclarationMap']);
        $t->same(['asset:state' => 'canonical'], $content['customManifestAttributeMap']);
        $t->same(4, $hero['manifestNamespaceDeclarationCount']);
        $t->same($expectedHeroNamespaceMap, $hero['manifestNamespaceDeclarationMap']);
        $t->same(['media:role' => 'cover'], $hero['customManifestAttributeMap']);

        $t->same(4, $reviewByPath['content.xml']['manifestNamespaceDeclarationCount']);
        $t->same($expectedContentNamespaceMap, $reviewByPath['content.xml']['manifestNamespaceDeclarationMap']);
        $t->same($expectedHeroNamespaceMap, $reviewByPath['Pictures/hero.png']['manifestNamespaceDeclarationMap']);
        $t->same($expectedContentNamespaceMap, $order[1]['manifestNamespaceDeclarationMap']);
        $t->same($expectedHeroNamespaceMap, $order[4]['manifestNamespaceDeclarationMap']);

        $t->same(4, $inventory['content.xml']['manifestNamespaceDeclarationCount']);
        $t->same($expectedContentNamespaceMap, $inventory['content.xml']['manifestNamespaceDeclarationMap']);
        $t->same($expectedHeroNamespaceMap, $inventory['Pictures/hero.png']['manifestNamespaceDeclarationMap']);

        $t->same(3, $readerManifest['rootNamespaceDeclarationCount']);
        $t->same($expectedRootNamespaceMap, $readerManifest['rootNamespaceDeclarationMap']);
        $t->same(3, $readerProvenance['manifestRootNamespaceDeclarationCount']);
        $t->same($expectedRootNamespaceMap, $readerProvenance['manifestRootNamespaceDeclarationMap']);
        $t->same(4, $readerProvenance['manifestFileEntryOrder'][1]['manifestNamespaceDeclarationCount']);
        $t->same($expectedContentNamespaceMap, $readerProvenance['manifestFileEntryOrder'][1]['manifestNamespaceDeclarationMap']);
        $t->same($expectedHeroNamespaceMap, $readerProvenance['manifestFileEntryOrder'][4]['manifestNamespaceDeclarationMap']);
        $t->same(4, $readerProvenance['parts']['content.xml']['manifestNamespaceDeclarationCount']);
        $t->same($expectedContentNamespaceMap, $readerProvenance['parts']['content.xml']['manifestNamespaceDeclarationMap']);
        $t->same($expectedHeroNamespaceMap, $readerProvenance['parts']['Pictures/hero.png']['manifestNamespaceDeclarationMap']);
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
    'aggregates compact ODT manifest encryption review provenance' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifest = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:wp="urn:wordpress:review" manifest:version="1.4">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="hero-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="hero-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="16" manifest:iteration-count="1024" manifest:salt="hero-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
  <manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Pictures/secret.bin" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="secret-checksum">
      <manifest:algorithm manifest:algorithm-name="AES-256-CBC" manifest:initialisation-vector="secret-iv"/>
      <manifest:algorithm manifest:algorithm-name="AES-128-CBC" manifest:initialisation-vector="legacy-iv"/>
      <wp:review-hint>legacy encryption metadata</wp:review-hint>
    </manifest:encryption-data>
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="legacy-checksum">
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="32" manifest:iteration-count="2048" manifest:salt="secret-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA256" manifest:key-size="32"/>
    </manifest:encryption-data>
  </manifest:file-entry>
</manifest:manifest>
XML;

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Pictures/secret.bin', 'data' => 'SECRETBYTES'],
            ],
        ))->summarize();
        $encryption = $summary['manifestEncryption'];
        $reviewEncryption = $summary['manifestReview']['manifestEncryption'];
        $itemsByPath = [];
        foreach ($encryption['items'] as $item) {
            $itemsByPath[$item['path']] = $item;
        }

        $t->same(2, $encryption['encryptedItemCount']);
        $t->same(3, $encryption['recordCount']);
        $t->same(['Pictures/hero.png', 'Pictures/secret.bin'], $encryption['encryptedParts']);
        $t->same(['SHA1/1K' => 2, 'SHA256/1K' => 1], $encryption['checksumTypeCounts']);
        $t->same([
            'AES-128-CBC' => 1,
            'AES-256-CBC' => 1,
            'Blowfish CFB' => 1,
        ], $encryption['algorithmNameCounts']);
        $t->same(['PBKDF2' => 2], $encryption['keyDerivationNameCounts']);
        $t->same(['SHA1' => 1, 'SHA256' => 1], $encryption['startKeyGenerationNameCounts']);
        $t->same(1, $encryption['unknownChildCount']);
        $t->same(['wp:review-hint' => 1], $encryption['unknownChildNameCounts']);
        $t->same(1, $encryption['issueItemCount']);
        $t->same([
            'odf-manifest-encryption-multiple-algorithms' => 1,
            'odf-manifest-encryption-multiple-encryption-data' => 1,
            'odf-manifest-encryption-unknown-child' => 1,
        ], $encryption['issueCodeCounts']);
        $t->same(1, $itemsByPath['Pictures/hero.png']['encryptionRecordCount']);
        $t->same(['Blowfish CFB'], $itemsByPath['Pictures/hero.png']['algorithmNames']);
        $t->same(2, $itemsByPath['Pictures/secret.bin']['encryptionRecordCount']);
        $t->same(['AES-256-CBC', 'AES-128-CBC'], $itemsByPath['Pictures/secret.bin']['algorithmNames']);
        $t->same(['wp:review-hint'], $itemsByPath['Pictures/secret.bin']['unknownChildNames']);
        $t->same([
            'odf-manifest-encryption-multiple-algorithms',
            'odf-manifest-encryption-unknown-child',
            'odf-manifest-encryption-multiple-encryption-data',
        ], $itemsByPath['Pictures/secret.bin']['issueCodes']);
        $t->same(false, $itemsByPath['Pictures/secret.bin']['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $itemsByPath['Pictures/secret.bin']['byteExposurePolicy']);
        $t->same($encryption, $reviewEncryption);
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
    'reports compact ODT manifest entries missing media types without exposing bytes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $sidecarBytes = 'BINARYPAYLOAD';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="" manifest:full-path="Pictures/nameless.bin" manifest:size="' . strlen($sidecarBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/"/>',
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [['name' => 'Pictures/nameless.bin', 'data' => $sidecarBytes, 'compressionMethod' => 0]],
        ));
        $summary = $odt->summarize();
        $nameless = $odt->manifestEntry('Pictures/nameless.bin');
        $directory = $odt->manifestEntry('Configurations2/');
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $item) {
            $mediaByPath[$item['path']] = $item;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory']['parts'];

        $t->same('', $nameless['mediaType']);
        $t->same('', $nameless['mediaTypeBase']);
        $t->same(true, $nameless['missingMediaType']);
        $t->same(true, $nameless['exists']);
        $t->same(strlen($sidecarBytes), $nameless['storedByteLength']);
        $t->same(null, $nameless['byteLength']);
        $t->same(null, $nameless['crc32']);
        $t->same(false, $nameless['canExposeBytes']);
        $t->same('missing-media-type-bytes-blocked', $nameless['byteExposurePolicy']);
        $t->same(['odf-manifest-file-entry-missing-media-type'], $nameless['diagnostics']);

        $t->same(false, $directory['missingMediaType']);
        $t->same(true, $directory['isDirectory']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);
        $t->same(['odf-manifest-directory-entry'], $directory['diagnostics']);

        $t->same(['Pictures/hero.png', 'Pictures/nameless.bin'], array_column($summary['mediaParts'], 'path'));
        $t->same(false, $mediaByPath['Pictures/nameless.bin']['canExposeBytes']);
        $t->same('missing-media-type-bytes-blocked', $mediaByPath['Pictures/nameless.bin']['byteExposurePolicy']);
        $t->same(['odf-manifest-file-entry-missing-media-type'], $mediaByPath['Pictures/nameless.bin']['diagnostics']);
        $t->same(1, $summary['exposableMediaPartCount']);
        $t->same(0, $summary['missingMediaPartCount']);

        $t->same(1, $summary['manifestReview']['missingMediaTypeCount']);
        $t->same('Pictures/nameless.bin', $summary['manifestReview']['missingMediaTypeItems'][0]['path']);
        $t->same(2, $summary['manifestReview']['diagnosticCount']);
        $t->same([
            'odf-manifest-directory-entry' => 1,
            'odf-manifest-file-entry-missing-media-type' => 1,
        ], $summary['manifestReview']['diagnosticCodeCounts']);
        $t->same('odf-manifest-file-entry-missing-media-type', $summary['manifestReview']['diagnostics'][0]['code']);
        $t->same(false, $summary['manifestReview']['diagnostics'][0]['canExposeBytes']);
        $t->same(true, $reviewByPath['Pictures/nameless.bin']['missingMediaType']);
        $t->same(false, $reviewByPath['Pictures/nameless.bin']['canExposeBytes']);

        $t->same(true, $inventory['Pictures/nameless.bin']['manifestMissingMediaType']);
        $t->same(['odf-manifest-file-entry-missing-media-type'], $inventory['Pictures/nameless.bin']['manifestDiagnostics']);
        $t->same(false, $inventory['Pictures/nameless.bin']['canExposeBytes']);
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
    'summarizes compact ODT manifest declared size provenance for package review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $largeBytes = 'LARGE-ODT';
        $tieAlphaBytes = 'AAAA';
        $tieBetaBytes = 'BBBBBB';
        $smallBytes = str_repeat('s', 12);
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="0007"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Pictures/large.bin" manifest:size="4096"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/missing.png" manifest:size="2048"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Pictures/tie-alpha.bin" manifest:size="120"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Pictures/tie-beta.bin" manifest:size="120"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Pictures/small.bin" manifest:size="12"/>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Pictures/large.bin', 'data' => $largeBytes, 'compressionMethod' => 0],
                ['name' => 'Pictures/tie-alpha.bin', 'data' => $tieAlphaBytes, 'compressionMethod' => 0],
                ['name' => 'Pictures/tie-beta.bin', 'data' => $tieBetaBytes, 'compressionMethod' => 0],
                ['name' => 'Pictures/small.bin', 'data' => $smallBytes, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $review = $summary['manifestReview'];
        $inventory = $summary['packageInventory']['parts'];
        $itemsByPath = [];
        foreach ($review['items'] as $item) {
            $itemsByPath[$item['path']] = $item;
        }

        $t->same(10, $review['count']);
        $t->same(6, $review['declaredSizeItemCount']);
        $t->same(4096 + 2048 + 120 + 120 + 12 + 7, $review['declaredSize']);
        $t->same(3, $review['declaredSizeMismatchCount']);
        $t->same(['Pictures/hero.png', 'Pictures/large.bin', 'Pictures/missing.png', 'Pictures/tie-alpha.bin', 'Pictures/tie-beta.bin', 'Pictures/small.bin'], array_column($review['declaredSizeItems'], 'path'));
        $t->same(5, $review['largestDeclaredSizeItemLimit']);
        $t->same(5, $review['largestDeclaredSizeItemCount']);
        $t->same(['Pictures/large.bin', 'Pictures/missing.png', 'Pictures/tie-beta.bin', 'Pictures/tie-alpha.bin', 'Pictures/small.bin'], array_column($review['largestDeclaredSizeItems'], 'path'));
        $t->same([4096, 2048, 120, 120, 12], array_column($review['largestDeclaredSizeItems'], 'declaredSize'));
        $t->same([strlen($largeBytes), null, strlen($tieBetaBytes), strlen($tieAlphaBytes), strlen($smallBytes)], array_column($review['largestDeclaredSizeItems'], 'storedByteLength'));

        $t->same(7, $itemsByPath['Pictures/hero.png']['declaredSize']);
        $t->same(false, $itemsByPath['Pictures/hero.png']['declaredSizeMismatch']);
        $t->same(4096, $itemsByPath['Pictures/large.bin']['declaredSize']);
        $t->same(strlen($largeBytes), $itemsByPath['Pictures/large.bin']['storedByteLength']);
        $t->same(true, $itemsByPath['Pictures/large.bin']['declaredSizeMismatch']);
        $t->same(['odf-manifest-declared-size-mismatch'], $itemsByPath['Pictures/large.bin']['diagnostics']);
        $t->same(2048, $itemsByPath['Pictures/missing.png']['declaredSize']);
        $t->same(false, $itemsByPath['Pictures/missing.png']['exists']);
        $t->same(false, $itemsByPath['Pictures/missing.png']['declaredSizeMismatch']);
        $t->same('missing-package-part', $itemsByPath['Pictures/missing.png']['byteExposurePolicy']);
        $t->same(['odf-manifest-missing-package-part'], $itemsByPath['Pictures/missing.png']['diagnostics']);
        $t->same('Pictures/tie-beta.bin', $review['largestDeclaredSizeItems'][2]['path']);
        $t->same('Pictures/tie-alpha.bin', $review['largestDeclaredSizeItems'][3]['path']);
        $t->same(4096, $inventory['Pictures/large.bin']['manifestDeclaredSize']);
        $t->same(true, $inventory['Pictures/large.bin']['manifestDeclaredSizeMismatch']);
        $t->same(null, $inventory['Pictures/missing.png']['manifestDeclaredSize'] ?? null);
        $t->same(false, isset($inventory['Pictures/missing.png']));
        $t->same(1, $review['missingCount']);
        $t->same('Pictures/missing.png', $review['missingItems'][0]['path']);
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
        $t->same(true, $settings['configurationPackagePart']);
        $t->same('configuration-package-bytes-blocked', $settings['byteExposurePolicy']);
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
        $review = $summary['manifestReview'];
        $inventory = $summary['packageInventory']['parts'];

        $t->same($encoded, $decoded);
        $t->same('Pictures/source%20hero.png', $encoded['path']);
        $t->same('Pictures/source hero.png', $encoded['packagePath']);
        $t->same(true, $encoded['uriEncodedPackageReference']);
        $t->same(true, $encoded['exists']);
        $t->same(strlen($sourceBytes), $encoded['byteLength']);
        $t->same(strlen($sourceBytes), $encoded['storedByteLength']);
        $t->same(sprintf('%08x', crc32($sourceBytes)), $encoded['crc32']);
        $t->same(true, $encoded['canExposeBytes']);
        $t->same('package-bytes-exposable', $encoded['byteExposurePolicy']);

        $t->same(2, count($summary['mediaParts']));
        $t->same('Pictures/source hero.png', $mediaByPath['Pictures/source%20hero.png']['packagePath']);
        $t->same(true, $mediaByPath['Pictures/source%20hero.png']['uriEncodedPackageReference']);
        $t->same(strlen($sourceBytes), $mediaByPath['Pictures/source%20hero.png']['byteLength']);
        $t->same(1, $review['uriEncodedPackageReferenceCount']);
        $t->same('Pictures/source%20hero.png', $review['uriEncodedPackageReferenceItems'][0]['path']);
        $t->same('Pictures/source hero.png', $review['uriEncodedPackageReferenceItems'][0]['packagePath']);
        $t->same(true, $review['items'][5]['uriEncodedPackageReference']);
        $t->same(false, $review['items'][4]['uriEncodedPackageReference']);
        $t->same(true, $review['manifestFileEntryOrder'][5]['uriEncodedPackageReference']);
        $t->same(true, $inventory['Pictures/source hero.png']['manifestUriEncodedPackageReference']);
        $t->same('Pictures/source%20hero.png', $inventory['Pictures/source hero.png']['manifestPath']);
        $t->same('Pictures/source hero.png', $inventory['Pictures/source hero.png']['manifestPackagePath']);
        $t->same(0, $summary['undeclaredPackageEntryCount']);
        $t->same(0, $review['undeclaredPackageEntryCount']);
        $t->same('Pictures/source hero.png', $review['items'][5]['packagePath']);

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

        foreach (['%1F', '%7F'] as $encodedControl) {
            $encodedControlManifest = str_replace(
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
                '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/' . $encodedControl . 'secret.png"/>',
                $manifestXml
            );
            $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $encodedControlManifest)));
        }
    },
    'keeps compact ODT URI encoded manifest parts declared in package inventory' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
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
        $inventory = $odt->summarize()['packageInventory'];
        $inventoryPart = $inventory['parts']['Pictures/source hero.png'];

        $t->same(0, $inventory['undeclaredEntryCount']);
        $t->same('Pictures/source hero.png', $inventoryPart['path']);
        $t->same(['manifest-declared', 'media-resource'], $inventoryPart['roles']);
        $t->same(true, $inventoryPart['declaredInManifest']);
        $t->same(false, $inventoryPart['undeclared']);
        $t->same('Pictures/source%20hero.png', $inventoryPart['manifestPath']);
        $t->same('Pictures/source hero.png', $inventoryPart['manifestPackagePath']);
        $t->same('image/png', $inventoryPart['manifestMediaType']);
    },
    'summarizes compact ODT media resource sidecar role precedence' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $audioBytes = 'AUDIO-BYTES';
        $formPreviewBytes = 'FORM-PREVIEW';
        $galleryPreviewBytes = 'GALLERY-PREVIEW';
        $linkedPreviewBytes = 'LINKED-PREVIEW';
        $attachmentPreviewBytes = 'ATTACHMENT-PREVIEW';
        $templatePreviewBytes = 'TEMPLATE-PREVIEW';
        $dictionaryPreviewBytes = 'DICTIONARY-PREVIEW';

        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="audio/ogg" manifest:full-path="Media/narration.ogg" manifest:size="' . strlen($audioBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Forms/Review/preview.png" manifest:size="' . strlen($formPreviewBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Gallery/Theme/preview.png" manifest:size="' . strlen($galleryPreviewBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Links/cache/preview.png" manifest:size="' . strlen($linkedPreviewBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Attachments/Review/preview.png" manifest:size="' . strlen($attachmentPreviewBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Templates/Review/preview.png" manifest:size="' . strlen($templatePreviewBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Dictionaries/en_US/preview.png" manifest:size="' . strlen($dictionaryPreviewBytes) . '"/>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest, extraParts: [
            ['name' => 'Media/narration.ogg', 'data' => $audioBytes, 'compressionMethod' => 0],
            ['name' => 'Forms/Review/preview.png', 'data' => $formPreviewBytes, 'compressionMethod' => 0],
            ['name' => 'Gallery/Theme/preview.png', 'data' => $galleryPreviewBytes, 'compressionMethod' => 0],
            ['name' => 'Links/cache/preview.png', 'data' => $linkedPreviewBytes, 'compressionMethod' => 0],
            ['name' => 'Attachments/Review/preview.png', 'data' => $attachmentPreviewBytes, 'compressionMethod' => 0],
            ['name' => 'Templates/Review/preview.png', 'data' => $templatePreviewBytes, 'compressionMethod' => 0],
            ['name' => 'Dictionaries/en_US/preview.png', 'data' => $dictionaryPreviewBytes, 'compressionMethod' => 0],
        ]))->summarize();
        $mediaResources = $summary['manifestReview']['mediaResources'];
        $itemsByPart = [];
        foreach ($mediaResources['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }

        $t->same(['Pictures/hero.png', 'Media/narration.ogg'], array_column($summary['mediaParts'], 'packagePath'));
        $t->same(8, $mediaResources['manifestDeclaredCount']);
        $t->same(2, $mediaResources['mediaResourceCount']);
        $t->same(2, $mediaResources['mediaResourceExistingCount']);
        $t->same(0, $mediaResources['mediaResourceMissingCount']);
        $t->same(2, $mediaResources['mediaResourceCanExposeCount']);
        $t->same(8, $mediaResources['existingCount']);
        $t->same(0, $mediaResources['missingCount']);
        $t->same(['image' => 7, 'audio' => 1, 'video' => 0, 'other' => 0], $mediaResources['familyCounts']);
        $t->same([
            'audio/ogg' => 1,
            'image/png' => 7,
        ], $mediaResources['mediaTypeBaseCounts']);
        $t->same(0, $mediaResources['roleConflictCount']);
        $t->same(0, $mediaResources['resourceRoleConflictCount']);
        $t->same(6, $mediaResources['packageRolePrecedenceCount']);
        $t->same(['odf-media-resource-package-role-precedence' => 6], $mediaResources['issueCodeCounts']);

        $t->same(true, $itemsByPart['Pictures/hero.png']['mediaResource']);
        $t->same([], $itemsByPart['Pictures/hero.png']['packageRolePrecedence'] ?? []);
        $t->same(true, $itemsByPart['Media/narration.ogg']['mediaResource']);
        $t->same(['manifest-media-type', 'package-extension'], $itemsByPart['Media/narration.ogg']['roleSources']);
        $t->same([], $itemsByPart['Media/narration.ogg']['packageRolePrecedence'] ?? []);
        $t->same('package-bytes-exposable', $itemsByPart['Media/narration.ogg']['byteExposurePolicy']);

        $t->same(false, $itemsByPart['Forms/Review/preview.png']['mediaResource']);
        $t->same(['form-package'], $itemsByPart['Forms/Review/preview.png']['packageRolePrecedence']);
        $t->same('form-package-bytes-blocked', $itemsByPart['Forms/Review/preview.png']['byteExposurePolicy']);
        $t->same(false, $itemsByPart['Gallery/Theme/preview.png']['mediaResource']);
        $t->same(['gallery-package'], $itemsByPart['Gallery/Theme/preview.png']['packageRolePrecedence']);
        $t->same(false, $itemsByPart['Links/cache/preview.png']['mediaResource']);
        $t->same(['linked-resource-package'], $itemsByPart['Links/cache/preview.png']['packageRolePrecedence']);
        $t->same(false, $itemsByPart['Attachments/Review/preview.png']['mediaResource']);
        $t->same(['attachment-package'], $itemsByPart['Attachments/Review/preview.png']['packageRolePrecedence']);
        $t->same(false, $itemsByPart['Templates/Review/preview.png']['mediaResource']);
        $t->same(['template-package'], $itemsByPart['Templates/Review/preview.png']['packageRolePrecedence']);
        $t->same(false, $itemsByPart['Dictionaries/en_US/preview.png']['mediaResource']);
        $t->same(['dictionary-package'], $itemsByPart['Dictionaries/en_US/preview.png']['packageRolePrecedence']);
        $t->same('dictionary-package-bytes-blocked', $itemsByPart['Dictionaries/en_US/preview.png']['byteExposurePolicy']);

        $t->same([
            'Forms/Review/preview.png',
            'Gallery/Theme/preview.png',
            'Links/cache/preview.png',
            'Attachments/Review/preview.png',
            'Templates/Review/preview.png',
            'Dictionaries/en_US/preview.png',
        ], array_column($mediaResources['packageRolePrecedenceItems'], 'part'));
    },
    'preserves compact ODT raw ZIP entry name provenance in package inventory' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $decodedName = "Pictures/caf\xc3\xa9.png";
        $rawName = "Pictures/caf\x82.png";
        $legacyBytes = 'CAFEPNG';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/caf%C3%A9.png" manifest:size="' . strlen($legacyBytes) . '"/>',
            $manifestXml
        );
        $parts = [
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifest],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            [
                'name' => $decodedName,
                'rawName' => $rawName,
                'generalPurposeFlags' => 0,
                'data' => $legacyBytes,
                'compressionMethod' => 0,
            ],
        ];

        $summary = OpenDocumentPackage::fromPackage(
            $buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name'))
        )->summarize();
        $inventory = $summary['packageInventory'];
        $legacy = $inventory['parts'][$decodedName];
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $media) {
            $mediaByPath[$media['path']] = $media;
        }

        $t->same(1, $inventory['rawNameProvenanceEntryCount']);
        $t->same(1, $inventory['legacyEncodedNameEntryCount']);
        $t->same(0, $inventory['unicodePathExtraEntryCount']);
        $t->same(1, $inventory['decodedNameDiffersFromRawNameEntryCount']);
        $t->same($decodedName, $inventory['rawNameProvenanceEntries'][0]['path']);
        $t->same(bin2hex($rawName), $inventory['rawNameProvenanceEntries'][0]['rawNameHex']);
        $t->same('cp437', $inventory['rawNameProvenanceEntries'][0]['nameEncoding']);
        $t->same(false, $inventory['rawNameProvenanceEntries'][0]['rawNameMatchesDecodedName']);

        $t->same('Pictures/caf%C3%A9.png', $legacy['manifestPath']);
        $t->same($decodedName, $legacy['manifestPackagePath']);
        $t->same(bin2hex($rawName), $legacy['rawNameHex']);
        $t->same('cp437', $legacy['nameEncoding']);
        $t->same(false, $legacy['rawNameMatchesDecodedName']);
        $t->same(true, $legacy['usesLegacyNameEncoding']);
        $t->same(false, $legacy['usesUnicodePathExtraField']);
        $t->same(true, $legacy['hasRawNameProvenance']);
        $t->same(true, $legacy['declaredInManifest']);
        $t->same(['manifest-declared', 'media-resource'], $legacy['roles']);
        $t->same(strlen($legacyBytes), $legacy['byteLength']);
        $t->same(strlen($legacyBytes), $mediaByPath['Pictures/caf%C3%A9.png']['byteLength']);
        $t->same($decodedName, $mediaByPath['Pictures/caf%C3%A9.png']['packagePath']);
        $t->same(hash('sha256', $legacyBytes), $mediaByPath['Pictures/caf%C3%A9.png']['byteSha256']);
    },
    'preserves compact ODT ZIP timestamp provenance in package review handoff' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $timestampedBytes = 'TIMESTAMPEDPNG';
        $modifiedAt = 1780479017;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/timestamped.png" manifest:size="' . strlen($timestampedBytes) . '"/>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [[
                'name' => 'Pictures/timestamped.png',
                'data' => $timestampedBytes,
                'compressionMethod' => 0,
                'modifiedAt' => $modifiedAt,
            ]],
        ))->summarize();
        $review = $summary['manifestReview'];
        $inventory = $summary['packageInventory'];
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $media) {
            $mediaByPath[$media['path']] = $media;
        }
        $reviewByPath = [];
        foreach ($review['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }

        $media = $mediaByPath['Pictures/timestamped.png'];
        $part = $inventory['parts']['Pictures/timestamped.png'];
        $reviewItem = $reviewByPath['Pictures/timestamped.png'];

        $t->same($modifiedAt, $media['zipModifiedAt']);
        $t->same('extended-timestamp', $media['zipTimestampSource']);
        $t->same(true, $media['zipHasDosTimestamp']);
        $t->same(true, $media['zipIsDosTimestampValid']);
        $t->same($modifiedAt, $media['zipExtendedModifiedAt']);
        $t->same($modifiedAt, $media['zipLocalModifiedAt']);
        $t->same('extended-timestamp', $media['zipLocalTimestampSource']);
        $t->same([], $media['zipTimestampIssues']);

        $t->same(1, $review['zipTimestampEntryCount']);
        $t->same(['extended-timestamp' => 1], $review['zipTimestampSourceCounts']);
        $t->same(0, $review['zipInvalidDosTimestampEntryCount']);
        $t->same('Pictures/timestamped.png', $review['zipTimestampItems'][0]['path']);
        $t->same($modifiedAt, $reviewItem['zipModifiedAt']);
        $t->same('extended-timestamp', $reviewItem['zipTimestampSource']);
        $t->same($modifiedAt, $reviewItem['zipLocalModifiedAt']);

        $t->same(1, $inventory['zipTimestampEntryCount']);
        $t->same(1, $inventory['zipDosTimestampEntryCount']);
        $t->same(1, $inventory['zipExtendedTimestampEntryCount']);
        $t->same(0, $inventory['zipInvalidDosTimestampEntryCount']);
        $t->same($modifiedAt, $part['zipModifiedAt']);
        $t->same('extended-timestamp', $part['zipTimestampSource']);
        $t->same($modifiedAt, $part['zipLocalModifiedAt']);
        $t->same('extended-timestamp', $part['zipLocalTimestampSource']);
        $t->same([], $part['zipTimestampIssues']);
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
        $t->same('Pictures/hero.png', $inventory['Pictures/hero.png']['manifestPackagePath']);
        $t->same('Pictures/hero.png', $inventory['Pictures/hero.png']['manifestPathReference']);
        $t->same('?cache=1#review', $inventory['Pictures/hero.png']['manifestPathSuffix']);
        $t->same('cache=1', $inventory['Pictures/hero.png']['manifestPathQuery']);
        $t->same('review', $inventory['Pictures/hero.png']['manifestPathFragment']);
        $t->same('Pictures/source hero.png', $inventory['Pictures/source hero.png']['manifestPackagePath']);
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
        $t->same(true, $summary['undeclaredPackageEntries'][0]['thumbnailPackagePart']);
        $t->same('package-thumbnail-bytes-blocked', $summary['undeclaredPackageEntries'][0]['byteExposurePolicy']);
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
    'keeps compact ODT manifest directory package entries out of media handoff' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="" manifest:full-path="Pictures/"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0]],
        ))->summarize();
        $inventory = $summary['packageInventory'];
        $directory = $inventory['parts']['Pictures/'];

        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same(1, $summary['exposableMediaPartCount']);
        $t->same(1, $inventory['mediaResourcePartCount']);
        $t->same(1, $inventory['packageDirectoryCount']);
        $t->same(['zip-directory', 'manifest-declared'], $directory['roles']);
        $t->same(true, $directory['declaredInManifest']);
        $t->same(false, $directory['undeclared']);
        $t->same(true, $summary['manifestReview']['directoryItems'][0]['isDirectory']);
        $t->same('Pictures/', $summary['manifestReview']['directoryItems'][0]['path']);
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
        $t->same(1, $inventory['scriptPackagePartCount']);
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
            'script-package' => 1,
            'undeclared-package-entry' => 2,
        ], $inventory['roleCounts']);
        $t->same([
            'package-thumbnail' => 1,
            'script-package' => 1,
            'undeclared-package-entry' => 2,
        ], $inventory['undeclaredRoleCounts']);
        $t->same(['odf-settings', 'manifest-declared'], $inventory['parts']['settings.xml']['roles']);
        $t->same(['package-signature', 'manifest-declared'], $inventory['parts']['META-INF/documentsignatures.xml']['roles']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $inventory['parts']['Thumbnails/thumbnail.png']['roles']);
        $t->same(['script-package', 'undeclared-package-entry'], $inventory['parts']['Scripts/review/basic.xba']['roles']);
        $t->same(1, $summary['packageSignatures']['count']);
        $t->same(1, $summary['packageThumbnails']['count']);
    },
    'summarizes compact ODT package inventory byte buckets for review handoff' => static function (TestRunner $t) use (
        $buildZipPackageWithCentralDirectoryOrder,
        $manifestXml,
        $contentXml,
        $stylesXml,
        $metaXml
    ): void {
        $heroBytes = 'PNGDATA';
        $audioBytes = 'AUDIO-REVIEW-BYTES';
        $noteBytes = 'private review note';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="' . strlen($heroBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="audio/ogg" manifest:full-path="Media/theme.ogg" manifest:size="' . strlen($audioBytes) . '"/>',
            $manifestXml
        );

        $package = $buildZipPackageWithCentralDirectoryOrder([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => $heroBytes, 'compressionMethod' => 0],
            ['name' => 'Media/theme.ogg', 'data' => $audioBytes, 'compressionMethod' => 12],
            ['name' => 'Notes/private.txt', 'data' => $noteBytes, 'compressionMethod' => 0],
        ], [
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Media/theme.ogg',
            'Notes/private.txt',
        ]);

        $summary = OpenDocumentPackage::fromPackage($package)->summarize();
        $inventory = $summary['packageInventory'];
        $contentCompressedBytes = strlen(gzdeflate($contentXml));
        $stylesCompressedBytes = strlen(gzdeflate($stylesXml));
        $manifestDeclaredBytes = strlen($contentXml)
            + strlen($stylesXml)
            + strlen($metaXml)
            + strlen($heroBytes)
            + strlen($audioBytes);
        $manifestDeclaredCompressedBytes = $contentCompressedBytes
            + $stylesCompressedBytes
            + strlen($metaXml)
            + strlen($heroBytes)
            + strlen($audioBytes);
        $totalBytes = strlen(OpenDocumentPackage::TEXT_MIMETYPE)
            + strlen($manifest)
            + strlen($contentXml)
            + strlen($stylesXml)
            + strlen($metaXml)
            + strlen($heroBytes)
            + strlen($audioBytes)
            + strlen($noteBytes);
        $totalCompressedBytes = strlen(OpenDocumentPackage::TEXT_MIMETYPE)
            + strlen($manifest)
            + $contentCompressedBytes
            + $stylesCompressedBytes
            + strlen($metaXml)
            + strlen($heroBytes)
            + strlen($audioBytes)
            + strlen($noteBytes);
        $exposableBytes = strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + strlen($heroBytes);
        $exposableCompressedBytes = $contentCompressedBytes + $stylesCompressedBytes + strlen($metaXml) + strlen($heroBytes);
        $blockedBytes = $totalBytes - $exposableBytes;
        $blockedCompressedBytes = $totalCompressedBytes - $exposableCompressedBytes;

        $t->same(8, $inventory['entryCount']);
        $t->same(5, $inventory['manifestDeclaredPartCount']);
        $t->same(1, $inventory['undeclaredEntryCount']);
        $t->same(1, $inventory['unsupportedCompressionMethodCount']);
        $t->same(4, $inventory['exposableEntryCount']);
        $t->same(4, $inventory['blockedEntryCount']);
        $t->same($totalBytes, $inventory['totalByteLength']);
        $t->same($totalCompressedBytes, $inventory['totalCompressedByteLength']);
        $t->same($exposableBytes, $inventory['exposableByteLength']);
        $t->same($exposableCompressedBytes, $inventory['exposableCompressedByteLength']);
        $t->same($blockedBytes, $inventory['blockedByteLength']);
        $t->same($blockedCompressedBytes, $inventory['blockedCompressedByteLength']);
        $t->same(strlen($audioBytes), $inventory['unsupportedCompressionByteLength']);
        $t->same(strlen($audioBytes), $inventory['unsupportedCompressionCompressedByteLength']);
        $t->same('odf-package-inventory-metadata-only', $inventory['byteExposurePolicy']);
        $t->same(false, $inventory['canExposeBytes']);
        $t->same($manifestDeclaredBytes, $inventory['roleByteLengths']['manifest-declared']);
        $t->same($manifestDeclaredCompressedBytes, $inventory['roleCompressedByteLengths']['manifest-declared']);
        $t->same(strlen($heroBytes) + strlen($audioBytes), $inventory['roleByteLengths']['media-resource']);
        $t->same(strlen($heroBytes) + strlen($audioBytes), $inventory['roleCompressedByteLengths']['media-resource']);
        $t->same(strlen($noteBytes), $inventory['roleByteLengths']['undeclared-package-entry']);
        $t->same(strlen($noteBytes), $inventory['roleCompressedByteLengths']['undeclared-package-entry']);
        $t->same(['Media/theme.ogg'], $inventory['unsupportedCompressionPartNames']);
        $t->same(false, $inventory['parts']['Media/theme.ogg']['canExposeBytes']);
        $t->same('unsupported', $inventory['parts']['Media/theme.ogg']['compressionMethodName']);
        $t->same('unsupported-compression-bytes-blocked', $inventory['parts']['Media/theme.ogg']['byteExposurePolicy']);
        $t->same(['undeclared-package-entry'], $inventory['parts']['Notes/private.txt']['roles']);
        $t->same(['Pictures/hero.png', 'Media/theme.ogg'], array_column($summary['mediaParts'], 'path'));
    },
    'maps compact ODT package inventory byte exposure policy matrix' => static function (TestRunner $t) use (
        $buildZipPackageWithCentralDirectoryOrder,
        $manifestXml,
        $contentXml,
        $stylesXml,
        $metaXml
    ): void {
        $heroBytes = 'PNGDATA';
        $secretBytes = 'SECRET-PNG-BYTES';
        $audioBytes = 'AUDIO-REVIEW-BYTES';
        $basicModuleXml = '<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Main' . "\n" . 'End Sub</script:module>';
        $signatureXml = '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>';
        $configurationXml = '<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="Review"/>';
        $fontBytes = 'WOFF2-FONT-BYTES';
        $rdfXml = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/"><rdf:Description rdf:about="content.xml"><dc:title>Review body</dc:title></rdf:Description></rdf:RDF>';
        $replacementBytes = 'REPLACEMENT-PNG-BYTES';
        $noteBytes = 'private review note';
        $matrixEntries = <<<'XML'
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/secret.png" manifest:size="16">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="secret-checksum"/>
  </manifest:file-entry>
XML;
        $matrixEntries .= '  <manifest:file-entry manifest:media-type="audio/ogg" manifest:full-path="Media/theme.ogg" manifest:size="' . strlen($audioBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Basic/Standard/Module1.xml" manifest:size="' . strlen($basicModuleXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="META-INF/documentsignatures.xml" manifest:size="' . strlen($signatureXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/accelerator/current.xml" manifest:size="' . strlen($configurationXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="font/woff2" manifest:full-path="Fonts/ReviewSans.woff2" manifest:size="' . strlen($fontBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="application/rdf+xml" manifest:full-path="manifest.rdf" manifest:size="' . strlen($rdfXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="ObjectReplacements/preview.png" manifest:size="' . strlen($replacementBytes) . '"/>' . "\n";
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            rtrim($matrixEntries),
            $manifestXml
        );

        $package = $buildZipPackageWithCentralDirectoryOrder([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => $heroBytes, 'compressionMethod' => 0],
            ['name' => 'Pictures/secret.png', 'data' => $secretBytes, 'compressionMethod' => 0],
            ['name' => 'Media/theme.ogg', 'data' => $audioBytes, 'compressionMethod' => 12],
            ['name' => 'Basic/Standard/Module1.xml', 'data' => $basicModuleXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml, 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationXml, 'compressionMethod' => 0],
            ['name' => 'Fonts/ReviewSans.woff2', 'data' => $fontBytes, 'compressionMethod' => 0],
            ['name' => 'manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
            ['name' => 'ObjectReplacements/preview.png', 'data' => $replacementBytes, 'compressionMethod' => 0],
            ['name' => 'Notes/private.txt', 'data' => $noteBytes, 'compressionMethod' => 0],
        ], [
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/secret.png',
            'Media/theme.ogg',
            'Basic/Standard/Module1.xml',
            'META-INF/documentsignatures.xml',
            'Configurations2/accelerator/current.xml',
            'Fonts/ReviewSans.woff2',
            'manifest.rdf',
            'ObjectReplacements/preview.png',
            'Notes/private.txt',
        ]);

        $summary = OpenDocumentPackage::fromPackage($package)->summarize();
        $inventory = $summary['packageInventory'];
        $parts = $inventory['parts'];
        $policyItemsByPath = [];
        foreach ($inventory['byteExposurePolicyItems'] as $item) {
            $policyItemsByPath[$item['path']] = $item;
        }
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $item) {
            $mediaByPath[$item['path']] = $item;
        }

        $packageBytesExposableBytes = strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + strlen($heroBytes);
        $packageBytesExposableCompressedBytes = strlen(gzdeflate($contentXml)) + strlen(gzdeflate($stylesXml)) + strlen($metaXml) + strlen($heroBytes);
        $expectedPolicyByteLengths = [
            'configuration-package-bytes-blocked' => strlen($configurationXml),
            'encrypted-resource-bytes-blocked' => strlen($secretBytes),
            'font-package-bytes-blocked' => strlen($fontBytes),
            'object-replacement-package-bytes-blocked' => strlen($replacementBytes),
            'package-bytes-exposable' => $packageBytesExposableBytes,
            'rdf-metadata-bytes-blocked' => strlen($rdfXml),
            'script-package-bytes-blocked' => strlen($basicModuleXml),
            'signature-package-bytes-blocked' => strlen($signatureXml),
            'undeclared-package-entry-no-bytes' => strlen($noteBytes),
            'unsupported-compression-bytes-blocked' => strlen($audioBytes),
        ];
        $expectedPolicyCompressedByteLengths = $expectedPolicyByteLengths;
        $expectedPolicyCompressedByteLengths['package-bytes-exposable'] = $packageBytesExposableCompressedBytes;

        $t->same([
            'configuration-package-bytes-blocked' => 1,
            'encrypted-resource-bytes-blocked' => 1,
            'font-package-bytes-blocked' => 1,
            'object-replacement-package-bytes-blocked' => 1,
            'package-bytes-exposable' => 4,
            'rdf-metadata-bytes-blocked' => 1,
            'script-package-bytes-blocked' => 1,
            'signature-package-bytes-blocked' => 1,
            'undeclared-package-entry-no-bytes' => 1,
            'unsupported-compression-bytes-blocked' => 1,
        ], $inventory['byteExposurePolicyCounts']);
        $t->same(13, $inventory['byteExposurePolicyItemCount']);
        $t->same($expectedPolicyByteLengths, $inventory['byteExposurePolicyByteLengths']);
        $t->same($expectedPolicyCompressedByteLengths, $inventory['byteExposurePolicyCompressedByteLengths']);
        $t->same([
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/secret.png',
            'Media/theme.ogg',
            'Basic/Standard/Module1.xml',
            'META-INF/documentsignatures.xml',
            'Configurations2/accelerator/current.xml',
            'Fonts/ReviewSans.woff2',
            'manifest.rdf',
            'ObjectReplacements/preview.png',
            'Notes/private.txt',
        ], array_column($inventory['byteExposurePolicyItems'], 'path'));
        $t->same(1, $inventory['undeclaredEntryCount']);
        $t->same(['Notes/private.txt'], array_column($inventory['undeclaredEntries'], 'path'));

        $expectations = [
            'Pictures/hero.png' => ['package-bytes-exposable', ['manifest-declared', 'media-resource'], true, true, false],
            'Pictures/secret.png' => ['encrypted-resource-bytes-blocked', ['manifest-declared', 'media-resource'], false, true, false],
            'Media/theme.ogg' => ['unsupported-compression-bytes-blocked', ['manifest-declared', 'media-resource'], false, true, false],
            'Basic/Standard/Module1.xml' => ['script-package-bytes-blocked', ['script-package', 'manifest-declared'], false, true, false],
            'META-INF/documentsignatures.xml' => ['signature-package-bytes-blocked', ['package-signature', 'manifest-declared'], false, true, false],
            'Configurations2/accelerator/current.xml' => ['configuration-package-bytes-blocked', ['configuration-package', 'manifest-declared'], false, true, false],
            'Fonts/ReviewSans.woff2' => ['font-package-bytes-blocked', ['font-package', 'manifest-declared'], false, true, false],
            'manifest.rdf' => ['rdf-metadata-bytes-blocked', ['rdf-metadata', 'manifest-declared'], false, true, false],
            'ObjectReplacements/preview.png' => ['object-replacement-package-bytes-blocked', ['object-replacement', 'manifest-declared'], false, true, false],
            'Notes/private.txt' => ['undeclared-package-entry-no-bytes', ['undeclared-package-entry'], false, false, true],
        ];
        foreach ($expectations as $path => [$policy, $roles, $canExposeBytes, $declared, $undeclared]) {
            $t->same($roles, $parts[$path]['roles'], $path . ' roles');
            $t->same($policy, $parts[$path]['byteExposurePolicy'], $path . ' policy');
            $t->same($canExposeBytes, $parts[$path]['canExposeBytes'], $path . ' byte exposure');
            $t->same($declared, $parts[$path]['declaredInManifest'], $path . ' declaration state');
            $t->same($undeclared, $parts[$path]['undeclared'], $path . ' undeclared state');
            $t->same($policy, $policyItemsByPath[$path]['byteExposurePolicy'], $path . ' policy item');
        }

        $t->same(['Pictures/hero.png', 'Pictures/secret.png', 'Media/theme.ogg'], array_keys($mediaByPath));
        $t->same('package-bytes-exposable', $mediaByPath['Pictures/hero.png']['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $mediaByPath['Pictures/secret.png']['byteExposurePolicy']);
        $t->same('unsupported-compression-bytes-blocked', $mediaByPath['Media/theme.ogg']['byteExposurePolicy']);
        $t->same(hash('sha256', $heroBytes), $parts['Pictures/hero.png']['byteSha256']);
        $t->same(null, $parts['Pictures/secret.png']['byteSha256']);
        $t->same(null, $parts['Media/theme.ogg']['byteSha256']);
        $t->same(sprintf('%08x', crc32($audioBytes)), $parts['Media/theme.ogg']['crc32']);
        $t->same(sprintf('%08x', crc32($noteBytes)), $parts['Notes/private.txt']['crc32']);
        $t->same(1, $inventory['roleCounts']['script-package']);
        $t->same(1, $inventory['roleCounts']['package-signature']);
        $t->same(1, $inventory['packageSignaturePartCount']);
        $t->same(1, $inventory['roleCounts']['configuration-package']);
        $t->same(1, $inventory['roleCounts']['font-package']);
        $t->same(1, $inventory['roleCounts']['rdf-metadata']);
        $t->same(1, $inventory['roleCounts']['object-replacement']);
        $t->same(1, $inventory['undeclaredRoleCounts']['undeclared-package-entry']);
        $t->same(['Pictures/secret.png'], $summary['encryptedParts']);
        $t->same(1, $summary['packageScripts']['count']);
        $t->same(1, $summary['packageConfigurations']['count']);
        $t->same(1, $summary['packageFonts']['count']);
        $t->same(1, $summary['packageObjectReplacements']['count']);
        $t->same(1, $summary['rdfMetadata']['parsedPartCount']);
    },
    'maps compact ODT manifest media family review matrix' => static function (TestRunner $t) use (
        $buildOdtPackage,
        $manifestXml
    ): void {
        $audioBytes = 'AUDIO-REVIEW';
        $videoBytes = 'VIDEO-REVIEW';
        $genericImageBytes = 'GENERIC-PNG';
        $genericAudioBytes = 'GENERIC-AUDIO';
        $scriptBytes = 'Sub Main' . "\n" . 'End Sub';
        $configurationBytes = '<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="Review"/>';
        $fontBytes = 'WOFF2-FONT';
        $rdfBytes = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/>';
        $replacementBytes = 'PREVIEWPNG';
        $binaryBytes = 'BINARY-DATA';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="audio/ogg" manifest:full-path="Media/narration.ogg" manifest:size="' . strlen($audioBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="video/mp4" manifest:full-path="Media/clip.mp4" manifest:size="' . strlen($videoBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Pictures/generic.png" manifest:size="' . strlen($genericImageBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Media/generic.ogg" manifest:size="' . strlen($genericAudioBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Basic/Standard/Review.xml" manifest:size="' . strlen($scriptBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/accelerator/current.xml" manifest:size="' . strlen($configurationBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="font/woff2" manifest:full-path="Fonts/ReviewSans.woff2" manifest:size="' . strlen($fontBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="application/rdf+xml" manifest:full-path="manifest.rdf" manifest:size="' . strlen($rdfBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="ObjectReplacements/preview.png" manifest:size="' . strlen($replacementBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.chart" manifest:full-path="Object%20Chart/"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Payloads/review.bin" manifest:size="' . strlen($binaryBytes) . '"/>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Media/narration.ogg', 'data' => $audioBytes, 'compressionMethod' => 0],
                ['name' => 'Media/clip.mp4', 'data' => $videoBytes, 'compressionMethod' => 0],
                ['name' => 'Pictures/generic.png', 'data' => $genericImageBytes, 'compressionMethod' => 0],
                ['name' => 'Media/generic.ogg', 'data' => $genericAudioBytes, 'compressionMethod' => 0],
                ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptBytes, 'compressionMethod' => 0],
                ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationBytes, 'compressionMethod' => 0],
                ['name' => 'Fonts/ReviewSans.woff2', 'data' => $fontBytes, 'compressionMethod' => 0],
                ['name' => 'manifest.rdf', 'data' => $rdfBytes, 'compressionMethod' => 0],
                ['name' => 'ObjectReplacements/preview.png', 'data' => $replacementBytes, 'compressionMethod' => 0],
                ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Payloads/review.bin', 'data' => $binaryBytes, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $review = $summary['manifestReview'];
        $inventory = $summary['packageInventory'];
        $reviewByPath = [];
        foreach ($review['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $orderByPath = [];
        foreach ($review['manifestFileEntryOrder'] as $item) {
            $orderByPath[$item['path']] = $item;
        }
        $familyByPath = [];
        foreach ($review['manifestMediaFamilyItems'] as $item) {
            $familyByPath[$item['path']] = $item['manifestMediaFamily'];
        }
        $mediaByPath = [];
        foreach ($summary['mediaParts'] as $media) {
            $mediaByPath[$media['path']] = $media;
        }

        $t->same([
            'audio' => 2,
            'binary' => 1,
            'configuration' => 1,
            'font' => 1,
            'image' => 2,
            'object-replacement' => 1,
            'opendocument-object-package' => 1,
            'opendocument-text-package' => 1,
            'rdf' => 1,
            'script' => 1,
            'video' => 1,
            'xml' => 3,
        ], $review['manifestMediaFamilyCounts']);
        $t->same(16, count($review['manifestMediaFamilyItems']));
        $t->same('opendocument-text-package', $familyByPath['/']);
        $t->same('xml', $familyByPath['content.xml']);
        $t->same('image', $familyByPath['Pictures/hero.png']);
        $t->same('audio', $familyByPath['Media/narration.ogg']);
        $t->same('video', $familyByPath['Media/clip.mp4']);
        $t->same('image', $familyByPath['Pictures/generic.png']);
        $t->same('audio', $familyByPath['Media/generic.ogg']);
        $t->same('script', $familyByPath['Basic/Standard/Review.xml']);
        $t->same('configuration', $familyByPath['Configurations2/accelerator/current.xml']);
        $t->same('font', $familyByPath['Fonts/ReviewSans.woff2']);
        $t->same('rdf', $familyByPath['manifest.rdf']);
        $t->same('object-replacement', $familyByPath['ObjectReplacements/preview.png']);
        $t->same('opendocument-object-package', $familyByPath['Object%20Chart/']);
        $t->same('binary', $familyByPath['Payloads/review.bin']);

        $t->same(strlen($audioBytes) + strlen($genericAudioBytes), $review['manifestMediaFamilyByteLengths']['audio']);
        $t->same(strlen('PNGDATA') + strlen($genericImageBytes), $review['manifestMediaFamilyByteLengths']['image']);
        $t->same(strlen($videoBytes), $review['manifestMediaFamilyByteLengths']['video']);
        $t->same(strlen($scriptBytes), $review['manifestMediaFamilyByteLengths']['script']);
        $t->same(strlen($configurationBytes), $review['manifestMediaFamilyByteLengths']['configuration']);
        $t->same(strlen($fontBytes), $review['manifestMediaFamilyByteLengths']['font']);
        $t->same(strlen($rdfBytes), $review['manifestMediaFamilyByteLengths']['rdf']);
        $t->same(strlen($replacementBytes), $review['manifestMediaFamilyByteLengths']['object-replacement']);
        $t->same(0, $review['manifestMediaFamilyByteLengths']['opendocument-object-package'] ?? 0);
        $t->same(strlen($binaryBytes), $review['manifestMediaFamilyByteLengths']['binary']);

        $t->same('audio', $mediaByPath['Media/narration.ogg']['manifestMediaFamily']);
        $t->same('video', $mediaByPath['Media/clip.mp4']['manifestMediaFamily']);
        $t->same('image', $mediaByPath['Pictures/generic.png']['manifestMediaFamily']);
        $t->same('audio', $mediaByPath['Media/generic.ogg']['manifestMediaFamily']);
        $t->same('script', $reviewByPath['Basic/Standard/Review.xml']['manifestMediaFamily']);
        $t->same('configuration', $reviewByPath['Configurations2/accelerator/current.xml']['manifestMediaFamily']);
        $t->same('font', $reviewByPath['Fonts/ReviewSans.woff2']['manifestMediaFamily']);
        $t->same('rdf', $reviewByPath['manifest.rdf']['manifestMediaFamily']);
        $t->same('object-replacement', $reviewByPath['ObjectReplacements/preview.png']['manifestMediaFamily']);
        $t->same('opendocument-object-package', $reviewByPath['Object%20Chart/']['manifestMediaFamily']);
        $t->same('binary', $reviewByPath['Payloads/review.bin']['manifestMediaFamily']);
        $t->same(true, $orderByPath['Basic/Standard/Review.xml']['scriptPackagePart']);
        $t->same(false, $orderByPath['Basic/Standard/Review.xml']['configurationPackagePart']);
        $t->same(false, $orderByPath['Configurations2/accelerator/current.xml']['scriptPackagePart']);
        $t->same(true, $orderByPath['Configurations2/accelerator/current.xml']['configurationPackagePart']);

        $t->same(15, $inventory['manifestDeclaredPartCount']);
        $t->same([
            'audio' => 2,
            'binary' => 1,
            'configuration' => 1,
            'font' => 1,
            'image' => 2,
            'object-replacement' => 1,
            'opendocument-object-package' => 1,
            'rdf' => 1,
            'script' => 1,
            'video' => 1,
            'xml' => 3,
        ], $inventory['manifestMediaFamilyCounts']);
        $t->same('audio', $inventory['parts']['Media/narration.ogg']['manifestMediaFamily']);
        $t->same('video', $inventory['parts']['Media/clip.mp4']['manifestMediaFamily']);
        $t->same('image', $inventory['parts']['Pictures/generic.png']['manifestMediaFamily']);
        $t->same('audio', $inventory['parts']['Media/generic.ogg']['manifestMediaFamily']);
        $t->same(['manifest-declared', 'media-resource'], $inventory['parts']['Media/generic.ogg']['roles']);
        $t->same('script', $inventory['parts']['Basic/Standard/Review.xml']['manifestMediaFamily']);
        $t->same('configuration', $inventory['parts']['Configurations2/accelerator/current.xml']['manifestMediaFamily']);
        $t->same('font', $inventory['parts']['Fonts/ReviewSans.woff2']['manifestMediaFamily']);
        $t->same('rdf', $inventory['parts']['manifest.rdf']['manifestMediaFamily']);
        $t->same('object-replacement', $inventory['parts']['ObjectReplacements/preview.png']['manifestMediaFamily']);
        $t->same('opendocument-object-package', $inventory['parts']['Object Chart/']['manifestMediaFamily']);
        $t->same('binary', $inventory['parts']['Payloads/review.bin']['manifestMediaFamily']);
    },
    'preserves compact ODT exposable package SHA-256 provenance for review handoff' => static function (TestRunner $t) use (
        $buildZipPackageWithCentralDirectoryOrder,
        $manifestXml,
        $contentXml,
        $stylesXml,
        $metaXml
    ): void {
        $reviewImage = 'REVIEW-PNG-BYTES';
        $secretImage = 'SECRET-PNG-BYTES';
        $audioBytes = 'AUDIO-BYTES';
        $privateNote = 'private note';
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/secret.png" manifest:size="16">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="secret-checksum"/>
  </manifest:file-entry>
XML;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/review.png" manifest:size="' . strlen($reviewImage) . '"/>'
            . $encryptedEntry
            . '<manifest:file-entry manifest:media-type="audio/ogg" manifest:full-path="Media/theme.ogg" manifest:size="' . strlen($audioBytes) . '"/>',
            $manifestXml
        );

        $package = $buildZipPackageWithCentralDirectoryOrder([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/review.png', 'data' => $reviewImage, 'compressionMethod' => 8],
            ['name' => 'Pictures/secret.png', 'data' => $secretImage, 'compressionMethod' => 0],
            ['name' => 'Media/theme.ogg', 'data' => $audioBytes, 'compressionMethod' => 12],
            ['name' => 'Notes/private.txt', 'data' => $privateNote, 'compressionMethod' => 0],
        ], [
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/review.png',
            'Pictures/secret.png',
            'Media/theme.ogg',
            'Notes/private.txt',
        ]);

        $odt = OpenDocumentPackage::fromPackage($package);
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

        $t->same(hash('sha256', 'PNGDATA'), $odt->manifestEntry('Pictures/hero.png')['byteSha256']);
        $t->same(hash('sha256', $reviewImage), $odt->manifestEntry('Pictures/review.png')['byteSha256']);
        $t->same(null, $odt->manifestEntry('Pictures/secret.png')['byteSha256']);
        $t->same(null, $odt->manifestEntry('Media/theme.ogg')['byteSha256']);

        $t->same(hash('sha256', $reviewImage), $mediaByPath['Pictures/review.png']['byteSha256']);
        $t->same(null, $mediaByPath['Pictures/secret.png']['byteSha256']);
        $t->same(null, $mediaByPath['Media/theme.ogg']['byteSha256']);

        $t->same(hash('sha256', $contentXml), $reviewByPath['content.xml']['byteSha256']);
        $t->same(hash('sha256', $reviewImage), $reviewByPath['Pictures/review.png']['byteSha256']);
        $t->same(null, $reviewByPath['Pictures/secret.png']['byteSha256']);
        $t->same(null, $reviewByPath['Media/theme.ogg']['byteSha256']);

        $t->same(hash('sha256', $contentXml), $inventory['content.xml']['byteSha256']);
        $t->same(hash('sha256', $reviewImage), $inventory['Pictures/review.png']['byteSha256']);
        $t->same(null, $inventory['Pictures/secret.png']['byteSha256']);
        $t->same(null, $inventory['Media/theme.ogg']['byteSha256']);
        $t->same(null, $inventory['Notes/private.txt']['byteSha256']);
    },
    'preflights deterministic compact ODT package identity metadata' => static function (TestRunner $t) use (
        $buildZipPackageWithCentralDirectoryOrder,
        $manifestXml,
        $contentXml,
        $stylesXml,
        $metaXml
    ): void {
        $secretImage = 'SECRET-PNG-BYTES';
        $privateNote = 'private note';
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/secret.png" manifest:size="16">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="secret-checksum"/>
  </manifest:file-entry>
XML;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . $encryptedEntry
            . '<manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="Pictures/missing.jpg" manifest:size="12"/>',
            $manifestXml
        );
        $parts = [
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/secret.png', 'data' => $secretImage, 'compressionMethod' => 0],
            ['name' => 'Notes/private.txt', 'data' => $privateNote, 'compressionMethod' => 0],
        ];

        $package = $buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name'));
        $summary = OpenDocumentPackage::fromPackage($package)->summarize();
        $identity = $summary['packageIdentity'];
        $repeatIdentity = OpenDocumentPackage::fromPackage($package)->summarize()['packageIdentity'];
        $changedParts = $parts;
        $changedParts[7]['data'] = 'private note changed';
        $changedIdentity = OpenDocumentPackage::fromPackage(
            $buildZipPackageWithCentralDirectoryOrder($changedParts, array_column($changedParts, 'name'))
        )->summarize()['packageIdentity'];

        $t->same(1, $identity['identityVersion']);
        $t->same('opendocument-text', $identity['packageType']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $identity['mimetype']);
        $t->same('1.3', $identity['manifestVersion']);
        $t->same(7, $identity['manifestEntryCount']);
        $t->same(8, $identity['packageEntryCount']);
        $t->same(false, $identity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $identity['byteExposurePolicy']);
        $t->same(64, strlen($identity['identitySha256']));
        $t->true($identity['identityPayloadByteLength'] > 0);
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->same($identity['identityPayloadByteLength'], $repeatIdentity['identityPayloadByteLength']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);

        $t->same([
            '/',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/secret.png',
            'Pictures/missing.jpg',
        ], $identity['manifestPaths']);
        $t->same([
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/secret.png',
            'Notes/private.txt',
        ], $identity['packagePaths']);
        $t->same([
            'encrypted-resource-bytes-blocked' => 1,
            'package-bytes-exposable' => 4,
            'undeclared-package-entry-no-bytes' => 1,
        ], $identity['byteExposurePolicyCounts']);
        $t->same([
            'image' => 2,
            'xml' => 3,
        ], $identity['manifestMediaFamilyCounts']);
        $t->same(1, $identity['undeclaredEntryCount']);
        $t->same(1, $identity['encryptedCount']);
        $t->same(0, $identity['unsupportedCompressionMethodCount']);

        $secretManifest = $identity['manifestEntries'][5];
        $missingManifest = $identity['manifestEntries'][6];
        $privatePackageEntry = $identity['packageEntries'][7];

        $t->same('Pictures/secret.png', $secretManifest['path']);
        $t->same(true, $secretManifest['encrypted']);
        $t->same(false, $secretManifest['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $secretManifest['byteExposurePolicy']);
        $t->same(null, $secretManifest['byteSha256'] ?? null);
        $t->same(['odf-manifest-encrypted-package-part'], $secretManifest['diagnostics']);

        $t->same('Pictures/missing.jpg', $missingManifest['path']);
        $t->same(false, $missingManifest['exists']);
        $t->same('missing-package-part', $missingManifest['byteExposurePolicy']);
        $t->same(['odf-manifest-missing-package-part'], $missingManifest['diagnostics']);

        $t->same('Notes/private.txt', $privatePackageEntry['path']);
        $t->same(['undeclared-package-entry'], $privatePackageEntry['roles']);
        $t->same(false, $privatePackageEntry['declaredInManifest']);
        $t->same(true, $privatePackageEntry['undeclared']);
        $t->same('undeclared-package-entry-no-bytes', $privatePackageEntry['byteExposurePolicy']);
        $t->same(null, $privatePackageEntry['byteSha256'] ?? null);
        $t->same(sprintf('%08x', crc32($privateNote)), $privatePackageEntry['crc32']);
    },
    'blocks compact ODT configuration package sidecars from document media handoff' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $acceleratorXml = '<accel:acceleratorlist xmlns:accel="http://openoffice.org/2001/accel"/>';
        $configIconBytes = 'CONFIGPNG';
        $statusbarXml = '<statusbar:statusbar xmlns:statusbar="http://openoffice.org/2001/statusbar"/>';
        $configurationEntries =
            '  <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/accelerator/current.xml" manifest:size="' . strlen($acceleratorXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Configurations2/images/Bitmaps/review.png" manifest:size="' . strlen($configIconBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/toolbar/missing.xml"/>' . "\n";
        $manifest = str_replace('</manifest:manifest>', $configurationEntries . '</manifest:manifest>', $manifestXml);

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Configurations2/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Configurations2/accelerator/current.xml', 'data' => $acceleratorXml, 'compressionMethod' => 0],
                ['name' => 'Configurations2/images/Bitmaps/review.png', 'data' => $configIconBytes, 'compressionMethod' => 0],
                ['name' => 'Configurations2/statusbar/standardbar.xml', 'data' => $statusbarXml, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $configurationByPath = [];
        foreach ($summary['packageConfigurations']['items'] as $item) {
            $configurationByPath[$item['packagePath']] = $item;
        }
        $inventory = $summary['packageInventory'];

        $t->same(5, $summary['packageConfigurations']['count']);
        $t->same(3, $summary['packageConfigurations']['readableCount']);
        $t->same(4, $summary['packageConfigurations']['declaredCount']);
        $t->same(1, $summary['packageConfigurations']['undeclaredCount']);
        $t->same(1, $summary['packageConfigurations']['missingCount']);
        $t->same(1, $summary['packageConfigurations']['directoryCount']);
        $t->same(0, $summary['packageConfigurations']['encryptedCount']);
        $t->same(0, $summary['packageConfigurations']['invalidMediaTypeCount']);
        $t->same(2, $summary['packageConfigurations']['issueCount']);
        $t->same([
            'odf-configuration-missing-package-part',
            'odf-configuration-undeclared-package-part',
        ], $summary['packageConfigurations']['issueCodes']);
        $t->same(['accelerator', 'images', 'statusbar', 'toolbar'], $summary['packageConfigurations']['configurationAreas']);
        $t->same(['configuration-image', 'configuration-root', 'configuration-xml'], $summary['packageConfigurations']['configurationKinds']);
        $t->same('configuration-package-bytes-blocked', $summary['packageConfigurations']['byteExposurePolicy']);
        $t->same('configuration-package-metadata-only', $summary['packageConfigurations']['reviewPolicy']);
        $t->same([
            'Configurations2/',
            'Configurations2/accelerator/current.xml',
            'Configurations2/images/Bitmaps/review.png',
            'Configurations2/statusbar/standardbar.xml',
            'Configurations2/toolbar/missing.xml',
        ], array_column($summary['packageConfigurations']['items'], 'packagePath'));

        $t->same(4, $summary['manifestReview']['configurationPackagePartCount']);
        $t->same([
            'Configurations2/',
            'Configurations2/accelerator/current.xml',
            'Configurations2/images/Bitmaps/review.png',
            'Configurations2/toolbar/missing.xml',
        ], array_column($summary['manifestReview']['configurationPackageItems'], 'path'));
        $t->same(4, $inventory['configurationPackagePartCount']);
        $t->same(4, $inventory['roleCounts']['configuration-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['configuration-package']);
        $t->same(['configuration-package', 'zip-directory', 'manifest-declared'], $inventory['parts']['Configurations2/']['roles']);
        $t->same(['configuration-package', 'manifest-declared'], $inventory['parts']['Configurations2/accelerator/current.xml']['roles']);
        $t->same(['configuration-package', 'manifest-declared'], $inventory['parts']['Configurations2/images/Bitmaps/review.png']['roles']);
        $t->same(['configuration-package', 'undeclared-package-entry'], $inventory['parts']['Configurations2/statusbar/standardbar.xml']['roles']);

        $accelerator = $reviewByPath['Configurations2/accelerator/current.xml'];
        $t->same(true, $accelerator['configurationPackagePart']);
        $t->same(true, $accelerator['exists']);
        $t->same(false, $accelerator['canExposeBytes']);
        $t->same(null, $accelerator['byteLength']);
        $t->same(strlen($acceleratorXml), $accelerator['storedByteLength']);
        $t->same(null, $accelerator['crc32']);
        $t->same(sprintf('%08x', crc32($acceleratorXml)), $accelerator['storedCrc32']);
        $t->same('configuration-package-bytes-blocked', $accelerator['byteExposurePolicy']);

        $configurationRoot = $configurationByPath['Configurations2/'];
        $t->same(true, $configurationRoot['isDirectory']);
        $t->same('configuration-root', $configurationRoot['configurationKind']);
        $t->same(null, $configurationRoot['byteLength']);
        $t->same('directory-entry-no-bytes', $configurationRoot['byteExposurePolicy']);

        $acceleratorConfiguration = $configurationByPath['Configurations2/accelerator/current.xml'];
        $t->same('accelerator', $acceleratorConfiguration['configurationArea']);
        $t->same('configuration-xml', $acceleratorConfiguration['configurationKind']);
        $t->same('accelerator/current.xml', $acceleratorConfiguration['configurationPath']);
        $t->same('xml', $acceleratorConfiguration['extension']);
        $t->same(true, $acceleratorConfiguration['declared']);
        $t->same(false, $acceleratorConfiguration['undeclared']);
        $t->same(true, $acceleratorConfiguration['valid']);
        $t->same(strlen($acceleratorXml), $acceleratorConfiguration['byteLength']);
        $t->same(sprintf('%08x', crc32($acceleratorXml)), $acceleratorConfiguration['crc32']);
        $t->same(false, $acceleratorConfiguration['canExposeAsDocumentMedia']);
        $t->same('configuration-package-bytes-blocked', $acceleratorConfiguration['byteExposurePolicy']);
        $t->same('configuration-package-metadata-only', $acceleratorConfiguration['reviewPolicy']);
        $t->same([], $acceleratorConfiguration['issues']);

        $configIcon = $reviewByPath['Configurations2/images/Bitmaps/review.png'];
        $t->same(true, $configIcon['configurationPackagePart']);
        $t->same(false, $configIcon['canExposeBytes']);
        $t->same('configuration-package-bytes-blocked', $configIcon['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));

        $iconConfiguration = $configurationByPath['Configurations2/images/Bitmaps/review.png'];
        $t->same('images', $iconConfiguration['configurationArea']);
        $t->same('configuration-image', $iconConfiguration['configurationKind']);
        $t->same('image/png', $iconConfiguration['mediaType']);
        $t->same(strlen($configIconBytes), $iconConfiguration['byteLength']);
        $t->same(sprintf('%08x', crc32($configIconBytes)), $iconConfiguration['crc32']);

        $missing = $reviewByPath['Configurations2/toolbar/missing.xml'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same('configuration-package-bytes-blocked', $missing['byteExposurePolicy']);
        $t->same(['odf-manifest-missing-package-part'], $missing['diagnostics']);

        $missingConfiguration = $configurationByPath['Configurations2/toolbar/missing.xml'];
        $t->same(false, $missingConfiguration['exists']);
        $t->same(false, $missingConfiguration['valid']);
        $t->same(['odf-configuration-missing-package-part'], $missingConfiguration['issues']);

        $orphan = $summary['undeclaredPackageEntries'][0];
        $t->same('Configurations2/statusbar/standardbar.xml', $orphan['path']);
        $t->same(true, $orphan['configurationPackagePart']);
        $t->same(false, $orphan['canExposeBytes']);
        $t->same('configuration-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $orphanConfiguration = $configurationByPath['Configurations2/statusbar/standardbar.xml'];
        $t->same(false, $orphanConfiguration['declared']);
        $t->same(true, $orphanConfiguration['undeclared']);
        $t->same('statusbar', $orphanConfiguration['configurationArea']);
        $t->same('text/xml', $orphanConfiguration['mediaType']);
        $t->same(strlen($statusbarXml), $orphanConfiguration['byteLength']);
        $t->same(['odf-configuration-undeclared-package-part'], $orphanConfiguration['issues']);
    },
    'reports compact ODT configuration package issue buckets as metadata-only review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $encryptedXml = '<toolbar encrypted="true"/>';
        $invalidImageBytes = 'not-image-review';
        $orphanXml = '<statusbar orphan="true"/>';
        $configurationEntries =
            '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/toolbar/encrypted.xml" manifest:size="' . strlen($encryptedXml) . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="config-checksum"/></manifest:file-entry>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/plain" manifest:full-path="Configurations2/images/Bitmaps/invalid.png" manifest:size="' . strlen($invalidImageBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/toolbar/missing.xml"/>' . "\n";
        $manifest = str_replace('</manifest:manifest>', $configurationEntries . '</manifest:manifest>', $manifestXml);

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Configurations2/toolbar/encrypted.xml', 'data' => $encryptedXml, 'compressionMethod' => 0],
                ['name' => 'Configurations2/images/Bitmaps/invalid.png', 'data' => $invalidImageBytes, 'compressionMethod' => 0],
                ['name' => 'Configurations2/statusbar/orphan.xml', 'data' => $orphanXml, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $configurations = $summary['packageConfigurations'];
        $itemsByPath = [];
        foreach ($configurations['items'] as $item) {
            $itemsByPath[$item['packagePath']] = $item;
        }

        $t->same(4, $configurations['count']);
        $t->same(2, $configurations['readableCount']);
        $t->same(3, $configurations['declaredCount']);
        $t->same(1, $configurations['undeclaredCount']);
        $t->same(1, $configurations['missingCount']);
        $t->same(0, $configurations['directoryCount']);
        $t->same(1, $configurations['encryptedCount']);
        $t->same(1, $configurations['invalidMediaTypeCount']);
        $t->same(4, $configurations['issueCount']);
        $t->same([
            'odf-configuration-encrypted-package-part',
            'odf-configuration-invalid-media-type',
            'odf-configuration-missing-package-part',
            'odf-configuration-undeclared-package-part',
        ], $configurations['issueCodes']);
        $t->same(['images', 'statusbar', 'toolbar'], $configurations['configurationAreas']);
        $t->same(['configuration-part', 'configuration-xml'], $configurations['configurationKinds']);
        $t->same('configuration-package-bytes-blocked', $configurations['byteExposurePolicy']);
        $t->same('configuration-package-metadata-only', $configurations['reviewPolicy']);

        $encrypted = $itemsByPath['Configurations2/toolbar/encrypted.xml'];
        $t->same(true, $encrypted['exists']);
        $t->same(true, $encrypted['encrypted']);
        $t->same(false, $encrypted['valid']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedXml), $encrypted['storedByteLength']);
        $t->same(null, $encrypted['crc32']);
        $t->same(sprintf('%08x', crc32($encryptedXml)), $encrypted['storedCrc32']);
        $t->same(['odf-configuration-encrypted-package-part'], $encrypted['issues']);

        $invalid = $itemsByPath['Configurations2/images/Bitmaps/invalid.png'];
        $t->same('images', $invalid['configurationArea']);
        $t->same('configuration-part', $invalid['configurationKind']);
        $t->same('text/plain', $invalid['mediaType']);
        $t->same(false, $invalid['valid']);
        $t->same(strlen($invalidImageBytes), $invalid['byteLength']);
        $t->same(sprintf('%08x', crc32($invalidImageBytes)), $invalid['crc32']);
        $t->same(['odf-configuration-invalid-media-type'], $invalid['issues']);

        $missing = $itemsByPath['Configurations2/toolbar/missing.xml'];
        $t->same(false, $missing['exists']);
        $t->same('configuration-xml', $missing['configurationKind']);
        $t->same(['odf-configuration-missing-package-part'], $missing['issues']);

        $orphan = $itemsByPath['Configurations2/statusbar/orphan.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(true, $orphan['valid']);
        $t->same('text/xml', $orphan['mediaType']);
        $t->same(strlen($orphanXml), $orphan['byteLength']);
        $t->same(['odf-configuration-undeclared-package-part'], $orphan['issues']);

        $reviewByPath = [];
        foreach ($summary['manifestReview']['configurationPackageItems'] as $item) {
            $reviewByPath[$item['packagePath']] = $item;
        }
        $t->same(true, $reviewByPath['Configurations2/toolbar/encrypted.xml']['configurationPackagePart']);
        $t->same('encrypted-resource-bytes-blocked', $reviewByPath['Configurations2/toolbar/encrypted.xml']['byteExposurePolicy']);
        $t->same(true, $summary['packageInventory']['parts']['Configurations2/statusbar/orphan.xml']['configurationPackagePart']);
        $t->same(['configuration-package', 'undeclared-package-entry'], $summary['packageInventory']['parts']['Configurations2/statusbar/orphan.xml']['roles']);
    },
    'reports compact ODT embedded object package provenance without media byte exposure' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $chartContent = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"><office:body/></office:document-content>';
        $chartPreview = 'PREVIEW';
        $chartRdf = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/>';
        $oleBytes = 'OLEBYTES!';
        $manifest = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart" manifest:version="1.3" manifest:preferred-view-mode="view"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml" manifest:size="' . strlen($chartContent) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Chart/Pictures/preview.png" manifest:media-type="image/png" manifest:size="' . strlen($chartPreview) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20OLE/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20OLE/oleObject.bin" manifest:media-type="application/vnd.openxmlformats-officedocument.oleObject" manifest:size="' . strlen($oleBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Missing/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Missing/content.xml" manifest:media-type="text/xml"/>' . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Object Chart/content.xml', 'data' => $chartContent, 'compressionMethod' => 0],
                ['name' => 'Object Chart/Pictures/preview.png', 'data' => $chartPreview, 'compressionMethod' => 0],
                ['name' => 'Object Chart/manifest.rdf', 'data' => $chartRdf, 'compressionMethod' => 0],
                ['name' => 'Object OLE/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Object OLE/oleObject.bin', 'data' => $oleBytes, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $objects = $summary['packageObjects'];
        $chart = $objects['byRootPart']['Object Chart/'];
        $ole = $objects['byRootPart']['Object OLE/'];
        $missing = $objects['byRootPart']['Object Missing/'];
        $inventory = $summary['packageInventory'];
        $parts = $inventory['parts'];
        $review = $summary['manifestReview'];
        $reviewByPath = [];
        foreach ($review['items'] as $item) {
            $reviewByPath[$item['fullPath']] = $item;
        }
        $chartContainedByPart = [];
        foreach ($chart['containedParts'] as $item) {
            $chartContainedByPart[$item['part']] = $item;
        }

        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same(1, $summary['exposableMediaPartCount']);
        $t->same(1, $inventory['mediaResourcePartCount']);
        $t->same(1, $inventory['undeclaredEntryCount']);
        $t->same(2, $inventory['embeddedObjectPackageRootCount']);
        $t->same(4, $inventory['embeddedObjectPackagePartCount']);
        $t->same(2, $inventory['roleCounts']['embedded-object-root']);
        $t->same(4, $inventory['roleCounts']['embedded-object-part']);
        $t->same(1, $inventory['undeclaredRoleCounts']['embedded-object-part']);

        $t->same(3, $objects['count']);
        $t->same(2, $objects['existingCount']);
        $t->same(1, $objects['missingCount']);
        $t->same(0, $objects['encryptedCount']);
        $t->same(4, $objects['containedPartCount']);
        $t->same(strlen($chartContent) + strlen($chartPreview) + strlen($chartRdf) + strlen($oleBytes), $objects['containedByteLength']);
        $t->same([
            'document-xml' => 1,
            'media-resource' => 1,
            'package-part' => 1,
            'rdf-metadata' => 1,
        ], $objects['containedRoleCounts']);
        $t->same([
            'document-xml' => strlen($chartContent),
            'media-resource' => strlen($chartPreview),
            'package-part' => strlen($oleBytes),
            'rdf-metadata' => strlen($chartRdf),
        ], $objects['containedRoleByteLengths']);
        $t->same([
            'document-xml' => strlen($chartContent),
            'media-resource' => strlen($chartPreview),
            'package-part' => strlen($oleBytes),
            'rdf-metadata' => strlen($chartRdf),
        ], $objects['containedRoleCompressedByteLengths']);
        $t->same([
            'image' => 1,
            'other' => 1,
            'rdf' => 1,
            'xml' => 1,
        ], $objects['containedMediaFamilyCounts']);
        $t->same([
            'image' => strlen($chartPreview),
            'other' => strlen($oleBytes),
            'rdf' => strlen($chartRdf),
            'xml' => strlen($chartContent),
        ], $objects['containedMediaFamilyByteLengths']);
        $t->same([
            'image' => strlen($chartPreview),
            'other' => strlen($oleBytes),
            'rdf' => strlen($chartRdf),
            'xml' => strlen($chartContent),
        ], $objects['containedMediaFamilyCompressedByteLengths']);
        $t->same(4, $objects['declaredContainedPartCount']);
        $t->same(3, $objects['existingDeclaredContainedPartCount']);
        $t->same(1, $objects['missingDeclaredContainedPartCount']);
        $t->same(1, $objects['undeclaredContainedPartCount']);
        $t->same(['Object Chart/', 'Object OLE/', 'Object Missing/'], $objects['rootParts']);
        $t->same(['chart', 'spreadsheet'], $objects['objectTypes']);
        $t->same('embedded-object-package-bytes-blocked', $objects['byteExposurePolicy']);
        $t->same('embedded-object-package-metadata-only', $objects['reviewPolicy']);
        $t->same([
            'odf-embedded-object-package-missing',
            'odf-embedded-object-package-missing-declared-part',
            'odf-embedded-object-package-undeclared-contained-part',
        ], $objects['issueCodes']);

        $t->same('Object Chart/', $chart['rootPart']);
        $t->same('Object Chart', $chart['objectPath']);
        $t->same('Object%20Chart/', $chart['fullPath']);
        $t->same('chart', $chart['objectType']);
        $t->same('application/vnd.oasis.opendocument.chart', $chart['mediaType']);
        $t->same('1.3', $chart['version']);
        $t->same('view', $chart['preferredViewMode']);
        $t->same(true, $chart['exists']);
        $t->same(false, $chart['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $chart['byteExposurePolicy']);
        $t->same(3, $chart['containedPartCount']);
        $t->same(strlen($chartContent) + strlen($chartPreview) + strlen($chartRdf), $chart['containedByteLength']);
        $t->same([
            'document-xml' => 1,
            'media-resource' => 1,
            'rdf-metadata' => 1,
        ], $chart['containedRoleCounts']);
        $t->same([
            'document-xml' => strlen($chartContent),
            'media-resource' => strlen($chartPreview),
            'rdf-metadata' => strlen($chartRdf),
        ], $chart['containedRoleByteLengths']);
        $t->same([
            'image' => 1,
            'rdf' => 1,
            'xml' => 1,
        ], $chart['containedMediaFamilyCounts']);
        $t->same([
            'image' => strlen($chartPreview),
            'rdf' => strlen($chartRdf),
            'xml' => strlen($chartContent),
        ], $chart['containedMediaFamilyByteLengths']);
        $t->same(['Object Chart/Pictures/preview.png', 'Object Chart/content.xml', 'Object Chart/manifest.rdf'], array_column($chart['containedParts'], 'part'));
        $t->same(['media-resource', 'document-xml', 'rdf-metadata'], array_column($chart['containedParts'], 'containedRole'));
        $t->same(['image', 'xml', 'rdf'], array_column($chart['containedParts'], 'containedMediaFamily'));
        $t->same('document-xml', $chartContainedByPart['Object Chart/content.xml']['containedRole']);
        $t->same('xml', $chartContainedByPart['Object Chart/content.xml']['containedMediaFamily']);
        $t->same(2, $chart['declaredContainedPartCount']);
        $t->same(2, $chart['existingDeclaredContainedPartCount']);
        $t->same(0, $chart['missingDeclaredContainedPartCount']);
        $t->same(1, $chart['undeclaredContainedPartCount']);
        $t->same(['Object Chart/manifest.rdf'], array_column($chart['undeclaredContainedParts'], 'part'));
        $t->same(['rdf-metadata'], array_column($chart['undeclaredContainedParts'], 'containedRole'));
        $t->same(['rdf'], array_column($chart['undeclaredContainedParts'], 'containedMediaFamily'));
        $t->same(['odf-embedded-object-package-undeclared-contained-part'], $chart['issues']);

        $t->same('spreadsheet', $ole['objectType']);
        $t->same('application/vnd.oasis.opendocument.spreadsheet', $ole['mediaType']);
        $t->same(false, $ole['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $ole['byteExposurePolicy']);
        $t->same(1, $ole['containedPartCount']);
        $t->same(strlen($oleBytes), $ole['containedByteLength']);
        $t->same(['package-part' => 1], $ole['containedRoleCounts']);
        $t->same(['package-part' => strlen($oleBytes)], $ole['containedRoleByteLengths']);
        $t->same(['other' => 1], $ole['containedMediaFamilyCounts']);
        $t->same(['other' => strlen($oleBytes)], $ole['containedMediaFamilyByteLengths']);
        $t->same(['Object OLE/oleObject.bin'], array_column($ole['containedParts'], 'part'));
        $t->same(['package-part'], array_column($ole['containedParts'], 'containedRole'));
        $t->same(['other'], array_column($ole['containedParts'], 'containedMediaFamily'));
        $t->same([], $ole['issues']);

        $t->same('Object Missing/', $missing['rootPart']);
        $t->same(false, $missing['exists']);
        $t->same(0, $missing['containedPartCount']);
        $t->same(1, $missing['declaredContainedPartCount']);
        $t->same(1, $missing['missingDeclaredContainedPartCount']);
        $t->same(['Object Missing/content.xml'], array_column($missing['missingDeclaredContainedParts'], 'part'));
        $t->same([
            'odf-embedded-object-package-missing',
            'odf-embedded-object-package-missing-declared-part',
        ], $missing['issues']);

        $t->same(7, $review['embeddedObjectPackagePartCount']);
        $t->same(3, $review['embeddedObjectRootCount']);
        $t->same(4, $review['embeddedObjectContainedPartCount']);
        $t->same(false, $reviewByPath['Object%20Chart/content.xml']['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $reviewByPath['Object%20Chart/content.xml']['byteExposurePolicy']);
        $t->same(false, $reviewByPath['Object%20Chart/Pictures/preview.png']['canExposeBytes']);
        $t->same(true, $reviewByPath['Object%20Chart/Pictures/preview.png']['embeddedObjectContainedPart']);
        $t->same('Object Chart/', $reviewByPath['Object%20Chart/Pictures/preview.png']['embeddedObjectRootPart']);
        $t->same('chart', $reviewByPath['Object%20Chart/Pictures/preview.png']['embeddedObjectType']);

        $t->same(['zip-directory', 'embedded-object-root', 'manifest-declared'], $parts['Object Chart/']['roles']);
        $t->same(['embedded-object-part', 'manifest-declared'], $parts['Object Chart/content.xml']['roles']);
        $t->same(['embedded-object-part', 'manifest-declared'], $parts['Object Chart/Pictures/preview.png']['roles']);
        $t->same(['rdf-metadata', 'embedded-object-part', 'undeclared-package-entry'], $parts['Object Chart/manifest.rdf']['roles']);
        $t->same(['zip-directory', 'embedded-object-root', 'manifest-declared'], $parts['Object OLE/']['roles']);
        $t->same(['embedded-object-part', 'manifest-declared'], $parts['Object OLE/oleObject.bin']['roles']);
        $t->same('embedded-object-package-bytes-blocked', $parts['Object Chart/content.xml']['byteExposurePolicy']);
        $t->same(false, $parts['Object Chart/Pictures/preview.png']['canExposeBytes']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('Object Chart/manifest.rdf', $summary['undeclaredPackageEntries'][0]['path']);
    },
    'blocks compact ODT script package bytes in package review summaries' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $basicLibraryXml = '<library:library xmlns:library="http://openoffice.org/2000/library" library:name="Standard"/>';
        $basicModuleXml = '<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Approve' . "\n" . 'End Sub</script:module>';
        $javaScript = 'function ReviewLinkClick() { return false; }';
        $scriptIcon = 'SCRIPTICON';
        $encryptedScript = 'encrypted macro payload';
        $orphanScript = 'function orphan() { return true; }';
        $scriptEntries =
            '  <manifest:file-entry manifest:media-type="" manifest:full-path="Basic/"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Basic/Standard/script-lb.xml" manifest:size="' . strlen($basicLibraryXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Basic/Standard/Review.xml" manifest:size="' . strlen($basicModuleXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="" manifest:full-path="Scripts/"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="application/javascript" manifest:full-path="Scripts/review-link.js" manifest:size="' . strlen($javaScript) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Scripts/icon.png" manifest:size="' . strlen($scriptIcon) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="application/javascript" manifest:full-path="Scripts/missing.js"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="application/javascript" manifest:full-path="Scripts/encrypted.js" manifest:size="2048"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="macro-checksum"><manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="macro-iv"/></manifest:encryption-data></manifest:file-entry>' . "\n";
        $manifest = str_replace('</manifest:manifest>', $scriptEntries . '</manifest:manifest>', $manifestXml);

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Basic/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Basic/Standard/script-lb.xml', 'data' => $basicLibraryXml, 'compressionMethod' => 0],
                ['name' => 'Basic/Standard/Review.xml', 'data' => $basicModuleXml, 'compressionMethod' => 0],
                ['name' => 'Scripts/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Scripts/review-link.js', 'data' => $javaScript, 'compressionMethod' => 0],
                ['name' => 'Scripts/icon.png', 'data' => $scriptIcon, 'compressionMethod' => 0],
                ['name' => 'Scripts/encrypted.js', 'data' => $encryptedScript, 'compressionMethod' => 0],
                ['name' => 'Scripts/orphan.js', 'data' => $orphanScript, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $scripts = $summary['packageScripts'];
        $scriptByPath = [];
        foreach ($scripts['items'] as $item) {
            $scriptByPath[$item['packagePath']] = $item;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory'];

        $t->same(9, $scripts['count']);
        $t->same(7, $scripts['fileCount']);
        $t->same(2, $scripts['directoryCount']);
        $t->same(8, $scripts['storedPartCount']);
        $t->same(5, $scripts['readableCount']);
        $t->same(8, $scripts['declaredCount']);
        $t->same(1, $scripts['undeclaredCount']);
        $t->same(1, $scripts['missingCount']);
        $t->same(1, $scripts['encryptedCount']);
        $t->same(3, $scripts['issueCount']);
        $t->same([
            'odf-script-encrypted-package-part',
            'odf-script-missing-package-part',
            'odf-script-undeclared-package-part',
        ], $scripts['issueCodes']);
        $t->same(['basic', 'scripts'], $scripts['scriptContainers']);
        $t->same(['basic-library-index', 'basic-module', 'javascript', 'script-directory', 'script-package-part'], $scripts['scriptKinds']);
        $t->same('script-package-bytes-blocked', $scripts['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $scripts['reviewPolicy']);

        $t->same(8, $summary['manifestReview']['scriptPackagePartCount']);
        $t->same([
            'Basic/',
            'Basic/Standard/script-lb.xml',
            'Basic/Standard/Review.xml',
            'Scripts/',
            'Scripts/review-link.js',
            'Scripts/icon.png',
            'Scripts/missing.js',
            'Scripts/encrypted.js',
        ], array_column($summary['manifestReview']['scriptPackageItems'], 'path'));
        $t->same(8, $inventory['scriptPackagePartCount']);
        $t->same(['script-package', 'zip-directory', 'manifest-declared'], $inventory['parts']['Basic/']['roles']);
        $t->same(['script-package', 'manifest-declared'], $inventory['parts']['Basic/Standard/script-lb.xml']['roles']);
        $t->same(['script-package', 'manifest-declared'], $inventory['parts']['Basic/Standard/Review.xml']['roles']);
        $t->same(['script-package', 'manifest-declared'], $inventory['parts']['Scripts/review-link.js']['roles']);
        $t->same(['script-package', 'manifest-declared'], $inventory['parts']['Scripts/icon.png']['roles']);
        $t->same(['script-package', 'undeclared-package-entry'], $inventory['parts']['Scripts/orphan.js']['roles']);
        $t->same(1, $inventory['undeclaredRoleCounts']['script-package']);

        $basicDirectory = $scriptByPath['Basic/'];
        $t->same(true, $basicDirectory['isDirectory']);
        $t->same('basic', $basicDirectory['scriptContainer']);
        $t->same('script-directory', $basicDirectory['scriptKind']);
        $t->same(null, $basicDirectory['scriptPath']);
        $t->same(null, $basicDirectory['scriptModule']);
        $t->same(true, $basicDirectory['declared']);
        $t->same(true, $basicDirectory['valid']);
        $t->same(null, $basicDirectory['byteLength']);
        $t->same(0, $basicDirectory['storedByteLength']);
        $t->same('directory-entry-no-bytes', $basicDirectory['byteExposurePolicy']);
        $t->same([], $basicDirectory['issues']);

        $basicLibraryIndex = $scriptByPath['Basic/Standard/script-lb.xml'];
        $t->same('basic', $basicLibraryIndex['scriptContainer']);
        $t->same('basic-library-index', $basicLibraryIndex['scriptKind']);
        $t->same('Standard', $basicLibraryIndex['scriptLibrary']);
        $t->same('script-lb', $basicLibraryIndex['scriptModule']);
        $t->same('text/xml', $basicLibraryIndex['mediaType']);
        $t->same(true, $basicLibraryIndex['declared']);
        $t->same(true, $basicLibraryIndex['valid']);
        $t->same(strlen($basicLibraryXml), $basicLibraryIndex['byteLength']);
        $t->same(sprintf('%08x', crc32($basicLibraryXml)), $basicLibraryIndex['crc32']);
        $t->same(false, $basicLibraryIndex['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $basicLibraryIndex['byteExposurePolicy']);
        $t->same([], $basicLibraryIndex['issues']);

        $scriptsDirectory = $scriptByPath['Scripts/'];
        $t->same(true, $scriptsDirectory['isDirectory']);
        $t->same('scripts', $scriptsDirectory['scriptContainer']);
        $t->same('script-directory', $scriptsDirectory['scriptKind']);
        $t->same('directory-entry-no-bytes', $scriptsDirectory['byteExposurePolicy']);

        $basicScript = $scriptByPath['Basic/Standard/Review.xml'];
        $t->same('basic', $basicScript['scriptContainer']);
        $t->same('basic-module', $basicScript['scriptKind']);
        $t->same('Standard', $basicScript['scriptLibrary']);
        $t->same('Review', $basicScript['scriptModule']);
        $t->same('text/xml', $basicScript['mediaType']);
        $t->same(true, $basicScript['declared']);
        $t->same(true, $basicScript['valid']);
        $t->same(strlen($basicModuleXml), $basicScript['byteLength']);
        $t->same(sprintf('%08x', crc32($basicModuleXml)), $basicScript['crc32']);
        $t->same(false, $basicScript['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $basicScript['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $basicScript['reviewPolicy']);
        $t->same([], $basicScript['issues']);

        $basic = $reviewByPath['Basic/Standard/Review.xml'];
        $t->same(true, $basic['scriptPackagePart']);
        $t->same(true, $basic['exists']);
        $t->same(false, $basic['canExposeBytes']);
        $t->same(null, $basic['byteLength']);
        $t->same(strlen($basicModuleXml), $basic['storedByteLength']);
        $t->same(null, $basic['crc32']);
        $t->same(sprintf('%08x', crc32($basicModuleXml)), $basic['storedCrc32']);
        $t->same('script-package-bytes-blocked', $basic['byteExposurePolicy']);
        $t->same([], $basic['diagnostics']);

        $javascriptScript = $scriptByPath['Scripts/review-link.js'];
        $t->same('scripts', $javascriptScript['scriptContainer']);
        $t->same('javascript', $javascriptScript['scriptKind']);
        $t->same('review-link.js', $javascriptScript['scriptPath']);
        $t->same('review-link', $javascriptScript['scriptModule']);
        $t->same('application/javascript', $javascriptScript['mediaType']);
        $t->same(strlen($javaScript), $javascriptScript['byteLength']);
        $t->same(sprintf('%08x', crc32($javaScript)), $javascriptScript['crc32']);

        $javascript = $reviewByPath['Scripts/review-link.js'];
        $t->same(true, $javascript['scriptPackagePart']);
        $t->same(false, $javascript['canExposeBytes']);
        $t->same(null, $javascript['byteLength']);
        $t->same(strlen($javaScript), $javascript['storedByteLength']);
        $t->same('script-package-bytes-blocked', $javascript['byteExposurePolicy']);

        $iconScript = $scriptByPath['Scripts/icon.png'];
        $t->same('script-package-part', $iconScript['scriptKind']);
        $t->same('image/png', $iconScript['mediaType']);
        $t->same(strlen($scriptIcon), $iconScript['byteLength']);
        $t->same(sprintf('%08x', crc32($scriptIcon)), $iconScript['crc32']);
        $t->same(false, $iconScript['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $iconScript['byteExposurePolicy']);

        $icon = $reviewByPath['Scripts/icon.png'];
        $t->same(true, $icon['scriptPackagePart']);
        $t->same(false, $icon['canExposeBytes']);
        $t->same(null, $icon['byteLength']);
        $t->same(strlen($scriptIcon), $icon['storedByteLength']);
        $t->same('script-package-bytes-blocked', $icon['byteExposurePolicy']);

        $missingScript = $scriptByPath['Scripts/missing.js'];
        $t->same(false, $missingScript['exists']);
        $t->same(null, $missingScript['byteLength']);
        $t->same(['odf-script-missing-package-part'], $missingScript['issues']);

        $missing = $reviewByPath['Scripts/missing.js'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $missing['byteExposurePolicy']);
        $t->same(['odf-manifest-missing-package-part'], $missing['diagnostics']);

        $encryptedScriptItem = $scriptByPath['Scripts/encrypted.js'];
        $t->same(true, $encryptedScriptItem['encrypted']);
        $t->same(false, $encryptedScriptItem['valid']);
        $t->same(null, $encryptedScriptItem['byteLength']);
        $t->same(strlen($encryptedScript), $encryptedScriptItem['storedByteLength']);
        $t->same(sprintf('%08x', crc32($encryptedScript)), $encryptedScriptItem['storedCrc32']);
        $t->same('encrypted-resource-bytes-blocked', $encryptedScriptItem['byteExposurePolicy']);
        $t->same(['odf-script-encrypted-package-part'], $encryptedScriptItem['issues']);

        $encrypted = $reviewByPath['Scripts/encrypted.js'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(false, $encrypted['canExposeBytes']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedScript), $encrypted['storedByteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-manifest-encrypted-package-part', 'odf-manifest-declared-size-mismatch'], $encrypted['diagnostics']);

        $orphanScriptItem = $scriptByPath['Scripts/orphan.js'];
        $t->same(false, $orphanScriptItem['declared']);
        $t->same(true, $orphanScriptItem['undeclared']);
        $t->same('application/javascript', $orphanScriptItem['mediaType']);
        $t->same(strlen($orphanScript), $orphanScriptItem['byteLength']);
        $t->same(['odf-script-undeclared-package-part'], $orphanScriptItem['issues']);

        $orphan = $summary['undeclaredPackageEntries'][0];
        $t->same('Scripts/orphan.js', $orphan['path']);
        $t->same(true, $orphan['scriptPackagePart']);
        $t->same(false, $orphan['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same(1, $summary['exposableMediaPartCount']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
    },
    'reports compact ODT script package media type mismatches without exposing script bytes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $reviewScript = 'function approveReview() { return false; }';
        $pythonScript = 'print("review")';
        $jarBytes = 'JARDATA';
        $untypedBytes = 'UNTYPED';
        $scriptEntries =
            '  <manifest:file-entry manifest:media-type="text/plain" manifest:full-path="Scripts/review.js" manifest:size="' . strlen($reviewScript) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="application/x-python" manifest:full-path="Scripts/audit.py" manifest:size="' . strlen($pythonScript) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="application/java-archive" manifest:full-path="Scripts/legacy.jar" manifest:size="' . strlen($jarBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="" manifest:full-path="Scripts/untyped.bin" manifest:size="' . strlen($untypedBytes) . '"/>' . "\n";
        $manifest = str_replace('</manifest:manifest>', $scriptEntries . '</manifest:manifest>', $manifestXml);

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Scripts/review.js', 'data' => $reviewScript, 'compressionMethod' => 0],
                ['name' => 'Scripts/audit.py', 'data' => $pythonScript, 'compressionMethod' => 0],
                ['name' => 'Scripts/legacy.jar', 'data' => $jarBytes, 'compressionMethod' => 0],
                ['name' => 'Scripts/untyped.bin', 'data' => $untypedBytes, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $scripts = $summary['packageScripts'];
        $scriptByPath = [];
        foreach ($scripts['items'] as $item) {
            $scriptByPath[$item['packagePath']] = $item;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory']['parts'];

        $t->same(4, $scripts['count']);
        $t->same(4, $scripts['readableCount']);
        $t->same(4, $scripts['declaredCount']);
        $t->same(0, $scripts['missingCount']);
        $t->same(0, $scripts['encryptedCount']);
        $t->same(2, $scripts['invalidMediaTypeCount']);
        $t->same(2, $scripts['issueCount']);
        $t->same(['odf-script-invalid-media-type'], $scripts['issueCodes']);
        $t->same(['scripts'], $scripts['scriptContainers']);
        $t->same(['java-archive', 'javascript', 'python', 'script-package-part'], $scripts['scriptKinds']);

        $badJavaScript = $scriptByPath['Scripts/review.js'];
        $t->same('text/plain', $badJavaScript['mediaType']);
        $t->same('javascript', $badJavaScript['scriptKind']);
        $t->same(false, $badJavaScript['mediaTypeValid']);
        $t->same(false, $badJavaScript['valid']);
        $t->same(['odf-script-invalid-media-type'], $badJavaScript['issues']);
        $t->same(strlen($reviewScript), $badJavaScript['storedByteLength']);
        $t->same('script-package-bytes-blocked', $badJavaScript['byteExposurePolicy']);

        $python = $scriptByPath['Scripts/audit.py'];
        $t->same('application/x-python', $python['mediaType']);
        $t->same('python', $python['scriptKind']);
        $t->same(true, $python['mediaTypeValid']);
        $t->same(true, $python['valid']);
        $t->same([], $python['issues']);

        $jar = $scriptByPath['Scripts/legacy.jar'];
        $t->same('java-archive', $jar['scriptKind']);
        $t->same(true, $jar['mediaTypeValid']);
        $t->same(true, $jar['valid']);

        $untyped = $scriptByPath['Scripts/untyped.bin'];
        $t->same(null, $untyped['mediaType']);
        $t->same('script-package-part', $untyped['scriptKind']);
        $t->same(false, $untyped['mediaTypeValid']);
        $t->same(false, $untyped['valid']);
        $t->same(['odf-script-invalid-media-type'], $untyped['issues']);

        $t->same(true, $reviewByPath['Scripts/review.js']['scriptPackagePart']);
        $t->same(false, $reviewByPath['Scripts/review.js']['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $reviewByPath['Scripts/review.js']['byteExposurePolicy']);
        $t->same(['script-package', 'manifest-declared'], $inventory['Scripts/review.js']['roles']);
        $t->same(false, $inventory['Scripts/review.js']['canExposeBytes']);
        $t->same(null, $inventory['Scripts/review.js']['byteSha256']);
        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
    },
    'reports compact ODT package fonts as metadata-only package review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $reviewSansBytes = 'WOFF2DAT';
        $sourceBytes = 'WOFFDATA';
        $invalidBytes = 'NOTFONT';
        $encryptedBytes = 'ENCFONT';
        $orphanBytes = 'TTFORPHAN';
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:media-type="font/ttf" manifest:full-path="Fonts/encrypted.ttf" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="font-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="font-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="font/woff2" manifest:full-path="Fonts/ReviewSans.woff2" manifest:size="' . strlen($reviewSansBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="application/vnd.ms-opentype" manifest:full-path="Fonts/Missing.otf"/>'
            . '<manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="Fonts/not-font.bin" manifest:size="' . strlen($invalidBytes) . '"/>'
            . '<manifest:file-entry manifest:media-type="font/woff; technology=variations" manifest:full-path="Assets/source.woff" manifest:size="' . strlen($sourceBytes) . '"/>'
            . $encryptedEntry,
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Fonts/ReviewSans.woff2', 'data' => $reviewSansBytes, 'compressionMethod' => 0],
                ['name' => 'Fonts/not-font.bin', 'data' => $invalidBytes, 'compressionMethod' => 0],
                ['name' => 'Fonts/encrypted.ttf', 'data' => $encryptedBytes, 'compressionMethod' => 0],
                ['name' => 'Fonts/orphan.ttf', 'data' => $orphanBytes, 'compressionMethod' => 0],
                ['name' => 'Assets/source.woff', 'data' => $sourceBytes, 'compressionMethod' => 0],
            ],
        ));
        $summary = $odt->summarize();
        $fonts = $summary['packageFonts'];
        $fontByPath = [];
        foreach ($fonts['items'] as $item) {
            $fontByPath[$item['packagePath']] = $item;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory'];

        $t->same(6, $fonts['count']);
        $t->same(4, $fonts['readableCount']);
        $t->same(5, $fonts['declaredCount']);
        $t->same(1, $fonts['undeclaredCount']);
        $t->same(1, $fonts['missingCount']);
        $t->same(1, $fonts['encryptedCount']);
        $t->same(1, $fonts['invalidMediaTypeCount']);
        $t->same(4, $fonts['issueCount']);
        $t->same([
            'odf-font-encrypted-package-part',
            'odf-font-invalid-media-type',
            'odf-font-missing-package-part',
            'odf-font-undeclared-package-part',
        ], $fonts['issueCodes']);
        $t->same([
            'opentype' => 1,
            'truetype' => 2,
            'unknown' => 1,
            'woff' => 1,
            'woff2' => 1,
        ], $fonts['fontFormatCounts']);
        $t->same([
            'media-type' => 4,
            'package-extension' => 1,
            'unknown' => 1,
        ], $fonts['fontFormatSourceCounts']);
        $t->same([
            'sfnt' => 3,
            'unknown' => 1,
            'webfont' => 2,
        ], $fonts['fontFormatFamilyCounts']);
        $t->same([
            'bin' => 1,
            'otf' => 1,
            'ttf' => 2,
            'woff' => 1,
            'woff2' => 1,
        ], $fonts['fontFileExtensionCounts']);
        $t->same(5, $fonts['recognizedFontFormatCount']);
        $t->same(1, $fonts['unknownFontFormatCount']);
        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same(5, $summary['manifestReview']['fontPackagePartCount']);
        $t->same(5, $inventory['fontPackagePartCount']);
        $t->same(5, $inventory['roleCounts']['font-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['font-package']);

        $declared = $fontByPath['Fonts/ReviewSans.woff2'];
        $manifestDeclared = $odt->manifestEntry('Fonts/ReviewSans.woff2');
        $t->same('font/woff2', $declared['mediaType']);
        $t->same('font/woff2', $declared['mediaTypeBase']);
        $t->same('woff2', $declared['fontFileExtension']);
        $t->same('woff2', $declared['fontFormat']);
        $t->same('media-type', $declared['fontFormatSource']);
        $t->same('webfont', $declared['fontFormatFamily']);
        $t->same(true, $declared['recognizedFontFormat']);
        $t->same(true, $declared['declared']);
        $t->same(true, $declared['valid']);
        $t->same(strlen($reviewSansBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($reviewSansBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('package-font-metadata-only', $declared['reviewPolicy']);
        $t->same([], $declared['issues']);
        $t->same(true, $manifestDeclared['fontPackagePart']);
        $t->same(false, $manifestDeclared['canExposeBytes']);
        $t->same(null, $manifestDeclared['byteLength']);
        $t->same(strlen($reviewSansBytes), $manifestDeclared['storedByteLength']);
        $t->same('font-package-bytes-blocked', $manifestDeclared['byteExposurePolicy']);
        $t->same(false, $reviewByPath['Fonts/ReviewSans.woff2']['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $reviewByPath['Fonts/ReviewSans.woff2']['byteExposurePolicy']);

        $asset = $fontByPath['Assets/source.woff'];
        $t->same('font/woff; technology=variations', $asset['mediaType']);
        $t->same('font/woff', $asset['mediaTypeBase']);
        $t->same(['technology' => 'variations'], $asset['mediaTypeParameterMap']);
        $t->same('woff', $asset['fontFileExtension']);
        $t->same('woff', $asset['fontFormat']);
        $t->same('media-type', $asset['fontFormatSource']);
        $t->same('webfont', $asset['fontFormatFamily']);
        $t->same(['font-package', 'manifest-declared'], $inventory['parts']['Assets/source.woff']['roles']);

        $missing = $fontByPath['Fonts/Missing.otf'];
        $t->same('opentype', $missing['fontFormat']);
        $t->same('media-type', $missing['fontFormatSource']);
        $t->same('sfnt', $missing['fontFormatFamily']);
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-font-missing-package-part'], $missing['issues']);

        $invalid = $fontByPath['Fonts/not-font.bin'];
        $t->same('application/octet-stream', $invalid['mediaType']);
        $t->same('bin', $invalid['fontFileExtension']);
        $t->same('unknown', $invalid['fontFormat']);
        $t->same('unknown', $invalid['fontFormatSource']);
        $t->same('unknown', $invalid['fontFormatFamily']);
        $t->same(false, $invalid['recognizedFontFormat']);
        $t->same(false, $invalid['valid']);
        $t->same(strlen($invalidBytes), $invalid['byteLength']);
        $t->same(['odf-font-invalid-media-type'], $invalid['issues']);

        $encrypted = $fontByPath['Fonts/encrypted.ttf'];
        $t->same('truetype', $encrypted['fontFormat']);
        $t->same('media-type', $encrypted['fontFormatSource']);
        $t->same(true, $encrypted['exists']);
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(true, $encrypted['declaredSizeMismatch']);
        $t->same(['odf-font-encrypted-package-part'], $encrypted['issues']);

        $orphan = $fontByPath['Fonts/orphan.ttf'];
        $t->same('truetype', $orphan['fontFormat']);
        $t->same('package-extension', $orphan['fontFormatSource']);
        $t->same('sfnt', $orphan['fontFormatFamily']);
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('font/ttf', $orphan['mediaType']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-font-undeclared-package-part'], $orphan['issues']);

        $t->same(['font-package', 'manifest-declared'], $inventory['parts']['Fonts/ReviewSans.woff2']['roles']);
        $t->same(['font-package', 'manifest-declared'], $inventory['parts']['Fonts/not-font.bin']['roles']);
        $t->same(['font-package', 'undeclared-package-entry'], $inventory['parts']['Fonts/orphan.ttf']['roles']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('Fonts/orphan.ttf', $summary['undeclaredPackageEntries'][0]['path']);
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
        $manifestReviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $manifestReviewByPath[$item['path']] = $item;
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
        $t->same(false, $declared['canExposeBytes']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-bytes-blocked', $declared['byteExposurePolicy']);
        $t->same('package-thumbnail-metadata-only', $declared['reviewPolicy']);
        $t->same([], $declared['issues']);

        $manifestDeclared = $manifestReviewByPath['Thumbnails/thumbnail.png'];
        $t->same(true, $manifestDeclared['thumbnailPackagePart']);
        $t->same('thumbnail', $manifestDeclared['manifestMediaFamily']);
        $t->same(false, $manifestDeclared['canExposeBytes']);
        $t->same(null, $manifestDeclared['byteLength']);
        $t->same(strlen($thumbnailBytes), $manifestDeclared['storedByteLength']);
        $t->same(null, $manifestDeclared['crc32']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $manifestDeclared['storedCrc32']);
        $t->same(null, $manifestDeclared['byteSha256']);
        $t->same('package-thumbnail-bytes-blocked', $manifestDeclared['byteExposurePolicy']);

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
        $t->same(true, $inventory['Thumbnails/thumbnail.png']['thumbnailPackagePart']);
        $t->same(false, $inventory['Thumbnails/thumbnail.png']['canExposeBytes']);
        $t->same('package-thumbnail-bytes-blocked', $inventory['Thumbnails/thumbnail.png']['byteExposurePolicy']);
        $t->same(['package-thumbnail', 'manifest-declared'], $inventory['Thumbnails/not-image.png']['roles']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $inventory['Thumbnails/orphan.webp']['roles']);
        $t->same(4, $summary['packageInventory']['packageThumbnailPartCount']);
        $t->same(4, $summary['packageIdentity']['packageThumbnailPartCount']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('Thumbnails/orphan.webp', $summary['undeclaredPackageEntries'][0]['path']);
        $t->same(true, $summary['undeclaredPackageEntries'][0]['thumbnailPackagePart']);
        $t->same('package-thumbnail-bytes-blocked', $summary['undeclaredPackageEntries'][0]['byteExposurePolicy']);
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
        $t->same(false, $inventory['META-INF/documentsignatures.xml']['canExposeBytes']);
        $t->same('signature-package-bytes-blocked', $inventory['META-INF/documentsignatures.xml']['byteExposurePolicy']);
        $t->same(null, $inventory['META-INF/documentsignatures.xml']['byteSha256']);
        $t->same(['package-signature', 'manifest-declared'], $inventory['META-INF/packagesignatures.xml']['roles']);
        $t->same('signature-package-bytes-blocked', $inventory['META-INF/packagesignatures.xml']['byteExposurePolicy']);
        $t->same(['package-signature', 'undeclared-package-entry'], $inventory['META-INF/orphan-signatures.xml']['roles']);
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('META-INF/orphan-signatures.xml', $summary['undeclaredPackageEntries'][0]['path']);
        $t->same(true, $summary['undeclaredPackageEntries'][0]['signaturePackagePart']);
        $t->same('signature-package-bytes-blocked', $summary['undeclaredPackageEntries'][0]['byteExposurePolicy']);
    },
    'blocks compact ODT signature sidecar bytes in package exposure policy' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $signatureXml = '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"><dsig:Signature Id="review-signature"/></dsig:document-signatures>';
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="META-INF/documentsignatures.xml" manifest:size="' . strlen($signatureXml) . '"/>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml, 'compressionMethod' => 0],
            ]
        ))->summarize();
        $manifestItems = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $manifestItems[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory'];
        $signaturePart = $inventory['parts']['META-INF/documentsignatures.xml'];

        $t->same('signature-package-bytes-blocked', $manifestItems['META-INF/documentsignatures.xml']['byteExposurePolicy']);
        $t->same(false, $manifestItems['META-INF/documentsignatures.xml']['canExposeBytes']);
        $t->same(null, $manifestItems['META-INF/documentsignatures.xml']['byteSha256']);
        $t->same(['package-signature', 'manifest-declared'], $signaturePart['roles']);
        $t->same('signature-package-bytes-blocked', $signaturePart['byteExposurePolicy']);
        $t->same(false, $signaturePart['canExposeBytes']);
        $t->same(null, $signaturePart['byteSha256']);
        $t->same(1, $inventory['packageSignaturePartCount']);
        $t->same(1, $inventory['byteExposurePolicyCounts']['signature-package-bytes-blocked']);
        $t->same(strlen($signatureXml), $inventory['byteExposurePolicyByteLengths']['signature-package-bytes-blocked']);
        $t->same('package-signature-metadata-only', $summary['packageSignatures']['items'][0]['reviewPolicy']);
        $t->same(false, $summary['packageSignatures']['items'][0]['canExposeAsDocumentMedia']);
    },
    'reports compact ODT object replacement sidecars as metadata-only package review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $previewBytes = 'PREVIEWPNG';
        $encryptedBytes = '<svg xmlns="http://www.w3.org/2000/svg"/>';
        $invalidBytes = 'replacement-bytes';
        $orphanBytes = 'ORPHANWEBP';
        $replacementEntries =
            '  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="ObjectReplacements/preview.png" manifest:size="' . strlen($previewBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="ObjectReplacements/missing.jpg"/>' . "\n"
            . '  <manifest:file-entry manifest:media-type="image/svg+xml" manifest:full-path="ObjectReplacements/encrypted.svg" manifest:size="' . strlen($encryptedBytes) . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="replacement-checksum"/></manifest:file-entry>' . "\n"
            . '  <manifest:file-entry manifest:media-type="application/octet-stream" manifest:full-path="ObjectReplacements/invalid.bin" manifest:size="' . strlen($invalidBytes) . '"/>' . "\n";
        $manifest = str_replace('</manifest:manifest>', $replacementEntries . '</manifest:manifest>', $manifestXml);

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'ObjectReplacements/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
                ['name' => 'ObjectReplacements/encrypted.svg', 'data' => $encryptedBytes, 'compressionMethod' => 0],
                ['name' => 'ObjectReplacements/invalid.bin', 'data' => $invalidBytes, 'compressionMethod' => 0],
                ['name' => 'ObjectReplacements/orphan.webp', 'data' => $orphanBytes, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $replacements = $summary['packageObjectReplacements'];
        $itemsByPath = [];
        foreach ($replacements['items'] as $item) {
            $itemsByPath[$item['packagePath']] = $item;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory'];

        $t->same(5, $replacements['count']);
        $t->same(3, $replacements['readableCount']);
        $t->same(4, $replacements['declaredCount']);
        $t->same(1, $replacements['undeclaredCount']);
        $t->same(1, $replacements['missingCount']);
        $t->same(1, $replacements['encryptedCount']);
        $t->same(1, $replacements['invalidMediaTypeCount']);
        $t->same(4, $replacements['issueCount']);
        $t->same([
            'odf-object-replacement-encrypted-package-part',
            'odf-object-replacement-invalid-media-type',
            'odf-object-replacement-missing-package-part',
            'odf-object-replacement-undeclared-package-part',
        ], $replacements['issueCodes']);

        $preview = $itemsByPath['ObjectReplacements/preview.png'];
        $t->same('image/png', $preview['mediaType']);
        $t->same(true, $preview['valid']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(sprintf('%08x', crc32($previewBytes)), $preview['crc32']);
        $t->same('object-replacement-package-bytes-blocked', $preview['byteExposurePolicy']);
        $t->same('object-replacement-metadata-only', $preview['reviewPolicy']);

        $missing = $itemsByPath['ObjectReplacements/missing.jpg'];
        $t->same(false, $missing['exists']);
        $t->same(['odf-object-replacement-missing-package-part'], $missing['issues']);
        $t->same('object-replacement-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $itemsByPath['ObjectReplacements/encrypted.svg'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-object-replacement-encrypted-package-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $invalid = $itemsByPath['ObjectReplacements/invalid.bin'];
        $t->same('application/octet-stream', $invalid['mediaType']);
        $t->same(false, $invalid['valid']);
        $t->same(['odf-object-replacement-invalid-media-type'], $invalid['issues']);

        $orphan = $itemsByPath['ObjectReplacements/orphan.webp'];
        $t->same('image/webp', $orphan['mediaType']);
        $t->same(false, $orphan['declared']);
        $t->same(['odf-object-replacement-undeclared-package-part'], $orphan['issues']);

        $t->same(4, $summary['manifestReview']['objectReplacementPackagePartCount']);
        $t->same([
            'ObjectReplacements/preview.png',
            'ObjectReplacements/missing.jpg',
            'ObjectReplacements/encrypted.svg',
            'ObjectReplacements/invalid.bin',
        ], array_column($summary['manifestReview']['objectReplacementPackageItems'], 'path'));
        $t->same(true, $reviewByPath['ObjectReplacements/preview.png']['objectReplacementPackagePart']);
        $t->same(false, $reviewByPath['ObjectReplacements/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['ObjectReplacements/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['ObjectReplacements/preview.png']['storedByteLength']);
        $t->same('object-replacement-package-bytes-blocked', $reviewByPath['ObjectReplacements/preview.png']['byteExposurePolicy']);
        $t->same(true, $reviewByPath['ObjectReplacements/missing.jpg']['objectReplacementPackagePart']);
        $t->same('object-replacement-package-bytes-blocked', $reviewByPath['ObjectReplacements/missing.jpg']['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $reviewByPath['ObjectReplacements/encrypted.svg']['byteExposurePolicy']);

        $t->same(4, $inventory['objectReplacementPartCount']);
        $t->same(4, $inventory['roleCounts']['object-replacement']);
        $t->same(1, $inventory['undeclaredRoleCounts']['object-replacement']);
        $t->same(['object-replacement', 'manifest-declared'], $inventory['parts']['ObjectReplacements/preview.png']['roles']);
        $t->same(['object-replacement', 'manifest-declared'], $inventory['parts']['ObjectReplacements/invalid.bin']['roles']);
        $t->same(['object-replacement', 'undeclared-package-entry'], $inventory['parts']['ObjectReplacements/orphan.webp']['roles']);
        $t->same(true, $inventory['parts']['ObjectReplacements/preview.png']['objectReplacementPackagePart']);
        $t->same(1, count($summary['mediaParts']), 'object replacement sidecars must stay out of document media handoff');
    },
    'reports compact ODT layout-cache sidecar as metadata-only package review data' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $layoutCacheBytes = 'LAYOUT-CACHE-BYTES';
        $manifest = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:media-type="application/binary" manifest:full-path="layout-cache" manifest:size="' . strlen($layoutCacheBytes) . '"/>' . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
            ],
        ))->summarize();
        $layoutCaches = $summary['packageLayoutCaches'];
        $layoutCache = $layoutCaches['items'][0];
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory'];

        $t->same(1, $layoutCaches['count']);
        $t->same(1, $layoutCaches['readableCount']);
        $t->same(1, $layoutCaches['declaredCount']);
        $t->same(0, $layoutCaches['undeclaredCount']);
        $t->same(0, $layoutCaches['missingCount']);
        $t->same(0, $layoutCaches['encryptedCount']);
        $t->same(0, $layoutCaches['invalidMediaTypeCount']);
        $t->same('layout-cache-package-bytes-blocked', $layoutCaches['byteExposurePolicy']);
        $t->same('layout-cache-metadata-only', $layoutCaches['reviewPolicy']);

        $t->same('layout-cache', $layoutCache['packagePath']);
        $t->same('application/binary', $layoutCache['mediaType']);
        $t->same(['application/binary', 'application/octet-stream'], $layoutCache['expectedMediaTypes']);
        $t->same(true, $layoutCache['valid']);
        $t->same(strlen($layoutCacheBytes), $layoutCache['byteLength']);
        $t->same(sprintf('%08x', crc32($layoutCacheBytes)), $layoutCache['crc32']);
        $t->same(false, $layoutCache['canExposeAsDocumentMedia']);
        $t->same('layout-cache-package-bytes-blocked', $layoutCache['byteExposurePolicy']);
        $t->same([], $layoutCache['issues']);

        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same(1, $summary['manifestReview']['layoutCachePartCount']);
        $t->same(true, $reviewByPath['layout-cache']['layoutCachePackagePart']);
        $t->same(false, $reviewByPath['layout-cache']['canExposeBytes']);
        $t->same(null, $reviewByPath['layout-cache']['byteLength']);
        $t->same(strlen($layoutCacheBytes), $reviewByPath['layout-cache']['storedByteLength']);
        $t->same('layout-cache-package-bytes-blocked', $reviewByPath['layout-cache']['byteExposurePolicy']);
        $t->same('layout-cache', $reviewByPath['layout-cache']['manifestMediaFamily']);
        $t->same(1, $summary['manifestReview']['manifestMediaFamilyCounts']['layout-cache']);
        $t->same(1, $inventory['layoutCachePartCount']);
        $t->same(1, $inventory['roleCounts']['layout-cache']);
        $t->same(['layout-cache', 'manifest-declared'], $inventory['parts']['layout-cache']['roles']);
        $t->same(true, $inventory['parts']['layout-cache']['layoutCachePackagePart']);
        $t->same(false, $inventory['parts']['layout-cache']['canExposeBytes']);

        $missing = OpenDocumentPackage::fromPackage($buildOdtPackage(manifest: $manifest))->summarize()['packageLayoutCaches']['items'][0];
        $t->same(false, $missing['exists']);
        $t->same(['odf-layout-cache-missing-package-part'], $missing['issues']);

        $invalidManifest = str_replace('manifest:media-type="application/binary"', 'manifest:media-type="image/png"', $manifest);
        $invalid = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $invalidManifest,
            extraParts: [
                ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
            ],
        ))->summarize()['packageLayoutCaches']['items'][0];
        $t->same(false, $invalid['valid']);
        $t->same(['odf-layout-cache-invalid-media-type'], $invalid['issues']);

        $undeclaredSummary = OpenDocumentPackage::fromPackage($buildOdtPackage(extraParts: [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]))->summarize();
        $undeclared = $undeclaredSummary['packageLayoutCaches']['items'][0];
        $t->same(false, $undeclared['declared']);
        $t->same(true, $undeclared['undeclared']);
        $t->same(['odf-layout-cache-undeclared-package-part'], $undeclared['issues']);
        $t->same(['layout-cache', 'undeclared-package-entry'], $undeclaredSummary['packageInventory']['parts']['layout-cache']['roles']);
    },
    'reports compact ODT RDF metadata sidecars as package review metadata' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $rdfXml = <<<'XML'
<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="https://example.test/ns/wp#"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <rdf:Description rdf:about="content.xml">
    <dc:title xml:lang="en">Reviewed compact ODT body</dc:title>
    <dc:creator rdf:resource="urn:uuid:compact-reviewer"/>
    <wp:review-status>ready</wp:review-status>
  </rdf:Description>
  <rdf:Description rdf:about="Pictures/hero.png">
    <dc:format>image/png</dc:format>
  </rdf:Description>
</rdf:RDF>
XML;
        $invalidRdfXml = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description';
        $encryptedRdfXml = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/>';
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:media-type="application/rdf+xml" manifest:full-path="metadata/encrypted.rdf" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="rdf-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="rdf-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifest = str_replace(
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>',
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>'
            . '<manifest:file-entry manifest:media-type="application/rdf+xml" manifest:full-path="manifest.rdf" manifest:size="' . strlen($rdfXml) . '"/>'
            . '<manifest:file-entry manifest:media-type="application/rdf+xml" manifest:full-path="metadata/invalid.rdf"/>'
            . '<manifest:file-entry manifest:media-type="application/rdf+xml" manifest:full-path="metadata/missing.rdf"/>'
            . $encryptedEntry,
            $manifestXml
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
                ['name' => 'metadata/invalid.rdf', 'data' => $invalidRdfXml, 'compressionMethod' => 0],
                ['name' => 'metadata/encrypted.rdf', 'data' => $encryptedRdfXml, 'compressionMethod' => 0],
                ['name' => 'metadata/orphan/manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
            ],
        ));
        $summary = $odt->summarize();
        $rdf = $summary['rdfMetadata'];
        $document = $odt->readContentDocument();
        $partsByPath = [];
        foreach ($rdf['parts'] as $item) {
            $partsByPath[$item['part']] = $item;
        }
        $triplesByPredicate = [];
        foreach ($partsByPath['manifest.rdf']['triples'] as $triple) {
            $triplesByPredicate[$triple['subject'] . '|' . $triple['predicate']] = $triple;
        }
        $inventory = $summary['packageInventory'];
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }

        $t->same($rdf, $odt->rdfMetadata());
        $t->same($rdf, $document->attr('rdfMetadata'));
        $t->same(5, $rdf['partCount']);
        $t->same(2, $rdf['parsedPartCount']);
        $t->same(1, $rdf['parseErrorCount']);
        $t->same(8, $rdf['tripleCount']);
        $t->same(6, $rdf['literalCount']);
        $t->same(2, $rdf['resourceCount']);
        $t->same(2, $rdf['subjectCount']);
        $t->same(['manifest.rdf', 'metadata/invalid.rdf', 'metadata/missing.rdf', 'metadata/encrypted.rdf', 'metadata/orphan/manifest.rdf'], array_column($rdf['parts'], 'part'));

        $declared = $partsByPath['manifest.rdf'];
        $t->same(true, $declared['declared']);
        $t->same(true, $declared['exists']);
        $t->same(true, $declared['parseable']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('package-rdf-metadata-only', $declared['reviewPolicy']);
        $t->same(strlen($rdfXml), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($rdfXml)), $declared['crc32']);
        $t->same(4, $declared['tripleCount']);
        $t->same(3, $declared['literalCount']);
        $t->same(1, $declared['resourceCount']);
        $t->same('Reviewed compact ODT body', $triplesByPredicate['content.xml|dc:title']['object']);
        $t->same('literal', $triplesByPredicate['content.xml|dc:title']['objectType']);
        $t->same('en', $triplesByPredicate['content.xml|dc:title']['language']);
        $t->same('urn:uuid:compact-reviewer', $triplesByPredicate['content.xml|dc:creator']['object']);
        $t->same('resource', $triplesByPredicate['content.xml|dc:creator']['objectType']);
        $t->same('ready', $triplesByPredicate['content.xml|wp:review-status']['object']);
        $t->same('image/png', $triplesByPredicate['Pictures/hero.png|dc:format']['object']);

        $invalid = $partsByPath['metadata/invalid.rdf'];
        $t->same(true, $invalid['declared']);
        $t->same(false, $invalid['parseable']);
        $t->same('invalid-rdf-xml', $invalid['diagnostic']);
        $t->contains('Unable to parse ODT RDF metadata metadata/invalid.rdf', $invalid['error']);

        $missing = $partsByPath['metadata/missing.rdf'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['parseable']);
        $t->same('missing-rdf-part', $missing['diagnostic']);
        $t->same(null, $missing['byteLength']);

        $encrypted = $partsByPath['metadata/encrypted.rdf'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(false, $encrypted['parseable']);
        $t->same('encrypted-rdf-part', $encrypted['diagnostic']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedRdfXml), $encrypted['storedByteLength']);
        $t->same(sprintf('%08x', crc32($encryptedRdfXml)), $encrypted['storedCrc32']);

        $orphan = $partsByPath['metadata/orphan/manifest.rdf'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(true, $orphan['parseable']);
        $t->same('odf-rdf-package-undeclared-part', $orphan['diagnostic']);
        $t->same(strlen($rdfXml), $orphan['byteLength']);

        $contentSubject = $rdf['subjectsBySubject']['content.xml'];
        $imageSubject = $rdf['subjectsBySubject']['Pictures/hero.png'];
        $t->same(2, $contentSubject['partCount']);
        $t->same(6, $contentSubject['tripleCount']);
        $t->same(4, $contentSubject['literalCount']);
        $t->same(2, $contentSubject['resourceCount']);
        $t->same(['manifest.rdf', 'metadata/orphan/manifest.rdf'], $contentSubject['parts']);
        $t->same(['dc:creator', 'dc:title', 'wp:review-status'], $contentSubject['predicates']);
        $t->same(2, $imageSubject['tripleCount']);
        $t->same(['dc:format'], $imageSubject['predicates']);

        $t->same(4, $summary['manifestReview']['rdfMetadataPartCount']);
        $t->same(['manifest.rdf', 'metadata/invalid.rdf', 'metadata/missing.rdf', 'metadata/encrypted.rdf'], array_column($summary['manifestReview']['rdfMetadataItems'], 'path'));
        $t->same(false, $reviewByPath['manifest.rdf']['canExposeBytes']);
        $t->same('rdf-metadata-bytes-blocked', $reviewByPath['manifest.rdf']['byteExposurePolicy']);
        $t->same(true, $reviewByPath['metadata/missing.rdf']['rdfMetadataPart']);
        $t->same(true, $reviewByPath['metadata/encrypted.rdf']['rdfMetadataPart']);
        $t->same(4, $inventory['rdfMetadataPartCount']);
        $t->same(4, $inventory['roleCounts']['rdf-metadata']);
        $t->same(1, $inventory['undeclaredRoleCounts']['rdf-metadata']);
        $t->same(['rdf-metadata', 'manifest-declared'], $inventory['parts']['manifest.rdf']['roles']);
        $t->same(['rdf-metadata', 'manifest-declared'], $inventory['parts']['metadata/invalid.rdf']['roles']);
        $t->same(['rdf-metadata', 'manifest-declared'], $inventory['parts']['metadata/encrypted.rdf']['roles']);
        $t->same(['rdf-metadata', 'undeclared-package-entry'], $inventory['parts']['metadata/orphan/manifest.rdf']['roles']);
        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same(1, $summary['undeclaredPackageEntryCount']);
        $t->same('metadata/orphan/manifest.rdf', $summary['undeclaredPackageEntries'][0]['path']);
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
    'surfaces compact ODT ZIP package comments as metadata-only provenance' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'comment' => 'manifest review'],
            ['name' => 'content.xml', 'data' => $contentXml, 'comment' => 'body review'],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0, 'comment' => 'media review'],
        ], 'odt package review');

        $summary = OpenDocumentPackage::fromPackage($package)->summarize();
        $inventory = $summary['packageInventory'];
        $comments = $inventory['comments'];
        $content = $inventory['parts']['content.xml'];
        $hero = $inventory['parts']['Pictures/hero.png'];

        $t->same($comments, $package->commentPreflight());
        $t->same($comments, $summary['packageInventory']['comments']);
        $t->same(true, $comments['hasPackageComment']);
        $t->same(true, $comments['hasEntryComments']);
        $t->same(true, $comments['hasComments']);
        $t->same('odt package review', $comments['packageComment']);
        $t->same(strlen('odt package review'), $comments['packageCommentLength']);
        $t->same(3, $comments['entryCommentCount']);
        $t->same(['META-INF/manifest.xml', 'content.xml', 'Pictures/hero.png'], $comments['commentedEntryNames']);
        $t->same(true, $inventory['hasPackageComment']);
        $t->same(true, $inventory['hasEntryComments']);
        $t->same(3, $inventory['entryCommentCount']);
        $t->same(['META-INF/manifest.xml', 'content.xml', 'Pictures/hero.png'], $inventory['commentedEntryNames']);

        $t->same('body review', $content['zipEntryComment']);
        $t->same(strlen('body review'), $content['zipEntryCommentLength']);
        $t->same('utf-8', $content['zipEntryCommentEncoding']);
        $t->same(true, $content['zipEntryHasComment']);
        $t->same([], $content['zipEntryCommentIssues']);
        $t->same('media review', $hero['zipEntryComment']);
        $t->same(true, $hero['zipEntryHasComment']);
        $t->same('package-bytes-exposable', $hero['byteExposurePolicy']);
        $t->same(1, count($summary['mediaParts']));
        $t->same('Pictures/hero.png', $summary['mediaParts'][0]['path']);
        $t->same('odf-package-inventory-metadata-only', $inventory['byteExposurePolicy']);
        $t->same(false, $inventory['canExposeBytes']);
    },
    'surfaces compact ODT ZIP platform attributes as metadata-only provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithPlatformParts = str_replace(
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>',
            '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>'
            . "\n  "
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/executable.png"/>'
            . "\n  "
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hidden.png"/>'
            . "\n  "
            . '<manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/text-attr.png"/>',
            $manifestXml
        );
        $package = $buildOdtPackage(
            manifest: $manifestWithPlatformParts,
            extraParts: [
                ['name' => 'Pictures/executable.png', 'data' => 'EXECPNG', 'compressionMethod' => 0, 'externalAttributes' => 0x81ed0000],
                ['name' => 'Pictures/hidden.png', 'data' => 'HIDDENPNG', 'compressionMethod' => 0, 'creatorHostSystem' => 10, 'externalAttributes' => 0x00000022],
                ['name' => 'Pictures/text-attr.png', 'data' => 'TEXTPNG', 'compressionMethod' => 0, 'externalAttributes' => 0x81a40000, 'internalAttributes' => 0x0001],
            ],
        );

        $summary = OpenDocumentPackage::fromPackage($package)->summarize();
        $inventory = $summary['packageInventory'];
        $identity = $summary['packageIdentity'];
        $parts = $inventory['parts'];
        $identityParts = [];
        foreach ($identity['packageEntries'] as $packageEntry) {
            $identityParts[$packageEntry['path']] = $packageEntry;
        }
        $hostSystemsByName = [];
        foreach ($inventory['creatorHostSystems']['hostSystems'] as $hostSystem) {
            $hostSystemsByName[$hostSystem['name']] = $hostSystem;
        }

        $t->same($package->platformMetadataPreflight(), $inventory['platformMetadata']);
        $t->same($package->permissionPreflight(), $inventory['permissions']);
        $t->same($package->creatorHostSystemPreflight(), $inventory['creatorHostSystems']);
        $t->same($package->dosAttributePreflight(), $inventory['dosAttributes']);
        $t->same($package->internalAttributePreflight(), $inventory['internalAttributes']);
        $t->same(0, $inventory['platformMetadataEntryCount']);
        $t->same(9, $inventory['knownCreatorHostSystemEntryCount']);
        $t->same(0, $inventory['unknownCreatorHostSystemEntryCount']);
        $t->same(0, $inventory['creatorVersionBelowNeededEntryCount']);
        $t->same(2, $inventory['unixModeEntryCount']);
        $t->same(1, $inventory['executableFileCount']);
        $t->same(0, $inventory['writablePermissionEntryCount']);
        $t->same(1, $inventory['dosAttributeEntryCount']);
        $t->same(1, $inventory['hiddenSystemOrVolumeLabelEntryCount']);
        $t->same(1, $inventory['internalAttributeEntryCount']);
        $t->same(8, $hostSystemsByName['unix']['entryCount']);
        $t->same(1, $hostSystemsByName['windows-ntfs']['entryCount']);
        $t->same(2, $identity['unixModeEntryCount']);
        $t->same(1, $identity['executableFileCount']);
        $t->same(1, $identity['dosAttributeEntryCount']);
        $t->same(1, $identity['internalAttributeEntryCount']);

        $executable = $parts['Pictures/executable.png'];
        $hidden = $parts['Pictures/hidden.png'];
        $textAttribute = $parts['Pictures/text-attr.png'];
        $identityExecutable = $identityParts['Pictures/executable.png'];
        $identityHidden = $identityParts['Pictures/hidden.png'];
        $identityTextAttribute = $identityParts['Pictures/text-attr.png'];

        $t->same('package-bytes-exposable', $executable['byteExposurePolicy']);
        $t->same(true, $executable['canExposeBytes']);
        $t->same(3, $executable['madeByHostSystem']);
        $t->same('unix', $executable['madeByHostSystemName']);
        $t->same(0x81ed0000, $executable['externalAttributes']);
        $t->same('81ed0000', $executable['externalAttributesHex']);
        $t->same(true, $executable['hasExternalAttributes']);
        $t->same(0100755, $executable['unixMode']);
        $t->same('100755', $executable['unixModeOctal']);
        $t->same(0755, $executable['unixPermissions']);
        $t->same('0755', $executable['unixPermissionsOctal']);
        $t->same(true, $executable['hasUnixMode']);
        $t->same('regular-file', $executable['unixFileTypeName']);
        $t->same(true, $executable['isUnixExecutableFile']);
        $t->same(['unix-executable-file'], $executable['platformAttributeIssues']);
        $t->same(true, $executable['hasPlatformAttributeProvenance']);
        $t->same('81ed0000', $identityExecutable['externalAttributesHex']);
        $t->same('100755', $identityExecutable['unixModeOctal']);
        $t->same(true, $identityExecutable['isUnixExecutableFile']);
        $t->same(['unix-executable-file'], $identityExecutable['platformAttributeIssues']);

        $t->same(10, $hidden['madeByHostSystem']);
        $t->same('windows-ntfs', $hidden['madeByHostSystemName']);
        $t->same(0x00000022, $hidden['externalAttributes']);
        $t->same('00000022', $hidden['externalAttributesHex']);
        $t->same(0x22, $hidden['dosAttributes']);
        $t->same(['hidden', 'archive'], $hidden['dosAttributeNames']);
        $t->same(true, $hidden['hasDosHiddenAttribute']);
        $t->same(true, $hidden['hasDosArchiveAttribute']);
        $t->same(false, $hidden['hasUnixMode']);
        $t->same(['dos-hidden-attribute'], $hidden['platformAttributeIssues']);
        $t->same(true, $hidden['hasPlatformAttributeProvenance']);
        $t->same('package-bytes-exposable', $hidden['byteExposurePolicy']);
        $t->same('windows-ntfs', $identityHidden['madeByHostSystemName']);
        $t->same('00000022', $identityHidden['externalAttributesHex']);
        $t->same(['hidden', 'archive'], $identityHidden['dosAttributeNames']);
        $t->same(['dos-hidden-attribute'], $identityHidden['platformAttributeIssues']);

        $t->same(0x0001, $textAttribute['internalFileAttributes']);
        $t->same('0001', $textAttribute['internalFileAttributesHex']);
        $t->same(['apparently-text'], $textAttribute['internalAttributeNames']);
        $t->same(true, $textAttribute['hasTextInternalAttribute']);
        $t->same(false, $textAttribute['hasUnknownInternalAttributeBits']);
        $t->same(0100644, $textAttribute['unixMode']);
        $t->same('100644', $textAttribute['unixModeOctal']);
        $t->same('0644', $textAttribute['unixPermissionsOctal']);
        $t->same(false, $textAttribute['isUnixExecutableFile']);
        $t->same(['internal-text-attribute'], $textAttribute['platformAttributeIssues']);
        $t->same(true, $textAttribute['hasPlatformAttributeProvenance']);
        $t->same('package-bytes-exposable', $textAttribute['byteExposurePolicy']);
        $t->same('odf-package-inventory-metadata-only', $inventory['byteExposurePolicy']);
        $t->same(false, $inventory['canExposeBytes']);
        $t->same('0001', $identityTextAttribute['internalFileAttributesHex']);
        $t->same(['apparently-text'], $identityTextAttribute['internalAttributeNames']);
        $t->same(['internal-text-attribute'], $identityTextAttribute['platformAttributeIssues']);
    },
    'summarizes compact ODT XML document part versions for package review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithSettings = str_replace(
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>',
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml?rev=7"/>',
            $manifestXml
        );
        $manifestWithSettings = str_replace(
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>',
            '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="settings.xml"/>',
            $manifestWithSettings
        );
        $contentWithVersion = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body><office:text><text:p>Versioned compact packet.</text:p></office:text></office:body>
</office:document-content>
XML;
        $stylesWithVersionMismatch = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:wp="urn:wordpress:review"
  office:version="1.2"
  wp:origin="style-review">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;
        $metaWithoutVersion = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;
        $settingsWithVersionMismatch = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.4">
  <office:settings/>
</office:document-settings>
XML;

        $summary = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifestWithSettings,
            content: $contentWithVersion,
            styles: $stylesWithVersionMismatch,
            meta: $metaWithoutVersion,
            extraParts: [['name' => 'settings.xml', 'data' => $settingsWithVersionMismatch, 'compressionMethod' => 0]]
        ))->summarize();
        $report = $summary['documentPartVersions'];
        $versionsByPart = [];
        foreach ($report['items'] as $item) {
            $versionsByPart[$item['part']] = $item;
        }

        $t->same('1.3', $report['manifestVersion']);
        $t->same(4, $report['count']);
        $t->same(3, $report['versionedCount']);
        $t->same(1, $report['missingVersionCount']);
        $t->same(['meta.xml'], $report['missingVersionParts']);
        $t->same(2, $report['versionMismatchCount']);
        $t->same([
            ['part' => 'styles.xml', 'officeVersion' => '1.2', 'manifestVersion' => '1.3'],
            ['part' => 'settings.xml', 'officeVersion' => '1.4', 'manifestVersion' => '1.3'],
        ], $report['versionMismatches']);
        $t->same(['1.2' => 1, '1.3' => 1, '1.4' => 1], $report['versionCounts']);
        $t->same(['package-bytes-exposable' => 4], $report['byteExposurePolicyCounts']);

        $t->same(1, $report['manifestPartReferenceSuffixCount']);
        $t->same(1, $report['manifestPartReferenceQueryCount']);
        $t->same(0, $report['manifestPartReferenceFragmentCount']);
        $t->same(0, $report['manifestUriEncodedPackageReferenceCount']);
        $t->same('content.xml?rev=7', $report['manifestPartReferenceSuffixItems'][0]['manifestFullPath']);
        $t->same('content.xml', $report['manifestPartReferenceSuffixItems'][0]['manifestPathReference']);
        $t->same('rev=7', $report['manifestPartReferenceSuffixItems'][0]['manifestPathQuery']);

        $content = $versionsByPart['content.xml'];
        $t->same('document-content', $content['rootName']);
        $t->same(true, $content['validRoot']);
        $t->same('1.3', $content['officeVersion']);
        $t->same('content.xml?rev=7', $content['manifestFullPath']);
        $t->same('content.xml', $content['manifestPackagePath']);
        $t->same('rev=7', $content['manifestPathQuery']);
        $t->same(['office:version'], $content['rootAttributeNames']);
        $t->same(0, $content['rootCustomAttributeCount']);
        $t->same([], $content['diagnostics']);

        $styles = $versionsByPart['styles.xml'];
        $t->same('1.2', $styles['officeVersion']);
        $t->same(['office:version', 'wp:origin'], $styles['rootAttributeNames']);
        $t->same(1, $styles['rootCustomAttributeCount']);
        $t->same(['wp:origin'], $styles['rootCustomAttributeNames']);
        $t->same('style-review', $styles['rootCustomAttributeMap']['wp:origin']);
        $t->same(['odf-xml-part-version-mismatch'], $styles['diagnostics']);

        $t->same(1, $report['rootCustomAttributePartCount']);
        $t->same(1, $report['rootCustomAttributeCount']);
        $t->same(['wp:origin'], $report['rootCustomAttributeNames']);
        $t->same('styles.xml', $report['rootCustomAttributeItems'][0]['part']);

        $meta = $versionsByPart['meta.xml'];
        $t->same(null, $meta['officeVersion']);
        $t->same(['odf-xml-part-missing-office-version'], $meta['diagnostics']);

        $settings = $versionsByPart['settings.xml'];
        $t->same('1.4', $settings['officeVersion']);
        $t->same('settings.xml', $settings['manifestFullPath']);
        $t->same(sprintf('%08x', crc32($settingsWithVersionMismatch)), $settings['crc32']);
        $t->same(['odf-xml-part-version-mismatch'], $settings['diagnostics']);
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
