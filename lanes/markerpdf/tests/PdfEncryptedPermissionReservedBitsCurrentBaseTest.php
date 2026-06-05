<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$reservedBitsPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Malformed reserved permission encrypted text leak) Tj ET';
    $ownerKey = 'RESERVED_PERMISSION_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'RESERVED_PERMISSION_USER_KEY_SHOULD_NOT_LEAK';
    $ownerBytes = str_pad($ownerKey, 32, 'O');
    $userBytes = str_pad($userKey, 32, 'U');

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerBytes)) . ">"
        . " /U <" . strtoupper(bin2hex($userBytes)) . ">"
        . " /P 16 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes];
};

return [
    'fails closed when Standard permission reserved bits are malformed before import preflight' => static function (
        TestRunner $t
    ) use ($reservedBitsPdf): void {
        [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes] = $reservedBitsPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $rawPermissions = $metadata['encryption']['standard_permissions'];
        $review = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $declaration = $permission['standard_permission_word_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'permission_word_reserved_bits_malformed',
        ], $report['review_reasons']);

        $t->same(16, $rawPermissions['declared']);
        $t->same(16, $rawPermissions['signed']);
        $t->same(16, $rawPermissions['unsigned']);
        $t->same('00000010', $rawPermissions['hex']);
        $t->same(true, $rawPermissions['permission_word_range_valid']);
        $t->same('permission_word_in_32bit_range', $rawPermissions['permission_word_range_status']);
        $t->same(false, $rawPermissions['reserved_bits_valid']);
        $t->same('malformed_reserved_bits_review', $rawPermissions['permission_word_status']);
        $t->same(['copy_or_extract'], $rawPermissions['allowed']);
        $t->same([
            'print',
            'modify_contents',
            'add_or_modify_annotations',
            'fill_form_fields',
            'extract_for_accessibility',
            'assemble_document',
            'high_quality_print',
        ], $rawPermissions['denied']);
        $t->same(false, $rawPermissions['reserved_bits']['set_bits_ok']);
        $t->same(true, $rawPermissions['reserved_bits']['clear_bits_ok']);
        $t->same(['reserved_bits_7_8_clear', 'reserved_bits_13_32_clear'], $rawPermissions['reserved_bits']['violations']);

        $t->same('standard_security_handler_malformed_permissions', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same('00000010', $permission['permission_hex']);
        $t->same(16, $permission['permission_signed']);
        $t->same(16, $permission['permission_unsigned']);
        $t->same(true, $permission['permission_word_range_valid']);
        $t->same('malformed_reserved_bits_review', $permission['permission_word_status'] ?? $handler['permission_word_status']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(null, $permission['accessibility_extract_allowed']);
        $t->same(null, $permission['print_quality']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['applicable_permission_names']);
        $t->same([], $permission['permission_bit_statuses']);
        $t->same([], $permission['permission_bits']);
        $t->same(0, $permission['permission_bit_review_count']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('malformed_reserved_bits_review', $handler['status']);
        $t->same(false, $handler['permission_word_well_formed']);
        $t->same(true, $handler['handler_supported_for_native_permission_review']);
        $t->same([], $handler['allowed'] ?? []);
        $t->same([], $handler['permission_bits']);
        $t->same(0, $handler['permission_bit_review_count']);
        $t->same(false, $handler['executes_permission_enforcement']);

        $t->same('malformed_reserved_bits_review', $review['permission_word_status']);
        $t->same(false, $review['permission_word_well_formed']);
        $t->same(false, $review['permission_bits_reliable']);
        $t->same(['reserved_bits_7_8_clear', 'reserved_bits_13_32_clear'], $review['reserved_bit_violations']);
        $t->same(null, $review['copy_or_extract_allowed']);
        $t->same(null, $review['accessibility_extract_allowed']);
        $t->same(null, $review['print_quality']);
        $t->same([], $review['allowed']);
        $t->same([], $review['denied']);
        $t->same([], $review['permission_bits']);
        $t->same(0, $review['permission_bit_review_count']);

        $t->same('standard_permission_word_declaration_review', $declaration['source']);
        $t->same(1, $declaration['declared_entry_count']);
        $t->same(1, $declaration['integer_entry_count']);
        $t->same('malformed_standard_permission_word_review', $declaration['status']);
        $t->same(['malformed_reserved_bits_review'], $declaration['entry_statuses']);
        $t->same([16], $declaration['unsigned_values']);
        $t->same(['00000010'], $declaration['hex_values']);

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
    'keeps malformed reserved-bit encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($reservedBitsPdf): void {
        [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes] = $reservedBitsPdf();
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
