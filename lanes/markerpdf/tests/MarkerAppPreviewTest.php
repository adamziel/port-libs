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

return [
    'counts pdf pages through the upstream marker app open pdf boundary' => static function (TestRunner $t) use ($pdfWithPagesTree): void {
        $preview = new MarkerAppPreview();

        $summary = $preview->openPdfSummary($pdfWithPagesTree());

        $t->same(2, $summary['page_count']);
        $t->same(2, $preview->pageCount($pdfWithPagesTree()));
        $t->same([1, 2], array_column($summary['pages'], 'page_number'));
        $t->same([4, 3], array_column($summary['pages'], 'object_id'));
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
