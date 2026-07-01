<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by directory root' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(docx_zip_source_record_directory_root_fixture_parts(), 'docx source-root review');
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $roots = docx_zip_source_record_index_by($summary['partZipSourceRecordDirectoryRoots'], 'directoryRoot');

        $t->same(4, $summary['partZipSourceRecordDirectoryRootCount']);
        $t->same([
            '/' => 1,
            '_rels/' => 1,
            'customXml/' => 2,
            'word/' => 3,
        ], $summary['partZipSourceRecordDirectoryRootCounts']);
        $t->same(7, $summary['partZipSourceRecordPartCount']);
        $t->same(
            $summary['zipPackageManifestSourceRecordBytes'],
            $summary['partZipSourceRecordByteLength']
        );
        $t->same(
            $summary['zipPackageManifestLocalRecordBytes'],
            $summary['partZipSourceRecordLocalRecordByteLength']
        );
        $t->same(
            $summary['zipPackageManifestCentralDirectoryRecordBytes'],
            $summary['partZipSourceRecordCentralDirectoryRecordByteLength']
        );
        $t->same(
            docx_zip_source_record_sum_by_root($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordDirectoryRootBytes']
        );
        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same(
            $summary['partZipSourceRecordDirectoryRootCount'],
            $identity['partZipSourceRecordDirectoryRootCount']
        );
        $t->same(
            $summary['partZipSourceRecordDirectoryRootCounts'],
            $identity['partZipSourceRecordDirectoryRootCounts']
        );
        $t->same($summary['partZipSourceRecordDirectoryRootBytes'], $identity['partZipSourceRecordDirectoryRootBytes']);
        $t->same($summary['partZipSourceRecordPartCount'], $identity['partZipSourceRecordPartCount']);
        $t->same($summary['partZipSourceRecordByteLength'], $identity['partZipSourceRecordByteLength']);
        $t->same(
            $summary['partZipSourceRecordLocalRecordByteLength'],
            $identity['partZipSourceRecordLocalRecordByteLength']
        );
        $t->same(
            $summary['partZipSourceRecordCentralDirectoryRecordByteLength'],
            $identity['partZipSourceRecordCentralDirectoryRecordByteLength']
        );
        $t->same(
            $summary['partZipSourceRecordDataDescriptorPartCount'],
            $identity['partZipSourceRecordDataDescriptorPartCount']
        );
        $t->same($summary['partZipSourceRecordIssuePartCount'], $identity['partZipSourceRecordIssuePartCount']);
        $t->same($summary['partZipSourceRecordDirectoryRoots'], $identity['partZipSourceRecordDirectoryRoots']);
        $t->same(
            false,
            array_key_exists('contents', $identity['partZipSourceRecordDirectoryRoots'][0]['largestSourceRecordPart'])
        );

        $word = $roots['word/'];
        $t->same(3, $word['partCount']);
        $t->same([
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/review.png',
        ], $word['partNames']);
        $t->same([0 => 1, 8 => 2], $word['compressionMethodCounts']);
        $t->same(['default' => 2, 'override' => 1], $word['contentTypeSourceCounts']);
        $t->same(0, $word['dataDescriptorPartCount']);
        $t->same(0, $word['sourceByteSpanIssuePartCount']);
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'word/', 'sourceRecordBytes'),
            $word['sourceRecordBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'word/', 'localHeaderBytes'),
            $word['localHeaderBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'word/', 'localHeaderFixedHeaderBytes'),
            $word['localHeaderFixedHeaderBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'word/', 'compressedDataBytes'),
            $word['compressedDataBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'word/', 'centralDirectoryRecordBytes'),
            $word['centralDirectoryRecordBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'word/', 'centralDirectoryFixedHeaderBytes'),
            $word['centralDirectoryFixedHeaderBytes']
        );
        $t->same('word/media/review.png', $word['largestSourceRecordPart']['partName']);
        $t->same(
            $inventory['word/media/review.png']['sourceRecordBytes'],
            $word['largestSourceRecordPart']['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $word['largestSourceRecordPart']));

        $customXml = $roots['customXml/'];
        $t->same(2, $customXml['partCount']);
        $t->same([
            'customXml/item1.xml',
            'customXml/itemProps1.xml',
        ], $customXml['partNames']);
        $t->same(['default' => 2], $customXml['contentTypeSourceCounts']);
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'customXml/', 'centralDirectoryRawNameBytes'),
            $customXml['centralDirectoryRawNameBytes']
        );
        $t->same(
            docx_zip_source_record_sum_for_root($inventory, 'customXml/', 'localHeaderRawNameBytes'),
            $customXml['localHeaderRawNameBytes']
        );
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_directory_root_fixture_parts(): array
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
    <w:p><w:r><w:t>ZIP source record directory roots.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('M', 512),
            'compressionMethod' => 0,
            'comment' => 'source-root image',
        ],
        ['name' => 'customXml/item1.xml', 'data' => '<review>source-root</review>', 'compressionMethod' => 0],
        ['name' => 'customXml/itemProps1.xml', 'data' => '<props/>', 'compressionMethod' => 8],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_index_by(array $items, string $key): array
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
function docx_zip_source_record_sum_by_root(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $root = is_string($part['zipDirectoryRoot'] ?? null) ? $part['zipDirectoryRoot'] : '/';
        $sums[$root] = ($sums[$root] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_sum_for_root(array $inventory, string $root, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipDirectoryRoot'] ?? null) !== $root) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}
