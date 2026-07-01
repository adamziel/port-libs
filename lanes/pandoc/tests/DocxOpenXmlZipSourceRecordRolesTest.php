<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by package part role' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(docx_zip_source_record_role_fixture_parts(), 'docx source role review');
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $roles = docx_zip_source_record_role_index_by($summary['partZipSourceRecordRoles'], 'role');
        $expectedRoles = [
            'content-types',
            'core-properties',
            'document-relationship-target',
            'embedded-package',
            'office-document',
            'office-document-relationships',
            'package-part',
            'package-relationships',
            'relationship-part',
            'root-relationship-target',
        ];

        $t->same('Source role buckets.', $document->children[0]->attr('text'));
        $t->same(count($expectedRoles), $summary['partZipSourceRecordRoleCount']);
        $t->same($expectedRoles, array_column($summary['partZipSourceRecordRoles'], 'role'));
        $t->same(
            docx_zip_source_record_role_counts($inventory),
            $summary['partZipSourceRecordRoleCounts']
        );
        $t->same(
            docx_zip_source_record_role_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordRoleBytes']
        );

        $documentTargets = $roles['document-relationship-target'];
        $t->same(2, $documentTargets['partCount']);
        $t->same([
            'word/embeddings/review.xlsx',
            'word/media/review.png',
        ], $documentTargets['partNames']);
        $t->same(['word/' => 2], $documentTargets['directoryRootCounts']);
        $t->same(['default' => 1, 'override' => 1], $documentTargets['contentTypeSourceCounts']);
        $t->same([0 => 1, 8 => 1], $documentTargets['compressionMethodCounts']);
        $t->same(
            docx_zip_source_record_role_sum_for_role($inventory, 'document-relationship-target', 'sourceRecordBytes'),
            $documentTargets['sourceRecordBytes']
        );
        $t->same(
            docx_zip_source_record_role_sum_for_role($inventory, 'document-relationship-target', 'localHeaderBytes'),
            $documentTargets['localHeaderBytes']
        );
        $t->same(
            docx_zip_source_record_role_sum_for_role($inventory, 'document-relationship-target', 'centralDirectoryRecordBytes'),
            $documentTargets['centralDirectoryRecordBytes']
        );
        $t->same('word/embeddings/review.xlsx', $documentTargets['largestSourceRecordPart']['partName']);
        $t->same(
            $inventory['word/embeddings/review.xlsx']['sourceRecordBytes'],
            $documentTargets['largestSourceRecordPart']['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $documentTargets['largestSourceRecordPart']));

        $embedded = $roles['embedded-package'];
        $t->same(1, $embedded['partCount']);
        $t->same(['word/embeddings/review.xlsx'], $embedded['partNames']);
        $t->same(['override' => 1], $embedded['contentTypeSourceCounts']);
        $t->same(
            $inventory['word/embeddings/review.xlsx']['sourceRecordBytes'],
            $embedded['sourceRecordBytes']
        );
        $t->same(
            ['document-relationship-target', 'embedded-package'],
            $embedded['largestSourceRecordPart']['roles']
        );

        $relationshipPart = $roles['relationship-part'];
        $t->same(2, $relationshipPart['partCount']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
        ], $relationshipPart['partNames']);
        $t->same(['_rels/' => 1, 'word/' => 1], $relationshipPart['directoryRootCounts']);
        $t->same(['default' => 2], $relationshipPart['contentTypeSourceCounts']);
        $t->same(
            ['application/vnd.openxmlformats-package.relationships+xml' => 2],
            $relationshipPart['contentTypeBaseCounts']
        );
        $t->same(
            docx_zip_source_record_role_sum_for_role($inventory, 'relationship-part', 'compressedDataBytes'),
            $relationshipPart['compressedDataBytes']
        );

        $packagePart = $roles['package-part'];
        $t->same(1, $packagePart['partCount']);
        $t->same(['customXml/untyped-source.bin'], $packagePart['partNames']);
        $t->same(['customXml/' => 1], $packagePart['directoryRootCounts']);
        $t->same(['missing' => 1], $packagePart['contentTypeSourceCounts']);
        $t->same(['(missing)' => 1], $packagePart['contentTypeBaseCounts']);
        $t->same(
            $inventory['customXml/untyped-source.bin']['sourceRecordBytes'],
            $packagePart['sourceRecordBytes']
        );
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_role_fixture_parts(): array
{
    $embeddedContentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $embeddedPackageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';

    return [
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/review.xlsx" ContentType="{$embeddedContentType}"/>
</Types>
XML,
        ],
        ['name' => '_rels/.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        ],
        ['name' => 'word/_rels/document.xml.rels', 'compressionMethod' => 8, 'data' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rEmbeddedWorkbook" Type="{$embeddedPackageRel}" Target="embeddings/review.xlsx"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Source role buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        ['name' => 'docProps/core.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Source role buckets</dc:title>
</cp:coreProperties>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('M', 160),
            'compressionMethod' => 8,
        ],
        [
            'name' => 'word/embeddings/review.xlsx',
            'data' => str_repeat('E', 1024),
            'compressionMethod' => 0,
            'comment' => 'embedded source role',
        ],
        [
            'name' => 'customXml/untyped-source.bin',
            'data' => 'untyped source-role payload',
            'compressionMethod' => 0,
        ],
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
        foreach (docx_zip_source_record_part_roles($part) as $role) {
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
        foreach (docx_zip_source_record_part_roles($part) as $role) {
            $sums[$role] = ($sums[$role] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
        }
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_role_sum_for_role(array $inventory, string $role, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (!in_array($role, docx_zip_source_record_part_roles($part), true)) {
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
function docx_zip_source_record_part_roles(array $part): array
{
    $roles = array_values(array_filter(
        array_map('strval', $part['roles'] ?? []),
        static fn (string $role): bool => $role !== '',
    ));

    return $roles === [] ? ['package-part'] : $roles;
}
