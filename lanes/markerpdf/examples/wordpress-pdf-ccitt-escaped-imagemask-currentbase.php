<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before escaped ImageMask CCITT) Tj ET';
$paint = 'q 16 0 0 1 72 700 cm /FaxMask Do Q';
$after = 'BT /F1 12 Tf 72 680 Td (After escaped ImageMask CCITT) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (Escaped ImageMask CCITT payload noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxMask 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 7 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Width 16 /Height 1 /Image#4Dask true /Filter /CCF /DecodeParms << /K 0 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >> /Decode [1 0] /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($paint) . " >>\nstream\n{$paint}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$checks = [
    'escaped_imagemask_without_subtype_classified' => ($review['image_xobject_count'] ?? null) === 1
        && ($entry['resource_name'] ?? null) === 'FaxMask'
        && ($entry['image_mask'] ?? null) === true,
    'escaped_imagemask_painted_resource_counted' => ($review['invoked_image_xobject_count'] ?? null) === 1
        && ($entry['invoked'] ?? null) === true,
    'ccitt_payload_excluded_from_text' => !str_contains($plainText, 'Escaped ImageMask CCITT payload noise'),
    'ccitt_review_only' => ($entry['native_raster_decode'] ?? null) === false
        && ($entry['decoded_with_current_filters'] ?? null) === false
        && ($entry['payload_in_visible_text'] ?? null) === false
        && ($entry['preview_only_filters'] ?? null) === ['CCF'],
    'ccitt_payload_excluded_from_review_json' => !str_contains($encodedReview, $payload),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

foreach ($checks as $name => $ok) {
    if (str_starts_with($name, 'executes_')) {
        continue;
    }

    if ($ok !== true) {
        throw new RuntimeException("Failed CCITT escaped ImageMask smoke check: {$name}");
    }
}

echo "<!-- markerpdf-ccitt-escaped-imagemask-currentbase -->\n";
foreach ($checks as $name => $ok) {
    echo $name . '=' . ($ok ? 'true' : 'false') . "\n";
}

foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
