<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpCompactDuplicatePropertyPacket = static function (
    string $title,
    string $description,
    string $date
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' dc:title="' . htmlspecialchars($title, ENT_XML1) . '"'
        . ' dc:creator="Compact Attribute Editor"'
        . ' dc:description="' . htmlspecialchars($description, ENT_XML1) . '"'
        . ' dc:subject="wordpress, xmp-compact-duplicate; metadata-boundary"'
        . ' pdf:Producer="Compact Attribute Producer"'
        . ' xmp:CreatorTool="Compact Attribute Tool"'
        . ' xmp:CreateDate="' . htmlspecialchars($date, ENT_XML1) . '"'
        . ' xmp:MetadataDate="2026-06-08T01:52:15Z">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Child Duplicate XMP Title Decoy</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Child Duplicate Author Decoy</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Child duplicate description decoy</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>child-duplicate-keyword-decoy</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Child Duplicate Producer Decoy</pdf:Producer>'
        . '<xmp:CreatorTool>Child Duplicate Tool Decoy</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-08T09:09:09Z</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T09:10:10Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpCompactDuplicatePropertyPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP compact duplicate-property fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\n"
        . "stream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Compact Duplicate Info Title) /Author (Info Compact Duplicate Author) /Producer (Info Compact Duplicate Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'prefers compact RDF document attributes over duplicate child XMP properties' => static function (
        TestRunner $t
    ) use ($xmpCompactDuplicatePropertyPacket, $xmpCompactDuplicatePropertyPdf): void {
        $currentXmp = $xmpCompactDuplicatePropertyPacket(
            'Current Compact Duplicate XMP Title',
            'Compact RDF document attributes own duplicate-property metadata.',
            '2026-06-07T21:52:15-04:00'
        );
        $decoyXmp = $xmpCompactDuplicatePropertyPacket(
            'Trailing Compact Duplicate Packet Decoy',
            'Trailing compact duplicate-property packet stays outside metadata.',
            '2026-06-08T02:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpCompactDuplicatePropertyPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Compact Duplicate Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Compact Duplicate XMP Title', $metadata['title']);
        $t->same('Compact RDF document attributes own duplicate-property metadata.', $metadata['description']);
        $t->same(['Compact Attribute Editor'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-compact-duplicate', 'metadata-boundary'], $metadata['keywords']);
        $t->same('Compact Attribute Tool', $metadata['creator_tool']);
        $t->same('Compact Attribute Producer', $metadata['producer']);
        $t->same('2026-06-07T21:52:15-04:00', $metadata['created_at']);
        $t->same('2026-06-08T01:52:15Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T01:52:15Z', $metadata['metadata_date_utc']);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Compact Duplicate Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Compact Duplicate Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Child Duplicate XMP Title Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Child Duplicate Author Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'child-duplicate-keyword-decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Compact Duplicate Packet Decoy'));
        $t->true(!str_contains($plainText, 'Current Compact Duplicate XMP Title'));
        $t->true(!str_contains($plainText, 'Child Duplicate XMP Title Decoy'));
        $t->true(!str_contains($plainText, 'Trailing Compact Duplicate Packet Decoy'));
    },
    'summarizes rejected compact duplicate-property XMP streams from attributes only' => static function (
        TestRunner $t
    ) use ($xmpCompactDuplicatePropertyPacket, $xmpCompactDuplicatePropertyPdf): void {
        $currentXmp = $xmpCompactDuplicatePropertyPacket(
            'Rejected Compact Duplicate XMP Title',
            'Rejected compact duplicate-property XMP is summarized only.',
            '2026-06-08T01:53:15Z'
        );
        $decoyXmp = $xmpCompactDuplicatePropertyPacket(
            'Rejected Compact Duplicate Packet Decoy',
            'Rejected trailing compact duplicate-property packet stays hidden.',
            '2026-06-08T02:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpCompactDuplicatePropertyPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Compact Duplicate Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Compact Duplicate Info Title', $metadata['title']);
        $t->same('Rejected XMP Compact Duplicate Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(1, $summary['author_count'] ?? null);
        $t->same(3, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-08T01:53:15Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T01:52:15Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Compact Duplicate XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Child Duplicate Author Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'child-duplicate-keyword-decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Compact Duplicate Packet Decoy'));
        $t->true(!str_contains($plainText, 'Rejected Compact Duplicate XMP Title'));
        $t->true(!str_contains($plainText, 'Child Duplicate XMP Title Decoy'));
        $t->true(!str_contains($plainText, 'Rejected Compact Duplicate Packet Decoy'));
    },
];
