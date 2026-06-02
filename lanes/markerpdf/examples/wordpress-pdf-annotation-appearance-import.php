<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$toUnicodeCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode annotation appearance fixture text.');
        }
        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageCMap = $toUnicodeCMap(['41' => 'Page Import Body']);
$checkedCMap = $toUnicodeCMap(['41' => 'Approved by Editor']);
$noteCMap = $toUnicodeCMap(['42' => 'Visible Review Note']);
$offCMap = $toUnicodeCMap(['41' => 'Unchecked Appearance Noise']);
$unusedCMap = $toUnicodeCMap(['41' => 'Unreferenced Appearance Noise']);
$pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$checkedAppearance = 'BT /F1 12 Tf 0 0 Td <41> Tj ET';
$noteAppearance = 'BT /F1 12 Tf 0 0 Td <42> Tj ET';
$offAppearance = 'BT /F1 12 Tf 0 0 Td <41> Tj ET';
$unusedAppearance = 'BT /F1 12 Tf 0 0 Td <41> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Annots [5 0 R 6 0 R] /Contents 7 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageSubset /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 680 220 704] /AS /On /AP << /N << /On 9 0 R /Off 10 0 R >> >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 648 260 672] /AP << /N 11 0 R >> >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($pageCMap) . " >>\nstream\n{$pageCMap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 12 0 R >> >> /Length " . strlen($checkedAppearance) . " >>\nstream\n{$checkedAppearance}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 13 0 R >> >> /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 14 0 R >> >> /Length " . strlen($noteAppearance) . " >>\nstream\n{$noteAppearance}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CheckedSubset /Encoding /Identity-H /ToUnicode 15 0 R >>\nendobj\n"
    . "13 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OffSubset /Encoding /Identity-H /ToUnicode 16 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NoteSubset /Encoding /Identity-H /ToUnicode 17 0 R >>\nendobj\n"
    . "15 0 obj\n<< /Length " . strlen($checkedCMap) . " >>\nstream\n{$checkedCMap}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Length " . strlen($offCMap) . " >>\nstream\n{$offCMap}\nendstream\nendobj\n"
    . "17 0 obj\n<< /Length " . strlen($noteCMap) . " >>\nstream\n{$noteCMap}\nendstream\nendobj\n"
    . "18 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 616 220 640] /AP << /N 19 0 R >> >>\nendobj\n"
    . "19 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 20 0 R >> >> /Length " . strlen($unusedAppearance) . " >>\nstream\n{$unusedAppearance}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnusedSubset /Encoding /Identity-H /ToUnicode 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Length " . strlen($unusedCMap) . " >>\nstream\n{$unusedCMap}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-pdf-annotation-appearance-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page-referenced /Annots /AP /N appearance Form XObjects appended to native page text extraction',
    'current_appearance_imported' => str_contains($plainText, 'Approved by Editor'),
    'direct_normal_appearance_imported' => str_contains($plainText, 'Visible Review Note'),
    'stale_appearances_excluded' => !str_contains($plainText, 'Unchecked Appearance Noise') && !str_contains($plainText, 'Unreferenced Appearance Noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
