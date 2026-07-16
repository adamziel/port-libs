<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpInstructionBoundaryPacket = static function (
    string $title,
    string $description,
    string $date,
    bool $includeNonTerminalInstruction = false
): string {
    $nonTerminalInstruction = $includeNonTerminalInstruction
        ? '<?xpacket id="review-only-boundary" end="decoy"?>'
        : '';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . $nonTerminalInstruction
        . '<dc:creator><rdf:Seq><rdf:li>Instruction Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-instruction-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Instruction Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Instruction Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T09:26:26Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpInstructionBoundaryBareRoot = static function (string $packet): string {
    $packet = preg_replace('/^<\?xpacket\b[^?]*\?>/s', '', $packet, 1) ?? $packet;

    return preg_replace('/<\?xpacket\s+end="w"\?>$/s', '', $packet, 1) ?? $packet;
};

$xmpInstructionBoundaryBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpInstructionBoundaryPacket, $xmpInstructionBoundaryBareRoot): string {
    $prePacketDecoy = $xmpInstructionBoundaryBareRoot($xmpInstructionBoundaryPacket(
        'Stale Instruction Boundary XMP Title',
        'A stale root before the active packet must not define WordPress metadata.',
        '2026-06-05T09:20:00Z'
    ));
    $current = $xmpInstructionBoundaryPacket($title, $description, $date, true);
    $trailing = $xmpInstructionBoundaryPacket(
        'Trailing Instruction Boundary Decoy Title',
        'A trailing packet after the active packet must stay hidden.',
        '2026-06-05T09:40:00Z'
    );

    return $prePacketDecoy . "\n" . $current . "\0\0" . $trailing;
};

$xmpInstructionBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP instruction boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Instruction Boundary Info Title) /Author (Info Instruction Author) /Producer (Info Instruction Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores non-terminal xpacket processing instructions before active packet end' => static function (
        TestRunner $t
    ) use ($xmpInstructionBoundaryBytes, $xmpInstructionBoundaryPdf): void {
        $metadataBytes = $xmpInstructionBoundaryBytes(
            'Current Instruction Boundary XMP Title',
            'The active packet survives an internal xpacket processing instruction.',
            '2026-06-05T05:26:26-04:00'
        );
        $pdf = $xmpInstructionBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Instruction Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Instruction Boundary XMP Title', $metadata['title']);
        $t->same('The active packet survives an internal xpacket processing instruction.', $metadata['description']);
        $t->same(['Instruction Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-instruction-boundary'], $metadata['keywords']);
        $t->same('Instruction Boundary Tool', $metadata['creator_tool']);
        $t->same('Instruction Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T05:26:26-04:00', $metadata['created_at']);
        $t->same('2026-06-05T09:26:26Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T09:26:26Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Instruction Boundary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Instruction Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Instruction Boundary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Instruction Boundary Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'end="decoy"'));
        $t->true(!str_contains($plainText, 'Current Instruction Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Instruction Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Instruction Boundary Decoy Title'));
    },
    'summarizes rejected XML streams from the active packet after internal xpacket instructions' => static function (
        TestRunner $t
    ) use ($xmpInstructionBoundaryBytes, $xmpInstructionBoundaryPdf): void {
        $metadataBytes = $xmpInstructionBoundaryBytes(
            'Rejected Instruction Boundary XMP Title',
            'Rejected active packet is summarized but redacted.',
            '2026-06-05T09:27:26Z'
        );
        $pdf = $xmpInstructionBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Instruction Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Instruction Boundary Info Title', $metadata['title']);
        $t->same('Rejected XMP Instruction Boundary Body', $plainText);
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
        $t->same('2026-06-05T09:27:26Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T09:26:26Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Instruction Boundary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Instruction Boundary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Instruction Boundary Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Instruction Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Instruction Boundary XMP Title'));
    },
];
