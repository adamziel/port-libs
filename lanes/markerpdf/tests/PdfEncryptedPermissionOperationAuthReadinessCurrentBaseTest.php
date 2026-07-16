<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$revisionSixOperationReadinessPdf = static function (bool $includePermissionDigest, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} operation auth readiness encrypted text leak) Tj ET";
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $perms = $includePermissionDigest ? ' /Perms ' . $hex($permissionDigest) : '';

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
        . " /P -44 /EncryptMetadata true{$perms}"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

$operationByName = static function (array $rows, string $name): array {
    foreach ($rows as $row) {
        if (($row['name'] ?? null) === $name) {
            return $row;
        }
    }

    throw new RuntimeException("Missing operation review row for {$name}.");
};

return [
    'carries Standard authentication readiness into permission operation rows' => static function (
        TestRunner $t
    ) use ($revisionSixOperationReadinessPdf, $operationByName): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] =
            $revisionSixOperationReadinessPdf(true, 'READY_R6');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $review = $permission['standard_permission_operation_review'];
        $copy = $operationByName($review['operations'], 'copy_or_extract');
        $modify = $operationByName($review['operations'], 'modify_contents');
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $permission['permission_authentication_status']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same('copy_or_extract', $copy['operation']);
        $t->same('allowed_by_permission_bit_pending_authentication', $copy['effective_status']);
        $t->same(true, $copy['authentication_material_ready_for_password_attempt']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $copy['permission_authentication_status']);
        $t->same(true, $copy['permission_digest_required']);
        $t->same(true, $copy['permission_digest_present']);
        $t->same(true, $copy['permission_digest_length_valid']);
        $t->same(false, $copy['password_validation_performed']);
        $t->same(false, $copy['permissions_authenticated']);
        $t->same(false, $copy['native_import_allowed_now']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $copy['permission_boundary']);

        $t->same('modify_contents', $modify['operation']);
        $t->same('denied_by_permission_bit', $modify['effective_status']);
        $t->same(true, $modify['authentication_material_ready_for_password_attempt']);
        $t->same(true, $modify['permission_digest_required']);
        $t->same(true, $modify['permission_digest_present']);
        $t->same('blocked_by_standard_permission_bit', $modify['permission_boundary']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerEncryptionKey)
            && !str_contains($encoded, $userEncryptionKey)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
    },
    'marks allowed operation rows as not password-ready when revision six Perms is missing' => static function (
        TestRunner $t
    ) use ($revisionSixOperationReadinessPdf, $operationByName): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] =
            $revisionSixOperationReadinessPdf(false, 'MISSING_PERMS_R6');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $review = $permission['standard_permission_operation_review'];
        $copy = $operationByName($review['operations'], 'copy_or_extract');
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $permission['permission_authentication_status']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);

        $t->same('copy_or_extract', $copy['operation']);
        $t->same('allowed_by_permission_bit_pending_authentication', $copy['effective_status']);
        $t->same(false, $copy['authentication_material_ready_for_password_attempt']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $copy['permission_authentication_status']);
        $t->same(true, $copy['permission_digest_required']);
        $t->same(false, $copy['permission_digest_present']);
        $t->same(null, $copy['permission_digest_length_valid']);
        $t->same(false, $copy['password_validation_performed']);
        $t->same(false, $copy['permissions_authenticated']);
        $t->same(false, $copy['native_import_allowed_now']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $copy['permission_boundary']);
        $t->same('review_only_until_password_validation_and_decryption', $copy['wordpress_import_policy']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerEncryptionKey)
            && !str_contains($encoded, $userEncryptionKey)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
    },
];
