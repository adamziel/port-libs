<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'keeps OPI numeric arrays top-level and rejects tailed operands before media review' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 740 Td (WordPress OPI array intro) Tj ET '
            . 'q 24 0 0 12 72 690 cm /Array#20Proxy#20Image Do Q '
            . 'BT /F1 12 Tf 72 660 Td (WordPress OPI array outro) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (OPI array image payload noise) Tj ET';

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Array#20Proxy#20Image 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /OPI 7 0 R /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /OPI /Version 1.3 /F (current-opi-array.tif) /ImageType /proxy /Private << /CropRect [999 999 1000 1000] /Position [8 8 9 9] /F (private-opi-array-decoy.tif) >> /IncludedImageDimensions [640 480] /CropRect [10 20 300 240] /Position [0 0 24 0 24 12 0 12] 99 0 R /Resolution 8 0 R /Overprint true >>\nendobj\n"
            . "8 0 obj\n[300 300] 99 0 R\nendobj\n"
            . "xref\n0 9\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $opi = is_array($entry['opi_proxy_review'] ?? null) ? $entry['opi_proxy_review'] : [];
        $opiEntry = is_array($opi['entries'][0] ?? null) ? $opi['entries'][0] : [];
        $encodedReview = json_encode($review, JSON_THROW_ON_ERROR);

        $t->same("WordPress OPI array intro\nWordPress OPI array outro", $plainText);
        $t->true(!str_contains($plainText, 'OPI array image payload noise'));
        $t->true(!str_contains($plainText, 'current-opi-array.tif'));
        $t->true(!str_contains($plainText, 'private-opi-array-decoy.tif'));
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same('Array Proxy Image', $entry['resource_name'] ?? null);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($encodedReview, 'OPI array image payload noise'));
        $t->true(!str_contains($encodedReview, 'private-opi-array-decoy.tif'));

        $t->same(true, $opi['present'] ?? null);
        $t->same(true, $opi['resolved'] ?? null);
        $t->same(1, $opi['entry_count'] ?? null);
        $t->same([], $opi['unresolved_versions'] ?? null);

        $t->same('1.3', $opiEntry['version'] ?? null);
        $t->same('current-opi-array.tif', $opiEntry['file_specification'] ?? null);
        $t->same([640.0, 480.0], $opiEntry['included_image_dimensions'] ?? null);
        $t->same([10.0, 20.0, 300.0, 240.0], $opiEntry['crop_rect'] ?? null);
        $t->same(null, $opiEntry['position'] ?? null);
        $t->same(null, $opiEntry['resolution'] ?? null);

        $t->same('Position', $opiEntry['position_boundary']['name'] ?? null);
        $t->same('trailing_top_level_operand', $opiEntry['position_boundary']['reason'] ?? null);
        $t->same(false, $opiEntry['position_boundary']['valid_numeric_array'] ?? null);
        $t->same(true, $opiEntry['position_boundary']['review_only'] ?? null);

        $t->same('Resolution', $opiEntry['resolution_boundary']['name'] ?? null);
        $t->same('trailing_indirect_array_operand', $opiEntry['resolution_boundary']['reason'] ?? null);
        $t->same(8, $opiEntry['resolution_boundary']['object_number'] ?? null);
        $t->same(0, $opiEntry['resolution_boundary']['generation'] ?? null);
        $t->same(false, $opiEntry['resolution_boundary']['valid_numeric_array'] ?? null);
        $t->same(true, $opiEntry['resolution_boundary']['review_only'] ?? null);
    },
];
