<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 10 0 R 12 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] /A 40 0 R /AA << /Fo << /S /Named /N /Print >> /Bl << /S /Hide /T [9 0 R] /H true >> /V 41 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> >> /A << /S /GoTo /D [3 0 R /Fit] >> >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Final title) /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "11 0 obj\n<< /FT /Tx /T (internal.notes) /V (Editable note) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 560 300 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /Reason (Signed before import) /M (D:20260602155600Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
    . "40 0 obj\n<< /S /URI /URI (javascript:app.alert\\('signature activation blocked'\\)) >>\nendobj\n"
    . "41 0 obj\n<< /S /Launch /F (signed-review.exe) /NewWindow true >>\nendobj\n"
    . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 44] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "51 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$fieldsByName = [];
foreach ($fields as $field) {
    $fieldsByName[$field['name']] = $field;
}

$signature = $fieldsByName['approval.signature'];
$state = $signature['signature_action_state'];
$titleLock = $fieldsByName['article.title']['signature_lock_state'];

echo '<!-- markerpdf:pdf-acroform-signature-field-action-state-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-signature-action-state',
    'native_boundary' => 'Signed AcroForm signature field actions, selected widget appearance, current /V signature dictionary, and /Lock field scope stay review-only metadata',
    'signature_field' => $state['field_name'],
    'signed' => $state['signed'],
    'signature_object' => $state['signature_object'],
    'action_count' => $state['action_count'],
    'action_types' => $state['action_types'],
    'blocked_unsafe_action_count' => $state['blocked_unsafe_action_count'],
    'selected_appearance_objects' => $state['selected_appearance_objects'],
    'locked_field_names' => $state['signature_lock_field_names'],
    'article_title_locked' => $titleLock['effective_locked'],
    'field_value_used_for_signature' => $state['field_value_used_for_signature'],
    'appearance_value_used_for_signature' => $state['appearance_value_used_for_signature'],
    'executes_form_actions' => $state['executes_action'],
    'executes_javascript' => $state['executes_javascript'],
    'executes_signature_validation' => $state['executes_signature_validation'],
    'executes_signing' => $state['executes_signing'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s signed by %s; %d review-only actions; appearance states: %s',
    (string) $signature['name'],
    (string) ($signature['signature']['name'] ?? 'unknown signer'),
    (int) $state['action_count'],
    implode(', ', $state['appearance_states'])
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Locked fields after signing: %s',
    implode(', ', $state['signature_lock_field_names'])
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
