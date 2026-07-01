<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Creator host version package identity.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="CreatorHostVersionBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Creator Host Version Package Identity</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromString(odf_zip_creator_host_version_bytes([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'method' => 0, 'versionMadeBy' => 0x0314],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'method' => 0, 'versionMadeBy' => 0x0314],
    ['name' => 'content.xml', 'data' => $contentXml, 'method' => 8, 'versionNeededToExtract' => 20, 'versionMadeBy' => 0x030a],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'method' => 8, 'versionMadeBy' => 0x0a14],
    ['name' => 'meta.xml', 'data' => $metaXml, 'method' => 0, 'versionMadeBy' => 0x0314],
    ['name' => 'Pictures/hero.png', 'data' => str_repeat('P', 64), 'method' => 0, 'versionMadeBy' => 0x0a14],
]));

return [
    'carries ODT ZIP creator host version rollups into package identity' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedHostSummaries = [
            [
                'id' => 3,
                'name' => 'unix',
                'isKnown' => true,
                'entryCount' => 4,
            ],
            [
                'id' => 10,
                'name' => 'windows-ntfs',
                'isKnown' => true,
                'entryCount' => 2,
            ],
        ];
        $expectedComparisonCounts = [
            'below-needed' => 1,
            'equals-needed' => 5,
            'above-needed' => 0,
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(2, $handoff['creatorHostSystemSummaryCount']);
            $t->same($expectedHostSummaries, $handoff['creatorHostSystemSummaries']);
            $t->same(6, $handoff['knownCreatorHostSystemEntryCount']);
            $t->same(0, $handoff['unknownCreatorHostSystemEntryCount']);
            $t->same(5, $handoff['creatorVersionMeetsNeededEntryCount']);
            $t->same(1, $handoff['creatorVersionBelowNeededEntryCount']);
            $t->same(5, $handoff['creatorVersionEqualNeededEntryCount']);
            $t->same(0, $handoff['creatorVersionAboveNeededEntryCount']);
            $t->same(1, $handoff['creatorVersionBelowNeededKnownHostEntryCount']);
            $t->same(0, $handoff['creatorVersionBelowNeededUnknownHostEntryCount']);
            $t->same($expectedComparisonCounts, $handoff['creatorVersionComparisonCounts']);
            $t->same([], $handoff['unknownCreatorHostSystemEntries']);
            $t->same('content.xml', $handoff['creatorVersionBelowNeededEntries'][0]['name']);
            $t->same(10, $handoff['creatorVersionBelowNeededEntries'][0]['madeByVersion']);
            $t->same(20, $handoff['creatorVersionBelowNeededEntries'][0]['versionNeededToExtract']);
            $t->same('below-needed', $handoff['creatorVersionBelowNeededEntries'][0]['creatorVersionComparison']);
        }

        $t->same($compactInventory['creatorHostSystems']['hostSystems'], $compactIdentity['creatorHostSystemSummaries']);
        $t->same($richProvenance['creatorHostSystems']['hostSystems'], $richIdentity['creatorHostSystemSummaries']);
        $t->same($richIdentity['creatorHostSystemSummaries'], $documentProvenance['creatorHostSystemSummaries']);

        $compactContent = $compactInventory['parts']['content.xml'];
        $richContent = $richProvenance['parts']['content.xml'];
        $t->same(20, $compactContent['versionNeededToExtract']);
        $t->same(20, $richContent['versionNeededToExtract']);
        $t->same('below-needed', $compactContent['creatorVersionComparison']);
        $t->same('below-needed', $richContent['creatorVersionComparison']);
        $t->same(['creator-version-below-version-needed'], $compactContent['creatorHostIssues']);
        $t->same(['creator-version-below-version-needed'], $richContent['creatorHostIssues']);
        $t->same(false, array_key_exists('contents', $richIdentity['creatorVersionBelowNeededEntries'][0]));
    },
];

/**
 * @param list<array{name:string, data:string, method?:int, versionNeededToExtract?:int, versionMadeBy?:int}> $entries
 */
function odf_zip_creator_host_version_bytes(array $entries): string
{
    $body = '';
    $central = '';
    $entryCount = 0;

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'] ?? 0;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if ($compressed === false) {
            throw new \RuntimeException('Unable to deflate ODF ZIP test entry ' . $name);
        }

        $offset = strlen($body);
        $crc32 = (int) sprintf('%u', crc32($data));
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $flags = 0x0800;
        $versionNeeded = $entry['versionNeededToExtract'] ?? 20;
        $versionMadeBy = $entry['versionMadeBy'] ?? 0x0314;

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            $versionNeeded,
            $flags,
            $method,
            0,
            0,
            $crc32,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            $versionMadeBy,
            $versionNeeded,
            $flags,
            $method,
            0,
            0,
            $crc32,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset
        );
        $central .= $name;
        ++$entryCount;
    }

    return $body . $central . pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $entryCount,
        $entryCount,
        strlen($central),
        strlen($body),
        0
    );
}
