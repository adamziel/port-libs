<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source record creator host systems for identity handoff' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_creator_host_system_fixture_parts(),
            'docx zip source creator host review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $sourceRecords = $package['zipPackage']['sourceRecords'];
        $identity = $package['packageIdentity'];
        $repeatIdentity = (new DocxOpenXmlReader())
            ->readZipPackage(ZipPackage::fromParts(
                docx_zip_source_record_creator_host_system_fixture_parts(),
                'docx zip source creator host review'
            ))
            ->attr('docx')['packageProvenance']['packageIdentity'];
        $changedParts = docx_zip_source_record_creator_host_system_fixture_parts();
        foreach ($changedParts as &$part) {
            if (($part['name'] ?? null) === 'word/document.xml') {
                $part['creatorHostSystem'] = 3;
            }
        }
        unset($part);
        $changedIdentity = (new DocxOpenXmlReader())
            ->readZipPackage(ZipPackage::fromParts($changedParts, 'docx zip source creator host review'))
            ->attr('docx')['packageProvenance']['packageIdentity'];
        $expectedCounts = [
            'ms-dos-fat' => 1,
            'unix' => 1,
            'windows-ntfs' => 1,
        ];
        $expectedEntryNames = [
            'ms-dos-fat' => ['[Content_Types].xml'],
            'unix' => ['_rels/.rels'],
            'windows-ntfs' => ['word/document.xml'],
        ];

        $t->same('ZIP creator host systems.', $document->children[0]->attr('text'));
        $t->same(3, $sourceRecords['creatorHostSystemEntryCount']);
        $t->same($expectedCounts, $sourceRecords['creatorHostSystemCounts']);
        $t->same($expectedEntryNames, $sourceRecords['entryNamesByCreatorHostSystem']);
        $t->same($sourceRecords['creatorHostSystemCounts'], $summary['zipSourceCreatorHostSystemCounts']);
        $t->same($sourceRecords['entryNamesByCreatorHostSystem'], $summary['zipSourceEntryNamesByCreatorHostSystem']);
        $t->same($summary['zipSourceCreatorHostSystemCounts'], $identity['zipSourceCreatorHostSystemCounts']);
        $t->same(
            $summary['zipSourceEntryNamesByCreatorHostSystem'],
            $identity['zipSourceEntryNamesByCreatorHostSystem']
        );
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true(
            $identity['identitySha256'] !== $changedIdentity['identitySha256'],
            'package identity must include ZIP source creator-host metadata even when payload bytes match'
        );
        $t->same(false, array_key_exists('contents', $identity['packageEntries'][0]));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, creatorHostSystem:int}>
 */
function docx_zip_source_record_creator_host_system_fixture_parts(): array
{
    return [
        [
            'name' => '[Content_Types].xml',
            'compressionMethod' => 0,
            'creatorHostSystem' => 0,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        ],
        [
            'name' => '_rels/.rels',
            'compressionMethod' => 0,
            'creatorHostSystem' => 3,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/document.xml',
            'compressionMethod' => 0,
            'creatorHostSystem' => 10,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP creator host systems.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
    ];
}
