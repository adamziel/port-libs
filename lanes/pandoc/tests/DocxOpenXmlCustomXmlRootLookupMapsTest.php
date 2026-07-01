<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX custom XML root lookup maps for package review' => static function (TestRunner $t): void {
        $parts = docx_custom_xml_root_lookup_maps_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $customXml = $document->attr('docx')['customXmlParts'];
        $summary = $document->attr('docx')['packageProvenance']['summary'];

        $t->same(3, $customXml['count']);
        $t->same([
            'audit' => 1,
            'copy:invoice' => 1,
            'inv:invoice' => 1,
        ], $customXml['rootNameCounts']);
        $t->same([
            'urn:example:audit' => 1,
            'urn:example:invoice' => 2,
        ], $customXml['rootNamespaceCounts']);
        $t->same([
            'customXml/invoice.xml',
        ], $customXml['partNamesByRootName']['inv:invoice']);
        $t->same([
            'customXml/invoice-copy.xml',
        ], $customXml['partNamesByRootName']['copy:invoice']);
        $t->same([
            'customXml/audit.xml',
        ], $customXml['partNamesByRootName']['audit']);
        $t->same([
            'customXml/invoice-copy.xml',
            'customXml/invoice.xml',
        ], $customXml['partNamesByRootNamespace']['urn:example:invoice']);
        $t->same([
            'customXml/audit.xml',
        ], $customXml['partNamesByRootNamespace']['urn:example:audit']);

        $t->same($customXml['rootNameCounts'], $summary['customXmlRootNameCounts']);
        $t->same($customXml['rootNamespaceCounts'], $summary['customXmlRootNamespaceCounts']);
        $t->same($customXml['partNamesByRootName'], $summary['customXmlPartNamesByRootName']);
        $t->same($customXml['partNamesByRootNamespace'], $summary['customXmlPartNamesByRootNamespace']);
        $t->same('urn:example:invoice', $customXml['byRelationshipId']['rInvoice']['rootNamespace']);
        $t->same('invoice', $customXml['byRelationshipId']['rInvoiceCopy']['rootLocalName']);
        $t->true(
            in_array('custom-xml-part', $document->attr('docx')['packageProvenance']['parts']['customXml/invoice.xml']['roles'], true),
            'custom XML root lookup part should retain the custom XML inventory role'
        );
    },
];

/**
 * @return array<string, string>
 */
function docx_custom_xml_root_lookup_maps_fixture_parts(): array
{
    $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
    $customXmlPropsRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps';

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/invoice-props.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/customXml/invoice-copy-props.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/customXml/audit-props.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rInvoice" Type="{$customXmlRel}" Target="../customXml/invoice.xml"/>
  <Relationship Id="rInvoiceCopy" Type="{$customXmlRel}" Target="../customXml/invoice-copy.xml"/>
  <Relationship Id="rAudit" Type="{$customXmlRel}" Target="../customXml/audit.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Custom XML root lookup maps fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'customXml/invoice.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<inv:invoice xmlns:inv="urn:example:invoice" xmlns:common="urn:example:common" status="draft">Alpha</inv:invoice>
XML,
        'customXml/invoice-copy.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<copy:invoice xmlns:copy="urn:example:invoice">Beta</copy:invoice>
XML,
        'customXml/audit.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<audit xmlns="urn:example:audit">Audit</audit>
XML,
        'customXml/_rels/invoice.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rInvoiceProps" Type="{$customXmlPropsRel}" Target="invoice-props.xml"/>
</Relationships>
XML,
        'customXml/_rels/invoice-copy.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rInvoiceCopyProps" Type="{$customXmlPropsRel}" Target="invoice-copy-props.xml"/>
</Relationships>
XML,
        'customXml/_rels/audit.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rAuditProps" Type="{$customXmlPropsRel}" Target="audit-props.xml"/>
</Relationships>
XML,
        'customXml/invoice-props.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{11111111-2222-3333-4444-555555555555}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml"/>
XML,
        'customXml/invoice-copy-props.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{66666666-7777-8888-9999-aaaaaaaaaaaa}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml"/>
XML,
        'customXml/audit-props.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{bbbbbbbb-cccc-dddd-eeee-ffffffffffff}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml"/>
XML,
    ];
}
