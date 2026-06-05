<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$stalePageText = 'BT /F1 12 Tf 72 720 Td (Stale AcroForm trailer root page body) Tj ET';
$currentPageText = 'BT /F1 12 Tf 72 720 Td (Current AcroForm trailer root page body) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>');
$addObject(4, "<< /Length " . strlen($stalePageText) . " >>\nstream\n{$stalePageText}\nendstream");
$addObject(5, '<< /Fields [6 0 R] /NeedAppearances false /DA (/Stale 9 Tf 1 0 0 rg) >>');
$addObject(6, '<< /FT /Tx /T (stale.email) /TU (Stale email label) /V (stale@example.test) /Kids [8 0 R] >>');
$addObject(8, '<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>');

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /AcroForm 25 0 R >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Contents 23 0 R /Annots [28 0 R 32 0 R 35 0 R] >>');
$addObject(23, "<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream");
$addObject(25, '<< /Fields [26 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>');
$addObject(26, '<< /FT /Tx /T (current.email) /TU (Current email label) /V (current@example.test) /Kids [28 0 R] >>');
$addObject(28, '<< /Subtype /Widget /Parent 26 0 R /Rect [72 640 300 664] /P 22 0 R /F 4 >>');
$addObject(30, '<< /FT /Ch /T (current.category) /V (page) /Opt [(post) (page)] /Kids [32 0 R] >>');
$addObject(32, '<< /Subtype /Widget /Parent 30 0 R /Rect [72 600 260 624] /P 22 0 R /F 4 >>');
$addObject(35, '<< /Subtype /Widget /FT /Tx /T (current.inline) /V (inline current value) /Rect [72 560 320 584] /P 22 0 R /F 4 >>');

$xrefOffset = strlen($pdf);
$maxObject = 35;
$pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
    . "0000000000 65535 f \n";
for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
        : "0000000000 00000 f \n";
}

$pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 20 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['current.email', 'current.category', 'current.inline'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing current trailer-root AcroForm field {$name}.");
    }
}
if (isset($fieldsByName['stale.email'])) {
    throw new RuntimeException('Stale lower-numbered catalog AcroForm field must not be imported.');
}
if (($form['need_appearances'] ?? false) !== true) {
    throw new RuntimeException('Current trailer-root AcroForm NeedAppearances was not selected.');
}
if (!str_contains($visibleText, 'Current AcroForm trailer root page body') || str_contains($visibleText, 'Stale AcroForm trailer root page body')) {
    throw new RuntimeException('Visible text did not follow the current trailer Root page tree.');
}

$encoded = json_encode($form, JSON_UNESCAPED_SLASHES) ?: '';
$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'object' => $field['object'] ?? null,
        'widget_objects' => array_column($widgets, 'object'),
        'page_objects' => array_column($widgets, 'page_object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-trailer-root-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-current-trailer-root-acroform-boundary',
    'native_boundary' => 'Final classic-xref trailer /Root selects the AcroForm and page tree before field/widget review',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'field_objects' => array_column($rows, 'object'),
    'need_appearances_from_current_root' => ($form['need_appearances'] ?? false) === true,
    'current_page_widget_parent_promoted' => isset($fieldsByName['current.category']),
    'current_standalone_widget_promoted' => isset($fieldsByName['current.inline']),
    'stale_catalog_field_excluded' => !isset($fieldsByName['stale.email'])
        && !str_contains($encoded, 'stale.email')
        && !str_contains($encoded, 'stale@example.test'),
    'visible_text_uses_current_root' => str_contains($visibleText, 'Current AcroForm trailer root page body')
        && !str_contains($visibleText, 'Stale AcroForm trailer root page body'),
    'form_values_visible_in_text' => str_contains($visibleText, 'current@example.test')
        || str_contains($visibleText, 'inline current value')
        || str_contains($visibleText, 'stale@example.test'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widget_objects']))
        . '; page objects ' . implode(',', array_map('strval', $row['page_objects']))
        . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
