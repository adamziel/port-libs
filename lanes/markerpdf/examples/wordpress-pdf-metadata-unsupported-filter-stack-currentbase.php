<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Unsupported Stack WordPress XMP Leak</rdf:li></rdf:Alt></dc:title>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>unsupported-stack-wordpress-keyword</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Unsupported Stack XMP Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Unsupported Stack XMP Tool</xmp:CreatorTool>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$metadataPayload = gzcompress($xmp);
if (!is_string($metadataPayload)) {
    throw new RuntimeException('Unable to compress unsupported metadata filter-stack smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Visible unsupported metadata stack WordPress body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter [ /FlateDecode /DCTDecode ] /Length " . strlen($metadataPayload) . " >>\nstream\n{$metadataPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Title (Info Fallback WordPress Title) /Author (Info WordPress Metadata Author) /Producer (Info WordPress Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 7 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];

if (($metadata['title'] ?? null) !== 'Info Fallback WordPress Title') {
    throw new RuntimeException('Expected unsupported metadata stream stack to fall back to Info title.');
}

if (($metadata['xmp'] ?? null) !== [] || str_contains($encodedMetadata ?: '', 'Unsupported Stack WordPress XMP Leak')) {
    throw new RuntimeException('Expected unsupported metadata stream stack XMP payload to stay excluded.');
}

if ($plainText !== 'Visible unsupported metadata stack WordPress body') {
    throw new RuntimeException('Expected visible PDF body text to remain extractable after metadata rejection.');
}

echo '<!-- markerpdf-metadata-unsupported-filter-stack-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog Metadata stream filter stack with supported FlateDecode followed by unsupported DCTDecode',
    'metadata_source' => $metadata['source'] ?? null,
    'metadata_title' => $metadata['title'] ?? null,
    'metadata_review_status' => $review['status'] ?? null,
    'metadata_filters' => $review['filters'] ?? null,
    'unsupported_filter_stack_failed_closed' => ($review['status'] ?? null) === 'unreadable_metadata_stream',
    'info_fallback_used' => ($metadata['title'] ?? null) === 'Info Fallback WordPress Title',
    'xmp_payload_excluded' => ($metadata['xmp'] ?? null) === [],
    'visible_text_preserved' => $plainText === 'Visible unsupported metadata stack WordPress body',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
