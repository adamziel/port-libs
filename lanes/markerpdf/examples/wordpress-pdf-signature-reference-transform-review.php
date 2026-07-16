<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Signed transform review content) Tj ET';
$signatureContentsHex = str_repeat('A', 96);
$signatureContentsToken = '<' . $signatureContentsHex . '>';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 13 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (invoice.total) /V (42.00) /Kids [12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (internal.notes) /V (review after signature) /Kids [13 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "13 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 300 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /M (D:20260602115648Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 33 0 R >>] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [(invoice.total) 10 0 R] >>\nendobj\n"
    . "33 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn /Import /Export] /Signature [/Modify] /Annots [/Create /Modify] /EF [/Create] /Msg (Reader rights review only) >>\nendobj\n"
    . "%%EOF";

$gapStart = strpos($pdf, $signatureContentsToken);
if ($gapStart === false) {
    throw new RuntimeException('Unable to locate signature contents token in focused fixture.');
}

$gapEnd = $gapStart + strlen($signatureContentsToken);
$pdf = strtr($pdf, [
    'AAAAAAAAAA' => sprintf('%010d', $gapStart),
    'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
    'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
]);

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$signature = $preflight['signatures'][0] ?? [];
$transforms = $signature['reference_transforms'] ?? [];
$fieldMdp = $transforms[0] ?? [];
$usageRights = $transforms[1] ?? [];

echo '<!-- markerpdf-signature-reference-transform-review-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-signature-reference-transform-review',
    'native_boundary' => 'signature reference transforms are review metadata for WordPress import without cryptographic validation or rights enforcement',
    'plain_text_imported' => $plainText === 'Signed transform review content',
    'import_decision' => $preflight['import_decision'],
    'reference_transform_methods' => $preflight['signature_reference_transform_methods'],
    'field_mdp_action' => $fieldMdp['action'] ?? null,
    'field_mdp_field_names' => $fieldMdp['field_names'] ?? [],
    'usage_right_categories' => $usageRights['right_categories'] ?? [],
    'raw_digest_value_exposed' => false,
    'raw_signature_contents_exposed' => false,
    'executes_rights_enforcement' => false,
    'executes_signature_validation' => false,
    'executes_signing' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:signature-reference-transforms ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'],
    'review_reasons' => $preflight['review_reasons'],
    'field_mdp' => [
        'action' => $fieldMdp['action'] ?? null,
        'field_names' => $fieldMdp['field_names'] ?? [],
        'digest_method' => $fieldMdp['digest_method'] ?? null,
        'digest_value_exposed' => $fieldMdp['digest_value_exposed'] ?? false,
    ],
    'usage_rights' => [
        'message' => $usageRights['message'] ?? null,
        'rights' => $usageRights['rights'] ?? [],
        'executes_rights_enforcement' => $usageRights['executes_rights_enforcement'] ?? false,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
