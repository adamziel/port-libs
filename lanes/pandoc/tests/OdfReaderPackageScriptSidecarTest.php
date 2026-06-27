<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$basicModuleXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review" script:language="StarBasic">Sub Approve
End Sub</script:module>
XML;
$javaScript = 'function reviewLink() { return false; }';
$pythonScript = "def audit():\n    return 'ok'\n";
$encryptedScript = 'encrypted script payload';
$orphanScript = 'function orphan() { return true; }';

$manifestXml = '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">' . "\n"
    . '  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Basic/" manifest:media-type=""/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="' . strlen($basicModuleXml) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Scripts/review-link.js" manifest:media-type="text/javascript" manifest:size="' . strlen($javaScript) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Scripts/python/audit.py" manifest:media-type="application/javascript" manifest:size="' . strlen($pythonScript) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Scripts/encrypted.js" manifest:media-type="application/javascript" manifest:size="' . strlen($encryptedScript) . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="script-checksum"/></manifest:file-entry>' . "\n"
    . '</manifest:manifest>';

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Script sidecar package.</text:p>
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
    <dc:title>Script Sidecar Packet</dc:title>
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
    ['name' => 'Basic/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $basicModuleXml, 'compressionMethod' => 0],
    ['name' => 'Scripts/review-link.js', 'data' => $javaScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/python/audit.py', 'data' => $pythonScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/encrypted.js', 'data' => $encryptedScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/orphan.js', 'data' => $orphanScript, 'compressionMethod' => 0],
], 'odt package script sidecars');

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
    'reports ODT package script sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $basicModuleXml,
        $javaScript,
        $pythonScript,
        $encryptedScript,
        $orphanScript,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $compactScripts = OpenDocumentPackage::fromPackage($package)->summarize()['packageScripts'];

        $t->same($scripts, $result['document']->attr('packageScripts'));
        $t->same($scripts, $result['metadata']['odfPackageScripts']);
        $t->same($scripts, $result['importReport']['packageScripts']);
        $t->same($compactScripts['count'], $scripts['count']);
        $t->same($compactScripts['issueCodes'], $scripts['issueCodes']);
        $t->same(6, $scripts['count']);
        $t->same(4, $scripts['readableCount']);
        $t->same(5, $scripts['declaredCount']);
        $t->same(1, $scripts['undeclaredCount']);
        $t->same(1, $scripts['missingCount']);
        $t->same(1, $scripts['encryptedCount']);
        $t->same(1, $scripts['invalidMediaTypeCount']);
        $t->same(4, $scripts['issueCount']);
        $t->same([
            'odf-script-encrypted-package-part',
            'odf-script-invalid-media-type',
            'odf-script-missing-package-part',
            'odf-script-undeclared-package-part',
        ], $scripts['issueCodes']);
        $t->same(['basic', 'scripts'], $scripts['scriptContainers']);
        $t->same(['basic-module', 'javascript', 'python'], $scripts['scriptKinds']);
        $t->same('script-package-bytes-blocked', $scripts['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $scripts['reviewPolicy']);

        $basic = $items['Basic/Standard/Review.xml'];
        $t->same('basic-module', $basic['scriptKind']);
        $t->same('basic', $basic['scriptContainer']);
        $t->same('Standard', $basic['scriptLibrary']);
        $t->same('Review', $basic['scriptModule']);
        $t->same('xml', $basic['extension']);
        $t->same(true, $basic['mediaTypeValid']);
        $t->same(strlen($basicModuleXml), $basic['byteLength']);
        $t->same(sprintf('%08x', crc32($basicModuleXml)), $basic['crc32']);
        $t->same(false, $basic['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $basic['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $basic['reviewPolicy']);
        $t->same([], $basic['issues']);

        $javascript = $items['Scripts/review-link.js'];
        $t->same('javascript', $javascript['scriptKind']);
        $t->same('scripts', $javascript['scriptContainer']);
        $t->same('review-link.js', $javascript['scriptPath']);
        $t->same('review-link', $javascript['scriptModule']);
        $t->same('text/javascript', $javascript['mediaTypeBase']);
        $t->same(true, $javascript['mediaTypeValid']);
        $t->same(strlen($javaScript), $javascript['byteLength']);

        $python = $items['Scripts/python/audit.py'];
        $t->same('python', $python['scriptKind']);
        $t->same('python/audit.py', $python['scriptPath']);
        $t->same('application/javascript', $python['mediaTypeBase']);
        $t->same(false, $python['mediaTypeValid']);
        $t->same(false, $python['valid']);
        $t->same(strlen($pythonScript), $python['byteLength']);
        $t->same(['odf-script-invalid-media-type'], $python['issues']);

        $missing = $items['Scripts/missing.js'];
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-script-missing-package-part'], $missing['issues']);

        $encrypted = $items['Scripts/encrypted.js'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedScript), $encrypted['storedByteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-script-encrypted-package-part'], $encrypted['issues']);

        $orphan = $items['Scripts/orphan.js'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('application/javascript', $orphan['mediaType']);
        $t->same(strlen($orphanScript), $orphan['byteLength']);
        $t->same(['odf-script-undeclared-package-part'], $orphan['issues']);
        $t->same('script-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $t->same(true, $manifestByPart['Basic/Standard/Review.xml']['scriptPackagePart']);
        $t->same(false, $manifestByPart['Basic/Standard/Review.xml']['canExposeBytes']);
        $t->same(null, $manifestByPart['Basic/Standard/Review.xml']['byteSha256']);
        $t->same(true, $manifestByPart['Scripts/python/audit.py']['scriptPackagePart']);
        $t->same(false, $manifestByPart['Scripts/python/audit.py']['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/python/audit.py']['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));

        $t->same(6, $provenance['roleCounts']['script-package']);
        $t->same(1, $provenance['undeclaredRoleCounts']['script-package']);
        $t->same(['manifest-declared', 'script-package'], $provenance['parts']['Basic/Standard/Review.xml']['roles']);
        $t->same(['script-package', 'undeclared-package-entry'], $provenance['parts']['Scripts/orphan.js']['roles']);
    },
];
