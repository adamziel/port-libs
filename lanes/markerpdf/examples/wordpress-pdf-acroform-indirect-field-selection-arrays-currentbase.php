<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible indirect field selection arrays body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 14 0 R 18 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 13 0 R 16 0 R] /SigFlags 3 /CO 60 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (invoice.total) /V (27.06) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 260 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (invoice.amount) /V (25.00) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "13 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 32 0 R /Kids [14 0 R] /AA << /V 41 0 R >> >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 560 260 604] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /FT /Btn /Ff 65536 /T (actions.submit) /Kids [18 0 R] /A 43 0 R >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 520 260 544] /P 3 0 R /F 4 /A 44 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Indirect Selector Reviewer) /Reason (Boundary review) /M (D:20260608123531Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "32 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields 70 0 R /P 2 >>\nendobj\n"
    . "41 0 obj\n<< /S /SubmitForm /F (https://example.test/signature-submit) /Fields 80 0 R /Flags 6 /Next 45 0 R >>\nendobj\n"
    . "43 0 obj\n<< /S /SubmitForm /F (https://example.test/button-submit) /Fields 80 0 R /Flags 6 >>\nendobj\n"
    . "44 0 obj\n<< /S /Hide /T 82 0 R /H true >>\nendobj\n"
    . "45 0 obj\n<< /S /ResetForm /Fields 81 0 R >>\nendobj\n"
    . "60 0 obj\n[8 0 R () [91 0 R] << /Nested 92 0 R >> % 93 0 R stays comment\n10 0 R 12 0 R]\nendobj\n"
    . "70 0 obj\n[6 0 R () [95 0 R] << /Nested 96 0 R >> % 97 0 R stays comment\n10 0 R]\nendobj\n"
    . "80 0 obj\n[6 0 R (submit.named.field) () [99 0 R] << /Nested 100 0 R >> % 101 0 R stays comment\n10 0 R]\nendobj\n"
    . "81 0 obj\n[10 0 R (reset.named.field) () [103 0 R] << /Nested 104 0 R >> % 105 0 R stays comment\n]\nendobj\n"
    . "82 0 obj\n[10 0 R (hide.named.field) () [107 0 R] << /Nested 108 0 R >> % 109 0 R stays comment\n]\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['invoice.total', 'invoice.amount', 'approval.signature', 'actions.submit'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Expected AcroForm field {$name} to be imported.");
    }
}

$signatureReview = $fieldsByName['approval.signature']['signature_seed_lock_action_review'] ?? [];
$buttonActions = $fieldsByName['actions.submit']['actions'] ?? [];
$widgetActions = $fieldsByName['actions.submit']['widgets'][0]['actions'] ?? [];
$expectedCalculation = [
    ['object' => 8, 'field_name' => 'invoice.total'],
    ['object' => 10, 'field_name' => 'invoice.amount'],
    ['object' => 12, 'field_name' => 'invoice.amount'],
];
if (($form['calculation_order'] ?? []) !== $expectedCalculation) {
    throw new RuntimeException('Indirect AcroForm /CO array did not resolve to the expected field/widget order.');
}
if (($fieldsByName['approval.signature']['signature_lock']['field_names'] ?? []) !== ['invoice.total', 'invoice.amount']) {
    throw new RuntimeException('Indirect signature lock /Fields array did not resolve locked field names.');
}
if (($signatureReview['submit_action_field_names'] ?? []) !== ['invoice.total', 'invoice.amount', 'submit.named.field']) {
    throw new RuntimeException('Indirect SubmitForm /Fields array did not resolve action field names.');
}
if (($buttonActions[0]['field_names'] ?? []) !== ['invoice.total', 'invoice.amount', 'submit.named.field']) {
    throw new RuntimeException('Button SubmitForm action did not expose indirect field names.');
}
if (($widgetActions[0]['field_names'] ?? []) !== ['invoice.amount', 'hide.named.field']) {
    throw new RuntimeException('Indirect Hide /T array did not resolve widget action field names.');
}
if ($visibleText !== 'Visible indirect field selection arrays body' || str_contains($visibleText, 'signature-submit')) {
    throw new RuntimeException('Action payloads leaked into visible WordPress text.');
}

$summary = [
    'source' => 'native-pdf-acroform-indirect-field-selection-arrays-currentbase',
    'native_boundary' => 'AcroForm calculation order, signature locks, SubmitForm/ResetForm field selections, and Hide targets resolve direct or indirect array objects while nested/literal/comment object-reference decoys stay review-only',
    'field_names' => array_keys($fieldsByName),
    'calculation_order' => $form['calculation_order'],
    'lock_field_names' => $fieldsByName['approval.signature']['signature_lock']['field_names'] ?? [],
    'submit_action_field_names' => $signatureReview['submit_action_field_names'] ?? [],
    'reset_action_field_names' => $signatureReview['reset_action_field_names'] ?? [],
    'hide_action_field_names' => $signatureReview['hide_action_field_names'] ?? [],
    'button_submit_field_names' => $buttonActions[0]['field_names'] ?? [],
    'widget_hide_field_names' => $widgetActions[0]['field_names'] ?? [],
    'actions_target_locked_fields' => $signatureReview['actions_target_locked_fields'] ?? false,
    'visible_text' => $visibleText,
    'action_payload_text_exposed' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_signature_validation' => false,
    'executes_signing' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-indirect-field-selection-arrays-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Value</th><th>Locked</th></tr>\n";
foreach ($fieldsByName as $name => $field) {
    $lockState = $field['signature_lock_state'] ?? [];
    echo '<tr><td>' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . (($lockState['effective_locked'] ?? false) ? 'yes' : 'no') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
