<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible typed non-field AcroForm body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 20 0 R 30 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.safe) /V (Safe value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Widget /FT /Tx /T (article.inline) /V (Inline widget value) /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /T (filespec.decoy) /V (Filespec value must not surface) /Kids [22 0 R] /F (embedded-review.txt) >>\nendobj\n"
    . "22 0 obj\n<< /FT /Tx /T (filespec.child.decoy) /V (Filespec child value must not surface) >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /T (signature.value.decoy) /V (Signature value dictionary must not surface) /Name (Standalone Signer) /Reason (Standalone signature value dictionary) /ByteRange [0 10 20 10] /Contents <01020304> >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fieldNames = array_values(array_map(
    static fn (array $field): string => (string) $field['name'],
    $form['fields']
));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedForm = json_encode($form, JSON_UNESCAPED_SLASHES);

foreach (['article.safe', 'article.inline'] as $requiredField) {
    if (!in_array($requiredField, $fieldNames, true)) {
        throw new RuntimeException("Expected AcroForm field {$requiredField} was not extracted.");
    }
}

foreach (['filespec.decoy', 'filespec.child.decoy', 'signature.value.decoy'] as $blockedField) {
    if (in_array($blockedField, $fieldNames, true)) {
        throw new RuntimeException("Typed non-field dictionary leaked into AcroForm fields: {$blockedField}.");
    }
}

foreach ([
    'Filespec value must not surface',
    'Filespec child value must not surface',
    'Signature value dictionary must not surface',
] as $blockedText) {
    if (str_contains($plainText, $blockedText) || (is_string($encodedForm) && str_contains($encodedForm, $blockedText))) {
        throw new RuntimeException("Typed non-field payload leaked into WordPress import review: {$blockedText}");
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-typed-dictionary-currentbase '
    . json_encode([
        'source' => 'native-pdf-acroform-typed-dictionary-boundary',
        'field_names' => $fieldNames,
        'typed_non_field_dictionaries_excluded' => true,
        'filespec_dictionary_excluded' => true,
        'signature_value_dictionary_excluded' => true,
        'widget_annotation_with_type_preserved' => true,
        'form_values_visible_in_text' => str_contains($plainText, 'Safe value') || str_contains($plainText, 'Inline widget value'),
        'executes_form_actions' => false,
        'executes_javascript' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_UNESCAPED_SLASHES)
    . " -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
