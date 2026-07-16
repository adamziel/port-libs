<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

$pdfWithPagesTree = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [4 0 R 3 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$pdfWithPageLabels = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R] /Count 5 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Kids [21 0 R 22 0 R] /Limits [1 8] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [1 2] /Nums [1 << /P (front-) /S /r /St 2 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [3 8] /Nums [3 << /P (Body\\040) /S /D /St 7 >> 4 << /P <466f6c646f757420> >> 8 << /P (stale-) /S /D /St 99 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'counts pdf pages through the upstream marker app open pdf boundary' => static function (TestRunner $t) use ($pdfWithPagesTree): void {
        $preview = new MarkerAppPreview();

        $summary = $preview->openPdfSummary($pdfWithPagesTree());

        $t->same(2, $summary['page_count']);
        $t->same(2, $preview->pageCount($pdfWithPagesTree()));
        $t->same([1, 2], array_column($summary['pages'], 'page_number'));
        $t->same([4, 3], array_column($summary['pages'], 'object_id'));
    },
    'resolves catalog page labels number tree onto preview page boundaries' => static function (TestRunner $t) use ($pdfWithPageLabels): void {
        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdfWithPageLabels());

        $t->same(['1', 'front-ii', 'front-iii', 'Body 7', 'Foldout '], $preview->pageLabels($pdfWithPageLabels()));
        $t->same(['1', 'front-ii', 'front-iii', 'Body 7', 'Foldout '], array_column($summary['pages'], 'page_label'));
        $t->same('Body 7', $preview->getPageImagePlan($pdfWithPageLabels(), 4)['page_label']);
    },
    'honors page label number tree limits before preview image page boundaries' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "20 0 obj\n<< /Kids [21 0 R 22 0 R] >>\nendobj\n"
            . "21 0 obj\n<< /Limits [0 1] /Nums [0 << /P (front-) /S /r /St 2 >> 2 << /P (stale-) /S /D /St 99 >>] >>\nendobj\n"
            . "22 0 obj\n<< /Limits [2 2] /Nums [1 << /P (wrong-) /S /D /St 40 >> 2 << /P (Body ) /S /D /St 7 >>] >>\nendobj\n"
            . "%%EOF\n";

        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);

        $t->same(['front-ii', 'front-iii', 'Body 7'], $preview->pageLabels($pdf));
        $t->same(['front-ii', 'front-iii', 'Body 7'], array_column($summary['pages'], 'page_label'));
        $t->same('front-iii', $preview->getPageImagePlan($pdf, 2)['page_label']);
        $t->same('Body 7', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
    'walks indirect page label kids arrays before preview image page boundaries' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "20 0 obj\n<< /Kids 30 0 R >>\nendobj\n"
            . "30 0 obj\n[21 0 R 22 0 R]\nendobj\n"
            . "21 0 obj\n<< /Limits [0 0] /Nums [0 << /P (front-) /S /r /St 4 >>] >>\nendobj\n"
            . "22 0 obj\n<< /Limits [1 1] /Nums [1 << /P (Body ) /S /D /St 9 >>] >>\nendobj\n"
            . "%%EOF\n";

        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);

        $t->same(['front-iv', 'Body 9'], $preview->pageLabels($pdf));
        $t->same(['front-iv', 'Body 9'], array_column($summary['pages'], 'page_label'));
        $t->same('Body 9', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
    'formats direct page label dictionaries with alphabetic roman and prefix-only sections' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels << /Nums [0 << /P (App-) /S /A /St 26 >> 2 << /P (Part /S ) /S /R /St 4 >> 4 << /P (foldout) >>] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R] /Count 5 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "%%EOF\n";

        $labels = (new MarkerAppPreview())->pageLabels($pdf);

        $t->same(['App-Z', 'App-AA', 'Part /S IV', 'Part /S V', 'foldout'], $labels);
    },
    'uses pages tree order inherited media boxes and direct page media boxes' => static function (TestRunner $t) use ($pdfWithPagesTree): void {
        $summary = (new MarkerAppPreview())->openPdfSummary($pdfWithPagesTree());

        $t->same([0.0, 0.0, 612.0, 792.0], $summary['pages'][0]['bbox']);
        $t->same('pages', $summary['pages'][0]['bbox_source']);
        $t->same([0.0, 0.0, 300.0, 400.0], $summary['pages'][1]['bbox']);
        $t->same('page', $summary['pages'][1]['bbox_source']);
    },
    'walks indirect Kids arrays for preview page count order and inherited boxes' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids 30 0 R /Count 99 >>\nendobj\n"
            . "30 0 obj\n[20 0 R 10 0 R]\nendobj\n"
            . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 77 /MediaBox [0 0 300 400] >>\nendobj\n"
            . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids 31 0 R /Count 88 /MediaBox [0 0 612 792] >>\nendobj\n"
            . "31 0 obj\n[8 0 R]\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 10 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /Page /Parent 20 0 R >>\nendobj\n"
            . "%%EOF\n";
        $summary = (new MarkerAppPreview())->openPdfSummary($pdf);

        $t->same(2, $summary['page_count']);
        $t->same([8, 3], array_column($summary['pages'], 'object_id'));
        $t->same([1, 2], array_column($summary['pages'], 'page_number'));
        $t->same([[0.0, 0.0, 612.0, 792.0], [0.0, 0.0, 300.0, 400.0]], array_column($summary['pages'], 'bbox'));
        $t->same(['pages', 'pages'], array_column($summary['pages'], 'bbox_source'));
    },
    'guards cyclic Kids arrays and duplicate leaves for preview page inventory' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [10 0 R 2 0 R 10 0 R 20 0 R] /Count 99 >>\nendobj\n"
            . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R 10 0 R 3 0 R] /Count 77 /MediaBox [0 0 300 400] >>\nendobj\n"
            . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [8 0 R 20 0 R] /Count 88 /MediaBox [0 0 500 600] >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 10 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /Page /Parent 20 0 R >>\nendobj\n"
            . "%%EOF\n";
        $summary = (new MarkerAppPreview())->openPdfSummary($pdf);

        $t->same(2, $summary['page_count']);
        $t->same([3, 8], array_column($summary['pages'], 'object_id'));
        $t->same([1, 2], array_column($summary['pages'], 'page_number'));
        $t->same([[0.0, 0.0, 300.0, 400.0], [0.0, 0.0, 500.0, 600.0]], array_column($summary['pages'], 'bbox'));
        $t->same(['pages', 'pages'], array_column($summary['pages'], 'bbox_source'));
    },
    'tracks inherited crop boxes rotation and page UserUnit for preview geometry' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.6\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 500 700] /CropBox [36 40 436 580] /Rotate 90 /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /UserUnit 2 /BleedBox [40 44 430 570] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 500] /CropBox 5 0 R /Rotate 180 /UserUnit -3 /TrimBox [20 30 240 390] /ArtBox 6 0 R >>\nendobj\n"
            . "5 0 obj\n[10 20 260 420]\nendobj\n"
            . "6 0 obj\n[30 40 230 360]\nendobj\n"
            . "%%EOF\n";

        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);
        $first = $summary['pages'][0];
        $second = $summary['pages'][1];
        $plan = $preview->getPageImagePlan($pdf, 1, 72.0);

        $t->same([36.0, 40.0, 436.0, 580.0], $first['bbox']);
        $t->same('crop_box', $first['bbox_source']);
        $t->same('pages', $first['media_box_source']);
        $t->same('pages', $first['crop_box_source']);
        $t->same(90, $first['rotation']);
        $t->same('pages', $first['rotation_source']);
        $t->same(2.0, $first['user_unit']);
        $t->same('page', $first['user_unit_source']);
        $t->same([40.0, 44.0, 430.0, 570.0], $first['bleed_box']);
        $t->same('page', $first['bleed_box_source']);
        $t->same(['width' => 540.0, 'height' => 400.0], $plan['display_page_size']);
        $t->same(['width' => 1080.0, 'height' => 800.0], $plan['physical_page_size']);
        $t->same(['width' => 1080, 'height' => 800], $plan['rendered_image_size']);

        $t->same([10.0, 20.0, 260.0, 420.0], $second['bbox']);
        $t->same([20.0, 30.0, 240.0, 390.0], $second['trim_box']);
        $t->same([30.0, 40.0, 230.0, 360.0], $second['art_box']);
        $t->same(180, $second['rotation']);
        $t->same(1.0, $second['user_unit']);
        $t->same('default', $second['user_unit_source']);
    },
    'normalizes indirect geometry values before WordPress preview sizing' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.6\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox 5 0 R /Rotate 6 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [260 420 10 20] /UserUnit 7 0 R >>\nendobj\n"
            . "5 0 obj\n[300 500 0 0]\nendobj\n"
            . "6 0 obj\n-90\nendobj\n"
            . "7 0 obj\n1.5\nendobj\n"
            . "%%EOF\n";

        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);
        $plan = $preview->getPageImagePlan($pdf, 1, 72.0);

        $t->same([0.0, 0.0, 300.0, 500.0], $summary['pages'][0]['media_box']);
        $t->same([10.0, 20.0, 260.0, 420.0], $summary['pages'][0]['crop_box']);
        $t->same([10.0, 20.0, 260.0, 420.0], $summary['pages'][0]['bbox']);
        $t->same(270, $summary['pages'][0]['rotation']);
        $t->same(1.5, $summary['pages'][0]['user_unit']);
        $t->same(['width' => 400.0, 'height' => 250.0], $plan['display_page_size']);
        $t->same(['width' => 600, 'height' => 375], $plan['rendered_image_size']);
    },
    'keeps inherited page boxes through invalid page rotation and page-local UserUnit rules' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.6\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 200 400] /CropBox 5 0 R /Rotate 90 /UserUnit 5 /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Rotate 45 >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [20 30 180 370] /UserUnit 2 >>\nendobj\n"
            . "5 0 obj\n[10 20 190 380]\nendobj\n"
            . "%%EOF\n";

        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);
        $first = $summary['pages'][0];
        $second = $summary['pages'][1];
        $secondPlan = $preview->getPageImagePlan($pdf, 2, 72.0);

        $t->same([10.0, 20.0, 190.0, 380.0], $first['crop_box']);
        $t->same('pages', $first['crop_box_source']);
        $t->same(90, $first['rotation']);
        $t->same('pages', $first['rotation_source']);
        $t->same(1.0, $first['user_unit']);
        $t->same('default', $first['user_unit_source']);

        $t->same([20.0, 30.0, 180.0, 370.0], $second['bbox']);
        $t->same(90, $second['rotation']);
        $t->same(2.0, $second['user_unit']);
        $t->same(['width' => 340.0, 'height' => 160.0], $secondPlan['display_page_size']);
        $t->same(['width' => 680.0, 'height' => 320.0], $secondPlan['physical_page_size']);
        $t->same(['width' => 680, 'height' => 320], $secondPlan['rendered_image_size']);
    },
    'resolves indirect numeric page box operands before rotated UserUnit preview sizing' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.6\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 400 600] /Rotate 8 0 R /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [4 0 R 5 0 R 6 0 R 7 0 R] /TrimBox [10 0 R 11 0 R 12 0 R 13 0 R] /UserUnit 9 0 R >>\nendobj\n"
            . "4 0 obj\n25\nendobj\n"
            . "5 0 obj\n35\nendobj\n"
            . "6 0 obj\n325\nendobj\n"
            . "7 0 obj\n435\nendobj\n"
            . "8 0 obj\n-90\nendobj\n"
            . "9 0 obj\n1.5\nendobj\n"
            . "10 0 obj\n30\nendobj\n"
            . "11 0 obj\n45\nendobj\n"
            . "12 0 obj\n315\nendobj\n"
            . "13 0 obj\n425\nendobj\n"
            . "%%EOF\n";

        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);
        $page = $summary['pages'][0];
        $plan = $preview->getPageImagePlan($pdf, 1, 72.0);

        $t->same([25.0, 35.0, 325.0, 435.0], $page['crop_box']);
        $t->same([25.0, 35.0, 325.0, 435.0], $page['bbox']);
        $t->same([30.0, 45.0, 315.0, 425.0], $page['trim_box']);
        $t->same(270, $page['rotation']);
        $t->same('pages', $page['rotation_source']);
        $t->same(1.5, $page['user_unit']);
        $t->same(['width' => 400.0, 'height' => 300.0], $plan['display_page_size']);
        $t->same(['width' => 600.0, 'height' => 450.0], $plan['physical_page_size']);
        $t->same(['width' => 600, 'height' => 450], $plan['rendered_image_size']);
    },
    'clips marker app preview crop boxes to media boundaries before rotation and UserUnit sizing' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.6\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 400 300] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [-50 20 500 260] /Rotate 90 /UserUnit 2.5 >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [450 350 600 500] /Rotate 270 /UserUnit 3 >>\nendobj\n"
            . "%%EOF\n";

        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);
        $first = $summary['pages'][0];
        $second = $summary['pages'][1];
        $firstPlan = $preview->getPageImagePlan($pdf, 1, 72.0);
        $secondPlan = $preview->getPageImagePlan($pdf, 2, 72.0);

        $t->same([-50.0, 20.0, 500.0, 260.0], $first['crop_box']);
        $t->same([0.0, 20.0, 400.0, 260.0], $first['bbox']);
        $t->same([0.0, 20.0, 400.0, 260.0], $first['effective_crop_box']);
        $t->same('crop_box_clipped_to_media_box', $first['effective_crop_box_source']);
        $t->same(true, $first['crop_box_clipped_to_media']);
        $t->same(true, $first['crop_box_intersects_media']);
        $t->same(false, $first['preview_zero_area']);
        $t->same(true, $first['rotation_swaps_axes']);
        $t->same(true, $first['user_unit_applied_to_preview']);
        $t->same(['crop_box_clipped_to_media_box', 'rotation_swaps_display_axes', 'user_unit_scales_rendered_preview'], $first['boundary_notes']);
        $t->same(['width' => 240.0, 'height' => 400.0], $firstPlan['display_page_size']);
        $t->same(['width' => 600.0, 'height' => 1000.0], $firstPlan['physical_page_size']);
        $t->same(['width' => 600, 'height' => 1000], $firstPlan['rendered_image_size']);

        $t->same([450.0, 350.0, 600.0, 500.0], $second['crop_box']);
        $t->same([400.0, 300.0, 400.0, 300.0], $second['bbox']);
        $t->same([400.0, 300.0, 400.0, 300.0], $secondPlan['page_bbox']);
        $t->same(false, $second['crop_box_intersects_media']);
        $t->same(true, $second['preview_zero_area']);
        $t->same(['width' => 0.0, 'height' => 0.0], $secondPlan['display_page_size']);
        $t->same(['width' => 0, 'height' => 0], $secondPlan['rendered_image_size']);
        $t->same(
            ['crop_box_clipped_to_media_box', 'zero_area_preview_box', 'rotation_swaps_display_axes', 'user_unit_scales_rendered_preview'],
            $secondPlan['boundary_notes']
        );
    },
    'plans marker app get_page_image pypdfium page index scale annotations and rgb output' => static function (TestRunner $t) use ($pdfWithPagesTree): void {
        $plan = (new MarkerAppPreview())->getPageImagePlan($pdfWithPagesTree(), 2, 144.0);

        $t->same(2, $plan['page_number']);
        $t->same(1, $plan['page_index']);
        $t->same([1], $plan['pypdfium_page_indices']);
        $t->same(2.0, $plan['scale']);
        $t->same('pypdfium-default', $plan['annotation_mode']);
        $t->same('RGB', $plan['color_mode']);
        $t->same(['width' => 600, 'height' => 800], $plan['rendered_image_size']);
    },
    'renders a WordPress upload preview payload without rasterizing through pypdfium' => static function (TestRunner $t) use ($pdfWithPagesTree): void {
        $plan = (new MarkerAppPreview())->getPageImagePlan($pdfWithPagesTree(), 1, 96.0);

        $payload = [
            'preview_page' => $plan['page_number'],
            'page_count' => $plan['page_count'],
            'rendered_size' => $plan['rendered_image_size'],
            'pypdfium_page_indices' => $plan['pypdfium_page_indices'],
            'annotation_mode' => $plan['annotation_mode'],
        ];

        $html = "<!-- wp:html -->\n";
        $html .= '<div data-marker-preview=\'' . json_encode($payload, JSON_THROW_ON_ERROR) . '\'></div>' . "\n";
        $html .= "<!-- /wp:html -->\n";

        $t->contains('"page_count":2', $html);
        $t->contains('"pypdfium_page_indices":[0]', $html);
        $t->contains('"width":816', $html);
    },
    'rejects invalid uploads page numbers and dpi before preview planning' => static function (TestRunner $t) use ($pdfWithPagesTree): void {
        $preview = new MarkerAppPreview();

        $t->throws(InvalidArgumentException::class, static fn (): int => $preview->pageCount('not a pdf'));
        $t->throws(InvalidArgumentException::class, static fn (): array => $preview->getPageImagePlan($pdfWithPagesTree(), 3));
        $t->throws(InvalidArgumentException::class, static fn (): array => $preview->getPageImagePlan($pdfWithPagesTree(), 1, 0.0));
    },
];
