<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm review body) Tj ET';
$richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p><b>Styled summary</b> &amp; review script blocked</p></body>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R 14 0 R 17 0 R 20 0 R 23 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 12 0 R 15 0 R 18 0 R 21 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.summary) /Ff 33554432 /V (Plain summary) /DV (Draft summary) /RV ({$richText}) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Ch /T (article.topics) /Ff 2097152 /V [(plugin) (themes)] /DV [(blocks)] /I [1 0] /Opt [[(themes) (Themes)] [(plugin) (Plugins)] [(blocks) (Blocks)]] /Kids [11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 360 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /FT /Tx /T (internal.secret) /Ff 4 /V (No export payload) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 560 360 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "15 0 obj\n<< /FT /Tx /T (article.empty) /Kids [17 0 R] >>\nendobj\n"
    . "17 0 obj\n<< /Subtype /Widget /Parent 15 0 R /Rect [72 520 360 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /FT /Btn /T (actions.submit) /Ff 65536 /Kids [20 0 R] >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 480 180 504] /P 3 0 R /F 4 /A << /S /SubmitForm /F 30 0 R /Flags 6 >> >>\nendobj\n"
    . "21 0 obj\n<< /FT /Btn /T (actions.reset) /Ff 65536 /Kids [23 0 R] /AA << /U << /S /ResetForm /Fields [(article.summary) 9 0 R 15 0 R] >> >> >>\nendobj\n"
    . "23 0 obj\n<< /Subtype /Widget /Parent 21 0 R /Rect [200 480 310 504] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (https://example.test/form-submit) >>\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$fieldsByName = [];
foreach ($fields as $field) {
    $fieldsByName[$field['name']] = $field;
}

$summary = $fieldsByName['article.summary'] ?? null;
$topics = $fieldsByName['article.topics'] ?? null;
$submit = $fieldsByName['actions.submit']['widgets'][0]['actions'][0] ?? null;
$reset = $fieldsByName['actions.reset']['actions'][0] ?? null;
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

if (!is_array($summary) || !is_array($topics) || !is_array($submit) || !is_array($reset)) {
    throw new RuntimeException('Expected AcroForm summary, topics, submit, and reset review rows.');
}
if (!is_array($summary['rich_text_review'] ?? null)) {
    throw new RuntimeException('Expected rich-text review metadata.');
}
if (!is_array($submit['field_value_review'] ?? null) || !is_array($reset['field_value_review'] ?? null)) {
    throw new RuntimeException('Expected SubmitForm/ResetForm field value review metadata.');
}
if (str_contains($visibleText, 'Styled summary') || str_contains($visibleText, 'form-submit') || str_contains($visibleText, 'No export payload')) {
    throw new RuntimeException('Review-only AcroForm payload leaked into visible text.');
}

$submitReview = $submit['field_value_review'];
$resetReview = $reset['field_value_review'];
$topicLabels = array_values(array_map(
    static fn (array $option): string => (string) ($option['label'] ?? $option['export'] ?? ''),
    $topics['value_state']['selected_options'] ?? []
));

echo '<!-- markerpdf:pdf-acroform-choice-richtext-submit-reset-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-choice-richtext-submit-reset-review',
    'native_boundary' => 'AcroForm /RV rich text, choice /Opt selected exports, SubmitForm export rows, and ResetForm default rows are review metadata only before WordPress import',
    'visible_text' => $visibleText,
    'rich_text_field' => $summary['name'] ?? null,
    'rich_text_plain_value' => $summary['rich_text_review']['plain_value'] ?? null,
    'rich_text_plain_preview' => $summary['rich_text_review']['rich_text_plain_preview'] ?? null,
    'selected_choice_labels' => $topicLabels,
    'submitted_field_names' => $submitReview['submitted_field_names'] ?? [],
    'no_export_excluded_field_names' => $submitReview['no_export_excluded_field_names'] ?? [],
    'push_button_excluded_field_names' => $submitReview['push_button_excluded_field_names'] ?? [],
    'reset_field_names' => $resetReview['reset_field_names'] ?? [],
    'cleared_field_names' => $resetReview['cleared_field_names'] ?? [],
    'exports_rich_text_html' => false,
    'restores_rich_text_html' => false,
    'rich_text_payload_excluded_from_visible_text' => true,
    'action_payloads_excluded_from_visible_text' => true,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('Rich text field article.summary imports plain /V "Plain summary"; /RV is review-only.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Choice field article.topics submits selected labels ' . implode(', ', $topicLabels) . '.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('SubmitForm exports ' . implode(', ', $submitReview['submitted_field_names'] ?? []) . ' and excludes no-export/internal controls.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('ResetForm restores defaults for ' . implode(', ', $resetReview['reset_field_names'] ?? []) . '.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
