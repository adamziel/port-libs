<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$imagePayload = str_repeat('PNG-DUPLICATE-PAYLOAD', 12);
$scriptPayload = "function reviewPackageCrc32() {\n  return true;\n}\n";
$configurationPayload = <<<'XML'
<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="duplicate-crc32"/>
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
      <text:p>ODF package CRC32 aggregate provenance.</text:p>
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
    <style:style style:name="Crc32Body" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>ODF package CRC32 aggregate provenance</dc:title>
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
    ], 'odf package crc32 aggregate provenance');
};

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$crc32 = static fn (string $payload): string => sprintf('%08x', crc32($payload));

return [
    'summarizes duplicate package CRC32 values across ODF provenance handoffs' => static function (TestRunner $t) use ($buildPackage, $indexBy, $crc32, $imagePayload, $scriptPayload, $configurationPayload): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $imageCrc32 = $crc32($imagePayload);
        $scriptCrc32 = $crc32($scriptPayload);
        $configurationCrc32 = $crc32($configurationPayload);
        $crcFields = [
            'packageCrc32EntryCount',
            'packageCrc32Count',
            'packageDuplicateCrc32Count',
            'packageDuplicateCrc32EntryCount',
            'packageCrc32Counts',
            'packageCrc32ByteLengths',
            'packageCrc32CompressedByteLengths',
            'packageCrc32SourceRecordBytes',
            'entryNamesByPackageCrc32',
            'packageCrc32Summaries',
            'packageDuplicateCrc32Summaries',
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(11, $handoff['packageCrc32EntryCount']);
            $t->same(8, $handoff['packageCrc32Count']);
            $t->same(3, $handoff['packageDuplicateCrc32Count']);
            $t->same(6, $handoff['packageDuplicateCrc32EntryCount']);
            $t->same(2, $handoff['packageCrc32Counts'][$imageCrc32]);
            $t->same(2, $handoff['packageCrc32Counts'][$scriptCrc32]);
            $t->same(2, $handoff['packageCrc32Counts'][$configurationCrc32]);
            $t->same(['Pictures/a.png', 'Pictures/b.png'], $handoff['entryNamesByPackageCrc32'][$imageCrc32]);
            $t->same(['Scripts/a.js', 'Scripts/b.js'], $handoff['entryNamesByPackageCrc32'][$scriptCrc32]);
            $t->same(['Configurations2/accelerator/a.xml', 'Configurations2/accelerator/b.xml'], $handoff['entryNamesByPackageCrc32'][$configurationCrc32]);
        }

        foreach ($crcFields as $field) {
            $t->same($compactInventory[$field], $compactIdentity[$field], "{$field} compact identity handoff");
            $t->same($richProvenance[$field], $richIdentity[$field], "{$field} rich identity handoff");
            $t->same($richProvenance[$field], $documentProvenance[$field], "{$field} document provenance handoff");
        }

        $compactSummaries = $indexBy($compactInventory['packageDuplicateCrc32Summaries'], 'crc32');
        $richSummaries = $indexBy($richProvenance['packageDuplicateCrc32Summaries'], 'crc32');
        foreach ([$compactSummaries, $richSummaries] as $summaries) {
            $image = $summaries[$imageCrc32];
            $script = $summaries[$scriptCrc32];
            $configuration = $summaries[$configurationCrc32];

            $t->same(['0' => 1, '8' => 1], $image['compressionMethodCounts']);
            $t->same(2, $image['exposableEntryCount']);
            $t->same(0, $image['blockedEntryCount']);
            $t->same(2, $image['manifestDeclaredEntryCount']);
            $t->same(2, $image['roleCounts']['media-resource']);
            $t->same(['package-bytes-exposable' => 2], $image['byteExposurePolicyCounts']);
            $t->same(['image/png' => 2], $image['manifestMediaTypeBaseCounts']);

            $t->same(['8' => 2], $script['compressionMethodCounts']);
            $t->same(0, $script['exposableEntryCount']);
            $t->same(2, $script['blockedEntryCount']);
            $t->same(2, $script['roleCounts']['script-package']);
            $t->same(['script-package-bytes-blocked' => 2], $script['byteExposurePolicyCounts']);

            $t->same(['0' => 2], $configuration['compressionMethodCounts']);
            $t->same(0, $configuration['exposableEntryCount']);
            $t->same(2, $configuration['blockedEntryCount']);
            $t->same(2, $configuration['roleCounts']['configuration-package']);
            $t->same(['configuration-package-bytes-blocked' => 2], $configuration['byteExposurePolicyCounts']);
            $t->same(['text/xml' => 2], $configuration['manifestMediaTypeBaseCounts']);
        }
    },
];
