<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm page widget Parent boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [34 0 R 38 0 R 64 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [32 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "30 0 obj\n<< /FT /Tx /T (valid) /TU (Valid root label) /TM (valid.root.map) /V (Inherited valid value) /DV (Draft valid value) /MaxLen 80 /Kids [32 0 R 36 0 R] /DA (/Helv 10 Tf 0 0 1 rg) >>\nendobj\n"
    . "32 0 obj\n<< /Par#65nt 30 0 R /T (first) /TU (First label) /TM (valid.first.export) /V (first@example.test) /Kids [34 0 R] >>\nendobj\n"
    . "34 0 obj\n<< /Subtype /Widget /Parent 32 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "36 0 obj\n<< /Par#65nt 30 0 R /T (second) /TU (Second label) /TM (valid.second.export) /V (second@example.test) /Kids [38 0 R] >>\nendobj\n"
    . "38 0 obj\n<< /Subtype /Widget /Parent 36 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "62 0 obj\n<< /Par#65nt 70 0 R /T (spoof.child) /TU (Spoof child label must not surface) /TM (spoof.child.map) /V (spoof@example.test) /Kids [64 0 R] >>\nendobj\n"
    . "64 0 obj\n<< /Subtype /Widget /Parent 62 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "70 0 obj\n<< /FT /Tx /T (detached.parent) /V (detached parent value must not surface) /Kids [72 0 R] >>\nendobj\n"
    . "72 0 obj\n<< /Subtype /Widget /Parent 70 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['valid.first', 'valid.second'] as $expectedName) {
    if (!isset($fieldsByName[$expectedName])) {
        throw new RuntimeException("Missing expected AcroForm field {$expectedName}.");
    }
}
if (isset($fieldsByName['spoof.child'])) {
    throw new RuntimeException('Escaped Parent mismatch page-widget repair decoy must not become a WordPress field.');
}
foreach (['spoof@example.test', 'Spoof child label must not surface', 'detached parent value must not surface'] as $leak) {
    if (str_contains(json_encode($form, JSON_UNESCAPED_SLASHES) ?: '', $leak) || str_contains($visibleText, $leak)) {
        throw new RuntimeException("AcroForm page-widget Parent boundary leaked {$leak}.");
    }
}

$first = $fieldsByName['valid.first'];
$second = $fieldsByName['valid.second'];
$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'hierarchy_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
        'widget_objects' => array_column($widgets, 'object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-page-widget-parent-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-page-widget-parent-boundary',
    'native_boundary' => 'Page-owned Widget annotation repair validates escaped field /Parent ownership before promoting omitted AcroForm field branches',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'escaped_parent_valid_branch_preserved' => array_column($second['field_hierarchy']['path'] ?? [], 'object') === [30, 36],
    'escaped_parent_mismatch_decoy_excluded' => !isset($fieldsByName['spoof.child']),
    'spoof_value_review_excluded' => !str_contains(json_encode($form, JSON_UNESCAPED_SLASHES) ?: '', 'spoof@example.test'),
    'visible_text_imported' => str_contains($visibleText, 'Visible AcroForm page widget Parent boundary body'),
    'form_values_visible_text_excluded' => !str_contains($visibleText, 'first@example.test')
        && !str_contains($visibleText, 'second@example.test')
        && !str_contains($visibleText, 'spoof@example.test'),
    'first_widget_objects' => array_column($first['widgets'] ?? [], 'object'),
    'second_widget_objects' => array_column($second['widgets'] ?? [], 'object'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Hierarchy</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(' > ', array_map('strval', $row['hierarchy_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])) . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
