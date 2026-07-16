<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Database/" manifest:media-type="application/vnd.oasis.opendocument.database" manifest:version="1.2"/>
  <manifest:file-entry manifest:full-path="Object%20Database/database.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Database object package review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$databaseXml = <<<'XML'
<db:database xmlns:db="urn:oasis:names:tc:opendocument:xmlns:database:1.0" db:name="ImportDS"/>
XML;

$buildDatabaseObjectPackage = static function () use ($manifestXml, $contentXml, $databaseXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
        ['name' => 'content.xml', 'data' => $contentXml],
        ['name' => 'Object Database/', 'data' => '', 'compressionMethod' => 0],
        ['name' => 'Object Database/database.xml', 'data' => $databaseXml, 'compressionMethod' => 0],
    ], 'odt embedded database object package');
};

return [
    'classifies ODT database embedded object packages without exposing contained bytes' => static function (TestRunner $t) use ($buildDatabaseObjectPackage, $databaseXml): void {
        $package = $buildDatabaseObjectPackage();
        $compact = OpenDocumentPackage::fromPackage($package);
        $summary = $compact->summarize();
        $objects = $summary['packageObjects'];
        $database = $objects['byRootPart']['Object Database/'];
        $inventory = $summary['packageInventory']['parts'];
        $readerResult = (new OdfReader())->readPackage($package);
        $readerProvenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $readerObjects = $readerProvenance['embeddedObjectPackages'];
        $readerDatabase = $readerObjects['byRootPart']['Object Database/'];
        $readerManifestByPart = [];
        foreach ($readerResult['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $readerManifestByPart[$item['part']] = $item;
            }
        }

        $t->same(1, $objects['count']);
        $t->same(['Object Database/'], $objects['rootParts']);
        $t->same(['database'], $objects['objectTypes']);
        $t->same(1, $summary['packageInventory']['embeddedObjectPackageRootCount']);
        $t->same(1, $summary['packageInventory']['embeddedObjectPackagePartCount']);
        $t->same(0, $summary['exposableMediaPartCount']);
        $t->same([], $summary['mediaParts']);

        $t->same('database', $database['objectType']);
        $t->same('application/vnd.oasis.opendocument.database', $database['mediaType']);
        $t->same('1.2', $database['version']);
        $t->same(true, $database['exists']);
        $t->same(false, $database['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $database['byteExposurePolicy']);
        $t->same('embedded-object-package-metadata-only', $database['reviewPolicy']);
        $t->same(1, $database['containedPartCount']);
        $t->same(strlen($databaseXml), $database['containedByteLength']);
        $t->same(['document-xml' => 1], $database['containedRoleCounts']);
        $t->same(['xml' => 1], $database['containedMediaFamilyCounts']);
        $t->same(['Object Database/database.xml'], array_column($database['containedParts'], 'part'));
        $t->same(['document-xml'], array_column($database['containedParts'], 'containedRole'));
        $t->same([], $database['issues']);

        $t->same(['zip-directory', 'embedded-object-root', 'manifest-declared'], $inventory['Object Database/']['roles']);
        $t->same(['embedded-object-part', 'manifest-declared'], $inventory['Object Database/database.xml']['roles']);
        $t->same(false, $inventory['Object Database/database.xml']['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $inventory['Object Database/database.xml']['byteExposurePolicy']);
        $t->same(null, $inventory['Object Database/database.xml']['byteSha256']);

        $t->same(1, $readerProvenance['embeddedObjectPackageCount']);
        $t->same(['database'], $readerObjects['objectTypes']);
        $t->same($readerObjects, $readerResult['document']->attr('manifest')['packageProvenance']['embeddedObjectPackages']);
        $t->same('database', $readerDatabase['objectType']);
        $t->same('application/vnd.oasis.opendocument.database', $readerDatabase['mediaType']);
        $t->same('embedded-object-package-bytes-blocked', $readerDatabase['byteExposurePolicy']);
        $t->same(1, $readerDatabase['containedPartCount']);
        $t->same(['document-xml' => 1], $readerDatabase['containedRoleCounts']);
        $t->same(['xml' => 1], $readerDatabase['containedMediaFamilyCounts']);
        $t->same([], $readerDatabase['issues']);
        $t->same(true, $readerManifestByPart['Object Database/']['embeddedObjectRoot']);
        $t->same(true, $readerManifestByPart['Object Database/database.xml']['embeddedObjectContainedPart']);
        $t->same('database', $readerManifestByPart['Object Database/database.xml']['embeddedObjectType']);
        $t->same(false, $readerManifestByPart['Object Database/database.xml']['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $readerManifestByPart['Object Database/database.xml']['byteExposurePolicy']);
        $t->same(['embedded-object-part', 'manifest-declared'], $readerProvenance['parts']['Object Database/database.xml']['roles']);
        $t->same(['Object Database/database.xml'], array_column($readerDatabase['containedParts'], 'part'));
        $t->same([], array_column($readerResult['media'], 'part'));
    },
];
