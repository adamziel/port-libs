<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$generationSelectedStandardAuthPdf = static function (int $referenceGeneration) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td (Generation {$referenceGeneration} auth material encrypted text leak) Tj ET";
    $staleOwnerValidation = str_repeat('A', 48);
    $staleUserValidation = str_repeat('B', 48);
    $staleOwnerEncryptionKey = str_repeat('C', 32);
    $staleUserEncryptionKey = str_repeat('D', 32);
    $stalePermissionDigest = str_repeat('E', 16);
    $currentOwnerValidation = str_repeat('O', 48);
    $currentUserValidation = str_repeat('U', 48);
    $currentOwnerEncryptionKey = str_repeat('K', 32);
    $currentUserEncryptionKey = str_repeat('L', 32);
    $currentPermissionDigest = str_repeat('P', 16);

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber][$generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(
        5,
        0,
        '<< /Filter /Standard /V 5 /R 6 /Length 256'
            . " /O 20 {$referenceGeneration} R"
            . " /U 21 {$referenceGeneration} R"
            . " /OE 22 {$referenceGeneration} R"
            . " /UE 23 {$referenceGeneration} R"
            . ' /P -44 /EncryptMetadata true'
            . " /Perms 24 {$referenceGeneration} R"
            . ' /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>'
            . ' /StmF /StdCF /StrF /StdCF >>'
    );

    $addObject(20, 0, $hex($staleOwnerValidation));
    $addObject(20, 1, $hex($currentOwnerValidation));
    $addObject(21, 0, $hex($staleUserValidation));
    $addObject(21, 1, $hex($currentUserValidation));
    $addObject(22, 0, $hex($staleOwnerEncryptionKey));
    $addObject(22, 1, $hex($currentOwnerEncryptionKey));
    $addObject(23, 0, $hex($staleUserEncryptionKey));
    $addObject(23, 1, $hex($currentUserEncryptionKey));
    $addObject(24, 0, $hex($stalePermissionDigest));
    $addObject(24, 1, $hex($currentPermissionDigest));

    $xrefOffset = strlen($pdf);
    $row = static fn (int $offset, int $generation, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n0 25\n";
    for ($objectNumber = 0; $objectNumber <= 24; $objectNumber++) {
        if ($objectNumber === 0) {
            $pdf .= $row(0, 65535, 'f');
            continue;
        }

        if (isset($offsets[$objectNumber][1])) {
            $pdf .= $row($offsets[$objectNumber][1], 1);
            continue;
        }

        if (isset($offsets[$objectNumber][0])) {
            $pdf .= $row($offsets[$objectNumber][0], 0);
            continue;
        }

        $pdf .= $row(0, 65535, 'f');
    }
    $pdf .= "trailer\n<< /Size 25 /Root 1 0 R /Encrypt 5 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [
        $pdf,
        $content,
        $staleOwnerValidation,
        $staleUserValidation,
        $staleOwnerEncryptionKey,
        $staleUserEncryptionKey,
        $stalePermissionDigest,
        $currentOwnerValidation,
        $currentUserValidation,
        $currentOwnerEncryptionKey,
        $currentUserEncryptionKey,
        $currentPermissionDigest,
    ];
};

return [
    'resolves generation-exact Standard authentication material before encrypted permission review' => static function (
        TestRunner $t
    ) use ($generationSelectedStandardAuthPdf): void {
        [
            $pdf,
            $content,
            $staleOwnerValidation,
            $staleUserValidation,
            $staleOwnerEncryptionKey,
            $staleUserEncryptionKey,
            $stalePermissionDigest,
            $currentOwnerValidation,
            $currentUserValidation,
            $currentOwnerEncryptionKey,
            $currentUserEncryptionKey,
            $currentPermissionDigest,
        ] = $generationSelectedStandardAuthPdf(1);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('Standard', $metadata['encryption']['filter']);
        $t->same(5, $metadata['encryption']['version']);
        $t->same(6, $metadata['encryption']['revision']);
        $t->same('FFFFFFD4', $metadata['encryption']['standard_permissions']['hex']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same(hash('sha256', $currentOwnerValidation), $auth['entries']['owner_validation']['sha256']);
        $t->same(hash('sha256', $currentUserValidation), $auth['entries']['user_validation']['sha256']);
        $t->same(hash('sha256', $currentOwnerEncryptionKey), $auth['entries']['owner_encryption_key']['sha256']);
        $t->same(hash('sha256', $currentUserEncryptionKey), $auth['entries']['user_encryption_key']['sha256']);
        $t->same(hash('sha256', $currentPermissionDigest), $auth['permission_digest']['sha256']);
        $t->same('standard_authentication_material_ready_for_password_attempt', $material['status']);
        $t->same(true, $material['ready_for_password_attempt']);
        $t->same([], $material['unresolved_required_entries']);
        $t->same(true, $material['permission_digest_length_valid']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $trust['status']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);

        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $staleOwnerValidation)
            && !str_contains($encoded, $staleUserValidation)
            && !str_contains($encoded, $staleOwnerEncryptionKey)
            && !str_contains($encoded, $staleUserEncryptionKey)
            && !str_contains($encoded, $stalePermissionDigest)
            && !str_contains($encoded, $currentOwnerValidation)
            && !str_contains($encoded, $currentUserValidation)
            && !str_contains($encoded, $currentOwnerEncryptionKey)
            && !str_contains($encoded, $currentUserEncryptionKey)
            && !str_contains($encoded, $currentPermissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($staleOwnerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($currentPermissionDigest))));
    },
    'fails closed when Standard authentication material references stale object generations' => static function (
        TestRunner $t
    ) use ($generationSelectedStandardAuthPdf): void {
        [
            $pdf,
            $content,
            $staleOwnerValidation,
            $staleUserValidation,
            $staleOwnerEncryptionKey,
            $staleUserEncryptionKey,
            $stalePermissionDigest,
            $currentOwnerValidation,
            $currentUserValidation,
            $currentOwnerEncryptionKey,
            $currentUserEncryptionKey,
            $currentPermissionDigest,
        ] = $generationSelectedStandardAuthPdf(0);

        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);

        foreach (['owner_validation', 'user_validation', 'owner_encryption_key', 'user_encryption_key'] as $entryName) {
            $entry = $auth['entries'][$entryName];
            $t->same(true, $entry['present']);
            $t->same(false, $entry['bytes_resolved']);
            $t->same(null, $entry['bytes']);
            $t->same(null, $entry['sha256']);
            $t->same('authentication_entry_unresolved', $entry['status']);
            $t->same(['authentication_entry_unresolved'], $entry['entry_statuses']);
            $t->same(false, $entry['raw_bytes_exposed']);
        }

        $digest = $auth['permission_digest'];
        $t->same(true, $digest['present']);
        $t->same(null, $digest['bytes']);
        $t->same(null, $digest['sha256']);
        $t->same(false, $digest['length_valid']);
        $t->same('permission_digest_unresolved', $digest['status']);
        $t->same(['permission_digest_entry_unresolved'], $digest['entry_statuses']);
        $t->same(false, $digest['raw_bytes_exposed']);

        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same(['owner_validation', 'user_validation', 'owner_encryption_key', 'user_encryption_key'], $material['unresolved_required_entries']);
        $t->same([], $material['missing_required_entries']);
        $t->same([], $material['length_mismatch_required_entries']);
        $t->same(true, $material['permission_digest_present']);
        $t->same(false, $material['permission_digest_length_valid']);
        $t->same('permission_digest_unresolved', $material['permission_digest_status']);

        $t->same('permission_digest_malformed_before_permission_authentication', $permission['permission_authentication_status']);
        $t->same('permission_digest_malformed_before_permission_authentication', $trust['status']);
        $t->same(false, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);

        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $staleOwnerValidation)
            && !str_contains($encoded, $staleUserValidation)
            && !str_contains($encoded, $staleOwnerEncryptionKey)
            && !str_contains($encoded, $staleUserEncryptionKey)
            && !str_contains($encoded, $stalePermissionDigest)
            && !str_contains($encoded, $currentOwnerValidation)
            && !str_contains($encoded, $currentUserValidation)
            && !str_contains($encoded, $currentOwnerEncryptionKey)
            && !str_contains($encoded, $currentUserEncryptionKey)
            && !str_contains($encoded, $currentPermissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($staleOwnerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($currentPermissionDigest))));
    },
];
