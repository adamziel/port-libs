<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpUnsafePacketBoundaryRoot = static function (
    string $title,
    string $description,
    string $date,
    bool $withUnsafeEntity = false
): string {
    $doctype = $withUnsafeEntity
        ? '<!DOCTYPE x:xmpmeta [<!ENTITY unsafeTitle "' . htmlspecialchars($title, ENT_XML1) . '"><!ENTITY unsafeDescription "' . htmlspecialchars($description, ENT_XML1) . '">]>'
        : '';
    $titleValue = $withUnsafeEntity ? '&unsafeTitle;' : htmlspecialchars($title, ENT_XML1);
    $descriptionValue = $withUnsafeEntity ? '&unsafeDescription;' : htmlspecialchars($description, ENT_XML1);

    return $doctype
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . $titleValue . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Unsafe Packet Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . $descriptionValue . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-unsafe-packet-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Unsafe Packet Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Unsafe Packet Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-07T21:06:27Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpUnsafePacketBoundaryPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$xmpUnsafePacketBoundaryBytes = static function (
    string $unsafeTitle,
    string $unsafeDescription,
    string $safeTitle
) use ($xmpUnsafePacketBoundaryPacket, $xmpUnsafePacketBoundaryRoot): string {
    $unsafePacket = $xmpUnsafePacketBoundaryPacket($xmpUnsafePacketBoundaryRoot(
        $unsafeTitle,
        $unsafeDescription,
        '2026-06-07T17:06:27-04:00',
        true
    ));
    $safeTrailingPacket = $xmpUnsafePacketBoundaryPacket($xmpUnsafePacketBoundaryRoot(
        $safeTitle,
        'Trailing safe packet must not replace the unsafe first packet',
        '2026-06-07T21:59:59Z',
        false
    ));

    return $unsafePacket . "\0\0\n" . $safeTrailingPacket;
};

$xmpUnsafePacketBoundaryPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP unsafe-packet boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Unsafe Packet Info Title) /Author (Info Unsafe Packet Author) /Producer (Info Unsafe Packet Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'fails closed on unsafe first XMP packet before trailing safe packet promotion' => static function (
        TestRunner $t
    ) use ($xmpUnsafePacketBoundaryBytes, $xmpUnsafePacketBoundaryPdf): void {
        $metadataBytes = $xmpUnsafePacketBoundaryBytes(
            'Unsafe Entity Packet XMP Title',
            'Unsafe entity packet must not become WordPress metadata',
            'Trailing Safe Packet Decoy Title'
        );
        $pdf = $xmpUnsafePacketBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Unsafe Packet Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Unsafe Packet Info Title', $metadata['title']);
        $t->same(['Info Unsafe Packet Author'], $metadata['authors']);
        $t->same('XMP Unsafe Packet Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_unsafe_document_xmp_stream', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_dtd_or_entity_declaration', $summary['status'] ?? null);
        $t->same(['DOCTYPE', 'ENTITY'], $summary['unsafe_markup'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unsafe Entity Packet XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Safe Packet Decoy Title'));
        $t->true(!str_contains($plainText, 'Unsafe Entity Packet XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Safe Packet Decoy Title'));
    },
    'summarizes rejected non metadata XML streams from the unsafe first packet only' => static function (
        TestRunner $t
    ) use ($xmpUnsafePacketBoundaryBytes, $xmpUnsafePacketBoundaryPdf): void {
        $metadataBytes = $xmpUnsafePacketBoundaryBytes(
            'Rejected Unsafe Packet XMP Title',
            'Rejected unsafe first packet is summarized only',
            'Rejected Trailing Safe Packet Decoy Title'
        );
        $pdf = $xmpUnsafePacketBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Unsafe Packet Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Unsafe Packet Info Title', $metadata['title']);
        $t->same('Rejected XMP Unsafe Packet Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_dtd_or_entity_declaration', $summary['status'] ?? null);
        $t->same(['DOCTYPE', 'ENTITY'], $summary['unsafe_markup'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Unsafe Packet XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Safe Packet Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Unsafe Packet XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Trailing Safe Packet Decoy Title'));
    },
];
