<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpCommentBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Comment Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-comment-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Comment Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Comment Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T02:12:24Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpCommentBoundaryStream = static function (
    string $currentPacket,
    string $commentDecoyPacket,
    string $doctypeDecoyTitle,
    string $trailingPacket
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<!-- ' . $commentDecoyPacket . ' -->'
        . '<!DOCTYPE xmpmeta [<!ENTITY decoy "<rdf:RDF><rdf:Description xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot;><dc:title>'
        . htmlspecialchars($doctypeDecoyTitle, ENT_XML1)
        . '</dc:title></rdf:Description></rdf:RDF>">]>'
        . '<?adobe-xap-filters esc="CRLF"?>'
        . $currentPacket
        . '<?xpacket end="w"?>'
        . "\0\0 \n"
        . $trailingPacket;
};

$xmpCommentBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP comment boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Comment Boundary Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores comment and doctype decoy XMP roots before the current root packet' => static function (
        TestRunner $t
    ) use ($xmpCommentBoundaryPacket, $xmpCommentBoundaryStream, $xmpCommentBoundaryPdf): void {
        $currentXmp = $xmpCommentBoundaryPacket(
            'Current Comment Boundary XMP Title',
            'Current XMP root follows packet comments and declarations',
            '2026-06-04T22:12:24-04:00'
        );
        $commentDecoyXmp = $xmpCommentBoundaryPacket(
            'Comment Decoy XMP Title',
            'Comment decoy must never become document metadata',
            '2026-06-05T02:59:59Z'
        );
        $trailingXmp = $xmpCommentBoundaryPacket(
            'Trailing Comment Boundary Decoy Title',
            'Trailing packet stays outside the current XMP root',
            '2026-06-05T03:00:00Z'
        );
        $metadataBytes = $xmpCommentBoundaryStream(
            $currentXmp,
            $commentDecoyXmp,
            'DOCTYPE Decoy XMP Title',
            $trailingXmp
        );
        $pdf = $xmpCommentBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Comment Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Comment Boundary XMP Title', $metadata['title']);
        $t->same('Current XMP root follows packet comments and declarations', $metadata['description']);
        $t->same(['Comment Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-comment-boundary'], $metadata['keywords']);
        $t->same('Comment Boundary Tool', $metadata['creator_tool']);
        $t->same('Comment Boundary Producer', $metadata['producer']);
        $t->same('2026-06-04T22:12:24-04:00', $metadata['created_at']);
        $t->same('2026-06-05T02:12:24Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T02:12:24Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Comment Boundary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Comment Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Comment Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'DOCTYPE Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Comment Boundary Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Comment Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Comment Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'DOCTYPE Decoy XMP Title'));
    },
    'summarizes rejected XML metadata streams without using comment decoy roots' => static function (
        TestRunner $t
    ) use ($xmpCommentBoundaryPacket, $xmpCommentBoundaryStream, $xmpCommentBoundaryPdf): void {
        $currentXmp = $xmpCommentBoundaryPacket(
            'Rejected Current Comment XMP Title',
            'Rejected current root is summarized but redacted',
            '2026-06-05T02:13:24Z'
        );
        $commentDecoyXmp = $xmpCommentBoundaryPacket(
            'Rejected Comment Decoy XMP Title',
            'Rejected comment decoy must not define the review summary',
            '2026-06-05T02:59:59Z'
        );
        $trailingXmp = $xmpCommentBoundaryPacket(
            'Rejected Trailing Decoy XMP Title',
            'Rejected trailing packet stays hidden',
            '2026-06-05T03:00:00Z'
        );
        $metadataBytes = $xmpCommentBoundaryStream(
            $currentXmp,
            $commentDecoyXmp,
            'Rejected DOCTYPE Decoy XMP Title',
            $trailingXmp
        );
        $pdf = $xmpCommentBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Comment Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Comment Boundary Info Title', $metadata['title']);
        $t->same('Rejected XMP Comment Boundary Body', $plainText);
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
        $t->same('2026-06-05T02:13:24Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Current Comment XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Comment Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected DOCTYPE Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Current Comment XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Comment Decoy XMP Title'));
    },
];
