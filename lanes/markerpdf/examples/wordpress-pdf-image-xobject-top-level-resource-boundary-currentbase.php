<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Top Level Image Intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Hero#20Image Do Q\n"
    . "q 10 0 0 10 108 690 cm /Private#20Image Do Q\n"
    . "q 10 0 0 10 132 690 cm /Private#20Form Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Top Level Image Outro) Tj ET';
$heroPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hero Image Payload Noise) Tj ET';
$privateImagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Private Image Payload Noise) Tj ET';
$privateFormPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Private Form Text Leak) Tj ET';
$heroCompressed = gzcompress($heroPayload);
$privateImageCompressed = gzcompress($privateImagePayload);
if (!is_string($heroCompressed) || !is_string($privateImageCompressed)) {
    throw new RuntimeException('Unable to compress image XObject boundary smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Hero#20Image 5 0 R /Private << /Private#20Image 6 0 R /Private#20Form 7 0 R >> >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($heroCompressed) . " >>\nstream\n{$heroCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($privateImageCompressed) . " >>\nstream\n{$privateImageCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 40 20] /Resources << /Font << /F1 10 0 R >> >> /Length " . strlen($privateFormPayload) . " >>\nstream\n{$privateFormPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['entries'][0]['resource_name'] ?? null) !== 'Hero Image'
    || ($review['entries'][0]['object_number'] ?? null) !== 5
    || ($review['entries'][0]['decoded_sha256'] ?? null) !== hash('sha256', $heroPayload)
    || str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'Private Image')
    || str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', hash('sha256', $privateImagePayload))
    || str_contains($plainText, 'WordPress Hero Image Payload Noise')
    || str_contains($plainText, 'WordPress Private Image Payload Noise')
    || str_contains($plainText, 'WordPress Private Form Text Leak')
) {
    throw new RuntimeException('Top-level image XObject resource boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-top-level-resource-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text page text plus marker.pdf.images.render_image image handoff; only top-level /XObject resource names are callable review resources',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'top_level_resource_selected' => ($review['entries'][0]['resource_name'] ?? null) === 'Hero Image',
    'nested_private_image_rejected' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'Private Image'),
    'nested_private_form_text_rejected' => !str_contains($plainText, 'WordPress Private Form Text Leak'),
    'payload_in_visible_text' => $review['entries'][0]['payload_in_visible_text'] ?? true,
];

echo '<!-- markerpdf:pdf-image-xobject-top-level-resource-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
