<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible submit reset resource body) Tj ET';
$richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p><b>Styled review value</b> stays metadata</p></body>';
$defaultStyle = 'font: 11pt ReviewSerif; color:#003366';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.rich_resource) /Ff 33554432 /V (Plain resource value) /DV (Draft resource value) /RV ({$richText}) /DS ({$defaultStyle}) /DA (/Body 11 Tf 0.1 0.2 0.3 rg) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /DA (/Widget 10 Tf 0.4 0.5 0.6 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (internal.resource_secret) /Ff 4 /V (Private resource payload) /DA (/Private 9 Tf 0.7 g) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 360 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Btn /T (actions.submit_pdf) /Ff 65536 /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 180 584] /P 3 0 R /F 4 /A << /S /SubmitForm /F 40 0 R /Fields [6 0 R 10 0 R] /Flags 11138 >> >>\nendobj\n"
    . "18 0 obj\n<< /FT /Btn /T (actions.reset_resource) /Ff 65536 /Kids [20 0 R] /AA << /U << /S /ResetForm /Fields [6 0 R 10 0 R] >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [200 560 310 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Font << /Helv 31 0 R /Body 32 0 R /Widget 34 0 R /Private 35 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "32 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ReviewSerif /Encoding /WinAnsiEncoding /FontDescriptor 33 0 R >>\nendobj\n"
    . "33 0 obj\n<< /Type /FontDescriptor /FontName /ReviewSerif /Flags 32 /FontWeight 600 >>\nendobj\n"
    . "34 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ReviewWidget /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "35 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /PrivateSans /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /F (https://example.test/pdf-submit) >>\nendobj\n"
    . "%%EOF";

$fields = [];
foreach ((new PdfAcroFormExtractor())->extractFields($pdf) as $field) {
    $fields[$field['name']] = $field;
}

$rich = $fields['article.rich_resource'] ?? null;
$submit = $fields['actions.submit_pdf']['widgets'][0]['actions'][0] ?? null;
$reset = $fields['actions.reset_resource']['actions'][0] ?? null;
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

if (!is_array($rich) || !is_array($submit) || !is_array($reset)) {
    throw new RuntimeException('Expected AcroForm rich resource, submit, and reset review rows.');
}
if (!is_array($rich['rich_text_review'] ?? null)) {
    throw new RuntimeException('Expected rich text review metadata.');
}
if (!is_array($submit['field_value_review'] ?? null) || !is_array($reset['field_value_review'] ?? null)) {
    throw new RuntimeException('Expected submit/reset field value review metadata.');
}

$submitRows = [];
foreach ($submit['field_value_review']['field_rows'] as $row) {
    $submitRows[$row['field_name']] = $row;
}
$resetRows = [];
foreach ($reset['field_value_review']['field_rows'] as $row) {
    $resetRows[$row['field_name']] = $row;
}
$resourceReview = $submitRows['article.rich_resource']['appearance_resource_review'] ?? null;
if (!is_array($resourceReview)) {
    throw new RuntimeException('Expected submit field default resource review metadata.');
}
if (
    str_contains($visibleText, 'Styled review value')
    || str_contains($visibleText, 'ReviewSerif')
    || str_contains($visibleText, 'Private resource payload')
    || str_contains($visibleText, 'pdf-submit')
) {
    throw new RuntimeException('Review-only AcroForm submit/reset payload leaked into visible text.');
}

echo '<!-- markerpdf:pdf-acroform-submit-reset-richtext-resource-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-submit-reset-richtext-resource-review',
    'native_boundary' => 'AcroForm SubmitForm flags plus rich-text /RV /DS and /DA /DR font resources are review metadata only before WordPress import',
    'visible_text' => $visibleText,
    'field_name' => $rich['name'] ?? null,
    'plain_value' => $rich['value'] ?? null,
    'rich_text_sha256' => $rich['rich_text_review']['rich_text_sha256'] ?? null,
    'default_style_sha256' => $rich['rich_text_review']['default_style_sha256'] ?? null,
    'submit_format' => $submit['submit_format'] ?? null,
    'submit_flags' => $submit['flag_names'] ?? [],
    'submitted_field_names' => $submit['field_value_review']['submitted_field_names'] ?? [],
    'no_export_excluded_field_names' => $submit['field_value_review']['no_export_excluded_field_names'] ?? [],
    'reset_field_names' => $reset['field_value_review']['reset_field_names'] ?? [],
    'resource_font' => $resourceReview['font_resource'] ?? null,
    'resource_font_base' => $resourceReview['font_resource_base_font'] ?? null,
    'widget_resource_font_base' => $resourceReview['widget_appearances'][0]['font_resource_base_font'] ?? null,
    'reset_resource_font_base' => $resetRows['article.rich_resource']['appearance_resource_review']['font_resource_base_font'] ?? null,
    'rich_text_payload_excluded_from_visible_text' => true,
    'resource_names_excluded_from_visible_text' => true,
    'submits_pdf_on_import' => false,
    'embeds_form_on_import' => false,
    'includes_annotations_on_import' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('article.rich_resource imports plain /V "' . (string) ($rich['value'] ?? '') . '"; /RV and /DS are review-only.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('SubmitForm requests ' . (string) ($submit['submit_format'] ?? 'unknown') . ' review but no PDF, annotation, rich-text, or default-resource payload is submitted during import.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('ResetForm restores default value "' . (string) ($resetRows['article.rich_resource']['reset_value'] ?? '') . '" without rendering AcroForm resources.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
