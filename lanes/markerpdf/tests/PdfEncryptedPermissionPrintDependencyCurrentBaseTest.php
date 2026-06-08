<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$printDependencyPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (High quality print dependency encrypted text leak) Tj ET';
    $ownerKey = 'PRINT_DEPENDENCY_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'PRINT_DEPENDENCY_USER_KEY_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
        . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
        . " /P -48 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

$permissionRowByName = static function (array $rows, string $name): array {
    foreach ($rows as $row) {
        if (($row['name'] ?? null) === $name) {
            return $row;
        }
    }

    throw new RuntimeException("Missing permission bit row for {$name}.");
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
    'denies high quality print when the Standard print permission bit is clear' => static function (
        TestRunner $t
    ) use ($printDependencyPdf, $permissionRowByName, $operationByName): void {
        [$pdf, $content, $ownerKey, $userKey] = $printDependencyPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $permissions = $metadata['encryption']['standard_permissions'];
        $permission = $report['permission_preflight'];
        $operationReview = $permission['standard_permission_operation_review'];
        $print = $permissionRowByName($permissions['permission_bits'], 'print');
        $highQuality = $permissionRowByName($permissions['permission_bits'], 'high_quality_print');
        $highQualityOperation = $operationByName($operationReview['operations'], 'high_quality_print');
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same(-48, $permissions['declared']);
        $t->same(-48, $permissions['signed']);
        $t->same(4294967248, $permissions['unsigned']);
        $t->same('FFFFFFD0', $permissions['hex']);
        $t->same(true, $permissions['reserved_bits_valid']);
        $t->same('disallowed', $permissions['print_quality']);
        $t->same([
            'copy_or_extract',
            'fill_form_fields',
            'extract_for_accessibility',
            'assemble_document',
        ], $permissions['allowed']);
        $t->same([
            'print',
            'modify_contents',
            'add_or_modify_annotations',
            'high_quality_print',
        ], $permissions['denied']);

        $t->same('print', $print['name']);
        $t->same(false, $print['bit_set']);
        $t->same(true, $print['applicable']);
        $t->same(true, $print['denied']);
        $t->same('denied_by_permission_bit', $print['status']);

        $t->same('high_quality_print', $highQuality['name']);
        $t->same(true, $highQuality['bit_set']);
        $t->same(true, $highQuality['applicable']);
        $t->same(false, $highQuality['allowed']);
        $t->same(true, $highQuality['denied']);
        $t->same('denied_by_permission_bit', $highQuality['status']);
        $t->same('print', $highQuality['requires_permission_name'] ?? null);
        $t->same(false, $highQuality['required_permission_allowed'] ?? null);
        $t->same('required_print_permission_denied', $highQuality['dependency_status'] ?? null);

        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, in_array('high_quality_print', $permission['allowed'], true));
        $t->same(true, in_array('high_quality_print', $permission['denied'], true));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same('standard_permission_operations_pending_authentication', $operationReview['status']);
        $t->same(true, in_array('high_quality_print', $operationReview['denied_operation_names'], true));
        $t->same(false, in_array('high_quality_print', $operationReview['pending_authentication_operation_names'], true));

        $t->same('high_quality_print', $highQualityOperation['operation']);
        $t->same(true, $highQualityOperation['bit_set']);
        $t->same(true, $highQualityOperation['syntactic_denied']);
        $t->same(false, $highQualityOperation['syntactic_allowed']);
        $t->same('denied_by_permission_bit', $highQualityOperation['effective_status']);
        $t->same('print', $highQualityOperation['requires_permission_name'] ?? null);
        $t->same(false, $highQualityOperation['required_permission_allowed'] ?? null);
        $t->same('required_print_permission_denied', $highQualityOperation['dependency_status'] ?? null);
        $t->same('blocked_by_standard_permission_bit', $highQualityOperation['permission_boundary']);
        $t->same('blocked_by_standard_permission_denial', $highQualityOperation['wordpress_import_policy']);

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
    'keeps print-dependency encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($printDependencyPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $printDependencyPdf();
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
