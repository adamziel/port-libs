<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpMalformedEncodingBoundaryPdf = static function (string $metadataBytes, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress malformed XMP encoding boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Info Fallback XMP Encoding Title) /Author (Info XMP Encoding Author) /Producer (Info XMP Encoding Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$xmpMalformedUtf16BePacketBytes = static function (): string {
    $prefix = '<?xml version="1.0" encoding="UTF-16BE"?>'
        . '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Malformed ';
    $suffix = ' XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>XMP Encoding Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Malformed UTF-16 surrogate must not be silently repaired</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-encoding-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>XMP Encoding Producer</pdf:Producer>'
        . '<xmp:CreatorTool>XMP Encoding Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-08T18:34:22Z</xmp:CreateDate>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>'
        . '<?xpacket end="w"?>';

    $encode = static function (string $xml): string {
        $bytes = iconv('UTF-8', 'UTF-16BE', $xml);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to encode malformed XMP UTF-16BE fixture.');
        }

        return $bytes;
    };

    return $encode($prefix) . "\xd8\x00" . $encode($suffix);
};

return [
    'rejects malformed UTF-16 XMP streams instead of silently repairing promoted metadata' => static function (
        TestRunner $t
    ) use ($xmpMalformedEncodingBoundaryPdf, $xmpMalformedUtf16BePacketBytes): void {
        $metadataBytes = $xmpMalformedUtf16BePacketBytes();
        $pdf = $xmpMalformedEncodingBoundaryPdf($metadataBytes, 'Malformed UTF16 XMP Encoding Body');

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Info Fallback XMP Encoding Title', $metadata['title']);
        $t->same(['Info XMP Encoding Author'], $metadata['authors']);
        $t->same('Info XMP Encoding Producer', $metadata['producer']);
        $t->same('Malformed UTF16 XMP Encoding Body', $plainText);
        $t->same('rejected_malformed_document_xmp_encoding', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_malformed_xmp_encoding', $summary['status'] ?? null);
        $t->same('UTF-16BE', $summary['packet_encoding'] ?? null);
        $t->same('strict_xmp_encoding_decode', $summary['encoding_boundary'] ?? null);
        $t->same('invalid_utf16_code_unit', $summary['reason'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Malformed XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'XMP Encoding Editor'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'xmp-encoding-boundary'));
        $t->true(!str_contains($plainText, 'Malformed XMP Title'));
        $t->true(!str_contains($plainText, 'XMP Encoding Editor'));
    },
];
