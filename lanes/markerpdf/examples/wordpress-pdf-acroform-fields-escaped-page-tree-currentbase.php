<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm escaped page tree body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /T#79pe /Pages /K#69ds [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /T#79pe /P#61ge /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (listed.escapedpage) /TU (Listed escaped page label) /TM (listed-escaped-page-export) /V (Listed escaped page value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (pageonly.escapedpage) /TU (Page-only escaped page label) /V (publish) /Opt [(draft) (publish)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx /T (detached.escapedpage.decoy) /TU (Detached escaped page label must not surface) /V (Detached escaped page value must not surface) /Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedForm = json_encode($form, JSON_UNESCAPED_SLASHES) ?: '';
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['listed.escapedpage', 'pageonly.escapedpage'] as $expectedName) {
    if (!isset($fieldsByName[$expectedName])) {
        throw new RuntimeException("Missing expected AcroForm field {$expectedName}.");
    }
}
if (isset($fieldsByName['detached.escapedpage.decoy'])) {
    throw new RuntimeException('Detached escaped page tree decoy must not become a WordPress field.');
}
foreach ([
    'Detached escaped page label must not surface',
    'Detached escaped page value must not surface',
] as $leak) {
    if (str_contains($encodedForm, $leak) || str_contains($visibleText, $leak)) {
        throw new RuntimeException("AcroForm escaped page tree boundary leaked {$leak}.");
    }
}

$listed = $fieldsByName['listed.escapedpage'];
$pageOnly = $fieldsByName['pageonly.escapedpage'];
$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widget_objects' => array_column($widgets, 'object'),
        'page_objects' => array_column($widgets, 'page_object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-escaped-page-tree-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-escaped-page-tree-boundary',
    'native_boundary' => 'Escaped page-tree /Type, /Kids, and /Page names are decoded before page-widget AcroForm repair',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'listed_widget_page_owned' => array_column($listed['widgets'] ?? [], 'page_object') === [3],
    'page_only_widget_repaired' => array_column($pageOnly['widgets'] ?? [], 'page_annotation_index') === [1],
    'detached_decoy_excluded' => !isset($fieldsByName['detached.escapedpage.decoy']),
    'visible_text_imported' => $visibleText === 'Visible AcroForm escaped page tree body',
    'form_values_visible_text_excluded' => !str_contains($visibleText, 'Listed escaped page value')
        && !str_contains($visibleText, 'publish'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widgets</th><th>Page annotation indexes</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])) . '; pages ' . implode(',', array_map('strval', $row['page_objects'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['page_annotation_indexes'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
