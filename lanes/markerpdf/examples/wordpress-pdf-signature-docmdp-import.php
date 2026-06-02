<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /Ff 1 /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /Reason (Approved for import) /Location (Remote) /ContactInfo (editor@example.com) /M (D:20260602032148Z) /ByteRange [0 128 512 64] /Contents <0102030405> /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 3 /V /1.2 >> >>] >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$docMdp = $form['permissions']['doc_mdp'];
$signatureFields = array_values(array_filter(
    $form['fields'],
    static fn (array $field): bool => ($field['field_type'] ?? null) === 'Sig'
));

echo '<!-- markerpdf:pdf-signature-docmdp ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-perms-docmdp',
    'native_boundary' => 'catalog /Perms /DocMDP certifying signature permissions plus /FT /Sig field review metadata',
    'permission_level' => $docMdp['permission_level'] ?? null,
    'permission_label' => $docMdp['permission_label'] ?? null,
    'allowed_changes' => $docMdp['allowed_changes'] ?? [],
    'signature_field_count' => count($signatureFields),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

$allowed = implode(', ', $docMdp['allowed_changes'] ?? []);
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars('Signed import permissions: ' . ($docMdp['permission_label'] ?? 'unknown') . ' (' . $allowed . ')', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($signatureFields as $field) {
    $signature = is_array($field['signature'] ?? null) ? $field['signature'] : [];
    $docMdpTransform = is_array($signature['doc_mdp'] ?? null) ? $signature['doc_mdp'] : [];
    $line = sprintf(
        '%s: %s signed at %s [%s level %s; byte-range parts %d; contents %d bytes]',
        (string) $field['name'],
        (string) ($signature['name'] ?? 'unknown signer'),
        (string) ($signature['signed_at'] ?? 'unknown time'),
        (string) ($docMdpTransform['transform_method'] ?? 'no transform'),
        (string) ($docMdpTransform['permission_level'] ?? 'n/a'),
        is_array($signature['byte_range'] ?? null) ? count($signature['byte_range']) : 0,
        (int) ($signature['contents_length_bytes'] ?? 0)
    );

    echo '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
