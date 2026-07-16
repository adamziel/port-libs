<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpUndefinedEntityBoundaryRoot = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>Undefined Entity Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-undefined-entity-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Undefined Entity Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Undefined Entity Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T23:18:33Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpUndefinedEntityBoundaryPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$xmpUndefinedEntityBoundaryBytes = static function (
    string $trailingTitle
) use ($xmpUndefinedEntityBoundaryPacket, $xmpUndefinedEntityBoundaryRoot): string {
    $malformedFirstPacket = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">&undefinedTitle;</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Undefined Entity Boundary Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">&undefinedDescription;</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-undefined-entity-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Undefined Entity Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Undefined Entity Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>&undefinedDate;</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T23:18:33Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $trailingPacket = $xmpUndefinedEntityBoundaryPacket($xmpUndefinedEntityBoundaryRoot(
        $trailingTitle,
        'A later valid packet must not replace an undefined-entity packet.',
        '2026-06-08T23:29:33Z'
    ));

    return $malformedFirstPacket . "\n\0\0" . $trailingPacket;
};

$xmpUndefinedEntityBoundaryPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress undefined-entity XMP boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Undefined Entity Info Title) /Author (Info Undefined Entity Author) /Producer (Info Undefined Entity Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'fails closed on undefined XMP entity references before trailing packet promotion' => static function (
        TestRunner $t
    ) use ($xmpUndefinedEntityBoundaryBytes, $xmpUndefinedEntityBoundaryPdf): void {
        $metadataBytes = $xmpUndefinedEntityBoundaryBytes('Trailing Undefined Entity Decoy Title');
        $pdf = $xmpUndefinedEntityBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Undefined Entity Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Undefined Entity Info Title', $metadata['title']);
        $t->same(['Info Undefined Entity Author'], $metadata['authors']);
        $t->same('Info Undefined Entity Producer', $metadata['producer']);
        $t->same('XMP Undefined Entity Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_document_xmp_xml', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('xmp_packet_review', $summary['source'] ?? null);
        $t->same('rejected_malformed_xmp_xml', $summary['status'] ?? null);
        $t->same('strict_dom_parse', $summary['malformed_xml_boundary'] ?? null);
        $t->same('xml_parse_failed', $summary['reason'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(0, $summary['author_count'] ?? null);
        $t->same(0, $summary['keyword_count'] ?? null);
        $t->same('UTF-8', $summary['packet_encoding'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(($summary['malformed_xml_error_count'] ?? 0) > 0);
        $t->true(is_string($encoded) && !str_contains($encoded, 'undefinedTitle'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'undefinedDescription'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Undefined Entity Decoy Title'));
        $t->true(!str_contains($plainText, 'undefinedTitle'));
        $t->true(!str_contains($plainText, 'Trailing Undefined Entity Decoy Title'));
    },
    'summarizes undefined entities in rejected XML streams without metadata promotion' => static function (
        TestRunner $t
    ) use ($xmpUndefinedEntityBoundaryBytes, $xmpUndefinedEntityBoundaryPdf): void {
        $metadataBytes = $xmpUndefinedEntityBoundaryBytes('Rejected Trailing Undefined Entity Decoy Title');
        $pdf = $xmpUndefinedEntityBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Undefined Entity Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Undefined Entity Info Title', $metadata['title']);
        $t->same('Rejected XMP Undefined Entity Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_malformed_xmp_xml', $summary['status'] ?? null);
        $t->same('strict_dom_parse', $summary['malformed_xml_boundary'] ?? null);
        $t->same('xml_parse_failed', $summary['reason'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(0, $summary['author_count'] ?? null);
        $t->same(0, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(($summary['malformed_xml_error_count'] ?? 0) > 0);
        $t->true(is_string($encoded) && !str_contains($encoded, 'undefinedTitle'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'undefinedDescription'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Undefined Entity Decoy Title'));
        $t->true(!str_contains($plainText, 'undefinedTitle'));
        $t->true(!str_contains($plainText, 'Rejected Trailing Undefined Entity Decoy Title'));
    },
];
