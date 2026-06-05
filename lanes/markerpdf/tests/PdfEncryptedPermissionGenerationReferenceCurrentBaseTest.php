<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedGenerationReferencePdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Generation-selected encrypted text leak) Tj ET';
    $staleOwner = str_repeat('S', 32);
    $staleUser = str_repeat('T', 32);
    $currentOwner = str_repeat('C', 32);
    $currentUser = str_repeat('U', 32);

    $pdf = "%PDF-1.7\n";
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
        '<< /Filter /Standard /V 4 /R 4 /Length 128'
            . ' /O <' . strtoupper(bin2hex($staleOwner)) . '>'
            . ' /U <' . strtoupper(bin2hex($staleUser)) . '>'
            . ' /P -64 /EncryptMetadata true >>'
    );
    $addObject(
        5,
        1,
        '<< /Filter /Standard /V 4 /R 4 /Length 128'
            . ' /O <' . strtoupper(bin2hex($currentOwner)) . '>'
            . ' /U <' . strtoupper(bin2hex($currentUser)) . '>'
            . ' /P -44 /EncryptMetadata true >>'
    );

    $xrefOffset = strlen($pdf);
    $row = static fn (int $offset, int $generation, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n0 6\n"
        . $row(0, 65535, 'f')
        . $row($offsets[1][0], 0)
        . $row($offsets[2][0], 0)
        . $row($offsets[3][0], 0)
        . $row($offsets[4][0], 0)
        . $row($offsets[5][0], 0)
        . "trailer\n<< /Size 6 /Root 1 0 R /Encrypt 5 1 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $content, $staleOwner, $staleUser, $currentOwner, $currentUser];
};

return [
    'uses trailer Encrypt object generation before encrypted permission preflight' => static function (
        TestRunner $t
    ) use ($encryptedGenerationReferencePdf): void {
        [$pdf, $content, $staleOwner, $staleUser, $currentOwner, $currentUser] = $encryptedGenerationReferencePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same('trailer_encrypt', $encryption['source']);
        $t->same(5, $encryption['object_number']);
        $t->same(1, $encryption['object_generation']);
        $t->same('Standard', $encryption['filter']);
        $t->same(4, $encryption['version']);
        $t->same(4, $encryption['revision']);
        $t->same('standard_handler_revision_4', $encryption['revision_label']);
        $t->same('FFFFFFD4', $encryption['standard_permissions']['hex']);
        $t->same(-44, $encryption['standard_permissions']['signed']);
        $t->same(4294967252, $encryption['standard_permissions']['unsigned']);
        $t->same(true, $encryption['standard_permissions']['reserved_bits_valid']);
        $t->same(true, in_array('copy_or_extract', $encryption['standard_permissions']['allowed'], true));
        $t->same(false, in_array('copy_or_extract', $encryption['standard_permissions']['denied'], true));

        $t->same(5, $reviewEncryption['object_number']);
        $t->same(1, $reviewEncryption['object_generation']);
        $t->same('FFFFFFD4', $reviewEncryption['permission_hex']);
        $t->same(true, $reviewEncryption['copy_or_extract_allowed']);
        $t->same(true, $reviewEncryption['permission_bits_reliable']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $staleOwner)
            && !str_contains($encoded, $staleUser)
            && !str_contains($encoded, $currentOwner)
            && !str_contains($encoded, $currentUser)
            && !str_contains($encoded, strtoupper(bin2hex($staleOwner)))
            && !str_contains($encoded, strtoupper(bin2hex($currentOwner))));
    },
];
