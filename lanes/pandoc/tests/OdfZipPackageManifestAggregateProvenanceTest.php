<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP package manifest aggregate provenance.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Package Manifest Aggregate Provenance</dc:title>
  </office:meta>
</office:document-meta>
XML;

$mediaBytes = 'REVIEW-PNG';
$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
XML;
$manifestXml .= '  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png" manifest:size="' . strlen($mediaBytes) . '"/>' . "\n"
    . '</manifest:manifest>';

$addCentralDirectorySignatureRecord = static function (ZipPackage $package, string $signatureData): ZipPackage {
    $bytes = $package->bytes();
    $eocdOffset = strrpos($bytes, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('Unable to locate ZIP end of central directory record');
    }

    $signatureRecord = "PK\x05\x05" . pack('v', strlen($signatureData)) . $signatureData;

    return ZipPackage::fromString(
        substr($bytes, 0, $eocdOffset)
        . $signatureRecord
        . substr($bytes, $eocdOffset)
    );
};

$extraField = pack('vva*', 0xcafe, strlen('odf-aggregate'), 'odf-aggregate');
$signatureData = 'odf-aggregate-central-directory-signature';
$package = $addCentralDirectorySignatureRecord(ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0, 'comment' => 'manifest aggregate'],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8, 'comment' => 'content aggregate'],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/review.png', 'data' => $mediaBytes, 'compressionMethod' => 0, 'extraFieldData' => $extraField, 'comment' => 'media aggregate', 'creatorHostSystem' => 10],
], 'odt aggregate package manifest'), $signatureData);

$aggregateFields = [
    'manifestVersion' => 'zipPackageManifestVersion',
    'archiveBytes' => 'zipPackageManifestArchiveBytes',
    'archiveLength' => 'zipPackageManifestArchiveLength',
    'entryCount' => 'zipPackageManifestEntryCount',
    'fileEntryCount' => 'zipPackageManifestFileEntryCount',
    'directoryEntryCount' => 'zipPackageManifestDirectoryEntryCount',
    'compressedBytes' => 'zipPackageManifestCompressedBytes',
    'uncompressedBytes' => 'zipPackageManifestUncompressedBytes',
    'localHeaderBytes' => 'zipPackageManifestLocalHeaderBytes',
    'localHeaderFixedHeaderBytes' => 'zipPackageManifestLocalHeaderFixedHeaderBytes',
    'localHeaderVariableFieldBytes' => 'zipPackageManifestLocalHeaderVariableFieldBytes',
    'localHeaderRawNameBytes' => 'zipPackageManifestLocalHeaderRawNameBytes',
    'localHeaderExtraFieldBytes' => 'zipPackageManifestLocalHeaderExtraFieldBytes',
    'localHeaderReviewFieldBytes' => 'zipPackageManifestLocalHeaderReviewFieldBytes',
    'localExtraFieldEntryCount' => 'zipPackageManifestLocalExtraFieldEntryCount',
    'hasLocalHeaderReviewFields' => 'zipPackageManifestHasLocalHeaderReviewFields',
    'localRecordBytes' => 'zipPackageManifestLocalRecordBytes',
    'dataDescriptorEntryCount' => 'zipPackageManifestDataDescriptorEntryCount',
    'dataDescriptorBytes' => 'zipPackageManifestDataDescriptorBytes',
    'storedEntryCount' => 'zipPackageManifestStoredEntryCount',
    'deflatedEntryCount' => 'zipPackageManifestDeflatedEntryCount',
    'unsupportedCompressionMethodCount' => 'zipPackageManifestUnsupportedCompressionMethodCount',
    'centralDirectoryRecordBytes' => 'zipPackageManifestCentralDirectoryRecordBytes',
    'centralDirectoryFixedHeaderBytes' => 'zipPackageManifestCentralDirectoryFixedHeaderBytes',
    'centralDirectoryVariableFieldBytes' => 'zipPackageManifestCentralDirectoryVariableFieldBytes',
    'centralDirectoryRawNameBytes' => 'zipPackageManifestCentralDirectoryRawNameBytes',
    'centralDirectoryExtraFieldBytes' => 'zipPackageManifestCentralDirectoryExtraFieldBytes',
    'centralDirectoryRawCommentBytes' => 'zipPackageManifestCentralDirectoryRawCommentBytes',
    'centralDirectoryReviewFieldBytes' => 'zipPackageManifestCentralDirectoryReviewFieldBytes',
    'centralExtraFieldEntryCount' => 'zipPackageManifestCentralExtraFieldEntryCount',
    'entryCommentCount' => 'zipPackageManifestEntryCommentCount',
    'hasPackageComment' => 'zipPackageManifestHasPackageComment',
    'hasCentralDirectorySignature' => 'zipPackageManifestHasCentralDirectorySignature',
    'centralDirectorySignatureOffset' => 'zipPackageManifestCentralDirectorySignatureOffset',
    'centralDirectorySignatureDataOffset' => 'zipPackageManifestCentralDirectorySignatureDataOffset',
    'centralDirectorySignatureEnd' => 'zipPackageManifestCentralDirectorySignatureEnd',
    'centralDirectorySignatureBytes' => 'zipPackageManifestCentralDirectorySignatureBytes',
    'centralDirectorySignatureRecordBytes' => 'zipPackageManifestCentralDirectorySignatureRecordBytes',
    'centralDirectorySignaturePreviewHex' => 'zipPackageManifestCentralDirectorySignaturePreviewHex',
    'centralDirectorySignaturePreviewByteCount' => 'zipPackageManifestCentralDirectorySignaturePreviewByteCount',
    'centralDirectorySignatureSha256' => 'zipPackageManifestCentralDirectorySignatureSha256',
    'centralDirectorySignatureLocation' => 'zipPackageManifestCentralDirectorySignatureLocation',
    'centralDirectorySignatureVerification' => 'zipPackageManifestCentralDirectorySignatureVerification',
    'centralDirectorySignatureByteExposurePolicy' => 'zipPackageManifestCentralDirectorySignatureByteExposurePolicy',
    'centralDirectorySignatureCanExposeBytes' => 'zipPackageManifestCentralDirectorySignatureCanExposeBytes',
    'hasCentralDirectoryReviewFields' => 'zipPackageManifestHasCentralDirectoryReviewFields',
    'maxPathSegmentCount' => 'zipPackageManifestMaxPathSegmentCount',
    'maxDirectoryDepth' => 'zipPackageManifestMaxDirectoryDepth',
    'deepestEntryNames' => 'zipPackageManifestDeepestEntryNames',
    'compressionMethodSummaryCount' => 'zipPackageManifestCompressionMethodSummaryCount',
    'compressionMethodSummaries' => 'zipPackageManifestCompressionMethodSummaries',
    'creatorHostSystemSummaryCount' => 'zipPackageManifestCreatorHostSystemSummaryCount',
    'knownCreatorHostSystemEntryCount' => 'zipPackageManifestKnownCreatorHostSystemEntryCount',
    'unknownCreatorHostSystemEntryCount' => 'zipPackageManifestUnknownCreatorHostSystemEntryCount',
    'creatorVersionMeetsNeededEntryCount' => 'zipPackageManifestCreatorVersionMeetsNeededEntryCount',
    'creatorVersionBelowNeededEntryCount' => 'zipPackageManifestCreatorVersionBelowNeededEntryCount',
    'creatorVersionEqualNeededEntryCount' => 'zipPackageManifestCreatorVersionEqualNeededEntryCount',
    'creatorVersionAboveNeededEntryCount' => 'zipPackageManifestCreatorVersionAboveNeededEntryCount',
    'creatorVersionBelowNeededKnownHostEntryCount' => 'zipPackageManifestCreatorVersionBelowNeededKnownHostEntryCount',
    'creatorVersionBelowNeededUnknownHostEntryCount' => 'zipPackageManifestCreatorVersionBelowNeededUnknownHostEntryCount',
    'hasUnknownCreatorHostSystems' => 'zipPackageManifestHasUnknownCreatorHostSystems',
    'hasCreatorVersionBelowNeededEntries' => 'zipPackageManifestHasCreatorVersionBelowNeededEntries',
    'creatorVersionComparisonCounts' => 'zipPackageManifestCreatorVersionComparisonCounts',
    'creatorHostSystemSummaries' => 'zipPackageManifestCreatorHostSystemSummaries',
    'unknownCreatorHostSystemEntries' => 'zipPackageManifestUnknownCreatorHostSystemEntries',
    'creatorVersionBelowNeededEntries' => 'zipPackageManifestCreatorVersionBelowNeededEntries',
    'directoryRootCount' => 'zipPackageManifestDirectoryRootCount',
    'directoryRoots' => 'zipPackageManifestDirectoryRoots',
    'directoryRootSummaries' => 'zipPackageManifestDirectoryRootSummaries',
    'extensionlessPackagePartCount' => 'zipPackageManifestExtensionlessPackagePartCount',
    'hasExtensionlessPackageParts' => 'zipPackageManifestHasExtensionlessPackageParts',
    'packagePartExtensionSummaryCount' => 'zipPackageManifestPackagePartExtensionSummaryCount',
    'packagePartExtensions' => 'zipPackageManifestPackagePartExtensions',
    'packagePartExtensionSummaries' => 'zipPackageManifestPackagePartExtensionSummaries',
    'centralDirectoryOrderNames' => 'zipPackageManifestCentralDirectoryOrderNames',
    'localHeaderOrderNames' => 'zipPackageManifestLocalHeaderOrderNames',
    'centralDirectoryOrderMatchesLocalHeaderOrder' => 'zipPackageManifestCentralDirectoryOrderMatchesLocalHeaderOrder',
];

return [
    'carries ODT ZIP package manifest aggregate byte-layout provenance through compact and rich identities' => static function (TestRunner $t) use ($package, $aggregateFields): void {
        $zipManifest = $package->packageManifestPreflight();
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];

        foreach ($aggregateFields as $manifestKey => $provenanceKey) {
            $t->same($zipManifest[$manifestKey], $compactInventory[$provenanceKey], "{$provenanceKey} compact inventory");
            $t->same($zipManifest[$manifestKey], $compactIdentity[$provenanceKey], "{$provenanceKey} compact identity");
            $t->same($zipManifest[$manifestKey], $richProvenance[$provenanceKey], "{$provenanceKey} rich provenance");
            $t->same($zipManifest[$manifestKey], $richIdentity[$provenanceKey], "{$provenanceKey} rich identity");
        }

        $t->same(count($zipManifest['deepestEntryNames']), $compactInventory['zipPackageManifestDeepestEntryNameCount']);
        $t->same(count($zipManifest['deepestEntryNames']), $compactIdentity['zipPackageManifestDeepestEntryNameCount']);
        $t->same(count($zipManifest['deepestEntryNames']), $richProvenance['zipPackageManifestDeepestEntryNameCount']);
        $t->same(count($zipManifest['deepestEntryNames']), $richIdentity['zipPackageManifestDeepestEntryNameCount']);
        $t->same($zipManifest['manifestSha256'], $compactInventory['zipPackageManifestSha256']);
        $t->same($zipManifest['manifestSha256'], $compactIdentity['zipPackageManifestSha256']);
        $t->same($zipManifest['manifestSha256'], $richProvenance['zipPackageManifestSha256']);
        $t->same($zipManifest['manifestSha256'], $richIdentity['zipPackageManifestSha256']);
        $t->same(2, $richIdentity['zipPackageManifestCreatorHostSystemSummaryCount']);
        $t->same(6, $richIdentity['zipPackageManifestKnownCreatorHostSystemEntryCount']);
        $t->same(0, $richIdentity['zipPackageManifestUnknownCreatorHostSystemEntryCount']);
        $t->same(['below-needed' => 0, 'equals-needed' => 6, 'above-needed' => 0], $richIdentity['zipPackageManifestCreatorVersionComparisonCounts']);
        $t->same('windows-ntfs', $richIdentity['zipPackageManifestCreatorHostSystemSummaries'][1]['madeByHostSystemName']);
        $t->same(['Pictures/review.png'], $richIdentity['zipPackageManifestCreatorHostSystemSummaries'][1]['entryNames']);
        $t->same([], $richIdentity['zipPackageManifestUnknownCreatorHostSystemEntries']);
        $t->same([], $richIdentity['zipPackageManifestCreatorVersionBelowNeededEntries']);
        $t->same(true, $richIdentity['zipPackageManifestHasLocalHeaderReviewFields']);
        $t->same(true, $richIdentity['zipPackageManifestHasCentralDirectoryReviewFields']);
        $t->same(true, $richIdentity['zipPackageManifestHasCentralDirectorySignature']);
        $t->same('central-directory-signature-metadata-only', $richIdentity['zipPackageManifestCentralDirectorySignatureByteExposurePolicy']);
        $t->same(false, $richIdentity['zipPackageManifestCentralDirectorySignatureCanExposeBytes']);
        $t->same(false, array_key_exists('centralDirectorySignatureData', $richIdentity));
        $t->same(false, array_key_exists('zipPackageManifestCentralDirectorySignatureData', $richIdentity));
        $t->same(false, $richIdentity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
    },
];
