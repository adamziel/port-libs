<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type='image/png; profile="cover;hero"; codec="png\"alpha"; quality=review' manifest:full-path="Pictures/hero.png" manifest:size="7"/>
  <manifest:file-entry manifest:media-type='audio/ogg; codecs="opus;vorbis"; variant=teaser' manifest:full-path="Media/theme.ogg" manifest:size="8"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p><draw:frame draw:name="hero"><draw:image xlink:href="Pictures/hero.png"/></draw:frame></text:p>
      <text:p><draw:frame draw:name="theme"><draw:object xlink:href="Media/theme.ogg"/></draw:frame></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Parameterized Manifest Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 8],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Media/theme.ogg', 'data' => 'OGGDATA!', 'compressionMethod' => 0],
];

return [
    'preserves ODF manifest parameterized media type rollups across package readers' => static function (TestRunner $t) use ($parts): void {
        $expectedHeroParameters = [
            ['name' => 'profile', 'value' => 'cover;hero', 'raw' => 'profile="cover;hero"'],
            ['name' => 'codec', 'value' => 'png"alpha', 'raw' => 'codec="png\"alpha"'],
            ['name' => 'quality', 'value' => 'review', 'raw' => 'quality=review'],
        ];
        $expectedHeroMap = [
            'profile' => 'cover;hero',
            'codec' => 'png"alpha',
            'quality' => 'review',
        ];
        $expectedThemeParameters = [
            ['name' => 'codecs', 'value' => 'opus;vorbis', 'raw' => 'codecs="opus;vorbis"'],
            ['name' => 'variant', 'value' => 'teaser', 'raw' => 'variant=teaser'],
        ];
        $expectedNameCounts = [
            'codec' => 1,
            'codecs' => 1,
            'profile' => 1,
            'quality' => 1,
            'variant' => 1,
        ];

        $package = ZipPackage::fromParts($parts, 'odf parameterized media type review');
        $summary = OpenDocumentPackage::fromPackage($package)->summarize();
        $review = $summary['manifestReview'];
        $reviewParameterizedByPath = [];
        foreach ($review['manifestParameterizedMediaTypeItems'] as $item) {
            $reviewParameterizedByPath[$item['path']] = $item;
        }
        $reviewOrderByPath = [];
        foreach ($review['manifestFileEntryOrder'] as $item) {
            $reviewOrderByPath[$item['path']] = $item;
        }

        $t->same(2, $review['manifestParameterizedMediaTypeCount']);
        $t->same(['codec', 'codecs', 'profile', 'quality', 'variant'], $review['manifestMediaTypeParameterNames']);
        $t->same($expectedNameCounts, $review['manifestMediaTypeParameterNameCounts']);
        $t->same('image/png', $reviewParameterizedByPath['Pictures/hero.png']['mediaTypeBase']);
        $t->same(3, $reviewParameterizedByPath['Pictures/hero.png']['mediaTypeParameterCount']);
        $t->same($expectedHeroParameters, $reviewParameterizedByPath['Pictures/hero.png']['mediaTypeParameters']);
        $t->same($expectedHeroMap, $reviewParameterizedByPath['Pictures/hero.png']['mediaTypeParameterMap']);
        $t->same(true, $reviewParameterizedByPath['Pictures/hero.png']['canExposeBytes']);
        $t->same('audio/ogg', $reviewParameterizedByPath['Media/theme.ogg']['mediaTypeBase']);
        $t->same($expectedThemeParameters, $reviewParameterizedByPath['Media/theme.ogg']['mediaTypeParameters']);
        $t->same($expectedHeroMap, $reviewOrderByPath['Pictures/hero.png']['mediaTypeParameterMap']);
        $t->same(2, $reviewOrderByPath['Media/theme.ogg']['mediaTypeParameterCount']);

        $result = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'odf parameterized media type review'));
        $mediaTypeSummary = $result['importReport']['manifest']['mediaTypeSummary'];
        $richParameterizedByPart = [];
        foreach ($mediaTypeSummary['parameterizedItems'] as $item) {
            $richParameterizedByPart[$item['part']] = $item;
        }
        $richGroupsByType = [];
        foreach ($mediaTypeSummary['items'] as $item) {
            $richGroupsByType[$item['mediaType']] = $item;
        }

        $t->same($mediaTypeSummary, $result['document']->attr('manifest')['mediaTypeSummary']);
        $t->same(2, $mediaTypeSummary['parameterizedItemCount']);
        $t->same(['codec', 'codecs', 'profile', 'quality', 'variant'], $mediaTypeSummary['mediaTypeParameterNames']);
        $t->same($expectedNameCounts, $mediaTypeSummary['mediaTypeParameterNameCounts']);
        $t->same('image/png', $richParameterizedByPart['Pictures/hero.png']['mediaTypeBase']);
        $t->same($expectedHeroParameters, $richParameterizedByPart['Pictures/hero.png']['mediaTypeParameters']);
        $t->same($expectedHeroMap, $richParameterizedByPart['Pictures/hero.png']['mediaTypeParameterMap']);
        $t->same('audio/ogg', $richParameterizedByPart['Media/theme.ogg']['mediaTypeBase']);
        $t->same($expectedThemeParameters, $richParameterizedByPart['Media/theme.ogg']['mediaTypeParameters']);
        $t->same(1, $richGroupsByType['image/png']['parameterizedItemCount']);
        $t->same(['codec', 'profile', 'quality'], $richGroupsByType['image/png']['mediaTypeParameterNames']);
        $t->same(['codec' => 1, 'profile' => 1, 'quality' => 1], $richGroupsByType['image/png']['mediaTypeParameterNameCounts']);
        $t->same(1, $richGroupsByType['audio/ogg']['parameterizedItemCount']);
        $t->same(['codecs', 'variant'], $richGroupsByType['audio/ogg']['mediaTypeParameterNames']);
        $t->same(['codecs' => 1, 'variant' => 1], $richGroupsByType['audio/ogg']['mediaTypeParameterNameCounts']);
    },
];
