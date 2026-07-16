<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Transfer Function Image Intro) Tj ET\n"
    . "q /TR2#20Identity gs 24 0 0 12 72 690 cm /TR2#20Identity#20Image Do Q\n"
    . "q /TR#20Function gs 16 0 0 8 108 690 cm /TR#20Function#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Transfer Function Image Outro) Tj ET';
$identityPayload = 'BT /F1 12 Tf 72 720 Td (WordPress TR2 Identity Image Noise) Tj ET';
$functionPayload = 'BT /F1 12 Tf 72 720 Td (WordPress TR Function Image Noise) Tj ET';
$identityCompressed = gzcompress($identityPayload);
$functionCompressed = gzcompress($functionPayload);
if (!is_string($identityCompressed) || !is_string($functionCompressed)) {
    throw new RuntimeException('Unable to compress transfer-function image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /TR2#20Identity 20 0 R /TR#20Function 21 0 R >> /XObject << /TR2#20Identity#20Image 5 0 R /TR#20Function#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($identityCompressed) . " >>\nstream\n{$identityCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($functionCompressed) . " >>\nstream\n{$functionCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /TR2 /Identity /ca 0.9 /BM /Normal >>\nendobj\n"
    . "21 0 obj\n<< /Type /ExtGState /TR 22 0 R /ca 0.85 /BM /Multiply >>\nendobj\n"
    . "22 0 obj\n<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0] /C1 [1] /N 1 >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$identity = $entriesByName['TR2 Identity Image'] ?? [];
$function = $entriesByName['TR Function Image'] ?? [];
$identityState = $identity['invocation_graphics_states'][0] ?? [];
$functionState = $function['invocation_graphics_states'][0] ?? [];
$identityTransfer = $identityState['transfer_functions'][0] ?? [];
$functionTransfer = $functionState['transfer_functions'][0] ?? [];
$payloadInVisibleText = str_contains($plainText, 'WordPress TR2 Identity Image Noise')
    || str_contains($plainText, 'WordPress TR Function Image Noise');

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || (($identityTransfer['name'] ?? null) !== 'TR2')
    || (($identityTransfer['transfer_function'] ?? null) !== 'Identity')
    || (($functionTransfer['name'] ?? null) !== 'TR')
    || (($functionTransfer['transfer_function_object'] ?? null) !== 22)
    || (($functionTransfer['function_type'] ?? null) !== 2)
    || (($functionTransfer['domain'] ?? null) !== [0.0, 1.0])
    || (($functionTransfer['range'] ?? null) !== [0.0, 1.0])
    || (($identity['decoded_sha256'] ?? null) !== hash('sha256', $identityPayload))
    || (($function['decoded_sha256'] ?? null) !== hash('sha256', $functionPayload))
    || $payloadInVisibleText
) {
    throw new RuntimeException('Image XObject ExtGState transfer-function smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-transfer-function-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; ExtGState TR/TR2 transfer functions remain review-only image rendering metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'identity_transfer_function' => $identityTransfer['transfer_function'] ?? null,
    'function_transfer_object' => $functionTransfer['transfer_function_object'] ?? null,
    'function_type' => $functionTransfer['function_type'] ?? null,
    'function_domain' => $functionTransfer['domain'] ?? null,
    'function_range' => $functionTransfer['range'] ?? null,
    'payload_in_visible_text' => $payloadInVisibleText,
];

echo '<!-- markerpdf:pdf-image-xobject-transfer-function-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
