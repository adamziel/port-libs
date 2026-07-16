<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

return [
    'preserves ODT script and configuration sidecar flags in manifest order handoff' => static function (TestRunner $t): void {
        $scriptBytes = '<script:module xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"/>';
        $configurationBytes = '<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="Review"/>';
        $scriptSize = strlen($scriptBytes);
        $configurationSize = strlen($configurationBytes);
        $manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Basic/Standard/Review.xml" manifest:size="{$scriptSize}"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="Configurations2/accelerator/current.xml" manifest:size="{$configurationSize}"/>
</manifest:manifest>
XML;
        $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Review packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptBytes, 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationBytes, 'compressionMethod' => 0],
        ], 'odt sidecar order package');

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactOrderByPath = [];
        foreach ($compactSummary['manifestReview']['manifestFileEntryOrder'] as $item) {
            $compactOrderByPath[$item['path']] = $item;
        }

        $readerResult = (new OdfReader())->readPackage($package);
        $manifestByPart = [];
        foreach ($readerResult['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $readerProvenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $readerOrderByPart = [];
        foreach ($readerProvenance['manifestFileEntryOrder'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $readerOrderByPart[$item['part']] = $item;
            }
        }
        $readerParts = $readerProvenance['parts'];

        $t->same(true, $compactOrderByPath['Basic/Standard/Review.xml']['scriptPackagePart']);
        $t->same(false, $compactOrderByPath['Basic/Standard/Review.xml']['configurationPackagePart']);
        $t->same(false, $compactOrderByPath['Configurations2/accelerator/current.xml']['scriptPackagePart']);
        $t->same(true, $compactOrderByPath['Configurations2/accelerator/current.xml']['configurationPackagePart']);

        $t->same(true, $manifestByPart['Basic/Standard/Review.xml']['scriptPackagePart']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Basic/Standard/Review.xml']['byteExposurePolicy']);
        $t->same(true, $readerOrderByPart['Basic/Standard/Review.xml']['scriptPackagePart']);
        $t->same(false, $readerOrderByPart['Basic/Standard/Review.xml']['configurationPackagePart']);
        $t->same(true, $readerParts['Basic/Standard/Review.xml']['scriptPackagePart']);
        $t->same(['manifest-declared', 'script-package'], $readerParts['Basic/Standard/Review.xml']['roles']);

        $t->same(true, $manifestByPart['Configurations2/accelerator/current.xml']['configurationPackagePart']);
        $t->same('configuration-package-bytes-blocked', $manifestByPart['Configurations2/accelerator/current.xml']['byteExposurePolicy']);
        $t->same(false, $readerOrderByPart['Configurations2/accelerator/current.xml']['scriptPackagePart']);
        $t->same(true, $readerOrderByPart['Configurations2/accelerator/current.xml']['configurationPackagePart']);
        $t->same(true, $readerParts['Configurations2/accelerator/current.xml']['configurationPackagePart']);
        $t->same(['configuration-package', 'manifest-declared'], $readerParts['Configurations2/accelerator/current.xml']['roles']);
    },
];
