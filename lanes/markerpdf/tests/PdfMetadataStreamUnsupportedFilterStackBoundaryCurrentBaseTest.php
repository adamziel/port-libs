<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$metadataStreamUnsupportedFilterStackBoundaryCurrentBaseXmp = static function (
    string $title,
    string $description,
    string $keyword,
    string $creatorTool,
    string $producer
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Unsupported Stack XMP Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>' . htmlspecialchars($keyword, ENT_XML1) . '</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>' . htmlspecialchars($producer, ENT_XML1) . '</pdf:Producer>'
        . '<xmp:CreatorTool>' . htmlspecialchars($creatorTool, ENT_XML1) . '</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-08T07:22:35Z</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T07:23:01Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$metadataStreamUnsupportedFilterStackBoundaryCurrentBasePdf = static function (
    string $metadataPayload,
    string $filterOperand,
    string $bodyText
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter {$filterOperand} /Length " . strlen($metadataPayload) . " >>\nstream\n{$metadataPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Title (Info Fallback Unsupported Stack Title) /Author (Info Unsupported Stack Author) /Producer (Info Unsupported Stack Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 7 0 R >>\n%%EOF";
};

return [
    'fails closed on metadata streams with unsupported filters after native filters' => static function (
        TestRunner $t
    ) use (
        $metadataStreamUnsupportedFilterStackBoundaryCurrentBaseXmp,
        $metadataStreamUnsupportedFilterStackBoundaryCurrentBasePdf
    ): void {
        $xmp = $metadataStreamUnsupportedFilterStackBoundaryCurrentBaseXmp(
            'Unsupported Stack XMP Leak',
            'Partially decoded metadata must not become WordPress document metadata.',
            'unsupported-filter-stack-boundary',
            'Unsupported Stack Creator Tool',
            'Unsupported Stack Producer'
        );
        $compressedXmp = gzcompress($xmp);
        if (!is_string($compressedXmp)) {
            throw new RuntimeException('Unable to compress unsupported-filter metadata stream fixture.');
        }

        $pdf = $metadataStreamUnsupportedFilterStackBoundaryCurrentBasePdf(
            $compressedXmp,
            '[ /FlateDecode /DCTDecode ]',
            'Visible unsupported metadata stack body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Info Fallback Unsupported Stack Title', $metadata['title']);
        $t->same(['Info Unsupported Stack Author'], $metadata['authors']);
        $t->same('Info Unsupported Stack Producer', $metadata['producer']);
        $t->same('Visible unsupported metadata stack body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('unreadable_metadata_stream', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode', 'DCTDecode'], $review['filters'] ?? null);
        $t->same(strlen($compressedXmp), $review['declared_length'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Unsupported Stack XMP Leak'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'unsupported-filter-stack-boundary'));
        $t->true(!str_contains($plainText, 'Unsupported Stack XMP Leak'));
        $t->true(!str_contains($plainText, 'unsupported-filter-stack-boundary'));
    },
];
