<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$basicModuleXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review" script:language="StarBasic">Sub Approve
End Sub</script:module>
XML;
$javaScript = 'function ReviewLinkClick() { return false; }';
$encryptedPython = "def hidden():\n    return 'blocked'\n";
$orphanRuby = "def orphan\n  true\nend\n";

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml; charset=UTF-8" manifest:size="BASIC_SIZE"/>
  <manifest:file-entry manifest:full-path="Scripts/review-link.js" manifest:media-type="text/javascript; charset=UTF-8" manifest:size="JS_SIZE"/>
  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>
  <manifest:file-entry manifest:full-path="Scripts/encrypted.py" manifest:media-type="text/x-python" manifest:size="PY_SIZE">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="script-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;
$manifestXml = str_replace(
    ['BASIC_SIZE', 'JS_SIZE', 'PY_SIZE'],
    [(string) strlen($basicModuleXml), (string) strlen($javaScript), (string) strlen($encryptedPython)],
    $manifestXml
);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Scripted <text:a xlink:href="https://example.test/source.odt"><office:event-listeners><script:event-listener script:event-name="dom:activate" script:language="ooo:Basic" xlink:href="vnd.sun.star.script:Standard.Review.Approve?language=Basic&amp;location=document" xlink:type="simple"/><script:event-listener script:event-name="dom:click" script:language="JavaScript" xlink:href="Scripts/review-link.js" xlink:type="simple"/><script:event-listener script:event-name="dom:error" script:language="JavaScript" xlink:href="Scripts/missing.js" xlink:type="simple"/></office:event-listeners>review link</text:a>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $basicModuleXml, 'compressionMethod' => 0],
    ['name' => 'Scripts/review-link.js', 'data' => $javaScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/encrypted.py', 'data' => $encryptedPython, 'compressionMethod' => 0],
    ['name' => 'Scripts/orphan.rb', 'data' => $orphanRuby, 'compressionMethod' => 0],
], 'odt script package review');

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
    'carries ODT script package review metadata without exposing script bytes' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $basicModuleXml,
        $javaScript,
        $encryptedPython,
        $orphanRuby
    ): void {
        $result = (new OdfReader())->readPackage($buildPackage());
        $scripts = $result['scriptMetadata'];
        $partsByPart = $indexBy($scripts['parts'], 'part');

        $t->same($scripts, $result['document']->attr('scriptMetadata'));
        $t->same($scripts, $result['metadata']['odfPackageScripts']);
        $t->same($scripts, $result['importReport']['scriptMetadata']);
        $t->same(5, $scripts['partCount']);
        $t->same(4, $scripts['declaredPartCount']);
        $t->same(1, $scripts['undeclaredPartCount']);
        $t->same(1, $scripts['missingPartCount']);
        $t->same(1, $scripts['encryptedPartCount']);
        $t->same(3, $scripts['issueCount']);
        $t->same([
            'odf-script-package-encrypted-part',
            'odf-script-package-missing-part',
            'odf-script-package-undeclared-part',
        ], $scripts['issueCodes']);
        $t->same('script-package-bytes-blocked', $scripts['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $scripts['reviewPolicy']);

        $basic = $partsByPart['Basic/Standard/Review.xml'];
        $t->same('text/xml; charset=UTF-8', $basic['mediaType']);
        $t->same('text/xml', $basic['mediaTypeBase']);
        $t->same(true, $basic['mediaTypeHasParameters']);
        $t->same(['charset' => 'UTF-8'], $basic['mediaTypeParameterMap']);
        $t->same('basic-module', $basic['kind']);
        $t->same('Basic', $basic['language']);
        $t->same(true, $basic['valid']);
        $t->same(false, $basic['canExposeBytes']);
        $t->same(false, $basic['canExposeAsDocumentMedia']);
        $t->same(null, $basic['byteLength']);
        $t->same(strlen($basicModuleXml), $basic['storedByteLength']);
        $t->same('script-package-bytes-blocked', $basic['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $basic['reviewPolicy']);
        $t->same([], $basic['issues']);
        $t->same([], $basic['diagnostics']);

        $javascript = $partsByPart['Scripts/review-link.js'];
        $t->same('text/javascript; charset=UTF-8', $javascript['mediaType']);
        $t->same('text/javascript', $javascript['mediaTypeBase']);
        $t->same('javascript-script', $javascript['kind']);
        $t->same(true, $javascript['valid']);
        $t->same(strlen($javaScript), $javascript['storedByteLength']);
        $t->same(null, $javascript['byteLength']);

        $missing = $partsByPart['Scripts/missing.js'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-script-package-missing-part'], $missing['issues']);
        $t->same('script-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $partsByPart['Scripts/encrypted.py'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(false, $encrypted['valid']);
        $t->same(strlen($encryptedPython), $encrypted['storedByteLength']);
        $t->same(null, $encrypted['byteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-script-package-encrypted-part'], $encrypted['issues']);

        $orphan = $partsByPart['Scripts/orphan.rb'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(false, $orphan['valid']);
        $t->same('ruby-script', $orphan['kind']);
        $t->same(strlen($orphanRuby), $orphan['storedByteLength']);
        $t->same(null, $orphan['byteLength']);
        $t->same('script-package-bytes-blocked', $orphan['byteExposurePolicy']);
        $t->same(['odf-script-package-undeclared-part'], $orphan['issues']);

        $t->same(['Basic/Standard/Review.xml', 'Scripts/encrypted.py', 'Scripts/missing.js', 'Scripts/orphan.rb', 'Scripts/review-link.js'], array_column($scripts['parts'], 'part'));
        $t->same(['Basic/Standard/Review.xml', 'Scripts/review-link.js', 'Scripts/missing.js'], array_column($scripts['references'], 'part'));
        $t->same([], array_column($result['media'], 'part'), 'script package payloads stay out of document media handoff');
    },
];
