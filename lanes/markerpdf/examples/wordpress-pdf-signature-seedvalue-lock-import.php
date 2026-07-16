<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 13 0 R 14 0 R 15 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R 11 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /Ff 1 /V 30 0 R /SV 31 0 R /Lock 32 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (registration.email) /V (editor@example.com) /Kids [13 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (invoice.total) /V (27.06) /Kids [14 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /FT /Tx /T (internal.notes) /V (still editable) /Kids [15 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 300 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "15 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 520 300 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /Reason (Approved for import) /M (D:20260602064500Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "31 0 obj\n<< /Type /SV /Ff 111 /Filter /Adobe.PPKLite /SubFilter [/adbe.pkcs7.detached /ETSI.CAdES.detached] /DigestMethod [/SHA256 /SHA512] /V 2.0 /Reasons [(Approved for import) (Final review)] /LegalAttestation [(I attest) <FEFF004F004B>] /AddRevInfo true /MDP << /P 2 >> /TimeStamp << /URL (https://timestamp.example.test/rfc3161) /Ff 1 >> >>\nendobj\n"
    . "32 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [(registration.email) (invoice.total)] /P 2 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$signatureFields = array_values(array_filter(
    $form['fields'],
    static fn (array $field): bool => ($field['field_type'] ?? null) === 'Sig'
));
$signatureField = $signatureFields[0] ?? [];
$seed = is_array($signatureField['signature_seed_value'] ?? null) ? $signatureField['signature_seed_value'] : [];
$lock = is_array($signatureField['signature_lock'] ?? null) ? $signatureField['signature_lock'] : [];
$mdp = is_array($seed['mdp'] ?? null) ? $seed['mdp'] : [];
$timestamp = is_array($seed['timestamp'] ?? null) ? $seed['timestamp'] : [];

echo '<!-- markerpdf:pdf-signature-seedvalue-lock ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-signature-field-seed-value-lock',
    'native_boundary' => 'signature field /SV constraints and /Lock field-scope metadata extracted without signing or executing actions',
    'signature_field_count' => count($signatureFields),
    'seed_required_constraints' => $seed['required_constraints'] ?? [],
    'lock_action' => $lock['action'] ?? null,
    'locked_fields' => $lock['field_names'] ?? [],
    'executes_signing' => $seed['executes_signing'] ?? false,
    'executes_action' => $lock['executes_action'] ?? false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    sprintf(
        'Signature seed constraints: %s; digest methods: %s; timestamp required: %s',
        implode(', ', $seed['required_constraints'] ?? []),
        implode(', ', $seed['digest_methods'] ?? []),
        ($timestamp['required'] ?? false) ? 'yes' : 'no'
    ),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(
    sprintf(
        '%s locks %s [%s; %s]',
        (string) ($signatureField['name'] ?? 'signature'),
        implode(', ', $lock['field_names'] ?? []),
        (string) ($lock['action_label'] ?? 'unknown'),
        (string) ($mdp['permission_label'] ?? 'unknown')
    ),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo '<li>' . htmlspecialchars(
    sprintf(
        'Allowed signing handlers: %s via %s',
        (string) ($seed['filter'] ?? 'unknown'),
        implode(', ', $seed['subfilters'] ?? [])
    ),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
