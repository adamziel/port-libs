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
  <office:body>
    <office:text>
      <text:p>Configuration package media type review.</text:p>
    </office:text>
  </office:body>
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
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Configuration Package Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$acceleratorXcu = '<oor:items xmlns:oor="http://openoffice.org/2001/registry"/>';
$schemaXcs = '<oor:component-schema xmlns:oor="http://openoffice.org/2001/registry"/>';
$dialogXdl = '<dlg:window xmlns:dlg="http://openoffice.org/2000/dialog"/>';
$configurationRootMediaType = 'application/vnd.sun.xml.ui.configuration';
$configurationSchemaMediaType = 'application/vnd.sun.xml.configuration';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/" manifest:media-type="application/vnd.sun.xml.ui.configuration"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xcu" manifest:media-type="application/vnd.sun.xml.ui.configuration" manifest:size="__ACCELERATOR_SIZE__"/>
  <manifest:file-entry manifest:full-path="Configurations2/registry/schema.xcs" manifest:media-type="application/vnd.sun.xml.configuration" manifest:size="__SCHEMA_SIZE__"/>
  <manifest:file-entry manifest:full-path="Configurations2/dialogs/review.xdl" manifest:media-type="application/xml" manifest:size="__DIALOG_SIZE__"/>
</manifest:manifest>
XML;

$manifestXml = str_replace(
    ['__ACCELERATOR_SIZE__', '__SCHEMA_SIZE__', '__DIALOG_SIZE__'],
    [(string) strlen($acceleratorXcu), (string) strlen($schemaXcs), (string) strlen($dialogXdl)],
    $manifestXml
);

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'Configurations2/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xcu', 'data' => $acceleratorXcu, 'compressionMethod' => 0],
    ['name' => 'Configurations2/registry/schema.xcs', 'data' => $schemaXcs, 'compressionMethod' => 0],
    ['name' => 'Configurations2/dialogs/review.xdl', 'data' => $dialogXdl, 'compressionMethod' => 0],
], 'odt configuration media review');

return [
    'maps rich ODT configuration package media aliases as metadata-only sidecars' =>
        static function (TestRunner $t) use ($buildPackage, $configurationRootMediaType, $configurationSchemaMediaType, $acceleratorXcu, $schemaXcs, $dialogXdl): void {
            $result = (new OdfReader())->readPackage($buildPackage());
            $configurations = $result['packageConfigurations'];
            $itemsByPart = [];
            foreach ($configurations['items'] as $item) {
                $itemsByPart[$item['part']] = $item;
            }

            $provenance = $result['importReport']['manifest']['packageProvenance'];

            $t->same($configurations, $result['document']->attr('packageConfigurations'));
            $t->same($configurations, $result['metadata']['odfPackageConfigurations']);
            $t->same($configurations, $result['importReport']['packageConfigurations']);
            $t->same([], array_column($result['media'], 'part'));
            $t->same(4, $configurations['count']);
            $t->same(3, $configurations['fileCount']);
            $t->same(1, $configurations['directoryCount']);
            $t->same(4, $configurations['declaredCount']);
            $t->same(0, $configurations['undeclaredCount']);
            $t->same(0, $configurations['missingCount']);
            $t->same(0, $configurations['invalidMediaTypeCount']);
            $t->same(0, $configurations['issueCount']);
            $t->same([], $configurations['issueCodes']);
            $t->same([
                'accelerator-configuration' => 1,
                'configuration-directory' => 1,
                'xml-configuration' => 2,
            ], $configurations['kindCounts']);
            $t->same(['accelerator' => 1, 'dialogs' => 1, 'registry' => 1], $configurations['groupCounts']);
            $t->same(4, $provenance['configurationPackagePartCount']);
            $t->same(4, $provenance['roleCounts']['configuration-package']);
            $t->same(0, $provenance['mediaResourcePartCount']);

            $root = $itemsByPart['Configurations2/'];
            $t->same($configurationRootMediaType, $root['mediaType']);
            $t->same($configurationRootMediaType, $root['mediaTypeBase']);
            $t->same('configuration-directory', $root['kind']);
            $t->same('directory-entry-no-bytes', $root['byteExposurePolicy']);
            $t->same([], $root['issues']);

            $accelerator = $itemsByPart['Configurations2/accelerator/current.xcu'];
            $t->same($configurationRootMediaType, $accelerator['mediaTypeBase']);
            $t->same('accelerator-configuration', $accelerator['kind']);
            $t->same(strlen($acceleratorXcu), $accelerator['storedByteLength']);
            $t->same(null, $accelerator['byteLength']);
            $t->same(false, $accelerator['canExposeAsDocumentMedia']);
            $t->same('configuration-package-bytes-blocked', $accelerator['byteExposurePolicy']);
            $t->same([], $accelerator['issues']);

            $schema = $itemsByPart['Configurations2/registry/schema.xcs'];
            $t->same($configurationSchemaMediaType, $schema['mediaTypeBase']);
            $t->same('xml-configuration', $schema['kind']);
            $t->same(strlen($schemaXcs), $schema['storedByteLength']);
            $t->same([], $schema['issues']);

            $dialog = $itemsByPart['Configurations2/dialogs/review.xdl'];
            $t->same('application/xml', $dialog['mediaTypeBase']);
            $t->same('xml-configuration', $dialog['kind']);
            $t->same(strlen($dialogXdl), $dialog['storedByteLength']);
            $t->same([], $dialog['issues']);
        },
    'maps compact ODT configuration package media aliases as metadata-only sidecars' =>
        static function (TestRunner $t) use ($buildPackage, $configurationRootMediaType, $configurationSchemaMediaType, $acceleratorXcu): void {
            $summary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
            $configurations = $summary['packageConfigurations'];
            $itemsByPath = [];
            foreach ($configurations['items'] as $item) {
                $itemsByPath[$item['packagePath']] = $item;
            }

            $inventory = $summary['packageInventory'];

            $t->same(4, $configurations['count']);
            $t->same(3, $configurations['readableCount']);
            $t->same(4, $configurations['declaredCount']);
            $t->same(0, $configurations['undeclaredCount']);
            $t->same(0, $configurations['missingCount']);
            $t->same(1, $configurations['directoryCount']);
            $t->same(0, $configurations['invalidMediaTypeCount']);
            $t->same(0, $configurations['issueCount']);
            $t->same([], $configurations['issueCodes']);
            $t->same(['accelerator', 'dialogs', 'registry'], $configurations['configurationAreas']);
            $t->same(['configuration-root', 'configuration-xml'], $configurations['configurationKinds']);
            $t->same(4, $inventory['configurationPackagePartCount']);
            $t->same(4, $inventory['roleCounts']['configuration-package']);

            $root = $itemsByPath['Configurations2/'];
            $t->same($configurationRootMediaType, $root['mediaTypeBase']);
            $t->same('configuration-root', $root['configurationKind']);
            $t->same('directory-entry-no-bytes', $root['byteExposurePolicy']);
            $t->same([], $root['issues']);

            $accelerator = $itemsByPath['Configurations2/accelerator/current.xcu'];
            $t->same($configurationRootMediaType, $accelerator['mediaTypeBase']);
            $t->same('configuration-xml', $accelerator['configurationKind']);
            $t->same('xcu', $accelerator['extension']);
            $t->same(strlen($acceleratorXcu), $accelerator['storedByteLength']);
            $t->same(false, $accelerator['canExposeAsDocumentMedia']);
            $t->same('configuration-package-bytes-blocked', $accelerator['byteExposurePolicy']);
            $t->same([], $accelerator['issues']);

            $schema = $itemsByPath['Configurations2/registry/schema.xcs'];
            $t->same($configurationSchemaMediaType, $schema['mediaTypeBase']);
            $t->same('configuration-xml', $schema['configurationKind']);
            $t->same('xcs', $schema['extension']);
            $t->same([], $schema['issues']);

            $dialog = $itemsByPath['Configurations2/dialogs/review.xdl'];
            $t->same('application/xml', $dialog['mediaTypeBase']);
            $t->same('configuration-xml', $dialog['configurationKind']);
            $t->same('xdl', $dialog['extension']);
            $t->same([], $dialog['issues']);
        },
];
