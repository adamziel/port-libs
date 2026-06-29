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
  <office:body><office:text><text:p>Document part media type review.</text:p></office:text></office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:styles/>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:meta/>
</office:document-meta>
XML;

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:settings/>
</office:document-settings>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="application/xml;charset=UTF-8"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="application/vnd.oasis.opendocument.meta+xml"/>
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/plain"/>
</manifest:manifest>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'settings.xml', 'data' => $settingsXml],
], 'odt document part manifest media type review');

$indexByPart = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[$item['part']] = $item;
    }

    return $indexed;
};

return [
    'flags non XML manifest media types for ODT core document parts' => static function (TestRunner $t) use ($buildPackage, $indexByPart): void {
        $richReport = (new OdfReader())->readPackage($buildPackage())['documentPartVersions'];
        $compactReport = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['documentPartVersions'];

        foreach ([$richReport, $compactReport] as $report) {
            $itemsByPart = $indexByPart($report['items']);
            $mismatchesByPart = $indexByPart($report['manifestMediaTypeMismatches']);

            $t->same(4, $report['count']);
            $t->same(2, $report['manifestMediaTypeMismatchCount']);
            $t->same(['content.xml', 'settings.xml'], array_column($report['manifestMediaTypeMismatches'], 'part'));

            $t->same(true, $itemsByPart['content.xml']['manifestMediaTypeMismatch']);
            $t->same(false, $itemsByPart['content.xml']['manifestMediaTypeValid']);
            $t->same('xml', $itemsByPart['content.xml']['expectedManifestMediaTypeFamily']);
            $t->same('image/png', $itemsByPart['content.xml']['manifestMediaType']);
            $t->same('image/png', $itemsByPart['content.xml']['manifestMediaTypeBase']);
            $t->same(true, in_array('odf-xml-part-manifest-media-type-mismatch', $itemsByPart['content.xml']['diagnostics'], true));

            $t->same(true, $itemsByPart['settings.xml']['manifestMediaTypeMismatch']);
            $t->same(false, $itemsByPart['settings.xml']['manifestMediaTypeValid']);
            $t->same('text/plain', $itemsByPart['settings.xml']['manifestMediaTypeBase']);
            $t->same(true, in_array('odf-xml-part-manifest-media-type-mismatch', $itemsByPart['settings.xml']['diagnostics'], true));

            $t->same(false, $itemsByPart['styles.xml']['manifestMediaTypeMismatch']);
            $t->same(true, $itemsByPart['styles.xml']['manifestMediaTypeValid']);
            $t->same('application/xml', $itemsByPart['styles.xml']['manifestMediaTypeBase']);
            $t->same(false, in_array('odf-xml-part-manifest-media-type-mismatch', $itemsByPart['styles.xml']['diagnostics'], true));

            $t->same(false, $itemsByPart['meta.xml']['manifestMediaTypeMismatch']);
            $t->same(true, $itemsByPart['meta.xml']['manifestMediaTypeValid']);
            $t->same('application/vnd.oasis.opendocument.meta+xml', $itemsByPart['meta.xml']['manifestMediaTypeBase']);
            $t->same(false, in_array('odf-xml-part-manifest-media-type-mismatch', $itemsByPart['meta.xml']['diagnostics'], true));

            $t->same('xml', $mismatchesByPart['content.xml']['expectedManifestMediaTypeFamily']);
            $t->same('image/png', $mismatchesByPart['content.xml']['manifestMediaTypeBase']);
            $t->same('text/plain', $mismatchesByPart['settings.xml']['manifestMediaTypeBase']);
        }
    },
];
