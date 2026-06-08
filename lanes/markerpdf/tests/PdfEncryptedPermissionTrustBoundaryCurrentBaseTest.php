<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$revisionSixPdf = static function (bool $versionRevisionMismatch, bool $includePerms, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} permission trust boundary encrypted leak) Tj ET";
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $perms = $includePerms ? ' /Perms ' . $hex($permissionDigest) : '';
    $version = $versionRevisionMismatch ? 4 : 5;
    $length = $versionRevisionMismatch ? 128 : 256;
    $cryptFilters = $versionRevisionMismatch
        ? ''
        : ' /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >> /StmF /StdCF /StrF /StdCF';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V {$version} /R 6 /Length {$length}"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true{$perms}{$cryptFilters} >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

$assertNoRawMaterial = static function (
    TestRunner $t,
    array $report,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $ownerEncryptionKey,
    string $userEncryptionKey,
    string $permissionDigest
): void {
    $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

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
};

return [
    'keeps complete raw Standard auth material unusable for permission trust when parameters are malformed' => static function (
        TestRunner $t
    ) use ($revisionSixPdf, $assertNoRawMaterial): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $revisionSixPdf(true, true, 'MISMATCHED_R6');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $encryption = $report['encryption'];
        $trust = $permission['permission_authentication_trust_review'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same(true, $permission['permission_bits_authentication_required']);
        $t->same(false, $permission['permission_bits_authentication_material_usable']);
        $t->same(false, $permission['permission_bits_trust_requires_password_validation']);
        $t->same('decoded_permission_bits_not_syntactically_reliable', $permission['permission_bits_trust_blocker']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same('decoded_permission_bits_not_syntactically_reliable', $permission['permission_authentication_status']);

        $t->same($trust, $encryption['permission_authentication_trust_review']);
        $t->same(true, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['authentication_material_usable_for_permission_trust']);
        $t->same(false, $trust['permission_trust_requires_password_validation']);
        $t->same('decoded_permission_bits_not_syntactically_reliable', $trust['permission_trust_blocker']);
        $t->same('blocked_by_malformed_permission_bits', $trust['trust_boundary']);
        $t->same('decoded_permission_bits_not_syntactically_reliable', $trust['status']);

        $t->same(false, $encryption['permission_bits_authentication_material_usable']);
        $t->same(false, $encryption['permission_bits_trust_requires_password_validation']);
        $t->same('decoded_permission_bits_not_syntactically_reliable', $encryption['permission_bits_trust_blocker']);
        $assertNoRawMaterial($t, $report, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest);
    },
    'marks complete revision six material usable for trust only after password validation' => static function (
        TestRunner $t
    ) use ($revisionSixPdf, $assertNoRawMaterial): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $revisionSixPdf(false, true, 'COMPLETE_R6');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $encryption = $report['encryption'];
        $trust = $permission['permission_authentication_trust_review'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(true, $permission['permission_bits_authentication_required']);
        $t->same(true, $permission['permission_bits_authentication_material_usable']);
        $t->same(true, $permission['permission_bits_trust_requires_password_validation']);
        $t->same('password_validation_not_performed', $permission['permission_bits_trust_blocker']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $permission['permission_authentication_status']);

        $t->same($trust, $encryption['permission_authentication_trust_review']);
        $t->same(true, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(true, $trust['authentication_material_usable_for_permission_trust']);
        $t->same(true, $trust['permission_trust_requires_password_validation']);
        $t->same('password_validation_not_performed', $trust['permission_trust_blocker']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $trust['trust_boundary']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);

        $t->same(true, $encryption['permission_bits_authentication_material_usable']);
        $t->same(true, $encryption['permission_bits_trust_requires_password_validation']);
        $t->same('password_validation_not_performed', $encryption['permission_bits_trust_blocker']);
        $assertNoRawMaterial($t, $report, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest);
    },
    'keeps revision six material unusable for permission trust when Perms is missing' => static function (
        TestRunner $t
    ) use ($revisionSixPdf, $assertNoRawMaterial): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $revisionSixPdf(false, false, 'MISSING_PERMS');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $encryption = $report['encryption'];
        $trust = $permission['permission_authentication_trust_review'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(true, $permission['permission_bits_authentication_required']);
        $t->same(false, $permission['permission_bits_authentication_material_usable']);
        $t->same(false, $permission['permission_bits_trust_requires_password_validation']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $permission['permission_bits_trust_blocker']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $permission['permission_authentication_status']);

        $t->same($trust, $encryption['permission_authentication_trust_review']);
        $t->same(false, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['authentication_material_usable_for_permission_trust']);
        $t->same(false, $trust['permission_trust_requires_password_validation']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $trust['permission_trust_blocker']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $trust['status']);

        $t->same(false, $encryption['permission_bits_authentication_material_usable']);
        $t->same(false, $encryption['permission_bits_trust_requires_password_validation']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $encryption['permission_bits_trust_blocker']);
        $assertNoRawMaterial($t, $report, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest);
    },
];
