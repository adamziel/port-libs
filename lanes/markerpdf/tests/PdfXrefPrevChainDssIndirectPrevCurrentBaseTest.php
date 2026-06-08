<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfDocumentSecurityStoreExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainDssIndirectPrevPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (DSS indirect Prev helper page) Tj ET';
    $signaturePayload = 'DSS_INDIRECT_PREV_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $certPayload = 'DSS_INDIRECT_PREV_CERT_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'DSS_INDIRECT_PREV_OCSP_BYTES_SHOULD_NOT_LEAK';
    $decoyCertPayload = 'DSS_INDIRECT_PREV_DECOY_CERT_BYTES_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /DSS 60 0 R >>');
    $pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $pageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>');
    $contentOffset = $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $dssOffset = $addObject(60, 0, '<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /' . $vriKey . ' 61 0 R >> >>');
    $vriOffset = $addObject(61, 0, '<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260608224208Z) >>');
    $certOffset = $addObject(70, 0, '<< /Length ' . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream");
    $ocspOffset = $addObject(71, 0, '<< /Length ' . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream");

    $baseXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($pagesOffset)
        . $xrefTableRow($pageOffset)
        . $xrefTableRow($contentOffset)
        . $xrefTableRow($fontOffset)
        . "60 2\n"
        . $xrefTableRow($dssOffset)
        . $xrefTableRow($vriOffset)
        . "70 2\n"
        . $xrefTableRow($certOffset)
        . $xrefTableRow($ocspOffset)
        . "trailer\n<< /Size 72 /Root 1 0 R >>\n"
        . "startxref\n{$baseXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /AcroForm 6 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R >>');
    $acroFormOffset = $addObject(6, 0, '<< /Fields [7 0 R] /SigFlags 3 >>');
    $signatureFieldOffset = $addObject(7, 0, '<< /FT /Sig /T (approval.dssIndirectPrev) /V 30 0 R >>');
    $signatureOffset = $addObject(30, 0, '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Indirect Prev DSS Reviewer) /M (D:20260608224208Z) /ByteRange [0 0 0 0] /Contents ' . $signatureContentsToken . ' /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>');
    $prevHelperOffset = $addObject(40, 0, (string) $baseXrefOffset);

    $latestRows = ''
        . $xrefStreamRow(1, $currentCatalogOffset, 0)
        . $xrefStreamRow(1, $acroFormOffset, 0)
        . $xrefStreamRow(1, $signatureFieldOffset, 0)
        . $xrefStreamRow(1, $signatureOffset, 0)
        . $xrefStreamRow(1, $prevHelperOffset, 0);
    $compressedRows = gzcompress($latestRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress DSS indirect-Prev xref rows.');
    }

    $latestXrefOffset = strlen($pdf);
    $pdf .= "90 0 obj\n"
        . '<< /Type /XRef /Size 91 /Root 1 0 R /Prev 40 0 R /Index [1 1 6 2 30 1 40 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$latestXrefOffset}\n%%EOF\n"
        . "40 0 obj\n0\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [72 0 R] /VRI << /DECOY 62 0 R >> >>\nendobj\n"
        . "62 0 obj\n<< /Type /VRI /Cert [72 0 R] /TU (D:20260608225500Z) >>\nendobj\n"
        . "72 0 obj\n<< /Length " . strlen($decoyCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$decoyCertPayload}\nendstream\nendobj\n";

    return [
        $pdf,
        $signaturePayload,
        $vriKey,
        hash('sha256', $certPayload),
        hash('sha256', $ocspPayload),
        $baseXrefOffset,
        $prevHelperOffset,
        $latestXrefOffset,
    ];
};

return [
    'resolves indirect xref-stream Prev helper before DSS signature review' => static function (
        TestRunner $t
    ) use ($xrefPrevChainDssIndirectPrevPdf): void {
        [$pdf, $signaturePayload, $vriKey, $certHash, $ocspHash, $baseXrefOffset, $prevHelperOffset, $latestXrefOffset] = $xrefPrevChainDssIndirectPrevPdf();

        $dss = (new PdfDocumentSecurityStoreExtractor())->extract($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);
        $signatureReview = $preflight['signatures'][0]['signature_security_review'] ?? [];
        $encoded = json_encode([$dss, $preflight], JSON_UNESCAPED_SLASHES);

        $t->same('DSS indirect Prev helper page', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->true($prevHelperOffset > $baseXrefOffset);
        $t->true($prevHelperOffset < $latestXrefOffset);
        $t->true(str_contains($pdf, "/Prev 40 0 R"));
        $t->same(true, $dss['present']);
        $t->same(60, $dss['object_number']);
        $t->same('DSS', $dss['type']);
        $t->same(1, $dss['cert_count']);
        $t->same(1, $dss['ocsp_count']);
        $t->same(0, $dss['crl_count']);
        $t->same(1, $dss['vri_count']);
        $t->same([$vriKey], $dss['vri_keys']);
        $t->same(2, $dss['total_validation_stream_count']);
        $t->same($certHash, $dss['global_certificates'][0]['sha256'] ?? null);
        $t->same($ocspHash, $dss['global_ocsps'][0]['sha256'] ?? null);
        $t->same($certHash, $dss['vri'][0]['certificates'][0]['sha256'] ?? null);
        $t->same($ocspHash, $dss['vri'][0]['ocsps'][0]['sha256'] ?? null);
        $t->same('D:20260608224208Z', $dss['vri'][0]['timestamp_update'] ?? null);

        $t->same(true, $preflight['document_security_store']['present']);
        $t->same(1, $preflight['document_security_store_count']);
        $t->same(1, $preflight['document_security_store_signature_match_count']);
        $t->same('matched_signature_contents_sha1', $signatureReview['dss_vri_match_status'] ?? null);
        $t->same($vriKey, $signatureReview['dss_vri_key'] ?? null);
        $t->same($certHash, $signatureReview['dss_vri_validation_hashes']['certificates'][0] ?? null);
        $t->same($ocspHash, $signatureReview['dss_vri_validation_hashes']['ocsps'][0] ?? null);
        $t->same(false, $dss['raw_validation_bytes_exposed']);
        $t->same(false, $dss['executes_signature_validation']);
        $t->same(false, $dss['executes_revocation_check']);
        $t->same(false, $dss['executes_external_pdf_tools']);
        $t->same(false, $preflight['executes_signature_validation']);
        $t->same(false, $preflight['executes_revocation_check']);
        $t->same(false, $preflight['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'DSS_INDIRECT_PREV_CERT_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_INDIRECT_PREV_OCSP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_INDIRECT_PREV_DECOY_CERT_BYTES_SHOULD_NOT_LEAK'));
    },
];
