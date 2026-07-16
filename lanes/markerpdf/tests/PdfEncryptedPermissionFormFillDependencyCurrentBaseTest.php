<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$formFillDependencyPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Form fill dependency encrypted text leak) Tj ET';
    $ownerKey = 'FORM_FILL_DEPENDENCY_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'FORM_FILL_DEPENDENCY_USER_KEY_SHOULD_NOT_LEAK';
    $ownerBytes = str_pad($ownerKey, 32, 'O');
    $userBytes = str_pad($userKey, 32, 'U');

    // 0xFFFFF0F0 keeps reserved bits well formed, allows copy and annotation/form-fill via bit 6,
    // and leaves the narrower revision-3 form-fill bit 9 clear.
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerBytes)) . ">"
        . " /U <" . strtoupper(bin2hex($userBytes)) . ">"
        . " /P -3856 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes];
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
    'treats annotation permission bit as sufficient for existing form-fill review' => static function (
        TestRunner $t
    ) use ($formFillDependencyPdf, $permissionRowByName, $operationByName): void {
        [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes] = $formFillDependencyPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $permissions = $metadata['encryption']['standard_permissions'];
        $permission = $report['permission_preflight'];
        $operationReview = $permission['standard_permission_operation_review'];
        $annotation = $permissionRowByName($permissions['permission_bits'], 'add_or_modify_annotations');
        $formFill = $permissionRowByName($permissions['permission_bits'], 'fill_form_fields');
        $formFillOperation = $operationByName($operationReview['operations'], 'fill_form_fields');
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same(-3856, $permissions['declared']);
        $t->same(-3856, $permissions['signed']);
        $t->same(4294963440, $permissions['unsigned']);
        $t->same('FFFFF0F0', $permissions['hex']);
        $t->same(true, $permissions['reserved_bits_valid']);
        $t->same('well_formed_standard_permissions', $permissions['permission_word_status']);
        $t->same([
            'copy_or_extract',
            'add_or_modify_annotations',
            'fill_form_fields',
        ], $permissions['allowed']);
        $t->same([
            'print',
            'modify_contents',
            'extract_for_accessibility',
            'assemble_document',
            'high_quality_print',
        ], $permissions['denied']);

        $t->same('add_or_modify_annotations', $annotation['name']);
        $t->same(true, $annotation['bit_set']);
        $t->same(true, $annotation['allowed']);
        $t->same(false, $annotation['denied']);
        $t->same('allowed_by_permission_bit', $annotation['status']);

        $t->same('fill_form_fields', $formFill['name']);
        $t->same(false, $formFill['bit_set']);
        $t->same(true, $formFill['allowed']);
        $t->same(false, $formFill['denied']);
        $t->same('allowed_by_permission_bit', $formFill['status']);
        $t->same('add_or_modify_annotations', $formFill['granted_by_permission_name'] ?? null);
        $t->same(true, $formFill['granted_by_permission_bit_set'] ?? null);
        $t->same('allowed_by_add_or_modify_annotations_permission', $formFill['dependency_status'] ?? null);

        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, in_array('fill_form_fields', $permission['allowed'], true));
        $t->same(false, in_array('fill_form_fields', $permission['denied'], true));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same('standard_permission_operations_pending_authentication', $operationReview['status']);
        $t->same(true, in_array('fill_form_fields', $operationReview['pending_authentication_operation_names'], true));
        $t->same(false, in_array('fill_form_fields', $operationReview['denied_operation_names'], true));

        $t->same('fill_form_fields', $formFillOperation['operation']);
        $t->same(false, $formFillOperation['bit_set']);
        $t->same(true, $formFillOperation['syntactic_allowed']);
        $t->same(false, $formFillOperation['syntactic_denied']);
        $t->same('allowed_by_permission_bit_pending_authentication', $formFillOperation['effective_status']);
        $t->same('add_or_modify_annotations', $formFillOperation['granted_by_permission_name'] ?? null);
        $t->same(true, $formFillOperation['granted_by_permission_bit_set'] ?? null);
        $t->same('allowed_by_add_or_modify_annotations_permission', $formFillOperation['dependency_status'] ?? null);
        $t->same('blocked_until_password_validation_and_permission_authentication', $formFillOperation['permission_boundary']);
        $t->same('review_only_until_password_validation_and_decryption', $formFillOperation['wordpress_import_policy']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, $ownerBytes)
            && !str_contains($encoded, $userBytes)
            && !str_contains($encoded, strtoupper(bin2hex($ownerBytes)))
            && !str_contains($encoded, strtoupper(bin2hex($userBytes))));
    },
    'keeps form-fill dependency encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($formFillDependencyPdf): void {
        [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes] = $formFillDependencyPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(false, str_contains($plainText, $content));
        $t->same(false, str_contains($plainText, $ownerKey));
        $t->same(false, str_contains($plainText, $userKey));
        $t->same(false, is_string($encoded) && str_contains($encoded, $ownerBytes));
        $t->same(false, is_string($encoded) && str_contains($encoded, $userBytes));
        $t->same(false, is_string($encoded) && str_contains($encoded, strtoupper(bin2hex($ownerBytes))));
        $t->same(false, is_string($encoded) && str_contains($encoded, strtoupper(bin2hex($userBytes))));
    },
];
