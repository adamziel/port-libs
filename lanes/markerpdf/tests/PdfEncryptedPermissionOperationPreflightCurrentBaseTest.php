<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$standardPermissionPdf = static function (int $revision, int $version, int $permissionWord, string $label): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} operation permission encrypted text leak) Tj ET";
    $ownerKey = "{$label}_OPERATION_OWNER_KEY_SHOULD_NOT_LEAK";
    $userKey = "{$label}_OPERATION_USER_KEY_SHOULD_NOT_LEAK";
    $keyLength = $version === 1 ? 40 : 128;

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V {$version} /R {$revision} /Length {$keyLength}"
        . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
        . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
        . " /P {$permissionWord} /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
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
    'maps revision two Standard permission bits to operation preflight rows without granting import' => static function (
        TestRunner $t
    ) use ($standardPermissionPdf, $operationByName): void {
        [$pdf, $content, $ownerKey, $userKey] = $standardPermissionPdf(2, 1, -44, 'REVISION_TWO');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $review = $permission['standard_permission_operation_review'];
        $copy = $operationByName($review['operations'], 'copy_or_extract');
        $modify = $operationByName($review['operations'], 'modify_contents');
        $accessibility = $operationByName($review['operations'], 'extract_for_accessibility');
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same($review, $report['encryption']['standard_permission_operation_review']);

        $t->same('standard_permission_operation_review', $review['source']);
        $t->same(true, $review['present']);
        $t->same(8, $review['operation_count']);
        $t->same(['allowed_by_permission_bit_pending_authentication', 'denied_by_permission_bit', 'not_applicable_for_revision'], $review['operation_statuses']);
        $t->same(['print', 'copy_or_extract'], $review['pending_authentication_operation_names']);
        $t->same(['modify_contents', 'add_or_modify_annotations'], $review['denied_operation_names']);
        $t->same(['fill_form_fields', 'extract_for_accessibility', 'assemble_document', 'high_quality_print'], $review['not_applicable_operation_names']);
        $t->same([], $review['native_import_allowed_operation_names']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $review['permission_boundary']);
        $t->same(false, $review['executes_permission_enforcement']);

        $t->same('copy_or_extract', $copy['operation']);
        $t->same('allowed_by_permission_bit', $copy['bit_status']);
        $t->same('allowed_by_permission_bit_pending_authentication', $copy['effective_status']);
        $t->same(true, $copy['syntactic_allowed']);
        $t->same(false, $copy['permissions_authenticated']);
        $t->same(false, $copy['native_import_allowed_now']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $copy['permission_boundary']);
        $t->same('review_only_until_password_validation_and_decryption', $copy['wordpress_import_policy']);

        $t->same('denied_by_permission_bit', $modify['effective_status']);
        $t->same(false, $modify['syntactic_allowed']);
        $t->same(true, $modify['syntactic_denied']);
        $t->same('blocked_by_standard_permission_bit', $modify['permission_boundary']);
        $t->same('blocked_by_standard_permission_denial', $modify['wordpress_import_policy']);

        $t->same('not_applicable_for_revision', $accessibility['effective_status']);
        $t->same(false, $accessibility['applicable']);
        $t->same(false, $accessibility['native_import_allowed_now']);
        $t->same('not_defined_for_standard_security_handler_revision', $accessibility['permission_boundary']);
        $t->same('not_applicable_for_security_handler_revision', $accessibility['wordpress_import_policy']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))))
            && !str_contains($encoded, strtoupper(bin2hex(str_pad($userKey, 32, 'U')))));
    },
    'keeps revision four allowed operations pending authentication before WordPress import' => static function (
        TestRunner $t
    ) use ($standardPermissionPdf, $operationByName): void {
        [$pdf, $content, $ownerKey, $userKey] = $standardPermissionPdf(4, 4, 4294967252, 'REVISION_FOUR');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $review = $permission['standard_permission_operation_review'];
        $assemble = $operationByName($review['operations'], 'assemble_document');
        $annotations = $operationByName($review['operations'], 'add_or_modify_annotations');
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(8, $review['operation_count']);
        $t->same(['allowed_by_permission_bit_pending_authentication', 'denied_by_permission_bit'], $review['operation_statuses']);
        $t->same(['print', 'copy_or_extract', 'fill_form_fields', 'extract_for_accessibility', 'assemble_document', 'high_quality_print'], $review['pending_authentication_operation_names']);
        $t->same(['modify_contents', 'add_or_modify_annotations'], $review['denied_operation_names']);
        $t->same([], $review['not_applicable_operation_names']);
        $t->same([], $review['native_import_allowed_operation_names']);
        $t->same(false, $review['permissions_authenticated']);
        $t->same(false, $review['authenticated_permission_bits_reliable']);

        $t->same('assemble_document', $assemble['operation']);
        $t->same(true, $assemble['applicable']);
        $t->same(true, $assemble['syntactic_allowed']);
        $t->same('allowed_by_permission_bit_pending_authentication', $assemble['effective_status']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $assemble['permission_boundary']);

        $t->same('add_or_modify_annotations', $annotations['operation']);
        $t->same(true, $annotations['applicable']);
        $t->same(true, $annotations['syntactic_denied']);
        $t->same('denied_by_permission_bit', $annotations['effective_status']);
        $t->same('blocked_by_standard_permission_bit', $annotations['permission_boundary']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))))
            && !str_contains($encoded, strtoupper(bin2hex(str_pad($userKey, 32, 'U')))));
    },
];
