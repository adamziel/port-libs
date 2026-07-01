<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source.png" manifest:media-type="image/png" manifest:size="9"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Source record provenance packet.</text:p>
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
    <dc:title>Source Record Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8, 'comment' => 'content source review'],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/source.png', 'data' => 'SOURCEPNG', 'compressionMethod' => 0],
];

$buildPackage = static fn (?array $packageParts = null): ZipPackage => ZipPackage::fromParts(
    $packageParts ?? $parts,
    'odt source record provenance'
);

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$sourceRecordSubset = static function (array $item): array {
    $keys = [
        'zipPackageManifestDirectoryRoot',
        'zipPackageManifestPathSegments',
        'zipPackageManifestPathSegmentCount',
        'zipPackageManifestDirectoryDepth',
        'zipPackageManifestCentralDirectoryIndex',
        'zipPackageManifestLocalHeaderOrder',
        'zipLocalHeaderOffset',
        'zipLocalHeaderBytes',
        'zipLocalHeaderSha256',
        'zipLocalHeaderFixedHeaderBytes',
        'zipLocalHeaderVariableFieldOffset',
        'zipLocalHeaderVariableFieldBytes',
        'zipLocalHeaderVariableFieldSha256',
        'zipLocalHeaderRawNameOffset',
        'zipLocalHeaderRawNameBytes',
        'zipLocalHeaderRawNameSha256',
        'zipLocalHeaderExtraFieldOffset',
        'zipLocalHeaderExtraFieldBytes',
        'zipLocalHeaderExtraFieldSha256',
        'zipLocalHeaderReviewFieldBytes',
        'zipLocalRecordOffset',
        'zipLocalRecordBytes',
        'zipLocalRecordEnd',
        'zipLocalRecordSha256',
        'zipCompressedDataOffset',
        'zipCompressedDataBytes',
        'zipCompressedDataEnd',
        'zipCompressedDataSha256',
        'zipUsesDataDescriptor',
        'zipDataDescriptorOffset',
        'zipDataDescriptorBytes',
        'zipDataDescriptorEnd',
        'zipDataDescriptorSha256',
        'zipCentralDirectoryRecordOffset',
        'zipCentralDirectoryRecordBytes',
        'zipCentralDirectoryRecordEnd',
        'zipCentralDirectoryRecordSha256',
        'zipCentralDirectoryFixedHeaderBytes',
        'zipCentralDirectoryVariableFieldOffset',
        'zipCentralDirectoryVariableFieldBytes',
        'zipCentralDirectoryVariableFieldSha256',
        'zipCentralDirectoryRawNameOffset',
        'zipCentralDirectoryRawNameBytes',
        'zipCentralDirectoryRawNameSha256',
        'zipCentralDirectoryExtraFieldOffset',
        'zipCentralDirectoryExtraFieldBytes',
        'zipCentralDirectoryExtraFieldSha256',
        'zipCentralDirectoryRawCommentOffset',
        'zipCentralDirectoryRawCommentBytes',
        'zipCentralDirectoryRawCommentSha256',
        'zipCentralDirectoryReviewFieldBytes',
        'zipSourceRecordBytes',
        'zipHasSourceRecordProvenance',
    ];

    $subset = [];
    foreach ($keys as $key) {
        $subset[$key] = array_key_exists($key, $item) ? $item[$key] : null;
    }

    return $subset;
};

$expectedSourceRecord = static function (array $zipEntry): array {
    return [
        'zipPackageManifestDirectoryRoot' => $zipEntry['directoryRoot'],
        'zipPackageManifestPathSegments' => $zipEntry['pathSegments'],
        'zipPackageManifestPathSegmentCount' => $zipEntry['pathSegmentCount'],
        'zipPackageManifestDirectoryDepth' => $zipEntry['directoryDepth'],
        'zipPackageManifestCentralDirectoryIndex' => $zipEntry['centralDirectoryIndex'],
        'zipPackageManifestLocalHeaderOrder' => $zipEntry['localHeaderOrder'],
        'zipLocalHeaderOffset' => $zipEntry['localHeaderOffset'],
        'zipLocalHeaderBytes' => $zipEntry['localHeaderLength'],
        'zipLocalHeaderSha256' => $zipEntry['localHeaderSha256'],
        'zipLocalHeaderFixedHeaderBytes' => $zipEntry['localHeaderFixedHeaderBytes'],
        'zipLocalHeaderVariableFieldOffset' => $zipEntry['localHeaderVariableFieldOffset'],
        'zipLocalHeaderVariableFieldBytes' => $zipEntry['localHeaderVariableFieldBytes'],
        'zipLocalHeaderVariableFieldSha256' => $zipEntry['localHeaderVariableFieldSha256'],
        'zipLocalHeaderRawNameOffset' => $zipEntry['localHeaderRawNameOffset'],
        'zipLocalHeaderRawNameBytes' => $zipEntry['localHeaderRawNameBytes'],
        'zipLocalHeaderRawNameSha256' => $zipEntry['localHeaderRawNameSha256'],
        'zipLocalHeaderExtraFieldOffset' => $zipEntry['localHeaderExtraFieldOffset'],
        'zipLocalHeaderExtraFieldBytes' => $zipEntry['localHeaderExtraFieldBytes'],
        'zipLocalHeaderExtraFieldSha256' => $zipEntry['localHeaderExtraFieldSha256'],
        'zipLocalHeaderReviewFieldBytes' => $zipEntry['localHeaderReviewFieldBytes'],
        'zipLocalRecordOffset' => $zipEntry['localRecordOffset'],
        'zipLocalRecordBytes' => $zipEntry['localRecordBytes'],
        'zipLocalRecordEnd' => $zipEntry['localRecordEnd'],
        'zipLocalRecordSha256' => $zipEntry['localRecordSha256'],
        'zipCompressedDataOffset' => $zipEntry['compressedDataOffset'],
        'zipCompressedDataBytes' => $zipEntry['compressedSize'],
        'zipCompressedDataEnd' => $zipEntry['compressedDataEnd'],
        'zipCompressedDataSha256' => $zipEntry['compressedDataSha256'],
        'zipUsesDataDescriptor' => $zipEntry['usesDataDescriptor'],
        'zipDataDescriptorOffset' => $zipEntry['dataDescriptorOffset'],
        'zipDataDescriptorBytes' => $zipEntry['dataDescriptorBytes'],
        'zipDataDescriptorEnd' => $zipEntry['dataDescriptorEnd'],
        'zipDataDescriptorSha256' => $zipEntry['dataDescriptorSha256'],
        'zipCentralDirectoryRecordOffset' => $zipEntry['centralDirectoryRecordOffset'],
        'zipCentralDirectoryRecordBytes' => $zipEntry['centralDirectoryRecordBytes'],
        'zipCentralDirectoryRecordEnd' => $zipEntry['centralDirectoryRecordEnd'],
        'zipCentralDirectoryRecordSha256' => $zipEntry['centralDirectoryRecordSha256'],
        'zipCentralDirectoryFixedHeaderBytes' => $zipEntry['centralDirectoryFixedHeaderBytes'],
        'zipCentralDirectoryVariableFieldOffset' => $zipEntry['centralDirectoryVariableFieldOffset'],
        'zipCentralDirectoryVariableFieldBytes' => $zipEntry['centralDirectoryVariableFieldBytes'],
        'zipCentralDirectoryVariableFieldSha256' => $zipEntry['centralDirectoryVariableFieldSha256'],
        'zipCentralDirectoryRawNameOffset' => $zipEntry['centralDirectoryRawNameOffset'],
        'zipCentralDirectoryRawNameBytes' => $zipEntry['centralDirectoryRawNameBytes'],
        'zipCentralDirectoryRawNameSha256' => $zipEntry['centralDirectoryRawNameSha256'],
        'zipCentralDirectoryExtraFieldOffset' => $zipEntry['centralDirectoryExtraFieldOffset'],
        'zipCentralDirectoryExtraFieldBytes' => $zipEntry['centralDirectoryExtraFieldBytes'],
        'zipCentralDirectoryExtraFieldSha256' => $zipEntry['centralDirectoryExtraFieldSha256'],
        'zipCentralDirectoryRawCommentOffset' => $zipEntry['centralDirectoryRawCommentOffset'],
        'zipCentralDirectoryRawCommentBytes' => $zipEntry['centralDirectoryRawCommentBytes'],
        'zipCentralDirectoryRawCommentSha256' => $zipEntry['centralDirectoryRawCommentSha256'],
        'zipCentralDirectoryReviewFieldBytes' => $zipEntry['centralDirectoryReviewFieldBytes'],
        'zipSourceRecordBytes' => $zipEntry['localRecordBytes'] + $zipEntry['centralDirectoryRecordBytes'],
        'zipHasSourceRecordProvenance' => true,
    ];
};

return [
    'carries ODT ZIP source record provenance through compact and rich package review' => static function (TestRunner $t) use ($buildPackage, $indexBy, $sourceRecordSubset, $expectedSourceRecord, $parts): void {
        $package = $buildPackage();
        $zipBytes = $package->bytes();
        $zipManifestByName = $indexBy($package->packageManifestPreflight()['entries'], 'name');
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');
        $compactIdentityParts = $indexBy($compactSummary['packageIdentity']['packageEntries'], 'path');

        $contentZipEntry = $zipManifestByName['content.xml'];
        $mediaZipEntry = $zipManifestByName['Pictures/source.png'];
        $expectedContent = $expectedSourceRecord($contentZipEntry);
        $expectedMedia = $expectedSourceRecord($mediaZipEntry);
        $compactContent = $compactInventory['parts']['content.xml'];
        $richContent = $richProvenance['parts']['content.xml'];
        $richMedia = $richProvenance['parts']['Pictures/source.png'];
        $changedParts = $parts;
        $changedParts[2]['comment'] = 'content source review changed';
        $changedIdentity = (new OdfReader())
            ->readPackage($buildPackage($changedParts))['importReport']['manifest']['packageProvenance']['packageIdentity'];

        $t->same($expectedContent, $sourceRecordSubset($compactContent));
        $t->same($expectedContent, $sourceRecordSubset($richContent));
        $t->same($expectedContent, $sourceRecordSubset($richIdentityParts['content.xml']));
        $t->same($expectedContent, $sourceRecordSubset($compactIdentityParts['content.xml']));
        $t->same($expectedMedia, $sourceRecordSubset($richMedia));
        $t->same($compactInventory['parts']['Pictures/source.png']['zipLocalRecordSha256'], $richMedia['zipLocalRecordSha256']);

        $t->same('content source review', $richContent['zipEntryComment']);
        $t->same(strlen('content source review'), $richContent['zipCentralDirectoryRawCommentBytes']);
        $t->same(strlen('content source review'), $richContent['zipCentralDirectoryReviewFieldBytes']);
        $t->same(false, $richContent['zipUsesDataDescriptor']);
        $t->same(0, $richContent['zipDataDescriptorBytes']);
        $t->same(null, $richContent['zipDataDescriptorSha256']);
        $t->same($richContent['zipCompressedDataEnd'], $richContent['zipLocalRecordEnd']);
        $t->same($richContent['zipLocalRecordBytes'] + $richContent['zipCentralDirectoryRecordBytes'], $richContent['zipSourceRecordBytes']);
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipLocalRecordOffset'], $richContent['zipLocalRecordBytes'])),
            $richContent['zipLocalRecordSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipCompressedDataOffset'], $richContent['zipCompressedDataBytes'])),
            $richContent['zipCompressedDataSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipLocalHeaderRawNameOffset'], $richContent['zipLocalHeaderRawNameBytes'])),
            $richContent['zipLocalHeaderRawNameSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipLocalHeaderExtraFieldOffset'], $richContent['zipLocalHeaderExtraFieldBytes'])),
            $richContent['zipLocalHeaderExtraFieldSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipCentralDirectoryRecordOffset'], $richContent['zipCentralDirectoryRecordBytes'])),
            $richContent['zipCentralDirectoryRecordSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipCentralDirectoryVariableFieldOffset'], $richContent['zipCentralDirectoryVariableFieldBytes'])),
            $richContent['zipCentralDirectoryVariableFieldSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipCentralDirectoryRawNameOffset'], $richContent['zipCentralDirectoryRawNameBytes'])),
            $richContent['zipCentralDirectoryRawNameSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipCentralDirectoryExtraFieldOffset'], $richContent['zipCentralDirectoryExtraFieldBytes'])),
            $richContent['zipCentralDirectoryExtraFieldSha256']
        );
        $t->same(
            hash('sha256', substr($zipBytes, $richContent['zipCentralDirectoryRawCommentOffset'], $richContent['zipCentralDirectoryRawCommentBytes'])),
            $richContent['zipCentralDirectoryRawCommentSha256']
        );
        $t->same(true, $richIdentityParts['content.xml']['zipHasSourceRecordProvenance']);
        $t->true($richIdentity['identitySha256'] !== $changedIdentity['identitySha256']);
        $t->same(false, $richIdentity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
    },
];
