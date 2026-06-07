<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Overprint Image Intro) Tj ET\n"
    . "q /Spot#20Overprint gs 24 0 0 12 72 690 cm /Spot#20Image Do Q\n"
    . "q /Process#20Knockout gs 16 0 0 8 108 690 cm /Process#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Overprint Image Outro) Tj ET';
$spotPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Spot Overprint Image Noise) Tj ET';
$processPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Process Knockout Image Noise) Tj ET';
$spotCompressed = gzcompress($spotPayload);
$processCompressed = gzcompress($processPayload);
if (!is_string($spotCompressed) || !is_string($processCompressed)) {
    throw new RuntimeException('Unable to compress ExtGState overprint image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Spot#20Overprint 20 0 R /Process#20Knockout 21 0 R >> /XObject << /Spot#20Image 5 0 R /Process#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($spotCompressed) . " >>\nstream\n{$spotCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($processCompressed) . " >>\nstream\n{$processCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /OP true /op true /OPM 1 /ca 0.75 /BM /Multiply >>\nendobj\n"
    . "21 0 obj\n<< /Type /ExtGState /OP false /op false /OPM 0 /ca 1 /BM /Normal >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$spot = $entriesByName['Spot Image'] ?? [];
$process = $entriesByName['Process Image'] ?? [];
$spotState = $spot['invocation_graphics_states'][0] ?? [];
$processState = $process['invocation_graphics_states'][0] ?? [];
$payloadInVisibleText = str_contains($plainText, 'WordPress Spot Overprint Image Noise')
    || str_contains($plainText, 'WordPress Process Knockout Image Noise');

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || (($spotState['stroking_overprint'] ?? null) !== true)
    || (($spotState['nonstroking_overprint'] ?? null) !== true)
    || (($spotState['overprint_mode'] ?? null) !== 1)
    || (($spotState['nonstroking_alpha'] ?? null) !== 0.75)
    || (($processState['stroking_overprint'] ?? null) !== false)
    || (($processState['nonstroking_overprint'] ?? null) !== false)
    || (($processState['overprint_mode'] ?? null) !== 0)
    || (($spot['decoded_sha256'] ?? null) !== hash('sha256', $spotPayload))
    || (($process['decoded_sha256'] ?? null) !== hash('sha256', $processPayload))
    || $payloadInVisibleText
) {
    throw new RuntimeException('Image XObject ExtGState overprint smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-extgstate-overprint-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; ExtGState overprint controls remain review-only image rendering metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'spot_extgstate_resources' => $spotState['ext_gstate_resources'] ?? [],
    'spot_stroking_overprint' => $spotState['stroking_overprint'] ?? null,
    'spot_nonstroking_overprint' => $spotState['nonstroking_overprint'] ?? null,
    'spot_overprint_mode' => $spotState['overprint_mode'] ?? null,
    'spot_blend_modes' => $spotState['blend_modes'] ?? [],
    'process_stroking_overprint' => $processState['stroking_overprint'] ?? null,
    'process_nonstroking_overprint' => $processState['nonstroking_overprint'] ?? null,
    'process_overprint_mode' => $processState['overprint_mode'] ?? null,
    'payload_in_visible_text' => $payloadInVisibleText,
];

echo '<!-- markerpdf:pdf-image-xobject-extgstate-overprint-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
