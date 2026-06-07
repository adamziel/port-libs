<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpCommentReferenceBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Comment Reference Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-comment-reference-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Comment Reference Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Comment Reference Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-07T10:06:11Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpCommentReferenceBoundaryPdf = static function (
    string $catalogMetadataValue,
    string $xmp,
    string $bodyText,
    string $extraObjects = ''
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP comment-reference fixture.');
    }

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata {$catalogMetadataValue} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . $extraObjects
        . "6 0 obj\n<< /Title (Comment Reference Info Title) /Author (Info Comment Author) /Producer (Info Comment Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'promotes catalog XMP metadata when the indirect reference is split by PDF comments' => static function (
        TestRunner $t
    ) use ($xmpCommentReferenceBoundaryPacket, $xmpCommentReferenceBoundaryPdf): void {
        $xmp = $xmpCommentReferenceBoundaryPacket(
            'Comment Split XMP Title',
            'Catalog Metadata object and generation operands are split by PDF comments',
            '2026-06-07T10:06:11Z'
        );
        $pdf = $xmpCommentReferenceBoundaryPdf(
            "5 % object number/generation split by PDF comment\n 0 % generation/R split by PDF comment\n R",
            $xmp,
            'Comment Reference XMP Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Comment Split XMP Title', $metadata['title']);
        $t->same(
            'Catalog Metadata object and generation operands are split by PDF comments',
            $metadata['description']
        );
        $t->same(['Comment Reference Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-comment-reference-boundary'], $metadata['keywords']);
        $t->same('Comment Reference Tool', $metadata['creator_tool']);
        $t->same('Comment Reference Producer', $metadata['producer']);
        $t->same('2026-06-07T10:06:11Z', $metadata['created_at']);
        $t->same('2026-06-07T10:06:11Z', $metadata['created_at_utc']);
        $t->same('2026-06-07T10:06:11Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Comment Reference Info Title', $metadata['info']['Title'] ?? null);
        $t->same('Comment Reference XMP Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'catalog_metadata_stream_boundary'));
        $t->true(!str_contains($plainText, 'Comment Split XMP Title'));
    },
    'rejects comment-split catalog Metadata references with trailing operands before XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpCommentReferenceBoundaryPacket, $xmpCommentReferenceBoundaryPdf): void {
        $xmp = $xmpCommentReferenceBoundaryPacket(
            'Trailing Operand Comment Split XMP Title',
            'A valid comment-split Metadata reference followed by another operand remains ambiguous',
            '2026-06-07T10:06:12Z'
        );
        $pdf = $xmpCommentReferenceBoundaryPdf(
            "5 % object number/generation split by PDF comment\n 0 R 7 0 R",
            $xmp,
            'Comment Reference Trailing Operand Body',
            "7 0 obj\n<< /S /JavaScript /JS (app.alert\\('comment reference metadata tail'\\)) >>\nendobj\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Comment Reference Info Title', $metadata['title']);
        $t->same('Comment Reference Trailing Operand Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_operand', $review['status'] ?? null);
        $t->same(1, $review['metadata_entry_count'] ?? null);
        $t->same(2, $review['metadata_operand_count'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same([7], $review['trailing_reference_object_numbers'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Operand Comment Split XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'comment reference metadata tail'));
        $t->true(!str_contains($plainText, 'Trailing Operand Comment Split XMP Title'));
        $t->true(!str_contains($plainText, 'comment reference metadata tail'));
    },
];
