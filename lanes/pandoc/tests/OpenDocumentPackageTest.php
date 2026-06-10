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

    if (!$mimetypeFirst) {
        $parts = [$parts[1], $parts[0], $parts[2], $parts[3], $parts[4], $parts[5]];
    }

    foreach ($extraParts as $part) {
        $parts[] = $part;
    }

    return ZipPackage::fromParts($parts, 'odt package');
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
        $t->same([['path' => 'Pictures/hero.png', 'mediaType' => 'image/png']], $summary['mediaParts']);
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
    'reports compact ODT manifest package review gaps without exposing undeclared media' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifest = str_replace(
            'manifest:full-path="Pictures/hero.png" manifest:size="7"',
            'manifest:full-path="Pictures/hero.png" manifest:size="99"',
            $manifestXml
        );
        $manifest = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="Pictures/missing.jpg" manifest:size="123"/>' . "\n" . '</manifest:manifest>',
            $manifest
        );

        $odt = OpenDocumentPackage::fromPackage($buildOdtPackage(
            manifest: $manifest,
            extraParts: [
                ['name' => 'Pictures/orphan.png', 'data' => 'ORPHANPNG'],
                ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<events/>', 'compressionMethod' => 0],
            ]
        ));
        $summary = $odt->summarize();
        $review = $summary['manifestReview'];

        $t->same(6, $review['count']);
        $t->same(8, $review['packageEntryCount']);
        $t->same(1, $review['missingItemCount']);
        $t->same('Pictures/missing.jpg', $review['missingItems'][0]['part']);
        $t->same('image/jpeg', $review['missingItems'][0]['mediaType']);
        $t->same(123, $review['missingItems'][0]['declaredSize']);
        $t->same(false, $review['missingItems'][0]['encrypted']);
        $t->same('odf-manifest-missing-package-entry', $review['missingItems'][0]['diagnostic']);
        $t->same(2, $review['undeclaredEntryCount']);
        $t->same('Pictures/orphan.png', $review['undeclaredEntries'][0]['part']);
        $t->same('odf-manifest-undeclared-package-entry', $review['undeclaredEntries'][0]['diagnostic']);
        $t->same(9, $review['undeclaredEntries'][0]['byteLength']);
        $t->same(sprintf('%08x', crc32('ORPHANPNG')), $review['undeclaredEntries'][0]['crc32']);
        $t->same('Configurations2/accelerator/current.xml', $review['undeclaredEntries'][1]['part']);
        $t->same(0, $review['undeclaredEntries'][1]['compressionMethod']);
        $t->same(1, $review['declaredSizeMismatchCount']);
        $t->same('Pictures/hero.png', $review['declaredSizeMismatches'][0]['part']);
        $t->same(99, $review['declaredSizeMismatches'][0]['declaredSize']);
        $t->same(7, $review['declaredSizeMismatches'][0]['byteLength']);
        $t->same(['Pictures/hero.png', 'Pictures/missing.jpg'], array_column($summary['mediaParts'], 'path'));
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
