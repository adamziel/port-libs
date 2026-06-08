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

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm field object dictionary-owner body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 20 0 R 30 0 R 40 0 R 50 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (owner.valid) /TU (Owner valid label) /TM (owner-valid-export) /V (Owner valid value) /DV (Owner valid default) /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n(<< /FT /Tx /T (literal.owner.decoy) /TU (Literal owner label) /TM (literal-owner-export) /V (Literal owner value) >>)\nendobj\n"
    . "30 0 obj\n% << /FT /Tx /T (comment.owner.decoy) /TU (Comment owner label) /TM (comment-owner-export) /V (Comment owner value) >>\nnull\nendobj\n"
    . "40 0 obj\n[<< /FT /Tx /T (array.owner.decoy) /TU (Array owner label) /TM (array-owner-export) /V (Array owner value) >>]\nendobj\n"
    . "50 0 obj\n/NotAField << /FT /Tx /T (tail.owner.decoy) /TU (Tail owner label) /TM (tail-owner-export) /V (Tail owner value) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $fieldsByName($form['fields']);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

if (array_keys($fields) !== ['owner.valid']) {
    throw new RuntimeException('Malformed AcroForm field object bodies must not be imported as top-level fields.');
}
if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode AcroForm review output.');
}

$decoyTexts = [
    'literal.owner.decoy',
    'Literal owner value',
    'comment.owner.decoy',
    'Comment owner value',
    'array.owner.decoy',
    'Array owner value',
    'tail.owner.decoy',
    'Tail owner value',
];
foreach ($decoyTexts as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("AcroForm malformed object-body decoy leaked into import review: {$decoyText}");
    }
}

foreach (['Owner valid value', 'Owner valid default', 'Owner valid label'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review-only field data leaked into visible text: {$reviewOnlyText}");
    }
}

$field = $fields['owner.valid'];
$widget = $field['widgets'][0] ?? [];
$summary = [
    'source' => 'native-pdf-acroform-field-object-dictionary-owner-boundary',
    'native_boundary' => 'AcroForm /Fields references are accepted as form fields only when the referenced object value is a top-level PDF dictionary; field-looking dictionaries inside literal strings, comments, arrays, or non-dictionary tails are excluded.',
    'field_names' => array_keys($fields),
    'valid_field_object' => $field['object'] ?? null,
    'valid_widget_object' => $widget['object'] ?? null,
    'valid_widget_page_annotation_index' => $widget['page_annotation_index'] ?? null,
    'valid_widget_referenced_from_page_annots' => $widget['referenced_from_page_annots'] ?? null,
    'malformed_literal_object_excluded' => !str_contains($encoded, 'literal.owner.decoy'),
    'malformed_comment_object_excluded' => !str_contains($encoded, 'comment.owner.decoy'),
    'malformed_array_object_excluded' => !str_contains($encoded, 'array.owner.decoy'),
    'malformed_tail_object_excluded' => !str_contains($encoded, 'tail.owner.decoy'),
    'visible_text' => $visibleText,
    'visible_text_excludes_form_values' => !str_contains($visibleText, 'Owner valid value')
        && !str_contains($visibleText, 'Literal owner value')
        && !str_contains($visibleText, 'Comment owner value')
        && !str_contains($visibleText, 'Array owner value')
        && !str_contains($visibleText, 'Tail owner value'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-object-dictionary-owner-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
