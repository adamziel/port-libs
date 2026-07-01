<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by creator host system' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_creator_host_system_fixture_parts(),
            'docx creator host review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $hostSystems = docx_zip_source_record_creator_host_index_by(
            $summary['partZipSourceRecordCreatorHostSystems'],
            'madeByHostSystemKey'
        );
        $expectedCounts = docx_zip_source_record_creator_host_counts($inventory);

        $t->same('Creator host buckets.', $document->children[0]->attr('text'));
        $t->same([0, 3, 10], array_column($summary['partZipSourceRecordCreatorHostSystems'], 'madeByHostSystem'));
        $t->same(count($expectedCounts), $summary['partZipSourceRecordCreatorHostSystemCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordCreatorHostSystemCounts']);
        $t->same(
            docx_zip_source_record_creator_host_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordCreatorHostSystemBytes']
        );
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipSourceRecordCreatorHostSystemCounts']));
        $t->same($summary['partZipSourceRecordPartCount'], $summary['partZipSourceRecordKnownCreatorHostSystemPartCount']);
        $t->same(0, $summary['partZipSourceRecordUnknownCreatorHostSystemPartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], $summary['partZipSourceRecordCreatorVersionMeetsNeededPartCount']);
        $t->same(0, $summary['partZipSourceRecordCreatorVersionBelowNeededPartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], $summary['partZipSourceRecordCreatorVersionEqualNeededPartCount']);
        $t->same(0, $summary['partZipSourceRecordCreatorVersionAboveNeededPartCount']);
        $t->same([
            'above-needed' => 0,
            'below-needed' => 0,
            'equals-needed' => $summary['partZipSourceRecordPartCount'],
        ], $summary['partZipSourceRecordCreatorVersionComparisonCounts']);

        $t->same(
            $summary['partZipSourceRecordCreatorHostSystemCount'],
            $identity['partZipSourceRecordCreatorHostSystemCount']
        );
        $t->same(
            $summary['partZipSourceRecordCreatorHostSystemCounts'],
            $identity['partZipSourceRecordCreatorHostSystemCounts']
        );
        $t->same(
            $summary['partZipSourceRecordCreatorHostSystemBytes'],
            $identity['partZipSourceRecordCreatorHostSystemBytes']
        );
        $t->same(
            $summary['partZipSourceRecordKnownCreatorHostSystemPartCount'],
            $identity['partZipSourceRecordKnownCreatorHostSystemPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordUnknownCreatorHostSystemPartCount'],
            $identity['partZipSourceRecordUnknownCreatorHostSystemPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordCreatorVersionComparisonCounts'],
            $identity['partZipSourceRecordCreatorVersionComparisonCounts']
        );
        $t->same(
            $summary['partZipSourceRecordCreatorHostSystems'],
            $identity['partZipSourceRecordCreatorHostSystems']
        );
        $t->same(
            false,
            array_key_exists('contents', $identity['partZipSourceRecordCreatorHostSystems'][0]['largestSourceRecordPart'])
        );

        $fat = $hostSystems['0'];
        $t->same('ms-dos-fat', $fat['madeByHostSystemName']);
        $t->same(true, $fat['isKnown']);
        $t->same(1, $fat['partCount']);
        $t->same(['customXml/fat.bin'], $fat['partNames']);
        $t->same(['customXml/' => 1], $fat['directoryRootCounts']);
        $t->same(['missing' => 1], $fat['contentTypeSourceCounts']);
        $t->same(['package-part' => 1], $fat['roleCounts']);

        $unix = $hostSystems['3'];
        $t->same('unix', $unix['madeByHostSystemName']);
        $t->same(4, $unix['partCount']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ], $unix['partNames']);
        $t->same(['/' => 1, '_rels/' => 1, 'word/' => 2], $unix['directoryRootCounts']);
        $t->same(['default' => 3, 'override' => 1], $unix['contentTypeSourceCounts']);
        $t->same(4, $unix['creatorVersionMeetsNeededPartCount']);
        $t->same([], $unix['unknownCreatorHostSystemParts']);
        $t->same([], $unix['creatorVersionBelowNeededParts']);

        $windows = $hostSystems['10'];
        $t->same('windows-ntfs', $windows['madeByHostSystemName']);
        $t->same(2, $windows['partCount']);
        $t->same(['docProps/core.xml', 'word/media/windows.png'], $windows['partNames']);
        $t->same(['docProps/' => 1, 'word/' => 1], $windows['directoryRootCounts']);
        $t->same(['default' => 1, 'override' => 1], $windows['contentTypeSourceCounts']);
        $t->same(['core-properties' => 1, 'document-relationship-target' => 1, 'root-relationship-target' => 1], $windows['roleCounts']);
        $t->same('word/media/windows.png', $windows['largestSourceRecordPart']['partName']);
        $t->same(10, $windows['largestSourceRecordPart']['madeByHostSystem']);
        $t->same('windows-ntfs', $windows['largestSourceRecordPart']['madeByHostSystemName']);
        $t->same('equals-needed', $windows['largestSourceRecordPart']['creatorVersionComparison']);
        $t->same(true, $windows['largestSourceRecordPart']['creatorVersionMeetsNeeded']);
        $t->same(false, array_key_exists('contents', $windows['largestSourceRecordPart']));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod?:int, creatorHostSystem?:int}>
 */
function docx_zip_source_record_creator_host_system_fixture_parts(): array
{
    return [
        ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        ],
        ['name' => '_rels/.rels', 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        ],
        ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/windows.png"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Creator host buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        ['name' => 'docProps/core.xml', 'creatorHostSystem' => 10, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Creator host buckets</dc:title>
</cp:coreProperties>
XML,
        ],
        [
            'name' => 'word/media/windows.png',
            'creatorHostSystem' => 10,
            'compressionMethod' => 0,
            'data' => str_repeat('W', 512),
        ],
        [
            'name' => 'customXml/fat.bin',
            'creatorHostSystem' => 0,
            'compressionMethod' => 0,
            'data' => 'fat host source record bytes',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_creator_host_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_creator_host_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_creator_host_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_creator_host_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_creator_host_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_creator_host_key(array $part): string
{
    return is_int($part['madeByHostSystem'] ?? null) ? (string) $part['madeByHostSystem'] : '(missing)';
}
