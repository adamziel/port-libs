<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect page Kids body) Tj ET';
$staleText = 'BT /F1 12 Tf 72 720 Td (Detached stale AcroForm page body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids 20 0 R /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Contents 31 0 R /Annots [18 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Annots [8 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (current.indirectpage) /TU (Current indirect page label) /TM (current-indirect-page-export) /V (current page form value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 4 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx /T (stale.detachedpage) /TU (Stale detached page label) /TM (stale-detached-page-export) /V (stale detached page value must not import) /Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n[4 0 R]\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (!isset($fieldsByName['current.indirectpage'])) {
    throw new RuntimeException('Missing current AcroForm field discovered through indirect page-tree Kids.');
}
if (isset($fieldsByName['stale.detachedpage'])) {
    throw new RuntimeException('Detached stale page widget must not be promoted into AcroForm review.');
}

$field = $fieldsByName['current.indirectpage'];
$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
$widget = $widgets[0] ?? [];
$indirectKidsResolved = ($widget['page_object'] ?? null) === 4
    && ($widget['page_index'] ?? null) === 0
    && ($widget['page_annotation_index'] ?? null) === 0;
$staleExcluded = !isset($fieldsByName['stale.detachedpage'])
    && !str_contains(json_encode($form, JSON_UNESCAPED_SLASHES) ?: '', 'stale detached page value must not import')
    && !str_contains($visibleText, 'Detached stale AcroForm page body');

echo '<!-- markerpdf:pdf-acroform-fields-page-tree-indirect-kids-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-page-tree-indirect-kids-boundary',
    'native_boundary' => 'Catalog page-tree /Kids indirect arrays are resolved before page Widget annotation repair; detached page-like objects are not scanned as fallback AcroForm sources.',
    'field_names' => array_keys($fieldsByName),
    'current_widget_object' => $widget['object'] ?? null,
    'current_widget_page_object' => $widget['page_object'] ?? null,
    'current_widget_page_index' => $widget['page_index'] ?? null,
    'indirect_page_tree_kids_resolved' => $indirectKidsResolved,
    'detached_stale_page_widget_excluded' => $staleExcluded,
    'form_values_visible_in_text' => str_contains($visibleText, 'current page form value'),
    'stale_page_text_visible' => str_contains($visibleText, 'Detached stale AcroForm page body'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widget page</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['field_type_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['value_state']['display_value'] ?? $field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars('page object ' . (string) ($widget['page_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
