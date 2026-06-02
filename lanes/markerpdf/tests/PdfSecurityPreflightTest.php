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
];
