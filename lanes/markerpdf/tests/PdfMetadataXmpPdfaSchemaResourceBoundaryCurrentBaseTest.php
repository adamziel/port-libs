<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpPdfaSchemaResourceBoundarySchema = static function (
    string $schema,
    string $namespaceUri,
    string $prefix,
    string $propertyName
): string {
    return '<pdfaExtension:schemas>'
        . '<rdf:Bag>'
        . '<rdf:li rdf:parseType="Resource">'
        . '<pdfaSchema:schema>' . htmlspecialchars($schema, ENT_XML1) . '</pdfaSchema:schema>'
        . '<pdfaSchema:namespaceURI>' . htmlspecialchars($namespaceUri, ENT_XML1) . '</pdfaSchema:namespaceURI>'
        . '<pdfaSchema:prefix>' . htmlspecialchars($prefix, ENT_XML1) . '</pdfaSchema:prefix>'
        . '<pdfaSchema:property>'
        . '<rdf:Seq>'
        . '<rdf:li rdf:parseType="Resource">'
        . '<pdfaProperty:name>' . htmlspecialchars($propertyName, ENT_XML1) . '</pdfaProperty:name>'
        . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>'
        . '<pdfaProperty:category>external</pdfaProperty:category>'
        . '<pdfaProperty:description>WordPress import review field</pdfaProperty:description>'
        . '</rdf:li>'
        . '</rdf:Seq>'
        . '</pdfaSchema:property>'
        . '</rdf:li>'
        . '</rdf:Bag>'
        . '</pdfaExtension:schemas>';
};

$xmpPdfaSchemaResourceBoundaryPacket = static function (
    bool $documentSchemaReference
) use ($xmpPdfaSchemaResourceBoundarySchema): string {
    $privateSchema = $xmpPdfaSchemaResourceBoundarySchema(
        'Private Attachment Schema',
        'https://example.org/ns/private-attachment/1.0/',
        'privateattach',
        'privateAttachmentId'
    );

    $documentSchemaProperty = $documentSchemaReference
        ? '<pdfaExtension:schemas rdf:resource="#documentSchemaBag"/>'
        : '';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/"'
        . ' xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#"'
        . ' xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current PDF/A Schema Resource Boundary Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Document XMP metadata excludes private schema resources.</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-05T22:51:15Z</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T22:52:15Z</xmp:MetadataDate>'
        . $documentSchemaProperty
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#documentSchemaBag"'
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
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#privateAttachmentSchema"'
        . ' xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/"'
        . ' xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#"'
        . ' xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">'
        . $privateSchema
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpPdfaSchemaResourceBoundaryPdf = static function (string $metadataBytes, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP PDF/A schema resource-boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (PDF/A Schema Resource Info Title) /Author (Info Schema Resource Author) /Producer (Info Schema Resource Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'keeps unreferenced private XMP PDF/A schemas out of document metadata' => static function (
        TestRunner $t
    ) use ($xmpPdfaSchemaResourceBoundaryPacket, $xmpPdfaSchemaResourceBoundaryPdf): void {
        $pdf = $xmpPdfaSchemaResourceBoundaryPdf(
            $xmpPdfaSchemaResourceBoundaryPacket(false),
            'XMP PDF/A Schema Resource Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current PDF/A Schema Resource Boundary Title', $metadata['title']);
        $t->same('Document XMP metadata excludes private schema resources.', $metadata['description']);
        $t->same('2026-06-05T22:51:15Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T22:52:15Z', $metadata['metadata_date_utc']);
        $t->same('PDF/A Schema Resource Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP PDF/A Schema Resource Boundary Body', $plainText);
        $t->same(false, isset($metadata['pdfa_extension_schemas']));
        $t->same(false, isset($metadata['pdfa_xmp_associated_schema']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Attachment Schema'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'privateAttachmentId'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'https://example.org/ns/private-attachment/1.0/'));
        $t->true(!str_contains($plainText, 'Private Attachment Schema'));
    },
    'resolves document-level XMP PDF/A schema resource references without private schema leakage' => static function (
        TestRunner $t
    ) use ($xmpPdfaSchemaResourceBoundaryPacket, $xmpPdfaSchemaResourceBoundaryPdf): void {
        $pdf = $xmpPdfaSchemaResourceBoundaryPdf(
            $xmpPdfaSchemaResourceBoundaryPacket(true),
            'XMP PDF/A Schema Resource Reference Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $schemas = $metadata['pdfa_extension_schemas'] ?? [];

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current PDF/A Schema Resource Boundary Title', $metadata['title']);
        $t->same('XMP PDF/A Schema Resource Reference Body', $plainText);
        $t->same(1, count($schemas));
        $t->same('Document WordPress Import Schema', $schemas[0]['schema'] ?? null);
        $t->same('https://example.org/ns/document-import/1.0/', $schemas[0]['namespace_uri'] ?? null);
        $t->same('docimport', $schemas[0]['prefix'] ?? null);
        $t->same(['documentPostId'], $schemas[0]['property_names'] ?? []);
        $t->same('Text', $schemas[0]['properties'][0]['value_type'] ?? null);
        $t->same('external', $schemas[0]['properties'][0]['category'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Attachment Schema'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'privateAttachmentId'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'https://example.org/ns/private-attachment/1.0/'));
        $t->true(!str_contains($plainText, 'Document WordPress Import Schema'));
        $t->true(!str_contains($plainText, 'Private Attachment Schema'));
    },
];
