<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm parent ownership boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 34 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [12 0 R 32 0 R] /NeedAppearances true >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (decoy.profile) /TU (Detached parent label must not surface) /TM (decoy.profile.map) /V (Detached parent value must not surface) /DV (Detached parent default must not surface) /MaxLen 6 /Kids [16 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Terminal email label) /TM (email.map) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /FT /Tx /T (valid.profile) /V (Inherited valid title) /DV (Draft valid title) /Kids [32 0 R] >>\nendobj\n"
    . "32 0 obj\n<< /Parent 30 0 R /T (title) /Kids [34 0 R] >>\nendobj\n"
    . "34 0 obj\n<< /Subtype /Widget /Parent 32 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['email', 'valid.profile.title']) {
    throw new RuntimeException('Unexpected AcroForm field names for parent ownership boundary.');
}
if (isset($fieldsByName['decoy.profile.email'])) {
    throw new RuntimeException('Detached AcroForm parent name must not be inherited.');
}
foreach (['Detached parent label must not surface', 'decoy.profile.map', 'Detached parent value must not surface'] as $decoyText) {
    if (str_contains(json_encode($form, JSON_UNESCAPED_SLASHES) ?: '', $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Detached AcroForm parent decoy leaked: {$decoyText}");
    }
}

$email = $fieldsByName['email'];
$valid = $fieldsByName['valid.profile.title'];
$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'path_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
        'widget_objects' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-parent-ownership-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-parent-kids-ownership-boundary',
    'native_boundary' => 'AcroForm Parent inheritance is accepted only when the parent Kids tree owns the child field; detached Parent decoys stay excluded from WordPress form review metadata and visible text',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'detached_parent_inheritance_rejected' => ($email['name'] ?? null) === 'email'
        && ($email['field_type_label'] ?? null) === 'unknown'
        && array_column($email['field_hierarchy']['path'] ?? [], 'object') === [12],
    'detached_parent_decoy_excluded' => !isset($fieldsByName['decoy.profile.email']),
    'owned_parent_inheritance_preserved' => ($valid['name'] ?? null) === 'valid.profile.title'
        && ($valid['field_type_label'] ?? null) === 'text'
        && ($valid['value'] ?? null) === 'Inherited valid title'
        && array_column($valid['field_hierarchy']['path'] ?? [], 'object') === [30, 32],
    'field_values_review_only' => !str_contains($visibleText, 'editor@example.test')
        && !str_contains($visibleText, 'Inherited valid title'),
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
    echo '<td>' . htmlspecialchars(implode(' > ', array_map('strval', $row['path_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widget_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
