<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = '/OC/HiddenLayer BDC BT /F1 12 Tf 72 720 Td (Hidden dictionary comment leak) Tj ET EMC '
    . '/OC/VisibleLayer BDC BT /F1 12 Tf 72 700 Td (Visible 100% literal layer) Tj ET EMC';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n"
    . "<< /Type /Catalog /Pages 2 0 R /OCProperties << % fake dictionary close >> /ON [20 0 R]\n"
    . " /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [ % 20 0 R ] fake hidden ref\n"
    . " 21 0 R] /Order [20 0 R 21 0 R] >> >> /Outlines 6 0 R >>\n"
    . "endobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /HiddenLayer 20 0 R /VisibleLayer 21 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Outlines /First 7 0 R /Count 1 >>\nendobj\n"
    . "7 0 obj\n<< /Title 40 0 R /Dest [ % 99 0 R ] stale destination\n 3 0 R /XYZ null null null ] >>\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Hidden 100% review layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Visible 100% import layer) >>\nendobj\n"
    . "40 0 obj\n% /Title (Fake comment title)\n(Visible 100% outline)\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = $extractor->extractOutlineMetadata($pdf);
if ($lines !== ['Visible 100% literal layer']) {
    throw new RuntimeException('Expected PDF comments inside dictionaries and arrays to stay out of parser tokens.');
}

$outline = $metadata['pdf_toc'][0] ?? null;
if (!is_array($outline) || ($outline['title'] ?? null) !== 'Visible 100% outline' || ($outline['page'] ?? null) !== 0) {
    throw new RuntimeException('Expected indirect outline string and destination tokens to ignore comment-only bytes.');
}

echo '<!-- markerpdf-parser-comment-array-dict-string-token-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-comment-aware-token-reader',
    'native_boundary' => 'PDF comments inside arrays and dictionaries are whitespace while percent signs inside literal strings remain content',
    'dictionary_comment_close_ignored' => !str_contains($plainText, 'Hidden dictionary comment leak'),
    'array_comment_reference_ignored' => !str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', '99 0 R'),
    'indirect_string_comment_ignored' => ($outline['title'] ?? null) === 'Visible 100% outline',
    'literal_percent_preserved' => str_contains($plainText, '100%'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
