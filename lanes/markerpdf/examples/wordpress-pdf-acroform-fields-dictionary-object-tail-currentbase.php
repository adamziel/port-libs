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

$fieldTailText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm field dictionary object tail body) Tj ET';
$fieldTailPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($fieldTailText) . " >>\nstream\n{$fieldTailText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (tailed.field.object) /TU (Tailed field label) /TM (tailed-field-export) /V (Tailed field value must not surface) /Kids [8 0 R] >> << /FT /Tx /T (tailed.field.sibling.decoy) /V (Sibling field decoy value) >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (valid.page.repair) /TU (Valid page repair label) /TM (valid-page-repair-export) /V (Valid page repair value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$widgetTailText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget dictionary object tail body) Tj ET';
$widgetTailPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($widgetTailText) . " >>\nstream\n{$widgetTailText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (tailed.widget.parent) /TU (Tailed widget parent label) /TM (tailed-widget-parent-export) /V (Tailed widget parent value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >> 99 0 R\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (valid.comment.widget) /TU (Valid comment widget label) /TM (valid-comment-widget-export) /V (Valid comment widget value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >> % comment-only widget tail\nendobj\n"
    . "99 0 obj\n<< /Subtype /Widget /Parent 6 0 R /FT /Tx /T (tailed.widget.sibling.decoy) /V (Sibling widget decoy value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfAcroFormExtractor();
$textExtractor = new PdfTextExtractor();
$fieldTailForm = $extractor->extractForm($fieldTailPdf);
$widgetTailForm = $extractor->extractForm($widgetTailPdf);
$fieldTailFields = $fieldsByName($fieldTailForm['fields']);
$widgetTailFields = $fieldsByName($widgetTailForm['fields']);
$fieldTailTextOutput = $textExtractor->extractPlainText($fieldTailPdf);
$widgetTailTextOutput = $textExtractor->extractPlainText($widgetTailPdf);
$encoded = json_encode([$fieldTailForm, $widgetTailForm], JSON_UNESCAPED_SLASHES);

if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode AcroForm dictionary-tail review output.');
}
if (array_keys($fieldTailFields) !== ['valid.page.repair']) {
    throw new RuntimeException('Tailed field dictionary object must fail closed before page-widget repair.');
}
if (array_keys($widgetTailFields) !== ['tailed.widget.parent', 'valid.comment.widget']) {
    throw new RuntimeException('Tailed widget object handling did not preserve the expected parent/comment-tail fields.');
}
if (($widgetTailFields['tailed.widget.parent']['widgets'] ?? null) !== []) {
    throw new RuntimeException('Tailed widget dictionary object must not attach to its parent field.');
}

foreach ([
    'tailed.field.object',
    'Tailed field value must not surface',
    'tailed.field.sibling.decoy',
    'Sibling field decoy value',
    'tailed.widget.sibling.decoy',
] as $decoy) {
    if (str_contains($encoded, $decoy) || str_contains($fieldTailTextOutput, $decoy) || str_contains($widgetTailTextOutput, $decoy)) {
        throw new RuntimeException("Malformed AcroForm dictionary-tail decoy leaked: {$decoy}");
    }
}
foreach ([
    'Valid page repair value',
    'Valid page repair label',
    'Tailed widget parent value',
    'Tailed widget parent label',
    'Valid comment widget value',
    'Valid comment widget label',
] as $reviewOnly) {
    if (str_contains($fieldTailTextOutput, $reviewOnly) || str_contains($widgetTailTextOutput, $reviewOnly)) {
        throw new RuntimeException("AcroForm review-only field data leaked into visible text: {$reviewOnly}");
    }
}

$validWidget = $widgetTailFields['valid.comment.widget']['widgets'][0] ?? [];
$summary = [
    'source' => 'native-pdf-acroform-fields-dictionary-object-tail-currentbase',
    'native_boundary' => 'Indirect AcroForm field and widget references are accepted only when the target is one complete dictionary object; stray top-level operands fail closed while comment-only tails remain valid.',
    'tailed_field_object_excluded' => array_keys($fieldTailFields) === ['valid.page.repair'],
    'tailed_widget_object_not_attached' => ($widgetTailFields['tailed.widget.parent']['widgets'] ?? null) === [],
    'comment_only_widget_preserved' => ($validWidget['object'] ?? null) === 12,
    'valid_page_repair_field_names' => array_keys($fieldTailFields),
    'widget_tail_field_names' => array_keys($widgetTailFields),
    'valid_comment_widget_page_annotation_index' => $validWidget['page_annotation_index'] ?? null,
    'field_values_review_only' => !str_contains($fieldTailTextOutput . "\n" . $widgetTailTextOutput, 'Valid page repair value')
        && !str_contains($fieldTailTextOutput . "\n" . $widgetTailTextOutput, 'Tailed widget parent value')
        && !str_contains($fieldTailTextOutput . "\n" . $widgetTailTextOutput, 'Valid comment widget value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-dictionary-object-tail-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Widgets</th><th>Review</th></tr>\n";
foreach (array_merge($fieldTailFields, $widgetTailFields) as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? array_column($field['widgets'], 'object') : [];
    echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['field_type_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $widgets)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo "<td>review-only</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
