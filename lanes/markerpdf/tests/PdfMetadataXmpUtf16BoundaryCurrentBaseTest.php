<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpUtf16BoundaryPacket = static function (
    string $title,
    string $description,
    string $date,
    string $encoding
): string {
    return '<?xml version="1.0" encoding="' . $encoding . '"?>'
        . '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>UTF16 Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>utf16-xmp-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>UTF16 Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>UTF16 Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T01:15:34Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpUtf16BoundaryBytes = static function (
    string $title,
    string $description,
    string $date,
    string $encoding
) use ($xmpUtf16BoundaryPacket): string {
    $bytes = iconv('UTF-8', $encoding, $xmpUtf16BoundaryPacket($title, $description, $date, $encoding));
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to encode XMP UTF-16 boundary fixture.');
    }

    return $bytes;
};

$xmpUtf16BoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP UTF-16 boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (UTF16 Boundary Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'bounds BOM-less UTF-16BE XMP before trailing UTF-16 decoy packets' => static function (
        TestRunner $t
    ) use ($xmpUtf16BoundaryBytes, $xmpUtf16BoundaryPdf): void {
        $currentXmp = $xmpUtf16BoundaryBytes(
            'Current UTF16BE XMP Title',
            'BOM-less UTF-16BE root survives trailing packet bytes',
            '2026-06-04T21:15:34-04:00',
            'UTF-16BE'
        );
        $decoyXmp = $xmpUtf16BoundaryBytes(
            'Trailing UTF16BE Decoy Title',
            'Trailing UTF-16BE decoy packet must not replace the current root',
            '2026-06-05T01:59:59Z',
            'UTF-16BE'
        );
        $pdf = $xmpUtf16BoundaryPdf(
            $currentXmp . "\0\0" . $decoyXmp,
            '/Type /Metadata /Subtype /XML',
            'UTF16 XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current UTF16BE XMP Title', $metadata['title']);
        $t->same('BOM-less UTF-16BE root survives trailing packet bytes', $metadata['description']);
        $t->same(['UTF16 Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'utf16-xmp-boundary'], $metadata['keywords']);
        $t->same('UTF16 Boundary Tool', $metadata['creator_tool']);
        $t->same('UTF16 Boundary Producer', $metadata['producer']);
        $t->same('2026-06-04T21:15:34-04:00', $metadata['created_at']);
        $t->same('2026-06-05T01:15:34Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T01:15:34Z', $metadata['metadata_date_utc']);
        $t->same('UTF-16BE', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['decoded_to_utf8'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('UTF16 Boundary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('UTF16 XMP Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing UTF16BE Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing UTF-16BE decoy packet'));
        $t->true(!str_contains($plainText, 'Current UTF16BE XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing UTF16BE Decoy Title'));
    },
    'summarizes rejected BOM-less UTF-16LE XMP streams with redacted packet fields' => static function (
        TestRunner $t
    ) use ($xmpUtf16BoundaryBytes, $xmpUtf16BoundaryPdf): void {
        $currentXmp = $xmpUtf16BoundaryBytes(
            'Rejected UTF16LE XMP Title',
            'Rejected BOM-less UTF-16LE XMP remains review-only',
            '2026-06-05T01:16:34Z',
            'UTF-16LE'
        );
        $decoyXmp = $xmpUtf16BoundaryBytes(
            'Rejected UTF16LE Decoy Title',
            'Rejected trailing UTF-16LE packet stays hidden',
            '2026-06-05T01:59:59Z',
            'UTF-16LE'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpUtf16BoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected UTF16 XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('UTF16 Boundary Info Title', $metadata['title']);
        $t->same('Rejected UTF16 XMP Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same('UTF-16LE', $summary['packet_encoding'] ?? null);
        $t->same(true, $summary['decoded_to_utf8'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T01:16:34Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected UTF16LE XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected UTF16LE Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected UTF16LE XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected trailing UTF-16LE packet'));
    },
];
