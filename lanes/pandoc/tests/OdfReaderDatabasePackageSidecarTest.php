<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$databaseScript = 'CREATE TABLE review_packet (id INTEGER PRIMARY KEY);';
$databaseData = 'DATABASE-DATA-BYTES';
$databaseConfig = '<database><table name="review_packet"/></database>';
$databaseEncrypted = 'ENCRYPTED-DATABASE-BYTES';
$databaseLog = 'DATABASE-LOG-BYTES';

$databaseScriptSize = strlen($databaseScript);
$databaseDataSize = strlen($databaseData);
$databaseConfigSize = strlen($databaseConfig);
$databaseEncryptedSize = strlen($databaseEncrypted);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="database/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="database/script" manifest:media-type="text/plain" manifest:size="{$databaseScriptSize}"/>
  <manifest:file-entry manifest:full-path="database/data" manifest:media-type="application/octet-stream" manifest:size="{$databaseDataSize}"/>
  <manifest:file-entry manifest:full-path="database/config.xml" manifest:media-type="text/xml" manifest:size="{$databaseConfigSize}bytes"/>
  <manifest:file-entry manifest:full-path="database/missing" manifest:media-type="application/octet-stream" manifest:size="19"/>
  <manifest:file-entry manifest:full-path="database/encrypted" manifest:media-type="application/octet-stream" manifest:size="{$databaseEncryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="database-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$uppercaseDatabaseManifestXml = str_replace('manifest:full-path="database/', 'manifest:full-path="Database/', $manifestXml);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Database sidecar package.</text:p>
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
    <dc:title>Database Sidecar Packet</dc:title>
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
    ['name' => 'database/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'database/script', 'data' => $databaseScript, 'compressionMethod' => 0],
    ['name' => 'database/data', 'data' => $databaseData, 'compressionMethod' => 0],
    ['name' => 'database/config.xml', 'data' => $databaseConfig, 'compressionMethod' => 0],
    ['name' => 'database/encrypted', 'data' => $databaseEncrypted, 'compressionMethod' => 0],
    ['name' => 'database/log', 'data' => $databaseLog, 'compressionMethod' => 0],
], 'odt database package sidecars');

$buildUppercasePackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $uppercaseDatabaseManifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Database/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Database/script', 'data' => $databaseScript, 'compressionMethod' => 0],
    ['name' => 'Database/data', 'data' => $databaseData, 'compressionMethod' => 0],
    ['name' => 'Database/config.xml', 'data' => $databaseConfig, 'compressionMethod' => 0],
    ['name' => 'Database/encrypted', 'data' => $databaseEncrypted, 'compressionMethod' => 0],
    ['name' => 'Database/log', 'data' => $databaseLog, 'compressionMethod' => 0],
], 'odt uppercase database package sidecars');

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
    'reports ODT database package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $databaseScript,
        $databaseData,
        $databaseConfig,
        $databaseLog,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerDatabases = $result['packageDatabases'];
        $readerItems = $indexBy($readerDatabases['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $undeclaredByPart = $indexBy($result['importReport']['manifest']['undeclaredEntries'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerDatabases, $result['document']->attr('packageDatabases'));
        $t->same($readerDatabases, $result['metadata']['odfPackageDatabases']);
        $t->same($readerDatabases, $result['importReport']['packageDatabases']);
        $t->same(7, $readerDatabases['count']);
        $t->same(6, $readerDatabases['fileCount']);
        $t->same(6, $readerDatabases['storedPartCount']);
        $t->same(4, $readerDatabases['readableCount']);
        $t->same(6, $readerDatabases['declaredCount']);
        $t->same(1, $readerDatabases['undeclaredCount']);
        $t->same(1, $readerDatabases['missingCount']);
        $t->same(1, $readerDatabases['directoryCount']);
        $t->same(1, $readerDatabases['encryptedCount']);
        $t->same(0, $readerDatabases['missingMediaTypeCount']);
        $t->same(0, $readerDatabases['invalidMediaTypeCount']);
        $t->same(1, $readerDatabases['invalidDeclaredSizeCount']);
        $t->same(4, $readerDatabases['issueCount']);
        $t->same([
            'odf-database-package-encrypted-part',
            'odf-database-package-invalid-declared-size',
            'odf-database-package-missing-part',
            'odf-database-package-undeclared-part',
        ], $readerDatabases['issueCodes']);
        $t->same($readerDatabases['kindCounts'], $readerDatabases['databaseKindCounts']);
        $t->same('database-package-bytes-blocked', $readerDatabases['byteExposurePolicy']);
        $t->same('database-package-metadata-only', $readerDatabases['reviewPolicy']);

        $script = $readerItems['database/script'];
        $t->same('database-script', $script['kind']);
        $t->same('script', $script['group']);
        $t->same(true, $script['declared']);
        $t->same(true, $script['valid']);
        $t->same(false, $script['canExposeBytes']);
        $t->same(false, $script['canExposeAsDocumentMedia']);
        $t->same(strlen($databaseScript), $script['byteLength']);
        $t->same(sprintf('%08x', crc32($databaseScript)), $script['crc32']);
        $t->same('database-package-bytes-blocked', $script['byteExposurePolicy']);
        $t->same([], $script['issues']);

        $data = $readerItems['database/data'];
        $t->same('database-binary-store', $data['kind']);
        $t->same('application/octet-stream', $data['mediaTypeBase']);
        $t->same(strlen($databaseData), $data['storedByteLength']);
        $t->same('database-package-bytes-blocked', $data['byteExposurePolicy']);

        $config = $readerItems['database/config.xml'];
        $t->same('database-xml', $config['kind']);
        $t->same(null, $config['declaredSize']);
        $t->same(strlen($databaseConfig) . 'bytes', $config['declaredSizeRaw']);
        $t->same(false, $config['declaredSizeValid']);
        $t->same(true, $config['declaredSizeInvalid']);
        $t->same(false, $config['declaredSizeMismatch']);
        $t->same(['odf-database-package-invalid-declared-size'], $config['issues']);

        $missing = $readerItems['database/missing'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-database-package-missing-part'], $missing['issues']);
        $t->same('database-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['database/encrypted'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-database-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $undeclared = $readerItems['database/log'];
        $t->same(false, $undeclared['declared']);
        $t->same(true, $undeclared['undeclared']);
        $t->same('database-log', $undeclared['kind']);
        $t->same('log', $undeclared['group']);
        $t->same(strlen($databaseLog), $undeclared['byteLength']);
        $t->same(['odf-database-package-undeclared-part'], $undeclared['issues']);
        $t->same('database-package-bytes-blocked', $undeclared['byteExposurePolicy']);

        $manifestData = $manifestByPart['database/data'];
        $t->same(true, $manifestData['databasePackagePart']);
        $t->same(false, $manifestData['canExposeBytes']);
        $t->same(null, $manifestData['byteLength']);
        $t->same(strlen($databaseData), $manifestData['storedByteLength']);
        $t->same(null, $manifestData['byteSha256']);
        $t->same('database-package-bytes-blocked', $manifestData['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(6, $readerProvenance['databasePackagePartCount']);
        $t->same(6, $readerProvenance['roleCounts']['database-package']);
        $t->same(['database-package', 'manifest-declared'], $readerProvenance['parts']['database/data']['roles']);
        $t->same(['database-package', 'undeclared-package-entry'], $readerProvenance['parts']['database/log']['roles']);
        $t->same(true, $readerProvenance['parts']['database/data']['databasePackagePart']);
        $t->same(true, $readerProvenance['parts']['database/log']['databasePackagePart']);
        $t->same('database-package-bytes-blocked', $readerProvenance['parts']['database/log']['byteExposurePolicy']);
        $t->same(true, $undeclaredByPart['database/log']['databasePackagePart']);
        $t->same('database-package-bytes-blocked', $undeclaredByPart['database/log']['byteExposurePolicy']);
        $t->same(true, $readerProvenance['packageIdentity']['manifestEntries'][6]['databasePackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['packageEntries'][8]['databasePackagePart']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactDatabases = $compactSummary['packageDatabases'];
        $compactItems = $indexBy($compactDatabases['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactUndeclaredByPath = $indexBy($compactSummary['undeclaredPackageEntries'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(7, $compactDatabases['count']);
        $t->same(6, $compactDatabases['fileCount']);
        $t->same(6, $compactDatabases['storedPartCount']);
        $t->same(4, $compactDatabases['readableCount']);
        $t->same(6, $compactDatabases['declaredCount']);
        $t->same(1, $compactDatabases['undeclaredCount']);
        $t->same(1, $compactDatabases['missingCount']);
        $t->same(1, $compactDatabases['directoryCount']);
        $t->same(1, $compactDatabases['encryptedCount']);
        $t->same(1, $compactDatabases['invalidDeclaredSizeCount']);
        $t->same(4, $compactDatabases['issueCount']);
        $t->same($readerDatabases['issueCodes'], $compactDatabases['issueCodes']);
        $t->same($compactDatabases['kindCounts'], $compactDatabases['databaseKindCounts']);
        $t->same('database-package-bytes-blocked', $compactDatabases['byteExposurePolicy']);
        $t->same('database-package-metadata-only', $compactDatabases['reviewPolicy']);
        $t->same('database-script', $compactItems['database/script']['kind']);
        $t->same(false, $compactItems['database/script']['canExposeBytes']);
        $t->same(false, $compactItems['database/script']['canExposeAsDocumentMedia']);
        $t->same(strlen($databaseScript), $compactItems['database/script']['byteLength']);
        $t->same(sprintf('%08x', crc32($databaseScript)), $compactItems['database/script']['crc32']);
        $t->same('database-xml', $compactItems['database/config.xml']['kind']);
        $t->same(null, $compactItems['database/config.xml']['declaredSize']);
        $t->same(strlen($databaseConfig) . 'bytes', $compactItems['database/config.xml']['declaredSizeRaw']);
        $t->same(false, $compactItems['database/config.xml']['declaredSizeValid']);
        $t->same(true, $compactItems['database/config.xml']['declaredSizeInvalid']);
        $t->same(false, $compactItems['database/config.xml']['declaredSizeMismatch']);
        $t->same(['odf-database-package-invalid-declared-size'], $compactItems['database/config.xml']['issues']);
        $t->same(['odf-database-package-missing-part'], $compactItems['database/missing']['issues']);
        $t->same(['odf-database-package-encrypted-part'], $compactItems['database/encrypted']['issues']);
        $t->same(['odf-database-package-undeclared-part'], $compactItems['database/log']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(6, $compactSummary['manifestReview']['databasePackagePartCount']);
        $t->same(true, $reviewByPath['database/data']['databasePackagePart']);
        $t->same(false, $reviewByPath['database/data']['canExposeBytes']);
        $t->same(null, $reviewByPath['database/data']['byteLength']);
        $t->same(strlen($databaseData), $reviewByPath['database/data']['storedByteLength']);
        $t->same('database-package-bytes-blocked', $reviewByPath['database/data']['byteExposurePolicy']);
        $t->same('database', $reviewByPath['database/data']['manifestMediaFamily']);
        $t->same(5, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['database']);
        $t->same(6, $inventory['databasePackagePartCount']);
        $t->same(6, $inventory['roleCounts']['database-package']);
        $t->same(['database-package', 'manifest-declared'], $inventory['parts']['database/data']['roles']);
        $t->same(['database-package', 'undeclared-package-entry'], $inventory['parts']['database/log']['roles']);
        $t->same(true, $inventory['parts']['database/data']['databasePackagePart']);
        $t->same(true, $inventory['parts']['database/log']['databasePackagePart']);
        $t->same('database-package-bytes-blocked', $inventory['parts']['database/log']['byteExposurePolicy']);
        $t->same(true, $compactUndeclaredByPath['database/log']['databasePackagePart']);
        $t->same('database-package-bytes-blocked', $compactUndeclaredByPath['database/log']['byteExposurePolicy']);
        $t->same(false, $inventory['parts']['database/data']['canExposeBytes']);
    },
    'preserves uppercase Database package metadata counters' => static function (TestRunner $t) use (
        $buildUppercasePackage,
        $databaseScript,
        $databaseData,
        $databaseConfig,
        $databaseLog,
        $indexBy
    ): void {
        $package = $buildUppercasePackage();
        $result = (new OdfReader())->readPackage($package);
        $readerDatabases = $result['packageDatabases'];
        $readerItems = $indexBy($readerDatabases['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same(7, $readerDatabases['count']);
        $t->same(6, $readerDatabases['fileCount']);
        $t->same(6, $readerDatabases['storedPartCount']);
        $t->same(4, $readerDatabases['readableCount']);
        $t->same(6, $readerDatabases['declaredCount']);
        $t->same(1, $readerDatabases['undeclaredCount']);
        $t->same($readerDatabases['kindCounts'], $readerDatabases['databaseKindCounts']);
        $t->same([
            'odf-database-package-encrypted-part',
            'odf-database-package-invalid-declared-size',
            'odf-database-package-missing-part',
            'odf-database-package-undeclared-part',
        ], $readerDatabases['issueCodes']);

        $script = $readerItems['Database/script'];
        $t->same('database-script', $script['kind']);
        $t->same('script', $script['group']);
        $t->same('database', $script['packageRoot']);
        $t->same(strlen($databaseScript), $script['byteLength']);
        $t->same('database-package-bytes-blocked', $script['byteExposurePolicy']);

        $data = $readerItems['Database/data'];
        $t->same('database-binary-store', $data['kind']);
        $t->same(strlen($databaseData), $data['storedByteLength']);
        $t->same(false, $data['canExposeAsDocumentMedia']);
        $t->same(true, $manifestByPart['Database/data']['databasePackagePart']);
        $t->same('database-package-bytes-blocked', $manifestByPart['Database/data']['byteExposurePolicy']);

        $config = $readerItems['Database/config.xml'];
        $t->same('database-xml', $config['kind']);
        $t->same(strlen($databaseConfig) . 'bytes', $config['declaredSizeRaw']);
        $t->same(true, $config['declaredSizeInvalid']);

        $encrypted = $readerItems['Database/encrypted'];
        $t->same(null, $encrypted['byteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $undeclared = $readerItems['Database/log'];
        $t->same(strlen($databaseLog), $undeclared['byteLength']);
        $t->same(['odf-database-package-undeclared-part'], $undeclared['issues']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(6, $readerProvenance['databasePackagePartCount']);
        $t->same(['database-package', 'manifest-declared'], $readerProvenance['parts']['Database/data']['roles']);
        $t->same(['database-package', 'undeclared-package-entry'], $readerProvenance['parts']['Database/log']['roles']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactDatabases = $compactSummary['packageDatabases'];
        $compactItems = $indexBy($compactDatabases['items'], 'packagePath');
        $inventory = $compactSummary['packageInventory'];

        $t->same(7, $compactDatabases['count']);
        $t->same(6, $compactDatabases['fileCount']);
        $t->same(6, $compactDatabases['storedPartCount']);
        $t->same(4, $compactDatabases['readableCount']);
        $t->same($compactDatabases['kindCounts'], $compactDatabases['databaseKindCounts']);
        $t->same($readerDatabases['issueCodes'], $compactDatabases['issueCodes']);
        $t->same('database-script', $compactItems['Database/script']['kind']);
        $t->same(false, $compactItems['Database/script']['canExposeBytes']);
        $t->same(strlen($databaseData), $compactItems['Database/data']['storedByteLength']);
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(6, $inventory['databasePackagePartCount']);
        $t->same(['database-package', 'manifest-declared'], $inventory['parts']['Database/data']['roles']);
        $t->same(['database-package', 'undeclared-package-entry'], $inventory['parts']['Database/log']['roles']);
        $t->same(false, $inventory['parts']['Database/data']['canExposeBytes']);
    },
];
