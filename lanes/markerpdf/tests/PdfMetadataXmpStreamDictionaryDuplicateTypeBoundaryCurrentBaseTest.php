<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDuplicateTypeBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Duplicate Type Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-duplicate-type-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Duplicate Type Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Duplicate Type Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T10:34:03Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDuplicateTypeBoundaryPdf = static function (
    string $xmp,
    string $metadataDictionaryPrefix,
    string $bodyText
): string {
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP duplicate Type boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionaryPrefix} /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate Type Info Title) /Author (Info Duplicate Type Author) /Producer (Info Duplicate Type Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects catalog XMP streams with duplicate Type and Subtype dictionary keys' => static function (
        TestRunner $t
    ) use ($xmpDuplicateTypeBoundaryPacket, $xmpDuplicateTypeBoundaryPdf): void {
        $xmp = $xmpDuplicateTypeBoundaryPacket(
            'Duplicate Type Hidden XMP Title',
            'A duplicate stream Type dictionary must stay review-only.',
            '2026-06-06T06:34:03-04:00'
        );
        $pdf = $xmpDuplicateTypeBoundaryPdf(
            $xmp,
            '/Type /EmbeddedFile /Subtype /text#2Fxml /Type /Metadata /Subtype /XML',
            'XMP Duplicate Type Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Duplicate Type Info Title', $metadata['title']);
        $t->same(['Info Duplicate Type Author'], $metadata['authors']);
        $t->same('XMP Duplicate Type Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_duplicate_metadata_stream_type_keys', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same(['Type', 'Subtype'], $review['duplicate_keys'] ?? null);
        $t->same(['EmbeddedFile', 'Metadata'], $review['type_values'] ?? null);
        $t->same(['text/xml', 'XML'], $review['subtype_values'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-06T10:34:03Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T10:34:03Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Duplicate Type Hidden XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'A duplicate stream Type dictionary'));
        $t->true(!str_contains($plainText, 'Duplicate Type Hidden XMP Title'));
        $t->true(!str_contains($plainText, 'xmp-duplicate-type-boundary'));
    },
    'accepts escaped single Type and Subtype keys for ordinary document XMP' => static function (
        TestRunner $t
    ) use ($xmpDuplicateTypeBoundaryPacket, $xmpDuplicateTypeBoundaryPdf): void {
        $xmp = $xmpDuplicateTypeBoundaryPacket(
            'Escaped Single Type XMP Title',
            'Escaped single Type and Subtype keys remain valid document XMP.',
            '2026-06-06T05:35:03-05:00'
        );
        $pdf = $xmpDuplicateTypeBoundaryPdf(
            $xmp,
            '/Ty#70e /Metadata /Sub#74ype /XML',
            'Escaped Single Type Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Escaped Single Type XMP Title', $metadata['title']);
        $t->same('Escaped single Type and Subtype keys remain valid document XMP.', $metadata['description']);
        $t->same(['Duplicate Type Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-duplicate-type-boundary'], $metadata['keywords']);
        $t->same('Duplicate Type Boundary Tool', $metadata['creator_tool']);
        $t->same('Duplicate Type Boundary Producer', $metadata['producer']);
        $t->same('2026-06-06T05:35:03-05:00', $metadata['created_at']);
        $t->same('2026-06-06T10:35:03Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T10:34:03Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Duplicate Type Info Title', $metadata['info']['Title'] ?? null);
        $t->same('Escaped Single Type Boundary Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'rejected_duplicate_metadata_stream_type_keys'));
        $t->true(!str_contains($plainText, 'Escaped Single Type XMP Title'));
        $t->true(!str_contains($plainText, 'xmp-duplicate-type-boundary'));
    },
];
