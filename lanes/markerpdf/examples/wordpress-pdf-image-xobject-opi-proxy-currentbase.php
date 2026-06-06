<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 740 Td (Attachment intro) Tj ET '
    . 'q 24 0 0 12 72 690 cm /Proxy#20Image Do Q '
    . 'BT /F1 12 Tf 72 660 Td (Attachment outro) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress OPI image payload noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Proxy#20Image 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /OPI << /1#2E3 7 0 R /Private << /1#2E3 << /F (Nested OPI Decoy) >> >> >> /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /OPI /Version 1.3 /F (highres-wordpress-hero.tif) /ImageType /proxy /IncludedImageDimensions [640 480] /CropRect [10 20 300 240] /Position [0 0 24 0 24 12 0 12] /Resolution [300 300] /Overprint true >>\nendobj\n"
    . "xref\n0 8\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$opi = is_array($entry['opi_proxy_review'] ?? null) ? $entry['opi_proxy_review'] : [];
$opiEntry = is_array($opi['entries'][0] ?? null) ? $opi['entries'][0] : [];

if (
    $plainText !== "Attachment intro\nAttachment outro"
    || str_contains($plainText, 'WordPress OPI image payload noise')
    || str_contains($plainText, 'highres-wordpress-hero.tif')
    || ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['resource_name'] ?? null) !== 'Proxy Image'
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || ($entry['payload_in_visible_text'] ?? true) !== false
    || ($entry['opi_proxy_present'] ?? false) !== true
    || ($entry['opi_proxy_payload_in_visible_text'] ?? true) !== false
    || ($opi['entry_count'] ?? 0) !== 1
    || ($opiEntry['version'] ?? null) !== '1.3'
    || ($opiEntry['file_specification'] ?? null) !== 'highres-wordpress-hero.tif'
    || ($opiEntry['included_image_dimensions'] ?? null) !== [640.0, 480.0]
) {
    throw new RuntimeException('Image XObject OPI proxy boundary smoke failed.');
}

$summary = [
    'source' => 'native-pdf-image-xobject-opi-proxy-currentbase',
    'wordpress_path' => 'searchable PDF attachment import keeps visible text clean while exposing OPI proxy image metadata to media review',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; OPI proxy dictionaries remain review-only image metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'plain_text' => $plainText,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'resource_name' => $entry['resource_name'],
    'decoded_sha256' => $entry['decoded_sha256'],
    'payload_in_visible_text' => $entry['payload_in_visible_text'],
    'opi_proxy_present' => $entry['opi_proxy_present'],
    'opi_proxy_review_only' => $entry['opi_proxy_review_only'],
    'opi_proxy_payload_in_visible_text' => $entry['opi_proxy_payload_in_visible_text'],
    'opi_version' => $opiEntry['version'],
    'opi_file_specification' => $opiEntry['file_specification'],
    'opi_included_image_dimensions' => $opiEntry['included_image_dimensions'],
];

echo '<!-- markerpdf-image-xobject-opi-proxy-currentbase: ok -->' . PHP_EOL;
echo '<pre>' . htmlspecialchars(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES, 'UTF-8') . '</pre>' . PHP_EOL;
