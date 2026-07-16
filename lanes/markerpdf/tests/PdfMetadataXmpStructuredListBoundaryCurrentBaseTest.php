<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpStructuredListBoundaryPacket = static function (
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
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt>'
        . '<rdf:li xml:lang="x-default" rdf:parseType="Resource">'
        . '<xmp:PrivateTitle>Structured Title Decoy</xmp:PrivateTitle>'
        . '<xmp:PrivateQualifier>title qualifier decoy</xmp:PrivateQualifier>'
        . '</rdf:li>'
        . '<rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq>'
        . '<rdf:li rdf:parseType="Resource">'
        . '<xmp:PrivateAuthor>Structured Author Decoy</xmp:PrivateAuthor>'
        . '<xmp:PrivateRole>author role decoy</xmp:PrivateRole>'
        . '</rdf:li>'
        . '<rdf:li>Structured List Author</rdf:li>'
        . '</rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt>'
        . '<rdf:li xml:lang="x-default" rdf:parseType="Resource">'
        . '<xmp:PrivateDescription>Structured Description Decoy</xmp:PrivateDescription>'
        . '<xmp:PrivateReview>description review decoy</xmp:PrivateReview>'
        . '</rdf:li>'
        . '<rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag>'
        . '<rdf:li rdf:parseType="Resource">'
        . '<xmp:PrivateKeyword>structured-keyword-decoy</xmp:PrivateKeyword>'
        . '<xmp:PrivateTag>keyword tag decoy</xmp:PrivateTag>'
        . '</rdf:li>'
        . '<rdf:li>wordpress</rdf:li>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>xmp-structured-list</rdf:value><xmp:PrivateTag>safe keyword qualifier</xmp:PrivateTag></rdf:li>'
        . '</rdf:Bag></dc:subject>'
        . '<pdf:Producer>Structured List Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Structured List Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-07T14:12:29Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpStructuredListBoundaryPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP structured-list fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Structured List Info Title) /Author (Info Structured Author) /Keywords (info, fallback) /Producer (Info Structured Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'skips structured XMP list nodes without rdf value before WordPress metadata import' => static function (
        TestRunner $t
    ) use ($xmpStructuredListBoundaryPacket, $xmpStructuredListBoundaryPdf): void {
        $currentXmp = $xmpStructuredListBoundaryPacket(
            'Current Structured List XMP Title',
            'Structured list nodes without rdf:value stay review-only.',
            '2026-06-07T10:12:29-04:00'
        );
        $decoyXmp = $xmpStructuredListBoundaryPacket(
            'Trailing Structured List Decoy Title',
            'Trailing structured-list packet stays outside metadata.',
            '2026-06-07T14:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpStructuredListBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Structured List Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Structured List XMP Title', $metadata['title']);
        $t->same('Structured list nodes without rdf:value stay review-only.', $metadata['description']);
        $t->same(['Structured List Author'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-structured-list'], $metadata['keywords']);
        $t->same('Structured List Tool', $metadata['creator_tool']);
        $t->same('Structured List Producer', $metadata['producer']);
        $t->same('2026-06-07T10:12:29-04:00', $metadata['created_at']);
        $t->same('2026-06-07T14:12:29Z', $metadata['created_at_utc']);
        $t->same('2026-06-07T14:12:29Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Structured List Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Structured List Boundary Body', $plainText);
        $t->true(!in_array('Info Structured Author', $metadata['authors'], true));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Structured Title Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Structured Author Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Structured Description Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'structured-keyword-decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'safe keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Structured List Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Structured List XMP Title'));
        $t->true(!str_contains($plainText, 'Structured Author Decoy'));
        $t->true(!str_contains($plainText, 'Trailing Structured List Decoy Title'));
    },
    'summarizes rejected structured XMP list streams without flattening private nodes' => static function (
        TestRunner $t
    ) use ($xmpStructuredListBoundaryPacket, $xmpStructuredListBoundaryPdf): void {
        $currentXmp = $xmpStructuredListBoundaryPacket(
            'Rejected Structured List XMP Title',
            'Rejected structured-list packet is summarized only.',
            '2026-06-07T14:13:29Z'
        );
        $decoyXmp = $xmpStructuredListBoundaryPacket(
            'Rejected Structured List Decoy Title',
            'Rejected trailing structured-list packet stays hidden.',
            '2026-06-07T14:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpStructuredListBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Structured List Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Structured List Info Title', $metadata['title']);
        $t->same('Rejected XMP Structured List Boundary Body', $plainText);
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
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-07T14:13:29Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-07T14:12:29Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Structured List XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Structured Author Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'structured-keyword-decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'safe keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Structured List Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Structured List XMP Title'));
        $t->true(!str_contains($plainText, 'Structured Author Decoy'));
        $t->true(!str_contains($plainText, 'Rejected Structured List Decoy Title'));
    },
];
