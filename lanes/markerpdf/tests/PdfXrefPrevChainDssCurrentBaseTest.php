<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfDocumentSecurityStoreExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainDssCurrentBasePdf = static function (): array {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale DSS Prev chain page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current DSS Prev chain page) Tj ET';
    $staleCertPayload = 'STALE_DSS_PREV_CHAIN_CERT_BYTES_SHOULD_NOT_LEAK';
    $staleOcspPayload = 'STALE_DSS_PREV_CHAIN_OCSP_BYTES_SHOULD_NOT_LEAK';
    $currentCertPayload = 'CURRENT_DSS_PREV_CHAIN_CERT_BYTES_SHOULD_NOT_LEAK';
    $currentOcspPayload = 'CURRENT_DSS_PREV_CHAIN_OCSP_BYTES_SHOULD_NOT_LEAK';
    $decoyCertPayload = 'DECOY_DSS_POST_XREF_CERT_BYTES_SHOULD_NOT_LEAK';
    $decoyOcspPayload = 'DECOY_DSS_POST_XREF_OCSP_BYTES_SHOULD_NOT_LEAK';
    $staleVriKey = strtoupper(hash('sha1', $staleCertPayload));
    $currentVriKey = strtoupper(hash('sha1', $currentCertPayload));
    $decoyVriKey = strtoupper(hash('sha1', $decoyCertPayload));

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleOffsets = [];
    $staleOffsets[1] = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /DSS 60 0 R >>');
    $staleOffsets[2] = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $staleOffsets[3] = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>');
    $staleOffsets[4] = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $staleOffsets[5] = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleOffsets[60] = $addObject(60, 0, "<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$staleVriKey} 61 0 R >> >>");
    $staleOffsets[61] = $addObject(61, 0, '<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260607194000Z) >>');
    $staleOffsets[70] = $addObject(70, 0, '<< /Length ' . strlen($staleCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$staleCertPayload}\nendstream");
    $staleOffsets[71] = $addObject(71, 0, '<< /Length ' . strlen($staleOcspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$staleOcspPayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 72\n" . $xrefTableRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 71; $objectNumber++) {
        $pdf .= isset($staleOffsets[$objectNumber])
            ? $xrefTableRow($staleOffsets[$objectNumber])
            : $xrefTableRow(0, 0, 'f');
    }
    $pdf .= "trailer\n<< /Size 72 /Root 1 0 R >>\nstartxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /DSS 60 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(60, 0, "<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$currentVriKey} 61 0 R >> >>");
    $addObject(61, 0, '<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260607211500Z) >>');
    $addObject(70, 0, '<< /Length ' . strlen($currentCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$currentCertPayload}\nendstream");
    $addObject(71, 0, '<< /Length ' . strlen($currentOcspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$currentOcspPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $staleOffsets[1], 0)
        . $xrefStreamRow(1, $staleOffsets[2], 0)
        . $xrefStreamRow(1, $staleOffsets[3], 0)
        . $xrefStreamRow(1, $staleOffsets[4], 0)
        . $xrefStreamRow(1, $staleOffsets[60], 0)
        . $xrefStreamRow(1, $staleOffsets[61], 0)
        . $xrefStreamRow(1, $staleOffsets[70], 0)
        . $xrefStreamRow(1, $staleOffsets[71], 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress DSS xref Prev-chain rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 72 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 4 60 2 70 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF\n"
        . "60 0 obj\n<< /Type /DSS /Certs [90 0 R] /OCSPs [91 0 R] /VRI << /{$decoyVriKey} 92 0 R >> >>\nendobj\n"
        . "90 0 obj\n<< /Length " . strlen($decoyCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$decoyCertPayload}\nendstream\nendobj\n"
        . "91 0 obj\n<< /Length " . strlen($decoyOcspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$decoyOcspPayload}\nendstream\nendobj\n"
        . "92 0 obj\n<< /Type /VRI /Cert [90 0 R] /OCSP [91 0 R] /TU (D:20260607220000Z) >>\nendobj\n";

    return [
        $pdf,
        $currentVriKey,
        $decoyVriKey,
        hash('sha256', $currentCertPayload),
        hash('sha256', $currentOcspPayload),
        [$staleCertPayload, $staleOcspPayload, $decoyCertPayload, $decoyOcspPayload, $currentCertPayload, $currentOcspPayload],
    ];
};

$xrefPrevChainDssOmittedGraphCurrentBasePdf = static function (): array {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale DSS omitted graph page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current DSS omitted graph page) Tj ET';
    $staleCertPayload = 'STALE_DSS_OMITTED_GRAPH_CERT_BYTES_SHOULD_NOT_LEAK';
    $staleOcspPayload = 'STALE_DSS_OMITTED_GRAPH_OCSP_BYTES_SHOULD_NOT_LEAK';
    $currentCertPayload = 'CURRENT_DSS_OMITTED_GRAPH_CERT_BYTES_SHOULD_NOT_LEAK';
    $currentOcspPayload = 'CURRENT_DSS_OMITTED_GRAPH_OCSP_BYTES_SHOULD_NOT_LEAK';
    $staleVriKey = strtoupper(hash('sha1', $staleCertPayload));
    $currentVriKey = strtoupper(hash('sha1', $currentCertPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /DSS 60 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(60, 0, "<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$staleVriKey} 61 0 R >> >>");
    $addObject(61, 0, '<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260607230000Z) >>');
    $addObject(70, 0, '<< /Length ' . strlen($staleCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$staleCertPayload}\nendstream");
    $addObject(71, 0, '<< /Length ' . strlen($staleOcspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$staleOcspPayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 72\n" . $xrefTableRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 71; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber . ':0'])
            ? $xrefTableRow($offsets[$objectNumber . ':0'])
            : $xrefTableRow(0, 0, 'f');
    }
    $pdf .= "trailer\n<< /Size 72 /Root 1 0 R >>\nstartxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /DSS 60 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Contents 4 1 R /Resources << /Font << /F1 5 0 R >> >> >>');
    $addObject(4, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(60, 1, "<< /Type /DSS /Certs [70 1 R] /OCSPs [71 1 R] /VRI << /{$currentVriKey} 61 1 R >> >>");
    $addObject(61, 1, '<< /Type /VRI /Cert [70 1 R] /OCSP [71 1 R] /TU (D:20260607233000Z) >>');
    $addObject(70, 1, '<< /Length ' . strlen($currentCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$currentCertPayload}\nendstream");
    $addObject(71, 1, '<< /Length ' . strlen($currentOcspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$currentOcspPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:1'], 1)
        . $xrefStreamRow(1, $offsets['2:1'], 1)
        . $xrefStreamRow(1, $offsets['3:1'], 1)
        . $xrefStreamRow(1, $offsets['4:1'], 1)
        . $xrefStreamRow(1, $offsets['5:0'], 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress DSS omitted-graph xref Prev-chain rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 72 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [
        $pdf,
        $currentVriKey,
        $staleVriKey,
        hash('sha256', $currentCertPayload),
        hash('sha256', $currentOcspPayload),
        [$staleCertPayload, $staleOcspPayload, $currentCertPayload, $currentOcspPayload],
    ];
};

return [
    'selects DSS validation streams from current xref Prev-chain objects before stale and post-xref decoys' => static function (TestRunner $t) use ($xrefPrevChainDssCurrentBasePdf): void {
        [$pdf, $currentVriKey, $decoyVriKey, $certHash, $ocspHash, $rawPayloads] = $xrefPrevChainDssCurrentBasePdf();

        $store = (new PdfDocumentSecurityStoreExtractor())->extract($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);
        $encodedStore = json_encode($store, JSON_UNESCAPED_SLASHES);

        $t->same('Current DSS Prev chain page', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(true, $store['present']);
        $t->same(60, $store['object_number']);
        $t->same('DSS', $store['type']);
        $t->same(1, $store['cert_count']);
        $t->same(1, $store['ocsp_count']);
        $t->same(0, $store['crl_count']);
        $t->same(1, $store['vri_count']);
        $t->same([$currentVriKey], $store['vri_keys']);
        $t->same(2, $store['total_validation_stream_count']);
        $t->same($certHash, $store['global_certificates'][0]['sha256'] ?? null);
        $t->same($ocspHash, $store['global_ocsps'][0]['sha256'] ?? null);
        $t->same($certHash, $store['vri'][0]['certificates'][0]['sha256'] ?? null);
        $t->same($ocspHash, $store['vri'][0]['ocsps'][0]['sha256'] ?? null);
        $t->same([], $store['unresolved_cert_refs']);
        $t->same([], $store['unresolved_ocsp_refs']);
        $t->same(false, $store['raw_validation_bytes_exposed']);
        $t->same(false, $store['executes_signature_validation']);
        $t->same(false, $store['executes_revocation_check']);
        $t->same(false, $store['executes_trust_chain_validation']);
        $t->same(false, $store['executes_external_pdf_tools']);

        $t->same(1, $preflight['document_security_store_count']);
        $t->same([$currentVriKey], $preflight['document_security_store']['vri_keys'] ?? []);
        $t->true(!in_array($decoyVriKey, $store['vri_keys'], true));
        foreach ($rawPayloads as $payload) {
            $t->true(is_string($encodedStore) && !str_contains($encodedStore, $payload));
        }
    },
    'repairs omitted DSS validation graph objects from current trailer Root before stale Prev rows' => static function (TestRunner $t) use ($xrefPrevChainDssOmittedGraphCurrentBasePdf): void {
        [$pdf, $currentVriKey, $staleVriKey, $certHash, $ocspHash, $rawPayloads] = $xrefPrevChainDssOmittedGraphCurrentBasePdf();

        $store = (new PdfDocumentSecurityStoreExtractor())->extract($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);
        $encodedStore = json_encode($store, JSON_UNESCAPED_SLASHES);

        $t->same('Current DSS omitted graph page', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(true, $store['present']);
        $t->same(60, $store['object_number']);
        $t->same('DSS', $store['type']);
        $t->same(1, $store['cert_count']);
        $t->same(1, $store['ocsp_count']);
        $t->same(0, $store['crl_count']);
        $t->same(1, $store['vri_count']);
        $t->same([$currentVriKey], $store['vri_keys']);
        $t->same(2, $store['total_validation_stream_count']);
        $t->same($certHash, $store['global_certificates'][0]['sha256'] ?? null);
        $t->same($ocspHash, $store['global_ocsps'][0]['sha256'] ?? null);
        $t->same($certHash, $store['vri'][0]['certificates'][0]['sha256'] ?? null);
        $t->same($ocspHash, $store['vri'][0]['ocsps'][0]['sha256'] ?? null);
        $t->same([], $store['unresolved_cert_refs']);
        $t->same([], $store['unresolved_ocsp_refs']);
        $t->same(false, $store['raw_validation_bytes_exposed']);
        $t->same(false, $store['executes_signature_validation']);
        $t->same(false, $store['executes_revocation_check']);
        $t->same(false, $store['executes_trust_chain_validation']);
        $t->same(false, $store['executes_external_pdf_tools']);
        $t->same([$currentVriKey], $preflight['document_security_store']['vri_keys'] ?? []);
        $t->true(!in_array($staleVriKey, $store['vri_keys'], true));
        foreach ($rawPayloads as $payload) {
            $t->true(is_string($encodedStore) && !str_contains($encodedStore, $payload));
        }
    },
];
