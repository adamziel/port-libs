<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect direct dictionary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields 20 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n[\n"
    . "<< /FT /Tx /T (indirect.direct.root) /TU (Indirect direct root label) /TM (indirect-direct-root-export) /V (Indirect direct root value) /DV (Indirect direct root default) /MaxLen 80 /Kids [8 0 R] >>\n"
    . "<< /FT /Tx /T (indirect.direct.parent) /TU (Indirect direct parent label) /TM (indirect-direct-parent-export) /V (Parent fallback value) /DV (Parent fallback default) /MaxLen 40 /Kids 21 0 R >>\n"
    . "(90 0 R) [91 0 R] << /Nested << /FT /Tx /T (fields.nested.dict.decoy) /V (Fields nested dictionary decoy) >> >> % << /FT /Tx /T (fields.comment.decoy) /V (Fields comment decoy) >>\n"
    . "]\nendobj\n"
    . "21 0 obj\n[\n"
    . "<< /T (child) /TU (Indirect direct child label) /TM (indirect-direct-child-export) /V (Indirect direct child value) /Kids [12 0 R] >>\n"
    . "(92 0 R) [93 0 R] << /Nested << /T (kids.nested.dict.decoy) /V (Kids nested dictionary decoy) >> >> % << /T (kids.comment.decoy) /V (Kids comment decoy) >>\n"
    . "]\nendobj\n"
    . "90 0 obj\n<< /FT /Tx /T (fields.literal.ref.decoy) /V (Fields literal ref decoy) >>\nendobj\n"
    . "91 0 obj\n<< /FT /Tx /T (fields.nested.array.decoy) /V (Fields nested array decoy) >>\nendobj\n"
    . "92 0 obj\n<< /FT /Tx /T (kids.literal.ref.decoy) /V (Kids literal ref decoy) >>\nendobj\n"
    . "93 0 obj\n<< /FT /Tx /T (kids.nested.array.decoy) /V (Kids nested array decoy) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$expectedNames = ['indirect.direct.root', 'indirect.direct.parent.child'];
if (array_keys($fieldsByName) !== $expectedNames) {
    throw new RuntimeException('Indirect direct AcroForm field dictionaries were not materialized.');
}

$root = $fieldsByName['indirect.direct.root'];
$child = $fieldsByName['indirect.direct.parent.child'];
$parentObject = $child['field_hierarchy']['ancestor_objects'][0] ?? null;
if (!is_int($root['object'] ?? null) || !is_int($parentObject) || !is_int($child['object'] ?? null)) {
    throw new RuntimeException('Synthetic AcroForm object metadata is missing.');
}
if (($root['value'] ?? null) !== 'Indirect direct root value' || ($child['value'] ?? null) !== 'Indirect direct child value') {
    throw new RuntimeException('Indirect direct AcroForm field values were not preserved as review metadata.');
}
if (array_column($child['field_hierarchy']['path'] ?? [], 'partial_name') !== ['indirect.direct.parent', 'child']) {
    throw new RuntimeException('Indirect direct AcroForm child hierarchy was not preserved.');
}
if (array_column($root['widgets'] ?? [], 'object') !== [8] || array_column($child['widgets'] ?? [], 'object') !== [12]) {
    throw new RuntimeException('Indirect direct AcroForm widgets were not attached.');
}

$decoyNames = [
    'fields.literal.ref.decoy',
    'fields.nested.array.decoy',
    'fields.nested.dict.decoy',
    'fields.comment.decoy',
    'kids.literal.ref.decoy',
    'kids.nested.array.decoy',
    'kids.nested.dict.decoy',
    'kids.comment.decoy',
];
foreach ($decoyNames as $decoyName) {
    if (isset($fieldsByName[$decoyName])) {
        throw new RuntimeException("AcroForm decoy {$decoyName} must not be promoted.");
    }
}
foreach (['Indirect direct root value', 'Indirect direct child value', 'Parent fallback value'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review text {$reviewOnlyText} leaked into visible page text.");
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
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-indirect-direct-dictionary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-indirect-array-direct-dictionary-boundary',
    'native_boundary' => 'AcroForm /Fields and field /Kids indirect array objects can contain top-level direct field dictionaries; those dictionaries are materialized as synthetic review objects while nested arrays, dictionaries, comments, and literal references stay decoys.',
    'field_names' => array_column($rows, 'name'),
    'field_count' => count($form['fields']),
    'indirect_fields_array_materialized' => ($root['object'] ?? null) > 93,
    'indirect_kids_array_materialized' => is_int($parentObject)
        && ($child['object'] ?? null) > 93
        && ($child['object'] ?? null) !== $parentObject,
    'child_hierarchy_preserved' => array_column($child['field_hierarchy']['path'] ?? [], 'partial_name') === ['indirect.direct.parent', 'child'],
    'root_widget_objects' => array_column($root['widgets'] ?? [], 'object'),
    'child_widget_objects' => array_column($child['widgets'] ?? [], 'object'),
    'array_decoy_fields_excluded' => count(array_intersect($decoyNames, array_keys($fieldsByName))) === 0,
    'form_values_used_as_visible_text' => str_contains($visibleText, 'Indirect direct root value')
        || str_contains($visibleText, 'Indirect direct child value')
        || str_contains($visibleText, 'Parent fallback value'),
    'visible_text' => $visibleText,
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
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
