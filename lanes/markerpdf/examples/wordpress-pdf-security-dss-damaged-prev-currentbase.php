<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (DSS damaged Prev review import) Tj ET';
$signaturePayload = 'DSS_DAMAGED_PREV_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$vriKey = strtoupper(hash('sha1', $signaturePayload));
$certPayload = 'DSS_DAMAGED_PREV_CERT_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'DSS_DAMAGED_PREV_OCSP_BYTES_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$pageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$contentOffset = $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$dssOffset = $addObject(60, 0, '<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /' . $vriKey . ' 61 0 R >> >>');
$vriOffset = $addObject(61, 0, '<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260608191638Z) >>');
$certOffset = $addObject(70, 0, '<< /Length ' . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream");
$ocspOffset = $addObject(71, 0, '<< /Length ' . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream");

$baseXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 5\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleCatalogOffset)
    . $xrefTableRow($pagesOffset)
    . $xrefTableRow($pageOffset)
    . $xrefTableRow($contentOffset)
    . "60 2\n"
    . $xrefTableRow($dssOffset)
    . $xrefTableRow($vriOffset)
    . "70 2\n"
    . $xrefTableRow($certOffset)
    . $xrefTableRow($ocspOffset)
    . "trailer\n<< /Size 72 /Root 1 0 R >>\n"
    . "startxref\n{$baseXrefOffset}\n%%EOF\n";

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R >>');
$acroFormOffset = $addObject(5, 0, '<< /Fields [6 0 R] /SigFlags 3 >>');
$signatureFieldOffset = $addObject(6, 0, '<< /FT /Sig /T (approval.dssDamagedPrev) /V 30 0 R >>');
$signatureOffset = $addObject(30, 0, '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Damaged Prev DSS Reviewer) /M (D:20260608191638Z) /ByteRange [0 0 0 0] /Contents ' . $signatureContentsToken . ' /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>');

$latestRows = ''
    . $xrefStreamRow(1, $currentCatalogOffset, 0)
    . $xrefStreamRow(1, $acroFormOffset, 0)
    . $xrefStreamRow(1, $signatureFieldOffset, 0)
    . $xrefStreamRow(1, $signatureOffset, 0);
$compressedLatestRows = gzcompress($latestRows);
if (!is_string($compressedLatestRows)) {
    throw new RuntimeException('Unable to compress DSS damaged-Prev xref rows.');
}

$latestXrefOffset = strlen($pdf);
$damagedPrevOffset = $baseXrefOffset + 5;
$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Prev ' . $damagedPrevOffset . ' /Index [1 1 5 2 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedLatestRows) . " >>\n"
    . "stream\n{$compressedLatestRows}\nendstream\nendobj\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF";

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$signature = $preflight['signatures'][0] ?? [];
$signatureReview = is_array($signature['signature_security_review'] ?? null) ? $signature['signature_security_review'] : [];
$dss = is_array($preflight['document_security_store'] ?? null) ? $preflight['document_security_store'] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);

if ($plainText !== 'DSS damaged Prev review import') {
    throw new RuntimeException('Expected repaired damaged-Prev fixture text to import.');
}
if (($dss['present'] ?? null) !== true || ($preflight['document_security_store_signature_match_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected repaired damaged-Prev DSS validation material to reach security preflight.');
}
if (($signatureReview['dss_vri_match_status'] ?? null) !== 'matched_signature_contents_sha1') {
    throw new RuntimeException('Expected repaired DSS VRI to match the signature contents digest.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $signaturePayload)
    || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
    || str_contains($encoded, $certPayload)
    || str_contains($encoded, $ocspPayload)
) {
    throw new RuntimeException('Expected signature and DSS validation payload bytes to stay out of WordPress output.');
}

$status = [
    'scenario' => 'wordpress-pdf-security-dss-damaged-prev-currentbase',
    'native_boundary' => 'damaged xref /Prev is repaired before DSS validation material is summarized for WordPress security review',
    'plain_text_imported' => true,
    'damaged_prev_offset' => $damagedPrevOffset,
    'dss_present' => $dss['present'] ?? null,
    'dss_validation_stream_count' => $dss['total_validation_stream_count'] ?? null,
    'dss_vri_key' => $signatureReview['dss_vri_key'] ?? null,
    'dss_vri_match_status' => $signatureReview['dss_vri_match_status'] ?? null,
    'signature_validation_review_only' => $preflight['executes_signature_validation'] === false,
    'revocation_check_review_only' => $preflight['executes_revocation_check'] === false,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
    'raw_signature_bytes_exposed' => false,
    'raw_validation_bytes_exposed' => false,
];

echo '<!-- markerpdf-security-dss-damaged-prev-currentbase-smoke ' . htmlspecialchars(json_encode($status, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:heading -->\n";
echo "<h2>DSS Damaged Prev Security Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
