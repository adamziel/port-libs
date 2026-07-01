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
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body><office:text><text:p>XML declaration packet.</text:p></office:text></office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<?xml version='1.0' encoding='ISO-8859-1' standalone='yes'?>
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles><style:style style:name="BodyText" style:family="paragraph"/></office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta><dc:title>XML Declaration Packet</dc:title></office:meta>
</office:document-meta>
XML;

$settingsXml = <<<'XML'
<?xml version="1.0" encoding="windows-1252" standalone="no"?>
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0"
  office:version="1.3">
  <office:settings><config:config-item-set config:name="ooo:view-settings"/></office:settings>
</office:document-settings>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'settings.xml', 'data' => $settingsXml],
], 'odt document part xml declarations');

$indexByPart = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[$item['part']] = $item;
    }

    return $indexed;
};

return [
    'preserves ODT document part XML declaration provenance' => static function (TestRunner $t) use ($buildPackage, $indexByPart): void {
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richReport = $richResult['documentPartVersions'];
        $compactReport = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['documentPartVersions'];

        $t->same($richReport, $richResult['importReport']['manifest']['documentPartVersions']);
        $t->same($richReport, $richResult['document']->attr('manifest')['documentPartVersions']);

        foreach ([$richReport, $compactReport] as $report) {
            $itemsByPart = $indexByPart($report['items']);
            $declarationsByPart = $indexByPart($report['xmlDeclarationItems']);

            $t->same(3, $report['xmlDeclarationPartCount']);
            $t->same(8, $report['xmlDeclarationAttributeCount']);
            $t->same(['1.0' => 3], $report['xmlDeclarationVersionCounts']);
            $t->same([
                'ISO-8859-1' => 1,
                'UTF-8' => 1,
                'windows-1252' => 1,
            ], $report['xmlDeclarationEncodingCounts']);
            $t->same([
                'no' => 1,
                'omitted' => 1,
                'yes' => 1,
            ], $report['xmlDeclarationStandaloneCounts']);
            $t->same(['content.xml', 'styles.xml', 'settings.xml'], array_column($report['xmlDeclarationItems'], 'part'));

            $t->same(true, $itemsByPart['content.xml']['xmlDeclarationPresent']);
            $t->same('1.0', $itemsByPart['content.xml']['xmlDeclarationVersion']);
            $t->same('UTF-8', $itemsByPart['content.xml']['xmlDeclarationEncoding']);
            $t->same(null, $itemsByPart['content.xml']['xmlDeclarationStandalone']);
            $t->same(2, $itemsByPart['content.xml']['xmlDeclarationAttributeCount']);

            $t->same(true, $itemsByPart['styles.xml']['xmlDeclarationPresent']);
            $t->same('ISO-8859-1', $itemsByPart['styles.xml']['xmlDeclarationEncoding']);
            $t->same(true, $itemsByPart['styles.xml']['xmlDeclarationStandalone']);
            $t->same(3, $itemsByPart['styles.xml']['xmlDeclarationAttributeCount']);

            $t->same(true, $itemsByPart['settings.xml']['xmlDeclarationPresent']);
            $t->same('windows-1252', $itemsByPart['settings.xml']['xmlDeclarationEncoding']);
            $t->same(false, $itemsByPart['settings.xml']['xmlDeclarationStandalone']);
            $t->same(3, $itemsByPart['settings.xml']['xmlDeclarationAttributeCount']);

            $t->same(false, $itemsByPart['meta.xml']['xmlDeclarationPresent']);
            $t->same(null, $itemsByPart['meta.xml']['xmlDeclarationVersion']);
            $t->same(null, $itemsByPart['meta.xml']['xmlDeclarationEncoding']);
            $t->same(null, $itemsByPart['meta.xml']['xmlDeclarationStandalone']);
            $t->same(0, $itemsByPart['meta.xml']['xmlDeclarationAttributeCount']);

            $t->same('document-content', $declarationsByPart['content.xml']['rootName']);
            $t->same('document-styles', $declarationsByPart['styles.xml']['rootName']);
            $t->same('document-settings', $declarationsByPart['settings.xml']['rootName']);
            $t->same(false, isset($declarationsByPart['meta.xml']));
        }

        $encodedReports = json_encode([$richReport, $compactReport]);
        $t->true(is_string($encodedReports), 'XML declaration metadata should encode for review');
        $t->true(!str_contains((string) $encodedReports, '<?xml'), 'raw XML declaration bytes should not be exposed');
    },
];
