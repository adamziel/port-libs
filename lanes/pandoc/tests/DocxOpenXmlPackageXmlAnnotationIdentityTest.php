<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'records docx xml annotation identity mapped case count' => static function (TestRunner $t): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json') ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $manifest['mappedDocxXmlAnnotationIdentityCases'] ?? null);
        $t->same(67, $manifest['docxXmlAnnotationIdentityAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedDocxXmlAnnotationIdentityCases'] ?? null);
        $t->same(67, $manifest['benchmarkDenominator']['breakdown']['docxXmlAnnotationIdentityAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedDocxXmlAnnotationIdentityCases'] ?? null);
        $t->same(67, $manifest['benchmarkDenominator']['inventory']['docxXmlAnnotationIdentityAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedDocxXmlAnnotationIdentityCases'] ?? null);
        $t->same(67, $manifest['inventory']['docxXmlAnnotationIdentityAssertions'] ?? null);
    },

    'carries docx xml annotation rollups through package identity' => static function (TestRunner $t): void {
        $annotationValues = [
            'customCdata' => 'hidden-payload-alpha cdata',
            'settingsCdata' => 'hidden-payload-beta cdata',
            'customComment' => 'hidden-payload-gamma comment',
            'settingsComment' => 'hidden-payload-delta comment',
            'customPi' => 'state="draft" hidden-payload-epsilon',
            'settingsPi' => 'hidden-payload-zeta',
        ];
        $parts = docx_xml_annotation_identity_fixture_parts($annotationValues);
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $entries = docx_xml_annotation_identity_index_by_part($identity['packageEntries']);

        $repeatIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_xml_annotation_identity_fixture_parts($annotationValues))
            ->attr('docx')['packageIdentity'];
        $changedValues = $annotationValues;
        $changedValues['settingsPi'] = 'hidden-payload-zeta changed';
        $changedIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_xml_annotation_identity_fixture_parts($changedValues))
            ->attr('docx')['packageIdentity'];

        $t->same($identity, $docx['packageIdentity']);
        $t->same(2, $identity['partXmlCdataSectionPartCount']);
        $t->same(2, $identity['partXmlCdataSectionCount']);
        $t->same(2, $identity['partXmlCommentPartCount']);
        $t->same(2, $identity['partXmlCommentCount']);
        $t->same(2, $identity['partXmlProcessingInstructionPartCount']);
        $t->same(2, $identity['partXmlProcessingInstructionCount']);

        foreach (docx_xml_annotation_identity_summary_keys() as $key) {
            $t->same($summary[$key], $identity[$key], "identity mirrors {$key}");
        }

        $entryMirrorKeys = [
            'xmlCdataSectionCount',
            'xmlCdataSectionByteLength',
            'xmlCdataSections',
            'xmlCdataSectionsTruncated',
            'xmlCommentCount',
            'xmlCommentByteLength',
            'xmlComments',
            'xmlCommentsTruncated',
            'xmlProcessingInstructionCount',
            'xmlProcessingInstructionDataByteLength',
            'xmlProcessingInstructionTargets',
            'xmlProcessingInstructions',
            'xmlProcessingInstructionsTruncated',
        ];
        foreach (['customXml/annotation-review.xml', 'word/settings.xml'] as $partName) {
            foreach ($entryMirrorKeys as $key) {
                $t->same($package['parts'][$partName][$key], $entries[$partName][$key], "{$partName} {$key}");
            }
        }

        $t->same('/review:packet/review:raw', $identity['partXmlCdataSections'][0]['parentPath']);
        $t->same('/review:packet/review:value', $identity['partXmlComments'][0]['parentPath']);
        $t->same(['annotation-state', 'settings-state'], $identity['partXmlProcessingInstructionTargets']);

        $identityJson = json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $t->true(is_string($identityJson), 'identity metadata should encode for review');
        $t->true(!str_contains($identityJson, 'hidden-payload'), 'raw XML annotation payloads should not appear in package identity');
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);
    },
];

/**
 * @return list<string>
 */
function docx_xml_annotation_identity_summary_keys(): array
{
    return [
        'partXmlCdataSectionPartCount',
        'partXmlCdataSectionCount',
        'partXmlCdataSectionByteLength',
        'partXmlCdataSectionPartNames',
        'partXmlCdataSections',
        'partXmlCdataSectionsTruncated',
        'partXmlCommentPartCount',
        'partXmlCommentCount',
        'partXmlCommentByteLength',
        'partXmlCommentPartNames',
        'partXmlComments',
        'partXmlCommentsTruncated',
        'partXmlProcessingInstructionPartCount',
        'partXmlProcessingInstructionCount',
        'partXmlProcessingInstructionDataByteLength',
        'partXmlProcessingInstructionTargets',
        'partXmlProcessingInstructionPartNames',
        'partXmlProcessingInstructions',
        'partXmlProcessingInstructionsTruncated',
    ];
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array<string, array<string, mixed>>
 */
function docx_xml_annotation_identity_index_by_part(array $entries): array
{
    $byPart = [];
    foreach ($entries as $entry) {
        if (is_array($entry) && is_string($entry['partName'] ?? null)) {
            $byPart[$entry['partName']] = $entry;
        }
    }

    return $byPart;
}

/**
 * @param array<string, string> $values
 * @return array<string, string>
 */
function docx_xml_annotation_identity_fixture_parts(array $values): array
{
    $customXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<review:packet xmlns:review="urn:docx-xml-annotation-identity">
  <review:raw><![CDATA[{$values['customCdata']}]]></review:raw>
  <review:value><!--{$values['customComment']}--><?annotation-state {$values['customPi']}?>safe</review:value>
</review:packet>
XML;
    $settingsXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <?settings-state {$values['settingsPi']}?>
  <w:docVars>
    <w:docVar w:name="Review"><!--{$values['settingsComment']}--><![CDATA[{$values['settingsCdata']}]]></w:docVar>
  </w:docVars>
</w:settings>
XML;

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/annotation-review.xml" ContentType="application/xml; profile=annotation-review"/>
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
  <Relationship Id="rCustomAnnotation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/annotation-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML annotation identity fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML annotation identity fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/annotation-review.xml' => $customXml,
        'word/settings.xml' => $settingsXml,
    ];
}
