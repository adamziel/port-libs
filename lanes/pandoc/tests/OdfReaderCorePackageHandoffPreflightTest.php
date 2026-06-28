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
      <text:p>Core package handoff.</text:p>
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

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0"
  office:version="1.3">
  <office:settings>
    <config:config-item-set config:name="writer-review"/>
  </office:settings>
</office:document-settings>
XML;

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="__CONTENT_SIZE__"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml" manifest:size="__STYLES_SIZE__"/>
</manifest:manifest>
XML;
$manifestXml = strtr($manifestXml, [
    '__CONTENT_SIZE__' => (string) strlen($contentXml),
    '__STYLES_SIZE__' => (string) strlen($stylesXml),
]);

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0],
], 'odt core package handoff preflight');

$indexByName = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        if (is_string($item['name'] ?? null)) {
            $indexed[$item['name']] = $item;
        }
    }

    return $indexed;
};

return [
    'preflights selected ODT core package entries before reader handoff' => static function (TestRunner $t) use ($buildPackage, $indexByName, $contentXml, $stylesXml, $settingsXml): void {
        $package = $buildPackage();
        $readerResult = (new OdfReader())->readPackage($package);
        $readerHandoff = $readerResult['corePackageHandoff'];
        $compactHandoff = OpenDocumentPackage::fromPackage($package)->summarize()['corePackageHandoff'];
        $readerEntries = $indexByName($readerHandoff['entries']);
        $compactEntries = $indexByName($compactHandoff['entries']);
        $readerSourceSpans = $indexByName($readerHandoff['selectedSourceByteSpanEntries']);
        $compactSourceSpans = $indexByName($compactHandoff['selectedSourceByteSpanEntries']);
        $readerLocalFixedFields = $indexByName($readerHandoff['selectedLocalHeaderFixedFieldEntries']);
        $readerCentralFixedFields = $indexByName($readerHandoff['selectedCentralDirectoryFixedFieldEntries']);

        $t->same($readerHandoff, $readerResult['document']->attr('manifest')['corePackageHandoff']);
        $t->same($readerHandoff, $readerResult['importReport']['manifest']['corePackageHandoff']);
        $t->same('odf-core-package-handoff', $readerHandoff['scope']);
        $t->same('odf-core-package-handoff-metadata-only', $readerHandoff['byteExposurePolicy']);
        $t->same('core-package-selected-entry-preflight', $readerHandoff['reviewPolicy']);
        $t->same(6, $readerHandoff['requestedEntryCount']);
        $t->same(3, $readerHandoff['requiredEntryCount']);
        $t->same(3, $readerHandoff['optionalEntryCount']);
        $t->same(5, $readerHandoff['presentEntryCount']);
        $t->same(1, $readerHandoff['missingOptionalEntryCount']);
        $t->same(5, $readerHandoff['handoffEntryCount']);
        $t->same(0, $readerHandoff['failedEntryCount']);
        $t->same(true, $readerHandoff['isSupportedByBoundedReader']);
        $t->same([
            'declared' => 2,
            'not-declared' => 1,
            'package-manifest-entry' => 1,
            'package-mimetype-entry' => 1,
            'undeclared' => 1,
        ], $readerHandoff['manifestDeclarationStateCounts']);
        $t->same(2, $readerHandoff['manifestDeclaredSelectedEntryCount']);
        $t->same(1, $readerHandoff['undeclaredSelectedEntryCount']);
        $t->same(2, $readerHandoff['specialPackageSelectedEntryCount']);

        $t->same('package-mimetype-entry', $readerEntries['mimetype']['manifestDeclarationState']);
        $t->same('/', $readerEntries['mimetype']['manifestFullPath']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $readerEntries['mimetype']['manifestMediaType']);
        $t->same('odf-mimetype', $readerEntries['mimetype']['role']);
        $t->same(strlen(OdfReader::MIMETYPE), $readerEntries['mimetype']['bytesRead']);

        $t->same('package-manifest-entry', $readerEntries['META-INF/manifest.xml']['manifestDeclarationState']);
        $t->same(false, $readerEntries['META-INF/manifest.xml']['manifestDeclared']);
        $t->same('odf-manifest', $readerEntries['META-INF/manifest.xml']['role']);

        $t->same('declared', $readerEntries['content.xml']['manifestDeclarationState']);
        $t->same(true, $readerEntries['content.xml']['manifestDeclared']);
        $t->same('content.xml', $readerEntries['content.xml']['manifestFullPath']);
        $t->same(strlen($contentXml), $readerEntries['content.xml']['manifestDeclaredSize']);
        $t->same(strlen($contentXml), $readerEntries['content.xml']['bytesRead']);
        $t->same(hash('sha256', $contentXml), $readerEntries['content.xml']['contentSha256']);

        $t->same('declared', $readerEntries['styles.xml']['manifestDeclarationState']);
        $t->same(strlen($stylesXml), $readerEntries['styles.xml']['manifestDeclaredSize']);
        $t->same(strlen($stylesXml), $readerEntries['styles.xml']['bytesRead']);

        $t->same('not-declared', $readerEntries['meta.xml']['manifestDeclarationState']);
        $t->same(false, $readerEntries['meta.xml']['exists']);
        $t->same('missing-optional', $readerEntries['meta.xml']['status']);
        $t->same(false, $readerEntries['meta.xml']['manifestDeclared']);

        $t->same('undeclared', $readerEntries['settings.xml']['manifestDeclarationState']);
        $t->same(false, $readerEntries['settings.xml']['manifestDeclared']);
        $t->same(strlen($settingsXml), $readerEntries['settings.xml']['bytesRead']);
        $t->same(hash('sha256', $settingsXml), $readerEntries['settings.xml']['contentSha256']);

        $t->same('package-mimetype-entry', $readerSourceSpans['mimetype']['manifestDeclarationState']);
        $t->same('/', $readerSourceSpans['mimetype']['manifestFullPath']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $readerSourceSpans['mimetype']['manifestMediaType']);
        $t->same('package-manifest-entry', $readerSourceSpans['META-INF/manifest.xml']['manifestDeclarationState']);
        $t->same(false, $readerSourceSpans['META-INF/manifest.xml']['manifestDeclared']);
        $t->same('declared', $readerSourceSpans['content.xml']['manifestDeclarationState']);
        $t->same('content.xml', $readerSourceSpans['content.xml']['manifestFullPath']);
        $t->same(strlen($contentXml), $readerSourceSpans['content.xml']['manifestDeclaredSize']);
        $t->same('undeclared', $readerSourceSpans['settings.xml']['manifestDeclarationState']);
        $t->same(false, $readerSourceSpans['settings.xml']['manifestDeclared']);
        $t->same(true, $readerSourceSpans['settings.xml']['hasSourceByteSpanProvenance']);
        $t->same('declared', $readerLocalFixedFields['styles.xml']['manifestDeclarationState']);
        $t->same(strlen($stylesXml), $readerLocalFixedFields['styles.xml']['manifestDeclaredSize']);
        $t->same('undeclared', $readerCentralFixedFields['settings.xml']['manifestDeclarationState']);
        $t->same(false, $readerCentralFixedFields['settings.xml']['manifestDeclared']);

        $t->same($readerHandoff['manifestDeclarationStateCounts'], $compactHandoff['manifestDeclarationStateCounts']);
        $t->same($readerEntries['settings.xml']['manifestDeclarationState'], $compactEntries['settings.xml']['manifestDeclarationState']);
        $t->same($readerEntries['content.xml']['manifestDeclaredSize'], $compactEntries['content.xml']['manifestDeclaredSize']);
        $t->same($readerSourceSpans['settings.xml']['manifestDeclarationState'], $compactSourceSpans['settings.xml']['manifestDeclarationState']);
        $t->same($readerSourceSpans['content.xml']['manifestDeclaredSize'], $compactSourceSpans['content.xml']['manifestDeclaredSize']);
    },
];
