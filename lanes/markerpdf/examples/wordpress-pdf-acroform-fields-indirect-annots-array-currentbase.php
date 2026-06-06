<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect Annots array chain body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots 20 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (chain.parent) /TU (Indirect Annots parent label) /TM (chain-parent-export) /V (parent chain value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (wrong.chain.parent) /V (wrong page chain value must not surface) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 40 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n21 0 R\nendobj\n"
    . "21 0 obj\n[8 0 R << /Subtype /Widget /FT /Tx /T (chain.inline) /TU (Inline chain label) /TM (chain-inline-export) /V (direct chain widget value) /Rect [72 600 320 624] /P 3 0 R /F 4 >> 12 0 R]\nendobj\n"
    . "40 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['chain.parent', 'chain.inline'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
}
if (isset($fieldsByName['wrong.chain.parent'])) {
    throw new RuntimeException('Wrong-page widget from indirect Annots chain must not be promoted.');
}

$parent = $fieldsByName['chain.parent'];
$inline = $fieldsByName['chain.inline'];
$parentWidgetObjects = array_column($parent['widgets'] ?? [], 'object');
$inlineWidgetObjects = array_column($inline['widgets'] ?? [], 'object');
$inlineObject = $inline['object'] ?? null;
if ($parentWidgetObjects !== [8]) {
    throw new RuntimeException('Indirect Annots chain did not retain the referenced parent widget.');
}
if (!is_int($inlineObject) || $inlineObject <= 40 || $inlineWidgetObjects !== [$inlineObject]) {
    throw new RuntimeException('Direct Widget inside indirect Annots chain was not materialized as a field/widget.');
}
if (str_contains($visibleText, 'parent chain value') || str_contains($visibleText, 'direct chain widget value')) {
    throw new RuntimeException('AcroForm review values leaked into visible WordPress text.');
}

$summary = [
    'source' => 'native-pdf-acroform-indirect-annots-array-chain-boundary',
    'native_boundary' => 'Page /Annots references may resolve through an indirect array chain before page-owned Widget repair; direct Widget dictionaries in the terminal array are materialized for review, and wrong-page /P widgets remain excluded.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'indirect_annots_chain_resolved' => array_keys($fieldsByName) === ['chain.parent', 'chain.inline'],
    'referenced_parent_widget_repaired' => $parentWidgetObjects === [8]
        && array_column($parent['widgets'] ?? [], 'page_annotation_index') === [0],
    'direct_widget_materialized_from_terminal_array' => is_int($inlineObject)
        && $inlineObject > 40
        && $inlineWidgetObjects === [$inlineObject]
        && array_column($inline['widgets'] ?? [], 'page_annotation_index') === [1],
    'wrong_page_widget_p_excluded' => !isset($fieldsByName['wrong.chain.parent']),
    'review_values_hidden_from_visible_text' => !str_contains($visibleText, 'parent chain value')
        && !str_contains($visibleText, 'direct chain widget value')
        && !str_contains($visibleText, 'wrong page chain value must not surface'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-indirect-annots-array-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Label</th><th>Value</th><th>Widgets</th></tr>\n";
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['alternate_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['value_state']['display_value'] ?? $field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', array_column($widgets, 'object')))
        . '; page annotation indexes ' . implode(',', array_map('strval', array_column($widgets, 'page_annotation_index'))),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
