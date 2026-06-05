<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm page widget boundary body) Tj ET';
$secondPageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget P second page boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 41 0 R /Annots [8 0 R (90 0 R) [91 0 R] << /Nested 92 0 R >> % 93 0 R stays a comment\n12 0 R 14 0 R 18 0 R 24 0 R 110 0 R 120 0 R 124 0 R 132 0 R 134 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 42 0 R /Annots [150 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 23 0 R 122 0 R 124 0 R (94 0 R) [95 0 R] << /Nested 96 0 R >> % 97 0 R stays a comment\n] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR << /Font << /Helv 40 0 R >> >> >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (listed.email) /V (listed@example.test) /Kids [8 0 R (98 0 R) [99 0 R] << /Nested 100 0 R >> % 101 0 R stays a comment\n112 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (omitted.category) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.note) /V (inline page widget value) /Rect [72 560 320 584] /P 3 0 R /F 4 /DA (/Helv 8 Tf 0 0 1 rg) >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx /T (indirect.geometry) /V (indirect geometry value) /Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [50 0 R 51 0 R 52 0 R 53 0 R] /P 3 0 R /F 54 0 R >>\nendobj\n"
    . "20 0 obj\n<< /FT /Tx /T (detached.secret) /V (detached widget value must not surface) /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
    . "23 0 obj\n<< /FT /Tx /T (review) /TU (Parent review label stays ancestor metadata) /TM (review-parent-map) /V (Parent review value) /Kids [24 0 R] >>\nendobj\n"
    . "24 0 obj\n<< /Subtype /Widget /Parent 23 0 R /T (label) /TU (Review label for editors) /TM (review.label.export) /V (Mapped label value) /Rect [72 480 320 504] /P 3 0 R /F 4 >>\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "50 0 obj\n360\nendobj\n"
    . "51 0 obj\n544\nendobj\n"
    . "52 0 obj\n72\nendobj\n"
    . "53 0 obj\n520\nendobj\n"
    . "54 0 obj\n4\nendobj\n"
    . "90 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.literal) /V (Annots literal decoy) /Rect [72 500 320 524] /P 3 0 R /F 4 >>\nendobj\n"
    . "91 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.nested_array) /V (Annots nested array decoy) /Rect [72 460 320 484] /P 3 0 R /F 4 >>\nendobj\n"
    . "92 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.nested_dict) /V (Annots nested dictionary decoy) /Rect [72 420 320 444] /P 3 0 R /F 4 >>\nendobj\n"
    . "93 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.comment) /V (Annots comment decoy) /Rect [72 380 320 404] /P 3 0 R /F 4 >>\nendobj\n"
    . "94 0 obj\n<< /FT /Tx /T (decoy.fields.literal) /V (Fields literal decoy) >>\nendobj\n"
    . "95 0 obj\n<< /FT /Tx /T (decoy.fields.nested_array) /V (Fields nested array decoy) >>\nendobj\n"
    . "96 0 obj\n<< /FT /Tx /T (decoy.fields.nested_dict) /V (Fields nested dictionary decoy) >>\nendobj\n"
    . "97 0 obj\n<< /FT /Tx /T (decoy.fields.comment) /V (Fields comment decoy) >>\nendobj\n"
    . "98 0 obj\n<< /FT /Tx /T (decoy.kids.literal) /V (Kids literal decoy) >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (decoy.kids.nested_array) /V (Kids nested array decoy) >>\nendobj\n"
    . "100 0 obj\n<< /FT /Tx /T (decoy.kids.nested_dict) /V (Kids nested dictionary decoy) >>\nendobj\n"
    . "101 0 obj\n<< /FT /Tx /T (decoy.kids.comment) /V (Kids comment decoy) >>\nendobj\n"
    . "110 0 obj\n<< /Type /Annot\n% /Subtype /Widget should not promote this text annotation into an AcroForm field\n/Subtype /Text /FT /Tx /T (decoy.comment_subtype.promoted) /V (Comment subtype page decoy) /Rect [72 340 320 364] /P 3 0 R /F 4 /Contents (Comment page widget marker decoy) >>\nendobj\n"
    . "112 0 obj\n<< /Type /Annot\n% /Subtype /Widget /Parent 6 0 R stays a comment-only child widget marker\n/Subtype /Text /Rect [72 300 320 324] /P 3 0 R /F 4 /Contents (Comment child widget marker decoy) >>\nendobj\n"
    . "118 0 obj\n<< /FT /Tx /T (childroot) /TU (Child root parent label) /TM (childroot-parent-map) /V (parent childroot review) /DV (default childroot value) /MaxLen 72 /Kids [122 0 R] >>\nendobj\n"
    . "120 0 obj\n<< /Subtype /Widget /Parent 122 0 R /Rect [72 260 320 284] /P 3 0 R /F 4 >>\nendobj\n"
    . "122 0 obj\n<< /Parent 118 0 R /T (email) /TU (Child root editor email) /TM (childroot.email.export) /V (childroot@example.test) /Kids [120 0 R] >>\nendobj\n"
    . "124 0 obj\n<< /Subtype /Widget /Parent 126 0 R /T (status) /TU (Workflow status label) /TM (workflow.status.export) /V (publish) /Rect [72 220 280 244] /P 3 0 R /F 4 >>\nendobj\n"
    . "126 0 obj\n<< /FT /Ch /T (workflow) /TU (Workflow parent label) /V (draft) /Opt [(draft) (publish)] /Kids [124 0 R] >>\nendobj\n"
    . "130 0 obj\n<< /FT /Tx /T (wrongpage.parent) /V (Wrong page parent value must not surface) /Kids [132 0 R] >>\nendobj\n"
    . "132 0 obj\n<< /Subtype /Widget /Parent 130 0 R /Rect [72 180 320 204] /P 4 0 R /F 4 >>\nendobj\n"
    . "134 0 obj\n<< /Subtype /Widget /FT /Tx /T (wrongpage.inline) /V (Wrong page inline value must not surface) /Rect [72 140 320 164] /P 4 0 R /F 4 >>\nendobj\n"
    . "148 0 obj\n<< /FT /Ch /T (second.page.status) /V (published) /Opt [(draft) (published)] /Kids [150 0 R] >>\nendobj\n"
    . "150 0 obj\n<< /Subtype /Widget /Parent 148 0 R /Rect [72 640 280 664] /P 4 0 R /F 4 >>\nendobj\n"
    . "41 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "42 0 obj\n<< /Length " . strlen($secondPageText) . " >>\nstream\n{$secondPageText}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['listed.email', 'omitted.category', 'inline.note', 'indirect.geometry', 'review.label', 'childroot.email', 'workflow.status', 'second.page.status'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
}
if (isset($fieldsByName['detached.secret'])) {
    throw new RuntimeException('Detached widget field must not be promoted into the AcroForm review.');
}
foreach (['wrongpage.parent', 'wrongpage.inline'] as $wrongPageName) {
    if (isset($fieldsByName[$wrongPageName])) {
        throw new RuntimeException("Wrong-page widget /P field {$wrongPageName} must not be promoted from this page annotation list.");
    }
}

$indirectWidget = $fieldsByName['indirect.geometry']['widgets'][0] ?? null;
if (!is_array($indirectWidget)) {
    throw new RuntimeException('Missing expected indirect-operand AcroForm widget.');
}
if (($indirectWidget['rect'] ?? null) !== [72.0, 520.0, 360.0, 544.0]) {
    throw new RuntimeException('Indirect AcroForm widget Rect operands were not resolved.');
}
if (($indirectWidget['annotation_flags'] ?? null) !== 4 || ($indirectWidget['annotation_visibility'] ?? null) !== 'visible') {
    throw new RuntimeException('Indirect AcroForm widget annotation flags were not resolved.');
}

$reviewLabel = $fieldsByName['review.label'];
$fieldNameReview = is_array($reviewLabel['field_name_review'] ?? null) ? $reviewLabel['field_name_review'] : [];
if (($reviewLabel['alternate_name'] ?? null) !== 'Review label for editors') {
    throw new RuntimeException('AcroForm /TU alternate name was not preserved for WordPress review.');
}
if (($reviewLabel['mapping_name'] ?? null) !== 'review.label.export') {
    throw new RuntimeException('AcroForm /TM mapping name was not preserved for WordPress review.');
}
if (($fieldNameReview['wordpress_label'] ?? null) !== 'Review label for editors') {
    throw new RuntimeException('AcroForm field name review did not choose the alternate label.');
}
if (($fieldNameReview['alternate_name_used_as_visible_text'] ?? null) !== false || str_contains($visibleText, 'Review label for editors')) {
    throw new RuntimeException('AcroForm alternate names must stay out of visible WordPress text.');
}

$decoyNames = [
    'decoy.annots.literal',
    'decoy.annots.nested_array',
    'decoy.annots.nested_dict',
    'decoy.annots.comment',
    'decoy.fields.literal',
    'decoy.fields.nested_array',
    'decoy.fields.nested_dict',
    'decoy.fields.comment',
    'decoy.kids.literal',
    'decoy.kids.nested_array',
    'decoy.kids.nested_dict',
    'decoy.kids.comment',
    'decoy.comment_subtype.promoted',
];
foreach ($decoyNames as $decoyName) {
    if (isset($fieldsByName[$decoyName])) {
        throw new RuntimeException("AcroForm array decoy field {$decoyName} must not be promoted.");
    }
}
if (in_array(112, array_column($fieldsByName['listed.email']['widgets'] ?? [], 'object'), true)) {
    throw new RuntimeException('Comment-only child Widget subtype marker must not attach as an AcroForm widget.');
}

$childRoot = $fieldsByName['childroot.email'];
if (($childRoot['object'] ?? null) !== 122 || ($childRoot['field_type_label'] ?? null) !== 'text') {
    throw new RuntimeException('Child AcroForm Fields entries must normalize to their parent field root.');
}
if (($childRoot['name'] ?? null) !== 'childroot.email' || ($childRoot['value'] ?? null) !== 'childroot@example.test') {
    throw new RuntimeException('Child AcroForm field root normalization lost the qualified name or terminal value.');
}
if (array_column($childRoot['field_hierarchy']['path'] ?? [], 'object') !== [118, 122]) {
    throw new RuntimeException('Child AcroForm field root hierarchy was not preserved.');
}

$workflowStatus = $fieldsByName['workflow.status'];
if (($workflowStatus['object'] ?? null) !== 124 || ($workflowStatus['field_type_label'] ?? null) !== 'choice') {
    throw new RuntimeException('Merged Widget AcroForm Fields entries must normalize to their parent field root.');
}
if (($workflowStatus['name'] ?? null) !== 'workflow.status' || ($workflowStatus['value'] ?? null) !== 'publish') {
    throw new RuntimeException('Merged Widget AcroForm field root normalization lost the qualified name or terminal value.');
}
if (array_column($workflowStatus['field_hierarchy']['path'] ?? [], 'object') !== [126, 124]) {
    throw new RuntimeException('Merged Widget AcroForm field hierarchy was not preserved.');
}

$secondPageStatus = $fieldsByName['second.page.status'];
if (($secondPageStatus['object'] ?? null) !== 148 || array_column($secondPageStatus['widgets'] ?? [], 'page_object') !== [4]) {
    throw new RuntimeException('Widget /P references that match their listing page must remain page-owned form fields.');
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
        'page_referenced_widgets' => count(array_filter(
            $widgets,
            static fn (array $widget): bool => ($widget['referenced_from_page_annots'] ?? false) === true
        )),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-page-widget-boundary',
    'native_boundary' => 'Page-owned Widget annotations and their Parent fields are reviewed when malformed AcroForm Fields omits them; explicit Widget /P references must match the listing page; comment-only Widget subtype markers and AcroForm alternate/mapping names stay review-only',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'promoted_page_widget_parent_fields' => ['omitted.category'],
    'promoted_standalone_widget_fields' => ['inline.note'],
    'wrong_page_widget_p_references_excluded' => !isset($fieldsByName['wrongpage.parent']) && !isset($fieldsByName['wrongpage.inline']),
    'wrong_page_decoy_names' => ['wrongpage.parent', 'wrongpage.inline'],
    'matching_widget_p_second_page_preserved' => ($secondPageStatus['object'] ?? null) === 148
        && array_column($secondPageStatus['widgets'] ?? [], 'page_object') === [4]
        && array_column($secondPageStatus['widgets'] ?? [], 'page_index') === [1],
    'alternate_name_review_field' => $reviewLabel['alternate_name'] ?? null,
    'mapping_name_review_field' => $reviewLabel['mapping_name'] ?? null,
    'wordpress_label_from_alternate_name' => $fieldNameReview['wordpress_label'] ?? null,
    'alternate_mapping_names_review_only' => ($fieldNameReview['alternate_name_used_as_visible_text'] ?? null) === false
        && ($fieldNameReview['mapping_name_used_as_visible_text'] ?? null) === false
        && !str_contains($visibleText, 'Review label for editors')
        && !str_contains($visibleText, 'review.label.export')
        && !str_contains($visibleText, 'Mapped label value'),
    'indirect_widget_rect_resolved' => ($indirectWidget['rect'] ?? null) === [72.0, 520.0, 360.0, 544.0],
    'indirect_widget_flags_resolved' => ($indirectWidget['annotation_flags'] ?? null) === 4,
    'indirect_widget_visibility' => $indirectWidget['annotation_visibility'] ?? null,
    'array_decoy_fields_excluded' => count(array_intersect($decoyNames, array_keys($fieldsByName))) === 0,
    'array_decoy_sources' => ['annots_literal_nested_comment', 'fields_literal_nested_comment', 'kids_literal_nested_comment'],
    'comment_widget_subtype_decoys_excluded' => !isset($fieldsByName['decoy.comment_subtype.promoted'])
        && !in_array(112, array_column($fieldsByName['listed.email']['widgets'] ?? [], 'object'), true),
    'child_field_entries_normalized_to_parent_roots' => ($childRoot['name'] ?? null) === 'childroot.email'
        && array_column($childRoot['field_hierarchy']['path'] ?? [], 'object') === [118, 122]
        && ($childRoot['field_type_label'] ?? null) === 'text',
    'merged_widget_field_entries_normalized_to_parent_roots' => ($workflowStatus['name'] ?? null) === 'workflow.status'
        && array_column($workflowStatus['field_hierarchy']['path'] ?? [], 'object') === [126, 124]
        && ($workflowStatus['field_type_label'] ?? null) === 'choice',
    'detached_widget_excluded' => !isset($fieldsByName['detached.secret']),
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
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])) . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
