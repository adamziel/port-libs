<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm non-field parent boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 1 /TU (Page tree label must not surface) /TM (page.tree.map) /V (Page tree value must not surface) /DV (Page tree default must not surface) /MaxLen 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 34 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 32 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /Parent 2 0 R /FT /Tx /T (article.title) /V (Current article title) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /FT /Tx /DV (Anonymous inherited draft) /MaxLen 40 /Kids [32 0 R] >>\nendobj\n"
    . "32 0 obj\n<< /Parent 30 0 R /T (anonymous.child) /V (Anonymous child value) /Kids [34 0 R] >>\nendobj\n"
    . "34 0 obj\n<< /Subtype /Widget /Parent 32 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['article.title', 'anonymous.child']) {
    throw new RuntimeException('Unexpected AcroForm fields for non-field parent boundary.');
}

$encoded = json_encode($form, JSON_UNESCAPED_SLASHES) ?: '';
foreach ([
    'Page tree label must not surface',
    'page.tree.map',
    'Page tree value must not surface',
    'Page tree default must not surface',
] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Typed page-tree AcroForm decoy leaked: {$decoyText}");
    }
}

$article = $fieldsByName['article.title'];
$anonymous = $fieldsByName['anonymous.child'];
$articleWidgets = is_array($article['widgets'] ?? null) ? $article['widgets'] : [];
$anonymousWidgets = is_array($anonymous['widgets'] ?? null) ? $anonymous['widgets'] : [];
$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'path_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
        'inherited_attributes' => $field['field_hierarchy']['inherited_attributes'] ?? [],
        'widget_objects' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-nonfield-parent-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-non-field-parent-boundary',
    'native_boundary' => 'Typed Catalog/Pages/Page/non-widget Annot dictionaries are excluded from AcroForm field hierarchy inheritance even when they contain field-like keys or Kids arrays; untyped AcroForm grouping nodes remain valid inheritance sources.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'non_field_page_tree_parent_excluded' => ($article['default_value'] ?? null) === null
        && ($article['max_length'] ?? null) === null
        && array_column($article['field_hierarchy']['path'] ?? [], 'object') === [6]
        && ($article['field_hierarchy']['ancestor_objects'] ?? []) === [],
    'page_tree_metadata_excluded_from_review' => !str_contains($encoded, 'Page tree label must not surface')
        && !str_contains($encoded, 'page.tree.map')
        && !str_contains($encoded, 'Page tree value must not surface')
        && !str_contains($encoded, 'Page tree default must not surface'),
    'anonymous_grouping_parent_preserved' => ($anonymous['default_value'] ?? null) === 'Anonymous inherited draft'
        && ($anonymous['max_length'] ?? null) === 40
        && array_column($anonymous['field_hierarchy']['path'] ?? [], 'object') === [30, 32]
        && ($anonymous['field_hierarchy']['inherited_attributes'] ?? []) === ['FT', 'DV', 'MaxLen'],
    'article_widget_page_object' => $articleWidgets[0]['page_object'] ?? null,
    'anonymous_widget_page_object' => $anonymousWidgets[0]['page_object'] ?? null,
    'field_values_review_only' => !str_contains($visibleText, 'Current article title')
        && !str_contains($visibleText, 'Anonymous child value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Hierarchy</th><th>Inherited</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(' > ', array_map('strval', $row['path_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['inherited_attributes'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widget_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
