<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

$markerAppPreviewCommentedStartxrefPdf = static function (): string {
    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /PageLabels << /Nums [0 << /P (Current-) /S /D /St 5 >>] >> >>');
    $addObject(2, '<< /Type /Pages /MediaBox [0 0 400 600] /CropBox [20 30 380 540] /Rotate 90 /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /UserUnit 2 >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 4\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . "trailer\n<< /Size 40 /Root 1 0 R >>\n"
        . "startxref\n999999\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /PageLabels << /Nums [0 << /P (Decoy-) /S /D /St 99 >>] >> >>');
    $addObject(21, '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [22 0 R 23 0 R] /Count 2 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /CropBox [0 0 200 200] >>');
    $addObject(23, '<< /Type /Page /Parent 21 0 R /CropBox [0 0 300 300] >>');

    $decoyXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 4\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . "trailer\n<< /Size 40 /Root 20 0 R >>\n"
        . "% startxref\n{$decoyXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps marker app preview on current classic xref when later startxref is commented' => static function (
        TestRunner $t
    ) use ($markerAppPreviewCommentedStartxrefPdf): void {
        $preview = new MarkerAppPreview();
        $pdf = $markerAppPreviewCommentedStartxrefPdf();

        $summary = $preview->openPdfSummary($pdf);
        $plan = $preview->getPageImagePlan($pdf, 1, 72.0);

        $t->same(1, $summary['page_count']);
        $t->same([3], array_column($summary['pages'], 'object_id'));
        $t->same(['Current-5'], array_column($summary['pages'], 'page_label'));
        $t->same([20.0, 30.0, 380.0, 540.0], $summary['pages'][0]['bbox']);
        $t->same('crop_box', $summary['pages'][0]['bbox_source']);
        $t->same(90, $summary['pages'][0]['rotation']);
        $t->same(2.0, $summary['pages'][0]['user_unit']);
        $t->same('Current-5', $plan['page_label']);
        $t->same(['width' => 510.0, 'height' => 360.0], $plan['display_page_size']);
        $t->same(['width' => 1020.0, 'height' => 720.0], $plan['physical_page_size']);
        $t->same(['width' => 1020, 'height' => 720], $plan['rendered_image_size']);
        $t->same(1, $plan['page_count']);
    },
];
