<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpCompletePacketFallbackRoot = static function (
    string $title,
    string $description,
    string $date
): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Complete Packet Fallback Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-complete-packet-fallback</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Complete Packet Fallback Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Complete Packet Fallback Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T10:01:48Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpCompletePacketFallbackPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$xmpCompletePacketFallbackBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpCompletePacketFallbackPacket, $xmpCompletePacketFallbackRoot): string {
    $emptyPacket = $xmpCompletePacketFallbackPacket(
        '<?xml version="1.0" encoding="UTF-8"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>'
    );
    $currentPacket = $xmpCompletePacketFallbackPacket($xmpCompletePacketFallbackRoot($title, $description, $date));
    $trailingPacket = $xmpCompletePacketFallbackPacket($xmpCompletePacketFallbackRoot(
        'Trailing Complete Packet Decoy XMP Title',
        'A later complete packet must not replace the current packet.',
        '2026-06-05T10:40:00Z'
    ));

    return $emptyPacket . "\0\0\n" . $currentPacket . "\0\0\n" . $trailingPacket;
};

$xmpCompletePacketFallbackPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP complete-packet fallback fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Complete Packet Info Title) /Author (Info Complete Packet Author) /Producer (Info Complete Packet Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'skips complete empty xpacket bodies before the current document XMP packet' => static function (
        TestRunner $t
    ) use ($xmpCompletePacketFallbackBytes, $xmpCompletePacketFallbackPdf): void {
        $metadataBytes = $xmpCompletePacketFallbackBytes(
            'Current Complete Packet Fallback XMP Title',
            'Current XMP metadata follows a complete empty packet body',
            '2026-06-05T06:01:48-04:00'
        );
        $pdf = $xmpCompletePacketFallbackPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Complete Packet Fallback Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Complete Packet Fallback XMP Title', $metadata['title']);
        $t->same('Current XMP metadata follows a complete empty packet body', $metadata['description']);
        $t->same(['Complete Packet Fallback Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-complete-packet-fallback'], $metadata['keywords']);
        $t->same('Complete Packet Fallback Tool', $metadata['creator_tool']);
        $t->same('Complete Packet Fallback Producer', $metadata['producer']);
        $t->same('2026-06-05T06:01:48-04:00', $metadata['created_at']);
        $t->same('2026-06-05T10:01:48Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T10:01:48Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Complete Packet Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Complete Packet Fallback Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Complete Packet Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'A later complete packet must not replace'));
        $t->true(!str_contains($plainText, 'Current Complete Packet Fallback XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Complete Packet Decoy XMP Title'));
    },
    'summarizes rejected XML streams from the first non-empty complete packet' => static function (
        TestRunner $t
    ) use ($xmpCompletePacketFallbackBytes, $xmpCompletePacketFallbackPdf): void {
        $metadataBytes = $xmpCompletePacketFallbackBytes(
            'Rejected Complete Packet Fallback XMP Title',
            'Rejected current complete packet is summarized only',
            '2026-06-05T10:02:48Z'
        );
        $pdf = $xmpCompletePacketFallbackPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Complete Packet Fallback Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Complete Packet Info Title', $metadata['title']);
        $t->same('Rejected XMP Complete Packet Fallback Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T10:02:48Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T10:01:48Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Complete Packet Fallback XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Complete Packet Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Complete Packet Fallback XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Complete Packet Decoy XMP Title'));
    },
];
