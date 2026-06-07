<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct widget canonical body) Tj ET';
$pageWidget = '<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>';
$parentKidWidget = '<< /F 4 /P 3 0 R /Rect [72 640 320 664] /Par#65nt 10 0 R /Sub#74ype /Widget >>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [{$pageWidget}] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (canonical.widget) /TU (Canonical widget label) /TM (canonical-widget-export) /V (Canonical widget value) /DV (Canonical widget default) /MaxLen 58 /Kids [{$parentKidWidget}] >>\nendobj\n"
    . "20 0 obj\n<< /FT /Tx /T (detached.canonical.decoy) /V (Detached canonical decoy value must not surface) /Kids [<< /F 4 /Rect [72 600 320 624] /Subtype /Widget /Parent 20 0 R >>] >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['canonical.widget']) {
    throw new RuntimeException('Unexpected AcroForm field names for direct widget canonical boundary.');
}
foreach (['detached.canonical.decoy', 'Detached canonical decoy value must not surface'] as $decoyText) {
    if (str_contains(json_encode($form, JSON_UNESCAPED_SLASHES) ?: '', $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Detached direct widget canonical decoy leaked into review metadata: {$decoyText}");
    }
}
foreach (['Canonical widget value', 'Canonical widget default'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review-only field text leaked into visible WordPress content: {$reviewOnlyText}");
    }
}

$field = $fieldsByName['canonical.widget'];
$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];

if (($field['field_type_label'] ?? null) !== 'text' || array_column($widgets, 'page_annotation_index') !== [0]) {
    throw new RuntimeException('Canonical direct widget boundary did not preserve the page-owned Widget annotation.');
}

echo '<!-- markerpdf:pdf-acroform-fields-direct-widget-canonical-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-direct-widget-canonical-boundary',
    'native_boundary' => 'Direct Widget dictionaries in page Annots and parent Kids are matched by decoded unordered dictionary content before WordPress AcroForm field review.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'field_object' => $field['object'] ?? null,
    'field_type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
    'widget_objects' => array_column($widgets, 'object'),
    'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    'decoded_name_key_match' => true,
    'unordered_dictionary_match' => true,
    'detached_decoy_excluded' => !isset($fieldsByName['detached.canonical.decoy']),
    'field_values_review_only' => !str_contains($visibleText, 'Canonical widget value')
        && !str_contains($visibleText, 'Canonical widget default'),
    'visible_text' => $visibleText,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widgets</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['field_type_label'] ?? $field['field_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['value_state']['display_value'] ?? $field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(implode(',', array_map('strval', array_column($widgets, 'object'))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
