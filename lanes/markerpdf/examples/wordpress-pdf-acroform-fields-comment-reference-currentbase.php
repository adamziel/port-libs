<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm comment reference body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 % catalog AcroForm reference comment\n0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 % listed widget comment\n0 R 12 % page-only widget comment\n0 R 14 % inline widget comment\n0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields 20 % indirect Fields array comment\n0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT 35 % indirect field type comment\n0 R /T 30 % indirect field name comment\n0 R /TU 31 % indirect alternate label comment\n0 R /TM 32 % indirect mapping name comment\n0 R /V 33 % indirect value comment\n0 R /MaxLen 34 % indirect max length comment\n0 R /Kids 21 % indirect Kids array comment\n0 R >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 % widget Parent reference comment\n0 R /Rect [40 % rect llx comment\n0 R 41 % rect lly comment\n0 R 42 % rect urx comment\n0 R 43 % rect ury comment\n0 R] /P 3 0 R /F 44 % annotation flags comment\n0 R >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (settings) /V (publish) /Opt [(draft) (publish)] /Kids [12 % omitted parent widget comment\n0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 % page widget parent comment\n0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.commentref) /V (Inline comment reference value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n[6 % comment-split Fields entry\n0 R (99 0 R stays literal)]\nendobj\n"
    . "21 0 obj\n[8 % comment-split Kids entry\n0 R]\nendobj\n"
    . "30 0 obj\n(article.commentref)\nendobj\n"
    . "31 0 obj\n(Comment reference label)\nendobj\n"
    . "32 0 obj\n(article.commentref.export)\nendobj\n"
    . "33 0 obj\n(Comment reference value)\nendobj\n"
    . "34 0 obj\n40\nendobj\n"
    . "35 0 obj\n/T#78\nendobj\n"
    . "40 0 obj\n300\nendobj\n"
    . "41 0 obj\n664\nendobj\n"
    . "42 0 obj\n72\nendobj\n"
    . "43 0 obj\n640\nendobj\n"
    . "44 0 obj\n4\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (decoy.literal.reference) /V (Decoy literal value) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['article.commentref', 'settings', 'inline.commentref'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Missing expected AcroForm field {$fieldName}.");
    }
}
if (isset($fieldsByName['decoy.literal.reference'])) {
    throw new RuntimeException('Literal reference text inside /Fields must not become an AcroForm field.');
}

$article = $fieldsByName['article.commentref'];
$articleWidget = $article['widgets'][0] ?? null;
if (!is_array($articleWidget)) {
    throw new RuntimeException('Missing expected comment-reference AcroForm widget.');
}
if (($article['value'] ?? null) !== 'Comment reference value') {
    throw new RuntimeException('Comment-split indirect /V reference was not resolved.');
}
if (($article['mapping_name'] ?? null) !== 'article.commentref.export') {
    throw new RuntimeException('Comment-split indirect /TM reference was not resolved.');
}
if (($articleWidget['rect'] ?? null) !== [72.0, 640.0, 300.0, 664.0]) {
    throw new RuntimeException('Comment-split indirect widget /Rect operands were not resolved.');
}
if (($articleWidget['annotation_flags'] ?? null) !== 4) {
    throw new RuntimeException('Comment-split indirect widget /F operand was not resolved.');
}
if ($visibleText !== 'Visible AcroForm comment reference body') {
    throw new RuntimeException('AcroForm review values leaked into visible WordPress text.');
}

$settings = $fieldsByName['settings'];
$inline = $fieldsByName['inline.commentref'];
$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widgets' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-comment-reference-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-comment-reference-boundary',
    'native_boundary' => 'PDF comments are treated as whitespace inside AcroForm indirect references for /AcroForm, /Fields, /Kids, /Parent, page /Annots, field scalar values, widget /Rect, and widget /F operands.',
    'field_names' => array_column($rows, 'name'),
    'field_count' => count($form['fields']),
    'comment_split_acroform_resolved' => count($form['fields']) === 3,
    'comment_split_fields_array_resolved' => isset($fieldsByName['article.commentref']),
    'comment_split_page_widget_parent_promoted' => isset($fieldsByName['settings'])
        && array_column($settings['widgets'] ?? [], 'object') === [12],
    'comment_split_inline_widget_promoted' => isset($fieldsByName['inline.commentref'])
        && array_column($inline['widgets'] ?? [], 'object') === [14],
    'comment_split_scalar_values_resolved' => ($article['value'] ?? null) === 'Comment reference value'
        && ($article['alternate_name'] ?? null) === 'Comment reference label'
        && ($article['mapping_name'] ?? null) === 'article.commentref.export',
    'comment_split_widget_geometry_resolved' => ($articleWidget['rect'] ?? null) === [72.0, 640.0, 300.0, 664.0],
    'comment_split_widget_flags_resolved' => ($articleWidget['annotation_flags'] ?? null) === 4,
    'literal_reference_decoy_excluded' => !isset($fieldsByName['decoy.literal.reference']),
    'form_values_visible_in_text' => str_contains($visibleText, 'Comment reference value')
        || str_contains($visibleText, 'Inline comment reference value')
        || str_contains($visibleText, 'Decoy literal value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widget objects</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
