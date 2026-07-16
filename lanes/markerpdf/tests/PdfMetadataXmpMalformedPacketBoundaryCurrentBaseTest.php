<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpMalformedPacketBoundaryRoot = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>Malformed Packet Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-malformed-packet-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Malformed Packet Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Malformed Packet Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T02:49:19Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpMalformedPacketBoundaryPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$xmpMalformedPacketBoundaryBytes = static function (
    string $malformedTitle,
    string $trailingTitle
) use ($xmpMalformedPacketBoundaryPacket, $xmpMalformedPacketBoundaryRoot): string {
    $malformedFirstPacket = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($malformedTitle, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-08T02:48:19Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '<?xpacket end="w"?>';
    $trailingPacket = $xmpMalformedPacketBoundaryPacket($xmpMalformedPacketBoundaryRoot(
        $trailingTitle,
        'A later valid packet must not replace a malformed first packet.',
        '2026-06-08T02:59:59Z'
    ));

    return $malformedFirstPacket . "\0\0\n" . $trailingPacket;
};

$xmpMalformedPacketBoundaryPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress malformed XMP packet boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Malformed Packet Info Title) /Author (Info Malformed Packet Author) /Producer (Info Malformed Packet Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'fails closed on malformed first XMP packet before trailing valid packet promotion' => static function (
        TestRunner $t
    ) use ($xmpMalformedPacketBoundaryBytes, $xmpMalformedPacketBoundaryPdf): void {
        $metadataBytes = $xmpMalformedPacketBoundaryBytes(
            'Malformed First Packet XMP Title',
            'Trailing Valid Packet Decoy Title'
        );
        $pdf = $xmpMalformedPacketBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Malformed Packet Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Malformed Packet Info Title', $metadata['title']);
        $t->same(['Info Malformed Packet Author'], $metadata['authors']);
        $t->same('XMP Malformed Packet Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_document_xmp_packet', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_malformed_first_xmp_packet', $summary['status'] ?? null);
        $t->same('unbounded_adobe_xmpmeta_root', $summary['malformed_packet_reason'] ?? null);
        $t->same(0, $summary['malformed_packet_index'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Malformed First Packet XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Valid Packet Decoy Title'));
        $t->true(!str_contains($plainText, 'Malformed First Packet XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Valid Packet Decoy Title'));
    },
    'summarizes rejected XML streams from the malformed first packet without trailing replacement' => static function (
        TestRunner $t
    ) use ($xmpMalformedPacketBoundaryBytes, $xmpMalformedPacketBoundaryPdf): void {
        $metadataBytes = $xmpMalformedPacketBoundaryBytes(
            'Rejected Malformed First Packet XMP Title',
            'Rejected Trailing Valid Packet Decoy Title'
        );
        $pdf = $xmpMalformedPacketBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Malformed Packet Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Malformed Packet Info Title', $metadata['title']);
        $t->same('Rejected XMP Malformed Packet Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_malformed_first_xmp_packet', $summary['status'] ?? null);
        $t->same('unbounded_adobe_xmpmeta_root', $summary['malformed_packet_reason'] ?? null);
        $t->same(0, $summary['malformed_packet_index'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Malformed First Packet XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Valid Packet Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Malformed First Packet XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Trailing Valid Packet Decoy Title'));
    },
];
