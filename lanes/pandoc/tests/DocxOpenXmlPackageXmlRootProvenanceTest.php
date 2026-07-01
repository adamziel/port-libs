<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml root elements without exposing xml bytes' => static function (TestRunner $t): void {
        $parts = docx_package_xml_root_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $documentPart = $package['parts']['word/document.xml'];
        $reviewPart = $package['parts']['customXml/root-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $invalidPart = $package['parts']['customXml/invalid-root.xml'];
        $binaryPart = $package['parts']['word/media/review.bin'];

        $rootByPartName = static function (array $roots): array {
            $byPartName = [];
            foreach ($roots as $root) {
                if (is_array($root) && is_string($root['partName'] ?? null)) {
                    $byPartName[$root['partName']] = $root;
                }
            }

            return $byPartName;
        };
        $summaryRoots = $rootByPartName($summary['partXmlRoots']);

        $t->same(8, $summary['partXmlRootPartCount']);
        $t->same(7, $summary['partXmlRootValidXmlPartCount']);
        $t->same(1, $summary['partXmlRootInvalidXmlPartCount']);
        $t->same(4, $summary['partXmlRootAttributeCount']);
        $t->same(12, $summary['partXmlRootNamespaceDeclarationCount']);
        $t->same([
            'http://schemas.openxmlformats.org/package/2006/content-types' => 1,
            'http://schemas.openxmlformats.org/package/2006/metadata/core-properties' => 1,
            'http://schemas.openxmlformats.org/package/2006/relationships' => 2,
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main' => 2,
            'urn:docx-root-review' => 1,
        ], $summary['partXmlRootNamespaceCounts']);
        $t->same([
            'Relationships' => 2,
            'Types' => 1,
            'coreProperties' => 1,
            'document' => 1,
            'packet' => 1,
            'settings' => 1,
        ], $summary['partXmlRootLocalNameCounts']);
        $t->same([
            'Relationships' => 2,
            'Types' => 1,
            'cp:coreProperties' => 1,
            'review:packet' => 1,
            'w:document' => 1,
            'w:settings' => 1,
        ], $summary['partXmlRootQualifiedNameCounts']);
        $t->same([
            'cp' => 1,
            'review' => 1,
            'w' => 2,
        ], $summary['partXmlRootPrefixCounts']);
        $t->same([
            'cp' => 1,
            'dc' => 1,
            'default' => 4,
            'mc' => 1,
            'r' => 1,
            'review' => 1,
            'w' => 2,
            'w14' => 1,
        ], $summary['partXmlRootNamespacePrefixCounts']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/invalid-root.xml',
            'customXml/root-review.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/settings.xml',
        ], $summary['partXmlRootPartNames']);
        $t->same(['customXml/invalid-root.xml'], $summary['partXmlRootInvalidPartNames']);
        $t->same(false, $summary['partXmlRootsTruncated']);

        $t->same(true, $documentPart['xmlRootChecked']);
        $t->same(true, $documentPart['xmlRootValidXml']);
        $t->same('http://schemas.openxmlformats.org/wordprocessingml/2006/main', $documentPart['xmlRootNamespace']);
        $t->same('document', $documentPart['xmlRootLocalName']);
        $t->same('w:document', $documentPart['xmlRootQualifiedName']);
        $t->same('w', $documentPart['xmlRootPrefix']);
        $t->same(1, $documentPart['xmlRootAttributeCount']);
        $t->same(2, $documentPart['xmlRootNamespaceDeclarationCount']);
        $t->same(['w', 'r'], $documentPart['xmlRootNamespacePrefixes']);

        $t->same('urn:docx-root-review', $reviewPart['xmlRootNamespace']);
        $t->same('packet', $reviewPart['xmlRootLocalName']);
        $t->same('review:packet', $reviewPart['xmlRootQualifiedName']);
        $t->same('review', $reviewPart['xmlRootPrefix']);
        $t->same(2, $reviewPart['xmlRootAttributeCount']);
        $t->same(2, $reviewPart['xmlRootNamespaceDeclarationCount']);
        $t->same(['review', 'default'], $reviewPart['xmlRootNamespacePrefixes']);

        $t->same('w:settings', $settingsPart['xmlRootQualifiedName']);
        $t->same(1, $settingsPart['xmlRootAttributeCount']);
        $t->same(3, $settingsPart['xmlRootNamespaceDeclarationCount']);
        $t->same(['w', 'mc', 'w14'], $settingsPart['xmlRootNamespacePrefixes']);

        $t->same(true, $invalidPart['xmlRootChecked']);
        $t->same(false, $invalidPart['xmlRootValidXml']);
        $t->true(is_string($invalidPart['xmlRootParseError']) && $invalidPart['xmlRootParseError'] !== '');
        $t->same(null, $invalidPart['xmlRootQualifiedName']);
        $t->same(0, $invalidPart['xmlRootAttributeCount']);
        $t->same(0, $invalidPart['xmlRootNamespaceDeclarationCount']);
        $t->same([], $invalidPart['xmlRootNamespacePrefixes']);

        $t->same(false, $binaryPart['xmlRootChecked']);
        $t->same(null, $binaryPart['xmlRootValidXml']);
        $t->same(null, $binaryPart['xmlRootQualifiedName']);

        $t->same('review:packet', $summaryRoots['customXml/root-review.xml']['qualifiedName']);
        $t->same(2, $summaryRoots['customXml/root-review.xml']['attributeCount']);
        $t->same(['review', 'default'], $summaryRoots['customXml/root-review.xml']['namespacePrefixes']);
        $t->same(false, $summaryRoots['customXml/invalid-root.xml']['validXml']);
        $t->true(is_string($summaryRoots['customXml/invalid-root.xml']['xmlParseError']));

        $encodedRoots = json_encode([
            $summary['partXmlRoots'],
            $documentPart['xmlRootQualifiedName'],
            $reviewPart['xmlRootQualifiedName'],
            $settingsPart['xmlRootQualifiedName'],
            $invalidPart['xmlRootParseError'],
        ]);
        $t->true(is_string($encodedRoots), 'XML root metadata should encode for review');
        $t->true(!str_contains((string) $encodedRoots, 'hidden-payload'), 'raw XML part bytes should not appear in root metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_root_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/root-review.xml" ContentType="application/xml; profile=root-review"/>
  <Override PartName="/customXml/invalid-root.xml" ContentType="application/xml; profile=root-invalid"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
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
  <Relationship Id="rReviewRoot" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/root-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" w:conformance="strict">
  <w:body>
    <w:p><w:r><w:t>Package XML root hidden-payload-alpha fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML root fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/root-review.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<review:packet xmlns:review="urn:docx-root-review" xmlns="urn:docx-root-default" review:state="ready" xml:lang="en">
  <value>hidden-payload-beta</value>
</review:packet>
XML,
        'customXml/invalid-root.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<broken><payload>hidden-payload-gamma</broken>
XML,
        'word/settings.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" mc:Ignorable="w14">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'word/media/review.bin' => "hidden-payload-delta\x00not xml",
    ];
}
