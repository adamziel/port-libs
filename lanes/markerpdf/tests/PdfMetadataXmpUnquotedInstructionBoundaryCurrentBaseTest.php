<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpUnquotedInstructionRoot = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>Unquoted Instruction Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-unquoted-instruction</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Unquoted Instruction Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Unquoted Instruction Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T07:02:31Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpUnquotedInstructionPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$xmpUnquotedInstructionBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpUnquotedInstructionRoot, $xmpUnquotedInstructionPacket): string {
    $staleUnquoted = '<?xpacket begin=unquoted id=stale-unquoted?>'
        . $xmpUnquotedInstructionRoot(
            'Stale Unquoted Instruction XMP Title',
            'Unquoted xpacket pseudo-attributes must not delimit document metadata.',
            '2026-06-08T07:59:59Z'
        )
        . '<?xpacket end=w?>';
    $current = $xmpUnquotedInstructionPacket($xmpUnquotedInstructionRoot($title, $description, $date));
    $trailing = $xmpUnquotedInstructionPacket($xmpUnquotedInstructionRoot(
        'Trailing Quoted Instruction Decoy Title',
        'A later valid packet must not replace the current quoted packet.',
        '2026-06-08T08:59:59Z'
    ));

    return $staleUnquoted . "\n" . $current . "\0\0\n" . $trailing;
};

$xmpUnquotedInstructionPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP unquoted-instruction boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Unquoted Instruction Info Title) /Author (Info Unquoted Instruction Author) /Producer (Info Unquoted Instruction Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores unquoted xpacket begin and end pseudo attributes before current quoted packet' => static function (
        TestRunner $t
    ) use ($xmpUnquotedInstructionBytes, $xmpUnquotedInstructionPdf): void {
        $metadataBytes = $xmpUnquotedInstructionBytes(
            'Current Quoted Instruction XMP Title',
            'Only quoted xpacket pseudo-attributes delimit the active packet.',
            '2026-06-08T03:02:31-04:00'
        );
        $pdf = $xmpUnquotedInstructionPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Unquoted Instruction Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Quoted Instruction XMP Title', $metadata['title']);
        $t->same('Only quoted xpacket pseudo-attributes delimit the active packet.', $metadata['description']);
        $t->same(['Unquoted Instruction Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-unquoted-instruction'], $metadata['keywords']);
        $t->same('Unquoted Instruction Tool', $metadata['creator_tool']);
        $t->same('Unquoted Instruction Producer', $metadata['producer']);
        $t->same('2026-06-08T03:02:31-04:00', $metadata['created_at']);
        $t->same('2026-06-08T07:02:31Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T07:02:31Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Unquoted Instruction Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Unquoted Instruction Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Unquoted Instruction XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Quoted Instruction Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Quoted Instruction XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Unquoted Instruction XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Quoted Instruction Decoy Title'));
    },
    'summarizes rejected XML streams from quoted xpacket boundaries only' => static function (
        TestRunner $t
    ) use ($xmpUnquotedInstructionBytes, $xmpUnquotedInstructionPdf): void {
        $metadataBytes = $xmpUnquotedInstructionBytes(
            'Rejected Quoted Instruction XMP Title',
            'Rejected current quoted packet is summarized but redacted.',
            '2026-06-08T07:03:31Z'
        );
        $pdf = $xmpUnquotedInstructionPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Unquoted Instruction Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Unquoted Instruction Info Title', $metadata['title']);
        $t->same('Rejected XMP Unquoted Instruction Boundary Body', $plainText);
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
        $t->same('2026-06-08T07:03:31Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T07:02:31Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Quoted Instruction XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Unquoted Instruction XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Quoted Instruction Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Quoted Instruction XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Unquoted Instruction XMP Title'));
    },
];
