<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$duplicateEncryptMetadataPdf = static function (): array {
    $xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Duplicate EncryptMetadata Hidden XMP</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Ambiguous EncryptMetadata must not preserve this stream</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:MetadataDate>2026-06-05T14:11:14Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress duplicate EncryptMetadata fixture XMP.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate EncryptMetadata encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerValidation)) . ">"
        . " /U <" . strtoupper(bin2hex($userValidation)) . ">"
        . " /P -44 /EncryptMetadata true /EncryptMetadata false >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 6 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'fails closed when duplicate EncryptMetadata declarations would otherwise preserve encrypted XMP' => static function (
        TestRunner $t
    ) use ($duplicateEncryptMetadataPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $duplicateEncryptMetadataPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $policy = $encryption['metadata_source_policy'] ?? [];
        $review = $encryption['encrypt_metadata_declaration_review'] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(['encryption'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->true(!isset($metadata['title']));
        $t->same(true, $encryption['encrypt_metadata']);
        $t->same(true, $encryption['encrypt_metadata_explicit']);
        $t->same(false, $encryption['encrypt_metadata_trusted']);
        $t->same(true, $encryption['encrypt_metadata_defaulted_fail_closed']);
        $t->same('duplicate_encrypt_metadata_entries_review', $encryption['encrypt_metadata_status']);
        $t->same($review, $reviewEncryption['encrypt_metadata_declaration_review']);
        $t->same('encrypt_metadata_declaration_review', $review['source']);
        $t->same(2, $review['declared_entry_count']);
        $t->same(2, $review['boolean_entry_count']);
        $t->same(true, $review['duplicate_entries']);
        $t->same(true, $review['ambiguous']);
        $t->same(true, $review['effective_value']);
        $t->same(false, $review['trusted']);
        $t->same(true, $review['defaulted_fail_closed']);
        $t->same('duplicate_encrypt_metadata_entries_review', $review['status']);
        $t->same(['well_formed_encrypt_metadata_boolean'], $review['entry_statuses']);
        $t->same([true, false], $review['boolean_values']);

        $t->same('suppressed_encrypted_metadata_stream', $policy['xmp_stream_policy'] ?? null);
        $t->same(['xmp'], $policy['suppressed_sources'] ?? []);
        $t->same([], $policy['preserved_sources'] ?? []);
        $t->same('duplicate_encrypt_metadata_entries_review', $policy['encrypt_metadata_status'] ?? null);
        $t->same(false, $policy['encrypt_metadata_trusted'] ?? null);
        $t->same(true, $policy['encrypt_metadata_defaulted_fail_closed'] ?? null);

        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['encrypt_metadata_defaulted_fail_closed']);
        $t->true(in_array('encrypt_metadata_fail_closed', $report['review_reasons'], true));
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, 'Duplicate EncryptMetadata Hidden XMP')
            && !str_contains($encoded, 'Ambiguous EncryptMetadata must not preserve this stream')
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
    },
];
