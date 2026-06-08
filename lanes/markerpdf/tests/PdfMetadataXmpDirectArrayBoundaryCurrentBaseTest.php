<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDirectArrayMetadataBoundaryPacket = static function (
    string $title,
    string $description,
    string $date
): string {
    return '<?xpacket begin="markerpdf"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:pdf="http://ns.adobe.com/pdf/1.3/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
};

$xmpDirectArrayMetadataBoundaryPdf = static function (
    string $metadataValue,
    string $xmpPacket,
    string $extraObjectBody
): string {
    $xmpLength = strlen($xmpPacket);
    $bodyStream = "BT /F1 12 Tf 72 720 Td (Visible body text) Tj ET";
    $bodyLength = strlen($bodyStream);

    return "%PDF-1.7\n"
        . "1 0 obj\n"
        . "<< /Type /Catalog /Pages 2 0 R /Metadata {$metadataValue} >>\n"
        . "endobj\n"
        . "2 0 obj\n"
        . "<< /Type /Pages /Kids [3 0 R] /Count 1 >>\n"
        . "endobj\n"
        . "3 0 obj\n"
        . "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>\n"
        . "endobj\n"
        . "4 0 obj\n"
        . "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\n"
        . "endobj\n"
        . "5 0 obj\n"
        . "<< /Type /Metadata /Subtype /XML /Length {$xmpLength} >>\n"
        . "stream\n"
        . $xmpPacket . "\n"
        . "endstream\n"
        . "endobj\n"
        . $extraObjectBody
        . "8 0 obj\n"
        . "<< /Length {$bodyLength} >>\n"
        . "stream\n"
        . $bodyStream . "\n"
        . "endstream\n"
        . "endobj\n"
        . "9 0 obj\n"
        . "<< /Title (Info Title) /Author (Info Author) >>\n"
        . "endobj\n"
        . "trailer\n"
        . "<< /Root 1 0 R /Info 9 0 R >>\n"
        . "%%EOF\n";
};

return [
    'keeps direct array catalog Metadata values review-only and reports referenced objects' => static function (TestRunner $test) use ($xmpDirectArrayMetadataBoundaryPdf, $xmpDirectArrayMetadataBoundaryPacket): void {
        $packet = $xmpDirectArrayMetadataBoundaryPacket(
            'Array Hidden XMP Title',
            'Array Hidden XMP Description',
            '2026-06-08T17:22:05Z'
        );
        $pdf = $xmpDirectArrayMetadataBoundaryPdf(
            '[5 0 R 7 0 R]',
            $packet,
            "7 0 obj\n<< /Type /Action /S /JavaScript /JS (ArrayHiddenAction) >>\nendobj\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataJson = (string) json_encode($metadata);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $test->same(['info', 'catalog'], $metadata['source']);
        $test->same('Info Title', $metadata['title']);
        $test->same(['Info Author'], $metadata['authors']);
        $test->same([], $metadata['xmp']);
        $test->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $test->same('rejected_non_indirect_metadata_reference', $review['status'] ?? null);
        $test->same('array', $review['metadata_value_type'] ?? null);
        $test->same('[5 0 R 7 0 R]', $review['metadata_value_preview'] ?? null);
        $test->same(2, $review['metadata_array_entry_count'] ?? null);
        $test->same([5, 7], $review['referenced_object_numbers'] ?? null);
        $test->same(true, $review['review_only'] ?? null);
        $test->same(false, $review['accepted_as_document_xmp'] ?? null);
        $test->same(false, $review['payload_included'] ?? null);
        $test->true(!str_contains($metadataJson, 'Array Hidden XMP Title'), 'XMP payload title leaked into metadata review');
        $test->true(!str_contains($metadataJson, 'ArrayHiddenAction'), 'Unsafe referenced object payload leaked into metadata review');
        $test->contains('Visible body text', $plainText);
        $test->true(!str_contains($plainText, 'Array Hidden XMP Title'), 'XMP payload title leaked into visible text');
        $test->true(!str_contains($plainText, 'ArrayHiddenAction'), 'Unsafe referenced object payload leaked into visible text');
    },
];
