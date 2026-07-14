<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfReader;

function pandoc_form_xobject_placement_metadata_fixture(): string
{
    $content = "BT /F1 12 Tf 72 720 Td (Before chart) Tj ET\n"
        . "q 120 0 0 50 72 620 cm /Chart Do Q\n"
        . "BT /F1 12 Tf 72 580 Td (After chart) Tj ET";
    $form = "<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Length 0 >>\nstream\n\nendstream";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> /XObject << /Chart 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n{$form}\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF\n";
}

return [
    'retains browser-renderable outer Form placements with surrounding text anchors when explicitly requested' => static function (TestRunner $t): void {
        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => false,
            'pdfCollectFormXObjectPlacements' => true,
        ]))->read(pandoc_form_xobject_placement_metadata_fixture());
        $metadata = $document->attr('meta');
        $placements = $metadata['pdfFormXObjectPlacements'] ?? null;

        $t->true(is_array($placements));
        $t->same(1, count($placements));
        $t->same('pdf-form-p1-n1-o6', $placements[0]['id']);
        $t->same(1, $placements[0]['page']);
        $t->same([72.0, 620.0, 192.0, 670.0], array_values($placements[0]['bbox']));
        $t->same('Before chart', $placements[0]['precedingText']);
        $t->same('After chart', $placements[0]['followingText']);
        $t->same(true, $placements[0]['placementEligible']);
    },
    'does not retain Form placement metadata unless the caller opts in' => static function (TestRunner $t): void {
        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => false,
        ]))->read(pandoc_form_xobject_placement_metadata_fixture());
        $metadata = $document->attr('meta');

        $t->same([], $metadata['pdfFormXObjectPlacements'] ?? null);
    },
];
