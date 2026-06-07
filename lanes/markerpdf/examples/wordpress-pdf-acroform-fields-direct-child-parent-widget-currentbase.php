<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct child Parent widget body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile parent label) /V (profile parent value) /DV (profile draft value) /MaxLen 64 /Kids [\n"
    . "<< /Parent 10 0 R /T (email) /TU (Direct child parent label) /TM (profile.email.export) /V (direct-child@example.test) /Kids [12 0 R] >>\n"
    . "<< /Parent 99 0 R /T (secret) /TU (Direct child decoy label) /TM (profile.secret.export) /V (direct child secret value must not surface) /Kids [16 0 R] >>\n"
    . "] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 99 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (detached.parent.decoy) /V (detached parent value must not surface) /Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 99 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['profile.email']) {
    throw new RuntimeException('Unexpected AcroForm fields for direct child Parent widget boundary.');
}

$field = $fieldsByName['profile.email'];
$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
$hierarchyObjects = array_column($field['field_hierarchy']['path'] ?? [], 'object');
$partialNames = array_column($field['field_hierarchy']['path'] ?? [], 'partial_name');
if (($field['value'] ?? null) !== 'direct-child@example.test') {
    throw new RuntimeException('Direct child AcroForm value metadata was not preserved.');
}
if (array_column($widgets, 'object') !== [12] || array_column($widgets, 'page_annotation_index') !== [0]) {
    throw new RuntimeException('Direct child AcroForm widget with ancestor Parent was not retained.');
}

$encoded = json_encode($form, JSON_UNESCAPED_SLASHES) ?: '';
foreach ([
    'profile.secret',
    'Direct child decoy label',
    'profile.secret.export',
    'direct child secret value must not surface',
    'detached.parent.decoy',
    'detached parent value must not surface',
] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("AcroForm direct child Parent decoy leaked: {$decoyText}");
    }
}
foreach (['direct-child@example.test', 'profile parent value', 'profile draft value'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review-only text leaked into WordPress body: {$reviewOnlyText}");
    }
}

$row = [
    'name' => $field['name'] ?? null,
    'label' => $field['field_name_review']['wordpress_label'] ?? $field['alternate_name'] ?? $field['name'] ?? null,
    'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
    'hierarchy' => implode('.', array_filter(array_map(
        static fn (mixed $part): string => is_string($part) ? $part : '',
        $partialNames
    ))),
    'field_object' => $field['object'] ?? null,
    'parent_object' => $hierarchyObjects[0] ?? null,
    'widget_objects' => array_column($widgets, 'object'),
    'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
];

echo '<!-- markerpdf:pdf-acroform-fields-direct-child-parent-widget-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-direct-child-parent-widget-boundary',
    'native_boundary' => 'A direct child field dictionary inside an indirect parent /Kids array can declare /Parent to the containing field; page widgets in that direct child /Kids branch whose /Parent points at the same containing field remain attached to the synthetic child review row.',
    'field_names' => array_keys($fieldsByName),
    'field_count' => count($form['fields']),
    'direct_child_parent_preserved' => $row['parent_object'] === 10 && is_int($row['field_object']) && $row['field_object'] > 99,
    'ancestor_parent_widget_retained' => $row['widget_objects'] === [12] && $row['page_annotation_indexes'] === [0],
    'mismatched_direct_child_excluded' => !isset($fieldsByName['profile.secret'])
        && !str_contains($encoded, 'direct child secret value must not surface'),
    'field_values_visible_in_text' => str_contains($visibleText, 'direct-child@example.test')
        || str_contains($visibleText, 'profile parent value')
        || str_contains($visibleText, 'profile draft value'),
    'visible_text' => $visibleText,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Label</th><th>Value</th><th>Hierarchy</th><th>Widget</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) $row['hierarchy'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(
    'field object ' . (string) $row['field_object'] . '; widget object ' . implode(',', array_map('strval', $row['widget_objects'])),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
