<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$signedLaunchUriPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Launch URI certificate permission import) Tj ET';
    $signaturePayload = 'LAUNCH_URI_CERT_PERMISSION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /OpenAction 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.certifier) /V 30 0 R /Kids [7 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 280 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 300 718] /A 23 0 R /AA << /E 25 0 R >> >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.com/open-review) /Next [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /S /Launch /F (post-import-helper.exe) /Win << /F (post-import-helper.exe) /O (open) /P (/silent) >> /NewWindow true >>\nendobj\n"
        . "22 0 obj\n<< /S /URI /URI (javascript:alert\\(1\\)) >>\nendobj\n"
        . "23 0 obj\n<< /S /URI /URI (https://example.com/annotation-review) /Next 24 0 R >>\nendobj\n"
        . "24 0 obj\n<< /S /Launch /F (annotation-helper.exe) /Win << /F (annotation-helper.exe) /O (print) >> >>\nendobj\n"
        . "25 0 obj\n<< /S /URI /URI (file:///etc/passwd) >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Certifying Reviewer) /M (D:20260602175042Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 3 /V /1.2 >> >>] >>\nendobj\n"
        . "%%EOF";

    $gapStart = strpos($pdf, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in focused fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);

    return [$pdf, $signaturePayload];
};

return [
    'summarizes launch and URI actions alongside certifying signature permissions' => static function (TestRunner $t) use ($signedLaunchUriPdf): void {
        [$pdf, $signaturePayload] = $signedLaunchUriPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $signature = $report['signatures'][0];
        $docMdp = $signature['reference_transforms'][0];

        $t->same('Launch URI certificate permission import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same([
            'signed_signature_present',
            'signature_reference_transforms_present',
            'unsafe_pdf_actions_present',
            'launch_actions_present',
            'unsafe_uri_actions_present',
        ], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'pdf_action_execution'], $report['blocked_operations']);

        $t->same(true, $signature['certifying_signature']);
        $t->same(['DocMDP'], $signature['reference_transform_methods']);
        $t->same(3, $docMdp['permission_level']);
        $t->same('form_fill_templates_signatures_annotations', $docMdp['permission_label']);
        $t->same(['fill_forms', 'instantiate_page_templates', 'sign', 'create_modify_delete_annotations'], $docMdp['allowed_changes']);

        $t->same('pdf_document_action_security_review', $actionReview['source']);
        $t->same(true, $actionReview['present']);
        $t->same(6, $actionReview['action_count']);
        $t->same(3, $actionReview['open_action_count']);
        $t->same(3, $actionReview['annotation_action_count']);
        $t->same(2, $actionReview['launch_action_count']);
        $t->same(4, $actionReview['uri_action_count']);
        $t->same(2, $actionReview['safe_uri_action_count']);
        $t->same(2, $actionReview['unsafe_uri_action_count']);
        $t->same(4, $actionReview['unsafe_action_count']);
        $t->same(['URI', 'Launch'], $actionReview['action_types']);
        $t->same(['review-uri', 'blocked-launch', 'blocked-unsafe-uri'], $actionReview['safety_labels']);
        $t->same(1, $actionReview['certifying_signature_count']);
        $t->same(['form_fill_templates_signatures_annotations'], $actionReview['certifying_permission_labels']);
        $t->same(['DocMDP'], $actionReview['signature_reference_transform_methods']);
        $t->same(false, $actionReview['executes_actions_on_import']);
        $t->same(false, $actionReview['executes_javascript']);
        $t->same(false, $report['executes_pdf_actions']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_external_pdf_tools']);

        $t->same([
            'catalog_open_action',
            'catalog_open_action',
            'catalog_open_action',
            'page_annotation_action',
            'page_annotation_action',
            'page_annotation_additional_action',
        ], array_column($actionReview['actions'], 'source'));
        $t->same(['URI', 'Launch', 'URI', 'URI', 'Launch', 'URI'], array_column($actionReview['actions'], 'action_type'));
        $t->same(['review-uri', 'blocked-launch', 'blocked-unsafe-uri', 'review-uri', 'blocked-launch', 'blocked-unsafe-uri'], array_column($actionReview['actions'], 'safety'));
        $t->same([20, 21, 22, 23, 24, 25], array_column($actionReview['actions'], 'action_object'));
        $t->same('post-import-helper.exe', $actionReview['actions'][1]['file']);
        $t->same('open', $actionReview['actions'][1]['operation']);
        $t->same('file:///etc/passwd', $actionReview['actions'][5]['uri']);
        $t->same(false, $actionReview['actions'][5]['is_safe_uri']);

        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encoded) && !str_contains($encoded, $signaturePayload) && !str_contains($encoded, strtoupper(bin2hex($signaturePayload))));
    },
    'keeps launch URI action targets out of visible WordPress text' => static function (TestRunner $t) use ($signedLaunchUriPdf): void {
        [$pdf] = $signedLaunchUriPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Launch URI certificate permission import', $plainText);
        $t->true(!str_contains($plainText, 'post-import-helper.exe'));
        $t->true(!str_contains($plainText, 'annotation-helper.exe'));
        $t->true(!str_contains($plainText, 'javascript:alert'));
        $t->true(!str_contains($plainText, 'file:///etc/passwd'));
        $t->true(!str_contains($plainText, 'https://example.com/open-review'));
    },
];
