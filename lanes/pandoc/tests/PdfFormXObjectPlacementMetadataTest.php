<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfReader;

function pandoc_form_xobject_placement_metadata_fixture(?string $content = null, ?string $formContent = null): string
{
    $content ??= "BT /F1 12 Tf 72 720 Td (Before chart) Tj ET\n"
        . "q 120 0 0 50 72 620 cm /Chart Do Q\n"
        . "BT /F1 12 Tf 72 580 Td (After chart) Tj ET";
    $formContent ??= '';
    $formResources = $formContent === '' ? '' : ' /Resources << /Font << /F1 7 0 R >> >>';
    $form = "<< /Type /XObject /Subtype /Form /BBox [0 0 1 1]{$formResources} /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> /XObject << /Chart 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n{$form}\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF\n";
}

/**
 * @return array<string, mixed>
 */
function pandoc_form_xobject_placement_metadata(string $content, ?string $formContent = null): array
{
    $document = (new PdfReader([
        'pdfGeometryTables' => false,
        'pdfRepairProseText' => false,
        'pdfCollectFormXObjectPlacements' => true,
    ]))->read(pandoc_form_xobject_placement_metadata_fixture($content, $formContent));

    return $document->attr('meta');
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
    'uses surrounding document text when a Form contains chart labels' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before chart) Tj ET\n"
            . "q 120 0 0 50 72 620 cm /Chart Do Q\n"
            // This is fully inside the transformed Form bbox [72,620]-[192,670].
            . "BT /F1 9 Tf 88 642 Td (Internal chart label) Tj ET\n"
            . "BT /F1 12 Tf 72 580 Td (After chart) Tj ET";
        $metadata = pandoc_form_xobject_placement_metadata($content);
        $placements = $metadata['pdfFormXObjectPlacements'] ?? [];

        $t->same(1, count($placements));
        $t->same('Before chart', $placements[0]['precedingText']);
        $t->same('After chart', $placements[0]['followingText']);
    },
    'uses surrounding document text when the Form stream itself contains a chart label' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before chart) Tj ET\n"
            . "q 120 0 0 50 72 620 cm /Chart Do Q\n"
            . "BT /F1 12 Tf 72 580 Td (After chart) Tj ET";
        // The label is painted by the Form, as real chart axis and legend
        // text is, rather than merely sharing its rectangle on the page.
        $metadata = pandoc_form_xobject_placement_metadata(
            $content,
            'BT /F1 0.2 Tf 0.3 0.5 Td (Key) Tj ET'
        );
        $placements = $metadata['pdfFormXObjectPlacements'] ?? [];

        $t->same(1, count($placements));
        $t->same('Before chart', $placements[0]['precedingText']);
        $t->same('After chart', $placements[0]['followingText']);
    },
    'does not use a chart label itself as a document-flow anchor' => static function (TestRunner $t): void {
        $content = "q 120 0 0 50 72 620 cm /Chart Do Q\n"
            . "BT /F1 9 Tf 88 642 Td (Internal chart label) Tj ET";
        $metadata = pandoc_form_xobject_placement_metadata($content);
        $placements = $metadata['pdfFormXObjectPlacements'] ?? [];

        $t->same(1, count($placements));
        $t->same(null, $placements[0]['precedingText']);
        $t->same(null, $placements[0]['followingText']);
    },
    'keeps an external text row crossing a Form edge unsafe' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before chart) Tj ET\n"
            . "q 120 0 0 50 72 620 cm /Chart Do Q\n"
            // Its left edge is inside the Form but the row extends beyond x=192.
            . "BT /F1 9 Tf 180 642 Td (Document text crossing chart edge) Tj ET\n"
            . "BT /F1 12 Tf 72 580 Td (After chart) Tj ET";
        $metadata = pandoc_form_xobject_placement_metadata($content);
        $placements = $metadata['pdfFormXObjectPlacements'] ?? [];

        $t->same(1, count($placements));
        $t->same(null, $placements[0]['precedingText']);
        $t->same(null, $placements[0]['followingText']);
    },
    'keeps text crossing a Form vertical edge unsafe' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before chart) Tj ET\n"
            . "q 120 0 0 50 72 620 cm /Chart Do Q\n"
            // The row crosses the Form's top edge at y=670.
            . "BT /F1 9 Tf 88 667 Td (Flow text across top edge) Tj ET\n"
            . "BT /F1 12 Tf 72 580 Td (After chart) Tj ET";
        $metadata = pandoc_form_xobject_placement_metadata($content);
        $placements = $metadata['pdfFormXObjectPlacements'] ?? [];

        $t->same(1, count($placements));
        $t->same(null, $placements[0]['precedingText']);
        $t->same(null, $placements[0]['followingText']);
    },
];
