<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$xmpPacket = static function (string $title): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$standardEncryptedIndirectOperandPdf = static function () use ($hex, $xmpPacket): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect Standard encrypted text leak) Tj ET';
    $xmp = $xmpPacket('Indirect Permission Root Title');
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter 6 0 R /V 7 0 R /R 8 0 R /Length 9 0 R /CF 10 0 R /StmF 11 0 R /StrF 11 0 R /EFF 12 0 R /O 14 0 R /U 15 0 R /OE 16 0 R /UE 17 0 R /P 18 0 R /EncryptMetadata 19 0 R /Perms 20 0 R >>\nendobj\n"
        . "6 0 obj\n/Standard\nendobj\n"
        . "7 0 obj\n5\nendobj\n"
        . "8 0 obj\n6\nendobj\n"
        . "9 0 obj\n256\nendobj\n"
        . "10 0 obj\n<< /StdCF 21 0 R /EmbeddedIdentity 22 0 R >>\nendobj\n"
        . "11 0 obj\n/StdCF\nendobj\n"
        . "12 0 obj\n/EmbeddedIdentity\nendobj\n"
        . "14 0 obj\n" . $hex($ownerValidation) . "\nendobj\n"
        . "15 0 obj\n" . $hex($userValidation) . "\nendobj\n"
        . "16 0 obj\n" . $hex($ownerEncryptionKey) . "\nendobj\n"
        . "17 0 obj\n" . $hex($userEncryptionKey) . "\nendobj\n"
        . "18 0 obj\n-44\nendobj\n"
        . "19 0 obj\nfalse\nendobj\n"
        . "20 0 obj\n" . $hex($permissionDigest) . "\nendobj\n"
        . "21 0 obj\n<< /CFM 23 0 R /AuthEvent 24 0 R /Length 25 0 R >>\nendobj\n"
        . "22 0 obj\n<< /CFM /Identity /AuthEvent /EFOpen >>\nendobj\n"
        . "23 0 obj\n/AESV3\nendobj\n"
        . "24 0 obj\n/DocOpen\nendobj\n"
        . "25 0 obj\n32\nendobj\n"
        . "30 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $ownerValidation, $userValidation, $ownerEncryptionKey, $permissionDigest];
};

$publicKeyEncryptedIndirectOperandPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect public-key encrypted text leak) Tj ET';
    $documentRecipientOne = 'INDIRECT_PUBLICKEY_DOCUMENT_RECIPIENT_ONE_SHOULD_NOT_LEAK';
    $documentRecipientTwo = 'INDIRECT_PUBLICKEY_DOCUMENT_RECIPIENT_TWO_SHOULD_NOT_LEAK';
    $embeddedRecipient = 'INDIRECT_PUBLICKEY_EMBEDDED_RECIPIENT_SHOULD_NOT_LEAK';
    $unusedRecipient = 'INDIRECT_PUBLICKEY_UNUSED_RECIPIENT_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter 6 0 R /SubFilter 7 0 R /V 8 0 R /Length 9 0 R /CF 10 0 R /StmF 11 0 R /StrF 11 0 R /EFF 12 0 R /EncryptMetadata true >>\nendobj\n"
        . "6 0 obj\n/Adobe.PubSec\nendobj\n"
        . "7 0 obj\n/adbe.pkcs7.s5\nendobj\n"
        . "8 0 obj\n4\nendobj\n"
        . "9 0 obj\n128\nendobj\n"
        . "10 0 obj\n<< /DefaultCryptFilter 13 0 R /EmbeddedFiles 14 0 R /UnusedRights << /CFM /V2 /AuthEvent /DocOpen /Length 16 /Recipients [" . $hex($unusedRecipient) . "] >> >>\nendobj\n"
        . "11 0 obj\n/DefaultCryptFilter\nendobj\n"
        . "12 0 obj\n/EmbeddedFiles\nendobj\n"
        . "13 0 obj\n<< /CFM 15 0 R /AuthEvent 16 0 R /Length 17 0 R /Recipients 18 0 R >>\nendobj\n"
        . "14 0 obj\n<< /CFM /AESV2 /AuthEvent /EFOpen /Length 16 /Recipients 20 0 R >>\nendobj\n"
        . "15 0 obj\n/AESV2\nendobj\n"
        . "16 0 obj\n/DocOpen\nendobj\n"
        . "17 0 obj\n16\nendobj\n"
        . "18 0 obj\n[" . $hex($documentRecipientOne) . " 19 0 R]\nendobj\n"
        . "19 0 obj\n" . $hex($documentRecipientTwo) . "\nendobj\n"
        . "20 0 obj\n[" . $hex($embeddedRecipient) . "]\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $documentRecipientOne, $documentRecipientTwo, $embeddedRecipient, $unusedRecipient];
};

return [
    'resolves indirect Standard permission and crypt-filter operands before encrypted import preflight' => static function (TestRunner $t) use ($standardEncryptedIndirectOperandPdf): void {
        [$pdf, $ownerValidation, $userValidation, $ownerEncryptionKey, $permissionDigest] = $standardEncryptedIndirectOperandPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $auth = $report['standard_authentication_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('Indirect Permission Root Title', $metadata['title']);
        $t->same('preserved_unencrypted_by_encrypt_metadata_false', $encryption['metadata_source_policy']['xmp_stream_policy']);
        $t->same('Standard', $encryption['filter']);
        $t->same(5, $encryption['version']);
        $t->same('aes_256', $encryption['algorithm']);
        $t->same(6, $encryption['revision']);
        $t->same('standard_handler_revision_6', $encryption['revision_label']);
        $t->same(256, $encryption['key_length_bits']);
        $t->same(false, $encryption['encrypt_metadata']);
        $t->same(true, $encryption['encrypt_metadata_explicit']);
        $t->same('StdCF', $encryption['stream_filter']);
        $t->same('StdCF', $encryption['string_filter']);
        $t->same('EmbeddedIdentity', $encryption['embedded_file_filter']);
        $t->same('AESV3', $encryption['crypt_filters']['StdCF']['method']);
        $t->same('DocOpen', $encryption['crypt_filters']['StdCF']['auth_event']);
        $t->same(32, $encryption['crypt_filters']['StdCF']['key_length_bytes']);
        $t->same('Identity', $encryption['crypt_filters']['EmbeddedIdentity']['method']);
        $t->same('EFOpen', $encryption['crypt_filters']['EmbeddedIdentity']['auth_event']);
        $t->same('FFFFFFD4', $encryption['standard_permissions']['hex']);
        $t->same(true, in_array('copy_or_extract', $encryption['standard_permissions']['allowed'], true));
        $t->same(true, $encryption['standard_permissions']['reserved_bits_valid']);
        $t->same(true, $reviewEncryption['perms_hash_present']);

        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['permission_word_well_formed']);
        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(['DocOpen', 'EFOpen'], $handler['standard_authentication_auth_events']);
        $t->same(hash('sha256', $ownerValidation), $auth['entries']['owner_validation']['sha256']);
        $t->same(hash('sha256', $userValidation), $auth['entries']['user_validation']['sha256']);
        $t->same(hash('sha256', $ownerEncryptionKey), $auth['entries']['owner_encryption_key']['sha256']);
        $t->same(hash('sha256', $permissionDigest), $auth['permission_digest']['sha256']);
        $t->same(false, $auth['password_validation_performed']);
        $t->same(false, $auth['permissions_authenticated']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, 'Indirect Standard encrypted text leak')
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
    },
    'resolves indirect public-key crypt-filter recipient selection before encrypted import preflight' => static function (TestRunner $t) use ($publicKeyEncryptedIndirectOperandPdf): void {
        [$pdf, $documentRecipientOne, $documentRecipientTwo, $embeddedRecipient, $unusedRecipient] = $publicKeyEncryptedIndirectOperandPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encryption = $report['encryption'];
        $cryptFilters = $metadata['encryption']['crypt_filters'];
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $selection = $recipientReview['crypt_filter_selection'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'public_key_recipient_permissions_undecoded'], $report['review_reasons']);
        $t->same('Adobe.PubSec', $encryption['filter']);
        $t->same('adbe.pkcs7.s5', $encryption['subfilter']);
        $t->same('DefaultCryptFilter', $encryption['stream_filter']);
        $t->same('DefaultCryptFilter', $encryption['string_filter']);
        $t->same('EmbeddedFiles', $encryption['embedded_file_filter']);
        $t->same('AESV2', $cryptFilters['DefaultCryptFilter']['method']);
        $t->same('DocOpen', $cryptFilters['DefaultCryptFilter']['auth_event']);
        $t->same(16, $cryptFilters['DefaultCryptFilter']['key_length_bytes']);
        $t->same(2, $cryptFilters['DefaultCryptFilter']['recipients']['recipient_count']);
        $t->same(1, $cryptFilters['EmbeddedFiles']['recipients']['recipient_count']);
        $t->same(1, $cryptFilters['UnusedRights']['recipients']['recipient_count']);
        $t->same(4, $encryption['public_key_recipient_count']);
        $t->same(3, $encryption['selected_public_key_recipient_count']);
        $t->same(['DefaultCryptFilter', 'EmbeddedFiles'], $encryption['public_key_crypt_filter_selection']['selected_recipient_filter_names']);

        $t->same('public_key_recipient_permissions', $permission['source']);
        $t->same('public_key_recipient_permissions_blocked_without_private_key', $permission['policy']);
        $t->same('blocked_encrypted_public_key_recipient_permissions', $permission['content_extraction_boundary']);
        $t->same(true, $permission['selected_recipient_permissions_declared']);
        $t->same(3, $permission['selected_public_key_recipient_count']);

        $t->same(['DefaultCryptFilter', 'EmbeddedFiles', 'UnusedRights'], $recipientReview['crypt_filter_recipient_filter_names']);
        $t->same(['DefaultCryptFilter', 'EmbeddedFiles'], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same(['UnusedRights'], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same([hash('sha256', $documentRecipientOne), hash('sha256', $documentRecipientTwo), hash('sha256', $embeddedRecipient)], $recipientReview['selected_recipient_sha256']);
        $t->same('public_key_crypt_filter_selection', $selection['source']);
        $t->same(['stream_filter' => 'DefaultCryptFilter', 'string_filter' => 'DefaultCryptFilter', 'embedded_file_filter' => 'EmbeddedFiles'], $selection['declared_content_filters']);
        $t->same(3, $selection['selected_recipient_count']);
        $t->same(0, $selection['selected_unresolved_recipient_count']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->same(false, $report['recipient_bytes_exposed']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, 'Indirect public-key encrypted text leak')
            && !str_contains($encoded, $documentRecipientOne)
            && !str_contains($encoded, $documentRecipientTwo)
            && !str_contains($encoded, $embeddedRecipient)
            && !str_contains($encoded, $unusedRecipient)
            && !str_contains($encoded, strtoupper(bin2hex($documentRecipientOne)))
            && !str_contains($encoded, strtoupper(bin2hex($unusedRecipient))));
    },
];
