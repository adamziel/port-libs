<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$publicKeyRecipientsTrailingOperandPdf = static function (string $subfilter, bool $cryptFilterRecipients): array {
    $content = $cryptFilterRecipients
        ? 'BT /F1 12 Tf 72 720 Td (Public-key crypt-filter recipient operand text leak) Tj ET'
        : 'BT /F1 12 Tf 72 720 Td (Public-key top-level recipient operand text leak) Tj ET';
    $recipient = $cryptFilterRecipients
        ? 'PUBLICKEY_CRYPT_FILTER_RECIPIENT_OPERAND_SHOULD_NOT_LEAK'
        : 'PUBLICKEY_TOP_LEVEL_RECIPIENT_OPERAND_SHOULD_NOT_LEAK';
    $recipientHex = strtoupper(bin2hex($recipient));
    $recipientOperand = "[<{$recipientHex}>] 6 0 R";
    $cryptFilterDictionary = $cryptFilterRecipients
        ? " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients {$recipientOperand} >> >>"
            . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter"
        : '';
    $legacyRecipients = $cryptFilterRecipients ? '' : " /Recipients {$recipientOperand}";

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /{$subfilter} /V 4 /Length 128"
        . $legacyRecipients
        . $cryptFilterDictionary
        . " /EncryptMetadata true >>\nendobj\n"
        . "6 0 obj\n<< /Unexpected /RecipientOperand >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $recipient, $recipientHex];
};

$assertPublicKeyRecipientTrailingOperandPreflight = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $recipient,
    string $recipientHex,
    string $expectedListSource,
    string $expectedPolicy,
    string $expectedBoundary
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $permission = $report['permission_preflight'];
    $recipientReview = $permission['public_key_recipient_review'];
    $recipientList = $recipientReview['recipient_lists'][0];
    $declarationReview = $recipientList['recipient_declaration_review'];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same($expectedPolicy, $permission['policy']);
    $t->same($expectedBoundary, $permission['content_extraction_boundary']);
    $t->same('public_key_recipient_declaration_malformed', $permission['source']);
    $t->same(true, $permission['permissions_declared']);
    $t->same(true, $permission['recipient_permissions_declared']);
    $t->same(true, $permission['public_key_recipient_declaration_fail_closed']);
    $t->same(true, $permission['public_key_recipient_malformed_entries']);
    $t->same(1, $permission['public_key_recipient_trailing_operand_count']);
    $t->same(['public_key_recipient_trailing_operand_review'], $permission['public_key_recipient_entry_statuses']);

    $t->same(true, $recipientReview['recipient_declaration_fail_closed']);
    $t->same(true, $recipientReview['recipient_malformed_entries']);
    $t->same(false, $recipientReview['recipient_duplicate_entries']);
    $t->same(1, $recipientReview['recipient_trailing_operand_count']);
    $t->same(['public_key_recipient_trailing_operand_review'], $recipientReview['recipient_entry_statuses']);
    $t->same('public_key_recipient_declaration_malformed_review', $recipientReview['permission_decode_status']);
    $t->same(1, $recipientReview['recipient_count']);
    $t->same(1, $recipientReview['selected_recipient_count']);
    $t->same([hash('sha256', $recipient)], $recipientReview['selected_recipient_sha256']);

    $t->same($expectedListSource, $recipientList['source']);
    $t->same(1, $recipientList['recipient_count']);
    $t->same(0, $recipientList['unresolved_recipient_count']);
    $t->same(true, $recipientList['recipient_declaration_fail_closed']);
    $t->same('malformed_public_key_recipient_entries_review', $recipientList['recipient_declaration_status']);
    $t->same('public_key_recipient_declaration_malformed_review', $recipientList['permission_decode_status']);
    $t->same('public_key_recipient_declaration_review', $declarationReview['source']);
    $t->same(1, $declarationReview['declared_entry_count']);
    $t->same(false, $declarationReview['duplicate_entries']);
    $t->same(true, $declarationReview['malformed_entries']);
    $t->same(true, $declarationReview['fail_closed']);
    $t->same(['public_key_recipient_trailing_operand_review'], $declarationReview['entry_statuses']);
    $t->same(['array'], $declarationReview['entry_operand_shapes']);
    $t->same(['indirect_reference'], $declarationReview['trailing_operand_shapes']);
    $t->same(['6 0 R'], $declarationReview['trailing_operand_previews']);

    $t->same('malformed_public_key_recipient_declaration_review', $permission['permission_handler_review']['permission_word_status']);
    $t->same(false, $permission['permission_handler_review']['permission_word_well_formed']);
    $t->same(true, $report['encryption']['public_key_recipient_declaration_fail_closed']);
    $t->same(1, $report['encryption']['public_key_recipient_trailing_operand_count']);
    $t->same(false, $recipientReview['executes_cms_parse']);
    $t->same(false, $report['executes_decryption']);
    $t->same(false, $report['executes_permission_enforcement']);
    $t->same(false, $report['executes_python_or_models']);
    $t->same(false, $report['executes_external_pdf_tools']);
    $t->true(is_string($encoded)
        && !str_contains($encoded, $content)
        && !str_contains($encoded, $recipient)
        && !str_contains($encoded, $recipientHex));
};

return [
    'fails closed when legacy public-key Recipients array has a trailing operand' => static function (
        TestRunner $t
    ) use ($publicKeyRecipientsTrailingOperandPdf, $assertPublicKeyRecipientTrailingOperandPreflight): void {
        $assertPublicKeyRecipientTrailingOperandPreflight(
            $t,
            ...array_merge(
                $publicKeyRecipientsTrailingOperandPdf('adbe.pkcs7.s4', false),
                [
                    'encryption_dictionary_recipients',
                    'permissions_malformed_blocked_without_decryption',
                    'blocked_encrypted_public_key_recipient_declaration_malformed',
                ]
            )
        );
    },

    'fails closed when selected public-key crypt-filter Recipients array has a trailing operand' => static function (
        TestRunner $t
    ) use ($publicKeyRecipientsTrailingOperandPdf, $assertPublicKeyRecipientTrailingOperandPreflight): void {
        $assertPublicKeyRecipientTrailingOperandPreflight(
            $t,
            ...array_merge(
                $publicKeyRecipientsTrailingOperandPdf('adbe.pkcs7.s5', true),
                [
                    'crypt_filter_recipients',
                    'permissions_malformed_blocked_without_decryption',
                    'blocked_encrypted_public_key_recipient_declaration_malformed',
                ]
            )
        );
    },
];
