<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfJavaScriptActionInspector;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$javascriptXrefPrevFreedActionPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current xref-free JavaScript guard text) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /OpenAction 8 0 R /Names << /JavaScript 6 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Names [(freed-prev-script) 8 0 R] >>');
    $addObject(8, "<< /S /JavaScript /JS (stalePrevOpenAction\\(\\)) >>");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 9\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[8])
        . "trailer\n<< /Size 9 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "8 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 9 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'suppresses JavaScript actions freed by the current xref Prev chain' => static function (
        TestRunner $t
    ) use ($javascriptXrefPrevFreedActionPdf): void {
        $pdf = $javascriptXrefPrevFreedActionPdf();
        $review = (new PdfJavaScriptActionInspector())->reviewDocumentActions($pdf);
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(true, isset($freeObjects[8]), 'current xref section frees the inherited JavaScript action object');
        $t->same('Current xref-free JavaScript guard text', $plainText);
        $t->same(false, $review['has_javascript']);
        $t->same(false, $review['executes_javascript']);
        $t->same(0, $review['action_count']);
        $t->same([], $review['actions']);
        $t->same(0, $review['chain_safety']['cycle_edges_blocked']);
        $t->same(0, $review['chain_safety']['max_depth_edges_blocked']);
        $t->true(str_contains($pdf, 'stalePrevOpenAction'), 'fixture contains the stale freed JavaScript bytes');
        $t->true(!str_contains($encodedReview, 'stalePrevOpenAction'), 'freed JavaScript action is excluded from safety review');
        $t->true(!str_contains($plainText, 'stalePrevOpenAction'), 'freed JavaScript action is excluded from visible text');
    },
];
