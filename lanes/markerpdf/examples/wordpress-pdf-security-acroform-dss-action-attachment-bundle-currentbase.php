<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$visible = 'BT /F1 12 Tf 72 720 Td (Security AcroForm DSS attachment bundle import) Tj ET';
$signaturePayload = 'ACROFORM_DSS_ACTION_ATTACHMENT_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$vriKey = strtoupper(hash('sha1', $signaturePayload));
$submitPayload = 'SUBMIT_XFDF_ATTACHMENT_BYTES_SHOULD_NOT_LEAK';
$importPayload = 'IMPORT_FDF_ATTACHMENT_BYTES_SHOULD_NOT_LEAK';
$launchPayload = 'LAUNCH_HELPER_ATTACHMENT_BYTES_SHOULD_NOT_LEAK';
$certPayload = 'GLOBAL_DSS_CERT_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'DSS_OCSP_BYTES_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 12 0 R 14 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.bundle) /V 30 0 R >>\nendobj\n"
    . "10 0 obj\n<< /FT /Btn /T (actions.submit_bundle) /AA << /V << /S /SubmitForm /F 40 0 R /Flags 36 >> >> >>\nendobj\n"
    . "12 0 obj\n<< /FT /Btn /T (actions.import_bundle) /AA << /U << /S /ImportData /F 50 0 R >> >> >>\nendobj\n"
    . "14 0 obj\n<< /FT /Btn /T (actions.launch_bundle) /AA << /K << /S /Launch /F 70 0 R /NewWindow true >> >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Bundle Reviewer) /M (D:20260602215714Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R 33 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /TransformParams 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [(actions.submit_bundle) (actions.import_bundle) (actions.launch_bundle)] >>\nendobj\n"
    . "33 0 obj\n<< /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 34 0 R >>\nendobj\n"
    . "34 0 obj\n<< /Type /TransformParams /V /2.2 /Form [/FillIn /Export] /EF [/Create /Import] >>\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /F (https://example.test/export.fdf) /AFRelationship /FormData /EF << /F 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.adobe.xfdf /Length " . strlen($submitPayload) . " >>\nstream\n{$submitPayload}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Filespec /F (import-review.fdf) /AFRelationship /Data /EF << /F 51 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.fdf /Length " . strlen($importPayload) . " >>\nstream\n{$importPayload}\nendstream\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [62 0 R] /OCSPs [63 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [62 0 R] /OCSP [63 0 R] >>\nendobj\n"
    . "62 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
    . "63 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
    . "70 0 obj\n<< /Type /Filespec /F (launch-current.exe) /AFRelationship /Data /EF << /F 72 0 R >> >>\nendobj\n"
    . "72 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Length " . strlen($launchPayload) . " >>\nstream\n{$launchPayload}\nendstream\nendobj\n"
    . "%%EOF";

$gapStart = strpos($pdf, $signatureContentsToken);
if ($gapStart === false) {
    throw new RuntimeException('Unable to locate signature contents token.');
}
$gapEnd = $gapStart + strlen($signatureContentsToken);
$pdf = strtr($pdf, [
    'AAAAAAAAAA' => sprintf('%010d', $gapStart),
    'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
    'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
]);

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$actionReview = $preflight['document_action_security_review'] ?? [];
$fileSpecReview = is_array($actionReview['action_file_spec_security_review'] ?? null)
    ? $actionReview['action_file_spec_security_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);

if ($plainText !== 'Security AcroForm DSS attachment bundle import') {
    throw new RuntimeException('Expected only page text in WordPress import output.');
}
if (($fileSpecReview['file_spec_count'] ?? null) !== 3 || ($fileSpecReview['embedded_file_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected three review-only action FileSpec attachments.');
}
foreach ([$signaturePayload, $submitPayload, $importPayload, $launchPayload, $certPayload, $ocspPayload] as $blockedText) {
    if (!is_string($encoded) || str_contains($encoded, $blockedText) || str_contains($plainText, $blockedText)) {
        throw new RuntimeException('Security or attachment payload leaked into import output.');
    }
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-security-acroform-dss-action-attachment-bundle-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-security-acroform-dss-action-filespec-review',
    'native_boundary' => 'AcroForm action FileSpecs, DSS validation streams, and signature permission transforms are metadata-only review rows before WordPress import.',
    'import_decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'action_count' => $actionReview['action_count'] ?? null,
    'action_file_spec_count' => $fileSpecReview['file_spec_count'] ?? null,
    'action_embedded_file_objects' => $fileSpecReview['embedded_file_objects'] ?? [],
    'action_embedded_file_hashes' => $fileSpecReview['embedded_file_hashes'] ?? [],
    'dss_certificate_hashes' => $actionReview['dss_certificate_hashes'] ?? [],
    'payload_text_exposed' => false,
    'executes_pdf_actions' => false,
    'executes_signature_validation' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($fileSpecReview['file_specs'] ?? [] as $fileSpec) {
    if (!is_array($fileSpec)) {
        continue;
    }
    echo '<li>' . htmlspecialchars(
        (string) ($fileSpec['action_type'] ?? 'Action')
        . ' reviews an action FileSpec as '
        . (string) ($fileSpec['relationship'] ?? 'unclassified')
        . ' without executing or exposing payload bytes.',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
