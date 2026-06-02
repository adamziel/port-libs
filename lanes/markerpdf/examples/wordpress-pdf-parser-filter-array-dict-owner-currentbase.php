<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hiddenContent = "% Malformed filter-array bytes contain stream owner decoys.\n"
    . "endstream\nendobj\n"
    . "20 0 obj\n<< /Length 57 >>\nstream\nBT /F1 12 Tf 72 640 Td (Fake dictionary owner leak) Tj ET\nendstream\nendobj\n"
    . "BT /F1 12 Tf 72 720 Td (Malformed filter array leak) Tj ET";
$compressed = gzcompress($hiddenContent, 0);
if (!is_string($compressed) || !str_contains($compressed, "\nendstream\nendobj\n20 0 obj")) {
    throw new RuntimeException('Unable to build filter-array dictionary owner smoke fixture.');
}

$visibleContent = 'BT /F1 12 Tf 72 680 Td (Safe current page text) Tj ET';

$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ << /Owner (Filter dictionary is not a decoder) /Fake [ /Nested ] >> /FlateDecode ] >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

if ($lines !== ['Safe current page text']) {
    throw new RuntimeException('Expected malformed filter-array stream to fail closed while preserving adjacent current page text.');
}

if (
    str_contains($plainText, 'Malformed filter array leak')
    || str_contains($plainText, 'Fake dictionary owner leak')
    || str_contains($plainText, 'Filter dictionary is not a decoder')
    || str_contains($plainText, '20 0 obj')
) {
    throw new RuntimeException('Expected malformed filter-array dictionary and stream-owner decoys to stay out of WordPress paragraphs.');
}

echo '<!-- markerpdf-parser-filter-array-dict-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-stream-filter-parser',
    'native_boundary' => 'stream Filter arrays accept decoder names and reject dictionary entries before native text extraction',
    'filter_array_dictionary_rejected' => !str_contains($plainText, 'Filter dictionary is not a decoder'),
    'hidden_filtered_payload_excluded' => !str_contains($plainText, 'Malformed filter array leak'),
    'fake_dictionary_owner_excluded' => !str_contains($plainText, 'Fake dictionary owner leak') && !str_contains($plainText, '20 0 obj'),
    'safe_current_page_preserved' => $lines === ['Safe current page text'],
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'page_labels' => $extractor->extractPageLabels($pdf),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
