<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineStructureDestinationPageContextPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover outline structure page remains visible) Tj ET';
    $targetText = 'BT /F1 12 Tf 72 720 Td (Target outline page remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 6 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Migration Plan) /Parent 5 0 R /Dest /PlanTarget /Next 9 0 R /First 7 0 R /Last 7 0 R /Count -1 /C [0 .2 .8] /F 3 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Implementation Detail) /Parent 6 0 R /Dest [4 0 R /FitH 620] /Count 0 /C [0.5 0.25 0] /F 1 >>\nendobj\n"
        . "8 0 obj\n<< /Names [(PlanTarget) [4 0 R /XYZ 90 700 0]] >>\nendobj\n"
        . "9 0 obj\n<< /Title (Action Plan Target) /Parent 5 0 R /Prev 6 0 R /A << /S /GoTo /D /PlanTarget >> /F 2 >>\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open-outline-structure) >>\nendobj\n"
        . "16 0 obj\n<< /S /Split /D .5 /Dm /V /M /I /Di 270 /SS .75 /B false >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /r /P (cover-) /St 1 >> 1 << /S /D /P (Section ) /St 3 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'surfaces outline structure state style color and destination page context' => static function (TestRunner $t) use ($outlineStructureDestinationPageContextPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineStructureDestinationPageContextPdf();
        $rows = $extractor->getOutlineStructureDestinationPageContext($pdf);

        $t->same(3, count($rows));
        $t->same(['Migration Plan', 'Implementation Detail', 'Action Plan Target'], array_column($rows, 'title'));
        $t->same([1, 2, 1], array_column($rows, 'level'));
        $t->same([6, 7, 9], array_column($rows, 'outline_object'));
        $t->same([5, 6, 5], array_column($rows, 'parent_object'));
        $t->same([null, null, 6], array_column($rows, 'previous_object'));
        $t->same([9, null, null], array_column($rows, 'next_object'));
        $t->same([7, null, null], array_column($rows, 'first_child_object'));
        $t->same([7, null, null], array_column($rows, 'last_child_object'));

        $t->same('collapsed', $rows[0]['structure_state']);
        $t->same(true, $rows[0]['has_children']);
        $t->same(-1, $rows[0]['outline_count']);
        $t->same(1, $rows[0]['descendant_count']);
        $t->same(false, $rows[0]['is_open']);
        $t->same(true, $rows[0]['is_collapsed']);
        $t->same(3, $rows[0]['style_flags']);
        $t->same(true, $rows[0]['is_bold']);
        $t->same(true, $rows[0]['is_italic']);
        $t->same([0.0, 0.2, 0.8], $rows[0]['text_color_rgb']);
        $t->same('#0033cc', $rows[0]['text_color_hex']);

        $t->same('leaf', $rows[1]['structure_state']);
        $t->same(false, $rows[1]['has_children']);
        $t->same(0, $rows[1]['outline_count']);
        $t->same(0, $rows[1]['descendant_count']);
        $t->same(true, $rows[1]['is_open']);
        $t->same(false, $rows[1]['is_collapsed']);
        $t->same(1, $rows[1]['style_flags']);
        $t->same(false, $rows[1]['is_bold']);
        $t->same(true, $rows[1]['is_italic']);
        $t->same('#804000', $rows[1]['text_color_hex']);

        $t->same(2, $rows[2]['style_flags']);
        $t->same(true, $rows[2]['is_bold']);
        $t->same(false, $rows[2]['is_italic']);
        $t->true(!array_key_exists('text_color_hex', $rows[2]));

        foreach ($rows as $row) {
            $t->same(1, $row['page']);
            $t->same(2, $row['page_number']);
            $t->same(4, $row['page_object']);
            $t->same('Section 3', $row['page_label']);
            $t->same('Split', $row['target_page_transition']['style']);
            $t->same(0.5, $row['target_page_transition']['duration']);
            $t->same('V', $row['target_page_transition']['dimension']);
            $t->same('I', $row['target_page_transition']['motion']);
            $t->same(270.0, $row['target_page_transition']['direction']);
            $t->same(0.75, $row['target_page_transition']['scale']);
            $t->same(false, $row['target_page_transition']['opaque_background']);
            $t->same(['page_open'], array_column($row['target_page_actions'], 'event_label'));
            $t->same(['review-uri'], array_column($row['target_page_actions'], 'safety'));
            $t->same([false], array_column($row['target_page_actions'], 'executes_on_import'));
        }

        $t->same('PlanTarget', $rows[0]['destination']);
        $t->same('XYZ', $rows[0]['view_mode']);
        $t->same([90.0, 700.0, null], $rows[0]['view_position']);
        $t->same(['left' => 90.0, 'top' => 700.0, 'zoom' => null], $rows[0]['view_parameters']);
        $t->same(null, $rows[1]['destination']);
        $t->same('FitH', $rows[1]['view_mode']);
        $t->same([620.0], $rows[1]['view_position']);
        $t->same(['top' => 620.0], $rows[1]['view_parameters']);

        $t->same(
            [
                ['title' => 'Migration Plan', 'level' => 1, 'page' => 1, 'destination' => 'PlanTarget'],
                ['title' => 'Implementation Detail', 'level' => 2, 'page' => 1, 'destination' => null],
                ['title' => 'Action Plan Target', 'level' => 1, 'page' => 1, 'destination' => 'PlanTarget'],
            ],
            $extractor->getPdfToc($pdf)
        );
    },
    'adds outline structure context to composite navigation rows without leaking dictionary text' => static function (TestRunner $t) use ($outlineStructureDestinationPageContextPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineStructureDestinationPageContextPdf();
        $navigation = $extractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['outline', 'page_presentations'], $navigation['source']);
        $t->same(3, count($navigation['outline']));
        $t->same('collapsed', $navigation['outline'][0]['structure_state']);
        $t->same('#0033cc', $navigation['outline'][0]['text_color_hex']);
        $t->same(true, $navigation['outline'][0]['is_bold']);
        $t->same(true, $navigation['outline'][0]['is_italic']);
        $t->same('Section 3', $navigation['outline'][0]['page_label']);
        $t->same('Split', $navigation['outline'][0]['target_page_transition']['style']);
        $t->same(['review-uri'], array_column($navigation['outline'][0]['target_page_actions'], 'safety'));

        $t->contains('Cover outline structure page remains visible', $plainText);
        $t->contains('Target outline page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Migration Plan'));
        $t->true(!str_contains($plainText, 'Implementation Detail'));
        $t->true(!str_contains($plainText, 'Action Plan Target'));
        $t->true(!str_contains($plainText, 'PlanTarget'));
        $t->true(!str_contains($plainText, 'page-open-outline-structure'));
    },
];
