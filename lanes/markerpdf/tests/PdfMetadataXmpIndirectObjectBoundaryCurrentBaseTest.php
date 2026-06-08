<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpIndirectObjectBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Indirect Object Boundary Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-indirect-object-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Indirect Object Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Indirect Object Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T13:10:58Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpIndirectObjectBoundaryPdf = static function (
    string $catalogMetadataValue,
    string $metadataObjectBody,
    string $bodyText,
    string $extraObjects = ''
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata {$catalogMetadataValue} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n{$metadataObjectBody}\nendobj\n"
        . $extraObjects
        . "8 0 obj\n<< /Title (Indirect Object Info Title) /Author (Info Indirect Object Author) /Producer (Info Indirect Object Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 8 0 R >>\n%%EOF";
};

return [
    'rejects nested indirect catalog Metadata object references before document XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpIndirectObjectBoundaryPacket, $xmpIndirectObjectBoundaryPdf): void {
        $xmp = $xmpIndirectObjectBoundaryPacket(
            'Nested Reference Hidden XMP Title',
            'A nested indirect wrapper must not define WordPress document metadata.',
            '2026-06-08T09:10:58-04:00'
        );
        $pdf = $xmpIndirectObjectBoundaryPdf(
            '5 0 R',
            '6 0 R',
            'Nested Metadata Reference Boundary Body',
            "6 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Indirect Object Info Title', $metadata['title']);
        $t->same(['Info Indirect Object Author'], $metadata['authors']);
        $t->same('Nested Metadata Reference Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_non_stream_metadata_object', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('indirect_reference', $review['object_value_type'] ?? null);
        $t->same('6 0 R', $review['object_value_preview'] ?? null);
        $t->same(true, $review['single_object_value'] ?? null);
        $t->same(6, $review['nested_reference_object_number'] ?? null);
        $t->same(0, $review['nested_reference_generation'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Nested Reference Hidden XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'nested indirect wrapper'));
        $t->true(!str_contains($plainText, 'Nested Reference Hidden XMP Title'));
        $t->true(!str_contains($plainText, 'xmp-indirect-object-boundary'));
    },
    'rejects array-wrapped catalog Metadata stream references before document XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpIndirectObjectBoundaryPacket, $xmpIndirectObjectBoundaryPdf): void {
        $xmp = $xmpIndirectObjectBoundaryPacket(
            'Array Wrapped Hidden XMP Title',
            'An array wrapper around a metadata stream reference must stay review-only.',
            '2026-06-08T13:11:58Z'
        );
        $pdf = $xmpIndirectObjectBoundaryPdf(
            '5 0 R',
            '[6 0 R 7 0 R]',
            'Array Wrapped Metadata Boundary Body',
            "6 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
                . "7 0 obj\n<< /S /JavaScript /JS (app.alert\\('array wrapped metadata action tail'\\)) >>\nendobj\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Indirect Object Info Title', $metadata['title']);
        $t->same('Array Wrapped Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_non_stream_metadata_object', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('array', $review['object_value_type'] ?? null);
        $t->same('[6 0 R 7 0 R]', $review['object_value_preview'] ?? null);
        $t->same(true, $review['single_object_value'] ?? null);
        $t->same([6, 7], $review['referenced_object_numbers'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Array Wrapped Hidden XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'array wrapped metadata action tail'));
        $t->true(!str_contains($plainText, 'Array Wrapped Hidden XMP Title'));
        $t->true(!str_contains($plainText, 'array wrapped metadata action tail'));
    },
];
