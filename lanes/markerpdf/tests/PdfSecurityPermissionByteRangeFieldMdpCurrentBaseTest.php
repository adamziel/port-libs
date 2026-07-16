<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldMdpPermissionByteRangePdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (FieldMDP byte range import) Tj ET';
    $signaturePayload = 'FIELDMDP_BYTE_RANGE_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $unsignedFieldValue = 'FIELDMDP_POST_SIGNATURE_FIELD_VALUE_SHOULD_NOT_LEAK';

    $signedPrefix = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.fieldPermissions) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed title) /Kids [11 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Field Permission Reviewer) /M (D:20260602203531Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [(article.title) (post.signature.notes)] >>\nendobj\n";
    $postSignatureObjects = "10 0 obj\n<< /FT /Tx /T (post.signature.notes) /V ({$unsignedFieldValue}) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
    $pdf = $signedPrefix . $postSignatureObjects;

    $gapStart = strpos($pdf, $signatureContentsToken);
    $postSignatureFieldOffset = strpos($pdf, "10 0 obj\n");
    if ($gapStart === false || $postSignatureFieldOffset === false) {
        throw new RuntimeException('Unable to locate signature or post-signature field fixture boundary.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', $postSignatureFieldOffset - $gapEnd),
    ]);

    return [$pdf, $signaturePayload, $unsignedFieldValue, $postSignatureFieldOffset];
};

return [
    'correlates FieldMDP permission targets with signed ByteRange coverage' => static function (TestRunner $t) use ($fieldMdpPermissionByteRangePdf): void {
        [$pdf, $signaturePayload, $unsignedFieldValue, $postSignatureFieldOffset] = $fieldMdpPermissionByteRangePdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $signature = $report['signatures'][0] ?? [];
        $fieldReview = $report['field_mdp_byte_range_review'];
        $rows = $fieldReview['rows'];
        $titleRow = $rows[0] ?? [];
        $notesRow = $rows[1] ?? [];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('FieldMDP byte range import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_boundary', $report['import_decision']);
        $t->same([
            'signed_signature_present',
            'signature_reference_transforms_present',
            'signature_byte_range_invalid',
        ], $report['review_reasons']);
        $t->same(['signature_validation', 'signing'], $report['blocked_operations']);

        $t->same(1, $report['signature_field_count']);
        $t->same(1, $report['signed_signature_count']);
        $t->same(1, $report['signature_reference_transform_count']);
        $t->same(['FieldMDP'], $report['signature_reference_transform_methods']);
        $t->same(1, $report['signature_byte_range_count']);
        $t->same(0, $report['valid_signature_byte_range_count']);
        $t->same(1, $report['invalid_signature_byte_range_count']);
        $t->same(false, $signature['byte_range']['valid']);
        $t->same('incomplete_file_coverage', $signature['byte_range']['status']);
        $t->same(true, $signature['byte_range']['signed_revision_valid']);
        $t->same('covers_prior_revision_except_signature_contents', $signature['byte_range']['revision_status']);
        $t->same($postSignatureFieldOffset, $signature['byte_range']['signed_revision_end']);
        $t->same(strlen($pdf) - $postSignatureFieldOffset, $signature['byte_range']['current_revision_tail_bytes']);

        $t->same(2, $report['field_mdp_byte_range_review_count']);
        $t->same(1, $report['field_mdp_byte_range_conflict_count']);
        $t->same([
            'field_mdp_target_covered_by_signed_revision',
            'field_mdp_target_outside_signed_revision',
        ], $report['field_mdp_byte_range_statuses']);

        $t->same('field_mdp_byte_range_review', $fieldReview['source']);
        $t->same(true, $fieldReview['present']);
        $t->same(1, $fieldReview['field_mdp_transform_count']);
        $t->same(2, $fieldReview['target_field_count']);
        $t->same(1, $fieldReview['target_covered_count']);
        $t->same(1, $fieldReview['target_not_covered_count']);
        $t->same(1, $fieldReview['target_outside_signed_revision_count']);
        $t->same(0, $fieldReview['target_inside_signature_contents_gap_count']);
        $t->same(0, $fieldReview['target_unresolved_count']);
        $t->same(['article.title', 'post.signature.notes'], $fieldReview['target_field_names']);
        $t->same(['locks_included_fields'], $fieldReview['field_mdp_action_labels']);
        $t->same(false, $fieldReview['field_permissions_enforced']);
        $t->same(false, $fieldReview['executes_signature_validation']);
        $t->same(false, $fieldReview['executes_rights_enforcement']);

        $t->same('article.title', $titleRow['field_name'] ?? null);
        $t->same(9, $titleRow['field_object'] ?? null);
        $t->same('approval.fieldPermissions', $titleRow['signature_field_name'] ?? null);
        $t->same(30, $titleRow['signature_object'] ?? null);
        $t->same(32, $titleRow['transform_params_object'] ?? null);
        $t->same(5, $titleRow['transform_data_object'] ?? null);
        $t->same('Include', $titleRow['field_mdp_action'] ?? null);
        $t->same('locks_included_fields', $titleRow['field_mdp_action_label'] ?? null);
        $t->same(['article.title', 'post.signature.notes'], $titleRow['declared_field_names'] ?? []);
        $t->same('covered_by_signed_segments', $titleRow['field_object_coverage_status'] ?? null);
        $t->same(true, $titleRow['field_object_covered_by_signed_revision'] ?? null);
        $t->same(1, $titleRow['widget_object_count'] ?? null);
        $t->same(['covered_by_signed_segments'], $titleRow['widget_object_coverage_statuses'] ?? []);
        $t->same('field_mdp_target_covered_by_signed_revision', $titleRow['target_status'] ?? null);
        $t->same(true, $titleRow['target_covered_by_signed_revision'] ?? null);
        $t->same(false, $titleRow['field_permission_enforced'] ?? null);

        $t->same('post.signature.notes', $notesRow['field_name'] ?? null);
        $t->same(10, $notesRow['field_object'] ?? null);
        $t->same('Tx', $notesRow['field_type'] ?? null);
        $t->same('outside_signed_revision', $notesRow['field_object_coverage_status'] ?? null);
        $t->same(false, $notesRow['field_object_covered_by_signed_revision'] ?? null);
        $t->same(1, $notesRow['widget_object_count'] ?? null);
        $t->same(['outside_signed_revision'], $notesRow['widget_object_coverage_statuses'] ?? []);
        $t->same('outside_signed_revision', $notesRow['widget_object_coverage'][0]['coverage_status'] ?? null);
        $t->same('field_mdp_target_outside_signed_revision', $notesRow['target_status'] ?? null);
        $t->same(false, $notesRow['target_covered_by_signed_revision'] ?? null);
        $t->same(true, $notesRow['target_outside_signed_revision'] ?? null);
        $t->true(($notesRow['field_object_span']['offset'] ?? 0) >= $postSignatureFieldOffset);
        $t->same($postSignatureFieldOffset, $notesRow['signed_revision_end'] ?? null);
        $t->same(false, $notesRow['field_permission_enforced'] ?? null);
        $t->same(false, $notesRow['executes_signature_validation'] ?? null);
        $t->same(false, $notesRow['executes_rights_enforcement'] ?? null);

        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_signing']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, $unsignedFieldValue)
            && !str_contains($encoded, 'DEADC0DE'));
    },
    'keeps unsigned FieldMDP target field values out of visible WordPress text' => static function (TestRunner $t) use ($fieldMdpPermissionByteRangePdf): void {
        [$pdf, $signaturePayload, $unsignedFieldValue] = $fieldMdpPermissionByteRangePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('FieldMDP byte range import', $plainText);
        $t->true(!str_contains($plainText, $signaturePayload));
        $t->true(!str_contains($plainText, strtoupper(bin2hex($signaturePayload))));
        $t->true(!str_contains($plainText, $unsignedFieldValue));
        $t->true(!str_contains($plainText, 'post.signature.notes'));
    },
];
