<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Signed DSS review content) Tj ET';
$signatureContentsHex = str_repeat('B', 96);
$signatureContentsToken = '<' . $signatureContentsHex . '>';
$certPayload = 'SIGNER_CERTIFICATE_DER_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'OCSP_RESPONSE_DER_BYTES_SHOULD_NOT_LEAK';
$crlPayload = 'CRL_BYTES_SHOULD_NOT_LEAK';
$timestampPayload = 'TIMESTAMP_TOKEN_BYTES_SHOULD_NOT_LEAK';
$certBytes = gzcompress($certPayload);
$crlCompressed = gzcompress($crlPayload);
if ($certBytes === false || $crlCompressed === false) {
    throw new RuntimeException('Unable to compress DSS validation fixture streams.');
}
$ocspBytes = strtoupper(bin2hex($ocspPayload)) . '>';
$crlBytes = strtoupper(bin2hex($crlCompressed)) . '>';
$timestampBytes = strtoupper(bin2hex($timestampPayload)) . '>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (DSS Reviewer) /M (D:20260602133500Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} >>\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /CRLs [72 0 R] /VRI << /ABCDEF1234 61 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /CRL [72 0 R] /TU (D:20260602133500Z) /TS 73 0 R >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($certBytes) . " /Filter 90 0 R /Subtype /application#2Fpkix-cert >>\nstream\n{$certBytes}\nendstream\nendobj\n"
    . "71 0 obj\n<< /Length " . strlen($ocspBytes) . " /Filter [91 0 R null] /Subtype /application#2Focsp-response >>\nstream\n{$ocspBytes}\nendstream\nendobj\n"
    . "72 0 obj\n<< /Length " . strlen($crlBytes) . " /Filter 92 0 R /Subtype /application#2Fpkix-crl >>\nstream\n{$crlBytes}\nendstream\nendobj\n"
    . "73 0 obj\n<< /Length " . strlen($timestampBytes) . " /Filter 91 0 R /Subtype /application#2Ftst-info >>\nstream\n{$timestampBytes}\nendstream\nendobj\n"
    . "90 0 obj\n/FlateDecode\nendobj\n"
    . "91 0 obj\n/ASCIIHexDecode\nendobj\n"
    . "92 0 obj\n[91 0 R 90 0 R null]\nendobj\n"
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
$dss = $preflight['document_security_store'];
$vri = $dss['vri'][0] ?? [];

echo '<!-- markerpdf-signature-dss-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-signature-dss-currentbase',
    'native_boundary' => 'catalog DSS validation material is summarized for WordPress security review without signature, revocation, trust-chain, Python/model, or external-tool execution',
    'plain_text_imported' => $plainText === 'Signed DSS review content',
    'import_decision' => $preflight['import_decision'],
    'review_reasons' => $preflight['review_reasons'],
    'dss_present' => $dss['present'],
    'validation_stream_count' => $dss['total_validation_stream_count'],
    'vri_keys' => $dss['vri_keys'],
    'vri_timestamp_update' => $vri['timestamp_update'] ?? null,
    'indirect_filter_decoded' => ($dss['global_certificates'][0]['sha256'] ?? null) === hash('sha256', $certPayload)
        && ($dss['global_ocsps'][0]['sha256'] ?? null) === hash('sha256', $ocspPayload)
        && ($dss['global_crls'][0]['sha256'] ?? null) === hash('sha256', $crlPayload)
        && ($vri['timestamp_token']['sha256'] ?? null) === hash('sha256', $timestampPayload),
    'validation_filters' => [
        $dss['global_certificates'][0]['filters'] ?? [],
        $dss['global_ocsps'][0]['filters'] ?? [],
        $dss['global_crls'][0]['filters'] ?? [],
        $vri['timestamp_token']['filters'] ?? [],
    ],
    'raw_validation_bytes_exposed' => $dss['raw_validation_bytes_exposed'],
    'raw_signature_contents_exposed' => false,
    'executes_signature_validation' => false,
    'executes_revocation_check' => false,
    'executes_trust_chain_validation' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:signature-dss-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'],
    'blocked_operations' => $preflight['blocked_operations'],
    'dss' => [
        'cert_count' => $dss['cert_count'],
        'ocsp_count' => $dss['ocsp_count'],
        'crl_count' => $dss['crl_count'],
        'vri_count' => $dss['vri_count'],
        'validation_stream_hashes' => [
            $dss['global_certificates'][0]['sha256'] ?? null,
            $dss['global_ocsps'][0]['sha256'] ?? null,
            $dss['global_crls'][0]['sha256'] ?? null,
            $vri['timestamp_token']['sha256'] ?? null,
        ],
    ],
    'validation_bytes_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
