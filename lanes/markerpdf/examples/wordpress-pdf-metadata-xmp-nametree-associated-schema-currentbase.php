<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$rootXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
    . ' xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/"'
    . ' xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#"'
    . ' xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">XMP NameTree Associated Schema Import</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-02T22:28:00Z</xmp:CreateDate>'
    . '<pdfaExtension:schemas>'
    . '<rdf:Bag>'
    . '<rdf:li rdf:parseType="Resource">'
    . '<pdfaSchema:schema>WordPress Import Extension Schema</pdfaSchema:schema>'
    . '<pdfaSchema:namespaceURI>https://example.org/ns/wp-import/1.0/</pdfaSchema:namespaceURI>'
    . '<pdfaSchema:prefix>wpimport</pdfaSchema:prefix>'
    . '<pdfaSchema:property>'
    . '<rdf:Seq>'
    . '<rdf:li rdf:parseType="Resource">'
    . '<pdfaProperty:name>wpPostId</pdfaProperty:name>'
    . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>'
    . '<pdfaProperty:category>external</pdfaProperty:category>'
    . '</rdf:li>'
    . '<rdf:li rdf:parseType="Resource">'
    . '<pdfaProperty:name>wpSourceChecksum</pdfaProperty:name>'
    . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>'
    . '<pdfaProperty:category>external</pdfaProperty:category>'
    . '</rdf:li>'
    . '</rdf:Seq>'
    . '</pdfaSchema:property>'
    . '</rdf:li>'
    . '</rdf:Bag>'
    . '</pdfaExtension:schemas>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$fileXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Schema Attachment XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$rootXmpStream = gzcompress($rootXmp);
$fileXmpStream = gzcompress($fileXmp);
$rootProfile = 'XMP name-tree associated schema root ICC bytes';
$schemaProfile = 'XMP name-tree associated schema attachment ICC bytes';
$rootProfileStream = gzcompress($rootProfile);
$schemaProfileStream = gzcompress($schemaProfile);
if (!is_string($rootXmpStream) || !is_string($fileXmpStream) || !is_string($rootProfileStream) || !is_string($schemaProfileStream)) {
    throw new RuntimeException('Unable to compress XMP associated schema smoke streams.');
}

$sourcePayload = '<wp-export><post id="xmp-associated-schema-smoke"/></wp-export>';
$schemaPayload = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" targetNamespace="https://example.org/ns/wp-import/1.0/"/>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$schemaChecksum = strtoupper(hash('md5', $schemaPayload));
$content = 'BT /F1 12 Tf 72 720 Td (XMP Associated Schema Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /Names << /EmbeddedFiles 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($schemaProfileStream) . " >>\nstream\n{$schemaProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (XMP Schema Root PDF/A) /Info (Root PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (XMP Schema Attachment PDF/A) /Info (Schema attachment profile) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Type /Filespec /F (schema.xsd) /Desc (XMP extension schema attachment) /AFRelationship /Schema /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 15 0 R >> >>\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size " . strlen($schemaPayload) . " /CheckSum <{$schemaChecksum}> >> /Length " . strlen($schemaPayload) . " >>\nstream\n{$schemaPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Names [(source.xml) 10 0 R (schema.xsd) 14 0 R] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$schemaSummary = $metadata['pdfa_xmp_associated_schema'] ?? [];
$schemaFile = $schemaSummary['associated_schema_files'][0] ?? [];

if (($metadata['title'] ?? null) !== 'XMP NameTree Associated Schema Import') {
    throw new RuntimeException('Expected root XMP title to remain authoritative.');
}
if (($schemaSummary['source'] ?? null) !== 'pdfa_xmp_associated_schema') {
    throw new RuntimeException('Expected XMP-associated schema summary.');
}
if (($schemaSummary['xmp_schema_property_names'] ?? []) !== ['wpPostId', 'wpSourceChecksum']) {
    throw new RuntimeException('Expected XMP extension schema property names.');
}
if (($schemaFile['relationship_role'] ?? null) !== 'schema_definition' || ($schemaFile['payload']['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected schema FileSpec checksum review metadata.');
}
if (!is_string($encoded) || str_contains($encoded, $sourcePayload) || str_contains($encoded, $schemaPayload) || str_contains($encoded, $schemaProfile) || str_contains($encoded, 'Hidden Schema Attachment XMP Title')) {
    throw new RuntimeException('Expected associated schema payloads and attachment-local XMP to remain review-only.');
}
if ($plainText !== 'XMP Associated Schema Body') {
    throw new RuntimeException('Expected visible WordPress text to come only from page content.');
}

echo '<!-- markerpdf-xmp-nametree-associated-schema-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-nametree-associated-schema-review',
    'native_boundary' => 'root XMP PDF/A extension schema declarations are correlated with /Names /EmbeddedFiles schema FileSpecs before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'document_title' => $metadata['title'] ?? null,
    'root_pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'xmp_schema_namespaces' => $schemaSummary['xmp_schema_namespaces'] ?? [],
    'xmp_schema_property_names' => $schemaSummary['xmp_schema_property_names'] ?? [],
    'associated_schema_file_names' => $schemaSummary['associated_schema_file_names'] ?? [],
    'associated_schema_file_sources' => $schemaSummary['associated_schema_file_sources'] ?? [],
    'schema_payload_omitted' => is_string($encoded) && !str_contains($encoded, $schemaPayload),
    'attachment_xmp_payload_omitted' => is_string($encoded) && !str_contains($encoded, 'Hidden Schema Attachment XMP Title'),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:pdfa-xmp-associated-schema-file ' . $htmlJson([
    'filename' => $schemaFile['filename'] ?? null,
    'relationship' => $schemaFile['relationship'] ?? null,
    'relationship_role' => $schemaFile['relationship_role'] ?? null,
    'payload' => $schemaFile['payload'] ?? [],
    'xmp_metadata' => $schemaFile['xmp_metadata'] ?? [],
    'attachment_pdfa_output_intents' => $schemaFile['attachment_pdfa_output_intents'] ?? [],
]) . " -->\n";
