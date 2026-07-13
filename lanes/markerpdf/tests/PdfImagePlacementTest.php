<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_placement_image_object(bool $mask = false): string
{
    $payload = 'x';

    return '<< /Type /XObject /Subtype /Image /Width 10 /Height 10'
        . ($mask ? ' /ImageMask true /BitsPerComponent 1' : ' /ColorSpace /DeviceRGB /BitsPerComponent 8')
        . ' /Length ' . strlen($payload) . " >>\nstream\n{$payload}\nendstream";
}

/**
 * @param array<int, string> $extraObjects
 */
function markerpdf_image_placement_single_page_pdf(string $content, string $xObjects, array $extraObjects): string
{
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /XObject << {$xObjects} >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    foreach ($extraObjects as $number => $body) {
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "%%EOF\n";
}

return [
    'reports only direct painted images in page paint order with transformed bounds' => static function (TestRunner $t): void {
        $pdf = markerpdf_image_placement_single_page_pdf(
            'q 20 0 0 10 12 24 cm /A Do Q q 8 0 0 6 48 60 cm /B Do Q',
            '/A 5 0 R /B 6 0 R /Unused 7 0 R',
            [
                5 => markerpdf_image_placement_image_object(),
                6 => markerpdf_image_placement_image_object(),
                7 => markerpdf_image_placement_image_object(),
            ]
        );

        $placements = (new PdfTextExtractor())->extractImagePlacements($pdf);

        $t->same([5, 6], array_column($placements, 'object'));
        $t->same([1, 2], array_column($placements, 'paintOrder'));
        $t->same([12.0, 24.0, 32.0, 34.0], array_values($placements[0]['bbox']));
        $t->same([48.0, 60.0, 56.0, 66.0], array_values($placements[1]['bbox']));
        $t->same(['high', 'high'], array_column($placements, 'confidence'));
    },
    'restores graphics state and retains repeated legitimate image paintings' => static function (TestRunner $t): void {
        $pdf = markerpdf_image_placement_single_page_pdf(
            'q 10 0 0 5 3 4 cm /A Do Q q 30 0 0 15 40 50 cm /A Do Q',
            '/A 5 0 R',
            [5 => markerpdf_image_placement_image_object()]
        );

        $placements = (new PdfTextExtractor())->extractImagePlacements($pdf);

        $t->same(2, count($placements));
        $t->same([1, 2], array_column($placements, 'paintOrder'));
        $t->same([3.0, 4.0, 13.0, 9.0], array_values($placements[0]['bbox']));
        $t->same([40.0, 50.0, 70.0, 65.0], array_values($placements[1]['bbox']));
    },
    'omits masks zero-area transforms and invalid text-object paintings' => static function (TestRunner $t): void {
        $pdf = markerpdf_image_placement_single_page_pdf(
            'BT /A Do ET q 0 0 0 10 2 3 cm /A Do Q q 8 0 0 8 10 10 cm /Mask Do Q q 12 0 0 6 20 30 cm /A Do Q',
            '/A 5 0 R /Mask 6 0 R',
            [
                5 => markerpdf_image_placement_image_object(),
                6 => markerpdf_image_placement_image_object(true),
            ]
        );

        $placements = (new PdfTextExtractor())->extractImagePlacements($pdf);

        $t->same(1, count($placements));
        $t->same(5, $placements[0]['object']);
        $t->same([20.0, 30.0, 32.0, 36.0], array_values($placements[0]['bbox']));
    },
    'marks placements with unmodeled clipping as low confidence' => static function (TestRunner $t): void {
        $pdf = markerpdf_image_placement_single_page_pdf(
            '0 0 m 20 20 l W n q 10 0 0 10 0 0 cm /A Do Q',
            '/A 5 0 R',
            [5 => markerpdf_image_placement_image_object()]
        );

        $placements = (new PdfTextExtractor())->extractImagePlacements($pdf);

        $t->same(1, count($placements));
        $t->same('low', $placements[0]['confidence']);
        $t->same([0.0, 0.0, 10.0, 10.0], array_values($placements[0]['bbox']));
    },
    'keeps off-page and artifact image paintings out of automatic placement' => static function (TestRunner $t): void {
        $pdf = markerpdf_image_placement_single_page_pdf(
            '/Artifact BMC q 30 0 0 20 72 700 cm /A Do Q EMC q 100 0 0 100 72 900 cm /B Do Q',
            '/A 5 0 R /B 6 0 R',
            [
                5 => markerpdf_image_placement_image_object(),
                6 => markerpdf_image_placement_image_object(),
            ]
        );

        $placements = (new PdfTextExtractor())->extractImagePlacements($pdf);

        $t->same(2, count($placements));
        $t->same([true, false], array_column($placements, 'visible'));
        $t->same(['low', 'low'], array_column($placements, 'confidence'));
    },
    'preserves graphics state across a page Contents array' => static function (TestRunner $t): void {
        $first = 'q 100 0 0 30 72 660 cm';
        $second = '/A Do Q';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /XObject << /A 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($first) . " >>\nstream\n{$first}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($second) . " >>\nstream\n{$second}\nendstream\nendobj\n"
            . "6 0 obj\n" . markerpdf_image_placement_image_object() . "\nendobj\n%%EOF\n";

        $placements = (new PdfTextExtractor())->extractImagePlacements($pdf);

        $t->same(1, count($placements));
        $t->same([72.0, 660.0, 172.0, 690.0], array_values($placements[0]['bbox']));
        $t->same('high', $placements[0]['confidence']);
    },
    'scans nested Form resources with their combined matrix as review-only placement' => static function (TestRunner $t): void {
        $formContent = 'q 1 0 0 1 0 0 cm /A Do Q';
        $form = '<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Matrix [2 0 0 3 10 20] /Resources << /XObject << /A 6 0 R >> >> /Length '
            . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream";
        $pdf = markerpdf_image_placement_single_page_pdf(
            'q 4 0 0 5 100 200 cm /F Do Q',
            '/F 5 0 R',
            [
                5 => $form,
                6 => markerpdf_image_placement_image_object(),
            ]
        );

        $placements = (new PdfTextExtractor())->extractImagePlacements($pdf);

        $t->same(1, count($placements));
        $t->same(6, $placements[0]['object']);
        $t->same(['F', 'A'], $placements[0]['resourcePath']);
        $t->same('low', $placements[0]['confidence']);
        $t->same([140.0, 300.0, 148.0, 315.0], array_values($placements[0]['bbox']));
    },
];
