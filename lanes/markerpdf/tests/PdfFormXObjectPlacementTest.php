<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_form_placement_form(string $bbox, string $content = '', ?string $matrix = null, string $resources = ''): string
{
    $matrixPart = $matrix === null ? '' : ' /Matrix [' . $matrix . ']';

    return '<< /Type /XObject /Subtype /Form /BBox [' . $bbox . ']' . $matrixPart
        . ($resources === '' ? '' : ' /Resources << ' . $resources . ' >>')
        . ' /Length ' . strlen($content) . " >>\nstream\n{$content}\nendstream";
}

/**
 * @param array<int, string> $extraObjects
 */
function markerpdf_form_placement_single_page_pdf(string $content, string $xObjects, array $extraObjects): string
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
    'reports direct Form XObject crops in PDF page coordinates with a deterministic request id' => static function (TestRunner $t): void {
        $form = markerpdf_form_placement_form('0 0 20 10', '0 0 m 20 10 l S', '2 0 0 3 10 20');
        $pdf = markerpdf_form_placement_single_page_pdf(
            'q 4 0 0 5 100 200 cm /Figure Do Q',
            '/Figure 5 0 R',
            [5 => $form]
        );

        $placements = (new PdfTextExtractor())->extractFormXObjectPlacements($pdf);

        $t->same(1, count($placements));
        $t->same('pdf-form-p1-n1-o5', $placements[0]['id']);
        $t->same(1, $placements[0]['page']);
        $t->same(3, $placements[0]['pageObject']);
        $t->same(5, $placements[0]['object']);
        $t->same('Figure', $placements[0]['resource']);
        $t->same(['Figure'], $placements[0]['resourcePath']);
        $t->same([8.0, 0.0, 0.0, 15.0, 140.0, 300.0], $placements[0]['matrix']);
        $t->same([0.0, 0.0, 20.0, 10.0], array_values($placements[0]['formBBox']));
        $t->same([140.0, 300.0, 300.0, 450.0], array_values($placements[0]['bbox']));
        $t->same(true, $placements[0]['visible']);
        $t->same(true, $placements[0]['placementEligible']);
        $t->same('high', $placements[0]['confidence']);
    },
    'keeps repeated outer Form paints distinct and does not turn nested Forms into separate crops' => static function (TestRunner $t): void {
        $nested = markerpdf_form_placement_form('0 0 8 8');
        $outer = markerpdf_form_placement_form(
            '0 0 10 5',
            'q 1 0 0 1 0 0 cm /Nested Do Q',
            null,
            '/XObject << /Nested 6 0 R >>'
        );
        $pdf = markerpdf_form_placement_single_page_pdf(
            'q 10 0 0 20 30 40 cm /Outer Do Q q 4 0 0 6 100 200 cm /Outer Do Q',
            '/Outer 5 0 R',
            [5 => $outer, 6 => $nested]
        );

        $placements = (new PdfTextExtractor())->extractFormXObjectPlacements($pdf);

        $t->same(2, count($placements));
        $t->same(['pdf-form-p1-n1-o5', 'pdf-form-p1-n2-o5'], array_column($placements, 'id'));
        $t->same([1, 2], array_column($placements, 'paintOrder'));
        $t->same([5, 5], array_column($placements, 'object'));
        $t->same([['Outer'], ['Outer']], array_column($placements, 'resourcePath'));
        $t->same([30.0, 40.0, 130.0, 140.0], array_values($placements[0]['bbox']));
        $t->same([100.0, 200.0, 140.0, 230.0], array_values($placements[1]['bbox']));
    },
    'reports non-placeable artifact and off-page Form paints for caller review without treating them as crop candidates' => static function (TestRunner $t): void {
        $form = markerpdf_form_placement_form('0 0 10 10');
        $pdf = markerpdf_form_placement_single_page_pdf(
            '/Artifact BMC q 10 0 0 10 72 700 cm /ArtifactFigure Do Q EMC q 10 0 0 10 72 900 cm /OffPage Do Q',
            '/ArtifactFigure 5 0 R /OffPage 6 0 R',
            [5 => $form, 6 => $form]
        );

        $placements = (new PdfTextExtractor())->extractFormXObjectPlacements($pdf);

        $t->same(2, count($placements));
        $t->same([true, false], array_column($placements, 'visible'));
        $t->same([false, false], array_column($placements, 'placementEligible'));
        $t->same(['low', 'low'], array_column($placements, 'confidence'));
    },
    'uses the enclosing page graphics state across Contents-array streams and preserves unrotated PDF coordinates' => static function (TestRunner $t): void {
        $first = 'q 0 1 -1 0 100 200 cm';
        $second = '/Chart Do Q';
        $form = markerpdf_form_placement_form('0 0 20 10');
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /XObject << /Chart 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($first) . " >>\nstream\n{$first}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($second) . " >>\nstream\n{$second}\nendstream\nendobj\n"
            . "6 0 obj\n{$form}\nendobj\n%%EOF\n";

        $placements = (new PdfTextExtractor())->extractFormXObjectPlacements($pdf);

        $t->same(1, count($placements));
        $t->same([0.0, 1.0, -1.0, 0.0, 100.0, 200.0], $placements[0]['matrix']);
        $t->same([90.0, 200.0, 100.0, 220.0], array_values($placements[0]['bbox']));
    },
];
