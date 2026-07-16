<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CorePdfConverter;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$qpdfRevisionSixFixture = static function (string $variant): string {
    // Generated once with qpdf 12.3.2:
    // qpdf --encrypt userpass ownerpass 256 --print=full --modify=none --extract=n --annotate=n -- plain.pdf r6-noextract.pdf
    // qpdf --encrypt userpass ownerpass 256 --print=full --modify=none --extract=y --annotate=n -- plain.pdf r6-extract.pdf
    $fixtures = [
        'r6-noextract' => <<<'BASE64'
JVBERi0xLjcKJb/3ov4KMSAwIG9iago8PCAvRXh0ZW5zaW9ucyA8PCAvQURCRSA8PCAvQmFzZVZl
cnNpb24gLzEuNyAvRXh0ZW5zaW9uTGV2ZWwgOCA+PiA+PiAvUGFnZXMgMiAwIFIgL1R5cGUgL0Nh
dGFsb2cgPj4KZW5kb2JqCjIgMCBvYmoKPDwgL0NvdW50IDEgL0tpZHMgWyAzIDAgUiBdIC9UeXBl
IC9QYWdlcyA+PgplbmRvYmoKMyAwIG9iago8PCAvQ29udGVudHMgNCAwIFIgL01lZGlhQm94IFsg
MCAwIDYxMiA3OTIgXSAvUGFyZW50IDIgMCBSIC9SZXNvdXJjZXMgPDwgL0ZvbnQgPDwgL0YxIDUg
MCBSID4+ID4+IC9UeXBlIC9QYWdlID4+CmVuZG9iago0IDAgb2JqCjw8IC9MZW5ndGggOTYgL0Zp
bHRlciAvRmxhdGVEZWNvZGUgPj4Kc3RyZWFtCogJKwyETArTz8Wku934Eu/Y9P2saRvMhpMVYp+S
yql0FUt5yhlLXVMy1UaoP60sOAFs9mXPZFNBzb5LQ/tZ7RswbxUKtvCHZhXl7XCdHw9ZSkFxs7lf
fV8Hrzgrt3DMC2VuZHN0cmVhbQplbmRvYmoKNSAwIG9iago8PCAvQmFzZUZvbnQgL0hlbHZldGlj
YSAvU3VidHlwZSAvVHlwZTEgL1R5cGUgL0ZvbnQgPj4KZW5kb2JqCjYgMCBvYmoKPDwgL0NGIDw8
IC9TdGRDRiA8PCAvQXV0aEV2ZW50IC9Eb2NPcGVuIC9DRk0gL0FFU1YzIC9MZW5ndGggMzIgPj4g
Pj4gL0ZpbHRlciAvU3RhbmRhcmQgL0xlbmd0aCAyNTYgL08gPDdlYjFlMzQxNzNlNDUxN2VkZmE1
YmI0ZjI1NzM1NTYxYjY4NzBkNjBlMTZmNTI2MDNkMDc4M2M0MjAzMjllOGYwYmQzYmU1NGQwZDYw
ZGQxMGQ2M2IzNTY2NjEwMThlMz4gL09FIDw5ZWUzNTBkMTAwMDE2ZDMyZDVjMDZjMGE0YzFiMjg2
YzA0ZWI3N2NhYjMxNWJjNjgwOGQ2YjIwOGViNTNhN2M1PiAvUCAtMTM0MCAvUGVybXMgPDc0M2M4
YjMwYzA2YjBjOWMzMzMzZGYzZWI3NmMzMGI3PiAvUiA2IC9TdG1GIC9TdGRDRiAvU3RyRiAvU3Rk
Q0YgL1UgPDM1ZmI0ODc0NDAwMmY2MDUwNDQyNTA3MjE0NzkzNGZkMTY0YzJiZDc0N2UyY2I3Mzhh
NDE4ZjA2MzllYjE5ZmJlNTlmM2VmOTcyN2FjYjQyZWJlYjQ4NTM1NzVjYzJjZT4gL1VFIDxkODE1
NTcxMWM0YTM3ODg0ODQyNDU0MGQ0NjEyMjRhNDhmMTUyZGExOThiYjlmODNhMWI1Njk1ZjEyN2Ji
OWMyPiAvViA1ID4+CmVuZG9iagp4cmVmCjAgNwowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAw
MTUgMDAwMDAgbiAKMDAwMDAwMDEzMCAwMDAwMCBuIAowMDAwMDAwMTg5IDAwMDAwIG4gCjAwMDAw
MDAzMTcgMDAwMDAgbiAKMDAwMDAwMDQ4MyAwMDAwMCBuIAowMDAwMDAwNTUzIDAwMDAwIG4gCnRy
YWlsZXIgPDwgL1Jvb3QgMSAwIFIgL1NpemUgNyAvSUQgWzwwNDY2YzMyNDJlMGRiZTdjYmEwZmIz
NjdlZDgzNzExNj48MDQ2NmMzMjQyZTBkYmU3Y2JhMGZiMzY3ZWQ4MzcxMTY+XSAvRW5jcnlwdCA2
IDAgUiA+PgpzdGFydHhyZWYKMTEwMwolJUVPRgo=
BASE64,
        'r6-extract' => <<<'BASE64'
JVBERi0xLjcKJb/3ov4KMSAwIG9iago8PCAvRXh0ZW5zaW9ucyA8PCAvQURCRSA8PCAvQmFzZVZl
cnNpb24gLzEuNyAvRXh0ZW5zaW9uTGV2ZWwgOCA+PiA+PiAvUGFnZXMgMiAwIFIgL1R5cGUgL0Nh
dGFsb2cgPj4KZW5kb2JqCjIgMCBvYmoKPDwgL0NvdW50IDEgL0tpZHMgWyAzIDAgUiBdIC9UeXBl
IC9QYWdlcyA+PgplbmRvYmoKMyAwIG9iago8PCAvQ29udGVudHMgNCAwIFIgL01lZGlhQm94IFsg
MCAwIDYxMiA3OTIgXSAvUGFyZW50IDIgMCBSIC9SZXNvdXJjZXMgPDwgL0ZvbnQgPDwgL0YxIDUg
MCBSID4+ID4+IC9UeXBlIC9QYWdlID4+CmVuZG9iago0IDAgb2JqCjw8IC9MZW5ndGggOTYgL0Zp
bHRlciAvRmxhdGVEZWNvZGUgPj4Kc3RyZWFtCheWvSKFn07NsFwXXVYMrQl28g9fO8wA12Qed0es
adZ+yNrK+aIkS06QYpLUTxW/lZeAO+bB0BROAeXtHDoibqzqqapYrazEeX+AzQCNX79VIoQvZDFy
1XPZfu6vJdOWPmVuZHN0cmVhbQplbmRvYmoKNSAwIG9iago8PCAvQmFzZUZvbnQgL0hlbHZldGlj
YSAvU3VidHlwZSAvVHlwZTEgL1R5cGUgL0ZvbnQgPj4KZW5kb2JqCjYgMCBvYmoKPDwgL0NGIDw8
IC9TdGRDRiA8PCAvQXV0aEV2ZW50IC9Eb2NPcGVuIC9DRk0gL0FFU1YzIC9MZW5ndGggMzIgPj4g
Pj4gL0ZpbHRlciAvU3RhbmRhcmQgL0xlbmd0aCAyNTYgL08gPDkyNjczMmIyNGMwMDlmODNjN2E1
YTgxMjQwZGM3NmYzNDU2ZDEyYzI5MDA4YzdmZDBiNDhkYWJiOGM1MGJkYjAwYzY5ZGUwZjViMDIz
NWZmMjFmNjc1MDU0ZDNiN2JjNz4gL09FIDxkODNlODZhMmI4ZWVkMDdjMDIyZWQzYjViZDIxZWVk
YmFhNmE5N2Q2ZDAxNjQ2ZmJiOTA0OTUxNjFhYTRhNDZlPiAvUCAtMTMyNCAvUGVybXMgPGZkMDQ1
ZDBhMmM0MGI4OWUwNWY3YWM4NjlmZmM5ODY1PiAvUiA2IC9TdG1GIC9TdGRDRiAvU3RyRiAvU3Rk
Q0YgL1UgPDBmODg3N2ExZDNlZTJmZDg2MzQ3MDc1OTQ5ZTEzZWQ4OTZjMTNhMWI0MDc5ZDExY2U1
NTUyYmM2MDBlZmQ3NjY4MjhjOTU1OGI1MGUwYTRhZjE2NzM0OWY1MjVjZDU5MD4gL1VFIDxiN2Zl
ZGZmNTA4NGI2ZTc4ZjAwNGRkNDU0NzY3ZTA1NjU5NjM3Y2M5YTFjMzRlNTYzNjBjMTJhZjViOTBk
MjAyPiAvViA1ID4+CmVuZG9iagp4cmVmCjAgNwowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAw
MTUgMDAwMDAgbiAKMDAwMDAwMDEzMCAwMDAwMCBuIAowMDAwMDAwMTg5IDAwMDAwIG4gCjAwMDAw
MDAzMTcgMDAwMDAgbiAKMDAwMDAwMDQ4MyAwMDAwMCBuIAowMDAwMDAwNTUzIDAwMDAwIG4gCnRy
YWlsZXIgPDwgL1Jvb3QgMSAwIFIgL1NpemUgNyAvSUQgWzw2MWQ0NWFkZGZjNDUwMjA1YWY2MzJh
OTYxMjk4Y2RhMD48NjFkNDVhZGRmYzQ1MDIwNWFmNjMyYTk2MTI5OGNkYTA+XSAvRW5jcnlwdCA2
IDAgUiA+PgpzdGFydHhyZWYKMTEwMwolJUVPRgo=
BASE64,
    ];

    if (!isset($fixtures[$variant])) {
        throw new RuntimeException("Unknown qpdf fixture variant: {$variant}");
    }

    $encoded = preg_replace('/\s+/', '', $fixtures[$variant]);
    $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
    if (!is_string($bytes)) {
        throw new RuntimeException("Unable to decode qpdf fixture variant: {$variant}");
    }

    return $bytes;
};

$assertCommonPasswordHandling = static function (TestRunner $t, array $report, bool $copyAllowed): void {
    $review = $report['password_handling_review'];

    $t->same(1, $report['password_handling_review_count']);
    $t->same('pdf_password_handling_review', $review['source']);
    $t->same(true, $review['present']);
    $t->same(true, $review['encrypted_document']);
    $t->same('Standard', $review['handler']);
    $t->same(true, $review['standard_handler']);
    $t->same('standard_handler_revision_6', $review['revision_label']);
    $t->same('aes_256', $review['algorithm']);
    $t->same(256, $review['key_length_bits']);
    $t->same(true, $review['requires_password_for_content_extraction']);
    $t->same('standard_authentication_material_ready_for_password_attempt', $review['standard_authentication_material_policy']);
    $t->same(true, $review['standard_authentication_material_ready_for_password_attempt']);
    $t->same(4, $review['standard_authentication_required_entry_count']);
    $t->same(['owner_validation', 'user_validation', 'owner_encryption_key', 'user_encryption_key'], $review['standard_authentication_present_required_entries']);
    $t->same([], $review['standard_authentication_missing_required_entries']);
    $t->same('permission_digest_ciphertext_review', $review['standard_authentication_permission_digest_status']);
    $t->same($copyAllowed, $review['copy_or_extract_allowed_after_authentication']);
    $t->same(true, $review['accessibility_extract_allowed_after_authentication']);
    $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $review['permission_authentication_status']);
    $t->same(false, $review['permission_bits_authenticated']);
    $t->same(false, $review['authenticated_permission_bits_reliable']);
    $t->same('password_required_ready_for_external_validation', $review['password_prompt_policy']);
    $t->same('blocked_until_caller_supplies_password_and_decryption_layer', $review['password_validation_boundary']);
    $t->same(false, $review['password_supplied']);
    $t->same(false, $review['password_validation_performed']);
    $t->same(false, $review['permissions_authenticated']);
    $t->same(false, $review['permissions_enforced']);
    $t->same(false, $review['decryption_performed']);
    $t->same(false, $review['native_text_extraction_allowed_now']);
    $t->same(false, $review['raw_password_material_exposed']);
    $t->same(false, $review['raw_key_material_exposed']);
    $t->same(false, $review['raw_owner_user_keys_exposed']);
    $t->same(false, $review['raw_file_encryption_keys_exposed']);
    $t->same(false, $review['executes_decryption']);
    $t->same(false, $review['executes_permission_enforcement']);
    $t->same(false, $review['executes_external_pdf_tools']);
};

return [
    'blocks qpdf revision six no-extract fixture while preserving password diagnostics' => static function (
        TestRunner $t
    ) use ($qpdfRevisionSixFixture, $assertCommonPasswordHandling): void {
        $pdf = $qpdfRevisionSixFixture('r6-noextract');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(true, $report['encrypted']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_denied'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_denied_by_permissions', $permission['policy']);
        $t->same('blocked_by_encryption_and_copy_permission', $permission['content_extraction_boundary']);
        $t->same(-1340, $permission['permission_signed']);
        $t->same(4294965956, $permission['permission_unsigned']);
        $t->same('FFFFFAC4', $permission['permission_hex']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['accessibility_extract_allowed']);
        $t->same(['print', 'extract_for_accessibility', 'high_quality_print'], $permission['allowed']);
        $t->same(['modify_contents', 'copy_or_extract', 'add_or_modify_annotations', 'fill_form_fields', 'assemble_document'], $permission['denied']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $permission['permission_authentication_status']);

        $t->same('Standard', $report['encryption']['filter']);
        $t->same('aes_256', $report['encryption']['algorithm']);
        $t->same('standard_handler_revision_6', $report['encryption']['revision_label']);
        $t->same(256, $report['encryption']['key_length_bits']);
        $t->same('StdCF', $report['encryption']['stream_filter']);
        $t->same('StdCF', $report['encryption']['string_filter']);
        $t->same('StdCF', $report['encryption']['embedded_file_filter']);

        $assertCommonPasswordHandling($t, $report, false);
        $t->same('copy_extract_denied_by_permissions', $report['password_handling_review']['permission_policy']);
        $t->same('blocked_by_encryption_and_copy_permission', $report['password_handling_review']['content_extraction_boundary']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, 'QPDF encrypted permission body')
            && !str_contains($encoded, 'userpass')
            && !str_contains($encoded, 'ownerpass'));
    },
    'keeps qpdf copy-allowed fixture review-only until password validation and decryption' => static function (
        TestRunner $t
    ) use ($qpdfRevisionSixFixture, $assertCommonPasswordHandling): void {
        $pdf = $qpdfRevisionSixFixture('r6-extract');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(true, $report['encrypted']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(-1324, $permission['permission_signed']);
        $t->same(4294965972, $permission['permission_unsigned']);
        $t->same('FFFFFAD4', $permission['permission_hex']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['accessibility_extract_allowed']);
        $t->same(['print', 'copy_or_extract', 'extract_for_accessibility', 'high_quality_print'], $permission['allowed']);
        $t->same(['modify_contents', 'add_or_modify_annotations', 'fill_form_fields', 'assemble_document'], $permission['denied']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $permission['permission_authentication_status']);

        $assertCommonPasswordHandling($t, $report, true);
        $t->same('copy_extract_allowed_after_decryption', $report['password_handling_review']['permission_policy']);
        $t->same('blocked_until_decryption_password_available', $report['password_handling_review']['content_extraction_boundary']);
    },
    'stops core converter at encrypted preflight for qpdf fixture without queuing models' => static function (
        TestRunner $t
    ) use ($qpdfRevisionSixFixture): void {
        $pdf = $qpdfRevisionSixFixture('r6-noextract');
        $path = sys_get_temp_dir() . '/markerpdf-qpdf-encrypted-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, $pdf);

        try {
            $calls = 0;
            $result = (new CorePdfConverter())->convertWithSuppliedPages(
                $path,
                [['pnum' => 0, 'blocks' => []]],
                [],
                static function () use (&$calls): array {
                    $calls++;

                    return ['text' => 'model pipeline should not run', 'images' => [], 'metadata' => []];
                }
            );

            $security = $result['metadata']['pdf_security'];
            $t->same(0, $calls);
            $t->same('', $result['text']);
            $t->same([], $result['images']);
            $t->same('pdf', $result['metadata']['filetype']);
            $t->same('encrypted-pdf-preflight', $result['context']['stage']);
            $t->same(true, $security['encrypted']);
            $t->same(false, $security['permission_allows_text_extraction']);
            $t->same(false, $security['should_queue_models']);
            $t->same(1, $security['password_handling_review_count']);
            $t->same('password_required_ready_for_external_validation', $security['password_handling_review']['password_prompt_policy']);
            $t->same('blocked_until_caller_supplies_password_and_decryption_layer', $security['password_handling_review']['password_validation_boundary']);
            $t->same(false, $result['context']['pdf_security']['should_queue_models']);
            $t->same(false, $result['context']['pdf_security']['executes_external_pdf_tools']);
        } finally {
            unlink($path);
        }
    },
];
