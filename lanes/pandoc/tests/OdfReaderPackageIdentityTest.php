<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:wp="urn:wordpress:review" manifest:version="1.3" wp:review-source="identity">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png?cache=1#cover" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml?macro=approve#entry" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Identity review packet.</text:p>
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
    <dc:title>Identity Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$scriptXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review" script:language="StarBasic">Sub Approve
End Sub</script:module>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0, 'comment' => 'manifest identity'],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
];

return [
    'preflights deterministic ODT reader package identity provenance' => static function (TestRunner $t) use ($parts): void {
        $package = ZipPackage::fromParts($parts, 'odt identity review');
        $sourceRecords = [];
        foreach ($package->packageManifestPreflight()['entries'] as $item) {
            $sourceRecords[$item['name']] = $item;
        }
        $result = (new OdfReader())->readPackage($package);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];
        $repeatIdentity = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'odt identity review'))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $changedParts = $parts;
        $changedParts[7]['data'] = 'PRIVATE-NOTE-CHANGED';
        $changedIdentity = (new OdfReader())->readPackage(ZipPackage::fromParts($changedParts, 'odt identity review'))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $changedCommentIdentity = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'odt identity review changed'))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $manifestEntries = [];
        foreach ($identity['manifestEntries'] as $item) {
            $manifestEntries[$item['fullPath']] = $item;
        }
        $packageEntries = [];
        foreach ($identity['packageEntries'] as $item) {
            $packageEntries[$item['part']] = $item;
        }
        $provenanceParts = [];
        foreach ($provenance['parts'] as $item) {
            $provenanceParts[$item['part']] = $item;
        }
        $manifestOrderByPart = [];
        foreach ($provenance['manifestFileEntryOrder'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestOrderByPart[$item['part']] = $item;
            }
        }

        $t->same($identity, $result['document']->attr('manifest')['packageProvenance']['packageIdentity']);
        $t->same(1, $identity['identityVersion']);
        $t->same('opendocument-text', $identity['packageType']);
        $t->same(OdfReader::MIMETYPE, $identity['mimetype']);
        $t->same('1.3', $identity['manifestVersion']);
        $t->same(6, $identity['manifestEntryCount']);
        $t->same(8, $identity['packageEntryCount']);
        $t->same(false, $identity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $identity['byteExposurePolicy']);
        $t->same(64, strlen($identity['identitySha256']));
        $t->true($identity['identityPayloadByteLength'] > 0);
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedCommentIdentity['identitySha256']);

        $t->same([
            '/',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png?cache=1#cover',
            'Basic/Standard/Review.xml?macro=approve#entry',
        ], $identity['manifestFullPaths']);
        $t->same(array_column($parts, 'name'), $identity['packageParts']);
        $t->same(1, $identity['manifestRootCustomAttributeCount']);
        $t->same(['wp:review-source'], $identity['manifestRootCustomAttributeNames']);
        $t->same(true, $identity['hasPackageComment']);
        $t->same(true, $identity['hasEntryComments']);
        $t->same(1, $identity['entryCommentCount']);
        $t->same(['META-INF/manifest.xml'], $identity['commentedEntryNames']);
        $t->same(1, $provenance['scriptPackagePartCount']);
        $t->same(1, $identity['scriptPackagePartCount']);
        $t->same(1, $identity['roleCounts']['script-package']);
        $t->same(1, $identity['packagePartByteExposurePolicyCounts']['script-package-bytes-blocked']);
        $t->same(1, $identity['packagePartByteExposurePolicyCounts']['undeclared-package-entry-no-bytes']);
        $t->same(count($parts), $provenance['centralDirectorySourceRecordEntryCount']);
        $t->same(count($parts), $provenance['centralDirectorySourceRecordSha256Count']);
        $t->true($provenance['centralDirectorySourceRecordByteLength'] > count($parts) * 46);

        $hero = $manifestEntries['Pictures/hero.png?cache=1#cover'];
        $script = $manifestEntries['Basic/Standard/Review.xml?macro=approve#entry'];
        $private = $packageEntries['Notes/private.txt'];
        $contentSource = $sourceRecords['content.xml'];
        $contentProvenance = $provenanceParts['content.xml'];
        $contentIdentity = $packageEntries['content.xml'];
        $contentSourceRecordBytes = $contentSource['centralDirectoryRecordEnd'] - $contentSource['centralDirectoryRecordOffset'];

        $t->same('Pictures/hero.png', $hero['part']);
        $t->same('Pictures/hero.png', $hero['partReference']);
        $t->same('?cache=1#cover', $hero['partSuffix']);
        $t->same('cache=1', $hero['partQuery']);
        $t->same('cover', $hero['partFragment']);
        $t->same(false, $hero['uriEncodedPartReference']);
        $t->same(true, $hero['canExposeBytes']);
        $t->same('Pictures/hero.png', $manifestOrderByPart['Pictures/hero.png']['partReference']);
        $t->same('cache=1', $manifestOrderByPart['Pictures/hero.png']['partQuery']);
        $t->same('cover', $manifestOrderByPart['Pictures/hero.png']['partFragment']);

        $t->same('Basic/Standard/Review.xml', $script['part']);
        $t->same('?macro=approve#entry', $script['partSuffix']);
        $t->same('macro=approve', $script['partQuery']);
        $t->same('entry', $script['partFragment']);
        $t->same(true, $script['scriptPackagePart']);
        $t->same(false, $script['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $script['byteExposurePolicy']);
        $t->same(['manifest-declared', 'script-package'], $packageEntries['Basic/Standard/Review.xml']['roles']);
        $t->same('script-package-bytes-blocked', $packageEntries['Basic/Standard/Review.xml']['byteExposurePolicy']);

        $t->same(['undeclared-package-entry'], $private['roles']);
        $t->same(false, $private['declaredInManifest']);
        $t->same(true, $private['undeclared']);
        $t->same('undeclared-package-entry-no-bytes', $private['byteExposurePolicy']);
        $t->same(null, $private['byteSha256'] ?? null);
        $t->same(sprintf('%08x', crc32('PRIVATE-NOTE')), $private['crc32']);

        $t->same($contentSource['centralDirectoryRecordOffset'], $contentProvenance['centralDirectoryRecordOffset']);
        $t->same($contentSourceRecordBytes, $contentProvenance['centralDirectoryRecordBytes']);
        $t->same($contentSource['centralDirectoryRecordEnd'], $contentProvenance['centralDirectoryRecordEnd']);
        $t->same($contentSource['centralDirectoryRecordSha256'], $contentProvenance['centralDirectoryRecordSha256']);
        $t->same(
            hash('sha256', substr($package->bytes(), $contentProvenance['centralDirectoryRecordOffset'], $contentProvenance['centralDirectoryRecordBytes'])),
            $contentProvenance['centralDirectoryRecordSha256']
        );
        $t->same($contentProvenance['centralDirectoryRecordOffset'], $contentIdentity['centralDirectoryRecordOffset']);
        $t->same($contentProvenance['centralDirectoryRecordBytes'], $contentIdentity['centralDirectoryRecordBytes']);
        $t->same($contentProvenance['centralDirectoryRecordEnd'], $contentIdentity['centralDirectoryRecordEnd']);
        $t->same($contentProvenance['centralDirectoryRecordSha256'], $contentIdentity['centralDirectoryRecordSha256']);
    },
    'carries ODT package sidecar role counts in rich and compact identities' => static function (TestRunner $t): void {
        $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Identity role counts.</text:p>
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
    <dc:title>Identity Role Counts</dc:title>
  </office:meta>
</office:document-meta>
XML;
        $rdfXml = <<<'XML'
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <rdf:Description rdf:about="content.xml">
    <dc:title>Identity RDF sidecar</dc:title>
  </rdf:Description>
</rdf:RDF>
XML;
        $signatureXml = <<<'XML'
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
  <dsig:Signature Id="identity-signature"/>
</dsig:document-signatures>
XML;
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Fonts/source.otf" manifest:media-type="font/otf"/>
  <manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml"/>
  <manifest:file-entry manifest:full-path="ObjectReplacements/preview.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:full-path="META-INF/review-state.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Links/cache/preview.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="database/script" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Versions/1/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Gallery/theme/preview.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Forms/review/form.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Attachments/review/source.pdf" manifest:media-type="application/pdf"/>
  <manifest:file-entry manifest:full-path="Templates/review/letter.ott" manifest:media-type="application/vnd.oasis.opendocument.text-template"/>
  <manifest:file-entry manifest:full-path="Dictionaries/en_US/en_US.dic" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
  <manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'Basic/Standard/Review.xml', 'data' => '<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review"/>', 'compressionMethod' => 0],
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml, 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel:item command="review"/>', 'compressionMethod' => 0],
            ['name' => 'Fonts/source.otf', 'data' => 'FONT-BYTES', 'compressionMethod' => 0],
            ['name' => 'manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
            ['name' => 'ObjectReplacements/preview.png', 'data' => 'REPLACEMENT-PNG', 'compressionMethod' => 0],
            ['name' => 'layout-cache', 'data' => 'LAYOUT-CACHE', 'compressionMethod' => 0],
            ['name' => 'META-INF/review-state.xml', 'data' => '<review-state/>', 'compressionMethod' => 0],
            ['name' => 'Links/cache/preview.png', 'data' => 'LINKED-PNG', 'compressionMethod' => 0],
            ['name' => 'database/script', 'data' => 'CREATE TABLE identity_role_counts (id INTEGER);', 'compressionMethod' => 0],
            ['name' => 'Versions/1/content.xml', 'data' => '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>', 'compressionMethod' => 0],
            ['name' => 'Gallery/theme/preview.png', 'data' => 'GALLERY-PNG', 'compressionMethod' => 0],
            ['name' => 'Forms/review/form.xml', 'data' => '<form/>', 'compressionMethod' => 0],
            ['name' => 'Attachments/review/source.pdf', 'data' => '%PDF-IDENTITY', 'compressionMethod' => 0],
            ['name' => 'Templates/review/letter.ott', 'data' => 'TEMPLATE-BYTES', 'compressionMethod' => 0],
            ['name' => 'Dictionaries/en_US/en_US.dic', 'data' => "identity\n", 'compressionMethod' => 0],
            ['name' => 'Object 1/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Object 1/content.xml', 'data' => '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>', 'compressionMethod' => 0],
        ], 'odt identity role count review');

        $richIdentity = (new OdfReader())->readPackage($package)['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $compactIdentity = OpenDocumentPackage::fromPackage($package)->summarize()['packageIdentity'];

        foreach ([
            'scriptPackagePartCount',
            'packageSignaturePartCount',
            'packageThumbnailPartCount',
            'configurationPackagePartCount',
            'packageFontPartCount',
            'rdfMetadataPartCount',
            'objectReplacementPartCount',
            'layoutCachePartCount',
            'metaInfSidecarPartCount',
            'linkedResourcePackagePartCount',
            'databasePackagePartCount',
            'versionPackagePartCount',
            'galleryPackagePartCount',
            'formPackagePartCount',
            'attachmentPackagePartCount',
            'templatePackagePartCount',
            'dictionaryPackagePartCount',
        ] as $key) {
            $expected = $key === 'packageThumbnailPartCount' ? 0 : 1;
            $t->same($expected, $richIdentity[$key], "rich {$key}");
        }

        foreach ([
            'scriptPackagePartCount',
            'packageSignaturePartCount',
            'embeddedObjectPackageRootCount',
            'embeddedObjectPackagePartCount',
            'objectReplacementPartCount',
            'configurationPackagePartCount',
            'fontPackagePartCount',
            'rdfMetadataPartCount',
            'layoutCachePartCount',
            'metaInfSidecarPartCount',
            'linkedResourcePackagePartCount',
            'databasePackagePartCount',
            'versionPackagePartCount',
            'galleryPackagePartCount',
            'formPackagePartCount',
            'attachmentPackagePartCount',
            'templatePackagePartCount',
            'dictionaryPackagePartCount',
        ] as $key) {
            $t->same(1, $compactIdentity[$key], "compact {$key}");
        }

        $t->same(0, $compactIdentity['packageThumbnailPartCount']);
        $t->same(1, $richIdentity['roleCounts']['database-package']);
        $t->same(1, $compactIdentity['roleCounts']['database-package']);
        $t->same(1, $richIdentity['packagePartByteExposurePolicyCounts']['database-package-bytes-blocked']);
        $t->same(1, $compactIdentity['byteExposurePolicyCounts']['database-package-bytes-blocked']);
    },
];
