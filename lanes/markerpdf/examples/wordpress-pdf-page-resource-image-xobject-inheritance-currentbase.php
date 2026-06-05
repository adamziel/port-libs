<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = "BT /F1 12 Tf 72 720 Td (Inherited image resource page) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Parent#20Logo Do Q";
$pageTwoContent = "BT /F1 12 Tf 72 720 Td (Leaf local resources only page) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Parent#20Logo Do Q";
$imagePayload = 'BT /F1 12 Tf 72 720 Td (Parent Logo Image Payload Leak) Tj ET';
$compressedImagePayload = gzcompress($imagePayload);
if (!is_string($compressedImagePayload)) {
    throw new RuntimeException('Unable to compress page resource image smoke payload.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /Parent#20Logo 8 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page-tree inherited image XObject resources with leaf /Resources override exclusion before WordPress media review',
    'inherited_image_resource_reported' => ($entry['page_resource_inherited'] ?? null) === true
        && ($entry['page_resource_owner_object'] ?? null) === 2
        && ($entry['page_resource_object'] ?? null) === 10,
    'leaf_resource_override_blocks_parent_image' => ($review['image_xobject_count'] ?? null) === 1
        && array_column($review['entries'] ?? [], 'page_object') === [3]
        && ($boundary[1]['resources']['xobject_names'] ?? null) === null,
    'image_payload_excluded_from_gutenberg_text' => !str_contains($plainText, 'Parent Logo Image Payload Leak'),
    'review_only_image_count' => $review['image_xobject_count'] ?? 0,
    'visible_paragraph_count' => count($lines),
];

if (
    $flags['inherited_image_resource_reported'] !== true
    || $flags['leaf_resource_override_blocks_parent_image'] !== true
    || $flags['image_payload_excluded_from_gutenberg_text'] !== true
    || $lines !== ['Inherited image resource page', 'Leaf local resources only page']
) {
    throw new RuntimeException('Expected page-resource image XObject inheritance smoke flags to pass.');
}

echo '<!-- markerpdf-page-resource-image-xobject-inheritance-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
