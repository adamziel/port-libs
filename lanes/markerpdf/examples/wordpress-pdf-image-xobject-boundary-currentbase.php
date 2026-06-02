<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = "BT /F1 12 Tf 72 720 Td (Current Image Boundary Intro) Tj ET\n"
    . "q 32 0 0 16 72 690 cm /Hero#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 668 Td (Current Image Boundary Outro) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Image XObject Payload Noise) Tj ET';
$compressedImagePayload = gzcompress($imagePayload);
if (!is_string($compressedImagePayload)) {
    throw new RuntimeException('Unable to compress image XObject smoke payload.');
}
$encodedImagePayload = strtoupper(bin2hex($compressedImagePayload)) . '>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Hero#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Length " . strlen($encodedImagePayload) . " >>\nstream\n{$encodedImagePayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || str_contains($plainText, 'WordPress Image XObject Payload Noise')
) {
    throw new RuntimeException('Image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image RGB handoff',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'first_resource_name' => $review['entries'][0]['resource_name'] ?? null,
    'first_image_filters' => $review['entries'][0]['filters'] ?? [],
    'first_image_decoded_with_current_filters' => $review['entries'][0]['decoded_with_current_filters'] ?? false,
    'payload_in_visible_text' => $review['entries'][0]['payload_in_visible_text'] ?? true,
];

echo '<!-- markerpdf:pdf-image-xobject-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
