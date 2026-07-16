<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpAltAttributeMembershipPacket = static function (
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
        . '<dc:title><rdf:Alt rdf:_2="Ignored Secondary Title" rdf:_1="' . htmlspecialchars($title, ENT_XML1) . '"/></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Alt Attribute Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt rdf:_2="Ignored Secondary Description" rdf:_1="' . htmlspecialchars($description, ENT_XML1) . '"/></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-alt-attribute-membership</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Alt Attribute Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Alt Attribute Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T06:05:43Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#privateAlt" rdf:_1="External Alt Attribute Decoy"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpAltAttributeMembershipPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP Alt attribute-membership fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Alt Attribute Info Title) /Author (Info Alt Attribute Author) /Keywords (info, fallback) /Producer (Info Alt Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'promotes RDF Alt membership attributes from document XMP before WordPress import' => static function (
        TestRunner $t
    ) use ($xmpAltAttributeMembershipPacket, $xmpAltAttributeMembershipPdf): void {
        $currentXmp = $xmpAltAttributeMembershipPacket(
            'Current Alt Attribute XMP Title',
            'RDF Alt membership attributes remain ordered document metadata.',
            '2026-06-08T02:05:43-04:00'
        );
        $decoyXmp = $xmpAltAttributeMembershipPacket(
            'Trailing Alt Attribute Decoy Title',
            'Trailing Alt attribute packet stays outside metadata.',
            '2026-06-08T06:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpAltAttributeMembershipPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Alt Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Alt Attribute XMP Title', $metadata['title']);
        $t->same('RDF Alt membership attributes remain ordered document metadata.', $metadata['description']);
        $t->same(['Alt Attribute Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-alt-attribute-membership'], $metadata['keywords']);
        $t->same('Alt Attribute Tool', $metadata['creator_tool']);
        $t->same('Alt Attribute Producer', $metadata['producer']);
        $t->same('2026-06-08T02:05:43-04:00', $metadata['created_at']);
        $t->same('2026-06-08T06:05:43Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T06:05:43Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Alt Attribute Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Alt Attribute Boundary Body', $plainText);
        $t->true(!in_array('Info Alt Attribute Author', $metadata['authors'], true));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Ignored Secondary Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Ignored Secondary Description'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Alt Attribute Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Alt Attribute Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Alt Attribute XMP Title'));
        $t->true(!str_contains($plainText, 'Alt Attribute Editor'));
        $t->true(!str_contains($plainText, 'Trailing Alt Attribute Decoy Title'));
    },
    'summarizes rejected RDF Alt membership attributes without text leakage' => static function (
        TestRunner $t
    ) use ($xmpAltAttributeMembershipPacket, $xmpAltAttributeMembershipPdf): void {
        $currentXmp = $xmpAltAttributeMembershipPacket(
            'Rejected Alt Attribute XMP Title',
            'Rejected Alt attribute packet is summarized only.',
            '2026-06-08T06:06:43Z'
        );
        $decoyXmp = $xmpAltAttributeMembershipPacket(
            'Rejected Alt Attribute Decoy Title',
            'Rejected trailing Alt attribute packet stays hidden.',
            '2026-06-08T06:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpAltAttributeMembershipPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Alt Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Alt Attribute Info Title', $metadata['title']);
        $t->same('Rejected XMP Alt Attribute Boundary Body', $plainText);
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
        $t->same('2026-06-08T06:06:43Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T06:05:43Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Alt Attribute XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Alt Attribute Editor'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Ignored Secondary Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Alt Attribute Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Alt Attribute Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Alt Attribute XMP Title'));
        $t->true(!str_contains($plainText, 'Alt Attribute Editor'));
        $t->true(!str_contains($plainText, 'Rejected Alt Attribute Decoy Title'));
    },
];
