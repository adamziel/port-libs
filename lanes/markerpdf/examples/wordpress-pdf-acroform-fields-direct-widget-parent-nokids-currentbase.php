<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct widget parent no Kids body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [8 0 R 12 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (direct.nokids) /TU (Direct widget parent without Kids label) /TM (direct-nokids-export) /V (direct no-kids value) /DV (direct no-kids default) /MaxLen 48 >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (direct.emptykids) /TU (Explicit empty Kids decoy label) /TM (direct-emptykids-export) /V (explicit empty Kids direct decoy value) /Kids [] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (direct.mismatch) /TU (Mismatched Kids decoy label) /TM (direct-mismatch-export) /V (mismatched Kids direct decoy value) /Kids [18 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (!isset($fieldsByName['direct.nokids'])) {
    throw new RuntimeException('Direct Widget /Fields entry did not normalize to its parent field without /Kids.');
}
foreach (['direct.emptykids', 'direct.mismatch'] as $decoyName) {
    if (isset($fieldsByName[$decoyName])) {
        throw new RuntimeException("Explicit bad /Kids decoy {$decoyName} must stay excluded from AcroForm review.");
    }
}

$field = $fieldsByName['direct.nokids'];
$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
if (($field['object'] ?? null) !== 6 || array_column($widgets, 'object') !== [8]) {
    throw new RuntimeException('Direct Widget /Fields normalization lost the parent field object or widget reference.');
}
if (str_contains($visibleText, 'direct no-kids value') || str_contains($visibleText, 'Direct widget parent without Kids label')) {
    throw new RuntimeException('AcroForm field values and labels must remain review metadata, not visible WordPress text.');
}

$rows = [[
    'name' => $field['name'] ?? null,
    'label' => $field['field_name_review']['wordpress_label'] ?? $field['alternate_name'] ?? $field['name'] ?? null,
    'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
    'field_object' => $field['object'] ?? null,
    'widget_objects' => array_column($widgets, 'object'),
    'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
]];

echo '<!-- markerpdf:pdf-acroform-fields-direct-widget-parent-nokids-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-direct-widget-parent-nokids-boundary',
    'native_boundary' => 'Pure Widget references listed directly in AcroForm Fields normalize to their Parent field when that field omits /Kids, while explicit empty or mismatched /Kids stays authoritative and excludes decoys.',
    'field_names' => array_keys($fieldsByName),
    'field_objects' => array_column($rows, 'field_object'),
    'widget_objects' => array_map(static fn (array $row): array => $row['widget_objects'], $rows),
    'page_annotation_indexes' => array_map(static fn (array $row): array => $row['page_annotation_indexes'], $rows),
    'direct_widget_parent_without_kids_normalized' => ($field['object'] ?? null) === 6
        && array_column($widgets, 'object') === [8]
        && array_column($widgets, 'page_annotation_index') === [0],
    'explicit_empty_kids_parent_excluded' => !isset($fieldsByName['direct.emptykids']),
    'explicit_mismatched_kids_parent_excluded' => !isset($fieldsByName['direct.mismatch']),
    'form_values_visible_in_text' => str_contains($visibleText, 'direct no-kids value')
        || str_contains($visibleText, 'direct no-kids default'),
    'need_appearances' => $form['need_appearances'],
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Label</th><th>Value</th><th>Widget</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'field object ' . (string) $row['field_object'] . '; widget object ' . implode(',', array_map('strval', $row['widget_objects'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
