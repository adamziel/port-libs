<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpAttributeBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' dc:title="' . htmlspecialchars($title, ENT_XML1) . '"'
        . ' dc:creator="Doe, Jane"'
        . ' dc:description="' . htmlspecialchars($description, ENT_XML1) . '"'
        . ' dc:subject="wordpress, xmp-attribute; compact-rdf"'
        . ' pdf:Producer="Attribute Boundary Producer"'
        . ' xmp:CreatorTool="Attribute Boundary Tool"'
        . ' xmp:CreateDate="' . htmlspecialchars($date, ENT_XML1) . '"'
        . ' xmp:MetadataDate="2026-06-05T11:46:50Z"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpAttributeBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP attribute boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Attribute Boundary Info Title) /Author (Info Attribute Author) /Producer (Info Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts compact RDF XMP attributes without splitting comma-bearing creator names' => static function (
        TestRunner $t
    ) use ($xmpAttributeBoundaryPacket, $xmpAttributeBoundaryPdf): void {
        $currentXmp = $xmpAttributeBoundaryPacket(
            'Current Attribute Boundary XMP Title',
            'Compact RDF attributes remain document metadata before WordPress import',
            '2026-06-05T07:46:50-04:00'
        );
        $decoyXmp = $xmpAttributeBoundaryPacket(
            'Trailing Attribute Boundary Decoy Title',
            'Trailing compact RDF packet stays outside the current boundary',
            '2026-06-05T11:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpAttributeBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Attribute Boundary XMP Title', $metadata['title']);
        $t->same('Compact RDF attributes remain document metadata before WordPress import', $metadata['description']);
        $t->same(['Doe, Jane'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-attribute', 'compact-rdf'], $metadata['keywords']);
        $t->same('Attribute Boundary Tool', $metadata['creator_tool']);
        $t->same('Attribute Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T07:46:50-04:00', $metadata['created_at']);
        $t->same('2026-06-05T11:46:50Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T11:46:50Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Attribute Boundary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Attribute Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Attribute Boundary Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing compact RDF packet'));
        $t->true(!str_contains($plainText, 'Current Attribute Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Attribute Boundary Decoy Title'));
    },
    'summarizes rejected compact RDF attribute XMP streams with redacted creator counts' => static function (
        TestRunner $t
    ) use ($xmpAttributeBoundaryPacket, $xmpAttributeBoundaryPdf): void {
        $currentXmp = $xmpAttributeBoundaryPacket(
            'Rejected Attribute Boundary XMP Title',
            'Rejected compact RDF attributes are summarized only',
            '2026-06-05T11:47:50Z'
        );
        $decoyXmp = $xmpAttributeBoundaryPacket(
            'Rejected Attribute Boundary Decoy Title',
            'Rejected trailing compact RDF packet stays hidden',
            '2026-06-05T11:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpAttributeBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Attribute Boundary Info Title', $metadata['title']);
        $t->same('Rejected XMP Attribute Boundary Body', $plainText);
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
        $t->same('2026-06-05T11:47:50Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T11:46:50Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Attribute Boundary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Doe, Jane'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Attribute Boundary Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Attribute Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Attribute Boundary Decoy Title'));
    },
];
