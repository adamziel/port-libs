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
  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart; profile=review-chart; charset=UTF-8"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml; charset=UTF-8; role=chart-data"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/Pictures/preview.svg" manifest:media-type="image/svg+xml; profile=thumbnail"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Embedded object media type parameter packet.</text:p>
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
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:meta/>
</office:document-meta>
XML;

$chartXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:bar"/>
    </office:chart>
  </office:body>
</office:document-content>
XML;

$previewSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Object Chart/content.xml', 'data' => $chartXml, 'compressionMethod' => 0],
    ['name' => 'Object Chart/Pictures/preview.svg', 'data' => $previewSvg, 'compressionMethod' => 0],
], 'odt embedded object media type parameters');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $value = $item[$key] ?? null;
        if (is_string($value) && $value !== '') {
            $indexed[$value] = $item;
        }
    }

    return $indexed;
};

$assertRootParameters = static function (TestRunner $t, array $chart, string $context): void {
    $t->same('application/vnd.oasis.opendocument.chart; profile=review-chart; charset=UTF-8', $chart['mediaType'], "{$context} root media type");
    $t->same('application/vnd.oasis.opendocument.chart', $chart['mediaTypeBase'], "{$context} root media type base");
    $t->same(true, $chart['mediaTypeHasParameters'], "{$context} root parameter flag");
    $t->same(2, $chart['mediaTypeParameterCount'], "{$context} root parameter count");
    $t->same([
        ['name' => 'profile', 'value' => 'review-chart', 'raw' => 'profile=review-chart'],
        ['name' => 'charset', 'value' => 'UTF-8', 'raw' => 'charset=UTF-8'],
    ], $chart['mediaTypeParameters'], "{$context} root parameters");
    $t->same([
        'profile' => 'review-chart',
        'charset' => 'UTF-8',
    ], $chart['mediaTypeParameterMap'], "{$context} root parameter map");
    $t->same(false, $chart['canExposeBytes'], "{$context} root byte exposure");
    $t->same('embedded-object-package-bytes-blocked', $chart['byteExposurePolicy'], "{$context} root byte policy");
};

$assertContainedParameters = static function (TestRunner $t, array $content, array $preview, string $context): void {
    $t->same('text/xml; charset=UTF-8; role=chart-data', $content['manifestMediaType'], "{$context} content media type");
    $t->same('text/xml', $content['manifestMediaTypeBase'], "{$context} content media type base");
    $t->same(true, $content['manifestMediaTypeHasParameters'], "{$context} content parameter flag");
    $t->same(2, $content['manifestMediaTypeParameterCount'], "{$context} content parameter count");
    $t->same([
        'charset' => 'UTF-8',
        'role' => 'chart-data',
    ], $content['manifestMediaTypeParameterMap'], "{$context} content parameter map");
    $t->same('document-xml', $content['containedRole'], "{$context} content role");
    $t->same('xml', $content['containedMediaFamily'], "{$context} content family");

    $t->same('image/svg+xml; profile=thumbnail', $preview['manifestMediaType'], "{$context} preview media type");
    $t->same('image/svg+xml', $preview['manifestMediaTypeBase'], "{$context} preview media type base");
    $t->same(true, $preview['manifestMediaTypeHasParameters'], "{$context} preview parameter flag");
    $t->same(1, $preview['manifestMediaTypeParameterCount'], "{$context} preview parameter count");
    $t->same([
        'profile' => 'thumbnail',
    ], $preview['manifestMediaTypeParameterMap'], "{$context} preview parameter map");
    $t->same('media-resource', $preview['containedRole'], "{$context} preview role");
    $t->same('image', $preview['containedMediaFamily'], "{$context} preview family");
};

return [
    'carries embedded object media-type parameter provenance without exposing object bytes' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $assertRootParameters,
        $assertContainedParameters
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];
        $readerObjects = $readerProvenance['embeddedObjectPackages'];
        $readerChart = $readerObjects['byRootPart']['Object Chart/'];
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactObjects = $compactSummary['packageObjects'];
        $compactChart = $compactObjects['byRootPart']['Object Chart/'];

        $assertRootParameters($t, $readerChart, 'reader');
        $assertRootParameters($t, $compactChart, 'compact');
        $t->same($readerObjects, $result['packageObjects']);
        $t->same($readerObjects, $result['metadata']['odfPackageObjects']);
        $t->same($readerObjects, $result['document']->attr('packageObjects'));
        $t->same($readerObjects, $result['importReport']['packageObjects']);
        $t->same($readerObjects, $result['document']->attr('manifest')['packageProvenance']['embeddedObjectPackages']);
        $t->same(1, $readerObjects['count'], 'reader object count');
        $t->same(1, $compactObjects['count'], 'compact object count');

        $readerContained = $indexBy($readerChart['containedParts'], 'part');
        $compactContained = $indexBy($compactChart['containedParts'], 'part');
        $assertContainedParameters(
            $t,
            $readerContained['Object Chart/content.xml'],
            $readerContained['Object Chart/Pictures/preview.svg'],
            'reader contained'
        );
        $assertContainedParameters(
            $t,
            $compactContained['Object Chart/content.xml'],
            $compactContained['Object Chart/Pictures/preview.svg'],
            'compact contained'
        );

        $readerDeclared = $indexBy($readerChart['declaredContainedParts'], 'part');
        $compactDeclared = $indexBy($compactChart['declaredContainedParts'], 'part');
        $t->same($readerContained['Object Chart/content.xml']['manifestMediaTypeParameterMap'], $readerDeclared['Object Chart/content.xml']['mediaTypeParameterMap']);
        $t->same($compactContained['Object Chart/Pictures/preview.svg']['manifestMediaTypeParameterMap'], $compactDeclared['Object Chart/Pictures/preview.svg']['mediaTypeParameterMap']);

        foreach (['reader' => $readerProvenance['parts'], 'compact' => $compactSummary['packageInventory']['parts']] as $context => $parts) {
            $contentPart = $parts['Object Chart/content.xml'];
            $previewPart = $parts['Object Chart/Pictures/preview.svg'];
            $t->same(false, $contentPart['canExposeBytes'], "{$context} content byte exposure");
            $t->same(false, $previewPart['canExposeBytes'], "{$context} preview byte exposure");
            $t->same('embedded-object-package-bytes-blocked', $contentPart['byteExposurePolicy'], "{$context} content byte policy");
            $t->same('embedded-object-package-bytes-blocked', $previewPart['byteExposurePolicy'], "{$context} preview byte policy");
            $t->same(['embedded-object-part', 'manifest-declared'], $contentPart['roles'], "{$context} content roles");
            $t->same(['embedded-object-part', 'manifest-declared'], $previewPart['roles'], "{$context} preview roles");
        }
    },
];
