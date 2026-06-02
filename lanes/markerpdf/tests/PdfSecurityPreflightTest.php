<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedSignedPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Encrypted cleartext leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /M (D:20260602091100Z) /ByteRange [0 12 9999 10] /Contents <010203> >>\nendobj\n"
        . "31 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 31 0 R >>\n%%EOF";
};

$signedPdfWithValidByteRange = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Signed import content) Tj ET';
    $signatureContentsHex = str_repeat('0', 128);
    $signatureContentsToken = '<' . $signatureContentsHex . '>';
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 1 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /M (D:20260602091200Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} >>\nendobj\n"
        . "%%EOF";

    $gapStart = strpos($pdf, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in focused fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);

    return strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);
};

$encryptedPdfAllowingCopy = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Copy-permitted encrypted leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";
};

$encryptedPdfWithoutStandardPermissions = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Unknown-permission encrypted leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /PublicKey /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";
};

$signedPdfWithReferenceTransforms = static function (): string {
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

    return strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);
};

$signedPdfWithDssValidationStore = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Signed DSS review content) Tj ET';
    $signatureContentsHex = str_repeat('B', 96);
    $signatureContentsToken = '<' . $signatureContentsHex . '>';
    $certBytes = 'SIGNER_CERTIFICATE_DER_BYTES_SHOULD_NOT_LEAK';
    $ocspBytes = 'OCSP_RESPONSE_DER_BYTES_SHOULD_NOT_LEAK';
    $crlBytes = 'CRL_BYTES_SHOULD_NOT_LEAK';
    $timestampBytes = 'TIMESTAMP_TOKEN_BYTES_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (DSS Reviewer) /M (D:20260602133500Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /CRLs [72 0 R] /VRI << /ABCDEF1234 61 0 R /Stale#20Name 62 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /CRL [72 0 R] /TU (D:20260602133500Z) /TS 73 0 R >>\nendobj\n"
        . "62 0 obj\n<< /Type /VRI /Cert [999 0 R] /OCSP [] /CRL [] /TU (D:20260602133000Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($certBytes) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certBytes}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($ocspBytes) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspBytes}\nendstream\nendobj\n"
        . "72 0 obj\n<< /Length " . strlen($crlBytes) . " /Subtype /application#2Fpkix-crl >>\nstream\n{$crlBytes}\nendstream\nendobj\n"
        . "73 0 obj\n<< /Length " . strlen($timestampBytes) . " /Subtype /application#2Ftst-info >>\nstream\n{$timestampBytes}\nendstream\nendobj\n"
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
        hash('sha256', $certBytes),
        hash('sha256', $ocspBytes),
        hash('sha256', $crlBytes),
        hash('sha256', $timestampBytes),
    ];
};

return [
    'blocks encrypted text extraction and quarantines invalid signature byte ranges' => static function (TestRunner $t) use ($encryptedSignedPdf): void {
        $pdf = $encryptedSignedPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('pdf_security_preflight', $report['source']);
        $t->true($report['encrypted']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_denied', 'signature_byte_range_invalid'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption', 'signature_validation', 'signing'], $report['blocked_operations']);
        $t->same('Standard', $report['encryption']['filter']);
        $t->same('FFFFFFC0', $report['encryption']['permission_hex']);
        $t->same(false, $report['encryption']['copy_or_extract_allowed']);
        $t->same(false, $report['encryption']['raw_key_material_exposed']);
        $t->same(1, $report['signature_field_count']);
        $t->same(1, $report['signed_signature_count']);
        $t->same(1, $report['invalid_signature_byte_range_count']);
        $t->same('approval.signature', $report['signatures'][0]['field_name']);
        $t->same(false, $report['signatures'][0]['byte_range']['valid']);
        $t->same('invalid_out_of_bounds', $report['signatures'][0]['byte_range']['status']);
        $t->same(false, $report['signatures'][0]['cryptographic_signature_validated']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_signing']);
        $t->same(false, $report['executes_python_or_models']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'DEADBEEF') && !str_contains($encoded, 'CAFEFEED') && !str_contains($encoded, '010203'));
    },
    'allows unencrypted text while requiring review-only signature preflight for covered byte ranges' => static function (TestRunner $t) use ($signedPdfWithValidByteRange): void {
        $pdf = $signedPdfWithValidByteRange();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $signature = $report['signatures'][0];

        $t->same('Signed import content', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(false, $report['encrypted']);
        $t->same(true, $report['content_extraction_allowed']);
        $t->same('native_text_allowed', $report['text_extraction_policy']);
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same(['signed_signature_present'], $report['review_reasons']);
        $t->same(1, $report['signature_field_count']);
        $t->same(1, $report['signed_signature_count']);
        $t->same(0, $report['invalid_signature_byte_range_count']);
        $t->true($signature['signed']);
        $t->same(64, $signature['contents_length_bytes']);
        $t->same(true, $signature['byte_range']['valid']);
        $t->same('covers_file_except_signature_contents', $signature['byte_range']['status']);
        $t->same(1, $signature['byte_range']['gap_count']);
        $t->same(strlen('<' . str_repeat('0', 128) . '>'), $signature['byte_range']['gaps'][0]['length']);
        $t->same(true, $signature['byte_range']['has_signature_contents_gap']);
        $t->same(false, $signature['cryptographic_signature_validated']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_signing']);
        $t->same(false, $report['executes_external_pdf_tools']);
    },
    'distinguishes copy-permitted encrypted PDFs from importable decrypted content' => static function (TestRunner $t) use ($encryptedPdfAllowingCopy): void {
        $pdf = $encryptedPdfAllowingCopy();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->true($report['encrypted']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);
        $t->same('FFFFFFD4', $report['encryption']['permission_hex']);
        $t->same(true, $report['encryption']['copy_or_extract_allowed']);
        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same(true, $permission['permissions_declared']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same(['print', 'copy_or_extract', 'fill_form_fields', 'extract_for_accessibility', 'assemble_document', 'high_quality_print'], $permission['allowed']);
        $t->same(['modify_contents', 'add_or_modify_annotations'], $permission['denied']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['accessibility_extract_allowed']);
        $t->same(true, $permission['requires_password_for_content_extraction']);
        $t->same(false, $permission['decryption_performed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(false, $permission['raw_key_material_exposed']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
    },
    'marks encrypted PDFs with unavailable Standard permissions as unknown review-only boundaries' => static function (TestRunner $t) use ($encryptedPdfWithoutStandardPermissions): void {
        $pdf = $encryptedPdfWithoutStandardPermissions();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->true($report['encrypted']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown'], $report['review_reasons']);
        $t->same('PublicKey', $report['encryption']['filter']);
        $t->same('adbe.pkcs7.s5', $report['encryption']['subfilter']);
        $t->same('encryption_dictionary_without_standard_permissions', $permission['source']);
        $t->same(false, $permission['permissions_declared']);
        $t->same(null, $permission['permission_hex']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(null, $permission['accessibility_extract_allowed']);
        $t->same('permissions_unknown_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_unknown', $permission['content_extraction_boundary']);
        $t->same(false, $permission['decryption_performed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same(false, $permission['raw_key_material_exposed']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unknown-permission encrypted leak'));
    },
    'summarizes FieldMDP and usage-rights signature reference transforms without enforcing signatures' => static function (TestRunner $t) use ($signedPdfWithReferenceTransforms): void {
        $pdf = $signedPdfWithReferenceTransforms();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $signature = $report['signatures'][0];
        $fieldMdp = $signature['reference_transforms'][0];
        $usageRights = $signature['reference_transforms'][1];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
        $rawSignatureHex = str_repeat('A', 96);

        $t->same('Signed transform review content', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(false, $report['encrypted']);
        $t->same(true, $report['content_extraction_allowed']);
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same(['signed_signature_present', 'signature_reference_transforms_present'], $report['review_reasons']);
        $t->same(1, $report['signature_field_count']);
        $t->same(1, $report['signed_signature_count']);
        $t->same(0, $report['invalid_signature_byte_range_count']);
        $t->same(2, $report['signature_reference_transform_count']);
        $t->same(['FieldMDP', 'UR3'], $report['signature_reference_transform_methods']);

        $t->same(2, $signature['reference_transform_count']);
        $t->same(['FieldMDP', 'UR3'], $signature['reference_transform_methods']);
        $t->same(true, $signature['byte_range']['valid']);
        $t->same('FieldMDP', $fieldMdp['transform_method']);
        $t->same('SigRef', $fieldMdp['type']);
        $t->same(5, $fieldMdp['data_object']);
        $t->same('SHA256', $fieldMdp['digest_method']);
        $t->same(true, $fieldMdp['digest_value_present']);
        $t->same(false, $fieldMdp['digest_value_exposed']);
        $t->same('field_modification_permissions', $fieldMdp['transform_category']);
        $t->same('TransformParams', $fieldMdp['transform_params_type']);
        $t->same('1.2', $fieldMdp['transform_params_version']);
        $t->same('Include', $fieldMdp['action']);
        $t->same(true, $fieldMdp['action_valid']);
        $t->same('locks_included_fields', $fieldMdp['action_label']);
        $t->same(['invoice.total', 'internal.notes'], $fieldMdp['field_names']);
        $t->same(['invoice.total', 'internal.notes'], $fieldMdp['included_fields']);
        $t->same([], $fieldMdp['excluded_fields']);
        $t->same(false, $fieldMdp['executes_signature_validation']);
        $t->same(false, $fieldMdp['executes_action']);

        $t->same('UR3', $usageRights['transform_method']);
        $t->same('usage_rights_ur3', $usageRights['transform_category']);
        $t->same('2.2', $usageRights['transform_params_version']);
        $t->same('Reader rights review only', $usageRights['message']);
        $t->same(['document', 'form', 'signature', 'annotations', 'embedded_files'], $usageRights['right_categories']);
        $t->same(['FullSave'], $usageRights['rights']['document']);
        $t->same(['FillIn', 'Import', 'Export'], $usageRights['rights']['form']);
        $t->same(['Modify'], $usageRights['rights']['signature']);
        $t->same(['Create', 'Modify'], $usageRights['rights']['annotations']);
        $t->same(['Create'], $usageRights['rights']['embedded_files']);
        $t->same(8, $usageRights['right_count']);
        $t->same(false, $usageRights['executes_rights_enforcement']);
        $t->same(false, $usageRights['executes_signature_validation']);
        $t->same(false, $usageRights['executes_action']);

        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_signing']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'DEADC0DE') && !str_contains($encoded, $rawSignatureHex));
    },
    'summarizes catalog DSS validation material without validating signatures or revocation' => static function (TestRunner $t) use ($signedPdfWithDssValidationStore): void {
        [$pdf, $certHash, $ocspHash, $crlHash, $timestampHash] = $signedPdfWithDssValidationStore();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $dss = $report['document_security_store'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('Signed DSS review content', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same(['signed_signature_present', 'document_security_store_present'], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation'], $report['blocked_operations']);
        $t->same(1, $report['signature_field_count']);
        $t->same(1, $report['signed_signature_count']);
        $t->same(1, $report['document_security_store_count']);

        $t->true($dss['present']);
        $t->same('catalog_dss_dictionary', $dss['source']);
        $t->same(60, $dss['object_number']);
        $t->same(1, $dss['cert_count']);
        $t->same(1, $dss['ocsp_count']);
        $t->same(1, $dss['crl_count']);
        $t->same(2, $dss['vri_count']);
        $t->same(4, $dss['total_validation_stream_count']);
        $t->same(['ABCDEF1234', 'Stale Name'], $dss['vri_keys']);
        $t->same(false, $dss['raw_validation_bytes_exposed']);
        $t->same(false, $dss['executes_signature_validation']);
        $t->same(false, $dss['executes_revocation_check']);
        $t->same(false, $dss['executes_external_pdf_tools']);

        $t->same($certHash, $dss['global_certificates'][0]['sha256']);
        $t->same(strlen('SIGNER_CERTIFICATE_DER_BYTES_SHOULD_NOT_LEAK'), $dss['global_certificates'][0]['length_bytes']);
        $t->same('application/pkix-cert', $dss['global_certificates'][0]['subtype']);
        $t->same($ocspHash, $dss['global_ocsps'][0]['sha256']);
        $t->same($crlHash, $dss['global_crls'][0]['sha256']);

        $vri = $dss['vri'][0];
        $t->same('ABCDEF1234', $vri['key']);
        $t->same(61, $vri['object_number']);
        $t->same(1, $vri['cert_count']);
        $t->same(1, $vri['ocsp_count']);
        $t->same(1, $vri['crl_count']);
        $t->same('D:20260602133500Z', $vri['timestamp_update']);
        $t->same($timestampHash, $vri['timestamp_token']['sha256']);
        $t->same(false, $vri['timestamp_token']['raw_bytes_exposed']);
        $t->same([999], $dss['vri'][1]['unresolved_cert_refs']);

        $t->true(is_string($encoded)
            && !str_contains($encoded, 'SIGNER_CERTIFICATE_DER_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'OCSP_RESPONSE_DER_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'CRL_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'TIMESTAMP_TOKEN_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, str_repeat('B', 96)));
    },
];
