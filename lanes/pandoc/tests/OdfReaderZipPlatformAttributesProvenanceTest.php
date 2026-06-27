<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/executable.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/hidden.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/text-attr.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Platform attribute provenance packet.</text:p>
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
    <style:style style:name="Text_20_body" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Platform Attribute Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/executable.png', 'data' => 'EXECPNG', 'compressionMethod' => 0, 'externalAttributes' => 0x81ed0000],
    ['name' => 'Pictures/hidden.png', 'data' => 'HIDDENPNG', 'compressionMethod' => 0, 'creatorHostSystem' => 10, 'externalAttributes' => 0x00000022],
    ['name' => 'Pictures/text-attr.png', 'data' => 'TEXTPNG', 'compressionMethod' => 0, 'externalAttributes' => 0x81a40000, 'internalAttributes' => 0x0001],
]);

return [
    'surfaces ODT ZIP platform attributes in rich package provenance' => static function (TestRunner $t) use ($buildPackage): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];
        $parts = $provenance['parts'];
        $identityParts = [];
        foreach ($identity['packageEntries'] as $packageEntry) {
            $identityParts[$packageEntry['part']] = $packageEntry;
        }
        $hostSystemsByName = [];
        foreach ($provenance['creatorHostSystems']['hostSystems'] as $hostSystem) {
            $hostSystemsByName[$hostSystem['name']] = $hostSystem;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same($package->platformMetadataPreflight(), $provenance['platformMetadata']);
        $t->same($package->permissionPreflight(), $provenance['permissions']);
        $t->same($package->creatorHostSystemPreflight(), $provenance['creatorHostSystems']);
        $t->same($package->dosAttributePreflight(), $provenance['dosAttributes']);
        $t->same($package->internalAttributePreflight(), $provenance['internalAttributes']);
        $t->same(0, $provenance['platformMetadataEntryCount']);
        $t->same(9, $provenance['knownCreatorHostSystemEntryCount']);
        $t->same(0, $provenance['unknownCreatorHostSystemEntryCount']);
        $t->same(0, $provenance['creatorVersionBelowNeededEntryCount']);
        $t->same(2, $provenance['unixModeEntryCount']);
        $t->same(1, $provenance['executableFileCount']);
        $t->same(0, $provenance['writablePermissionEntryCount']);
        $t->same(1, $provenance['dosAttributeEntryCount']);
        $t->same(1, $provenance['hiddenSystemOrVolumeLabelEntryCount']);
        $t->same(1, $provenance['internalAttributeEntryCount']);
        $t->same(8, $hostSystemsByName['unix']['entryCount']);
        $t->same(1, $hostSystemsByName['windows-ntfs']['entryCount']);
        $t->same(2, $identity['unixModeEntryCount']);
        $t->same(1, $identity['executableFileCount']);
        $t->same(1, $identity['dosAttributeEntryCount']);
        $t->same(1, $identity['internalAttributeEntryCount']);

        $executable = $parts['Pictures/executable.png'];
        $hidden = $parts['Pictures/hidden.png'];
        $textAttribute = $parts['Pictures/text-attr.png'];
        $identityExecutable = $identityParts['Pictures/executable.png'];
        $identityHidden = $identityParts['Pictures/hidden.png'];
        $identityTextAttribute = $identityParts['Pictures/text-attr.png'];

        $t->same('package-bytes-exposable', $executable['byteExposurePolicy']);
        $t->same(true, $executable['canExposeBytes']);
        $t->same(3, $executable['madeByHostSystem']);
        $t->same('unix', $executable['madeByHostSystemName']);
        $t->same(0x81ed0000, $executable['externalAttributes']);
        $t->same('81ed0000', $executable['externalAttributesHex']);
        $t->same(true, $executable['hasExternalAttributes']);
        $t->same(0100755, $executable['unixMode']);
        $t->same('100755', $executable['unixModeOctal']);
        $t->same(0755, $executable['unixPermissions']);
        $t->same('0755', $executable['unixPermissionsOctal']);
        $t->same(true, $executable['hasUnixMode']);
        $t->same('regular-file', $executable['unixFileTypeName']);
        $t->same(true, $executable['isUnixExecutableFile']);
        $t->same(['unix-executable-file'], $executable['platformAttributeIssues']);
        $t->same(true, $executable['hasPlatformAttributeProvenance']);
        $t->same('81ed0000', $identityExecutable['externalAttributesHex']);
        $t->same('100755', $identityExecutable['unixModeOctal']);
        $t->same(true, $identityExecutable['isUnixExecutableFile']);
        $t->same(['unix-executable-file'], $identityExecutable['platformAttributeIssues']);

        $t->same(10, $hidden['madeByHostSystem']);
        $t->same('windows-ntfs', $hidden['madeByHostSystemName']);
        $t->same(0x00000022, $hidden['externalAttributes']);
        $t->same('00000022', $hidden['externalAttributesHex']);
        $t->same(0x22, $hidden['dosAttributes']);
        $t->same(['hidden', 'archive'], $hidden['dosAttributeNames']);
        $t->same(true, $hidden['hasDosHiddenAttribute']);
        $t->same(true, $hidden['hasDosArchiveAttribute']);
        $t->same(false, $hidden['hasUnixMode']);
        $t->same(['dos-hidden-attribute'], $hidden['platformAttributeIssues']);
        $t->same(true, $hidden['hasPlatformAttributeProvenance']);
        $t->same('package-bytes-exposable', $hidden['byteExposurePolicy']);
        $t->same('windows-ntfs', $identityHidden['madeByHostSystemName']);
        $t->same('00000022', $identityHidden['externalAttributesHex']);
        $t->same(['hidden', 'archive'], $identityHidden['dosAttributeNames']);
        $t->same(['dos-hidden-attribute'], $identityHidden['platformAttributeIssues']);

        $t->same(0x0001, $textAttribute['internalFileAttributes']);
        $t->same('0001', $textAttribute['internalFileAttributesHex']);
        $t->same(['apparently-text'], $textAttribute['internalAttributeNames']);
        $t->same(true, $textAttribute['hasTextInternalAttribute']);
        $t->same(false, $textAttribute['hasUnknownInternalAttributeBits']);
        $t->same(0100644, $textAttribute['unixMode']);
        $t->same('100644', $textAttribute['unixModeOctal']);
        $t->same('0644', $textAttribute['unixPermissionsOctal']);
        $t->same(false, $textAttribute['isUnixExecutableFile']);
        $t->same(['internal-text-attribute'], $textAttribute['platformAttributeIssues']);
        $t->same(true, $textAttribute['hasPlatformAttributeProvenance']);
        $t->same('package-bytes-exposable', $textAttribute['byteExposurePolicy']);
        $t->same(false, $provenance['packageIdentity']['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $provenance['packageIdentity']['byteExposurePolicy']);
        $t->same('0001', $identityTextAttribute['internalFileAttributesHex']);
        $t->same(['apparently-text'], $identityTextAttribute['internalAttributeNames']);
        $t->same(['internal-text-attribute'], $identityTextAttribute['platformAttributeIssues']);
    },
];
