<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm parent child overlap boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile label) /TM (profile.export) /V (Parent current review only) /DV (Parent default review only) /MaxLen 64 /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Parent 6 0 R /T (email) /TU (Email label) /TM (profile.email.export) /V (editor@example.test) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /FT /Tx /T (detached.duplicate.decoy) /V (Detached duplicate decoy) /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['profile.email']) {
    throw new RuntimeException('Expected one deduplicated AcroForm review field.');
}
if (($fieldsByName['profile.email']['value'] ?? null) !== 'editor@example.test') {
    throw new RuntimeException('Expected terminal AcroForm value to remain authoritative.');
}
if (str_contains($visibleText, 'editor@example.test') || str_contains($visibleText, 'Parent current review only')) {
    throw new RuntimeException('AcroForm values must remain review metadata, not visible paragraph text.');
}

$field = $fieldsByName['profile.email'];
$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
$summary = [
    'source' => 'native-pdf-acroform-parent-child-overlap-boundary',
    'native_boundary' => 'Overlapping AcroForm /Fields entries that name both a parent and the same owned child are collapsed to one terminal WordPress review row.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'terminal_object' => $field['object'] ?? null,
    'path_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
    'widget_objects' => array_column($widgets, 'object'),
    'terminal_value_authoritative' => ($field['value'] ?? null) === 'editor@example.test',
    'parent_default_inherited_for_review' => ($field['default_value'] ?? null) === 'Parent default review only',
    'visible_text_contains_form_value' => str_contains($visibleText, 'editor@example.test'),
    'duplicate_review_rows_removed' => count($form['fields']) === 1,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-overlap-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Object</th><th>Value</th><th>Hierarchy</th><th>Widgets</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['value_state']['display_value'] ?? $field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(implode(' > ', array_map('strval', $summary['path_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $summary['widget_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
