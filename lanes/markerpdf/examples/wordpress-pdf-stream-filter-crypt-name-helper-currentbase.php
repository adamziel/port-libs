<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $helperBody, string $filteredText, string $visibleAfter): string {
    $filteredContent = 'BT /F1 12 Tf 72 720 Td (' . $filteredText . ') Tj ET';
    $compressed = gzcompress($filteredContent);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress Crypt filter helper smoke stream.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 700 Td (' . $visibleAfter . ') Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Type /CryptFilterDecodeParms /Name 10 0 R >> null ] /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "10 0 obj\n{$helperBody}\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();

$malformedPdf = $buildPdf(
    "/Identity /PrivateCF\n",
    'Malformed Crypt Name Helper Leak',
    'Visible After Crypt Name Helper'
);
$malformedText = $extractor->extractPlainText($malformedPdf);

$validPdf = $buildPdf(
    "/Identity % standalone helper comment\n",
    'Standalone Identity Helper Import',
    'Visible After Standalone Identity Helper'
);
$validText = $extractor->extractPlainText($validPdf);

$result = [
    'scenario' => 'wordpress_pdf_stream_filter_crypt_name_helper_currentbase',
    'native_boundary' => 'Crypt DecodeParms /Name indirect helpers must be standalone names before identity pass-through stream decoding',
    'malformed_helper_rejected' => !str_contains($malformedText, 'Malformed Crypt Name Helper Leak'),
    'malformed_visible_text_preserved' => $malformedText === 'Visible After Crypt Name Helper',
    'valid_identity_helper_imported' => str_contains($validText, 'Standalone Identity Helper Import'),
    'valid_visible_text_preserved' => str_contains($validText, 'Visible After Standalone Identity Helper'),
    'private_crypt_name_excluded' => !str_contains($malformedText, 'PrivateCF'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'self_test_passed' => false,
];

$result['self_test_passed'] = $result['malformed_helper_rejected']
    && $result['malformed_visible_text_preserved']
    && $result['valid_identity_helper_imported']
    && $result['valid_visible_text_preserved']
    && $result['private_crypt_name_excluded'];

if (!$result['self_test_passed']) {
    throw new RuntimeException('Crypt filter Name helper boundary smoke failed: ' . json_encode($result));
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
