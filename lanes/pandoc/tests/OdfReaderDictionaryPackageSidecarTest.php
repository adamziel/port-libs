<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$wordList = "review\npacket\n";
$affixRules = "SET UTF-8\nSFX A Y 1\n";
$dictionaryMetadata = '<dictionary name="review" locale="en-US"/>';
$encryptedWordList = 'SECRET-DICTIONARY-WORDS';
$orphanWordList = "orphan\nterm\n";

$wordListSize = strlen($wordList);
$affixRulesSize = strlen($affixRules);
$dictionaryMetadataSize = strlen($dictionaryMetadata);
$encryptedWordListSize = strlen($encryptedWordList);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Dictionaries/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US.dic" manifest:media-type="text/plain" manifest:size="{$wordListSize}"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US.aff" manifest:media-type="text/plain" manifest:size="{$affixRulesSize}"/>
  <manifest:file-entry manifest:full-path="Dictionaries/config.xml" manifest:media-type="text/xml" manifest:size="{$dictionaryMetadataSize}"/>
  <manifest:file-entry manifest:full-path="Dictionaries/missing.dic" manifest:media-type="text/plain" manifest:size="9"/>
  <manifest:file-entry manifest:full-path="Dictionaries/encrypted.dic" manifest:media-type="text/plain" manifest:size="{$encryptedWordListSize}">
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
      <text:p>Dictionary package sidecar review.</text:p>
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
    <dc:title>Dictionary Package Review</dc:title>
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
    ['name' => 'Dictionaries/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US.dic', 'data' => $wordList, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/en_US.aff', 'data' => $affixRules, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/config.xml', 'data' => $dictionaryMetadata, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/encrypted.dic', 'data' => $encryptedWordList, 'compressionMethod' => 0],
    ['name' => 'Dictionaries/custom.dic', 'data' => $orphanWordList, 'compressionMethod' => 0],
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

return [
    'reports ODT dictionary package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $wordList,
        $affixRules,
        $dictionaryMetadata,
        $orphanWordList,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerDictionaries = $result['packageDictionaries'];
        $readerItems = $indexBy($readerDictionaries['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerDictionaries, $result['document']->attr('packageDictionaries'));
        $t->same($readerDictionaries, $result['metadata']['odfPackageDictionaries']);
        $t->same($readerDictionaries, $result['importReport']['packageDictionaries']);
        $t->same(7, $readerDictionaries['count']);
        $t->same(4, $readerDictionaries['readableCount']);
        $t->same(6, $readerDictionaries['declaredCount']);
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
        $t->same('dictionary-package-bytes-blocked', $readerDictionaries['byteExposurePolicy']);
        $t->same('dictionary-package-metadata-only', $readerDictionaries['reviewPolicy']);

        $word = $readerItems['Dictionaries/en_US.dic'];
        $t->same('dictionary-word-list', $word['kind']);
        $t->same(true, $word['declared']);
        $t->same(true, $word['valid']);
        $t->same(false, $word['canExposeBytes']);
        $t->same(false, $word['canExposeAsDocumentMedia']);
        $t->same(strlen($wordList), $word['byteLength']);
        $t->same(sprintf('%08x', crc32($wordList)), $word['crc32']);
        $t->same('dictionary-package-bytes-blocked', $word['byteExposurePolicy']);
        $t->same([], $word['issues']);

        $affix = $readerItems['Dictionaries/en_US.aff'];
        $t->same('dictionary-affix-rules', $affix['kind']);
        $t->same(strlen($affixRules), $affix['byteLength']);

        $metadata = $readerItems['Dictionaries/config.xml'];
        $t->same('dictionary-metadata', $metadata['kind']);
        $t->same(strlen($dictionaryMetadata), $metadata['storedByteLength']);

        $missing = $readerItems['Dictionaries/missing.dic'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-dictionary-package-missing-part'], $missing['issues']);
        $t->same('dictionary-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Dictionaries/encrypted.dic'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-dictionary-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $undeclared = $readerItems['Dictionaries/custom.dic'];
        $t->same(false, $undeclared['declared']);
        $t->same(true, $undeclared['undeclared']);
        $t->same('dictionary-word-list', $undeclared['kind']);
        $t->same(strlen($orphanWordList), $undeclared['byteLength']);
        $t->same(['odf-dictionary-package-undeclared-part'], $undeclared['issues']);
        $t->same('dictionary-package-bytes-blocked', $undeclared['byteExposurePolicy']);

        $manifestWord = $manifestByPart['Dictionaries/en_US.dic'];
        $t->same(true, $manifestWord['dictionaryPackagePart']);
        $t->same(false, $manifestWord['canExposeBytes']);
        $t->same(null, $manifestWord['byteLength']);
        $t->same(strlen($wordList), $manifestWord['storedByteLength']);
        $t->same(null, $manifestWord['byteSha256']);
        $t->same('dictionary-package-bytes-blocked', $manifestWord['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(6, $readerProvenance['dictionaryPackagePartCount']);
        $t->same(6, $readerProvenance['roleCounts']['dictionary-package']);
        $t->same(['dictionary-package', 'manifest-declared'], $readerProvenance['parts']['Dictionaries/en_US.dic']['roles']);
        $t->same(['dictionary-package', 'undeclared-package-entry'], $readerProvenance['parts']['Dictionaries/custom.dic']['roles']);
        $t->same(true, $readerProvenance['parts']['Dictionaries/en_US.dic']['dictionaryPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['manifestEntries'][6]['dictionaryPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['packageEntries'][7]['dictionaryPackagePart']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactDictionaries = $compactSummary['packageDictionaries'];
        $compactItems = $indexBy($compactDictionaries['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(7, $compactDictionaries['count']);
        $t->same(4, $compactDictionaries['readableCount']);
        $t->same(6, $compactDictionaries['declaredCount']);
        $t->same(1, $compactDictionaries['undeclaredCount']);
        $t->same(1, $compactDictionaries['missingCount']);
        $t->same(1, $compactDictionaries['directoryCount']);
        $t->same(1, $compactDictionaries['encryptedCount']);
        $t->same(3, $compactDictionaries['issueCount']);
        $t->same($readerDictionaries['issueCodes'], $compactDictionaries['issueCodes']);
        $t->same('dictionary-package-bytes-blocked', $compactDictionaries['byteExposurePolicy']);
        $t->same('dictionary-package-metadata-only', $compactDictionaries['reviewPolicy']);
        $t->same('dictionary-word-list', $compactItems['Dictionaries/en_US.dic']['kind']);
        $t->same('dictionary-affix-rules', $compactItems['Dictionaries/en_US.aff']['kind']);
        $t->same(false, $compactItems['Dictionaries/en_US.dic']['canExposeBytes']);
        $t->same(false, $compactItems['Dictionaries/en_US.dic']['canExposeAsDocumentMedia']);
        $t->same(strlen($wordList), $compactItems['Dictionaries/en_US.dic']['byteLength']);
        $t->same(sprintf('%08x', crc32($wordList)), $compactItems['Dictionaries/en_US.dic']['crc32']);
        $t->same(['odf-dictionary-package-missing-part'], $compactItems['Dictionaries/missing.dic']['issues']);
        $t->same(['odf-dictionary-package-encrypted-part'], $compactItems['Dictionaries/encrypted.dic']['issues']);
        $t->same(['odf-dictionary-package-undeclared-part'], $compactItems['Dictionaries/custom.dic']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(6, $compactSummary['manifestReview']['dictionaryPackagePartCount']);
        $t->same(true, $reviewByPath['Dictionaries/en_US.dic']['dictionaryPackagePart']);
        $t->same(false, $reviewByPath['Dictionaries/en_US.dic']['canExposeBytes']);
        $t->same(null, $reviewByPath['Dictionaries/en_US.dic']['byteLength']);
        $t->same(strlen($wordList), $reviewByPath['Dictionaries/en_US.dic']['storedByteLength']);
        $t->same('dictionary-package-bytes-blocked', $reviewByPath['Dictionaries/en_US.dic']['byteExposurePolicy']);
        $t->same('dictionary', $reviewByPath['Dictionaries/en_US.dic']['manifestMediaFamily']);
        $t->same(5, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['dictionary']);
        $t->same(6, $inventory['dictionaryPackagePartCount']);
        $t->same(6, $inventory['roleCounts']['dictionary-package']);
        $t->same(['dictionary-package', 'manifest-declared'], $inventory['parts']['Dictionaries/en_US.dic']['roles']);
        $t->same(['dictionary-package', 'undeclared-package-entry'], $inventory['parts']['Dictionaries/custom.dic']['roles']);
        $t->same(true, $inventory['parts']['Dictionaries/en_US.dic']['dictionaryPackagePart']);
        $t->same(false, $inventory['parts']['Dictionaries/en_US.dic']['canExposeBytes']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Dictionary package sidecar review.', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'review' . "\n" . 'packet'), 'Dictionary word-list bytes must not be rendered into WordPress output');
        $t->true(!str_contains($blocksHtml, 'orphan' . "\n" . 'term'), 'Undeclared dictionary bytes must not be rendered into WordPress output');
    },
];
