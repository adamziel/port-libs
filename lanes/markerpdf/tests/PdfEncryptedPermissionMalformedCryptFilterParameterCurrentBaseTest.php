<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$malformedCryptFilterParameterPdf = static function (
    string $parameterName,
    string $filterBody,
    string $label
) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} malformed crypt-filter parameter encrypted text leak) Tj ET";
    $ownerKey = str_repeat(substr($label, 0, 1), 32);
    $userKey = str_repeat(substr($label, -1), 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerKey)
        . " /U " . $hex($userKey)
        . " /P -44 /EncryptMetadata true"
        . " /CF <<"
        . " /DocCF << {$filterBody} >>"
        . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >>"
        . " >>"
        . " /StmF /DocCF /StrF /DocCF /EFF /ClearEmbedded >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey, $parameterName];
};

$findCryptFilterParameterRow = static function (array $rows, string $parameterName): array {
    foreach ($rows as $row) {
        if (is_array($row) && ($row['pdf_name'] ?? null) === $parameterName) {
            return $row;
        }
    }

    return [];
};

$assertMalformedCryptFilterParameterPreflight = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerKey,
    string $userKey,
    string $parameterName,
    string $operandShape,
    string $entryStatus
) use ($findCryptFilterParameterRow): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $metadataEncryption = $metadata['encryption'];
    $reportEncryption = $report['encryption'];
    $review = $report['crypt_filter_content_review'];
    $permission = $report['permission_preflight'];
    $parameterReview = $metadataEncryption['crypt_filters']['DocCF']['parameter_declaration_review'];
    $parameterRow = $findCryptFilterParameterRow($parameterReview['rows'], $parameterName);
    $parameterEntry = $parameterRow['entries'][0] ?? [];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', $plainText);
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same([
        'encrypted_document',
        'encrypted_text_extraction_blocked',
        'copy_or_extract_allowed_but_crypt_filter_fail_closed',
        'crypt_filter_text_fail_closed',
    ], $report['review_reasons']);

    $t->same('Standard', $metadataEncryption['filter']);
    $t->same(4, $metadataEncryption['version']);
    $t->same(4, $metadataEncryption['revision']);
    $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
    $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));

    $t->same(true, $metadataEncryption['crypt_filters']['DocCF']['parameter_declaration_fail_closed']);
    $t->same(
        'malformed_crypt_filter_parameter_entries_review',
        $metadataEncryption['crypt_filters']['DocCF']['parameter_declaration_status']
    );
    $t->same([], $metadataEncryption['crypt_filters']['DocCF']['duplicate_parameter_names']);
    $t->same([$parameterName], $metadataEncryption['crypt_filters']['DocCF']['malformed_parameter_names']);
    $t->same(1, $metadataEncryption['crypt_filters']['DocCF']['malformed_parameter_count']);
    $t->same(false, $metadataEncryption['crypt_filters']['ClearEmbedded']['parameter_declaration_fail_closed'] ?? false);

    $t->same('crypt_filter_parameter_declaration_review', $parameterReview['source']);
    $t->same('DocCF', $parameterReview['filter_name']);
    $t->same([], $parameterReview['duplicate_parameter_names']);
    $t->same(0, $parameterReview['duplicate_parameter_count']);
    $t->same([$parameterName], $parameterReview['malformed_parameter_names']);
    $t->same(1, $parameterReview['malformed_parameter_count']);
    $t->same('malformed_crypt_filter_parameter_entries_review', $parameterReview['status']);
    $t->same(true, $parameterReview['fail_closed']);

    $t->same('crypt_filter_parameter_declaration_row', $parameterRow['source'] ?? null);
    $t->same($parameterName, $parameterRow['pdf_name'] ?? null);
    $t->same(1, $parameterRow['declared_entry_count'] ?? null);
    $t->same(false, $parameterRow['duplicate_entries'] ?? null);
    $t->same([$operandShape], $parameterRow['entry_operand_shapes'] ?? []);
    $t->same([$entryStatus], $parameterRow['entry_statuses'] ?? []);
    $t->same(true, $parameterRow['malformed_entries'] ?? null);
    $t->same(1, $parameterRow['malformed_entry_count'] ?? null);
    $t->same(true, $parameterEntry['resolved'] ?? null);
    $t->same($operandShape, $parameterEntry['operand_shape'] ?? null);
    $t->same($entryStatus, $parameterEntry['status'] ?? null);
    $t->same(false, $parameterEntry['executes_decryption'] ?? false);
    $t->same(false, $parameterEntry['executes_permission_enforcement'] ?? false);

    $t->same(1, $report['crypt_filter_content_review_count']);
    $t->same($review, $permission['crypt_filter_content_review']);
    $t->same($review, $reportEncryption['crypt_filter_content_review']);
    $t->same('malformed_crypt_filter_parameter_entry_fail_closed', $review['text_content_policy']);
    $t->same('identity_filter_review_only_payload_boundary', $review['embedded_file_payload_policy']);
    $t->same(['document_streams', 'document_strings'], $review['fail_closed_role_names']);
    $t->same(['DocCF'], $review['fail_closed_filter_names']);
    $t->same(2, $review['fail_closed_role_count']);
    $t->same(['malformed_crypt_filter_parameter_entries_review'], $review['crypt_filter_parameter_statuses']);
    $t->same([], $review['crypt_filter_parameter_duplicate_role_names']);
    $t->same([], $review['crypt_filter_parameter_duplicate_filter_names']);
    $t->same([], $review['crypt_filter_parameter_duplicate_names']);
    $t->same(['document_streams', 'document_strings'], $review['crypt_filter_parameter_malformed_role_names']);
    $t->same(['DocCF'], $review['crypt_filter_parameter_malformed_filter_names']);
    $t->same([$parameterName], $review['crypt_filter_parameter_malformed_names']);

    $streamRole = $review['roles'][0];
    $stringRole = $review['roles'][1];
    $embeddedRole = $review['roles'][2];
    $t->same('document_streams', $streamRole['role']);
    $t->same('DocCF', $streamRole['filter_name']);
    $t->same(true, $streamRole['crypt_filter_parameter_fail_closed']);
    $t->same('malformed_crypt_filter_parameter_entries_review', $streamRole['crypt_filter_parameter_declaration_status']);
    $t->same([$parameterName], $streamRole['crypt_filter_parameter_malformed_names']);
    $t->same($parameterReview, $streamRole['crypt_filter_parameter_declaration_review']);

    $t->same('document_strings', $stringRole['role']);
    $t->same('DocCF', $stringRole['filter_name']);
    $t->same(true, $stringRole['crypt_filter_parameter_fail_closed']);
    $t->same([$parameterName], $stringRole['crypt_filter_parameter_malformed_names']);

    $t->same('embedded_file_streams', $embeddedRole['role']);
    $t->same('ClearEmbedded', $embeddedRole['filter_name']);
    $t->same('Identity', $embeddedRole['method']);
    $t->same('identity_crypt_filter', $embeddedRole['status']);
    $t->same(false, $embeddedRole['crypt_filter_parameter_fail_closed']);
    $t->same([], $embeddedRole['crypt_filter_parameter_malformed_names']);

    $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
    $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
    $t->same('blocked_by_malformed_document_crypt_filter_parameter', $permission['content_extraction_boundary']);
    $t->same('malformed_crypt_filter_parameter_entry_fail_closed', $permission['crypt_filter_text_policy']);
    $t->same(true, $permission['crypt_filter_text_fail_closed']);
    $t->same(['document_streams', 'document_strings'], $permission['crypt_filter_fail_closed_role_names']);
    $t->same(['DocCF'], $permission['crypt_filter_fail_closed_filter_names']);
    $t->same(true, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);
    $t->same(false, $permission['decryption_performed']);

    $t->same(false, $report['executes_decryption']);
    $t->same(false, $report['executes_permission_enforcement']);
    $t->same(false, $report['executes_python_or_models']);
    $t->same(false, $report['executes_external_pdf_tools']);
    $t->true(is_string($encoded)
        && !str_contains($encoded, $content)
        && !str_contains($encoded, $ownerKey)
        && !str_contains($encoded, $userKey)
        && !str_contains($encoded, strtoupper(bin2hex($ownerKey)))
        && !str_contains($encoded, strtoupper(bin2hex($userKey))));
};

return [
    'fails closed when selected crypt-filter method is a literal string operand' => static function (
        TestRunner $t
    ) use ($malformedCryptFilterParameterPdf, $assertMalformedCryptFilterParameterPreflight): void {
        [$pdf, $content, $ownerKey, $userKey, $parameterName] = $malformedCryptFilterParameterPdf(
            'CFM',
            '/CFM (AESV2) /AuthEvent /DocOpen /Length 16',
            'METHOD'
        );

        $assertMalformedCryptFilterParameterPreflight(
            $t,
            $pdf,
            $content,
            $ownerKey,
            $userKey,
            $parameterName,
            'literal_string',
            'crypt_filter_parameter_non_name_operand_review'
        );
    },
    'fails closed when selected crypt-filter AuthEvent is a literal string operand' => static function (
        TestRunner $t
    ) use ($malformedCryptFilterParameterPdf, $assertMalformedCryptFilterParameterPreflight): void {
        [$pdf, $content, $ownerKey, $userKey, $parameterName] = $malformedCryptFilterParameterPdf(
            'AuthEvent',
            '/CFM /AESV2 /AuthEvent (DocOpen) /Length 16',
            'AUTHEVENT'
        );

        $assertMalformedCryptFilterParameterPreflight(
            $t,
            $pdf,
            $content,
            $ownerKey,
            $userKey,
            $parameterName,
            'literal_string',
            'crypt_filter_parameter_non_name_operand_review'
        );
    },
    'fails closed when selected crypt-filter Length is an array operand' => static function (
        TestRunner $t
    ) use ($malformedCryptFilterParameterPdf, $assertMalformedCryptFilterParameterPreflight): void {
        [$pdf, $content, $ownerKey, $userKey, $parameterName] = $malformedCryptFilterParameterPdf(
            'Length',
            '/CFM /AESV2 /AuthEvent /DocOpen /Length [16]',
            'LENGTH'
        );

        $assertMalformedCryptFilterParameterPreflight(
            $t,
            $pdf,
            $content,
            $ownerKey,
            $userKey,
            $parameterName,
            'array',
            'crypt_filter_parameter_composite_operand_review'
        );
    },
];
