<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm non-widget subtype boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 20 0 R 22 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 12 0 R 14 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /TU (Article title label) /TM (article-title-export) /V (Accepted article title) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Link /T (link.title.decoy) /V (Link field value must not surface) /A << /S /URI /URI (https://example.test/leak) >> >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Text /T (note.title.decoy) /V (Note annotation value must not surface) /Contents (Sticky note payload must not surface) >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /FreeText /T (freetext.title.decoy) /Kids [16 0 R] /V (FreeText annotation value must not surface) >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx /T (nested.annotation.child.decoy) /V (Nested annotation child value must not surface) >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Link /T (page.link.decoy) /A << /S /URI /URI (https://example.test/page-leak) >> >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.widget) /V (Inline widget value) /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $fieldsByName($form['fields']);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode AcroForm metadata.');
}

if (array_keys($fields) !== ['article.title', 'inline.widget']) {
    throw new RuntimeException('Expected non-widget subtype dictionaries to stay out of AcroForm field review.');
}

foreach ([
    'link.title.decoy',
    'Link field value must not surface',
    'note.title.decoy',
    'Sticky note payload must not surface',
    'freetext.title.decoy',
    'nested.annotation.child.decoy',
    'https://example.test/leak',
    'https://example.test/page-leak',
] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Non-widget subtype decoy leaked into WordPress review: {$decoyText}");
    }
}

foreach (['Accepted article title', 'Inline widget value'] as $reviewOnlyValue) {
    if (str_contains($visibleText, $reviewOnlyValue)) {
        throw new RuntimeException("AcroForm field value leaked into visible WordPress text: {$reviewOnlyValue}");
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
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-nonwidget-subtype-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-nonwidget-subtype-boundary',
    'native_boundary' => 'AcroForm /Fields entries with a non-Widget /Subtype are treated as annotation/XObject-style dictionaries, not form fields; real Widget dictionaries remain review metadata.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'non_widget_subtype_fields_excluded' => !isset($fields['link.title.decoy'])
        && !isset($fields['note.title.decoy'])
        && !isset($fields['freetext.title.decoy'])
        && !isset($fields['nested.annotation.child.decoy']),
    'page_link_annotation_excluded' => !str_contains($encoded, 'page.link.decoy'),
    'inline_widget_preserved' => isset($fields['inline.widget']),
    'visible_text' => $visibleText,
    'form_values_review_only' => !str_contains($visibleText, 'Accepted article title')
        && !str_contains($visibleText, 'Inline widget value'),
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
