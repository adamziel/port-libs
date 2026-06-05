<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$documentSchema = '<rdf:Description rdf:about="#documentSchemaBag"'
    . ' xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#"'
    . ' xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">'
    . '<rdf:Bag><rdf:li rdf:parseType="Resource">'
    . '<pdfaSchema:schema>Document WordPress Import Schema</pdfaSchema:schema>'
    . '<pdfaSchema:namespaceURI>https://example.org/ns/document-import/1.0/</pdfaSchema:namespaceURI>'
    . '<pdfaSchema:prefix>docimport</pdfaSchema:prefix>'
    . '<pdfaSchema:property><rdf:Seq><rdf:li rdf:parseType="Resource">'
    . '<pdfaProperty:name>documentPostId</pdfaProperty:name>'
    . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>'
    . '<pdfaProperty:category>external</pdfaProperty:category>'
    . '<pdfaProperty:description>Document schema field</pdfaProperty:description>'
    . '</rdf:li></rdf:Seq></pdfaSchema:property>'
    . '</rdf:li></rdf:Bag>'
    . '</rdf:Description>';

$privateSchema = '<rdf:Description rdf:about="#privateAttachmentSchema"'
    . ' xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/"'
    . ' xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#"'
    . ' xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">'
    . '<pdfaExtension:schemas><rdf:Bag><rdf:li rdf:parseType="Resource">'
    . '<pdfaSchema:schema>Private Attachment Schema</pdfaSchema:schema>'
    . '<pdfaSchema:namespaceURI>https://example.org/ns/private-attachment/1.0/</pdfaSchema:namespaceURI>'
    . '<pdfaSchema:prefix>privateattach</pdfaSchema:prefix>'
    . '<pdfaSchema:property><rdf:Seq><rdf:li rdf:parseType="Resource">'
    . '<pdfaProperty:name>privateAttachmentId</pdfaProperty:name>'
    . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>'
    . '<pdfaProperty:category>external</pdfaProperty:category>'
    . '<pdfaProperty:description>Private attachment schema field</pdfaProperty:description>'
    . '</rdf:li></rdf:Seq></pdfaSchema:property>'
    . '</rdf:li></rdf:Bag></pdfaExtension:schemas>'
    . '</rdf:Description>';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
    . ' xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current PDF/A Schema Resource Boundary Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Document XMP metadata excludes private schema resources.</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-05T22:51:15Z</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T22:52:15Z</xmp:MetadataDate>'
    . '<pdfaExtension:schemas rdf:resource="#documentSchemaBag"/>'
    . '</rdf:Description>'
    . $documentSchema
    . $privateSchema
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP PDF/A schema resource-boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP PDF/A Schema Resource Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (PDF/A Schema Resource Info Title) /Author (Info Schema Resource Author) /Producer (Info Schema Resource Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$schemas = $metadata['pdfa_extension_schemas'] ?? [];

if (($metadata['title'] ?? null) !== 'Current PDF/A Schema Resource Boundary Title') {
    throw new RuntimeException('Expected document XMP title to be promoted.');
}
if (count($schemas) !== 1 || ($schemas[0]['schema'] ?? null) !== 'Document WordPress Import Schema') {
    throw new RuntimeException('Expected only the document-level PDF/A extension schema to be promoted.');
}
if (!is_string($encoded) || str_contains($encoded, 'Private Attachment Schema') || str_contains($encoded, 'privateAttachmentId')) {
    throw new RuntimeException('Private XMP schema resource leaked into document metadata.');
}
if (str_contains($plainText, 'Document WordPress Import Schema') || str_contains($plainText, 'Private Attachment Schema')) {
    throw new RuntimeException('XMP schema text leaked into visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-pdfa-schema-resource-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-pdfa-schema-resource-boundary',
    'native_boundary' => 'Root XMP PDF/A schemas are read only from document-level XMP properties; unreferenced private RDF resources remain excluded',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'schema_count' => count($schemas),
    'schema_names' => array_values(array_filter(array_map(
        static fn (mixed $row): ?string => is_array($row) && is_string($row['schema'] ?? null) ? $row['schema'] : null,
        $schemas
    ))),
    'private_schema_promoted' => is_string($encoded) && str_contains($encoded, 'Private Attachment Schema'),
    'private_schema_visible_text' => str_contains($plainText, 'Private Attachment Schema'),
    'executes_ocr_or_models' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) ($metadata['title'] ?? 'Untitled PDF'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:pdfa-extension-schema-review ' . $htmlJson([
    'schemas' => $schemas,
    'payload_included' => false,
    'visible_text_source' => false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
