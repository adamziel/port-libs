<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible submit reset lock body) Tj ET';
$checkedAppearance = 'q 0 0 12 12 re S Q';
$offAppearance = 'q Q';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R 14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R 13 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 650 240 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Btn /T (article.consent) /V /Yes /DV /Off /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 610 96 634] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 50 0 R /Off 51 0 R >> >> >>\nendobj\n"
    . "11 0 obj\n<< /FT /Tx /T (article.title) /V (Locked current title) /DV (Draft title) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [120 610 360 634] /P 3 0 R /F 4 >>\nendobj\n"
    . "13 0 obj\n<< /FT /Btn /T (actions.submit_reset) /Ff 65536 /Kids [14 0 R] /AA << /U 41 0 R >> >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 560 220 584] /P 3 0 R /F 4 /A 40 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Approval Reviewer) /Reason (Approved) /M (D:20260602210500Z) /ByteRange [0 128 512 64] /Contents <010203040506> >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
    . "40 0 obj\n<< /S /SubmitForm /F (https://example.test/locked-submit) /Fields [9 0 R 11 0 R] /Flags 2 >>\nendobj\n"
    . "41 0 obj\n<< /S /ResetForm /Fields [(article.consent) (article.title)] >>\nendobj\n"
    . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length " . strlen($checkedAppearance) . " >>\nstream\n{$checkedAppearance}\nendstream\nendobj\n"
    . "51 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n"
    . "%%EOF";

$fields = [];
foreach ((new PdfAcroFormExtractor())->extractFields($pdf) as $field) {
    $fields[$field['name']] = $field;
}

$actionField = $fields['actions.submit_reset'] ?? null;
$review = is_array($actionField)
    && is_array($actionField['submit_reset_appearance_lock_review'] ?? null)
        ? $actionField['submit_reset_appearance_lock_review']
        : null;
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

if ($review === null || ($review['source'] ?? null) !== 'acroform_submit_reset_appearance_lock_currentbase_review_boundary') {
    throw new RuntimeException('Expected submit/reset appearance lock review metadata.');
}
if (($review['locked_target_field_names'] ?? []) !== ['article.consent']
    || ($review['selected_appearance_objects'] ?? []) !== [50]
    || ($review['action_count'] ?? 0) !== 2
) {
    throw new RuntimeException('Expected locked target and selected appearance summary.');
}
if (($review['executes_action'] ?? true) !== false
    || ($review['executes_appearance_streams'] ?? true) !== false
    || ($review['renders_appearances'] ?? true) !== false
) {
    throw new RuntimeException('AcroForm submit/reset review unexpectedly executed actions or appearances.');
}
if ($visibleText !== 'Visible submit reset lock body'
    || str_contains($visibleText, 'locked-submit')
    || str_contains($visibleText, 'Approval Reviewer')
    || str_contains($visibleText, 'Locked current title')
) {
    throw new RuntimeException('Review-only AcroForm payload leaked into visible WordPress text.');
}

echo '<!-- markerpdf:pdf-acroform-submit-reset-appearance-lock-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-submit-reset-appearance-lock-currentbase',
    'native_boundary' => 'AcroForm SubmitForm/ResetForm field selections, widget /AP state, and signed /Lock scope are imported as review metadata only',
    'visible_text' => $visibleText,
    'action_field' => $review['field_name'] ?? null,
    'action_count' => $review['action_count'] ?? null,
    'submitted_field_names' => $review['submitted_field_names'] ?? [],
    'reset_field_names' => $review['reset_field_names'] ?? [],
    'locked_target_field_names' => $review['locked_target_field_names'] ?? [],
    'appearance_field_names' => $review['appearance_field_names'] ?? [],
    'selected_appearance_objects' => $review['selected_appearance_objects'] ?? [],
    'default_value_authoritative_for_reset_review' => $review['default_value_authoritative_for_reset_review'] ?? null,
    'signature_locks_enforced_on_import' => false,
    'appearance_value_used_for_import' => false,
    'submits_form_data' => false,
    'resets_form_values' => false,
    'executes_action' => false,
    'executes_javascript' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('SubmitForm and ResetForm target ' . implode(', ', $review['selected_field_names'] ?? []) . ' as review metadata.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Signed field lock covers ' . implode(', ', $review['locked_target_field_names'] ?? []) . '; the import does not enforce or execute the lock.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Widget appearance object ' . implode(', ', array_map('strval', $review['selected_appearance_objects'] ?? [])) . ' remains non-rendered review metadata.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
