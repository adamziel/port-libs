<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpContainerAttributeMembershipPacket = static function (
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
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq>'
        . '<rdf:Description rdf:_2="Container Attribute Reviewer" rdf:_1="Container Attribute Editor" rdf:_10="Container Attribute Contributor">'
        . '<xmp:PrivateRole>container attribute role decoy</xmp:PrivateRole>'
        . '</rdf:Description>'
        . '</rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag>'
        . '<rdf:Description rdf:_2="xmp-container-attribute" rdf:_1="wordpress"><xmp:PrivateTag>container keyword decoy</xmp:PrivateTag></rdf:Description>'
        . '</rdf:Bag></dc:subject>'
        . '<pdf:Producer>Container Attribute Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Container Attribute Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T06:05:43Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#privateContainer">'
        . '<rdf:Seq><rdf:Description rdf:_1="External Container Attribute Decoy"/></rdf:Seq>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpContainerAttributeMembershipPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP container attribute-membership fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Container Attribute Info Title) /Author (Info Container Attribute Author) /Keywords (info, fallback) /Producer (Info Container Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts XMP RDF container attribute membership lists before WordPress import' => static function (
        TestRunner $t
    ) use ($xmpContainerAttributeMembershipPacket, $xmpContainerAttributeMembershipPdf): void {
        $currentXmp = $xmpContainerAttributeMembershipPacket(
            'Current Container Attribute XMP Title',
            'Nested RDF container attribute membership remains ordered XMP metadata.',
            '2026-06-08T02:05:43-04:00'
        );
        $decoyXmp = $xmpContainerAttributeMembershipPacket(
            'Trailing Container Attribute Decoy Title',
            'Trailing container attribute packet stays outside metadata.',
            '2026-06-08T06:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpContainerAttributeMembershipPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Container Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Container Attribute XMP Title', $metadata['title']);
        $t->same('Nested RDF container attribute membership remains ordered XMP metadata.', $metadata['description']);
        $t->same(['Container Attribute Editor', 'Container Attribute Reviewer', 'Container Attribute Contributor'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-container-attribute'], $metadata['keywords']);
        $t->same('Container Attribute Tool', $metadata['creator_tool']);
        $t->same('Container Attribute Producer', $metadata['producer']);
        $t->same('2026-06-08T02:05:43-04:00', $metadata['created_at']);
        $t->same('2026-06-08T06:05:43Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T06:05:43Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Container Attribute Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Container Attribute Boundary Body', $plainText);
        $t->true(!in_array('Info Container Attribute Author', $metadata['authors'], true));
        $t->true(is_string($encoded) && !str_contains($encoded, 'container attribute role decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'container keyword decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Container Attribute Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Container Attribute Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Container Attribute XMP Title'));
        $t->true(!str_contains($plainText, 'Container Attribute Editor'));
        $t->true(!str_contains($plainText, 'Trailing Container Attribute Decoy Title'));
    },
    'summarizes rejected XMP container attribute membership streams without text leakage' => static function (
        TestRunner $t
    ) use ($xmpContainerAttributeMembershipPacket, $xmpContainerAttributeMembershipPdf): void {
        $currentXmp = $xmpContainerAttributeMembershipPacket(
            'Rejected Container Attribute XMP Title',
            'Rejected container attribute packet is summarized only.',
            '2026-06-08T06:06:43Z'
        );
        $decoyXmp = $xmpContainerAttributeMembershipPacket(
            'Rejected Container Attribute Decoy Title',
            'Rejected trailing container attribute packet stays hidden.',
            '2026-06-08T06:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpContainerAttributeMembershipPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Container Attribute Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Container Attribute Info Title', $metadata['title']);
        $t->same('Rejected XMP Container Attribute Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(3, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-08T06:06:43Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T06:05:43Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Container Attribute XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Container Attribute Editor'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'container attribute role decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Container Attribute Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Container Attribute Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Container Attribute XMP Title'));
        $t->true(!str_contains($plainText, 'Container Attribute Editor'));
        $t->true(!str_contains($plainText, 'Rejected Container Attribute Decoy Title'));
    },
];
