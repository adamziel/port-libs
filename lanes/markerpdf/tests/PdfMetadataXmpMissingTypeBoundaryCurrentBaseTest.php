<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpMissingTypeBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Missing Type Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-missing-type-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Missing Type Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Missing Type Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-07T22:28:13Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpMissingTypeBoundaryPdf = static function (
    string $xmp,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP missing Type boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Missing Type Info Title) /Author (Info Missing Type Author) /Producer (Info Missing Type Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects catalog XMP streams that omit the Metadata Type role' => static function (
        TestRunner $t
    ) use ($xmpMissingTypeBoundaryPacket, $xmpMissingTypeBoundaryPdf): void {
        $xmp = $xmpMissingTypeBoundaryPacket(
            'Missing Type Hidden XMP Title',
            'A generic XML stream without /Type /Metadata must stay review-only.',
            '2026-06-07T18:28:13-04:00'
        );
        $pdf = $xmpMissingTypeBoundaryPdf(
            $xmp,
            '/Subtype /XML',
            'XMP Missing Type Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Missing Type Info Title', $metadata['title']);
        $t->same(['Info Missing Type Author'], $metadata['authors']);
        $t->same('XMP Missing Type Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_missing_metadata_stream_type', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same(0, $review['type_entry_count'] ?? null);
        $t->same(1, $review['subtype_entry_count'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['XML'], $review['subtype_values'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-07T22:28:13Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-07T22:28:13Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Missing Type Hidden XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'xmp-missing-type-boundary'));
        $t->true(!str_contains($plainText, 'Missing Type Hidden XMP Title'));
        $t->true(!str_contains($plainText, 'xmp-missing-type-boundary'));
    },
    'rejects catalog XMP streams whose Type role is not a PDF name' => static function (
        TestRunner $t
    ) use ($xmpMissingTypeBoundaryPacket, $xmpMissingTypeBoundaryPdf): void {
        $xmp = $xmpMissingTypeBoundaryPacket(
            'Literal Type Hidden XMP Title',
            'A literal Type decoy must not promote a generic XML stream.',
            '2026-06-07T22:29:13Z'
        );
        $pdf = $xmpMissingTypeBoundaryPdf(
            $xmp,
            '/Type (Metadata literal decoy) /Subtype /XML',
            'XMP Literal Type Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Missing Type Info Title', $metadata['title']);
        $t->same('XMP Literal Type Boundary Body', $plainText);
        $t->same('rejected_non_name_metadata_stream_type', $review['status'] ?? null);
        $t->same(1, $review['type_entry_count'] ?? null);
        $t->same(1, $review['subtype_entry_count'] ?? null);
        $t->same('literal_string', $review['type_operand_type'] ?? null);
        $t->same('(Metadata literal decoy)', $review['type_operand_preview'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['XML'], $review['subtype_values'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same('2026-06-07T22:29:13Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Literal Type Hidden XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'A literal Type decoy must not promote'));
        $t->true(!str_contains($plainText, 'Literal Type Hidden XMP Title'));
    },
    'accepts escaped Metadata Type and XML Subtype names for document XMP' => static function (
        TestRunner $t
    ) use ($xmpMissingTypeBoundaryPacket, $xmpMissingTypeBoundaryPdf): void {
        $xmp = $xmpMissingTypeBoundaryPacket(
            'Escaped Metadata Type XMP Title',
            'Escaped Type and Subtype names remain valid document metadata.',
            '2026-06-07T22:30:13Z'
        );
        $pdf = $xmpMissingTypeBoundaryPdf(
            $xmp,
            '/Ty#70e /Metadata /Sub#74ype /XML',
            'XMP Escaped Type Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Escaped Metadata Type XMP Title', $metadata['title']);
        $t->same('Escaped Type and Subtype names remain valid document metadata.', $metadata['description']);
        $t->same(['Missing Type Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-missing-type-boundary'], $metadata['keywords']);
        $t->same('Missing Type Boundary Tool', $metadata['creator_tool']);
        $t->same('Missing Type Boundary Producer', $metadata['producer']);
        $t->same('2026-06-07T22:30:13Z', $metadata['created_at']);
        $t->same('2026-06-07T22:30:13Z', $metadata['created_at_utc']);
        $t->same('2026-06-07T22:28:13Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Missing Type Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Escaped Type Boundary Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'rejected_missing_metadata_stream_type'));
        $t->true(!str_contains($plainText, 'Escaped Metadata Type XMP Title'));
        $t->true(!str_contains($plainText, 'xmp-missing-type-boundary'));
    },
];
