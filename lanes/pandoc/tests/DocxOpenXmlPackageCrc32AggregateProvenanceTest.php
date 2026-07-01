<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'carries DOCX package CRC32 aggregate provenance through summary and identity' => static function (TestRunner $t): void {
        $parts = docx_package_crc32_aggregate_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $summary = $docx['packageProvenance']['summary'];
        $identity = $docx['packageIdentity'];
        $imageCrc32 = sprintf('%08x', crc32($parts['word/media/reused-a.png']));
        $customXmlCrc32 = sprintf('%08x', crc32($parts['customXml/item1.xml']));
        $embeddedObjectCrc32 = sprintf('%08x', crc32($parts['word/embeddings/object1.bin']));

        $t->same(count($parts), $summary['packageCrc32EntryCount']);
        $t->same(count($parts) - 3, $summary['packageCrc32Count']);
        $t->same(3, $summary['packageDuplicateCrc32Count']);
        $t->same(6, $summary['packageDuplicateCrc32EntryCount']);
        $t->same(2, $summary['packageCrc32Counts'][$imageCrc32]);
        $t->same(2, $summary['packageCrc32Counts'][$customXmlCrc32]);
        $t->same(2, $summary['packageCrc32Counts'][$embeddedObjectCrc32]);
        $t->same(2 * strlen($parts['word/media/reused-a.png']), $summary['packageCrc32ByteLengths'][$imageCrc32]);
        $t->same($summary['packageCrc32ByteLengths'][$imageCrc32], $summary['packageCrc32CompressedByteLengths'][$imageCrc32]);
        $t->same(0, $summary['packageCrc32SourceRecordBytes'][$imageCrc32]);
        $t->same([
            'word/media/reused-a.png',
            'word/media/reused-b.png',
        ], $summary['entryNamesByPackageCrc32'][$imageCrc32]);

        $duplicates = docx_package_crc32_duplicate_summaries_by_crc32($summary['packageDuplicateCrc32Summaries']);
        $t->true(isset($duplicates[$customXmlCrc32]));
        $t->true(isset($duplicates[$embeddedObjectCrc32]));
        $t->true(isset($duplicates[$imageCrc32]));

        $imageSummary = $duplicates[$imageCrc32];
        $t->same(2, $imageSummary['entryCount']);
        $t->same(2, $imageSummary['partCount']);
        $t->same([
            'word/media/reused-a.png',
            'word/media/reused-b.png',
        ], $imageSummary['entryNames']);
        $t->same($imageSummary['entryNames'], $imageSummary['partNames']);
        $t->same(0, $imageSummary['exposableEntryCount']);
        $t->same(2, $imageSummary['blockedEntryCount']);
        $t->same(0, $imageSummary['sourceRecordBytes']);
        $t->same(['image/png' => 2], $imageSummary['contentTypeBaseCounts']);
        $t->same(['default' => 2], $imageSummary['contentTypeSourceCounts']);
        $t->same(['(missing)' => 2], $imageSummary['compressionMethodCounts']);
        $t->same(['docx-package-part-bytes-blocked' => 2], $imageSummary['byteExposurePolicyCounts']);
        $t->same(2, $imageSummary['roleCounts']['document-relationship-target']);
        $t->true(!array_key_exists('contents', $imageSummary));

        $customXmlSummary = $duplicates[$customXmlCrc32];
        $t->same(['application/xml' => 2], $customXmlSummary['contentTypeBaseCounts']);
        $t->same(2, $customXmlSummary['roleCounts']['custom-xml-part']);
        $t->same([
            'customXml/item1.xml',
            'customXml/item2.xml',
        ], $customXmlSummary['entryNames']);

        $embeddedObjectSummary = $duplicates[$embeddedObjectCrc32];
        $t->same(['application/octet-stream' => 2], $embeddedObjectSummary['contentTypeBaseCounts']);
        $t->same(2, $embeddedObjectSummary['roleCounts']['embedded-object']);
        $t->same([
            'word/embeddings/object1.bin',
            'word/embeddings/object2.bin',
        ], $embeddedObjectSummary['entryNames']);

        $t->same($summary['packageCrc32EntryCount'], $identity['packageCrc32EntryCount']);
        $t->same($summary['packageCrc32Count'], $identity['packageCrc32Count']);
        $t->same($summary['packageDuplicateCrc32Count'], $identity['packageDuplicateCrc32Count']);
        $t->same($summary['packageDuplicateCrc32EntryCount'], $identity['packageDuplicateCrc32EntryCount']);
        $t->same($summary['packageCrc32Counts'], $identity['packageCrc32Counts']);
        $t->same($summary['packageCrc32ByteLengths'], $identity['packageCrc32ByteLengths']);
        $t->same($summary['packageCrc32CompressedByteLengths'], $identity['packageCrc32CompressedByteLengths']);
        $t->same($summary['packageCrc32SourceRecordBytes'], $identity['packageCrc32SourceRecordBytes']);
        $t->same($summary['entryNamesByPackageCrc32'], $identity['entryNamesByPackageCrc32']);
        $t->same($summary['packageDuplicateCrc32Summaries'], $identity['packageDuplicateCrc32Summaries']);
        $t->same($identity['packageCrc32EntryCount'], $summary['packageIdentityPackageCrc32EntryCount']);
        $t->same($identity['packageCrc32Count'], $summary['packageIdentityPackageCrc32Count']);
        $t->same($identity['packageDuplicateCrc32Count'], $summary['packageIdentityPackageDuplicateCrc32Count']);
        $t->same($identity['packageDuplicateCrc32EntryCount'], $summary['packageIdentityPackageDuplicateCrc32EntryCount']);

        $repeatIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_package_crc32_aggregate_fixture_parts())
            ->attr('docx')['packageIdentity'];
        $changedParts = $parts;
        $changedParts['word/media/reused-b.png'] = 'changed duplicate image payload';
        $changedIdentity = (new DocxOpenXmlReader())
            ->readPackage($changedParts)
            ->attr('docx')['packageIdentity'];
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_crc32_aggregate_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImageA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/reused-a.png"/>
  <Relationship Id="rImageB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/reused-b.png"/>
  <Relationship Id="rCustomXmlA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml"/>
  <Relationship Id="rCustomXmlB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item2.xml"/>
  <Relationship Id="rObjectA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/object1.bin"/>
  <Relationship Id="rObjectB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/object2.bin"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>CRC32 package aggregate fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties">
  <cp:revision>1</cp:revision>
</cp:coreProperties>
XML,
        'word/media/reused-a.png' => 'shared image payload for crc32 grouping',
        'word/media/reused-b.png' => 'shared image payload for crc32 grouping',
        'customXml/item1.xml' => '<review><value>shared custom XML payload</value></review>',
        'customXml/item2.xml' => '<review><value>shared custom XML payload</value></review>',
        'word/embeddings/object1.bin' => 'shared embedded object payload for crc32 grouping',
        'word/embeddings/object2.bin' => 'shared embedded object payload for crc32 grouping',
        'customXml/solo.xml' => '<review><value>unique payload</value></review>',
    ];
}

/**
 * @param list<array<string, mixed>> $summaries
 * @return array<string, array<string, mixed>>
 */
function docx_package_crc32_duplicate_summaries_by_crc32(array $summaries): array
{
    $byCrc32 = [];
    foreach ($summaries as $summary) {
        $byCrc32[(string) ($summary['crc32'] ?? '')] = $summary;
    }

    return $byCrc32;
}
