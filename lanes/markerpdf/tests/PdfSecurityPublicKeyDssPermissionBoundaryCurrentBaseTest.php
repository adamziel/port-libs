<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$publicKeyDssPermissionBoundaryPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Public-key DSS permission boundary leak) Tj ET';
    $documentRecipient = 'BOUNDARY_DOCUMENT_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
    $embeddedFileRecipient = 'BOUNDARY_EMBEDDED_FILE_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
    $legacyRecipient = 'BOUNDARY_LEGACY_S5_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
    $unusedRecipient = 'BOUNDARY_UNUSED_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
    $signaturePayload = 'BOUNDARY_DSS_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $certPayload = 'BOUNDARY_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'BOUNDARY_DSS_OCSP_BYTES_SHOULD_NOT_LEAK';
    $documentRecipientHex = strtoupper(bin2hex($documentRecipient));
    $embeddedFileRecipientHex = strtoupper(bin2hex($embeddedFileRecipient));
    $legacyRecipientHex = strtoupper(bin2hex($legacyRecipient));
    $unusedRecipientHex = strtoupper(bin2hex($unusedRecipient));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.publicKeyBoundary) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Encrypted signed title) >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Public Key Boundary Reviewer) /M (D:20260602224641Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R 33 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [9 0 R] >>\nendobj\n"
        . "33 0 obj\n<< /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 34 0 R >>\nendobj\n"
        . "34 0 obj\n<< /Type /TransformParams /V /2.2 /Form [/FillIn /Export] /EF [/Create] /Msg (Public-key DSS rights review only) >>\nendobj\n"
        . "50 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128 /Recipients [<{$legacyRecipientHex}>]"
        . " /CF <<"
        . " /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$documentRecipientHex}>] >>"
        . " /EmbeddedFiles << /CFM /AESV2 /AuthEvent /EFOpen /Length 16 /Recipients 51 0 R >>"
        . " /UnusedRights << /CFM /V2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$unusedRecipientHex}>] >>"
        . " >> /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EFF /EmbeddedFiles /EncryptMetadata true >>\nendobj\n"
        . "51 0 obj\n[<{$embeddedFileRecipientHex}>]\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602224641Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 50 0 R >>\n%%EOF";

    $gapStart = strpos($pdf, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in public-key boundary fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);

    return [
        $pdf,
        $documentRecipient,
        $embeddedFileRecipient,
        $legacyRecipient,
        $unusedRecipient,
        $signaturePayload,
        $documentRecipientHex,
        $embeddedFileRecipientHex,
        $legacyRecipientHex,
        $unusedRecipientHex,
        hash('sha256', $certPayload),
        hash('sha256', $ocspPayload),
    ];
};

return [
    'summarizes public-key recipient and DSS signature permission boundaries without granting import' => static function (
        TestRunner $t
    ) use ($publicKeyDssPermissionBoundaryPdf): void {
        [
            $pdf,
            $documentRecipient,
            $embeddedFileRecipient,
            $legacyRecipient,
            $unusedRecipient,
            $signaturePayload,
            $documentRecipientHex,
            $embeddedFileRecipientHex,
            $legacyRecipientHex,
            $unusedRecipientHex,
            $certHash,
            $ocspHash,
        ] = $publicKeyDssPermissionBoundaryPdf();

        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $boundary = $report['public_key_dss_permission_boundary_review'];
        $dssReview = $report['document_security_store_signature_review'];
        $transformReview = $report['document_security_store_signature_reference_transform_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->true(in_array('public_key_recipient_permissions_undecoded', $report['review_reasons'], true));
        $t->true(in_array('document_security_store_present', $report['review_reasons'], true));
        $t->true(in_array('signature_reference_transforms_present', $report['review_reasons'], true));
        $t->same(['native_text_extraction', 'decryption', 'signature_validation', 'signing', 'revocation_check', 'trust_chain_validation'], $report['blocked_operations']);

        $t->same('crypt_filter_recipients_with_legacy_encryption_dictionary_recipients', $recipientReview['recipient_source_policy']);
        $t->same(4, $recipientReview['recipient_count']);
        $t->same(2, $recipientReview['selected_recipient_count']);
        $t->same(1, $recipientReview['top_level_recipient_count']);
        $t->same(false, $recipientReview['top_level_recipients_selected']);
        $t->same(['crypt_filter_recipients'], $recipientReview['selected_recipient_sources']);
        $t->same('crypt_filter_recipients', $recipientReview['selected_recipient_source_policy']);
        $t->same(['DefaultCryptFilter', 'EmbeddedFiles'], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same(['UnusedRights'], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same([hash('sha256', $documentRecipient), hash('sha256', $embeddedFileRecipient)], $recipientReview['selected_recipient_sha256']);

        $t->same('document_security_store_signature_reference_transform_review', $transformReview['source']);
        $t->same(1, $transformReview['matched_vri_count']);
        $t->same(2, $transformReview['signature_reference_transform_count']);
        $t->same(['FieldMDP', 'UR3'], $transformReview['signature_reference_transform_methods']);
        $t->same(1, $dssReview['signature_vri_match_count']);
        $t->same($certHash, $report['document_security_store']['global_certificates'][0]['sha256']);
        $t->same($ocspHash, $report['document_security_store']['global_ocsps'][0]['sha256']);

        $t->same('public_key_dss_permission_boundary_review', $boundary['source']);
        $t->same(true, $boundary['present']);
        $t->same('blocked_public_key_dss_permission_review_only', $boundary['boundary_decision']);
        $t->same('public_key_recipient_permissions_blocked_without_private_key', $boundary['permission_policy']);
        $t->same('blocked_encrypted_public_key_recipient_permissions', $boundary['content_extraction_boundary']);
        $t->same(false, $boundary['native_text_extraction_allowed_now']);
        $t->same(true, $boundary['requires_private_key_for_permission_review']);
        $t->same(false, $boundary['recipient_permissions_decoded']);
        $t->same('cms_pkcs7_permission_decode_unavailable', $boundary['recipient_permission_decode_status']);
        $t->same(4, $boundary['recipient_count']);
        $t->same(2, $boundary['selected_recipient_count']);
        $t->same(2, $boundary['unselected_recipient_count']);
        $t->same(1, $boundary['top_level_recipient_count']);
        $t->same(false, $boundary['top_level_recipients_selected']);
        $t->same(['DefaultCryptFilter', 'EmbeddedFiles', 'UnusedRights'], $boundary['crypt_filter_recipient_filter_names']);
        $t->same(['DefaultCryptFilter', 'EmbeddedFiles'], $boundary['selected_crypt_filter_recipient_filter_names']);
        $t->same(['UnusedRights'], $boundary['unselected_crypt_filter_recipient_filter_names']);
        $t->same(['stream_filter' => 'DefaultCryptFilter', 'string_filter' => 'DefaultCryptFilter', 'embedded_file_filter' => 'EmbeddedFiles'], $boundary['declared_content_filters']);
        $t->same([hash('sha256', $documentRecipient), hash('sha256', $embeddedFileRecipient)], $boundary['selected_recipient_sha256']);
        $t->same(strlen($documentRecipient) + strlen($embeddedFileRecipient), $boundary['selected_recipient_bytes']);
        $t->same(true, $boundary['document_security_store_present']);
        $t->same(2, $boundary['document_security_store_validation_stream_count']);
        $t->same(1, $boundary['document_security_store_vri_count']);
        $t->same(1, $boundary['document_security_store_signature_match_count']);
        $t->same(0, $boundary['document_security_store_unmatched_vri_count']);
        $t->same(2, $boundary['document_security_store_signature_reference_transform_count']);
        $t->same(['FieldMDP', 'UR3'], $boundary['document_security_store_signature_reference_transform_methods']);
        $t->same(2, $boundary['signature_permission_transform_count']);
        $t->same(['FieldMDP', 'UR3'], $boundary['signature_permission_transform_methods']);
        $t->same(['article.title'], $boundary['field_mdp_field_names']);
        $t->same(['form', 'embedded_files'], $boundary['usage_right_categories']);
        $t->same(true, $boundary['public_key_permissions_do_not_authorize_import_without_private_key']);
        $t->same(true, $boundary['dss_validation_material_does_not_authorize_decryption']);
        $t->same(true, $boundary['signature_permissions_do_not_grant_text_import']);
        $t->same(false, $boundary['executes_cms_parse']);
        $t->same(false, $boundary['executes_decryption']);
        $t->same(false, $boundary['executes_permission_enforcement']);
        $t->same(false, $boundary['executes_rights_enforcement']);
        $t->same(false, $boundary['executes_signature_validation']);
        $t->same(false, $boundary['executes_revocation_check']);
        $t->same(false, $boundary['executes_trust_chain_validation']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->same(1, $report['public_key_dss_permission_boundary_review_count']);

        $t->true(is_string($encoded)
            && !str_contains($encoded, 'Public-key DSS permission boundary leak')
            && !str_contains($encoded, $documentRecipient)
            && !str_contains($encoded, $embeddedFileRecipient)
            && !str_contains($encoded, $legacyRecipient)
            && !str_contains($encoded, $unusedRecipient)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, $documentRecipientHex)
            && !str_contains($encoded, $embeddedFileRecipientHex)
            && !str_contains($encoded, $legacyRecipientHex)
            && !str_contains($encoded, $unusedRecipientHex)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'BOUNDARY_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'BOUNDARY_DSS_OCSP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DEADC0DE'));
    },
    'keeps public-key DSS permission material out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($publicKeyDssPermissionBoundaryPdf): void {
        [$pdf] = $publicKeyDssPermissionBoundaryPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('', $plainText);
        foreach ([
            'Public-key DSS permission boundary leak',
            'BOUNDARY_DOCUMENT_RECIPIENT_BYTES_SHOULD_NOT_LEAK',
            'BOUNDARY_EMBEDDED_FILE_RECIPIENT_BYTES_SHOULD_NOT_LEAK',
            'BOUNDARY_LEGACY_S5_RECIPIENT_BYTES_SHOULD_NOT_LEAK',
            'BOUNDARY_UNUSED_RECIPIENT_BYTES_SHOULD_NOT_LEAK',
            'BOUNDARY_DSS_SIGNATURE_BYTES_SHOULD_NOT_LEAK',
            'BOUNDARY_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK',
            'BOUNDARY_DSS_OCSP_BYTES_SHOULD_NOT_LEAK',
            'Public-key DSS rights review only',
        ] as $hiddenText) {
            $t->true(!str_contains($plainText, $hiddenText));
        }
    },
];
