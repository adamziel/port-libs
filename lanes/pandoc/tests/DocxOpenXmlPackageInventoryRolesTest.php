<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package artifact inventory roles for reviewer handoff' => static function (TestRunner $t): void {
        $coreType = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
        $extendedType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
        $customType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
        $thumbnailType = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail';
        $originType = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
        $signatureType = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';

        $parts = docx_package_inventory_roles_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $thumbnail = $package['packageThumbnails']['byRelationshipId']['rPackageThumbnail'];
        $origin = $package['digitalSignatures']['byOriginRelationshipId']['rSignatureOrigin'];
        $signature = $package['digitalSignatures']['bySignatureRelationshipId']['rSignatureXml'];

        $t->true(in_array('root-relationship-target', $inventory['docProps/core.xml']['roles'], true), 'core properties root target role missing');
        $t->true(in_array('core-properties', $inventory['docProps/core.xml']['roles'], true), 'core properties inventory role missing');
        $t->true(in_array('root-relationship-target', $inventory['docProps/app.xml']['roles'], true), 'extended properties root target role missing');
        $t->true(in_array('extended-properties', $inventory['docProps/app.xml']['roles'], true), 'extended properties inventory role missing');
        $t->true(in_array('root-relationship-target', $inventory['docProps/custom.xml']['roles'], true), 'custom properties root target role missing');
        $t->true(in_array('custom-properties', $inventory['docProps/custom.xml']['roles'], true), 'custom properties inventory role missing');
        $t->true(in_array('root-relationship-target', $inventory['docProps/thumbnail.png']['roles'], true), 'thumbnail root target role missing');
        $t->true(in_array('package-thumbnail', $inventory['docProps/thumbnail.png']['roles'], true), 'package thumbnail inventory role missing');
        $t->true(in_array('root-relationship-target', $inventory['_xmlsignatures/origin.sigs']['roles'], true), 'signature origin root target role missing');
        $t->true(in_array('digital-signature-origin', $inventory['_xmlsignatures/origin.sigs']['roles'], true), 'digital signature origin inventory role missing');
        $t->true(in_array('relationship-target', $inventory['_xmlsignatures/sig1.xml']['roles'], true), 'signature XML relationship target role missing');
        $t->true(in_array('digital-signature-signature', $inventory['_xmlsignatures/sig1.xml']['roles'], true), 'digital signature XML inventory role missing');

        $t->same(1, $summary['roleCounts']['core-properties']);
        $t->same(1, $summary['roleCounts']['extended-properties']);
        $t->same(1, $summary['roleCounts']['custom-properties']);
        $t->same(1, $summary['roleCounts']['package-thumbnail']);
        $t->same(1, $summary['roleCounts']['digital-signature-origin']);
        $t->same(1, $summary['roleCounts']['digital-signature-signature']);
        $t->same(strlen($parts['docProps/core.xml']), $summary['roleByteLengths']['core-properties']);
        $t->same(strlen($parts['docProps/app.xml']), $summary['roleByteLengths']['extended-properties']);
        $t->same(strlen($parts['docProps/custom.xml']), $summary['roleByteLengths']['custom-properties']);
        $t->same(strlen($parts['docProps/thumbnail.png']), $summary['roleByteLengths']['package-thumbnail']);
        $t->same(strlen($parts['_xmlsignatures/origin.sigs']), $summary['roleByteLengths']['digital-signature-origin']);
        $t->same(strlen($parts['_xmlsignatures/sig1.xml']), $summary['roleByteLengths']['digital-signature-signature']);
        $t->same(['docProps/core.xml'], $summary['partNamesByRole']['core-properties']);
        $t->same(['docProps/app.xml'], $summary['partNamesByRole']['extended-properties']);
        $t->same(['docProps/custom.xml'], $summary['partNamesByRole']['custom-properties']);
        $t->same(['docProps/thumbnail.png'], $summary['partNamesByRole']['package-thumbnail']);
        $t->same(['_xmlsignatures/origin.sigs'], $summary['partNamesByRole']['digital-signature-origin']);
        $t->same(['_xmlsignatures/sig1.xml'], $summary['partNamesByRole']['digital-signature-signature']);
        $t->same('docProps/thumbnail.png', $summary['largestPartsByRole']['package-thumbnail']['partName'] ?? null);
        $t->same(strlen($parts['docProps/thumbnail.png']), $summary['largestPartsByRole']['package-thumbnail']['bytes'] ?? null);
        $t->same('_xmlsignatures/sig1.xml', $summary['deepestPartsByRole']['digital-signature-signature']['partName'] ?? null);
        $t->same(1, $summary['deepestPartsByRole']['digital-signature-signature']['directoryDepth'] ?? null);
        $t->same(['docProps' => 1], $summary['roleTopLevelSegmentCounts']['core-properties']);
        $t->same(['_xmlsignatures' => 1], $summary['roleTopLevelSegmentCounts']['digital-signature-signature']);
        $t->same(['override' => 1], $summary['roleContentTypeSourceCounts']['custom-properties']);
        $t->same(['default' => 1], $summary['roleContentTypeSourceCounts']['package-thumbnail']);
        $t->same(['image/png' => 1], $summary['roleContentTypeBaseCounts']['package-thumbnail']);

        $t->same(1, $summary['packageThumbnailCount']);
        $t->same(1, $summary['digitalSignatureOriginCount']);
        $t->same(1, $summary['digitalSignatureSignatureCount']);
        $t->same(1, $summary['relationshipTypeCounts'][$coreType]);
        $t->same(1, $summary['relationshipTypeCounts'][$extendedType]);
        $t->same(1, $summary['relationshipTypeCounts'][$customType]);
        $t->same(1, $summary['relationshipTypeCounts'][$thumbnailType]);
        $t->same(1, $summary['relationshipTypeCounts'][$originType]);
        $t->same(1, $summary['relationshipTypeCounts'][$signatureType]);

        $t->same(1, $package['relationshipTypes'][$coreType]['targetRoleCounts']['core-properties']);
        $t->same(1, $package['relationshipTypes'][$extendedType]['targetRoleCounts']['extended-properties']);
        $t->same(1, $package['relationshipTypes'][$customType]['targetRoleCounts']['custom-properties']);
        $t->same(1, $package['relationshipTypes'][$thumbnailType]['targetRoleCounts']['package-thumbnail']);
        $t->same(1, $package['relationshipTypes'][$originType]['targetRoleCounts']['digital-signature-origin']);
        $t->same(1, $package['relationshipTypes'][$signatureType]['targetRoleCounts']['digital-signature-signature']);
        $t->same('docProps/core.xml', $package['relationshipTypes'][$coreType]['largestExistingTargetPart']['partName']);
        $t->same('docProps/app.xml', $package['relationshipTypes'][$extendedType]['largestExistingTargetPart']['partName']);
        $t->same('docProps/custom.xml', $package['relationshipTypes'][$customType]['largestExistingTargetPart']['partName']);
        $t->same('docProps/thumbnail.png', $package['relationshipTypes'][$thumbnailType]['largestExistingTargetPart']['partName']);
        $t->same('_xmlsignatures/origin.sigs', $package['relationshipTypes'][$originType]['largestExistingTargetPart']['partName']);
        $t->same('_xmlsignatures/sig1.xml', $package['relationshipTypes'][$signatureType]['largestExistingTargetPart']['partName']);

        $t->same('docProps/thumbnail.png', $thumbnail['targetPart']);
        $t->same(false, $thumbnail['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-metadata-only', $thumbnail['reviewPolicy']);
        $t->same('_xmlsignatures/origin.sigs', $origin['targetPart']);
        $t->same('digital-signature-metadata-only', $origin['reviewPolicy']);
        $t->same('_xmlsignatures/sig1.xml', $signature['targetPart']);
        $t->same(false, $signature['cryptographicValidation']);
        $t->same('digital-signature-metadata-only', $signature['reviewPolicy']);
        $t->true(!isset($docx['media']['docProps/thumbnail.png']), 'package thumbnail bytes should not become document media');
        $t->true(!isset($docx['media']['_xmlsignatures/sig1.xml']), 'signature XML bytes should not become document media');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_inventory_roles_fixture_parts(): array
{
    $signatureXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>digest</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:SignatureValue>signature</ds:SignatureValue>
</ds:Signature>
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
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rExtended" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
  <Relationship Id="rCustom" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>
  <Relationship Id="rPackageThumbnail" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/thumbnail.png"/>
  <Relationship Id="rSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package artifact inventory role fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Inventory role fixture</dc:title>
  <dc:creator>Migration Editor</dc:creator>
</cp:coreProperties>
XML,
        'docProps/app.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
  <Application>Port Libs Test Fixture</Application>
</Properties>
XML,
        'docProps/custom.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="2" name="ReviewState"><vt:lpwstr>ready</vt:lpwstr></property>
</Properties>
XML,
        'docProps/thumbnail.png' => 'png thumbnail bytes',
        '_xmlsignatures/origin.sigs' => 'signature origin bytes',
        '_xmlsignatures/_rels/origin.sigs.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSignatureXml" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
</Relationships>
XML,
        '_xmlsignatures/sig1.xml' => $signatureXml,
    ];
}
