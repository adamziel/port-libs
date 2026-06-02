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

$encryptedPublicKeyRecipientPermissionsPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Public-key recipient encrypted leak) Tj ET';
    $recipientOne = 'CRYPT_FILTER_RECIPIENT_ONE_PERMISSION_BYTES_SHOULD_NOT_LEAK';
    $recipientTwo = 'CRYPT_FILTER_RECIPIENT_TWO_PERMISSION_BYTES_SHOULD_NOT_LEAK';
    $recipientOneHex = strtoupper(bin2hex($recipientOne));
    $recipientTwoHex = strtoupper(bin2hex($recipientTwo));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
        . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients 6 0 R >> >>"
        . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
        . "6 0 obj\n[<{$recipientOneHex}> 7 0 R]\nendobj\n"
        . "7 0 obj\n<{$recipientTwoHex}>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $recipientOne, $recipientTwo, $recipientOneHex, $recipientTwoHex];
};

$encryptedPdfWithMalformedStandardPermissions = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Malformed permission encrypted leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";
};

$encryptedPdfWithUnsupportedHandlerPermissions = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Unsupported handler encrypted leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /PublicKey /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128 /P -44 /Recipients [<0011223344556677>] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";
};

$encryptedPdfWithStandardDigestAuthenticationReview = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Standard auth material encrypted leak) Tj ET';
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
        . " /O <" . strtoupper(bin2hex($ownerValidation)) . ">"
        . " /U <" . strtoupper(bin2hex($userValidation)) . ">"
        . " /OE <" . strtoupper(bin2hex($ownerEncryptionKey)) . ">"
        . " /UE <" . strtoupper(bin2hex($userEncryptionKey)) . ">"
        . " /P -44 /EncryptMetadata true"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF /Perms <" . strtoupper(bin2hex($permissionDigest)) . "> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
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

$signedPdfWithIndirectDssFilterOperands = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Signed indirect DSS filter content) Tj ET';
    $signatureContentsHex = str_repeat('C', 96);
    $signatureContentsToken = '<' . $signatureContentsHex . '>';
    $certPayload = 'INDIRECT_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'INDIRECT_DSS_OCSP_BYTES_SHOULD_NOT_LEAK';
    $crlPayload = 'INDIRECT_DSS_CRL_BYTES_SHOULD_NOT_LEAK';
    $timestampPayload = 'INDIRECT_DSS_TIMESTAMP_BYTES_SHOULD_NOT_LEAK';
    $compressedCert = gzcompress($certPayload);
    $compressedCrl = gzcompress($crlPayload);
    if ($compressedCert === false || $compressedCrl === false) {
        throw new RuntimeException('Unable to compress indirect DSS filter fixture streams.');
    }
    $asciiHexOcsp = strtoupper(bin2hex($ocspPayload)) . '>';
    $asciiHexCompressedCrl = strtoupper(bin2hex($compressedCrl)) . '>';
    $asciiHexTimestamp = strtoupper(bin2hex($timestampPayload)) . '>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Indirect DSS Reviewer) /M (D:20260602160100Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /CRLs [72 0 R] /VRI << /C0FFEE1234 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /CRL [72 0 R] /TU (D:20260602160100Z) /TS 73 0 R >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($compressedCert) . " /Filter 90 0 R /Subtype /application#2Fpkix-cert >>\nstream\n{$compressedCert}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($asciiHexOcsp) . " /Filter [91 0 R null] /Subtype /application#2Focsp-response >>\nstream\n{$asciiHexOcsp}\nendstream\nendobj\n"
        . "72 0 obj\n<< /Length " . strlen($asciiHexCompressedCrl) . " /Filter 92 0 R /Subtype /application#2Fpkix-crl >>\nstream\n{$asciiHexCompressedCrl}\nendstream\nendobj\n"
        . "73 0 obj\n<< /Length " . strlen($asciiHexTimestamp) . " /Filter 91 0 R /Subtype /application#2Ftst-info >>\nstream\n{$asciiHexTimestamp}\nendstream\nendobj\n"
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

    return [
        $pdf,
        hash('sha256', $certPayload),
        hash('sha256', $ocspPayload),
        hash('sha256', $crlPayload),
        hash('sha256', $timestampPayload),
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
    'reviews public-key recipient permissions as undecoded envelope metadata' => static function (TestRunner $t) use ($encryptedPublicKeyRecipientPermissionsPdf): void {
        [$pdf, $recipientOne, $recipientTwo, $recipientOneHex, $recipientTwoHex] = $encryptedPublicKeyRecipientPermissionsPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $recipientReview = $permission['public_key_recipient_review'];
        $recipientList = $recipientReview['recipient_lists'][0];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->true($report['encrypted']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'public_key_recipient_permissions_undecoded'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same('Adobe.PubSec', $report['encryption']['filter']);
        $t->same('adbe.pkcs7.s5', $report['encryption']['subfilter']);
        $t->same(2, $report['encryption']['public_key_recipient_count']);
        $t->same('cms_pkcs7_permission_decode_unavailable', $report['encryption']['public_key_recipient_permission_decode_status']);
        $t->same(false, $report['encryption']['recipient_bytes_exposed']);

        $t->same('public_key_recipient_permissions', $permission['source']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(false, $permission['standard_permissions_declared']);
        $t->same(true, $permission['recipient_permissions_declared']);
        $t->same(null, $permission['permission_hex']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(null, $permission['accessibility_extract_allowed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['permission_word_well_formed']);
        $t->same('public_key_recipient_permissions_blocked_without_private_key', $permission['policy']);
        $t->same('blocked_encrypted_public_key_recipient_permissions', $permission['content_extraction_boundary']);
        $t->same(false, $permission['decryption_performed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same(false, $permission['recipient_bytes_exposed']);

        $t->same('Adobe.PubSec', $handler['handler']);
        $t->same(false, $handler['standard_handler']);
        $t->same(true, $handler['recipient_permissions_declared']);
        $t->same(false, $handler['handler_supported_for_native_permission_review']);
        $t->same(null, $handler['permission_word_well_formed']);
        $t->same('public_key_recipient_permissions_undecoded_review', $handler['status']);
        $t->same(2, $handler['public_key_recipient_count']);
        $t->same('cms_pkcs7_permission_decode_unavailable', $handler['public_key_recipient_permission_decode_status']);
        $t->same('crypt_filter_recipients', $handler['public_key_recipient_source_policy']);
        $t->same(false, $handler['executes_cms_parse']);
        $t->same(false, $handler['executes_decryption']);
        $t->same(false, $handler['executes_permission_enforcement']);

        $t->same('public_key_security_handler', $recipientReview['source']);
        $t->same('crypt_filter_recipients', $recipientReview['recipient_source_policy']);
        $t->same(['DefaultCryptFilter'], $recipientReview['crypt_filter_recipient_filter_names']);
        $t->same(2, $recipientReview['recipient_count']);
        $t->same([hash('sha256', $recipientOne), hash('sha256', $recipientTwo)], $recipientReview['recipient_sha256']);
        $t->same('DefaultCryptFilter', $recipientList['crypt_filter']);
        $t->same(2, $recipientList['recipient_count']);
        $t->same(false, $recipientList['recipient_bytes_exposed']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->same(false, $report['recipient_bytes_exposed']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Public-key recipient encrypted leak'));
        $t->true(is_string($encoded) && !str_contains($encoded, $recipientOne) && !str_contains($encoded, $recipientTwo));
        $t->true(is_string($encoded) && !str_contains($encoded, $recipientOneHex) && !str_contains($encoded, $recipientTwoHex));
    },
    'flags malformed Standard permission reserved bits as review-only handler metadata' => static function (TestRunner $t) use ($encryptedPdfWithMalformedStandardPermissions): void {
        $pdf = $encryptedPdfWithMalformedStandardPermissions();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'permission_word_reserved_bits_malformed'], $report['review_reasons']);
        $t->same('00000010', $report['encryption']['permission_hex']);
        $t->same('malformed_reserved_bits_review', $report['encryption']['permission_word_status']);
        $t->same(false, $report['encryption']['permission_bits_reliable']);
        $t->same(['reserved_bits_7_8_clear', 'reserved_bits_13_32_clear'], $report['encryption']['reserved_bit_violations']);

        $t->same('standard_security_handler_malformed_permissions', $permission['source']);
        $t->same(true, $permission['permissions_declared']);
        $t->same('00000010', $permission['permission_hex']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);

        $t->same('permission_handler_review', $handler['source']);
        $t->same('Standard', $handler['handler']);
        $t->same(true, $handler['standard_handler']);
        $t->same(true, $handler['handler_supported_for_native_permission_review']);
        $t->same(false, $handler['permission_word_well_formed']);
        $t->same('malformed_reserved_bits_review', $handler['status']);
        $t->same('FFFFF0C0', $handler['reserved_bits']['expected_set_mask_hex']);
        $t->same('00000003', $handler['reserved_bits']['expected_clear_mask_hex']);
        $t->same(false, $handler['reserved_bits']['set_bits_ok']);
        $t->same(true, $handler['reserved_bits']['clear_bits_ok']);
        $t->same(['reserved_bits_7_8_clear', 'reserved_bits_13_32_clear'], $handler['reserved_bits']['violations']);
        $t->same(false, $handler['executes_decryption']);
        $t->same(false, $handler['executes_permission_enforcement']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Malformed permission encrypted leak') && !str_contains($encoded, 'DEADBEEF'));
    },
    'treats non-Standard handler permission words as unsupported review metadata' => static function (TestRunner $t) use ($encryptedPdfWithUnsupportedHandlerPermissions): void {
        $pdf = $encryptedPdfWithUnsupportedHandlerPermissions();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_handler_permissions_unsupported'], $report['review_reasons']);
        $t->same('PublicKey', $report['encryption']['filter']);
        $t->same('adbe.pkcs7.s5', $report['encryption']['subfilter']);
        $t->same('FFFFFFD4', $report['encryption']['permission_hex']);
        $t->same([], $report['encryption']['allowed']);
        $t->same([], $report['encryption']['denied']);
        $t->same(null, $report['encryption']['copy_or_extract_allowed']);
        $t->same(null, $report['encryption']['accessibility_extract_allowed']);
        $t->same(false, $report['encryption']['permission_bits_reliable']);

        $t->same('unsupported_security_handler_permissions', $permission['source']);
        $t->same(true, $permission['permissions_declared']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(null, $permission['accessibility_extract_allowed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['permission_word_well_formed']);
        $t->same('permissions_unsupported_handler_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_unsupported_handler', $permission['content_extraction_boundary']);

        $t->same('PublicKey', $handler['handler']);
        $t->same(false, $handler['standard_handler']);
        $t->same(false, $handler['handler_supported_for_native_permission_review']);
        $t->same(null, $handler['permission_word_well_formed']);
        $t->same('unsupported_security_handler_permissions_review', $handler['status']);
        $t->same(false, $handler['executes_decryption']);
        $t->same(false, $handler['executes_permission_enforcement']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unsupported handler encrypted leak') && !str_contains($encoded, '0011223344556677'));
    },
    'reviews Standard permission digest and authentication entries without validating passwords' => static function (TestRunner $t) use ($encryptedPdfWithStandardDigestAuthenticationReview): void {
        [$pdf, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $encryptedPdfWithStandardDigestAuthenticationReview();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $auth = $report['standard_authentication_review'];
        $entries = $auth['entries'];
        $digest = $auth['permission_digest'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('Standard', $report['encryption']['filter']);
        $t->same('standard_handler_revision_6', $report['encryption']['revision_label']);
        $t->same('FFFFFFD4', $report['encryption']['permission_hex']);
        $t->same(true, $report['encryption']['copy_or_extract_allowed']);
        $t->same(true, $report['encryption']['perms_hash_present']);

        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same($auth, $permission['standard_authentication_review']);
        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(true, $handler['standard_authentication_present']);
        $t->same(6, $handler['standard_authentication_revision']);
        $t->same(['DocOpen'], $handler['standard_authentication_auth_events']);
        $t->same(['owner_validation', 'user_validation', 'owner_encryption_key', 'user_encryption_key'], $handler['standard_authentication_credential_entries']);
        $t->same(true, $handler['standard_permission_digest_present']);
        $t->same('permission_digest_ciphertext_review', $handler['standard_permission_digest_status']);
        $t->same(false, $handler['password_validation_performed']);
        $t->same(false, $handler['permissions_authenticated']);

        $t->same('standard_security_handler_authentication_review', $auth['source']);
        $t->same(['O' => 48, 'U' => 48, 'OE' => 32, 'UE' => 32, 'Perms' => 16], $auth['expected_lengths']);
        $t->same(48, $entries['owner_validation']['bytes']);
        $t->same(hash('sha256', $ownerValidation), $entries['owner_validation']['sha256']);
        $t->same(48, $entries['user_validation']['bytes']);
        $t->same(hash('sha256', $userValidation), $entries['user_validation']['sha256']);
        $t->same(32, $entries['owner_encryption_key']['bytes']);
        $t->same(hash('sha256', $ownerEncryptionKey), $entries['owner_encryption_key']['sha256']);
        $t->same(32, $entries['user_encryption_key']['bytes']);
        $t->same(hash('sha256', $userEncryptionKey), $entries['user_encryption_key']['sha256']);
        $t->same(16, $digest['bytes']);
        $t->same(hash('sha256', $permissionDigest), $digest['sha256']);
        $t->same(false, $auth['credential_material_exposed']);
        $t->same(false, $auth['raw_owner_user_keys_exposed']);
        $t->same(false, $auth['raw_file_encryption_keys_exposed']);
        $t->same(false, $auth['password_validation_performed']);
        $t->same(false, $auth['permissions_authenticated']);
        $t->same(false, $auth['executes_decryption']);
        $t->same(false, $auth['executes_permission_enforcement']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, 'Standard auth material encrypted leak')
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
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
    'decodes indirect DSS validation stream filter operands before hashing review metadata' => static function (TestRunner $t) use ($signedPdfWithIndirectDssFilterOperands): void {
        [$pdf, $certHash, $ocspHash, $crlHash, $timestampHash] = $signedPdfWithIndirectDssFilterOperands();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $dss = $report['document_security_store'];
        $vri = $dss['vri'][0] ?? [];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('Signed indirect DSS filter content', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same(['signed_signature_present', 'document_security_store_present'], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation'], $report['blocked_operations']);
        $t->same(1, $report['signature_field_count']);
        $t->same(1, $report['signed_signature_count']);
        $t->same(1, $report['document_security_store_count']);

        $t->true($dss['present']);
        $t->same(1, $dss['cert_count']);
        $t->same(1, $dss['ocsp_count']);
        $t->same(1, $dss['crl_count']);
        $t->same(1, $dss['vri_count']);
        $t->same(4, $dss['total_validation_stream_count']);
        $t->same(['C0FFEE1234'], $dss['vri_keys']);

        $t->same(['FlateDecode'], $dss['global_certificates'][0]['filters']);
        $t->same($certHash, $dss['global_certificates'][0]['sha256']);
        $t->same(strlen('INDIRECT_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK'), $dss['global_certificates'][0]['length_bytes']);
        $t->same(strlen(gzcompress('INDIRECT_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')), $dss['global_certificates'][0]['declared_length_bytes']);
        $t->same(['ASCIIHexDecode'], $dss['global_ocsps'][0]['filters']);
        $t->same($ocspHash, $dss['global_ocsps'][0]['sha256']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $dss['global_crls'][0]['filters']);
        $t->same($crlHash, $dss['global_crls'][0]['sha256']);

        $t->same('C0FFEE1234', $vri['key'] ?? null);
        $t->same(['FlateDecode'], $vri['certificates'][0]['filters'] ?? []);
        $t->same(['ASCIIHexDecode'], $vri['ocsps'][0]['filters'] ?? []);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $vri['crls'][0]['filters'] ?? []);
        $t->same(['ASCIIHexDecode'], $vri['timestamp_token']['filters'] ?? []);
        $t->same($timestampHash, $vri['timestamp_token']['sha256'] ?? null);
        $t->same(false, $dss['raw_validation_bytes_exposed']);
        $t->same(false, $report['raw_signature_validation_bytes_exposed']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_revocation_check']);
        $t->same(false, $report['executes_trust_chain_validation']);
        $t->same(false, $report['executes_external_pdf_tools']);

        $t->true(is_string($encoded)
            && !str_contains($encoded, 'INDIRECT_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'INDIRECT_DSS_OCSP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'INDIRECT_DSS_CRL_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'INDIRECT_DSS_TIMESTAMP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, str_repeat('C', 96)));
    },
];
