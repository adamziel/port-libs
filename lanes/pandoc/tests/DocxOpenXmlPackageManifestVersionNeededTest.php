<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'projects DOCX ZIP package manifest version-needed provenance' => static function (TestRunner $t): void {
        $zip = docx_package_manifest_version_needed_fixture_package();
        $manifest = $zip->packageManifestPreflight();
        $manifestSummaries = docx_package_manifest_version_needed_index_by_version(
            $manifest['versionNeededToExtractSummaries']
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $zipPackage = $package['zipPackage'];

        $t->same('Version needed provenance.', $document->children[0]->attr('text'));
        $t->same(2, $manifest['versionNeededToExtractSummaryCount']);
        $t->same([10, 20], $manifest['versionNeededToExtractVersions']);
        $t->same([10, 20], $manifest['minimumVersionNeededToExtractVersions']);
        $t->same(20, $manifest['maxVersionNeededToExtract']);
        $t->same(20, $manifest['maxMinimumVersionNeededToExtract']);
        $t->same(true, $manifest['hasMultipleVersionNeededToExtractVersions']);

        $t->same(
            $manifest['versionNeededToExtractSummaryCount'],
            $summary['zipPackageManifestVersionNeededToExtractSummaryCount']
        );
        $t->same(
            $manifest['versionNeededToExtractVersions'],
            $summary['zipPackageManifestVersionNeededToExtractVersions']
        );
        $t->same(
            $manifest['minimumVersionNeededToExtractVersions'],
            $summary['zipPackageManifestMinimumVersionNeededToExtractVersions']
        );
        $t->same($manifest['maxVersionNeededToExtract'], $summary['zipPackageManifestMaxVersionNeededToExtract']);
        $t->same(
            $manifest['maxMinimumVersionNeededToExtract'],
            $summary['zipPackageManifestMaxMinimumVersionNeededToExtract']
        );
        $t->same(
            $manifest['hasMultipleVersionNeededToExtractVersions'],
            $summary['zipPackageManifestHasMultipleVersionNeededToExtractVersions']
        );
        $t->same(
            $manifest['versionNeededToExtractSummaries'],
            $summary['zipPackageManifestVersionNeededToExtractSummaries']
        );

        $t->same(
            $manifest['versionNeededToExtractSummaryCount'],
            $zipPackage['packageManifestVersionNeededToExtractSummaryCount']
        );
        $t->same(
            $manifest['versionNeededToExtractVersions'],
            $zipPackage['packageManifestVersionNeededToExtractVersions']
        );
        $t->same(
            $manifest['minimumVersionNeededToExtractVersions'],
            $zipPackage['packageManifestMinimumVersionNeededToExtractVersions']
        );
        $t->same($manifest['maxVersionNeededToExtract'], $zipPackage['packageManifestMaxVersionNeededToExtract']);
        $t->same(
            $manifest['maxMinimumVersionNeededToExtract'],
            $zipPackage['packageManifestMaxMinimumVersionNeededToExtract']
        );
        $t->same(
            $manifest['hasMultipleVersionNeededToExtractVersions'],
            $zipPackage['packageManifestHasMultipleVersionNeededToExtractVersions']
        );
        $t->same(
            $manifest['versionNeededToExtractSummaries'],
            $zipPackage['packageManifestVersionNeededToExtractSummaries']
        );
        $t->same(false, array_key_exists('contents', $summary['zipPackageManifestVersionNeededToExtractSummaries'][0]));
        $t->same(false, array_key_exists('contents', $zipPackage['packageManifestVersionNeededToExtractSummaries'][0]));

        $t->same(['word/media/stored.bin', 'word/media/'], $manifestSummaries[10]['entryNames']);
        $t->same(['stored'], $manifestSummaries[10]['compressionMethodNames']);
        $t->same([10], $manifestSummaries[10]['minimumVersionNeededToExtracts']);
        $t->same(['deflated', 'stored'], $manifestSummaries[20]['compressionMethodNames']);
        $t->same([10, 20], $manifestSummaries[20]['minimumVersionNeededToExtracts']);
        $t->same(['[Content_Types].xml', '_rels/.rels', 'word/document.xml'], $manifestSummaries[20]['entryNames']);
    },
];

function docx_package_manifest_version_needed_fixture_package(): ZipPackage
{
    return docx_package_manifest_version_needed_zip_from_parts(
        docx_package_manifest_version_needed_fixture_parts(),
        'docx version needed review'
    );
}

/**
 * @return list<array{name:string, data:string, compressionMethod:int, versionNeededToExtract:int}>
 */
function docx_package_manifest_version_needed_fixture_parts(): array
{
    $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
    $relationships = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
    $document = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Version needed provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

    return [
        ['name' => '[Content_Types].xml', 'data' => $contentTypes, 'compressionMethod' => 0, 'versionNeededToExtract' => 20],
        ['name' => '_rels/.rels', 'data' => $relationships, 'compressionMethod' => 0, 'versionNeededToExtract' => 20],
        ['name' => 'word/document.xml', 'data' => $document, 'compressionMethod' => 8, 'versionNeededToExtract' => 20],
        ['name' => 'word/media/stored.bin', 'data' => 'stored media bytes', 'compressionMethod' => 0, 'versionNeededToExtract' => 10],
        ['name' => 'word/media/', 'data' => '', 'compressionMethod' => 0, 'versionNeededToExtract' => 10],
    ];
}

/**
 * @param list<array<string, mixed>> $parts
 */
function docx_package_manifest_version_needed_zip_from_parts(array $parts, string $packageComment): ZipPackage
{
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $central = '';

    foreach ($parts as $part) {
        $name = (string) $part['name'];
        $data = is_string($part['data'] ?? null) ? $part['data'] : '';
        $method = is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : 8;
        $versionNeededToExtract = is_int($part['versionNeededToExtract'] ?? null)
            ? $part['versionNeededToExtract']
            : 20;
        $compressed = match ($method) {
            0 => $data,
            8 => gzdeflate($data),
            default => throw new RuntimeException("Unsupported ZIP compression method {$method} for {$name}"),
        };
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate ZIP entry {$name}");
        }

        $offset = strlen($body);
        $crc = $crc32($data);
        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            $versionNeededToExtract,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            $versionNeededToExtract,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset
        );
        $central .= $name;
    }

    $centralOffset = strlen($body);
    $entryCount = count($parts);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), $centralOffset, strlen($packageComment))
        . $packageComment
    );
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function docx_package_manifest_version_needed_index_by_version(array $items): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (!is_array($item) || !is_int($item['versionNeededToExtract'] ?? null)) {
            continue;
        }

        $indexed[$item['versionNeededToExtract']] = $item;
    }

    return $indexed;
}
