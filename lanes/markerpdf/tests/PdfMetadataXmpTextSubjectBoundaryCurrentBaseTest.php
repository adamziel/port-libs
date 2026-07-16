<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpTextSubjectBoundaryPacket = static function (
    string $title,
    string $description,
    string $date,
    string $subject = 'wordpress, xmp-text-subject; import-review'
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title>' . htmlspecialchars($title, ENT_XML1) . '</dc:title>'
        . '<dc:creator>Doe, Jane</dc:creator>'
        . '<dc:description>' . htmlspecialchars($description, ENT_XML1) . '</dc:description>'
        . '<dc:subject>' . htmlspecialchars($subject, ENT_XML1) . '</dc:subject>'
        . '<pdf:Producer>Text Subject Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Text Subject Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T12:58:30Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpTextSubjectBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP text-subject boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Text Subject Info Title) /Author (Info Subject Author) /Producer (Info Subject Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'splits simple text XMP dc subject keywords without splitting creator names' => static function (
        TestRunner $t
    ) use ($xmpTextSubjectBoundaryPacket, $xmpTextSubjectBoundaryPdf): void {
        $currentXmp = $xmpTextSubjectBoundaryPacket(
            'Current Text Subject XMP Title',
            'Simple text dc:subject values are keyword metadata',
            '2026-06-05T08:58:30-04:00'
        );
        $decoyXmp = $xmpTextSubjectBoundaryPacket(
            'Trailing Text Subject Decoy Title',
            'Trailing text subject packet must stay outside the boundary',
            '2026-06-05T12:59:59Z',
            'decoy, should-not-leak'
        );
        $pdf = $xmpTextSubjectBoundaryPdf(
            $currentXmp . "\0\0 " . $decoyXmp,
            '/Type /Metadata /Subtype /XML',
            'XMP Text Subject Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Text Subject XMP Title', $metadata['title']);
        $t->same('Simple text dc:subject values are keyword metadata', $metadata['description']);
        $t->same(['Doe, Jane'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-text-subject', 'import-review'], $metadata['keywords']);
        $t->same('Text Subject Boundary Tool', $metadata['creator_tool']);
        $t->same('Text Subject Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T08:58:30-04:00', $metadata['created_at']);
        $t->same('2026-06-05T12:58:30Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T12:58:30Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Text Subject Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Text Subject Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Text Subject Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'should-not-leak'));
        $t->true(!str_contains($plainText, 'Current Text Subject XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Text Subject Decoy Title'));
    },
    'summarizes rejected simple text XMP subject streams with split keyword counts' => static function (
        TestRunner $t
    ) use ($xmpTextSubjectBoundaryPacket, $xmpTextSubjectBoundaryPdf): void {
        $currentXmp = $xmpTextSubjectBoundaryPacket(
            'Rejected Text Subject XMP Title',
            'Rejected simple text subject packet is summarized only',
            '2026-06-05T12:59:30Z'
        );
        $decoyXmp = $xmpTextSubjectBoundaryPacket(
            'Rejected Text Subject Decoy Title',
            'Rejected trailing text subject packet stays hidden',
            '2026-06-05T12:59:59Z',
            'decoy, should-not-leak'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpTextSubjectBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Text Subject Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Text Subject Info Title', $metadata['title']);
        $t->same('Rejected XMP Text Subject Boundary Body', $plainText);
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
        $t->same('2026-06-05T12:59:30Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T12:58:30Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Text Subject XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Doe, Jane'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'should-not-leak'));
        $t->true(!str_contains($plainText, 'Rejected Text Subject XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Text Subject Decoy Title'));
    },
];
