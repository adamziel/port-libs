<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceImageXObjectInheritancePdf = static function (): array {
    $pageOneContent = "BT /F1 12 Tf 72 720 Td (Inherited image resource page) Tj ET\n"
        . "q 24 0 0 12 72 690 cm /Parent#20Logo Do Q";
    $pageTwoContent = "BT /F1 12 Tf 72 720 Td (Leaf local resources only page) Tj ET\n"
        . "q 24 0 0 12 72 690 cm /Parent#20Logo Do Q";
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Parent Logo Image Payload Leak) Tj ET';
    $compressedImagePayload = gzcompress($imagePayload);
    if (!is_string($compressedImagePayload)) {
        throw new RuntimeException('Unable to compress page resource image fixture payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /Parent#20Logo 8 0 R >> >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $imagePayload];
};

return [
    'reports inherited page resource owner on image review rows without backfilling leaf overrides' => static function (TestRunner $t) use ($pageResourceImageXObjectInheritancePdf): void {
        [$pdf, $imagePayload] = $pageResourceImageXObjectInheritancePdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);

        $t->same(2, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same([3], array_column($review['entries'], 'page_object'));

        $entry = $review['entries'][0];
        $t->same('Parent Logo', $entry['resource_name']);
        $t->same(['Parent Logo'], $entry['resource_path']);
        $t->same(8, $entry['object_number']);
        $t->same(true, $entry['invoked']);
        $t->same([[24.0, 0.0, 0.0, 12.0, 72.0, 690.0]], $entry['invocation_matrices']);
        $t->same(true, $entry['page_resource_inherited']);
        $t->same(2, $entry['page_resource_owner_object']);
        $t->same(10, $entry['page_resource_object']);
        $t->same(0, $entry['page_resource_generation']);
        $t->same(true, $entry['page_resource_review_only']);
        $t->same(false, $entry['payload_in_visible_text']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);

        $t->same(true, $boundary[0]['resources']['inherited'] ?? null);
        $t->same(['Parent Logo'], $boundary[0]['resources']['xobject_names'] ?? null);
        $t->same(false, $boundary[1]['resources']['inherited'] ?? null);
        $t->same(['Font'], $boundary[1]['resources']['categories'] ?? null);
        $t->same(null, $boundary[1]['resources']['xobject_names'] ?? null);

        $t->same(['Inherited image resource page', 'Leaf local resources only page'], $extractor->extractTextLines($pdf));
        $t->same("Inherited image resource page\nLeaf local resources only page", $plainText);
        $t->same(false, str_contains($plainText, 'Parent Logo Image Payload Leak'));
    },
];
