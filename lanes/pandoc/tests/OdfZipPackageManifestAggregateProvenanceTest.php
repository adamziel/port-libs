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

$duplicateManifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Objects/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/Review.PNG" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$duplicatePackage = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $duplicateManifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Objects/content.xml', 'data' => '<object/>', 'compressionMethod' => 0],
    ['name' => 'Pictures/review.png', 'data' => 'review', 'compressionMethod' => 0],
    ['name' => 'Pictures/Review.PNG', 'data' => 'review-upper', 'compressionMethod' => 0],
], 'odt zip manifest basename aggregate provenance');

$aggregateFields = [
    'manifestVersion' => 'zipPackageManifestVersion',
    'packageSource' => 'zipPackageManifestPackageSource',
    'archiveBytes' => 'zipPackageManifestArchiveBytes',
    'archiveLength' => 'zipPackageManifestArchiveLength',
    'archiveSha256' => 'zipPackageManifestArchiveSha256',
    'entryCount' => 'zipPackageManifestEntryCount',
    'fileEntryCount' => 'zipPackageManifestFileEntryCount',
    'directoryEntryCount' => 'zipPackageManifestDirectoryEntryCount',
    'compressedBytes' => 'zipPackageManifestCompressedBytes',
    'uncompressedBytes' => 'zipPackageManifestUncompressedBytes',
    'expansionRatio' => 'zipPackageManifestExpansionRatio',
    'largestEntry' => 'zipPackageManifestLargestEntry',
    'zeroByteEntryCount' => 'zipPackageManifestZeroByteEntryCount',
    'zeroByteFileCount' => 'zipPackageManifestZeroByteFileCount',
    'emptyDirectoryEntryCount' => 'zipPackageManifestEmptyDirectoryEntryCount',
    'hasZeroByteEntries' => 'zipPackageManifestHasZeroByteEntries',
    'zeroByteEntries' => 'zipPackageManifestZeroByteEntries',
    'unknownExpansionRatioEntryCount' => 'zipPackageManifestUnknownExpansionRatioEntryCount',
    'hasUnknownExpansionRatioEntries' => 'zipPackageManifestHasUnknownExpansionRatioEntries',
    'unknownExpansionRatioEntries' => 'zipPackageManifestUnknownExpansionRatioEntries',
    'expansionRatioBucketSummaryCount' => 'zipPackageManifestExpansionRatioBucketSummaryCount',
    'expansionRatioBuckets' => 'zipPackageManifestExpansionRatioBuckets',
    'expansionRatioBucketSummaries' => 'zipPackageManifestExpansionRatioBucketSummaries',
    'sourceRecordByteLengthBucketSummaryCount' => 'zipPackageManifestSourceRecordByteLengthBucketSummaryCount',
    'sourceRecordByteLengthBuckets' => 'zipPackageManifestSourceRecordByteLengthBuckets',
    'sourceRecordByteLengthBucketSummaries' => 'zipPackageManifestSourceRecordByteLengthBucketSummaries',
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
    'centralDirectoryOffset' => 'zipPackageManifestCentralDirectoryOffset',
    'centralDirectoryBytes' => 'zipPackageManifestCentralDirectoryBytes',
    'centralDirectoryEnd' => 'zipPackageManifestCentralDirectoryEnd',
    'centralDirectorySha256' => 'zipPackageManifestCentralDirectorySha256',
    'centralDirectoryToEocdGapOffset' => 'zipPackageManifestCentralDirectoryToEocdGapOffset',
    'centralDirectoryToEocdGapBytes' => 'zipPackageManifestCentralDirectoryToEocdGapBytes',
    'centralDirectoryToEocdGapSha256' => 'zipPackageManifestCentralDirectoryToEocdGapSha256',
    'endOfCentralDirectoryOffset' => 'zipPackageManifestEndOfCentralDirectoryOffset',
    'endOfCentralDirectoryBytes' => 'zipPackageManifestEndOfCentralDirectoryBytes',
    'endOfCentralDirectoryEnd' => 'zipPackageManifestEndOfCentralDirectoryEnd',
    'endOfCentralDirectorySha256' => 'zipPackageManifestEndOfCentralDirectorySha256',
    'centralDirectoryRecordBytes' => 'zipPackageManifestCentralDirectoryRecordBytes',
    'centralDirectoryFixedHeaderBytes' => 'zipPackageManifestCentralDirectoryFixedHeaderBytes',
    'centralDirectoryVariableFieldBytes' => 'zipPackageManifestCentralDirectoryVariableFieldBytes',
    'centralDirectoryRawNameBytes' => 'zipPackageManifestCentralDirectoryRawNameBytes',
    'centralDirectoryExtraFieldBytes' => 'zipPackageManifestCentralDirectoryExtraFieldBytes',
    'centralDirectoryRawCommentBytes' => 'zipPackageManifestCentralDirectoryRawCommentBytes',
    'centralDirectoryReviewFieldBytes' => 'zipPackageManifestCentralDirectoryReviewFieldBytes',
    'sourceRecordBytes' => 'zipPackageManifestSourceRecordBytes',
    'centralExtraFieldEntryCount' => 'zipPackageManifestCentralExtraFieldEntryCount',
    'entryCommentCount' => 'zipPackageManifestEntryCommentCount',
    'hasPackageComment' => 'zipPackageManifestHasPackageComment',
    'packageCommentOffset' => 'zipPackageManifestPackageCommentOffset',
    'packageCommentBytes' => 'zipPackageManifestPackageCommentBytes',
    'packageCommentSha256' => 'zipPackageManifestPackageCommentSha256',
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
    'pathSegmentPositionSummaryCount' => 'zipPackageManifestPathSegmentPositionSummaryCount',
    'pathSegmentPositionOccurrenceCount' => 'zipPackageManifestPathSegmentPositionOccurrenceCount',
    'pathSegmentPositionCounts' => 'zipPackageManifestPathSegmentPositionCounts',
    'pathSegmentPositionEntryCounts' => 'zipPackageManifestPathSegmentPositionEntryCounts',
    'pathSegmentPositionSummaries' => 'zipPackageManifestPathSegmentPositionSummaries',
    'caseFoldPathSegmentSummaryCount' => 'zipPackageManifestCaseFoldPathSegmentSummaryCount',
    'caseFoldPathSegments' => 'zipPackageManifestCaseFoldPathSegments',
    'caseFoldPathSegmentOccurrenceCount' => 'zipPackageManifestCaseFoldPathSegmentOccurrenceCount',
    'caseFoldPathSegmentCounts' => 'zipPackageManifestCaseFoldPathSegmentCounts',
    'caseFoldPathSegmentEntryCounts' => 'zipPackageManifestCaseFoldPathSegmentEntryCounts',
    'caseFoldPathSegmentSummaries' => 'zipPackageManifestCaseFoldPathSegmentSummaries',
    'compressionMethodSummaryCount' => 'zipPackageManifestCompressionMethodSummaryCount',
    'compressionMethodSummaries' => 'zipPackageManifestCompressionMethodSummaries',
    'generalPurposeFlagSummaryCount' => 'zipPackageManifestGeneralPurposeFlagSummaryCount',
    'generalPurposeUtf8NameEntryCount' => 'zipPackageManifestGeneralPurposeUtf8NameEntryCount',
    'generalPurposeDataDescriptorEntryCount' => 'zipPackageManifestGeneralPurposeDataDescriptorEntryCount',
    'generalPurposeDeflateOptionEntryCount' => 'zipPackageManifestGeneralPurposeDeflateOptionEntryCount',
    'generalPurposeFlagSummaries' => 'zipPackageManifestGeneralPurposeFlagSummaries',
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
    'packagePartBaseNameSummaryCount' => 'zipPackageManifestPackagePartBaseNameSummaryCount',
    'packagePartBaseNames' => 'zipPackageManifestPackagePartBaseNames',
    'packagePartBaseNameSummaries' => 'zipPackageManifestPackagePartBaseNameSummaries',
    'duplicatePackagePartBaseNameCount' => 'zipPackageManifestDuplicatePackagePartBaseNameCount',
    'hasDuplicatePackagePartBaseNames' => 'zipPackageManifestHasDuplicatePackagePartBaseNames',
    'duplicatePackagePartBaseNames' => 'zipPackageManifestDuplicatePackagePartBaseNames',
    'duplicatePackagePartBaseNameSummaries' => 'zipPackageManifestDuplicatePackagePartBaseNameSummaries',
    'packagePartCaseFoldBaseNameSummaryCount' => 'zipPackageManifestPackagePartCaseFoldBaseNameSummaryCount',
    'packagePartCaseFoldBaseNames' => 'zipPackageManifestPackagePartCaseFoldBaseNames',
    'packagePartCaseFoldBaseNameSummaries' => 'zipPackageManifestPackagePartCaseFoldBaseNameSummaries',
    'duplicatePackagePartCaseFoldBaseNameCount' => 'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameCount',
    'hasDuplicatePackagePartCaseFoldBaseNames' => 'zipPackageManifestHasDuplicatePackagePartCaseFoldBaseNames',
    'duplicatePackagePartCaseFoldBaseNames' => 'zipPackageManifestDuplicatePackagePartCaseFoldBaseNames',
    'duplicatePackagePartCaseFoldBaseNameSummaries' => 'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameSummaries',
    'packagePartBaseNameStemSummaryCount' => 'zipPackageManifestPackagePartBaseNameStemSummaryCount',
    'packagePartBaseNameStems' => 'zipPackageManifestPackagePartBaseNameStems',
    'packagePartBaseNameStemSummaries' => 'zipPackageManifestPackagePartBaseNameStemSummaries',
    'duplicatePackagePartBaseNameStemCount' => 'zipPackageManifestDuplicatePackagePartBaseNameStemCount',
    'hasDuplicatePackagePartBaseNameStems' => 'zipPackageManifestHasDuplicatePackagePartBaseNameStems',
    'duplicatePackagePartBaseNameStems' => 'zipPackageManifestDuplicatePackagePartBaseNameStems',
    'duplicatePackagePartBaseNameStemSummaries' => 'zipPackageManifestDuplicatePackagePartBaseNameStemSummaries',
    'packagePartCaseFoldBaseNameStemSummaryCount' => 'zipPackageManifestPackagePartCaseFoldBaseNameStemSummaryCount',
    'packagePartCaseFoldBaseNameStems' => 'zipPackageManifestPackagePartCaseFoldBaseNameStems',
    'packagePartCaseFoldBaseNameStemSummaries' => 'zipPackageManifestPackagePartCaseFoldBaseNameStemSummaries',
    'duplicatePackagePartCaseFoldBaseNameStemCount' => 'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStemCount',
    'hasDuplicatePackagePartCaseFoldBaseNameStems' => 'zipPackageManifestHasDuplicatePackagePartCaseFoldBaseNameStems',
    'duplicatePackagePartCaseFoldBaseNameStems' => 'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStems',
    'duplicatePackagePartCaseFoldBaseNameStemSummaries' => 'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStemSummaries',
    'centralDirectoryOrderNames' => 'zipPackageManifestCentralDirectoryOrderNames',
    'localHeaderOrderNames' => 'zipPackageManifestLocalHeaderOrderNames',
    'centralDirectoryOrderMatchesLocalHeaderOrder' => 'zipPackageManifestCentralDirectoryOrderMatchesLocalHeaderOrder',
];

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$packageManifestEntryScalarSubset = static function (array $item): array {
    $keys = [
        'zipPackageManifestCompressionMethodName',
        'zipPackageManifestCrc32Hex',
        'zipPackageManifestCompressedSize',
        'zipPackageManifestUncompressedSize',
        'zipPackageManifestExpansionRatio',
        'zipPackageManifestVersionMadeBy',
        'zipPackageManifestMadeByHostSystem',
        'zipPackageManifestMadeByHostSystemName',
        'zipPackageManifestMadeByVersion',
        'zipPackageManifestVersionNeededToExtract',
        'zipPackageManifestCreatorVersionMeetsNeeded',
        'zipPackageManifestCreatorVersionComparison',
        'zipPackageManifestCreatorVersionDelta',
        'zipPackageManifestCreatorHostSystemIsKnown',
        'zipPackageManifestCreatorHostSystemIssues',
        'zipPackageManifestPathSegmentPositionReviews',
        'zipPackageManifestPackagePartBaseName',
        'zipPackageManifestPackagePartCaseFoldBaseName',
        'zipPackageManifestPackagePartBaseNameStem',
        'zipPackageManifestPackagePartCaseFoldBaseNameStem',
        'zipPackageManifestPackagePartExtension',
        'zipPackageManifestPackagePartExtensionKey',
        'zipPackageManifestExtensionlessPackagePart',
    ];
    $defaults = [
        'zipPackageManifestCreatorHostSystemIssues' => [],
    ];

    $subset = [];
    foreach ($keys as $key) {
        $subset[$key] = array_key_exists($key, $item) ? $item[$key] : ($defaults[$key] ?? null);
    }

    return $subset;
};

return [
    'carries ODT ZIP package manifest aggregate byte-layout provenance through compact and rich identities' => static function (TestRunner $t) use (
        $package,
        $aggregateFields,
        $indexBy,
        $packageManifestEntryScalarSubset
    ): void {
        $zipManifest = $package->packageManifestPreflight();
        $zipManifestEntries = $indexBy($zipManifest['entries'], 'name');
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $compactIdentityEntries = $indexBy($compactIdentity['packageEntries'], 'path');
        $richIdentityEntries = $indexBy($richIdentity['packageEntries'], 'part');

        foreach ($aggregateFields as $manifestKey => $provenanceKey) {
            $t->same($zipManifest[$manifestKey], $compactInventory[$provenanceKey], "{$provenanceKey} compact inventory");
            $t->same($zipManifest[$manifestKey], $compactIdentity[$provenanceKey], "{$provenanceKey} compact identity");
            $t->same($zipManifest[$manifestKey], $richProvenance[$provenanceKey], "{$provenanceKey} rich provenance");
            $t->same($zipManifest[$manifestKey], $richIdentity[$provenanceKey], "{$provenanceKey} rich identity");
        }

        $t->same(['up-to-1x', '1x-to-10x'], $richIdentity['zipPackageManifestExpansionRatioBuckets']);
        $t->same(2, $richIdentity['zipPackageManifestExpansionRatioBucketSummaryCount']);
        $bucketSummaries = $indexBy($richIdentity['zipPackageManifestExpansionRatioBucketSummaries'], 'expansionRatioBucket');
        $t->same(['mimetype', 'META-INF/manifest.xml', 'meta.xml', 'Pictures/review.png'], $bucketSummaries['up-to-1x']['entryNames']);
        $t->same(['content.xml', 'styles.xml'], $bucketSummaries['1x-to-10x']['entryNames']);
        $t->same(['/', 'META-INF/', 'Pictures/'], $bucketSummaries['up-to-1x']['directoryRoots']);
        $t->same(['stored'], $bucketSummaries['up-to-1x']['compressionMethodNames']);
        $t->same(['deflated'], $bucketSummaries['1x-to-10x']['compressionMethodNames']);
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
        foreach (['content.xml', 'Pictures/review.png'] as $name) {
            $zipEntry = $zipManifestEntries[$name];
            $expectedEntryScalarProvenance = [
                'zipPackageManifestCompressionMethodName' => $zipEntry['compressionMethodName'],
                'zipPackageManifestCrc32Hex' => $zipEntry['crc32Hex'],
                'zipPackageManifestCompressedSize' => $zipEntry['compressedSize'],
                'zipPackageManifestUncompressedSize' => $zipEntry['uncompressedSize'],
                'zipPackageManifestExpansionRatio' => $zipEntry['expansionRatio'],
                'zipPackageManifestVersionMadeBy' => $zipEntry['versionMadeBy'],
                'zipPackageManifestMadeByHostSystem' => $zipEntry['madeByHostSystem'],
                'zipPackageManifestMadeByHostSystemName' => $zipEntry['madeByHostSystemName'],
                'zipPackageManifestMadeByVersion' => $zipEntry['madeByVersion'],
                'zipPackageManifestVersionNeededToExtract' => $zipEntry['versionNeededToExtract'],
                'zipPackageManifestCreatorVersionMeetsNeeded' => $zipEntry['creatorVersionMeetsNeeded'],
                'zipPackageManifestCreatorVersionComparison' => $zipEntry['creatorVersionComparison'],
                'zipPackageManifestCreatorVersionDelta' => $zipEntry['creatorVersionDelta'],
                'zipPackageManifestCreatorHostSystemIsKnown' => $zipEntry['creatorHostSystemIsKnown'],
                'zipPackageManifestCreatorHostSystemIssues' => $zipEntry['creatorHostSystemIssues'],
                'zipPackageManifestPathSegmentPositionReviews' => $zipEntry['pathSegmentPositionReviews'],
                'zipPackageManifestPackagePartBaseName' => $zipEntry['packagePartBaseName'],
                'zipPackageManifestPackagePartCaseFoldBaseName' => $zipEntry['packagePartCaseFoldBaseName'],
                'zipPackageManifestPackagePartBaseNameStem' => $zipEntry['packagePartBaseNameStem'],
                'zipPackageManifestPackagePartCaseFoldBaseNameStem' => $zipEntry['packagePartCaseFoldBaseNameStem'],
                'zipPackageManifestPackagePartExtension' => $zipEntry['packagePartExtension'],
                'zipPackageManifestPackagePartExtensionKey' => $zipEntry['packagePartExtensionKey'],
                'zipPackageManifestExtensionlessPackagePart' => $zipEntry['extensionlessPackagePart'],
            ];

            $t->same($expectedEntryScalarProvenance, $packageManifestEntryScalarSubset($compactInventory['parts'][$name]), "{$name} compact inventory package-manifest scalar provenance");
            $t->same($expectedEntryScalarProvenance, $packageManifestEntryScalarSubset($compactIdentityEntries[$name]), "{$name} compact identity package-manifest scalar provenance");
            $t->same($expectedEntryScalarProvenance, $packageManifestEntryScalarSubset($richProvenance['parts'][$name]), "{$name} rich provenance package-manifest scalar provenance");
            $t->same($expectedEntryScalarProvenance, $packageManifestEntryScalarSubset($richIdentityEntries[$name]), "{$name} rich identity package-manifest scalar provenance");
        }
        $t->same(3, $richIdentity['zipPackageManifestPathSegmentPositionSummaryCount']);
        $t->same(8, $richIdentity['zipPackageManifestPathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 2, 'last' => 2, 'only' => 4], $richIdentity['zipPackageManifestPathSegmentPositionCounts']);
        $t->same(['first' => 2, 'last' => 2, 'only' => 4], $richIdentity['zipPackageManifestPathSegmentPositionEntryCounts']);
        $positionSummaries = $indexBy($richIdentity['zipPackageManifestPathSegmentPositionSummaries'], 'position');
        $t->same(['META-INF', 'Pictures'], $positionSummaries['first']['segments']);
        $t->same(['manifest.xml', 'review.png'], $positionSummaries['last']['segments']);
        $t->same(['Pictures', 'review.png'], $richIdentityEntries['Pictures/review.png']['zipPackageManifestPathSegments']);
        $t->same([
            [
                'pathSegmentIndex' => 0,
                'segment' => 'Pictures',
                'position' => 'first',
                'isFirst' => true,
                'isLast' => false,
                'isOnly' => false,
            ],
            [
                'pathSegmentIndex' => 1,
                'segment' => 'review.png',
                'position' => 'last',
                'isFirst' => false,
                'isLast' => true,
                'isOnly' => false,
            ],
        ], $richIdentityEntries['Pictures/review.png']['zipPackageManifestPathSegmentPositionReviews']);
        $t->same(false, array_key_exists('centralDirectorySignatureData', $richIdentity));
        $t->same(false, array_key_exists('zipPackageManifestCentralDirectorySignatureData', $richIdentity));
        $t->same(false, $richIdentity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
    },
    'carries ODT ZIP package manifest basename rollups through compact and rich identities' => static function (TestRunner $t) use (
        $duplicatePackage,
        $aggregateFields,
        $indexBy
    ): void {
        $zipManifest = $duplicatePackage->packageManifestPreflight();
        $zipManifestEntries = $indexBy($zipManifest['entries'], 'name');
        $compactSummary = OpenDocumentPackage::fromPackage($duplicatePackage)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($duplicatePackage);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $compactIdentityEntries = $indexBy($compactIdentity['packageEntries'], 'path');
        $richIdentityEntries = $indexBy($richIdentity['packageEntries'], 'part');
        $baseNameAggregateKeys = [
            'packagePartBaseNameSummaryCount',
            'packagePartBaseNames',
            'packagePartBaseNameSummaries',
            'duplicatePackagePartBaseNameCount',
            'hasDuplicatePackagePartBaseNames',
            'duplicatePackagePartBaseNames',
            'duplicatePackagePartBaseNameSummaries',
            'packagePartCaseFoldBaseNameSummaryCount',
            'packagePartCaseFoldBaseNames',
            'packagePartCaseFoldBaseNameSummaries',
            'duplicatePackagePartCaseFoldBaseNameCount',
            'hasDuplicatePackagePartCaseFoldBaseNames',
            'duplicatePackagePartCaseFoldBaseNames',
            'duplicatePackagePartCaseFoldBaseNameSummaries',
            'packagePartBaseNameStemSummaryCount',
            'packagePartBaseNameStems',
            'packagePartBaseNameStemSummaries',
            'duplicatePackagePartBaseNameStemCount',
            'hasDuplicatePackagePartBaseNameStems',
            'duplicatePackagePartBaseNameStems',
            'duplicatePackagePartBaseNameStemSummaries',
            'packagePartCaseFoldBaseNameStemSummaryCount',
            'packagePartCaseFoldBaseNameStems',
            'packagePartCaseFoldBaseNameStemSummaries',
            'duplicatePackagePartCaseFoldBaseNameStemCount',
            'hasDuplicatePackagePartCaseFoldBaseNameStems',
            'duplicatePackagePartCaseFoldBaseNameStems',
            'duplicatePackagePartCaseFoldBaseNameStemSummaries',
        ];
        $surfaces = [
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
        ];

        foreach ($surfaces as $label => $surface) {
            foreach ($baseNameAggregateKeys as $manifestKey) {
                $provenanceKey = $aggregateFields[$manifestKey];
                $t->same($zipManifest[$manifestKey], $surface[$provenanceKey], "{$label} {$provenanceKey}");
            }

            $t->same(1, $surface['zipPackageManifestDuplicatePackagePartBaseNameCount'], "{$label} duplicate exact basename count");
            $t->same(true, $surface['zipPackageManifestHasDuplicatePackagePartBaseNames'], "{$label} duplicate exact basename flag");
            $t->same(['content.xml'], $surface['zipPackageManifestDuplicatePackagePartBaseNames'], "{$label} duplicate exact basenames");
            $t->same(2, $surface['zipPackageManifestDuplicatePackagePartCaseFoldBaseNameCount'], "{$label} duplicate case-fold basename count");
            $t->same(true, $surface['zipPackageManifestHasDuplicatePackagePartCaseFoldBaseNames'], "{$label} duplicate case-fold basename flag");
            $t->same(['content.xml', 'review.png'], $surface['zipPackageManifestDuplicatePackagePartCaseFoldBaseNames'], "{$label} duplicate case-fold basenames");
            $t->same(1, $surface['zipPackageManifestDuplicatePackagePartBaseNameStemCount'], "{$label} duplicate exact basename stem count");
            $t->same(true, $surface['zipPackageManifestHasDuplicatePackagePartBaseNameStems'], "{$label} duplicate exact basename stem flag");
            $t->same(['content'], $surface['zipPackageManifestDuplicatePackagePartBaseNameStems'], "{$label} duplicate exact basename stems");
            $t->same(2, $surface['zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStemCount'], "{$label} duplicate case-fold basename stem count");
            $t->same(true, $surface['zipPackageManifestHasDuplicatePackagePartCaseFoldBaseNameStems'], "{$label} duplicate case-fold basename stem flag");
            $t->same(['content', 'review'], $surface['zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStems'], "{$label} duplicate case-fold basename stems");
        }

        $entrySurfaces = [
            'compact inventory' => $compactInventory['parts'],
            'compact identity' => $compactIdentityEntries,
            'rich provenance' => $richProvenance['parts'],
            'rich identity' => $richIdentityEntries,
        ];
        $entryFieldMap = [
            'packagePartBaseName' => 'zipPackageManifestPackagePartBaseName',
            'packagePartCaseFoldBaseName' => 'zipPackageManifestPackagePartCaseFoldBaseName',
            'packagePartBaseNameStem' => 'zipPackageManifestPackagePartBaseNameStem',
            'packagePartCaseFoldBaseNameStem' => 'zipPackageManifestPackagePartCaseFoldBaseNameStem',
        ];

        foreach (['Objects/content.xml', 'Pictures/Review.PNG'] as $name) {
            foreach ($entrySurfaces as $label => $entries) {
                foreach ($entryFieldMap as $manifestKey => $provenanceKey) {
                    $t->same($zipManifestEntries[$name][$manifestKey], $entries[$name][$provenanceKey], "{$label} {$name} {$provenanceKey}");
                }
            }
        }

        $t->same('Review.PNG', $richIdentityEntries['Pictures/Review.PNG']['zipPackageManifestPackagePartBaseName']);
        $t->same('review.png', $richIdentityEntries['Pictures/Review.PNG']['zipPackageManifestPackagePartCaseFoldBaseName']);
        $t->same('Review', $richIdentityEntries['Pictures/Review.PNG']['zipPackageManifestPackagePartBaseNameStem']);
        $t->same('review', $richIdentityEntries['Pictures/Review.PNG']['zipPackageManifestPackagePartCaseFoldBaseNameStem']);
    },
];
