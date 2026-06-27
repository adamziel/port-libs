<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body><office:text><text:p>Reference provenance packet.</text:p></office:text></office:body>
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

$contentSize = strlen($contentXml);
$settingsSize = strlen($settingsXml);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content%2Exml?draft=1#body" manifest:media-type="text/xml;charset=UTF-8" manifest:size="{$contentSize}"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="settings%2Exml?source=template#view" manifest:media-type="text/xml" manifest:size="{$settingsSize}"/>
</manifest:manifest>
XML;

return [
    'preserves ODT core XML manifest reference suffixes in document part version metadata' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml, $settingsXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0],
        ], 'odt document part manifest reference provenance'));
        $report = $result['importReport']['manifest']['documentPartVersions'];
        $itemsByPart = [];
        foreach ($report['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }

        $t->same($report, $result['document']->attr('manifest')['documentPartVersions']);
        $t->same($report, $result['documentPartVersions']);
        $t->same(4, $report['count']);
        $t->same(2, $report['manifestPartReferenceSuffixCount']);
        $t->same(2, $report['manifestPartReferenceQueryCount']);
        $t->same(2, $report['manifestPartReferenceFragmentCount']);
        $t->same(2, $report['manifestUriEncodedPartReferenceCount']);
        $t->same(['package-bytes-exposable' => 4], $report['byteExposurePolicyCounts']);
        $t->same([
            [
                'part' => 'content.xml',
                'manifestFullPath' => 'content%2Exml?draft=1#body',
                'manifestPartReference' => 'content%2Exml',
                'manifestPartSuffix' => '?draft=1#body',
                'manifestPartQuery' => 'draft=1',
                'manifestPartFragment' => 'body',
                'manifestUriEncodedPartReference' => true,
            ],
            [
                'part' => 'settings.xml',
                'manifestFullPath' => 'settings%2Exml?source=template#view',
                'manifestPartReference' => 'settings%2Exml',
                'manifestPartSuffix' => '?source=template#view',
                'manifestPartQuery' => 'source=template',
                'manifestPartFragment' => 'view',
                'manifestUriEncodedPartReference' => true,
            ],
        ], $report['manifestPartReferenceSuffixItems']);

        $content = $itemsByPart['content.xml'];
        $t->same(1, $content['manifestIndex']);
        $t->same('content%2Exml?draft=1#body', $content['manifestFullPath']);
        $t->same('content%2Exml', $content['manifestPartReference']);
        $t->same('?draft=1#body', $content['manifestPartSuffix']);
        $t->same('draft=1', $content['manifestPartQuery']);
        $t->same('body', $content['manifestPartFragment']);
        $t->same(true, $content['manifestUriEncodedPartReference']);
        $t->same('text/xml;charset=UTF-8', $content['manifestMediaType']);
        $t->same('text/xml', $content['manifestMediaTypeBase']);
        $t->same(true, $content['manifestMediaTypeHasParameters']);
        $t->same([
            [
                'name' => 'charset',
                'value' => 'UTF-8',
                'raw' => 'charset=UTF-8',
            ],
        ], $content['manifestMediaTypeParameters']);
        $t->same(['charset' => 'UTF-8'], $content['manifestMediaTypeParameterMap']);
        $t->same(strlen($contentXml), $content['manifestDeclaredSize']);
        $t->same((string) strlen($contentXml), $content['manifestDeclaredSizeRaw']);
        $t->same(true, $content['manifestDeclaredSizeValid']);
        $t->same(false, $content['manifestDeclaredSizeInvalid']);
        $t->same(false, $content['manifestDeclaredSizeMismatch']);
        $t->same(true, $content['canExposeBytes']);
        $t->same('package-bytes-exposable', $content['byteExposurePolicy']);
        $t->same(strlen($contentXml), $content['byteLength']);
        $t->same(sprintf('%08x', crc32($contentXml)), $content['crc32']);

        $settings = $itemsByPart['settings.xml'];
        $t->same('settings%2Exml?source=template#view', $settings['manifestFullPath']);
        $t->same('settings%2Exml', $settings['manifestPartReference']);
        $t->same('source=template', $settings['manifestPartQuery']);
        $t->same('view', $settings['manifestPartFragment']);
        $t->same(true, $settings['manifestUriEncodedPartReference']);
        $t->same(strlen($settingsXml), $settings['manifestDeclaredSize']);
        $t->same('package-bytes-exposable', $settings['byteExposurePolicy']);

        $provenanceOrder = $result['importReport']['manifest']['packageProvenance']['manifestFileEntryOrder'];
        $t->same($content['manifestPartReference'], $provenanceOrder[1]['partReference']);
        $t->same($content['manifestPartQuery'], $provenanceOrder[1]['partQuery']);
        $t->same($settings['manifestPartFragment'], $provenanceOrder[4]['partFragment']);
    },
];
