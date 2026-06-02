<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Seed lock action import) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /SV 31 0 R /Lock 32 0 R /Kids [8 0 R] /AA << /V 40 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 /A 41 0 R >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Locked title value) /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "11 0 obj\n<< /FT /Tx /T (internal.notes) /V (Editable note value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Seed Reviewer) /Reason (Seed lock review) /M (D:20260602185507Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "31 0 obj\n<< /Type /SV /Ff 107 /Filter /Adobe.PPKLite /SubFilter [/adbe.pkcs7.detached /ETSI.CAdES.detached] /DigestMethod [/SHA256] /Reasons [(Seed lock review)] /AddRevInfo true /MDP << /P 2 >> /TimeStamp << /URL (https://timestamp.example.test/rfc3161) /Ff 1 >> >>\nendobj\n"
    . "32 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
    . "40 0 obj\n<< /S /SubmitForm /F (https://example.test/signed-submit) /Fields [(article.title) (internal.notes)] /Flags 6 /Next 42 0 R >>\nendobj\n"
    . "41 0 obj\n<< /S /ResetForm /Fields [9 0 R] /Next 43 0 R >>\nendobj\n"
    . "42 0 obj\n<< /S /ImportData /F (file://local-review.fdf) >>\nendobj\n"
    . "43 0 obj\n<< /S /Hide /T [(internal.notes)] /H true >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) $field['name']] = $field;
}

$signature = $fields['approval.signature'] ?? [];
$review = is_array($signature['signature_seed_lock_action_review'] ?? null)
    ? $signature['signature_seed_lock_action_review']
    : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (($review['source'] ?? null) !== 'acroform_signature_seed_lock_action_boundary'
    || ($review['action_count'] ?? 0) !== 4
    || ($review['unsafe_action_count'] ?? 0) !== 4
) {
    throw new RuntimeException('Expected signature seed, lock, and form-action review summary.');
}
if (($review['form_actions_execute_on_import'] ?? true) !== false
    || ($review['executes_signature_validation'] ?? true) !== false
    || ($review['executes_signing'] ?? true) !== false
) {
    throw new RuntimeException('Signature seed/lock action review unexpectedly executed import-time behavior.');
}
if ($plainText !== 'Seed lock action import'
    || str_contains($plainText, 'signed-submit')
    || str_contains($plainText, 'local-review.fdf')
    || str_contains($plainText, 'timestamp.example.test')
) {
    throw new RuntimeException('Signature seed/lock action payload leaked into visible WordPress text.');
}

echo '<!-- markerpdf:pdf-acroform-seed-lock-actions-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-seed-lock-actions-currentbase',
    'native_boundary' => 'signature /SV constraints, signed /Lock scope, and /A /AA /Next form actions are imported as review metadata only',
    'signature_field' => $review['field_name'] ?? null,
    'signed' => $review['signed'] ?? null,
    'seed_required_constraints' => $review['seed_value_required_constraints'] ?? [],
    'seed_timestamp_required' => $review['seed_value_timestamp_required'] ?? null,
    'lock_action' => $review['lock_action'] ?? null,
    'locked_fields' => $review['lock_field_names'] ?? [],
    'action_types' => $review['action_types'] ?? [],
    'action_field_names' => $review['action_field_names'] ?? [],
    'locked_action_field_names' => $review['locked_action_field_names'] ?? [],
    'form_actions_execute_on_import' => $review['form_actions_execute_on_import'] ?? null,
    'executes_signature_validation' => $review['executes_signature_validation'] ?? null,
    'executes_signing' => $review['executes_signing'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s is signed; seed constraints require %s.',
    (string) ($review['field_name'] ?? 'signature'),
    implode(', ', $review['seed_value_required_constraints'] ?? [])
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'The %s lock covers %s, and actions touching %s stay review-only.',
    (string) ($review['lock_action'] ?? 'unknown'),
    implode(', ', $review['lock_field_names'] ?? []),
    implode(', ', $review['locked_action_field_names'] ?? [])
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
