<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Raw ODT package preflight.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$packUInt64 = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('ZIP64 fixture value must be non-negative');
    }

    return pack('VV', $value & 0xffffffff, intdiv($value, 0x100000000));
};

$buildOdtZipBytes = static function () use ($manifestXml, $contentXml): string {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ])->bytes();
};

$addZip64EndOfCentralDirectory = static function (string $zip) use ($packUInt64): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $diskEntryCount = unpack('vvalue', substr($zip, $eocdOffset + 8, 2))['value'];
    $totalEntryCount = unpack('vvalue', substr($zip, $eocdOffset + 10, 2))['value'];
    $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    $zip64EocdOffset = $eocdOffset;
    $zip64Eocd = "PK\x06\x06"
        . $packUInt64(44)
        . pack('vvVV', 45, 45, 0, 0)
        . $packUInt64((int) $diskEntryCount)
        . $packUInt64((int) $totalEntryCount)
        . $packUInt64((int) $centralDirectorySize)
        . $packUInt64((int) $centralDirectoryOffset);
    $zip64Locator = "PK\x06\x07"
        . pack('V', 0)
        . $packUInt64($zip64EocdOffset)
        . pack('V', 1);
    $eocd = substr($zip, $eocdOffset);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 8, 2);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 10, 2);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 12, 4);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 16, 4);

    return substr($zip, 0, $eocdOffset) . $zip64Eocd . $zip64Locator . $eocd;
};

return [
    'preflights instantiable ODT raw packages with first mimetype evidence' => static function (TestRunner $t) use ($buildOdtZipBytes): void {
        $summary = OpenDocumentPackage::rawImportPreflight($buildOdtZipBytes());

        $t->same(true, $summary['isValid']);
        $t->same(true, $summary['isOpenDocumentTextPackage']);
        $t->same(true, $summary['canInstantiateZipPackage']);
        $t->same(true, $summary['canInstantiateOpenDocumentPackage']);
        $t->same(null, $summary['zipPackageInstantiationError']);
        $t->same(null, $summary['openDocumentPackageInstantiationError']);
        $t->same('1.3', $summary['manifestVersion']);
        $t->same(2, $summary['manifestEntryCount']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $summary['mimetypeEntry']['mediaType']);
        $t->same(true, $summary['mimetypeEntry']['matchesOpenDocumentText']);
        $t->same([], $summary['mimetypeEntry']['diagnostics']);
        $t->same([], $summary['diagnostics']);
        $t->same('odf-raw-package-import-metadata-only', $summary['byteExposurePolicy']);
        $t->same(false, $summary['canExposeBytes']);
    },

    'preflights ZIP64 EOCD ODT packages before bounded package instantiation' => static function (TestRunner $t) use ($buildOdtZipBytes, $addZip64EndOfCentralDirectory): void {
        $summary = OpenDocumentPackage::rawImportPreflight(
            $addZip64EndOfCentralDirectory($buildOdtZipBytes())
        );

        $t->same(false, $summary['isValid']);
        $t->same(true, $summary['isOpenDocumentTextPackage']);
        $t->same(false, $summary['canInstantiateZipPackage']);
        $t->same(false, $summary['canInstantiateOpenDocumentPackage']);
        $t->contains('ZIP64 end-of-central-directory records are not supported', (string) $summary['zipPackageInstantiationError']);
        $t->contains('ZIP64 end-of-central-directory records are not supported', (string) $summary['openDocumentPackageInstantiationError']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $summary['mimetypeEntry']['mediaType']);
        $t->same(true, $summary['mimetypeEntry']['matchesOpenDocumentText']);
        $t->same([], $summary['mimetypeEntry']['diagnostics']);
        $t->same(true, $summary['requiresZip64']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectory']);
        $t->same(['zip64-end-of-central-directory'], $summary['zip64EndOfCentralDirectoryIssueCodes']);
        $t->same(3, $summary['zip64EndOfCentralDirectory']['totalEntryCount']);
        $t->same(6, $summary['zip64EndOfCentralDirectory']['eocdZip64ResolutionFieldCount']);
        $t->same(4, $summary['zip64EndOfCentralDirectory']['eocdZip64SentinelFieldCount']);
        $t->same(4, $summary['zip64EndOfCentralDirectory']['eocdZip64ResolvedFieldCount']);
        $t->same([
            'diskEntryCount',
            'totalEntryCount',
            'centralDirectorySize',
            'centralDirectoryOffset',
        ], $summary['zip64EndOfCentralDirectory']['eocdZip64ResolvedFields']);
        $diagnostics = implode(',', $summary['diagnostics']);
        $t->contains('zip64-end-of-central-directory', $diagnostics);
        $t->contains('zip-package-instantiation-failed', $diagnostics);
        $t->contains('odf-package-instantiation-failed', $diagnostics);
        $t->same(false, $summary['zipRawStrictImport']['canInstantiate']);
        $t->same('odf-raw-package-import-metadata-only', $summary['byteExposurePolicy']);
        $t->same(false, $summary['canExposeBytes']);
    },
];
