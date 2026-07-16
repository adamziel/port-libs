<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$lowResolutionPrintPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Low resolution print encrypted text leak) Tj ET';
    $ownerKey = 'LOW_RESOLUTION_PRINT_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'LOW_RESOLUTION_PRINT_USER_KEY_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
        . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
        . " /P -2092 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

$rowByName = static function (array $rows, string $name): array {
    foreach ($rows as $row) {
        if (($row['name'] ?? null) === $name) {
            return $row;
        }
    }

    throw new RuntimeException("Missing permission row for {$name}.");
};

return [
    'surfaces low resolution print quality separately from encrypted text import permission' => static function (
        TestRunner $t
    ) use ($lowResolutionPrintPdf, $rowByName): void {
        [$pdf, $content, $ownerKey, $userKey] = $lowResolutionPrintPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $permissions = $metadata['encryption']['standard_permissions'];
        $permission = $report['permission_preflight'];
        $permissionReview = $permission['standard_permission_print_quality_review'];
        $encryptionReview = $report['encryption']['standard_permission_print_quality_review'];
        $topLevelReview = $report['standard_permission_print_quality_review'];
        $printBit = $rowByName($permissions['permission_bits'], 'print');
        $highQualityBit = $rowByName($permissions['permission_bits'], 'high_quality_print');
        $printOperation = $rowByName($permission['standard_permission_operation_review']['operations'], 'print');
        $highQualityOperation = $rowByName($permission['standard_permission_operation_review']['operations'], 'high_quality_print');
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same(-2092, $permissions['declared']);
        $t->same(-2092, $permissions['signed']);
        $t->same(4294965204, $permissions['unsigned']);
        $t->same('FFFFF7D4', $permissions['hex']);
        $t->same(true, $permissions['reserved_bits_valid']);
        $t->same('low_resolution', $permissions['print_quality']);
        $t->same(true, in_array('print', $permissions['allowed'], true));
        $t->same(false, in_array('high_quality_print', $permissions['allowed'], true));
        $t->same(true, in_array('high_quality_print', $permissions['denied'], true));

        $t->same('print', $printBit['name']);
        $t->same(true, $printBit['bit_set']);
        $t->same(true, $printBit['allowed']);
        $t->same('allowed_by_permission_bit', $printBit['status']);
        $t->same('high_quality_print', $highQualityBit['name']);
        $t->same(false, $highQualityBit['bit_set']);
        $t->same(true, $highQualityBit['denied']);
        $t->same('permission_bit_not_set', $highQualityBit['dependency_status'] ?? null);

        $t->same(1, $report['standard_permission_print_quality_review_count']);
        $t->same(1, $permission['standard_permission_print_quality_review_count']);
        $t->same($permissionReview, $encryptionReview);
        $t->same($permissionReview, $topLevelReview);
        $t->same('standard_permission_print_quality_review', $permissionReview['source']);
        $t->same(true, $permissionReview['present']);
        $t->same('low_resolution', $permissionReview['print_quality']);
        $t->same(true, $permissionReview['print_allowed']);
        $t->same(false, $permissionReview['high_quality_print_allowed']);
        $t->same(true, $permissionReview['limited_to_low_resolution']);
        $t->same(false, $permissionReview['native_import_allowed_now']);
        $t->same('low_resolution_print_pending_authentication', $permissionReview['status']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $permissionReview['permission_boundary']);
        $t->same('review_only_until_password_validation_and_decryption', $permissionReview['wordpress_import_policy']);
        $t->same(false, $permissionReview['permissions_authenticated']);
        $t->same(false, $permissionReview['password_validation_performed']);
        $t->same(false, $permissionReview['executes_permission_enforcement']);

        $t->same('allowed_by_permission_bit_pending_authentication', $printOperation['effective_status']);
        $t->same('denied_by_permission_bit', $highQualityOperation['effective_status']);
        $t->same('permission_bit_not_set', $highQualityOperation['dependency_status'] ?? null);
        $t->same('review_only_until_password_validation_and_decryption', $printOperation['wordpress_import_policy']);
        $t->same('blocked_by_standard_permission_denial', $highQualityOperation['wordpress_import_policy']);

        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
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
    'keeps low resolution print encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($lowResolutionPrintPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $lowResolutionPrintPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(false, str_contains($plainText, $content));
        $t->same(false, is_string($encoded) && str_contains($encoded, $content));
        $t->same(false, is_string($encoded) && str_contains($encoded, $ownerKey));
        $t->same(false, is_string($encoded) && str_contains($encoded, $userKey));
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_external_pdf_tools']);
    },
];
