<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$declaredSignatureBytes = '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>';
$orphanSignatureBytes = '<orphan-signatures/>';
$privateNoteBytes = 'PRIVATE-NOTE';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml" manifest:size="__SIGNATURE_SIZE__"/>
  <manifest:file-entry manifest:full-path="META-INF/macrosignatures.xml" manifest:media-type="application/xml"/>
</manifest:manifest>
XML;

$manifestXml = str_replace('__SIGNATURE_SIZE__', (string) strlen($declaredSignatureBytes), $manifestXml);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Signature package policy review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'META-INF/documentsignatures.xml', 'data' => $declaredSignatureBytes, 'compressionMethod' => 0],
    ['name' => 'META-INF/orphan-signatures.xml', 'data' => $orphanSignatureBytes, 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => $privateNoteBytes, 'compressionMethod' => 0],
], 'odt signature package byte policy');

$indexByPart = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $part = $item['part'] ?? null;
        if (is_string($part) && $part !== '') {
            $indexed[$part] = $item;
        }
    }

    return $indexed;
};

return [
    'keeps undeclared ODT signature package byte policies aligned with signature review metadata' => static function (TestRunner $t) use (
        $buildPackage,
        $indexByPart,
        $declaredSignatureBytes,
        $orphanSignatureBytes,
        $privateNoteBytes
    ): void {
        $result = (new OdfReader())->readPackage($buildPackage());
        $signatures = $result['packageSignatures'];
        $signatureItems = $indexByPart($signatures['items']);
        $undeclared = $indexByPart($result['importReport']['manifest']['undeclaredEntries']);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $parts = $provenance['parts'];
        $identity = $provenance['packageIdentity'];

        $t->same($signatures, $result['metadata']['odfPackageSignatures']);
        $t->same($signatures, $result['document']->attr('packageSignatures'));
        $t->same(3, $signatures['count']);
        $t->same(2, $signatures['readableCount']);
        $t->same(2, $signatures['declaredCount']);
        $t->same(1, $signatures['undeclaredCount']);
        $t->same(1, $signatures['missingCount']);
        $t->same('signature-package-bytes-blocked', $signatures['byteExposurePolicy']);
        $t->same('package-signature-metadata-only', $signatures['reviewPolicy']);

        $declared = $signatureItems['META-INF/documentsignatures.xml'];
        $t->same('signature-package-bytes-blocked', $declared['byteExposurePolicy']);
        $t->same(strlen($declaredSignatureBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($declaredSignatureBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);

        $missing = $signatureItems['META-INF/macrosignatures.xml'];
        $t->same('signature-package-bytes-blocked', $missing['byteExposurePolicy']);
        $t->same(false, $missing['exists']);
        $t->same(['odf-signature-missing-package-part'], $missing['issues']);

        $orphan = $signatureItems['META-INF/orphan-signatures.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('signature-package-bytes-blocked', $orphan['byteExposurePolicy']);
        $t->same(strlen($orphanSignatureBytes), $orphan['byteLength']);
        $t->same(['odf-signature-undeclared-package-part'], $orphan['issues']);

        $t->same('signature-package-bytes-blocked', $undeclared['META-INF/orphan-signatures.xml']['byteExposurePolicy']);
        $t->same('undeclared-package-entry-no-bytes', $undeclared['Notes/private.txt']['byteExposurePolicy']);
        $t->same(false, $undeclared['META-INF/orphan-signatures.xml']['canExposeBytes']);
        $t->same(false, $undeclared['Notes/private.txt']['canExposeBytes']);

        $t->same(['package-signature', 'undeclared-package-entry'], $parts['META-INF/orphan-signatures.xml']['roles']);
        $t->same(['undeclared-package-entry'], $parts['Notes/private.txt']['roles']);
        $t->same('signature-package-bytes-blocked', $parts['META-INF/orphan-signatures.xml']['byteExposurePolicy']);
        $t->same('undeclared-package-entry-no-bytes', $parts['Notes/private.txt']['byteExposurePolicy']);
        $t->same(sprintf('%08x', crc32($orphanSignatureBytes)), $parts['META-INF/orphan-signatures.xml']['crc32']);
        $t->same(sprintf('%08x', crc32($privateNoteBytes)), $parts['Notes/private.txt']['crc32']);
        $t->same(null, $parts['META-INF/orphan-signatures.xml']['byteSha256'] ?? null);
        $t->same(null, $parts['Notes/private.txt']['byteSha256'] ?? null);

        $t->same(2, $provenance['packageSignaturePartCount']);
        $t->same(2, $provenance['roleCounts']['package-signature']);
        $t->same(1, $provenance['undeclaredRoleCounts']['package-signature']);
        $t->same(2, $provenance['packagePartByteExposurePolicyCounts']['signature-package-bytes-blocked']);
        $t->same(1, $provenance['packagePartByteExposurePolicyCounts']['undeclared-package-entry-no-bytes']);
        $t->same(2, $identity['packagePartByteExposurePolicyCounts']['signature-package-bytes-blocked']);
        $t->same(1, $identity['packagePartByteExposurePolicyCounts']['undeclared-package-entry-no-bytes']);
        $t->same([], array_column($result['media'], 'part'));
    },
];
