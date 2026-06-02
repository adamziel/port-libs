<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible field action submit reset resource body) Tj ET';
$submitPayload = 'Blocked current-base field action submit payload';
$richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p>Field action rich text should stay metadata only</p></body>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Body 10 Tf 0 0 0 rg) /DR 50 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.field_action_title) /V (Current field action title) /DV (Default field action title) /RV ({$richText}) /DA (/Body 10 Tf 0.1 0.2 0.3 rg) /AA << /V 30 0 R /F 31 0 R >> /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /DA (/Widget 9 Tf 0.4 0.5 0.6 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (internal.field_action_secret) /Ff 4 /V (Secret field action value) /DA (/Private 9 Tf 0.7 g) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 360 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /S /SubmitForm /F 40 0 R /Fields [6 0 R 10 0 R] /Flags 32 >>\nendobj\n"
    . "31 0 obj\n<< /S /ResetForm /Fields [6 0 R 10 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/fallback-field-action.fdf) /UF (https://example.test/current-field-action.xfdf) /Desc (Field action submit endpoint) /AFRelationship /FormData /EF << /F 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.adobe.xfdf /Params << /Size " . strlen($submitPayload) . " /CheckSum (field-submit-checksum) >> /Length " . strlen($submitPayload) . " >>\nstream\n{$submitPayload}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Font << /Body 51 0 R /Widget 52 0 R /Private 53 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ReviewBody /Encoding /WinAnsiEncoding /FontDescriptor 54 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /WidgetFace /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "53 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /PrivateSans /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "54 0 obj\n<< /Type /FontDescriptor /FontName /ReviewBody /Flags 32 /FontWeight 500 >>\nendobj\n"
    . "%%EOF";

$fields = [];
foreach ((new PdfAcroFormExtractor())->extractFields($pdf) as $field) {
    $fields[$field['name']] = $field;
}

$title = $fields['article.field_action_title'] ?? null;
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
if (!is_array($title)) {
    throw new RuntimeException('Expected AcroForm field action title row.');
}

$actions = [];
foreach ($title['actions'] ?? [] as $action) {
    $actions[$action['trigger']] = $action;
}
$submit = $actions['V'] ?? null;
$reset = $actions['F'] ?? null;
if (!is_array($submit) || !is_array($reset)) {
    throw new RuntimeException('Expected field-level SubmitForm and ResetForm action rows.');
}
if (!is_array($submit['action_resource_review'] ?? null) || !is_array($reset['action_resource_review'] ?? null)) {
    throw new RuntimeException('Expected action-level submit/reset resource review metadata.');
}
foreach (['Field action rich text', 'Secret field action value', 'current-field-action.xfdf', $submitPayload] as $blockedText) {
    if (str_contains($visibleText, $blockedText)) {
        throw new RuntimeException('Review-only AcroForm field action metadata leaked into visible text.');
    }
}

$submitReview = $submit['action_resource_review'];
$resetReview = $reset['action_resource_review'];
echo '<!-- markerpdf:pdf-acroform-field-action-submit-reset-resource-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-field-action-submit-reset-resource-review',
    'native_boundary' => 'Field-level AcroForm SubmitForm and ResetForm additional actions are imported as review metadata only; selected fields, appearance resources, and FileSpec targets are summarized without executing actions.',
    'visible_text' => $visibleText,
    'field_name' => $title['name'] ?? null,
    'plain_value' => $title['value'] ?? null,
    'rich_text_sha256' => $title['rich_text_review']['rich_text_sha256'] ?? null,
    'submit_trigger' => $submit['trigger_label'] ?? null,
    'submit_target' => $submitReview['target'] ?? null,
    'submit_selected_fields' => $submitReview['selected_field_names'] ?? [],
    'submit_included_fields' => $submitReview['submitted_field_names'] ?? [],
    'submit_no_export_excluded_fields' => $submitReview['no_export_excluded_field_names'] ?? [],
    'submit_field_fonts' => $submitReview['field_font_resource_base_fonts'] ?? [],
    'submit_file_spec_object' => $submitReview['target_file_spec_object'] ?? null,
    'submit_embedded_file_objects' => $submitReview['target_embedded_file_objects'] ?? [],
    'reset_trigger' => $reset['trigger_label'] ?? null,
    'reset_fields' => $resetReview['reset_field_names'] ?? [],
    'reset_default_fields' => $resetReview['default_value_field_names'] ?? [],
    'reset_cleared_fields' => $resetReview['cleared_field_names'] ?? [],
    'field_value_payload_exposed' => false,
    'file_spec_payload_text_exposed' => false,
    'submits_pdf_on_import' => false,
    'resets_form_values_on_import' => false,
    'renders_appearances' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('Field action SubmitForm reviews target ' . (string) ($submitReview['target'] ?? '') . ' without submitting form data during import.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Default appearance resources are summarized as ' . implode(', ', $submitReview['field_font_resource_base_fonts'] ?? []) . ' and not rendered or executed.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('ResetForm restores "' . (string) ($title['default_value'] ?? '') . '" only as review metadata for WordPress import.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
