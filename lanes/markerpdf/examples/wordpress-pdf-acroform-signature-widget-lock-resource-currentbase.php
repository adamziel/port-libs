<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Signature widget lock resource body) Tj ET';
$normalAppearance = 'q /Seal Do Q BT /Fsig 10 Tf 0 0 Td (Signed appearance resource normal) Tj ET';
$rolloverAppearance = 'q /Audit Do Q BT /Froll 9 Tf 0 0 Td (Rollover signature resource review) Tj ET';
$downAppearance = 'q /PressedSeal Do Q BT /Fdown 9 Tf 0 0 Td (Down signature resource review) Tj ET';
$script = "app.alert('signature appearance resource action blocked');";
$compressedScript = gzcompress($script);
if (!is_string($compressedScript)) {
    throw new RuntimeException('Unable to compress signature appearance resource script.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R] /SigFlags 3 /DA (/Fsig 10 Tf 0 g) /DR << /Font << /Fsig 70 0 R /Froll 71 0 R /Fdown 72 0 R >> >> >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.locked_resource) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 684] /P 3 0 R /F 4 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> /R << /Signed 52 0 R >> /D << /Signed 54 0 R >> >> >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Locked article title) /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 590 320 614] /P 3 0 R /F 4 >>\nendobj\n"
    . "11 0 obj\n<< /FT /Ch /T (article.section) /V (review) /Opt [(review) (publish)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 550 320 574] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Lock Resource Reviewer) /Reason (Resource lock review) /M (D:20260602225113Z) /ByteRange [0 128 512 64] /Contents <010203040506> >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R 11 0 R] /P 2 >>\nendobj\n"
    . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Resources << /Font << /Fsig 70 0 R >> /XObject << /Seal 80 0 R >> >> /Length " . strlen($normalAppearance) . " >>\nstream\n{$normalAppearance}\nendstream\nendobj\n"
    . "51 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "52 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Resources << /Font << /Froll 71 0 R >> /XObject << /Audit 81 0 R >> >> /Length " . strlen($rolloverAppearance) . " >>\nstream\n{$rolloverAppearance}\nendstream\nendobj\n"
    . "54 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Resources << /Font << /Fdown 72 0 R >> /XObject << /PressedSeal 82 0 R >> >> /Length " . strlen($downAppearance) . " >>\nstream\n{$downAppearance}\nendstream\nendobj\n"
    . "70 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n"
    . "71 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>\nendobj\n"
    . "72 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>\nendobj\n"
    . "80 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /A 90 0 R /AA << /D 91 0 R >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "81 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "82 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>\nstream\nx\nendstream\nendobj\n"
    . "90 0 obj\n<< /S /JavaScript /JS 92 0 R >>\nendobj\n"
    . "91 0 obj\n<< /S /URI /URI (javascript:signatureResource()) >>\nendobj\n"
    . "92 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) $field['name']] = $field;
}

$signature = $fields['approval.locked_resource'] ?? [];
$review = is_array($signature['signature_widget_lock_resource_review'] ?? null)
    ? $signature['signature_widget_lock_resource_review']
    : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (($review['source'] ?? null) !== 'acroform_signature_widget_lock_resource_currentbase_review_boundary'
    || ($review['selected_appearance_objects'] ?? []) !== [50, 52, 54]
    || ($review['appearance_resource_xobject_action_count'] ?? null) !== 2
) {
    throw new RuntimeException('Expected signed signature widget lock/resource review metadata.');
}

foreach ([
    'signature appearance resource action blocked',
    'javascript:signatureResource',
    'Lock Resource Reviewer',
    'Locked article title',
] as $blockedText) {
    if (str_contains($plainText, $blockedText)) {
        throw new RuntimeException('Signature widget lock/resource payload leaked into visible WordPress text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-signature-widget-lock-resource-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-signature-widget-lock-resource-currentbase',
    'native_boundary' => 'Signed AcroForm signature widgets expose /Lock FieldMDP scope alongside selected normal/rollover/down appearance resource review metadata without executing actions, rendering appearances, or validating signatures',
    'signature_field' => $review['field_name'] ?? null,
    'signed' => $review['signed'] ?? null,
    'lock_action' => $review['lock_action'] ?? null,
    'locked_fields' => $review['lock_field_names'] ?? [],
    'selected_appearance_objects' => $review['selected_appearance_objects'] ?? [],
    'appearance_resource_font_names' => $review['appearance_resource_font_names'] ?? [],
    'appearance_resource_xobject_names' => $review['appearance_resource_xobject_names'] ?? [],
    'appearance_resource_xobject_action_count' => $review['appearance_resource_xobject_action_count'] ?? null,
    'appearance_resource_xobject_action_types' => $review['appearance_resource_xobject_action_types'] ?? [],
    'visible_text' => $plainText,
    'signature_locks_enforced_on_import' => $review['signature_locks_enforced_on_import'] ?? null,
    'appearance_resources_used_for_import' => $review['appearance_resources_used_for_import'] ?? null,
    'executes_action' => $review['executes_action'] ?? null,
    'executes_javascript' => $review['executes_javascript'] ?? null,
    'executes_appearance_streams' => $review['executes_appearance_streams'] ?? null,
    'renders_appearances' => $review['renders_appearances'] ?? null,
    'executes_signature_validation' => $review['executes_signature_validation'] ?? null,
    'executes_signing' => $review['executes_signing'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s locks %s after signing.',
    (string) ($review['field_name'] ?? 'signature'),
    implode(', ', $review['lock_field_names'] ?? [])
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Widget resources %s contain %d review-only action dictionaries.',
    implode(', ', $review['appearance_resource_xobject_names'] ?? []),
    (int) ($review['appearance_resource_xobject_action_count'] ?? 0)
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
