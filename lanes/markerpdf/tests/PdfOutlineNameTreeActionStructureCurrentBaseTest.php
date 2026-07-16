<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNameTreeActionStructurePdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover name tree action page remains visible) Tj ET';
    $targetText = 'BT /F1 12 Tf '
        . '/NavTitle << /MCID 0 >> BDC 72 720 Td (Review heading from structure) Tj EMC '
        . '/NavBody << /MCID 1 >> BDC 72 704 Td (Review body from structure) Tj EMC ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Dur 5 /Trans 16 0 R /AA << /O 15 0 R >> /Resources << /Font << /F1 7 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Collapsed Review Target) /Parent 5 0 R /Dest /ReviewStart /First 7 0 R /Last 7 0 R /Count -1 /C [0 .35 .7] /F 3 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Hidden Child Row) /Parent 6 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "8 0 obj\n<< /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(A) (Z)] /Names [(ReviewStart) 9 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D [4 0 R /XYZ 72 720 1] /Next [13 0 R 14 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/outline-structure-review) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outline structure script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open-name-tree-structure) >>\nendobj\n"
        . "16 0 obj\n<< /S /Split /D .5 /Dm /V /M /I /Di 270 /SS .75 /B false >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Tagged ) /St 9 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap 60 0 R /ParentTree 55 0 R /K [52 0 R 53 0 R] >>\nendobj\n"
        . "52 0 obj\n<< /Type /StructElem /S /NavTitle /P 50 0 R /K 0 >>\nendobj\n"
        . "53 0 obj\n<< /Type /StructElem /S /NavBody /P 50 0 R /K 1 >>\nendobj\n"
        . "55 0 obj\n<< /Nums [0 [52 0 R 53 0 R]] >>\nendobj\n"
        . "60 0 obj\n<< /NavTitle /H1 /NavBody /P >>\nendobj\n"
        . "%%EOF";
};

return [
    'carries outline structure metadata onto name-tree destination action rows' => static function (TestRunner $t) use ($outlineNameTreeActionStructurePdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineNameTreeActionStructurePdf());

        $t->true(in_array('outline', $metadata['source'], true));
        $t->true(in_array('outline_actions', $metadata['source'], true));
        $t->true(in_array('tagged_content', $metadata['source'], true));
        $t->same(2, count($metadata['outline']));
        $t->same('Collapsed Review Target', $metadata['outline'][0]['title']);
        $t->same('collapsed', $metadata['outline'][0]['structure_state']);
        $t->same('#0059b3', $metadata['outline'][0]['text_color_hex']);
        $t->same(['H1', 'P'], $metadata['outline'][0]['target_structure_roles']);
        $t->same(['Review heading from structure', 'Review body from structure'], array_column($metadata['outline'][0]['target_tagged_content'], 'text'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(3, count($actions));
        $t->same(['GoTo', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript'], array_column($actions, 'safety'));
        $t->same(['ReviewStart', 'ReviewStart', 'ReviewStart'], array_column($actions, 'destination_action_name'));
        $t->same([9, 13, 14], array_column($actions, 'action_object'));
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);

        foreach ($actions as $action) {
            $t->same(5, $action['outline_parent_object']);
            $t->same(7, $action['outline_first_child_object']);
            $t->same(7, $action['outline_last_child_object']);
            $t->same(true, $action['outline_has_children']);
            $t->same(-1, $action['outline_count']);
            $t->same(1, $action['outline_descendant_count']);
            $t->same(false, $action['outline_is_open']);
            $t->same(true, $action['outline_is_collapsed']);
            $t->same('collapsed', $action['outline_structure_state']);
            $t->same(3, $action['outline_style_flags']);
            $t->same(true, $action['outline_is_italic']);
            $t->same(true, $action['outline_is_bold']);
            $t->same('#0059b3', $action['outline_text_color_hex']);
            $t->same(1, $action['destination_action_target_page']);
            $t->same('Tagged 9', $action['destination_action_target_page_label']);
            $t->same('XYZ', $action['destination_action_target_view_mode']);
            $t->same(['left' => 72.0, 'top' => 720.0, 'zoom' => 1.0], $action['destination_action_target_view_parameters']);
            $t->same('Split', $action['destination_action_target_page_transition']['style']);
            $t->same(['review-uri'], array_column($action['destination_action_target_page_actions'], 'safety'));
            $t->same(['H1', 'P'], $action['destination_action_target_structure_roles']);
            $t->same(
                ['Review heading from structure', 'Review body from structure'],
                array_column($action['destination_action_target_tagged_content'], 'text')
            );
        }

        $t->same('https://example.com/outline-structure-review', $actions[1]['uri']);
        $t->same(false, $actions[2]['executes_on_import']);
    },
    'keeps name-tree action structure operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineNameTreeActionStructurePdf): void {
        $pdf = $outlineNameTreeActionStructurePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Cover name tree action page remains visible', $plainText);
        $t->contains("Review heading from structure\nReview body from structure", $plainText);
        $t->true(!str_contains($plainText, 'Collapsed Review Target'));
        $t->true(!str_contains($plainText, 'Hidden Child Row'));
        $t->true(!str_contains($plainText, 'ReviewStart'));
        $t->true(!str_contains($plainText, 'outline-structure-review'));
        $t->true(!str_contains($plainText, 'hidden outline structure script'));
    },
];
