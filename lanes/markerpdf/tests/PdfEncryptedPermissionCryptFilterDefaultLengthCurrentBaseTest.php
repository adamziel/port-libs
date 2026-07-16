<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$standardV4InheritedCryptFilterLengthPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Inherited AESV2 crypt filter length text leak) Tj ET';
    $ownerValidation = str_repeat('D', 32);
    $userValidation = str_repeat('L', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true"
        . " /CF << /DocAes << /CFM /AESV2 /AuthEvent /DocOpen >> >>"
        . " /StmF /DocAes /StrF /DocAes >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$standardV5InheritedCryptFilterLengthPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Inherited AESV3 crypt filter length text leak) Tj ET';
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
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [
        $pdf,
        $content,
        $ownerValidation,
        $userValidation,
        $ownerEncryptionKey,
        $userEncryptionKey,
        $permissionDigest,
    ];
};

$assertInheritedCryptFilterLength = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $filterName,
    string $method,
    int $topLevelLengthBits,
    int $expectedLengthBytes,
    array $sensitiveBytes
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $encryption = $metadata['encryption'];
    $review = $report['crypt_filter_content_review'];
    $permission = $report['permission_preflight'];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', $plainText);
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

    $t->same('Standard', $encryption['filter']);
    $t->same($topLevelLengthBits, $encryption['key_length_bits']);
    $t->same('encryption_dictionary_length_entry', $encryption['key_length_source']);
    $t->same($filterName, $encryption['stream_filter']);
    $t->same($filterName, $encryption['string_filter']);
    $t->same($method, $encryption['crypt_filters'][$filterName]['method']);
    $t->same($expectedLengthBytes, $encryption['crypt_filters'][$filterName]['key_length_bytes']);
    $t->same(true, $encryption['crypt_filters'][$filterName]['key_length_defaulted']);
    $t->same('standard_security_handler_length_inherited', $encryption['crypt_filters'][$filterName]['key_length_source']);
    $t->same($topLevelLengthBits, $encryption['crypt_filters'][$filterName]['key_length_source_bits']);

    $t->same('standard_security_handler_permissions', $permission['source']);
    $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
    $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
    $t->same(true, $permission['permission_bits_reliable']);
    $t->same(true, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);

    $t->same('review_only_encrypted_document_boundary', $review['text_content_policy']);
    $t->same(['encrypted_crypt_filter'], $review['role_statuses']);
    $t->same([$filterName], $review['encrypted_filter_names']);
    $t->same(['crypt_filter_key_length_supported'], $review['key_length_statuses']);
    $t->same([], $review['fail_closed_role_names']);
    $t->same([], $review['key_length_invalid_role_names']);

    foreach ($review['roles'] as $role) {
        $t->same($filterName, $role['filter_name']);
        $t->same($method, $role['method']);
        $t->same($expectedLengthBytes, $role['key_length_bytes']);
        $t->same(true, $role['key_length_defaulted']);
        $t->same('standard_security_handler_length_inherited', $role['key_length_source']);
        $t->same($topLevelLengthBits, $role['key_length_source_bits']);
        $t->same('crypt_filter_key_length_supported', $role['key_length_status']);
        $t->same(false, $role['key_length_fail_closed']);
        $t->same('encrypted_crypt_filter', $role['status']);
        $t->same(true, $role['content_encrypted']);
    }

    $t->same(false, $report['executes_decryption']);
    $t->same(false, $report['executes_permission_enforcement']);
    $t->same(false, $report['executes_python_or_models']);
    $t->same(false, $report['executes_external_pdf_tools']);
    $t->true(is_string($encoded) && !str_contains($encoded, $content));
    foreach ($sensitiveBytes as $bytes) {
        $t->true(is_string($encoded)
            && !str_contains($encoded, $bytes)
            && !str_contains($encoded, strtoupper(bin2hex($bytes))));
    }
};

return [
    'inherits top-level Standard length for AESV2 crypt filters that omit local Length' => static function (
        TestRunner $t
    ) use ($standardV4InheritedCryptFilterLengthPdf, $assertInheritedCryptFilterLength): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $standardV4InheritedCryptFilterLengthPdf();

        $assertInheritedCryptFilterLength(
            $t,
            $pdf,
            $content,
            'DocAes',
            'AESV2',
            128,
            16,
            [$ownerValidation, $userValidation]
        );
    },
    'inherits top-level Standard length for AESV3 crypt filters that omit local Length' => static function (
        TestRunner $t
    ) use ($standardV5InheritedCryptFilterLengthPdf, $assertInheritedCryptFilterLength): void {
        [
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permissionDigest,
        ] = $standardV5InheritedCryptFilterLengthPdf();

        $assertInheritedCryptFilterLength(
            $t,
            $pdf,
            $content,
            'StdCF',
            'AESV3',
            256,
            32,
            [
                $ownerValidation,
                $userValidation,
                $ownerEncryptionKey,
                $userEncryptionKey,
                $permissionDigest,
            ]
        );
    },
];
