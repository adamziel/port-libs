<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by package inventory role' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(docx_zip_source_record_role_fixture_parts(), 'docx source-role review');
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $roles = docx_zip_source_record_role_index_by($summary['partZipSourceRecordRoles'], 'role');
        $expectedRoleCounts = docx_zip_source_record_role_counts($inventory);

        $t->same(8, $summary['partZipSourceRecordRoleCount']);
        $t->same($expectedRoleCounts, $summary['partZipSourceRecordRoleCounts']);
        $t->same(docx_zip_source_record_role_sums($inventory, 'sourceRecordBytes'), $summary['partZipSourceRecordRoleBytes']);
        $t->same(array_sum($expectedRoleCounts), $summary['partZipSourceRecordRoleOccurrenceCount']);
        $t->same(0, $summary['partZipSourceRecordRoleDataDescriptorOccurrenceCount']);
        $t->same(0, $summary['partZipSourceRecordRoleIssueOccurrenceCount']);
        $t->same(2, $summary['partZipSourceRecordRoleCounts']['relationship-part']);
        $t->same(2, $summary['partZipSourceRecordRoleCounts']['package-part']);
        $t->same(1, $summary['partZipSourceRecordRoleCounts']['office-document']);
        $t->same(1, $summary['partZipSourceRecordRoleCounts']['root-relationship-target']);
        $t->same(1, $summary['partZipSourceRecordRoleCounts']['document-relationship-target']);

        $relationshipPart = $roles['relationship-part'];
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
        ], $relationshipPart['partNames']);
        $t->same(['_rels/' => 1, 'word/' => 1], $relationshipPart['directoryRootCounts']);
        $t->same([8 => 2], $relationshipPart['compressionMethodCounts']);
        $t->same(['default' => 2], $relationshipPart['contentTypeSourceCounts']);
        $t->same(
            docx_zip_source_record_sum_for_role($inventory, 'relationship-part', 'sourceRecordBytes'),
            $relationshipPart['sourceRecordBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_role($inventory, 'relationship-part', 'centralDirectoryRecordBytes'),
            $relationshipPart['centralDirectoryRecordBytes']
        );

        $officeDocument = $roles['office-document'];
        $t->same(['word/document.xml'], $officeDocument['partNames']);
        $t->same(['word/' => 1], $officeDocument['directoryRootCounts']);
        $t->same(['override' => 1], $officeDocument['contentTypeSourceCounts']);
        $t->same('word/document.xml', $officeDocument['largestSourceRecordPart']['partName']);
        $t->same(
            $inventory['word/document.xml']['sourceRecordBytes'],
            $officeDocument['largestSourceRecordPart']['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $officeDocument['largestSourceRecordPart']));

        $packagePart = $roles['package-part'];
        $t->same([
            'customXml/item1.xml',
            'customXml/itemProps1.xml',
        ], $packagePart['partNames']);
        $t->same(['customXml/' => 2], $packagePart['directoryRootCounts']);
        $t->same(['default' => 2], $packagePart['contentTypeSourceCounts']);
        $t->same(
            docx_zip_source_record_sum_for_role($inventory, 'package-part', 'localHeaderRawNameBytes'),
            $packagePart['localHeaderRawNameBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_role($inventory, 'package-part', 'centralDirectoryRawNameBytes'),
            $packagePart['centralDirectoryRawNameBytes']
        );

        $documentTarget = $roles['document-relationship-target'];
        $t->same(['word/media/review.png'], $documentTarget['partNames']);
        $t->same(['word/' => 1], $documentTarget['directoryRootCounts']);
        $t->same(['default' => 1], $documentTarget['contentTypeSourceCounts']);
        $t->same(
            $inventory['word/media/review.png']['sourceRecordBytes'],
            $documentTarget['sourceRecordBytes']
        );
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_role_fixture_parts(): array
{
    return [
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        ],
        ['name' => '_rels/.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        ],
        ['name' => 'word/_rels/document.xml.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP source record role buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('R', 384),
            'compressionMethod' => 0,
            'comment' => 'source-role image',
        ],
        ['name' => 'customXml/item1.xml', 'data' => '<review>source-role</review>', 'compressionMethod' => 0],
        ['name' => 'customXml/itemProps1.xml', 'data' => '<props/>', 'compressionMethod' => 8],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_role_index_by(array $items, string $key): array
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
function docx_zip_source_record_role_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        foreach (docx_zip_source_record_roles_for_part($part) as $role) {
            $counts[$role] = ($counts[$role] ?? 0) + 1;
        }
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_role_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        foreach (docx_zip_source_record_roles_for_part($part) as $role) {
            $sums[$role] = ($sums[$role] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
        }
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_sum_for_role(array $inventory, string $role, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (!in_array($role, docx_zip_source_record_roles_for_part($part), true)) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, mixed> $part
 * @return list<string>
 */
function docx_zip_source_record_roles_for_part(array $part): array
{
    $roles = array_values(array_unique(array_filter(
        array_map('strval', $part['roles'] ?? []),
        static fn (string $role): bool => $role !== '',
    )));

    return $roles === [] ? ['package-part'] : $roles;
}
