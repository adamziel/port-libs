<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$registrationXml = '<db:database xmlns:db="urn:oasis:names:tc:opendocument:xmlns:database:1.0" db:name="ReviewDS"/>';
$scriptBytes = 'CREATE TABLE review_packet(id INTEGER);';
$dataBytes = "HSQLDBDATA\0\1";
$encryptedBytes = 'encrypted database log payload';
$invalidXml = '<db:database/>';
$orphanLog = 'checkpoint orphan log';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Database/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Database/registration.xml" manifest:media-type="text/xml" manifest:size="__REGISTRATION_SIZE__"/>
  <manifest:file-entry manifest:full-path="Database/script" manifest:media-type="text/plain" manifest:size="__SCRIPT_SIZE__"/>
  <manifest:file-entry manifest:full-path="Database/data" manifest:media-type="application/octet-stream" manifest:size="__DATA_SIZE__"/>
  <manifest:file-entry manifest:full-path="Database/missing.properties" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Database/encrypted.log" manifest:media-type="text/plain" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="database-checksum"/>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Database/invalid.xml" manifest:media-type="image/png" manifest:size="__INVALID_SIZE__"/>
</manifest:manifest>
XML;

$manifestXml = str_replace(
    ['__REGISTRATION_SIZE__', '__SCRIPT_SIZE__', '__DATA_SIZE__', '__INVALID_SIZE__'],
    [(string) strlen($registrationXml), (string) strlen($scriptBytes), (string) strlen($dataBytes), (string) strlen($invalidXml)],
    $manifestXml
);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Database package metadata review.</text:p>
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
    <dc:title>Database Package Metadata</dc:title>
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
    ['name' => 'Database/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Database/registration.xml', 'data' => $registrationXml, 'compressionMethod' => 0],
    ['name' => 'Database/script', 'data' => $scriptBytes, 'compressionMethod' => 0],
    ['name' => 'Database/data', 'data' => $dataBytes, 'compressionMethod' => 0],
    ['name' => 'Database/encrypted.log', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Database/invalid.xml', 'data' => $invalidXml, 'compressionMethod' => 0],
    ['name' => 'Database/orphan.log', 'data' => $orphanLog, 'compressionMethod' => 0],
], 'odt reader database package metadata');

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
    'reports ODT database package sidecars as metadata-only package review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $registrationXml,
        $scriptBytes,
        $dataBytes,
        $encryptedBytes,
        $invalidXml,
        $orphanLog
    ): void {
        $result = (new OdfReader())->readPackage($buildPackage());
        $databases = $result['packageDatabases'];
        $items = $indexBy($databases['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($databases, $result['document']->attr('packageDatabases'));
        $t->same($databases, $result['metadata']['odfPackageDatabases']);
        $t->same($databases, $result['importReport']['packageDatabases']);
        $t->same(8, $databases['count']);
        $t->same(7, $databases['fileCount']);
        $t->same(1, $databases['directoryCount']);
        $t->same(7, $databases['storedPartCount']);
        $t->same(5, $databases['readableCount']);
        $t->same(7, $databases['declaredCount']);
        $t->same(1, $databases['undeclaredCount']);
        $t->same(1, $databases['missingCount']);
        $t->same(1, $databases['encryptedCount']);
        $t->same(0, $databases['missingMediaTypeCount']);
        $t->same(1, $databases['invalidMediaTypeCount']);
        $t->same(4, $databases['issueCount']);
        $t->same([
            'odf-database-package-encrypted-part',
            'odf-database-package-invalid-media-type',
            'odf-database-package-missing-part',
            'odf-database-package-undeclared-part',
        ], $databases['issueCodes']);
        $t->same([
            'database-directory' => 1,
            'database-storage' => 1,
            'database-text' => 4,
            'database-xml' => 2,
        ], $databases['databaseKindCounts']);
        $t->same('database-package-bytes-blocked', $databases['byteExposurePolicy']);
        $t->same('database-package-metadata-only', $databases['reviewPolicy']);

        $directory = $items['Database/'];
        $t->same(true, $directory['isDirectory']);
        $t->same(true, $directory['databasePackageDirectory']);
        $t->same(false, $directory['databasePackagePart']);
        $t->same('database-directory', $directory['databaseKind']);
        $t->same(null, $directory['mediaType']);
        $t->same(true, $directory['mediaTypeValid']);
        $t->same(0, $directory['storedByteLength']);
        $t->same(null, $directory['byteLength']);
        $t->same(false, $directory['canExposeBytes']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);
        $t->same([], $directory['issues']);

        $registration = $items['Database/registration.xml'];
        $t->same('database-xml', $registration['databaseKind']);
        $t->same('registration.xml', $registration['databasePath']);
        $t->same('xml', $registration['extension']);
        $t->same('text/xml', $registration['mediaType']);
        $t->same(true, $registration['mediaTypeValid']);
        $t->same(true, $registration['valid']);
        $t->same(strlen($registrationXml), $registration['byteLength']);
        $t->same(sprintf('%08x', crc32($registrationXml)), $registration['crc32']);
        $t->same(false, $registration['canExposeAsDocumentMedia']);
        $t->same('database-package-bytes-blocked', $registration['byteExposurePolicy']);
        $t->same([], $registration['issues']);
        $t->same(true, $manifestByPart['Database/registration.xml']['databasePackagePart']);
        $t->same(false, $manifestByPart['Database/registration.xml']['canExposeBytes']);
        $t->same(null, $manifestByPart['Database/registration.xml']['byteSha256']);

        $script = $items['Database/script'];
        $t->same('database-text', $script['databaseKind']);
        $t->same('script', $script['databaseLeafName']);
        $t->same('text/plain', $script['mediaType']);
        $t->same(strlen($scriptBytes), $script['byteLength']);
        $t->same('database-package-bytes-blocked', $manifestByPart['Database/script']['byteExposurePolicy']);

        $data = $items['Database/data'];
        $t->same('database-storage', $data['databaseKind']);
        $t->same('application/octet-stream', $data['mediaType']);
        $t->same(true, $data['mediaTypeValid']);
        $t->same(strlen($dataBytes), $data['byteLength']);

        $missing = $items['Database/missing.properties'];
        $t->same(false, $missing['exists']);
        $t->same('database-text', $missing['databaseKind']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-database-package-missing-part'], $missing['issues']);
        $t->same('database-package-bytes-blocked', $manifestByPart['Database/missing.properties']['byteExposurePolicy']);

        $encrypted = $items['Database/encrypted.log'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(false, $encrypted['valid']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(sprintf('%08x', crc32($encryptedBytes)), $encrypted['storedCrc32']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-database-package-encrypted-part'], $encrypted['issues']);

        $invalid = $items['Database/invalid.xml'];
        $t->same(false, $invalid['mediaTypeValid']);
        $t->same(false, $invalid['valid']);
        $t->same('image/png', $invalid['mediaType']);
        $t->same(strlen($invalidXml), $invalid['byteLength']);
        $t->same(['odf-database-package-invalid-media-type'], $invalid['issues']);
        $t->same('database-package-bytes-blocked', $invalid['byteExposurePolicy']);

        $orphan = $items['Database/orphan.log'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('database-text', $orphan['databaseKind']);
        $t->same('text/plain', $orphan['mediaType']);
        $t->same(strlen($orphanLog), $orphan['byteLength']);
        $t->same(['odf-database-package-undeclared-part'], $orphan['issues']);
        $t->same('database-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(['database-package', 'manifest-declared'], $provenance['parts']['Database/script']['roles']);
        $t->same(['database-package', 'undeclared-package-entry'], $provenance['parts']['Database/orphan.log']['roles']);
        $t->same(6, $provenance['databasePackagePartCount']);
        $t->same(1, $provenance['undeclaredRoleCounts']['database-package']);
    },
];
