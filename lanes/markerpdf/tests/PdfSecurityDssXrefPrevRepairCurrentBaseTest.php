<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfDocumentSecurityStoreExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$securityDssDamagedPrevPdf = static function (): array {
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

    return [
        $pdf,
        $signaturePayload,
        $vriKey,
        hash('sha256', $certPayload),
        hash('sha256', $ocspPayload),
        $baseXrefOffset,
        $damagedPrevOffset,
    ];
};

return [
    'repairs damaged xref-stream Prev before catalog DSS security review' => static function (TestRunner $t) use ($securityDssDamagedPrevPdf): void {
        [$pdf, $signaturePayload, $vriKey, $certHash, $ocspHash, $baseXrefOffset, $damagedPrevOffset] = $securityDssDamagedPrevPdf();
        $dss = (new PdfDocumentSecurityStoreExtractor())->extract($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);
        $signature = $preflight['signatures'][0] ?? [];
        $signatureReview = $signature['signature_security_review'] ?? [];
        $encoded = json_encode([$dss, $preflight], JSON_UNESCAPED_SLASHES);

        $t->same('DSS damaged Prev review import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(true, $dss['present']);
        $t->same(60, $dss['object_number']);
        $t->same('DSS', $dss['type']);
        $t->same(1, $dss['cert_count']);
        $t->same(1, $dss['ocsp_count']);
        $t->same(0, $dss['crl_count']);
        $t->same(1, $dss['vri_count']);
        $t->same([$vriKey], $dss['vri_keys']);
        $t->same(2, $dss['total_validation_stream_count']);
        $t->same($certHash, $dss['global_certificates'][0]['sha256']);
        $t->same($ocspHash, $dss['global_ocsps'][0]['sha256']);
        $t->same($certHash, $dss['vri'][0]['certificates'][0]['sha256']);
        $t->same($ocspHash, $dss['vri'][0]['ocsps'][0]['sha256']);
        $t->same('D:20260608191638Z', $dss['vri'][0]['timestamp_update']);

        $t->same(true, $preflight['document_security_store']['present']);
        $t->same(1, $preflight['document_security_store_count']);
        $t->same(1, $preflight['document_security_store_signature_match_count']);
        $t->same('matched_signature_contents_sha1', $signatureReview['dss_vri_match_status']);
        $t->same($vriKey, $signatureReview['dss_vri_key']);
        $t->same($certHash, $signatureReview['dss_vri_validation_hashes']['certificates'][0]);
        $t->same($ocspHash, $signatureReview['dss_vri_validation_hashes']['ocsps'][0]);
        $t->same(false, $dss['raw_validation_bytes_exposed']);
        $t->same(false, $dss['executes_signature_validation']);
        $t->same(false, $dss['executes_revocation_check']);
        $t->same(false, $dss['executes_trust_chain_validation']);
        $t->same(false, $dss['executes_external_pdf_tools']);
        $t->same(false, $preflight['executes_signature_validation']);
        $t->same(false, $preflight['executes_revocation_check']);
        $t->same(false, $preflight['executes_external_pdf_tools']);
        $t->true($damagedPrevOffset > $baseXrefOffset && $damagedPrevOffset < $baseXrefOffset + 16);
        $t->true(str_contains($pdf, "/Prev {$damagedPrevOffset}"));
        $t->true(str_contains($pdf, "startxref\n{$baseXrefOffset}"));
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'DSS_DAMAGED_PREV_CERT_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_DAMAGED_PREV_OCSP_BYTES_SHOULD_NOT_LEAK'));
    },
];
