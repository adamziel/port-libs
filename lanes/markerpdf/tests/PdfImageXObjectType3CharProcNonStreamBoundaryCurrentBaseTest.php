<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcNonStreamImageBoundaryPdf = static function (): array {
    $charProc = "<< /Fake /Dictionary /Length 99 >>\n"
        . "q 12 0 0 8 1 2 cm /Glyph#20Image Do Q\n"
        . "BT /Fghost 9 Tf (non-stream Type3 review leak) Tj ET\n";
    $pageContent = 'BT /Ft3 24 Tf 72 720 Td (A) Tj ET';
    $glyphPayload = 'BT /Fghost 9 Tf 0 0 Td (Non-stream Type3 image payload noise) Tj ET';
    $glyphCompressed = gzcompress($glyphPayload);
    if (!is_string($glyphCompressed)) {
        throw new RuntimeException('Unable to compress non-stream Type3 image review fixture payload.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 >>\nendobj\n"
        . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3NonStreamImageBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding << /Type /Encoding /Differences [65 /A] >> "
        . "/CharProcs << /A 3 0 R >> "
        . "/Resources << /XObject << /Glyph#20Image 5 0 R >> /Font << /Fghost 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n{$charProc}\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray "
        . "/BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($glyphCompressed) . " >>\n"
        . "stream\n{$glyphCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

    return [$pdf, $glyphPayload];
};

return [
    'rejects non-stream Type3 CharProc objects before Image XObject review on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcNonStreamImageBoundaryPdf): void {
        [$pdf, $glyphPayload] = $type3CharProcNonStreamImageBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['page_count']);
        $t->same(0, $review['image_xobject_count']);
        $t->same(0, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same([], $review['entries']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same('A', $plainText);
        $t->true(!str_contains($plainText, 'non-stream Type3 review leak'));
        $t->true(!str_contains($plainText, 'Non-stream Type3 image payload noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Glyph Image'));
        $t->true(!str_contains($encoded, $glyphPayload));
    },
];
