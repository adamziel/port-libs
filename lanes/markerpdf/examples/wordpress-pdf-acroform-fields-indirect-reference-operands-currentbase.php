<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect reference operand body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [80 0 R 81 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [40 0 R 41 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (indirect.ref.email) /TU (Indirect ref label) /TM (indirect-ref-export) /V (indirect-ref@example.test) /DV (draft-indirect-ref@example.test) /MaxLen 80 /Kids [50 0 R 51 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 60 0 R /Rect [72 640 320 664] /P 70 0 R /F 4 >>\nendobj\n"
    . "40 0 obj\n6 0 R\nendobj\n"
    . "41 0 obj\n98 0 R 6 0 R\nendobj\n"
    . "50 0 obj\n8 0 R\nendobj\n"
    . "51 0 obj\n99 0 R 8 0 R\nendobj\n"
    . "60 0 obj\n6 0 R\nendobj\n"
    . "70 0 obj\n3 0 R\nendobj\n"
    . "80 0 obj\n8 0 R\nendobj\n"
    . "81 0 obj\n90 0 R 8 0 R\nendobj\n"
    . "90 0 obj\n<< /Subtype /Widget /FT /Tx /T (tailed.annots.decoy) /V (Tailed Annots decoy value) /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "98 0 obj\n<< /FT /Tx /T (tailed.fields.decoy) /V (Tailed Fields reference value) >>\nendobj\n"
    . "99 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$field = $fieldsByName['indirect.ref.email'] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected indirect AcroForm field reference wrapper to resolve to the field dictionary.');
}

$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
if (array_column($widgets, 'object') !== [8]) {
    throw new RuntimeException('Expected indirect Kids reference wrapper to resolve to widget object 8.');
}
if (array_column($widgets, 'page_object') !== [3] || array_column($widgets, 'page_annotation_index') !== [0]) {
    throw new RuntimeException('Expected indirect widget /P and page /Annots reference wrappers to preserve page annotation metadata.');
}

$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);
foreach (['tailed.fields.decoy', 'Tailed Fields reference value', 'tailed.annots.decoy', 'Tailed Annots decoy value'] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Tailed AcroForm reference-object decoy leaked into WordPress review metadata: {$decoyText}");
    }
}
foreach (['indirect-ref@example.test', 'draft-indirect-ref@example.test', 'Indirect ref label'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm field review text leaked into visible WordPress paragraphs: {$reviewOnlyText}");
    }
}

$rows = [[
    'name' => $field['name'] ?? null,
    'type' => $field['field_type_label'] ?? null,
    'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
    'widget_objects' => array_column($widgets, 'object'),
    'page_objects' => array_column($widgets, 'page_object'),
    'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
]];

echo '<!-- markerpdf:pdf-acroform-fields-indirect-reference-operands-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-indirect-reference-operands',
    'native_boundary' => 'Pure indirect-reference wrapper objects inside /Fields, /Kids, page /Annots, widget /Parent, and widget /P resolve before AcroForm page-widget repair; tailed reference wrappers fail closed and field values stay review-only',
    'field_names' => array_column($rows, 'name'),
    'fields_reference_wrapper_resolved' => isset($fieldsByName['indirect.ref.email']),
    'kids_reference_wrapper_resolved' => array_column($widgets, 'object') === [8],
    'widget_parent_reference_wrapper_resolved' => array_column($widgets, 'object') === [8] && ($field['object'] ?? null) === 6,
    'widget_page_reference_wrapper_resolved' => array_column($widgets, 'page_object') === [3],
    'page_annots_reference_wrapper_resolved' => array_column($widgets, 'page_annotation_index') === [0],
    'tailed_reference_wrappers_excluded' => !str_contains($encoded, 'tailed.fields.decoy') && !str_contains($encoded, 'tailed.annots.decoy'),
    'field_values_review_only' => !str_contains($visibleText, 'indirect-ref@example.test') && !str_contains($visibleText, 'Indirect ref label'),
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
        'objects ' . implode(',', array_map('strval', $row['widget_objects']))
            . '; pages ' . implode(',', array_map('strval', $row['page_objects']))
            . '; annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
