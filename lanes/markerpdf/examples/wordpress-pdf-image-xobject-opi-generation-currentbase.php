<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 740 Td (WordPress OPI Generation Intro) Tj ET '
    . 'q 30 0 0 15 72 690 cm /Generation#20Proxy#20Image Do Q '
    . 'BT /F1 12 Tf 72 660 Td (WordPress OPI Generation Outro) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Generation OPI image payload noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Generation#20Proxy#20Image 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /OPI 7 1 R /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "7 1 obj\n<< /Type /OPI /Version 2.0 /F (current-highres-wordpress-hero.tif) /ImageType /current /IncludedImageDimensions [1200 600] /CropRect [0 0 300 150] /Position [0 0 30 0 30 15 0 15] /Resolution [600 300] /Overprint false >>\nendobj\n"
    . "7 0 obj\n<< /Type /OPI /Version 1.3 /F (stale-highres-wordpress-hero.tif) /ImageType /stale /Resolution [72 72] >>\nendobj\n"
    . "xref\n0 8\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = is_array($review['entries'][0] ?? null) ? $review['entries'][0] : [];
$opi = is_array($entry['opi_proxy_review'] ?? null) ? $entry['opi_proxy_review'] : [];
$opiEntry = is_array($opi['entries'][0] ?? null) ? $opi['entries'][0] : [];

$metadata = [
    'source' => 'native-pdf-image-xobject-opi-generation-currentbase',
    'upstream_boundary' => 'marker PDF image extraction keeps raster payloads separate from searchable text; native review resolves indirect image dictionaries by exact PDF generation',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'opi_resolved' => $opi['resolved'] ?? null,
    'opi_entry_count' => $opi['entry_count'] ?? null,
    'opi_version' => $opiEntry['version'] ?? null,
    'opi_file_specification' => $opiEntry['file_specification'] ?? null,
    'stale_generation_excluded' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'stale-highres-wordpress-hero.tif'),
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Generation OPI image payload noise'),
];

if (
    $metadata['image_xobject_count'] !== 1
    || $metadata['invoked_image_xobject_count'] !== 1
    || $metadata['opi_resolved'] !== true
    || $metadata['opi_entry_count'] !== 1
    || $metadata['opi_version'] !== '2'
    || $metadata['opi_file_specification'] !== 'current-highres-wordpress-hero.tif'
    || $metadata['stale_generation_excluded'] !== true
    || $metadata['payload_in_visible_text'] !== false
    || $plainText !== "WordPress OPI Generation Intro\nWordPress OPI Generation Outro"
) {
    throw new RuntimeException('Image XObject OPI generation boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-opi-generation-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
