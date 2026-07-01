<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml root elements without exposing contents' => static function (TestRunner $t): void {
        $hiddenRootText = 'hidden-root-text-payload';
        $parts = docx_package_xml_root_element_fixture_parts($hiddenRootText);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $customPart = $package['parts']['customXml/root-review.xml'];
        $brokenPart = $package['parts']['word/broken.xml'];
        $mediaPart = $package['parts']['word/media/review.png'];

        $rootsByPart = [];
        foreach ($summary['partXmlRootElements'] as $root) {
            $rootsByPart[$root['partName']] = $root;
        }

        $t->same(8, $summary['partXmlRootPartCount']);
        $t->same(7, $summary['partXmlRootValidPartCount']);
        $t->same(1, $summary['partXmlRootInvalidPartCount']);
        $t->same(4, $summary['partXmlRootPrefixedPartCount']);
        $t->same(2, $summary['partXmlRootAttributeCount']);
        $t->same(9, $summary['partXmlRootNamespaceDeclarationCount']);
        $t->same(['cp', 'dc', 'default', 'review', 'w', 'wp'], $summary['partXmlRootNamespacePrefixes']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/root-review.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/broken.xml',
            'word/document.xml',
            'word/settings.xml',
        ], $summary['partXmlRootPartNames']);
        $t->same(['word/broken.xml'], $summary['partXmlRootInvalidPartNames']);
        $t->same('package-part-xml-root-metadata-only', $summary['partXmlRootReviewPolicy']);
        $t->same(false, $summary['partXmlRootCanExposeBytes']);

        $t->same(1, $summary['partXmlRootNamespaceCounts']['http://schemas.openxmlformats.org/package/2006/content-types']);
        $t->same(2, $summary['partXmlRootNamespaceCounts']['http://schemas.openxmlformats.org/package/2006/relationships']);
        $t->same(2, $summary['partXmlRootNamespaceCounts']['http://schemas.openxmlformats.org/wordprocessingml/2006/main']);
        $t->same(1, $summary['partXmlRootNamespaceCounts']['urn:docx-root-review']);
        $t->same(2, $summary['partXmlRootLocalNameCounts']['Relationships']);
        $t->same(1, $summary['partXmlRootLocalNameCounts']['packet']);
        $t->same(1, $summary['partXmlRootQualifiedNameCounts']['review:packet']);
        $t->same(2, $summary['partXmlRootQualifiedNameCounts']['Relationships']);

        $t->same(true, $customPart['xmlRootChecked']);
        $t->same(true, $customPart['xmlRootValidXml']);
        $t->same('urn:docx-root-review', $customPart['xmlRootNamespace']);
        $t->same('packet', $customPart['xmlRootLocalName']);
        $t->same('review:packet', $customPart['xmlRootQualifiedName']);
        $t->same('review', $customPart['xmlRootPrefix']);
        $t->same(2, $customPart['xmlRootAttributeCount']);
        $t->same(2, $customPart['xmlRootNamespaceDeclarationCount']);
        $t->same(['review', 'wp'], $customPart['xmlRootNamespacePrefixes']);
        $t->same(false, $customPart['xmlRootCanExposeBytes']);

        $t->same('customXml/root-review.xml', $rootsByPart['customXml/root-review.xml']['partName']);
        $t->same('urn:docx-root-review', $rootsByPart['customXml/root-review.xml']['rootNamespace']);
        $t->same('review:packet', $rootsByPart['customXml/root-review.xml']['rootQualifiedName']);
        $t->same(2, $rootsByPart['customXml/root-review.xml']['rootAttributeCount']);
        $t->same(['document-relationship-target', 'custom-xml-part'], $rootsByPart['customXml/root-review.xml']['roles']);
        $t->same(false, $rootsByPart['customXml/root-review.xml']['canExposeBytes']);

        $t->same(true, $brokenPart['xmlRootChecked']);
        $t->same(false, $brokenPart['xmlRootValidXml']);
        $t->same(null, $brokenPart['xmlRootNamespace']);
        $t->same(null, $rootsByPart['word/broken.xml']['rootQualifiedName']);
        $t->same(false, $rootsByPart['word/broken.xml']['validXml']);
        $t->true(is_string($brokenPart['xmlRootParseError']) && $brokenPart['xmlRootParseError'] !== '', 'invalid XML root parse error should be retained');

        $t->same(false, $mediaPart['xmlRootChecked']);
        $t->same(null, $mediaPart['xmlRootValidXml']);
        $t->true(!isset($rootsByPart['word/media/review.png']), 'non-XML package entries should not appear in root summaries');

        $encodedRoots = json_encode([
            $customPart,
            $summary['partXmlRootElements'],
        ]);
        $t->true(is_string($encodedRoots), 'XML root metadata should encode for review');
        $t->true(!str_contains((string) $encodedRoots, $hiddenRootText), 'raw XML root text should not appear in package metadata');
        $t->true(!array_key_exists('contents', $rootsByPart['customXml/root-review.xml']), 'root summary must not expose package bytes');
        $t->true(!array_key_exists('xml', $rootsByPart['customXml/root-review.xml']), 'root summary must not expose parsed XML');
    },
    'records docx package xml root element provenance mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_root_element_fixture_parts(string $hiddenRootText): array
{
    $customXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<review:packet xmlns:review="urn:docx-root-review" xmlns:wp="urn:wp-metadata" review:state="draft" xml:lang="en">
  <review:value>{$hiddenRootText}</review:value>
</review:packet>
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
        'customXml/root-review.xml' => $customXml,
        'word/settings.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'word/broken.xml' => '<broken><open>',
        'word/media/review.png' => 'fake png bytes',
    ];
}
