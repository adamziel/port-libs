<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$signedInlineActionChainPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Signed inline action chain import) Tj ET';
    $signaturePayload = 'INLINE_ACTION_CHAIN_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

    $signedPrefix = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [90 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.inlineAction) /V 30 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Inline Action Reviewer) /M (D:20260602204117Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
    $postSignatureAnnotation = "90 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 360 718] /A << /S /URI /URI (https://example.test/signed-inline-review) /Next [<< /S /Launch /F (inline-helper.exe) /Win << /F (inline-helper.exe) /O (open) >> >> << /S /URI /URI (javascript:inlineSignature\\(\\)) >>] >> >>\nendobj\n"
        . "%%EOF";
    $pdf = $signedPrefix . $postSignatureAnnotation;

    $gapStart = strpos($pdf, $signatureContentsToken);
    $postSignatureOffset = strpos($pdf, "90 0 obj\n");
    if ($gapStart === false || $postSignatureOffset === false) {
        throw new RuntimeException('Unable to locate signature or appended inline action fixture boundary.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', $postSignatureOffset - $gapEnd),
    ]);

    return [$pdf, $signaturePayload, $postSignatureOffset];
};

return [
    'uses annotation container spans for inline post-signature action chain review' => static function (TestRunner $t) use ($signedInlineActionChainPdf): void {
        [$pdf, $signaturePayload, $postSignatureOffset] = $signedInlineActionChainPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $actions = $actionReview['actions'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('Signed inline action chain import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_boundary', $report['import_decision']);
        $t->same([
            'signed_signature_present',
            'signature_reference_transforms_present',
            'signature_byte_range_invalid',
            'unsafe_pdf_actions_present',
            'launch_actions_present',
            'unsafe_uri_actions_present',
            'post_signature_pdf_actions_present',
        ], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'pdf_action_execution'], $report['blocked_operations']);

        $t->same(false, $report['signatures'][0]['byte_range']['valid']);
        $t->same('incomplete_file_coverage', $report['signatures'][0]['byte_range']['status']);
        $t->same($postSignatureOffset, $report['signatures'][0]['byte_range']['segments'][1]['end']);

        $t->same(3, $actionReview['action_count']);
        $t->same(3, $actionReview['annotation_action_count']);
        $t->same(1, $actionReview['launch_action_count']);
        $t->same(2, $actionReview['uri_action_count']);
        $t->same(1, $actionReview['safe_uri_action_count']);
        $t->same(1, $actionReview['unsafe_uri_action_count']);
        $t->same(2, $actionReview['unsafe_action_count']);
        $t->same(3, $actionReview['action_byte_range_review_count']);
        $t->same(3, $actionReview['post_signature_action_count']);
        $t->same(3, $actionReview['unsigned_action_byte_range_count']);
        $t->same([90], $actionReview['post_signature_action_objects']);
        $t->same(['outside_all_signature_byte_ranges'], $actionReview['action_byte_range_statuses']);
        $t->same(true, $actionReview['has_post_signature_actions']);

        $t->same(['URI', 'Launch'], $actionReview['action_types']);
        $t->same(['review-uri', 'blocked-launch', 'blocked-unsafe-uri'], $actionReview['safety_labels']);
        $t->same(['page_annotation_action', 'page_annotation_action', 'page_annotation_action'], array_column($actions, 'source'));
        $t->same(['URI', 'Launch', 'URI'], array_column($actions, 'action_type'));
        $t->same(['review-uri', 'blocked-launch', 'blocked-unsafe-uri'], array_column($actions, 'safety'));
        $t->same([null, null, null], array_column($actions, 'action_object'));
        $t->same([90, 90, 90], array_column($actions, 'annotation_object'));
        $t->same([90, 90, 90], array_column($actions, 'action_container_object'));
        $t->same(['annotation_object', 'annotation_object', 'annotation_object'], array_column($actions, 'action_container_source'));
        $t->same([90, 90, 90], array_column($actions, 'action_byte_range_review_object'));
        $t->same(['action_container_object', 'action_container_object', 'action_container_object'], array_column($actions, 'action_byte_range_review_source'));
        $t->same([false, true, true], array_map(
            static fn (array $action): bool => (bool) ($action['chained'] ?? false),
            $actions
        ));

        foreach ($actions as $action) {
            $t->same('outside_all_signature_byte_ranges', $action['signature_byte_range_coverage_status']);
            $t->same(false, $action['covered_by_all_signature_byte_ranges']);
            $t->same(true, $action['outside_any_signature_byte_range']);
            $t->same(0, $action['signature_byte_range_signed_coverage_count']);
            $t->same(1, $action['signature_byte_range_unsigned_coverage_count']);
            $t->true(($action['action_object_span']['offset'] ?? 0) >= $postSignatureOffset);
            $t->same('outside_signed_revision', $action['signature_byte_range_reviews'][0]['coverage_status']);
            $t->same('approval.inlineAction', $action['signature_byte_range_reviews'][0]['field_name']);
            $t->same(30, $action['signature_byte_range_reviews'][0]['signature_object']);
        }

        $t->same('https://example.test/signed-inline-review', $actions[0]['uri']);
        $t->same('inline-helper.exe', $actions[1]['file']);
        $t->same('open', $actions[1]['operation']);
        $t->same('javascript:inlineSignature()', $actions[2]['uri']);
        $t->same(false, $actions[2]['is_safe_uri']);
        $t->same(false, $actionReview['executes_actions_on_import']);
        $t->same(false, $report['executes_pdf_actions']);
        $t->same(false, $report['executes_signature_validation']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload))));
    },
    'keeps inline post-signature action operands out of visible WordPress text' => static function (TestRunner $t) use ($signedInlineActionChainPdf): void {
        [$pdf] = $signedInlineActionChainPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Signed inline action chain import', $plainText);
        $t->true(!str_contains($plainText, 'signed-inline-review'));
        $t->true(!str_contains($plainText, 'inline-helper.exe'));
        $t->true(!str_contains($plainText, 'inlineSignature'));
    },
];
