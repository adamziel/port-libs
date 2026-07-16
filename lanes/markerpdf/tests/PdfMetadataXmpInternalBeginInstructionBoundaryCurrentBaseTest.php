<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpInternalBeginInstructionPacket = static function (
    string $title,
    string $description,
    string $date,
    bool $includeInternalBegin = false
): string {
    $internalBegin = $includeInternalBegin
        ? '<?xpacket begin="inline-decoy" id="not-a-packet-boundary"?>'
        : '';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . $internalBegin
        . '<dc:creator><rdf:Seq><rdf:li>Internal Begin Instruction Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-internal-begin</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Internal Begin Instruction Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Internal Begin Instruction Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T16:02:18Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpInternalBeginInstructionBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpInternalBeginInstructionPacket): string {
    $current = $xmpInternalBeginInstructionPacket($title, $description, $date, true);
    $trailing = $xmpInternalBeginInstructionPacket(
        'Trailing Internal Begin Decoy XMP Title',
        'A trailing packet must not replace the active packet after an internal begin instruction.',
        '2026-06-05T16:59:59Z'
    );

    return $current . "\0\0\n" . $trailing;
};

$xmpInternalBeginInstructionPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP internal-begin boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Internal Begin Info Title) /Author (Info Internal Begin Author) /Producer (Info Internal Begin Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores nested xpacket begin instructions inside the active XMP root' => static function (
        TestRunner $t
    ) use ($xmpInternalBeginInstructionBytes, $xmpInternalBeginInstructionPdf): void {
        $metadataBytes = $xmpInternalBeginInstructionBytes(
            'Current Internal Begin XMP Title',
            'The active packet survives an internal xpacket begin instruction.',
            '2026-06-05T12:02:18-04:00'
        );
        $pdf = $xmpInternalBeginInstructionPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Internal Begin Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Internal Begin XMP Title', $metadata['title']);
        $t->same('The active packet survives an internal xpacket begin instruction.', $metadata['description']);
        $t->same(['Internal Begin Instruction Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-internal-begin'], $metadata['keywords']);
        $t->same('Internal Begin Instruction Tool', $metadata['creator_tool']);
        $t->same('Internal Begin Instruction Producer', $metadata['producer']);
        $t->same('2026-06-05T12:02:18-04:00', $metadata['created_at']);
        $t->same('2026-06-05T16:02:18Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T16:02:18Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Internal Begin Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Internal Begin Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Internal Begin Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'not-a-packet-boundary'));
        $t->true(!str_contains($plainText, 'Current Internal Begin XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Internal Begin Decoy XMP Title'));
    },
    'summarizes rejected XML streams from the active root around internal xpacket begins' => static function (
        TestRunner $t
    ) use ($xmpInternalBeginInstructionBytes, $xmpInternalBeginInstructionPdf): void {
        $metadataBytes = $xmpInternalBeginInstructionBytes(
            'Rejected Internal Begin XMP Title',
            'Rejected active packet is summarized but redacted.',
            '2026-06-05T16:03:18Z'
        );
        $pdf = $xmpInternalBeginInstructionPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Internal Begin Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Internal Begin Info Title', $metadata['title']);
        $t->same('Rejected XMP Internal Begin Boundary Body', $plainText);
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
        $t->same('2026-06-05T16:03:18Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T16:02:18Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Internal Begin XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Internal Begin Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'not-a-packet-boundary'));
        $t->true(!str_contains($plainText, 'Rejected Internal Begin XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Internal Begin Decoy XMP Title'));
    },
];
