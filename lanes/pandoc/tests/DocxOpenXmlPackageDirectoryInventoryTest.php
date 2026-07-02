<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package exact directory inventory mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries docx package exact directory maps through summary and identity' => static function (TestRunner $t): void {
        $parts = docx_exact_directory_inventory_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];

        $repeatIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_exact_directory_inventory_fixture_parts())
            ->attr('docx')['packageIdentity'];
        $renamedParts = $parts;
        unset($renamedParts['customXml/media/raw.bin']);
        $renamedParts['customXml/raw-copy.bin'] = 'custom raw bytes';
        $renamedIdentity = (new DocxOpenXmlReader())
            ->readPackage($renamedParts)
            ->attr('docx')['packageIdentity'];

        $expectedDirectoryCounts = [
            '/' => 1,
            '_rels' => 1,
            'customXml' => 1,
            'customXml/media' => 2,
            'docProps' => 1,
            'word' => 1,
            'word/_rels' => 1,
            'word/media' => 2,
        ];

        $t->same($identity, $docx['packageIdentity']);
        $t->same(8, $summary['partDirectoryCount']);
        $t->same($expectedDirectoryCounts, $summary['partDirectoryCounts']);
        $t->same(8, $identity['packageDirectoryCount']);
        $t->same($expectedDirectoryCounts, $identity['packageDirectoryCounts']);
        $t->same($summary['partDirectoryCount'], $identity['packageDirectoryCount']);
        $t->same($summary['partDirectoryCounts'], $identity['packageDirectoryCounts']);
        $t->same($summary['partNamesByPartDirectory'], $identity['entryNamesByPackageDirectory']);
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $renamedIdentity['identitySha256']);

        $t->same(['[Content_Types].xml'], $summary['partNamesByPartDirectory']['/']);
        $t->same(['_rels/.rels'], $summary['partNamesByPartDirectory']['_rels']);
        $t->same(['customXml/raw.bin'], $summary['partNamesByPartDirectory']['customXml']);
        $t->same([
            'customXml/media/data.xml',
            'customXml/media/raw.bin',
        ], $summary['partNamesByPartDirectory']['customXml/media']);
        $t->same([
            'word/media/image.png',
            'word/media/review.png',
        ], $summary['partNamesByPartDirectory']['word/media']);
        $t->same([
            'customXml/media/data.xml',
            'customXml/media/raw.bin',
        ], $identity['entryNamesByPackageDirectory']['customXml/media']);
        $t->same([
            'word/media/image.png',
            'word/media/review.png',
        ], $identity['entryNamesByPackageDirectory']['word/media']);

        $directories = [];
        foreach ($summary['partDirectories'] as $directory) {
            $directories[$directory['directory']] = $directory;
        }
        $t->same(array_keys($expectedDirectoryCounts), array_column($summary['partDirectories'], 'directory'));
        $t->same(2, $directories['word/media']['partCount']);
        $t->same(['document-relationship-target' => 2], $directories['word/media']['roleCounts']);
        $t->same(['default' => 2], $directories['word/media']['contentTypeSourceCounts']);
        $t->same(2, $directories['customXml/media']['partCount']);
        $t->same(1, $directories['customXml/media']['missingContentTypePartCount']);
        $t->same(['default' => 1, 'missing' => 1], $directories['customXml/media']['contentTypeSourceCounts']);
        $t->same(['package-part' => 2], $directories['customXml/media']['roleCounts']);

        $entriesByName = [];
        foreach ($identity['packageEntries'] as $entry) {
            $entriesByName[$entry['partName']] = $entry;
        }
        $reviewEntry = $entriesByName['word/media/review.png'];
        $customRawEntry = $entriesByName['customXml/media/raw.bin'];

        $t->same('word/media', $reviewEntry['directory']);
        $t->same('media', $reviewEntry['directoryBaseName']);
        $t->same(2, $reviewEntry['directoryDepth']);
        $t->same(['document-relationship-target'], $reviewEntry['roles']);
        $t->same(hash('sha256', $parts['word/media/review.png']), $reviewEntry['sha256']);
        $t->same(false, array_key_exists('contents', $reviewEntry));
        $t->same('customXml/media', $customRawEntry['directory']);
        $t->same('missing', $customRawEntry['contentTypeSource']);
        $t->same(['package-part'], $customRawEntry['roles']);
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_exact_directory_inventory_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewPng" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rImagePng" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package directory inventory fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => '<coreProperties/>',
        'word/media/review.png' => 'document review image bytes',
        'word/media/image.png' => 'document inline image bytes',
        'customXml/media/data.xml' => '<data/>',
        'customXml/media/raw.bin' => 'custom raw bytes',
        'customXml/raw.bin' => 'root raw bytes',
    ];
}
