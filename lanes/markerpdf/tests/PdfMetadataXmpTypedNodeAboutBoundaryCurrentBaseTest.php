<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpTypedNodeAboutPacket = static function (
    string $title,
    string $description,
    string $date,
    bool $typedDocumentNode = false
): string {
    $documentProperties = '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">'
        . htmlspecialchars($title, ENT_XML1)
        . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Typed About Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">'
        . htmlspecialchars($description, ENT_XML1)
        . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-typed-about</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Typed About Boundary Producer</pdf:Producer>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T09:16:19Z</xmp:MetadataDate>';

    $documentNode = $typedDocumentNode
        ? '<xmp:Document rdf:about=""'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
            . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
            . ' xmp:CreatorTool="Explicit Typed About Tool">'
            . $documentProperties
            . '</xmp:Document>'
        : '<rdf:Description rdf:about=""'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
            . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
            . $documentProperties
            . '<xmp:CreatorTool>Typed About Boundary Tool</xmp:CreatorTool>'
            . '</rdf:Description>';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<xmp:PrivateReview'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmp:CreatorTool="Private Blank Typed Tool">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Private Blank Typed XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Private Blank Typed Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Private blank typed resource should not describe the document</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>private-blank-typed</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Private Blank Typed Producer</pdf:Producer>'
        . '<xmp:CreateDate>2026-06-06T01:01:01Z</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T01:01:02Z</xmp:MetadataDate>'
        . '</xmp:PrivateReview>'
        . $documentNode
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpTypedNodeAboutPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP typed-node rdf:about boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Typed About Info Fallback Title) /Author (Typed About Info Author) /Producer (Typed About Info Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores anonymous top-level typed XMP resources before document descriptions' => static function (
        TestRunner $t
    ) use ($xmpTypedNodeAboutPacket, $xmpTypedNodeAboutPdf): void {
        $currentXmp = $xmpTypedNodeAboutPacket(
            'Current Typed About XMP Title',
            'Anonymous typed resources stay out of document metadata',
            '2026-06-06T05:16:19-04:00'
        );
        $trailingDecoy = $xmpTypedNodeAboutPacket(
            'Trailing Typed About Decoy Title',
            'Trailing typed-about packet must stay hidden',
            '2026-06-06T09:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $trailingDecoy;
        $pdf = $xmpTypedNodeAboutPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Typed About Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Typed About XMP Title', $metadata['title']);
        $t->same('Anonymous typed resources stay out of document metadata', $metadata['description']);
        $t->same(['Typed About Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-typed-about'], $metadata['keywords']);
        $t->same('Typed About Boundary Tool', $metadata['creator_tool']);
        $t->same('Typed About Boundary Producer', $metadata['producer']);
        $t->same('2026-06-06T05:16:19-04:00', $metadata['created_at']);
        $t->same('2026-06-06T09:16:19Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T09:16:19Z', $metadata['metadata_date_utc']);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('XMP Typed About Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed Tool'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Typed About Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Typed About XMP Title'));
        $t->true(!str_contains($plainText, 'Private Blank Typed XMP Title'));
    },
    'keeps explicit rdf about typed XMP document nodes accepted' => static function (
        TestRunner $t
    ) use ($xmpTypedNodeAboutPacket, $xmpTypedNodeAboutPdf): void {
        $metadataBytes = $xmpTypedNodeAboutPacket(
            'Explicit Typed About XMP Title',
            'Explicit rdf about typed nodes are document metadata',
            '2026-06-06T09:17:19Z',
            true
        );
        $pdf = $xmpTypedNodeAboutPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'Explicit XMP Typed About Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Explicit Typed About XMP Title', $metadata['title']);
        $t->same('Explicit rdf about typed nodes are document metadata', $metadata['description']);
        $t->same(['Typed About Editor', 'Import Review Team'], $metadata['authors']);
        $t->same('Explicit Typed About Tool', $metadata['creator_tool']);
        $t->same('2026-06-06T09:17:19Z', $metadata['created_at_utc']);
        $t->same('Explicit XMP Typed About Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed Tool'));
    },
    'summarizes rejected typed-node streams without anonymous resource values' => static function (
        TestRunner $t
    ) use ($xmpTypedNodeAboutPacket, $xmpTypedNodeAboutPdf): void {
        $currentXmp = $xmpTypedNodeAboutPacket(
            'Rejected Typed About XMP Title',
            'Rejected typed-about XMP is summarized only',
            '2026-06-06T09:18:19Z'
        );
        $trailingDecoy = $xmpTypedNodeAboutPacket(
            'Rejected Trailing Typed About Decoy Title',
            'Rejected trailing typed-about packet stays hidden',
            '2026-06-06T09:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $trailingDecoy;
        $pdf = $xmpTypedNodeAboutPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Typed About Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Typed About Info Fallback Title', $metadata['title']);
        $t->same('Rejected XMP Typed About Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(2, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-06T09:18:19Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T09:16:19Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Blank Typed Tool'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Typed About Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, '2026-06-06T01:01:01Z'));
        $t->true(!str_contains($plainText, 'Rejected Typed About XMP Title'));
        $t->true(!str_contains($plainText, 'Private Blank Typed XMP Title'));
    },
];
