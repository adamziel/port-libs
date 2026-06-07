<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpAboutEachPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:aboutEach="#privateCollection"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Private AboutEach XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Private AboutEach Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Private aboutEach description must not become document metadata</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>private-about-each</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Private AboutEach Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Private AboutEach Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-07T21:59:59Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:aboutEachPrefix="https://example.org/private/"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Private AboutEachPrefix XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Private AboutEachPrefix Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li>private-about-each-prefix</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Private AboutEachPrefix Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Private AboutEachPrefix Tool</xmp:CreatorTool>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>AboutEach Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-about-each-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>AboutEach Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>AboutEach Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-07T21:47:51Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#privateCollection" rdf:value="Private collection resource scalar"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpAboutEachPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP aboutEach boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (AboutEach Info Title) /Author (Info AboutEach Author) /Producer (Info AboutEach Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores XMP rdf aboutEach resources before document metadata import' => static function (
        TestRunner $t
    ) use ($xmpAboutEachPacket, $xmpAboutEachPdf): void {
        $currentXmp = $xmpAboutEachPacket(
            'Current AboutEach XMP Title',
            'RDF aboutEach resources stay out of document metadata before WordPress import.',
            '2026-06-07T17:47:51-04:00'
        );
        $trailingXmp = $xmpAboutEachPacket(
            'Trailing AboutEach Decoy Title',
            'Trailing aboutEach packet stays outside the active packet boundary.',
            '2026-06-07T22:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $trailingXmp;
        $pdf = $xmpAboutEachPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP AboutEach Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current AboutEach XMP Title', $metadata['title']);
        $t->same('RDF aboutEach resources stay out of document metadata before WordPress import.', $metadata['description']);
        $t->same(['AboutEach Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-about-each-boundary'], $metadata['keywords']);
        $t->same('AboutEach Boundary Tool', $metadata['creator_tool']);
        $t->same('AboutEach Boundary Producer', $metadata['producer']);
        $t->same('2026-06-07T17:47:51-04:00', $metadata['created_at']);
        $t->same('2026-06-07T21:47:51Z', $metadata['created_at_utc']);
        $t->same('2026-06-07T21:47:51Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('AboutEach Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP AboutEach Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private AboutEach XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private AboutEach Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private AboutEachPrefix XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private collection resource scalar'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing AboutEach Decoy Title'));
        $t->true(!str_contains($plainText, 'Current AboutEach XMP Title'));
        $t->true(!str_contains($plainText, 'Private AboutEach XMP Title'));
        $t->true(!str_contains($plainText, 'Private AboutEachPrefix XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing AboutEach Decoy Title'));
    },
    'summarizes rejected XMP streams from document nodes after aboutEach resources' => static function (
        TestRunner $t
    ) use ($xmpAboutEachPacket, $xmpAboutEachPdf): void {
        $currentXmp = $xmpAboutEachPacket(
            'Rejected AboutEach XMP Title',
            'Rejected aboutEach packet is summarized only.',
            '2026-06-07T21:48:51Z'
        );
        $trailingXmp = $xmpAboutEachPacket(
            'Rejected Trailing AboutEach Decoy Title',
            'Rejected trailing aboutEach packet stays hidden.',
            '2026-06-07T22:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $trailingXmp;
        $pdf = $xmpAboutEachPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP AboutEach Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('AboutEach Info Title', $metadata['title']);
        $t->same('Rejected XMP AboutEach Boundary Body', $plainText);
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
        $t->same('2026-06-07T21:48:51Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-07T21:47:51Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected AboutEach XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private AboutEach XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private AboutEach Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private AboutEachPrefix XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing AboutEach Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected AboutEach XMP Title'));
        $t->true(!str_contains($plainText, 'Private AboutEach XMP Title'));
        $t->true(!str_contains($plainText, 'Private AboutEachPrefix XMP Title'));
    },
];
