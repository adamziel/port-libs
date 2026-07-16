<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpWrappedAttributeMembershipPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Description rdf:_2="Wrapped Attribute Reviewer" rdf:_1="Wrapped Attribute Editor" rdf:_10="Wrapped Attribute Contributor"/></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Description rdf:_2="xmp-wrapped-attribute" rdf:_1="wordpress"/></dc:subject>'
        . '<pdf:Producer>Wrapped Attribute Membership Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Wrapped Attribute Membership Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T11:52:48Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#wrappedAttributeDecoy" rdf:_1="External Wrapped Attribute Decoy"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpWrappedAttributeMembershipPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP wrapped attribute-membership fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Wrapped Attribute Info Title) /Author (Info Wrapped Attribute Author) /Keywords (info, fallback) /Producer (Info Wrapped Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'promotes RDF membership attributes on inline wrapped XMP list resources' => static function (
        TestRunner $t
    ) use ($xmpWrappedAttributeMembershipPacket, $xmpWrappedAttributeMembershipPdf): void {
        $currentXmp = $xmpWrappedAttributeMembershipPacket(
            'Current Wrapped Attribute Membership XMP Title',
            'Inline wrapped RDF membership attributes remain document XMP list metadata.',
            '2026-06-06T07:52:48-04:00'
        );
        $decoyXmp = $xmpWrappedAttributeMembershipPacket(
            'Trailing Wrapped Attribute Membership Decoy Title',
            'Trailing wrapped attribute-membership packet stays outside the current boundary.',
            '2026-06-06T11:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpWrappedAttributeMembershipPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Wrapped Attribute Membership Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Wrapped Attribute Membership XMP Title', $metadata['title']);
        $t->same('Inline wrapped RDF membership attributes remain document XMP list metadata.', $metadata['description']);
        $t->same(['Wrapped Attribute Editor', 'Wrapped Attribute Reviewer', 'Wrapped Attribute Contributor'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-wrapped-attribute'], $metadata['keywords']);
        $t->same('Wrapped Attribute Membership Tool', $metadata['creator_tool']);
        $t->same('Wrapped Attribute Membership Producer', $metadata['producer']);
        $t->same('2026-06-06T07:52:48-04:00', $metadata['created_at']);
        $t->same('2026-06-06T11:52:48Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T11:52:48Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Wrapped Attribute Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Wrapped Attribute Membership Boundary Body', $plainText);
        $t->true(!in_array('Info Wrapped Attribute Author', $metadata['authors'], true));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Wrapped Attribute Membership Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Wrapped Attribute Decoy'));
        $t->true(!str_contains($plainText, 'Current Wrapped Attribute Membership XMP Title'));
        $t->true(!str_contains($plainText, 'Wrapped Attribute Editor'));
        $t->true(!str_contains($plainText, 'Trailing Wrapped Attribute Membership Decoy Title'));
    },
    'summarizes rejected XMP streams with inline wrapped attribute membership counts only' => static function (
        TestRunner $t
    ) use ($xmpWrappedAttributeMembershipPacket, $xmpWrappedAttributeMembershipPdf): void {
        $currentXmp = $xmpWrappedAttributeMembershipPacket(
            'Rejected Wrapped Attribute Membership XMP Title',
            'Rejected XMP wrapped attribute-membership lists are summarized only.',
            '2026-06-06T11:53:48Z'
        );
        $decoyXmp = $xmpWrappedAttributeMembershipPacket(
            'Rejected Wrapped Attribute Membership Decoy Title',
            'Rejected trailing wrapped attribute-membership packet stays hidden.',
            '2026-06-06T11:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpWrappedAttributeMembershipPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Wrapped Attribute Membership Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Wrapped Attribute Info Title', $metadata['title']);
        $t->same('Rejected XMP Wrapped Attribute Membership Boundary Body', $plainText);
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
        $t->same('2026-06-06T11:53:48Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T11:52:48Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Wrapped Attribute Membership XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Wrapped Attribute Editor'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Wrapped Attribute Membership Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Wrapped Attribute Membership XMP Title'));
        $t->true(!str_contains($plainText, 'Wrapped Attribute Editor'));
        $t->true(!str_contains($plainText, 'Rejected Wrapped Attribute Membership Decoy Title'));
    },
];
