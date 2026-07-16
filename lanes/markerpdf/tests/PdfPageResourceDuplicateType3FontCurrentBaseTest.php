<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicateType3FontPdf = static function (): array {
    $charProc = "1000 0 d0\n"
        . "q 12 0 0 8 1 2 cm /GlyphImage Do Q\n"
        . "BT /Fghost 9 Tf (Duplicate Type3 CharProc text leak) Tj ET\n";
    $pageContent = 'BT /Fdup 24 Tf 72 720 Td (A) Tj ET';
    $payload = 'BT /Fghost 9 Tf 0 0 Td (Duplicate Type3 Image Payload Noise) Tj ET';
    $compressed = gzcompress($payload);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress duplicate Type3 CharProc image fixture payload.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "3 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fdup /BaseFont /DuplicateT3 "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding << /Type /Encoding /Differences [65 /A] >> "
        . "/CharProcs << /A 4 0 R >> "
        . "/Resources << /XObject << /GlyphImage 5 0 R >> /Font << /Fghost 2 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Font << /Fdup 2 0 R /Fdup 3 0 R >> >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $payload];
};

return [
    'rejects duplicate inherited Type3 font resource names before CharProc image review' => static function (
        TestRunner $t
    ) use ($pageResourceDuplicateType3FontPdf): void {
        [$pdf, $payload] = $pageResourceDuplicateType3FontPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];

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
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(30, $resources['resource_object'] ?? null);
        $t->same(['Font'], $resources['categories'] ?? null);
        $t->same(false, isset($resources['font_names']));
        $t->same(false, str_contains($plainText, 'Duplicate Type3 CharProc text leak'));
        $t->same(false, str_contains($plainText, 'Duplicate Type3 Image Payload Noise'));
        $t->same(false, str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $payload));
    },
];
