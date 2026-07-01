<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package part case-fold path collisions for review handoff' => static function (TestRunner $t): void {
        $parts = docx_casefold_path_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $groups = [];
        foreach ($summary['partCaseFoldPaths'] as $group) {
            $groups[$group['caseFoldPartName']] = $group;
        }

        $t->same(6, $summary['partCaseFoldPathCount']);
        $t->same(1, $summary['duplicatePartCaseFoldPathCount']);
        $t->same(3, $summary['duplicatePartCaseFoldPathPartCount']);
        $t->same(['word/media/review.png'], $summary['duplicatePartCaseFoldPaths']);
        $t->same('word/media/review.png', $inventory['word/media/review.png']['caseFoldPartName']);
        $t->same('word/media/review.png', $inventory['word/media/REVIEW.PNG']['caseFoldPartName']);
        $t->same('word/media/review.png', $inventory['WORD/media/review.png']['caseFoldPartName']);
        $t->same('customxml/review.png', $inventory['customXml/review.png']['caseFoldPartName']);

        $collision = $groups['word/media/review.png'];
        $t->same(3, $collision['partCount']);
        $t->same(3, $collision['caseVariantCount']);
        $t->same(strlen($parts['word/media/review.png']) + strlen($parts['word/media/REVIEW.PNG']) + strlen($parts['WORD/media/review.png']), $collision['byteLength']);
        $t->same([
            'WORD/media/review.png' => 1,
            'word/media/REVIEW.PNG' => 1,
            'word/media/review.png' => 1,
        ], $collision['partNameCounts']);
        $t->same(['WORD/media', 'word/media'], $collision['directories']);
        $t->same(['REVIEW.PNG', 'review.png'], $collision['baseNames']);
        $t->same([
            'WORD/media/review.png',
            'word/media/REVIEW.PNG',
            'word/media/review.png',
        ], $collision['partNames']);
        $t->same(['image/png' => 3], $collision['contentTypeBaseCounts']);
        $t->same(['default' => 3], $collision['contentTypeSourceCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'package-part' => 1,
        ], $collision['roleCounts']);
        $t->same('word/media/REVIEW.PNG', $collision['largestPart']['partName']);
        $t->same('word/media/review.png', $collision['largestPart']['caseFoldPartName']);
        $t->same('word/media', $collision['largestPart']['directory']);
        $t->same('REVIEW.PNG', $collision['largestPart']['baseName']);
        $t->same('png', $collision['largestPart']['partExtension']);
        $t->same(strlen($parts['word/media/REVIEW.PNG']), $collision['largestPart']['bytes']);
        $t->same(hash('sha256', $parts['word/media/REVIEW.PNG']), $collision['largestPart']['sha256']);
        $t->same('image/png', $collision['largestPart']['contentTypeBase']);
        $t->same('default', $collision['largestPart']['contentTypeSource']);
        $t->same(['document-relationship-target'], $collision['largestPart']['roles']);

        $custom = $groups['customxml/review.png'];
        $t->same(1, $custom['partCount']);
        $t->same(1, $custom['caseVariantCount']);
        $t->same(['customXml/review.png' => 1], $custom['partNameCounts']);
        $t->same(['customXml'], $custom['directories']);
        $t->same(['review.png'], $custom['baseNames']);
        $t->same(['package-part' => 1], $custom['roleCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_casefold_path_fixture_parts(): array
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
  <Relationship Id="rLowerImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rUpperImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/REVIEW.PNG"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Case-fold path fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => 'lower png bytes',
        'word/media/REVIEW.PNG' => 'upper png bytes with larger payload',
        'WORD/media/review.png' => 'shadow png bytes',
        'customXml/review.png' => 'custom review png bytes',
    ];
}
