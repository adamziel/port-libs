<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpPdfSchemaPacket = static function (
    string $title,
    string $producer,
    string $keywords,
    string $pdfVersion,
    string $trapped
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<pdf:Producer>' . htmlspecialchars($producer, ENT_XML1) . '</pdf:Producer>'
        . '<pdf:Keywords>' . htmlspecialchars($keywords, ENT_XML1) . '</pdf:Keywords>'
        . '<pdf:PDFVersion>' . htmlspecialchars($pdfVersion, ENT_XML1) . '</pdf:PDFVersion>'
        . '<pdf:Trapped>' . htmlspecialchars($trapped, ENT_XML1) . '</pdf:Trapped>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpPdfSchemaPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP PDF schema boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Info PDF Schema Title) /Keywords (info, stale) /Producer (Info PDF Schema Producer) /Trapped /False >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts PDF namespace XMP scalars as review metadata before stale Info' => static function (
        TestRunner $t
    ) use ($xmpPdfSchemaPacket, $xmpPdfSchemaPdf): void {
        $xmp = $xmpPdfSchemaPacket(
            'Current PDF Schema XMP Title',
            'PDF Schema XMP Producer',
            'wordpress, xmp-pdf-schema; import-review',
            '1.7',
            'unknown'
        );
        $pdf = $xmpPdfSchemaPdf(
            $xmp,
            '/Type /Metadata /Subtype /XML',
            'XMP PDF Schema Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $pdfSchema = $metadata['xmp_pdf'] ?? [];

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current PDF Schema XMP Title', $metadata['title']);
        $t->same('PDF Schema XMP Producer', $metadata['producer']);
        $t->same(['wordpress', 'xmp-pdf-schema', 'import-review'], $metadata['keywords']);
        $t->same('Info PDF Schema Title', $metadata['info']['Title'] ?? null);
        $t->same('Info PDF Schema Producer', $metadata['info']['Producer'] ?? null);
        $t->same('XMP PDF Schema Boundary Body', $plainText);

        $t->same('xmp_pdf', $pdfSchema['source'] ?? null);
        $t->same(true, $pdfSchema['review_only'] ?? null);
        $t->same(false, $pdfSchema['payload_included'] ?? null);
        $t->same('PDF Schema XMP Producer', $pdfSchema['producer'] ?? null);
        $t->same(['wordpress', 'xmp-pdf-schema', 'import-review'], $pdfSchema['keywords'] ?? null);
        $t->same(3, $pdfSchema['keyword_count'] ?? null);
        $t->same('1.7', $pdfSchema['pdf_version'] ?? null);
        $t->same('unknown', $pdfSchema['trapped'] ?? null);
        $t->same('Unknown', $pdfSchema['trapped_normalized'] ?? null);
        $t->same($pdfSchema, $metadata['xmp']['pdf_schema'] ?? null);
        $t->true(!str_contains($plainText, 'Current PDF Schema XMP Title'));
        $t->true(!str_contains($plainText, 'PDF Schema XMP Producer'));
    },
    'summarizes rejected PDF namespace XMP streams without exposing text values' => static function (
        TestRunner $t
    ) use ($xmpPdfSchemaPacket, $xmpPdfSchemaPdf): void {
        $xmp = $xmpPdfSchemaPacket(
            'Rejected PDF Schema XMP Title',
            'Rejected PDF Schema Producer',
            'wordpress, rejected-pdf-schema',
            '2.0',
            'True'
        );
        $pdf = $xmpPdfSchemaPdf(
            $xmp,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP PDF Schema Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->true(!isset($metadata['xmp_pdf']));
        $t->same('Info PDF Schema Title', $metadata['title']);
        $t->same(['info', 'stale'], $metadata['keywords']);
        $t->same('Rejected XMP PDF Schema Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(['title', 'producer', 'keywords', 'pdf_schema'], $summary['field_names'] ?? null);
        $t->same(['producer', 'keywords', 'pdf_version', 'trapped'], $summary['pdf_schema_field_names'] ?? null);
        $t->same(2, $summary['pdf_schema_keyword_count'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(in_array('pdf_schema', $summary['redacted_fields'] ?? [], true));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected PDF Schema XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected PDF Schema Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'rejected-pdf-schema'));
        $t->true(is_string($encoded) && !str_contains($encoded, '2.0'));
        $t->true(!str_contains($plainText, 'Rejected PDF Schema XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected PDF Schema Producer'));
    },
];
