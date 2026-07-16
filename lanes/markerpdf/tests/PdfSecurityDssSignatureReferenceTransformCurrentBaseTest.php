<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$dssSignatureReferenceTransformPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (DSS signature reference transform import) Tj ET';
    $signaturePayload = 'DSS_REFERENCE_TRANSFORM_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $orphanVriKey = '0123456789ABCDEF';
    $certPayload = 'DSS_REFERENCE_TRANSFORM_CERT_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'DSS_REFERENCE_TRANSFORM_OCSP_BYTES_SHOULD_NOT_LEAK';
    $orphanCrlPayload = 'DSS_REFERENCE_TRANSFORM_ORPHAN_CRL_BYTES_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.certification) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed title) /Kids [11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (internal.notes) /V (Internal review note) >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (DSS Reference Reviewer) /M (D:20260602205800Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R 32 0 R << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 35 0 R >>] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams 33 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 34 0 R >>\nendobj\n"
        . "33 0 obj\n<< /Type /TransformParams /V /1.2 /P 2 >>\nendobj\n"
        . "34 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [9 0 R (internal.notes)] >>\nendobj\n"
        . "35 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn /Export] /Signature [/Modify] /Annots [/Create] /EF [/Create] /Msg (Usage rights review only) >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R /{$orphanVriKey} 62 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602205830Z) >>\nendobj\n"
        . "62 0 obj\n<< /Type /VRI /CRL [72 0 R] /TU (D:20260602205840Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
        . "72 0 obj\n<< /Length " . strlen($orphanCrlPayload) . " /Subtype /application#2Fpkix-crl >>\nstream\n{$orphanCrlPayload}\nendstream\nendobj\n"
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

    return [
        $pdf,
        $signaturePayload,
        $vriKey,
        $orphanVriKey,
        hash('sha256', $certPayload),
        hash('sha256', $ocspPayload),
        hash('sha256', $orphanCrlPayload),
    ];
};

return [
    'correlates DSS VRI signature rows with signature Reference transform metadata without validation' => static function (TestRunner $t) use ($dssSignatureReferenceTransformPdf): void {
        [
            $pdf,
            $signaturePayload,
            $vriKey,
            $orphanVriKey,
            $certHash,
            $ocspHash,
            $orphanCrlHash,
        ] = $dssSignatureReferenceTransformPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $dssReview = $report['document_security_store_signature_review'];
        $transformReview = $report['document_security_store_signature_reference_transform_review'];
        $matchedRow = $dssReview['vri_signature_rows'][0] ?? [];
        $orphanRow = $dssReview['vri_signature_rows'][1] ?? [];
        $transformRows = $matchedRow['signature_reference_transform_rows'] ?? [];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('DSS signature reference transform import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same(['signed_signature_present', 'signature_reference_transforms_present', 'document_security_store_present'], $report['review_reasons']);
        $t->same(3, $report['signature_reference_transform_count']);
        $t->same(['DocMDP', 'FieldMDP', 'UR3'], $report['signature_reference_transform_methods']);

        $t->same('document_security_store_signature_reference_transform_review', $transformReview['source']);
        $t->same(true, $transformReview['present']);
        $t->same(2, $transformReview['vri_count']);
        $t->same(1, $transformReview['vri_with_reference_transform_count']);
        $t->same(3, $transformReview['signature_reference_transform_count']);
        $t->same(['DocMDP', 'FieldMDP', 'UR3'], $transformReview['signature_reference_transform_methods']);
        $t->same(['certification_permissions', 'field_modification_permissions', 'usage_rights_ur3'], $transformReview['signature_reference_transform_categories']);
        $t->same(1, $transformReview['doc_mdp_reference_transform_count']);
        $t->same(1, $transformReview['field_mdp_reference_transform_count']);
        $t->same(1, $transformReview['usage_rights_reference_transform_count']);
        $t->same([$vriKey], $transformReview['vri_keys']);
        $t->same([30], $transformReview['matched_signature_objects']);
        $t->same(['approval.certification'], $transformReview['matched_field_names']);
        $t->same(false, $transformReview['executes_signature_validation']);
        $t->same(false, $transformReview['executes_rights_enforcement']);
        $t->same(false, $transformReview['raw_signature_contents_exposed']);
        $t->same(false, $transformReview['raw_digest_values_exposed']);

        $t->same($vriKey, $matchedRow['key']);
        $t->same('matched_signature_contents_sha1', $matchedRow['match_status']);
        $t->same(3, $matchedRow['signature_reference_transform_count']);
        $t->same(['DocMDP', 'FieldMDP', 'UR3'], $matchedRow['signature_reference_transform_methods']);
        $t->same($certHash, $matchedRow['validation_hashes']['certificates'][0]);
        $t->same($ocspHash, $matchedRow['validation_hashes']['ocsps'][0]);

        $t->same('DocMDP', $transformRows[0]['transform_method']);
        $t->same('certification_permissions', $transformRows[0]['transform_category']);
        $t->same(31, $transformRows[0]['transform_object']);
        $t->same(33, $transformRows[0]['transform_params_object']);
        $t->same(2, $transformRows[0]['permission_level']);
        $t->same('form_fill_templates_signatures', $transformRows[0]['permission_label']);
        $t->same(['fill_forms', 'instantiate_page_templates', 'sign'], $transformRows[0]['allowed_changes']);

        $t->same('FieldMDP', $transformRows[1]['transform_method']);
        $t->same('field_modification_permissions', $transformRows[1]['transform_category']);
        $t->same(32, $transformRows[1]['transform_object']);
        $t->same('SHA256', $transformRows[1]['digest_method']);
        $t->same(true, $transformRows[1]['digest_value_present']);
        $t->same(false, $transformRows[1]['digest_value_exposed']);
        $t->same('Include', $transformRows[1]['field_mdp_action']);
        $t->same('locks_included_fields', $transformRows[1]['field_mdp_action_label']);
        $t->same(['article.title', 'internal.notes'], $transformRows[1]['field_mdp_field_names']);
        $t->same(['article.title', 'internal.notes'], $transformRows[1]['field_mdp_included_fields']);

        $t->same('UR3', $transformRows[2]['transform_method']);
        $t->same('usage_rights_ur3', $transformRows[2]['transform_category']);
        $t->same(null, $transformRows[2]['transform_object']);
        $t->same(['document', 'form', 'signature', 'annotations', 'embedded_files'], $transformRows[2]['usage_right_categories']);
        $t->same(6, $transformRows[2]['usage_right_count']);
        $t->same(['FillIn', 'Export'], $transformRows[2]['usage_rights']['form']);
        $t->same(false, $transformRows[2]['executes_rights_enforcement']);

        $t->same($orphanVriKey, $orphanRow['key']);
        $t->same('no_matching_signature_contents_digest', $orphanRow['match_status']);
        $t->same(0, $orphanRow['signature_reference_transform_count']);
        $t->same([], $orphanRow['signature_reference_transform_methods']);
        $t->same([], $orphanRow['signature_reference_transform_rows']);
        $t->same($orphanCrlHash, $orphanRow['validation_hashes']['crls'][0]);

        $t->same(3, $report['document_security_store_signature_reference_transform_count']);
        $t->same(['DocMDP', 'FieldMDP', 'UR3'], $report['document_security_store_signature_reference_transform_methods']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_revocation_check']);
        $t->same(false, $report['executes_trust_chain_validation']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'DSS_REFERENCE_TRANSFORM_CERT_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_REFERENCE_TRANSFORM_OCSP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_REFERENCE_TRANSFORM_ORPHAN_CRL_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DEADC0DE'));
    },
    'keeps DSS signature reference transform payloads out of visible WordPress text' => static function (TestRunner $t) use ($dssSignatureReferenceTransformPdf): void {
        [$pdf] = $dssSignatureReferenceTransformPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('DSS signature reference transform import', $plainText);
        $t->true(!str_contains($plainText, 'DSS_REFERENCE_TRANSFORM_SIGNATURE_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'DSS_REFERENCE_TRANSFORM_CERT_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'DSS_REFERENCE_TRANSFORM_OCSP_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'Usage rights review only'));
    },
];
