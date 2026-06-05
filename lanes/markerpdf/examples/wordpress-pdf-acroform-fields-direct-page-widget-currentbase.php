<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneText = 'BT /F1 12 Tf 72 720 Td (Visible direct page widget WordPress page one body) Tj ET';
$pageTwoText = 'BT /F1 12 Tf 72 720 Td (Visible direct page widget WordPress page two body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 40 0 R /Annots [\n"
    . "<< /Subtype /Widget /FT /Tx /T (direct.inline) /TU (Direct inline label) /TM (direct-inline-export) /V (Direct inline value) /DV (Direct inline default) /Rect [72 640 320 664] /P 3 0 R /F 4 /DA (/Helv 8 Tf 0 0 1 rg) >>\n"
    . "<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\n"
    . "<< /Subtype /Widget /Parent 12 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\n"
    . "<< /Subtype /Widget /FT /Tx /T (wrongpage.direct) /V (Wrong page direct value must not surface) /Rect [72 520 320 544] /P 4 0 R /F 4 >>\n"
    . "<< /Subtype /Text /FT /Tx /T (text.annotation.decoy) /V (Text annotation decoy value) /Rect [72 480 320 504] /P 3 0 R /F 4 >>\n"
    . "] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 41 0 R /Annots [\n"
    . "<< /Subtype /Widget /Parent 18 0 R /Rect [72 640 280 664] /P 4 0 R /F 4 >>\n"
    . "] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (parent.direct) /TU (Direct parent label) /TM (direct-parent-export) /V (Direct parent value) /DV (Direct parent default) /MaxLen 48 >>\nendobj\n"
    . "12 0 obj\n<< /FT /Tx /T (emptykids.direct) /TU (Explicit empty Kids direct label) /TM (emptykids-direct-export) /V (Explicit empty Kids direct value) /Kids [] >>\nendobj\n"
    . "18 0 obj\n<< /FT /Ch /T (second.direct) /TU (Second direct status label) /TM (second-direct-export) /V (published) /Opt [(draft) (published)] >>\nendobj\n"
    . "40 0 obj\n<< /Length " . strlen($pageOneText) . " >>\nstream\n{$pageOneText}\nendstream\nendobj\n"
    . "41 0 obj\n<< /Length " . strlen($pageTwoText) . " >>\nstream\n{$pageTwoText}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['direct.inline', 'parent.direct', 'second.direct'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Missing expected direct page Widget field {$fieldName}.");
    }
}

foreach (['wrongpage.direct', 'emptykids.direct', 'text.annotation.decoy'] as $decoyName) {
    if (isset($fieldsByName[$decoyName])) {
        throw new RuntimeException("Direct page Widget decoy {$decoyName} must not be promoted.");
    }
}

$inline = $fieldsByName['direct.inline'];
if (($inline['value'] ?? null) !== 'Direct inline value' || ($inline['widgets'][0]['page_annotation_index'] ?? null) !== 0) {
    throw new RuntimeException('Direct inline page Widget field was not materialized for review.');
}
if (($inline['field_name_review']['field_value_used_as_visible_text'] ?? null) !== false) {
    throw new RuntimeException('Direct inline page Widget value must stay out of visible WordPress text.');
}

$parent = $fieldsByName['parent.direct'];
if (($parent['object'] ?? null) !== 10 || array_column($parent['widgets'] ?? [], 'page_annotation_index') !== [1]) {
    throw new RuntimeException('Direct page Widget /Parent repair did not preserve the parent field and page annotation index.');
}
if (($parent['max_length'] ?? null) !== 48 || ($parent['mapping_name'] ?? null) !== 'direct-parent-export') {
    throw new RuntimeException('Direct page Widget /Parent review metadata was not preserved.');
}

$second = $fieldsByName['second.direct'];
if (($second['object'] ?? null) !== 18 || array_column($second['widgets'] ?? [], 'page_object') !== [4]) {
    throw new RuntimeException('Direct page Widget /P matching on the second page was not preserved.');
}

foreach ([
    'Direct inline value',
    'Direct parent value',
    'published',
    'Wrong page direct value must not surface',
    'Explicit empty Kids direct value',
    'Text annotation decoy value',
] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review text leaked into visible WordPress text: {$reviewOnlyText}");
    }
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
        'page_objects' => array_column($widgets, 'page_object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-direct-page-widget-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-direct-widget-acroform-boundary',
    'native_boundary' => 'Direct top-level Widget dictionaries in page Annots arrays are materialized into in-memory review objects before AcroForm field repair; wrong-page /P widgets, non-Widget annotations, and explicit empty /Kids parents remain excluded.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'direct_inline_widget_field' => 'direct.inline',
    'direct_parent_widget_field' => 'parent.direct',
    'second_page_widget_field' => 'second.direct',
    'excluded_decoy_fields' => ['wrongpage.direct', 'emptykids.direct', 'text.annotation.decoy'],
    'wrong_page_widget_p_excluded' => !isset($fieldsByName['wrongpage.direct']),
    'explicit_empty_kids_parent_excluded' => !isset($fieldsByName['emptykids.direct']),
    'text_annotation_decoy_excluded' => !isset($fieldsByName['text.annotation.decoy']),
    'visible_text' => $visibleText,
    'field_rows' => $rows,
    'form_values_used_as_visible_text' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES) . " -->\n";
