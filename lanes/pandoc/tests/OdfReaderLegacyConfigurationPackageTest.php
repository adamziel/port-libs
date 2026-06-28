<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$acceleratorXml = '<accel:acceleratorlist xmlns:accel="http://openoffice.org/2001/accel"/>';
$configIconBytes = 'LEGACY-CONFIG-PNG';
$statusbarXml = '<statusbar:statusbar xmlns:statusbar="http://openoffice.org/2001/statusbar"/>';
$heroBytes = 'PNGDATA';
$acceleratorSize = strlen($acceleratorXml);
$configIconSize = strlen($configIconBytes);
$heroSize = strlen($heroBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="{$heroSize}"/>
  <manifest:file-entry manifest:full-path="Configurations/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Configurations/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="{$acceleratorSize}"/>
  <manifest:file-entry manifest:full-path="Configurations/images/Bitmaps/review.png" manifest:media-type="image/png" manifest:size="{$configIconSize}"/>
  <manifest:file-entry manifest:full-path="Configurations/toolbar/missing.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Legacy configuration package.</text:p>
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
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Legacy Configurations Package</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => $heroBytes, 'compressionMethod' => 0],
    ['name' => 'Configurations/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Configurations/accelerator/current.xml', 'data' => $acceleratorXml, 'compressionMethod' => 0],
    ['name' => 'Configurations/images/Bitmaps/review.png', 'data' => $configIconBytes, 'compressionMethod' => 0],
    ['name' => 'Configurations/statusbar/standardbar.xml', 'data' => $statusbarXml, 'compressionMethod' => 0],
], 'odt legacy configurations package');

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

return [
    'classifies legacy ODT Configurations package sidecars as metadata-only' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $acceleratorXml,
        $configIconBytes,
        $statusbarXml,
        $heroBytes
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $configurations = $result['packageConfigurations'];
        $configurationsByPart = $indexBy($configurations['items'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($configurations, $result['document']->attr('packageConfigurations'));
        $t->same($configurations, $result['metadata']['odfPackageConfigurations']);
        $t->same($configurations, $result['importReport']['packageConfigurations']);
        $t->same(5, $configurations['count']);
        $t->same(4, $configurations['fileCount']);
        $t->same(1, $configurations['directoryCount']);
        $t->same(4, $configurations['declaredCount']);
        $t->same(1, $configurations['undeclaredCount']);
        $t->same(1, $configurations['missingCount']);
        $t->same(0, $configurations['encryptedCount']);
        $t->same(0, $configurations['invalidMediaTypeCount']);
        $t->same(['odf-configuration-package-missing-part', 'odf-configuration-package-undeclared-part'], $configurations['issueCodes']);
        $t->same([
            'accelerator-configuration' => 1,
            'configuration-directory' => 1,
            'image-configuration-resource' => 1,
            'statusbar-configuration' => 1,
            'toolbar-configuration' => 1,
        ], $configurations['kindCounts']);
        $t->same([
            'accelerator' => 1,
            'images' => 1,
            'statusbar' => 1,
            'toolbar' => 1,
        ], $configurations['groupCounts']);
        $t->same('configuration-package-bytes-blocked', $configurations['byteExposurePolicy']);
        $t->same('configuration-package-metadata-only', $configurations['reviewPolicy']);

        $accelerator = $configurationsByPart['Configurations/accelerator/current.xml'];
        $t->same('Configurations', $accelerator['packageRoot']);
        $t->same('accelerator-configuration', $accelerator['kind']);
        $t->same('accelerator', $accelerator['group']);
        $t->same(null, $accelerator['byteLength']);
        $t->same(strlen($acceleratorXml), $accelerator['storedByteLength']);
        $t->same(false, $accelerator['canExposeAsDocumentMedia']);
        $t->same([], $accelerator['issues']);

        $configIcon = $manifestByPart['Configurations/images/Bitmaps/review.png'];
        $t->same(true, $configIcon['configurationPackagePart']);
        $t->same(false, $configIcon['canExposeBytes']);
        $t->same(null, $configIcon['byteLength']);
        $t->same(strlen($configIconBytes), $configIcon['storedByteLength']);
        $t->same(null, $configIcon['byteSha256']);
        $t->same('configuration-package-bytes-blocked', $configIcon['byteExposurePolicy']);
        $t->same('image-configuration-resource', $configurationsByPart['Configurations/images/Bitmaps/review.png']['kind']);
        $t->same('Configurations', $configurationsByPart['Configurations/images/Bitmaps/review.png']['packageRoot']);

        $missing = $configurationsByPart['Configurations/toolbar/missing.xml'];
        $t->same(false, $missing['exists']);
        $t->same(['odf-configuration-package-missing-part'], $missing['issues']);

        $orphan = $configurationsByPart['Configurations/statusbar/standardbar.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('Configurations', $orphan['packageRoot']);
        $t->same(strlen($statusbarXml), $orphan['storedByteLength']);
        $t->same(['odf-configuration-package-undeclared-part'], $orphan['issues']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(strlen($heroBytes), $result['media'][0]['byteLength']);
        $t->same(4, $provenance['configurationPackagePartCount']);
        $t->same(4, $provenance['roleCounts']['configuration-package']);
        $t->same(1, $provenance['undeclaredRoleCounts']['configuration-package']);
        $t->same(['configuration-package', 'manifest-declared'], $provenance['parts']['Configurations/images/Bitmaps/review.png']['roles']);
        $t->same(['configuration-package', 'undeclared-package-entry'], $provenance['parts']['Configurations/statusbar/standardbar.xml']['roles']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactConfigurations = $compactSummary['packageConfigurations'];
        $compactByPath = $indexBy($compactConfigurations['items'], 'packagePath');
        $compactManifestByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(5, $compactConfigurations['count']);
        $t->same(3, $compactConfigurations['readableCount']);
        $t->same(4, $compactConfigurations['declaredCount']);
        $t->same(1, $compactConfigurations['undeclaredCount']);
        $t->same(1, $compactConfigurations['missingCount']);
        $t->same(1, $compactConfigurations['directoryCount']);
        $t->same(['accelerator', 'images', 'statusbar', 'toolbar'], $compactConfigurations['configurationAreas']);
        $t->same(['configuration-image', 'configuration-root', 'configuration-xml'], $compactConfigurations['configurationKinds']);
        $t->same('Configurations', $compactByPath['Configurations/accelerator/current.xml']['packageRoot']);
        $t->same('accelerator/current.xml', $compactByPath['Configurations/accelerator/current.xml']['configurationPath']);
        $t->same(strlen($acceleratorXml), $compactByPath['Configurations/accelerator/current.xml']['byteLength']);
        $t->same('Configurations', $compactByPath['Configurations/images/Bitmaps/review.png']['packageRoot']);
        $t->same(false, $compactByPath['Configurations/images/Bitmaps/review.png']['canExposeAsDocumentMedia']);
        $t->same(['odf-configuration-missing-package-part'], $compactByPath['Configurations/toolbar/missing.xml']['issues']);
        $t->same(['odf-configuration-undeclared-package-part'], $compactByPath['Configurations/statusbar/standardbar.xml']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(true, $compactManifestByPath['Configurations/images/Bitmaps/review.png']['configurationPackagePart']);
        $t->same(false, $compactManifestByPath['Configurations/images/Bitmaps/review.png']['canExposeBytes']);
        $t->same('configuration-package-bytes-blocked', $compactManifestByPath['Configurations/images/Bitmaps/review.png']['byteExposurePolicy']);
        $t->same(4, $compactSummary['manifestReview']['configurationPackagePartCount']);
        $t->same(4, $inventory['configurationPackagePartCount']);
        $t->same(4, $inventory['roleCounts']['configuration-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['configuration-package']);
        $t->same(['configuration-package', 'manifest-declared'], $inventory['parts']['Configurations/images/Bitmaps/review.png']['roles']);
        $t->same(['configuration-package', 'undeclared-package-entry'], $inventory['parts']['Configurations/statusbar/standardbar.xml']['roles']);
    },
];
