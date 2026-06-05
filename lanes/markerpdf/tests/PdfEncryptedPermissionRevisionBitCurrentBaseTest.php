<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$standardPermissionPdf = static function (int $revision, int $version, int $permissionWord, string $label): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} encrypted permission text leak) Tj ET";
    $ownerKey = "{$label}_OWNER_KEY_SHOULD_NOT_LEAK";
    $userKey = "{$label}_USER_KEY_SHOULD_NOT_LEAK";

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V {$version} /R {$revision} /Length 128"
        . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
        . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
        . " /P {$permissionWord} /EncryptMetadata true >>\nendobj\n"
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

return [
    'surfaces revision gated Standard permission bits before encrypted import preflight' => static function (
        TestRunner $t
    ) use ($standardPermissionPdf, $permissionRowByName): void {
        [$pdf, $content, $ownerKey, $userKey] = $standardPermissionPdf(2, 1, -44, 'REVISION_TWO');
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permissions = $metadata['encryption']['standard_permissions'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $encryption = $report['encryption'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same(2, $permissions['effective_revision']);
        $t->same(['print', 'modify_contents', 'copy_or_extract', 'add_or_modify_annotations'], $permissions['applicable_permission_names']);
        $t->same(['fill_form_fields', 'extract_for_accessibility', 'assemble_document', 'high_quality_print'], $permissions['not_applicable_permission_names']);
        $t->same(['print', 'copy_or_extract'], $permissions['allowed']);
        $t->same(['modify_contents', 'add_or_modify_annotations'], $permissions['denied']);
        $t->same('high_resolution', $permissions['print_quality']);
        $t->same(8, $permissions['permission_bit_review_count']);
        $t->same([
            'allowed_by_permission_bit',
            'denied_by_permission_bit',
            'not_applicable_for_revision',
        ], $permissions['permission_bit_statuses']);

        $copy = $permissionRowByName($permissions['permission_bits'], 'copy_or_extract');
        $modify = $permissionRowByName($permissions['permission_bits'], 'modify_contents');
        $fill = $permissionRowByName($permissions['permission_bits'], 'fill_form_fields');
        $highQuality = $permissionRowByName($permissions['permission_bits'], 'high_quality_print');

        $t->same('standard_permission_bit_review', $copy['source']);
        $t->same(5, $copy['bit']);
        $t->same('00000010', $copy['mask_hex']);
        $t->same(true, $copy['bit_set']);
        $t->same(true, $copy['applicable']);
        $t->same(true, $copy['allowed']);
        $t->same(false, $copy['denied']);
        $t->same('allowed_by_permission_bit', $copy['status']);

        $t->same(4, $modify['bit']);
        $t->same('00000008', $modify['mask_hex']);
        $t->same(false, $modify['bit_set']);
        $t->same(true, $modify['applicable']);
        $t->same(false, $modify['allowed']);
        $t->same(true, $modify['denied']);
        $t->same('denied_by_permission_bit', $modify['status']);

        $t->same(9, $fill['bit']);
        $t->same('00000100', $fill['mask_hex']);
        $t->same(true, $fill['bit_set']);
        $t->same(false, $fill['applicable']);
        $t->same(false, $fill['allowed']);
        $t->same(false, $fill['denied']);
        $t->same('not_applicable_for_revision', $fill['status']);

        $t->same(12, $highQuality['bit']);
        $t->same('00000800', $highQuality['mask_hex']);
        $t->same(true, $highQuality['bit_set']);
        $t->same(false, $highQuality['applicable']);
        $t->same('not_applicable_for_revision', $highQuality['status']);

        $t->same(8, $permission['permission_bit_review_count']);
        $t->same($permissions['permission_bits'], $permission['permission_bits']);
        $t->same($permissions['applicable_permission_names'], $permission['applicable_permission_names']);
        $t->same($permissions['not_applicable_permission_names'], $permission['not_applicable_permission_names']);
        $t->same($permissions['permission_bit_statuses'], $permission['permission_bit_statuses']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same($permissions['permission_bits'], $handler['permission_bits']);
        $t->same($permissions['permission_bits'], $encryption['permission_bits']);
        $t->same(['fill_form_fields', 'extract_for_accessibility', 'assemble_document', 'high_quality_print'], $handler['not_applicable_permission_names']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, strtoupper(bin2hex($ownerKey)))
            && !str_contains($encoded, strtoupper(bin2hex($userKey))));
    },
    'keeps revision four Standard permission bit review tied to decryption gating' => static function (
        TestRunner $t
    ) use ($standardPermissionPdf, $permissionRowByName): void {
        [$pdf, $content, $ownerKey, $userKey] = $standardPermissionPdf(4, 4, 4294967252, 'REVISION_FOUR');
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permissions = $metadata['encryption']['standard_permissions'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $assemble = $permissionRowByName($permissions['permission_bits'], 'assemble_document');
        $annotations = $permissionRowByName($permissions['permission_bits'], 'add_or_modify_annotations');
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(4, $permissions['effective_revision']);
        $t->same('unsigned_decimal', $permissions['declared_form']);
        $t->same(true, $permissions['normalized_from_unsigned_decimal']);
        $t->same(-44, $permissions['signed']);
        $t->same(4294967252, $permissions['unsigned']);
        $t->same('FFFFFFD4', $permissions['hex']);
        $t->same([], $permissions['not_applicable_permission_names']);
        $t->same([
            'print',
            'modify_contents',
            'copy_or_extract',
            'add_or_modify_annotations',
            'fill_form_fields',
            'extract_for_accessibility',
            'assemble_document',
            'high_quality_print',
        ], $permissions['applicable_permission_names']);
        $t->same([
            'print',
            'copy_or_extract',
            'fill_form_fields',
            'extract_for_accessibility',
            'assemble_document',
            'high_quality_print',
        ], $permissions['allowed']);
        $t->same(['modify_contents', 'add_or_modify_annotations'], $permissions['denied']);
        $t->same(8, $permissions['permission_bit_review_count']);
        $t->same(['allowed_by_permission_bit', 'denied_by_permission_bit'], $permissions['permission_bit_statuses']);

        $t->same(11, $assemble['bit']);
        $t->same('00000400', $assemble['mask_hex']);
        $t->same(true, $assemble['bit_set']);
        $t->same(true, $assemble['applicable']);
        $t->same(true, $assemble['allowed']);
        $t->same('allowed_by_permission_bit', $assemble['status']);

        $t->same(6, $annotations['bit']);
        $t->same('00000020', $annotations['mask_hex']);
        $t->same(false, $annotations['bit_set']);
        $t->same(true, $annotations['applicable']);
        $t->same(true, $annotations['denied']);
        $t->same('denied_by_permission_bit', $annotations['status']);

        $t->same($permissions['permission_bits'], $permission['permission_bits']);
        $t->same([], $permission['not_applicable_permission_names']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same($permissions['permission_bit_statuses'], $handler['permission_bit_statuses']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, strtoupper(bin2hex($ownerKey)))
            && !str_contains($encoded, strtoupper(bin2hex($userKey))));
    },
];
