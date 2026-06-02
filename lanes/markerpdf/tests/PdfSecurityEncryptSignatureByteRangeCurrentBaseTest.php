<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedSignedValidByteRangePdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Encrypted signed cleartext leak) Tj ET';
    $signaturePayload = 'ENCRYPTED_VALID_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.encryptedSignature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Encrypted Signature Reviewer) /M (D:20260602190722Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} >>\nendobj\n"
        . "31 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 31 0 R >>\n%%EOF";

    $gapStart = strpos($pdf, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in encrypted signature fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);

    return [$pdf, $signaturePayload];
};

return [
    'keeps encrypted PDFs blocked even when signature ByteRange covers the signed revision' => static function (TestRunner $t) use ($encryptedSignedValidByteRangePdf): void {
        [$pdf, $signaturePayload] = $encryptedSignedValidByteRangePdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $signature = $report['signatures'][0] ?? [];
        $review = $report['encrypted_signature_byte_range_review'];
        $row = $review['rows'][0] ?? [];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required', 'encrypted_signature_byte_range_present'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption', 'signature_validation', 'signing'], $report['blocked_operations']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same(1, $report['signature_field_count']);
        $t->same(1, $report['signed_signature_count']);
        $t->same(1, $report['signature_byte_range_count']);
        $t->same(1, $report['valid_signature_byte_range_count']);
        $t->same(0, $report['invalid_signature_byte_range_count']);
        $t->same(1, $report['encrypted_signature_byte_range_review_count']);

        $t->same('approval.encryptedSignature', $signature['field_name'] ?? null);
        $t->same(true, $signature['signed'] ?? null);
        $t->same(true, $signature['byte_range']['valid'] ?? null);
        $t->same('covers_file_except_signature_contents', $signature['byte_range']['status'] ?? null);
        $t->same(true, $signature['byte_range']['has_signature_contents_gap'] ?? null);
        $t->same('review_required_signature_metadata', $signature['signature_security_review']['review_decision'] ?? null);
        $t->same(strlen($signaturePayload), $signature['contents_digest']['bytes'] ?? null);
        $t->same(hash('sha256', $signaturePayload), $signature['contents_digest']['sha256'] ?? null);
        $t->same(false, $signature['contents_digest']['raw_bytes_exposed'] ?? null);

        $t->same('encrypted_signature_byte_range_review', $review['source']);
        $t->same(true, $review['present']);
        $t->same(true, $review['encrypted']);
        $t->same(1, $review['byte_range_count']);
        $t->same(1, $review['valid_byte_range_count']);
        $t->same(0, $review['invalid_byte_range_count']);
        $t->same(['covers_file_except_signature_contents'], $review['byte_range_statuses']);
        $t->same(['approval.encryptedSignature'], $review['field_names']);
        $t->same('copy_extract_allowed_after_decryption', $review['policy']);
        $t->same('blocked_until_decryption_password_available', $review['content_extraction_boundary']);
        $t->same(true, $review['requires_password_for_content_extraction']);
        $t->same(false, $review['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $review['text_extraction_policy']);
        $t->same(true, $review['byte_range_does_not_grant_import']);
        $t->same(false, $review['executes_decryption']);
        $t->same(false, $review['executes_signature_validation']);
        $t->same(false, $review['executes_signing']);
        $t->same(false, $review['raw_signature_contents_exposed']);
        $t->same(false, $review['raw_key_material_exposed']);

        $t->same('approval.encryptedSignature', $row['field_name'] ?? null);
        $t->same(true, $row['byte_range_valid'] ?? null);
        $t->same('covers_file_except_signature_contents', $row['byte_range_status'] ?? null);
        $t->same(true, $row['byte_range_covers_signature_contents'] ?? null);
        $t->same('review_required_signature_metadata', $row['signature_review_decision'] ?? null);
        $t->same(false, $row['native_text_extraction_allowed_now'] ?? null);
        $t->same(true, $row['byte_range_structural_review_only'] ?? null);
        $t->same(true, $row['byte_range_does_not_grant_import'] ?? null);
        $t->same(false, $row['cryptographic_signature_validated'] ?? null);

        $t->true(is_string($encoded)
            && !str_contains($encoded, 'Encrypted signed cleartext leak')
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'DEADBEEF')
            && !str_contains($encoded, 'CAFEFEED'));
    },
    'keeps valid encrypted signature bytes out of visible WordPress text' => static function (TestRunner $t) use ($encryptedSignedValidByteRangePdf): void {
        [$pdf, $signaturePayload] = $encryptedSignedValidByteRangePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('', $plainText);
        $t->true(!str_contains($plainText, 'Encrypted signed cleartext leak'));
        $t->true(!str_contains($plainText, $signaturePayload));
        $t->true(!str_contains($plainText, strtoupper(bin2hex($signaturePayload))));
    },
];
