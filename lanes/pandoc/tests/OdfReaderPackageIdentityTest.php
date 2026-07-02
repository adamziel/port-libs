<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
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
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'odt identity review'));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];
        $repeatIdentity = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'odt identity review'))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $changedParts = $parts;
        $changedParts[7]['data'] = 'PRIVATE-NOTE-CHANGED';
        $changedIdentity = (new OdfReader())->readPackage(ZipPackage::fromParts($changedParts, 'odt identity review'))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $changedReferenceParts = $parts;
        $changedReferenceParts[1]['data'] = str_replace('cache=1#cover', 'cache=2#cover', $changedReferenceParts[1]['data']);
        $changedReferenceIdentity = (new OdfReader())->readPackage(ZipPackage::fromParts($changedReferenceParts, 'odt identity review'))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $changedCommentIdentity = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'odt identity review changed'))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $manifestEntries = [];
        foreach ($identity['manifestEntries'] as $item) {
            $manifestEntries[$item['fullPath']] = $item;
        }
        $packageEntries = [];
        foreach ($identity['packageEntries'] as $item) {
            $packageEntries[$item['part']] = $item;
        }
        $manifestOrderByPart = [];
        foreach ($provenance['manifestFileEntryOrder'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestOrderByPart[$item['part']] = $item;
            }
        }
        $suffixItems = [];
        foreach ($identity['manifestPartReferenceSuffixItems'] as $item) {
            $suffixItems[$item['fullPath']] = $item;
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
        $t->true($identity['identitySha256'] !== $changedReferenceIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedCommentIdentity['identitySha256']);
        $t->same(2, $identity['manifestPartReferenceSuffixCount']);
        $t->same(2, $identity['manifestPartReferenceQueryCount']);
        $t->same(2, $identity['manifestPartReferenceFragmentCount']);
        $t->same($provenance['manifestPartReferenceSuffixItems'], $identity['manifestPartReferenceSuffixItems']);
        $t->same([
            'Pictures/hero.png?cache=1#cover',
            'Basic/Standard/Review.xml?macro=approve#entry',
        ], array_column($identity['manifestPartReferenceSuffixItems'], 'fullPath'));

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
        $t->same($result['documentPartVersions'], $provenance['documentPartVersions']);
        $t->same($result['documentPartVersions'], $identity['documentPartVersions']);
        $t->same(3, $identity['documentPartVersionCount']);
        $t->same(0, $identity['documentPartVersionedCount']);
        $t->same(3, $identity['documentPartMissingVersionCount']);
        $t->same(0, $identity['documentPartVersionMismatchCount']);
        $t->same(0, $identity['documentPartRootCustomAttributeCount']);
        $t->same(
            $result['documentPartVersions']['rootNamespaceDeclarationCount'],
            $identity['documentPartRootNamespaceDeclarationCount']
        );
        $t->same(['content.xml', 'styles.xml', 'meta.xml'], $identity['documentPartVersions']['missingVersionParts']);

        $hero = $manifestEntries['Pictures/hero.png?cache=1#cover'];
        $script = $manifestEntries['Basic/Standard/Review.xml?macro=approve#entry'];
        $private = $packageEntries['Notes/private.txt'];

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
        $t->same('cache=1', $suffixItems['Pictures/hero.png?cache=1#cover']['partQuery']);
        $t->same('cover', $suffixItems['Pictures/hero.png?cache=1#cover']['partFragment']);
        $t->same('package-bytes-exposable', $suffixItems['Pictures/hero.png?cache=1#cover']['byteExposurePolicy']);

        $t->same('Basic/Standard/Review.xml', $script['part']);
        $t->same('?macro=approve#entry', $script['partSuffix']);
        $t->same('macro=approve', $script['partQuery']);
        $t->same('entry', $script['partFragment']);
        $t->same(true, $script['scriptPackagePart']);
        $t->same(false, $script['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $script['byteExposurePolicy']);
        $t->same('macro=approve', $suffixItems['Basic/Standard/Review.xml?macro=approve#entry']['partQuery']);
        $t->same('script-package-bytes-blocked', $suffixItems['Basic/Standard/Review.xml?macro=approve#entry']['byteExposurePolicy']);
        $t->same(['manifest-declared', 'script-package'], $packageEntries['Basic/Standard/Review.xml']['roles']);
        $t->same('script-package-bytes-blocked', $packageEntries['Basic/Standard/Review.xml']['byteExposurePolicy']);

        $t->same(['undeclared-package-entry'], $private['roles']);
        $t->same(false, $private['declaredInManifest']);
        $t->same(true, $private['undeclared']);
        $t->same('undeclared-package-entry-no-bytes', $private['byteExposurePolicy']);
        $t->same(null, $private['byteSha256'] ?? null);
        $t->same(sprintf('%08x', crc32('PRIVATE-NOTE')), $private['crc32']);
    },
];
