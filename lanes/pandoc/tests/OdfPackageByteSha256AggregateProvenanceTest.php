<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$imagePayload = str_repeat('PNG-DUPLICATE-PAYLOAD', 12);
$scriptPayload = "function reviewPackageSha256() {\n  return true;\n}\n";
$configurationPayload = <<<'XML'
<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="duplicate-sha256"/>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/a.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/b.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Scripts/a.js" manifest:media-type="application/javascript"/>
  <manifest:file-entry manifest:full-path="Scripts/b.js" manifest:media-type="application/javascript"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/a.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/b.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>ODF package SHA-256 aggregate provenance.</text:p>
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
    <style:style style:name="Sha256Body" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>ODF package SHA-256 aggregate provenance</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static function () use ($manifestXml, $contentXml, $stylesXml, $metaXml, $imagePayload, $scriptPayload, $configurationPayload): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
        ['name' => 'Pictures/a.png', 'data' => $imagePayload, 'compressionMethod' => 0],
        ['name' => 'Pictures/b.png', 'data' => $imagePayload, 'compressionMethod' => 8],
        ['name' => 'Scripts/a.js', 'data' => $scriptPayload, 'compressionMethod' => 8],
        ['name' => 'Scripts/b.js', 'data' => $scriptPayload, 'compressionMethod' => 8],
        ['name' => 'Configurations2/accelerator/a.xml', 'data' => $configurationPayload, 'compressionMethod' => 0],
        ['name' => 'Configurations2/accelerator/b.xml', 'data' => $configurationPayload, 'compressionMethod' => 0],
    ], 'odf package sha256 aggregate provenance');
};

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$sha256 = static fn (string $payload): string => hash('sha256', $payload);

return [
    'summarizes duplicate package SHA-256 values across ODF provenance handoffs' => static function (TestRunner $t) use ($buildPackage, $indexBy, $sha256, $imagePayload, $scriptPayload, $configurationPayload): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $imageSha256 = $sha256($imagePayload);
        $scriptSha256 = $sha256($scriptPayload);
        $configurationSha256 = $sha256($configurationPayload);
        $sha256Fields = [
            'packageByteSha256EntryCount',
            'packageByteSha256Count',
            'packageDuplicateByteSha256Count',
            'packageDuplicateByteSha256EntryCount',
            'packageByteSha256Counts',
            'packageByteSha256ByteLengths',
            'packageByteSha256CompressedByteLengths',
            'packageByteSha256SourceRecordBytes',
            'entryNamesByPackageByteSha256',
            'packageByteSha256Summaries',
            'packageDuplicateByteSha256Summaries',
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(5, $handoff['packageByteSha256EntryCount']);
            $t->same(4, $handoff['packageByteSha256Count']);
            $t->same(1, $handoff['packageDuplicateByteSha256Count']);
            $t->same(2, $handoff['packageDuplicateByteSha256EntryCount']);
            $t->same(2, $handoff['packageByteSha256Counts'][$imageSha256]);
            $t->same(null, $handoff['packageByteSha256Counts'][$scriptSha256] ?? null);
            $t->same(null, $handoff['packageByteSha256Counts'][$configurationSha256] ?? null);
            $t->same(['Pictures/a.png', 'Pictures/b.png'], $handoff['entryNamesByPackageByteSha256'][$imageSha256]);
            $t->same(null, $handoff['entryNamesByPackageByteSha256'][$scriptSha256] ?? null);
            $t->same(null, $handoff['entryNamesByPackageByteSha256'][$configurationSha256] ?? null);
        }

        foreach ($sha256Fields as $field) {
            $t->same($compactInventory[$field], $compactIdentity[$field], "{$field} compact identity handoff");
            $t->same($richProvenance[$field], $richIdentity[$field], "{$field} rich identity handoff");
            $t->same($richProvenance[$field], $documentProvenance[$field], "{$field} document provenance handoff");
        }

        $compactSummaries = $indexBy($compactInventory['packageDuplicateByteSha256Summaries'], 'byteSha256');
        $richSummaries = $indexBy($richProvenance['packageDuplicateByteSha256Summaries'], 'byteSha256');
        foreach ([$compactSummaries, $richSummaries] as $summaries) {
            $image = $summaries[$imageSha256];

            $t->same(['0' => 1, '8' => 1], $image['compressionMethodCounts']);
            $t->same(2, $image['exposableEntryCount']);
            $t->same(0, $image['blockedEntryCount']);
            $t->same(2, $image['manifestDeclaredEntryCount']);
            $t->same(2, $image['roleCounts']['media-resource']);
            $t->same(['package-bytes-exposable' => 2], $image['byteExposurePolicyCounts']);
            $t->same(['image/png' => 2], $image['manifestMediaTypeBaseCounts']);
            $t->same(false, isset($summaries[$scriptSha256]));
            $t->same(false, isset($summaries[$configurationSha256]));
        }
    },
];
