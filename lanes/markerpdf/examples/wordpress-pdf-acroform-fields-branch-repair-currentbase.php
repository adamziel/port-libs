<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm sibling branch repair body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 18 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [12 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile group label) /TM (profile.group.map) /V (parent@example.test) /DV (draft@example.test) /MaxLen 64 /Kids [12 0 R 16 0 R 20 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Email label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /Parent 10 0 R /T (status) /TU (Status label) /TM (profile.status.export) /V (publish) /Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /Parent 10 0 R /T (secret) /TU (Secret label must not surface) /TM (profile.secret.export) /V (private secret value must not surface) /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['profile.email', 'profile.status']) {
    throw new RuntimeException('Expected only the listed child branch and page-owned sibling branch.');
}
if (isset($fieldsByName['profile.secret'])) {
    throw new RuntimeException('Detached sibling branch must not be imported through the parent root.');
}
if (count(array_filter($form['fields'], static fn (array $field): bool => ($field['name'] ?? null) === 'profile.email')) !== 1) {
    throw new RuntimeException('Listed child branch must not be duplicated when a sibling page widget is repaired.');
}
if (str_contains($visibleText, 'editor@example.test') || str_contains($visibleText, 'publish') || str_contains($visibleText, 'private secret value must not surface')) {
    throw new RuntimeException('AcroForm values must stay out of visible WordPress text.');
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

echo '<!-- markerpdf:pdf-acroform-fields-branch-repair-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-fields-branch-repair-currentbase',
    'native_boundary' => 'AcroForm page-widget repair promotes only the widget parent field branch; verified ancestors may donate inherited names/defaults, but the parent root is not imported wholesale.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'listed_child_branch_preserved_once' => count(array_filter($form['fields'], static fn (array $field): bool => ($field['name'] ?? null) === 'profile.email')) === 1,
    'page_owned_sibling_branch_promoted' => isset($fieldsByName['profile.status'])
        && array_column($fieldsByName['profile.status']['widgets'] ?? [], 'object') === [18],
    'detached_sibling_branch_excluded' => !isset($fieldsByName['profile.secret']),
    'parent_root_not_imported_wholesale' => !isset($fieldsByName['profile']),
    'values_visible_in_text' => str_contains($visibleText, 'editor@example.test')
        || str_contains($visibleText, 'publish')
        || str_contains($visibleText, 'private secret value must not surface'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widget_objects']))
        . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
