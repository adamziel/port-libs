<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$dictionaryManifestXml = '<dictionaries xmlns="urn:example:dictionaries"><dictionary locale="en-US"/></dictionaries>';
$dicBytes = "dictionary\npackage\n";
$affBytes = "SET UTF-8\n";
$previewBytes = 'DICTIONARY-PREVIEW-PNG';
$encryptedBytes = 'ENCRYPTED-DICTIONARY-BYTES';
$orphanBytes = "orphan\nword\n";

$dictionaryManifestSize = strlen($dictionaryManifestXml);
$dicSize = strlen($dicBytes);
$affSize = strlen($affBytes);
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/manifest.xml" manifest:media-type="text/xml" manifest:size="{$dictionaryManifestSize}"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/en_US.dic" manifest:media-type="text/plain" manifest:size="{$dicSize}"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/en_US.aff" manifest:media-type="text/plain" manifest:size="{$affSize}"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/missing.dic" manifest:media-type="text/plain" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/encrypted.dic" manifest:media-type="text/plain" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="dictionary-checksum"/>
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
      <text:p>Dictionary package sidecars.</text:p>
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
    <dc:title>Dictionary Sidecar Packet</dc:title>
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
    ['name' => 'Dictionaries/en_US/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US/manifest.xml', 'data' => $dictionaryManifestXml, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US/en_US.dic', 'data' => $dicBytes, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US/en_US.aff', 'data' => $affBytes, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US/encrypted.dic', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US/orphan.dic', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt dictionary package sidecars');

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
    'reports ODT dictionary package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $dictionaryManifestXml,
        $dicBytes,
        $affBytes,
        $previewBytes,
        $orphanBytes,
        $indexBy,
        $identityEntriesByPath
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerDictionaries = $result['packageDictionaries'];
        $readerItems = $indexBy($readerDictionaries['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];
        $readerIdentityManifest = $identityEntriesByPath($readerProvenance['packageIdentity']['manifestEntries']);
        $readerIdentityEntries = $identityEntriesByPath($readerProvenance['packageIdentity']['packageEntries']);

        $t->same($readerDictionaries, $result['document']->attr('packageDictionaries'));
        $t->same($readerDictionaries, $result['metadata']['odfPackageDictionaries']);
        $t->same($readerDictionaries, $result['importReport']['packageDictionaries']);
        $t->same(8, $readerDictionaries['count']);
        $t->same(5, $readerDictionaries['readableCount']);
        $t->same(7, $readerDictionaries['declaredCount']);
        $t->same(1, $readerDictionaries['undeclaredCount']);
        $t->same(1, $readerDictionaries['missingCount']);
        $t->same(1, $readerDictionaries['directoryCount']);
        $t->same(1, $readerDictionaries['encryptedCount']);
        $t->same(0, $readerDictionaries['missingMediaTypeCount']);
        $t->same(0, $readerDictionaries['invalidMediaTypeCount']);
        $t->same(3, $readerDictionaries['issueCount']);
        $t->same([
            'odf-dictionary-package-encrypted-part',
            'odf-dictionary-package-missing-part',
            'odf-dictionary-package-undeclared-part',
        ], $readerDictionaries['issueCodes']);
        $t->same([
            'dictionary-directory' => 1,
            'dictionary-manifest' => 1,
            'dictionary-preview-media' => 1,
            'dictionary-word-list' => 5,
        ], $readerDictionaries['kindCounts']);
        $t->same(['en_us' => 8], $readerDictionaries['groupCounts']);
        $t->same('dictionary-package-bytes-blocked', $readerDictionaries['byteExposurePolicy']);
        $t->same('dictionary-package-metadata-only', $readerDictionaries['reviewPolicy']);

        $directory = $readerItems['Dictionaries/en_US/'];
        $t->same('dictionary-directory', $directory['kind']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $dictionaryManifest = $readerItems['Dictionaries/en_US/manifest.xml'];
        $t->same('dictionary-manifest', $dictionaryManifest['kind']);
        $t->same(strlen($dictionaryManifestXml), $dictionaryManifest['byteLength']);
        $t->same(sprintf('%08x', crc32($dictionaryManifestXml)), $dictionaryManifest['crc32']);
        $t->same(false, $dictionaryManifest['canExposeBytes']);
        $t->same(false, $dictionaryManifest['canExposeAsDocumentMedia']);
        $t->same('dictionary-package-bytes-blocked', $dictionaryManifest['byteExposurePolicy']);

        $dictionary = $readerItems['Dictionaries/en_US/en_US.dic'];
        $t->same('dictionary-word-list', $dictionary['kind']);
        $t->same('text/plain', $dictionary['mediaTypeBase']);
        $t->same(strlen($dicBytes), $dictionary['byteLength']);
        $t->same(false, $dictionary['canExposeBytes']);

        $affix = $readerItems['Dictionaries/en_US/en_US.aff'];
        $t->same('dictionary-word-list', $affix['kind']);
        $t->same(strlen($affBytes), $affix['byteLength']);

        $preview = $readerItems['Dictionaries/en_US/preview.png'];
        $t->same('dictionary-preview-media', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $missing = $readerItems['Dictionaries/en_US/missing.dic'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-dictionary-package-missing-part'], $missing['issues']);

        $encrypted = $readerItems['Dictionaries/en_US/encrypted.dic'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-dictionary-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Dictionaries/en_US/orphan.dic'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('dictionary-word-list', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-dictionary-package-undeclared-part'], $orphan['issues']);

        $manifestPreview = $manifestByPart['Dictionaries/en_US/preview.png'];
        $t->same(true, $manifestPreview['dictionaryPackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($previewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('dictionary-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(7, $readerProvenance['dictionaryPackagePartCount']);
        $t->same(7, $readerProvenance['roleCounts']['dictionary-package']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['dictionary-package']);
        $t->same(['dictionary-package', 'manifest-declared'], $readerProvenance['parts']['Dictionaries/en_US/preview.png']['roles']);
        $t->same(['dictionary-package', 'undeclared-package-entry'], $readerProvenance['parts']['Dictionaries/en_US/orphan.dic']['roles']);
        $t->same(true, $readerProvenance['parts']['Dictionaries/en_US/preview.png']['dictionaryPackagePart']);
        $t->same(true, $readerIdentityManifest['Dictionaries/en_US/preview.png']['dictionaryPackagePart']);
        $t->same(true, $readerIdentityEntries['Dictionaries/en_US/preview.png']['dictionaryPackagePart']);
        $t->same(7, $readerProvenance['packageIdentity']['dictionaryPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Dictionary package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $dictionaryManifestXml));
        $t->same(false, str_contains($blocks, $dicBytes));
        $t->same(false, str_contains($blocks, $affBytes));
        $t->same(false, str_contains($blocks, $previewBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactDictionaries = $compactSummary['packageDictionaries'];
        $compactItems = $indexBy($compactDictionaries['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];
        $compactMediaResources = $compactSummary['manifestReview']['mediaResources'];
        $compactMediaResourceByPart = $indexBy($compactMediaResources['items'], 'part');
        $compactMediaResourcePrecedenceByPart = $indexBy($compactMediaResources['packageRolePrecedenceItems'], 'part');
        $compactIdentityManifest = $identityEntriesByPath($compactSummary['packageIdentity']['manifestEntries']);
        $compactIdentityEntries = $identityEntriesByPath($compactSummary['packageIdentity']['packageEntries']);

        $t->same(8, $compactDictionaries['count']);
        $t->same(5, $compactDictionaries['readableCount']);
        $t->same(7, $compactDictionaries['declaredCount']);
        $t->same(1, $compactDictionaries['undeclaredCount']);
        $t->same(1, $compactDictionaries['missingCount']);
        $t->same(1, $compactDictionaries['directoryCount']);
        $t->same(1, $compactDictionaries['encryptedCount']);
        $t->same(3, $compactDictionaries['issueCount']);
        $t->same($readerDictionaries['issueCodes'], $compactDictionaries['issueCodes']);
        $t->same('dictionary-package-bytes-blocked', $compactDictionaries['byteExposurePolicy']);
        $t->same('dictionary-package-metadata-only', $compactDictionaries['reviewPolicy']);
        $t->same('dictionary-manifest', $compactItems['Dictionaries/en_US/manifest.xml']['kind']);
        $t->same(false, $compactItems['Dictionaries/en_US/manifest.xml']['canExposeBytes']);
        $t->same(false, $compactItems['Dictionaries/en_US/manifest.xml']['canExposeAsDocumentMedia']);
        $t->same(strlen($dictionaryManifestXml), $compactItems['Dictionaries/en_US/manifest.xml']['byteLength']);
        $t->same(sprintf('%08x', crc32($dictionaryManifestXml)), $compactItems['Dictionaries/en_US/manifest.xml']['crc32']);
        $t->same('dictionary-word-list', $compactItems['Dictionaries/en_US/en_US.dic']['kind']);
        $t->same('dictionary-preview-media', $compactItems['Dictionaries/en_US/preview.png']['kind']);
        $t->same(['odf-dictionary-package-missing-part'], $compactItems['Dictionaries/en_US/missing.dic']['issues']);
        $t->same(['odf-dictionary-package-encrypted-part'], $compactItems['Dictionaries/en_US/encrypted.dic']['issues']);
        $t->same(['odf-dictionary-package-undeclared-part'], $compactItems['Dictionaries/en_US/orphan.dic']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'packagePath'));
        $t->same(7, $compactSummary['manifestReview']['dictionaryPackagePartCount']);
        $t->same(true, $reviewByPath['Dictionaries/en_US/preview.png']['dictionaryPackagePart']);
        $t->same(false, $reviewByPath['Dictionaries/en_US/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Dictionaries/en_US/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['Dictionaries/en_US/preview.png']['storedByteLength']);
        $t->same('dictionary-package-bytes-blocked', $reviewByPath['Dictionaries/en_US/preview.png']['byteExposurePolicy']);
        $t->same('dictionary', $reviewByPath['Dictionaries/en_US/preview.png']['manifestMediaFamily']);
        $t->same(1, $compactMediaResources['mediaResourceCount']);
        $t->same(5, $compactMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'Pictures/hero.png',
            'Dictionaries/en_US/en_US.dic',
            'Dictionaries/en_US/en_US.aff',
            'Dictionaries/en_US/preview.png',
            'Dictionaries/en_US/missing.dic',
            'Dictionaries/en_US/encrypted.dic',
        ], array_column($compactMediaResources['items'], 'part'));
        $t->same(['dictionary-package'], $compactMediaResourceByPart['Dictionaries/en_US/preview.png']['packageRolePrecedence']);
        $t->same(false, $compactMediaResourceByPart['Dictionaries/en_US/preview.png']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $compactMediaResourceByPart['Dictionaries/en_US/preview.png']['issues']);
        $t->same('dictionary-package-bytes-blocked', $compactMediaResourcePrecedenceByPart['Dictionaries/en_US/preview.png']['byteExposurePolicy']);
        $t->same(6, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['dictionary']);
        $t->same(7, $inventory['dictionaryPackagePartCount']);
        $t->same(7, $inventory['roleCounts']['dictionary-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['dictionary-package']);
        $t->same(['dictionary-package', 'manifest-declared'], $inventory['parts']['Dictionaries/en_US/preview.png']['roles']);
        $t->same(['dictionary-package', 'undeclared-package-entry'], $inventory['parts']['Dictionaries/en_US/orphan.dic']['roles']);
        $t->same(true, $inventory['parts']['Dictionaries/en_US/preview.png']['dictionaryPackagePart']);
        $t->same(true, $compactIdentityManifest['Dictionaries/en_US/preview.png']['dictionaryPackagePart']);
        $t->same(true, $compactIdentityEntries['Dictionaries/en_US/preview.png']['dictionaryPackagePart']);
        $t->same(7, $compactSummary['packageIdentity']['dictionaryPackagePartCount']);
    },
];
