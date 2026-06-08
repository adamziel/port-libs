<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$standardSubfilterBoundaryPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Standard handler public-key SubFilter encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);
    $ownerHex = strtoupper(bin2hex($ownerValidation));
    $userHex = strtoupper(bin2hex($userValidation));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /SubFilter /adbe.pkcs7.s5 /V 4 /R 4 /Length 128"
        . " /O <{$ownerHex}> /U <{$userHex}> /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerHex, $userHex];
};

return [
    'fails closed when a Standard security handler declares a public-key SubFilter' => static function (
        TestRunner $t
    ) use ($standardSubfilterBoundaryPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerHex, $userHex] = $standardSubfilterBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $metadataEncryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $permission['permission_handler_review'];
        $subfilterReview = $permission['security_handler_subfilter_declaration_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'security_handler_subfilter_declaration_malformed',
            'standard_security_handler_subfilter_incompatible',
        ], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same('adbe.pkcs7.s5', $metadataEncryption['subfilter']);
        $t->same('standard_security_handler_subfilter_incompatible_review', $metadataEncryption['security_handler_subfilter_declaration_review']['status']);
        $t->same(true, $metadataEncryption['security_handler_subfilter_declaration_review']['fail_closed']);
        $t->same(true, $metadataEncryption['security_handler_subfilter_declaration_review']['standard_handler_incompatible']);
        $t->same(['adbe.pkcs7.s5'], $metadataEncryption['security_handler_subfilter_declaration_review']['subfilter_names']);
        $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
        $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));

        $t->same('security_handler_subfilter_declaration_malformed', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_security_handler_subfilter_malformed', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(true, $permission['standard_permissions_declared']);
        $t->same(false, $permission['recipient_permissions_declared']);
        $t->same(true, $permission['security_handler_subfilter_declaration_fail_closed']);
        $t->same(true, $permission['security_handler_subfilter_permission_boundary']);
        $t->same(true, $permission['security_handler_standard_subfilter_incompatible']);
        $t->same(false, $permission['security_handler_duplicate_subfilter_entries']);
        $t->same(false, $permission['security_handler_malformed_subfilter_entries']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(null, $permission['accessibility_extract_allowed']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['permission_bits']);
        $t->same(0, $permission['permission_bit_review_count']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('security_handler_subfilter_declaration_review', $subfilterReview['source']);
        $t->same('Standard', $subfilterReview['handler']);
        $t->same(1, $subfilterReview['declared_entry_count']);
        $t->same(false, $subfilterReview['duplicate_entries']);
        $t->same(false, $subfilterReview['malformed_entries']);
        $t->same(true, $subfilterReview['standard_handler_incompatible']);
        $t->same(true, $subfilterReview['fail_closed']);
        $t->same('standard_security_handler_subfilter_incompatible_review', $subfilterReview['status']);
        $t->same('adbe.pkcs7.s5', $subfilterReview['selected_subfilter_name']);
        $t->same(['security_handler_subfilter_name'], $subfilterReview['entry_statuses']);
        $t->same(['name'], $subfilterReview['entry_operand_shapes']);

        $t->same('malformed_security_handler_subfilter_declaration_review', $handler['status']);
        $t->same(false, $handler['handler_supported_for_native_permission_review']);
        $t->same(false, $handler['permission_word_well_formed']);
        $t->same(true, $handler['security_handler_subfilter_declaration_fail_closed']);
        $t->same(true, $handler['security_handler_subfilter_permission_boundary']);
        $t->same(true, $handler['security_handler_standard_subfilter_incompatible']);
        $t->same([], $handler['allowed'] ?? []);
        $t->same([], $handler['permission_bits']);
        $t->same(false, $handler['executes_permission_enforcement']);

        $t->same(false, $reviewEncryption['permission_bits_reliable']);
        $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
        $t->same([], $reviewEncryption['allowed']);
        $t->same([], $reviewEncryption['permission_bits']);
        $t->same('standard_security_handler_subfilter_incompatible_review', $reviewEncryption['security_handler_subfilter_declaration_status']);
        $t->same(true, $reviewEncryption['security_handler_standard_subfilter_incompatible']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerHex)
            && !str_contains($encoded, $userHex));
    },
];
