<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm child branch boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [12 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile parent label) /TM (profile-root-map) /V (parent@example.test) /DV (default@example.test) /MaxLen 64 /Kids [12 0 R 16 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Editor email label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /Parent 10 0 R /T (secret) /TU (Sibling secret label) /TM (profile.secret.export) /V (sibling-secret@example.test) /Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['profile.email']) {
    throw new RuntimeException('Expected malformed child Fields entry to stay bounded to the referenced branch.');
}
if (isset($fieldsByName['profile.secret'])) {
    throw new RuntimeException('Unlisted sibling AcroForm field must not be imported.');
}
if (($fieldsByName['profile.email']['value'] ?? null) !== 'editor@example.test') {
    throw new RuntimeException('Expected terminal child field value to remain authoritative.');
}
if (array_column($fieldsByName['profile.email']['field_hierarchy']['path'] ?? [], 'object') !== [10, 12]) {
    throw new RuntimeException('Expected parent hierarchy metadata to be preserved for the child field.');
}
if (!str_contains($visibleText, 'Visible AcroForm child branch boundary body')) {
    throw new RuntimeException('Expected page body text to remain visible.');
}
foreach (['editor@example.test', 'parent@example.test', 'sibling-secret@example.test'] as $formValue) {
    if (str_contains($visibleText, $formValue)) {
        throw new RuntimeException('AcroForm values must stay review metadata, not visible WordPress text.');
    }
}

$field = $fieldsByName['profile.email'];
$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];

echo '<!-- markerpdf:pdf-acroform-fields-child-branch-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-child-fields-branch-boundary',
    'native_boundary' => 'Malformed AcroForm /Fields entries that point directly at a child field inherit parent context but traverse only the referenced child branch, excluding unlisted sibling fields before WordPress review.',
    'field_names' => array_keys($fieldsByName),
    'field_object' => $field['object'] ?? null,
    'field_value' => $field['value'] ?? null,
    'field_value_visible_text_exposed' => str_contains($visibleText, 'editor@example.test'),
    'parent_value_visible_text_exposed' => str_contains($visibleText, 'parent@example.test'),
    'sibling_field_imported' => isset($fieldsByName['profile.secret']),
    'sibling_value_visible_text_exposed' => str_contains($visibleText, 'sibling-secret@example.test'),
    'hierarchy_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
    'inherited_attributes' => $field['field_hierarchy']['inherited_attributes'] ?? [],
    'widget_objects' => array_column($widgets, 'object'),
    'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Value</th><th>Hierarchy</th><th>Widgets</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(implode(' > ', array_map('strval', array_column($field['field_hierarchy']['path'] ?? [], 'object'))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(implode(',', array_map('strval', array_column($widgets, 'object'))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
