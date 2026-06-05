<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate AcroForm Fields body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [99 0 R] /DA (/Helv 9 Tf 0 0 0 rg with literal /Fields [101 0 R]) /Fie#6Cds [6 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.current_duplicate) /TU (Current duplicate label) /TM (article.current_duplicate.export) /V (Current duplicate field value) /DV (Current duplicate draft) /MaxLen 96 /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (stale.duplicate_fields) /TU (Stale duplicate label) /TM (stale.duplicate.export) /V (Stale duplicate field value must not surface) >>\nendobj\n"
    . "101 0 obj\n<< /FT /Tx /T (literal.fields.decoy) /V (Literal Fields decoy value must not surface) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['article.current_duplicate']) {
    throw new RuntimeException('Expected the later AcroForm /Fields key to select the current field array only.');
}
if (isset($fieldsByName['stale.duplicate_fields'], $fieldsByName['literal.fields.decoy'])) {
    throw new RuntimeException('Duplicate or literal AcroForm /Fields decoys must not be imported.');
}

$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
if (!str_contains($visibleText, 'Visible duplicate AcroForm Fields body')) {
    throw new RuntimeException('Expected visible page text to remain importable.');
}
if (
    str_contains($visibleText, 'Current duplicate field value')
    || str_contains($visibleText, 'Stale duplicate field value must not surface')
    || str_contains($visibleText, 'Literal Fields decoy value must not surface')
) {
    throw new RuntimeException('AcroForm field values must remain review metadata, not visible WordPress paragraph text.');
}

$field = $fieldsByName['article.current_duplicate'];
$encodedForm = json_encode($form, JSON_UNESCAPED_SLASHES);
if (!is_string($encodedForm)) {
    throw new RuntimeException('Unable to encode AcroForm review metadata.');
}

echo '<!-- markerpdf:pdf-acroform-fields-duplicate-key-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-duplicate-fields-key-boundary',
    'native_boundary' => 'Duplicate top-level AcroForm /Fields keys are not merged; the later escaped key selects the current field array before WordPress review metadata.',
    'field_names' => array_keys($fieldsByName),
    'field_value' => $field['value'] ?? null,
    'field_value_review_only' => !str_contains($visibleText, (string) ($field['value'] ?? '')),
    'need_appearances' => $form['need_appearances'],
    'stale_duplicate_field_imported' => str_contains($encodedForm, 'stale.duplicate_fields'),
    'literal_fields_decoy_imported' => str_contains($encodedForm, 'literal.fields.decoy'),
    'visible_text_imported' => str_contains($visibleText, 'Visible duplicate AcroForm Fields body'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(
    (string) ($field['name'] ?? '') . ': ' . (string) ($field['value'] ?? '') . ' [review metadata]',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
