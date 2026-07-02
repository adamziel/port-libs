<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx selected xml root and byte aggregates for package review' => static function (TestRunner $t): void {
        $parts = docx_selected_xml_root_byte_aggregate_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $selected = $package['selectedXmlParts'];
        $summary = $package['summary'];
        $byKind = $selected['byKind'];
        $expectedByteLength =
            strlen($parts['word/document.xml'])
            + strlen($parts['docProps/core.xml'])
            + strlen($parts['word/styles.xml'])
            + strlen($parts['word/numbering.xml'])
            + strlen($parts['custom/settings/review-settings.xml'])
            + strlen($parts['word/theme/review-theme.xml']);
        $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $coreNamespace = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
        $dcNamespace = 'http://purl.org/dc/elements/1.1/';
        $drawingNamespace = 'http://schemas.openxmlformats.org/drawingml/2006/main';

        $t->same(18, $selected['count']);
        $t->same(6, $selected['existingCount']);
        $t->same(4, $selected['relationshipSelectedCount']);
        $t->same($expectedByteLength, $selected['byteLength']);
        $t->same($selected['byteLength'], $summary['selectedXmlPartByteLength']);
        $t->same([
            'conventional-fallback' => 12,
            'conventional-part' => 2,
            'relationship' => 4,
        ], $selected['selectionSourceCounts']);
        $t->same($selected['selectionSourceCounts'], $summary['selectedXmlPartSelectionSourceCounts']);

        $t->same([
            $drawingNamespace => 1,
            $coreNamespace => 1,
            $wordNamespace => 4,
        ], $selected['rootNamespaceCounts']);
        $t->same($selected['rootNamespaceCounts'], $summary['selectedXmlPartRootNamespaceCounts']);
        $t->same(7, $selected['rootNamespaceDeclarationCount']);
        $t->same(4, $selected['rootNamespaceDeclarationUriCount']);
        $t->same(['w', 'cp', 'dc', 'a'], $selected['rootNamespacePrefixes']);
        $t->same([$dcNamespace, $drawingNamespace, $coreNamespace, $wordNamespace], $selected['rootNamespaceDeclarationUris']);
        $t->same([
            $dcNamespace => 1,
            $drawingNamespace => 1,
            $coreNamespace => 1,
            $wordNamespace => 4,
        ], $selected['rootNamespaceDeclarationUriCounts']);
        $t->same($selected['rootNamespaceDeclarationUriCount'], $summary['selectedXmlPartRootNamespaceDeclarationUriCount']);
        $t->same($selected['rootNamespaceDeclarationUris'], $summary['selectedXmlPartRootNamespaceDeclarationUris']);
        $t->same($selected['rootNamespaceDeclarationUriCounts'], $summary['selectedXmlPartRootNamespaceDeclarationUriCounts']);
        $t->same([
            'coreProperties' => 1,
            'document' => 1,
            'numbering' => 1,
            'settings' => 1,
            'styles' => 1,
            'theme' => 1,
        ], $selected['rootLocalNameCounts']);
        $t->same($selected['rootLocalNameCounts'], $summary['selectedXmlPartRootLocalNameCounts']);
        $t->same([
            'a:theme' => 1,
            'cp:coreProperties' => 1,
            'w:document' => 1,
            'w:numbering' => 1,
            'w:settings' => 1,
            'w:styles' => 1,
        ], $selected['rootQualifiedNameCounts']);
        $t->same($selected['rootQualifiedNameCounts'], $summary['selectedXmlPartRootQualifiedNameCounts']);

        $largest = $selected['largestPart'];
        $t->same($largest, $summary['selectedXmlPartLargestPart']);
        $t->same('styles', $largest['kind']);
        $t->same('word/styles.xml', $largest['partName']);
        $t->same('conventional-part', $largest['selectionSource']);
        $t->same(null, $largest['relationshipId']);
        $t->same(strlen($parts['word/styles.xml']), $largest['bytes']);
        $t->same(sprintf('%08x', crc32($parts['word/styles.xml'])), $largest['crc32']);
        $t->same(hash('sha256', $parts['word/styles.xml']), $largest['sha256']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $largest['contentTypeBase']);
        $t->same('override', $largest['contentTypeSource']);
        $t->same('http://schemas.openxmlformats.org/wordprocessingml/2006/main', $largest['rootNamespace']);
        $t->same('styles', $largest['rootLocalName']);
        $t->same('w:styles', $largest['rootQualifiedName']);
        $t->same(true, $largest['validRoot']);
        $t->same([], $largest['issues']);
        $t->true(!array_key_exists('contents', $largest), 'largest selected XML part must not expose package bytes');
        $t->true(!array_key_exists('xml', $largest), 'largest selected XML part must not expose parsed XML bytes');

        $t->same('relationship', $byKind['settings']['selectionSource']);
        $t->same('rSettings', $byKind['settings']['relationshipId']);
        $t->same('?profile=selected#settings', $byKind['settings']['targetReferenceSuffix']);
        $t->same('w:settings', $byKind['settings']['rootQualifiedName']);
        $t->same([$wordNamespace => 1], $byKind['settings']['rootNamespaceDeclarationUriCounts']);
        $t->same(['w' => $wordNamespace], $byKind['settings']['rootNamespaceDeclarationMap']);
        $t->same('relationship', $byKind['theme']['selectionSource']);
        $t->same('rTheme', $byKind['theme']['relationshipId']);
        $t->same('a:theme', $byKind['theme']['rootQualifiedName']);
        $t->same([$drawingNamespace => 1], $byKind['theme']['rootNamespaceDeclarationUriCounts']);
        $t->same(['a' => $drawingNamespace], $byKind['theme']['rootNamespaceDeclarationMap']);
        $t->same([$dcNamespace => 1, $coreNamespace => 1], $byKind['coreProperties']['rootNamespaceDeclarationUriCounts']);
        $t->same(['cp' => $coreNamespace, 'dc' => $dcNamespace], $byKind['coreProperties']['rootNamespaceDeclarationMap']);
    },
    'records docx selected xml root byte aggregate mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
];

/**
 * @return array<string, string>
 */
function docx_selected_xml_root_byte_aggregate_fixture_parts(): array
{
    $styles = str_repeat(
        '  <w:style w:type="paragraph" w:styleId="ReviewStyle"><w:name w:val="Review Style"/></w:style>' . "\n",
        8
    );

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
  <Override PartName="/custom/settings/review-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/theme/review-theme.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
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
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="../custom/settings/review-settings.xml?profile=selected#settings"/>
  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/review-theme.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Selected XML root byte aggregate fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Selected XML aggregate fixture</dc:title>
</cp:coreProperties>
XML,
        'word/styles.xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
{$styles}</w:styles>
XML,
        'word/numbering.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>
XML,
        'custom/settings/review-settings.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'word/theme/review-theme.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Review Theme"/>
XML,
    ];
}
