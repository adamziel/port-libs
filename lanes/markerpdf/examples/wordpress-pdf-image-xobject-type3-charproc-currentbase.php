<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/PdfTextExtractor.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$charProc = "1000 0 d0\n"
    . "q 12 0 0 8 1 2 cm /Glyph#20Image Do Q\n"
    . "BT /Fghost 9 Tf (WordPress Type3 CharProc text leak) Tj ET\n";
$pageContent = 'BT /Ft3 24 Tf 72 720 Td (A) Tj ET';
$glyphPayload = 'BT /Fghost 9 Tf 0 0 Td (WordPress Type3 Glyph Image Payload Noise) Tj ET';
$unusedPayload = 'BT /Fghost 9 Tf 0 0 Td (WordPress Unused Type3 Image Payload Noise) Tj ET';
$glyphCompressed = gzcompress($glyphPayload);
$unusedCompressed = gzcompress($unusedPayload);
if (!is_string($glyphCompressed) || !is_string($unusedCompressed)) {
    throw new RuntimeException('Unable to compress Type3 CharProc image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 >>\nendobj\n"
    . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3GlyphImageBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding << /Type /Encoding /Differences [65 /A] >> "
    . "/CharProcs << /A 3 0 R >> "
    . "/Resources << /XObject << /Glyph#20Image 5 0 R /Unused#20Glyph#20Image 6 0 R >> /Font << /Fghost 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($glyphCompressed) . " >>\nstream\n{$glyphCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unusedCompressed) . " >>\nstream\n{$unusedCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

$metadata = [
    'source' => 'native-pdf-image-xobject-type3-charproc-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable Type3 text plus marker.pdf.images.render_image media handoff; Type3 CharProc image XObjects are review-only glyph paint metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'] ?? null,
    'type3_font_resource' => $entry['type3_font_resource'] ?? null,
    'type3_glyph_name' => $entry['type3_glyph_name'] ?? null,
    'glyph_image_invoked' => $entry['invoked'] ?? null,
    'glyph_image_bbox' => $entry['image_unit_bbox'] ?? null,
    'glyph_image_payload_reviewed_by_hash' => ($entry['decoded_sha256'] ?? null) === hash('sha256', $glyphPayload),
    'visible_text' => $plainText,
    'charproc_text_excluded' => !str_contains($plainText, 'WordPress Type3 CharProc text leak'),
    'glyph_payload_excluded_from_text' => !str_contains($plainText, 'WordPress Type3 Glyph Image Payload Noise'),
    'unused_payload_excluded_from_text' => !str_contains($plainText, 'WordPress Unused Type3 Image Payload Noise'),
    'payload_bytes_excluded_from_review_json' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $glyphPayload),
];

if (
    $metadata['image_xobject_count'] !== 1
    || $metadata['invoked_image_xobject_count'] !== 1
    || $metadata['glyph_image_invoked'] !== true
    || $metadata['type3_font_resource'] !== 'Ft3'
    || $metadata['type3_glyph_name'] !== 'A'
    || $metadata['visible_text'] !== 'A'
    || $metadata['charproc_text_excluded'] !== true
    || $metadata['glyph_payload_excluded_from_text'] !== true
    || $metadata['unused_payload_excluded_from_text'] !== true
    || $metadata['payload_bytes_excluded_from_review_json'] !== true
) {
    throw new RuntimeException('Type3 CharProc image XObject WordPress smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-type3-charproc-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<p>A</p>\n";
