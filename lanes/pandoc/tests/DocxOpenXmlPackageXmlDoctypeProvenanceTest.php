<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml doctypes without exposing declaration bytes' => static function (TestRunner $t): void {
        $parts = docx_package_xml_doctype_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $publicPart = $package['parts']['customXml/public-doctype.xml'];
        $systemPart = $package['parts']['customXml/system-doctype.xml'];
        $unclosedPart = $package['parts']['customXml/unclosed-doctype.xml'];
        $documentPart = $package['parts']['word/document.xml'];
        $binaryPart = $package['parts']['word/media/review.bin'];

        $t->same(3, $summary['partXmlDoctypePartCount']);
        $t->same(3, $summary['partXmlDoctypeCount']);
        $t->same(3, $summary['partXmlDoctypeExternalReferenceCount']);
        $t->same(1, $summary['partXmlDoctypeInternalSubsetCount']);
        $t->same(1, $summary['partXmlDoctypeEntityDeclarationCount']);
        $t->same(
            $publicPart['xmlDoctypeByteLength'] + $systemPart['xmlDoctypeByteLength'] + $unclosedPart['xmlDoctypeByteLength'],
            $summary['partXmlDoctypeByteLength'],
        );
        $t->same([
            'broken' => 1,
            'report' => 1,
            'review' => 1,
        ], $summary['partXmlDoctypeNameCounts']);
        $t->same([
            'xml-doctype-declaration' => 3,
            'xml-doctype-unclosed' => 1,
            'xml-entity-declaration' => 1,
            'xml-external-doctype-reference' => 3,
        ], $summary['partXmlDoctypeIssueCodeCounts']);
        $t->same([
            'customXml/public-doctype.xml',
            'customXml/system-doctype.xml',
            'customXml/unclosed-doctype.xml',
        ], $summary['partXmlDoctypePartNames']);
        $t->same(false, $summary['partXmlDoctypesTruncated']);

        $doctypeByPart = [];
        foreach ($summary['partXmlDoctypes'] as $doctype) {
            $doctypeByPart[$doctype['partName']] = $doctype;
        }

        $t->same(true, $publicPart['xmlDoctypePresent']);
        $t->same('report', $publicPart['xmlDoctypeName']);
        $t->same('-//Example//Review Package 1.0//EN', $publicPart['xmlDoctypePublicId']);
        $t->same('https://example.invalid/review-public.dtd', $publicPart['xmlDoctypeSystemId']);
        $t->same(true, $publicPart['xmlDoctypeInternalSubsetPresent']);
        $t->true($publicPart['xmlDoctypeInternalSubsetByteLength'] > 0, 'internal subset byte length should be recorded');
        $t->same(1, $publicPart['xmlDoctypeEntityDeclarationCount']);
        $t->same([
            'xml-doctype-declaration',
            'xml-external-doctype-reference',
            'xml-entity-declaration',
        ], $publicPart['xmlDoctypeIssueCodes']);
        $t->same(hash('sha256', docx_package_xml_doctype_declaration($parts['customXml/public-doctype.xml'])), $publicPart['xmlDoctypeSha256']);

        $t->same('customXml/public-doctype.xml', $doctypeByPart['customXml/public-doctype.xml']['partName']);
        $t->same('report', $doctypeByPart['customXml/public-doctype.xml']['name']);
        $t->same(true, $doctypeByPart['customXml/public-doctype.xml']['hasExternalReference']);
        $t->same(true, $doctypeByPart['customXml/public-doctype.xml']['internalSubsetPresent']);
        $t->same(1, $doctypeByPart['customXml/public-doctype.xml']['entityDeclarationCount']);
        $t->same('application/xml', $doctypeByPart['customXml/public-doctype.xml']['contentTypeBase']);
        $t->same('override', $doctypeByPart['customXml/public-doctype.xml']['contentTypeSource']);

        $t->same(true, $systemPart['xmlDoctypePresent']);
        $t->same('review', $systemPart['xmlDoctypeName']);
        $t->same(null, $systemPart['xmlDoctypePublicId']);
        $t->same('https://example.invalid/review-system.dtd', $systemPart['xmlDoctypeSystemId']);
        $t->same(false, $systemPart['xmlDoctypeInternalSubsetPresent']);
        $t->same(0, $systemPart['xmlDoctypeEntityDeclarationCount']);
        $t->same([
            'xml-doctype-declaration',
            'xml-external-doctype-reference',
        ], $systemPart['xmlDoctypeIssueCodes']);

        $t->same(true, $unclosedPart['xmlDoctypePresent']);
        $t->same('broken', $unclosedPart['xmlDoctypeName']);
        $t->same('https://example.invalid/broken.dtd', $unclosedPart['xmlDoctypeSystemId']);
        $t->same(false, $unclosedPart['xmlDoctypeInternalSubsetPresent']);
        $t->same([
            'xml-doctype-declaration',
            'xml-external-doctype-reference',
            'xml-doctype-unclosed',
        ], $unclosedPart['xmlDoctypeIssueCodes']);
        $t->same(hash('sha256', $parts['customXml/unclosed-doctype.xml']), $unclosedPart['xmlDoctypeSha256']);

        $t->same(false, $documentPart['xmlDoctypePresent']);
        $t->same(null, $documentPart['xmlDoctypeName']);
        $t->same([], $documentPart['xmlDoctypeIssueCodes']);
        $t->same(false, $binaryPart['xmlDoctypePresent']);
        $t->same(0, $binaryPart['xmlDoctypeByteLength']);

        $encodedDoctypeMetadata = json_encode([
            $publicPart,
            $systemPart,
            $unclosedPart,
            $summary['partXmlDoctypes'],
        ]);
        $t->true(is_string($encodedDoctypeMetadata), 'doctype metadata should encode for review');
        $t->true(!str_contains((string) $encodedDoctypeMetadata, 'hidden-doctype-payload'), 'raw internal subset text should not appear in package metadata');
        $t->true(!str_contains((string) $encodedDoctypeMetadata, '<!DOCTYPE'), 'raw doctype declaration should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_doctype_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/public-doctype.xml" ContentType="application/xml; profile=doctype-review"/>
  <Override PartName="/customXml/system-doctype.xml" ContentType="application/xml; profile=doctype-review"/>
  <Override PartName="/customXml/unclosed-doctype.xml" ContentType="application/xml; profile=doctype-review"/>
  <Override PartName="/word/media/review.bin" ContentType="application/octet-stream"/>
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
  <Relationship Id="rPublicDoctype" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/public-doctype.xml"/>
  <Relationship Id="rSystemDoctype" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/system-doctype.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML doctype provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML doctype fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/public-doctype.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE report PUBLIC "-//Example//Review Package 1.0//EN" "https://example.invalid/review-public.dtd" [
  <!ENTITY reviewSecret "hidden-doctype-payload-alpha">
]>
<report xmlns="urn:docx-doctype-review"><title>Public doctype</title></report>
XML,
        'customXml/system-doctype.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE review SYSTEM "https://example.invalid/review-system.dtd">
<review xmlns="urn:docx-doctype-review"><title>System doctype</title></review>
XML,
        'customXml/unclosed-doctype.xml' => <<<'XML'
<!DOCTYPE broken SYSTEM "https://example.invalid/broken.dtd"
XML,
        'word/media/review.bin' => <<<'BIN'
<!DOCTYPE ignored SYSTEM "hidden-doctype-payload-beta">
BIN,
    ];
}

function docx_package_xml_doctype_declaration(string $xml): string
{
    $start = strpos($xml, '<!DOCTYPE');
    if ($start === false) {
        return '';
    }

    $end = strpos($xml, ']>', $start);
    if ($end !== false) {
        return substr($xml, $start, $end - $start + 2);
    }

    $end = strpos($xml, '>', $start);
    return $end === false ? substr($xml, $start) : substr($xml, $start, $end - $start + 1);
}
