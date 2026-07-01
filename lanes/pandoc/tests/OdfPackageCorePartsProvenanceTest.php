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
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Core package provenance.</text:p>
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
    <style:style style:name="Body" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Core package provenance</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestWithoutOptionalDeclarations = str_replace(
    [
        '  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>' . "\n",
        '  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>' . "\n",
    ],
    '',
    $manifestXml
);

$buildPackage = static function (string $manifest) use ($contentXml, $stylesXml, $metaXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 8],
    ], 'odt core package provenance');
};

$itemsByPart = static function (array $items): array {
    $byPart = [];
    foreach ($items as $item) {
        $byPart[$item['part']] = $item;
    }

    return $byPart;
};

return [
    'summarizes compact ODT core package part provenance without exposing bytes' => static function (TestRunner $t) use (
        $buildPackage,
        $itemsByPart,
        $manifestWithoutOptionalDeclarations,
        $contentXml,
        $stylesXml,
        $metaXml
    ): void {
        $summary = OpenDocumentPackage::fromPackage($buildPackage($manifestWithoutOptionalDeclarations))->summarize();
        $coreParts = $summary['packageCoreParts'];
        $coreByPart = $itemsByPart($coreParts['items']);

        $t->same('odf-core-package-provenance-metadata-only', $coreParts['byteExposurePolicy']);
        $t->same(false, $coreParts['canExposeBytes']);
        $t->same(6, $coreParts['itemCount']);
        $t->same(5, $coreParts['presentCount']);
        $t->same(1, $coreParts['absentCount']);
        $t->same(3, $coreParts['requiredCount']);
        $t->same(0, $coreParts['missingRequiredCount']);
        $t->same(1, $coreParts['manifestDeclaredCount']);
        $t->same(2, $coreParts['undeclaredExistingCount']);
        $t->same(0, $coreParts['missingDeclaredCount']);
        $t->same(2, $coreParts['issueCount']);
        $t->same(2, $coreParts['issueItemCount']);
        $t->same(['odf-core-package-undeclared-part' => 2], $coreParts['issueCodeCounts']);
        $t->same(['styles.xml', 'meta.xml'], array_column($coreParts['issueItems'], 'part'));

        $t->same(true, $coreByPart['content.xml']['declaredInManifest']);
        $t->same(true, $coreByPart['content.xml']['exists']);
        $t->same(strlen($contentXml), $coreByPart['content.xml']['byteLength']);
        $t->same('text/xml', $coreByPart['content.xml']['manifestMediaTypeBase']);
        $t->same(true, $coreByPart['content.xml']['manifestMediaTypeMatchesExpected']);
        $t->same([], $coreByPart['content.xml']['issues']);

        $t->same(false, $coreByPart['styles.xml']['declaredInManifest']);
        $t->same(true, $coreByPart['styles.xml']['exists']);
        $t->same(strlen($stylesXml), $coreByPart['styles.xml']['byteLength']);
        $t->same(['odf-core-package-undeclared-part'], $coreByPart['styles.xml']['issues']);
        $t->same(false, $coreByPart['meta.xml']['declaredInManifest']);
        $t->same(strlen($metaXml), $coreByPart['meta.xml']['byteLength']);
        $t->same(['odf-core-package-undeclared-part'], $coreByPart['meta.xml']['issues']);

        $t->same(false, $coreByPart['settings.xml']['exists']);
        $t->same(false, $coreByPart['settings.xml']['declaredInManifest']);
        $t->same([], $coreByPart['settings.xml']['issues']);
        $t->same('odf-mimetype', $coreByPart['mimetype']['role']);
        $t->same(false, $coreByPart['mimetype']['declaredInManifest']);
        $t->same(false, $coreByPart['mimetype']['canExposeBytes']);
        $t->same($coreParts, $summary['packageIdentity']['packageCoreParts']);
        $t->same(2, $summary['packageIdentity']['corePackageIssueCount']);
        $t->same(['odf-core-package-undeclared-part'], $summary['packageIdentity']['corePackageIssueCodes']);
    },
    'surfaces ODT reader core package part issues in package provenance and identity' => static function (TestRunner $t) use (
        $buildPackage,
        $itemsByPart,
        $manifestWithoutOptionalDeclarations,
        $contentXml
    ): void {
        $manifest = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>' . "\n" . '</manifest:manifest>',
            $manifestWithoutOptionalDeclarations
        );
        $result = (new OdfReader())->readPackage($buildPackage($manifest));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $coreParts = $provenance['packageCoreParts'];
        $coreByPart = $itemsByPart($coreParts['items']);

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(6, $coreParts['itemCount']);
        $t->same(5, $coreParts['presentCount']);
        $t->same(1, $coreParts['absentCount']);
        $t->same(2, $coreParts['manifestDeclaredCount']);
        $t->same(2, $coreParts['undeclaredExistingCount']);
        $t->same(1, $coreParts['missingDeclaredCount']);
        $t->same(3, $coreParts['issueCount']);
        $t->same([
            'odf-core-package-missing-declared-part' => 1,
            'odf-core-package-undeclared-part' => 2,
        ], $coreParts['issueCodeCounts']);
        $t->same(['styles.xml', 'meta.xml', 'settings.xml'], array_column($coreParts['issueItems'], 'part'));

        $t->same(true, $coreByPart['content.xml']['declaredInManifest']);
        $t->same(strlen($contentXml), $coreByPart['content.xml']['byteLength']);
        $t->same([], $coreByPart['content.xml']['issues']);
        $t->same(false, $coreByPart['styles.xml']['declaredInManifest']);
        $t->same(['odf-core-package-undeclared-part'], $coreByPart['styles.xml']['issues']);
        $t->same(false, $coreByPart['meta.xml']['declaredInManifest']);
        $t->same(['odf-core-package-undeclared-part'], $coreByPart['meta.xml']['issues']);
        $t->same(true, $coreByPart['settings.xml']['declaredInManifest']);
        $t->same(false, $coreByPart['settings.xml']['exists']);
        $t->same(['odf-core-package-missing-declared-part'], $coreByPart['settings.xml']['issues']);

        $identity = $provenance['packageIdentity'];
        $t->same(3, $identity['corePackageIssueCount']);
        $t->same([
            'odf-core-package-missing-declared-part',
            'odf-core-package-undeclared-part',
        ], $identity['corePackageIssueCodes']);
        $t->same($coreParts, $identity['packageCoreParts']);
    },
];
