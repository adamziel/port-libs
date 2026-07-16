<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Parent field boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 20 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [12 0 R 18 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "12 0 obj\n<< /Parent << /FT /Tx /T (profile) /TU (Direct parent label) /TM (profile-parent-map) /V (Inherited direct parent value) /DV (Inherited direct parent default) /MaxLen 80 /DA (/Helv 10 Tf 0 0 1 rg) /Kids [12 0 R] >> /T (email) /TU (Email child label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /Parent << /FT /Tx /T (detached.parent.decoy) /TU (Detached parent label must not surface) /TM (detached-parent-map) /V (Detached direct parent value must not surface) /DV (Detached direct parent default must not surface) /MaxLen 5 /Kids [99 0 R] >> /FT /Tx /T (local.only) /TU (Local child label) /TM (local-only-export) /V (Local child value) /Kids [20 0 R] >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "99 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (!isset($fieldsByName['profile.email'], $fieldsByName['local.only'])) {
    throw new RuntimeException('Expected direct Parent field-boundary rows were not imported.');
}
if (isset($fieldsByName['email'], $fieldsByName['detached.parent.decoy'])) {
    throw new RuntimeException('Direct Parent ownership boundary leaked a stale or unowned field name.');
}
if ($visibleText !== 'Visible AcroForm direct Parent field boundary body') {
    throw new RuntimeException('AcroForm review values leaked into visible WordPress text.');
}

$email = $fieldsByName['profile.email'];
$local = $fieldsByName['local.only'];
$parentObject = $email['field_hierarchy']['ancestor_objects'][0] ?? null;
if (!is_int($parentObject) || $parentObject <= 99) {
    throw new RuntimeException('Direct Parent field dictionary was not materialized into a synthetic parent object.');
}
if (($email['default_value'] ?? null) !== 'Inherited direct parent default' || ($email['max_length'] ?? null) !== 80) {
    throw new RuntimeException('Owned direct Parent field dictionary did not provide inherited review metadata.');
}
if (($local['default_value'] ?? null) !== null || ($local['max_length'] ?? null) !== null) {
    throw new RuntimeException('Unowned direct Parent field dictionary leaked inherited metadata.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'inherited' => implode(',', array_map('strval', $field['field_hierarchy']['inherited_attributes'] ?? [])),
        'widgets' => implode(',', array_map('strval', array_column($widgets, 'object'))),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-direct-parent-field-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-direct-parent-field-boundary',
    'native_boundary' => 'Listed AcroForm child fields materialize direct /Parent field dictionaries only when the parent /Kids array owns the child object.',
    'field_names' => array_column($rows, 'name'),
    'field_count' => count($form['fields']),
    'direct_parent_inherited_name_preserved' => isset($fieldsByName['profile.email']),
    'direct_parent_synthetic_object' => $parentObject,
    'owned_direct_parent_metadata_inherited' => ($email['default_value'] ?? null) === 'Inherited direct parent default'
        && ($email['max_length'] ?? null) === 80,
    'unowned_direct_parent_excluded' => !isset($fieldsByName['detached.parent.decoy'])
        && ($local['default_value'] ?? null) === null
        && ($local['max_length'] ?? null) === null,
    'form_values_visible_in_text' => str_contains($visibleText, 'editor@example.test')
        || str_contains($visibleText, 'Inherited direct parent default')
        || str_contains($visibleText, 'Local child value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Inherited</th><th>Widget objects</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['inherited'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['widgets'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
