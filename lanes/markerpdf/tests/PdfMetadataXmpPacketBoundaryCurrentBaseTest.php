<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpPacketBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Packet Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Packet Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Packet Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-04T23:35:35Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpPacketBoundaryBareRoot = static function (string $packet): string {
    $packet = preg_replace('/^<\?xpacket\b[^?]*\?>/s', '', $packet, 1) ?? $packet;

    return preg_replace('/<\?xpacket\s+end="w"\?>$/s', '', $packet, 1) ?? $packet;
};

$xmpPacketBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText = 'XMP Packet Boundary Body'): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP packet boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Fallback Packet Boundary Title) /Author (Fallback Author) /Producer (Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts root XMP before packet padding and appended decoy bytes' => static function (
        TestRunner $t
    ) use ($xmpPacketBoundaryPacket, $xmpPacketBoundaryPdf): void {
        $currentXmp = $xmpPacketBoundaryPacket(
            'Current Padded XMP Title',
            'Root packet survives trailing padding',
            '2026-06-04T19:35:35-04:00'
        );
        $decoyXmp = $xmpPacketBoundaryPacket(
            'Trailing Decoy XMP Title',
            'Trailing packet must not replace the current root',
            '2026-06-04T23:59:59Z'
        );
        $pdf = $xmpPacketBoundaryPdf(
            $currentXmp . "\0\0 \n" . $decoyXmp,
            '/Type /Metadata /Subtype /XML'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Padded XMP Title', $metadata['title']);
        $t->same('Root packet survives trailing padding', $metadata['description']);
        $t->same(['Packet Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-boundary'], $metadata['keywords']);
        $t->same('Packet Boundary Tool', $metadata['creator_tool']);
        $t->same('Packet Boundary Producer', $metadata['producer']);
        $t->same('2026-06-04T19:35:35-04:00', $metadata['created_at']);
        $t->same('2026-06-04T23:35:35Z', $metadata['created_at_utc']);
        $t->same('2026-06-04T23:35:35Z', $metadata['metadata_date_utc']);
        $t->same('Current Padded XMP Title', $metadata['xmp']['title'] ?? null);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Fallback Packet Boundary Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Packet Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing packet must not replace'));
        $t->true(!str_contains($plainText, 'Current Padded XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Decoy XMP Title'));
    },
    'prefers active xpacket begin end root over pre packet XMP decoys' => static function (
        TestRunner $t
    ) use ($xmpPacketBoundaryPacket, $xmpPacketBoundaryBareRoot, $xmpPacketBoundaryPdf): void {
        $prePacketDecoy = $xmpPacketBoundaryBareRoot($xmpPacketBoundaryPacket(
            'Pre Packet Decoy XMP Title',
            'A root before xpacket begin must not become document metadata',
            '2026-06-05T07:59:59Z'
        ));
        $currentXmp = $xmpPacketBoundaryPacket(
            'Current XPacket Root Title',
            'The begin/end packet root wins before WordPress import',
            '2026-06-05T03:45:12-04:00'
        );
        $trailingXmp = $xmpPacketBoundaryPacket(
            'Trailing XPacket Decoy Title',
            'Trailing packet must not replace the active packet root',
            '2026-06-05T08:59:59Z'
        );
        $pdf = $xmpPacketBoundaryPdf(
            $prePacketDecoy . "\n" . $currentXmp . "\0\0 " . $trailingXmp,
            '/Type /Metadata /Subtype /XML',
            'XMP Begin End Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current XPacket Root Title', $metadata['title']);
        $t->same('The begin/end packet root wins before WordPress import', $metadata['description']);
        $t->same(['Packet Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-boundary'], $metadata['keywords']);
        $t->same('Packet Boundary Tool', $metadata['creator_tool']);
        $t->same('Packet Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T03:45:12-04:00', $metadata['created_at']);
        $t->same('2026-06-05T07:45:12Z', $metadata['created_at_utc']);
        $t->same('2026-06-04T23:35:35Z', $metadata['metadata_date_utc']);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('XMP Begin End Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Pre Packet Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing XPacket Decoy Title'));
        $t->true(!str_contains($plainText, 'Current XPacket Root Title'));
        $t->true(!str_contains($plainText, 'Pre Packet Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing XPacket Decoy Title'));
    },
    'summarizes rejected catalog metadata XML using the same packet boundary' => static function (
        TestRunner $t
    ) use ($xmpPacketBoundaryPacket, $xmpPacketBoundaryPdf): void {
        $currentXmp = $xmpPacketBoundaryPacket(
            'Rejected Padded XMP Title',
            'Rejected packet is review-only',
            '2026-06-04T23:36:35Z'
        );
        $decoyXmp = $xmpPacketBoundaryPacket(
            'Rejected Trailing Decoy Title',
            'Rejected trailing packet stays hidden',
            '2026-06-04T23:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpPacketBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Packet Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Fallback Packet Boundary Title', $metadata['title']);
        $t->same('Rejected XMP Packet Boundary Body', $plainText);
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
        $t->same('2026-06-04T23:36:35Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Padded XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Padded XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Trailing Decoy Title'));
    },
    'summarizes rejected XML metadata streams from active xpacket roots only' => static function (
        TestRunner $t
    ) use ($xmpPacketBoundaryPacket, $xmpPacketBoundaryBareRoot, $xmpPacketBoundaryPdf): void {
        $prePacketDecoy = $xmpPacketBoundaryBareRoot($xmpPacketBoundaryPacket(
            'Rejected Pre Packet Decoy Title',
            'Rejected pre packet root must not define review dates',
            '2026-06-05T08:01:59Z'
        ));
        $currentXmp = $xmpPacketBoundaryPacket(
            'Rejected Current XPacket Title',
            'Rejected active packet is summarized but redacted',
            '2026-06-05T08:02:59Z'
        );
        $trailingXmp = $xmpPacketBoundaryPacket(
            'Rejected Trailing XPacket Decoy Title',
            'Rejected trailing packet stays hidden',
            '2026-06-05T08:59:59Z'
        );
        $metadataBytes = $prePacketDecoy . "\n" . $currentXmp . "\0\0" . $trailingXmp;
        $pdf = $xmpPacketBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Begin End Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Fallback Packet Boundary Title', $metadata['title']);
        $t->same('Rejected XMP Begin End Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T08:02:59Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Pre Packet Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Current XPacket Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing XPacket Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Current XPacket Title'));
        $t->true(!str_contains($plainText, 'Rejected Pre Packet Decoy Title'));
    },
];
