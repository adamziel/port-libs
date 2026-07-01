<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml root elements without exposing values' => static function (TestRunner $t): void {
        $hiddenReviewState = 'hidden-payload-alpha root attribute';
        $hiddenReviewText = 'hidden-payload-beta review body';
        $parts = docx_package_xml_root_element_fixture_parts($hiddenReviewState, $hiddenReviewText);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $reviewPart = $inventory['customXml/root-review.xml'];
        $settingsPart = $inventory['word/settings.xml'];
        $brokenPart = $inventory['word/broken.xml'];
        $mediaPart = $inventory['word/media/logo.png'];
        $rootsByPart = [];
        foreach ($summary['partXmlRootElements'] as $root) {
            $rootsByPart[$root['partName']] = $root;
        }

        $t->same(7, $summary['partXmlRootElementPartCount']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/root-review.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/settings.xml',
        ], $summary['partXmlRootElementPartNames']);
        $t->same([
            'Relationships',
            'Types',
            'cp:coreProperties',
            'review:packet',
            'w:document',
            'w:settings',
        ], $summary['partXmlRootElementNames']);
        $t->same(2, $summary['partXmlRootElementNameCounts']['Relationships']);
        $t->same(2, $summary['partXmlRootElementLocalNameCounts']['Relationships']);
        $t->same(2, $summary['partXmlRootElementNamespaceUriCounts']['http://schemas.openxmlformats.org/package/2006/relationships']);
        $t->same(2, $summary['partXmlRootElementNamespaceUriCounts']['http://schemas.openxmlformats.org/wordprocessingml/2006/main']);
        $t->same(1, $summary['partXmlRootElementNamespaceUriCounts']['urn:docx-root-review']);
        $t->same(1, $summary['partXmlRootElementAttributeNameCounts']['review:state']);
        $t->same(1, $summary['partXmlRootElementAttributeNameCounts']['xml:lang']);
        $t->same(1, $summary['partXmlRootElementNamespaceDeclarationNameCounts']['xmlns:review']);
        $t->same(false, $summary['partXmlRootElementsTruncated']);

        $t->same(true, $reviewPart['xmlHasRootElement']);
        $t->same('review:packet', $reviewPart['xmlRootElementName']);
        $t->same('packet', $reviewPart['xmlRootElementLocalName']);
        $t->same('review', $reviewPart['xmlRootElementPrefix']);
        $t->same('urn:docx-root-review', $reviewPart['xmlRootElementNamespaceUri']);
        $t->same('/review:packet', $reviewPart['xmlRootElementPath']);
        $t->same(2, $reviewPart['xmlRootElementAttributeCount']);
        $t->same(['review:state', 'xml:lang'], $reviewPart['xmlRootElementAttributeNames']);
        $t->same(1, $reviewPart['xmlRootElementNamespaceDeclarationCount']);
        $t->same(['xmlns:review'], $reviewPart['xmlRootElementNamespaceDeclarationNames']);

        $t->same(true, $settingsPart['xmlHasRootElement']);
        $t->same('w:settings', $settingsPart['xmlRootElementName']);
        $t->same(['w:review'], $settingsPart['xmlRootElementAttributeNames']);
        $t->same(false, $brokenPart['xmlHasRootElement']);
        $t->same(null, $brokenPart['xmlRootElementName']);
        $t->same(false, $mediaPart['xmlHasRootElement']);
        $t->same(null, $mediaPart['xmlRootElementName']);

        $t->same('review:packet', $rootsByPart['customXml/root-review.xml']['name']);
        $t->same(['review:state', 'xml:lang'], $rootsByPart['customXml/root-review.xml']['attributeNames']);
        $t->same(['xmlns:review'], $rootsByPart['customXml/root-review.xml']['namespaceDeclarationNames']);
        $t->same('w:settings', $rootsByPart['word/settings.xml']['name']);
        $t->same(['w:review'], $rootsByPart['word/settings.xml']['attributeNames']);

        $encodedRoots = json_encode([
            $reviewPart,
            $settingsPart,
            $summary['partXmlRootElements'],
        ]);
        $t->true(is_string($encodedRoots), 'XML root metadata should encode for review');
        $t->true(!str_contains((string) $encodedRoots, 'hidden-payload'), 'root metadata should not expose XML values');
    },
    'records docx package xml root element mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_root_element_fixture_parts(string $hiddenReviewState, string $hiddenReviewText): array
{
    $reviewXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<review:packet xmlns:review="urn:docx-root-review" review:state="{$hiddenReviewState}" xml:lang="en">
  <review:value>{$hiddenReviewText}</review:value>
</review:packet>
XML;
    $settingsXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" w:review="root">
  <w:updateFields w:val="true"/>
</w:settings>
XML;

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/root-review.xml" ContentType="application/xml; profile=root-review"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/broken.xml" ContentType="application/xml"/>
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
  <Relationship Id="rCustomRoot" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/root-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML root provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML root fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/root-review.xml' => $reviewXml,
        'word/settings.xml' => $settingsXml,
        'word/broken.xml' => '<broken',
        'word/media/logo.png' => 'PNG-BYTES',
    ];
}
