<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate parent page boundary one) Tj ET';
$pageTwoText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate parent page boundary two) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (duplicate.parent.current) /TU (Duplicate Parent current label) /TM (duplicate-parent-current-export) /V (Current duplicate Parent value) /DV (Current duplicate Parent draft) /MaxLen 64 >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 99 0 R /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (duplicate.parent.stale-last) /TU (Duplicate Parent stale label) /TM (duplicate-parent-stale-export) /V (Stale duplicate Parent value must not surface) >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Parent 98 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (duplicate.page.current) /TU (Duplicate page current label) /TM (duplicate-page-current-export) /V (Current duplicate page value) >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 4 0 R /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /FT /Tx /T (duplicate.page.stale-last) /TU (Duplicate page stale label) /TM (duplicate-page-stale-export) /V (Stale duplicate page value must not surface) >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 520 320 544] /P 3 0 R /P 4 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneText) . " >>\nstream\n{$pageOneText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoText) . " >>\nstream\n{$pageTwoText}\nendstream\nendobj\n"
    . "98 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (stale.parent.first) /TU (Stale first Parent label) /TM (stale-first-parent-export) /V (Stale first Parent value must not surface) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['duplicate.parent.current', 'duplicate.page.current'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Missing expected AcroForm field {$fieldName}.");
    }
}
foreach (['stale.parent.first', 'duplicate.parent.stale-last', 'duplicate.page.stale-last'] as $fieldName) {
    if (isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Stale duplicate-key AcroForm field {$fieldName} must not be promoted.");
    }
}

$parent = $fieldsByName['duplicate.parent.current'];
$page = $fieldsByName['duplicate.page.current'];
$parentWidget = $parent['widgets'][0] ?? null;
$pageWidget = $page['widgets'][0] ?? null;
if (!is_array($parentWidget) || ($parentWidget['object'] ?? null) !== 8) {
    throw new RuntimeException('Duplicate /Parent last value did not select the current parent widget.');
}
if (!is_array($pageWidget) || ($pageWidget['object'] ?? null) !== 16) {
    throw new RuntimeException('Duplicate /P last value did not select the current page-owned widget.');
}
if (($pageWidget['page_annotation_index'] ?? null) !== 2) {
    throw new RuntimeException('Duplicate /P widget did not preserve page annotation order.');
}
if (str_contains($visibleText, 'Current duplicate Parent value')
    || str_contains($visibleText, 'Current duplicate page value')
    || str_contains($visibleText, 'Stale duplicate Parent value must not surface')
    || str_contains($visibleText, 'Stale duplicate page value must not surface')
) {
    throw new RuntimeException('AcroForm review values leaked into visible WordPress text.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widgets' => array_column($widgets, 'object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-duplicate-parent-page-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-duplicate-parent-page-boundary',
    'native_boundary' => 'Duplicate top-level Widget /Parent and /P keys use the last PDF dictionary value before page-owned AcroForm field repair.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'last_duplicate_parent_selected' => ($parent['object'] ?? null) === 6
        && array_column($parent['widgets'] ?? [], 'object') === [8],
    'stale_first_parent_excluded' => !isset($fieldsByName['stale.parent.first']),
    'stale_last_parent_excluded' => !isset($fieldsByName['duplicate.parent.stale-last']),
    'last_duplicate_page_selected' => ($page['object'] ?? null) === 14
        && array_column($page['widgets'] ?? [], 'object') === [16]
        && array_column($page['widgets'] ?? [], 'page_annotation_index') === [2],
    'stale_last_page_excluded' => !isset($fieldsByName['duplicate.page.stale-last']),
    'form_values_visible_in_text' => str_contains($visibleText, 'Current duplicate Parent value')
        || str_contains($visibleText, 'Current duplicate page value')
        || str_contains($visibleText, 'Stale duplicate Parent value must not surface')
        || str_contains($visibleText, 'Stale duplicate page value must not surface'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widget objects</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widgets'])) . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
