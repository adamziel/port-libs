<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"/>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Source metadata <text:title/> by <text:author-name/> created at <text:creation-time/> tagged <text:keywords/> custom <text:user-defined text:name="wp-source-id"/> approved <text:user-defined text:name="approved"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>WordPress ODT source packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <meta:creation-date>2026-06-05T09:30:15Z</meta:creation-date>
    <meta:keyword>odt</meta:keyword>
    <meta:keyword>metadata</meta:keyword>
    <meta:user-defined meta:name="wp-source-id" meta:value-type="string">packet-42</meta:user-defined>
    <meta:user-defined meta:name="approved" meta:value-type="boolean" meta:boolean-value="true"/>
  </office:meta>
</office:document-meta>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
]);

$result = (new OdfReader())->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (in_array('--self-test', $argv, true)) {
    if (($result['metadata']['title'] ?? '') !== 'WordPress ODT source packet') {
        throw new RuntimeException('Expected ODT meta.xml title to be parsed');
    }
    if (($result['metadata']['creationTime'] ?? '') !== 'PT09H30M15S') {
        throw new RuntimeException('Expected ODT meta.xml creation timestamp to feed creation-time fields');
    }
    if (($result['importReport']['content']['fieldCount'] ?? 0) !== 6) {
        throw new RuntimeException('Expected empty ODT metadata fields to be counted after fallback');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-title" data-odf-field-type="title" data-odf-field-string-value="WordPress ODT source packet" data-odf-field-metadata-source="meta.xml">WordPress ODT source packet</span>')) {
        throw new RuntimeException('Expected empty title field to render from meta.xml');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-user-defined" data-odf-field-type="user-defined" data-odf-field-name="approved" data-odf-field-value-type="boolean" data-odf-field-boolean-value="true" data-odf-field-metadata-source="meta.xml">true</span>')) {
        throw new RuntimeException('Expected typed user-defined metadata field to render from meta.xml');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-creation-time" data-odf-field-type="creation-time" data-odf-field-time-value="PT09H30M15S" data-odf-field-metadata-source="meta.xml">PT09H30M15S</span>')) {
        throw new RuntimeException('Expected empty creation-time field to render from meta.xml creation timestamp');
    }

    echo "odf metadata field handoff self-test ok\n";
    return;
}

echo "ODF metadata field handoff:\n";
echo $blocks;
