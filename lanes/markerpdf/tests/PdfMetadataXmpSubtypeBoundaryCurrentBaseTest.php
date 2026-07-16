<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpSubtypeBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>XMP Subtype Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-subtype-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Subtype Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Subtype Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T10:42:03Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpSubtypeBoundaryPdf = static function (
    string $metadataDictionary,
    string $bodyText,
    string $extraObjects = ''
) use ($xmpSubtypeBoundaryPacket): string {
    $xmp = $xmpSubtypeBoundaryPacket(
        'Hidden Subtype Boundary XMP Title',
        'Ambiguous metadata stream subtype operands must not promote document XMP.',
        '2026-06-08T06:42:03-04:00'
    );
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP subtype boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Subtype Boundary Info Title) /Author (Info Subtype Boundary Author) /Producer (Info Subtype Boundary Producer) >>\nendobj\n"
        . $extraObjects
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects metadata streams missing the XML Subtype role before XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpSubtypeBoundaryPdf): void {
        $pdf = $xmpSubtypeBoundaryPdf(
            '/Type /Metadata',
            'XMP Missing Subtype Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Subtype Boundary Info Title', $metadata['title']);
        $t->same(['Info Subtype Boundary Author'], $metadata['authors']);
        $t->same('XMP Missing Subtype Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_missing_metadata_stream_subtype', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same(1, $review['type_entry_count'] ?? null);
        $t->same(0, $review['subtype_entry_count'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same(['Metadata'], $review['type_values'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-08T10:42:03Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Subtype Boundary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'xmp-subtype-boundary'));
        $t->true(!str_contains($plainText, 'Hidden Subtype Boundary XMP Title'));
    },
    'rejects non-name metadata stream Subtype operands before XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpSubtypeBoundaryPdf): void {
        $pdf = $xmpSubtypeBoundaryPdf(
            '/Type /Metadata /Subtype (XML literal decoy)',
            'XMP Non Name Subtype Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Subtype Boundary Info Title', $metadata['title']);
        $t->same('XMP Non Name Subtype Boundary Body', $plainText);
        $t->same('rejected_non_name_metadata_stream_subtype', $review['status'] ?? null);
        $t->same(1, $review['type_entry_count'] ?? null);
        $t->same(1, $review['subtype_entry_count'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same(['Metadata'], $review['type_values'] ?? null);
        $t->same('literal_string', $review['subtype_operand_type'] ?? null);
        $t->same('(XML literal decoy)', $review['subtype_operand_preview'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same('2026-06-08T10:42:03Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Subtype Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Hidden Subtype Boundary XMP Title'));
    },
    'rejects non XML metadata stream Subtype names before XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpSubtypeBoundaryPdf): void {
        $pdf = $xmpSubtypeBoundaryPdf(
            '/Type /Metadata /Subtype /text#2Fxml',
            'XMP Non XML Subtype Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Subtype Boundary Info Title', $metadata['title']);
        $t->same('XMP Non XML Subtype Boundary Body', $plainText);
        $t->same('rejected_non_xml_metadata_stream_subtype', $review['status'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same(['Metadata'], $review['type_values'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['text/xml'], $review['subtype_values'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same('2026-06-08T10:42:03Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Subtype Boundary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Ambiguous metadata stream subtype operands'));
        $t->true(!str_contains($plainText, 'Hidden Subtype Boundary XMP Title'));
    },
    'accepts indirect single XML Subtype helpers for document XMP' => static function (
        TestRunner $t
    ) use ($xmpSubtypeBoundaryPdf): void {
        $pdf = $xmpSubtypeBoundaryPdf(
            '/Type /Metadata /Subtype 7 0 R',
            'XMP Indirect XML Subtype Boundary Body',
            "7 0 obj\n/XML\nendobj\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Hidden Subtype Boundary XMP Title', $metadata['title']);
        $t->same('Ambiguous metadata stream subtype operands must not promote document XMP.', $metadata['description']);
        $t->same(['XMP Subtype Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-subtype-boundary'], $metadata['keywords']);
        $t->same('Subtype Boundary Tool', $metadata['creator_tool']);
        $t->same('Subtype Boundary Producer', $metadata['producer']);
        $t->same('2026-06-08T06:42:03-04:00', $metadata['created_at']);
        $t->same('2026-06-08T10:42:03Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T10:42:03Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Subtype Boundary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Indirect XML Subtype Boundary Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'rejected_non_name_metadata_stream_subtype'));
        $t->true(!str_contains($plainText, 'Hidden Subtype Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'xmp-subtype-boundary'));
    },
];
