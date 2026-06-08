<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$accessibilityOnlyPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Accessibility-only encrypted text leak) Tj ET';
    $ownerKey = 'ACCESSIBILITY_ONLY_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'ACCESSIBILITY_ONLY_USER_KEY_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
        . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
        . " /P -316 /EncryptMetadata true >>\nendobj\n"
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
    'separates accessibility extraction permission from WordPress text import permission' => static function (
        TestRunner $t
    ) use ($accessibilityOnlyPdf, $operationByName): void {
        [$pdf, $content, $ownerKey, $userKey] = $accessibilityOnlyPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $operationReview = $permission['standard_permission_operation_review'];
        $textReview = $permission['standard_permission_text_extraction_review'];
        $copyOperation = $operationByName($operationReview['operations'], 'copy_or_extract');
        $accessibilityOperation = $operationByName($operationReview['operations'], 'extract_for_accessibility');
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_denied'], $report['review_reasons']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_denied_by_permissions', $permission['policy']);
        $t->same('blocked_by_encryption_and_copy_permission', $permission['content_extraction_boundary']);
        $t->same(-316, $permission['permission_signed']);
        $t->same(4294966980, $permission['permission_unsigned']);
        $t->same('FFFFFEC4', $permission['permission_hex']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['accessibility_extract_allowed']);
        $t->same(['print', 'extract_for_accessibility', 'assemble_document', 'high_quality_print'], $permission['allowed']);
        $t->same(['modify_contents', 'copy_or_extract', 'add_or_modify_annotations', 'fill_form_fields'], $permission['denied']);

        $t->same('copy_or_extract', $copyOperation['operation']);
        $t->same('denied_by_permission_bit', $copyOperation['effective_status']);
        $t->same(false, $copyOperation['syntactic_allowed']);
        $t->same(true, $copyOperation['syntactic_denied']);
        $t->same('blocked_by_standard_permission_bit', $copyOperation['permission_boundary']);
        $t->same('blocked_by_standard_permission_denial', $copyOperation['wordpress_import_policy']);

        $t->same('extract_for_accessibility', $accessibilityOperation['operation']);
        $t->same('allowed_by_permission_bit_pending_authentication', $accessibilityOperation['effective_status']);
        $t->same(true, $accessibilityOperation['syntactic_allowed']);
        $t->same(false, $accessibilityOperation['syntactic_denied']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $accessibilityOperation['permission_boundary']);
        $t->same('review_only_until_password_validation_and_decryption', $accessibilityOperation['wordpress_import_policy']);

        $t->same(1, $permission['standard_permission_text_extraction_review_count']);
        $t->same(1, $report['standard_permission_text_extraction_review_count']);
        $t->same($textReview, $report['standard_permission_text_extraction_review']);
        $t->same($textReview, $report['encryption']['standard_permission_text_extraction_review']);
        $t->same('standard_permission_text_extraction_review', $textReview['source']);
        $t->same(true, $textReview['present']);
        $t->same('accessibility_extract_allowed_but_copy_extract_denied', $textReview['status']);
        $t->same(false, $textReview['copy_or_extract_allowed']);
        $t->same(true, $textReview['accessibility_extract_allowed']);
        $t->same('copy_or_extract', $textReview['wordpress_text_import_permission']);
        $t->same('extract_for_accessibility', $textReview['accessibility_permission']);
        $t->same(false, $textReview['text_import_allowed_after_decryption']);
        $t->same(true, $textReview['accessibility_extraction_allowed_after_decryption']);
        $t->same(false, $textReview['accessibility_permission_grants_wordpress_import']);
        $t->same('blocked_by_standard_copy_permission_bit', $textReview['content_extraction_boundary']);
        $t->same('blocked_by_copy_permission_denial_accessibility_review_only', $textReview['wordpress_import_policy']);
        $t->same('denied_by_permission_bit', $textReview['copy_operation_status']);
        $t->same('allowed_by_permission_bit_pending_authentication', $textReview['accessibility_operation_status']);
        $t->same(false, $textReview['native_text_extraction_allowed_now']);
        $t->same(false, $textReview['executes_decryption']);
        $t->same(false, $textReview['executes_permission_enforcement']);

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
    'keeps accessibility-only encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($accessibilityOnlyPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $accessibilityOnlyPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(false, str_contains($plainText, $content));
        $t->same(false, is_string($encoded) && str_contains($encoded, $content));
        $t->same(false, is_string($encoded) && str_contains($encoded, $ownerKey));
        $t->same(false, is_string($encoded) && str_contains($encoded, $userKey));
    },
];
