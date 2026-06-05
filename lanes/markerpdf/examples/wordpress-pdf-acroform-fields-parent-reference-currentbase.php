<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm parent reference boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [10 0 R 18 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (profile) /V (parent@example.test) /DV (draft@example.test) /Kids [8 0 R 12 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Parent 6 0 R /T (email) /TU (Email label) /TM (profile.email.export) /V (editor@example.test) /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /Parent 30 0 R /T (spoof) /TU (Spoof label must not surface) /TM (profile.spoof.export) /V (spoof child value must not surface) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx /T (article.title) /V (Reviewed title value) /Kids [18 0 R 20 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 30 0 R /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /FT /Tx /T (detached.parent) /V (detached parent value must not surface) /Kids [32 0 R] >>\nendobj\n"
    . "32 0 obj\n<< /Subtype /Widget /Parent 30 0 R /Rect [72 480 320 504] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['profile.email', 'article.title']) {
    throw new RuntimeException('Unexpected AcroForm fields for parent-reference boundary smoke.');
}
if (array_column($fieldsByName['article.title']['widgets'] ?? [], 'object') !== [18]) {
    throw new RuntimeException('Mismatched child widget Parent reference was not excluded.');
}

$encoded = json_encode($form, JSON_UNESCAPED_SLASHES) ?: '';
foreach ([
    'profile.spoof',
    'Spoof label must not surface',
    'profile.spoof.export',
    'spoof child value must not surface',
    'detached.parent',
    'detached parent value must not surface',
] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Mismatched AcroForm parent-reference decoy leaked: {$decoyText}");
    }
}
foreach (['editor@example.test', 'Reviewed title value'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review-only value leaked into visible WordPress text: {$reviewOnlyText}");
    }
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'label' => $field['field_name_review']['wordpress_label'] ?? $field['alternate_name'] ?? $field['name'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'path_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
        'widget_objects' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-parent-reference-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-field-tree-parent-reference-boundary',
    'native_boundary' => 'AcroForm field-tree Kids entries with explicit Parent references are accepted only when the child Parent points back to the listing field; mismatched child fields and widgets stay out of WordPress review metadata and visible text.',
    'field_names' => array_column($rows, 'name'),
    'mismatched_child_field_excluded' => !isset($fieldsByName['profile.spoof']),
    'mismatched_child_widget_excluded' => array_column($fieldsByName['article.title']['widgets'] ?? [], 'object') === [18],
    'field_values_review_only' => !str_contains($visibleText, 'editor@example.test')
        && !str_contains($visibleText, 'Reviewed title value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Label</th><th>Value</th><th>Hierarchy</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(' > ', array_map('strval', $row['path_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widget_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
