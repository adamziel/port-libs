<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpInstructionAttributeRoot = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>Instruction Attribute Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-instruction-attribute</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Instruction Attribute Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Instruction Attribute Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T13:49:54Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpInstructionAttributePacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$xmpInstructionAttributeBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpInstructionAttributeRoot, $xmpInstructionAttributePacket): string {
    $fakeDelimitedStale = '<?xpacket id="not-a-delimiter begin=\'\'"?>'
        . $xmpInstructionAttributeRoot(
            'Stale Instruction Attribute XMP Title',
            'Quoted begin and end text in unrelated xpacket attributes must not define WordPress metadata.',
            '2026-06-06T13:59:59Z'
        )
        . '<?xpacket id="not-a-delimiter end=\'w\'"?>';
    $current = $xmpInstructionAttributePacket($xmpInstructionAttributeRoot($title, $description, $date));
    $trailing = $xmpInstructionAttributePacket($xmpInstructionAttributeRoot(
        'Trailing Instruction Attribute Decoy Title',
        'A trailing valid packet must stay outside the selected packet boundary.',
        '2026-06-06T14:59:59Z'
    ));

    return $fakeDelimitedStale . "\n" . $current . "\0\0" . $trailing;
};

$xmpInstructionAttributePdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP instruction-attribute boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Instruction Attribute Info Title) /Author (Info Instruction Attribute Author) /Producer (Info Instruction Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores quoted xpacket begin and end text inside unrelated instruction attributes' => static function (
        TestRunner $t
    ) use ($xmpInstructionAttributeBytes, $xmpInstructionAttributePdf): void {
        $metadataBytes = $xmpInstructionAttributeBytes(
            'Current Instruction Attribute XMP Title',
            'Only real xpacket pseudo-attributes delimit the active packet.',
            '2026-06-06T09:49:54-04:00'
        );
        $pdf = $xmpInstructionAttributePdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Instruction Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Instruction Attribute XMP Title', $metadata['title']);
        $t->same('Only real xpacket pseudo-attributes delimit the active packet.', $metadata['description']);
        $t->same(['Instruction Attribute Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-instruction-attribute'], $metadata['keywords']);
        $t->same('Instruction Attribute Boundary Tool', $metadata['creator_tool']);
        $t->same('Instruction Attribute Boundary Producer', $metadata['producer']);
        $t->same('2026-06-06T09:49:54-04:00', $metadata['created_at']);
        $t->same('2026-06-06T13:49:54Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T13:49:54Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Instruction Attribute Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Instruction Attribute Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Instruction Attribute XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Instruction Attribute Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Instruction Attribute XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Instruction Attribute XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Instruction Attribute Decoy Title'));
    },
    'summarizes rejected XML streams using real xpacket instruction attributes only' => static function (
        TestRunner $t
    ) use ($xmpInstructionAttributeBytes, $xmpInstructionAttributePdf): void {
        $metadataBytes = $xmpInstructionAttributeBytes(
            'Rejected Instruction Attribute XMP Title',
            'Rejected active packet is summarized but redacted.',
            '2026-06-06T13:50:54Z'
        );
        $pdf = $xmpInstructionAttributePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Instruction Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Instruction Attribute Info Title', $metadata['title']);
        $t->same('Rejected XMP Instruction Attribute Boundary Body', $plainText);
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
        $t->same('2026-06-06T13:50:54Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T13:49:54Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Instruction Attribute XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Instruction Attribute XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Instruction Attribute Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Instruction Attribute XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Instruction Attribute XMP Title'));
    },
];
