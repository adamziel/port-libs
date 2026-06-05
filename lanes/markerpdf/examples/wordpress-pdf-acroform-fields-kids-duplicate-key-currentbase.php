<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate AcroForm Kids body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [10 0 R 14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile label) /TM (profile.export) /V (stale parent value) /DV (draft parent value) /Kids [30 0 R] /Kids [8 0 R 12 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Parent 6 0 R /T (email) /TU (Editor email label) /TM (profile.email.export) /V (editor@example.test) /Kids [32 0 R] /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /Parent 6 0 R /T (status) /FT /Ch /TU (Status label) /TM (profile.status.export) /V (publish) /Opt [(draft) (publish)] /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 280 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Parent 6 0 R /T (secret) /TU (Stale secret label) /TM (profile.secret.export) /V (stale-secret@example.test) /Kids [34 0 R] >>\nendobj\n"
    . "32 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
    . "34 0 obj\n<< /Subtype /Widget /Parent 30 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['profile.email', 'profile.status'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
}
foreach (['profile.secret', 'Stale secret label', 'profile.secret.export', 'stale-secret@example.test'] as $decoyText) {
    if (isset($fieldsByName[$decoyText]) || str_contains(json_encode($form, JSON_UNESCAPED_SLASHES) ?: '', $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Stale duplicate /Kids AcroForm branch leaked into review metadata: {$decoyText}");
    }
}
if (in_array(32, array_column($fieldsByName['profile.email']['widgets'] ?? [], 'object'), true)) {
    throw new RuntimeException('First duplicate /Kids widget decoy must not attach to the current email field.');
}

$email = $fieldsByName['profile.email'];
$status = $fieldsByName['profile.status'];
$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'path_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
        'widget_objects' => array_column($widgets, 'object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-kids-duplicate-key-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-last-kids-key-boundary',
    'native_boundary' => 'Duplicate field /Kids keys use the last top-level value before WordPress AcroForm review, so stale first-branch fields and detached first-branch widgets stay excluded.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'last_kids_branch_selected' => array_keys($fieldsByName) === ['profile.email', 'profile.status'],
    'stale_first_kids_branch_excluded' => !isset($fieldsByName['profile.secret']),
    'first_kids_widget_decoy_excluded' => !in_array(32, array_column($email['widgets'] ?? [], 'object'), true),
    'email_widget_objects' => array_column($email['widgets'] ?? [], 'object'),
    'status_widget_objects' => array_column($status['widgets'] ?? [], 'object'),
    'field_values_review_only' => !str_contains($visibleText, 'editor@example.test')
        && !str_contains($visibleText, 'draft parent value')
        && !str_contains($visibleText, 'publish'),
    'visible_text' => $visibleText,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Hierarchy</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(' > ', array_map('strval', $row['path_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])) . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
