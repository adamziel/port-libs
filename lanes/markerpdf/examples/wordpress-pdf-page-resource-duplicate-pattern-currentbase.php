<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress duplicate Pattern intro) Tj ET\n"
    . "/Pattern cs /Dup#20Tile scn 0 0 20 10 re f\n"
    . "/Pattern cs /Valid#20Tile scn 30 0 20 10 re f\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress duplicate Pattern outro) Tj ET';
$patternContent = 'q 6 0 0 3 1 2 cm /Tile#20Image Do Q';
$stalePayload = 'BT /F1 12 Tf 72 720 Td (WordPress stale duplicate Pattern image noise) Tj ET';
$currentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress current duplicate Pattern image noise) Tj ET';
$validPayload = 'BT /F1 12 Tf 72 720 Td (WordPress valid inherited Pattern image noise) Tj ET';
$staleCompressed = gzcompress($stalePayload);
$currentCompressed = gzcompress($currentPayload);
$validCompressed = gzcompress($validPayload);
if (!is_string($staleCompressed) || !is_string($currentCompressed) || !is_string($validCompressed)) {
    throw new RuntimeException('Unable to compress duplicate Pattern WordPress smoke payloads.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Dup#20Tile 11 0 R /Dup#20Tile 12 0 R /Valid#20Tile 13 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Tile#20Image 5 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Tile#20Image 6 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Tile#20Image 7 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
$validHash = hash('sha256', $validPayload);
$staleHash = hash('sha256', $stalePayload);
$currentHash = hash('sha256', $currentPayload);
$lines = $extractor->extractTextLines($pdf);

if (
    $lines !== ['WordPress duplicate Pattern intro', 'WordPress duplicate Pattern outro']
    || ($review['image_xobject_count'] ?? null) !== 1
    || ($review['invoked_image_xobject_count'] ?? null) !== 1
    || ($resources['pattern_names'] ?? null) !== ['Valid Tile']
    || str_contains($plainText, 'duplicate Pattern image noise')
    || str_contains($plainText, 'valid inherited Pattern image noise')
    || str_contains($encodedReview, $staleHash)
    || str_contains($encodedReview, $currentHash)
    || !str_contains($encodedReview, $validHash)
) {
    throw new RuntimeException('Duplicate inherited Pattern resource smoke failed before WordPress import.');
}

echo '<!-- markerpdf-page-resource-duplicate-pattern-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-pattern-currentbase',
    'native_boundary' => 'duplicate inherited /Pattern resource names are rejected before tiling Pattern image review',
    'inherited_page_resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'pattern_names' => $resources['pattern_names'] ?? [],
    'duplicate_pattern_images_excluded' => !str_contains($encodedReview, $staleHash)
        && !str_contains($encodedReview, $currentHash),
    'valid_pattern_image_retained' => str_contains($encodedReview, $validHash),
    'visible_paragraph_count' => count($lines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
