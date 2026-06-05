<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpExternalAboutPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="https://example.org/not-this-pdf"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">External Resource Decoy XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>External Resource Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">External resource description must not become document metadata</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>external-resource-decoy</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>External Resource Producer</pdf:Producer>'
        . '<xmp:CreatorTool>External Resource Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-05T22:59:59Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>External About Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-external-about</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>External About Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>External About Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T22:14:53Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpExternalAboutPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP external-about boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (External About Info Title) /Author (Info External About Author) /Producer (Info External About Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores external-resource XMP descriptions before document metadata import' => static function (
        TestRunner $t
    ) use ($xmpExternalAboutPacket, $xmpExternalAboutPdf): void {
        $currentXmp = $xmpExternalAboutPacket(
            'Current External About XMP Title',
            'External rdf:about descriptions stay out of document metadata',
            '2026-06-05T18:14:53-04:00'
        );
        $decoyXmp = $xmpExternalAboutPacket(
            'Trailing External About Decoy Title',
            'Trailing packet must stay outside the current document metadata',
            '2026-06-05T22:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpExternalAboutPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP External About Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current External About XMP Title', $metadata['title']);
        $t->same('External rdf:about descriptions stay out of document metadata', $metadata['description']);
        $t->same(['External About Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-external-about'], $metadata['keywords']);
        $t->same('External About Boundary Tool', $metadata['creator_tool']);
        $t->same('External About Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T18:14:53-04:00', $metadata['created_at']);
        $t->same('2026-06-05T22:14:53Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T22:14:53Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('External About Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP External About Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Resource Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Resource Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Resource Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing External About Decoy Title'));
        $t->true(!str_contains($plainText, 'Current External About XMP Title'));
        $t->true(!str_contains($plainText, 'External Resource Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing External About Decoy Title'));
    },
    'summarizes rejected XMP streams from document descriptions only' => static function (
        TestRunner $t
    ) use ($xmpExternalAboutPacket, $xmpExternalAboutPdf): void {
        $currentXmp = $xmpExternalAboutPacket(
            'Rejected External About XMP Title',
            'Rejected external-about packet is summarized only',
            '2026-06-05T22:15:53Z'
        );
        $decoyXmp = $xmpExternalAboutPacket(
            'Rejected External About Decoy Title',
            'Rejected trailing packet must stay hidden',
            '2026-06-05T22:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpExternalAboutPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP External About Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('External About Info Title', $metadata['title']);
        $t->same('Rejected XMP External About Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(2, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T22:15:53Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T22:14:53Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected External About XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Resource Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Resource Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected External About Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected External About XMP Title'));
        $t->true(!str_contains($plainText, 'External Resource Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected External About Decoy Title'));
    },
];
