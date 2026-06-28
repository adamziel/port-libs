<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
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
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:font-face-decls>
    <style:font-face style:name="ReviewSans" svg:font-family="Review Sans"/>
  </office:font-face-decls>
  <office:automatic-styles>
    <style:page-layout style:name="pmReview">
      <style:page-layout-properties fo:page-width="8.5in" fo:page-height="11in"/>
    </style:page-layout>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="ReviewBody" style:family="paragraph"/>
    <text:list-style style:name="ReviewList">
      <text:list-level-style-number text:level="1" style:num-format="1"/>
    </text:list-style>
  </office:styles>
  <office:master-styles>
    <style:master-page style:name="ReviewPage" style:page-layout-name="pmReview"/>
  </office:master-styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0">
  <office:automatic-styles>
    <style:style style:name="AutoParagraph" style:family="paragraph" style:parent-style-name="ReviewBody"/>
    <style:page-layout style:name="pmContentAuto"/>
    <number:number-style style:name="ContentNumber">
      <number:number number:min-integer-digits="2"/>
    </number:number-style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:p text:style-name="AutoParagraph">Style package provenance packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;

return [
    'summarizes ODT style and master-page package provenance without exposing style bytes' => static function (TestRunner $t) use ($manifestXml, $stylesXml, $contentXml, $metaXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
        ], 'odt style package provenance'));

        $provenance = $result['importReport']['manifest']['packageProvenance']['stylePackageProvenance'];
        $itemsByPart = [];
        foreach ($provenance['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']['stylePackageProvenance']);
        $t->same($provenance, $result['document']->attr('packageStyles'));
        $t->same($provenance, $result['metadata']['odfPackageStyles']);
        $t->same($provenance, $result['packageStyles']);
        $t->same($provenance, $result['importReport']['packageStyles']);
        $t->same(2, $provenance['count']);
        $t->same(8, $provenance['definitionCount']);
        $t->same(4, $provenance['automaticStyleDefinitionCount']);
        $t->same(['AutoParagraph', 'ContentNumber', 'pmContentAuto', 'pmReview'], $provenance['automaticStyleDefinitionNames']);
        $t->same([
            [
                'name' => 'ContentNumber',
                'definitionType' => 'dataStyles',
                'sourcePart' => 'content.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
            [
                'name' => 'pmContentAuto',
                'definitionType' => 'pageLayouts',
                'sourcePart' => 'content.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
            [
                'name' => 'AutoParagraph',
                'definitionType' => 'styles',
                'sourcePart' => 'content.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
            [
                'name' => 'pmReview',
                'definitionType' => 'pageLayouts',
                'sourcePart' => 'styles.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
        ], $provenance['automaticStyleDefinitions']);
        $t->same(1, $provenance['masterStyleDefinitionCount']);
        $t->same(['content.xml', 'styles.xml'], $provenance['sourceParts']);
        $t->same([
            'dataStyles' => 1,
            'fontFaces' => 1,
            'listStyles' => 1,
            'masterPages' => 1,
            'pageLayouts' => 2,
            'styles' => 2,
            'tableTemplates' => 0,
        ], $provenance['definitionTypeCounts']);
        $t->same([
            'office:automatic-styles' => 4,
            'office:font-face-decls' => 1,
            'office:master-styles' => 1,
            'office:styles' => 2,
        ], $provenance['sourceContainerCounts']);
        $t->same('odf-style-package-provenance-metadata-only', $provenance['byteExposurePolicy']);
        $t->same(false, $provenance['canExposeBytes']);

        $content = $itemsByPart['content.xml'];
        $t->same(['AutoParagraph'], $content['styleNames']);
        $t->same(['ContentNumber'], $content['dataStyleNames']);
        $t->same(['pmContentAuto'], $content['pageLayoutNames']);
        $t->same(3, $content['automaticStyleCount']);
        $t->same(['AutoParagraph', 'ContentNumber', 'pmContentAuto'], $content['automaticStyleNames']);
        $t->same([
            [
                'name' => 'ContentNumber',
                'definitionType' => 'dataStyles',
                'sourcePart' => 'content.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
            [
                'name' => 'pmContentAuto',
                'definitionType' => 'pageLayouts',
                'sourcePart' => 'content.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
            [
                'name' => 'AutoParagraph',
                'definitionType' => 'styles',
                'sourcePart' => 'content.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
        ], $content['automaticStyleDefinitions']);
        $t->same(['office:automatic-styles' => 3], $content['sourceContainerCounts']);
        $t->same('package-bytes-exposable', $content['packageByteExposurePolicy']);
        $t->same('odf-style-package-provenance-metadata-only', $content['byteExposurePolicy']);
        $t->same(false, $content['canExposeBytes']);
        $t->same(strlen($contentXml), $content['storedByteLength']);

        $styles = $itemsByPart['styles.xml'];
        $t->same(['ReviewBody'], $styles['styleNames']);
        $t->same(['ReviewSans'], $styles['fontFaceNames']);
        $t->same(['ReviewList'], $styles['listStyleNames']);
        $t->same(['pmReview'], $styles['pageLayoutNames']);
        $t->same(['ReviewPage'], $styles['masterPageNames']);
        $t->same(1, $styles['automaticStyleCount']);
        $t->same(['pmReview'], $styles['automaticStyleNames']);
        $t->same([
            [
                'name' => 'pmReview',
                'definitionType' => 'pageLayouts',
                'sourcePart' => 'styles.xml',
                'sourceContainer' => 'office:automatic-styles',
            ],
        ], $styles['automaticStyleDefinitions']);
        $t->same(1, $styles['masterStyleCount']);
        $t->same([
            'office:automatic-styles' => 1,
            'office:font-face-decls' => 1,
            'office:master-styles' => 1,
            'office:styles' => 2,
        ], $styles['sourceContainerCounts']);
        $t->same('package-bytes-exposable', $styles['packageByteExposurePolicy']);
        $t->same('odf-style-package-provenance-metadata-only', $styles['byteExposurePolicy']);
        $t->same(false, $styles['canExposeBytes']);
        $t->same(strlen($stylesXml), $styles['storedByteLength']);

        $t->same('content.xml', $result['styles']['AutoParagraph']['sourcePart']);
        $t->same('office:automatic-styles', $result['styles']['AutoParagraph']['sourceContainer']);
        $t->same(true, $result['styles']['AutoParagraph']['automaticStyle']);
        $t->same('styles.xml', $result['masterPages']['ReviewPage']['sourcePart']);
        $t->same('office:master-styles', $result['masterPages']['ReviewPage']['sourceContainer']);
        $t->same(true, $result['masterPages']['ReviewPage']['masterStyle']);
    },
];
