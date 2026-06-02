<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDestinationActionPageLabelStructurePdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover outline label structure page remains visible) Tj ET';
    $targetText = 'BT /F1 12 Tf '
        . '/ChapterTitle << /MCID 0 >> BDC 72 720 Td (Destination heading from structure) Tj EMC '
        . '/ChapterBody << /MCID 1 >> BDC 72 700 Td (Destination body from structure) Tj EMC ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Chapter action target) /Parent 5 0 R /Dest /ChapterAction /F 2 >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ChapterAction) 9 0 R (ChapterView) [4 0 R /FitH 680]] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /ChapterView /Next [10 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/chapter-action-review) >>\nendobj\n"
        . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden label structure script'\\)) >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /r /P (front-) /St 1 >> 1 << /S /D /P (Chapter ) /St 12 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap 60 0 R /ParentTree 55 0 R /K [52 0 R 53 0 R] >>\nendobj\n"
        . "52 0 obj\n<< /Type /StructElem /S /ChapterTitle /P 50 0 R /K 0 >>\nendobj\n"
        . "53 0 obj\n<< /Type /StructElem /S /ChapterBody /P 50 0 R /K 1 >>\nendobj\n"
        . "55 0 obj\n<< /Nums [0 [52 0 R 53 0 R]] >>\nendobj\n"
        . "60 0 obj\n<< /ChapterTitle /H1 /ChapterBody /P >>\nendobj\n"
        . "%%EOF";
};

return [
    'summarizes outline destination action target page labels and structure rows' => static function (TestRunner $t) use ($outlineDestinationActionPageLabelStructurePdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineDestinationActionPageLabelStructurePdf());

        $t->same(['outline', 'outline_actions', 'tagged_content', 'page_review'], $metadata['source']);
        $t->same('Chapter action target', $metadata['outline'][0]['title']);
        $t->same('ChapterAction', $metadata['outline'][0]['destination']);
        $t->same('Chapter 12', $metadata['outline'][0]['page_label']);
        $t->same(['H1', 'P'], $metadata['outline'][0]['target_structure_roles']);
        $t->same(['Destination heading from structure', 'Destination body from structure'], array_column($metadata['outline'][0]['target_tagged_content'], 'text'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(3, count($actions));
        $t->same(['GoTo', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['ChapterAction', 'ChapterAction', 'ChapterAction'], array_column($actions, 'destination_action_name'));
        $t->same(['Chapter 12', 'Chapter 12', 'Chapter 12'], array_column($actions, 'destination_action_target_page_label'));
        $t->same([2, 2, 2], array_column($actions, 'destination_action_target_page_number'));

        foreach ($actions as $action) {
            $t->same([0, 1], $action['destination_action_target_structure_mcids']);
            $t->same(['ChapterTitle', 'ChapterBody'], $action['destination_action_target_structure_raw_roles']);
            $t->same(['H1', 'P'], $action['destination_action_target_structure_roles']);
            $t->same(['Destination heading from structure', 'Destination body from structure'], $action['destination_action_target_structure_text']);
            $t->same(
                ['Destination heading from structure', 'Destination body from structure'],
                array_column($action['destination_action_target_tagged_content'], 'text')
            );
            $t->same(false, $action['executes_on_import']);
        }

        $t->same('review-uri', $actions[1]['safety']);
        $t->same('blocked-javascript', $actions[2]['safety']);
    },
    'keeps destination action label and structure review operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineDestinationActionPageLabelStructurePdf): void {
        $pdf = $outlineDestinationActionPageLabelStructurePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Cover outline label structure page remains visible', $plainText);
        $t->contains("Destination heading from structure\nDestination body from structure", $plainText);
        $t->true(!str_contains($plainText, 'Chapter action target'));
        $t->true(!str_contains($plainText, 'ChapterAction'));
        $t->true(!str_contains($plainText, 'ChapterView'));
        $t->true(!str_contains($plainText, 'chapter-action-review'));
        $t->true(!str_contains($plainText, 'hidden label structure script'));
    },
];
