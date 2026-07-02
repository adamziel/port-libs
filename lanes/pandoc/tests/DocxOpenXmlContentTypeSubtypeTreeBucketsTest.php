<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package content types by subtype tree' => static function (TestRunner $t): void {
        $parts = docx_content_type_subtype_tree_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $buckets = docx_content_type_subtype_tree_index_by(
            $summary['partContentTypeSubtypeTrees'],
            'contentTypeSubtypeTreeKey'
        );

        $t->same('Subtype tree review.', $document->children[0]->attr('text'));
        $t->same(6, $summary['partContentTypeSubtypeTreeCount']);
        $t->same([
            '(invalid)' => 1,
            '(missing)' => 1,
            'experimental' => 1,
            'personal' => 1,
            'standard' => 4,
            'vendor' => 5,
        ], $summary['partContentTypeSubtypeTreeCounts']);
        $t->same([
            '(invalid)',
            '(missing)',
            'experimental',
            'personal',
            'standard',
            'vendor',
        ], array_column($summary['partContentTypeSubtypeTrees'], 'contentTypeSubtypeTreeKey'));

        $vendor = $buckets['vendor'];
        $t->same('vendor', $vendor['contentTypeSubtypeTree']);
        $t->same(5, $vendor['partCount']);
        $t->same(2, $vendor['relationshipPartCount']);
        $t->same(1, $vendor['parameterizedPartCount']);
        $t->same(['default' => 2, 'override' => 3], $vendor['contentTypeSourceCounts']);
        $t->same(['application' => 5], $vendor['mediaTypeCounts']);
        $t->same([
            'vnd.example.review+json' => 1,
            'vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'vnd.openxmlformats-package.core-properties+xml' => 1,
            'vnd.openxmlformats-package.relationships+xml' => 2,
        ], $vendor['subtypeCounts']);
        $t->same(['rels'], $vendor['defaultExtensions']);
        $t->same(['customXml/vendor.json', 'docProps/core.xml', 'word/document.xml'], $vendor['overridePartNames']);
        $t->same(2, $vendor['roleCounts']['relationship-part']);
        $t->same(1, $vendor['roleCounts']['office-document']);
        $t->same(1, $vendor['roleCounts']['core-properties']);
        $t->same('customXml/vendor.json', $vendor['largestPart']['partName']);
        $t->same('vnd.example.review+json', $vendor['largestPart']['contentTypeSubtype']);
        $t->same('vendor', $vendor['largestPart']['contentTypeSubtypeTree']);

        $standard = $buckets['standard'];
        $t->same('standard', $standard['contentTypeSubtypeTree']);
        $t->same(4, $standard['partCount']);
        $t->same(1, $standard['parameterizedPartCount']);
        $t->same(['default' => 4], $standard['contentTypeSourceCounts']);
        $t->same(['application' => 3, 'text' => 1], $standard['mediaTypeCounts']);
        $t->same(['octet-stream' => 1, 'plain' => 1, 'xml' => 2], $standard['subtypeCounts']);
        $t->same(['bin', 'txt', 'xml'], $standard['defaultExtensions']);
        $t->same([
            '[Content_Types].xml',
            'customXml/raw.bin',
            'customXml/std.txt',
            'word/styles.xml',
        ], $standard['partNames']);
        $t->same(['content-types' => 1, 'package-part' => 3], $standard['roleCounts']);

        $personal = $buckets['personal'];
        $t->same('personal', $personal['contentTypeSubtypeTree']);
        $t->same(1, $personal['partCount']);
        $t->same(['application' => 1], $personal['mediaTypeCounts']);
        $t->same(['prs.example.note+xml' => 1], $personal['subtypeCounts']);
        $t->same(['application/prs.example.note+xml' => 1], $personal['contentTypeBaseCounts']);
        $t->same(['customXml/personal.xml'], $personal['overridePartNames']);

        $experimental = $buckets['experimental'];
        $t->same('experimental', $experimental['contentTypeSubtypeTree']);
        $t->same(1, $experimental['partCount']);
        $t->same(['application' => 1], $experimental['mediaTypeCounts']);
        $t->same(['x-review' => 1], $experimental['subtypeCounts']);
        $t->same(['customXml/experimental.dat'], $experimental['partNames']);

        $invalid = $buckets['(invalid)'];
        $t->same(null, $invalid['contentTypeSubtypeTree']);
        $t->same(1, $invalid['invalidContentTypePartCount']);
        $t->same(['(invalid)' => 1], $invalid['mediaTypeCounts']);
        $t->same(['(invalid)' => 1], $invalid['subtypeCounts']);
        $t->same(['review-subtype' => 1], $invalid['contentTypeBaseCounts']);
        $t->same(['customXml/invalid.dat'], $invalid['overridePartNames']);

        $missing = $buckets['(missing)'];
        $t->same(null, $missing['contentTypeSubtypeTree']);
        $t->same(1, $missing['missingContentTypePartCount']);
        $t->same(['(missing)' => 1], $missing['mediaTypeCounts']);
        $t->same(['(missing)' => 1], $missing['subtypeCounts']);
        $t->same(['(missing)' => 1], $missing['contentTypeBaseCounts']);
        $t->same(['missing' => 1], $missing['contentTypeSourceCounts']);
        $t->same(['payload'], $missing['defaultExtensions']);
        $t->same('customXml/no-type.payload', $missing['largestPart']['partName']);
    },
];

/**
 * @return array<string, string>
 */
function docx_content_type_subtype_tree_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Default Extension="txt" ContentType="text/plain; charset=UTF-8"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/vendor.json" ContentType="application/vnd.example.review+json; profile=subtype-tree"/>
  <Override PartName="/customXml/personal.xml" ContentType="application/prs.example.note+xml"/>
  <Override PartName="/customXml/experimental.dat" ContentType="application/x-review"/>
  <Override PartName="/customXml/invalid.dat" ContentType="review-subtype"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Subtype tree review</dc:title>
</cp:coreProperties>
XML,
        'word/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Subtype tree review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'customXml/vendor.json' => str_repeat('{"vendor":true}', 80),
        'customXml/personal.xml' => '<personal/>',
        'customXml/experimental.dat' => 'experimental subtype payload',
        'customXml/invalid.dat' => 'invalid subtype payload',
        'customXml/std.txt' => 'standard text payload',
        'customXml/raw.bin' => 'raw standard payload',
        'customXml/no-type.payload' => 'missing content type payload',
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_content_type_subtype_tree_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}
