<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf '
    . '/TH << /MCID 0 >> BDC 72 720 Td (Feature) Tj EMC '
    . '/TH << /MCID 1 >> BDC 180 720 Td (Status) Tj EMC '
    . '/TD << /MCID 2 >> BDC 72 704 Td (Images) Tj EMC '
    . '/TD << /MCID 3 >> BDC 180 704 Td (Ready) Tj EMC '
    . '/TD << /MCID 4 >> BDC 72 688 Td (Charts) Tj EMC '
    . '/TD << /MCID 5 >> BDC 180 688 Td (Queued) Tj EMC '
    . '/TD << /MCID 6 >> BDC 72 672 Td (Totals) Tj EMC '
    . '/TD << /MCID 7 >> BDC 180 672 Td (Done) Tj EMC '
    . '/Artifact << /MCID 99 >> BDC 72 650 Td (Artifact table footer noise) Tj EMC ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Grid /Table /HeadBand /THead /BodyBand /TBody /FootBand /TFoot /DataRow /TR /HeadingCell /TH /DataCell /TD /Wrap /Div >> /K [21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Grid /Pg 3 0 R /K [22 0 R 23 0 R 24 0 R 25 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /FootBand /K [42 0 R] >>\nendobj\n"
    . "23 0 obj\n<< /Type /StructElem /S /Wrap /K [26 0 R] >>\nendobj\n"
    . "24 0 obj\n<< /Type /StructElem /S /BodyBand /K [41 0 R] >>\nendobj\n"
    . "25 0 obj\n<< /Type /StructElem /S /Wrap /K [27 0 R] >>\nendobj\n"
    . "26 0 obj\n<< /Type /StructElem /S /BodyBand /K [43 0 R] >>\nendobj\n"
    . "27 0 obj\n<< /Type /StructElem /S /HeadBand /K [40 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /DataRow /K [50 0 R 51 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /DataRow /K [52 0 R 53 0 R] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /DataRow /K [56 0 R 57 0 R] >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /DataRow /K [54 0 R 55 0 R] >>\nendobj\n"
    . "50 0 obj\n<< /Type /StructElem /S /HeadingCell /K 0 >>\nendobj\n"
    . "51 0 obj\n<< /Type /StructElem /S /HeadingCell /K 1 >>\nendobj\n"
    . "52 0 obj\n<< /Type /StructElem /S /DataCell /K 2 >>\nendobj\n"
    . "53 0 obj\n<< /Type /StructElem /S /DataCell /K 3 >>\nendobj\n"
    . "54 0 obj\n<< /Type /StructElem /S /DataCell /K 4 >>\nendobj\n"
    . "55 0 obj\n<< /Type /StructElem /S /DataCell /K 5 >>\nendobj\n"
    . "56 0 obj\n<< /Type /StructElem /S /DataCell /K 6 >>\nendobj\n"
    . "57 0 obj\n<< /Type /StructElem /S /DataCell /K 7 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$tagged = $extractor->extractTaggedContent($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = ['Feature', 'Status', 'Images', 'Ready', 'Charts', 'Queued', 'Totals', 'Done'];

if (array_column($tagged, 'text') !== $expected) {
    throw new RuntimeException('Expected normalized tagged-table cell order.');
}

if (array_column($tagged, 'table_section_role') !== ['THead', 'THead', 'TBody', 'TBody', 'TBody', 'TBody', 'TFoot', 'TFoot']) {
    throw new RuntimeException('Expected tagged-table section diagnostics.');
}

if (str_contains($plainText, 'Artifact table footer noise')) {
    throw new RuntimeException('Artifact MCID leaked into visible WordPress table text.');
}

$rows = [];
foreach ($tagged as $cell) {
    $rowIndex = (int) ($cell['table_row_index'] ?? 0);
    $cellIndex = (int) ($cell['table_cell_index'] ?? 0);
    $rows[$rowIndex][$cellIndex] = (string) ($cell['text'] ?? '');
}
ksort($rows);
foreach ($rows as &$row) {
    ksort($row);
}
unset($row);

echo '<!-- markerpdf-tagged-table-irregular-sections-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-structtree-tagged-table-section-order',
    'native_boundary' => 'tagged table sections are normalized as THead, TBody, TFoot while repeated body sections and wrapper nodes keep all cells',
    'section_order_normalized' => array_column($tagged, 'table_section_order_normalized') === array_fill(0, 8, true),
    'all_cells_preserved' => count($tagged) === 8,
    'artifact_excluded' => !str_contains($plainText, 'Artifact table footer noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><thead><tr>";
foreach ($rows[0] ?? [] as $text) {
    echo '<th>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
}
echo "</tr></thead><tbody>";
foreach (array_slice($rows, 1, -1, true) as $row) {
    echo '<tr>';
    foreach ($row as $text) {
        echo '<td>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    }
    echo '</tr>';
}
echo "</tbody><tfoot><tr>";
foreach ($rows[array_key_last($rows)] ?? [] as $text) {
    echo '<td>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
}
echo "</tr></tfoot></table></figure>\n";
echo "<!-- /wp:table -->\n";
