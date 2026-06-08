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

$tailedText = 'BT /F1 12 Tf 72 720 Td (Visible catalog AcroForm reference tail smoke body) Tj ET';
$tailedPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R 50 0 R /Lang (en-US) >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($tailedText) . " >>\nstream\n{$tailedText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (catalog.ref.tail) /TU (Catalog ref tail label) /TM (catalog-ref-tail-export) /V (Catalog ref tail value must not surface) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "50 0 obj\n<< /Fields [52 0 R] /NeedAppearances true >>\nendobj\n"
    . "52 0 obj\n<< /FT /Tx /T (catalog.ref.tail.decoy) /V (Trailing catalog reference operand value) >>\nendobj\n"
    . "%%EOF";

$validText = 'BT /F1 12 Tf 72 720 Td (Visible catalog AcroForm reference comment smoke body) Tj ET';
$validPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R % comment-only catalog AcroForm reference tail\n/Lang (en-US) >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($validText) . " >>\nstream\n{$validText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (catalog.ref.comment) /TU (Catalog ref comment label) /TM (catalog-ref-comment-export) /V (Catalog ref comment value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfAcroFormExtractor();
$textExtractor = new PdfTextExtractor();
$tailedForm = $extractor->extractForm($tailedPdf);
$validForm = $extractor->extractForm($validPdf);
$tailedVisibleText = $textExtractor->extractPlainText($tailedPdf);
$validVisibleText = $textExtractor->extractPlainText($validPdf);
$validFields = $fieldsByName($validForm['fields']);
$encoded = json_encode([$tailedForm, $validForm], JSON_UNESCAPED_SLASHES);

if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode AcroForm catalog-reference-tail review output.');
}
if (($tailedForm['need_appearances'] ?? true) !== false || ($tailedForm['fields'] ?? []) !== []) {
    throw new RuntimeException('Tailed catalog AcroForm reference must fail closed before field review.');
}
if (array_keys($validFields) !== ['catalog.ref.comment']) {
    throw new RuntimeException('Comment-only catalog AcroForm reference tail should preserve the valid field.');
}

foreach ([
    'catalog.ref.tail',
    'Catalog ref tail label',
    'catalog-ref-tail-export',
    'Catalog ref tail value must not surface',
    'catalog.ref.tail.decoy',
    'Trailing catalog reference operand value',
] as $decoy) {
    if (str_contains($encoded, $decoy) || str_contains($tailedVisibleText, $decoy) || str_contains($validVisibleText, $decoy)) {
        throw new RuntimeException("Malformed AcroForm catalog reference decoy leaked: {$decoy}");
    }
}

foreach ([
    'Catalog ref comment value',
    'Catalog ref comment label',
] as $reviewOnly) {
    if (str_contains($validVisibleText, $reviewOnly)) {
        throw new RuntimeException("AcroForm review-only field data leaked into visible text: {$reviewOnly}");
    }
}

$validField = $validFields['catalog.ref.comment'];
$summary = [
    'source' => 'native-pdf-acroform-fields-catalog-reference-tail-currentbase',
    'native_boundary' => 'Catalog /AcroForm indirect references are accepted only when no extra top-level operand appears before the next catalog key.',
    'tailed_catalog_reference_excluded' => ($tailedForm['fields'] ?? []) === [],
    'comment_only_catalog_reference_preserved' => ($validField['object'] ?? null) === 6,
    'valid_field_names' => array_keys($validFields),
    'valid_widget_objects' => array_column($validField['widgets'] ?? [], 'object'),
    'valid_widget_page_objects' => array_column($validField['widgets'] ?? [], 'page_object'),
    'valid_widget_page_annotation_indexes' => array_column($validField['widgets'] ?? [], 'page_annotation_index'),
    'field_values_review_only' => !str_contains($tailedVisibleText . "\n" . $validVisibleText, 'Catalog ref tail value must not surface')
        && !str_contains($tailedVisibleText . "\n" . $validVisibleText, 'Catalog ref comment value'),
    'form_values_visible_in_text' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-catalog-reference-tail-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widgets</th></tr>\n";
foreach ($validFields as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? array_column($field['widgets'], 'object') : [];
    echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['field_type_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>review-only</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $widgets)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
