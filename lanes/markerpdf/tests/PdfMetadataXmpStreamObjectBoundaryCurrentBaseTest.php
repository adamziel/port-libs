<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpStreamObjectBoundaryPacket = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>XMP Stream Object Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-stream-object-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Stream Object Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Stream Object Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T10:37:10Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpStreamObjectBoundaryPdf = static function (
    string $xmp,
    string $metadataTail,
    string $bodyText
): string {
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP stream-object boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream{$metadataTail}\nendobj\n"
        . "6 0 obj\n<< /Title (XMP Stream Object Info Title) /Author (Info Stream Object Author) /Producer (Info Stream Object Producer) >>\nendobj\n"
        . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('metadata stream object action tail'\\)) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects document XMP metadata stream objects with non comment tokens after endstream' => static function (
        TestRunner $t
    ) use ($xmpStreamObjectBoundaryPacket, $xmpStreamObjectBoundaryPdf): void {
        $xmp = $xmpStreamObjectBoundaryPacket(
            'Malformed Stream Object XMP Title',
            'A stream object action tail must not define WordPress metadata',
            '2026-06-05T06:37:10-04:00'
        );
        $pdf = $xmpStreamObjectBoundaryPdf(
            $xmp,
            "\n/A 8 0 R /Next 99 0 R",
            'XMP Stream Object Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('XMP Stream Object Info Title', $metadata['title']);
        $t->same(['Info Stream Object Author'], $metadata['authors']);
        $t->same('XMP Stream Object Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_stream_object', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T10:37:10Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Malformed Stream Object XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'metadata stream object action tail'));
        $t->true(!str_contains($plainText, 'Malformed Stream Object XMP Title'));
        $t->true(!str_contains($plainText, 'metadata stream object action tail'));
    },
    'accepts document XMP metadata stream objects with comment only tails' => static function (
        TestRunner $t
    ) use ($xmpStreamObjectBoundaryPacket, $xmpStreamObjectBoundaryPdf): void {
        $xmp = $xmpStreamObjectBoundaryPacket(
            'Comment Tail Stream Object XMP Title',
            'Comment-only stream object tails remain valid metadata whitespace',
            '2026-06-05T07:37:10-03:00'
        );
        $pdf = $xmpStreamObjectBoundaryPdf(
            $xmp,
            "\n% /A 8 0 R is only a PDF comment after endstream",
            'XMP Stream Object Comment Tail Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Comment Tail Stream Object XMP Title', $metadata['title']);
        $t->same('Comment-only stream object tails remain valid metadata whitespace', $metadata['description']);
        $t->same(['XMP Stream Object Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-stream-object-boundary'], $metadata['keywords']);
        $t->same('Stream Object Boundary Tool', $metadata['creator_tool']);
        $t->same('Stream Object Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T07:37:10-03:00', $metadata['created_at']);
        $t->same('2026-06-05T10:37:10Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T10:37:10Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('XMP Stream Object Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Stream Object Comment Tail Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'metadata stream object action tail'));
        $t->true(!str_contains($plainText, 'Comment Tail Stream Object XMP Title'));
        $t->true(!str_contains($plainText, 'xmp-stream-object-boundary'));
    },
];
