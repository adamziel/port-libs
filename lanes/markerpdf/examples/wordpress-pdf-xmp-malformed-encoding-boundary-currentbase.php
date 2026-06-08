<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodeUtf16Be = static function (string $xml): string {
    $bytes = iconv('UTF-8', 'UTF-16BE', $xml);
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to encode malformed XMP encoding smoke fixture.');
    }

    return $bytes;
};

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

$metadataBytes = $encodeUtf16Be($prefix) . "\xd8\x00" . $encodeUtf16Be($suffix);
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress malformed XMP encoding smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Malformed UTF16 XMP Encoding Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Info Fallback XMP Encoding Title) /Author (Info XMP Encoding Author) /Producer (Info XMP Encoding Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$summary = $review['xmp_summary'] ?? [];

if (($metadata['source'] ?? []) !== ['info', 'catalog']) {
    throw new RuntimeException('Expected malformed XMP encoding stream to fall back to /Info metadata.');
}
if (($metadata['title'] ?? null) !== 'Info Fallback XMP Encoding Title') {
    throw new RuntimeException('Expected /Info title to win after malformed XMP encoding rejection.');
}
if (($review['status'] ?? null) !== 'rejected_malformed_document_xmp_encoding') {
    throw new RuntimeException('Expected catalog review to record malformed document XMP encoding.');
}
if (($summary['packet_encoding'] ?? null) !== 'UTF-16BE') {
    throw new RuntimeException('Expected XMP review summary to retain UTF-16BE provenance.');
}
if (!is_string($encoded) || str_contains($encoded, 'Malformed XMP Title') || str_contains($encoded, 'XMP Encoding Editor')) {
    throw new RuntimeException('Expected repaired XMP text to stay out of WordPress metadata output.');
}
if (str_contains($plainText, 'Malformed XMP Title') || str_contains($plainText, 'XMP Encoding Editor')) {
    throw new RuntimeException('Expected malformed XMP text to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-malformed-encoding-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-strict-encoding-boundary',
    'native_boundary' => 'Catalog /Metadata XMP UTF-16 decode is strict; malformed code units remain review-only',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_info_fallback' => ($metadata['title'] ?? null) === 'Info Fallback XMP Encoding Title',
    'review_status' => $review['status'] ?? null,
    'packet_encoding' => $summary['packet_encoding'] ?? null,
    'payload_included' => $review['payload_included'] ?? true,
    'repaired_xmp_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Malformed XMP Title')
        && !str_contains($encoded, 'XMP Encoding Editor'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Malformed XMP Title')
        && !str_contains($plainText, 'XMP Encoding Editor'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata-review ' . $htmlJson([
    'review_status' => $review['status'] ?? null,
    'xmp_summary_status' => $summary['status'] ?? null,
    'packet_encoding' => $summary['packet_encoding'] ?? null,
    'encoding_boundary' => $summary['encoding_boundary'] ?? null,
    'reason' => $summary['reason'] ?? null,
    'payload_included' => $review['payload_included'] ?? true,
    'text_values_redacted' => $summary['text_values_redacted'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
