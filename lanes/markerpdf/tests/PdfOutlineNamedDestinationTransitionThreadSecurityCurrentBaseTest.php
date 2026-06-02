<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNamedDestinationTransitionThreadSecurityPdf = static function (): array {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Intro security navigation page remains visible) Tj ET';
    $deckContent = 'BT /F1 12 Tf 72 720 Td (Secure deck target page remains visible) Tj ET';
    $signaturePayload = 'OUTLINE_SECURITY_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] /AcroForm 40 0 R /Perms << /DocMDP 43 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 6 /Trans 16 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Secure Deck Outline) /Parent 5 0 R /Dest /SecureDeck >>\nendobj\n"
        . "8 0 obj\n<< /Names [(SecureDeck) 9 0 R (DeckTarget) [4 0 R /FitH 710]] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /DeckTarget /Next [10 0 R 11 0 R 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/outline-security-review) >>\nendobj\n"
        . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outline security script'\\)) >>\nendobj\n"
        . "12 0 obj\n<< /S /Launch /F (outline-helper.exe) /Win << /F (outline-helper.exe) /O (open) >> /NewWindow true >>\nendobj\n"
        . "16 0 obj\n<< /S /Dissolve /D .7 >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Security Deck Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 682 280 728] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 682 540 728] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Deck ) /St 7 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($deckContent) . " >>\nstream\n{$deckContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Fields [41 0 R] /SigFlags 3 >>\nendobj\n"
        . "41 0 obj\n<< /FT /Sig /T (approval.outlineSecurity) /V 43 0 R /Kids [42 0 R] >>\nendobj\n"
        . "42 0 obj\n<< /Subtype /Widget /Parent 41 0 R /Rect [72 620 300 664] /P 4 0 R /F 4 >>\nendobj\n"
        . "43 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Outline Security Reviewer) /M (D:20260602213951Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n"
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
    'surfaces outline named-destination action chains in security preflight with transition thread context' => static function (TestRunner $t) use ($outlineNamedDestinationTransitionThreadSecurityPdf): void {
        [$pdf] = $outlineNamedDestinationTransitionThreadSecurityPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $outlineSecurity = $actionReview['outline_action_security_review'];
        $actions = $actionReview['actions'];

        $t->same('Intro security navigation page remains visible' . "\n" . 'Secure deck target page remains visible', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same(['signed_signature_present', 'signature_reference_transforms_present', 'unsafe_pdf_actions_present', 'launch_actions_present'], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'pdf_action_execution'], $report['blocked_operations']);
        $t->same(4, $report['document_action_review_count']);
        $t->same(2, $report['unsafe_document_action_count']);
        $t->same(1, $report['launch_action_count']);

        $t->same('outline_action_security_review', $outlineSecurity['source']);
        $t->same(true, $outlineSecurity['present']);
        $t->same(4, $outlineSecurity['outline_action_count']);
        $t->same(2, $outlineSecurity['unsafe_outline_action_count']);
        $t->same(['Secure Deck Outline'], $outlineSecurity['outline_titles']);
        $t->same([6], $outlineSecurity['outline_objects']);
        $t->same([9, 10, 11, 12], $outlineSecurity['outline_action_objects']);
        $t->same(['GoTo', 'URI', 'JavaScript', 'Launch'], $outlineSecurity['outline_action_types']);
        $t->same(['local-destination', 'review-uri', 'blocked-javascript', 'blocked-launch'], $outlineSecurity['outline_action_safety_labels']);
        $t->same(['SecureDeck'], $outlineSecurity['destination_action_names']);
        $t->same([1], $outlineSecurity['destination_action_target_pages']);
        $t->same(['Deck 7'], $outlineSecurity['destination_action_target_page_labels']);
        $t->same(['Dissolve'], $outlineSecurity['destination_action_target_transition_styles']);
        $t->same(['Security Deck Thread'], $outlineSecurity['destination_action_target_article_thread_titles']);
        $t->same(['DocMDP'], $outlineSecurity['signature_permission_transform_methods']);
        $t->same(['outline_action_review_only_not_granted_by_cert_permissions'], $outlineSecurity['outline_action_permission_statuses']);
        $t->same(false, $outlineSecurity['cert_permissions_grant_outline_action_execution']);
        $t->same(false, $outlineSecurity['executes_pdf_actions']);

        $t->same(['outline_action', 'outline_action', 'outline_action', 'outline_action'], array_column($actions, 'source'));
        $t->same(['GoTo', 'URI', 'JavaScript', 'Launch'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript', 'blocked-launch'], array_column($actions, 'safety'));
        $t->same(['SecureDeck', 'SecureDeck', 'SecureDeck', 'SecureDeck'], array_column($actions, 'destination_action_name'));
        $t->same([1, 1, 1, 1], array_column($actions, 'destination_action_target_page'));
        $t->same(['Deck 7', 'Deck 7', 'Deck 7', 'Deck 7'], array_column($actions, 'destination_action_target_page_label'));
        $t->same(['DocMDP'], $actions[0]['signature_permission_transform_methods']);
        $t->same('outline_action_review_only_not_granted_by_cert_permissions', $actions[2]['outline_action_permission_status']);
        $t->same(false, $actions[2]['outline_action_allowed_by_cert_permissions']);
        $t->same(true, $actions[2]['outline_action_requires_security_review']);
        $t->same(6, $actions[0]['action_container_object']);
        $t->same('outline_object', $actions[0]['action_container_source']);
    },
    'preserves outline target page transition and article thread details on each security action row' => static function (TestRunner $t) use ($outlineNamedDestinationTransitionThreadSecurityPdf): void {
        [$pdf] = $outlineNamedDestinationTransitionThreadSecurityPdf();
        $actions = (new PdfSecurityPreflight())->analyze($pdf)['document_action_security_review']['actions'];

        foreach ($actions as $action) {
            $t->same('Secure Deck Outline', $action['outline_title']);
            $t->same(1, $action['outline_level']);
            $t->same('SecureDeck', $action['outline_destination_name']);
            $t->same(1, $action['destination_action_target_page']);
            $t->same('Deck 7', $action['destination_action_target_page_label']);
            $t->same(6.0, $action['destination_action_target_display_duration']);
            $t->same('Dissolve', $action['destination_action_target_page_transition']['style']);
            $t->same(0.7, $action['destination_action_target_page_transition']['duration']);
            $t->same([21, 22], array_column($action['destination_action_target_article_beads'], 'bead_object'));
            $t->same(['Security Deck Thread'], $action['destination_action_target_article_thread_titles']);
            $t->same(false, $action['executes_on_import']);
            $t->same(false, $action['executes_action']);
        }

        $t->same('DeckTarget', $actions[0]['destination']);
        $t->same('https://example.com/outline-security-review', $actions[1]['uri']);
        $t->same('outline-helper.exe', $actions[3]['file']);
        $t->same(true, $actions[3]['new_window']);
    },
    'keeps outline security operands signature bytes and thread dictionaries out of visible text and navigation toc' => static function (TestRunner $t) use ($outlineNamedDestinationTransitionThreadSecurityPdf): void {
        [$pdf, $signaturePayload] = $outlineNamedDestinationTransitionThreadSecurityPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfToc($pdf);
        $encoded = json_encode((new PdfSecurityPreflight())->analyze($pdf), JSON_UNESCAPED_SLASHES);

        $t->same([['title' => 'Secure Deck Outline', 'level' => 1, 'page' => 1, 'destination' => 'SecureDeck']], $toc);
        $t->contains('Intro security navigation page remains visible', $plainText);
        $t->contains('Secure deck target page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Secure Deck Outline'));
        $t->true(!str_contains($plainText, 'SecureDeck'));
        $t->true(!str_contains($plainText, 'DeckTarget'));
        $t->true(!str_contains($plainText, 'outline-security-review'));
        $t->true(!str_contains($plainText, 'hidden outline security script'));
        $t->true(!str_contains($plainText, 'outline-helper.exe'));
        $t->true(!str_contains($plainText, 'Security Deck Thread'));
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload))));
    },
];
