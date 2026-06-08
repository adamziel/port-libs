<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate widget Subtype boundary body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R 14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.duplicate_subtype) /TU (Duplicate Subtype label) /TM (duplicate-subtype-export) /V (Duplicate Subtype value) /Kids [8 0 R 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Text /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Subtype /Text /Parent 6 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 /Contents (Stale widget subtype annotation) >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Text /Subtype /Widget /FT /Tx /T (inline.duplicate_subtype) /TU (Inline duplicate Subtype label) /TM (inline-duplicate-subtype-export) /V (Inline duplicate Subtype value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Subtype /Text /FT /Tx /T (stale.inline_subtype) /TU (Stale inline Subtype label) /TM (stale-inline-subtype-export) /V (Stale inline Subtype value must not surface) /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['article.duplicate_subtype', 'inline.duplicate_subtype']) {
    throw new RuntimeException('Unexpected AcroForm field names for duplicate Widget subtype boundary.');
}

$listed = $fieldsByName['article.duplicate_subtype'];
$inline = $fieldsByName['inline.duplicate_subtype'];
$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

if (array_column($listed['widgets'] ?? [], 'object') !== [8]) {
    throw new RuntimeException('Expected listed field widget metadata to use the last duplicate /Subtype /Widget.');
}
if (array_column($inline['widgets'] ?? [], 'object') !== [12]) {
    throw new RuntimeException('Expected page-owned inline field repair to use the last duplicate /Subtype /Widget.');
}
foreach ([
    'Stale widget subtype annotation',
    'stale.inline_subtype',
    'Stale inline Subtype value must not surface',
] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException('Expected stale duplicate /Subtype annotation to stay excluded: ' . $decoyText);
    }
}
foreach (['Duplicate Subtype value', 'Inline duplicate Subtype value'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException('Expected AcroForm field value to remain review-only: ' . $reviewOnlyText);
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-duplicate-subtype-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-fields-duplicate-subtype-boundary-currentbase',
    'native_boundary' => 'Widget annotation detection uses the last duplicate /Subtype name before AcroForm field and page-widget repair.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'listed_widget_objects' => array_column($listed['widgets'] ?? [], 'object'),
    'inline_widget_objects' => array_column($inline['widgets'] ?? [], 'object'),
    'listed_page_annotation_indexes' => array_column($listed['widgets'] ?? [], 'page_annotation_index'),
    'inline_page_annotation_indexes' => array_column($inline['widgets'] ?? [], 'page_annotation_index'),
    'stale_first_widget_last_text_excluded' => !str_contains($encoded, 'stale.inline_subtype'),
    'last_widget_subtype_repaired' => array_column($listed['widgets'] ?? [], 'object') === [8]
        && array_column($inline['widgets'] ?? [], 'object') === [12],
    'field_values_review_only' => !str_contains($visibleText, 'Duplicate Subtype value')
        && !str_contains($visibleText, 'Inline duplicate Subtype value'),
    'visible_text' => $visibleText,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widgets</th></tr>\n";
foreach ($fieldsByName as $field) {
    $widgets = array_column($field['widgets'] ?? [], 'object');
    echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['field_type_label'] ?? $field['field_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['value_state']['display_value'] ?? $field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $widgets)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
