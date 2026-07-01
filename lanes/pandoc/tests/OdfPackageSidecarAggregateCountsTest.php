<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/review-state.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="database/script" manifest:media-type="text/plain"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Package sidecar aggregate counts.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$signatureXml = <<<'XML'
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'META-INF/review-state.xml', 'data' => '<review-state/>', 'compressionMethod' => 0],
    ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml, 'compressionMethod' => 0],
    ['name' => 'database/script', 'data' => 'CREATE TABLE package_sidecar(id INTEGER);', 'compressionMethod' => 0],
], 'odf sidecar aggregate counts');

return [
    'counts META-INF and database package sidecars in compact and rich identities' => static function (TestRunner $t) use ($buildPackage): void {
        $package = $buildPackage();
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactParts = $compactInventory['parts'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $richParts = $richProvenance['parts'];

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
        ] as $label => $summary) {
            $t->same(1, $summary['metaInfSidecarPackagePartCount'], "{$label} meta-inf sidecar count");
            $t->same(1, $summary['databasePackagePartCount'], "{$label} database sidecar count");
            $t->same(1, $summary['roleCounts']['meta-inf-sidecar'], "{$label} meta-inf role count");
            $t->same(1, $summary['roleCounts']['database-package'], "{$label} database role count");
            $t->same(1, $summary['roleCounts']['package-signature'], "{$label} signature remains separate");
        }

        foreach ([
            'compact' => $compactParts,
            'rich' => $richParts,
        ] as $label => $parts) {
            $t->same(true, $parts['META-INF/review-state.xml']['metaInfSidecarPackagePart'], "{$label} meta-inf flag");
            $t->same(false, $parts['META-INF/review-state.xml']['signaturePackagePart'], "{$label} meta-inf is not signature");
            $t->same(false, $parts['META-INF/review-state.xml']['canExposeBytes'], "{$label} meta-inf bytes blocked");
            $t->same('meta-inf-sidecar-package-bytes-blocked', $parts['META-INF/review-state.xml']['byteExposurePolicy'], "{$label} meta-inf policy");
            $t->same(true, $parts['database/script']['databasePackagePart'], "{$label} database flag");
            $t->same(false, $parts['database/script']['canExposeBytes'], "{$label} database bytes blocked");
            $t->same('database-package-bytes-blocked', $parts['database/script']['byteExposurePolicy'], "{$label} database policy");
            $t->same(true, $parts['META-INF/documentsignatures.xml']['signaturePackagePart'], "{$label} signature flag");
            $t->same(false, $parts['META-INF/documentsignatures.xml']['metaInfSidecarPackagePart'], "{$label} signature excluded from meta-inf count");
            $t->same('signature-package-bytes-blocked', $parts['META-INF/documentsignatures.xml']['byteExposurePolicy'], "{$label} signature policy");
        }

        $t->same(1, $compactSummary['packageByteHandoff']['requestCount']);
        $t->same(1, $richProvenance['packageByteHandoff']['requestCount']);
        $t->same(['content.xml'], array_column($compactSummary['packageByteHandoff']['handoffEntries'], 'name'));
        $t->same(['content.xml'], array_column($richProvenance['packageByteHandoff']['handoffEntries'], 'name'));
    },
];
