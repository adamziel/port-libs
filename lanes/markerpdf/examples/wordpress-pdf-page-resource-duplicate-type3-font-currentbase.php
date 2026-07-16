<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "1000 0 d0\n"
    . "q 12 0 0 8 1 2 cm /GlyphImage Do Q\n"
    . "BT /Fghost 9 Tf (Duplicate Type3 CharProc text leak) Tj ET\n";
$pageContent = 'BT /Fdup 24 Tf 72 720 Td (A) Tj ET';
$payload = 'BT /Fghost 9 Tf 0 0 Td (Duplicate Type3 Image Payload Noise) Tj ET';
$compressed = gzcompress($payload);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress duplicate Type3 CharProc image smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "3 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fdup /BaseFont /DuplicateT3 "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding << /Type /Encoding /Differences [65 /A] >> "
    . "/CharProcs << /A 4 0 R >> "
    . "/Resources << /XObject << /GlyphImage 5 0 R >> /Font << /Fghost 2 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Font << /Fdup 2 0 R /Fdup 3 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

if ($lines !== ['A']) {
    throw new RuntimeException('Expected duplicate inherited Type3 font resource to fail closed to raw text.');
}

if (($review['image_xobject_count'] ?? null) !== 0 || ($review['entries'] ?? []) !== []) {
    throw new RuntimeException('Expected duplicate inherited Type3 font resource to suppress CharProc image review.');
}

$reviewComment = [
    'source' => 'native-pdf-page-resource-duplicate-type3-font-currentbase',
    'native_boundary' => 'duplicate inherited /Font resource names are suppressed before Type3 CharProc image review',
    'page_resource_inherited' => ($resources['inherited'] ?? null) === true,
    'page_resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'page_resource_object' => $resources['resource_object'] ?? null,
    'duplicate_type3_font_names_suppressed' => !isset($resources['font_names']),
    'type3_charproc_image_review_suppressed' => ($review['image_xobject_count'] ?? null) === 0,
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'Duplicate Type3 CharProc text leak')
        && !str_contains($plainText, 'Duplicate Type3 Image Payload Noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-page-resource-duplicate-type3-font-currentbase '
    . htmlspecialchars(json_encode($reviewComment, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
