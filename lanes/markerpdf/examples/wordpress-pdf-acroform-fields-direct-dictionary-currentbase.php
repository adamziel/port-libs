<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible direct AcroForm dictionary WordPress body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [\n"
    . "<< /FT /Tx /T (direct.root) /TU (Direct root label) /TM (direct-root-export) /V (Direct root value) /DV (Direct root default) /MaxLen 80 /Kids [8 0 R] >>\n"
    . "<< /FT /Tx /T (direct.parent) /TU (Direct parent label) /TM (direct-parent-map) /V (Parent direct value) /DV (Parent direct default) /MaxLen 32 /Kids [\n"
    . "<< /T (child) /TU (Direct child label) /TM (direct-child-export) /V (Direct child terminal value) /Kids [12 0 R] >>\n"
    . "(99 0 R) [101 0 R] << /Nested << /T (kids.nested.dict.decoy) /V (Kids nested dictionary decoy) >> >> % << /FT /Tx /T (kids.comment.decoy) /V (Kids comment decoy) >>\n"
    . "] >>\n"
    . "(102 0 R) [104 0 R] << /Nested << /FT /Tx /T (fields.nested.dict.decoy) /V (Fields nested dictionary decoy) >> >> % << /FT /Tx /T (fields.comment.decoy) /V (Fields comment decoy) >>\n"
    . "] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (kids.literal.ref.decoy) /V (Kids literal ref decoy) >>\nendobj\n"
    . "101 0 obj\n<< /FT /Tx /T (kids.nested.array.decoy) /V (Kids nested array decoy) >>\nendobj\n"
    . "102 0 obj\n<< /FT /Tx /T (fields.literal.ref.decoy) /V (Fields literal ref decoy) >>\nendobj\n"
    . "104 0 obj\n<< /FT /Tx /T (fields.nested.array.decoy) /V (Fields nested array decoy) >>\nendobj\n"
    . "110 0 obj\n<< /FT /Tx /T (detached.highwater.decoy) /V (Detached highwater decoy) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['direct.root', 'direct.parent.child'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected direct AcroForm field {$name}.");
    }
}
if (count($form['fields']) !== 2) {
    throw new RuntimeException('Direct AcroForm dictionaries must produce exactly two review fields in this smoke.');
}

$root = $fieldsByName['direct.root'];
$child = $fieldsByName['direct.parent.child'];
$parentObject = $child['field_hierarchy']['ancestor_objects'][0] ?? null;
if (!is_int($root['object'] ?? null) || ($root['object'] ?? 0) <= 110) {
    throw new RuntimeException('Direct root field dictionary was not materialized as a synthetic review object.');
}
if (!is_int($parentObject) || $parentObject <= 110 || !is_int($child['object'] ?? null) || ($child['object'] ?? 0) <= 110) {
    throw new RuntimeException('Direct parent/child field dictionaries were not materialized as synthetic review objects.');
}
if (($root['value'] ?? null) !== 'Direct root value' || ($child['value'] ?? null) !== 'Direct child terminal value') {
    throw new RuntimeException('Direct AcroForm dictionary values were not preserved for review.');
}
if (($child['value_state']['hierarchy_boundary']['current_value_source'] ?? null) !== 'field_terminal_override') {
    throw new RuntimeException('Direct child field value did not override its direct parent value.');
}
if (array_column($root['widgets'] ?? [], 'object') !== [8] || array_column($child['widgets'] ?? [], 'object') !== [12]) {
    throw new RuntimeException('Direct AcroForm dictionaries did not retain indirect widget ownership.');
}
if (array_column($root['widgets'] ?? [], 'page_annotation_index') !== [0] || array_column($child['widgets'] ?? [], 'page_annotation_index') !== [1]) {
    throw new RuntimeException('Direct AcroForm widgets lost page annotation indexes.');
}

$decoyNames = [
    'kids.literal.ref.decoy',
    'kids.nested.array.decoy',
    'kids.nested.dict.decoy',
    'kids.comment.decoy',
    'fields.literal.ref.decoy',
    'fields.nested.array.decoy',
    'fields.nested.dict.decoy',
    'fields.comment.decoy',
    'detached.highwater.decoy',
];
foreach ($decoyNames as $decoyName) {
    if (isset($fieldsByName[$decoyName])) {
        throw new RuntimeException("Direct AcroForm decoy field {$decoyName} must not be promoted.");
    }
}

$reviewOnlyTexts = [
    'Direct root value',
    'Direct child terminal value',
    'Parent direct default',
    'Kids nested dictionary decoy',
    'Fields nested dictionary decoy',
    'Detached highwater decoy',
];
foreach ($reviewOnlyTexts as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("Review-only AcroForm text leaked into visible WordPress text: {$reviewOnlyText}");
    }
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'object' => $field['object'] ?? null,
        'widget_objects' => array_column($widgets, 'object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-direct-dictionary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-direct-field-dictionaries',
    'native_boundary' => 'Catalog AcroForm /Fields and field /Kids arrays can contain direct field dictionaries; this smoke materializes only top-level direct dictionaries into review rows and keeps nested array/dictionary/comment decoys excluded',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'synthetic_field_objects' => [$root['object'], $parentObject, $child['object']],
    'direct_fields_materialized' => is_int($root['object'] ?? null) && ($root['object'] ?? 0) > 110,
    'direct_kids_materialized' => is_int($parentObject) && is_int($child['object'] ?? null) && ($child['object'] ?? 0) > 110,
    'direct_child_value_overrides_parent' => ($child['value_state']['hierarchy_boundary']['current_value_source'] ?? null) === 'field_terminal_override',
    'page_annotation_indexes_preserved' => array_column($root['widgets'] ?? [], 'page_annotation_index') === [0]
        && array_column($child['widgets'] ?? [], 'page_annotation_index') === [1],
    'array_decoy_fields_excluded' => count(array_intersect($decoyNames, array_keys($fieldsByName))) === 0,
    'form_values_visible_in_text' => false,
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
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])) . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
