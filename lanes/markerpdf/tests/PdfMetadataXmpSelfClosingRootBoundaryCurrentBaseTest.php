<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpSelfClosingRootPacket = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Self Closing Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-self-closing-root</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Self Closing Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Self Closing Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T05:07:53Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpSelfClosingRootBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpSelfClosingRootPacket): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="empty-boundary-decoy"/>'
        . $xmpSelfClosingRootPacket($title, $description, $date)
        . '<?xpacket end="w"?>';
};

$xmpSelfClosingRootPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP self-closing root boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Self Closing Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'skips empty self-closing XMP wrapper before the current metadata root' => static function (
        TestRunner $t
    ) use ($xmpSelfClosingRootBytes, $xmpSelfClosingRootPacket, $xmpSelfClosingRootPdf): void {
        $currentXmp = $xmpSelfClosingRootBytes(
            'Current Self Closing Root XMP Title',
            'Current XMP root follows an empty self-closing wrapper',
            '2026-06-04T21:07:53-04:00'
        );
        $decoyXmp = $xmpSelfClosingRootPacket(
            'Trailing Self Closing Decoy Title',
            'Trailing self-closing decoy must not replace the current root',
            '2026-06-05T05:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpSelfClosingRootPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'Self Closing XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Self Closing Root XMP Title', $metadata['title']);
        $t->same('Current XMP root follows an empty self-closing wrapper', $metadata['description']);
        $t->same(['Self Closing Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-self-closing-root'], $metadata['keywords']);
        $t->same('Self Closing Boundary Tool', $metadata['creator_tool']);
        $t->same('Self Closing Boundary Producer', $metadata['producer']);
        $t->same('2026-06-04T21:07:53-04:00', $metadata['created_at']);
        $t->same('2026-06-05T01:07:53Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T05:07:53Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Self Closing Info Title', $metadata['info']['Title'] ?? null);
        $t->same('Self Closing XMP Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Self Closing Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing self-closing decoy'));
        $t->true(!str_contains($plainText, 'Current Self Closing Root XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Self Closing Decoy Title'));
    },
    'summarizes rejected XML streams after empty self-closing XMP wrappers' => static function (
        TestRunner $t
    ) use ($xmpSelfClosingRootBytes, $xmpSelfClosingRootPacket, $xmpSelfClosingRootPdf): void {
        $currentXmp = $xmpSelfClosingRootBytes(
            'Rejected Self Closing Root XMP Title',
            'Rejected current XMP root remains review-only',
            '2026-06-05T05:08:53Z'
        );
        $decoyXmp = $xmpSelfClosingRootPacket(
            'Rejected Self Closing Decoy Title',
            'Rejected trailing decoy stays hidden',
            '2026-06-05T05:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpSelfClosingRootPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected Self Closing XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Self Closing Info Title', $metadata['title']);
        $t->same('Rejected Self Closing XMP Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T05:08:53Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Self Closing Root XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Self Closing Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Self Closing Root XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Self Closing Decoy Title'));
    },
];
