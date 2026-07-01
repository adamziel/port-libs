<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$nonAsciiDirectory = "objects/caf\xC3\xA9";
$nonAsciiPart = $nonAsciiDirectory . '/review.xml';

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="customXml/item.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="pictures draft/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="encoded%20dir/review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="{$nonAsciiPart}" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="pictures/Review.PNG" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package directory name character review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="DirectoryNameReview" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package Directory Name Character Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'customXml/item.xml', 'data' => '<custom/>', 'compressionMethod' => 0],
    ['name' => 'customXml/Missing.DATA', 'data' => str_repeat('M', 21), 'compressionMethod' => 0],
    ['name' => 'pictures draft/review.png', 'data' => 'draft image', 'compressionMethod' => 0],
    ['name' => 'encoded%20dir/review.xml', 'data' => '<encoded/>', 'compressionMethod' => 0],
    ['name' => $nonAsciiPart, 'data' => '<non-ascii/>', 'compressionMethod' => 0],
    ['name' => 'pictures/Review.PNG', 'data' => 'basename uppercase only', 'compressionMethod' => 0],
], 'odt package directory name character review');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODF package directory name character flags across compact and rich provenance' => static function (TestRunner $t) use ($buildPackage, $indexBy, $nonAsciiDirectory, $nonAsciiPart): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactIdentityParts = $indexBy($compactIdentity['packageEntries'], 'path');

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');

        $expectedDirectories = [
            'META-INF/',
            'customXml/',
            'encoded%20dir/',
            $nonAsciiDirectory . '/',
            'pictures draft/',
        ];
        $expectedFlagCounts = [
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 3,
            'whitespace' => 1,
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(5, $handoff['packageDirectoryNameCharacterReviewDirectoryCount']);
            $t->same(6, $handoff['packageDirectoryNameCharacterReviewEntryCount']);
            $t->same(3, $handoff['packageDirectoryNameUppercaseEntryCount']);
            $t->same(1, $handoff['packageDirectoryNameWhitespaceEntryCount']);
            $t->same(1, $handoff['packageDirectoryNamePercentEncodedOctetEntryCount']);
            $t->same(1, $handoff['packageDirectoryNameNonAsciiEntryCount']);
            $t->same($expectedFlagCounts, $handoff['packageDirectoryNameCharacterFlagEntryCounts']);
            $t->same($expectedDirectories, $handoff['packageDirectoryNameCharacterReviewDirectoryNames']);
            $t->same(['META-INF/', 'customXml/'], $handoff['packageDirectoryNameCharacterFlagDirectories']['uppercase']);
            $t->same(['pictures draft/'], $handoff['packageDirectoryNameCharacterFlagDirectories']['whitespace']);
            $t->same(['encoded%20dir/'], $handoff['packageDirectoryNameCharacterFlagDirectories']['percent-encoded-octet']);
            $t->same([$nonAsciiDirectory . '/'], $handoff['packageDirectoryNameCharacterFlagDirectories']['non-ascii']);
            $t->same(
                ['META-INF/manifest.xml', 'customXml/Missing.DATA', 'customXml/item.xml'],
                $handoff['packageDirectoryNameCharacterFlagEntryNames']['uppercase']
            );
            $t->same(['pictures draft/review.png'], $handoff['packageDirectoryNameCharacterFlagEntryNames']['whitespace']);
            $t->same(['encoded%20dir/review.xml'], $handoff['packageDirectoryNameCharacterFlagEntryNames']['percent-encoded-octet']);
            $t->same([$nonAsciiPart], $handoff['packageDirectoryNameCharacterFlagEntryNames']['non-ascii']);
        }

        $compactDirectories = $indexBy($compactInventory['packageDirectoryNameCharacterReviewDirectories'], 'directory');
        $richDirectories = $indexBy($richProvenance['packageDirectoryNameCharacterReviewDirectories'], 'directory');

        foreach ([$compactDirectories['customXml/'], $richDirectories['customXml/']] as $customDirectory) {
            $t->same('customXml/', $customDirectory['directory']);
            $t->same(1, $customDirectory['directoryDepth']);
            $t->same(2, $customDirectory['entryCount']);
            $t->same(2, $customDirectory['fileEntryCount']);
            $t->same(1, $customDirectory['declaredEntryCount']);
            $t->same(1, $customDirectory['undeclaredEntryCount']);
            $t->same(30, $customDirectory['byteLength']);
            $t->same(['uppercase'], $customDirectory['flags']);
            $t->same(['uppercase' => 2], $customDirectory['flagEntryCounts']);
            $t->same(['Missing.DATA' => 1, 'item.xml' => 1], $customDirectory['basenameCounts']);
            $t->same(['data' => 1, 'xml' => 1], $customDirectory['packagePartExtensionCounts']);
            $t->same(['manifest-declared' => 1, 'undeclared-package-entry' => 1], $customDirectory['roleCounts']);
            $t->same(['(missing)' => 1, 'text/xml' => 1], $customDirectory['manifestMediaTypeBaseCounts']);
            $t->same(['customXml/Missing.DATA', 'customXml/item.xml'], $customDirectory['entryNames']);
            $t->same('customXml/Missing.DATA', $customDirectory['largestEntry']['entryName']);
            $t->same('odf-package-directory-name-character-metadata-only', $customDirectory['reviewPolicy']);
            $t->same(false, $customDirectory['canExposeBytes']);
        }

        $t->same(['uppercase'], $compactInventory['parts']['customXml/item.xml']['directoryNameCharacterFlags']);
        $t->same(true, $compactInventory['parts']['customXml/item.xml']['directoryNameHasUppercase']);
        $t->same(true, $richProvenance['parts']['pictures draft/review.png']['directoryNameHasWhitespace']);
        $t->same(true, $richProvenance['parts']['encoded%20dir/review.xml']['directoryNameHasPercentEncodedOctet']);
        $t->same(true, $compactInventory['parts'][$nonAsciiPart]['directoryNameHasNonAscii']);
        $t->same([], $compactInventory['parts']['pictures/Review.PNG']['directoryNameCharacterFlags']);
        $t->same(false, $richProvenance['parts']['pictures/Review.PNG']['directoryNameHasUppercase']);
        $t->same(false, in_array('pictures/Review.PNG', $richProvenance['packageDirectoryNameCharacterFlagEntryNames']['uppercase'], true));

        $t->same(['uppercase'], $compactIdentityParts['customXml/Missing.DATA']['directoryNameCharacterFlags']);
        $t->same(true, $richIdentityParts[$nonAsciiPart]['directoryNameHasNonAscii']);
        $t->same(
            $richProvenance['packageDirectoryNameCharacterFlagEntryCounts'],
            $documentProvenance['packageDirectoryNameCharacterFlagEntryCounts']
        );
    },
];
