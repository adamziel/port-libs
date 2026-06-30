<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$descriptionXml = '<description xmlns="urn:example:extensions"><display-name>Audit Extension</display-name></description>';
$configXml = '<oor:component-data xmlns:oor="http://openoffice.org/2001/registry" oor:name="Audit"/>';
$iconBytes = 'EXTENSION-ICON-PNG';
$scriptBytes = 'export function audit() { return true; }';
$encryptedBytes = 'ENCRYPTED-EXTENSION-BYTES';
$orphanBytes = 'ORPHAN-EXTENSION-JAR';

$descriptionSize = strlen($descriptionXml);
$configSize = strlen($configXml);
$iconSize = strlen($iconBytes);
$scriptSize = strlen($scriptBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Extensions/Audit/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Extensions/Audit/description.xml" manifest:media-type="text/xml" manifest:size="{$descriptionSize}"/>
  <manifest:file-entry manifest:full-path="Extensions/Audit/config.xcu" manifest:media-type="text/xml" manifest:size="{$configSize}"/>
  <manifest:file-entry manifest:full-path="Extensions/Audit/icon.png" manifest:media-type="image/png" manifest:size="{$iconSize}"/>
  <manifest:file-entry manifest:full-path="Extensions/Audit/script.js" manifest:media-type="application/javascript" manifest:size="{$scriptSize}"/>
  <manifest:file-entry manifest:full-path="Extensions/Audit/missing.jar" manifest:media-type="application/java-archive" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Extensions/Audit/encrypted.oxt" manifest:media-type="application/vnd.openofficeorg.extension" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="extension-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Extension package sidecars.</text:p>
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
    <dc:title>Extension Sidecar Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Extensions/Audit/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Extensions/Audit/description.xml', 'data' => $descriptionXml, 'compressionMethod' => 0],
    ['name' => 'Extensions/Audit/config.xcu', 'data' => $configXml, 'compressionMethod' => 0],
    ['name' => 'Extensions/Audit/icon.png', 'data' => $iconBytes, 'compressionMethod' => 0],
    ['name' => 'Extensions/Audit/script.js', 'data' => $scriptBytes, 'compressionMethod' => 0],
    ['name' => 'Extensions/Audit/encrypted.oxt', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Extensions/Audit/orphan.jar', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt extension package sidecars');

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

$identityEntriesByPath = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $path = $item['path'] ?? $item['fullPath'] ?? $item['part'] ?? $item['name'] ?? null;
        if (is_string($path) && $path !== '') {
            $indexed[$path] = $item;
        }
    }

    return $indexed;
};

return [
    'reports ODT extension package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $descriptionXml,
        $configXml,
        $iconBytes,
        $scriptBytes,
        $orphanBytes,
        $indexBy,
        $identityEntriesByPath
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerExtensions = $result['packageExtensions'];
        $readerItems = $indexBy($readerExtensions['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];
        $readerMediaResources = $readerProvenance['mediaResources'];
        $readerMediaResourceByPart = $indexBy($readerMediaResources['items'], 'part');
        $readerMediaResourcePrecedenceByPart = $indexBy($readerMediaResources['packageRolePrecedenceItems'], 'part');
        $readerIdentityManifest = $identityEntriesByPath($readerProvenance['packageIdentity']['manifestEntries']);
        $readerIdentityEntries = $identityEntriesByPath($readerProvenance['packageIdentity']['packageEntries']);

        $t->same($readerExtensions, $result['document']->attr('packageExtensions'));
        $t->same($readerExtensions, $result['metadata']['odfPackageExtensions']);
        $t->same($readerExtensions, $result['importReport']['packageExtensions']);
        $t->same(8, $readerExtensions['count']);
        $t->same(5, $readerExtensions['readableCount']);
        $t->same(7, $readerExtensions['declaredCount']);
        $t->same(1, $readerExtensions['undeclaredCount']);
        $t->same(1, $readerExtensions['missingCount']);
        $t->same(1, $readerExtensions['directoryCount']);
        $t->same(1, $readerExtensions['encryptedCount']);
        $t->same(0, $readerExtensions['missingMediaTypeCount']);
        $t->same(0, $readerExtensions['invalidMediaTypeCount']);
        $t->same(3, $readerExtensions['issueCount']);
        $t->same([
            'odf-extension-package-encrypted-part',
            'odf-extension-package-missing-part',
            'odf-extension-package-undeclared-part',
        ], $readerExtensions['issueCodes']);
        $t->same([
            'extension-binary-resource' => 3,
            'extension-configuration' => 1,
            'extension-directory' => 1,
            'extension-manifest' => 1,
            'extension-media-resource' => 1,
            'extension-script' => 1,
        ], $readerExtensions['kindCounts']);
        $t->same(['audit' => 8], $readerExtensions['groupCounts']);
        $t->same('extension-package-bytes-blocked', $readerExtensions['byteExposurePolicy']);
        $t->same('extension-package-metadata-only', $readerExtensions['reviewPolicy']);

        $directory = $readerItems['Extensions/Audit/'];
        $t->same('extension-directory', $directory['kind']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $description = $readerItems['Extensions/Audit/description.xml'];
        $t->same('extension-manifest', $description['kind']);
        $t->same(strlen($descriptionXml), $description['byteLength']);
        $t->same(sprintf('%08x', crc32($descriptionXml)), $description['crc32']);
        $t->same(false, $description['canExposeBytes']);
        $t->same(false, $description['canExposeAsDocumentMedia']);
        $t->same('extension-package-bytes-blocked', $description['byteExposurePolicy']);
        $t->same([], $description['issues']);

        $configuration = $readerItems['Extensions/Audit/config.xcu'];
        $t->same('extension-configuration', $configuration['kind']);
        $t->same('text/xml', $configuration['mediaTypeBase']);
        $t->same(strlen($configXml), $configuration['byteLength']);

        $icon = $readerItems['Extensions/Audit/icon.png'];
        $t->same('extension-media-resource', $icon['kind']);
        $t->same('image/png', $icon['mediaTypeBase']);
        $t->same(strlen($iconBytes), $icon['byteLength']);
        $t->same(false, $icon['canExposeAsDocumentMedia']);

        $script = $readerItems['Extensions/Audit/script.js'];
        $t->same('extension-script', $script['kind']);
        $t->same('application/javascript', $script['mediaTypeBase']);
        $t->same(strlen($scriptBytes), $script['byteLength']);

        $missing = $readerItems['Extensions/Audit/missing.jar'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-extension-package-missing-part'], $missing['issues']);
        $t->same('extension-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Extensions/Audit/encrypted.oxt'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-extension-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Extensions/Audit/orphan.jar'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('extension-binary-resource', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-extension-package-undeclared-part'], $orphan['issues']);
        $t->same('extension-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestIcon = $manifestByPart['Extensions/Audit/icon.png'];
        $t->same(true, $manifestIcon['extensionPackagePart']);
        $t->same(false, $manifestIcon['canExposeBytes']);
        $t->same(null, $manifestIcon['byteLength']);
        $t->same(strlen($iconBytes), $manifestIcon['storedByteLength']);
        $t->same(null, $manifestIcon['byteSha256']);
        $t->same('extension-package-bytes-blocked', $manifestIcon['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(1, $readerMediaResources['mediaResourceCount']);
        $t->same(4, $readerMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'Pictures/hero.png',
            'Extensions/Audit/icon.png',
            'Extensions/Audit/script.js',
            'Extensions/Audit/missing.jar',
            'Extensions/Audit/encrypted.oxt',
        ], array_column($readerMediaResources['items'], 'part'));
        $t->same(['extension-package'], $readerMediaResourceByPart['Extensions/Audit/icon.png']['packageRolePrecedence']);
        $t->same(false, $readerMediaResourceByPart['Extensions/Audit/icon.png']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $readerMediaResourceByPart['Extensions/Audit/icon.png']['issues']);
        $t->same('extension-package-bytes-blocked', $readerMediaResourcePrecedenceByPart['Extensions/Audit/icon.png']['byteExposurePolicy']);
        $t->same(7, $readerProvenance['extensionPackagePartCount']);
        $t->same(7, $readerProvenance['roleCounts']['extension-package']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['extension-package']);
        $t->same(['extension-package', 'manifest-declared'], $readerProvenance['parts']['Extensions/Audit/icon.png']['roles']);
        $t->same(['extension-package', 'undeclared-package-entry'], $readerProvenance['parts']['Extensions/Audit/orphan.jar']['roles']);
        $t->same(true, $readerProvenance['parts']['Extensions/Audit/icon.png']['extensionPackagePart']);
        $t->same(true, $readerIdentityManifest['Extensions/Audit/icon.png']['extensionPackagePart']);
        $t->same(true, $readerIdentityEntries['Extensions/Audit/icon.png']['extensionPackagePart']);
        $t->same(7, $readerProvenance['packageIdentity']['extensionPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Extension package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $descriptionXml));
        $t->same(false, str_contains($blocks, $configXml));
        $t->same(false, str_contains($blocks, $iconBytes));
        $t->same(false, str_contains($blocks, $scriptBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactExtensions = $compactSummary['packageExtensions'];
        $compactItems = $indexBy($compactExtensions['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];
        $compactMediaResources = $compactSummary['manifestReview']['mediaResources'];
        $compactMediaResourceByPart = $indexBy($compactMediaResources['items'], 'part');
        $compactMediaResourcePrecedenceByPart = $indexBy($compactMediaResources['packageRolePrecedenceItems'], 'part');
        $compactIdentityManifest = $identityEntriesByPath($compactSummary['packageIdentity']['manifestEntries']);
        $compactIdentityEntries = $identityEntriesByPath($compactSummary['packageIdentity']['packageEntries']);

        $t->same(8, $compactExtensions['count']);
        $t->same(5, $compactExtensions['readableCount']);
        $t->same(7, $compactExtensions['declaredCount']);
        $t->same(1, $compactExtensions['undeclaredCount']);
        $t->same(1, $compactExtensions['missingCount']);
        $t->same(1, $compactExtensions['directoryCount']);
        $t->same(1, $compactExtensions['encryptedCount']);
        $t->same(3, $compactExtensions['issueCount']);
        $t->same($readerExtensions['issueCodes'], $compactExtensions['issueCodes']);
        $t->same($readerExtensions['kindCounts'], $compactExtensions['kindCounts']);
        $t->same($readerExtensions['groupCounts'], $compactExtensions['groupCounts']);
        $t->same('extension-package-bytes-blocked', $compactExtensions['byteExposurePolicy']);
        $t->same('extension-package-metadata-only', $compactExtensions['reviewPolicy']);
        $t->same('extension-manifest', $compactItems['Extensions/Audit/description.xml']['kind']);
        $t->same(false, $compactItems['Extensions/Audit/description.xml']['canExposeBytes']);
        $t->same(false, $compactItems['Extensions/Audit/description.xml']['canExposeAsDocumentMedia']);
        $t->same(strlen($descriptionXml), $compactItems['Extensions/Audit/description.xml']['byteLength']);
        $t->same(sprintf('%08x', crc32($descriptionXml)), $compactItems['Extensions/Audit/description.xml']['crc32']);
        $t->same('extension-configuration', $compactItems['Extensions/Audit/config.xcu']['kind']);
        $t->same('extension-media-resource', $compactItems['Extensions/Audit/icon.png']['kind']);
        $t->same('extension-script', $compactItems['Extensions/Audit/script.js']['kind']);
        $t->same(['odf-extension-package-missing-part'], $compactItems['Extensions/Audit/missing.jar']['issues']);
        $t->same(['odf-extension-package-encrypted-part'], $compactItems['Extensions/Audit/encrypted.oxt']['issues']);
        $t->same(['odf-extension-package-undeclared-part'], $compactItems['Extensions/Audit/orphan.jar']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'packagePath'));
        $t->same(7, $compactSummary['manifestReview']['extensionPackagePartCount']);
        $t->same(true, $reviewByPath['Extensions/Audit/icon.png']['extensionPackagePart']);
        $t->same(false, $reviewByPath['Extensions/Audit/icon.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Extensions/Audit/icon.png']['byteLength']);
        $t->same(strlen($iconBytes), $reviewByPath['Extensions/Audit/icon.png']['storedByteLength']);
        $t->same('extension-package-bytes-blocked', $reviewByPath['Extensions/Audit/icon.png']['byteExposurePolicy']);
        $t->same('extension', $reviewByPath['Extensions/Audit/icon.png']['manifestMediaFamily']);
        $t->same(1, $compactMediaResources['mediaResourceCount']);
        $t->same(4, $compactMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'Pictures/hero.png',
            'Extensions/Audit/icon.png',
            'Extensions/Audit/script.js',
            'Extensions/Audit/missing.jar',
            'Extensions/Audit/encrypted.oxt',
        ], array_column($compactMediaResources['items'], 'part'));
        $t->same(['extension-package'], $compactMediaResourceByPart['Extensions/Audit/icon.png']['packageRolePrecedence']);
        $t->same(false, $compactMediaResourceByPart['Extensions/Audit/icon.png']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $compactMediaResourceByPart['Extensions/Audit/icon.png']['issues']);
        $t->same('extension-package-bytes-blocked', $compactMediaResourcePrecedenceByPart['Extensions/Audit/icon.png']['byteExposurePolicy']);
        $t->same(6, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['extension']);
        $t->same(7, $inventory['extensionPackagePartCount']);
        $t->same(7, $inventory['roleCounts']['extension-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['extension-package']);
        $t->same(['extension-package', 'manifest-declared'], $inventory['parts']['Extensions/Audit/icon.png']['roles']);
        $t->same(['extension-package', 'undeclared-package-entry'], $inventory['parts']['Extensions/Audit/orphan.jar']['roles']);
        $t->same(true, $inventory['parts']['Extensions/Audit/icon.png']['extensionPackagePart']);
        $t->same(false, $inventory['parts']['Extensions/Audit/icon.png']['canExposeBytes']);
        $t->same(true, $compactIdentityManifest['Extensions/Audit/icon.png']['extensionPackagePart']);
        $t->same(true, $compactIdentityEntries['Extensions/Audit/icon.png']['extensionPackagePart']);
        $t->same(7, $compactSummary['packageIdentity']['extensionPackagePartCount']);
    },
];
