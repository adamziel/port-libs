<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDeclaredEncodingPacket = static function (
    string $title,
    string $description,
    string $date,
    string $encoding
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<?xml version="1.0" encoding="' . htmlspecialchars($encoding, ENT_XML1) . '"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Ren' . "\u{00e9}" . 'e Declared Encoding Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-declared-encoding</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Declared Encoding Producer</pdf:Producer>'
        . '<xmp:CreatorTool>D' . "\u{00e9}" . 'clared Encoding Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T14:09:12Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDeclaredEncodingBytes = static function (
    string $title,
    string $description,
    string $date,
    string $encoding
) use ($xmpDeclaredEncodingPacket): string {
    $bytes = iconv('UTF-8', $encoding, $xmpDeclaredEncodingPacket($title, $description, $date, $encoding));
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to encode declared XMP metadata fixture.');
    }

    return $bytes;
};

$xmpDeclaredEncodingPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress declared XMP metadata fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Declared Encoding Info Title) /Author (Info Declared Author) /Producer (Info Declared Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'honors declared non UTF XMP encoding inside active packet before fallback decoding' => static function (
        TestRunner $t
    ) use ($xmpDeclaredEncodingBytes, $xmpDeclaredEncodingPdf): void {
        $currentXmp = $xmpDeclaredEncodingBytes(
            'Caf' . "\u{00e9}" . ' Declared Encoding XMP Title',
            'R' . "\u{00e9}" . 'sum' . "\u{00e9}" . ' metadata remains document XMP before WordPress import',
            '2026-06-05T10:09:12-04:00',
            'Windows-1252'
        );
        $decoyXmp = $xmpDeclaredEncodingBytes(
            'Trailing Declared Encoding Decoy Title',
            'Trailing declared-encoding packet stays outside the active metadata boundary',
            '2026-06-05T14:59:59Z',
            'Windows-1252'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpDeclaredEncodingPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'Declared Encoding XMP Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Caf' . "\u{00e9}" . ' Declared Encoding XMP Title', $metadata['title']);
        $t->same('R' . "\u{00e9}" . 'sum' . "\u{00e9}" . ' metadata remains document XMP before WordPress import', $metadata['description']);
        $t->same(['Ren' . "\u{00e9}" . 'e Declared Encoding Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-declared-encoding'], $metadata['keywords']);
        $t->same('D' . "\u{00e9}" . 'clared Encoding Tool', $metadata['creator_tool']);
        $t->same('Declared Encoding Producer', $metadata['producer']);
        $t->same('2026-06-05T10:09:12-04:00', $metadata['created_at']);
        $t->same('2026-06-05T14:09:12Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T14:09:12Z', $metadata['metadata_date_utc']);
        $t->same('Windows-1252', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['decoded_to_utf8'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same(false, array_key_exists('encoding_fallback', $metadata['xmp'] ?? []));
        $t->same('Declared Encoding Info Title', $metadata['info']['Title'] ?? null);
        $t->same('Declared Encoding XMP Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Declared Encoding Decoy Title'));
        $t->true(!str_contains($plainText, 'Declared Encoding XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Declared Encoding Decoy Title'));
    },
    'summarizes rejected declared non UTF XMP packets without marking fallback decoding' => static function (
        TestRunner $t
    ) use ($xmpDeclaredEncodingBytes, $xmpDeclaredEncodingPdf): void {
        $currentXmp = $xmpDeclaredEncodingBytes(
            'Rejected Caf' . "\u{00e9}" . ' Declared Encoding XMP Title',
            'Rejected R' . "\u{00e9}" . 'sum' . "\u{00e9}" . ' metadata remains review-only',
            '2026-06-05T14:10:12Z',
            'Windows-1252'
        );
        $decoyXmp = $xmpDeclaredEncodingBytes(
            'Rejected Trailing Declared Encoding Decoy Title',
            'Rejected trailing declared-encoding packet stays outside the active review summary',
            '2026-06-05T14:59:59Z',
            'Windows-1252'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpDeclaredEncodingPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected Declared Encoding XMP Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Declared Encoding Info Title', $metadata['title']);
        $t->same('Rejected Declared Encoding XMP Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(2, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same('Windows-1252', $summary['packet_encoding'] ?? null);
        $t->same(true, $summary['decoded_to_utf8'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, array_key_exists('encoding_fallback', $summary));
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T14:10:12Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T14:09:12Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Caf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Declared Encoding Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Caf'));
        $t->true(!str_contains($plainText, 'Rejected Trailing Declared Encoding Decoy Title'));
    },
];
