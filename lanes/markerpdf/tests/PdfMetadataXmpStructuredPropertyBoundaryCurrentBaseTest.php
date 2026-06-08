<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpStructuredPropertyBoundaryPacket = static function (string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title rdf:parseType="Resource"><xmp:PrivateTitle>Private Structured XMP Title</xmp:PrivateTitle></dc:title>'
        . '<dc:description rdf:parseType="Resource"><xmp:PrivateDescription>Private structured description leak</xmp:PrivateDescription></dc:description>'
        . '<dc:creator><rdf:Seq><rdf:li>Structured Property Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-structured-property</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer rdf:parseType="Resource"><xmp:PrivateProducer>Private Structured Producer</xmp:PrivateProducer></pdf:Producer>'
        . '<xmp:CreatorTool rdf:parseType="Resource"><xmp:PrivateTool>Private Structured Tool</xmp:PrivateTool></xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-07T23:48:01Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpStructuredPropertyBoundaryPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP structured-property boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Structured Property Info Title) /Subject (Structured Property Info Subject) /Author (Info Structured Author) /Creator (Info Structured Creator) /Producer (Info Structured Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'keeps structured XMP properties without rdf value out of document metadata' => static function (
        TestRunner $t
    ) use ($xmpStructuredPropertyBoundaryPacket, $xmpStructuredPropertyBoundaryPdf): void {
        $pdf = $xmpStructuredPropertyBoundaryPdf(
            $xmpStructuredPropertyBoundaryPacket('2026-06-07T19:48:01-04:00'),
            '/Type /Metadata /Subtype /XML',
            'XMP Structured Property Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Structured Property Info Title', $metadata['title']);
        $t->same('Structured Property Info Subject', $metadata['description']);
        $t->same('Info Structured Creator', $metadata['creator_tool']);
        $t->same('Info Structured Producer', $metadata['producer']);
        $t->same(['Structured Property Author'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-structured-property'], $metadata['keywords']);
        $t->same('2026-06-07T19:48:01-04:00', $metadata['created_at']);
        $t->same('2026-06-07T23:48:01Z', $metadata['created_at_utc']);
        $t->same('2026-06-07T23:48:01Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same(false, isset($metadata['xmp']['title']));
        $t->same(false, isset($metadata['xmp']['description']));
        $t->same(false, isset($metadata['xmp']['creator_tool']));
        $t->same(false, isset($metadata['xmp']['producer']));
        $t->same('XMP Structured Property Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Structured XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private structured description leak'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Structured Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Structured Tool'));
        $t->true(!str_contains($plainText, 'Private Structured XMP Title'));
        $t->true(!str_contains($plainText, 'Private Structured Tool'));
    },
    'summarizes rejected structured XMP streams without private scalar fields' => static function (
        TestRunner $t
    ) use ($xmpStructuredPropertyBoundaryPacket, $xmpStructuredPropertyBoundaryPdf): void {
        $metadataBytes = $xmpStructuredPropertyBoundaryPacket('2026-06-07T23:49:01Z');
        $pdf = $xmpStructuredPropertyBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Structured Property Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Structured Property Info Title', $metadata['title']);
        $t->same('Rejected XMP Structured Property Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(1, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-07T23:49:01Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-07T23:48:01Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Structured XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private structured description leak'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Structured Producer'));
        $t->true(!str_contains($plainText, 'Private Structured XMP Title'));
        $t->true(!str_contains($plainText, 'Private Structured Producer'));
    },
];
