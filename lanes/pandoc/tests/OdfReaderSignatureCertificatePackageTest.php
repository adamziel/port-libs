<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$certificateBytes = 'SIGNING-CERTIFICATE-DER';
$encryptedCertificateBytes = 'ENCRYPTED-CERTIFICATE-DER';
$invalidBytes = 'CERTIFICATE-PREVIEW-PNG';
$orphanCertificateBytes = 'ORPHAN-CERTIFICATE-DER';
$certificateSize = strlen($certificateBytes);
$encryptedCertificateSize = strlen($encryptedCertificateBytes);
$invalidSize = strlen($invalidBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="META-INF/certificates/signing.cer" manifest:media-type="application/pkix-cert" manifest:size="{$certificateSize}"/>
  <manifest:file-entry manifest:full-path="META-INF/certificates/missing.cer" manifest:media-type="application/pkix-cert"/>
  <manifest:file-entry manifest:full-path="META-INF/certificates/encrypted.cer" manifest:media-type="application/x-x509-ca-cert" manifest:size="{$encryptedCertificateSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="certificate-checksum"/>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="META-INF/certificates/preview.png" manifest:media-type="image/png" manifest:size="{$invalidSize}"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Signature certificate package.</text:p>
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
    <dc:title>Signature Certificate Package</dc:title>
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
    ['name' => 'META-INF/certificates/signing.cer', 'data' => $certificateBytes, 'compressionMethod' => 0],
    ['name' => 'META-INF/certificates/encrypted.cer', 'data' => $encryptedCertificateBytes, 'compressionMethod' => 0],
    ['name' => 'META-INF/certificates/preview.png', 'data' => $invalidBytes, 'compressionMethod' => 0],
    ['name' => 'META-INF/certificates/orphan.cer', 'data' => $orphanCertificateBytes, 'compressionMethod' => 0],
], 'odt signature certificate package');

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

$expectedCertificateMediaTypes = [
    'application/pkix-cert',
    'application/x-x509-ca-cert',
    'application/x-x509-user-cert',
    'application/x-pem-file',
    'application/pem-certificate-chain',
    'application/octet-stream',
    'application/binary',
];

return [
    'classifies ODT package certificate blobs as metadata-only signature sidecars' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $certificateBytes,
        $encryptedCertificateBytes,
        $invalidBytes,
        $orphanCertificateBytes,
        $expectedCertificateMediaTypes
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $signatures = $result['packageSignatures'];
        $items = $indexBy($signatures['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $inventory = $provenance['parts'];

        $t->same($signatures, $result['document']->attr('packageSignatures'));
        $t->same($signatures, $result['metadata']['odfPackageSignatures']);
        $t->same($signatures, $result['importReport']['packageSignatures']);
        $t->same(5, $signatures['count']);
        $t->same(3, $signatures['readableCount']);
        $t->same(4, $signatures['declaredCount']);
        $t->same(1, $signatures['undeclaredCount']);
        $t->same(1, $signatures['missingCount']);
        $t->same(1, $signatures['encryptedCount']);
        $t->same(1, $signatures['invalidMediaTypeCount']);
        $t->same(4, $signatures['issueCount']);
        $t->same([
            'odf-signature-encrypted-package-part',
            'odf-signature-invalid-media-type',
            'odf-signature-missing-package-part',
            'odf-signature-undeclared-package-part',
        ], $signatures['issueCodes']);

        $certificate = $items['META-INF/certificates/signing.cer'];
        $t->same('signature-certificate', $certificate['kind']);
        $t->same($expectedCertificateMediaTypes, $certificate['expectedMediaTypes']);
        $t->same('application/pkix-cert', $certificate['mediaType']);
        $t->same(true, $certificate['declared']);
        $t->same(true, $certificate['valid']);
        $t->same(strlen($certificateBytes), $certificate['byteLength']);
        $t->same(sprintf('%08x', crc32($certificateBytes)), $certificate['crc32']);
        $t->same(false, $certificate['canExposeAsDocumentMedia']);
        $t->same('signature-package-bytes-blocked', $certificate['byteExposurePolicy']);
        $t->same('package-signature-metadata-only', $certificate['reviewPolicy']);
        $t->same([], $certificate['issues']);

        $missing = $items['META-INF/certificates/missing.cer'];
        $t->same('signature-certificate', $missing['kind']);
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-signature-missing-package-part'], $missing['issues']);

        $encrypted = $items['META-INF/certificates/encrypted.cer'];
        $t->same('signature-certificate', $encrypted['kind']);
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedCertificateBytes), $encrypted['storedByteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-signature-encrypted-package-part'], $encrypted['issues']);

        $invalid = $items['META-INF/certificates/preview.png'];
        $t->same('signature-certificate', $invalid['kind']);
        $t->same('image/png', $invalid['mediaTypeBase']);
        $t->same(false, $invalid['valid']);
        $t->same(strlen($invalidBytes), $invalid['byteLength']);
        $t->same('signature-package-bytes-blocked', $invalid['byteExposurePolicy']);
        $t->same(['odf-signature-invalid-media-type'], $invalid['issues']);

        $orphan = $items['META-INF/certificates/orphan.cer'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(null, $orphan['mediaType']);
        $t->same(strlen($orphanCertificateBytes), $orphan['byteLength']);
        $t->same('signature-package-bytes-blocked', $orphan['byteExposurePolicy']);
        $t->same(['odf-signature-undeclared-package-part'], $orphan['issues']);

        $manifestCertificate = $manifestByPart['META-INF/certificates/signing.cer'];
        $t->same(true, $manifestCertificate['signaturePackagePart']);
        $t->same(false, $manifestCertificate['metaInfSidecarPackagePart']);
        $t->same(false, $manifestCertificate['canExposeBytes']);
        $t->same(null, $manifestCertificate['byteLength']);
        $t->same(strlen($certificateBytes), $manifestCertificate['storedByteLength']);
        $t->same(null, $manifestCertificate['byteSha256']);
        $t->same('signature-package-bytes-blocked', $manifestCertificate['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(0, $result['packageMetaInfSidecars']['count']);
        $t->same(0, $result['signatureMetadata']['count']);
        $t->same(4, $provenance['packageSignaturePartCount']);
        $t->same(4, $provenance['roleCounts']['package-signature']);
        $t->same(1, $provenance['undeclaredRoleCounts']['package-signature']);
        $t->same(['package-signature', 'manifest-declared'], $inventory['META-INF/certificates/signing.cer']['roles']);
        $t->same(['package-signature', 'undeclared-package-entry'], $inventory['META-INF/certificates/orphan.cer']['roles']);
        $t->same(true, $inventory['META-INF/certificates/preview.png']['signaturePackagePart']);
        $t->same(false, in_array('media-resource', $inventory['META-INF/certificates/preview.png']['roles'], true));
        $t->same(5, $provenance['mediaResources']['manifestDeclaredCount']);
        $t->same(1, $provenance['mediaResources']['mediaResourceCount']);
        $t->same(4, $provenance['mediaResources']['packageRolePrecedenceCount']);
        $t->same([
            'META-INF/certificates/signing.cer',
            'META-INF/certificates/missing.cer',
            'META-INF/certificates/encrypted.cer',
            'META-INF/certificates/preview.png',
        ], array_column($provenance['mediaResources']['packageRolePrecedenceItems'], 'part'));
        $t->same([
            'odf-media-resource-missing-package-part' => 1,
            'odf-media-resource-package-role-precedence' => 4,
        ], $provenance['mediaResources']['issueCodeCounts']);
        $t->same(['package-signature'], $provenance['mediaResources']['packageRolePrecedenceItems'][0]['packageRolePrecedence']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactSignatures = $compactSummary['packageSignatures'];
        $compactItems = $indexBy($compactSignatures['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactInventory = $compactSummary['packageInventory'];

        $t->same(5, $compactSignatures['count']);
        $t->same(3, $compactSignatures['readableCount']);
        $t->same(4, $compactSignatures['declaredCount']);
        $t->same(1, $compactSignatures['undeclaredCount']);
        $t->same(1, $compactSignatures['missingCount']);
        $t->same(1, $compactSignatures['encryptedCount']);
        $t->same(1, $compactSignatures['invalidMediaTypeCount']);
        $t->same($signatures['issueCodes'], $compactSignatures['issueCodes']);
        $t->same('signature-certificate', $compactItems['META-INF/certificates/signing.cer']['kind']);
        $t->same($expectedCertificateMediaTypes, $compactItems['META-INF/certificates/signing.cer']['expectedMediaTypes']);
        $t->same(strlen($certificateBytes), $compactItems['META-INF/certificates/signing.cer']['byteLength']);
        $t->same(['odf-signature-missing-package-part'], $compactItems['META-INF/certificates/missing.cer']['issues']);
        $t->same(['odf-signature-encrypted-package-part'], $compactItems['META-INF/certificates/encrypted.cer']['issues']);
        $t->same(['odf-signature-invalid-media-type'], $compactItems['META-INF/certificates/preview.png']['issues']);
        $t->same(['odf-signature-undeclared-package-part'], $compactItems['META-INF/certificates/orphan.cer']['issues']);
        $t->same('signature-package-bytes-blocked', $compactItems['META-INF/certificates/signing.cer']['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(0, $compactSummary['packageMetaInfSidecars']['count']);
        $t->same(true, $reviewByPath['META-INF/certificates/signing.cer']['signaturePackagePart']);
        $t->same(false, $reviewByPath['META-INF/certificates/signing.cer']['metaInfSidecarPackagePart']);
        $t->same('signature', $reviewByPath['META-INF/certificates/signing.cer']['manifestMediaFamily']);
        $t->same(5, $compactSummary['manifestReview']['mediaResources']['manifestDeclaredCount']);
        $t->same(1, $compactSummary['manifestReview']['mediaResources']['mediaResourceCount']);
        $t->same(4, $compactSummary['manifestReview']['mediaResources']['packageRolePrecedenceCount']);
        $t->same([
            'META-INF/certificates/signing.cer',
            'META-INF/certificates/missing.cer',
            'META-INF/certificates/encrypted.cer',
            'META-INF/certificates/preview.png',
        ], array_column($compactSummary['manifestReview']['mediaResources']['packageRolePrecedenceItems'], 'part'));
        $t->same($provenance['mediaResources']['issueCodeCounts'], $compactSummary['manifestReview']['mediaResources']['issueCodeCounts']);
        $t->same(4, $compactInventory['packageSignaturePartCount']);
        $t->same(4, $compactInventory['roleCounts']['package-signature']);
        $t->same(1, $compactInventory['undeclaredRoleCounts']['package-signature']);
        $t->same(['package-signature', 'manifest-declared'], $compactInventory['parts']['META-INF/certificates/signing.cer']['roles']);
        $t->same(['package-signature', 'undeclared-package-entry'], $compactInventory['parts']['META-INF/certificates/orphan.cer']['roles']);
        $t->same(false, $compactInventory['parts']['META-INF/certificates/preview.png']['canExposeBytes']);
    },
];
