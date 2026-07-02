<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx selected xml namespace declaration rollups for package review' => static function (TestRunner $t): void {
        $namespaces = docx_selected_xml_namespace_declaration_rollup_namespaces();
        $parts = docx_selected_xml_namespace_declaration_rollup_fixture_parts($namespaces);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $selected = $package['selectedXmlParts'];
        $summary = $package['summary'];
        $byKind = $selected['byKind'];

        $expectedPrefixCounts = [
            'cp' => 1,
            'dc' => 1,
            'default' => 1,
            'mc' => 1,
            'r' => 1,
            'w' => 2,
            'w15' => 1,
            'wp' => 1,
        ];
        $expectedUriCounts = [
            $namespaces['a'] => 1,
            $namespaces['cp'] => 1,
            $namespaces['dc'] => 1,
            $namespaces['mc'] => 1,
            $namespaces['r'] => 1,
            $namespaces['w'] => 2,
            $namespaces['w15'] => 1,
            $namespaces['wp'] => 1,
        ];
        ksort($expectedUriCounts, SORT_STRING);
        $expectedDocumentUriCounts = [
            $namespaces['r'] => 1,
            $namespaces['w'] => 1,
            $namespaces['wp'] => 1,
        ];
        ksort($expectedDocumentUriCounts, SORT_STRING);

        $t->same(18, $selected['count']);
        $t->same(4, $selected['existingCount']);
        $t->same(4, $selected['relationshipSelectedCount']);
        $t->same(9, $selected['rootNamespaceDeclarationCount']);
        $t->same(['w', 'r', 'wp', 'cp', 'dc', 'mc', 'w15', 'default'], $selected['rootNamespacePrefixes']);
        $t->same($expectedPrefixCounts, $selected['rootNamespaceDeclarationPrefixCounts']);
        $t->same($expectedUriCounts, $selected['rootNamespaceDeclarationUriCounts']);
        $t->same($selected['rootNamespaceDeclarationPrefixCounts'], $summary['selectedXmlPartRootNamespaceDeclarationPrefixCounts']);
        $t->same($selected['rootNamespaceDeclarationUriCounts'], $summary['selectedXmlPartRootNamespaceDeclarationUriCounts']);

        $t->same(['word/document.xml', 'word/settings.xml'], $selected['rootNamespaceDeclarationPartNamesByPrefix']['w']);
        $t->same(['word/theme/default-theme.xml'], $selected['rootNamespaceDeclarationPartNamesByPrefix']['default']);
        $t->same(['word/document.xml', 'word/settings.xml'], $summary['selectedXmlPartRootNamespaceDeclarationPartNamesByUri'][$namespaces['w']]);
        $t->same(['word/theme/default-theme.xml'], $summary['selectedXmlPartRootNamespaceDeclarationPartNamesByUri'][$namespaces['a']]);

        $t->same([
            'r' => $namespaces['r'],
            'w' => $namespaces['w'],
            'wp' => $namespaces['wp'],
        ], $byKind['document']['rootNamespaceDeclarationMap']);
        $t->same([
            'r' => 1,
            'w' => 1,
            'wp' => 1,
        ], $byKind['document']['rootNamespaceDeclarationPrefixCounts']);
        $t->same($expectedDocumentUriCounts, $byKind['document']['rootNamespaceDeclarationUriCounts']);
        $t->same('xmlns:w', $byKind['document']['rootNamespaceDeclarations'][0]['name']);
        $t->same('w', $byKind['document']['rootNamespaceDeclarations'][0]['prefix']);
        $t->same($namespaces['w'], $byKind['document']['rootNamespaceDeclarations'][0]['uri']);
        $t->same(strlen($namespaces['w']), $byKind['document']['rootNamespaceDeclarations'][0]['uriByteLength']);
        $t->same(hash('sha256', $namespaces['w']), $byKind['document']['rootNamespaceDeclarations'][0]['uriSha256']);

        $t->same([
            'mc' => $namespaces['mc'],
            'w' => $namespaces['w'],
            'w15' => $namespaces['w15'],
        ], $byKind['settings']['rootNamespaceDeclarationMap']);
        $t->same([
            'mc' => 1,
            'w' => 1,
            'w15' => 1,
        ], $byKind['settings']['rootNamespaceDeclarationPrefixCounts']);
        $t->same([
            'default' => $namespaces['a'],
        ], $byKind['theme']['rootNamespaceDeclarationMap']);
        $t->same('xmlns', $byKind['theme']['rootNamespaceDeclarations'][0]['name']);
        $t->same('default', $byKind['theme']['rootNamespaceDeclarations'][0]['prefix']);
        $t->same('theme', $byKind['theme']['rootQualifiedName']);
        $t->same(null, $byKind['theme']['rootPrefix']);
        $t->same(true, $byKind['theme']['validRoot']);
    },
];

/**
 * @return array<string, string>
 */
function docx_selected_xml_namespace_declaration_rollup_namespaces(): array
{
    return [
        'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
        'cp' => 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties',
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'mc' => 'http://schemas.openxmlformats.org/markup-compatibility/2006',
        'r' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
        'w' => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'w15' => 'http://schemas.microsoft.com/office/word/2012/wordml',
        'wp' => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
    ];
}

/**
 * @param array<string, string> $namespaces
 * @return array<string, string>
 */
function docx_selected_xml_namespace_declaration_rollup_fixture_parts(array $namespaces): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/theme/default-theme.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
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
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/default-theme.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="{$namespaces['w']}" xmlns:r="{$namespaces['r']}" xmlns:wp="{$namespaces['wp']}">
  <w:body>
    <w:p><w:r><w:t>Selected XML namespace declaration rollup fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="{$namespaces['cp']}" xmlns:dc="{$namespaces['dc']}">
  <dc:title>Selected XML namespace declaration rollup</dc:title>
</cp:coreProperties>
XML,
        'word/settings.xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="{$namespaces['w']}" xmlns:mc="{$namespaces['mc']}" xmlns:w15="{$namespaces['w15']}" mc:Ignorable="w15">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'word/theme/default-theme.xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<theme xmlns="{$namespaces['a']}" name="Default Namespace Theme">
  <themeElements/>
</theme>
XML,
    ];
}
